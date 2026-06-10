

DATABASE_CONFIG = {
    'host': 'localhost',
    'database': 'eindwerk',
    'user': 'root',
    'password': '',
    'charset': 'utf8mb4',
    'collation': 'utf8mb4_general_ci'
}

API_HOST = '0.0.0.0'
API_PORT = 5000
DEBUG = False

OSM_NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search'
OSRM_URL = 'http://router.project-osrm.org/route/v1/driving'
API_NINJAS_KEY = ''  # Set via environment variable or replace with your key

CACHE_DIR = 'cache'
CACHE_EXPIRY_HOURS = 24 * 90
