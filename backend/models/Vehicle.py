from .VehicleTemplate import VehicleTemplate 

class Vehicle :
    def __init__ (self ,vehicle_id =None ,vehicle_template_id =None ,
    template =None ,current_latitude =None ,current_longitude =None ,
    battery_level =100 ,status ='available'):
        self .vehicle_id =vehicle_id 
        self .vehicle_template_id =vehicle_template_id 
        self .template =template 
        self .current_latitude =current_latitude 
        self .current_longitude =current_longitude 
        self .battery_level =battery_level if battery_level is not None else 100 
        self .status =status 

    def to_dict (self ):
        return {
        'id':self .vehicle_id ,
        'vehicle_template_id':self .vehicle_template_id ,
        'current_latitude':self .current_latitude ,
        'current_longitude':self .current_longitude ,
        'battery_level':self .battery_level ,
        'status':self .status ,
        'template':self .template .to_dict ()if self .template else None 
        }

    @classmethod 
    def from_dict (cls ,data ):
        template =None 
        if data .get ('template'):
            template =VehicleTemplate .from_dict (data ['template'])

        return cls (
        vehicle_id =data .get ('id'),
        vehicle_template_id =data .get ('vehicle_template_id'),
        template =template ,
        current_latitude =data .get ('current_latitude'),
        current_longitude =data .get ('current_longitude'),
        battery_level =data .get ('battery_level')if data .get ('battery_level')is not None else 100 ,
        status =data .get ('status','available')
        )

    def consume_battery (self ,distance_km ):
        """Consume battery based on distance traveled"""
        if not self .template or not self .template .consumption_kwh_per_km :

            consumption_rate =0.2 
        else :
            consumption_rate =self .template .consumption_kwh_per_km 

        energy_used =distance_km *consumption_rate 

        if self .template and self .template .battery_capacity_kwh :
            battery_percentage_used =(energy_used /self .template .battery_capacity_kwh )*100 
        else :

            battery_percentage_used =(energy_used /100 )*100 

        self .battery_level =max (0 ,self .battery_level -battery_percentage_used )

        return self .battery_level 

    def needs_charging (self ):
        """Check if vehicle needs charging (below 20%)"""
        return self .battery_level <20 
