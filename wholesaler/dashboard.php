<?php
session_start();
if (!isset($_SESSION['wholesaler_role']) || $_SESSION['wholesaler_role'] !== 'wholesaler') {
    header("Location: login.php");
    exit;
}

require_once '../db.php';
$pdo = getDB();
$wholesaler_id = $_SESSION['wholesaler_id'];

// Get today's stats
$stmt = $pdo->prepare("
    SELECT 
        SUM(oi.quantity) as total_items, 
        SUM(oi.quantity * oi.wholesale_price) as total_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE p.wholesaler_id = ? AND DATE(o.created_at) = CURDATE() AND o.status = 'completed'
");
$stmt->execute([$wholesaler_id]);
$today_stats = $stmt->fetch();
$total_revenue = $today_stats['total_revenue'] ?? 0;
$total_items = $today_stats['total_items'] ?? 0;

// Get breakdown by slot
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(s.slot_time, 'Deleted Slot') AS slot_time, 
        p.name as product_name, 
        p.image_url, 
        SUM(oi.quantity) as sold_qty, 
        oi.wholesale_price, 
        SUM(oi.quantity * oi.wholesale_price) as slot_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN delivery_slots s ON o.slot_id = s.id
    WHERE p.wholesaler_id = ? AND DATE(o.created_at) = CURDATE() AND o.status = 'completed'
    GROUP BY o.slot_id, p.id, oi.wholesale_price
    ORDER BY s.id ASC
");
$stmt->execute([$wholesaler_id]);
$slot_breakdowns = $stmt->fetchAll();

// Group the breakdown by slot
$grouped_slots = [];
foreach ($slot_breakdowns as $b) {
    $grouped_slots[$b['slot_time']][] = $b;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wholesaler Dashboard - YukiMartBD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/js/tailwind.config.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased flex h-screen overflow-hidden selection:bg-brand-100 selection:text-purple-900">

    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col hidden md:flex z-20 shadow-sm">
        <div class="h-16 flex items-center px-6 border-b border-gray-100 shrink-0">
            <div class="bg-brand-500 text-white w-8 h-8 flex items-center justify-center rounded mr-3 shadow-sm">
                W
            </div>
            <h1 class="text-lg font-extrabold tracking-tight text-gray-900">Yuki<span class="text-brand-500">Wholesale</span></h1>
        </div>
        <nav class="flex-1 p-4 space-y-1.5 overflow-y-auto">
            <h4 class="text-[10px] font-extrabold text-gray-400 uppercase tracking-wider mb-2 mt-2 px-2">Menu</h4>
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 bg-brand-50 text-brand-700 font-bold rounded text-sm transition-all shadow-sm border border-brand-100">
                <i class="fa-solid fa-chart-pie w-5 text-center"></i> Dashboard
            </a>
            <a href="products.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-tags w-5 text-center"></i> My Products
            </a>
        </nav>
        <div class="p-4 border-t border-gray-100">
            <a href="logout.php" class="flex items-center gap-3 px-3 py-2.5 text-red-600 hover:bg-red-50 font-bold rounded text-sm transition-all">
                <i class="fa-solid fa-right-from-bracket w-5 text-center"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-y-auto relative">
        <header class="h-16 bg-white border-b border-gray-100 flex items-center px-6 justify-between sticky top-0 z-10 shadow-sm shrink-0">
            <div class="md:hidden flex items-center gap-3">
                <div class="bg-brand-500 text-white w-8 h-8 flex items-center justify-center rounded shadow-sm">
                    W
                </div>
                <h1 class="text-lg font-extrabold tracking-tight text-gray-900">Yuki<span class="text-brand-500">Wholesale</span></h1>
            </div>
            <h2 class="hidden md:block text-lg font-extrabold text-gray-900 tracking-tight">Today's Summary</h2>
            <div class="text-sm font-bold text-gray-600 bg-gray-100 px-3 py-1 rounded">
                <i class="fa-regular fa-calendar mr-1"></i> <?= date('M d, Y') ?>
            </div>
        </header>

        <div class="p-6 max-w-5xl mx-auto w-full">
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                <div class="bg-white p-6 rounded border border-gray-150 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-brand-50 flex items-center justify-center text-brand-600 text-xl border border-brand-100">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <div>
                        <p class="text-xs font-extrabold text-gray-400 uppercase tracking-wider mb-0.5">Today's Revenue</p>
                        <h3 class="text-2xl font-black text-gray-900 leading-none">৳<?= number_format($total_revenue, 2) ?></h3>
                    </div>
                </div>
                <div class="bg-white p-6 rounded border border-gray-150 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 text-xl border border-blue-100">
                        <i class="fa-solid fa-box"></i>
                    </div>
                    <div>
                        <p class="text-xs font-extrabold text-gray-400 uppercase tracking-wider mb-0.5">Items Sold Today</p>
                        <h3 class="text-2xl font-black text-gray-900 leading-none"><?= $total_items ?></h3>
                    </div>
                </div>
            </div>

            <!-- Breakdown by Slot -->
            <h3 class="text-sm font-extrabold text-gray-900 tracking-tight mb-4 uppercase">Sales Breakdown by Slot</h3>
            
            <?php if (empty($grouped_slots)): ?>
                <div class="text-center bg-white p-12 rounded border border-gray-150 shadow-sm">
                    <div class="bg-gray-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fa-solid fa-face-frown-open text-2xl text-gray-400"></i>
                    </div>
                    <h3 class="font-bold text-gray-800">No Sales Yet</h3>
                    <p class="text-sm text-gray-500 mt-1">Check back later after deliveries are completed.</p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($grouped_slots as $slot_time => $items): ?>
                        <div class="bg-white rounded border border-gray-150 shadow-sm overflow-hidden">
                            <div class="bg-gray-50 border-b border-gray-100 px-4 py-3 flex justify-between items-center">
                                <h4 class="font-bold text-gray-800 text-sm"><i class="fa-regular fa-clock text-brand-500 mr-2"></i><?= htmlspecialchars($slot_time) ?></h4>
                            </div>
                            <div class="p-0">
                                <table class="w-full text-left whitespace-nowrap">
                                    <thead class="bg-white border-b border-gray-50 hidden sm:table-header-group">
                                        <tr>
                                            <th class="p-3 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Product</th>
                                            <th class="p-3 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider text-center">Qty Sold</th>
                                            <th class="p-3 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider text-right">Wholesale Price</th>
                                            <th class="p-3 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider text-right">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <?php 
                                        $slot_total = 0;
                                        foreach ($items as $item): 
                                            $slot_total += $item['slot_revenue'];
                                        ?>
                                        <tr class="hover:bg-gray-50/50 transition-colors">
                                            <td class="p-3">
                                                <div class="flex items-center gap-3">
                                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" class="w-8 h-8 rounded object-cover bg-gray-100" onerror="this.src='https://via.placeholder.com/32'">
                                                    <span class="font-bold text-xs text-gray-800"><?= htmlspecialchars($item['product_name']) ?></span>
                                                </div>
                                            </td>
                                            <td class="p-3 text-center">
                                                <span class="font-black text-gray-700 text-sm"><?= $item['sold_qty'] ?></span>
                                            </td>
                                            <td class="p-3 text-right">
                                                <span class="font-semibold text-gray-600 text-xs">৳<?= $item['wholesale_price'] ?></span>
                                            </td>
                                            <td class="p-3 text-right">
                                                <span class="font-black text-brand-600 text-sm">৳<?= number_format($item['slot_revenue'], 2) ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <tr class="bg-gray-50/50">
                                            <td colspan="3" class="p-3 text-right font-bold text-xs text-gray-500 uppercase">Slot Total:</td>
                                            <td class="p-3 text-right font-black text-brand-700">৳<?= number_format($slot_total, 2) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>
</body>
</html>
