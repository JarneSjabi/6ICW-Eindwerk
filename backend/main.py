from flask import Flask, request, jsonify
from flask_cors import CORS
from datetime import datetime, timedelta
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from database import Database
from models import Vehicle, VehicleTemplate, Ride, Route, User, VehicleLocationReport
from models.Ride import _serialize_ts as serialize_ride_ts
from services import RouteService
from services.vehicle_simulator import VehicleSimulator
from services.ev_charger_service import EVChargerService
from algorithms import assign_vehicle_to_ride, calculate_ride_price, calculate_distance

app = Flask(__name__)
CORS(app)

db = Database()
route_service = RouteService()
vehicle_simulator = VehicleSimulator(db, update_interval=1.0)
ev_charger_service = EVChargerService()


def _parse_client_datetime(value):
    """Parse ISO or MySQL-style datetimes from JSON; returns naive local datetime or None."""
    if value is None or value == '':
        return None
    if isinstance(value, datetime):
        return value
    s = str(value).strip().replace('Z', '').split('+', 1)[0].strip().replace('T', ' ')
    if '.' in s:
        s = s.split('.')[0]
    try:
        return datetime.strptime(s[:19], '%Y-%m-%d %H:%M:%S')
    except ValueError:
        return None


def _vehicle_idle_activity(vehicle_id: int):
    """None | 'to_charger' | 'charging' | 'standby_at_charger' when vehicle has no simulator ride."""
    try:
        return vehicle_simulator.get_vehicle_idle_activity(vehicle_id)
    except Exception:
        return None


def _vehicle_activity_detail(vehicle_id: int):
    try:
        return vehicle_simulator.get_vehicle_activity_detail(vehicle_id)
    except Exception:
        return None


def _clear_simulator_rides_for_vehicle(vehicle_id: int, except_ride_id=None):
    """Remove all in-memory simulator rides for a vehicle (prevents stale ride overlap)."""
    with vehicle_simulator.lock:
        for rid, data in list(vehicle_simulator.active_rides.items()):
            if data.get('vehicle_id') == vehicle_id and rid != except_ride_id:
                vehicle_simulator.active_rides.pop(rid, None)


def _route_waypoints_json(waypoints):
    """JSON for MariaDB CHECK (json_valid(waypoints)); all numbers as float."""
    import json

    if not waypoints:
        return json.dumps([])
    clean = []
    for wp in waypoints:
        if isinstance(wp, dict):
            clean.append([float(wp.get('latitude', wp.get('lat', 0))), float(wp.get('longitude', wp.get('lng', 0)))])
        else:
            clean.append([float(wp[0]), float(wp[1])])
    return json.dumps(clean)



def start_simulator():
    """Start the vehicle simulator"""
    vehicle_simulator.start()


def resume_active_rides():
    """Resume any existing assigned or in-progress rides after restart."""
    assigned_rides = db.execute_query(
    """
        SELECT r.id as ride_id, r.vehicle_id, r.status, r.pickup_latitude, r.pickup_longitude,
               r.dropoff_latitude, r.dropoff_longitude, rt.waypoints
        FROM ride_requests r
        LEFT JOIN routes rt ON r.id = rt.ride_id
        WHERE r.status IN ('assigned', 'in_progress') AND r.vehicle_id IS NOT NULL
        """
    ) or []

    for row in assigned_rides:
        try:
            waypoints = row.get('waypoints')
            if isinstance(waypoints, (bytes, bytearray)):
                waypoints = waypoints.decode('utf-8', errors='ignore')
            if isinstance(waypoints, str) and waypoints:
                import json
                waypoints = json.loads(waypoints)
            if not waypoints or not isinstance(waypoints, list) or len(waypoints) < 2:
                pickup_lat = row.get('pickup_latitude')
                pickup_lng = row.get('pickup_longitude')
                dropoff_lat = row.get('dropoff_latitude')
                dropoff_lng = row.get('dropoff_longitude')
                if pickup_lat is not None and pickup_lng is not None and dropoff_lat is not None and dropoff_lng is not None:
                    waypoints =[[float (pickup_lat ),float (pickup_lng )],[float (dropoff_lat ),float (dropoff_lng )]]
                else :
                    continue 

            ride_phase ='in_transit'if row .get ('status')=='in_progress'else 'to_pickup'
            vehicle_simulator .assign_ride_to_vehicle (
            int (row ['ride_id']),
            int (row ['vehicle_id']),
            pickup_lat =float (row ['pickup_latitude'])if row .get ('pickup_latitude')is not None else None ,
            pickup_lng =float (row ['pickup_longitude'])if row .get ('pickup_longitude')is not None else None ,
            dropoff_lat =float (row ['dropoff_latitude'])if row .get ('dropoff_latitude')is not None else None ,
            dropoff_lng =float (row ['dropoff_longitude'])if row .get ('dropoff_longitude')is not None else None ,
            route_waypoints =waypoints ,
            phase =ride_phase 
            )
        except Exception as exc :
            print (f"[main] Failed to resume ride {row .get ('ride_id')}: {exc }")



@app .before_request 
def before_request ():
    """Ensure a live DB connection before every request (see Database.ensure_live)."""
    db .ensure_live ()

@app .teardown_appcontext 
def close_db (error ):
    pass 



@app .route ('/api/vehicles',methods =['GET'])
def get_vehicles ():
    """Get all vehicles"""
    query ="""
        SELECT v.id, v.vehicle_template_id, v.status, v.battery_level,
               vt.name as template_name, vt.description as template_description,
               vt.capacity as template_capacity, vt.max_range_km, vt.battery_capacity_kwh,
               vt.consumption_kwh_per_km, vt.charging_time_0_to_100_min,
               vlr.latitude as current_latitude, vlr.longitude as current_longitude
        FROM vehicles v
        LEFT JOIN vehicle_templates vt ON v.vehicle_template_id = vt.id
        LEFT JOIN vehicle_location_reports vlr ON v.id = vlr.vehicle_id
            AND vlr.id = (SELECT MAX(id) FROM vehicle_location_reports WHERE vehicle_id = v.id)
    """
    vehicles =db .execute_query (query )

    if vehicles :
        result =[]
        for v in vehicles :
            vehicle =Vehicle (
            vehicle_id =v ['id'],
            vehicle_template_id =v ['vehicle_template_id'],
            current_latitude =v .get ('current_latitude'),
            current_longitude =v .get ('current_longitude'),
            battery_level =v .get ('battery_level')if v .get ('battery_level')is not None else 100 ,
            status =v .get ('status','available')
            )
            if v .get ('template_name'):
                vehicle .template =VehicleTemplate (
                template_id =v ['vehicle_template_id'],
                name =v ['template_name'],
                description =v .get ('template_description'),
                capacity =v .get ('template_capacity',4 ),
                max_range_km =v .get ('max_range_km'),
                battery_capacity_kwh =v .get ('battery_capacity_kwh'),
                consumption_kwh_per_km =v .get ('consumption_kwh_per_km')
                )
            vdict =vehicle .to_dict ()
            vdict ['idle_activity']=_vehicle_idle_activity (v ['id'])
            vdict ['activity_detail']=_vehicle_activity_detail (v ['id'])
            result .append (vdict )
        return jsonify (result )
    return jsonify ([])

@app .route ('/api/vehicles/<int:vehicle_id>',methods =['GET'])
def get_vehicle (vehicle_id ):
    """Get a specific vehicle"""
    query ="""
        SELECT v.id, v.vehicle_template_id, v.status, v.battery_level,
               vt.name as template_name, vt.description as template_description,
               vt.capacity as template_capacity, vt.max_range_km, vt.battery_capacity_kwh,
               vt.consumption_kwh_per_km, vt.charging_time_0_to_100_min,
               vlr.latitude as current_latitude, vlr.longitude as current_longitude
        FROM vehicles v
        LEFT JOIN vehicle_templates vt ON v.vehicle_template_id = vt.id
        LEFT JOIN vehicle_location_reports vlr ON v.id = vlr.vehicle_id
            AND vlr.id = (SELECT MAX(id) FROM vehicle_location_reports WHERE vehicle_id = v.id)
        WHERE v.id = %s
    """
    result =db .execute_query (query ,(vehicle_id ,vehicle_id ))

    if result :
        v =result [0 ]
        vehicle =Vehicle (
        vehicle_id =v ['id'],
        vehicle_template_id =v ['vehicle_template_id'],
        current_latitude =v .get ('current_latitude'),
        current_longitude =v .get ('current_longitude'),
        battery_level =v .get ('battery_level')if v .get ('battery_level')is not None else 100 ,
        status =v .get ('status','available')
        )
        if v .get ('template_name'):
            vehicle .template =VehicleTemplate (
            template_id =v ['vehicle_template_id'],
            name =v ['template_name'],
            description =v .get ('template_description'),
            capacity =v .get ('template_capacity',4 ),
            max_range_km =v .get ('max_range_km'),
            battery_capacity_kwh =v .get ('battery_capacity_kwh'),
            consumption_kwh_per_km =v .get ('consumption_kwh_per_km')
            )
        vdict =vehicle .to_dict ()
        vdict ['idle_activity']=_vehicle_idle_activity (vehicle_id )
        vdict ['activity_detail']=_vehicle_activity_detail (vehicle_id )
        return jsonify (vdict )
    return jsonify ({'error':'Vehicle not found'}),404 


@app .route ('/api/vehicles/<int:vehicle_id>/locations',methods =['GET'])
def get_vehicle_locations (vehicle_id ):
    """Return recent location reports for a vehicle"""
    try :
        limit =int (request .args .get ('limit',50 ))
    except Exception :
        limit =50 

    query =f"SELECT id, latitude, longitude FROM vehicle_location_reports WHERE vehicle_id = %s ORDER BY id DESC LIMIT {limit }"
    results =db .execute_query (query ,(vehicle_id ,))

    if results :
        results =results [::-1 ]
    return jsonify (results or [])


@app .route ('/api/rides/<int:ride_id>/events',methods =['GET'])
def get_ride_events (ride_id ):
    """Return simple event log for a ride (creation, assignment, recent vehicle locations)"""
    ride_data =db .execute_query ("SELECT id, status, vehicle_id, created_at, updated_at FROM ride_requests WHERE id = %s",(ride_id ,))
    if not ride_data :
        return jsonify ({'error':'Ride not found'}),404 
    r =ride_data [0 ]
    events =[]

    events .append ({
    'type':'requested',
    'timestamp':r .get ('created_at'),
    'details':{'status':r .get ('status')}
    })

    vehicle_id =r .get ('vehicle_id')
    if vehicle_id :
        events .append ({
        'type':'assigned',
        'timestamp':r .get ('updated_at'),
        'details':{'vehicle_id':vehicle_id }
        })

        locs_q ="SELECT id, latitude, longitude FROM vehicle_location_reports WHERE vehicle_id = %s ORDER BY id DESC LIMIT 50"
        locs =db .execute_query (locs_q ,(vehicle_id ,))
        if locs :
            locs =locs [::-1 ]
            for loc in locs :
                events .append ({
                'type':'location',
                'id':loc ['id'],
                'latitude':loc ['latitude'],
                'longitude':loc ['longitude']
                })
    return jsonify (events )

@app .route ('/api/vehicles',methods =['POST'])
def create_vehicle ():
    """Create a new vehicle"""
    data =request .json 
    query ="""
        INSERT INTO vehicles (vehicle_template_id, created_at, updated_at)
        VALUES (%s, NOW(), NOW())
    """
    vehicle_id =db .execute_update (query ,(data .get ('vehicle_template_id'),))

    if vehicle_id :
        return jsonify ({'id':vehicle_id ,'message':'Vehicle created'}),201 
    return jsonify ({'error':'Failed to create vehicle'}),500 



@app .route ('/api/charging-stations',methods =['GET'])
def get_charging_stations ():
    """Get charging stations near a location using EV Charger API"""
    try :
        lat =float (request .args .get ('lat',50.8503 ))
        lon =float (request .args .get ('lon',4.3517 ))
        distance =float (request .args .get ('distance',10.0 ))
        level =request .args .get ('level')



        try :
            limit =int (request .args .get ('limit',0 ))or None 
        except (TypeError ,ValueError ):
            limit =None 

        if level :
            level =int (level )

        stations =ev_charger_service .get_charging_stations (
        latitude =lat ,
        longitude =lon ,
        distance =distance ,
        level =level 
        )

        if limit :
            stations =stations [:limit ]

        return jsonify ([station .to_dict ()for station in stations ])

    except ValueError as e :
        return jsonify ({'error':'Invalid parameters','details':str (e )}),400 
    except Exception as e :
        print (f"Error in get_charging_stations: {e }")
        return jsonify ({'error':'Failed to fetch charging stations'}),500 

@app .route ('/api/cache/chargers',methods =['GET'])
def get_cached_charging_stations ():
    """Return all cached charging stations from the backend cache file."""
    try :
        stations =ev_charger_service .get_all_cached_charging_stations ()
        return jsonify ([station .to_dict ()for station in stations ])
    except Exception as e :
        print (f"Error in get_cached_charging_stations: {e }")
        return jsonify ({'error':'Failed to get cached charging stations'}),500 

@app .route ('/api/cache/stats',methods =['GET'])
def get_cache_stats ():
    """Get cache statistics"""
    try :
        stats =ev_charger_service .get_cache_stats ()
        stats ['last_fetch_source']=ev_charger_service .last_fetch_source 
        stats ['last_fetch_at']=ev_charger_service .last_fetch_at 
        return jsonify (stats )
    except Exception as e :
        print (f"Error getting cache stats: {e }")
        return jsonify ({'error':'Failed to get cache statistics'}),500 

@app .route ('/api/cache/clear',methods =['POST'])
def clear_expired_cache ():
    """Clear expired cache files"""
    try :
        cleared_count =ev_charger_service .clear_expired_cache ()
        return jsonify ({'message':f'Cleared {cleared_count } expired cache files'})
    except Exception as e :
        print (f"Error clearing cache: {e }")
        return jsonify ({'error':'Failed to clear cache'}),500 



@app .route ('/api/rides',methods =['GET'])
def get_rides ():
    """Get all rides with optional filters"""
    status =request .args .get ('status')
    user_id =request .args .get ('user_id')
    query ="""
        SELECT id, user_id, status, vehicle_id, comfort_level, shared_ride,
               estimated_price_cents, pickup_address, dropoff_address,
               pickup_latitude, pickup_longitude, dropoff_latitude, dropoff_longitude,
               estimated_distance_km, estimated_duration_minutes, requested_pickup_time,
               passenger_count, actual_pickup_time, actual_dropoff_time,
               created_at, updated_at
        FROM ride_requests
    """
    conditions =[]
    params =[]
    if status :
        conditions .append ("status = %s")
        params .append (status )
    if user_id :
        conditions .append ("user_id = %s")
        params .append (user_id )
    if conditions :
        query +=" WHERE "+" AND ".join (conditions )
    query +=" ORDER BY created_at DESC"

    rides =db .execute_query (query ,tuple (params )if params else None )

    if rides :
        result =[]
        for r in rides :
            ride =Ride .from_dict (r )
            result .append (ride .to_dict ())
        return jsonify (result )
    return jsonify ([])

@app .route ('/api/rides/<int:ride_id>',methods =['GET'])
def get_ride (ride_id ):
    """Get a specific ride with full details"""
    query ="""
        SELECT r.id, r.user_id, r.status, r.vehicle_id, r.route_id, r.comfort_level, r.shared_ride,
               r.estimated_price_cents, r.pickup_address, r.dropoff_address,
               r.pickup_latitude, r.pickup_longitude, r.dropoff_latitude, r.dropoff_longitude,
               r.estimated_distance_km, r.estimated_duration_minutes, r.requested_pickup_time,
               r.passenger_count, r.actual_pickup_time, r.actual_dropoff_time,
               r.created_at, r.updated_at,
               rt.waypoints as route_waypoints, rt.distance_km as route_distance_km,
               rt.estimated_duration_minutes as route_duration_minutes,
               rt.start_latitude as route_start_latitude, rt.start_longitude as route_start_longitude,
               rt.end_latitude as route_end_latitude, rt.end_longitude as route_end_longitude,
               rt.status as route_status
        FROM ride_requests r
        LEFT JOIN routes rt ON r.route_id = rt.id
        WHERE r.id = %s
    """
    result =db .execute_query (query ,(ride_id ,))

    if result :
        r =result [0 ]
        uid =request .args .get ('user_id',type =int )
        if uid is not None and r .get ('user_id')is not None :
            try :
                if int (r ['user_id'])!=uid :
                    return jsonify ({'error':'Forbidden'}),403 
            except (TypeError ,ValueError ):
                pass 
        ride =Ride .from_dict (r )
        payload =ride .to_dict ()
        if r .get ('route_waypoints'):
            import json 
            wps =r ['route_waypoints']
            if isinstance (wps ,(bytes ,bytearray )):
                wps =wps .decode ('utf-8',errors ='ignore')
            if isinstance (wps ,str ):
                try :
                    wps =json .loads (wps )
                except json .JSONDecodeError :
                    wps =[]
            payload ['route']={
            'start_lat':float (r ['route_start_latitude'])if r .get ('route_start_latitude')is not None else None ,
            'start_lng':float (r ['route_start_longitude'])if r .get ('route_start_longitude')is not None else None ,
            'dest_lat':float (r ['route_end_latitude'])if r .get ('route_end_latitude')is not None else None ,
            'dest_lng':float (r ['route_end_longitude'])if r .get ('route_end_longitude')is not None else None ,
            'waypoints':wps ,
            'distance_km':float (r ['route_distance_km'])if r .get ('route_distance_km')is not None else None ,
            'estimated_duration_min':r .get ('route_duration_minutes'),
            'status':r .get ('route_status'),
            }
        return jsonify (payload )
    return jsonify ({'error':'Ride not found'}),404 

@app .route ('/api/rides',methods =['POST'])
def create_ride ():
    """Create a new ride request"""
    data =request .json 

    try :

        user_id =data .get ('user_id')or 1 
        pickup_address =data .get ('pickup_address')
        dropoff_address =data .get ('dropoff_address')
        pickup_latitude =data .get ('pickup_latitude')
        pickup_longitude =data .get ('pickup_longitude')
        dropoff_latitude =data .get ('dropoff_latitude')
        dropoff_longitude =data .get ('dropoff_longitude')
        comfort_level =data .get ('comfort_level','standard')
        shared_ride =data .get ('shared_ride',0 )
        passenger_count =data .get ('passenger_count',1 )
        requested_pickup_time =data .get ('requested_pickup_time')
        estimated_distance_km =data .get ('estimated_distance_km')
        estimated_duration_minutes =data .get ('estimated_duration_minutes')
        estimated_price_cents =data .get ('estimated_price_cents')

        now =datetime .now ()
        parsed_pickup =_parse_client_datetime (requested_pickup_time )
        pickup_for_db =parsed_pickup if parsed_pickup else now 
        is_scheduled_booking =bool (parsed_pickup and parsed_pickup >now +timedelta (minutes =2 ))
        pickup_mysql =pickup_for_db .strftime ('%Y-%m-%d %H:%M:%S')


        if not (pickup_address and dropoff_address and pickup_latitude and pickup_longitude and 
        dropoff_latitude and dropoff_longitude ):
            return jsonify ({'error':'Missing required location data'}),400 


        ride_query ="""
            INSERT INTO ride_requests 
            (user_id, status, comfort_level, shared_ride, passenger_count,
             pickup_address, dropoff_address, pickup_latitude, pickup_longitude,
             dropoff_latitude, dropoff_longitude, estimated_distance_km,
             estimated_duration_minutes, estimated_price_cents, requested_pickup_time,
             created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
        """

        ride_id =db .execute_update (ride_query ,(
        user_id ,
        'pending',
        comfort_level ,
        shared_ride ,
        passenger_count ,
        pickup_address ,
        dropoff_address ,
        pickup_latitude ,
        pickup_longitude ,
        dropoff_latitude ,
        dropoff_longitude ,
        estimated_distance_km ,
        estimated_duration_minutes ,
        estimated_price_cents ,
        pickup_mysql 
        ))

        if not ride_id :
            return jsonify ({'error':'Failed to create ride'}),500 


        route_id =None 
        route =None 
        try :
            route =route_service .create_route (
            start_address =pickup_address ,
            destination_address =dropoff_address ,
            start_lat =float (pickup_latitude ),
            start_lng =float (pickup_longitude ),
            dest_lat =float (dropoff_latitude ),
            dest_lng =float (dropoff_longitude )
            )
        except Exception as e :
            print (f"Warning: route_service.create_route failed: {e }")

        if not route :

            fallback_distance =calculate_distance (
            float (pickup_latitude ),float (pickup_longitude ),
            float (dropoff_latitude ),float (dropoff_longitude )
            )
            route =Route (
            start_address =pickup_address ,
            destination_address =dropoff_address ,
            start_lat =float (pickup_latitude ),
            start_lng =float (pickup_longitude ),
            dest_lat =float (dropoff_latitude ),
            dest_lng =float (dropoff_longitude ),
            waypoints =[
            [float (pickup_latitude ),float (pickup_longitude )],
            [float (dropoff_latitude ),float (dropoff_longitude )]
            ],
            distance_km =round (fallback_distance ,2 ),
            estimated_duration_min =round ((fallback_distance /50.0 )*60.0 ,2 )
            )

        route_status ='planned'if is_scheduled_booking else 'active'

        assigned_vehicle =None 
        try :
            available_vehicles_query ="""
                SELECT v.id, v.vehicle_template_id, v.status, v.battery_level,
                       vt.name as template_name, vt.description as template_description,
                       vt.capacity as template_capacity, vt.max_range_km,
                       vt.battery_capacity_kwh, vt.consumption_kwh_per_km,
                       vlr.latitude AS current_latitude,
                       vlr.longitude AS current_longitude
                FROM vehicles v
                LEFT JOIN vehicle_templates vt ON v.vehicle_template_id = vt.id
                LEFT JOIN vehicle_location_reports vlr ON v.id = vlr.vehicle_id
                    AND vlr.id = (SELECT MAX(id) FROM vehicle_location_reports WHERE vehicle_id = v.id)
                WHERE v.status = 'available'
                AND v.id NOT IN (
                    SELECT vehicle_id FROM ride_requests 
                    WHERE status IN ('pending', 'assigned', 'in_progress') AND vehicle_id IS NOT NULL
                )
            """
            available_vehicles_data =db .execute_query (available_vehicles_query )or []

            if route and available_vehicles_data :
                pickup_lat_f =float (pickup_latitude )
                pickup_lng_f =float (pickup_longitude )
                available_vehicles =[]
                for v_data in available_vehicles_data :
                    lat =v_data .get ('current_latitude')
                    lng =v_data .get ('current_longitude')
                    if lat is None or lng is None :
                        continue
                    lat ,lng =float (lat ),float (lng )
                    vehicle =Vehicle (
                    vehicle_id =v_data ['id'],
                    vehicle_template_id =v_data ['vehicle_template_id'],
                    current_latitude =lat ,
                    current_longitude =lng ,
                    battery_level =v_data .get ('battery_level')if v_data .get ('battery_level')is not None else 100 ,
                    status =v_data .get ('status','available')
                    )
                    if v_data .get ('template_name'):
                        vehicle .template =VehicleTemplate (
                        template_id =v_data ['vehicle_template_id'],
                        name =v_data .get ('template_name'),
                        description =v_data .get ('template_description'),
                        capacity =v_data .get ('template_capacity',4 ),
                        max_range_km =v_data .get ('max_range_km'),
                        battery_capacity_kwh =v_data .get ('battery_capacity_kwh'),
                        consumption_kwh_per_km =v_data .get ('consumption_kwh_per_km')
                        )
                    available_vehicles .append (vehicle )

                ride_obj =Ride (
                ride_id =ride_id ,
                user_id =user_id ,
                pickup_address =pickup_address ,
                dropoff_address =dropoff_address ,
                pickup_latitude =pickup_latitude ,
                pickup_longitude =pickup_longitude ,
                dropoff_latitude =dropoff_latitude ,
                dropoff_longitude =dropoff_longitude ,
                passenger_count =passenger_count ,
                requested_pickup_time =pickup_mysql 
                )

                assignment =assign_vehicle_to_ride (available_vehicles ,ride_obj ,route )
                if assignment :
                    assigned_vehicle ,_ =assignment 
        except Exception as e :
            print (f"Warning: Vehicle assignment failed: {e }")


        route_vehicle_id =None 
        if assigned_vehicle :
            route_vehicle_id =assigned_vehicle .vehicle_id 

        if route and route_vehicle_id is not None :
            waypoints_json =_route_waypoints_json (route .waypoints )
            route_insert_sql ="""
                INSERT INTO routes
                (vehicle_id, name, start_latitude, start_longitude, end_latitude, end_longitude,
                 waypoints, distance_km, estimated_duration_minutes, status, scheduled_start_time,
                 ride_id, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
            """
            route_id =db .execute_update (route_insert_sql ,(
            route_vehicle_id ,
            f"{pickup_address } to {dropoff_address }",
            pickup_latitude ,
            pickup_longitude ,
            dropoff_latitude ,
            dropoff_longitude ,
            waypoints_json ,
            route .distance_km ,
            route .estimated_duration_min ,
            route_status ,
            pickup_mysql ,
            ride_id 
            ))
            if route_id :
                db .execute_update (
                "UPDATE ride_requests SET route_id = %s, updated_at = NOW() WHERE id = %s",
                (route_id ,ride_id )
                )
            else :
                print (f"Warning: routes INSERT failed for ride {ride_id } (constraints / FK; see DB error above)")

        if assigned_vehicle :
            if route_id :
                db .execute_update (
                "UPDATE routes SET vehicle_id = %s, updated_at = NOW() WHERE id = %s",
                (assigned_vehicle .vehicle_id ,route_id )
                )

            db .execute_update (
            "UPDATE ride_requests SET vehicle_id = %s, status = %s, updated_at = NOW() WHERE id = %s",
            (assigned_vehicle .vehicle_id ,'assigned',ride_id )
            )
            db .execute_update (
            "UPDATE vehicles SET status = %s, updated_at = NOW() WHERE id = %s",
            ('in_use',assigned_vehicle .vehicle_id )
            )
            if not vehicle_simulator .running :
                start_simulator ()
            sim_scheduled =pickup_mysql if is_scheduled_booking else None 
            vehicle_simulator .assign_ride_to_vehicle (
            ride_id ,
            assigned_vehicle .vehicle_id ,
            start_lat =assigned_vehicle .current_latitude ,
            start_lng =assigned_vehicle .current_longitude ,
            pickup_lat =float (pickup_latitude ),
            pickup_lng =float (pickup_longitude ),
            dropoff_lat =float (dropoff_latitude ),
            dropoff_lng =float (dropoff_longitude ),
            route_waypoints =route .waypoints ,
            scheduled_start_time =sim_scheduled 
            )


        return get_ride (ride_id )

    except Exception as e :
        return jsonify ({'error':f'Failed to create ride: {str (e )}'}),500 

@app .route ('/api/rides/<int:ride_id>/status',methods =['PATCH'])
def update_ride_status (ride_id ):
    """Update ride status"""
    data =request .json 
    new_status =data .get ('status')


    valid_statuses =['pending','assigned','in_progress','completed','cancelled']
    if new_status not in valid_statuses :
        return jsonify ({'error':f'Invalid status. Allowed: {valid_statuses }'}),400 

    query ="""
        UPDATE ride_requests SET status = %s, updated_at = NOW()
        WHERE id = %s
    """
    if new_status =='completed':
        query ="""
            UPDATE ride_requests
            SET status = %s, actual_dropoff_time = COALESCE(actual_dropoff_time, NOW()), updated_at = NOW()
            WHERE id = %s
        """
    elif new_status =='in_progress':
        query ="""
            UPDATE ride_requests
            SET status = %s, actual_pickup_time = COALESCE(actual_pickup_time, NOW()), updated_at = NOW()
            WHERE id = %s
        """
    result =db .execute_update (query ,(new_status ,ride_id ))

    if result :
        if new_status in ('completed','cancelled'):
            ride_data =db .execute_query ("SELECT vehicle_id FROM ride_requests WHERE id = %s",(ride_id ,))
            if ride_data and ride_data [0 ].get ('vehicle_id'):
                vid =ride_data [0 ]['vehicle_id']
                db .execute_update ("UPDATE vehicles SET status = %s, updated_at = NOW() WHERE id = %s",('available',vid ))
                _clear_simulator_rides_for_vehicle (vid )
                with vehicle_simulator .lock :
                    vehicle_simulator .idle_vehicle_states .pop (vid ,None )
        return get_ride (ride_id )
    return jsonify ({'error':'Failed to update ride status'}),500 

@app .route ('/api/rides/<int:ride_id>',methods =['DELETE'])
def cancel_ride (ride_id ):
    """Cancel a ride"""
    ride_data =db .execute_query (
    "SELECT vehicle_id, user_id FROM ride_requests WHERE id = %s AND status IN ('pending', 'assigned')",
    (ride_id ,),
    )
    if not ride_data :
        return jsonify ({'error':'Cannot cancel ride - invalid status or ride not found'}),400 

    uid =request .args .get ('user_id',type =int )
    row =ride_data [0 ]
    if uid is not None and row .get ('user_id')is not None :
        try :
            if int (row ['user_id'])!=uid :
                return jsonify ({'error':'Forbidden'}),403 
        except (TypeError ,ValueError ):
            pass 

    vehicle_id =row .get ('vehicle_id')
    query ="""
        UPDATE ride_requests SET status = %s, updated_at = NOW()
        WHERE id = %s
    """
    result =db .execute_update (query ,('cancelled',ride_id ))

    if result :
        if vehicle_id :
            db .execute_update ("UPDATE vehicles SET status = %s, updated_at = NOW() WHERE id = %s",('available',vehicle_id ))
            _clear_simulator_rides_for_vehicle (vehicle_id )
            with vehicle_simulator .lock :
                vehicle_simulator .idle_vehicle_states .pop (vehicle_id ,None )
        return jsonify ({'message':'Ride cancelled successfully'}),200 
    return jsonify ({'error':'Cannot cancel ride - invalid status or ride not found'}),400 



@app .route ('/api/simulation/start',methods =['POST'])
def start_simulation ():
    """Start the vehicle simulation"""
    try :
        if not vehicle_simulator .running :
            vehicle_simulator .start ()
        return jsonify ({'message':'Vehicle simulation started successfully'}),200 
    except Exception as e :
        return jsonify ({'error':f'Failed to start simulation: {str (e )}'}),500 

@app .route ('/api/simulation/stop',methods =['POST'])
def stop_simulation ():
    """Stop the vehicle simulation"""
    try :
        vehicle_simulator .stop ()
        return jsonify ({'message':'Vehicle simulation stopped successfully'}),200 
    except Exception as e :
        return jsonify ({'error':f'Failed to stop simulation: {str (e )}'}),500 

@app .route ('/api/simulation/status',methods =['GET'])
def get_simulation_status ():
    """Get the status of the vehicle simulation"""
    return jsonify ({
    'running':vehicle_simulator .running ,
    'active_rides':len (vehicle_simulator .active_rides ),
    'update_interval':vehicle_simulator .update_interval 
    })

@app .route ('/api/rides/<int:ride_id>/progress',methods =['GET'])
def get_ride_progress (ride_id ):
    """Get the current progress of a ride (simulation phase, ETA, map location)."""
    ride_query ="""
        SELECT id, user_id, status, vehicle_id, pickup_address, dropoff_address,
               pickup_latitude, pickup_longitude, dropoff_latitude, dropoff_longitude,
               estimated_distance_km, estimated_duration_minutes,
               requested_pickup_time, actual_pickup_time, actual_dropoff_time
        FROM ride_requests WHERE id = %s
    """
    ride_data =db .execute_query (ride_query ,(ride_id ,))
    if not ride_data :
        return jsonify ({'error':'Ride not found'}),404 

    ride_info =ride_data [0 ]
    uid =request .args .get ('user_id',type =int )
    if uid is not None and ride_info .get ('user_id')is not None :
        try :
            if int (ride_info ['user_id'])!=uid :
                return jsonify ({'error':'Forbidden'}),403 
        except (TypeError ,ValueError ):
            pass 

    progress =vehicle_simulator .get_ride_progress (ride_id )
    vehicle_id =ride_info .get ('vehicle_id')
    current_location =None 
    if vehicle_id :
        location_data =db .execute_query (
        """
            SELECT latitude, longitude FROM vehicle_location_reports
            WHERE vehicle_id = %s ORDER BY id DESC LIMIT 1
            """,
        (vehicle_id ,),
        )
        if location_data :
            current_location ={
            'latitude':float (location_data [0 ]['latitude']),
            'longitude':float (location_data [0 ]['longitude']),
            }

    duration_min =float (ride_info ['estimated_duration_minutes']or 0 )
    est_dist =float (ride_info ['estimated_distance_km']or 0 )
    simulated_speed_kmh = vehicle_simulator .SIMULATED_SPEED_KMH

    if progress is None :
        prog_pct =0.0 
        phase ='pending'if ride_info ['status']=='pending'else 'waiting_vehicle'
        remaining_km =est_dist 
        eta_minutes =None 
        total_duration_min =duration_min 
    else :
        prog_pct =max(0.0, min(100.0, round (float (progress .get('progress', 0.0) or 0.0)*100.0 ,2 )))
        phase =progress .get ('phase')or 'unknown'
        route_dist_km =float (progress .get ('route_distance')or est_dist or 0 )
        if route_dist_km <=0 :
            route_dist_km =est_dist or 1e-6 
        total_duration_min =round ((route_dist_km /simulated_speed_kmh )*60.0 ,1 )if route_dist_km >0 else duration_min 
        remaining_km =max (0.0 ,route_dist_km *(1.0 -float (progress ['progress'])))
        eta_minutes =round ((remaining_km /simulated_speed_kmh )*60.0 ,1 )if remaining_km >0 else 0.0 
    scheduled_wait_minutes =None 
    if phase =='scheduled':
        requested_pickup =ride_info .get ('requested_pickup_time')
        requested_dt =vehicle_simulator ._coerce_datetime (requested_pickup )if requested_pickup else None 
        if requested_dt :
            scheduled_wait_minutes =max (0.0 ,round ((requested_dt -datetime .now ()).total_seconds ()/60.0 ,1 ))
            eta_minutes =round (scheduled_wait_minutes +max (total_duration_min ,0.0 ),1 )
        else :
            eta_minutes =None 
    elapsed_minutes =round ((prog_pct /100.0 )*total_duration_min ,1 )if total_duration_min >0 else 0.0 

    return jsonify ({
    'ride_id':ride_id ,
    'status':ride_info ['status'],
    'vehicle_id':vehicle_id ,
    'actual_pickup_time':serialize_ride_ts (ride_info .get ('actual_pickup_time')),
    'actual_dropoff_time':serialize_ride_ts (ride_info .get ('actual_dropoff_time')),
    'progress_percent':prog_pct ,
    'simulation_phase':phase ,
    'distance_km':ride_info ['estimated_distance_km'],
    'duration_minutes':ride_info ['estimated_duration_minutes'],
    'total_duration_minutes':total_duration_min ,
    'elapsed_minutes':elapsed_minutes ,
    'scheduled_wait_minutes':scheduled_wait_minutes ,
    'remaining_distance_km':round (remaining_km ,3 ),
    'eta_minutes':eta_minutes ,
    'current_location':current_location ,
    'pickup_address':ride_info ['pickup_address'],
    'dropoff_address':ride_info ['dropoff_address'],
    'pickup_coords':{
    'latitude':float (ride_info ['pickup_latitude'])if ride_info .get ('pickup_latitude')is not None else None ,
    'longitude':float (ride_info ['pickup_longitude'])if ride_info .get ('pickup_longitude')is not None else None ,
    },
    'dropoff_coords':{
    'latitude':float (ride_info ['dropoff_latitude'])if ride_info .get ('dropoff_latitude')is not None else None ,
    'longitude':float (ride_info ['dropoff_longitude'])if ride_info .get ('dropoff_longitude')is not None else None ,
    },
    })

@app .route ('/api/vehicles/<int:vehicle_id>/location-history',methods =['GET'])
def get_vehicle_location_history (vehicle_id ):
    """Get location history for a vehicle"""
    limit =request .args .get ('limit',100 ,type =int )

    query ="""
        SELECT id, vehicle_id, latitude, longitude, reported_at
        FROM vehicle_location_reports 
        WHERE vehicle_id = %s 
        ORDER BY id DESC 
        LIMIT %s
    """
    results =db .execute_query (query ,(vehicle_id ,limit ))

    if results :

        results =results [::-1 ]
        return jsonify ([{
        'id':r ['id'],
        'latitude':r ['latitude'],
        'longitude':r ['longitude'],
        'timestamp':r ['reported_at'].isoformat ()if isinstance (r ['reported_at'],datetime )else r ['reported_at']
        }for r in results ])

    return jsonify ([])



@app .route ('/api/mapview/rides',methods =['GET'])
def get_mapview_rides ():
    """Get all active rides with their routes and waypoints for mapview"""
    try :
        statuses =request .args .get ('status','in_progress,assigned').split (',')
        status_placeholders =','.join (['%s']*len (statuses ))

        query =f"""
            SELECT r.id, r.user_id, r.status, r.vehicle_id, 
                   r.pickup_address, r.dropoff_address,
                   r.pickup_latitude, r.pickup_longitude,
                   r.dropoff_latitude, r.dropoff_longitude,
                   r.actual_pickup_time, r.actual_dropoff_time,
                   r.estimated_distance_km, r.estimated_duration_minutes,
                   COALESCE(r.customer_name, CONCAT(u.firstname, ' ', u.lastname)) as customer_name,
                   rt.waypoints, rt.distance_km, rt.estimated_duration_minutes as route_duration,
                   v.id as vehicle_id_check,
                   vt.name as vehicle_template_name
            FROM ride_requests r
            LEFT JOIN routes rt ON r.id = rt.ride_id
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN vehicles v ON r.vehicle_id = v.id
            LEFT JOIN vehicle_templates vt ON v.vehicle_template_id = vt.id
            WHERE r.status IN ({status_placeholders })
            ORDER BY r.created_at DESC
        """

        results =db .execute_query (query ,tuple (statuses ))

        if results is None :
            return jsonify ({'error':'Database query failed'}),500 

        rides_list =[]
        for r in results :
            waypoints =[]
            if r .get ('waypoints'):
                try :
                    raw_waypoints =r ['waypoints']
                    if isinstance (raw_waypoints ,(bytes ,bytearray )):
                        raw_waypoints =raw_waypoints .decode ('utf-8',errors ='ignore')
                    if isinstance (raw_waypoints ,str ):
                        import json 
                        waypoints =json .loads (raw_waypoints )
                    else :
                        waypoints =raw_waypoints 
                except Exception :
                    waypoints =[]

            sim_progress =vehicle_simulator .get_ride_progress (r ['id'])
            simulation_phase =sim_progress .get ('phase')if sim_progress else None 
            charger_stop =sim_progress .get ('charger_stop_info')if sim_progress else None 
            progress_percent =0.0
            if sim_progress :
                progress_percent =sim_progress .get ('progress_percent')
                if progress_percent is None :
                    progress =sim_progress .get ('progress')
                    try :
                        progress_percent =float (progress or 0.0 )*100.0
                    except (TypeError ,ValueError ):
                        progress_percent =0.0

            ride_data ={
            'id':r ['id'],
            'user_id':r ['user_id'],
            'status':r ['status'],
            'vehicle_id':r ['vehicle_id'],
            'customer_name':(r .get ('customer_name')or '').strip ()or None ,
            'pickup_address':r ['pickup_address'],
            'dropoff_address':r ['dropoff_address'],
            'pickup_latitude':float (r ['pickup_latitude'])if r ['pickup_latitude']else None ,
            'pickup_longitude':float (r ['pickup_longitude'])if r ['pickup_longitude']else None ,
            'dropoff_latitude':float (r ['dropoff_latitude'])if r ['dropoff_latitude']else None ,
            'dropoff_longitude':float (r ['dropoff_longitude'])if r ['dropoff_longitude']else None ,
            'estimated_distance_km':float (r ['estimated_distance_km'])if r ['estimated_distance_km']else 0 ,
            'estimated_duration_minutes':r ['estimated_duration_minutes'],
            'actual_pickup_time':serialize_ride_ts (r .get ('actual_pickup_time')),
            'actual_dropoff_time':serialize_ride_ts (r .get ('actual_dropoff_time')),
            'simulation_phase':simulation_phase ,
            'progress_percent':float (progress_percent ),
            'charger_target':{
            'name':charger_stop .get ('name'),
            'address':charger_stop .get ('address'),
            'latitude':charger_stop .get ('latitude'),
            'longitude':charger_stop .get ('longitude'),
            }if charger_stop else None ,
            'waypoints':waypoints ,
            'vehicle':{
            'id':r ['vehicle_id'],
            'template_name':r ['vehicle_template_name']
            }if r ['vehicle_id']else None 
            }
            rides_list .append (ride_data )

        return jsonify (rides_list )

    except Exception as e :
        print (f"Error in get_mapview_rides: {e }")
        return jsonify ({'error':str (e )}),500 


@app .route ('/api/mapview/trails',methods =['GET'])
def get_mapview_trails ():
    """Movement history (voertuigspoor) from simulator memory."""
    vehicle_ids =request .args .get ('vehicle_ids')
    limit =request .args .get ('limit',500 ,type =int )
    ids =None 
    if vehicle_ids :
        ids =[v .strip ()for v in vehicle_ids .split (',')if v .strip ()]
    return jsonify (vehicle_simulator .get_movement_histories (ids ,limit =limit ))


@app .route ('/api/mapview/traces',methods =['GET'])
def get_mapview_traces ():
    """Ridden paths (afgelegde weg) from simulator memory."""
    ride_ids =request .args .get ('ride_ids')
    limit =request .args .get ('limit',1000 ,type =int )
    ids =None 
    if ride_ids :
        ids =[r .strip ()for r in ride_ids .split (',')if r .strip ()]
    return jsonify (vehicle_simulator .get_ride_trace_paths (ids ,limit =limit ))


@app .route ('/api/mapview/battery-reachability',methods =['GET'])
def get_mapview_battery_reachability ():
    """Live battery vs destination/charger reachability for map overlay."""
    return jsonify (vehicle_simulator .get_battery_reachability_snapshot ())



@app .route ('/api/admin/fleet',methods =['GET'])
def get_fleet_overview ():
    """Get fleet overview for admin"""
    vehicles_query ="""
        SELECT COUNT(*) as total_vehicles,
               COUNT(CASE WHEN v.id IN (
                   SELECT vehicle_id FROM ride_requests 
                   WHERE status IN ('pending', 'assigned', 'in_progress')
               ) THEN 1 END) as vehicles_in_use,
               COUNT(CASE WHEN v.id NOT IN (
                   SELECT vehicle_id FROM ride_requests 
                   WHERE status IN ('pending', 'assigned', 'in_progress')
               ) THEN 1 END) as vehicles_available
        FROM vehicles v
    """
    vehicles_data =db .execute_query (vehicles_query )

    rides_query ="""
        SELECT 
            COUNT(*) as total_rides,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_rides,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as active_rides,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_rides,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_rides
        FROM ride_requests
    """
    rides_data =db .execute_query (rides_query )

    return jsonify ({
    'vehicles':vehicles_data [0 ]if vehicles_data else {},
    'rides':rides_data [0 ]if rides_data else {}
    })



@app .route ('/api/vehicles/<int:vehicle_id>/command',methods =['POST'])
def send_vehicle_command (vehicle_id ):
    """
    Send command to autonomous vehicle.
    Returns network packet format for vehicle communication.
    """
    data =request .json 
    command_type =data .get ('type')

    if command_type =='route':
        ride_id =data .get ('ride_id')
        if not ride_id :
            return jsonify ({'error':'ride_id required'}),400 


        ride_query ="""
            SELECT r.*, rt.*, rt.id as route_id
            FROM rides r
            LEFT JOIN routes rt ON r.id = rt.ride_id
            WHERE r.id = %s AND r.assigned_vehicle_id = %s
        """
        result =db .execute_query (ride_query ,(ride_id ,vehicle_id ))

        if not result :
            return jsonify ({'error':'Ride not found or not assigned to this vehicle'}),404 

        r =result [0 ]
        route =Route .from_dict (r )


        packet ={
        'type':'route_command',
        'vehicle_id':vehicle_id ,
        'ride_id':ride_id ,
        'timestamp':datetime .now ().isoformat (),
        'route':{
        'start':{
        'latitude':route .start_lat ,
        'longitude':route .start_lng ,
        'address':route .start_address 
        },
        'destination':{
        'latitude':route .dest_lat ,
        'longitude':route .dest_lng ,
        'address':route .destination_address 
        },
        'waypoints':route .waypoints ,
        'estimated_duration_minutes':route .estimated_duration_min ,
        'distance_km':route .distance_km 
        },
        'priority':'normal'
        }

        return jsonify (packet )

    return jsonify ({'error':'Invalid command type'}),400 

if __name__ =='__main__':
    if not db .get_connection ():
        db .connect ()
    else :
        try :
            db .connection .ping (reconnect =True )
        except Exception :
            db .connect ()
    start_simulator ()
    resume_active_rides ()
    app .run (debug =True ,host ='0.0.0.0',port =5000 )