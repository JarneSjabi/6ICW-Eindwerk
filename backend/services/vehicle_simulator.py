"""
Vehicle Simulation Service
Simulates vehicles moving along assigned routes and reports their location
"""
import threading 
import time 
from datetime import datetime 
from database import Database 
import math 
import json 
from services.ev_charger_service import EVChargerService 
from services.route_service import RouteService 


def _haversine_km(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    r_earth = 6371.0 
    lat1_rad, lat2_rad = math.radians(lat1), math.radians(lat2)
    dlat, dlon = math.radians(lat2 - lat1), math.radians(lon2 - lon1)
    a = math.sin(dlat / 2) ** 2 + math.cos(lat1_rad) * math.cos(lat2_rad) * math.sin(dlon / 2) ** 2 
    c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a))
    return r_earth * c 


def load_charger_candidates_near(latitude: float, longitude: float, **kwargs):
    """Load nearby charger candidates using EVChargerService, preferring cache first."""
    try:
        service = EVChargerService()
        distance = float(kwargs.get('distance', 20.0))
        cached = service.get_cached_charging_stations(
            latitude=float(latitude),
            longitude=float(longitude),
            distance=distance,
            level=kwargs.get('level')
        )
        if cached:
            return cached 
        return service.get_charging_stations(
            latitude=float(latitude),
            longitude=float(longitude),
            distance=distance,
            level=kwargs.get('level')
        )
    except Exception as e:
        print(f"[strategic_idle] load_charger_candidates_near failed: {e}")
        return []


def pick_strategic_charger(lat: float, lng: float, candidates: list, db=None, route_service: RouteService = None, **kwargs):
    """Pick a strategic charger from candidate stations. Prefer actual travel time when available."""
    if not candidates:
        return lat, lng, None 

    route_service = route_service or RouteService()
    best = None 
    best_score = float('inf')

    for candidate in candidates:
        if candidate is None:
            continue 
        score = None 
        try:
            route = route_service.get_route(lat, lng, float(candidate.latitude), float(candidate.longitude))
            if route and route.get('duration_sec') is not None:
                score = float(route['duration_sec'])
            elif route and route.get('distance_km') is not None:
                score = float(route['distance_km'])
        except Exception:
            score = None 

        if score is None:
            score = _haversine_km(lat, lng, float(candidate.latitude), float(candidate.longitude))

        if score < best_score:
            best_score = score
            best = candidate 

    if best is None:
        return lat, lng, None 

    try:
        return float(best.latitude), float(best.longitude), best 
    except Exception as e:
        print(f"[strategic_idle] pick_strategic_charger fallback failed: {e}")
        return lat, lng, None 


class VehicleSimulator:
    """Simulates vehicle movement and location reporting"""

    # Important: Speed and Max Charge
    IDLE_CHARGE_TARGET_PCT = 99.9
    SIMULATED_SPEED_KMH = 70.0          # CONSTANT SPEED
    MAX_SIMULATED_SPEED_KMH = 180.0

    CRITICAL_BATTERY_PCT = 15 
    MIN_CHARGE_AT_STOP_PCT = 70 
    CHARGER_SEARCH_DISTANCE_KM = 15 
    MINIMUM_SAFE_BATTERY_PCT = 10 

    MAX_MOVEMENT_HISTORY_PER_VEHICLE = 2000 
    PICKUP_ARRIVAL_RADIUS_KM = 0.25 
    BATTERY_SAFETY_MARGIN_PCT = 5.0 

    def __init__(self, db: Database, update_interval: float = 5.0):
        """
        Initialize the vehicle simulator
        
        Args:
            db: Database instance
            update_interval: How often to update vehicle positions (in seconds)
        """
        self.db = db 
        self.update_interval = update_interval 
        self.active_rides = {}
        self.idle_vehicle_states = {}
        self.movement_history = {}
        self.ride_traces = {}
        self.last_battery_checks = {}
        self.simulation_thread = None 
        self.running = False 
        self.lock = threading.Lock()
        self.route_service = RouteService()

    def _update_vehicle_db_position(self, vehicle_id: int, lat: float, lng: float):
        """Keep vehicle state updated without relying on removed vehicle coordinate columns."""
        self.db.execute_update(
            "UPDATE vehicles SET updated_at = NOW() WHERE id = %s",
            (vehicle_id,),
        )

    def _resolve_vehicle_position(self, vehicle_id: int, start_lat=None, start_lng=None):
        """Best-known position: in-memory trail, then explicit start, then DB."""
        history = self.movement_history.get(vehicle_id, [])
        if history:
            last = history[-1]
            return float(last['lat']), float(last['lng'])
        if start_lat is not None and start_lng is not None:
            return float(start_lat), float(start_lng)
        row = self.db.execute_query(
            """
            SELECT latitude, longitude
            FROM vehicle_location_reports
            WHERE vehicle_id = %s
            ORDER BY id DESC
            LIMIT 1
            """,
            (vehicle_id,),
        )
        if row and row[0].get('latitude') is not None and row[0].get('longitude') is not None:
            return float(row[0]['latitude']), float(row[0]['longitude'])
        return start_lat, start_lng 

    def _record_battery_check(
        self, vehicle_id: int, *, ride_id=None, phase=None, lat: float, lng: float,
        battery_pct: int, remaining_distance_km: float, consumption_kwh_per_km: float,
        battery_capacity_kwh: float, can_reach_destination: bool, nearest_charger=None,
        active_charger_target=None, context: str = 'ride',
        distance_target_type=None, distance_target_label=None,
    ):
        """Store the exact check the simulator uses (shown on map battery layer)."""
        energy_needed_kwh = remaining_distance_km * consumption_kwh_per_km if remaining_distance_km is not None else None 
        battery_needed_pct = None 
        projected_remaining_pct = None 
        if energy_needed_kwh is not None and battery_capacity_kwh > 0:
            battery_needed_pct = round((energy_needed_kwh / battery_capacity_kwh) * 100, 2)
            projected_remaining_pct = round(battery_pct - battery_needed_pct, 2)
        self.last_battery_checks[vehicle_id] = {
            'vehicle_id': vehicle_id,
            'ride_id': ride_id,
            'phase': phase,
            'context': context,
            'checked_at': datetime.now().isoformat(),
            'latitude': float(lat),
            'longitude': float(lng),
            'battery_pct': battery_pct,
            'remaining_distance_km': round(remaining_distance_km, 3) if remaining_distance_km is not None else None,
            'distance_unit': 'km',
            'distance_target_type': distance_target_type,
            'distance_target_label': distance_target_label,
            'consumption_kwh_per_km': consumption_kwh_per_km,
            'battery_capacity_kwh': battery_capacity_kwh,
            'energy_needed_kwh': round(energy_needed_kwh, 3) if energy_needed_kwh is not None else None,
            'battery_needed_pct': battery_needed_pct,
            'projected_remaining_pct': projected_remaining_pct,
            'safety_margin_pct': self.BATTERY_SAFETY_MARGIN_PCT,
            'can_reach_destination': can_reach_destination,
            'formula': '(battery_pct - battery_needed_pct) > safety_margin_pct',
            'nearest_charger': nearest_charger,
            'active_charger_target': active_charger_target,
        }

    def _persist_vehicle_location(self, vehicle_id: int, lat: float, lng: float, ride_id=None) -> bool:
        """
        Single write path: memory trail, DB report, and vehicles.current_* stay in sync.
        Skips insert when position unchanged (prevents duplicate rows / false jumps).
        """
        lat, lng = float(lat), float(lng)
        history = self.movement_history.get(vehicle_id, [])
        if history:
            last = history[-1]
            if abs(last['lat'] - lat) < 1e-7 and abs(last['lng'] - lng) < 1e-7:
                # Position unchanged in-memory; still ensure vehicles.current_* stays in sync
                try:
                    self._update_vehicle_db_position(vehicle_id, lat, lng)
                except Exception:
                    pass
                return False 
        self.db.execute_update(
            """
            INSERT INTO vehicle_location_reports (vehicle_id, latitude, longitude, reported_at)
            VALUES (%s, %s, %s, NOW())
            """,
            (vehicle_id, lat, lng),
        )
        self._update_vehicle_db_position(vehicle_id, lat, lng)
        self._record_position(vehicle_id, lat, lng, ride_id)
        return True 

    def _seed_idle_charging_at(self, vehicle_id: int, lat: float, lng: float, batt: int):
        """Start idle charging from the vehicle's actual position (e.g. ride drop-off)."""
        try:
            candidates = load_charger_candidates_near(lat, lng, distance=self.CHARGER_SEARCH_DISTANCE_KM)
        except Exception as e:
            print(f"[strategic_idle] Charger lookup failed at drop-off: {e}")
            candidates = []
        try:
            t_lat, t_lng, charger_obj = pick_strategic_charger(lat, lng, candidates, db=self.db)
        except Exception as e:
            print(f"[strategic_idle] pick_strategic_charger failed at drop-off: {e}")
            t_lat, t_lng, charger_obj = lat, lng, None 
        charger_power_kw = getattr(charger_obj, 'max_power_kw', 22) if charger_obj else 22 
        charger_type = getattr(charger_obj, 'charger_type', 'slow') if charger_obj else 'slow'
        travel_waypoints = self._build_route_waypoints(float(lat), float(lng), float(t_lat), float(t_lng))
        travel_distance = self._estimate_polyline_distance(travel_waypoints)
        self.idle_vehicle_states[vehicle_id] = {
            'mode': 'travel',
            'current_lat': float(lat),
            'current_lng': float(lng),
            'target_lat': float(t_lat),
            'target_lng': float(t_lng),
            'charger_name': getattr(charger_obj, 'name', None) or 'Laadstation',
            'charger_address': getattr(charger_obj, 'address', None),
            'charger_power_kw': charger_power_kw,
            'charger_type': charger_type,
            'battery_level_float': float(batt),
            'travel_waypoints': travel_waypoints,
            'travel_distance_km': travel_distance,
            'travel_progress_km': 0.0,
        }
        print(
            f"[VehicleSimulator] Vehicle {vehicle_id} seeking charger from drop-off "
            f"({lat:.4f},{lng:.4f}) -> {getattr(charger_obj, 'name', 'Laadstation')}"
        )

    def start(self):
        """Start the vehicle simulation in a background thread"""
        if self.running:
            return 

        self.running = True 
        self.simulation_thread = threading.Thread(target=self._simulation_loop, daemon=True)
        self.simulation_thread.start()
        print("[VehicleSimulator] Simulation started")

    def stop(self):
        """Stop the vehicle simulation"""
        self.running = False 
        if self.simulation_thread:
            self.simulation_thread.join()
        print("[VehicleSimulator] Simulation stopped")

    def assign_ride_to_vehicle(self, ride_id: int, vehicle_id: int,
                               start_lat: float = None, start_lng: float = None,
                               pickup_lat: float = None, pickup_lng: float = None,
                               dropoff_lat: float = None, dropoff_lng: float = None,
                               route_waypoints: list = None, scheduled_start_time: str = None,
                               phase: str = None):
        """Register a ride for simulation"""
        with self.lock:
            for rid, data in list(self.active_rides.items()):
                if data.get('vehicle_id') == vehicle_id:
                    self.active_rides.pop(rid, None)
                    print(f"[VehicleSimulator] Cleared stale simulator ride {rid} for vehicle {vehicle_id}")
            self.idle_vehicle_states.pop(vehicle_id, None)
            print(f"[VehicleSimulator] Cleared idle/charging state for vehicle {vehicle_id} (ride {ride_id})")

            resolved_lat, resolved_lng = self._resolve_vehicle_position(vehicle_id, start_lat, start_lng)
            if resolved_lat is not None and resolved_lng is not None:
                start_lat, start_lng = resolved_lat, resolved_lng 

            waypoints = []
            if route_waypoints:
                waypoints = [list(map(float, wp)) for wp in route_waypoints]
            if start_lat is not None and start_lng is not None:
                start_point = [float(start_lat), float(start_lng)]
                pickup_point = [float(pickup_lat), float(pickup_lng)] if pickup_lat is not None and pickup_lng is not None else None 
                if pickup_point and (
                    not waypoints 
                    or self._coords_distance_km(waypoints[0][0], waypoints[0][1], pickup_point[0], pickup_point[1]) <= 0.2
                ):
                    to_pickup_waypoints = self._build_route_waypoints(
                        start_point[0], start_point[1], pickup_point[0], pickup_point[1]
                    )
                    if waypoints:
                        waypoints = self._dedupe_adjacent_waypoints(to_pickup_waypoints + waypoints[1:])
                    else:
                        waypoints = self._dedupe_adjacent_waypoints(to_pickup_waypoints)
                elif not waypoints or self._coords_distance_km(waypoints[0][0], waypoints[0][1], start_point[0], start_point[1]) > 0.05:
                    waypoints = [start_point] + waypoints 

            if not waypoints and pickup_lat is not None and pickup_lng is not None:
                waypoints = [[float(pickup_lat), float(pickup_lng)]]
            if dropoff_lat is not None and dropoff_lng is not None:
                end_pt = [float(dropoff_lat), float(dropoff_lng)]
                if not waypoints or self._coords_distance_km(waypoints[-1][0], waypoints[-1][1], end_pt[0], end_pt[1]) > 0.05:
                    waypoints.append(end_pt)

            pickup_point = [float(pickup_lat), float(pickup_lng)] if pickup_lat is not None and pickup_lng is not None else None 
            pickup_distance = self._distance_along_path_to_pickup(waypoints, pickup_point[0], pickup_point[1]) if pickup_point else 0.0 
            if phase is None:
                phase = 'scheduled' if scheduled_start_time else 'to_pickup'

            self.active_rides[ride_id] = {
                'vehicle_id': vehicle_id,
                'progress': 0.0,
                'phase': phase,
                'scheduled_start_time': scheduled_start_time,
                'route_waypoints': waypoints,
                'pickup_distance_km': pickup_distance,
                'previous_lat': None,
                'previous_lng': None,
                'distance_traveled': 0.0,
                'route_distance': None,
                'battery_level_float': None 
            }
        print(f"[VehicleSimulator] Assigned ride {ride_id} to vehicle {vehicle_id}")

    def get_vehicle_idle_activity(self, vehicle_id: int):
        """When status is available and not on a ride: charging / relocating / standby at charger."""
        with self.lock:
            if vehicle_id in {d['vehicle_id'] for d in self.active_rides.values()}:
                return None 
            st = self.idle_vehicle_states.get(vehicle_id)
            if not st:
                return None 
            mode = st.get('mode', 'travel')
            if mode == 'travel':
                return 'to_charger'
            if mode == 'charging':
                return 'charging'
            if mode == 'parked':
                return 'standby_at_charger'
        return None 

    def _record_position(self, vehicle_id: int, lat: float, lng: float, ride_id=None):
        """Store position in process memory for map trails and ride traces."""
        ts = datetime.now().isoformat()
        point = {'lat': float(lat), 'lng': float(lng), 'ts': ts, 'ride_id': ride_id}
        history = self.movement_history.setdefault(vehicle_id, [])
        if history:
            last = history[-1]
            if abs(last['lat'] - point['lat']) < 1e-7 and abs(last['lng'] - point['lng']) < 1e-7:
                return 
        history.append(point)
        if len(history) > self.MAX_MOVEMENT_HISTORY_PER_VEHICLE:
            del history[:-self.MAX_MOVEMENT_HISTORY_PER_VEHICLE]
        if ride_id is not None:
            trace = self.ride_traces.setdefault(ride_id, [])
            if not trace or abs(trace[-1]['lat'] - point['lat']) >= 1e-7 or abs(trace[-1]['lng'] - point['lng']) >= 1e-7:
                trace.append({'lat': point['lat'], 'lng': point['lng'], 'ts': ts})
                if len(trace) > self.MAX_MOVEMENT_HISTORY_PER_VEHICLE:
                    del trace[:-self.MAX_MOVEMENT_HISTORY_PER_VEHICLE]

    def get_movement_histories(self, vehicle_ids=None, limit: int = 500):
        """Return movement history per vehicle from in-memory store."""
        with self.lock:
            ids = vehicle_ids if vehicle_ids is not None else list(self.movement_history.keys())
            result = {}
            for vid in ids:
                try:
                    vid_int = int(vid)
                except (TypeError, ValueError):
                    continue 
                history = self.movement_history.get(vid_int, [])
                if history:
                    result[str(vid_int)] = [
                        [p['lat'], p['lng']] for p in history[-limit:]
                    ]
            return result 

    def get_ride_trace_paths(self, ride_ids=None, limit: int = 1000):
        """Return ridden path per ride from in-memory store."""
        with self.lock:
            ids = ride_ids if ride_ids is not None else list(self.ride_traces.keys())
            result = {}
            for rid in ids:
                try:
                    rid_int = int(rid)
                except (TypeError, ValueError):
                    continue 
                trace = self.ride_traces.get(rid_int, [])
                if trace:
                    result[str(rid_int)] = [
                        [p['lat'], p['lng']] for p in trace[-limit:]
                    ]
            return result 

    def get_vehicle_activity_detail(self, vehicle_id: int):
        """Rich activity info for map popups: phase, charger target, ride phase."""
        with self.lock:
            for ride_id, ride_data in self.active_rides.items():
                if ride_data.get('vehicle_id') != vehicle_id:
                    continue 
                phase = ride_data.get('phase')
                detail = {
                    'context': 'ride',
                    'ride_id': ride_id,
                    'phase': phase,
                }
                charger = ride_data.get('charger_stop_info')
                if charger:
                    detail['charger'] = {
                        'name': charger.get('name'),
                        'address': charger.get('address'),
                        'latitude': charger.get('latitude'),
                        'longitude': charger.get('longitude'),
                        'type': charger.get('type'),
                        'power_kw': charger.get('power_kw'),
                    }
                if phase == 'heading_to_charger':
                    detail['idle_activity'] = 'to_charger'
                elif phase == 'charging_at_stop':
                    detail['idle_activity'] = 'charging'
                return detail 

            st = self.idle_vehicle_states.get(vehicle_id)
            if not st:
                return None 
            mode = st.get('mode', 'travel')
            idle_activity = None 
            if mode == 'travel':
                idle_activity = 'to_charger'
            elif mode == 'charging':
                idle_activity = 'charging'
            elif mode == 'parked':
                idle_activity = 'standby_at_charger'
            detail = {
                'context': 'idle',
                'mode': mode,
                'idle_activity': idle_activity,
            }
            if st.get('target_lat') is not None and st.get('target_lng') is not None:
                detail['charger'] = {
                    'name': st.get('charger_name'),
                    'address': st.get('charger_address'),
                    'latitude': float(st['target_lat']),
                    'longitude': float(st['target_lng']),
                    'type': st.get('charger_type'),
                    'power_kw': st.get('charger_power_kw'),
                }
            return detail 

    def get_battery_reachability_snapshot(self):
        """Return last simulator battery checks (same values used for routing decisions)."""
        with self.lock:
            return [check.copy() for check in self.last_battery_checks.values()]

    def get_ride_progress(self, ride_id: int) -> dict:
        """Get current progress of a ride"""
        with self.lock:
            if ride_id in self.active_rides:
                result = self.active_rides[ride_id].copy()
                progress = result.get('progress')
                if progress is not None:
                    try:
                        result['progress_percent'] = float(progress) * 100.0
                    except (TypeError, ValueError):
                        result['progress_percent'] = 0.0
                else:
                    result['progress_percent'] = 0.0
                return result 
        return None 

    def _can_reach_destination(self, current_battery_pct: float, remaining_distance_km: float,
                               consumption_kwh_per_km: float, battery_capacity_kwh: float) -> bool:
        """Check if vehicle can reach destination with current battery and consumption rate"""
        energy_needed_kwh = remaining_distance_km * consumption_kwh_per_km 
        battery_needed_pct = (energy_needed_kwh / battery_capacity_kwh) * 100 
        return (current_battery_pct - battery_needed_pct) > self.BATTERY_SAFETY_MARGIN_PCT 

    def _seek_charger_for_ride(self, lat: float, lng: float) -> tuple:
        """Find nearest charger during active ride. Returns (charger_lat, charger_lng, charger_obj)"""
        try:
            candidates = load_charger_candidates_near(lat, lng, distance=self.CHARGER_SEARCH_DISTANCE_KM)
            if not candidates:
                return None, None, None 
            charger_lat, charger_lng, charger_obj = pick_strategic_charger(
                lat, lng, candidates, db=self.db, route_service=self.route_service
            )
            return charger_lat, charger_lng, charger_obj 
        except Exception as e:
            print(f"[VehicleSimulator] Mid-ride charger seek failed: {e}")
            return None, None, None 

    def _reselect_better_charger_target(self, current_lat: float, current_lng: float,
                                        current_target: dict):
        """Re-evaluate the active charger target from the vehicle's current location."""
        if not current_target:
            return None, None, None 

        try:
            route_to_current = self.route_service.get_route(
                current_lat, current_lng,
                float(current_target['latitude']),
                float(current_target['longitude'])
            )
            if not route_to_current or route_to_current.get('duration_sec') is None:
                return None, None, None 

            candidates = load_charger_candidates_near(
                current_lat, current_lng, distance=self.CHARGER_SEARCH_DISTANCE_KM
            )
            if not candidates:
                return None, None, None 

            best_lat, best_lng, best_obj = pick_strategic_charger(
                current_lat, current_lng, candidates, db=self.db, route_service=self.route_service
            )
            if not best_obj:
                return None, None, None 

            if (abs(best_lat - float(current_target['latitude'])) < 1e-5
                    and abs(best_lng - float(current_target['longitude'])) < 1e-5):
                return None, None, None 

            best_route = self.route_service.get_route(current_lat, current_lng, best_lat, best_lng)
            if not best_route or best_route.get('duration_sec') is None:
                return None, None, None 

            current_duration = float(route_to_current['duration_sec'])
            best_duration = float(best_route['duration_sec'])
            if best_duration < current_duration * 0.95:
                return best_lat, best_lng, best_obj 
        except Exception as e:
            print(f"[VehicleSimulator] Charger target re-evaluation failed: {e}")

        return None, None, None 

    def _insert_charger_stop(self, waypoints: list, current_lat: float, current_lng: float,
                             charger_lat: float, charger_lng: float, current_progress: float) -> list:
        """Insert charger stop into the waypoint list at current position in route using routed legs."""
        if not waypoints or len(waypoints) < 2:
            return waypoints 

        try:
            total_distance = self._estimate_polyline_distance(waypoints)
            current_distance = total_distance * current_progress 
            cumulative = 0.0 
            current_segment_idx = 0 

            for i in range(len(waypoints) - 1):
                segment_dist = self._calculate_distance(
                    float(waypoints[i][0]), float(waypoints[i][1]),
                    float(waypoints[i + 1][0]), float(waypoints[i + 1][1])
                )
                if cumulative + segment_dist >= current_distance:
                    current_segment_idx = i 
                    break 
                cumulative += segment_dist 

            tail = waypoints[current_segment_idx + 1:]

            to_charger = self._build_route_waypoints(
                float(current_lat), float(current_lng), float(charger_lat), float(charger_lng)
            )
            if tail:
                resume_lat, resume_lng = float(tail[0][0]), float(tail[0][1])
                from_charger = self._build_route_waypoints(
                    float(charger_lat), float(charger_lng), resume_lat, resume_lng
                )
                stitched = (
                    [to_charger[0]]
                    + to_charger[1:]
                    + from_charger[1:]
                    + tail[1:]
                )
            else:
                stitched = to_charger 
            return self._dedupe_adjacent_waypoints(stitched)
        except Exception as e:
            print(f"[VehicleSimulator] Failed to insert charger stop: {e}")
            return waypoints 

    def _trim_waypoints_to_current_location(self, waypoints: list, lat: float, lng: float, tolerance_km: float = 0.05) -> list:
        """Trim route waypoints so the active route starts at the vehicle's current location."""
        if not waypoints:
            return waypoints 

        for idx, wp in enumerate(waypoints):
            if self._calculate_distance(lat, lng, float(wp[0]), float(wp[1])) <= tolerance_km:
                trimmed = [[float(wp[0]), float(wp[1])]] + [
                    [float(next_wp[0]), float(next_wp[1])]
                    for next_wp in waypoints[idx + 1:]
                ]
                # Force first point to exactly current position to avoid repeated rebasing
                trimmed[0] = [float(lat), float(lng)]
                return trimmed
        return waypoints 

    def _build_route_waypoints(self, start_lat: float, start_lng: float, dest_lat: float, dest_lng: float) -> list:
        """Build road-following waypoints using route service; fallback to straight segment."""
        try:
            route = self.route_service.get_route(start_lat, start_lng, dest_lat, dest_lng)
            if route and route.get('waypoints'):
                points = [[float(p[0]), float(p[1])] for p in route['waypoints']]
                if len(points) >= 2:
                    return self._dedupe_adjacent_waypoints(points)
        except Exception as e:
            print(f"[VehicleSimulator] Route build failed, using fallback: {e}")
        return [[float(start_lat), float(start_lng)], [float(dest_lat), float(dest_lng)]]

    def _dedupe_adjacent_waypoints(self, waypoints: list, tolerance_km: float = 0.01) -> list:
        """Remove adjacent duplicate/near-duplicate points to keep interpolation stable."""
        if not waypoints:
            return []
        cleaned = [[float(waypoints[0][0]), float(waypoints[0][1])]]
        for wp in waypoints[1:]:
            lat, lng = float(wp[0]), float(wp[1])
            last_lat, last_lng = cleaned[-1]
            if self._calculate_distance(last_lat, last_lng, lat, lng) > tolerance_km:
                cleaned.append([lat, lng])
        if len(cleaned) == 1:
            cleaned.append([cleaned[0][0], cleaned[0][1]])
        return cleaned 

    def _simulation_loop(self):
        """Main simulation loop that runs in background thread"""
        while self.running:
            try:
                self._update_vehicle_positions()
            except Exception as e:
                print(f"[VehicleSimulator] Error in simulation loop: {e}")
            time.sleep(self.update_interval)

    def _update_vehicle_positions(self):
        """Update positions of all active vehicles"""
        with self.lock:
            rides_to_remove = []

            for ride_id, ride_data in list(self.active_rides.items()):
                try:
                    ride_query = """
                        SELECT r.id, r.vehicle_id, r.status, r.pickup_latitude, r.pickup_longitude,
                               r.dropoff_latitude, r.dropoff_longitude,
                               r.requested_pickup_time, rt.waypoints, rt.distance_km,
                               rt.estimated_duration_minutes, rt.scheduled_start_time, rt.status as route_status
                        FROM ride_requests r
                        LEFT JOIN routes rt ON r.id = rt.ride_id
                        WHERE r.id = %s
                    """
                    result = self.db.execute_query(ride_query, (ride_id,))

                    if not result:
                        rides_to_remove.append(ride_id)
                        continue 

                    ride_info = result[0]
                    vehicle_id = ride_data['vehicle_id']
                    status = ride_info.get('status')
                    route_status = ride_info.get('route_status')
                    scheduled_start_time = ride_info.get('scheduled_start_time')

                    if status in ('completed', 'cancelled'):
                        rides_to_remove.append(ride_id)
                        continue 

                    if ride_data.get('phase') == 'scheduled':
                        scheduled_start = self._coerce_datetime(scheduled_start_time) or self._coerce_datetime(
                            ride_info.get('requested_pickup_time')
                        )
                        if scheduled_start and datetime.now() < scheduled_start:
                            continue 
                        ride_data['phase'] = 'to_pickup'
                        self.db.execute_update(
                            "UPDATE vehicles SET status = %s, updated_at = NOW() WHERE id = %s",
                            ('in_use', vehicle_id),
                        )
                        print(f"[VehicleSimulator] Scheduled ride {ride_id}: vehicle {vehicle_id} heading to pickup")
                        if route_status == 'planned':
                            self.db.execute_update(
                                "UPDATE routes SET status = %s WHERE ride_id = %s",
                                ('active', ride_id),
                            )

                    waypoints = ride_data.get('route_waypoints') or ride_info.get('waypoints')

                    if not waypoints:
                        pickup_lat = float(ride_info.get('pickup_latitude')) if ride_info.get('pickup_latitude') else None 
                        pickup_lng = float(ride_info.get('pickup_longitude')) if ride_info.get('pickup_longitude') else None 
                        dropoff_lat = float(ride_info.get('dropoff_latitude')) if ride_info.get('dropoff_latitude') else None 
                        dropoff_lng = float(ride_info.get('dropoff_longitude')) if ride_info.get('dropoff_longitude') else None 
                        if pickup_lat is not None and pickup_lng is not None and dropoff_lat is not None and dropoff_lng is not None:
                            waypoints = [[pickup_lat, pickup_lng], [dropoff_lat, dropoff_lng]]
                        else:
                            continue 

                    if isinstance(waypoints, (bytes, bytearray)):
                        waypoints = waypoints.decode('utf-8', errors='ignore')
                    if isinstance(waypoints, str):
                        waypoints = json.loads(waypoints)

                    if isinstance(waypoints, list) and waypoints and isinstance(waypoints[0], dict):
                        waypoints = [[float(point.get('latitude')), float(point.get('longitude'))] for point in waypoints]

                    if ride_data.get('route_waypoints') is None:
                        ride_data['route_waypoints'] = waypoints 
                        if ride_data.get('battery_level_float') is None:
                            vehicle_query_init = """
                                SELECT v.battery_level
                                FROM vehicles v
                                WHERE v.id = %s
                            """
                            vehicle_result_init = self.db.execute_query(vehicle_query_init, (vehicle_id,))
                            if vehicle_result_init:
                                ride_data['battery_level_float'] = float(vehicle_result_init[0].get('battery_level', 100))

                    route_distance = ride_data.get('route_distance') if ride_data.get('route_distance') is not None else ride_info.get('distance_km')
                    route_duration_min = ride_info.get('estimated_duration_minutes')
                    if route_distance is None:
                        route_distance = self._estimate_polyline_distance(waypoints)
                    else:
                        try:
                            route_distance = float(route_distance)
                        except (TypeError, ValueError):
                            route_distance = self._estimate_polyline_distance(waypoints)

                    if route_distance <= 0:
                        route_distance = self._estimate_polyline_distance(waypoints)

                    current_distance = float(ride_data.get('distance_traveled', 0.0) or 0.0)
                    if route_distance < current_distance:
                        route_distance = current_distance

                    ride_data['route_distance'] = route_distance

                    # FORCE CONSTANT SPEED - ignore any estimated duration
                    simulated_speed_kmh = self.SIMULATED_SPEED_KMH

                    if ride_data.get('route_speed_kmh') is None:
                        ride_data['route_speed_kmh'] = simulated_speed_kmh

                    current_distance = ride_data.get('distance_traveled', 0.0)
                    if ride_data.get('phase') == 'charging_at_stop':
                        new_distance = current_distance 
                        new_progress = current_distance / route_distance if route_distance > 0 else 1.0 
                        charger_info = ride_data.get('charger_stop_info') or {}
                        current_lat = float(charger_info.get('latitude', waypoints[-1][0]))
                        current_lng = float(charger_info.get('longitude', waypoints[-1][1]))

                        if waypoints:
                            first_wp_lat = float(waypoints[0][0])
                            first_wp_lng = float(waypoints[0][1])
                            if self._calculate_distance(current_lat, current_lng, first_wp_lat, first_wp_lng) > 0.05:
                                waypoints = [[current_lat, current_lng]] + waypoints
                                ride_data['route_waypoints'] = waypoints 
                                ride_data['route_distance'] = self._estimate_polyline_distance(waypoints)
                                ride_data['distance_traveled'] = new_distance 
                                ride_data['progress'] = new_progress 
                                print(f"[VehicleSimulator] Vehicle {vehicle_id} rebased charge route to current charger location")
                    else:
                        distance_increment = simulated_speed_kmh * (self.update_interval / 3600.0)
                        new_distance = min(current_distance + distance_increment, route_distance)
                        new_progress = new_distance / route_distance if route_distance > 0 else 1.0 
                        current_lat, current_lng = self._interpolate_position(waypoints, new_progress)

                    self._persist_vehicle_location(vehicle_id, current_lat, current_lng, ride_id)

                    prev_lat = ride_data.get('previous_lat')
                    prev_lng = ride_data.get('previous_lng')
                    distance_traveled = 0.0 
                    if prev_lat is not None and prev_lng is not None:
                        distance_traveled = self._calculate_distance(prev_lat, prev_lng, current_lat, current_lng)
                    else:
                        start_lat, start_lng = waypoints[0]
                        distance_traveled = self._calculate_distance(start_lat, start_lng, current_lat, current_lng)

                    vehicle_query = """
                        SELECT v.battery_level, vt.consumption_kwh_per_km, vt.battery_capacity_kwh
                        FROM vehicles v
                        LEFT JOIN vehicle_templates vt ON v.vehicle_template_id = vt.id
                        WHERE v.id = %s
                    """
                    vehicle_result = self.db.execute_query(vehicle_query, (vehicle_id,))
                    if vehicle_result:
                        vehicle_data = vehicle_result[0]
                        consumption_rate = float(vehicle_data.get('consumption_kwh_per_km') or 0.2)
                        battery_capacity = float(vehicle_data.get('battery_capacity_kwh') or 100)

                        battery_level_float = ride_data.get('battery_level_float')
                        if battery_level_float is None:
                            battery_level_float = float(vehicle_data.get('battery_level', 100))
                            ride_data['battery_level_float'] = battery_level_float 

                        previous_battery_int = int(math.floor(battery_level_float))

                        if ride_data.get('phase') == 'charging_at_stop':
                            energy_used = 0.0 
                        else:
                            energy_used = distance_traveled * consumption_rate 
                        battery_used_percent = (energy_used / battery_capacity) * 100 
                        battery_level_float = max(0.0, battery_level_float - battery_used_percent)
                        ride_data['battery_level_float'] = battery_level_float 

                        new_battery_level_int = int(math.floor(battery_level_float))

                        if new_battery_level_int != previous_battery_int or new_progress >= 1.0:
                            self.db.execute_update(
                                "UPDATE vehicles SET battery_level = %s, updated_at = NOW() WHERE id = %s",
                                (new_battery_level_int, vehicle_id)
                            )
                            print(
                                f"[VehicleSimulator] Vehicle {vehicle_id} battery: {previous_battery_int}% -> "
                                f"{new_battery_level_int}% (traveled {distance_traveled:.3f} km, used {battery_used_percent:.4f}% @ {consumption_rate} kwh/km)"
                            )

                    ride_data['previous_lat'] = current_lat 
                    ride_data['previous_lng'] = current_lng 
                    ride_data['progress'] = new_progress 
                    ride_data['distance_traveled'] = new_distance 
                    ride_data['route_distance'] = route_distance 

                    if vehicle_result and ride_data.get('phase') in (
                        'in_transit', 'to_pickup', 'heading_to_charger', 'charging_at_stop',
                    ):
                        phase = ride_data.get('phase')
                        remaining_distance = route_distance - new_distance 
                        distance_target_type = 'ride_dropoff'
                        distance_target_label = 'Eindbestemming (resterende route)'
                        charger_stop_exists = ride_data.get('charger_stop_info') is not None 
                        target_charger = ride_data.get('charger_stop_info')

                        if phase == 'heading_to_charger' and target_charger:
                            better_lat, better_lng, better_obj = self._reselect_better_charger_target(
                                current_lat, current_lng, target_charger
                            )
                            if better_obj:
                                ride_data['charger_stop_info'] = {
                                    'latitude': float(better_lat),
                                    'longitude': float(better_lng),
                                    'name': getattr(better_obj, 'name', None) or 'Laadstation',
                                    'address': getattr(better_obj, 'address', None),
                                    'power_kw': getattr(better_obj, 'max_power_kw', 22),
                                    'type': getattr(better_obj, 'charger_type', 'slow'),
                                    'charge_target_pct': ride_data.get('charger_stop_info', {}).get('charge_target_pct', self.MIN_CHARGE_AT_STOP_PCT),
                                    'original_waypoints': ride_data.get('route_waypoints'),
                                }
                                # Preserve progress when inserting charger
                                old_route = ride_data.get('route_waypoints')
                                old_prog = ride_data.get('progress', 0.0)
                                new_waypoints = self._insert_charger_stop(
                                    old_route, current_lat, current_lng,
                                    better_lat, better_lng, old_prog
                                )
                                new_route_distance = self._estimate_polyline_distance(new_waypoints)
                                ride_data['route_waypoints'] = new_waypoints
                                ride_data['route_distance'] = new_route_distance
                                # Maintain same progress fraction along the new route
                                ride_data['distance_traveled'] = new_route_distance * old_prog
                                ride_data['progress'] = old_prog
                                ride_data['previous_lat'] = current_lat 
                                ride_data['previous_lng'] = current_lng 
                                target_charger = ride_data['charger_stop_info']
                                print(
                                    f"[VehicleSimulator] Vehicle {vehicle_id} switched mid-ride charger target to {getattr(better_obj, 'name', 'Laadstation')}"
                                )

                            remaining_distance = self._calculate_distance(
                                current_lat, current_lng,
                                float(target_charger['latitude']), float(target_charger['longitude']),
                            )
                            distance_target_type = 'charger_mid_ride'
                            distance_target_label = 'Laadstation (tussenstop)'
                        elif phase == 'charging_at_stop':
                            remaining_distance = 0.0 
                            distance_target_type = 'charger_stop'
                            distance_target_label = 'Aan laadstation (laden)'
                        elif phase == 'to_pickup':
                            distance_target_type = 'ride_dropoff'
                            distance_target_label = 'Eindbestemming (via ophaalpunt)'

                        current_batt_int = int(math.floor(battery_level_float))

                        can_reach = self._can_reach_destination(
                            current_batt_int, remaining_distance,
                            consumption_rate, battery_capacity
                        )
                        nearest_preview = None 
                        if target_charger and target_charger.get('latitude') is not None:
                            d_tgt = self._calculate_distance(
                                current_lat, current_lng,
                                float(target_charger['latitude']), float(target_charger['longitude']),
                            )
                            nearest_preview = {
                                'name': target_charger.get('name') or 'Laadstation',
                                'latitude': float(target_charger['latitude']),
                                'longitude': float(target_charger['longitude']),
                                'distance_km': round(d_tgt, 3),
                                'can_reach': self._can_reach_destination(
                                    current_batt_int, d_tgt, consumption_rate, battery_capacity
                                ),
                                'type': target_charger.get('type'),
                                'power_kw': target_charger.get('power_kw'),
                            }
                        elif ride_data.get('phase') in ('in_transit', 'to_pickup'):
                            c_lat, c_lng, c_obj = self._seek_charger_for_ride(current_lat, current_lng)
                            if c_lat is not None and c_lng is not None:
                                d_chg = self._calculate_distance(current_lat, current_lng, c_lat, c_lng)
                                nearest_preview = {
                                    'name': getattr(c_obj, 'name', None) or 'Laadstation',
                                    'latitude': float(c_lat),
                                    'longitude': float(c_lng),
                                    'distance_km': round(d_chg, 3),
                                    'can_reach': self._can_reach_destination(
                                        current_batt_int, d_chg, consumption_rate, battery_capacity
                                    ),
                                    'type': getattr(c_obj, 'charger_type', None),
                                    'power_kw': getattr(c_obj, 'max_power_kw', None),
                                }
                        self._record_battery_check(
                            vehicle_id,
                            ride_id=ride_id,
                            phase=phase,
                            lat=current_lat,
                            lng=current_lng,
                            battery_pct=current_batt_int,
                            remaining_distance_km=remaining_distance,
                            consumption_kwh_per_km=consumption_rate,
                            battery_capacity_kwh=battery_capacity,
                            can_reach_destination=can_reach,
                            nearest_charger=nearest_preview,
                            active_charger_target={
                                'name': target_charger.get('name'),
                                'latitude': target_charger.get('latitude'),
                                'longitude': target_charger.get('longitude'),
                            } if target_charger else None,
                            context='ride',
                            distance_target_type=distance_target_type,
                            distance_target_label=distance_target_label,
                        )

                        if (not can_reach and not charger_stop_exists
                                and ride_data.get('phase') == 'in_transit'):
                            charger_lat, charger_lng, charger_obj = self._seek_charger_for_ride(current_lat, current_lng)
                            if charger_lat is not None and charger_lng is not None and charger_obj:
                                # Preserve progress when inserting charger stop
                                old_route = ride_data.get('route_waypoints')
                                old_prog = ride_data.get('progress', 0.0)
                                new_waypoints = self._insert_charger_stop(
                                    old_route, current_lat, current_lng,
                                    charger_lat, charger_lng, old_prog
                                )
                                new_route_distance = self._estimate_polyline_distance(new_waypoints)

                                ride_data['charger_stop_info'] = {
                                    'latitude': float(charger_lat),
                                    'longitude': float(charger_lng),
                                    'name': getattr(charger_obj, 'name', None) or 'Laadstation',
                                    'address': getattr(charger_obj, 'address', None),
                                    'power_kw': getattr(charger_obj, 'max_power_kw', 22),
                                    'type': getattr(charger_obj, 'charger_type', 'slow'),
                                    'charge_target_pct': self.MIN_CHARGE_AT_STOP_PCT,
                                    'original_waypoints': ride_data.get('route_waypoints'),
                                }
                                ride_data['route_waypoints'] = new_waypoints
                                ride_data['phase'] = 'heading_to_charger'
                                ride_data['route_distance'] = new_route_distance
                                # Maintain same progress fraction along the new route
                                ride_data['distance_traveled'] = new_route_distance * old_prog
                                ride_data['progress'] = old_prog
                                ride_data['previous_lat'] = current_lat 
                                ride_data['previous_lng'] = current_lng 
                                print(
                                    f"[VehicleSimulator] Vehicle {vehicle_id} (Ride {ride_id}) battery critical ({current_batt_int}%) - "
                                    f"inserting charger stop. Charger: {charger_obj.name if hasattr(charger_obj, 'name') else 'Unknown'} "
                                    f"({charger_obj.charger_type if hasattr(charger_obj, 'charger_type') else 'unknown'}, "
                                    f"{getattr(charger_obj, 'max_power_kw', 22)}kW). Remaining: {remaining_distance:.1f}km"
                                )
                        elif current_batt_int <= self.MINIMUM_SAFE_BATTERY_PCT and charger_stop_exists:
                            charger_info = ride_data.get('charger_stop_info')
                            distance_to_charger = self._calculate_distance(
                                current_lat, current_lng,
                                charger_info['latitude'], charger_info['longitude']
                            )
                            if distance_to_charger <= 0.5:
                                ride_data['phase'] = 'charging_at_stop'
                                print(f"[VehicleSimulator] Vehicle {vehicle_id} arrived at mid-ride charger - starting charging")

                    if ride_data.get('phase') == 'charging_at_stop':
                        charger_info = ride_data.get('charger_stop_info')
                        current_batt_int = int(math.floor(battery_level_float))
                        target_charge_pct = charger_info.get('charge_target_pct', self.MIN_CHARGE_AT_STOP_PCT)

                        if ride_data.get('route_waypoints'):
                            trimmed_waypoints = self._trim_waypoints_to_current_location(
                                ride_data['route_waypoints'], current_lat, current_lng
                            )
                            if trimmed_waypoints != ride_data['route_waypoints']:
                                ride_data['route_waypoints'] = trimmed_waypoints 
                                ride_data['route_distance'] = self._estimate_polyline_distance(trimmed_waypoints)
                                ride_data['distance_traveled'] = 0.0 
                                ride_data['progress'] = 0.0 
                                ride_data['previous_lat'] = current_lat 
                                ride_data['previous_lng'] = current_lng 
                                print(f"[VehicleSimulator] Vehicle {vehicle_id} charging-at-stop route rebased at charger location")

                        remaining_route_distance = float(ride_data.get('route_distance', 0.0))
                        can_reach_after_charge = self._can_reach_destination(
                            current_batt_int, remaining_route_distance, consumption_rate, battery_capacity
                        )

                        if can_reach_after_charge or current_batt_int >= target_charge_pct:
                            ride_data['phase'] = 'in_transit'
                            print(
                                f"[VehicleSimulator] Vehicle {vehicle_id} finished mid-ride charge ({current_batt_int}%); "
                                f"remaining {remaining_route_distance:.2f}km is safe to continue"
                            )
                        else:
                            charger_power_kw = charger_info.get('power_kw', 22)
                            charger_type = charger_info.get('type', 'slow')

                            time_per_cycle_hours = self.update_interval / 3600.0 
                            efficiency = 0.92 

                            energy_gained_kwh = charger_power_kw * time_per_cycle_hours * efficiency 
                            battery_gained_percent = (energy_gained_kwh / battery_capacity) * 100 
                            previous_batt_int = int(math.floor(battery_level_float))
                            new_batt = min(battery_level_float + battery_gained_percent, 100.0)
                            new_batt_int = int(math.floor(new_batt))
                            battery_level_float = new_batt 
                            ride_data['battery_level_float'] = battery_level_float 

                            if new_batt_int > previous_batt_int:
                                self.db.execute_update(
                                    "UPDATE vehicles SET battery_level = %s, updated_at = NOW() WHERE id = %s",
                                    (new_batt_int, vehicle_id)
                                )
                                print(
                                    f"[VehicleSimulator] Vehicle {vehicle_id} mid-ride charging at {charger_type} ({charger_power_kw}kW): "
                                    f"{current_batt_int}% -> {new_batt_int}% (+{battery_gained_percent:.2f}% in {self.update_interval}s)"
                                )

                    if ride_data.get('phase') == 'to_pickup':
                        pickup_distance = float(ride_data.get('pickup_distance_km') or 0.0)
                        pickup_lat_val = ride_info.get('pickup_latitude')
                        pickup_lng_val = ride_info.get('pickup_longitude')
                        geo_at_pickup = False 
                        if pickup_lat_val is not None and pickup_lng_val is not None:
                            geo_at_pickup = self._calculate_distance(
                                current_lat, current_lng,
                                float(pickup_lat_val), float(pickup_lng_val),
                            ) <= self.PICKUP_ARRIVAL_RADIUS_KM 
                        distance_to_pickup = 1e9
                        try:
                            distance_to_pickup = self._calculate_distance(
                                current_lat, current_lng,
                                float(pickup_lat_val), float(pickup_lng_val)
                            ) if pickup_lat_val is not None and pickup_lng_val is not None else 1e9
                        except Exception:
                            distance_to_pickup = 1e9

                        proximity_threshold = max(self.PICKUP_ARRIVAL_RADIUS_KM * 1.5, 0.05)

                        if geo_at_pickup or (new_distance + 1e-9 >= pickup_distance and distance_to_pickup <= proximity_threshold):
                            update_status_query = """
                                UPDATE ride_requests
                                SET status = 'in_progress',
                                    actual_pickup_time = COALESCE(actual_pickup_time, NOW()),
                                    updated_at = NOW()
                                WHERE id = %s
                            """
                            self.db.execute_update(update_status_query, (ride_id,))
                            ride_data['phase'] = 'in_transit'
                            self.db.execute_update(
                                "UPDATE vehicles SET status = %s, updated_at = NOW() WHERE id = %s",
                                ('in_use', vehicle_id),
                            )
                            print(f"[VehicleSimulator] Ride {ride_id} now in progress (passenger onboard)")

                    if new_progress >= 1.0 and ride_data.get('phase') not in ('heading_to_charger', 'charging_at_stop'):
                        update_ride_query = """
                            UPDATE ride_requests
                            SET status = 'completed',
                                actual_dropoff_time = NOW(),
                                updated_at = NOW()
                            WHERE id = %s
                        """
                        self.db.execute_update(update_ride_query, (ride_id,))
                        self.db.execute_update("UPDATE vehicles SET status = %s, updated_at = NOW() WHERE id = %s", ('available', vehicle_id))
                        self._update_vehicle_db_position(vehicle_id, current_lat, current_lng)

                        ride_data.pop('charger_stop_info', None)

                        completion_batt = int(math.floor(ride_data.get('battery_level_float') or 0))
                        if completion_batt < self.IDLE_CHARGE_TARGET_PCT:
                            self._seed_idle_charging_at(vehicle_id, current_lat, current_lng, completion_batt)
                        else:
                            self.idle_vehicle_states.pop(vehicle_id, None)
                        rides_to_remove.append(ride_id)
                        print(f"[VehicleSimulator] Ride {ride_id} completed (Progress: {new_progress * 100:.1f}%)")

                except Exception as e:
                    print(f"[VehicleSimulator] Error updating ride {ride_id}: {e}")
                    rides_to_remove.append(ride_id)

            for ride_id in rides_to_remove:
                self.active_rides.pop(ride_id, None)

            self._tick_idle_vehicles_unlocked()

    def _tick_idle_vehicles_unlocked(self):
        """Move available vehicles toward strategically chosen chargers; simulate charging."""
        busy = {data['vehicle_id'] for data in self.active_rides.values()}
        rows = self.db.execute_query(
            """
            SELECT v.id, v.battery_level,
                   vlr.latitude AS current_latitude,
                   vlr.longitude AS current_longitude
            FROM vehicles v
            LEFT JOIN vehicle_location_reports vlr ON v.id = vlr.vehicle_id
                AND vlr.id = (SELECT MAX(id) FROM vehicle_location_reports WHERE vehicle_id = v.id)
            WHERE v.status = 'available'
            """
        )
        if not rows:
            return 

        step_km = self.SIMULATED_SPEED_KMH * (self.update_interval / 3600.0)

        for row in rows:
            vid = row['id']
            if vid in busy:
                continue 
            st = self.idle_vehicle_states.get(vid)
            if st and st.get('current_lat') is not None and st.get('current_lng') is not None:
                lat, lng = float(st['current_lat']), float(st['current_lng'])
            else:
                lat = row.get('current_latitude')
                lng = row.get('current_longitude')
                if lat is None or lng is None:
                    fresh = self.db.execute_query(
                        "SELECT latitude, longitude FROM vehicle_location_reports WHERE vehicle_id = %s ORDER BY id DESC LIMIT 1",
                        (vid,)
                    )
                    if fresh:
                        lat = fresh[0].get('latitude')
                        lng = fresh[0].get('longitude')
                if lat is None or lng is None:
                    continue 
                lat, lng = float(lat), float(lng)

            batt = int(row.get('battery_level') or 0)

            if not st:
                if batt >= self.IDLE_CHARGE_TARGET_PCT:
                    continue 

                try:
                    candidates = load_charger_candidates_near(lat, lng, distance=self.CHARGER_SEARCH_DISTANCE_KM)
                except Exception as e:
                    print(f"[strategic_idle] Charger lookup failed, using fallback: {e}")
                    candidates = []
                try:
                    t_lat, t_lng, charger_obj = pick_strategic_charger(lat, lng, candidates, db=self.db, route_service=self.route_service)
                except Exception as e:
                    print(f"[strategic_idle] pick_strategic_charger failed, staying put: {e}")
                    t_lat, t_lng, charger_obj = lat, lng, None 

                charger_power_kw = getattr(charger_obj, 'max_power_kw', 22) if charger_obj else 22 
                charger_type = getattr(charger_obj, 'charger_type', 'slow') if charger_obj else 'slow'

                self.idle_vehicle_states[vid] = {
                    'mode': 'travel',
                    'target_lat': t_lat,
                    'target_lng': t_lng,
                    'charger_name': getattr(charger_obj, 'name', None) or 'Laadstation',
                    'charger_address': getattr(charger_obj, 'address', None),
                    'charger_power_kw': charger_power_kw,
                    'charger_type': charger_type,
                    'battery_level_float': float(batt),
                }
                travel_waypoints = self._build_route_waypoints(lat, lng, float(t_lat), float(t_lng))
                self.idle_vehicle_states[vid]['travel_waypoints'] = travel_waypoints 
                self.idle_vehicle_states[vid]['travel_distance_km'] = self._estimate_polyline_distance(travel_waypoints)
                self.idle_vehicle_states[vid]['travel_progress_km'] = 0.0 
                st = self.idle_vehicle_states[vid]
                print(f"[VehicleSimulator] Vehicle {vid} idle — heading to charger (battery {batt}%, charger type: {charger_type}, power: {charger_power_kw}kW)")

            mode = st.get('mode', 'travel')

            if mode == 'parked':
                if batt >= self.IDLE_CHARGE_TARGET_PCT:
                    self.idle_vehicle_states.pop(vid, None)
                continue 

            if mode == 'travel':
                travel_waypoints = st.get('travel_waypoints')
                travel_distance = float(st.get('travel_distance_km') or 0.0)
                if travel_waypoints and travel_distance > 0:
                    progressed = min(float(st.get('travel_progress_km') or 0.0) + step_km, travel_distance)
                    st['travel_progress_km'] = progressed 
                    nlat, nlng = self._interpolate_position(travel_waypoints, progressed / travel_distance)
                else:
                    rebuilt_waypoints = self._build_route_waypoints(
                        float(lat), float(lng), float(st['target_lat']), float(st['target_lng'])
                    )
                    rebuilt_distance = self._estimate_polyline_distance(rebuilt_waypoints)
                    st['travel_waypoints'] = rebuilt_waypoints 
                    st['travel_distance_km'] = rebuilt_distance 
                    st['travel_progress_km'] = 0.0 

                    if rebuilt_waypoints and rebuilt_distance > 0:
                        progressed = min(step_km, rebuilt_distance)
                        st['travel_progress_km'] = progressed 
                        nlat, nlng = self._interpolate_position(rebuilt_waypoints, progressed / rebuilt_distance)
                    else:
                        nlat, nlng = self._step_toward(lat, lng, st['target_lat'], st['target_lng'], step_km)
                self._persist_vehicle_location(vid, nlat, nlng, None)
                st['current_lat'] = float(nlat)
                st['current_lng'] = float(nlng)
                dist_to_target = self._calculate_distance(nlat, nlng, st['target_lat'], st['target_lng'])
                vehicle_row = self.db.execute_query(
                    """
                    SELECT vt.consumption_kwh_per_km, vt.battery_capacity_kwh
                    FROM vehicles v
                    LEFT JOIN vehicle_templates vt ON v.vehicle_template_id = vt.id
                    WHERE v.id = %s
                    """,
                    (vid,),
                )
                consumption = 0.2 
                capacity = 100.0 
                if vehicle_row:
                    consumption = float(vehicle_row[0].get('consumption_kwh_per_km') or 0.2)
                    capacity = float(vehicle_row[0].get('battery_capacity_kwh') or 100.0)
                idle_target_label = 'Laadstation'
                idle_target_type = 'charger_idle'
                if st.get('mode') == 'charging':
                    idle_target_label = 'Aan laadstation (laden)'
                    idle_target_type = 'charger_stop'
                self._record_battery_check(
                    vid,
                    ride_id=None,
                    phase=st.get('mode'),
                    lat=nlat,
                    lng=nlng,
                    battery_pct=batt,
                    remaining_distance_km=dist_to_target if st.get('mode') == 'travel' else 0.0,
                    consumption_kwh_per_km=consumption,
                    battery_capacity_kwh=capacity,
                    can_reach_destination=self._can_reach_destination(
                        batt, dist_to_target if st.get('mode') == 'travel' else 0.0, consumption, capacity
                    ),
                    nearest_charger={
                        'name': st.get('charger_name'),
                        'latitude': float(st['target_lat']),
                        'longitude': float(st['target_lng']),
                        'distance_km': round(dist_to_target, 3),
                        'can_reach': self._can_reach_destination(batt, dist_to_target, consumption, capacity),
                        'type': st.get('charger_type'),
                        'power_kw': st.get('charger_power_kw'),
                    },
                    active_charger_target={
                        'name': st.get('charger_name'),
                        'latitude': float(st['target_lat']),
                        'longitude': float(st['target_lng']),
                    } if st.get('mode') == 'travel' else None,
                    context='idle',
                    distance_target_type=idle_target_type,
                    distance_target_label=idle_target_label,
                )
                if dist_to_target <= 0.025:
                    st['mode'] = 'charging'
                    print(f"[VehicleSimulator] Vehicle {vid} arrived at idle charger — charging")
                continue 

            if mode == 'charging':
                if batt >= self.IDLE_CHARGE_TARGET_PCT:
                    self.idle_vehicle_states.pop(vid, None)
                    print(f"[VehicleSimulator] Vehicle {vid} finished idle charge ({batt}% >= {self.IDLE_CHARGE_TARGET_PCT}%)")
                    continue 

                charger_power_kw = st.get('charger_power_kw', 22)
                charger_type = st.get('charger_type', 'slow')

                vehicle_query = """
                    SELECT vt.battery_capacity_kwh
                    FROM vehicles v
                    LEFT JOIN vehicle_templates vt ON v.vehicle_template_id = vt.id
                    WHERE v.id = %s
                """
                vehicle_result = self.db.execute_query(vehicle_query, (vid,))
                battery_capacity_kwh = 100.0 
                if vehicle_result:
                    battery_capacity_kwh = float(vehicle_result[0].get('battery_capacity_kwh') or 100.0)

                time_per_cycle_hours = self.update_interval / 3600.0 
                efficiency = 0.92 

                energy_gained_kwh = charger_power_kw * time_per_cycle_hours * efficiency 
                battery_gained_percent = (energy_gained_kwh / battery_capacity_kwh) * 100 

                battery_level_float = float(st.get('battery_level_float', batt))
                new_batt = min(battery_level_float + battery_gained_percent, float(self.IDLE_CHARGE_TARGET_PCT))
                st['battery_level_float'] = new_batt 
                new_batt_int = int(math.floor(new_batt))

                if new_batt_int > batt:
                    self.db.execute_update(
                        "UPDATE vehicles SET battery_level = %s, updated_at = NOW() WHERE id = %s",
                        (new_batt_int, vid),
                    )
                    print(
                        f"[VehicleSimulator] Vehicle {vid} charging at {charger_type} ({charger_power_kw}kW): "
                        f"{batt}% -> {new_batt_int}% (+{battery_gained_percent:.2f}% in {self.update_interval}s)"
                    )

    def _step_toward(self, lat: float, lng: float, target_lat: float, target_lng: float, step_km: float) -> tuple:
        d = self._calculate_distance(lat, lng, target_lat, target_lng)
        if d <= step_km or d < 1e-8:
            return float(target_lat), float(target_lng)
        frac = step_km / d 
        return lat + (target_lat - lat) * frac, lng + (target_lng - lng) * frac 

    def _coerce_datetime(self, value):
        if value is None:
            return None 
        if isinstance(value, datetime):
            return value 
        s = str(value).strip().replace('Z', '')
        if '+' in s:
            s = s.split('+', 1)[0].strip()
        s = s.replace('T', ' ')
        if '.' in s:
            s = s.split('.')[0]
        try:
            return datetime.strptime(s[:19], '%Y-%m-%d %H:%M:%S')
        except ValueError:
            return None 

    def _coords_distance_km(self, lat1: float, lng1: float, lat2: float, lng2: float) -> float:
        return self._calculate_distance(lat1, lng1, lat2, lng2)

    def _distance_along_path_to_pickup(self, waypoints: list, pickup_lat: float, pickup_lng: float) -> float:
        """Path distance from first waypoint to the polyline vertex nearest to pickup."""
        if not waypoints or len(waypoints) < 2:
            return 0.0 
        pickup_lat, pickup_lng = float(pickup_lat), float(pickup_lng)
        best_idx = 0 
        best_d = float('inf')
        for i, wp in enumerate(waypoints):
            d = self._calculate_distance(float(wp[0]), float(wp[1]), pickup_lat, pickup_lng)
            if d < best_d:
                best_d = d 
                best_idx = i 
        total = 0.0 
        for i in range(best_idx):
            total += self._calculate_distance(
                float(waypoints[i][0]), float(waypoints[i][1]),
                float(waypoints[i + 1][0]), float(waypoints[i + 1][1]),
            )
        return total 

    def _interpolate_position(self, waypoints: list, progress: float) -> tuple:
        """
        Interpolate vehicle position along route based on progress (0-1)
        
        Args:
            waypoints: List of [lat, lng] coordinates
            progress: Journey progress from 0 (start) to 1 (destination)
        
        Returns:
            Tuple of (latitude, longitude)
        """
        if not waypoints or len(waypoints) < 2:
            return (51.5074, -0.1278)

        waypoints = [[float(wp[0]), float(wp[1])] for wp in waypoints]
        progress = float(progress)

        if progress <= 0:
            return (waypoints[0][0], waypoints[0][1])

        if progress >= 1.0:
            return (waypoints[-1][0], waypoints[-1][1])

        total_distance = self._estimate_polyline_distance(waypoints)
        target_distance = total_distance * progress 
        cumulative_distance = 0 

        for i in range(len(waypoints) - 1):
            lat1, lng1 = float(waypoints[i][0]), float(waypoints[i][1])
            lat2, lng2 = float(waypoints[i + 1][0]), float(waypoints[i + 1][1])
            segment_distance = self._calculate_distance(lat1, lng1, lat2, lng2)

            if cumulative_distance + segment_distance >= target_distance:
                if segment_distance > 0:
                    segment_progress = (target_distance - cumulative_distance) / segment_distance 
                else:
                    segment_progress = 0 
                interpolated_lat = lat1 + (lat2 - lat1) * segment_progress 
                interpolated_lng = lng1 + (lng2 - lng1) * segment_progress 
                return (float(interpolated_lat), float(interpolated_lng))

            cumulative_distance += segment_distance 

        return (float(waypoints[-1][0]), float(waypoints[-1][1]))

    def _estimate_polyline_distance(self, waypoints: list) -> float:
        """Estimate total distance of a polyline route in kilometers"""
        total_distance = 0.0 
        for i in range(len(waypoints) - 1):
            lat1, lng1 = float(waypoints[i][0]), float(waypoints[i][1])
            lat2, lng2 = float(waypoints[i + 1][0]), float(waypoints[i + 1][1])
            total_distance += self._calculate_distance(lat1, lng1, lat2, lng2)
        return total_distance 

    def _calculate_distance(self, lat1: float, lng1: float, lat2: float, lng2: float) -> float:
        """Calculate distance between two coordinates in km using Haversine formula"""
        lat1 = float(lat1)
        lat2 = float(lat2)
        lng1 = float(lng1)
        lng2 = float(lng2)

        R = 6371 
        lat1_rad = math.radians(lat1)
        lat2_rad = math.radians(lat2)
        delta_lat = math.radians(lat2 - lat1)
        delta_lng = math.radians(lng2 - lng1)

        a = math.sin(delta_lat / 2) ** 2 + math.cos(lat1_rad) * math.cos(lat2_rad) * math.sin(delta_lng / 2) ** 2 
        c = 2 * math.asin(math.sqrt(a))

        return R * c