<?php
require 'auth.php';
include '../config/db.php';

// Delete product
if (isset($_GET['delete'])) {
    $pid = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM product_images WHERE product_id=$pid");
    mysqli_query($conn, "DELETE FROM cart WHERE product_id=$pid");
    mysqli_query($conn, "DELETE FROM products WHERE id=$pid");
    header('Location: products.php?deleted=1');
    exit;
}

$search   = clean($conn, $_GET['q'] ?? '');
$cat_id   = intval($_GET['cat'] ?? 0);
$page     = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$where = '1';
if ($search) $where .= " AND (p.name LIKE '%$search%' OR p.brand LIKE '%$search%')";
if ($cat_id) $where .= " AND p.category_id=$cat_id";

$total       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM products p WHERE $where"))['c'];
$total_pages = ceil($total / $per_page);

$products = mysqli_query($conn, "
    SELECT p.*, c.name as cat_name,
    (SELECT image FROM product_images WHERE product_id=p.id AND is_main=1 LIMIT 1) as main_image
    FROM products p JOIN categories c ON p.category_id=c.id
    WHERE $where ORDER BY p.created_at DESC LIMIT $per_page OFFSET $offset
");

$cats_res = mysqli_query($conn, "SELECT * FROM categories ORDER BY sort_order");
$all_cats = [];
while ($c = mysqli_fetch_assoc($cats_res)) $all_cats[] = $c;
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='pending'"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Products — Frank Gadgets Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { -webkit-font-smoothing: antialiased; }
body { font-family: 'Inter', sans-serif; }
.sidebar-link { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; font-size:14px; font-weight:500; color:#6b7280; transition:all 0.2s; text-decoration:none; }
.sidebar-link:hover { background:#f3f4f6; color:#111827; }
.sidebar-link.active { background:#eff6ff; color:#2563eb; font-weight:600; }
</style>
</head>
<body class="bg-gray-50">
<div class="flex h-screen overflow-hidden">

    <aside class="w-56 bg-white border-r border-gray-100 flex flex-col flex-shrink-0">
        <div class="p-5 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-white border border-gray-200 rounded-lg flex items-center justify-center overflow-hidden p-0.5">
                    <img src="../assets/images/logo.png" alt="FG" class="w-full h-full object-contain">
                </div>
                <div>
                    <p class="font-bold text-sm text-gray-900 leading-none">Frank Gadgets</p>
                    <p class="text-xs text-gray-400">Admin Panel</p>
                </div>
            </div>
        </div>
        <nav class="flex-1 p-3 space-y-1">
            <a href="index.php"       class="sidebar-link">📊 Dashboard</a>
            <a href="orders.php"      class="sidebar-link">📦 Orders
                <?php if($pending_count>0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="products.php"    class="sidebar-link active">🛍️ Products</a>
            <a href="add-product.php" class="sidebar-link">➕ Add Product</a>
            <a href="categories.php"  class="sidebar-link">📂 Categories</a>
        </nav>
        <div class="p-3 border-t border-gray-100">
            <a href="logout.php" class="sidebar-link text-red-500 hover:bg-red-50">🚪 Logout</a>
            <a href="../index.php" target="_blank" class="sidebar-link">🌐 View Store</a>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto">
        <div class="p-6 max-w-6xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Products <span class="text-gray-400 font-normal text-lg">(<?php echo $total; ?>)</span></h1>
                <div class="flex gap-3">
                    <?php if(isset($_GET['deleted'])): ?>
                    <span class="bg-green-100 text-green-700 text-sm font-medium px-3 py-1.5 rounded-xl">✅ Deleted</span>
                    <?php endif; ?>
                    <a href="add-product.php" class="bg-blue-600 text-white text-sm font-semibold px-4 py-2.5 rounded-xl hover:bg-blue-700 transition-colors">
                        + Add Product
                    </a>
                </div>
            </div>

            <!-- Search & Filter -->
            <div class="bg-white rounded-2xl shadow-sm p-4 mb-5 flex flex-wrap gap-3">
                <form method="GET" class="flex gap-2 flex-1 min-w-0">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search products..."
                        class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-blue-500 min-w-0">
                    <select name="cat" class="border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-blue-500 bg-white">
                        <option value="">All Categories</option>
                        <?php foreach($all_cats as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $cat_id==$c['id']?'selected':''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-xl hover:bg-blue-700 transition-colors">Filter</button>
                </form>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Product</th>
                                <th class="text-left px-3 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Category</th>
                                <th class="text-left px-3 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Price</th>
                                <th class="text-left px-3 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Stock</th>
                                <th class="text-left px-3 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</th>
                                <th class="px-3 py-3.5"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $has = false;
                        while($p = mysqli_fetch_assoc($products)):
                            $has = true;
                            $img = $p['main_image'] ? UPLOADS_URL . $p['main_image'] : '../assets/images/no-image.png';
                        ?>
                        <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                                        <img src="<?php echo htmlspecialchars($img); ?>" alt="" class="w-full h-full object-cover"
                                            onerror="this.style.display='none'">
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800 line-clamp-1"><?php echo htmlspecialchars($p['name']); ?></p>
                                        <?php if($p['brand']): ?><p class="text-xs text-gray-400"><?php echo htmlspecialchars($p['brand']); ?></p><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3.5 text-xs text-gray-500"><?php echo htmlspecialchars($p['cat_name']); ?></td>
                            <td class="px-3 py-3.5">
                                <p class="font-semibold text-sm">₦<?php echo number_format($p['price']); ?></p>
                                <?php if($p['old_price']): ?>
                                <p class="text-xs text-gray-400 line-through">₦<?php echo number_format($p['old_price']); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-3.5">
                                <span class="text-sm font-semibold <?php echo $p['stock']==0?'text-red-500':($p['stock']<=5?'text-amber-500':'text-gray-700'); ?>">
                                    <?php echo $p['stock']; ?>
                                </span>
                            </td>
                            <td class="px-3 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                                    <?php echo $p['status']==='active'?'bg-green-100 text-green-700':($p['status']==='draft'?'bg-gray-100 text-gray-600':'bg-red-100 text-red-600'); ?>">
                                    <?php echo ucfirst($p['status']); ?>
                                </span>
                            </td>
                            <td class="px-3 py-3.5">
                                <div class="flex items-center gap-3">
                                    <a href="edit-product.php?id=<?php echo $p['id']; ?>" class="text-xs text-blue-600 font-medium hover:underline">Edit</a>
                                    <a href="products.php?delete=<?php echo $p['id']; ?>"
                                       onclick="return confirm('Delete this product?')"
                                       class="text-xs text-red-500 font-medium hover:underline">Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if(!$has): ?>
                        <tr><td colspan="6" class="text-center py-12 text-gray-400 text-sm">No products found</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if($total_pages > 1): ?>
                <div class="flex items-center justify-between px-5 py-4 border-t border-gray-100">
                    <p class="text-xs text-gray-400">Showing <?php echo min($offset+1,$total); ?>–<?php echo min($offset+$per_page,$total); ?> of <?php echo $total; ?></p>
                    <div class="flex gap-2">
                        <?php for($i=1;$i<=$total_pages;$i++): ?>
                        <a href="products.php?page=<?php echo $i; ?><?php echo $search?"&q=".urlencode($search):""; ?><?php echo $cat_id?"&cat=$cat_id":""; ?>"
                           class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-medium <?php echo $i===$page?'bg-blue-600 text-white':'border border-gray-200 text-gray-600 hover:border-blue-400'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>