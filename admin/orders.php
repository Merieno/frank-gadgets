<?php
require 'auth.php';
include '../config/db.php';

// Status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $oid    = intval($_POST['order_id']);
    $status = clean($conn, $_POST['status']);
    mysqli_query($conn, "UPDATE orders SET status='$status' WHERE id=$oid");
    header('Location: orders.php?updated=1');
    exit;
}

$status_filter = clean($conn, $_GET['status'] ?? '');
$search        = clean($conn, $_GET['q'] ?? '');
$page          = max(1, intval($_GET['page'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;

$where = '1';
if ($status_filter) $where .= " AND o.status='$status_filter'";
if ($search)        $where .= " AND (o.order_number LIKE '%$search%' OR o.customer_name LIKE '%$search%' OR o.customer_phone LIKE '%$search%')";

$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders o WHERE $where"))['c'];
$total_pages = ceil($total / $per_page);

$orders = mysqli_query($conn, "SELECT * FROM orders o WHERE $where ORDER BY o.created_at DESC LIMIT $per_page OFFSET $offset");

$statuses = ['pending','processing','shipped','delivered','cancelled'];
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='pending'"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders — Frank Gadgets Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { -webkit-font-smoothing: antialiased; }
body { font-family: 'Inter', sans-serif; }
.sidebar-link { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; font-size:14px; font-weight:500; color:#6b7280; transition:all 0.2s; text-decoration:none; }
.sidebar-link:hover { background:#f3f4f6; color:#111827; }
.sidebar-link.active { background:#eff6ff; color:#2563eb; font-weight:600; }
.badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:980px; font-size:11px; font-weight:600; }
.badge-pending   { background:#fef9c3; color:#854d0e; }
.badge-processing{ background:#dbeafe; color:#1e40af; }
.badge-shipped   { background:#d1fae5; color:#065f46; }
.badge-delivered { background:#dcfce7; color:#14532d; }
.badge-cancelled { background:#fee2e2; color:#991b1b; }
</style>
</head>
<body class="bg-gray-50">
<div class="flex h-screen overflow-hidden">

    <!-- SIDEBAR -->
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
        <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
            <a href="index.php"       class="sidebar-link">📊 Dashboard</a>
            <a href="orders.php"      class="sidebar-link active">📦 Orders
                <?php if($pending_count>0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="products.php"    class="sidebar-link">🛍️ Products</a>
            <a href="add-product.php" class="sidebar-link">➕ Add Product</a>
            <a href="categories.php"  class="sidebar-link">📂 Categories</a>
        </nav>
        <div class="p-3 border-t border-gray-100">
            <a href="logout.php"    class="sidebar-link text-red-500 hover:bg-red-50">🚪 Logout</a>
            <a href="../index.php" target="_blank" class="sidebar-link">🌐 View Store</a>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="flex-1 overflow-y-auto">
        <div class="p-6 max-w-6xl mx-auto">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Orders</h1>
                <?php if(isset($_GET['updated'])): ?>
                <span class="bg-green-100 text-green-700 text-sm font-medium px-3 py-1.5 rounded-xl">✅ Status updated</span>
                <?php endif; ?>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-2xl shadow-sm p-4 mb-5 flex flex-wrap gap-3 items-center">
                <form method="GET" class="flex gap-2 flex-1 min-w-0">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search order, name, phone..."
                        class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-blue-500 min-w-0">
                    <?php if($status_filter): ?>
                    <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                    <?php endif; ?>
                    <button type="submit" class="bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-xl hover:bg-blue-700 transition-colors">Search</button>
                </form>
                <div class="flex gap-2 flex-wrap">
                    <a href="orders.php" class="text-xs px-3 py-1.5 rounded-xl border font-medium transition-colors <?php echo !$status_filter?'bg-blue-600 text-white border-blue-600':'border-gray-200 text-gray-600 hover:border-blue-400'; ?>">All</a>
                    <?php foreach($statuses as $s): ?>
                    <a href="orders.php?status=<?php echo $s; ?>" class="text-xs px-3 py-1.5 rounded-xl border font-medium transition-colors capitalize <?php echo $status_filter===$s?'bg-blue-600 text-white border-blue-600':'border-gray-200 text-gray-600 hover:border-blue-400'; ?>">
                        <?php echo ucfirst($s); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left px-5 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Order #</th>
                                <th class="text-left px-3 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Customer</th>
                                <th class="text-left px-3 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Total</th>
                                <th class="text-left px-3 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Payment</th>
                                <th class="text-left px-3 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</th>
                                <th class="text-left px-3 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Date</th>
                                <th class="px-3 py-3.5"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $has = false;
                        while($o = mysqli_fetch_assoc($orders)):
                            $has = true;
                        ?>
                        <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3.5">
                                <a href="order-detail.php?id=<?php echo $o['id']; ?>" class="font-semibold text-blue-600 hover:underline text-xs">
                                    <?php echo $o['order_number']; ?>
                                </a>
                            </td>
                            <td class="px-3 py-3.5">
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($o['customer_name']); ?></p>
                                <p class="text-xs text-gray-400"><?php echo htmlspecialchars($o['customer_phone']); ?></p>
                            </td>
                            <td class="px-3 py-3.5 font-semibold">₦<?php echo number_format($o['total']); ?></td>
                            <td class="px-3 py-3.5 text-xs text-gray-500 capitalize"><?php echo str_replace('_',' ',$o['payment_method']); ?></td>
                            <td class="px-3 py-3.5">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                    <select name="status" onchange="this.form.submit()"
                                        class="text-xs border border-gray-200 rounded-lg px-2 py-1 outline-none focus:border-blue-500 cursor-pointer bg-white">
                                        <?php foreach($statuses as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php echo $o['status']===$s?'selected':''; ?>>
                                            <?php echo ucfirst($s); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                            <td class="px-3 py-3.5 text-xs text-gray-400"><?php echo date('d M Y', strtotime($o['created_at'])); ?></td>
                            <td class="px-3 py-3.5">
                                <a href="order-detail.php?id=<?php echo $o['id']; ?>"
                                   class="text-xs text-blue-600 hover:underline font-medium">View</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if(!$has): ?>
                        <tr><td colspan="7" class="text-center py-12 text-gray-400 text-sm">No orders found</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="flex items-center justify-between px-5 py-4 border-t border-gray-100">
                    <p class="text-xs text-gray-400">Showing <?php echo min($offset+1,$total); ?>–<?php echo min($offset+$per_page,$total); ?> of <?php echo $total; ?></p>
                    <div class="flex gap-2">
                        <?php for($i=1;$i<=$total_pages;$i++): ?>
                        <a href="orders.php?page=<?php echo $i; ?><?php echo $status_filter?"&status=$status_filter":""; ?><?php echo $search?"&q=".urlencode($search):""; ?>"
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