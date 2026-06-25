<?php
session_start();
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once '../db.php';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_settings') {
        $amount = (float)$_POST['free_delivery_amount'];
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('free_delivery_amount', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$amount, $amount]);
        header("Location: settings.php?success=settings");
        exit;
    } elseif ($_POST['action'] === 'add_slot') {
        $name = trim($_POST['slot_name']);
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];
        
        $formatted_start = date("h:i A", strtotime($start));
        $formatted_end = date("h:i A", strtotime($end));
        $slot_time = $name ? "$name ($formatted_start - $formatted_end)" : "$formatted_start - $formatted_end";

        $stmt = $pdo->prepare("INSERT INTO delivery_slots (slot_name, start_time, end_time, slot_time, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$name ?: null, $start, $end, $slot_time]);
        header("Location: settings.php?success=slot");
        exit;
    } elseif ($_POST['action'] === 'toggle_slot') {
        $stmt = $pdo->prepare("UPDATE delivery_slots SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header("Location: settings.php?success=slot_status");
        exit;
    } elseif ($_POST['action'] === 'edit_slot') {
        $name = trim($_POST['slot_name']);
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];
        
        $formatted_start = date("h:i A", strtotime($start));
        $formatted_end = date("h:i A", strtotime($end));
        $slot_time = $name ? "$name ($formatted_start - $formatted_end)" : "$formatted_start - $formatted_end";

        $stmt = $pdo->prepare("UPDATE delivery_slots SET slot_name = ?, start_time = ?, end_time = ?, slot_time = ? WHERE id = ?");
        $stmt->execute([$name ?: null, $start, $end, $slot_time, $_POST['id']]);
        header("Location: settings.php?success=slot_edited");
        exit;
    } elseif ($_POST['action'] === 'delete_slot') {
        $stmt = $pdo->prepare("DELETE FROM delivery_slots WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header("Location: settings.php?success=slot_deleted");
        exit;
    } elseif ($_POST['action'] === 'add_coupon') {
        $code = strtoupper(trim($_POST['code']));
        $type = $_POST['discount_type'];
        $value = (float)$_POST['discount_value'];
        $min_amount = (float)$_POST['min_order_amount'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO coupons (code, discount_type, discount_value, min_order_amount) VALUES (?, ?, ?, ?)");
            $stmt->execute([$code, $type, $value, $min_amount]);
            header("Location: settings.php?success=coupon");
            exit;
        } catch (PDOException $e) {
            header("Location: settings.php?error=coupon_exists");
            exit;
        }
    } elseif ($_POST['action'] === 'toggle_coupon') {
        $stmt = $pdo->prepare("UPDATE coupons SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header("Location: settings.php?success=coupon_status");
        exit;
    } elseif ($_POST['action'] === 'delete_coupon') {
        $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header("Location: settings.php?success=coupon_deleted");
        exit;
    } elseif ($_POST['action'] === 'edit_coupon') {
        $code = strtoupper(trim($_POST['code']));
        $type = $_POST['discount_type'];
        $value = (float)$_POST['discount_value'];
        $min_amount = (float)$_POST['min_order_amount'];
        $id = (int)$_POST['id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE coupons SET code = ?, discount_type = ?, discount_value = ?, min_order_amount = ? WHERE id = ?");
            $stmt->execute([$code, $type, $value, $min_amount, $id]);
            header("Location: settings.php?success=coupon_edited");
            exit;
        } catch (PDOException $e) {
            header("Location: settings.php?error=coupon_exists");
            exit;
        }
    }
}

// Fetch Free Delivery Setting
$free_delivery_amount = 0;
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'free_delivery_amount'");
if ($row = $stmt->fetch()) {
    $free_delivery_amount = $row['setting_value'];
}

// Fetch Delivery Slots
$slots = $pdo->query("SELECT * FROM delivery_slots")->fetchAll();

// Fetch Coupons
$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - YukiMartBD Admin</title>
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
<body class="bg-gray-50 text-gray-800 antialiased flex h-screen overflow-hidden selection:bg-brand-100 selection:text-brand-900">

    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col hidden md:flex z-20 shadow-sm">
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
            <a href="settings.php" class="flex items-center gap-3 px-3 py-2.5 bg-brand-50 text-brand-600 font-bold rounded text-sm transition-all shadow-sm border border-brand-100">
                <i class="fa-solid fa-cog w-5 text-center"></i> Settings
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-y-auto relative">
        <header class="h-16 bg-white border-b border-gray-100 flex items-center px-6 justify-between sticky top-0 z-10 shadow-sm shrink-0">
            <div class="md:hidden flex items-center gap-3">
                <div class="flex items-center justify-center"><img src="../assets/images/logo.png" alt="Logo" class="w-8 h-8 object-contain rounded shadow-sm"></div>
                <h1 class="text-lg font-extrabold tracking-tight text-gray-900">Yuki<span class="text-brand-500">Admin</span></h1>
            </div>
            <h2 class="hidden md:block text-lg font-extrabold text-gray-900 tracking-tight">Settings & Management</h2>
        </header>

        <div class="p-6 max-w-6xl mx-auto w-full">
            <?php if(isset($_GET['success'])): ?>
                <div class="bg-brand-50 text-brand-700 p-4 rounded mb-6 font-semibold border border-brand-200 shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-check-circle"></i> Action completed successfully!
                </div>
            <?php endif; ?>
            <?php if(isset($_GET['error']) && $_GET['error'] === 'coupon_exists'): ?>
                <div class="bg-red-50 text-red-700 p-4 rounded mb-6 font-semibold border border-red-200 shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-exclamation-triangle"></i> Coupon code already exists!
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                
                <!-- General Settings (Free Delivery) -->
                <div class="bg-white p-6 rounded border border-gray-150 shadow-sm">
                    <h3 class="text-sm font-extrabold text-gray-900 tracking-tight mb-4 uppercase"><i class="fa-solid fa-truck text-brand-500 mr-2"></i> General Settings</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_settings">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Free Delivery Amount Reach (৳)</label>
                            <p class="text-xs text-gray-400 mb-2">Orders exceeding this subtotal will get free delivery.</p>
                            <input type="number" step="0.01" name="free_delivery_amount" value="<?= htmlspecialchars($free_delivery_amount) ?>" required class="modern-input">
                        </div>
                        <button type="submit" class="bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-extrabold text-sm px-5 py-2.5 rounded shadow transition-all">
                            Save Settings
                        </button>
                    </form>
                </div>

                <!-- Delivery Slots Management -->
                <div class="bg-white p-6 rounded border border-gray-150 shadow-sm">
                    <h3 class="text-sm font-extrabold text-gray-900 tracking-tight mb-4 uppercase"><i class="fa-solid fa-clock text-blue-500 mr-2"></i> Delivery Slots</h3>
                    <form method="POST" class="space-y-3 mb-6">
                        <input type="hidden" name="action" value="add_slot">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Slot Name (e.g. Morning Shift)</label>
                            <input type="text" name="slot_name" placeholder="Morning Delivery" class="modern-input">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Start Time <span class="text-red-500">*</span></label>
                                <input type="time" name="start_time" required class="modern-input">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">End Time <span class="text-red-500">*</span></label>
                                <input type="time" name="end_time" required class="modern-input">
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-sm py-2.5 rounded shadow transition-all">
                            Add Slot
                        </button>
                    </form>

                    <div class="border border-gray-100 rounded overflow-hidden">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-gray-50 border-b border-gray-100 text-xs text-gray-500 font-bold uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3">Slot Time</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach($slots as $slot): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 font-semibold text-gray-800"><?= htmlspecialchars($slot['slot_time']) ?></td>
                                    <td class="px-4 py-3 text-center">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="toggle_slot">
                                            <input type="hidden" name="id" value="<?= $slot['id'] ?>">
                                            <?php if($slot['is_active']): ?>
                                                <button type="submit" class="text-xs bg-brand-100 text-brand-700 font-bold px-2.5 py-1 rounded hover:bg-brand-200 transition-colors">Active</button>
                                            <?php else: ?>
                                                <button type="submit" class="text-xs bg-gray-100 text-gray-500 font-bold px-2.5 py-1 rounded hover:bg-gray-200 transition-colors">Inactive</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-1 flex items-center justify-end">
                                        <button type="button" 
                                                onclick="openEditSlotModal(<?= $slot['id'] ?>, '<?= htmlspecialchars($slot['slot_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($slot['start_time'] ?? '', ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($slot['end_time'] ?? '', ENT_QUOTES, 'UTF-8') ?>')" 
                                                class="text-blue-500 hover:text-white hover:bg-blue-500 border border-transparent hover:border-blue-600 w-8 h-8 rounded flex items-center justify-center transition-all inline-flex"
                                                title="Edit Slot">
                                            <i class="fa-solid fa-pen text-sm"></i>
                                        </button>
                                        <form method="POST" class="inline" onsubmit="return confirm('Delete this slot permanently? This will not affect existing orders.')">
                                            <input type="hidden" name="action" value="delete_slot">
                                            <input type="hidden" name="id" value="<?= $slot['id'] ?>">
                                            <button type="submit" 
                                                    class="text-red-500 hover:text-white hover:bg-red-500 border border-transparent hover:border-red-600 w-8 h-8 rounded flex items-center justify-center transition-all inline-flex"
                                                    title="Delete Slot">
                                                <i class="fa-solid fa-trash text-sm"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Coupons Management -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <!-- Add Coupon Form -->
                <div class="xl:col-span-1">
                    <div class="bg-white p-6 rounded border border-gray-150 shadow-sm">
                        <h3 class="text-sm font-extrabold text-gray-900 tracking-tight mb-4 uppercase"><i class="fa-solid fa-ticket text-orange-500 mr-2"></i> Add Coupon</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_coupon">
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Coupon Code <span class="text-red-500">*</span></label>
                                <input type="text" name="code" placeholder="e.g. SUMMER10" required class="modern-input uppercase">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Discount Type</label>
                                    <select name="discount_type" required class="modern-input">
                                        <option value="fixed">Fixed Amount (৳)</option>
                                        <option value="percentage">Percentage (%)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Value <span class="text-red-500">*</span></label>
                                    <input type="number" step="0.01" name="discount_value" required class="modern-input">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Min Order Amount (৳)</label>
                                <input type="number" step="0.01" name="min_order_amount" value="0.00" class="modern-input">
                            </div>
                            
                            <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white font-extrabold text-sm py-3 rounded shadow transition-all mt-2">
                                Add Coupon
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Coupons List -->
                <div class="xl:col-span-2">
                    <div class="bg-white rounded border border-gray-150 shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h3 class="text-sm font-extrabold text-gray-900 tracking-tight uppercase">Active Coupons</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left whitespace-nowrap">
                                <thead class="bg-white border-b border-gray-100">
                                    <tr>
                                        <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Code</th>
                                        <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Discount</th>
                                        <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Min Order</th>
                                        <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Status</th>
                                        <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if(count($coupons) === 0): ?>
                                    <tr><td colspan="5" class="p-6 text-center text-gray-400 text-sm font-semibold">No coupons found.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach($coupons as $c): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="p-4">
                                            <span class="font-extrabold text-gray-900 bg-gray-100 px-2 py-1 rounded text-sm tracking-wider border border-gray-200"><?= htmlspecialchars($c['code']) ?></span>
                                        </td>
                                        <td class="p-4 font-bold text-orange-600 text-sm">
                                            <?= $c['discount_type'] === 'percentage' ? $c['discount_value'] . '%' : '৳' . $c['discount_value'] ?>
                                        </td>
                                        <td class="p-4 font-semibold text-gray-600 text-sm">
                                            ৳<?= $c['min_order_amount'] ?>
                                        </td>
                                        <td class="p-4">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="toggle_coupon">
                                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="text-xs font-bold px-2 py-1 rounded transition-colors <?= $c['is_active'] ? 'bg-brand-50 text-brand-600 border border-brand-200 hover:bg-brand-100' : 'bg-gray-50 text-gray-500 border border-gray-200 hover:bg-gray-100' ?>">
                                                    <?= $c['is_active'] ? 'Active' : 'Inactive' ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="p-4 text-right space-x-1 flex items-center justify-end">
                                            <button type="button" 
                                                    onclick="openEditCouponModal(<?= $c['id'] ?>, '<?= htmlspecialchars($c['code'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($c['discount_type'], ENT_QUOTES, 'UTF-8') ?>', <?= $c['discount_value'] ?>, <?= $c['min_order_amount'] ?>)" 
                                                    class="text-blue-500 hover:text-white hover:bg-blue-500 border border-transparent hover:border-blue-600 w-8 h-8 rounded flex items-center justify-center transition-all inline-flex"
                                                    title="Edit Coupon">
                                                <i class="fa-solid fa-pen text-sm"></i>
                                            </button>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="delete_coupon">
                                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="text-red-500 hover:text-white hover:bg-red-500 border border-transparent hover:border-red-600 w-8 h-8 rounded flex items-center justify-center transition-all inline-flex" onclick="return confirm('Delete this coupon?')">
                                                    <i class="fa-solid fa-trash text-sm"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Slot Modal -->
    <div id="editSlotModal" class="fixed inset-0 bg-black/60 hidden flex items-center justify-center z-50 p-4 backdrop-blur-sm transition-all duration-300 opacity-0">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-md transform scale-95 transition-transform duration-300">
            <!-- Header -->
            <div class="p-5 border-b border-gray-150 flex justify-between items-center bg-gray-50 rounded-t-lg">
                <h3 class="text-sm font-extrabold text-gray-900 uppercase"><i class="fa-solid fa-clock text-blue-500 mr-2"></i> Edit Delivery Slot</h3>
                <button type="button" onclick="closeEditSlotModal()" class="text-gray-400 hover:text-gray-800 bg-gray-200 hover:bg-gray-300 w-8 h-8 rounded-full transition-colors flex items-center justify-center shadow-inner"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <!-- Body & Form -->
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="edit_slot">
                <input type="hidden" name="id" id="editSlotId">
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Slot Name</label>
                    <input type="text" name="slot_name" id="editSlotName" placeholder="Morning Delivery" class="modern-input">
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Start Time <span class="text-red-500">*</span></label>
                        <input type="time" name="start_time" id="editSlotStartTime" required class="modern-input">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">End Time <span class="text-red-500">*</span></label>
                        <input type="time" name="end_time" id="editSlotEndTime" required class="modern-input">
                    </div>
                </div>
                
                <!-- Footer buttons -->
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeEditSlotModal()" class="px-4 py-2 text-xs bg-gray-200 hover:bg-gray-300 font-bold rounded transition-colors">Cancel</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs px-5 py-2 rounded shadow transition-all">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditSlotModal(id, slotName, startTime, endTime) {
            document.getElementById('editSlotId').value = id;
            document.getElementById('editSlotName').value = slotName || '';
            document.getElementById('editSlotStartTime').value = startTime ? startTime.substring(0, 5) : '';
            document.getElementById('editSlotEndTime').value = endTime ? endTime.substring(0, 5) : '';
            
            const modal = document.getElementById('editSlotModal');
            modal.classList.remove('hidden');
            // trigger animation reflow
            void modal.offsetWidth;
            modal.classList.remove('opacity-0');
            modal.classList.add('opacity-100');
            modal.querySelector('.transform').classList.remove('scale-95');
            modal.querySelector('.transform').classList.add('scale-100');
        }
        
        function closeEditSlotModal() {
            const modal = document.getElementById('editSlotModal');
            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            modal.querySelector('.transform').classList.remove('scale-100');
            modal.querySelector('.transform').classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    </script>

    <!-- Edit Coupon Modal -->
    <div id="editCouponModal" class="fixed inset-0 bg-black/60 hidden flex items-center justify-center z-50 p-4 backdrop-blur-sm transition-all duration-300 opacity-0">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-md transform scale-95 transition-transform duration-300">
            <!-- Header -->
            <div class="p-5 border-b border-gray-150 flex justify-between items-center bg-gray-50 rounded-t-lg">
                <h3 class="text-sm font-extrabold text-gray-900 uppercase"><i class="fa-solid fa-ticket text-orange-500 mr-2"></i> Edit Coupon</h3>
                <button type="button" onclick="closeEditCouponModal()" class="text-gray-400 hover:text-gray-800 bg-gray-200 hover:bg-gray-300 w-8 h-8 rounded-full transition-colors flex items-center justify-center shadow-inner"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <!-- Body & Form -->
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="edit_coupon">
                <input type="hidden" name="id" id="editCouponId">
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Coupon Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" id="editCouponCode" placeholder="e.g. SUMMER10" required class="modern-input uppercase">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Discount Type</label>
                        <select name="discount_type" id="editCouponDiscountType" required class="modern-input">
                            <option value="fixed">Fixed Amount (৳)</option>
                            <option value="percentage">Percentage (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Value <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" name="discount_value" id="editCouponDiscountValue" required class="modern-input">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Min Order Amount (৳)</label>
                    <input type="number" step="0.01" name="min_order_amount" id="editCouponMinOrderAmount" class="modern-input">
                </div>
                
                <!-- Footer buttons -->
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeEditCouponModal()" class="px-4 py-2 text-xs bg-gray-200 hover:bg-gray-300 font-bold rounded transition-colors">Cancel</button>
                    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-extrabold text-xs px-5 py-2 rounded shadow transition-all">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditCouponModal(id, code, type, value, minAmount) {
            document.getElementById('editCouponId').value = id;
            document.getElementById('editCouponCode').value = code;
            document.getElementById('editCouponDiscountType').value = type;
            document.getElementById('editCouponDiscountValue').value = value;
            document.getElementById('editCouponMinOrderAmount').value = minAmount;
            
            const modal = document.getElementById('editCouponModal');
            modal.classList.remove('hidden');
            // trigger animation reflow
            void modal.offsetWidth;
            modal.classList.remove('opacity-0');
            modal.classList.add('opacity-100');
            modal.querySelector('.transform').classList.remove('scale-95');
            modal.querySelector('.transform').classList.add('scale-100');
        }
        
        function closeEditCouponModal() {
            const modal = document.getElementById('editCouponModal');
            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            modal.querySelector('.transform').classList.remove('scale-100');
            modal.querySelector('.transform').classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    </script>
</body>
</html>
