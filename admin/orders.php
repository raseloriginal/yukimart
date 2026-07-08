<?php
session_start();
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once '../db.php';
$pdo = getDB();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Preserve active query filters for redirection
    $query_params = [];
    foreach ($_GET as $key => $val) {
        if ($key === 'view_order_id' || $key === 'success') continue; // Avoid duplicating
        $query_params[] = urlencode($key) . '=' . urlencode($val);
    }
    // Add view_order_id so the details modal reopens automatically after action
    if (isset($_POST['order_id'])) {
        $query_params[] = 'view_order_id=' . urlencode($_POST['order_id']);
    }
    $query_string = !empty($query_params) ? '&' . implode('&', $query_params) : '';

    if ($_POST['action'] === 'update_status') {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $_POST['order_id']]);
        header("Location: orders.php?success=1" . $query_string);
        exit;
    }
}

// Fetch helper lists for dropdowns
$areas = $pdo->query("SELECT id, name FROM delivery_areas WHERE is_active = 1")->fetchAll();
$slots = $pdo->query("SELECT id, slot_time FROM delivery_slots WHERE is_active = 1")->fetchAll();

// Pagination and Filtering Logic
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10; // 10 orders per page
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';
$area_filter = isset($_GET['area_filter']) ? trim($_GET['area_filter']) : '';
$slot_filter = isset($_GET['slot_filter']) ? trim($_GET['slot_filter']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$whereClause = "WHERE 1=1";
$params = [];

if ($search !== '') {
    if (preg_match('/^#?ORD-(\d+)$/i', $search, $matches)) {
        $whereClause .= " AND o.id = ?";
        $params[] = $matches[1];
    } elseif (is_numeric($search)) {
        $whereClause .= " AND (o.id = ? OR o.customer_phone LIKE ?)";
        $params[] = $search;
        $params[] = "%$search%";
    } else {
        $whereClause .= " AND (o.customer_name LIKE ? OR o.shipping_address LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
}

if ($status_filter !== '') {
    $whereClause .= " AND o.status = ?";
    $params[] = $status_filter;
}

if ($area_filter !== '') {
    $whereClause .= " AND o.area_id = ?";
    $params[] = $area_filter;
}

if ($slot_filter !== '') {
    $whereClause .= " AND o.slot_id = ?";
    $params[] = $slot_filter;
}

if ($start_date !== '') {
    $whereClause .= " AND DATE(o.created_at) >= ?";
    $params[] = $start_date;
}

if ($end_date !== '') {
    $whereClause .= " AND DATE(o.created_at) <= ?";
    $params[] = $end_date;
}

// Calculate dynamic stats metrics matching filters
$statsQuery = "
    SELECT 
        COUNT(o.id) as total_count,
        COALESCE(SUM(o.grand_total), 0) as total_revenue,
        SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN o.status = 'due' THEN 1 ELSE 0 END) as due_count,
        COALESCE(SUM(CASE WHEN o.status = 'due' THEN o.grand_total ELSE 0 END), 0) as due_amount,
        COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.grand_total ELSE 0 END), 0) as completed_revenue,
        SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM orders o
    JOIN delivery_areas a ON o.area_id = a.id
    LEFT JOIN delivery_slots s ON o.slot_id = s.id
    $whereClause
";
$stmtStats = $pdo->prepare($statsQuery);
$stmtStats->execute($params);
$stats = $stmtStats->fetch();

// Count total orders matching filters for pagination
$countQuery = "
    SELECT COUNT(o.id) 
    FROM orders o
    JOIN delivery_areas a ON o.area_id = a.id
    LEFT JOIN delivery_slots s ON o.slot_id = s.id
    $whereClause
";
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$totalOrders = $stmtCount->fetchColumn();
$totalPages = max(1, ceil($totalOrders / $limit));

// Fetch matching orders for current page
$query = "
    SELECT o.*, a.name as area_name, COALESCE(s.slot_time, 'Deleted Slot') as slot_time
    FROM orders o 
    JOIN delivery_areas a ON o.area_id = a.id
    LEFT JOIN delivery_slots s ON o.slot_id = s.id
    $whereClause
    ORDER BY o.id DESC
    LIMIT $limit OFFSET $offset
";
$stmtOrders = $pdo->prepare($query);
$stmtOrders->execute($params);
$orders = $stmtOrders->fetchAll();

// Retrieve all order items for the fetched orders to avoid N+1 query loops
$order_ids = array_column($orders, 'id');
$order_items_map = [];
if (!empty($order_ids)) {
    $in_clause = implode(',', array_fill(0, count($order_ids), '?'));
    $item_stmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name, p.image_url as product_image
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id IN ($in_clause)
    ");
    $item_stmt->execute($order_ids);
    $items = $item_stmt->fetchAll();
    foreach ($items as $item) {
        $order_items_map[$item['order_id']][] = $item;
    }
}

$status_colors = [
    'pending' => 'bg-gray-100 text-gray-800 border-gray-200',
    'processing' => 'bg-blue-100 text-blue-800 border-blue-200',
    'collected' => 'bg-brand-100 text-purple-800 border-purple-200',
    'out_for_delivery' => 'bg-orange-100 text-orange-800 border-orange-200',
    'completed' => 'bg-brand-100 text-emerald-800 border-emerald-200',
    'cancelled' => 'bg-red-100 text-red-800 border-red-200',
    'due' => 'bg-yellow-100 text-yellow-800 border-yellow-200'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - YukiMartBD Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/js/tailwind.config.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Leaflet CSS & JS for map integration -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .modern-input { width: 100%; padding: 0.5rem 1rem; border-radius: 0.25rem; border: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.875rem; outline: none; transition: all 0.2s; }
        .modern-input:focus { border-color: #10b981; box-shadow: 0 0 0 1px #10b981; }
        
        /* Custom scrollbar styling */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        @media print {
            body * { visibility: hidden; }
            #printInvoiceContainer, #printInvoiceContainer * { visibility: visible; }
            #printInvoiceContainer { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased flex h-screen overflow-hidden selection:bg-brand-100 selection:text-brand-900">

    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col hidden md:flex z-20 shadow-sm shrink-0">
        <div class="h-16 flex items-center px-6 border-b border-gray-100 shrink-0">
            <div class="flex items-center justify-center mr-3"><img src="../assets/images/logo.png" alt="Logo" class="w-8 h-8 object-contain rounded"></div>
            <h1 class="text-lg font-extrabold tracking-tight text-gray-900">Yuki<span class="text-brand-500">Admin</span></h1>
        </div>
        <nav class="flex-1 p-4 space-y-1.5 overflow-y-auto">
            <h4 class="text-[10px] font-extrabold text-gray-400 uppercase tracking-wider mb-2 mt-2 px-2">Menu</h4>
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-chart-pie w-5 text-center"></i> Dashboard
            </a>
            <a href="products.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-box w-5 text-center"></i> Products
            </a>
            <a href="orders.php" class="flex items-center gap-3 px-3 py-2.5 bg-brand-50 text-brand-600 font-bold rounded text-sm transition-all shadow-sm border border-brand-100">
                <i class="fa-solid fa-cart-shopping w-5 text-center"></i> Orders
            </a>
            <a href="users.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-users w-5 text-center"></i> Users (DSR)
            </a>
            <a href="warehouses.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-warehouse w-5 text-center"></i> Warehouses
            </a>
            <a href="areas.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-map-location-dot w-5 text-center"></i> Delivery Areas
            </a>
            <a href="map.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-map w-5 text-center"></i> Delivery Map
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-cog w-5 text-center"></i> Settings
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-y-auto relative w-full">
        <!-- Header -->
        <header class="h-16 bg-white border-b border-gray-100 flex items-center px-6 justify-between sticky top-0 z-10 shadow-sm shrink-0">
            <div class="md:hidden flex items-center gap-3">
                <div class="flex items-center justify-center"><img src="../assets/images/logo.png" alt="Logo" class="w-8 h-8 object-contain rounded shadow-sm"></div>
                <h1 class="text-lg font-extrabold tracking-tight text-gray-900">Yuki<span class="text-brand-500">Admin</span></h1>
            </div>
            <h2 class="hidden md:block text-lg font-extrabold text-gray-900 tracking-tight">Manage Orders</h2>
        </header>

        <div class="p-6 max-w-7xl mx-auto w-full">
            <?php if(isset($_GET['success'])): ?>
                <div class="bg-brand-50 text-brand-700 p-4 rounded mb-6 font-semibold border border-brand-200 shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-check-circle"></i> Order updated successfully!
                </div>
            <?php endif; ?>

            <!-- KPI Stats Dashboard Cards Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                <!-- Card 1 -->
                <div class="bg-white rounded-lg p-5 border border-gray-150 shadow-sm flex items-center gap-4 hover:shadow-md transition-shadow">
                    <div class="bg-blue-50 text-blue-500 w-12 h-12 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-cart-shopping text-xl"></i>
                    </div>
                    <div>
                        <span class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Total Orders</span>
                        <span class="block font-black text-gray-900 text-xl mt-0.5"><?= $stats['total_count'] ?></span>
                    </div>
                </div>
                <!-- Card 2 -->
                <div class="bg-white rounded-lg p-5 border border-gray-150 shadow-sm flex items-center gap-4 hover:shadow-md transition-shadow">
                    <div class="bg-brand-50 text-brand-500 w-12 h-12 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-sack-dollar text-xl"></i>
                    </div>
                    <div>
                        <span class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Completed Sales</span>
                        <span class="block font-black text-brand-600 text-xl mt-0.5">৳<?= number_format($stats['completed_revenue'], 2) ?></span>
                        <span class="block text-[9px] text-gray-400 font-semibold mt-0.5"><?= $stats['completed_count'] ?> Delivered</span>
                    </div>
                </div>
                <!-- Card 3 -->
                <div class="bg-white rounded-lg p-5 border border-gray-150 shadow-sm flex items-center gap-4 hover:shadow-md transition-shadow">
                    <div class="bg-amber-50 text-amber-500 w-12 h-12 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-clock-rotate-left text-xl"></i>
                    </div>
                    <div>
                        <span class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Pending Orders</span>
                        <span class="block font-black text-amber-600 text-xl mt-0.5"><?= $stats['pending_count'] ?></span>
                        <span class="block text-[9px] text-gray-400 font-semibold mt-0.5">Require Action</span>
                    </div>
                </div>
                <!-- Card 4 -->
                <div class="bg-white rounded-lg p-5 border border-gray-150 shadow-sm flex items-center gap-4 hover:shadow-md transition-shadow">
                    <div class="bg-red-50 text-red-500 w-12 h-12 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-circle-exclamation text-xl"></i>
                    </div>
                    <div>
                        <span class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Due Amount</span>
                        <span class="block font-black text-red-600 text-xl mt-0.5">৳<?= number_format($stats['due_amount'], 2) ?></span>
                        <span class="block text-[9px] text-gray-400 font-semibold mt-0.5"><?= $stats['due_count'] ?> Unpaid</span>
                    </div>
                </div>
            </div>

            <!-- Filters Bar -->
            <form method="GET" class="bg-white rounded-lg border border-gray-150 shadow-sm p-5 mb-6 space-y-4">
                <!-- Top Row: Search and Filters -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <!-- Search Input -->
                    <div class="md:col-span-6 relative">
                        <i class="fa-solid fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by Order ID, Name, Phone..." class="w-full pl-11 pr-4 py-2.5 border border-gray-200 rounded text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-all">
                    </div>
                    <!-- Status Filter -->
                    <div class="md:col-span-2">
                        <select name="status_filter" class="w-full py-2.5 px-3 border border-gray-200 rounded text-sm focus:ring-2 focus:ring-brand-500 outline-none bg-white">
                            <option value="">All Statuses</option>
                            <?php foreach(array_keys($status_colors) as $st): ?>
                                <option value="<?= $st ?>" <?= $status_filter === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Area Filter -->
                    <div class="md:col-span-2">
                        <select name="area_filter" class="w-full py-2.5 px-3 border border-gray-200 rounded text-sm focus:ring-2 focus:ring-brand-500 outline-none bg-white">
                            <option value="">All Areas</option>
                            <?php foreach($areas as $a): ?>
                                <option value="<?= $a['id'] ?>" <?= (string)$area_filter === (string)$a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Slot Filter -->
                    <div class="md:col-span-2">
                        <select name="slot_filter" class="w-full py-2.5 px-3 border border-gray-200 rounded text-sm focus:ring-2 focus:ring-brand-500 outline-none bg-white">
                            <option value="">All Slots</option>
                            <?php foreach($slots as $sl): ?>
                                <option value="<?= $sl['id'] ?>" <?= (string)$slot_filter === (string)$sl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sl['slot_time']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Bottom Row: Date Filters and Presets -->
                <div class="flex flex-col sm:flex-row justify-between items-center pt-3 border-t border-gray-100 gap-3">
                    <!-- Date Inputs -->
                    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                        <span class="text-xs font-semibold text-gray-500">Order Date:</span>
                        <input type="date" name="start_date" id="filter_start_date" value="<?= htmlspecialchars($start_date) ?>" class="py-1.5 px-3 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-brand-500 outline-none bg-gray-50">
                        <span class="text-gray-400 text-xs">to</span>
                        <input type="date" name="end_date" id="filter_end_date" value="<?= htmlspecialchars($end_date) ?>" class="py-1.5 px-3 border border-gray-200 rounded text-xs focus:ring-1 focus:ring-brand-500 outline-none bg-gray-50">
                    </div>
                    <!-- Quick Date Presets & Actions -->
                    <div class="flex items-center gap-1.5 w-full sm:w-auto justify-end">
                        <button type="button" onclick="setDatePreset('today')" class="px-3 py-1.5 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded font-semibold transition-colors">Today</button>
                        <button type="button" onclick="setDatePreset('yesterday')" class="px-3 py-1.5 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded font-semibold transition-colors">Yesterday</button>
                        <button type="button" onclick="setDatePreset('week')" class="px-3 py-1.5 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 rounded font-semibold transition-colors">Last 7 Days</button>
                        <button type="button" onclick="setDatePreset('clear')" class="px-3 py-1.5 text-xs bg-red-50 hover:bg-red-100 text-red-600 rounded font-semibold transition-colors">Clear Date</button>
                        
                        <span class="h-6 w-[1px] bg-gray-200 mx-1"></span>
                        
                        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 px-5 rounded text-xs shadow-sm transition-all">Apply Filter</button>
                        <?php if ($search !== '' || $status_filter !== '' || $area_filter !== '' || $slot_filter !== '' || $start_date !== '' || $end_date !== ''): ?>
                            <a href="orders.php" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded text-xs font-semibold transition-colors">Reset All</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Table Card -->
            <div class="bg-white rounded-lg border border-gray-150 shadow-sm overflow-hidden mb-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Order ID</th>
                                <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Customer</th>
                                <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Delivery Details</th>
                                <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Amount</th>
                                <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($orders as $o): 
                                $o_json = [
                                    'id' => $o['id'],
                                    'customer_name' => $o['customer_name'],
                                    'customer_phone' => $o['customer_phone'],
                                    'shipping_address' => $o['shipping_address'],
                                    'area_name' => $o['area_name'],
                                    'slot_time' => $o['slot_time'],
                                    'total_items' => $o['total_items'],
                                    'subtotal' => $o['subtotal'],
                                    'delivery_charge' => $o['delivery_charge'],
                                    'discount' => $o['discount'],
                                    'coupon_code' => $o['coupon_code'],
                                    'grand_total' => $o['grand_total'],
                                    'status' => $o['status'],
                                    'created_at' => date('M d, Y H:i', strtotime($o['created_at'])),
                                    'latitude' => $o['latitude'],
                                    'longitude' => $o['longitude'],
                                    'items' => $order_items_map[$o['id']] ?? []
                                ];
                            ?>
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <!-- Order ID -->
                                <td class="p-4">
                                    <div class="font-bold text-sm text-gray-800">#ORD-<?= $o['id'] ?></div>
                                    <div class="text-[10px] text-gray-400 font-bold uppercase mt-1">
                                        <?= date('M d, H:i', strtotime($o['created_at'])) ?>
                                    </div>
                                </td>
                                
                                <!-- Customer Info -->
                                <td class="p-4">
                                    <p class="font-bold text-sm text-gray-800"><?= htmlspecialchars($o['customer_name']) ?></p>
                                    <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($o['customer_phone']) ?></p>
                                </td>
                                
                                <!-- Details -->
                                <td class="p-4 text-xs text-gray-600 space-y-1">
                                    <p>
                                        <span class="font-bold text-gray-400 uppercase text-[9px] tracking-wider">Area:</span> 
                                        <span class="font-semibold text-gray-700"><?= htmlspecialchars($o['area_name']) ?></span>
                                    </p>
                                    <p>
                                        <span class="font-bold text-gray-400 uppercase text-[9px] tracking-wider">Slot:</span> 
                                        <span class="font-semibold text-gray-700 text-[11px]"><?= htmlspecialchars($o['slot_time']) ?></span>
                                    </p>
                                    <p>
                                        <span class="font-bold text-gray-400 uppercase text-[9px] tracking-wider">Items:</span> 
                                        <span class="font-semibold text-gray-700"><?= $o['total_items'] ?></span>
                                    </p>
                                </td>
                                
                                <!-- Total -->
                                <td class="p-4">
                                    <p class="font-extrabold text-brand-600 text-sm">৳<?= $o['grand_total'] ?></p>
                                    <?php if ($o['discount'] > 0): ?>
                                        <span class="inline-block text-[9px] font-bold text-red-500 bg-red-50 border border-red-100 rounded-sm px-1.5 mt-0.5" title="Discount Applied (Code: <?= htmlspecialchars($o['coupon_code']) ?>)">
                                            -৳<?= $o['discount'] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Status -->
                                <td class="p-4">
                                    <form method="POST" class="m-0">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <select name="status" class="modern-input py-1.5 px-2 text-xs font-semibold border-gray-200 bg-white cursor-pointer shadow-xs focus:ring-1 focus:ring-brand-500 rounded <?= $o['status'] === 'completed' ? 'text-brand-700 bg-brand-50 border-emerald-200' : ($o['status'] === 'cancelled' ? 'text-red-700 bg-red-50 border-red-200' : ($o['status'] === 'due' ? 'text-yellow-700 bg-yellow-50 border-yellow-200' : 'text-gray-700')) ?>" onchange="this.form.submit()">
                                            <?php foreach(array_keys($status_colors) as $st): ?>
                                                <option value="<?= $st ?>" <?= $o['status'] === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                
                                <!-- Actions -->
                                <td class="p-4 text-right">
                                    <button type="button" 
                                            data-order-btn-id="<?= $o['id'] ?>" 
                                            onclick='openOrderDetails(<?= htmlspecialchars(json_encode($o_json, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>)' 
                                            class="text-brand-600 hover:text-brand-700 bg-brand-50 hover:bg-brand-100 font-extrabold text-xs px-3 py-2 rounded flex items-center gap-1.5 transition-all border border-brand-100 hover:border-brand-200 shadow-sm ml-auto" 
                                            title="View Details">
                                        <i class="fa-solid fa-eye"></i> View Details
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if(count($orders) === 0): ?>
                <div class="text-center py-16">
                    <i class="fa-solid fa-folder-open text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-bold text-gray-500">No orders found.</h3>
                    <p class="text-sm text-gray-400 mt-1">Try adjusting your filters or search criteria.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Server-Side Pagination Controls -->
            <?php if($totalPages > 1): ?>
            <div class="mt-8 flex flex-col items-center justify-center gap-3">
                <div class="flex justify-center items-center space-x-1">
                    <!-- First & Prev -->
                    <?php if($page > 1): 
                        $query_args = $_GET;
                        $query_args['page'] = 1;
                        $first_url = "?" . http_build_query($query_args);
                        $query_args['page'] = $page - 1;
                        $prev_url = "?" . http_build_query($query_args);
                    ?>
                        <a href="<?= $first_url ?>" class="px-3 py-2 bg-white border border-gray-200 text-gray-500 hover:bg-brand-50 hover:text-brand-600 rounded-md font-semibold transition-all shadow-sm text-xs"><i class="fa-solid fa-angles-left"></i></a>
                        <a href="<?= $prev_url ?>" class="px-3 py-2 bg-white border border-gray-200 text-gray-500 hover:bg-brand-50 hover:text-brand-600 rounded-md font-semibold transition-all shadow-sm text-xs flex items-center gap-1"><i class="fa-solid fa-chevron-left text-[10px]"></i> Prev</a>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <?php 
                        $start_p = max(1, $page - 2);
                        $end_p = min($totalPages, $page + 2);
                        for($p = $start_p; $p <= $end_p; $p++): 
                            $query_args = $_GET;
                            $query_args['page'] = $p;
                            $p_url = "?" . http_build_query($query_args);
                            $is_active = ($p === $page);
                    ?>
                        <a href="<?= $p_url ?>" class="px-3 py-2 rounded-md font-bold transition-all text-xs border <?= $is_active ? 'bg-brand-500 border-brand-500 text-white shadow-md' : 'bg-white border-gray-200 text-gray-600 hover:bg-brand-50 hover:text-brand-600 shadow-sm' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    
                    <!-- Next & Last -->
                    <?php if($page < $totalPages): 
                        $query_args = $_GET;
                        $query_args['page'] = $page + 1;
                        $next_url = "?" . http_build_query($query_args);
                        $query_args['page'] = $totalPages;
                        $last_url = "?" . http_build_query($query_args);
                    ?>
                        <a href="<?= $next_url ?>" class="px-3 py-2 bg-white border border-gray-200 text-gray-500 hover:bg-brand-50 hover:text-brand-600 rounded-md font-semibold transition-all shadow-sm text-xs flex items-center gap-1">Next <i class="fa-solid fa-chevron-right text-[10px]"></i></a>
                        <a href="<?= $last_url ?>" class="px-3 py-2 bg-white border border-gray-200 text-gray-500 hover:bg-brand-50 hover:text-brand-600 rounded-md font-semibold transition-all shadow-sm text-xs"><i class="fa-solid fa-angles-right"></i></a>
                    <?php endif; ?>
                </div>
                <div class="text-center text-xs text-gray-400 font-semibold">
                    Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalOrders) ?> of <?= $totalOrders ?> orders
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Order Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-black/60 hidden flex items-center justify-center z-50 p-4 backdrop-blur-sm transition-all duration-300 opacity-0">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col transform scale-95 transition-transform duration-300">
            <!-- Header -->
            <div class="p-5 border-b border-gray-150 flex justify-between items-center bg-gray-50 rounded-t-lg shrink-0">
                <div class="flex items-center gap-3">
                    <div class="bg-brand-500 text-white w-9 h-9 flex items-center justify-center rounded-lg shadow-sm">
                        <i class="fa-solid fa-receipt text-base"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-gray-900 leading-tight uppercase flex items-center gap-2">
                            <span id="modalOrderTitle">Order Details</span>
                            <span id="modalStatusBadge"></span>
                        </h3>
                        <p class="text-[10px] text-gray-400 font-bold uppercase mt-0.5" id="modalOrderDate"></p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="printReceipt()" class="text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 text-xs font-bold px-3.5 py-2 rounded-md transition-colors flex items-center gap-1.5 border border-blue-100 shadow-sm">
                        <i class="fa-solid fa-print"></i> Print Invoice
                    </button>
                    <button type="button" onclick="closeDetailsModal()" class="text-gray-400 hover:text-gray-800 bg-gray-200 hover:bg-gray-300 w-8 h-8 rounded-full transition-colors flex items-center justify-center shadow-inner"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            
            <!-- Body -->
            <div class="overflow-y-auto flex-1 bg-gray-50/50 p-6">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Left: Items Checklist -->
                    <div class="lg:col-span-7 space-y-4">
                        <div class="bg-white p-5 rounded-lg border border-gray-150 shadow-sm">
                            <div class="flex justify-between items-center mb-3 pb-2.5 border-b border-gray-100">
                                <h4 class="text-xs font-extrabold text-gray-400 uppercase tracking-wider flex items-center gap-1.5"><i class="fa-solid fa-clipboard-list text-brand-500"></i> Packing Checklist</h4>
                                <span class="text-[10px] bg-brand-50 text-brand-700 px-2 py-0.5 rounded font-extrabold uppercase tracking-wide">Check items off as you pack</span>
                            </div>
                            <div class="space-y-2.5 max-h-[45vh] overflow-y-auto pr-1" id="modalItemsList">
                                <!-- Items populated dynamically via JS -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right: Customer, Location Map, Billing -->
                    <div class="lg:col-span-5 space-y-4">
                        <!-- Customer Details -->
                        <div class="bg-white p-5 rounded-lg border border-gray-150 shadow-sm space-y-3">
                            <h4 class="text-xs font-extrabold text-gray-400 uppercase tracking-wider mb-1"><i class="fa-solid fa-user-tag text-blue-500 mr-1.5"></i> Customer & Shipping</h4>
                            <div class="space-y-2 text-xs">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-xs shrink-0"><i class="fa-solid fa-user"></i></div>
                                    <span class="font-extrabold text-gray-800" id="modalCustomerName"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-xs shrink-0"><i class="fa-solid fa-phone"></i></div>
                                    <a href="" id="modalCustomerPhoneLink" class="font-bold text-blue-600 hover:underline"></a>
                                </div>
                                <div class="flex items-start gap-2">
                                    <div class="w-6 h-6 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-xs shrink-0 mt-0.5"><i class="fa-solid fa-location-dot"></i></div>
                                    <div class="flex-1">
                                        <span class="font-extrabold text-gray-700" id="modalAreaName"></span>
                                        <p class="text-gray-500 text-[11px] leading-relaxed mt-0.5 break-words" id="modalShippingAddress"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Map Wrapper -->
                        <div class="bg-white p-5 rounded-lg border border-gray-150 shadow-sm space-y-3" id="modalMapWrapper">
                            <h4 class="text-xs font-extrabold text-gray-400 uppercase tracking-wider mb-1 flex items-center justify-between">
                                <span><i class="fa-solid fa-map-location-dot text-red-500 mr-1.5"></i> Pinpointed Location</span>
                                <span class="text-[9px] bg-red-50 text-red-600 border border-red-100 rounded px-1.5 py-0.5 font-bold uppercase tracking-wider">Satellite View</span>
                            </h4>
                            <div id="modalMapContainer" class="w-full h-40 rounded border border-gray-200 shadow-inner z-0"></div>
                        </div>
                        
                        <!-- Billing breakdown -->
                        <div class="bg-white p-5 rounded-lg border border-gray-150 shadow-sm space-y-2.5 text-xs">
                            <h4 class="text-xs font-extrabold text-gray-400 uppercase tracking-wider mb-1"><i class="fa-solid fa-file-invoice-dollar text-brand-500 mr-1.5"></i> Order Payment</h4>
                            <div class="flex justify-between text-gray-500 font-semibold">
                                <span>Subtotal</span>
                                <span class="text-gray-800 font-bold" id="modalBillingSubtotal"></span>
                            </div>
                            <div class="flex justify-between text-gray-500 font-semibold">
                                <span>Delivery Charge</span>
                                <span class="text-gray-800 font-bold" id="modalBillingDelivery"></span>
                            </div>
                            <div class="flex justify-between text-red-500 font-bold hidden" id="modalBillingDiscountRow">
                                <span id="modalDiscountLabel">Discount</span>
                                <span id="modalBillingDiscount"></span>
                            </div>
                            <div class="flex justify-between text-sm font-black text-gray-950 pt-2 border-t border-dashed border-gray-200">
                                <span>Grand Total</span>
                                <span class="text-brand-600 text-base" id="modalBillingGrand"></span>
                            </div>
                        </div>

                        <!-- Manage status dropdown inside modal -->
                        <div class="bg-white p-5 rounded-lg border border-gray-150 shadow-sm space-y-3">
                            <h4 class="text-xs font-extrabold text-gray-400 uppercase tracking-wider mb-1"><i class="fa-solid fa-sliders text-brand-500 mr-1.5"></i> Quick Management</h4>
                            
                            <form method="POST" class="m-0 space-y-1">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="order_id" id="modalStatus_order_id">
                                <label class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Update Order Status</label>
                                <select name="status" id="modalStatus_select" class="modern-input py-2 px-3 text-xs font-semibold border-gray-200 bg-white cursor-pointer shadow-xs focus:ring-1 focus:ring-brand-500" onchange="this.form.submit()">
                                    <?php foreach(array_keys($status_colors) as $st): ?>
                                        <option value="<?= $st ?>"><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Printer POS Invoice Container -->
    <div id="printInvoiceContainer" class="hidden"></div>

    <script>
        // Set date filter inputs based on quick presets
        function setDatePreset(preset) {
            const startInput = document.getElementById('filter_start_date');
            const endInput = document.getElementById('filter_end_date');
            const today = new Date();
            
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            if (preset === 'today') {
                const formatted = formatDate(today);
                startInput.value = formatted;
                endInput.value = formatted;
            } else if (preset === 'yesterday') {
                const yesterday = new Date(today);
                yesterday.setDate(today.getDate() - 1);
                const formatted = formatDate(yesterday);
                startInput.value = formatted;
                endInput.value = formatted;
            } else if (preset === 'week') {
                const weekAgo = new Date(today);
                weekAgo.setDate(today.getDate() - 6);
                startInput.value = formatDate(weekAgo);
                endInput.value = formatDate(today);
            } else if (preset === 'clear') {
                startInput.value = '';
                endInput.value = '';
            }
        }

        // Modal Details UI logic
        let currentOrder = null;
        let detailMap = null;
        let detailMarker = null;

        function openOrderDetails(order) {
            currentOrder = order;
            
            // Set basic headers
            document.getElementById('modalOrderTitle').innerHTML = `Order <span class="text-brand-500">#ORD-${order.id}</span>`;
            document.getElementById('modalOrderDate').innerText = 'Placed on: ' + order.created_at;
            
            const colors = {
                'pending': 'bg-gray-100 text-gray-800 border-gray-200',
                'processing': 'bg-blue-100 text-blue-800 border-blue-200',
                'collected': 'bg-brand-100 text-purple-800 border-purple-200',
                'out_for_delivery': 'bg-orange-100 text-orange-800 border-orange-200',
                'completed': 'bg-brand-100 text-emerald-800 border-emerald-200',
                'cancelled': 'bg-red-100 text-red-800 border-red-200',
                'due': 'bg-yellow-100 text-yellow-800 border-yellow-200'
            };
            const statusText = order.status.replace('_', ' ');
            document.getElementById('modalStatusBadge').className = `ml-2 inline-block px-2.5 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider ${colors[order.status] || ''}`;
            document.getElementById('modalStatusBadge').innerText = statusText;

            // Customer details
            document.getElementById('modalCustomerName').innerText = order.customer_name;
            document.getElementById('modalCustomerPhoneLink').innerText = order.customer_phone;
            document.getElementById('modalCustomerPhoneLink').href = `tel:${order.customer_phone}`;
            document.getElementById('modalAreaName').innerText = order.area_name + ' (Slot: ' + order.slot_time + ')';
            document.getElementById('modalShippingAddress').innerText = order.shipping_address;

            // Billing breakdown values
            document.getElementById('modalBillingSubtotal').innerText = `৳${parseFloat(order.subtotal).toFixed(2)}`;
            document.getElementById('modalBillingDelivery').innerText = `৳${parseFloat(order.delivery_charge).toFixed(2)}`;
            
            const discount = parseFloat(order.discount || 0);
            const discountRow = document.getElementById('modalBillingDiscountRow');
            if (discount > 0) {
                discountRow.classList.remove('hidden');
                document.getElementById('modalDiscountLabel').innerText = `Discount ${order.coupon_code ? '(' + order.coupon_code + ')' : ''}`;
                document.getElementById('modalBillingDiscount').innerText = `-৳${discount.toFixed(2)}`;
            } else {
                discountRow.classList.add('hidden');
            }
            document.getElementById('modalBillingGrand').innerText = `৳${parseFloat(order.grand_total).toFixed(2)}`;

            // Quick forms status value
            document.getElementById('modalStatus_order_id').value = order.id;
            document.getElementById('modalStatus_select').value = order.status;

            // Items Packing checklist template
            let itemsHtml = '';
            order.items.forEach((item, index) => {
                const itemSub = parseFloat(item.subtotal || 0).toFixed(2);
                const itemPrice = parseFloat(item.unit_price || 0).toFixed(2);
                
                itemsHtml += `
                    <label class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded p-3 select-none hover:border-brand-500 hover:bg-brand-50/20 transition-all cursor-pointer relative group">
                        <div class="flex items-center gap-3 min-w-0">
                            <input type="checkbox" id="chk_item_${order.id}_${index}" class="w-4.5 h-4.5 text-brand-600 rounded border-gray-300 focus:ring-brand-500 cursor-pointer flex-shrink-0" onclick="event.stopPropagation()">
                            <img src="${item.product_image || 'https://via.placeholder.com/100'}" onerror="this.src='https://via.placeholder.com/100'" alt="product image" class="w-10 h-10 rounded object-cover border border-gray-100 flex-shrink-0 bg-white">
                            <div class="min-w-0 leading-tight">
                                <p class="text-xs font-bold text-gray-800 truncate" title="${item.product_name}">${item.product_name}</p>
                                <p class="text-[10px] text-gray-400 mt-1">৳${itemPrice} &times; ${item.quantity}</p>
                            </div>
                        </div>
                        <div class="text-right font-black text-brand-600 text-xs pl-2 shrink-0">
                            ৳${itemSub}
                        </div>
                    </label>
                `;
            });
            
            if (order.items.length === 0) {
                itemsHtml = `<div class="text-center text-xs text-gray-400 py-6">No items found for this order.</div>`;
            }
            document.getElementById('modalItemsList').innerHTML = itemsHtml;

            // Open with visual transitions
            const modal = document.getElementById('detailsModal');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                modal.querySelector('.transform').classList.remove('scale-95');
                modal.querySelector('.transform').classList.add('scale-100');
            }, 10);

            // Initialize Map
            initModalMap(order.latitude, order.longitude);
        }

        function closeDetailsModal() {
            const modal = document.getElementById('detailsModal');
            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            modal.querySelector('.transform').classList.remove('scale-100');
            modal.querySelector('.transform').classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        function initModalMap(lat, lng) {
            const mapWrapper = document.getElementById('modalMapWrapper');
            if (!lat || !lng) {
                mapWrapper.classList.add('hidden');
                return;
            }
            mapWrapper.classList.remove('hidden');
            
            setTimeout(() => {
                const floatLat = parseFloat(lat);
                const floatLng = parseFloat(lng);
                if (!detailMap) {
                    detailMap = L.map('modalMapContainer').setView([floatLat, floatLng], 16);
                    L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
                        maxZoom: 20,
                        subdomains: ['mt0','mt1','mt2','mt3']
                    }).addTo(detailMap);
                    detailMarker = L.marker([floatLat, floatLng]).addTo(detailMap);
                } else {
                    detailMap.setView([floatLat, floatLng], 16);
                    if (detailMarker) {
                        detailMarker.setLatLng([floatLat, floatLng]);
                    } else {
                        detailMarker = L.marker([floatLat, floatLng]).addTo(detailMap);
                    }
                }
                detailMap.invalidateSize();
            }, 300);
        }

        // Print POS receipt layout
        function printReceipt() {
            if (!currentOrder) return;
            
            let itemsRows = '';
            currentOrder.items.forEach(item => {
                const itemSub = parseFloat(item.subtotal || 0).toFixed(2);
                const itemPrice = parseFloat(item.unit_price || 0).toFixed(2);
                itemsRows += `
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 6px 0; font-size: 11px;">${item.product_name}</td>
                        <td style="padding: 6px 0; text-align: center; font-size: 11px;">৳${itemPrice}</td>
                        <td style="padding: 6px 0; text-align: center; font-size: 11px;">${item.quantity}</td>
                        <td style="padding: 6px 0; text-align: right; font-size: 11px; font-weight: bold;">৳${itemSub}</td>
                    </tr>
                `;
            });

            const discount = parseFloat(currentOrder.discount || 0);
            let discountBlock = '';
            if (discount > 0) {
                discountBlock = `
                    <div style="display: flex; justify-content: space-between; font-size: 11px; margin-top: 4px; color: red;">
                        <span>Discount ${currentOrder.coupon_code ? '(' + currentOrder.coupon_code + ')' : ''}:</span>
                        <span>-৳${discount.toFixed(2)}</span>
                    </div>
                `;
            }

            const printHtml = `
                <div style="font-family: monospace; max-width: 300px; margin: 0 auto; color: #000; padding: 10px;">
                    <div style="text-align: center; margin-bottom: 15px;">
                        <h2 style="margin: 0; font-size: 18px; font-weight: 800;">YUKIMART BD</h2>
                        <p style="margin: 2px 0; font-size: 10px;">Quality Groceries at Wholesale Price</p>
                        <p style="margin: 2px 0; font-size: 10px;">Tel: 01700000000</p>
                        <div style="border-bottom: 2px dashed #000; margin-top: 10px;"></div>
                    </div>
                    
                    <div style="font-size: 10px; margin-bottom: 10px; line-height: 1.4;">
                        <div><strong>Order ID:</strong> #ORD-${currentOrder.id}</div>
                        <div><strong>Date:</strong> ${currentOrder.created_at}</div>
                        <div><strong>Customer:</strong> ${currentOrder.customer_name}</div>
                        <div><strong>Phone:</strong> ${currentOrder.customer_phone}</div>
                        <div><strong>Area:</strong> ${currentOrder.area_name}</div>
                        <div><strong>Address:</strong> ${currentOrder.shipping_address}</div>
                        <div><strong>Slot:</strong> ${currentOrder.slot_time}</div>
                    </div>
                    
                    <div style="border-bottom: 1px dashed #000; margin-bottom: 5px;"></div>
                    
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px dashed #000;">
                                <th style="text-align: left; padding: 4px 0; font-size: 10px;">Item</th>
                                <th style="text-align: center; padding: 4px 0; font-size: 10px;">Price</th>
                                <th style="text-align: center; padding: 4px 0; font-size: 10px;">Qty</th>
                                <th style="text-align: right; padding: 4px 0; font-size: 10px;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsRows}
                        </tbody>
                    </table>
                    
                    <div style="border-bottom: 1px dashed #000; margin-top: 5px; margin-bottom: 8px;"></div>
                    
                    <div style="font-size: 11px;">
                        <div style="display: flex; justify-content: space-between; font-size: 11px;">
                            <span>Subtotal:</span>
                            <span>৳${parseFloat(currentOrder.subtotal).toFixed(2)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 11px; margin-top: 4px;">
                            <span>Delivery Charge:</span>
                            <span>৳${parseFloat(currentOrder.delivery_charge).toFixed(2)}</span>
                        </div>
                        ${discountBlock}
                        <div style="border-bottom: 1px dashed #000; margin-top: 6px; margin-bottom: 6px;"></div>
                        <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: bold;">
                            <span>GRAND TOTAL:</span>
                            <span>GRAND TOTAL:</span>
                            <span>৳${parseFloat(currentOrder.grand_total).toFixed(2)}</span>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 25px; font-size: 10px; border-top: 1px dashed #000; padding-top: 10px;">
                        <p style="margin: 0; font-weight: bold;">Thank You for Shopping!</p>
                        <p style="margin: 2px 0;">www.yukimartbd.com</p>
                    </div>
                </div>
            `;

            document.getElementById('printInvoiceContainer').innerHTML = printHtml;
            window.print();
        }

        // Reopen details modal automatically if coming from a redirection that performed an update
        window.onload = function() {
            <?php if (isset($_GET['view_order_id'])): ?>
                const viewOrderId = <?= (int)$_GET['view_order_id'] ?>;
                const btn = document.querySelector(`[data-order-btn-id="${viewOrderId}"]`);
                if (btn) {
                    btn.click();
                }
            <?php endif; ?>
        };
    </script>
</body>
</html>
