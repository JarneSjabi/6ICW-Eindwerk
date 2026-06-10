class Route :
    def __init__ (self ,route_id =None ,ride_id =None ,start_address =None ,
    destination_address =None ,start_lat =None ,start_lng =None ,
    dest_lat =None ,dest_lng =None ,waypoints =None ,
    distance_km =None ,estimated_duration_min =None ,created_at =None ,updated_at =None ):
        self .route_id =route_id 
        self .ride_id =ride_id 
        self .start_address =start_address 
        self .destination_address =destination_address 
        self .start_lat =start_lat 
        self .start_lng =start_lng 
        self .dest_lat =dest_lat 
        self .dest_lng =dest_lng 
        self .waypoints =waypoints or []
        self .distance_km =distance_km 
        self .estimated_duration_min =estimated_duration_min 
        self .created_at =created_at 
        self .updated_at =updated_at 

    def to_dict (self ):
        return {
        'id':self .route_id ,
        'ride_id':self .ride_id ,
        'start_address':self .start_address ,
        'destination_address':self .destination_address ,
        'start_lat':self .start_lat ,
        'start_lng':self .start_lng ,
        'dest_lat':self .dest_lat ,
        'dest_lng':self .dest_lng ,
        'waypoints':self .waypoints ,
        'distance_km':self .distance_km ,
        'estimated_duration_min':self .estimated_duration_min ,
        'created_at':self .created_at .isoformat ()if self .created_at else None ,
        'updated_at':self .updated_at .isoformat ()if self .updated_at else None 
        }

    @classmethod 
    def from_dict (cls ,data ):
        return cls (
        route_id =data .get ('id'),
        ride_id =data .get ('ride_id'),
        start_address =data .get ('start_address'),
        destination_address =data .get ('destination_address'),
        start_lat =data .get ('start_lat'),
        start_lng =data .get ('start_lng'),
        dest_lat =data .get ('dest_lat'),
        dest_lng =data .get ('dest_lng'),
        waypoints =data .get ('waypoints',[]),
        distance_km =data .get ('distance_km'),
        estimated_duration_min =data .get ('estimated_duration_min'),
        created_at =data .get ('created_at'),
        updated_at =data .get ('updated_at')
        )
