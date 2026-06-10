import heapq 
from typing import Dict ,List ,Tuple ,Optional 

class Graph :
    """Graph representation for Dijkstra's algorithm"""
    def __init__ (self ):
        self .nodes ={}

    def add_node (self ,node_id :str ):
        if node_id not in self .nodes :
            self .nodes [node_id ]={}

    def add_edge (self ,from_node :str ,to_node :str ,weight :float ,bidirectional :bool =True ):
        """Add an edge between two nodes with a weight"""
        self .add_node (from_node )
        self .add_node (to_node )
        self .nodes [from_node ][to_node ]=weight 
        if bidirectional :
            self .nodes [to_node ][from_node ]=weight 

    def get_neighbors (self ,node_id :str )->Dict [str ,float ]:
        """Get all neighbors of a node with their weights"""
        return self .nodes .get (node_id ,{})

def dijkstra (graph :Graph ,start :str ,end :str )->Tuple [Optional [List [str ]],Optional [float ]]:
    """
    Dijkstra's algorithm to find shortest path between start and end nodes.
    Returns (path, total_distance) or (None, None) if no path exists.
    """
    if start not in graph .nodes or end not in graph .nodes :
        return None ,None 


    pq =[(0 ,start ,[start ])]
    visited =set ()
    distances ={start :0 }

    while pq :
        current_dist ,current_node ,path =heapq .heappop (pq )

        if current_node in visited :
            continue 

        visited .add (current_node )

        if current_node ==end :
            return path ,current_dist 

        for neighbor ,weight in graph .get_neighbors (current_node ).items ():
            if neighbor in visited :
                continue 

            new_dist =current_dist +weight 

            if neighbor not in distances or new_dist <distances [neighbor ]:
                distances [neighbor ]=new_dist 
                heapq .heappush (pq ,(new_dist ,neighbor ,path +[neighbor ]))

    return None ,None 

def calculate_distance (lat1 :float ,lon1 :float ,lat2 :float ,lon2 :float )->float :
    """
    Calculate distance between two coordinates using Haversine formula.
    Returns distance in kilometers.
    """
    from math import radians ,sin ,cos ,sqrt ,atan2 

    R =6371 

    lat1_rad =radians (lat1 )
    lat2_rad =radians (lat2 )
    delta_lat =radians (lat2 -lat1 )
    delta_lon =radians (lon2 -lon1 )

    a =sin (delta_lat /2 )**2 +cos (lat1_rad )*cos (lat2_rad )*sin (delta_lon /2 )**2 
    c =2 *atan2 (sqrt (a ),sqrt (1 -a ))

    return R *c 
