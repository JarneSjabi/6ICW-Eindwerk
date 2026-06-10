class ChargingStation :
    def __init__ (self ,station_id =None ,name =None ,latitude =None ,longitude =None ,address =None ,charger_type ='fast',max_power_kw =150 ,available_slots =4 ):
        self .station_id =station_id 
        self .name =name 
        self .latitude =latitude 
        self .longitude =longitude 
        self .address =address 
        self .charger_type =charger_type 
        self .max_power_kw =max_power_kw 
        self .available_slots =available_slots 

    def to_dict (self ):
        return {
        'id':self .station_id ,
        'name':self .name ,
        'latitude':self .latitude ,
        'longitude':self .longitude ,
        'address':self .address ,
        'charger_type':self .charger_type ,
        'max_power_kw':self .max_power_kw ,
        'available_slots':self .available_slots 
        }

    @classmethod 
    def from_dict (cls ,data ):
        return cls (
        station_id =data .get ('id'),
        name =data .get ('name'),
        latitude =data .get ('latitude'),
        longitude =data .get ('longitude'),
        address =data .get ('address'),
        charger_type =data .get ('charger_type','fast'),
        max_power_kw =data .get ('max_power_kw',150 ),
        available_slots =data .get ('available_slots',4 )
        )