<?php
session_start();
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once '../db.php';
$pdo = getDB();

$activeTab = 'single';
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_area') {
        $name = trim($_POST['name']);
        $charge = (float)$_POST['delivery_charge'];
        
        if (empty($name)) {
            $error = "Area name cannot be empty!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO delivery_areas (name, delivery_charge, is_active) VALUES (?, ?, 1)");
            $stmt->execute([$name, $charge]);
            header("Location: areas.php?success=1");
            exit;
        }
    } elseif ($_POST['action'] === 'bulk_add_areas') {
        $activeTab = 'bulk';
        $bulk_data = $_POST['bulk_data'] ?? '';
        $lines = explode("\n", str_replace("\r", "", $bulk_data));
        
        $to_insert = [];
        $line_number = 0;
        
        try {
            foreach ($lines as $line) {
                $line_number++;
                $line = trim($line);
                if ($line === '') {
                    continue; // Skip empty lines
                }
                
                $parts = explode(',', $line, 2);
                if (count($parts) < 2) {
                    throw new Exception("Line {$line_number}: Invalid format. Expected 'Area Name, Charge'.");
                }
                
                $name = trim($parts[0]);
                $charge_str = trim($parts[1]);
                
                if (empty($name)) {
                    throw new Exception("Line {$line_number}: Area name cannot be empty.");
                }
                
                if (!is_numeric($charge_str)) {
                    throw new Exception("Line {$line_number}: Delivery charge '{$charge_str}' must be a number.");
                }
                
                $charge = (float)$charge_str;
                if ($charge < 0) {
                    throw new Exception("Line {$line_number}: Delivery charge cannot be negative.");
                }
                
                $to_insert[] = [
                    'name' => $name,
                    'charge' => $charge
                ];
            }
            
            if (empty($to_insert)) {
                throw new Exception("No valid delivery areas provided.");
            }
            
            // Insert all inside a transaction
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO delivery_areas (name, delivery_charge, is_active) VALUES (?, ?, 1)");
            foreach ($to_insert as $item) {
                $stmt->execute([$item['name'], $item['charge']]);
            }
            $pdo->commit();
            
            header("Location: areas.php?success=bulk&count=" . count($to_insert));
            exit;
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    } elseif ($_POST['action'] === 'toggle_area') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE delivery_areas SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: areas.php?success=1");
        exit;
    } elseif ($_POST['action'] === 'edit_area') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $charge = (float)$_POST['delivery_charge'];
        
        if (empty($name)) {
            $error = "Area name cannot be empty!";
        } else {
            $stmt = $pdo->prepare("UPDATE delivery_areas SET name = ?, delivery_charge = ? WHERE id = ?");
            $stmt->execute([$name, $charge, $id]);
            header("Location: areas.php?success=1");
            exit;
        }
    } elseif ($_POST['action'] === 'delete_area') {
        $id = (int)$_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM delivery_areas WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: areas.php?success=1");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $error = "Cannot delete this area because it has existing orders or users. Please toggle its status to 'Inactive' instead.";
            } else {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

$areas = $pdo->query("SELECT * FROM delivery_areas ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Delivery Areas - YukiMartBD Admin</title>
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
            <a href="areas.php" class="flex items-center gap-3 px-3 py-2.5 bg-brand-50 text-brand-600 font-bold rounded text-sm transition-all shadow-sm border border-brand-100">
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
    <main class="flex-1 flex flex-col overflow-y-auto relative">
        <header class="h-16 bg-white border-b border-gray-100 flex items-center px-6 justify-between sticky top-0 z-10 shadow-sm shrink-0">
            <div class="md:hidden flex items-center gap-3">
                <div class="flex items-center justify-center"><img src="../assets/images/logo.png" alt="Logo" class="w-8 h-8 object-contain rounded shadow-sm"></div>
                <h1 class="text-lg font-extrabold tracking-tight text-gray-900">Yuki<span class="text-brand-500">Admin</span></h1>
            </div>
            <h2 class="hidden md:block text-lg font-extrabold text-gray-900 tracking-tight">Manage Delivery Areas</h2>
        </header>

        <div class="p-6 max-w-6xl mx-auto w-full">
            <?php if(isset($_GET['success'])): ?>
                <div class="bg-brand-50 text-brand-700 p-4 rounded mb-6 font-semibold border border-brand-200 shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-check-circle"></i> 
                    <?php 
                    if ($_GET['success'] === 'bulk' && isset($_GET['count'])) {
                        echo htmlspecialchars($_GET['count']) . " delivery areas imported successfully!";
                    } else {
                        echo "Action completed successfully!";
                    }
                    ?>
                </div>
            <?php endif; ?>
            <?php if($error !== null): ?>
                <div class="bg-red-50 text-red-700 p-4 rounded mb-6 font-semibold border border-red-200 shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <!-- Add Area Card with Tabs -->
                <div class="xl:col-span-1">
                    <div class="bg-white rounded border border-gray-150 shadow-sm overflow-hidden">
                        <!-- Tabs Header -->
                        <div class="flex border-b border-gray-100 bg-gray-50">
                            <button type="button" id="tabSingleBtn" onclick="switchTab('single')" class="flex-1 py-3 px-4 text-xs font-extrabold uppercase tracking-wider <?= $activeTab === 'single' ? 'text-brand-600 border-b-2 border-brand-500 bg-white' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100/50' ?> transition-all">
                                <i class="fa-solid fa-map-pin mr-1.5"></i> Single Add
                            </button>
                            <button type="button" id="tabBulkBtn" onclick="switchTab('bulk')" class="flex-1 py-3 px-4 text-xs font-extrabold uppercase tracking-wider <?= $activeTab === 'bulk' ? 'text-brand-600 border-b-2 border-brand-500 bg-white' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100/50' ?> transition-all">
                                <i class="fa-solid fa-list-check mr-1.5"></i> Bulk Add
                            </button>
                        </div>
                        
                        <div class="p-6">
                            <!-- Single Add Form -->
                            <form id="formSingle" method="POST" class="space-y-4 <?= $activeTab === 'single' ? '' : 'hidden' ?>">
                                <input type="hidden" name="action" value="add_area">
                                
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Area Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="name" required placeholder="e.g. Banani" class="modern-input">
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Delivery Charge (৳) <span class="text-red-500">*</span></label>
                                    <input type="number" step="0.01" name="delivery_charge" required placeholder="e.g. 60.00" class="modern-input">
                                </div>
                                
                                <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-extrabold text-sm py-3 rounded shadow transition-all mt-2">
                                    Add Area
                                </button>
                            </form>

                            <!-- Bulk Add Form -->
                            <form id="formBulk" method="POST" class="space-y-4 <?= $activeTab === 'bulk' ? '' : 'hidden' ?>">
                                <input type="hidden" name="action" value="bulk_add_areas">
                                
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Bulk Data <span class="text-red-500">*</span></label>
                                    <textarea name="bulk_data" required rows="6" placeholder="Format: Area Name, Delivery Charge (one per line)&#10;e.g.&#10;Mohakhali, 60&#10;Uttara, 80&#10;Mirpur, 50" class="modern-input font-mono text-xs leading-relaxed"><?= htmlspecialchars($_POST['bulk_data'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="bg-gray-50 border border-gray-150 rounded p-3 text-xs text-gray-500 leading-normal">
                                    <p class="font-semibold text-gray-700 mb-1"><i class="fa-solid fa-circle-info text-brand-500 mr-1"></i> Formatting Instructions:</p>
                                    <ul class="list-disc pl-4 space-y-1">
                                        <li>Write each area on a new line.</li>
                                        <li>Separate the area name and charge with a comma.</li>
                                        <li>Charges can contain decimals (e.g. 65.50).</li>
                                    </ul>
                                </div>
                                
                                <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-extrabold text-sm py-3 rounded shadow transition-all mt-2">
                                    Import Areas
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Areas List -->
                <div class="xl:col-span-2">
                    <div class="bg-white rounded border border-gray-150 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left whitespace-nowrap">
                                <thead class="bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Area Name</th>
                                        <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Delivery Charge</th>
                                        <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Status</th>
                                        <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (count($areas) === 0): ?>
                                        <tr>
                                            <td colspan="4" class="p-6 text-center text-gray-400 text-sm font-semibold">No delivery areas found.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach($areas as $a): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="p-4 font-bold text-sm text-gray-800">
                                            <?= htmlspecialchars($a['name']) ?>
                                        </td>
                                        <td class="p-4 text-sm text-gray-600 font-medium">
                                            ৳<?= number_format($a['delivery_charge'], 2) ?>
                                        </td>
                                        <td class="p-4">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="toggle_area">
                                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                                <button type="submit" class="text-xs font-bold px-2.5 py-1 rounded transition-colors <?= $a['is_active'] ? 'bg-brand-50 text-brand-600 border border-brand-200 hover:bg-brand-100' : 'bg-gray-50 text-gray-500 border border-gray-200 hover:bg-gray-100' ?>">
                                                    <?= $a['is_active'] ? 'Active' : 'Inactive' ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="p-4 text-right space-x-1 flex items-center justify-end">
                                            <button type="button" 
                                                    onclick="openEditModal(<?= $a['id'] ?>, '<?= htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') ?>', <?= $a['delivery_charge'] ?>)"
                                                    class="text-blue-500 hover:text-white hover:bg-blue-500 border border-transparent hover:border-blue-600 w-8 h-8 rounded flex items-center justify-center transition-all inline-flex"
                                                    title="Edit Area">
                                                <i class="fa-solid fa-pen text-sm"></i>
                                            </button>
                                            <form method="POST" class="inline" onsubmit="return confirm('Delete this delivery area?')">
                                                <input type="hidden" name="action" value="delete_area">
                                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                                <button type="submit" class="text-red-500 hover:text-white hover:bg-red-500 border border-transparent hover:border-red-600 w-8 h-8 rounded flex items-center justify-center transition-all inline-flex" title="Delete Area">
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

    <!-- Edit Area Modal -->
    <div id="editAreaModal" class="fixed inset-0 bg-black/60 hidden flex items-center justify-center z-50 p-4 backdrop-blur-sm transition-all duration-300 opacity-0">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-md transform scale-95 transition-transform duration-300">
            <!-- Header -->
            <div class="p-5 border-b border-gray-150 flex justify-between items-center bg-gray-50 rounded-t-lg">
                <h3 class="text-sm font-extrabold text-gray-900 uppercase"><i class="fa-solid fa-map-pin text-brand-500 mr-2"></i> Edit Delivery Area</h3>
                <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-800 bg-gray-200 hover:bg-gray-300 w-8 h-8 rounded-full transition-colors flex items-center justify-center shadow-inner"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <!-- Body & Form -->
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="edit_area">
                <input type="hidden" name="id" id="editAreaId">
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Area Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="editAreaName" required class="modern-input">
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Delivery Charge (৳) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" name="delivery_charge" id="editAreaCharge" required class="modern-input">
                </div>
                
                <!-- Footer buttons -->
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-xs bg-gray-200 hover:bg-gray-300 font-bold rounded transition-colors">Cancel</button>
                    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-extrabold text-xs px-5 py-2 rounded shadow transition-all">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, name, charge) {
            document.getElementById('editAreaId').value = id;
            document.getElementById('editAreaName').value = name;
            document.getElementById('editAreaCharge').value = charge;
            
            const modal = document.getElementById('editAreaModal');
            modal.classList.remove('hidden');
            void modal.offsetWidth;
            modal.classList.remove('opacity-0');
            modal.classList.add('opacity-100');
            modal.querySelector('.transform').classList.remove('scale-95');
            modal.querySelector('.transform').classList.add('scale-100');
        }
        
        function closeEditModal() {
            const modal = document.getElementById('editAreaModal');
            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            modal.querySelector('.transform').classList.remove('scale-100');
            modal.querySelector('.transform').classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        function switchTab(type) {
            const btnSingle = document.getElementById('tabSingleBtn');
            const btnBulk = document.getElementById('tabBulkBtn');
            const formSingle = document.getElementById('formSingle');
            const formBulk = document.getElementById('formBulk');
            
            if (type === 'single') {
                btnSingle.className = "flex-1 py-3 px-4 text-xs font-extrabold uppercase tracking-wider text-brand-600 border-b-2 border-brand-500 bg-white transition-all";
                btnBulk.className = "flex-1 py-3 px-4 text-xs font-extrabold uppercase tracking-wider text-gray-400 hover:text-gray-600 hover:bg-gray-100/50 transition-all";
                formSingle.classList.remove('hidden');
                formBulk.classList.add('hidden');
                formSingle.querySelector('input[name="name"]').focus();
            } else {
                btnBulk.className = "flex-1 py-3 px-4 text-xs font-extrabold uppercase tracking-wider text-brand-600 border-b-2 border-brand-500 bg-white transition-all";
                btnSingle.className = "flex-1 py-3 px-4 text-xs font-extrabold uppercase tracking-wider text-gray-400 hover:text-gray-600 hover:bg-gray-100/50 transition-all";
                formBulk.classList.remove('hidden');
                formSingle.classList.add('hidden');
                formBulk.querySelector('textarea[name="bulk_data"]').focus();
            }
        }
    </script>
</body>
</html>
