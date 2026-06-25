<?php
session_start();
if (!isset($_SESSION['dsr_role']) && !isset($_SESSION['admin_role'])) {
    header("Location: login.php");
    exit;
}

require_once '../db.php';
$pdo = getDB();

// Fetch slots for dropdown
$slots = $pdo->query("SELECT id, slot_time FROM delivery_slots WHERE is_active = 1")->fetchAll();

$selected_slot = $_GET['slot_id'] ?? ($slots[0]['id'] ?? null);
$products_to_collect = [];

if ($selected_slot) {
    // Aggregate items needed for the selected slot for pending/processing orders
    $stmt = $pdo->prepare("
        SELECT p.id as product_id, p.name, p.image_url, SUM(oi.quantity) as total_qty
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE o.slot_id = ? AND o.status IN ('pending', 'processing') AND oi.is_collected = 0
        GROUP BY p.id, p.name, p.image_url
    ");
    $stmt->execute([$selected_slot]);
    $products_to_collect = $stmt->fetchAll();
}

// Handle complete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_collection') {
    $slot_id = $_POST['slot_id'];
    $collected_product_ids = $_POST['collected_products'] ?? [];

    if (!empty($collected_product_ids)) {
        try {
            $pdo->beginTransaction();
            
            // Mark items as collected
            $placeholders = str_repeat('?,', count($collected_product_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                UPDATE order_items oi
                JOIN orders o ON oi.order_id = o.id
                SET oi.is_collected = 1
                WHERE o.slot_id = ? AND oi.product_id IN ($placeholders)
            ");
            $params = array_merge([$slot_id], $collected_product_ids);
            $stmt->execute($params);

            // Update order status if all items are collected
            $pdo->exec("
                UPDATE orders o
                SET o.status = 'collected'
                WHERE o.slot_id = " . (int)$slot_id . " 
                AND o.status IN ('pending', 'processing')
                AND NOT EXISTS (
                    SELECT 1 FROM order_items oi WHERE oi.order_id = o.id AND oi.is_collected = 0
                )
            ");

            $pdo->commit();
            header("Location: collection.php?slot_id=$slot_id&success=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to update collection.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DSR Collection - YukiMartBD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/js/tailwind.config.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .modern-input { width: 100%; padding: 0.5rem 1rem; border-radius: 0.25rem; border: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.875rem; outline: none; transition: all 0.2s; }
        .modern-input:focus { border-color: #10b981; box-shadow: 0 0 0 1px #10b981; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased pb-20 selection:bg-brand-100 selection:text-brand-900">

    <!-- Header -->
    <header class="sticky top-0 z-30 bg-brand-600 text-white shadow-sm">
        <div class="px-4 h-16 flex items-center justify-between max-w-lg mx-auto">
            <h1 class="text-lg font-extrabold tracking-tight">Item Collection</h1>
            <a href="delivery.php" class="text-[10px] font-extrabold bg-brand-700/50 hover:bg-brand-800 px-3 py-1.5 rounded uppercase tracking-wider transition-all">
                Delivery <i class="fa-solid fa-arrow-right ml-1"></i>
            </a>
        </div>
    </header>

    <main class="p-4 max-w-lg mx-auto mt-2">
        <?php if(isset($_GET['success'])): ?>
            <div class="bg-brand-50 text-brand-700 p-3 rounded mb-4 text-xs font-bold flex items-center gap-2 border border-brand-200 shadow-sm">
                <i class="fa-solid fa-check-circle"></i> Collection updated successfully!
            </div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="bg-red-50 text-red-700 p-3 rounded mb-4 text-xs font-bold flex items-center gap-2 border border-red-200 shadow-sm">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
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

        <?php if (empty($products_to_collect)): ?>
            <div class="text-center bg-white p-8 rounded border border-gray-150 shadow-sm mt-6">
                <div class="bg-gray-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fa-solid fa-box-open text-2xl text-gray-400"></i>
                </div>
                <h3 class="font-bold text-gray-800">All Caught Up!</h3>
                <p class="text-xs text-gray-500 mt-1">No pending items to collect for this slot.</p>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="complete_collection">
                <input type="hidden" name="slot_id" value="<?= htmlspecialchars($selected_slot) ?>">

                <div class="flex justify-between items-center mb-3 px-1">
                    <h2 class="text-sm font-extrabold text-gray-900 tracking-tight uppercase">Items to Collect</h2>
                    <span class="bg-brand-50 border border-brand-200 text-brand-700 text-[10px] font-bold px-2 py-0.5 rounded-sm"><?= count($products_to_collect) ?> types</span>
                </div>

                <div class="space-y-3 mb-8">
                    <?php foreach($products_to_collect as $p): ?>
                    <label class="flex items-center justify-between bg-white p-3 rounded border border-gray-150 shadow-sm cursor-pointer hover:border-brand-300 transition-all group">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" name="collected_products[]" value="<?= $p['product_id'] ?>" class="w-5 h-5 text-brand-600 rounded border-gray-300 focus:ring-brand-500 cursor-pointer">
                            <img src="<?= htmlspecialchars($p['image_url']) ?>" class="w-12 h-12 rounded object-cover bg-gray-50 border border-gray-100" onerror="this.src='https://via.placeholder.com/48'">
                            <div>
                                <p class="text-xs font-bold text-gray-800 leading-tight group-hover:text-brand-600 transition-colors"><?= htmlspecialchars($p['name']) ?></p>
                                <p class="text-[10px] text-gray-400 mt-0.5 uppercase font-bold tracking-wider">Need: <span class="text-brand-600 text-sm"><?= $p['total_qty'] ?></span></p>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>

                <!-- Fixed bottom button for DSR -->
                <div class="fixed bottom-0 inset-x-0 z-40 px-4 pb-4 bg-gradient-to-t from-gray-50 via-gray-50 to-transparent pt-6">
                    <div class="max-w-lg mx-auto">
                        <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-extrabold text-sm py-3.5 px-4 rounded shadow-lg transition-all flex justify-center items-center gap-2">
                            <i class="fa-solid fa-check-double text-xs"></i> Mark Selected as Collected
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
