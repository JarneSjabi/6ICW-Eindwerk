import requests 
from typing import Optional ,Tuple ,List 
from models .Route import Route 

class RouteService :
    """Service for route planning using OpenStreetMap Nominatim and OSRM"""

    def __init__ (self ):
        self .nominatim_url ="https://nominatim.openstreetmap.org/search"
        self .osrm_url ="http://router.project-osrm.org/route/v1/driving"

    def geocode_address (self ,address :str )->Optional [Tuple [float ,float ]]:
        """
        Geocode an address to coordinates using OpenStreetMap Nominatim API.
        Returns (latitude, longitude) or None if not found.
        """
        try :
            params ={
            'q':address ,
            'format':'json',
            'limit':1 
            }
            headers ={
            'User-Agent':'AutonomousMobilityPlatform/1.0'
            }
            response =requests .get (self .nominatim_url ,params =params ,headers =headers ,timeout =5 )
            response .raise_for_status ()
            data =response .json ()

            if data and len (data )>0 :
                lat =float (data [0 ]['lat'])
                lon =float (data [0 ]['lon'])
                return (lat ,lon )
        except Exception as e :
            print (f"Error geocoding address {address }: {e }")
        return None 

    def get_route (self ,start_lat :float ,start_lng :float ,
    dest_lat :float ,dest_lng :float )->Optional [dict ]:
        """
        Get route from OSRM routing service.
        Returns route data with waypoints, distance, and duration.
        """
        try :
            url =f"{self .osrm_url }/{start_lng },{start_lat };{dest_lng },{dest_lat }"
            params ={
            'overview':'full',
            'geometries':'geojson',
            'steps':'true',
            'annotations':'true'
            }
            response =requests .get (url ,params =params ,timeout =10 )
            response .raise_for_status ()
            data =response .json ()

            if data .get ('code')=='Ok'and data .get ('routes'):
                route =data ['routes'][0 ]
                geometry =route .get ('geometry',{}).get ('coordinates',[])


                waypoints =[[coord [1 ],coord [0 ]]for coord in geometry ]

                distance_km =route .get ('distance',0 )/1000 
                duration_sec =route .get ('duration',0 )
                duration_min =duration_sec /60 

                return {
                'waypoints':waypoints ,
                'distance_km':round (distance_km ,2 ),
                'duration_min':round (duration_min ,2 ),
                'duration_sec':duration_sec
                }
        except Exception as e :
            print (f"Error getting route: {e }")
        return None 

    def create_route (self ,start_address :str =None ,destination_address :str =None ,
    start_lat :float =None ,start_lng :float =None ,
    dest_lat :float =None ,dest_lng :float =None )->Optional [Route ]:
        """
        Create a complete route either from addresses or from provided coordinates.
        If coordinates are passed, those are used directly. Returns Route object with all details.
        """

        if start_lat is not None and start_lng is not None and dest_lat is not None and dest_lng is not None :

            if not start_address :
                start_address =f"LatLng: {start_lat :.6f},{start_lng :.6f}"
            if not destination_address :
                destination_address =f"LatLng: {dest_lat :.6f},{dest_lng :.6f}"
        else :

            if not start_address or not destination_address :
                return None 
            start_coords =self .geocode_address (start_address )
            dest_coords =self .geocode_address (destination_address )
            if not start_coords or not dest_coords :
                return None 
            start_lat ,start_lng =start_coords 
            dest_lat ,dest_lng =dest_coords 


        route_data =self .get_route (start_lat ,start_lng ,dest_lat ,dest_lng )

        if not route_data :

            from algorithms .dijkstra import calculate_distance 
            distance_km =calculate_distance (start_lat ,start_lng ,dest_lat ,dest_lng )
            route_data ={
            'waypoints':[[start_lat ,start_lng ],[dest_lat ,dest_lng ]],
            'distance_km':distance_km ,
            'duration_min':(distance_km /50 )*60 
            }

        route =Route (
        start_address =start_address ,
        destination_address =destination_address ,
        start_lat =start_lat ,
        start_lng =start_lng ,
        dest_lat =dest_lat ,
        dest_lng =dest_lng ,
        waypoints =route_data ['waypoints'],
        distance_km =route_data ['distance_km'],
        estimated_duration_min =route_data ['duration_min']
        )

        return route 
