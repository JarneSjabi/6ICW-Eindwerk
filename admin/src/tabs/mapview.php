<?php

use App\Core\Config;
use App\Core\Database;

$db = Database::getConnection();

$backendUrl = Config::get('BACKEND_SERVICE_URL') ?? $db->query("SELECT value FROM settings WHERE key_name = 'backend_service_url'")->fetch(PDO::FETCH_ASSOC)['value'] ?? 'http://localhost:5000';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Map Container -->
            <div class="map-frame">
                <div id="map" style="height: 80vh; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
                <div id="mapLayerPanel" class="map-layer-panel">
                    <button type="button" class="map-layer-panel__toggle" id="mapLayerToggle" title="Kaartlagen">
                        <i class="fas fa-layer-group"></i>
                    </button>
                    <div class="map-layer-panel__body" id="mapLayerBody">
                        <div class="map-layer-panel__title"><i class="fas fa-layer-group"></i> Kaartlagen</div>
                        <div class="map-layer-panel__section map-layer-toggles">
                            <div class="layer-toggle layer-toggle--locked">
                                <span class="layer-toggle__label">Voertuigen</span>
                                <span class="layer-toggle__state on">Aan</span>
                            </div>
                            <label class="layer-toggle">
                                <span class="layer-toggle__label">Geplande routes</span>
                                <input type="checkbox" data-layer="routes" checked hidden>
                                <span class="layer-toggle__switch" aria-hidden="true"></span>
                            </label>
                            <label class="layer-toggle">
                                <span class="layer-toggle__label">Routepunten</span>
                                <input type="checkbox" data-layer="waypoints" checked hidden>
                                <span class="layer-toggle__switch" aria-hidden="true"></span>
                            </label>
                            <label class="layer-toggle">
                                <span class="layer-toggle__label">Afgelegde weg</span>
                                <input type="checkbox" data-layer="traces" checked hidden>
                                <span class="layer-toggle__switch" aria-hidden="true"></span>
                            </label>
                            <label class="layer-toggle">
                                <span class="layer-toggle__label">Voertuigspoor</span>
                                <input type="checkbox" data-layer="trails" checked hidden>
                                <span class="layer-toggle__switch" aria-hidden="true"></span>
                            </label>
                            <label class="layer-toggle">
                                <span class="layer-toggle__label">Laadstations</span>
                                <input type="checkbox" data-layer="chargers" checked hidden>
                                <span class="layer-toggle__switch" aria-hidden="true"></span>
                            </label>
                            <label class="layer-toggle">
                                <span class="layer-toggle__label">Batterijanalyse</span>
                                <input type="checkbox" data-layer="battery" checked hidden>
                                <span class="layer-toggle__switch" aria-hidden="true"></span>
                            </label>
                            <div id="chargerCacheStatus" class="charger-cache-status"></div>
                            <div id="cachedChargersPanel" class="cached-chargers-panel"></div>
                        </div>
                        <div class="map-layer-panel__section">
                            <div class="map-layer-panel__subtitle">Ritten filter</div>
                            <select id="rideScopeFilter" class="form-control form-control-sm">
                                <option value="active" selected>Alleen actieve ritten</option>
                                <option value="all">Alle ritten (incl. voltooid)</option>
                            </select>
                            <select id="vehicleFilter" class="form-control form-control-sm mt-2" title="Filter voertuigen op status">
                                <option value="all" selected>Alle voertuigen</option>
                                <option value="to_charger">Alleen voertuigen naar laadstation</option>
                                <option value="available">Alleen beschikbare voertuigen</option>
                            </select>
                            <input type="search" id="rideSearchFilter" class="form-control form-control-sm mt-2" placeholder="Zoek rit, passagier, adres...">
                        </div>
                    </div>
                </div>
                <div id="batteryHud" class="battery-hud" style="display:none;">
                                <div class="battery-hud__title">
                                    <i class="fas fa-battery-half"></i> Live batterijberekening
                                    <select id="batteryHudMode" class="form-control form-control-sm battery-hud__mode" title="Toon: geselecteerde voertuig of alle voertuigen">
                                        <option value="selected">Geselecteerd voertuig</option>
                                        <option value="all" selected>Alle voertuigen</option>
                                    </select>
                                </div>
                                <div id="batteryHudContent" class="battery-hud__content"></div>
                            </div>
            </div>

            <!-- Legend -->
            <div class="map-legend mt-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fa fa-info-circle"></i> Legenda</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <p><i class="fa fa-arrow-right" style="color: #ff9800;"></i> <strong>Rijdt naar ophaalpunt</strong></p>
                                <p><i class="fa fa-car" style="color: #28a745;"></i> <strong>Actieve rit</strong> Onderweg met passagier</p>
                                <p><i class="fa fa-undo" style="color: #2196f3;"></i> <strong>Keert terug</strong> Terugrijden naar strategisch punt</p>
                                <p><i class="fas fa-car" style="color: #ff9422;"></i> <strong>Rijdt naar</strong> Laadstation</p>
                                <p><i class="fas fa-check-circle" style="color: #b5b5b5;"></i> <strong>Beschikbaar</strong> Volgeladen</p>
                                <p><i class="fa fa-battery-half" style="color: #ff5722;"></i> <strong>Laadt op</strong></p>
                            </div>
                            <div class="col-md-3">
                                <p><i class="fas fa-charging-station" style="color: #4caf50;"></i> <strong>Laadstation</strong> EV oplaadpunten</p>
                                <p><i class="fas fa-plug" style="color: #2196f3;"></i> <strong>Traag laden</strong> DC Slow Charging</p>
                                <p><i class="fas fa-bolt" style="color: #ff8d22;"></i> <strong>Snel laden</strong> DC Fast Charging</p>
                                <p><i class="fas fa-bolt" style="color: #ff5722;"></i> <strong>Ultrasnel laden</strong> DC Fast Charging</p>
                            </div>
                            <div class="col-md-3">
                                <p><i class="fas fa-flag-checkered" style="color: #000;"></i> <strong>Start/Eind</strong> Ophaalpunt/Eindbestemming</p>
                                <p><i class="fas fa-map-pin" style="color: #f44336;"></i> <strong>Tussenpunt</strong> Routepunt</p>
                                <p><span class="legend-line-planned"></span> <strong>Geplande route</strong></p>
                                <p><span class="legend-line-trace"></span> <strong>Afgelegde weg</strong> (huidige rit)</p>
                                <p><span class="legend-line-trail"></span> <strong>Voertuigspoor</strong> Bewegingsgeschiedenis</p>
                                <p><span class="legend-line-battery"></span> <strong>Batterijanalyse</strong> Bereikbaarheid laadstation</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet Map Library -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

<!-- Leaflet Routing Machine -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
<script src="https://cdn.jsdelivr.net/npm/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

<!-- Custom Mapview Script -->
<script>
    const BACKEND_URL = '<?php echo $backendUrl; ?>';
    const UPDATE_INTERVAL = 3000;
    const LAYER_STORAGE_KEY = 'icw_mapview_layers';

    let map;
    let vehicleMarkers = {};
    let vehicleTrailLines = {};
    let routePolylines = {};
    let tracePolylines = {};
    let waypointMarkers = {};
    let startFinishMarkers = {};
    let batteryLines = {};
    let batteryMarkers = {};
    let updateInterval;
    let lastVehicles = [];
    let lastRides = [];
    let layerFetchInFlight = {};
    let cachedChargers = [];
    let vehiclePreviousPositions = {};

    const layerConfig = {
        vehicles: { enabled: true, group: null },
        routes: { enabled: true, group: null },
        waypoints: { enabled: true, group: null },
        traces: { enabled: true, group: null },
        trails: { enabled: true, group: null },
        chargers: { enabled: true, group: null },
        battery: { enabled: true, group: null }
    };

    const batteryMetricTips = {
        battery: 'Huidig batterijniveau van het voertuig in procent (%). Wordt elke simulatietick (~1 s) bijgewerkt op basis van afgelegde afstand en laden.',
        distance: 'Resterende afstand in kilometers (km). Decimaal: 13,9 km = dertien komma negen kilometer, niet dertienduizend. Het doel hangt af van de fase: eindbestemming tijdens een rit, of het laadstation wanneer het voertuig ernaar rijdt.',
        needed: 'Batterijpercentage dat nodig is om het actieve doel te bereiken. Formule: (afstand × verbruik kWh/km ÷ batterijcapaciteit kWh) × 100. Geldt altijd voor het huidige doel (eindbestemming of laadstation).',
        projection: 'Geschat batterijpercentage ná het afleggen van de afstand tot het actieve doel (batterij − benodigd). Wordt elke tick opnieuw berekend. Bij een nieuwe rit start deze waarde opnieuw voor die rit; tijdens rijden naar een laadstation geldt het laadstation als doel.',
        reachable: 'Ja als de projectie minstens 5% boven nul blijft (veiligheidsmarge). Dit is dezelfde check die de simulator gebruikt om een laadstop in te plannen.',
        target: 'Het actieve navigatiedoel waarvoor afstand en benodigd percentage gelden.'
    };

    // default to showing all rides on map
    let rideScope = 'active';
    let rideSearch = '';
    // selected vehicle for battery HUD (set when clicking a marker)
    let selectedVehicleId = null;
    // battery HUD mode: 'selected' (show last clicked vehicle) or 'all'
    let batteryHudMode = 'all';

    const vehicleStateIcons = {
        'to_pickup': {
            color: '#ff9800',
            icon: 'fa-arrow-right',
            label: 'Naar ophaalpunt'
        },
        'in_transit': {
            color: '#28a745',
            icon: 'fa-car',
            label: 'Actieve rit'
        },
        'returning': {
            color: '#2196f3',
            icon: 'fa-undo',
            label: 'Terugrijdend'
        },
        'to_charger': {
            color: '#ff9800',
            icon: 'fa-car',
            label: 'Rijdt naar laadstation'
        },
        'charging': {
            color: '#ff5722',
            icon: 'fa-battery-half',
            label: 'Laadt op'
        },
        'standby_at_charger': {
            color: '#4caf50',
            icon: 'fa-plug',
            label: 'Aan laadstation'
        },
        'available': {
            color: '#607d8b',
            icon: 'fa-check-circle',
            label: 'Beschikbaar'
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        initializeMap();
        setupLayerPanel();
        refreshMapData();
        updateInterval = setInterval(refreshMapData, UPDATE_INTERVAL);
    });

    function initializeMap() {
        map = L.map('map').setView([50.8503, 4.3517], 7);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19,
            className: 'map-tiles'
        }).addTo(map);

        layerConfig.vehicles.group = L.layerGroup().addTo(map);
        layerConfig.trails.group = L.layerGroup();
        layerConfig.routes.group = L.layerGroup();
        layerConfig.traces.group = L.layerGroup();
        layerConfig.waypoints.group = L.layerGroup();
        layerConfig.chargers.group = L.layerGroup().addTo(map);
        layerConfig.chargers.cacheGroup = L.layerGroup().addTo(map);
        layerConfig.battery.group = L.layerGroup();
    }

    function loadLayerPrefs() {
        const defaults = { routes: true, waypoints: true, traces: true, trails: true, chargers: true, battery: true };
        try {
            const raw = localStorage.getItem(LAYER_STORAGE_KEY);
            if (raw) return { ...defaults, ...JSON.parse(raw) };
        } catch (e) { /* ignore */ }
        return { ...defaults };
    }

    function saveLayerPrefs() {
        const state = {};
        Object.keys(layerConfig).forEach((key) => {
            if (key !== 'vehicles') state[key] = layerConfig[key].enabled;
        });
        try {
            localStorage.setItem(LAYER_STORAGE_KEY, JSON.stringify(state));
        } catch (e) { /* ignore */ }
    }

    function applyLayerPrefs(prefs) {
        Object.entries(prefs).forEach(([key, enabled]) => {
            if (!layerConfig[key]) return;
            layerConfig[key].enabled = !!enabled;
            const input = document.querySelector(`input[data-layer="${key}"]`);
            if (input) input.checked = !!enabled;
            syncLayerVisibility(key);
            if (key === 'battery') {
                document.getElementById('batteryHud').style.display = enabled ? 'block' : 'none';
            }
        });
        document.querySelectorAll('input[data-layer]').forEach((input) => {
            input.parentElement.querySelector('.layer-toggle__switch')?.classList.toggle('is-on', input.checked);
        });
    }

    function setupLayerPanel() {
        applyLayerPrefs(loadLayerPrefs());
        const panel = document.getElementById('mapLayerPanel');
        const toggle = document.getElementById('mapLayerToggle');
        const body = document.getElementById('mapLayerBody');

        toggle.addEventListener('click', () => {
            body.classList.toggle('is-open');
        });

        document.querySelectorAll('input[data-layer]').forEach(input => {
            const switchEl = input.parentElement.querySelector('.layer-toggle__switch');
            const syncSwitch = () => switchEl?.classList.toggle('is-on', input.checked);
            syncSwitch();
            input.addEventListener('change', () => {
                const key = input.dataset.layer;
                if (!layerConfig[key]) return;
                layerConfig[key].enabled = input.checked;
                syncSwitch();
                syncLayerVisibility(key);
                if (key === 'battery') {
                    document.getElementById('batteryHud').style.display = input.checked ? 'block' : 'none';
                }
                saveLayerPrefs();
                refreshOptionalLayers();
            });
        });

        document.getElementById('rideScopeFilter').addEventListener('change', (e) => {
            rideScope = e.target.value;
            refreshMapData();
        });

        const vehicleFilterEl = document.getElementById('vehicleFilter');
        if (vehicleFilterEl) {
            vehicleFilterEl.addEventListener('change', (e) => {
                // simply refresh map to apply new vehicle filter
                refreshMapData();
            });
        }

        const batteryHudModeEl = document.getElementById('batteryHudMode');
        if (batteryHudModeEl) {
            batteryHudModeEl.addEventListener('change', (e) => {
                batteryHudMode = e.target.value || 'selected';
                // refresh battery layer immediately to reflect new mode
                fetchBatteryAnalysis();
            });
        }

        document.getElementById('rideSearchFilter').addEventListener('input', (e) => {
            rideSearch = e.target.value.trim().toLowerCase();
            renderRideLayers(lastRides);
            renderVehicleMarkers(lastVehicles, filterRides(lastRides));
        });
    }

    function syncLayerVisibility(layerKey) {
        const cfg = layerConfig[layerKey];
        if (!cfg || !cfg.group) return;
        if (cfg.enabled) {
            if (!map.hasLayer(cfg.group)) map.addLayer(cfg.group);
        } else {
            if (map.hasLayer(cfg.group)) map.removeLayer(cfg.group);
        }
    }

    function getRideStatusParam() {
        return rideScope === 'all' ? 'in_progress,assigned,completed' : 'in_progress,assigned';
    }

    function filterRides(rides) {
        if (!rideSearch) return rides;
        return rides.filter(ride => {
            const haystack = [
                String(ride.id),
                ride.customer_name,
                ride.pickup_address,
                ride.dropoff_address,
                ride.status,
                ride.vehicle?.template_name
            ].filter(Boolean).join(' ').toLowerCase();
            return haystack.includes(rideSearch);
        });
    }

    function getVisibleVehicleIds(vehicles, rides) {
        const rideVehicleIds = new Set(rides.map(r => r.vehicle_id).filter(Boolean));
        if (rideSearch) {
            return [...rideVehicleIds];
        }
        return vehicles.map(v => v.id);
    }

    async function refreshMapData() {
        try {
            const [vehiclesResponse, ridesResponse] = await Promise.all([
                fetch(`${BACKEND_URL}/api/vehicles`),
                fetch(`${BACKEND_URL}/api/mapview/rides?status=${getRideStatusParam()}`)
            ]);
            lastVehicles = await vehiclesResponse.json();
            lastRides = await ridesResponse.json();

            const filteredRides = filterRides(lastRides);
            renderVehicleMarkers(lastVehicles, filteredRides);
            renderRideLayers(filteredRides);
            await refreshOptionalLayers(lastVehicles, filteredRides);
        } catch (error) {
            console.error('Error updating map:', error);
        }
    }

    async function refreshOptionalLayers(vehicles = lastVehicles, rides = filterRides(lastRides)) {
        const vehicleIds = getVisibleVehicleIds(vehicles, rides);
        const rideIds = rides.map(r => r.id);

        const tasks = [];
        if (layerConfig.trails.enabled) {
            tasks.push(fetchTrails(vehicleIds));
        } else {
            clearTrails();
        }
        if (layerConfig.traces.enabled) {
            tasks.push(fetchTraces(rideIds));
        } else {
            clearTraces();
        }
        if (layerConfig.chargers.enabled) {
            tasks.push(fetchCachedChargers());
            tasks.push(fetchChargers());
            tasks.push(fetchChargerCacheStatus());
        } else {
            layerConfig.chargers.group.clearLayers();
            layerConfig.chargers.cacheGroup.clearLayers();
            document.getElementById('chargerCacheStatus').textContent = '';
        }
        if (layerConfig.battery.enabled) {
            tasks.push(fetchBatteryAnalysis());
        } else {
            clearBatteryLayer();
        }
        await Promise.all(tasks);
    }

    async function fetchWithGuard(key, url, handler) {
        if (layerFetchInFlight[key]) return;
        layerFetchInFlight[key] = true;
        try {
            const response = await fetch(url);
            const data = await response.json();
            handler(data);
        } catch (e) {
            console.error(`Layer fetch failed (${key}):`, e);
        } finally {
            layerFetchInFlight[key] = false;
        }
    }

    async function fetchTrails(vehicleIds) {
        if (!vehicleIds.length) {
            clearTrails();
            return;
        }
        const url = `${BACKEND_URL}/api/mapview/trails?vehicle_ids=${vehicleIds.join(',')}&limit=800`;
        await fetchWithGuard('trails', url, (trails) => updateVehicleTrails(trails, vehicleIds));
    }

    async function fetchTraces(rideIds) {
        if (!rideIds.length) {
            clearTraces();
            return;
        }
        const url = `${BACKEND_URL}/api/mapview/traces?ride_ids=${rideIds.join(',')}&limit=1200`;
        await fetchWithGuard('traces', url, (traces) => updateTracePathsFromBackend(traces, rideIds));
    }

    async function fetchCachedChargers() {
        await fetchWithGuard('cached_chargers', `${BACKEND_URL}/api/cache/chargers`, (chargers) => {
            updateCachedChargingStations(chargers);
            renderCachedChargerPanel(chargers);
        });
    }

    async function fetchChargers() {
        const center = map.getBounds().getCenter();
        const url = `${BACKEND_URL}/api/charging-stations?lat=${center.lat}&lon=${center.lng}&distance=20&limit=25`;
        await fetchWithGuard('chargers', url, updateChargingStations);
    }

    async function fetchChargerCacheStatus() {
        await fetchWithGuard('cache', `${BACKEND_URL}/api/cache/stats`, (stats) => {
            const el = document.getElementById('chargerCacheStatus');
            if (!stats) return;
            const hits = stats.cache_hits || 0;
            const misses = stats.cache_misses || 0;
            const queries = stats.cache_queries || hits + misses;
            const ratio = stats.cache_hit_ratio != null ? `${stats.cache_hit_ratio.toFixed(2)}%` : 'N/A';
            el.textContent = `Cache hit ratio: ${ratio} (${hits} / ${queries})`;
        });
    }

    async function fetchBatteryAnalysis() {
        await fetchWithGuard('battery', `${BACKEND_URL}/api/mapview/battery-reachability`, updateBatteryLayer);
    }

    function getChargerLabel(vehicle, ride) {
        const detail = vehicle.activity_detail;
        if (detail?.charger?.name) return detail.charger.name;
        if (ride?.charger_target?.name) return ride.charger_target.name;
        return null;
    }

    function determineVehicleState(vehicle, rides) {
        const activeRide = rides.find(r => r.vehicle_id === vehicle.id && ['assigned', 'in_progress', 'completed'].includes(r.status));
        const detail = vehicle.activity_detail;

        if (activeRide) {
            if (activeRide.status === 'completed') {
                return 'available';
            }
            if (activeRide.simulation_phase === 'heading_to_charger' || detail?.phase === 'heading_to_charger') {
                return 'to_charger';
            }
            if (activeRide.simulation_phase === 'charging_at_stop' || detail?.phase === 'charging_at_stop') {
                return 'charging';
            }
            if (activeRide.actual_pickup_time && activeRide.actual_dropoff_time) {
                return 'returning';
            }
            if (activeRide.actual_pickup_time || activeRide.status === 'in_progress') {
                return 'in_transit';
            }
            return 'to_pickup';
        }

        const idle = detail?.idle_activity || vehicle.idle_activity;
        if (idle === 'to_charger') return 'to_charger';
        if (idle === 'charging') return 'charging';
        if (idle === 'standby_at_charger') return 'standby_at_charger';
        return 'available';
    }

    function renderVehicleMarkers(vehicles, rides) {
        const visibleIds = new Set(getVisibleVehicleIds(vehicles, rides));
        const vehicleFilter = document.getElementById('vehicleFilter')?.value || 'all';

        vehicles.forEach(vehicle => {
            const vehicleId = vehicle.id;
            if (!visibleIds.has(vehicleId) && rideSearch) {
                if (vehicleMarkers[vehicleId]) {
                    layerConfig.vehicles.group.removeLayer(vehicleMarkers[vehicleId]);
                    delete vehicleMarkers[vehicleId];
                }
                return;
            }

            const lat = parseFloat(vehicle.current_latitude);
            const lng = parseFloat(vehicle.current_longitude);
            const templateName = vehicle.template?.name || `Voertuig #${vehicleId}`;
            const activeRide = rides.find(r => r.vehicle_id === vehicle.id);
            const state = determineVehicleState(vehicle, rides);

            const previous = vehiclePreviousPositions[vehicleId];
            // Apply vehicle filter (e.g. show only vehicles heading to charger)
            if (vehicleFilter !== 'all') {
                if (vehicleFilter === 'to_charger' && state !== 'to_charger') return;
                if (vehicleFilter === 'available' && state !== 'available') return;
            }
            const stateInfo = vehicleStateIcons[state] || vehicleStateIcons.available;
            const chargerName = getChargerLabel(vehicle, activeRide);

            if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;

            let statusLine = stateInfo.label;
            if (state === 'to_charger' && chargerName) {
                statusLine = `Rijdt naar ${chargerName}`;
            } else if (state === 'charging' && chargerName) {
                statusLine = `Laadt op bij ${chargerName}`;
            }

            let rideProgressHtml = '';
            if (activeRide) {
                const phaseLabels = {
                    scheduled: 'Gepland',
                    to_pickup: 'Naar ophaalpunt',
                    in_transit: 'Naar bestemming',
                    heading_to_charger: 'Naar laadstation',
                    charging_at_stop: 'Laden',
                    returning: 'Terugrijdend',
                    completed: 'Voltooid'
                };
                const phaseLabel = phaseLabels[activeRide.simulation_phase] || activeRide.status || 'Onbekend';
                const rawProgress = activeRide.progress_percent ?? activeRide.progress ?? 0;
                const progressValue = Number.isFinite(Number(rawProgress))
                    ? Math.max(0, Math.min(100, Number(rawProgress)))
                    : 0;
                rideProgressHtml = `
                    <div style="margin-top: 8px;">
                        <div style="height: 8px; border-radius: 4px; overflow:hidden; background:#e9ecef; margin-bottom:4px;">
                            <div role="progressbar" aria-valuenow="${progressValue}" aria-valuemin="0" aria-valuemax="100"
                                style="width:${progressValue}%; background:${stateInfo.color}; height:100%;"></div>
                        </div>
                        <small style="color:#333;">${phaseLabel} · ${progressValue.toFixed(1)}%</small>
                    </div>`;
            }

            const popupContent = `
            <div>
                <strong>${templateName}</strong><br>
                <span style="color: ${stateInfo.color};">
                    <i class="fa ${stateInfo.icon}"></i> ${statusLine}
                </span><br>
                Batterij: ${vehicle.battery_level ?? 'N/A'}%<br>
                ${activeRide ? `Rit #${activeRide.id}${activeRide.customer_name ? ' — ' + activeRide.customer_name : ''}<br>` : ''}
                ${rideProgressHtml}
                ID: ${vehicleId}
            </div>`;

            const icon = createVehicleIcon(stateInfo);

            if (vehicleMarkers[vehicleId]) {
                vehicleMarkers[vehicleId].setLatLng([lat, lng]);
                vehicleMarkers[vehicleId].setPopupContent(popupContent);
                vehicleMarkers[vehicleId].setIcon(icon);
                // ensure click selects vehicle for battery HUD and still opens the popup
                vehicleMarkers[vehicleId].off('click');
                vehicleMarkers[vehicleId].on('click', function() {
                    selectedVehicleId = vehicleId;
                    if (batteryHudMode === 'selected') fetchBatteryAnalysis();
                    this.openPopup();
                });
            } else {
                vehicleMarkers[vehicleId] = L.marker([lat, lng], { icon })
                    .bindPopup(popupContent)
                    .addTo(layerConfig.vehicles.group)
                    .on('click', function() {
                        selectedVehicleId = vehicleId;
                        if (batteryHudMode === 'selected') fetchBatteryAnalysis();
                        this.openPopup();
                    });
            }
        });
    }

    function renderRideLayers(rides) {
        updateRoutePolylines(rides);
        updateRouteMarkers(rides);
    }

    function createVehicleIcon(stateInfo) {
        return L.divIcon({
            html: `<div class="vehicle-marker" style="background-color: ${stateInfo.color};"><i class="fas ${stateInfo.icon}"></i></div>`,
            className: 'vehicle-icon',
            iconSize: [40, 40],
            iconAnchor: [20, 20],
            popupAnchor: [0, -20]
        });
    }

    function updateRoutePolylines(rides) {
        if (!layerConfig.routes.enabled) {
            layerConfig.routes.group.clearLayers();
            routePolylines = {};
            return;
        }

        rides.forEach(ride => {
            const rideId = ride.id;
            if (!ride.waypoints || !Array.isArray(ride.waypoints) || ride.waypoints.length === 0) return;

            const latlngs = ride.waypoints.map(wp => [wp[0], wp[1]]);
            if (routePolylines[rideId]) {
                routePolylines[rideId].setLatLngs(latlngs);
            } else {
                const polyline = L.polyline(latlngs, {
                    color: '#2196f3',
                    weight: 3,
                    opacity: 0.6,
                    dashArray: '10, 5',
                    className: 'planned-route'
                }).bindPopup(`
                    <div>
                        <strong>Rit #${rideId}</strong><br>
                        Status: ${ride.status}<br>
                        Van: ${ride.pickup_address || 'N/A'}<br>
                        Naar: ${ride.dropoff_address || 'N/A'}<br>
                        Passagier: ${ride.customer_name || 'N/A'}
                    </div>
                `).addTo(layerConfig.routes.group);
                routePolylines[rideId] = polyline;
            }
        });

        Object.keys(routePolylines).forEach(rideId => {
            if (!rides.some(r => r.id == rideId)) {
                layerConfig.routes.group.removeLayer(routePolylines[rideId]);
                delete routePolylines[rideId];
            }
        });
    }

    function updateTracePathsFromBackend(tracesByRide, rideIds) {
        if (!layerConfig.traces.enabled) return;

        rideIds.forEach(rideId => {
            const path = tracesByRide[String(rideId)];
            if (!path || path.length < 2) return;

            if (tracePolylines[rideId]) {
                tracePolylines[rideId].setLatLngs(path);
            } else {
                tracePolylines[rideId] = L.polyline(path, {
                    color: '#ff6b6b',
                    weight: 4,
                    opacity: 0.85,
                    className: 'trace-path'
                }).bindPopup(`<strong>Afgelegde weg — Rit #${rideId}</strong>`)
                  .addTo(layerConfig.traces.group);
            }
        });

        Object.keys(tracePolylines).forEach(rideId => {
            if (!rideIds.includes(Number(rideId)) && !rideIds.includes(rideId)) {
                layerConfig.traces.group.removeLayer(tracePolylines[rideId]);
                delete tracePolylines[rideId];
            }
        });
    }

    function clearTraces() {
        layerConfig.traces.group.clearLayers();
        tracePolylines = {};
    }

    function updateVehicleTrails(trailsByVehicle, vehicleIds) {
        if (!layerConfig.trails.enabled) return;

        vehicleIds.forEach(vehicleId => {
            const history = trailsByVehicle[String(vehicleId)];
            if (!history || history.length < 2) return;

            if (vehicleTrailLines[vehicleId]) {
                vehicleTrailLines[vehicleId].setLatLngs(history);
            } else {
                vehicleTrailLines[vehicleId] = L.polyline(history, {
                    color: '#424242',
                    weight: 4,
                    opacity: 0.75,
                    lineJoin: 'round'
                }).bindPopup(`<strong>Voertuigspoor #${vehicleId}</strong>`)
                  .addTo(layerConfig.trails.group);
            }
        });

        Object.keys(vehicleTrailLines).forEach(vehicleId => {
            if (!vehicleIds.includes(Number(vehicleId)) && !vehicleIds.includes(vehicleId)) {
                layerConfig.trails.group.removeLayer(vehicleTrailLines[vehicleId]);
                delete vehicleTrailLines[vehicleId];
            }
        });
    }

    function clearTrails() {
        layerConfig.trails.group.clearLayers();
        vehicleTrailLines = {};
    }

    function formatKm(km) {
        if (km == null || km === '' || Number.isNaN(Number(km))) return '—';
        return new Intl.NumberFormat('nl-BE', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(Number(km)) + ' km';
    }

    function formatPct(value) {
        if (value == null || value === '' || Number.isNaN(Number(value))) return '—';
        return new Intl.NumberFormat('nl-BE', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(Number(value)) + '%';
    }

    function calculateDistanceKm(lat1, lng1, lat2, lng2) {
        const toRad = v => Number(v) * Math.PI / 180;
        const R = 6371;
        const dLat = toRad(lat2 - lat1);
        const dLng = toRad(lng2 - lng1);
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    function getMidpoint(from, to) {
        return [(from[0] + to[0]) / 2, (from[1] + to[1]) / 2];
    }

    function getNearestCachedCharger(from, exclude) {
        if (!cachedChargers || !cachedChargers.length) return null;
        const candidates = cachedChargers
            .map(charger => {
                const lat = Number(charger.latitude || charger.lat || 0);
                const lng = Number(charger.longitude || charger.lng || 0);
                return {
                    charger,
                    lat,
                    lng,
                    distance: calculateDistanceKm(from[0], from[1], lat, lng)
                };
            })
            .filter(c => c.lat && c.lng)
            .sort((a, b) => a.distance - b.distance);

        if (!exclude) return candidates[0] || null;
        return candidates.find(candidate =>
            Math.abs(candidate.lat - exclude.latitude) > 1e-5 || Math.abs(candidate.lng - exclude.longitude) > 1e-5
        ) || null;
    }

    function renderDebugChargerLine(from, to, text, color, dashArray = '6, 6') {
        L.polyline([from, to], {
            color,
            weight: 2,
            opacity: 0.9,
            dashArray
        }).addTo(layerConfig.battery.group);

        L.marker(getMidpoint(from, to), {
            icon: L.divIcon({
                className: 'debug-label',
                html: `<div>${text}</div>`,
                iconSize: [180, 28],
                iconAnchor: [90, 14]
            }),
            interactive: false
        }).addTo(layerConfig.battery.group);
    }

    function metricLabel(text, tip) {
        return `<span class="battery-hud__label">${text}<span class="info-tip" tabindex="0" aria-label="Meer info"><i class="fas fa-info-circle"></i><span class="info-tip__bubble">${tip}</span></span></span>`;
    }

    function renderBatteryHudRow(labelHtml, valueHtml, subHtml = '') {
        return `<div class="battery-hud__row">${labelHtml}<div class="battery-hud__value">${valueHtml}${subHtml}</div></div>`;
    }

    function formatBatteryPopup(item) {
        const target = item.active_charger_target || item.nearest_charger;
        return `
            <strong>Voertuig #${item.vehicle_id}</strong> (${item.context || 'sim'})<br>
            Doel: ${item.distance_target_label || '—'}<br>
            Batterij: ${item.battery_pct}% · ${item.consumption_kwh_per_km} kWh/km · ${item.battery_capacity_kwh} kWh<br>
            Afstand doel: ${formatKm(item.remaining_distance_km)}<br>
            Benodigd: ${formatPct(item.battery_needed_pct)}<br>
            Resterend na doel: ${formatPct(item.projected_remaining_pct)} (marge ${item.safety_margin_pct}%)<br>
            Bereikbaar: <strong>${item.can_reach_destination ? 'ja' : 'nee'}</strong><br>
            ${target?.name ? `Laadstation: ${target.name}<br>` : ''}
            <small>${item.formula || ''}</small>
        `;
    }

    function renderBatteryHud(items) {
        const hud = document.getElementById('batteryHudContent');
        if (!items.length) {
            hud.innerHTML = '<p class="battery-hud__empty">Wacht op simulator-tick…</p>';
            return;
        }
        hud.innerHTML = items.map(item => {
            const ok = item.can_reach_destination;
            const target = item.active_charger_target || item.nearest_charger;
            const goalSub = item.distance_target_label
                ? `<small class="battery-hud__sub">${item.distance_target_label}</small>` : '';
            return `
                <div class="battery-hud__card ${ok ? 'ok' : 'warn'}">
                    <div class="battery-hud__card-title">Voertuig #${item.vehicle_id}${item.ride_id ? ` · rit #${item.ride_id}` : ''}</div>
                    ${renderBatteryHudRow(metricLabel('Batterij', batteryMetricTips.battery), `<strong>${item.battery_pct}%</strong>`)}
                    ${renderBatteryHudRow(metricLabel('Afstand doel', batteryMetricTips.distance), `<strong>${formatKm(item.remaining_distance_km)}</strong>`, goalSub)}
                    ${renderBatteryHudRow(metricLabel('Benodigd', batteryMetricTips.needed), `<strong>${formatPct(item.battery_needed_pct)}</strong>`)}
                    ${renderBatteryHudRow(metricLabel('Resterend na doel', batteryMetricTips.projection), `<strong>${formatPct(item.projected_remaining_pct)}</strong>`)}
                    ${renderBatteryHudRow(metricLabel('Bereikbaar', batteryMetricTips.reachable), `<strong>${ok ? 'ja' : 'nee'}</strong>`)}
                    ${target?.name ? `<div class="battery-hud__target">${metricLabel('Doel', batteryMetricTips.target)} <strong>→ ${target.name}</strong></div>` : ''}
                    <div class="battery-hud__time">${item.checked_at ? new Date(item.checked_at).toLocaleTimeString('nl-BE') : ''}</div>
                </div>
            `;
        }).join('');
    }

    function updateBatteryLayer(items) {
        if (!layerConfig.battery.enabled) return;
        // If HUD is in 'selected' mode, show only the selected vehicle or a helpful prompt.
        if (batteryHudMode === 'selected') {
            if (selectedVehicleId) {
                items = (items || []).filter(it => Number(it.vehicle_id) === Number(selectedVehicleId));
            } else {
                clearBatteryLayer();
                document.getElementById('batteryHudContent').innerHTML = '<div class="battery-hud__empty">Klik op een voertuig om batterijkansen te bekijken.</div>';
                return;
            }
        }
        clearBatteryLayer();
        renderBatteryHud(items);

        items.forEach(item => {
            const vid = item.vehicle_id;
            const from = [item.latitude, item.longitude];
            const target = item.active_charger_target;
            const preview = item.nearest_charger;
            if (!target && !preview) return;

            const primary = target || preview;
            const primaryColor = item.can_reach_destination ? '#2e7d32' : '#c62828';

            L.polyline([from, [primary.latitude, primary.longitude]], {
                color: primaryColor,
                weight: 3,
                opacity: 0.95,
                dashArray: '6, 4'
            }).bindPopup(formatBatteryPopup(item))
              .addTo(layerConfig.battery.group);

            L.circleMarker([primary.latitude, primary.longitude], {
                radius: 6,
                color: primaryColor,
                fillColor: primaryColor,
                fillOpacity: 0.75,
                weight: 2
            }).bindPopup(formatBatteryPopup(item))
              .addTo(layerConfig.battery.group);

            if (target) {
                renderDebugChargerLine(from, [target.latitude, target.longitude],
                    `Actief doel: ${target.name || 'Laadstation'} (${formatKm(item.remaining_distance_km)})`,
                    '#1976d2', '6, 6');
            }

            const nearestCache = getNearestCachedCharger(from, target);
            if (nearestCache && (!target || Math.abs(nearestCache.lat - target.latitude) > 1e-5 || Math.abs(nearestCache.lng - target.longitude) > 1e-5)) {
                renderDebugChargerLine(from, [nearestCache.lat, nearestCache.lng],
                    `Alternatief doel: ${nearestCache.charger.name || 'Laadstation'} (${formatKm(nearestCache.distance)})`,
                    '#ff9800', '8, 4');
            }

            if (!target && preview) {
                renderDebugChargerLine(from, [preview.latitude, preview.longitude],
                    `Gecontroleerd doel: ${preview.name || 'Laadstation'} (${formatKm(preview.distance_km)})`,
                    '#6a1b9a', '4, 3');
            }
        });
    }

    function clearBatteryLayer() {
        layerConfig.battery.group.clearLayers();
        batteryLines = {};
        batteryMarkers = {};
        document.getElementById('batteryHudContent').innerHTML = '';
    }

    function updateRouteMarkers(rides) {
        if (!layerConfig.waypoints.enabled) {
            layerConfig.waypoints.group.clearLayers();
            waypointMarkers = {};
            startFinishMarkers = {};
            return;
        }

        const seenPickups = new Set();
        const seenDropoffs = new Set();

        rides.forEach(ride => {
            const rideId = ride.id;

            
            if (ride.pickup_latitude && ride.pickup_longitude) {
                const pickupKey = `${ride.pickup_latitude},${ride.pickup_longitude}`;

                if (!seenPickups.has(pickupKey)) {
                    if (!startFinishMarkers[`pickup_${rideId}`]) {
                        const icon = L.divIcon({
                            html: '<i class="fas fa-flag-checkered" style="color: #000; font-size: 20px;"></i>',
                            className: 'start-finish-marker',
                            iconSize: [30, 30],
                            iconAnchor: [15, 15]
                        });

                        const marker = L.marker(
                                [ride.pickup_latitude, ride.pickup_longitude], {
                                    icon
                                }
                            )
                            .bindPopup(`
                        <div>
                            <strong>Ophaalpunt</strong><br>
                            ${ride.pickup_address || 'N/A'}<br>
                            Passagier: ${ride.customer_name || 'N/A'}
                        </div>
                    `)
                            .addTo(layerConfig.waypoints.group);

                        startFinishMarkers[`pickup_${rideId}`] = marker;
                        seenPickups.add(pickupKey);
                    }
                }
            }

            
            if (ride.dropoff_latitude && ride.dropoff_longitude) {
                const dropoffKey = `${ride.dropoff_latitude},${ride.dropoff_longitude}`;

                if (!seenDropoffs.has(dropoffKey)) {
                    if (!startFinishMarkers[`dropoff_${rideId}`]) {
                        const icon = L.divIcon({
                            html: '<i class="fas fa-flag" style="color: #f44336; font-size: 20px;"></i>',
                            className: 'finish-marker',
                            iconSize: [30, 30],
                            iconAnchor: [15, 15]
                        });

                        const marker = L.marker(
                                [ride.dropoff_latitude, ride.dropoff_longitude], {
                                    icon
                                }
                            )
                            .bindPopup(`
                        <div>
                            <strong>Eindbestemming</strong><br>
                            ${ride.dropoff_address || 'N/A'}
                        </div>
                    `)
                            .addTo(layerConfig.waypoints.group);

                        startFinishMarkers[`dropoff_${rideId}`] = marker;
                        seenDropoffs.add(dropoffKey);
                    }
                }
            }

            
            if (ride.waypoints && Array.isArray(ride.waypoints) && ride.waypoints.length > 2) {
                const totalIntermediates = ride.waypoints.length - 2;
                const sampleStep = totalIntermediates > 12 ? 3 : 1;

                for (let i = 1; i < ride.waypoints.length - 1; i += sampleStep) {
                    const wp = ride.waypoints[i];
                    const waypointKey = `waypoint_${rideId}_${i}`;

                    if (!waypointMarkers[waypointKey]) {
                        const icon = L.divIcon({
                            html: '<i class="fas fa-map-pin" style="color: #f44336;"></i>',
                            className: 'waypoint-marker',
                            iconSize: [25, 25],
                            iconAnchor: [12, 12]
                        });

                        const marker = L.marker([wp[0], wp[1]], {
                                icon
                            })
                            .bindPopup(`<div><strong>Routepunt ${i}</strong></div>`)
                            .addTo(layerConfig.waypoints.group);

                        waypointMarkers[waypointKey] = marker;
                    }
                }

                if (sampleStep > 1) {
                    const lastIndex = ride.waypoints.length - 2;
                    if ((lastIndex - 1) % sampleStep !== 0) {
                        const wp = ride.waypoints[lastIndex];
                        const waypointKey = `waypoint_${rideId}_${lastIndex}`;
                        if (!waypointMarkers[waypointKey]) {
                            const icon = L.divIcon({
                                html: '<i class="fas fa-map-pin" style="color: #f44336;"></i>',
                                className: 'waypoint-marker',
                                iconSize: [25, 25],
                                iconAnchor: [12, 12]
                            });

                            const marker = L.marker([wp[0], wp[1]], {
                                    icon
                                })
                                .bindPopup(`<div><strong>Routepunt ${lastIndex}</strong></div>`)
                                .addTo(layerConfig.waypoints.group);

                            waypointMarkers[waypointKey] = marker;
                        }
                    }
                }
            }
        });

        
        Object.keys(startFinishMarkers).forEach(key => {
            const rideId = key.split('_')[1];
            if (!rides.some(r => r.id == rideId)) {
                layerConfig.waypoints.group.removeLayer(startFinishMarkers[key]);
                delete startFinishMarkers[key];
            }
        });

        Object.keys(waypointMarkers).forEach(key => {
            const rideId = key.split('_')[1];
            if (!rides.some(r => r.id == rideId)) {
                layerConfig.waypoints.group.removeLayer(waypointMarkers[key]);
                delete waypointMarkers[key];
            }
        });
    }

    window.addEventListener('beforeunload', function() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
    });

    function renderChargerMarkers(chargers, group) {
        if (!layerConfig.chargers.enabled) return;
        group.clearLayers();

        chargers.forEach(charger => {
            const lat = parseFloat(charger.latitude);
            const lng = parseFloat(charger.longitude);

            if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;

            
            let iconHtml = '<i class="fas fa-charging-station" style="color: #4caf50; font-size: 18px;"></i>';
            let chargerTypeLabel = 'Laadstation';

            switch (charger.charger_type) {
                case 'ultra_fast':
                    iconHtml = '<i class="fas fa-bolt" style="color: #ff5722; font-size: 20px;"></i>';
                    chargerTypeLabel = 'Ultrasnel laden';
                    break;
                case 'fast':
                    iconHtml = '<i class="fas fa-charging-station" style="color: #ff9800; font-size: 18px;"></i>';
                    chargerTypeLabel = 'Snel laden';
                    break;
                case 'slow':
                    iconHtml = '<i class="fas fa-plug" style="color: #2196f3; font-size: 16px;"></i>';
                    chargerTypeLabel = 'Traag laden';
                    break;
            }

            const icon = L.divIcon({
                html: iconHtml,
                className: 'charger-marker',
                iconSize: [25, 25],
                iconAnchor: [12, 12]
            });

            const popupContent = `
            <div>
                <strong>${charger.name || 'Laadstation'}</strong><br>
                <span style="color: #666;">${chargerTypeLabel}</span><br>
                <small>Max vermogen: ${charger.max_power_kw || 'N/A'} kW</small><br>
                <small>Beschikbare slots: ${charger.available_slots || 'N/A'}</small><br>
                <small>Adres: ${charger.address || 'N/A'}</small>
            </div>
        `;

            L.marker([lat, lng], { icon })
                .bindPopup(popupContent)
                .addTo(group);
        });
    }

    function updateChargingStations(chargers) {
        renderChargerMarkers(chargers, layerConfig.chargers.group);
    }

    function updateCachedChargingStations(chargers) {
        cachedChargers = Array.isArray(chargers) ? chargers : [];
        renderChargerMarkers(chargers, layerConfig.chargers.cacheGroup);
        renderCachedChargerPanel(chargers);
    }

    function renderCachedChargerPanel(chargers) {
        const panel = document.getElementById('cachedChargersPanel');
        if (!panel) return;

        const count = Array.isArray(chargers) ? chargers.length : 0;
        panel.textContent = `Gecachte laadstations: ${count}`;
        panel.style.display = 'block';
    }
</script>

<style>
    .vehicle-icon {
        background: linear-gradient(135deg, #007bff, #0056b3);
        border-radius: 50%;
        border: 3px solid white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: white;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.4);
        transition: all 0.3s ease;
    }

    .vehicle-icon:hover {
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
        transform: scale(1.1);
    }

    .debug-label {
        pointer-events: none;
        background: rgba(0, 0, 0, 0.72);
        color: #fff;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 11px;
        line-height: 1.2;
        text-align: center;
        white-space: nowrap;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
    }

    .vehicle-marker {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }

    .start-finish-marker,
    .finish-marker,
    .waypoint-marker {
        background: transparent !important;
    }

    .map-legend {
        position: relative;
        z-index: 400;
    }

    .map-legend .card {
        border: 1px solid #dee2e6;
        border-radius: 6px;
    }

    .map-legend p {
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }

    .map-legend i {
        margin-right: 8px;
        width: 16px;
        text-align: center;
    }

    .leaflet-popup-content {
        font-size: 12px;
        min-width: 180px;
        color: #111;
        background: #fff;
    }

    .leaflet-popup-content-wrapper {
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
        border-radius: 10px;
        padding: 10px;
    }

    .leaflet-popup-content strong {
        display: block;
        margin-bottom: 4px;
        color: #111;
    }

    .leaflet-popup-content br {
        margin-bottom: 4px;
    }

    .map-frame {
        position: relative;
    }

    #map {
        border: 1px solid #dee2e6;
        border-radius: 8px;
    }

    .map-layer-panel {
        position: absolute;
        top: 12px;
        right: 12px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
        max-width: 280px;
    }

    .map-layer-panel__toggle {
        width: 42px;
        height: 42px;
        border: none;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
        color: #333;
        cursor: pointer;
    }

    .map-layer-panel__body {
        display: none;
        width: 260px;
        background: rgba(255, 255, 255, 0.97);
        border-radius: 10px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        padding: 12px;
        backdrop-filter: blur(4px);
    }

    .map-layer-panel__body.is-open {
        display: block;
    }

    .map-layer-panel__title {
        font-weight: 600;
        margin-bottom: 10px;
        font-size: 0.95rem;
    }

    .map-layer-panel__subtitle {
        font-size: 0.8rem;
        color: #666;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .map-layer-panel__section + .map-layer-panel__section {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #eee;
    }

    .layer-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 8px;
        font-size: 0.88rem;
        cursor: pointer;
        user-select: none;
    }

    .layer-toggle--locked {
        cursor: default;
        opacity: 0.85;
    }

    .layer-toggle__label {
        flex: 1;
    }

    .layer-toggle__state {
        font-size: 0.75rem;
        font-weight: 600;
        color: #2e7d32;
    }

    .layer-toggle__switch {
        width: 38px;
        height: 22px;
        border-radius: 11px;
        background: #cfd8dc;
        position: relative;
        transition: background 0.2s;
        flex-shrink: 0;
    }

    .layer-toggle__switch::after {
        content: '';
        position: absolute;
        top: 2px;
        left: 2px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.25);
        transition: transform 0.2s;
    }

    .layer-toggle__switch.is-on {
        background: #4caf50;
    }

    .layer-toggle__switch.is-on::after {
        transform: translateX(16px);
    }

    .cached-chargers-panel {
        display: none;
        max-height: 220px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 10px;
        margin-top: 10px;
        font-size: 0.9rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .cached-chargers-panel.d-none {
        display: none;
    }
    .cached-chargers-count {
        font-weight: 600;
        margin-bottom: 8px;
    }
    .cached-charger-item {
        padding: 8px 0;
        border-bottom: 1px solid #f1f1f1;
    }
    .cached-charger-item:last-child {
        border-bottom: none;
    }
    .cached-chargers-empty {
        color: #666;
    }
    .charger-cache-status {
        font-size: 0.72rem;
        color: #666;
        margin-top: 6px;
        line-height: 1.35;
    }

    .battery-hud {
        position: absolute;
        left: 12px;
        bottom: 12px;
        z-index: 1000;
        width: min(360px, calc(100% - 24px));
        max-height: 42%;
        overflow: auto;
        background: rgba(255, 255, 255, 0.96);
        border-radius: 10px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        padding: 10px 12px;
        backdrop-filter: blur(4px);
    }

    .battery-hud__title {
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 8px;
    }

    .battery-hud__content {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .battery-hud__card {
        border-radius: 8px;
        padding: 8px 10px;
        border-left: 4px solid #ccc;
        background: #fafafa;
        font-size: 0.8rem;
    }

    .battery-hud__card.ok {
        border-left-color: #2e7d32;
    }

    .battery-hud__card.warn {
        border-left-color: #c62828;
    }

    .battery-hud__card-title {
        font-weight: 600;
        margin-bottom: 4px;
    }

    .battery-hud__row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 4px;
    }

    .battery-hud__label {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #444;
    }

    .battery-hud__value {
        text-align: right;
        flex-shrink: 0;
    }

    .battery-hud__sub {
        display: block;
        font-size: 0.68rem;
        color: #777;
        font-weight: normal;
        margin-top: 2px;
    }

    .battery-hud__target {
        margin-top: 6px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        color: #555;
        font-size: 0.78rem;
    }

    .info-tip {
        position: relative;
        display: inline-flex;
        color: #90a4ae;
        cursor: help;
        outline: none;
    }

    .info-tip i {
        font-size: 0.75rem;
    }

    .info-tip__bubble {
        display: none;
        position: absolute;
        left: 50%;
        bottom: calc(100% + 8px);
        transform: translateX(-50%);
        width: 220px;
        padding: 8px 10px;
        background: #263238;
        color: #fff;
        font-size: 0.72rem;
        line-height: 1.4;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        z-index: 2000;
        font-weight: normal;
        text-align: left;
        pointer-events: none;
    }

    .info-tip__bubble::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 6px solid transparent;
        border-top-color: #263238;
    }

    .info-tip:hover .info-tip__bubble,
    .info-tip:focus .info-tip__bubble {
        display: block;
    }

    .battery-hud__time {
        margin-top: 4px;
        font-size: 0.7rem;
        color: #888;
    }

    .battery-hud__empty {
        margin: 0;
        color: #666;
        font-size: 0.82rem;
    }

    .legend-line-planned {
        display: inline-block;
        width: 30px;
        height: 3px;
        background: linear-gradient(to right, #2196f3 0%, #2196f3 50%, transparent 50%, transparent 100%);
        background-size: 6px 3px;
        background-repeat: repeat-x;
        vertical-align: middle;
        margin-right: 8px;
    }

    .legend-line-trace {
        display: inline-block;
        width: 30px;
        height: 3px;
        background: #ff6b6b;
        vertical-align: middle;
        margin-right: 8px;
    }

    .legend-line-trail {
        display: inline-block;
        width: 30px;
        height: 3px;
        background: #424242;
        vertical-align: middle;
        margin-right: 8px;
    }

    .legend-line-battery {
        display: inline-block;
        width: 30px;
        height: 0;
        border-top: 2px dashed #2e7d32;
        vertical-align: middle;
        margin-right: 8px;
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(33, 150, 243, 0.7);
        }

        70% {
            box-shadow: 0 0 0 10px rgba(33, 150, 243, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(33, 150, 243, 0);
        }
    }

    .vehicle-icon[data-state="in_transit"] {
        animation: pulse 2s infinite;
    }
</style>