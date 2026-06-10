def _serialize_ts (value ):
    if value is None :
        return None 
    if hasattr (value ,'isoformat'):
        try :
            return value .isoformat ()
        except Exception :
            return None 
    return str (value )


class Ride :
    def __init__ (self ,ride_id =None ,user_id =None ,status ='pending',
    vehicle_id =None ,comfort_level ='standard',
    shared_ride =False ,estimated_price_cents =None ,
    pickup_address =None ,dropoff_address =None ,
    pickup_latitude =None ,pickup_longitude =None ,
    dropoff_latitude =None ,dropoff_longitude =None ,
    estimated_distance_km =None ,estimated_duration_minutes =None ,
    requested_pickup_time =None ,passenger_count =1 ,
    actual_pickup_time =None ,actual_dropoff_time =None ,
    created_at =None ,updated_at =None ):
        self .ride_id =ride_id 
        self .user_id =user_id 
        self .status =status 
        self .vehicle_id =vehicle_id 
        self .comfort_level =comfort_level 
        self .shared_ride =shared_ride 
        self .estimated_price_cents =estimated_price_cents 
        self .pickup_address =pickup_address 
        self .dropoff_address =dropoff_address 
        self .pickup_latitude =pickup_latitude 
        self .pickup_longitude =pickup_longitude 
        self .dropoff_latitude =dropoff_latitude 
        self .dropoff_longitude =dropoff_longitude 
        self .estimated_distance_km =estimated_distance_km 
        self .estimated_duration_minutes =estimated_duration_minutes 
        self .requested_pickup_time =requested_pickup_time 
        self .passenger_count =passenger_count 
        self .actual_pickup_time =actual_pickup_time 
        self .actual_dropoff_time =actual_dropoff_time 
        self .created_at =created_at 
        self .updated_at =updated_at 

    def to_dict (self ):
        req_pt =self .requested_pickup_time 
        if req_pt is not None and hasattr (req_pt ,'isoformat'):
            req_pt =req_pt .isoformat ()
        return {
        'id':self .ride_id ,
        'user_id':self .user_id ,
        'status':self .status ,
        'vehicle_id':self .vehicle_id ,
        'comfort_level':self .comfort_level ,
        'shared_ride':self .shared_ride ,
        'estimated_price_cents':self .estimated_price_cents ,
        'pickup_address':self .pickup_address ,
        'dropoff_address':self .dropoff_address ,
        'pickup_latitude':float (self .pickup_latitude )if self .pickup_latitude is not None else None ,
        'pickup_longitude':float (self .pickup_longitude )if self .pickup_longitude is not None else None ,
        'dropoff_latitude':float (self .dropoff_latitude )if self .dropoff_latitude is not None else None ,
        'dropoff_longitude':float (self .dropoff_longitude )if self .dropoff_longitude is not None else None ,
        'estimated_distance_km':float (self .estimated_distance_km )if self .estimated_distance_km is not None else None ,
        'estimated_duration_minutes':self .estimated_duration_minutes ,
        'requested_pickup_time':req_pt ,
        'passenger_count':self .passenger_count ,
        'actual_pickup_time':_serialize_ts (self .actual_pickup_time ),
        'actual_dropoff_time':_serialize_ts (self .actual_dropoff_time ),
        'created_at':self .created_at .isoformat ()if self .created_at else None ,
        'updated_at':self .updated_at .isoformat ()if self .updated_at else None 
        }

    @classmethod 
    def from_dict (cls ,data ):
        return cls (
        ride_id =data .get ('id'),
        user_id =data .get ('user_id'),
        status =data .get ('status','pending'),
        vehicle_id =data .get ('vehicle_id'),
        comfort_level =data .get ('comfort_level','standard'),
        shared_ride =data .get ('shared_ride',False ),
        estimated_price_cents =data .get ('estimated_price_cents'),
        pickup_address =data .get ('pickup_address'),
        dropoff_address =data .get ('dropoff_address'),
        pickup_latitude =data .get ('pickup_latitude'),
        pickup_longitude =data .get ('pickup_longitude'),
        dropoff_latitude =data .get ('dropoff_latitude'),
        dropoff_longitude =data .get ('dropoff_longitude'),
        estimated_distance_km =data .get ('estimated_distance_km'),
        estimated_duration_minutes =data .get ('estimated_duration_minutes'),
        requested_pickup_time =data .get ('requested_pickup_time'),
        passenger_count =data .get ('passenger_count',1 ),
        actual_pickup_time =data .get ('actual_pickup_time'),
        actual_dropoff_time =data .get ('actual_dropoff_time'),
        created_at =data .get ('created_at'),
        updated_at =data .get ('updated_at')
        )
