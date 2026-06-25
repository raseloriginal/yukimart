<?php
session_start();
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once '../db.php';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_user') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$_POST['phone']]);
        if ($stmt->rowCount() > 0) {
            $error = "Phone number already exists!";
        } else {
            $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, phone, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['name'],
                $_POST['phone'],
                $pass,
                $_POST['role']
            ]);
            header("Location: users.php?success=1");
            exit;
        }
    } elseif ($_POST['action'] === 'delete_user') {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$_POST['id']]);
        header("Location: users.php?success=1");
        exit;
    }
}

$users = $pdo->query("SELECT id, name, phone, role, created_at FROM users WHERE role IN ('dsr', 'wholesaler') ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - YukiMartBD Admin</title>
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
            <a href="users.php" class="flex items-center gap-3 px-3 py-2.5 bg-brand-50 text-brand-600 font-bold rounded text-sm transition-all shadow-sm border border-brand-100">
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
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-y-auto relative">
        <header class="h-16 bg-white border-b border-gray-100 flex items-center px-6 justify-between sticky top-0 z-10 shadow-sm shrink-0">
            <div class="md:hidden flex items-center gap-3">
                <div class="flex items-center justify-center"><img src="../assets/images/logo.png" alt="Logo" class="w-8 h-8 object-contain rounded shadow-sm"></div>
                <h1 class="text-lg font-extrabold tracking-tight text-gray-900">Yuki<span class="text-brand-500">Admin</span></h1>
            </div>
            <h2 class="hidden md:block text-lg font-extrabold text-gray-900 tracking-tight">Manage Users</h2>
        </header>

        <div class="p-6 max-w-6xl mx-auto w-full">
            <?php if(isset($_GET['success'])): ?>
                <div class="bg-brand-50 text-brand-700 p-4 rounded mb-6 font-semibold border border-brand-200 shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-check-circle"></i> Action completed successfully!
                </div>
            <?php endif; ?>
            <?php if(isset($error)): ?>
                <div class="bg-red-50 text-red-700 p-4 rounded mb-6 font-semibold border border-red-200 shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <!-- Add User Form -->
                <div class="xl:col-span-1">
                    <div class="bg-white p-6 rounded border border-gray-150 shadow-sm">
                        <h3 class="text-sm font-extrabold text-gray-900 tracking-tight mb-4 uppercase">Add New User</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_user">
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Full Name <span class="text-red-500">*</span></label>
                                <input type="text" name="name" required class="modern-input">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Phone Number <span class="text-red-500">*</span></label>
                                <input type="tel" name="phone" required class="modern-input">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Password <span class="text-red-500">*</span></label>
                                <input type="password" name="password" required class="modern-input">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Role <span class="text-red-500">*</span></label>
                                <select name="role" required class="modern-input">
                                    <option value="dsr">Delivery Man (DSR)</option>
                                    <option value="wholesaler">Wholesaler</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-extrabold text-sm py-3 rounded shadow transition-all mt-2">
                                Add User
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Users List -->
                <div class="xl:col-span-2">
                    <div class="bg-white rounded border border-gray-150 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left whitespace-nowrap">
                                <thead class="bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Name</th>
                                        <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Phone</th>
                                        <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Role</th>
                                        <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach($users as $u): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="p-4 font-bold text-sm text-gray-800">
                                            <?= htmlspecialchars($u['name']) ?>
                                        </td>
                                        <td class="p-4 text-sm text-gray-600 font-medium">
                                            <?= htmlspecialchars($u['phone']) ?>
                                        </td>
                                        <td class="p-4">
                                            <?php if($u['role'] === 'dsr'): ?>
                                                <span class="bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-1 rounded-sm text-[10px] font-bold uppercase">DSR</span>
                                            <?php else: ?>
                                                <span class="bg-brand-50 text-brand-700 border border-purple-200 px-2.5 py-1 rounded-sm text-[10px] font-bold uppercase">Wholesaler</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 text-right">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="text-red-500 hover:text-white hover:bg-red-500 border border-transparent hover:border-red-600 w-8 h-8 rounded flex items-center justify-center transition-all inline-flex" onclick="return confirm('Delete this user?')">
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
</body>
</html>
