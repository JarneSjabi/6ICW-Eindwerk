from typing import List ,Optional ,Tuple 
from models .Vehicle import Vehicle 
from models .Ride import Ride 
from models .Route import Route 
from models .ChargingStation import ChargingStation 
from algorithms .dijkstra import calculate_distance 
from services .ev_charger_service import EVChargerService 
import math 

def assign_vehicle_to_ride (available_vehicles :List [Vehicle ],ride :Ride ,route :Route )->Optional [Tuple [Vehicle ,float ]]:
    """
    Assign the best available vehicle to a ride based on:
    - Closest vehicle to pickup
    - Route efficiency for the full trip
    - Required passenger capacity
    - Battery state and available range

    Returns (Vehicle, estimated_pickup_time_minutes) or None if no vehicle available
    """
    if not available_vehicles or not route :
        return None 

    best_vehicle =None 
    best_score =float ('inf')
    best_tie_breaker =float ('inf')
    best_pickup_time =0 
    required_seats =ride .passenger_count or 1 
    route_distance =float (route .distance_km or 0.0 )

    for vehicle in available_vehicles :
        if vehicle .current_latitude is None or vehicle .current_longitude is None :
            continue 

        tpl_cap =vehicle .template .capacity if vehicle .template else 4 
        if tpl_cap <required_seats :
            continue 

        if vehicle .status !='available':
            continue 

        battery_percent =float (vehicle .battery_level if vehicle .battery_level is not None else 0 )
        if battery_percent <=10 :
            continue 

        consumption_rate =float (vehicle .template .consumption_kwh_per_km if vehicle .template and vehicle .template .consumption_kwh_per_km else 0.2 )
        battery_capacity =float (vehicle .template .battery_capacity_kwh if vehicle .template and vehicle .template .battery_capacity_kwh else 100.0 )
        available_range =(battery_capacity *(battery_percent /100.0 ))/consumption_rate 

        distance_to_pickup =calculate_distance (
        float (vehicle .current_latitude ),
        float (vehicle .current_longitude ),
        float (route .start_lat ),
        float (route .start_lng )
        )

        estimated_pickup_time =(distance_to_pickup /40.0 )*60.0 
        total_trip_km =distance_to_pickup +route_distance 

        if total_trip_km <=0 :
            continue 

        buffer_km =6.0 
        if available_range <total_trip_km +buffer_km :
            continue 

        battery_penalty =0.0 
        if battery_percent <40 :
            battery_penalty =(40.0 -battery_percent )*0.75 

        range_margin =max (0.0 ,available_range -total_trip_km )
        range_penalty =0.0 if range_margin >=15.0 else (15.0 -range_margin )*1.2 

        score =distance_to_pickup
        tie_breaker =battery_penalty +range_penalty

        if (
            best_vehicle is None
            or score <best_score
            or (math.isclose(score ,best_score ,rel_tol =1e-6 ) and tie_breaker <best_tie_breaker)
        ):
            best_score =score 
            best_tie_breaker =tie_breaker
            best_vehicle =vehicle 
            best_pickup_time =estimated_pickup_time 

    if best_vehicle :
        return (best_vehicle ,best_pickup_time )
    return None 

def calculate_ride_price (route :Route ,comfort_level :str ,sharing_preference :str )->float :
    """
    Calculate ride price based on:
    - Distance
    - Comfort level
    - Sharing preference
    
    Returns price in euros
    """
    if not route or not route .distance_km :
        return 0.0 

    base_price_per_km =1.5 


    comfort_multipliers ={
    'basic':0.8 ,
    'standard':1.0 ,
    'premium':1.5 
    }


    sharing_multipliers ={
    'none':1.0 ,
    'shared':0.7 ,
    'preferred':0.6 
    }

    multiplier =comfort_multipliers .get (comfort_level ,1.0 )*sharing_multipliers .get (sharing_preference ,1.0 )

    price =route .distance_km *base_price_per_km *multiplier 


    return max (price ,5.0 )

def find_best_charging_station (vehicle :Vehicle ,charging_stations :List [ChargingStation ],ride_requests :List [Ride ],radius_km :float =10.0 )->Optional [ChargingStation ]:
    """
    Find the best charging station for a vehicle to return to, based on:
    - Distance from current vehicle location
    - Density of pending ride requests in the area around the station
    
    If no charging_stations provided, fetches from API
    """
    if not charging_stations :

        charger_service =EVChargerService ()
        charging_stations =charger_service .get_charging_stations (
        latitude =vehicle .current_latitude ,
        longitude =vehicle .current_longitude ,
        distance =50 ,
        limit =20 
        )

    if not charging_stations :
        return None 

    best_station =None 
    best_score =float ('-inf')

    for station in charging_stations :

        distance_to_station =calculate_distance (
        vehicle .current_latitude ,
        vehicle .current_longitude ,
        station .latitude ,
        station .longitude 
        )


        ride_density =0 
        for ride in ride_requests :
            if ride .status =='pending':
                distance_to_ride =calculate_distance (
                station .latitude ,
                station .longitude ,
                ride .pickup_latitude ,
                ride .pickup_longitude 
                )
                if distance_to_ride <=radius_km :
                    ride_density +=1 


        score =ride_density -(distance_to_station *0.1 )

        if score >best_score :
            best_score =score 
            best_station =station 

    return best_station 
