<?php
require 'auth.php';
include '../config/db.php';

// Stats
$total_orders    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders"))['c'];
$pending_orders  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='pending'"))['c'];
$total_products  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM products"))['c'];
$total_revenue   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total) as s FROM orders WHERE status != 'cancelled'"))['s'] ?? 0;
$today_orders    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE DATE(created_at)=CURDATE()"))['c'];
$msg_unread      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM messages WHERE is_read = 0"))['c'];

// Recent orders
$recent_orders = mysqli_query($conn, "SELECT * FROM orders ORDER BY created_at DESC LIMIT 8");

// Low stock
$low_stock = mysqli_query($conn, "SELECT * FROM products WHERE stock <= 5 AND status='active' ORDER BY stock ASC LIMIT 6");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Frank Gadgets Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { -webkit-font-smoothing: antialiased; }
body { font-family: 'Inter', sans-serif; }
.sidebar-link {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; border-radius: 10px;
    font-size: 14px; font-weight: 500; color: #6b7280;
    transition: all 0.2s; text-decoration: none;
}
.sidebar-link:hover { background: #f3f4f6; color: #111827; }
.sidebar-link.active { background: #eff6ff; color: #2563eb; font-weight: 600; }
.stat-card { background: white; border-radius: 16px; padding: 20px 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
.badge {
    display: inline-flex; align-items: center; padding: 3px 10px;
    border-radius: 980px; font-size: 11px; font-weight: 600;
}
.badge-pending  { background: #fef9c3; color: #854d0e; }
.badge-processing { background: #dbeafe; color: #1e40af; }
.badge-shipped  { background: #d1fae5; color: #065f46; }
.badge-delivered{ background: #dcfce7; color: #14532d; }
.badge-cancelled{ background: #fee2e2; color: #991b1b; }

/* ── Mobile sidebar ── */
#sidebar-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.4);
    z-index: 40; display: none;
}
#sidebar-overlay.open { display: block; }

@media (max-width: 768px) {
    #sidebar {
        position: fixed; top: 0; left: 0; bottom: 0;
        z-index: 50; transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    #sidebar.open { transform: translateX(0); }
}

/* Mobile top bar */
.mobile-topbar {
    display: none; position: sticky; top: 0; z-index: 30;
    background: white; border-bottom: 1px solid #f3f4f6;
    padding: 0 16px; height: 56px;
    align-items: center; justify-content: space-between;
}
@media (max-width: 768px) {
    .mobile-topbar { display: flex; }
}
</style>
</head>
<body class="bg-gray-50">

<!-- ══════════ MOBILE TOP BAR ══════════ -->
<div class="mobile-topbar">
    <button onclick="toggleSidebar()" class="p-2 -ml-2 rounded-lg hover:bg-gray-100">
        <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
    <div class="flex items-center gap-2">
        <div class="w-7 h-7 bg-white border border-gray-200 rounded-lg flex items-center justify-center overflow-hidden p-0.5">
            <img src="../assets/images/logo.png" alt="FG" class="w-full h-full object-contain">
        </div>
        <span class="font-bold text-sm text-gray-900">Frank Gadgets</span>
    </div>
    <a href="messages.php" class="relative p-2 -mr-2">
        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <?php if($msg_unread > 0): ?>
        <span class="absolute top-1 right-1 bg-red-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center font-bold"><?= $msg_unread ?></span>
        <?php endif; ?>
    </a>
</div>

<!-- ══════════ SIDEBAR OVERLAY ══════════ -->
<div id="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="flex h-screen overflow-hidden">

    <!-- ══════════ SIDEBAR ══════════ -->
    <aside id="sidebar" class="w-56 bg-white border-r border-gray-100 flex flex-col flex-shrink-0">
        <div class="p-5 border-b border-gray-100">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-white border border-gray-200 rounded-lg flex items-center justify-center overflow-hidden p-0.5">
                        <img src="../assets/images/logo.png" alt="FG" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <p class="font-bold text-sm text-gray-900 leading-none">Frank Gadgets</p>
                        <p class="text-xs text-gray-400">Admin Panel</p>
                    </div>
                </div>
                <!-- Close button (mobile only) -->
                <button onclick="toggleSidebar()" class="md:hidden p-1.5 rounded-lg hover:bg-gray-100 -mr-1">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
            <a href="index.php"       class="sidebar-link active">📊 Dashboard</a>
            <a href="orders.php"      class="sidebar-link">📦 Orders
                <?php if($pending_orders > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?= $pending_orders ?></span>
                <?php endif; ?>
            </a>
            <a href="products.php"    class="sidebar-link">🛍️ Products</a>
            <a href="add-product.php" class="sidebar-link">➕ Add Product</a>
            <a href="categories.php"  class="sidebar-link">📂 Categories</a>
            <a href="messages.php"    class="sidebar-link">💬 Messages
                <?php if($msg_unread > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?= $msg_unread ?></span>
                <?php endif; ?>
            </a>
        </nav>

        <div class="p-3 border-t border-gray-100">
            <div class="flex items-center gap-2 px-2 mb-2">
                <div class="w-7 h-7 bg-blue-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                    <?= strtoupper(substr($_SESSION['admin_name'], 0, 1)) ?>
                </div>
                <span class="text-sm font-medium text-gray-700 truncate"><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
            </div>
            <a href="logout.php" class="sidebar-link text-red-500 hover:bg-red-50 hover:text-red-600">🚪 Logout</a>
            <a href="../index.php" target="_blank" class="sidebar-link">🌐 View Store</a>
        </div>
    </aside>

    <!-- ══════════ MAIN ══════════ -->
    <main class="flex-1 overflow-y-auto">
        <div class="p-6 max-w-6xl mx-auto">

            <!-- Header -->
            <div class="flex items-center justify-between mb-7">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p class="text-gray-500 text-sm">Welcome back, <?= htmlspecialchars($_SESSION['admin_name']) ?>!</p>
                </div>
                <a href="add-product.php"
                   class="bg-blue-600 text-white text-sm font-semibold px-4 py-2.5 rounded-xl hover:bg-blue-700 transition-colors hidden sm:inline-block">
                    + Add Product
                </a>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

    <!-- Revenue -->
    <div class="stat-card">
        <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wider mb-2">
            Total Revenue
        </p>

        <h2 class="text-[20px] sm:text-2xl lg:text-[28px] font-black text-gray-900 leading-tight break-words">
            ₦<?= number_format($total_revenue) ?>
        </h2>
    </div>

    <!-- Orders -->
    <div class="stat-card">
        <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wider mb-2">
            Total Orders
        </p>

        <h2 class="text-[20px] sm:text-2xl lg:text-[28px] font-black text-gray-900">
            <?= $total_orders ?>
        </h2>

        <p class="text-xs text-green-600 font-semibold mt-1">
            +<?= $today_orders ?> today
        </p>
    </div>

    <!-- Pending -->
    <div class="stat-card">
        <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wider mb-2">
            Pending
        </p>

        <h2 class="text-[20px] sm:text-2xl lg:text-[28px] font-black text-amber-500">
            <?= $pending_orders ?>
        </h2>
    </div>

    <!-- Products -->
    <div class="stat-card">
        <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wider mb-2">
            Products
        </p>

        <h2 class="text-[20px] sm:text-2xl lg:text-[28px] font-black text-gray-900">
            <?= $total_products ?>
        </h2>
    </div>

</div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Recent orders -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <h2 class="font-bold text-gray-900">Recent Orders</h2>
                        <a href="orders.php" class="text-xs text-blue-600 font-medium hover:underline">View all →</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100">
                                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Order</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Customer</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Total</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while($o = mysqli_fetch_assoc($recent_orders)): ?>
                            <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3">
                                    <a href="order-detail.php?id=<?= $o['id'] ?>" class="font-semibold text-blue-600 hover:underline text-xs">
                                        <?= $o['order_number'] ?>
                                    </a>
                                </td>
                                <td class="px-3 py-3 text-gray-700 text-xs"><?= htmlspecialchars($o['customer_name']) ?></td>
                                <td class="px-3 py-3 font-semibold text-xs">₦<?= number_format($o['total']) ?></td>
                                <td class="px-3 py-3">
                                    <span class="badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Low stock -->
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <h2 class="font-bold text-gray-900">Low Stock</h2>
                        <a href="products.php" class="text-xs text-blue-600 font-medium hover:underline">Manage →</a>
                    </div>
                    <div class="p-4 space-y-3">
                    <?php
                    $has_low = false;
                    while($p = mysqli_fetch_assoc($low_stock)):
                        $has_low = true;
                    ?>
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-medium text-gray-800 line-clamp-1 flex-1"><?= htmlspecialchars($p['name']) ?></p>
                        <span class="text-xs font-bold px-2 py-1 rounded-lg <?= $p['stock']==0?'bg-red-100 text-red-600':'bg-amber-100 text-amber-700' ?>">
                            <?= $p['stock']==0?'Out':'Stock: '.$p['stock'] ?>
                        </span>
                    </div>
                    <?php endwhile; ?>
                    <?php if(!$has_low): ?>
                    <p class="text-sm text-gray-400 py-4 text-center">✅ All products are well stocked</p>
                    <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<!-- ══════════ SIDEBAR TOGGLE SCRIPT ══════════ -->
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
    document.body.style.overflow = document.getElementById('sidebar').classList.contains('open') ? 'hidden' : '';
}
</script>

</body>
</html>