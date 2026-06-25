<?php
session_start();
if (!isset($_SESSION['wholesaler_role']) || $_SESSION['wholesaler_role'] !== 'wholesaler') {
    header("Location: login.php");
    exit;
}

require_once '../db.php';
$pdo = getDB();
$wholesaler_id = $_SESSION['wholesaler_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_price') {
        $stmt = $pdo->prepare("UPDATE products SET wholesale_price = ? WHERE id = ? AND wholesaler_id = ?");
        $stmt->execute([$_POST['wholesale_price'], $_POST['product_id'], $wholesaler_id]);
        header("Location: products.php?success=1");
        exit;
    }
}

// Fetch products
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.wholesaler_id = ? ORDER BY p.id DESC");
$stmt->execute([$wholesaler_id]);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Products - Wholesaler Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/js/tailwind.config.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .modern-input { width: 100%; padding: 0.375rem 0.75rem; border-radius: 0.25rem; border: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.75rem; outline: none; transition: all 0.2s; font-weight: bold; }
        .modern-input:focus { border-color: #a855f7; box-shadow: 0 0 0 1px #a855f7; }
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
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-50 hover:text-brand-600 font-semibold rounded text-sm transition-all">
                <i class="fa-solid fa-chart-pie w-5 text-center"></i> Dashboard
            </a>
            <a href="products.php" class="flex items-center gap-3 px-3 py-2.5 bg-brand-50 text-brand-700 font-bold rounded text-sm transition-all shadow-sm border border-brand-100">
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
            <h2 class="hidden md:block text-lg font-extrabold text-gray-900 tracking-tight">Manage My Pricing</h2>
        </header>

        <div class="p-6 max-w-5xl mx-auto w-full">
            <?php if(isset($_GET['success'])): ?>
                <div class="bg-brand-50 text-brand-700 p-4 rounded mb-6 font-semibold border border-purple-200 shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-check-circle"></i> Price updated successfully!
                </div>
            <?php endif; ?>

            <div class="bg-white rounded border border-gray-150 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Product</th>
                                <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Category</th>
                                <th class="p-4 text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Retail Price (Customer)</th>
                                <th class="p-4 text-[10px] font-extrabold text-brand-600 uppercase tracking-wider">My Wholesale Price</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($products as $p): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <img src="<?= htmlspecialchars($p['image_url']) ?>" class="w-10 h-10 rounded object-cover bg-gray-100 border border-gray-200" onerror="this.src='https://via.placeholder.com/40'">
                                        <span class="font-bold text-sm text-gray-800"><?= htmlspecialchars($p['name']) ?></span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span class="bg-gray-100 text-gray-600 px-2.5 py-1 rounded-sm text-xs font-bold block w-max"><?= htmlspecialchars($p['category_name']) ?></span>
                                </td>
                                <td class="p-4 text-sm">
                                    <div class="font-extrabold text-gray-500">৳<?= $p['regular_price'] ?></div>
                                </td>
                                <td class="p-4 bg-brand-50/30">
                                    <form method="POST" class="flex items-center gap-2">
                                        <input type="hidden" name="action" value="update_price">
                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                        <div class="relative w-28">
                                            <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-gray-400 font-bold text-xs pointer-events-none">৳</span>
                                            <input type="number" step="0.01" name="wholesale_price" value="<?= htmlspecialchars($p['wholesale_price']) ?>" class="modern-input pl-6 text-brand-700" required>
                                        </div>
                                        <button type="submit" class="bg-brand-100 text-brand-700 border border-purple-200 hover:bg-brand-600 hover:text-white px-3 py-1.5 rounded text-xs font-bold transition-all shadow-sm">
                                            Update
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="4" class="p-8 text-center text-gray-500 text-sm font-semibold">
                                        <i class="fa-solid fa-box-open text-2xl text-gray-300 mb-2 block"></i>
                                        No products have been assigned to you yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
