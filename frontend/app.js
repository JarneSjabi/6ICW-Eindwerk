


const API_CONFIG = {
    backend: {
        baseUrl: 'http://localhost:5000/api',
        endpoints: {
            rides: '/rides',
            vehicles: '/vehicles',
            routes: '/routes',
            price: '/price'
        }
    },
    map: {
        defaultCenter: [50.8503, 4.3517],
        defaultZoom: 13,
        tileUrl: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'
    },
    app: {
        autoRefreshInterval: 30000,
        vehicleTrackingInterval: 3000,
        eventsRefreshInterval: 4000
    }
};


let appState = {
    currentUser: null,
    currentRideId: null,
    maps: {
        request: null,
        picker: null,
        rideTrack: null
    },
    markers: {
        start: null,
        destination: null,
        pickerStart: null,
        pickerDestination: null,
        vehicleLocation: null,
        ridePickup: null,
        rideDropoff: null,
        rideRouteLine: null
    },
    intervals: {
        vehicleTracking: null,
        progressTracking: null,
        eventsRefresh: null,
        autoRefresh: null
    }
};

function userQueryParam() {
    const id = appState.currentUser?.id;
    return id != null ? `?user_id=${encodeURIComponent(id)}` : '';
}


document.addEventListener('DOMContentLoaded', () => {
    initializeApp();
});

async function initializeApp() {
    console.log('Initializing application...');

    await loadUserSession();

    initRequestMap();

    setupEventListeners();

    await loadRides();

    setupAutoRefresh();

    console.log('Application initialized successfully');
}

async function loadUserSession() {
    try {
        await loadAvailableUsers();

        const userSelect = document.getElementById('user-select');
        const currentUserId = userSelect.value || 1;

        await switchUser(Number(currentUserId));
    } catch (error) {
        console.error('Failed to load user session:', error);
        showAlert('Fout bij het laden van gebruiker', 'danger');
    }
}

async function loadAvailableUsers() {
    try {
        
        const users = [
            { id: 1, name: 'Gebruiker 1', email: 'demo1@example.com', phone: '+32 123 456 789', balance: 25.50 },
            { id: 2, name: 'Gebruiker 2', email: 'demo2@example.com', phone: '+32 987 654 321', balance: 15.00 }
        ];
        
        const userSelect = document.getElementById('user-select');
        userSelect.innerHTML = '';
        
        users.forEach(user => {
            const option = document.createElement('option');
            option.value = user.id;
            option.textContent = user.name;
            userSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Failed to load users:', error);
    }
}

async function switchUser(userId) {
    try {
        
        const users = {
            1: { id: 1, name: 'Demo Gebruiker', email: 'demo1@example.com', phone: '+32 123 456 789', balance: 25.50 },
            2: { id: 2, name: 'Demo Gebruiker 2', email: 'demo2@example.com', phone: '+32 987 654 321', balance: 15.00 }
        };
        
        appState.currentUser = users[Number(userId)] || users[1];
        updateUserDisplay();
        
        
        await loadRides();
    } catch (error) {
        console.error('Failed to switch user:', error);
    }
}

function updateUserDisplay() {
    const user = appState.currentUser || {};
    const name = user.name || 'Demo Gebruiker';
    const email = user.email || '-';
    const phone = user.phone || '-';

    const accountName = document.getElementById('account-name');
    const accountEmail = document.getElementById('account-email');
    const accountPhone = document.getElementById('account-phone');
    const creditBadge = document.getElementById('credit-badge');
    const userSelect = document.getElementById('user-select');

    if (accountName) accountName.textContent = name;
    if (accountEmail) accountEmail.textContent = email;
    if (accountPhone) accountPhone.textContent = phone;
    if (userSelect && user.id != null) userSelect.value = user.id;
}

function toggleScheduledTime() {
    const scheduledContainer = document.getElementById('scheduled-time-container');
    const scheduledRadio = document.getElementById('pickup-scheduled');
    
    if (scheduledRadio.checked) {
        scheduledContainer.style.display = 'block';
        document.getElementById('scheduled-pickup-time').required = true;
    } else {
        scheduledContainer.style.display = 'none';
        document.getElementById('scheduled-pickup-time').required = false;
    }
}


function setupEventListeners() {
    
    document.getElementById('ride-request-form').addEventListener('submit', handleRideSubmit);
    
    
    document.getElementById('open-map-picker').addEventListener('click', openMapPicker);
    document.getElementById('clear-map-selection').addEventListener('click', clearMapSelection);
    
    
    document.getElementById('reset-map-selection').addEventListener('click', resetMapPicker);
    document.getElementById('apply-map-selection').addEventListener('click', applyMapSelection);
    
    
    document.getElementById('start-address').addEventListener('change', geocodeAddress);
    document.getElementById('destination-address').addEventListener('change', geocodeAddress);
    
    
    document.querySelectorAll('input[name="pickup-time-type"]').forEach(radio => {
        radio.addEventListener('change', toggleScheduledTime);
    });
    
    
    document.getElementById('change-password-btn').addEventListener('click', () => {
        showAlert('Wachtwoord wijzigen komt binnenkort beschikbaar', 'info');
    });
    
    
    document.getElementById('user-select').addEventListener('change', (e) => {
        switchUser(Number(e.target.value));
    });
    
    
    document.getElementById('rideModal').addEventListener('hidden.bs.modal', cleanupRideTracking);
}


function showSection(section) {
    
    document.querySelectorAll('.section').forEach(s => s.style.display = 'none');
    
    
    const sectionEl = document.getElementById(`${section}-section`);
    if (sectionEl) {
        sectionEl.style.display = 'block';
    }
    
    
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    const activeLink = document.querySelector(`[href="#"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
    
    
    loadSectionData(section);
}

async function loadSectionData(section) {
    switch (section) {
        case 'rides':
            initRequestMap();
            await loadRides();
            break;
        case 'history':
            await loadRideHistory();
            break;
        case 'account':
            updateUserDisplay();
            break;
    }
}

function logout() {
    if (confirm('Wilt u echt afmelden?')) {
        
        window.location.href = '../admin/logout.php';
    }
}


async function loadRides() {
    try {
        const uid = appState.currentUser?.id;
        const url = uid != null
            ? `${API_CONFIG.backend.baseUrl}${API_CONFIG.backend.endpoints.rides}?user_id=${encodeURIComponent(uid)}`
            : `${API_CONFIG.backend.baseUrl}${API_CONFIG.backend.endpoints.rides}`;
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        let rides = await response.json();
        
        const curId = uid != null ? Number(uid) : null;
        if (curId != null) {
            rides = rides.filter(r => Number(r.user_id) === curId);
        }
        
        
        const activeRides = rides.filter(r => r.status === 'pending' || r.status === 'assigned' || r.status === 'in_progress');
        
        const ridesList = document.getElementById('rides-list');
        
        if (!activeRides || activeRides.length === 0) {
            ridesList.innerHTML = `
                <div class="list-group-item text-center text-muted py-4">
                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                    <p class="mt-2">Geen actieve ritten</p>
                </div>
            `;
            return;
        }
        
        ridesList.innerHTML = '';
        activeRides.forEach(ride => {
            const rideEl = createRideElement(ride);
            ridesList.appendChild(rideEl);
        });
        
        
        loadHistory(rides);
        
    } catch (error) {
        console.error('Error loading rides:', error);
        showAlert('Fout bij het laden van ritten', 'danger');
    }
}

async function loadHistory(allRides) {
    try {
        
        const completedRides = allRides.filter(r => r.status === 'completed');
        
        const historyList = document.getElementById('history-list');
        
        if (!completedRides || completedRides.length === 0) {
            historyList.innerHTML = `
                <div class="col-12 text-center text-muted py-4">
                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                    <p class="mt-2">Geen ritgeschiedenis gevonden</p>
                </div>
            `;
            return;
        }
        
        historyList.innerHTML = '';
        completedRides.forEach(ride => {
            const rideEl = createHistoryElement(ride);
            const col = document.createElement('div');
            col.className = 'col-md-6 mb-3';
            col.appendChild(rideEl);
            historyList.appendChild(col);
        });
        
    } catch (error) {
        console.error('Error loading history:', error);
    }
}

function createHistoryElement(ride) {
    const card = document.createElement('div');
    card.className = 'card h-100';
    
    const startAddr = ride.pickup_address || 'Onbekend';
    const destAddr = ride.dropoff_address || 'Onbekend';
    const distance = ride.actual_distance_km || ride.estimated_distance_km || '-';
    const duration = ride.actual_duration_minutes || ride.estimated_duration_minutes || '-';
    const price = ride.actual_price_cents ? `€${(ride.actual_price_cents / 100).toFixed(2)}` : 
                ride.estimated_price_cents ? `€${(ride.estimated_price_cents / 100).toFixed(2)}` : '€--.--';
    
    card.innerHTML = `
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h6 class="card-title mb-0">${startAddr} → ${destAddr}</h6>
                <small class="text-muted">${ride.created_at ? new Date(ride.created_at).toLocaleDateString() : ''}</small>
            </div>
            <div class="row text-center">
                <div class="col-4">
                    <small class="text-muted">Afstand</small>
                    <div class="fw-bold">${distance} km</div>
                </div>
                <div class="col-4">
                    <small class="text-muted">Duur</small>
                    <div class="fw-bold">${duration} min</div>
                </div>
                <div class="col-4">
                    <small class="text-muted">Kostprijs</small>
                    <div class="fw-bold text-success">${price}</div>
                </div>
            </div>
        </div>
    `;
    
    return card;
}

function createRideElement(ride) {
    const item = document.createElement('a');
    item.href = '#';
    item.className = 'list-group-item list-group-item-action';
    item.onclick = (e) => {
        e.preventDefault();
        showRideDetails(ride.id);
    };
    
    const statusIcon = {
        'pending': '<i class="bi bi-hourglass"></i>',
        'assigned': '<i class="bi bi-car-front"></i>',
        'in_progress': '<i class="bi bi-lightning-charge"></i>',
        'completed': '<i class="bi bi-check-circle"></i>',
        'cancelled': '<i class="bi bi-x-circle"></i>'
    };
    
    const statusText = {
        'pending': 'In afwachting',
        'assigned': 'Voertuig toegewezen',
        'in_progress': 'Onderweg',
        'completed': 'Afgewerkt',
        'cancelled': 'Geannuleerd'
    };
    
    const startAddr = ride.pickup_address || 'Onbekend';
    const destAddr = ride.dropoff_address || 'Onbekend';
    const distance = ride.estimated_distance_km || '-';
    const duration = ride.estimated_duration_minutes || '-';
    const price = ride.estimated_price_cents ? `€${(ride.estimated_price_cents / 100).toFixed(2)}` : '€--.--';
    
    let etaText = '';
    const pickupTime = ride.requested_pickup_time ? new Date(ride.requested_pickup_time) : null;
    const now = new Date();
    const maybeScheduled = pickupTime && !Number.isNaN(pickupTime.getTime()) && pickupTime - now > 3 * 60 * 1000;

    if ((ride.status === 'assigned' || ride.status === 'in_progress') && maybeScheduled) {
        const minutes = Math.ceil((pickupTime - now) / (1000 * 60));
        etaText = `<br><small class="text-primary"><i class="bi bi-clock-history"></i> Gepland ophalen over ±${minutes} min</small>`;
    } else if (ride.status === 'assigned') {
        const est = ride.estimated_duration_minutes;
        etaText = `<br><small class="text-info"><i class="bi bi-car-front"></i> Voertuig onderweg naar ophaalpunt${est ? ` (±${est} min rit)` : ''}</small>`;
    } else if (ride.status === 'in_progress') {
        const est = ride.estimated_duration_minutes;
        etaText = `<br><small class="text-success"><i class="bi bi-geo-alt"></i> Onderweg naar bestemming${est ? ` · ±${est} min` : ''}</small>`;
    }
    
    item.innerHTML = `
        <div class="d-flex w-100 justify-content-between align-items-start">
            <div class="flex-grow-1">
                <h6 class="mb-1 fw-bold">
                    ${statusIcon[ride.status] || ''} ${statusText[ride.status] || ride.status}
                </h6>
                <p class="mb-1 small">
                    <i class="bi bi-geo-alt"></i> ${startAddr}
                </p>
                <p class="mb-1 small">
                    <i class="bi bi-geo-alt-fill"></i> ${destAddr}
                </p>
                <small class="text-muted">
                    <i class="bi bi-speedometer"></i> ${distance} km | <i class="bi bi-clock"></i> ${duration} min
                </small>
                ${etaText}
            </div>
            <div class="text-end">
                <div class="fw-bold text-success">${price}</div>
                <small class="text-muted">Rit #${ride.id}</small>
            </div>
        </div>
    `;
    
    return item;
}

async function loadRideHistory() {
    try {
        const uid = appState.currentUser?.id;
        const url = uid != null
            ? `${API_CONFIG.backend.baseUrl}${API_CONFIG.backend.endpoints.rides}?user_id=${encodeURIComponent(uid)}`
            : `${API_CONFIG.backend.baseUrl}${API_CONFIG.backend.endpoints.rides}`;
        const response = await fetch(url);
        const rides = await response.json();
        
        const curId = uid != null ? Number(uid) : null;
        let mine = curId != null ? rides.filter(r => Number(r.user_id) === curId) : rides;

        
        const completed = mine.filter(r => r.status === 'completed' || r.status === 'cancelled');
        
        const historyList = document.getElementById('history-list');
        
        if (!completed || completed.length === 0) {
            historyList.innerHTML = `
                <div class="col-12 text-center text-muted py-4">
                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                    <p class="mt-2">Geen ritgeschiedenis</p>
                </div>
            `;
            return;
        }
        
        historyList.innerHTML = '';
        completed.forEach(ride => {
            const card = createHistoryCard(ride);
            historyList.appendChild(card);
        });
        
    } catch (error) {
        console.error('Error loading history:', error);
        showAlert('Fout bij het laden van ritgeschiedenis', 'danger');
    }
}

function createHistoryCard(ride) {
    const col = document.createElement('div');
    col.className = 'col-md-6 col-lg-4 mb-3';
    
    const statusBadge = ride.status === 'completed' 
        ? '<span class="badge bg-success">Voltooid</span>'
        : '<span class="badge bg-danger">Geannuleerd</span>';
    
    const date = new Date(ride.created_at).toLocaleDateString('nl-NL', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    col.innerHTML = `
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="card-title mb-0">Rit #${ride.id}</h6>
                    ${statusBadge}
                </div>
                <small class="text-muted d-block mb-2">${date}</small>
                <p class="card-text small mb-1">
                    <strong>Van:</strong> ${ride.pickup_address || '-'}
                </p>
                <p class="card-text small mb-1">
                    <strong>Naar:</strong> ${ride.dropoff_address || '-'}
                </p>
                <p class="card-text small mb-2">
                    <strong>Afstand:</strong> ${ride.estimated_distance_km?.toFixed(1) || '-'} km
                </p>
                <div class="alert alert-info py-1 px-2 mb-0">
                    <strong>Bedrag:</strong> €${ride.estimated_price_cents ? (ride.estimated_price_cents / 100).toFixed(2) : '0.00'}
                </div>
            </div>
        </div>
    `;
    
    return col;
}


async function showRideDetails(rideId) {
    try {
        appState.currentRideId = rideId;
        
        const response = await fetch(
            `${API_CONFIG.backend.baseUrl}${API_CONFIG.backend.endpoints.rides}/${rideId}${userQueryParam()}`
        );
        const ride = await response.json();
        if (!response.ok) {
            throw new Error(ride.error || `HTTP ${response.status}`);
        }

        const etaInfo = document.getElementById('ride-eta-info');
        if (etaInfo) {
            etaInfo.classList.add('d-none');
            etaInfo.textContent = '';
        }
        
        
        document.getElementById('ride-modal-id').textContent = `Rit #${ride.id}`;
        updateRideInfo(ride);
        
        
        initRideTrackingMap(ride);
        
        const canCancel = ride.status === 'pending' || ride.status === 'assigned';
        document.getElementById('cancel-ride-btn').classList.toggle('d-none', !canCancel);

        if (ride.vehicle_id) {
            startVehicleTracking(ride.vehicle_id, rideId);
            document.getElementById('vehicle-info').classList.remove('d-none');
        } else {
            document.getElementById('vehicle-info').classList.add('d-none');
        }
        
        
        const modal = new bootstrap.Modal(document.getElementById('rideModal'));
        modal.show();
        
    } catch (error) {
        console.error('Error showing ride details:', error);
        showAlert('Fout bij het laden van ritdetails', 'danger');
    }
}

function updateRideInfo(ride) {
    const statusText = {
        'pending': '🕐 In afwachting',
        'assigned': '✓ Voertuig toegewezen',
        'in_progress': '📍 Onderweg',
        'completed': '✓ Voltooid',
        'cancelled': '✗ Geannuleerd'
    };
    
    const comfortText = {
        'basic': '🚗 Basis',
        'standard': '⭐ Standaard',
        'premium': '✨ Premium'
    };
    
    const estimatedPrice = ride.estimated_price_cents ? `€${(ride.estimated_price_cents / 100).toFixed(2)}` : 'Berekenen...';
    const sharingText = ride.shared_ride ? '👥 Gedeeld' : 'Privé';
    
    const rideInfo = document.getElementById('ride-info');
    rideInfo.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <div class="mb-2">
                    <strong>Status:</strong> 
                    <span class="badge bg-primary">${statusText[ride.status] || ride.status}</span>
                </div>
                <div class="mb-2">
                    <strong>Comfort:</strong> ${comfortText[ride.comfort_level] || ride.comfort_level}
                </div>
                <div class="mb-2">
                    <strong>Ritdeling:</strong> ${sharingText}
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div class="mb-2">
                    <strong>Prijs:</strong> <span class="text-success fs-5">${estimatedPrice}</span>
                </div>
                <div class="mb-2">
                    <strong>Afstand:</strong> ${ride.estimated_distance_km?.toFixed(1) || '-'} km
                </div>
                <div class="mb-2">
                    <strong>Duur:</strong> ${ride.estimated_duration_minutes || '-'} minuten
                </div>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-12">
                <div class="alert alert-light border">
                    <strong>Route:</strong><br>
                    📍 ${ride.pickup_address || 'Onbekend'}<br>
                    ⬇️<br>
                    🎯 ${ride.dropoff_address || 'Onbekend'}
                </div>
            </div>
        </div>
    `;
}


function initRequestMap() {
    if (appState.maps.request) return;
    
    appState.maps.request = L.map('request-map').setView(
        API_CONFIG.map.defaultCenter,
        API_CONFIG.map.defaultZoom
    );
    
    L.tileLayer(API_CONFIG.map.tileUrl, {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(appState.maps.request);
}

function updateRequestMapMarkers() {
    
    if (appState.markers.start) {
        appState.maps.request.removeLayer(appState.markers.start);
        appState.markers.start = null;
    }
    if (appState.markers.destination) {
        appState.maps.request.removeLayer(appState.markers.destination);
        appState.markers.destination = null;
    }
    
    const sLat = parseFloat(document.getElementById('start-lat').value || '');
    const sLng = parseFloat(document.getElementById('start-lng').value || '');
    const dLat = parseFloat(document.getElementById('dest-lat').value || '');
    const dLng = parseFloat(document.getElementById('dest-lng').value || '');
    
    const bounds = [];
    
    if (!isNaN(sLat) && !isNaN(sLng)) {
        appState.markers.start = L.marker([sLat, sLng], {
            draggable: true,
            title: 'Startpunt',
            icon: L.divIcon({
                html: '<i class="fas fa-flag-checkered" style="color: green; font-size: 24px;"></i>',
                className: 'custom-marker',
                iconSize: [24, 24],
                iconAnchor: [12, 24]
            })
        }).addTo(appState.maps.request);
        
        appState.markers.start.on('dragend', (e) => {
            const latlng = e.target.getLatLng();
            document.getElementById('start-lat').value = latlng.lat;
            document.getElementById('start-lng').value = latlng.lng;
            updatePriceEstimate();
        });
        
        bounds.push([sLat, sLng]);
    }
    
    if (!isNaN(dLat) && !isNaN(dLng)) {
        appState.markers.destination = L.marker([dLat, dLng], {
            draggable: true,
            title: 'Bestemming',
            icon: L.divIcon({
                html: '<i class="fas fa-flag-checkered" style="color: #f44336; font-size: 24px;"></i>',
                className: 'custom-marker',
                iconSize: [24, 24],
                iconAnchor: [12, 24]
            })
        }).addTo(appState.maps.request);
        
        appState.markers.destination.on('dragend', (e) => {
            const latlng = e.target.getLatLng();
            document.getElementById('dest-lat').value = latlng.lat;
            document.getElementById('dest-lng').value = latlng.lng;
            updatePriceEstimate();
        });
        
        bounds.push([dLat, dLng]);
    }
    
    
    if (bounds.length === 2) {
        appState.maps.request.fitBounds(bounds, { padding: [50, 50] });
    } else if (bounds.length === 1) {
        appState.maps.request.setView(bounds[0], 14);
    }
}

function initRideTrackingMap(ride) {
    const container = document.getElementById('ride-map');
    
    
    if (appState.maps.rideTrack) {
        appState.maps.rideTrack.remove();
    }

    const bounds = [];
    
    appState.maps.rideTrack = L.map(container).setView(
        API_CONFIG.map.defaultCenter,
        API_CONFIG.map.defaultZoom
    );
    
    L.tileLayer(API_CONFIG.map.tileUrl, {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(appState.maps.rideTrack);

    const pickupLat = ride.pickup_latitude != null ? parseFloat(ride.pickup_latitude) : null;
    const pickupLng = ride.pickup_longitude != null ? parseFloat(ride.pickup_longitude) : null;
    const dropLat = ride.dropoff_latitude != null ? parseFloat(ride.dropoff_latitude) : null;
    const dropLng = ride.dropoff_longitude != null ? parseFloat(ride.dropoff_longitude) : null;

    if (!Number.isNaN(pickupLat) && !Number.isNaN(pickupLng)) {
        bounds.push([pickupLat, pickupLng]);
        appState.markers.ridePickup = L.marker([pickupLat, pickupLng], {
            title: 'Ophaalpunt'
        }).addTo(appState.maps.rideTrack).bindPopup('Ophaalpunt');
    }

    if (!Number.isNaN(dropLat) && !Number.isNaN(dropLng)) {
        bounds.push([dropLat, dropLng]);
        appState.markers.rideDropoff = L.marker([dropLat, dropLng], {
            title: 'Bestemming'
        }).addTo(appState.maps.rideTrack).bindPopup('Bestemming');
    }

    if (ride.route && Array.isArray(ride.route.waypoints) && ride.route.waypoints.length >= 2) {
        appState.markers.rideRouteLine = L.polyline(ride.route.waypoints, { color: '#0d6efd', weight: 5, opacity: 0.85 })
            .addTo(appState.maps.rideTrack);
        bounds.push(...ride.route.waypoints);
    }

    if (ride.route) {
        if (ride.route.start_lat != null && ride.route.start_lng != null
            && (!appState.markers.ridePickup || (pickupLat == null))) {
            const sl = parseFloat(ride.route.start_lat);
            const sn = parseFloat(ride.route.start_lng);
            if (!Number.isNaN(sl) && !Number.isNaN(sn)) {
                L.marker([sl, sn]).addTo(appState.maps.rideTrack).bindPopup('Start route');
                bounds.push([sl, sn]);
            }
        }
        if (ride.route.dest_lat != null && ride.route.dest_lng != null && (!appState.markers.rideDropoff || dropLat == null)) {
            const dl = parseFloat(ride.route.dest_lat);
            const dn = parseFloat(ride.route.dest_lng);
            if (!Number.isNaN(dl) && !Number.isNaN(dn)) {
                L.marker([dl, dn]).addTo(appState.maps.rideTrack).bindPopup('Einde route');
                bounds.push([dl, dn]);
            }
        }
    }

    if (bounds.length >= 2) {
        try {
            appState.maps.rideTrack.fitBounds(bounds, { padding: [48, 48], maxZoom: 14 });
        } catch (e) {
            appState.maps.rideTrack.setView(bounds[0], 13);
        }
    } else if (bounds.length === 1) {
        appState.maps.rideTrack.setView(bounds[0], 13);
    }
}

function openMapPicker() {
    const modalEl = document.getElementById('mapPickerModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    
    setTimeout(() => {
        initPickerMap();
        if (appState.maps.picker) {
            appState.maps.picker.invalidateSize();
        }
    }, 300);
}

function initPickerMap() {
    if (appState.maps.picker) return;
    
    appState.maps.picker = L.map('map-picker').setView(
        API_CONFIG.map.defaultCenter,
        API_CONFIG.map.defaultZoom
    );
    
    L.tileLayer(API_CONFIG.map.tileUrl, {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(appState.maps.picker);
    
    let clickCount = 0;
    
    appState.maps.picker.on('click', (e) => {
        clickCount++;
        if (clickCount === 1) {
            if (appState.markers.pickerStart) {
                appState.maps.picker.removeLayer(appState.markers.pickerStart);
            }
            appState.markers.pickerStart = L.marker(e.latlng, {
                draggable: true,
                title: 'Startpunt'
            }).addTo(appState.maps.picker);
        } else if (clickCount === 2) {
            if (appState.markers.pickerDestination) {
                appState.maps.picker.removeLayer(appState.markers.pickerDestination);
            }
            appState.markers.pickerDestination = L.marker(e.latlng, {
                draggable: true,
                title: 'Bestemming'
            }).addTo(appState.maps.picker);
        }
    });
}

function resetMapPicker() {
    if (appState.markers.pickerStart) {
        appState.maps.picker.removeLayer(appState.markers.pickerStart);
        appState.markers.pickerStart = null;
    }
    if (appState.markers.pickerDestination) {
        appState.maps.picker.removeLayer(appState.markers.pickerDestination);
        appState.markers.pickerDestination = null;
    }
}

function applyMapSelection() {
    if (appState.markers.pickerStart) {
        const latlng = appState.markers.pickerStart.getLatLng();
        document.getElementById('start-lat').value = latlng.lat;
        document.getElementById('start-lng').value = latlng.lng;
    }
    if (appState.markers.pickerDestination) {
        const latlng = appState.markers.pickerDestination.getLatLng();
        document.getElementById('dest-lat').value = latlng.lat;
        document.getElementById('dest-lng').value = latlng.lng;
    }
    
    updateRequestMapMarkers();
    updatePriceEstimate();
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('mapPickerModal'));
    modal.hide();
}

function clearMapSelection() {
    document.getElementById('start-lat').value = '';
    document.getElementById('start-lng').value = '';
    document.getElementById('dest-lat').value = '';
    document.getElementById('dest-lng').value = '';
    
    document.getElementById('start-address').value = '';
    document.getElementById('destination-address').value = '';
    
    updateRequestMapMarkers();
}


async function geocodeAddress() {
    
    
    
    const startAddr = document.getElementById('start-address').value;
    const destAddr = document.getElementById('destination-address').value;
    
    if (startAddr && startAddr.length > 5) {
        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(startAddr)}&format=json&limit=1`
            );
            const results = await response.json();
            if (results.length > 0) {
                document.getElementById('start-lat').value = parseFloat(results[0].lat);
                document.getElementById('start-lng').value = parseFloat(results[0].lon);
            }
        } catch (error) {
            console.error('Geocoding error:', error);
        }
    }
    
    if (destAddr && destAddr.length > 5) {
        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(destAddr)}&format=json&limit=1`
            );
            const results = await response.json();
            if (results.length > 0) {
                document.getElementById('dest-lat').value = parseFloat(results[0].lat);
                document.getElementById('dest-lng').value = parseFloat(results[0].lon);
            }
        } catch (error) {
            console.error('Geocoding error:', error);
        }
    }
    
    updateRequestMapMarkers();
    updatePriceEstimate();
}


async function updatePriceEstimate() {
    const startLat = document.getElementById('start-lat').value;
    const startLng = document.getElementById('start-lng').value;
    const destLat = document.getElementById('dest-lat').value;
    const destLng = document.getElementById('dest-lng').value;
    const comfortLevel = document.getElementById('comfort-level').value;
    const sharingPref = document.getElementById('sharing-preference').value;
    const passengerCount = parseInt(document.getElementById('passenger-count').value) || 1;
    
    if (!startLat || !startLng || !destLat || !destLng) {
        document.getElementById('price-estimate-container').classList.add('d-none');
        return;
    }
    
    try {
        
        const osrmUrl = `http://router.project-osrm.org/route/v1/driving/${startLng},${startLat};${destLng},${destLat}?overview=false`;
        const response = await fetch(osrmUrl);
        const routeData = await response.json();
        
        if (routeData.routes && routeData.routes.length > 0) {
            const route = routeData.routes[0];
            const distanceKm = route.distance / 1000;
            const durationMin = Math.ceil(route.duration / 60);
            
            
            let basePrice = distanceKm * 1.5; 
            
            
            const comfortMultipliers = {
                'basic': 1,
                'standard': 1.2,
                'premium': 1.5
            };
            basePrice *= comfortMultipliers[comfortLevel] || 1.0;
            
            
            if (sharingPref === 'shared') {
                basePrice *= 0.7; 
            } else if (sharingPref === 'preferred') {
                basePrice *= 0.85; 
            }
            
            
            if (passengerCount > 1) {
                basePrice *= (1 + (passengerCount - 1) * 0.3);
            }
            
            
            document.getElementById('price-estimate').innerHTML = `<strong>€${basePrice.toFixed(2)}</strong>`;
            document.getElementById('distance-estimate').textContent = distanceKm.toFixed(1);
            document.getElementById('duration-estimate').textContent = durationMin;
            document.getElementById('price-estimate-container').classList.remove('d-none');
        }
    } catch (error) {
        console.error('Error calculating price:', error);
    }
}


async function handleRideSubmit(e) {
    e.preventDefault();
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Bezig...';
    
    try {
        const sharingPref = document.getElementById('sharing-preference').value;
        const pickupScheduled = document.getElementById('pickup-scheduled').checked;
        let requestedPickupIso = null;
        if (pickupScheduled) {
            const dtLocal = document.getElementById('scheduled-pickup-time').value;
            if (!dtLocal) {
                throw new Error('Kies een datum en uur voor een geplande rit');
            }
            requestedPickupIso = new Date(dtLocal).toISOString();
        }

        const formData = {
            user_id: appState.currentUser?.id || 1,
            pickup_address: document.getElementById('start-address').value,
            dropoff_address: document.getElementById('destination-address').value,
            comfort_level: document.getElementById('comfort-level').value,
            shared_ride: sharingPref === 'shared' || sharingPref === 'preferred' ? 1 : 0,
            passenger_count: parseInt(document.getElementById('passenger-count').value) || 1,
            status: 'pending'
        };
        if (requestedPickupIso) {
            formData.requested_pickup_time = requestedPickupIso;
        }
        
        
        const startLat = document.getElementById('start-lat').value;
        const startLng = document.getElementById('start-lng').value;
        const destLat = document.getElementById('dest-lat').value;
        const destLng = document.getElementById('dest-lng').value;
        
        if (startLat && startLng && destLat && destLng) {
            formData.pickup_latitude = parseFloat(startLat);
            formData.pickup_longitude = parseFloat(startLng);
            formData.dropoff_latitude = parseFloat(destLat);
            formData.dropoff_longitude = parseFloat(destLng);
        }
        
        
        const priceEstimate = document.getElementById('price-estimate')?.textContent;
        if (priceEstimate) {
            const priceValue = parseFloat(priceEstimate.replace(/[^\d.]/g, ''));
            formData.estimated_price_cents = Math.round(priceValue * 100);
        }
        
        
        const distance = document.getElementById('distance-estimate')?.textContent;
        const duration = document.getElementById('duration-estimate')?.textContent;
        if (distance) formData.estimated_distance_km = parseFloat(distance);
        if (duration) formData.estimated_duration_minutes = parseInt(duration);
        
        const response = await fetch(
            `${API_CONFIG.backend.baseUrl}${API_CONFIG.backend.endpoints.rides}`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            }
        );
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Fout bij aanvraag');
        }
        
        const ride = await response.json();
        
        showAlert('Rit succesvol aangevraagd!', 'success');
        
        
        document.getElementById('ride-request-form').reset();
        clearMapSelection();
        
        
        await loadRides();
        
        
        setTimeout(() => {
            showRideDetails(ride.id);
        }, 500);
        
    } catch (error) {
        console.error('Error submitting ride:', error);
        showAlert(`Fout: ${error.message}`, 'danger');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Rit Aanvragen';
    }
}

async function cancelRide() {
    if (!appState.currentRideId) return;
    
    if (!confirm('Wilt u deze rit echt annuleren?')) return;
    
    try {
        const response = await fetch(
            `${API_CONFIG.backend.baseUrl}${API_CONFIG.backend.endpoints.rides}/${appState.currentRideId}${userQueryParam()}`,
            {
                method: 'DELETE'
            }
        );
        
        if (response.ok) {
            showAlert('Rit geannuleerd', 'warning');
            const modal = bootstrap.Modal.getInstance(document.getElementById('rideModal'));
            modal.hide();
            await loadRides();
        }
    } catch (error) {
        console.error('Error cancelling ride:', error);
        showAlert('Fout bij annuleren', 'danger');
    }
}


function startVehicleTracking(vehicleId, rideId) {
    
    updateVehiclePosition(vehicleId);
    updateRideProgress(rideId);
    updateRideEvents(rideId);
    
    
    appState.intervals.vehicleTracking = setInterval(
        () => updateVehiclePosition(vehicleId),
        API_CONFIG.app.vehicleTrackingInterval
    );
    
    appState.intervals.progressTracking = setInterval(
        () => updateRideProgress(rideId),
        API_CONFIG.app.vehicleTrackingInterval
    );
    
    appState.intervals.eventsRefresh = setInterval(
        () => updateRideEvents(rideId),
        API_CONFIG.app.eventsRefreshInterval
    );
}


function displayPhaseTimingBars(progress, totalMinutes) {
    const phasesContainer = document.getElementById('ride-phases-bars');
    if (!phasesContainer) return;
    phasesContainer.innerHTML = '';

    const phaseLabels = {
        scheduled: 'Gepland',
        to_pickup: 'Naar ophaalpunt',
        in_transit: 'Naar bestemming',
        heading_to_charger: 'Naar laadstation',
        charging_at_stop: 'Laden',
        returning: 'Terugrijdend',
        completed: 'Voltooid',
        pending: 'In afwachting',
        waiting_vehicle: 'Voertuig wordt voorbereid'
    };

    const phaseColors = {
        scheduled: '#6c757d',
        to_pickup: '#ff9800',
        in_transit: '#28a745',
        heading_to_charger: '#2196F3',
        charging_at_stop: '#ffc107',
        returning: '#9C27B0',
        completed: '#4caf50',
        pending: '#6c757d',
        waiting_vehicle: '#6c757d'
    };
    
    // Estimate phase durations based on current progress
    const phaseKey = progress.simulation_phase || progress.status || 'pending';
    const phaseLabel = phaseLabels[phaseKey] || phaseKey;
    const phaseColor = phaseColors[phaseKey] || '#6c757d';
    const progressPercent = Number.isFinite(Number(progress.progress_percent))
        ? Math.max(0, Math.min(100, Number(progress.progress_percent)))
        : 0;

    const completedBar = document.createElement('div');
    completedBar.style.cssText = `
        flex: ${Math.max(5, progressPercent)};
        background-color: ${phaseColor};
        border-radius: 4px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: bold;
        color: white;
        text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        transition: all 0.2s ease;
    `;
    completedBar.title = `Huidige fase: ${phaseLabel}`;
    completedBar.textContent = `${phaseLabel} ${progressPercent.toFixed(1)}%`;
    phasesContainer.appendChild(completedBar);

    if (progressPercent < 100) {
        const remainingBar = document.createElement('div');
        remainingBar.style.cssText = `
            flex: ${Math.max(5, 100 - progressPercent)};
            background-color: #e9ecef;
            border-radius: 4px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            color: #495057;
            transition: all 0.2s ease;
        `;
        remainingBar.title = `Resterende voortgang`;
        remainingBar.textContent = progressPercent > 0 ? 'Resterend' : 'Start binnenkort';
        phasesContainer.appendChild(remainingBar);
    }
}

async function updateRideProgress(rideId) {
    try {
        const response = await fetch(
            `${API_CONFIG.backend.baseUrl}/rides/${rideId}/progress${userQueryParam()}`
        );
        
        if (!response.ok) {
            return;
        }
        
        const progress = await response.json();

        const etaInfo = document.getElementById('ride-eta-info');
        if (etaInfo) {
            if (progress.simulation_phase === 'scheduled') {
                etaInfo.classList.remove('d-none');
                const waitRaw = Number(progress.scheduled_wait_minutes);
                const etaRaw = Number(progress.eta_minutes);
                const waitMin = Number.isFinite(waitRaw) ? Math.max(0, Math.round(waitRaw)) : null;
                const etaMin = Number.isFinite(etaRaw) ? Math.max(0, Math.round(etaRaw)) : null;
                etaInfo.innerHTML = `<i class="bi bi-calendar-event"></i> Geplande rit: voertuig vertrekt op tijd richting ophaalpunt${waitMin != null ? ` (start over ~${waitMin} min)` : ''}${etaMin != null ? ` · totale resterende reistijd ~${etaMin} min` : ''}.`;
            } else if (progress.eta_minutes != null && (progress.status === 'assigned' || progress.status === 'in_progress')) {
                etaInfo.classList.remove('d-none');
                const m = Math.max(1, Math.round(Number(progress.eta_minutes)));
                etaInfo.innerHTML = `<i class="bi bi-clock-history"></i> Geschatte resterende tijd: <strong>~${m} min</strong>`;
            } else {
                etaInfo.classList.add('d-none');
            }
        }
        
        const rawProgressPercent = Number(progress.progress_percent);
        const progressPercent = Number.isFinite(rawProgressPercent)
            ? Math.max(0, Math.min(100, rawProgressPercent))
            : 0;
        const totalMinutesRaw = Number(progress.total_duration_minutes);
        const totalMinutes = Number.isFinite(totalMinutesRaw) && totalMinutesRaw > 0
            ? totalMinutesRaw
            : (Number(progress.duration_minutes) || 0);
        const elapsedMinutesRaw = Number(progress.elapsed_minutes);
        const elapsedMinutes = Number.isFinite(elapsedMinutesRaw) && elapsedMinutesRaw >= 0
            ? elapsedMinutesRaw
            : (totalMinutes > 0 ? (progressPercent / 100) * totalMinutes : 0);

        const progressBar = document.getElementById('ride-progress-bar');
        const progressText = document.getElementById('ride-progress-text');
        const progressContainer = document.getElementById('ride-progress-container');
        
        if (progressBar && progressContainer) {
            if (['assigned', 'in_progress', 'scheduled', 'completed'].includes(progress.status)) {
                progressContainer.classList.remove('d-none');
            }
            progressBar.style.width = `${progressPercent}%`;
            progressBar.setAttribute('aria-valuenow', String(progressPercent));
            document.getElementById('progressval').textContent = `${progressPercent.toFixed(1)}%`;
            
            if (progressText) {
                const phaseNl = {
                    scheduled: 'Wacht op geplande tijd',
                    to_pickup: 'Naar ophaalpunt',
                    in_transit: 'Naar bestemming',
                    pending: 'In afwachting',
                    waiting_vehicle: 'Voertuig wordt voorbereid'
                };
                const phaseLabel = phaseNl[progress.simulation_phase] || progress.simulation_phase || '';
                const elapsedRounded = Math.max(0, Math.round(elapsedMinutes));
                const totalRounded = Math.max(0, Math.round(totalMinutes));
                    progressText.textContent = `${progressPercent.toFixed(1)}% · ${phaseLabel} · ${elapsedRounded} / ${totalRounded} min`;
                
                    // Display phase bars with individual timings
                    displayPhaseTimingBars(progress, totalMinutes);
            }
        }
        
        
        if (progress.current_location && appState.maps.rideTrack) {
            const latlng = [progress.current_location.latitude, progress.current_location.longitude];
            
            if (!appState.markers.vehicleLocation) {
                const blueIcon = L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-blue.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41]
                });
                
                appState.markers.vehicleLocation = L.marker(latlng, { icon: blueIcon })
                    .addTo(appState.maps.rideTrack)
                    .bindPopup(`Voertuig #${progress.vehicle_id}`);
            } else {
                appState.markers.vehicleLocation.setLatLng(latlng);
            }
            
            
            if (appState.maps.rideTrack.getZoom() < 14) {
                appState.maps.rideTrack.setView(latlng, 14);
            }
        }
        
    } catch (error) {
        console.error('Error updating ride progress:', error);
    }
}

async function updateVehiclePosition(vehicleId) {
    try {
        const response = await fetch(
            `${API_CONFIG.backend.baseUrl}${API_CONFIG.backend.endpoints.vehicles}/${vehicleId}`
        );
        const vehicle = await response.json();
        
        if (vehicle.current_latitude && vehicle.current_longitude && appState.maps.rideTrack) {
            const latlng = [vehicle.current_latitude, vehicle.current_longitude];
            
            if (!appState.markers.vehicleLocation) {
                const blueIcon = L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-blue.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41]
                });
                
                appState.markers.vehicleLocation = L.marker(latlng, { icon: blueIcon })
                    .addTo(appState.maps.rideTrack)
                    .bindPopup(`Voertuig #${vehicleId}`);
            } else {
                appState.markers.vehicleLocation.setLatLng(latlng);
            }
        }
    } catch (error) {
        console.error('Error updating vehicle position:', error);
    }
}

async function updateRideEvents(rideId) {
    try {
        
        
        const response = await fetch(
            `${API_CONFIG.backend.baseUrl}${API_CONFIG.backend.endpoints.rides}/${rideId}${userQueryParam()}`
        );
        const ride = await response.json();
        
        
        if (response.ok) {
            updateRideInfo(ride);
        }
    } catch (error) {
        console.error('Error updating ride events:', error);
    }
}

function cleanupRideTracking() {
    if (appState.intervals.vehicleTracking) {
        clearInterval(appState.intervals.vehicleTracking);
        appState.intervals.vehicleTracking = null;
    }
    if (appState.intervals.progressTracking) {
        clearInterval(appState.intervals.progressTracking);
        appState.intervals.progressTracking = null;
    }
    if (appState.intervals.eventsRefresh) {
        clearInterval(appState.intervals.eventsRefresh);
        appState.intervals.eventsRefresh = null;
    }
    const map = appState.maps.rideTrack;
    ['vehicleLocation', 'ridePickup', 'rideDropoff', 'rideRouteLine'].forEach((key) => {
        const layer = appState.markers[key];
        if (layer && map) {
            try {
                map.removeLayer(layer);
            } catch (e) {}
        }
        appState.markers[key] = null;
    });
}


function setupAutoRefresh() {
    appState.intervals.autoRefresh = setInterval(() => {
        const ridesSection = document.getElementById('rides-section');
        const adminSection = document.getElementById('admin-section');
        
        if (ridesSection?.style.display !== 'none') {
            loadRides();
        }
        if (adminSection?.style.display !== 'none') {
            
        }
    }, API_CONFIG.app.autoRefreshInterval);
}


function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alert-container');
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alert);
    
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}


function formatPrice(price) {
    return `€${parseFloat(price).toFixed(2)}`;
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('nl-NL', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

console.log('Autonome Mobiliteitsplatformsplatform - Frontend Application Loaded');
