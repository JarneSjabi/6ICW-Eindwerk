"""
EV Charger API Service
Integrates with API-Ninjas EV Charger API to fetch charging stations
"""
import hashlib
import json
import os
import time
from math import atan2, cos, radians, sin, sqrt
from typing import Any, Dict, Iterator, List, Optional, Tuple

import requests
from models.ChargingStation import ChargingStation


def _haversine_km(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    """Distance in km (same formula as algorithms.dijkstra.calculate_distance; kept local to avoid import cycles)."""
    r_earth = 6371.0
    lat1_rad, lat2_rad = radians(lat1), radians(lat2)
    dlat, dlon = radians(lat2 - lat1), radians(lon2 - lon1)
    a = sin(dlat / 2) ** 2 + cos(lat1_rad) * cos(lat2_rad) * sin(dlon / 2) ** 2
    c = 2 * atan2(sqrt(a), sqrt(1 - a))
    return r_earth * c


class EVChargerService:
    """Service for fetching EV charging stations from API-Ninjas"""

    CACHE_FILENAME = 'ev_charger_cache.json'

    def __init__(self, api_key: str = None, cache_dir: str = None, cache_expiry_hours: int = 24):
        """
        Initialize the EV charger service

        Args:
            api_key: API-Ninjas API key. If None, tries to get from environment variable or config
            cache_dir: Directory to store cached responses. Defaults to 'cache' subdirectory
            cache_expiry_hours: How long to cache responses in hours. Defaults to 24 hours
        """
        self.api_key = api_key or os.getenv('API_NINJAS_KEY')
        if not self.api_key:
            try:
                from config import API_NINJAS_KEY
                self.api_key = API_NINJAS_KEY
            except ImportError:
                pass

        self.base_url = 'https://api.api-ninjas.com/v1/evcharger'
        if cache_dir is None:
            try:
                from config import CACHE_DIR, CACHE_EXPIRY_HOURS
                cache_dir = CACHE_DIR
                cache_expiry_hours = CACHE_EXPIRY_HOURS
            except ImportError:
                cache_dir = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'cache')
        self.cache_dir = os.path.abspath(cache_dir)
        self.cache_path = os.path.join(self.cache_dir, self.CACHE_FILENAME)
        self.cache_expiry_seconds = cache_expiry_hours * 3600
        self.last_fetch_source = None
        self.last_fetch_at = None
        self.cache_query_count = 0
        self.cache_hit_counts = {
            'exact_cache': 0,
            'nearby_cache': 0,
        }
        self.cache_miss_count = 0

        os.makedirs(self.cache_dir, exist_ok=True)

        if not self.api_key or self.api_key == 'YOUR_API_KEY_HERE':
            print("Warning: No valid API-Ninjas key provided. Set API_NINJAS_KEY in config.py or environment variable.")

    def _load_cache_file(self) -> Dict[str, Any]:
        if not os.path.exists(self.cache_path):
            return {'entries': {}}

        try:
            with open(self.cache_path, 'r', encoding='utf-8') as f:
                cache_blob = json.load(f)

            if not isinstance(cache_blob, dict):
                return {'entries': {}}

            if 'entries' not in cache_blob or not isinstance(cache_blob['entries'], dict):
                cache_blob['entries'] = {}

            return cache_blob
        except (json.JSONDecodeError, OSError):
            return {'entries': {}}

    def _save_cache_file(self, cache_blob: Dict[str, Any]) -> bool:
        try:
            with open(self.cache_path, 'w', encoding='utf-8') as f:
                json.dump(cache_blob, f, ensure_ascii=False, indent=2)
            return True
        except OSError as e:
            print(f"Error writing cache file: {e}")
            return False

    def _generate_cache_key(self, params: Dict) -> str:
        """Generate a stable cache key from normalized request parameters"""
        return json.dumps(params, sort_keys=True)

    def _load_from_cache(self, cache_key: str) -> Optional[Dict]:
        cache_blob = self._load_cache_file()
        entry = cache_blob.get('entries', {}).get(cache_key)
        if not isinstance(entry, dict):
            return None

        if self._is_cache_expired(entry):
            cache_blob['entries'].pop(cache_key, None)
            self._save_cache_file(cache_blob)
            return None

        print(f"Loading from cache: {cache_key}")
        return entry.get('data')

    def _is_cache_expired(self, cached_data: Dict) -> bool:
        cached_time = cached_data.get('timestamp', 0)
        return time.time() - cached_time > self.cache_expiry_seconds

    def _record_cache_lookup(self, cache_source: str):
        self.cache_query_count += 1
        if cache_source in self.cache_hit_counts:
            self.cache_hit_counts[cache_source] += 1
        else:
            self.cache_miss_count += 1

    def _params_satisfy_cache(self, request_params: Dict, cached_params: Dict) -> bool:
        try:
            if request_params.get('level') != cached_params.get('level'):
                return False

            request_distance = float(request_params.get('distance', 0))
            cache_distance = float(cached_params.get('distance', 0))
            if cache_distance < request_distance:
                return False

            request_lat = float(request_params.get('lat', 0))
            request_lon = float(request_params.get('lon', 0))
            cache_lat = float(cached_params.get('lat', 0))
            cache_lon = float(cached_params.get('lon', 0))
            tolerance_km = max(1.0, request_distance * 0.1)
            return _haversine_km(request_lat, request_lon, cache_lat, cache_lon) <= tolerance_km
        except (TypeError, ValueError):
            return False

    def _find_nearby_cached_data(self, params: Dict) -> Optional[Dict]:
        best_match = None
        best_distance = float('inf')
        cache_blob = self._load_cache_file()
        entries = cache_blob.get('entries', {})
        for cache_key, entry in entries.items():
            if not isinstance(entry, dict):
                continue
            if self._is_cache_expired(entry):
                continue
            cached_params = entry.get('params')
            if not isinstance(cached_params, dict):
                continue
            if not self._params_satisfy_cache(params, cached_params):
                continue
            distance = _haversine_km(
                float(params['lat']),
                float(params['lon']),
                float(cached_params['lat']),
                float(cached_params['lon'])
            )
            if distance < best_distance:
                best_distance = distance
                best_match = entry.get('data')
        if best_match is not None:
            print(f"Using nearby cache for EV charger query (distance {best_distance:.2f} km)")
        return best_match

    def _get_cached_data(self, params: Dict) -> Tuple[Optional[List[Any]], str]:
        cache_key = self._generate_cache_key(params)
        exact = self._load_from_cache(cache_key)
        if exact is not None:
            self._record_cache_lookup('exact_cache')
            return exact, 'exact_cache'
        nearby = self._find_nearby_cached_data(params)
        if nearby is not None:
            self._record_cache_lookup('nearby_cache')
            return nearby, 'nearby_cache'
        self._record_cache_lookup('none')
        return None, 'none'

    def get_cached_charging_stations(self, latitude: float, longitude: float, distance: float = 10.0, level: int = None) -> List[ChargingStation]:
        """Return cached stations only, without querying the live API."""
        params = self._normalize_request_params(latitude, longitude, distance, level)
        cached_data, cache_source = self._get_cached_data(params)
        if cached_data is None:
            print(f"No cached chargers available for params: {params}")
            return []
        print(f"Loaded {len(cached_data)} cached charging stations ({cache_source})")
        return self._parse_stations_from_data(cached_data)

    @staticmethod
    def _station_matches_requested_level(item: Dict, level: Optional[int]) -> bool:
        if level is None or level not in (1, 2, 3):
            return True
        connections = item.get('connections') or []
        return any(conn.get('level') == level for conn in connections)

    @staticmethod
    def _station_dedupe_key(item: Dict) -> Optional[Tuple[float, float, str]]:
        try:
            lat = item.get('latitude')
            lon = item.get('longitude')
            if lat is None or lon is None:
                return None
            name = str(item.get('name') or '').strip().lower()
            return (round(float(lat), 5), round(float(lon), 5), name)
        except (TypeError, ValueError):
            return None

    def _normalize_request_params(self, latitude: float, longitude: float, distance: float, level: Optional[int]) -> Dict[str, Any]:
        """Normalize request parameters for deterministic caching."""
        normalized = {
            'lat': round(float(latitude), 5),
            'lon': round(float(longitude), 5),
            'distance': round(min(float(distance), 50.0), 1),
        }
        if level in (1, 2, 3):
            normalized['level'] = int(level)
        return normalized

    def _save_to_cache(self, cache_key: str, request_params: Dict, data: List[Any]):
        """Save API response payload and the request params used (for fused-cache compatibility)."""
        cache_blob = self._load_cache_file()
        cache_blob['entries'][cache_key] = {
            'timestamp': time.time(),
            'params': request_params,
            'data': data,
        }
        if self._save_cache_file(cache_blob):
            print(f"Saved cache entry for {cache_key}")

    def get_charging_stations(self, latitude: float, longitude: float,
                             distance: float = 10.0, level: int = None) -> List[ChargingStation]:
        """
        Get charging stations near a location

        Args:
            latitude: Latitude coordinate
            longitude: Longitude coordinate
            distance: Search distance in kilometers (max 50)
            level: Charging level (1, 2, or 3). None for all levels

        Returns:
            List of ChargingStation objects
        """
        if not self.api_key or self.api_key == 'YOUR_API_KEY_HERE':
            print("Warning: No valid API-Ninjas key configured. Set API_NINJAS_KEY in config.py")
            return []

        params = self._normalize_request_params(latitude, longitude, distance, level)
        cache_key = self._generate_cache_key(params)
        cached_data, cache_source = self._get_cached_data(params)

        if cached_data is not None:
            print(f"Using cached data for {len(cached_data)} charging stations")
            self.last_fetch_source = cache_source
            self.last_fetch_at = time.time()
            return self._parse_stations_from_data(cached_data)

        headers = {
            'X-Api-Key': self.api_key
        }

        print(f"Fetching charging stations from API with params: {params}")

        try:
            response = requests.get(self.base_url, params=params, headers=headers, timeout=10)
            print(f"API Response status: {response.status_code}")

            if response.status_code == 400:
                print(f"Bad Request details: {response.text}")
                return []

            response.raise_for_status()

            data = response.json()
            print(f"Received {len(data)} charging stations from API")

            self.last_fetch_source = 'api'
            self.last_fetch_at = time.time()
            self._save_to_cache(cache_key, params, data)

            return self._parse_stations_from_data(data)

        except requests.RequestException as e:
            print(f"Error fetching charging stations: {e}")
            return []
        except Exception as e:
            print(f"Error parsing charging station data: {e}")
            return []

    def _parse_stations_from_data(self, data: List[Dict]) -> List[ChargingStation]:
        """Parse charging station data into ChargingStation objects"""
        stations = []

        for item in data:

            charger_type = self._determine_charger_type(item.get('connections', []))
            max_power = self._get_max_power_kw(item.get('connections', []))
            available_slots = self._count_available_slots(item.get('connections', []))

            station = ChargingStation(
                name=item.get('name', 'Unknown Station'),
                latitude=float(item.get('latitude', 0)) if item.get('latitude') is not None else 0.0,
                longitude=float(item.get('longitude', 0)) if item.get('longitude') is not None else 0.0,
                address=item.get('address', ''),
                charger_type=charger_type,
                max_power_kw=max_power,
                available_slots=available_slots
            )
            stations.append(station)

        return stations

    def clear_expired_cache(self):
        """Clear expired entries from the single JSON cache file"""
        cache_blob = self._load_cache_file()
        entries = cache_blob.get('entries', {})
        cleared_count = 0

        keys_to_remove = []
        for cache_key, entry in entries.items():
            if not isinstance(entry, dict):
                keys_to_remove.append(cache_key)
                continue
            if self._is_cache_expired(entry):
                keys_to_remove.append(cache_key)

        for cache_key in keys_to_remove:
            entries.pop(cache_key, None)
            cleared_count += 1

        if cleared_count > 0:
            cache_blob['entries'] = entries
            self._save_cache_file(cache_blob)

        print(f"Cleared {cleared_count} expired cache entries")
        return cleared_count

    def get_cache_stats(self) -> Dict:
        """Get cache statistics"""
        cache_blob = self._load_cache_file()
        entries = cache_blob.get('entries', {})
        total_files = len(entries)
        valid_files = 0
        expired_files = 0

        for entry in entries.values():
            if not isinstance(entry, dict):
                expired_files += 1
                continue
            if self._is_cache_expired(entry):
                expired_files += 1
            else:
                valid_files += 1

        total_size = 0
        if os.path.exists(self.cache_path):
            try:
                total_size = os.path.getsize(self.cache_path)
            except OSError:
                total_size = 0

        hit_count = sum(self.cache_hit_counts.values())
        total_queries = hit_count + self.cache_miss_count
        hit_ratio = None
        if total_queries > 0:
            hit_ratio = round((hit_count / total_queries) * 100, 2)

        return {
            'total_files': total_files,
            'valid_files': valid_files,
            'expired_files': expired_files,
            'total_size_mb': round(total_size / (1024 * 1024), 2),
            'cache_queries': total_queries,
            'cache_hits': hit_count,
            'cache_misses': self.cache_miss_count,
            'cache_hit_ratio': hit_ratio,
            'exact_cache_hits': self.cache_hit_counts.get('exact_cache', 0),
            'nearby_cache_hits': self.cache_hit_counts.get('nearby_cache', 0),
        }

    def get_all_cached_charging_stations(self) -> List[ChargingStation]:
        """Return all non-expired cached station entries from the single cache file."""
        cache_blob = self._load_cache_file()
        entries = cache_blob.get('entries', {})
        stations = []
        seen = set()

        for entry in entries.values():
            if not isinstance(entry, dict):
                continue
            if self._is_cache_expired(entry):
                continue
            for item in entry.get('data', []) or []:
                dedupe_key = self._station_dedupe_key(item)
                if dedupe_key is None or dedupe_key in seen:
                    continue
                seen.add(dedupe_key)
                charger_type = self._determine_charger_type(item.get('connections', []))
                max_power = self._get_max_power_kw(item.get('connections', []))
                available_slots = self._count_available_slots(item.get('connections', []))

                stations.append(ChargingStation(
                    name=item.get('name', 'Unknown Station'),
                    latitude=float(item.get('latitude', 0)) if item.get('latitude') is not None else 0.0,
                    longitude=float(item.get('longitude', 0)) if item.get('longitude') is not None else 0.0,
                    address=item.get('address', ''),
                    charger_type=charger_type,
                    max_power_kw=max_power,
                    available_slots=available_slots
                ))

        return stations

    def _determine_charger_type(self, connections: List[Dict]) -> str:
        """Determine charger type based on connection types"""
        if not connections:
            return 'unknown'

        for conn in connections:
            level = conn.get('level')
            if level == 3:
                return 'ultra_fast'
            elif level == 2:
                type_name = conn.get('type_name', '').lower()
                if 'dc' in type_name or 'fast' in type_name or 'ccs' in type_name or 'chademo' in type_name:
                    return 'fast'

        return 'slow'

    def _get_max_power_kw(self, connections: List[Dict]) -> int:
        """Get maximum power in kW from connections"""

        max_power = 22

        for conn in connections:
            level = conn.get('level')
            if level is not None:
                if level == 3:
                    max_power = max(max_power, 150)
                elif level == 2:
                    max_power = max(max_power, 50)

        return max_power

    def _count_available_slots(self, connections: List[Dict]) -> int:
        """Count total available charging slots"""
        total_slots = 0
        for conn in connections:
            num_connectors = conn.get('num_connectors', 1)
            if num_connectors is not None:
                total_slots += num_connectors
            else:
                total_slots += 1

        return total_slots