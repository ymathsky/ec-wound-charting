<?php
// Filename: map_view.php

require_once 'templates/header.php';
require_once 'db_connect.php';
?>
<style>
    /* Ensure the map and list take up the full available height */
    main {
        height: calc(100vh - 64px); /* Full viewport height minus header height */
    }
    #map-container {
        height: 100%;
    }
    /* Custom style for the optimized route panel */
    #route-panel ol {
        list-style-type: decimal;
        margin-left: 1.5rem;
    }
</style>

<div class="flex h-screen bg-gray-100">
    <?php require_once 'templates/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="w-full bg-white p-4 flex justify-between items-center shadow-md">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Visit Map</h1>
                <div class="flex items-center mt-1 space-x-2">
                    <p class="text-sm text-gray-600">Route optimization for:</p>
                    <input type="date" id="mapDate" class="border border-gray-300 rounded px-2 py-1 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <!-- Action Buttons -->
            <div class="flex items-center space-x-4">
                <button id="calculateRouteBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md text-sm transition flex items-center">
                    <i data-lucide="route" class="w-4 h-4 mr-2"></i>
                    Calculate Optimal Route
                </button>
                <button id="toggleTrafficBtn" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-md text-sm transition flex items-center">
                    <i data-lucide="traffic-cone" class="w-4 h-4 mr-2"></i>
                    <span>Show Traffic</span>
                </button>
                <button id="findMeBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md text-sm transition flex items-center">
                    <i data-lucide="crosshair" class="w-4 h-4 mr-2"></i>
                    Find My Location
                </button>
            </div>
        </header>

        <main class="flex-1 flex overflow-hidden bg-gray-100 p-6 gap-6">
            <!-- Left Column for Lists -->
            <div class="w-1/3 bg-white rounded-lg shadow-lg flex flex-col">
                <h2 class="text-lg font-semibold text-gray-800 p-4 border-b">Appointment List</h2>
                <div id="appointment-list" class="overflow-y-auto p-2">
                    <div class="flex justify-center items-center h-full"><div class="spinner"></div></div>
                </div>
                <!-- NEW: Optimized Route Panel -->
                <div id="route-panel-container" class="border-t">
                    <h2 class="text-lg font-semibold text-gray-800 p-4 border-b">Optimized Route</h2>
                    <div id="route-panel" class="overflow-y-auto p-4 text-sm">
                        <p class="text-gray-500">Click "Calculate Optimal Route" to generate the most efficient visit order.</p>
                    </div>
                </div>
            </div>

            <!-- Right Column for the Map -->
            <div id="map-container" class="w-2/3 bg-white rounded-lg shadow-lg">
                <div id="map" class="w-full h-full rounded-md flex items-center justify-center">
                    <!-- Spinner will be replaced by the map -->
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    let map;
    let markers = [];
    let trafficLayer;
    let infoWindow;
    let userLocationMarker;
    let allAppointments = []; // Store all appointments globally

    // NEW: Directions services
    let directionsService;
    let directionsRenderer;

    // This function will be called by the dynamically loaded Google Maps script
    function initMap() {
        const defaultCenter = { lat: 12.8797, lng: 121.7740 }; // Philippines Center

        map = new google.maps.Map(document.getElementById("map"), {
            zoom: 6,
            center: defaultCenter,
            mapTypeControl: false,
            streetViewControl: false,
        });

        trafficLayer = new google.maps.TrafficLayer();
        infoWindow = new google.maps.InfoWindow();

        // Initialize Directions Services
        directionsService = new google.maps.DirectionsService();

        // UPDATED: Initialize DirectionsRenderer with custom marker options
        directionsRenderer = new google.maps.DirectionsRenderer({
            suppressMarkers: true, // We will create our own custom markers
        });
        directionsRenderer.setMap(map);


        fetchAppointmentsForMap();
        setupUIEventListeners();
    }

    async function fetchAppointmentsForMap() {
        const appointmentList = document.getElementById('appointment-list');
        try {
            // Get date from URL
            const urlParams = new URLSearchParams(window.location.search);
            const dateParam = urlParams.get('date');
            const fetchUrl = dateParam ? `api/get_appointments.php?date=${dateParam}` : 'api/get_appointments.php';

            // Update Header Title if date is present
            if (dateParam) {
                const dateObj = new Date(dateParam);
                const formattedDate = dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                document.querySelector('h1').textContent = `Visit Map: ${formattedDate}`;
            }

            const response = await fetch(fetchUrl);
            if (!response.ok) throw new Error('Failed to fetch appointments for the map.');

            allAppointments = await response.json(); // Store globally

            const geocoder = new google.maps.Geocoder();
            const bounds = new google.maps.LatLngBounds();
            let geocodedCount = 0;

            if (allAppointments.length === 0) {
                document.getElementById('map').innerHTML = `<p class="text-gray-600 font-semibold p-8 text-center">No appointments scheduled for today.</p>`;
                appointmentList.innerHTML = `<p class="text-gray-500 p-4 text-center">No appointments today.</p>`;
                return;
            }

            appointmentList.innerHTML = ''; // Clear spinner

            allAppointments.forEach((appt, index) => {
                const listItem = document.createElement('div');
                listItem.className = 'p-3 border-b hover:bg-gray-50 cursor-pointer';
                listItem.innerHTML = `
                    <p class="font-semibold text-gray-800">${appt.first_name} ${appt.last_name}</p>
                    <p class="text-xs text-gray-600">${appt.address || 'No address on file'}</p>
                `;
                listItem.dataset.index = index;
                appointmentList.appendChild(listItem);

                if (appt.address) {
                    geocoder.geocode({ 'address': appt.address }, function(results, status) {
                        if (status == 'OK') {
                            geocodedCount++;
                            const location = results[0].geometry.location;

                            // Custom Icon for Patient
                            const patientIcon = {
                                url: 'data:image/svg+xml,' + encodeURIComponent(`
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="#DB2777" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin">
                                        <path d="M12 2c-4.4 0-8 3.6-8 8 0 5.6 8 12 8 12s8-6.4 8-12c0-4.4-3.6-8-8-8z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>
                                `),
                                scaledSize: new google.maps.Size(32, 32)
                            };

                            const marker = new google.maps.Marker({
                                map: map,
                                position: location,
                                title: `${appt.first_name} ${appt.last_name}`,
                                animation: google.maps.Animation.DROP,
                                icon: patientIcon
                            });

                            marker.content = `
                                <div class="p-1" style="min-width: 200px;">
                                    <h5 class="font-bold text-base">${appt.first_name} ${appt.last_name}</h5>
                                    <p class="text-xs text-gray-500 mb-1">ID: ${appt.patient_code || 'N/A'}</p>
                                    <p class="text-sm"><strong>DOB:</strong> ${appt.date_of_birth || 'N/A'}</p>
                                    <p class="text-sm"><strong>Contact:</strong> ${appt.contact_number || 'N/A'}</p>
                                    <p class="text-sm mt-1">${appt.address}</p>
                                    <a href="https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(appt.address)}" target="_blank" class="text-blue-600 font-semibold mt-2 inline-block">Get Directions</a>
                                </div>`;

                            marker.addListener('click', () => {
                                infoWindow.setContent(marker.content);
                                infoWindow.open(map, marker);
                            });

                            markers[index] = marker;
                            bounds.extend(location);
                            map.fitBounds(bounds);

                            listItem.addEventListener('click', () => {
                                map.panTo(marker.getPosition());
                                infoWindow.setContent(marker.content);
                                infoWindow.open(map, marker);
                                map.setZoom(15);
                            });

                        } else {
                            console.warn(`Geocode for "${appt.address}" failed: ${status}`);
                        }
                    });
                }
            });

            setTimeout(() => {
                if (geocodedCount === 0) {
                    document.getElementById('map').innerHTML = `<p class="text-gray-600 font-semibold p-8 text-center">No valid addresses found for today's appointments.</p>`;
                }
            }, 2000);

        } catch (error) {
            console.error(error);
            document.getElementById('map').innerHTML = `<p class="text-red-600 font-semibold p-8">${error.message}</p>`;
            appointmentList.innerHTML = `<p class="text-red-500 p-4">${error.message}</p>`;
        }
    }

    function setupUIEventListeners() {
        // Date Picker Logic
        const mapDateInput = document.getElementById('mapDate');
        const urlParams = new URLSearchParams(window.location.search);
        const dateParam = urlParams.get('date');
        
        // Set initial date
        if (dateParam) {
            mapDateInput.value = dateParam;
        } else {
            mapDateInput.value = new Date().toISOString().split('T')[0];
        }

        // Reload on change
        mapDateInput.addEventListener('change', (e) => {
            const newDate = e.target.value;
            window.location.href = `map_view.php?date=${newDate}`;
        });

        document.getElementById('calculateRouteBtn').addEventListener('click', calculateOptimalRoute);

        const toggleTrafficBtn = document.getElementById('toggleTrafficBtn');
        toggleTrafficBtn.addEventListener('click', () => {
            if (trafficLayer.getMap()) {
                trafficLayer.setMap(null);
                toggleTrafficBtn.querySelector('span').textContent = 'Show Traffic';
            } else {
                trafficLayer.setMap(map);
                toggleTrafficBtn.querySelector('span').textContent = 'Hide Traffic';
            }
        });

        const findMeBtn = document.getElementById('findMeBtn');
        findMeBtn.addEventListener('click', () => {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const pos = { lat: position.coords.latitude, lng: position.coords.longitude };

                        // Custom Icon for Doctor/User
                        const doctorIcon = {
                            url: 'data:image/svg+xml,' + encodeURIComponent(`
                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="#1D4ED8" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round">
                                    <circle cx="12" cy="8" r="5"/>
                                    <path d="M20 21a8 8 0 0 0-16 0"/>
                                </svg>
                            `),
                            scaledSize: new google.maps.Size(40, 40)
                        };

                        if (userLocationMarker) {
                            userLocationMarker.setPosition(pos);
                        } else {
                            userLocationMarker = new google.maps.Marker({
                                position: pos,
                                map: map,
                                title: "Your Location",
                                icon: doctorIcon,
                            });
                        }
                        map.setCenter(pos);
                        map.setZoom(14);
                    },
                    () => alert("Error: The Geolocation service failed or was denied.")
                );
            } else {
                alert("Error: Your browser doesn't support geolocation.");
            }
        });
    }

    // --- Route Calculation Logic ---
    function calculateOptimalRoute() {
        if (allAppointments.length < 2) {
            alert("You need at least two appointments with valid addresses to calculate a route.");
            return;
        }

        const routePanel = document.getElementById('route-panel');
        routePanel.innerHTML = '<div class="spinner"></div><p class="ml-2">Calculating optimal route...</p>';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const userLocation = { lat: position.coords.latitude, lng: position.coords.longitude };

                const waypoints = allAppointments
                    .filter(appt => appt.address)
                    .map(appt => ({ location: appt.address, stopover: true }));

                if (waypoints.length < 2) {
                    routePanel.innerHTML = '<p class="text-red-500">Not enough valid patient addresses to create a route.</p>';
                    return;
                }

                // Clear individual markers before rendering the route
                markers.forEach(marker => marker.setMap(null));
                if(userLocationMarker) userLocationMarker.setMap(null); // Also hide user marker

                const request = {
                    origin: userLocation,
                    destination: userLocation, // Return to start
                    waypoints: waypoints,
                    optimizeWaypoints: true,
                    travelMode: google.maps.TravelMode.DRIVING,
                };

                directionsService.route(request, (response, status) => {
                    if (status === 'OK') {
                        directionsRenderer.setDirections(response);
                        displayOptimizedRoute(response);
                    } else {
                        routePanel.innerHTML = `<p class="text-red-500">Directions request failed: ${status}</p>`;
                    }
                });
            },
            () => {
                alert("Could not get your location. Please allow location access and try again.");
                routePanel.innerHTML = '<p class="text-red-500">Could not get your location to calculate the route.</p>';
            }
        );
    }

    function displayOptimizedRoute(response) {
        const route = response.routes[0];
        const routePanel = document.getElementById('route-panel');
        routePanel.innerHTML = ''; // Clear loading spinner

        // Clear existing markers array before adding new ones
        markers.forEach(marker => marker.setMap(null));
        markers = [];

        const summaryPanel = document.createElement('div');
        summaryPanel.innerHTML = `<p class="font-semibold mb-2">Total Distance: ${route.legs.reduce((total, leg) => total + leg.distance.value, 0) / 1000} km</p>`;
        routePanel.appendChild(summaryPanel);

        const orderedList = document.createElement('ol');
        const waypointsInOrder = route.waypoint_order.map(i => allAppointments.filter(a => a.address)[i]);

        // Add start/end marker (user's location)
        const startMarker = new google.maps.Marker({
            position: route.legs[0].start_location,
            map: map,
            label: { text: "A", color: "white" }
        });
        markers.push(startMarker);


        waypointsInOrder.forEach((appt, index) => {
            const leg = route.legs[index];
            const listItem = document.createElement('li');
            listItem.className = 'mb-2 cursor-pointer hover:bg-gray-100 p-1 rounded';
            listItem.innerHTML = `<strong>${appt.first_name} ${appt.last_name}</strong><br><span class="text-xs">${appt.address}</span><br><span class="text-xs text-gray-500">Est. travel: ${leg.duration.text}</span>`;

            // Create a numbered marker for each waypoint
            const marker = new google.maps.Marker({
                position: leg.end_location,
                map: map,
                label: { text: (index + 1).toString(), color: "white" }
            });

            // Define content for the info window
            const infoContent = `
                <div class="p-1" style="min-width: 200px;">
                    <h5 class="font-bold text-base">${appt.first_name} ${appt.last_name}</h5>
                    <p class="text-xs text-gray-500 mb-1">ID: ${appt.patient_code || 'N/A'}</p>
                    <p class="text-sm"><strong>DOB:</strong> ${appt.date_of_birth || 'N/A'}</p>
                    <p class="text-sm"><strong>Contact:</strong> ${appt.contact_number || 'N/A'}</p>
                    <p class="text-sm mt-1">${appt.address}</p>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(appt.address)}" target="_blank" class="text-blue-600 font-semibold mt-2 inline-block">Get Directions</a>
                </div>`;

            // Add click listener to the marker
            marker.addListener('click', () => {
                infoWindow.setContent(infoContent);
                infoWindow.open(map, marker);
            });

            // Add click listener to the list item to trigger the marker
            listItem.addEventListener('click', () => {
                map.panTo(marker.getPosition());
                infoWindow.setContent(infoContent);
                infoWindow.open(map, marker);
                map.setZoom(15);
            });

            markers.push(marker);
            orderedList.appendChild(listItem);
        });
        routePanel.appendChild(orderedList);
    }


    // --- SECURE API KEY LOADER ---
    async function loadGoogleMapsScript() {
        try {
            const response = await fetch('api/get_maps_api_key.php');
            if (!response.ok) throw new Error('Could not fetch Google Maps API key from the server.');

            const data = await response.json();
            const apiKey = data.apiKey;

            if (!apiKey) throw new Error('API key was not provided by the server.');

            const script = document.createElement('script');
            // Add 'libraries=places' to your API call if you plan to use Places API features
            script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&callback=initMap`;
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);

        } catch (error) {
            console.error(error);
            document.getElementById('map').innerHTML = `<p class="text-red-600 font-semibold p-8">${error.message}</p>`;
        }
    }

    // --- Initial Load ---
    document.addEventListener('DOMContentLoaded', function() {
        loadGoogleMapsScript();
        // Initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });

</script>

<?php
require_once 'templates/footer.php';
?>

