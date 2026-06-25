<?php
session_start();
if (!isset($_SESSION['dsr_role']) && !isset($_SESSION['admin_role'])) {
    header("Location: login.php");
    exit;
}

require_once '../db.php';
$pdo = getDB();

$slots = $pdo->query("SELECT id, slot_time FROM delivery_slots WHERE is_active = 1")->fetchAll();
$selected_slot = $_GET['slot_id'] ?? ($slots[0]['id'] ?? null);

$orders = [];
if ($selected_slot) {
    // Fetch orders that are ready to be delivered or out for delivery
    $stmt = $pdo->prepare("
        SELECT o.*, a.name as area_name 
        FROM orders o
        JOIN delivery_areas a ON o.area_id = a.id
        WHERE o.slot_id = ? AND o.status IN ('collected', 'out_for_delivery')
    ");
    $stmt->execute([$selected_slot]);
    $orders = $stmt->fetchAll();
    
    // Fetch order items details for each order
    foreach ($orders as &$order) {
        $item_stmt = $pdo->prepare("
            SELECT oi.quantity, oi.unit_price, oi.subtotal, p.name as product_name, p.image_url as product_image
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $item_stmt->execute([$order['id']]);
        $order['items'] = $item_stmt->fetchAll();
    }
    unset($order);
}

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    $action = $_POST['action'];
    $slot_id = $_POST['slot_id'];
    
    $valid_statuses = ['complete' => 'completed', 'cancel' => 'cancelled', 'due' => 'due'];
    
    if (isset($valid_statuses[$action])) {
        $new_status = $valid_statuses[$action];
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        header("Location: delivery.php?slot_id=$slot_id&success=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DSR Delivery - YukiMartBD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/js/tailwind.config.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; -webkit-tap-highlight-color: transparent; }
        .modern-input { width: 100%; padding: 0.5rem 1rem; border-radius: 0.25rem; border: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.875rem; outline: none; transition: all 0.2s; }
        .modern-input:focus { border-color: #10b981; box-shadow: 0 0 0 1px #10b981; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased pb-20 selection:bg-brand-100 selection:text-brand-900">

    <!-- Header -->
    <header class="sticky top-0 z-30 bg-brand-600 text-white shadow-sm">
        <div class="px-4 h-16 flex items-center justify-between max-w-lg mx-auto">
            <a href="collection.php" class="text-[10px] font-extrabold bg-brand-700/50 hover:bg-brand-800 px-3 py-1.5 rounded uppercase tracking-wider transition-all">
                <i class="fa-solid fa-arrow-left mr-1"></i> Collection
            </a>
            <h1 class="text-lg font-extrabold tracking-tight">Delivery Map</h1>
        </div>
    </header>

    <main class="p-4 max-w-lg mx-auto mt-2">
        <?php if(isset($_GET['success'])): ?>
            <div class="bg-brand-50 text-brand-700 p-3 rounded mb-4 text-xs font-bold flex items-center gap-2 border border-brand-200 shadow-sm">
                <i class="fa-solid fa-check-circle"></i> Order status updated successfully!
            </div>
        <?php endif; ?>

        <form method="GET" class="mb-6 bg-white p-4 rounded border border-gray-150 shadow-sm">
            <label class="block text-xs font-extrabold text-gray-400 uppercase tracking-wider mb-2">Select Delivery Slot</label>
            <div class="relative">
                <select name="slot_id" onchange="this.form.submit()" class="modern-input w-full appearance-none pr-8">
                    <?php foreach($slots as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_slot ? 'selected' : '' ?>><?= htmlspecialchars($s['slot_time']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 pointer-events-none">
                    <i class="fa-solid fa-chevron-down text-xs"></i>
                </span>
            </div>
        </form>

        <?php if (empty($orders)): ?>
            <div class="text-center bg-white p-8 rounded border border-gray-150 shadow-sm mt-6">
                <div class="bg-gray-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fa-solid fa-motorcycle text-2xl text-gray-400"></i>
                </div>
                <h3 class="font-bold text-gray-800">Clear Road Ahead!</h3>
                <p class="text-xs text-gray-500 mt-1">No orders ready for delivery in this slot.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach($orders as $o): ?>
                <div class="bg-white rounded border border-gray-150 shadow-sm overflow-hidden transition-all group hover:border-brand-300">
                    <!-- Card Header (Click to open Bottom Sheet) -->
                    <div class="p-4 cursor-pointer hover:bg-gray-50 flex justify-between items-start" onclick='openOrderDetails(<?= htmlspecialchars(json_encode($o), ENT_QUOTES, "UTF-8") ?>)'>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-black text-gray-900 text-sm">#ORD-<?= $o['id'] ?></span>
                                <?php if($o['status'] == 'out_for_delivery'): ?>
                                    <span class="bg-blue-50 text-blue-700 border border-blue-200 text-[9px] font-extrabold px-1.5 py-0.5 rounded-sm uppercase tracking-wider">Out for Delivery</span>
                                <?php else: ?>
                                    <span class="bg-orange-50 text-orange-700 border border-orange-200 text-[9px] font-extrabold px-1.5 py-0.5 rounded-sm uppercase tracking-wider">Ready to Go</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-[10px] text-gray-500 mb-1 font-bold tracking-wider uppercase flex items-center gap-1">
                                <i class="fa-solid fa-map-location-dot text-brand-500"></i> <?= htmlspecialchars($o['area_name']) ?>
                            </p>
                            <p class="text-xs font-semibold text-gray-700 truncate w-48 mt-1.5 leading-snug"><i class="fa-solid fa-location-dot text-gray-400 mr-1 text-[10px]"></i> <?= htmlspecialchars($o['shipping_address']) ?></p>
                        </div>
                        <div class="text-right flex flex-col items-end">
                            <p class="font-black text-brand-600 text-base">৳<?= $o['grand_total'] ?></p>
                            <p class="text-[10px] text-gray-400 font-bold uppercase mt-1"><?= $o['total_items'] ?> items</p>
                            <span class="text-[9px] text-brand-500 font-bold mt-2 flex items-center gap-1">Details <i class="fa-solid fa-chevron-right text-[7px]"></i></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Bottom Sheet Backdrop -->
    <div id="bottomSheetBackdrop" class="fixed inset-0 bg-black/50 z-40 opacity-0 pointer-events-none transition-opacity duration-300" onclick="closeBottomSheet()"></div>

    <!-- Bottom Sheet -->
    <div id="orderBottomSheet" class="fixed bottom-0 inset-x-0 z-50 bg-white shadow-2xl rounded-t-lg max-h-[85vh] flex flex-col transform translate-y-full transition-transform duration-300 max-w-lg mx-auto">
        <!-- Header -->
        <div class="flex flex-col items-center pt-2 pb-3 border-b border-gray-150 px-4 shrink-0">
            <div class="w-12 h-1.5 bg-gray-200 rounded-full mb-3 cursor-pointer" onclick="closeBottomSheet()"></div>
            <div class="w-full flex items-center justify-between">
                <h3 class="text-md font-extrabold text-gray-900" id="bsOrderTitle">Order Details</h3>
                <button onclick="closeBottomSheet()" class="text-gray-400 hover:text-gray-600 p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
        </div>

        <!-- Content (Scrollable) -->
        <div class="overflow-y-auto flex-1 p-4 space-y-5" id="bsContent">
            <!-- Dynamically populated via JS -->
        </div>

        <!-- Action Footer -->
        <div class="p-4 bg-gray-50 border-t border-gray-100 shrink-0" id="bsFooter">
            <!-- Dynamically populated via JS -->
        </div>
    </div>

    <script>
        function openOrderDetails(order) {
            document.getElementById('bsOrderTitle').innerHTML = `Order <span class="text-brand-600">#ORD-${order.id}</span>`;
            
            let statusBadge = '';
            if (order.status === 'out_for_delivery') {
                statusBadge = `<span class="bg-blue-50 text-blue-700 border border-blue-200 text-[10px] font-extrabold px-2 py-0.5 rounded-sm uppercase tracking-wider">Out for Delivery</span>`;
            } else {
                statusBadge = `<span class="bg-orange-50 text-orange-700 border border-orange-200 text-[10px] font-extrabold px-2 py-0.5 rounded-sm uppercase tracking-wider">Ready to Go</span>`;
            }

            let itemsHtml = `
                <div>
                    <h4 class="text-[10px] font-extrabold text-gray-400 uppercase tracking-wider mb-2">Ordered Products</h4>
                    <div class="grid grid-cols-2 gap-1.5 max-h-60 overflow-y-auto no-scrollbar p-0.5">
            `;
            order.items.forEach(item => {
                itemsHtml += `
                    <label class="flex items-center gap-2 bg-white border border-gray-200 rounded p-2.5 relative cursor-pointer select-none hover:border-brand-500 transition-colors shadow-xs">
                        <input type="checkbox" onclick="event.stopPropagation();" class="w-4 h-4 text-brand-600 rounded border-gray-300 focus:ring-brand-500 cursor-pointer flex-shrink-0">
                        <img src="${item.product_image}" onerror="this.src='https://via.placeholder.com/100'" alt="${item.product_name}" class="w-9 h-9 rounded-sm object-cover border border-gray-100 flex-shrink-0 bg-gray-50">
                        <div class="min-w-0 flex-1 leading-none">
                            <p class="text-[11px] font-bold text-gray-800 truncate" title="${item.product_name}">${item.product_name}</p>
                            <p class="text-[10px] text-gray-400 mt-1">৳${item.unit_price} &times; ${item.quantity}</p>
                            <p class="text-[11px] font-black text-brand-600 mt-1">৳${item.subtotal}</p>
                        </div>
                    </label>
                `;
            });
            itemsHtml += `
                    </div>
                </div>
            `;

            let customerHtml = `
                <div class="space-y-3">
                    <h4 class="text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Customer & Delivery Info</h4>
                    <div class="bg-white border border-gray-150 rounded p-3 space-y-2.5 text-xs shadow-xs">
                        <div class="flex items-center space-x-2">
                            <div class="w-6 h-6 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center text-xs"><i class="fa-solid fa-user"></i></div>
                            <span class="font-bold text-gray-800">${order.customer_name}</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-6 h-6 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center text-xs"><i class="fa-solid fa-location-dot"></i></div>
                            <div class="flex-1 min-w-0">
                                <span class="font-semibold text-gray-700">${order.area_name}</span>
                                <p class="text-gray-500 text-[11px] leading-relaxed mt-0.5 break-words">${order.shipping_address}</p>
                            </div>
                            <button type="button" onclick="openMapModal(${order.id}, ${order.latitude || 'null'}, ${order.longitude || 'null'})" class="${(order.latitude && order.longitude) ? 'text-red-600 bg-red-50 hover:text-red-700' : 'text-brand-600 hover:text-brand-700 bg-brand-50'} p-2 rounded flex items-center justify-center transition-all flex-shrink-0" title="${(order.latitude && order.longitude) ? 'Location Pinned' : 'Set Location'}"><i class="fa-solid fa-map-marked-alt text-sm"></i></button>
                        </div>
                        <div class="flex items-center space-x-2 pt-1">
                            <a href="tel:${order.customer_phone}" class="flex-1 flex items-center justify-center gap-2 bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-200 py-2.5 rounded font-bold transition-all text-xs">
                                <i class="fa-solid fa-phone"></i> Call Customer (${order.customer_phone})
                            </a>
                        </div>
                    </div>
                </div>
            `;

            let discountHtml = '';
            if (parseFloat(order.discount) > 0) {
                discountHtml = `
                    <div class="flex justify-between text-red-500">
                        <span>Discount</span>
                        <span class="font-semibold">-৳${order.discount}</span>
                    </div>
                `;
            }

            let summaryHtml = `
                <div class="border-t border-gray-100 pt-3.5 space-y-1.5 text-xs">
                    <div class="flex justify-between text-gray-500">
                        <span>Subtotal</span>
                        <span class="font-semibold text-gray-800">৳${order.subtotal}</span>
                    </div>
                    <div class="flex justify-between text-gray-500">
                        <span>Delivery Charge</span>
                        <span class="font-semibold text-gray-800">৳${order.delivery_charge}</span>
                    </div>
                    ${discountHtml}
                    <div class="flex justify-between text-sm font-extrabold text-gray-950 pt-2 border-t border-dashed border-gray-200">
                        <span>Grand Total</span>
                        <span class="text-brand-600 text-base">৳${order.grand_total}</span>
                    </div>
                </div>
            `;

            document.getElementById('bsContent').innerHTML = `
                <div class="flex items-center justify-between mb-1">
                    ${statusBadge}
                    <span class="text-[10px] text-gray-400 font-bold uppercase">Cash on Delivery</span>
                </div>
                ${itemsHtml}
                ${customerHtml}
                ${summaryHtml}
            `;

            document.getElementById('bsFooter').innerHTML = `
                <form method="POST" class="flex gap-2">
                    <input type="hidden" name="order_id" value="${order.id}">
                    <input type="hidden" name="slot_id" value="${order.slot_id}">
                    
                    <button type="submit" name="action" value="cancel" class="flex-1 bg-red-50 text-red-600 border border-red-200 font-extrabold py-3 rounded text-[11px] uppercase tracking-wider hover:bg-red-500 hover:text-white transition-all shadow-xs" onclick="return confirm('Cancel this order?')">Cancel</button>
                    <button type="submit" name="action" value="due" class="flex-1 bg-orange-50 text-orange-600 border border-orange-200 font-extrabold py-3 rounded text-[11px] uppercase tracking-wider hover:bg-orange-500 hover:text-white transition-all shadow-xs" onclick="return confirm('Mark as due payment?')">Make Due</button>
                    <button type="submit" name="action" value="complete" class="flex-[1.5] bg-brand-600 text-white font-black py-3 rounded text-[11px] uppercase tracking-wider shadow hover:bg-brand-700 transition-all" onclick="return confirm('Confirm order delivered and cash collected?')">Complete</button>
                </form>
            `;

            const sheet = document.getElementById('orderBottomSheet');
            const backdrop = document.getElementById('bottomSheetBackdrop');
            sheet.classList.remove('translate-y-full');
            sheet.classList.add('translate-y-0');
            backdrop.classList.remove('opacity-0', 'pointer-events-none');
            backdrop.classList.add('opacity-100', 'pointer-events-auto');
        }

        function closeBottomSheet() {
            const sheet = document.getElementById('orderBottomSheet');
            const backdrop = document.getElementById('bottomSheetBackdrop');
            sheet.classList.add('translate-y-full');
            sheet.classList.remove('translate-y-0');
            backdrop.classList.add('opacity-0', 'pointer-events-none');
            backdrop.classList.remove('opacity-100', 'pointer-events-auto');
        }
    </script>
    <script>
        let map, marker, currentOrderId;
        function openMapModal(orderId, lat, lng) {
            currentOrderId = orderId;
            document.getElementById('mapModal').classList.remove('hidden');
            
            const startLat = lat || 23.8103; // default Dhaka
            const startLng = lng || 90.4125;
            
            setTimeout(() => {
                if (!map) {
                    map = L.map('mapContainer').setView([startLat, startLng], 13);
                    L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
                        maxZoom: 20,
                        subdomains: ['mt0','mt1','mt2','mt3']
                    }).addTo(map);
                    
                    map.on('click', function(e) {
                        if (marker) { map.removeLayer(marker); }
                        marker = L.marker(e.latlng).addTo(map);
                    });
                } else {
                    map.setView([startLat, startLng], 13);
                }
                
                if (marker) { map.removeLayer(marker); }
                if (lat && lng) {
                    marker = L.marker([lat, lng]).addTo(map);
                }
                map.invalidateSize();
            }, 250);
        }

        function saveLocation() {
            if (!marker) { alert('Please select a location on the map first.'); return; }
            const latLng = marker.getLatLng();
            fetch('save_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: currentOrderId,
                    latitude: latLng.lat,
                    longitude: latLng.lng
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Location saved successfully');
                    closeMapModal();
                    location.reload(); // reload to fetch updated coordinates
                } else {
                    alert('Failed to save location: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error saving location');
            });
        }

        function closeMapModal() {
            document.getElementById('mapModal').classList.add('hidden');
        }

        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        map.setView([lat, lng], 17);
                        if (marker) { map.removeLayer(marker); }
                        marker = L.marker([lat, lng]).addTo(map);
                    },
                    function(error) {
                        alert("Unable to retrieve your location. Please check browser permissions.");
                    }
                );
            } else {
                alert("Geolocation is not supported by your browser.");
            }
        }
    </script>
<!-- Map Modal -->
<div id="mapModal" class="fixed inset-0 bg-white flex flex-col hidden z-[9999]">
    <div class="flex justify-between items-center p-4 border-b shadow-sm z-10">
        <h3 class="text-lg font-bold">Set Delivery Location</h3>
        <button onclick="closeMapModal()" class="text-gray-500 hover:text-gray-700 text-xl"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div id="mapContainer" class="relative w-full flex-1 z-0"></div>
    <div class="p-4 border-t flex justify-between items-center bg-white shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] z-10">
        <button onclick="getCurrentLocation()" class="px-4 py-2 bg-blue-50 text-blue-600 rounded border border-blue-200" title="My Location"><i class="fa-solid fa-location-crosshairs"></i></button>
        <div class="space-x-3">
            <button onclick="closeMapModal()" class="px-5 py-2 bg-gray-200 rounded font-medium">Cancel</button>
            <button id="saveLocationBtn" class="px-5 py-2 bg-brand-600 text-white rounded font-medium" onclick="saveLocation()">Save</button>
        </div>
    </div>
</div>
</body>
</html>
