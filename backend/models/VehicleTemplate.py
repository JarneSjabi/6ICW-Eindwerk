class VehicleTemplate :
    def __init__ (self ,template_id =None ,name =None ,description =None ,brand =None ,model =None ,capacity =4 ,max_range_km =None ,battery_capacity_kwh =None ,consumption_kwh_per_km =None ,charging_time_0_to_100_min =30 ):
        self .template_id =template_id 
        self .name =name 
        self .description =description 
        self .brand =brand 
        self .model =model 
        self .capacity =capacity 
        self .max_range_km =max_range_km 
        self .battery_capacity_kwh =battery_capacity_kwh 
        self .consumption_kwh_per_km =consumption_kwh_per_km 
        self .charging_time_0_to_100_min =charging_time_0_to_100_min 

    def to_dict (self ):
        return {
        'id':self .template_id ,
        'name':self .name ,
        'description':self .description ,
        'brand':self .brand ,
        'model':self .model ,
        'capacity':self .capacity ,
        'max_range_km':self .max_range_km ,
        'battery_capacity_kwh':self .battery_capacity_kwh ,
        'consumption_kwh_per_km':self .consumption_kwh_per_km ,
        'charging_time_0_to_100_min':self .charging_time_0_to_100_min 
        }

    @classmethod 
    def from_dict (cls ,data ):
        return cls (
        template_id =data .get ('id'),
        name =data .get ('name'),
        description =data .get ('description'),
        brand =data .get ('brand'),
        model =data .get ('model'),
        capacity =data .get ('capacity',4 ),
        max_range_km =data .get ('max_range_km'),
        battery_capacity_kwh =data .get ('battery_capacity_kwh'),
        consumption_kwh_per_km =data .get ('consumption_kwh_per_km'),
        charging_time_0_to_100_min =data .get ('charging_time_0_to_100_min',30 )
        )
