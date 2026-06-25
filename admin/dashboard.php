<?php
session_start();
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once '../db.php';
$pdo = getDB();

// ─── Stat Cards ─────────────────────────────────────────────────────────────
$stats = [
    'total_orders'    => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'pending_orders'  => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
    'total_revenue'   => $pdo->query("SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE status = 'completed'")->fetchColumn() ?: 0,
    'total_users'     => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_products'  => $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn(),
    'completed_orders'=> $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn(),
];

// ─── Chart 1: Daily Revenue + Orders (last 30 days) ─────────────────────────
$revenueRows = $pdo->query("
    SELECT DATE(created_at) as day,
           COALESCE(SUM(grand_total),0) as revenue,
           COUNT(*) as order_count,
           COALESCE(AVG(grand_total),0) as avg_value
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
")->fetchAll();

$allDays = [];
for ($i = 29; $i >= 0; $i--) {
    $allDays[date('Y-m-d', strtotime("-{$i} days"))] = ['revenue' => 0, 'order_count' => 0, 'avg_value' => 0];
}
foreach ($revenueRows as $r) {
    $allDays[$r['day']] = ['revenue' => (float)$r['revenue'], 'order_count' => (int)$r['order_count'], 'avg_value' => (float)$r['avg_value']];
}
$chartDays        = array_map(fn($d) => date('d M', strtotime($d)), array_keys($allDays));
$chartRevenue     = array_column(array_values($allDays), 'revenue');
$chartOrderCounts = array_column(array_values($allDays), 'order_count');
$chartAOV         = array_column(array_values($allDays), 'avg_value');

// ─── Chart 2: Order Status Distribution ─────────────────────────────────────
$statusRows = $pdo->query("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status")->fetchAll();
$statusLabels = array_column($statusRows, 'status');
$statusCounts = array_map('intval', array_column($statusRows, 'cnt'));

// ─── Chart 3: Top 5 Best-Selling Products ────────────────────────────────────
$topProducts = $pdo->query("
    SELECT p.name, SUM(oi.quantity) as total_qty, SUM(oi.subtotal) as total_rev
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    GROUP BY oi.product_id, p.name
    ORDER BY total_qty DESC
    LIMIT 5
")->fetchAll();
$topProdNames  = array_column($topProducts, 'name');
$topProdQty    = array_map('intval', array_column($topProducts, 'total_qty'));
$topProdRev    = array_map('floatval', array_column($topProducts, 'total_rev'));

// ─── Chart 4: Revenue by Delivery Area ───────────────────────────────────────
$areaRows = $pdo->query("
    SELECT da.name, COALESCE(SUM(o.grand_total),0) as revenue, COUNT(o.id) as cnt
    FROM delivery_areas da
    LEFT JOIN orders o ON o.area_id = da.id
    GROUP BY da.id, da.name
    ORDER BY revenue DESC
")->fetchAll();
$areaNames   = array_column($areaRows, 'name');
$areaRevenue = array_map('floatval', array_column($areaRows, 'revenue'));
$areaCount   = array_map('intval', array_column($areaRows, 'cnt'));

// ─── Chart 5: DSR Performance ────────────────────────────────────────────────
$dsrRows = $pdo->query("
    SELECT u.name, COUNT(o.id) as order_count, COALESCE(SUM(o.grand_total),0) as revenue
    FROM users u
    LEFT JOIN orders o ON o.dsr_id = u.id
    WHERE u.role = 'dsr'
    GROUP BY u.id, u.name
    ORDER BY revenue DESC
    LIMIT 8
")->fetchAll();
$dsrNames   = array_column($dsrRows, 'name');
$dsrRevenue = array_map('floatval', array_column($dsrRows, 'revenue'));
$dsrOrders  = array_map('intval', array_column($dsrRows, 'order_count'));

// ─── Chart 6: User Role Breakdown ────────────────────────────────────────────
$roleRows = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll();
$roleLabels = array_column($roleRows, 'role');
$roleCounts = array_map('intval', array_column($roleRows, 'cnt'));

// ─── Chart 7: Coupon Usage Analytics ─────────────────────────────────────────
$couponRows = $pdo->query("
    SELECT coupon_code, COUNT(*) as used_count, COALESCE(SUM(discount),0) as total_discount
    FROM orders
    WHERE coupon_code IS NOT NULL AND coupon_code != ''
    GROUP BY coupon_code
    ORDER BY used_count DESC
    LIMIT 6
")->fetchAll();
$couponCodes   = array_column($couponRows, 'coupon_code');
$couponUsed    = array_map('intval', array_column($couponRows, 'used_count'));
$couponDisc    = array_map('floatval', array_column($couponRows, 'total_discount'));

// ─── Chart 8: Delivery Slot Distribution ─────────────────────────────────────
$slotRows = $pdo->query("
    SELECT ds.slot_time, COUNT(o.id) as cnt
    FROM delivery_slots ds
    LEFT JOIN orders o ON o.slot_id = ds.id
    GROUP BY ds.id, ds.slot_time
    ORDER BY cnt DESC
")->fetchAll();
$slotLabels = array_column($slotRows, 'slot_time');
// Shorten labels
$slotLabels = array_map(fn($s) => preg_replace('/\s*\(.*\)/', '', $s), $slotLabels);
$slotCounts = array_map('intval', array_column($slotRows, 'cnt'));

// ─── Chart 9: Profit vs Cost (last 4 weeks) ──────────────────────────────────
$profitRows = $pdo->query("
    SELECT 
        CONCAT('Week ', WEEK(o.created_at) - WEEK(DATE_SUB(CURDATE(), INTERVAL 27 DAY)) + 1) as week_label,
        COALESCE(SUM(oi.subtotal),0) as revenue,
        COALESCE(SUM(oi.wholesale_price * oi.quantity),0) as cost
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL 27 DAY)
    GROUP BY WEEK(o.created_at)
    ORDER BY WEEK(o.created_at) ASC
")->fetchAll();
$profitWeeks   = array_column($profitRows, 'week_label');
$profitRevenue = array_map('floatval', array_column($profitRows, 'revenue'));
$profitCost    = array_map('floatval', array_column($profitRows, 'cost'));
$profitMargin  = array_map(fn($r, $c) => round($r > 0 ? (($r - $c) / $r) * 100 : 0, 1), $profitRevenue, $profitCost);

// ─── Chart 10: Avg Order Value Trend (last 14 days) ──────────────────────────
$aovDays = array_slice(array_keys($allDays), -14);
$aovVals = array_map(fn($d) => round($allDays[$d]['avg_value'], 2), $aovDays);
$aovLabels = array_map(fn($d) => date('d M', strtotime($d)), $aovDays);

// Today's summary
$todayRevenue   = $pdo->query("SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
$todayOrders    = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
$cancelledCount = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - YukiMartBD</title>
    <meta name="description" content="YukiMartBD Admin Dashboard — real-time sales analytics, order management, and business insights.">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/js/tailwind.config.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .chart-card {
            background: white;
            border-radius: 0.5rem;
            border: 1px solid #f0f0f0;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
            padding: 1.25rem;
            transition: box-shadow .2s, transform .2s;
            position: relative;
            overflow: hidden;
        }
        .chart-card:hover { box-shadow: 0 6px 24px rgba(16,185,129,.1); transform: translateY(-2px); }
        .chart-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        .chart-card.green::before  { background: linear-gradient(90deg, #10b981, #34d399); }
        .chart-card.blue::before   { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
        .chart-card.purple::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
        .chart-card.orange::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .chart-card.red::before    { background: linear-gradient(90deg, #ef4444, #f87171); }
        .chart-card.pink::before   { background: linear-gradient(90deg, #ec4899, #f472b6); }
        .chart-card.teal::before   { background: linear-gradient(90deg, #14b8a6, #2dd4bf); }
        .chart-card.indigo::before { background: linear-gradient(90deg, #6366f1, #818cf8); }
        .chart-title {
            font-size: 0.72rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: .06em;
            color: #6b7280; margin-bottom: 0.75rem;
        }
        .stat-card {
            background: white;
            border-radius: 0.5rem;
            border: 1px solid #f0f0f0;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
            padding: 1.1rem 1.25rem;
            display: flex; align-items: center; gap: 1rem;
            transition: box-shadow .2s, transform .2s;
        }
        .stat-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.08); transform: translateY(-2px); }
        .stat-icon {
            width: 46px; height: 46px; border-radius: 0.5rem;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }
        .stat-badge {
            display: inline-flex; align-items: center; gap: 3px;
            font-size: 10px; font-weight: 700; padding: 2px 7px;
            border-radius: 9999px; margin-top: 3px;
        }
        .pulse { animation: pulse 2.5s cubic-bezier(0.4,0,0.6,1) infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
        .seed-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white; border: none; cursor: pointer;
            padding: 0.45rem 1.1rem; border-radius: 0.375rem;
            font-size: 0.75rem; font-weight: 700;
            display: inline-flex; align-items: center; gap: 6px;
            transition: opacity .2s, transform .2s;
        }
        .seed-btn:hover { opacity: 0.9; transform: scale(1.03); }
        .seed-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        #seed-result { font-size: 0.75rem; font-weight: 600; }
        canvas { display: block; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased flex h-screen overflow-hidden selection:bg-brand-100 selection:text-brand-900">

    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col hidden md:flex z-20 shadow-sm">
        <div class="h-16 flex items-center px-6 border-b border-gray-100 shrink-0">
            <div class="flex items-center justify-center mr-3"><img src="../assets/images/logo.png" alt="Logo" class="w-8 h-8 object-contain rounded"></div>
            <h1 class="text-lg font-extrabold tracking-tight text-gray-900">Yuki<span class="text-brand-500">Admin</span></h1>
        </div>
        <nav class="flex-1 p-4 space-y-1.5 overflow-y-auto">
            <h4 class="text-[10px] font-extrabold text-gray-400 uppercase tracking-wider mb-2 mt-2 px-2">Menu</h4>
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 bg-brand-50 text-brand-600 font-bold rounded text-sm transition-all shadow-sm border border-brand-100">
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
        <div class="p-4 border-t border-gray-100 shrink-0">
            <a href="logout.php" class="flex items-center gap-3 px-3 py-2.5 text-red-500 hover:bg-red-50 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-sign-out-alt w-5 text-center"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-y-auto relative">
        <!-- Top Header -->
        <header class="h-16 bg-white border-b border-gray-100 flex items-center px-6 justify-between sticky top-0 z-10 shadow-sm shrink-0">
            <div class="md:hidden flex items-center gap-3">
                <div class="flex items-center justify-center"><img src="../assets/images/logo.png" alt="Logo" class="w-8 h-8 object-contain rounded shadow-sm"></div>
                <h1 class="text-lg font-extrabold tracking-tight text-gray-900">Yuki<span class="text-brand-500">Admin</span></h1>
            </div>
            <h2 class="hidden md:block text-lg font-extrabold text-gray-900 tracking-tight">Overview Dashboard</h2>
            <div class="flex items-center gap-3">
                <!-- Seed Data Button -->
                <button id="seed-btn" class="seed-btn" onclick="seedData()">
                    <i class="fa-solid fa-database"></i> Seed Demo Data
                </button>
                <span id="seed-result" class="hidden text-brand-600"></span>
                <span class="text-xs font-bold text-gray-500 bg-gray-100 px-3 py-1.5 rounded-full">
                    <i class="fa-solid fa-circle text-brand-500 text-[8px] mr-1 pulse"></i> Admin Online
                </span>
            </div>
        </header>

        <div class="p-5 max-w-screen-2xl mx-auto w-full">

            <!-- ── Stat Cards Row ─────────────────────────────────────────── -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
                <!-- Total Orders -->
                <div class="stat-card col-span-1">
                    <div class="stat-icon bg-blue-50 text-blue-500">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-extrabold uppercase tracking-wider">Total Orders</p>
                        <p class="text-xl font-black text-gray-900 leading-tight"><?= number_format($stats['total_orders']) ?></p>
                    </div>
                </div>
                <!-- Pending -->
                <div class="stat-card col-span-1">
                    <div class="stat-icon bg-orange-50 text-orange-500">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-extrabold uppercase tracking-wider">Pending</p>
                        <p class="text-xl font-black text-gray-900 leading-tight"><?= number_format($stats['pending_orders']) ?></p>
                    </div>
                </div>
                <!-- Revenue -->
                <div class="stat-card col-span-1">
                    <div class="stat-icon bg-brand-50 text-brand-500">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-extrabold uppercase tracking-wider">Revenue</p>
                        <p class="text-xl font-black text-gray-900 leading-tight">৳<?= number_format($stats['total_revenue'], 0) ?></p>
                    </div>
                </div>
                <!-- Users -->
                <div class="stat-card col-span-1">
                    <div class="stat-icon bg-brand-50 text-brand-500">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-extrabold uppercase tracking-wider">Users</p>
                        <p class="text-xl font-black text-gray-900 leading-tight"><?= number_format($stats['total_users']) ?></p>
                    </div>
                </div>
                <!-- Today's Revenue -->
                <div class="stat-card col-span-1">
                    <div class="stat-icon bg-teal-50 text-teal-500">
                        <i class="fa-solid fa-sun"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-extrabold uppercase tracking-wider">Today Rev.</p>
                        <p class="text-xl font-black text-gray-900 leading-tight">৳<?= number_format($todayRevenue, 0) ?></p>
                    </div>
                </div>
                <!-- Products -->
                <div class="stat-card col-span-1">
                    <div class="stat-icon bg-pink-50 text-pink-500">
                        <i class="fa-solid fa-box"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 font-extrabold uppercase tracking-wider">Products</p>
                        <p class="text-xl font-black text-gray-900 leading-tight"><?= number_format($stats['total_products']) ?></p>
                    </div>
                </div>
            </div>

            <!-- ── Row 1: Revenue Trend (large) + Order Status ───────────── -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

                <!-- Chart 1: Revenue + Orders Line Chart -->
                <div class="chart-card green lg:col-span-2">
                    <p class="chart-title"><i class="fa-solid fa-chart-line mr-1"></i> Sales & Revenue Trend — Last 30 Days</p>
                    <div style="height:240px; position:relative;">
                        <canvas id="chart1"></canvas>
                    </div>
                </div>

                <!-- Chart 2: Order Status Doughnut -->
                <div class="chart-card blue">
                    <p class="chart-title"><i class="fa-solid fa-circle-half-stroke mr-1"></i> Order Status Distribution</p>
                    <div style="height:240px; position:relative;">
                        <canvas id="chart2"></canvas>
                    </div>
                </div>
            </div>

            <!-- ── Row 2: Best Sellers + Area Revenue ───────────────────── -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

                <!-- Chart 3: Top Products Horizontal Bar -->
                <div class="chart-card orange">
                    <p class="chart-title"><i class="fa-solid fa-trophy mr-1"></i> Top 5 Best-Selling Products</p>
                    <div style="height:230px; position:relative;">
                        <canvas id="chart3"></canvas>
                    </div>
                </div>

                <!-- Chart 4: Revenue by Area Pie -->
                <div class="chart-card purple">
                    <p class="chart-title"><i class="fa-solid fa-map-pin mr-1"></i> Revenue by Delivery Area</p>
                    <div style="height:230px; position:relative;">
                        <canvas id="chart4"></canvas>
                    </div>
                </div>
            </div>

            <!-- ── Row 3: DSR + User Roles + Coupon ─────────────────────── -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

                <!-- Chart 5: DSR Performance Bar -->
                <div class="chart-card teal lg:col-span-1">
                    <p class="chart-title"><i class="fa-solid fa-person-biking mr-1"></i> DSR Performance</p>
                    <div style="height:230px; position:relative;">
                        <canvas id="chart5"></canvas>
                    </div>
                </div>

                <!-- Chart 6: User Role Doughnut -->
                <div class="chart-card indigo lg:col-span-1">
                    <p class="chart-title"><i class="fa-solid fa-user-tag mr-1"></i> User Role Breakdown</p>
                    <div style="height:230px; position:relative;">
                        <canvas id="chart6"></canvas>
                    </div>
                </div>

                <!-- Chart 7: Coupon Analytics -->
                <div class="chart-card pink lg:col-span-1">
                    <p class="chart-title"><i class="fa-solid fa-ticket mr-1"></i> Coupon Usage Analytics</p>
                    <div style="height:230px; position:relative;">
                        <canvas id="chart7"></canvas>
                    </div>
                </div>
            </div>

            <!-- ── Row 4: Slot Distribution + Profit vs Cost + AOV ──────── -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

                <!-- Chart 8: Delivery Slot Polar Area -->
                <div class="chart-card red lg:col-span-1">
                    <p class="chart-title"><i class="fa-solid fa-clock mr-1"></i> Delivery Slot Distribution</p>
                    <div style="height:230px; position:relative;">
                        <canvas id="chart8"></canvas>
                    </div>
                </div>

                <!-- Chart 9: Profit vs Cost Grouped Bar -->
                <div class="chart-card green lg:col-span-1">
                    <p class="chart-title"><i class="fa-solid fa-scale-balanced mr-1"></i> Revenue vs Wholesale Cost (Weekly)</p>
                    <div style="height:230px; position:relative;">
                        <canvas id="chart9"></canvas>
                    </div>
                </div>

                <!-- Chart 10: AOV Trend Area Chart -->
                <div class="chart-card blue lg:col-span-1">
                    <p class="chart-title"><i class="fa-solid fa-arrow-trend-up mr-1"></i> Avg Order Value — Last 14 Days</p>
                    <div style="height:230px; position:relative;">
                        <canvas id="chart10"></canvas>
                    </div>
                </div>
            </div>

            <!-- ── Quick Actions ─────────────────────────────────────────── -->
            <div class="bg-white rounded-lg border border-gray-100 shadow-sm p-5">
                <h3 class="text-xs font-extrabold text-gray-900 tracking-tight mb-3 uppercase">Quick Actions</h3>
                <div class="flex flex-wrap gap-3">
                    <a href="products.php" class="bg-brand-50 border border-brand-200 text-brand-700 px-5 py-2.5 rounded text-sm font-bold hover:bg-brand-500 hover:text-white transition-all shadow-sm flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i> Add Product
                    </a>
                    <a href="users.php" class="bg-blue-50 border border-blue-200 text-blue-700 px-5 py-2.5 rounded text-sm font-bold hover:bg-blue-500 hover:text-white transition-all shadow-sm flex items-center gap-2">
                        <i class="fa-solid fa-user-plus"></i> Add User
                    </a>
                    <a href="orders.php" class="bg-orange-50 border border-orange-200 text-orange-700 px-5 py-2.5 rounded text-sm font-bold hover:bg-orange-500 hover:text-white transition-all shadow-sm flex items-center gap-2">
                        <i class="fa-solid fa-list-check"></i> View Orders
                    </a>
                    <a href="areas.php" class="bg-brand-50 border border-purple-200 text-brand-700 px-5 py-2.5 rounded text-sm font-bold hover:bg-brand-500 hover:text-white transition-all shadow-sm flex items-center gap-2">
                        <i class="fa-solid fa-map-plus"></i> Add Area
                    </a>
                </div>
            </div>

        </div><!-- /p-5 -->
    </main>

</body>
</html>

<script>
// ── PHP → JS Data ──────────────────────────────────────────────────────────
const DATA = {
    days:         <?= json_encode($chartDays) ?>,
    revenue:      <?= json_encode($chartRevenue) ?>,
    orderCounts:  <?= json_encode($chartOrderCounts) ?>,
    aov:          <?= json_encode($chartAOV) ?>,

    statusLabels: <?= json_encode(array_map('ucfirst', $statusLabels)) ?>,
    statusCounts: <?= json_encode($statusCounts) ?>,

    topProdNames: <?= json_encode($topProdNames) ?>,
    topProdQty:   <?= json_encode($topProdQty) ?>,
    topProdRev:   <?= json_encode($topProdRev) ?>,

    areaNames:    <?= json_encode($areaNames) ?>,
    areaRevenue:  <?= json_encode($areaRevenue) ?>,
    areaCount:    <?= json_encode($areaCount) ?>,

    dsrNames:     <?= json_encode($dsrNames) ?>,
    dsrRevenue:   <?= json_encode($dsrRevenue) ?>,
    dsrOrders:    <?= json_encode($dsrOrders) ?>,

    roleLabels:   <?= json_encode(array_map('ucfirst', $roleLabels)) ?>,
    roleCounts:   <?= json_encode($roleCounts) ?>,

    couponCodes:  <?= json_encode($couponCodes) ?>,
    couponUsed:   <?= json_encode($couponUsed) ?>,
    couponDisc:   <?= json_encode($couponDisc) ?>,

    slotLabels:   <?= json_encode($slotLabels) ?>,
    slotCounts:   <?= json_encode($slotCounts) ?>,

    profitWeeks:  <?= json_encode($profitWeeks) ?>,
    profitRev:    <?= json_encode($profitRevenue) ?>,
    profitCost:   <?= json_encode($profitCost) ?>,

    aovLabels:    <?= json_encode($aovLabels) ?>,
    aovVals:      <?= json_encode($aovVals) ?>,
};

// ── Common Chart Defaults ──────────────────────────────────────────────────
Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
Chart.defaults.font.size   = 11;
Chart.defaults.color       = '#9ca3af';
Chart.defaults.plugins.legend.labels.boxWidth = 10;
Chart.defaults.plugins.legend.labels.padding  = 12;

const TOOLTIP_STYLE = {
    backgroundColor: 'rgba(17,24,39,0.92)',
    titleColor: '#f9fafb', bodyColor: '#d1d5db',
    borderColor: 'rgba(255,255,255,0.1)', borderWidth: 1,
    padding: 10, cornerRadius: 6, titleFont: { weight: '700' },
};

const PALETTE = {
    green:  ['#10b981','#34d399','#6ee7b7','#a7f3d0','#d1fae5'],
    blue:   ['#3b82f6','#60a5fa','#93c5fd','#bfdbfe','#dbeafe'],
    purple: ['#8b5cf6','#a78bfa','#c4b5fd','#ddd6fe','#ede9fe'],
    orange: ['#f59e0b','#fbbf24','#fcd34d','#fde68a','#fef3c7'],
    red:    ['#ef4444','#f87171','#fca5a5','#fecaca','#fee2e2'],
    teal:   ['#14b8a6','#2dd4bf','#5eead4','#99f6e4','#ccfbf1'],
    pink:   ['#ec4899','#f472b6','#f9a8d4','#fbcfe8','#fce7f3'],
    mixed:  ['#10b981','#3b82f6','#f59e0b','#ef4444','#8b5cf6','#14b8a6','#ec4899','#f97316'],
};

function makeGradient(ctx, color1, color2 = 'transparent') {
    const g = ctx.createLinearGradient(0, 0, 0, ctx.canvas.offsetHeight || 220);
    g.addColorStop(0, color1);
    g.addColorStop(1, color2);
    return g;
}

// ── Chart 1: Revenue + Orders Line ────────────────────────────────────────
(function() {
    const ctx = document.getElementById('chart1').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: DATA.days,
            datasets: [
                {
                    label: 'Revenue (৳)',
                    data: DATA.revenue,
                    borderColor: '#10b981', borderWidth: 2.5,
                    backgroundColor: makeGradient(ctx, 'rgba(16,185,129,.18)', 'rgba(16,185,129,.01)'),
                    fill: true, tension: 0.4,
                    pointRadius: 0, pointHoverRadius: 5,
                    pointHoverBackgroundColor: '#10b981',
                    yAxisID: 'yRev',
                },
                {
                    label: 'Orders',
                    data: DATA.orderCounts,
                    borderColor: '#3b82f6', borderWidth: 2, borderDash: [5,4],
                    backgroundColor: 'transparent',
                    fill: false, tension: 0.4,
                    pointRadius: 0, pointHoverRadius: 5,
                    pointHoverBackgroundColor: '#3b82f6',
                    yAxisID: 'yOrd',
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top' }, tooltip: TOOLTIP_STYLE },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
                yRev: {
                    position: 'left', grid: { color: '#f3f4f6' },
                    ticks: { callback: v => '৳' + (v >= 1000 ? (v/1000).toFixed(1) + 'k' : v) }
                },
                yOrd: {
                    position: 'right', grid: { drawOnChartArea: false },
                    ticks: { stepSize: 1, callback: v => v + ' ord' }
                }
            }
        }
    });
})();

// ── Chart 2: Order Status Doughnut ────────────────────────────────────────
(function() {
    const statusColors = {
        'Pending':'#f59e0b','Processing':'#3b82f6','Collected':'#14b8a6',
        'Out_for_delivery':'#8b5cf6','Completed':'#10b981','Cancelled':'#ef4444','Due':'#f97316'
    };
    const colors = DATA.statusLabels.map(l => statusColors[l.charAt(0).toUpperCase()+l.slice(1)] || PALETTE.mixed[0]);
    new Chart(document.getElementById('chart2'), {
        type: 'doughnut',
        data: { labels: DATA.statusLabels, datasets: [{ data: DATA.statusCounts, backgroundColor: colors, borderWidth: 2, borderColor: '#fff', hoverOffset: 8 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: { position: 'right', labels: { font: { size: 10 } } },
                tooltip: { ...TOOLTIP_STYLE, callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw} orders` } }
            }
        }
    });
})();

// ── Chart 3: Top Products Horizontal Bar ──────────────────────────────────
(function() {
    const ctx = document.getElementById('chart3').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: DATA.topProdNames,
            datasets: [
                {
                    label: 'Units Sold',
                    data: DATA.topProdQty,
                    backgroundColor: PALETTE.orange.slice(0, DATA.topProdNames.length),
                    borderRadius: 5, barThickness: 18,
                    yAxisID: 'yQty',
                },
                {
                    label: 'Revenue (৳)',
                    data: DATA.topProdRev,
                    backgroundColor: PALETTE.teal.slice(0, DATA.topProdNames.length).map(c => c + 'bb'),
                    borderRadius: 5, barThickness: 18,
                    yAxisID: 'yRev',
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' }, tooltip: TOOLTIP_STYLE },
            scales: {
                x: { display: false },
                yQty: { position: 'left', grid: { color: '#f3f4f6' } },
                yRev: { position: 'right', display: false },
                y: { grid: { display: false } }
            }
        }
    });
})();

// ── Chart 4: Area Revenue Pie ──────────────────────────────────────────────
(function() {
    new Chart(document.getElementById('chart4'), {
        type: 'pie',
        data: {
            labels: DATA.areaNames,
            datasets: [{ data: DATA.areaRevenue, backgroundColor: PALETTE.mixed, borderWidth: 2, borderColor: '#fff', hoverOffset: 10 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { font: { size: 10 } } },
                tooltip: { ...TOOLTIP_STYLE, callbacks: { label: ctx => ` ${ctx.label}: ৳${Number(ctx.raw).toLocaleString()}` } }
            }
        }
    });
})();

// ── Chart 5: DSR Performance Bar ──────────────────────────────────────────
(function() {
    new Chart(document.getElementById('chart5'), {
        type: 'bar',
        data: {
            labels: DATA.dsrNames,
            datasets: [
                {
                    label: 'Revenue (৳)',
                    data: DATA.dsrRevenue,
                    backgroundColor: PALETTE.teal.map(c => c + 'cc'),
                    borderRadius: 6, barThickness: 14,
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: TOOLTIP_STYLE },
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 30, font: { size: 9 } } },
                y: { grid: { color: '#f3f4f6' }, ticks: { callback: v => '৳' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v) } }
            }
        }
    });
})();

// ── Chart 6: User Role Doughnut ───────────────────────────────────────────
(function() {
    const roleColors = { 'Admin':'#ef4444','Dsr':'#3b82f6','Customer':'#10b981','Wholesaler':'#8b5cf6' };
    const colors = DATA.roleLabels.map(l => roleColors[l] || '#6b7280');
    new Chart(document.getElementById('chart6'), {
        type: 'doughnut',
        data: { labels: DATA.roleLabels, datasets: [{ data: DATA.roleCounts, backgroundColor: colors, borderWidth: 2, borderColor: '#fff', hoverOffset: 8 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '58%',
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 10 } } },
                tooltip: { ...TOOLTIP_STYLE, callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw} users` } }
            }
        }
    });
})();

// ── Chart 7: Coupon Analytics Bar ─────────────────────────────────────────
(function() {
    new Chart(document.getElementById('chart7'), {
        type: 'bar',
        data: {
            labels: DATA.couponCodes.length ? DATA.couponCodes : ['No Coupons'],
            datasets: [
                {
                    label: 'Times Used',
                    data: DATA.couponUsed.length ? DATA.couponUsed : [0],
                    backgroundColor: '#ec4899cc',
                    borderRadius: 6, barThickness: 16,
                    yAxisID: 'yUsed',
                },
                {
                    label: 'Discount Given (৳)',
                    data: DATA.couponDisc.length ? DATA.couponDisc : [0],
                    backgroundColor: '#f9a8d4cc',
                    borderRadius: 6, barThickness: 16,
                    yAxisID: 'yDisc',
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' }, tooltip: TOOLTIP_STYLE },
            scales: {
                x: { grid: { display: false } },
                yUsed: { position: 'left', grid: { color: '#f3f4f6' }, ticks: { stepSize: 1 } },
                yDisc: { position: 'right', display: false }
            }
        }
    });
})();

// ── Chart 8: Delivery Slot Polar Area ─────────────────────────────────────
(function() {
    new Chart(document.getElementById('chart8'), {
        type: 'polarArea',
        data: {
            labels: DATA.slotLabels,
            datasets: [{ data: DATA.slotCounts, backgroundColor: PALETTE.mixed.map(c => c + 'cc'), borderColor: '#fff', borderWidth: 2 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 9 }, boxWidth: 8 } },
                tooltip: TOOLTIP_STYLE
            },
            scales: { r: { ticks: { display: false }, grid: { color: '#f3f4f6' } } }
        }
    });
})();

// ── Chart 9: Profit vs Cost Grouped Bar ───────────────────────────────────
(function() {
    new Chart(document.getElementById('chart9'), {
        type: 'bar',
        data: {
            labels: DATA.profitWeeks.length ? DATA.profitWeeks : ['W1','W2','W3','W4'],
            datasets: [
                {
                    label: 'Revenue (৳)',
                    data: DATA.profitRev,
                    backgroundColor: '#10b981cc',
                    borderRadius: 6,
                },
                {
                    label: 'Cost (৳)',
                    data: DATA.profitCost,
                    backgroundColor: '#ef4444bb',
                    borderRadius: 6,
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' }, tooltip: TOOLTIP_STYLE },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { color: '#f3f4f6' }, ticks: { callback: v => '৳' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v) } }
            }
        }
    });
})();

// ── Chart 10: AOV Area Chart ───────────────────────────────────────────────
(function() {
    const ctx = document.getElementById('chart10').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: DATA.aovLabels,
            datasets: [{
                label: 'Avg Order Value (৳)',
                data: DATA.aovVals,
                borderColor: '#3b82f6', borderWidth: 2.5,
                backgroundColor: makeGradient(ctx, 'rgba(59,130,246,.20)', 'rgba(59,130,246,.01)'),
                fill: true, tension: 0.45,
                pointRadius: 3, pointBackgroundColor: '#3b82f6',
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { ...TOOLTIP_STYLE, callbacks: { label: ctx => ` AOV: ৳${Number(ctx.raw).toFixed(0)}` } } },
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 0, maxTicksLimit: 7 } },
                y: { grid: { color: '#f3f4f6' }, ticks: { callback: v => '৳' + v } }
            }
        }
    });
})();

// ── Seed Data AJAX ────────────────────────────────────────────────────────
function seedData() {
    const btn = document.getElementById('seed-btn');
    const res = document.getElementById('seed-result');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Seeding…';
    res.className = 'text-xs font-semibold text-gray-500';
    res.textContent = '';
    res.classList.remove('hidden');

    fetch('seed_data.php', { method: 'GET', credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                res.className = 'text-xs font-semibold text-brand-600';
                res.textContent = '✓ ' + (data.log ? data.log[data.log.length - 1] : 'Done!') + ' Reloading…';
                setTimeout(() => location.reload(), 1500);
            } else {
                res.className = 'text-xs font-semibold text-red-500';
                res.textContent = '✗ ' + (data.message || 'Error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-database"></i> Seed Demo Data';
            }
        })
        .catch(() => {
            res.className = 'text-xs font-semibold text-red-500';
            res.textContent = '✗ Network error';
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-database"></i> Seed Demo Data';
        });
}
</script>
