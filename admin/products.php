<?php
session_start();
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once '../db.php';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_product') {
        $stmt = $pdo->prepare("INSERT INTO products (category_id, name, description, regular_price, wholesale_price, image_url, wholesaler_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $count = isset($_POST['name']) ? count($_POST['name']) : 0;
        for ($i = 0; $i < $count; $i++) {
            if (empty(trim($_POST['name'][$i]))) continue;
            
            $wholesaler_id = !empty($_POST['wholesaler_id'][$i]) ? $_POST['wholesaler_id'][$i] : null;
            $stmt->execute([
                $_POST['category_id'][$i],
                $_POST['name'][$i],
                '', // bulk add doesn't need huge descriptions immediately
                $_POST['regular_price'][$i],
                $_POST['wholesale_price'][$i],
                $_POST['image_url'][$i],
                $wholesaler_id
            ]);
        }
        header("Location: products.php?success=1");
        exit;
    } elseif ($_POST['action'] === 'edit_product') {
        $wholesaler_id = !empty($_POST['wholesaler_id']) ? $_POST['wholesaler_id'] : null;
        $stmt = $pdo->prepare("UPDATE products SET category_id = ?, name = ?, description = ?, regular_price = ?, wholesale_price = ?, image_url = ?, wholesaler_id = ? WHERE id = ?");
        $stmt->execute([
            $_POST['category_id'],
            $_POST['name'],
            $_POST['description'],
            $_POST['regular_price'],
            $_POST['wholesale_price'],
            $_POST['image_url'],
            $wholesaler_id,
            $_POST['product_id']
        ]);
        header("Location: products.php?success=1");
        exit;
    } elseif ($_POST['action'] === 'delete_product') {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header("Location: products.php?success=1");
        exit;
    }
}

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$wholesalers = $pdo->query("SELECT id, name FROM users WHERE role = 'wholesaler'")->fetchAll();

// Pagination and Filtering Logic
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 12; // 12 products per page
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category_filter']) ? trim($_GET['category_filter']) : '';

$whereClause = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $whereClause .= " AND (p.name LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter !== '') {
    $whereClause .= " AND p.category_id = ?";
    $params[] = $category_filter;
}

// Count total for pagination
$countQuery = "SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id $whereClause";
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$totalProducts = $stmtCount->fetchColumn();
$totalPages = max(1, ceil($totalProducts / $limit));

// Fetch products
$query = "SELECT p.*, c.name as category_name, u.name as wholesaler_name 
          FROM products p 
          JOIN categories c ON p.category_id = c.id 
          LEFT JOIN users u ON p.wholesaler_id = u.id 
          $whereClause 
          ORDER BY p.id DESC 
          LIMIT $limit OFFSET $offset";
$stmtProducts = $pdo->prepare($query);
$stmtProducts->execute($params);
$products = $stmtProducts->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - YukiMartBD Admin</title>
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
            <a href="products.php" class="flex items-center gap-3 px-3 py-2.5 bg-brand-50 text-brand-600 font-bold rounded text-sm transition-all shadow-sm border border-brand-100">
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
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-y-auto relative w-full">
        <header class="h-16 bg-white border-b border-gray-100 flex items-center px-6 justify-between sticky top-0 z-10 shadow-sm shrink-0">
            <div class="md:hidden flex items-center gap-3">
                <div class="flex items-center justify-center"><img src="../assets/images/logo.png" alt="Logo" class="w-8 h-8 object-contain rounded shadow-sm"></div>
                <h1 class="text-lg font-extrabold tracking-tight text-gray-900">Yuki<span class="text-brand-500">Admin</span></h1>
            </div>
            <h2 class="hidden md:block text-lg font-extrabold text-gray-900 tracking-tight">Manage Products</h2>
        </header>

        <div class="p-6 max-w-7xl mx-auto w-full">
            <?php if(isset($_GET['success'])): ?>
                <div class="bg-brand-50 text-brand-700 p-4 rounded mb-6 font-semibold border border-brand-200 shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-check-circle"></i> Action completed successfully!
                </div>
            <?php endif; ?>

            <!-- Top Control Bar -->
            <form method="GET" class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <div class="w-full md:w-2/3 flex flex-col sm:flex-row gap-2">
                    <div class="relative flex-1">
                        <i class="fa-solid fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search products by name or category..." class="w-full pl-11 pr-4 py-3 border border-gray-200 rounded shadow-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-all">
                    </div>
                    <select name="category_filter" class="w-full sm:w-48 py-3 px-4 border border-gray-200 rounded shadow-sm focus:ring-2 focus:ring-brand-500 outline-none bg-white">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (string)$category_filter === (string)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded shadow-sm transition-all whitespace-nowrap">Filter</button>
                </div>
                <button type="button" onclick="openAddModal()" class="w-full md:w-auto bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-extrabold text-sm py-3 px-6 rounded shadow transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-plus"></i> Add New Product(s)
                </button>
            </form>

            <!-- Products Grid -->
            <div id="productsGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach($products as $p): ?>
                <div class="product-card bg-white rounded-lg border border-gray-150 shadow-sm overflow-hidden hover:shadow-md transition-shadow flex flex-col group" data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>" data-category="<?= strtolower(htmlspecialchars($p['category_name'])) ?>">
                    <!-- Image -->
                    <div class="h-48 bg-gray-50 relative border-b border-gray-150 flex items-center justify-center overflow-hidden">
                        <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="Product" onerror="this.src='https://via.placeholder.com/300'" class="w-full h-full object-cover">
                        <?php if($p['wholesaler_name']): ?>
                            <div class="absolute top-2 right-2 bg-brand-100 text-brand-700 text-[10px] font-extrabold px-2 py-1 rounded shadow-sm flex items-center gap-1 opacity-90">
                                <i class="fa-solid fa-store"></i> <?= htmlspecialchars($p['wholesaler_name']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="absolute bottom-2 left-2 bg-brand-100 text-brand-700 text-[10px] font-extrabold px-2 py-1 rounded shadow-sm opacity-90">
                            <?= htmlspecialchars($p['category_name']) ?>
                        </div>
                    </div>
                    <!-- Details -->
                    <div class="p-4 flex-1 flex flex-col">
                        <h3 class="font-extrabold text-gray-900 text-sm mb-3 line-clamp-2" title="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></h3>
                        
                        <div class="grid grid-cols-2 gap-2 mt-auto">
                            <div class="bg-blue-50 p-2.5 rounded border border-blue-100 text-center">
                                <span class="block text-[10px] font-extrabold text-blue-400 uppercase tracking-wider mb-0.5">Buying</span>
                                <span class="font-bold text-blue-700 text-sm">৳<?= $p['wholesale_price'] ?></span>
                            </div>
                            <div class="bg-brand-50 p-2.5 rounded border border-brand-100 text-center">
                                <span class="block text-[10px] font-extrabold text-brand-400 uppercase tracking-wider mb-0.5">Selling</span>
                                <span class="font-black text-brand-600 text-sm">৳<?= $p['regular_price'] ?></span>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-gray-100">
                            <button type="button" class="text-blue-500 hover:text-blue-600 bg-blue-50 hover:bg-blue-100 w-9 h-9 rounded flex items-center justify-center transition-all" onclick="openEditModal(<?= htmlspecialchars(json_encode($p)) ?>)" title="Edit">
                                <i class="fa-solid fa-edit text-sm"></i>
                            </button>
                            <form method="POST" class="inline m-0">
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="text-red-500 hover:text-red-600 bg-red-50 hover:bg-red-100 w-9 h-9 rounded flex items-center justify-center transition-all" onclick="return confirm('Delete this product?')" title="Delete">
                                    <i class="fa-solid fa-trash text-sm"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination Controls -->
            <?php if($totalPages > 1): ?>
            <div class="mt-8 flex justify-center items-center space-x-2">
                <?php if($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category_filter=<?= urlencode($category_filter) ?>" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 hover:bg-brand-50 hover:text-brand-600 rounded font-bold transition-all shadow-sm"><i class="fa-solid fa-chevron-left text-xs mr-1"></i> Prev</a>
                <?php endif; ?>
                
                <span class="px-4 py-2 bg-gray-100 border border-gray-200 text-gray-800 font-extrabold rounded shadow-sm">Page <?= $page ?> of <?= $totalPages ?></span>
                
                <?php if($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category_filter=<?= urlencode($category_filter) ?>" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 hover:bg-brand-50 hover:text-brand-600 rounded font-bold transition-all shadow-sm">Next <i class="fa-solid fa-chevron-right text-xs ml-1"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if(count($products) === 0): ?>
            <div class="text-center py-16">
                <i class="fa-solid fa-box-open text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-bold text-gray-500">No products found.</h3>
                <p class="text-sm text-gray-400 mt-1">Try adjusting your search criteria.</p>
                <?php if($search !== '' || $category_filter !== ''): ?>
                    <a href="products.php" class="mt-4 inline-block px-5 py-2 bg-brand-50 text-brand-600 font-bold rounded-lg border border-brand-200 hover:bg-brand-100 transition-all">Clear Filters</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add Product Modal (Bulk Supported) -->
    <div id="addModal" class="fixed inset-0 bg-black/60 hidden flex items-center justify-center z-50 p-4 backdrop-blur-sm">
        <div class="bg-white rounded shadow-2xl w-full max-w-6xl max-h-[90vh] flex flex-col">
            <div class="p-5 border-b flex justify-between items-center bg-gray-50 rounded-t">
                <h3 class="text-base font-extrabold text-gray-900 tracking-tight uppercase"><i class="fa-solid fa-boxes-stacked text-brand-600 mr-2"></i> Add Products</h3>
                <button type="button" onclick="closeAddModal()" class="text-gray-400 hover:text-gray-800 bg-gray-200 hover:bg-gray-300 w-8 h-8 rounded-full transition-colors"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 overflow-y-auto flex-1 bg-gray-50/50">
                <form method="POST" id="addForm">
                    <input type="hidden" name="action" value="add_product">
                    
                    <div id="productRows" class="space-y-4">
                        <!-- Rows dynamically generated here -->
                    </div>
                    
                    <div class="mt-6 flex justify-center">
                        <button type="button" onclick="addProductRow()" class="bg-white border-2 border-dashed border-brand-300 text-brand-600 hover:bg-brand-50 hover:border-brand-500 font-extrabold text-sm py-3 px-6 rounded-lg transition-all flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Add Another Row
                        </button>
                    </div>
                </form>
            </div>
            <div class="p-4 border-t bg-white flex justify-end gap-3 rounded-b shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
                <button type="button" onclick="closeAddModal()" class="px-6 py-2.5 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded font-bold text-sm transition-colors">Cancel</button>
                <button type="button" onclick="document.getElementById('addForm').submit()" class="px-6 py-2.5 bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-extrabold text-sm rounded shadow-lg transition-all flex items-center gap-2">
                    <i class="fa-solid fa-save"></i> Save All Products
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" class="fixed inset-0 bg-black/60 hidden flex items-center justify-center z-50 p-4 backdrop-blur-sm">
        <div class="bg-white rounded border border-gray-150 shadow-2xl w-full max-w-md max-h-[90vh] flex flex-col">
            <div class="p-4 border-b flex justify-between items-center bg-gray-50 rounded-t">
                <h3 class="text-sm font-extrabold text-gray-900 tracking-tight uppercase">Edit Product</h3>
                <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-800 bg-gray-200 hover:bg-gray-300 w-8 h-8 rounded-full transition-colors"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 overflow-y-auto">
                <form method="POST" class="space-y-4" id="editForm">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Product Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="edit_name" required class="modern-input">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Category <span class="text-red-500">*</span></label>
                        <select name="category_id" id="edit_category_id" required class="modern-input">
                            <?php foreach($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Buying (Wholesale) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" name="wholesale_price" id="edit_wholesale_price" required class="modern-input">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Selling (Regular) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" name="regular_price" id="edit_regular_price" required class="modern-input">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Image URL <span class="text-red-500">*</span></label>
                        <input type="url" name="image_url" id="edit_image_url" required class="modern-input" placeholder="https://...">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Wholesaler</label>
                        <select name="wholesaler_id" id="edit_wholesaler_id" class="modern-input">
                            <option value="">None (Company Owned)</option>
                            <?php foreach($wholesalers as $w): ?>
                                <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Description</label>
                        <textarea name="description" id="edit_description" rows="3" class="modern-input resize-none"></textarea>
                    </div>
                    
                    <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-extrabold text-sm py-3 rounded shadow transition-all mt-2 flex justify-center items-center gap-2">
                        <i class="fa-solid fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const categoriesOptions = `<?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES) ?></option><?php endforeach; ?>`;
        const wholesalersOptions = `<option value="">None (Company Owned)</option><?php foreach($wholesalers as $w): ?><option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name'], ENT_QUOTES) ?></option><?php endforeach; ?>`;

        function addProductRow() {
            const container = document.getElementById('productRows');
            const rowHtml = `
                <div class="bg-white p-5 rounded border border-gray-200 shadow-sm relative group transition-all hover:border-brand-300 hover:shadow-md">
                    <button type="button" onclick="removeRow(this)" class="absolute -top-3 -right-3 bg-red-100 text-red-600 hover:bg-red-500 hover:text-white w-8 h-8 rounded-full flex items-center justify-center text-sm opacity-0 group-hover:opacity-100 transition-all shadow border border-red-200 hover:border-red-600"><i class="fa-solid fa-times"></i></button>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                        <div class="md:col-span-3">
                            <label class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-wider mb-1">Product Name *</label>
                            <input type="text" name="name[]" required class="modern-input" placeholder="e.g. Fresh Apples">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-wider mb-1">Category *</label>
                            <select name="category_id[]" required class="modern-input bg-gray-50">
                                ${categoriesOptions}
                            </select>
                        </div>
                        <div class="md:col-span-1">
                            <label class="block text-[10px] font-extrabold text-blue-400 uppercase tracking-wider mb-1">Buying *</label>
                            <input type="number" step="0.01" name="wholesale_price[]" required class="modern-input font-bold text-blue-600">
                        </div>
                        <div class="md:col-span-1">
                            <label class="block text-[10px] font-extrabold text-brand-400 uppercase tracking-wider mb-1">Selling *</label>
                            <input type="number" step="0.01" name="regular_price[]" required class="modern-input font-bold text-brand-600">
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-wider mb-1">Image URL *</label>
                            <input type="url" name="image_url[]" required class="modern-input" placeholder="https://...">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-wider mb-1">Wholesaler</label>
                            <select name="wholesaler_id[]" class="modern-input bg-gray-50">
                                ${wholesalersOptions}
                            </select>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', rowHtml);
        }

        function removeRow(btn) {
            const row = btn.closest('.bg-white');
            if (document.getElementById('productRows').children.length > 1) {
                row.remove();
            } else {
                alert("You must have at least one product row.");
            }
        }

        function openAddModal() {
            const container = document.getElementById('productRows');
            if (container.children.length === 0) {
                addProductRow();
            }
            document.getElementById('addModal').classList.remove('hidden');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }

        function openEditModal(product) {
            document.getElementById('edit_product_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_category_id').value = product.category_id;
            document.getElementById('edit_regular_price').value = product.regular_price;
            document.getElementById('edit_wholesale_price').value = product.wholesale_price;
            document.getElementById('edit_image_url').value = product.image_url;
            document.getElementById('edit_wholesaler_id').value = product.wholesaler_id || '';
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editForm').reset();
        }


    </script>
</body>
</html>
