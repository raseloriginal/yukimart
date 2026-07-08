<?php
session_start();
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Map - YukiMartBD Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/js/tailwind.config.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Leaflet Geoman (for polygon drawing) -->
    <link rel="stylesheet" href="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.css" />
    <script src="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.min.js"></script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .modern-input { width: 100%; padding: 0.5rem 1rem; border-radius: 0.25rem; border: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.875rem; outline: none; transition: all 0.2s; }
        .modern-input:focus { border-color: #10b981; box-shadow: 0 0 0 1px #10b981; }
        
        #map { height: calc(100vh - 64px); width: 100%; z-index: 1;}
        
        .floating-panel {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 320px;
            max-height: calc(100vh - 100px);
            overflow-y: auto;
        }

        .leaflet-popup-content-wrapper {
            border-radius: 8px;
        }
        .leaflet-popup-content {
            margin: 15px;
            line-height: 1.5;
        }

        /* Custom dispatch marker icon */
        .dispatch-marker {
            background-color: #10b981;
            border: 3px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            text-align: center;
            line-height: 24px;
            color: white;
            font-weight: bold;
        }

        /* Custom customer marker icon */
        .customer-marker {
            background-color: #3b82f6;
            border: 2px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased flex h-screen overflow-hidden selection:bg-brand-100 selection:text-brand-900">

    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col hidden md:flex z-20 shadow-sm shrink-0">
        <div class="h-16 flex items-center px-6 border-b border-gray-100 shrink-0">
            <div class="flex items-center justify-center mr-3"><img src="../assets/images/logo.png" alt="Logo" class="w-8 h-8 object-contain rounded"></div>
            <h1 class="text-lg font-extrabold tracking-tight text-gray-900">Yuki<span class="text-brand-500">Mart</span></h1>
        </div>
        <nav class="flex-1 p-4 space-y-1.5 overflow-y-auto">
            <h4 class="text-[10px] font-extrabold text-gray-400 uppercase tracking-wider mb-2 mt-2 px-2">Menu</h4>
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-chart-pie w-5 text-center"></i> Dashboard
            </a>
            <a href="products.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-box w-5 text-center"></i> Products
            </a>
            <a href="orders.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-cart-shopping w-5 text-center"></i> Orders
            </a>
            <a href="users.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-users w-5 text-center"></i> Users (DSR)
            </a>
            <a href="warehouses.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-warehouse w-5 text-center"></i> Warehouses
            </a>
            <a href="areas.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-list w-5 text-center"></i> Delivery Areas
            </a>
            <a href="map.php" class="flex items-center gap-3 px-3 py-2.5 bg-brand-50 text-brand-600 font-bold rounded text-sm transition-all shadow-sm border border-brand-100">
                <i class="fa-solid fa-map-location-dot w-5 text-center"></i> Delivery Map
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-cog w-5 text-center"></i> Settings
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col relative overflow-hidden">
        <header class="h-16 bg-white border-b border-gray-100 flex items-center px-6 justify-between shrink-0 z-10 shadow-sm relative">
            <div class="md:hidden flex items-center gap-3">
                <div class="flex items-center justify-center"><img src="../assets/images/logo.png" alt="Logo" class="w-8 h-8 object-contain rounded shadow-sm"></div>
                <h1 class="text-lg font-extrabold tracking-tight text-gray-900">Yuki<span class="text-brand-500">Admin</span></h1>
            </div>
            <h2 class="hidden md:block text-lg font-extrabold text-gray-900 tracking-tight">Delivery Map & Zones</h2>
            
            <div class="flex gap-2">
                <!-- Removed Add Radius button, using Geoman toolbar instead -->
            </div>
        </header>

        <!-- Map Container -->
        <div class="relative flex-1">
            <div id="map"></div>
            
            <!-- Floating Options Panel -->
            <div class="floating-panel">
                <h3 class="text-sm font-extrabold text-gray-900 mb-3 border-b pb-2">Map Controls</h3>
                
                <div class="space-y-4">
                    <!-- Layer Toggles -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">Visibility</h4>
                        <label class="flex items-center gap-2 cursor-pointer mb-2">
                            <input type="checkbox" id="toggleDispatch" checked onchange="updateLayers()" class="rounded text-brand-600 focus:ring-brand-500">
                            <span class="text-sm font-medium text-gray-700">Dispatch Point</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer mb-2">
                            <input type="checkbox" id="toggleAreas" checked onchange="updateLayers()" class="rounded text-brand-600 focus:ring-brand-500">
                            <span class="text-sm font-medium text-gray-700">Delivery Areas</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="toggleCustomers" checked onchange="updateLayers()" class="rounded text-brand-600 focus:ring-brand-500">
                            <span class="text-sm font-medium text-gray-700">Customer Pins</span>
                        </label>
                    </div>

                    <!-- Dispatch Point Info -->
                    <div class="bg-gray-50 p-3 rounded border border-gray-100">
                        <h4 class="text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Dispatch Point</h4>
                        <p class="text-xs text-gray-600 mb-2">Drag the green marker to move it.</p>
                        <button onclick="saveDispatchPoint()" id="saveDispatchBtn" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs font-bold py-1.5 rounded transition-colors hidden">
                            Save New Location
                        </button>
                    </div>
                    
                    <div class="bg-blue-50 text-blue-700 p-3 rounded border border-blue-200 text-xs font-semibold">
                        <i class="fa-solid fa-info-circle mr-1"></i> Use the drawing tools on the left to draw custom delivery zones.
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add/Edit Area Modal -->
    <div id="areaModal" class="fixed inset-0 bg-black/60 hidden flex items-center justify-center z-[2000] p-4 backdrop-blur-sm transition-all duration-300 opacity-0">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-md transform scale-95 transition-transform duration-300">
            <div class="p-5 border-b border-gray-150 flex justify-between items-center bg-gray-50 rounded-t-lg">
                <h3 class="text-sm font-extrabold text-gray-900 uppercase" id="modalTitle"><i class="fa-solid fa-circle text-brand-500 mr-2"></i> Area Details</h3>
                <button type="button" onclick="closeAreaModal()" class="text-gray-400 hover:text-gray-800 bg-gray-200 hover:bg-gray-300 w-8 h-8 rounded-full transition-colors flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <form id="areaForm" onsubmit="saveArea(event)" class="p-6 space-y-4">
                <input type="hidden" id="areaId">
                <input type="hidden" id="areaLat">
                <input type="hidden" id="areaLng">
                <input type="hidden" id="areaPolygonData">
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Area Name <span class="text-red-500">*</span></label>
                    <input type="text" id="areaName" required class="modern-input">
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Delivery Charge (৳) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" id="areaCharge" required class="modern-input">
                </div>

                <!-- Hidden radius, no longer actively used but kept for legacy areas -->
                <div class="hidden">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Radius (meters) <span class="text-red-500">*</span></label>
                    <input type="number" id="areaRadius" value="0" required class="modern-input">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Color <span class="text-red-500">*</span></label>
                    <input type="color" id="areaColor" value="#3388ff" class="w-full h-10 p-1 border rounded cursor-pointer">
                </div>
                
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeAreaModal()" class="px-4 py-2 text-xs bg-gray-200 hover:bg-gray-300 font-bold rounded transition-colors">Cancel</button>
                    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-extrabold text-xs px-5 py-2 rounded shadow transition-all">Save Area</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let map;
        let dispatchMarker;
        let areasGroup;
        let customersGroup;
        
        let mapData = {
            dispatch_point: {lat: 23.8103, lng: 90.4125},
            areas: [],
            customers: []
        };
        
        let tempDrawnLayer = null;

        document.addEventListener("DOMContentLoaded", function() {
            initMap();
            loadMapData();
        });

        function initMap() {
            map = L.map('map').setView([23.8103, 90.4125], 11);
            
            // Google Maps Tile Layer
            L.tileLayer('http://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
                maxZoom: 20,
                subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
                attribution: 'Map data © Google'
            }).addTo(map);

            areasGroup = L.layerGroup().addTo(map);
            customersGroup = L.layerGroup().addTo(map);
            
            // Add Geoman controls
            map.pm.addControls({
                position: 'topleft',
                drawCircle: false,
                drawCircleMarker: false,
                drawMarker: false,
                drawPolyline: false,
                drawRectangle: true,
                drawPolygon: true,
                editMode: true,
                dragMode: false,
                cutPolygon: false,
                removalMode: true,
                drawText: false
            });
            
            // Handle shape drawing
            map.on('pm:create', function(e) {
                tempDrawnLayer = e.layer;
                const geojson = tempDrawnLayer.toGeoJSON();
                const coords = JSON.stringify(geojson.geometry.coordinates);
                
                openAreaModal(0, '', 0, 0, '#3388ff', 0, 0, coords);
            });
            
            // Handle shape editing (removal)
            map.on('pm:remove', function(e) {
                if (e.layer.areaId) {
                    deleteArea(e.layer.areaId, false); // false = don't reload data yet, just remove from db
                }
            });
        }

        async function loadMapData() {
            try {
                const res = await fetch('../api/admin_map_data.php');
                const json = await res.json();
                
                if (json.success) {
                    mapData = json.data;
                    
                    // Fallback if no dispatch point saved
                    if (!mapData.dispatch_point.lat) {
                        mapData.dispatch_point = {lat: 23.8103, lng: 90.4125};
                    }
                    
                    map.setView([mapData.dispatch_point.lat, mapData.dispatch_point.lng], 11);
                    renderDispatchPoint();
                    renderAreas();
                    renderCustomers();
                } else {
                    alert("Failed to load map data: " + json.error);
                }
            } catch (err) {
                console.error(err);
                alert("Error loading map data.");
            }
        }

        function renderDispatchPoint() {
            if (dispatchMarker) {
                map.removeLayer(dispatchMarker);
            }
            
            const icon = L.divIcon({
                className: 'dispatch-marker',
                html: '<i class="fa-solid fa-building"></i>',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });

            dispatchMarker = L.marker([mapData.dispatch_point.lat, mapData.dispatch_point.lng], {
                icon: icon,
                draggable: true,
                title: 'Dispatch Point'
            });

            dispatchMarker.bindPopup("<b>Dispatch Point</b><br>Drag to move.");
            
            dispatchMarker.on('dragend', function(e) {
                document.getElementById('saveDispatchBtn').classList.remove('hidden');
            });

            if (document.getElementById('toggleDispatch').checked) {
                dispatchMarker.addTo(map);
            }
        }

        async function saveDispatchPoint() {
            const pos = dispatchMarker.getLatLng();
            const btn = document.getElementById('saveDispatchBtn');
            
            btn.innerHTML = 'Saving...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'save_dispatch');
            formData.append('lat', pos.lat);
            formData.append('lng', pos.lng);

            try {
                const res = await fetch('../api/admin_map_save.php', { method: 'POST', body: formData });
                const json = await res.json();
                
                if (json.success) {
                    btn.classList.add('hidden');
                    mapData.dispatch_point.lat = pos.lat;
                    mapData.dispatch_point.lng = pos.lng;
                    await loadMapData(); // Reload to update all concentric area positions
                } else {
                    alert("Error saving: " + json.error);
                }
            } catch (err) {
                alert("Network error.");
            }
            
            btn.innerHTML = 'Save New Location';
            btn.disabled = false;
        }

        function renderAreas() {
            areasGroup.clearLayers();
            
            if (!document.getElementById('toggleAreas').checked) return;

            // We don't necessarily need to sort by radius anymore since they are polygons,
            // but we can just loop through and draw them
            mapData.areas.forEach(area => {
                let layer = null;
                const popupContent = `
                    <div class="text-sm">
                        <h4 class="font-bold text-gray-900 border-b pb-1 mb-2">${area.name}</h4>
                        <p class="text-gray-600 mb-1"><b>Charge:</b> ৳${area.delivery_charge}</p>
                        ${area.polygon_data ? '<p class="text-gray-600 mb-3"><b>Type:</b> Custom Shape</p>' : `<p class="text-gray-600 mb-3"><b>Radius:</b> ${area.radius_meters}m</p>`}
                        <div class="flex gap-2 mt-2">
                            <button onclick="openAreaModal(${area.id}, '${area.name.replace(/'/g, "\\'")}', ${area.delivery_charge}, ${area.radius_meters || 0}, '${area.color}', ${area.latitude || 0}, ${area.longitude || 0}, '${area.polygon_data ? area.polygon_data : ''}')" class="bg-blue-50 text-blue-600 hover:bg-blue-100 px-3 py-1.5 rounded text-xs font-bold flex-1 transition-colors text-center">Edit</button>
                            <button onclick="deleteArea(${area.id})" class="bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1.5 rounded text-xs font-bold flex-1 transition-colors text-center">Delete</button>
                        </div>
                    </div>
                `;

                if (area.polygon_data) {
                    try {
                        const coords = JSON.parse(area.polygon_data);
                        // GeoJSON coords are [lng, lat], Leaflet wants [lat, lng]
                        // GeoJSON polygon coordinates are an array of rings
                        layer = L.polygon(coords[0].map(c => [c[1], c[0]]), {
                            color: area.color || '#3388ff',
                            fillColor: area.color || '#3388ff',
                            fillOpacity: 0.2,
                            weight: 2
                        });
                        
                        // Enable editing for existing polygons
                        layer.on('pm:edit', function(e) {
                            const newCoords = JSON.stringify(e.layer.toGeoJSON().geometry.coordinates);
                            updateAreaPolygon(area.id, newCoords);
                        });
                    } catch(e) { console.error("Invalid polygon data for area " + area.id); }
                } else if (area.latitude && area.longitude && area.radius_meters) {
                    // Fallback to circle
                    layer = L.circle([area.latitude, area.longitude], {
                        color: area.color || '#3388ff',
                        fillColor: area.color || '#3388ff',
                        fillOpacity: 0.2,
                        radius: area.radius_meters,
                        weight: 2
                    });
                }
                
                if (layer) {
                    layer.bindPopup(popupContent);
                    areasGroup.addLayer(layer);
                    
                    // Attach id for geoman removal
                    layer.areaId = area.id;
                }
            });
        }
        
        async function updateAreaCoords(id, lat, lng) {
            const formData = new FormData();
            formData.append('action', 'update_area_coords');
            formData.append('id', id);
            formData.append('lat', lat);
            formData.append('lng', lng);
            
            try {
                await fetch('../api/admin_map_save.php', { method: 'POST', body: formData });
                // Update local data
                const area = mapData.areas.find(a => a.id === id);
                if (area) {
                    area.latitude = lat;
                    area.longitude = lng;
                }
            } catch (err) {
                console.error(err);
            }
        }

        function renderCustomers() {
            customersGroup.clearLayers();
            
            if (!document.getElementById('toggleCustomers').checked) return;

            mapData.customers.forEach(cust => {
                const color = cust.has_active_order ? '#10b981' : '#ef4444'; // green if active, red otherwise
                
                const icon = L.divIcon({
                    className: 'custom-pin',
                    html: `<i class="fa-solid fa-location-dot" style="color: ${color}; font-size: 28px; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);"></i>`,
                    iconSize: [28, 28],
                    iconAnchor: [14, 28]
                });

                const marker = L.marker([cust.lat, cust.lng], {
                    icon: icon,
                    title: cust.customer_name
                });
                
                marker.bindTooltip(`<b>${cust.customer_name}</b>`, {
                    permanent: true,
                    direction: 'top',
                    offset: [0, -28],
                    className: 'font-bold bg-white px-1.5 py-0.5 rounded shadow text-[10px] text-gray-800 border border-gray-200'
                });
                
                marker.bindPopup(`<b>${cust.customer_name}</b><br>${cust.phone}`);
                customersGroup.addLayer(marker);
            });
        }

        function updateLayers() {
            if (document.getElementById('toggleDispatch').checked) {
                if (dispatchMarker && !map.hasLayer(dispatchMarker)) dispatchMarker.addTo(map);
            } else {
                if (dispatchMarker && map.hasLayer(dispatchMarker)) map.removeLayer(dispatchMarker);
            }
            
            renderAreas();
            renderCustomers();
        }

        // Modal Functions
        function openAreaModal(id, name, charge, radius, color, lat, lng, polygon_data = '') {
            document.getElementById('areaId').value = id;
            document.getElementById('areaName').value = name;
            document.getElementById('areaCharge').value = charge;
            document.getElementById('areaRadius').value = radius;
            document.getElementById('areaColor').value = color || '#3388ff';
            document.getElementById('areaLat').value = lat;
            document.getElementById('areaLng').value = lng;
            document.getElementById('areaPolygonData').value = polygon_data;
            
            document.getElementById('modalTitle').innerHTML = id > 0 ? '<i class="fa-solid fa-pen text-brand-500 mr-2"></i> Edit Area' : '<i class="fa-solid fa-plus text-brand-500 mr-2"></i> New Area';
            
            const modal = document.getElementById('areaModal');
            modal.classList.remove('hidden');
            void modal.offsetWidth;
            modal.classList.remove('opacity-0');
            modal.classList.add('opacity-100');
            modal.querySelector('.transform').classList.remove('scale-95');
            modal.querySelector('.transform').classList.add('scale-100');
        }

        function closeAreaModal() {
            const modal = document.getElementById('areaModal');
            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            modal.querySelector('.transform').classList.remove('scale-100');
            modal.querySelector('.transform').classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                // Remove temporary drawn layer if it wasn't saved
                if (tempDrawnLayer && document.getElementById('areaId').value == '0') {
                    map.removeLayer(tempDrawnLayer);
                    tempDrawnLayer = null;
                }
            }, 300);
        }

        async function saveArea(e) {
            e.preventDefault();
            
            const btn = e.target.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Saving...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'save_area');
            formData.append('id', document.getElementById('areaId').value);
            formData.append('name', document.getElementById('areaName').value);
            formData.append('charge', document.getElementById('areaCharge').value);
            formData.append('radius', document.getElementById('areaRadius').value);
            formData.append('color', document.getElementById('areaColor').value);
            formData.append('lat', document.getElementById('areaLat').value);
            formData.append('lng', document.getElementById('areaLng').value);
            formData.append('polygon_data', document.getElementById('areaPolygonData').value);

            try {
                const res = await fetch('../api/admin_map_save.php', { method: 'POST', body: formData });
                const json = await res.json();
                
                if (json.success) {
                    closeAreaModal();
                    await loadMapData(); // reload everything
                } else {
                    alert("Error saving area: " + json.error);
                }
            } catch (err) {
                console.error(err);
                alert("Network error.");
            }
            
            btn.innerHTML = originalText;
            btn.disabled = false;
        }

        async function updateAreaPolygon(id, polygonData) {
            const formData = new FormData();
            formData.append('action', 'save_area'); // reuse save_area to update polygon
            
            // Fetch existing data for this area
            const area = mapData.areas.find(a => a.id === id);
            if (!area) return;

            formData.append('id', id);
            formData.append('name', area.name);
            formData.append('charge', area.delivery_charge);
            formData.append('color', area.color);
            formData.append('radius', area.radius_meters);
            formData.append('lat', area.latitude);
            formData.append('lng', area.longitude);
            formData.append('polygon_data', polygonData);
            
            try {
                await fetch('../api/admin_map_save.php', { method: 'POST', body: formData });
            } catch (err) {
                console.error("Failed to update polygon shape: ", err);
            }
        }

        async function deleteArea(id, promptAndReload = true) {
            if (promptAndReload) {
                if (!confirm("Are you sure you want to delete this delivery area?")) return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_area');
            formData.append('id', id);
            
            try {
                const res = await fetch('../api/admin_map_save.php', { method: 'POST', body: formData });
                const json = await res.json();
                
                if (json.success && promptAndReload) {
                    await loadMapData(); // reload
                } else if (!json.success) {
                    console.error("Error deleting area: " + json.error);
                }
            } catch (err) {
                console.error(err);
            }
        }
    </script>
</body>
</html>
