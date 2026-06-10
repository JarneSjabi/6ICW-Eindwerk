from datetime import datetime 

class VehicleLocationReport :
    def __init__ (self ,report_id =None ,vehicle_id =None ,latitude =None ,
    longitude =None ,created_at =None ):
        self .report_id =report_id 
        self .vehicle_id =vehicle_id 
        self .latitude =latitude 
        self .longitude =longitude 
        self .created_at =created_at or datetime .now ()

    def to_dict (self ):
        return {
        'id':self .report_id ,
        'vehicle_id':self .vehicle_id ,
        'latitude':self .latitude ,
        'longitude':self .longitude ,
        'created_at':self .created_at .isoformat ()if isinstance (self .created_at ,datetime )else self .created_at 
        }

    @classmethod 
    def from_dict (cls ,data ):
        return cls (
        report_id =data .get ('id'),
        vehicle_id =data .get ('vehicle_id'),
        latitude =data .get ('latitude'),
        longitude =data .get ('longitude'),
        created_at =data .get ('created_at')
        )
