<?php
require 'auth.php';
include '../config/db.php';
include '../includes/mailer.php';

$id = intval($_GET['id'] ?? 0);
$order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE id=$id LIMIT 1"));
if (!$order) { header('Location: orders.php'); exit; }

// Status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status      = clean($conn, $_POST['status']);
    $prev_status = $order['status'] ?? 'pending';
    mysqli_query($conn, "UPDATE orders SET status='$status' WHERE id=$id");
    $order['status'] = $status;

    if ($status === 'processing' && $prev_status === 'pending') {
        $email_items = [];
        $ei_res = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id=$id");
        while ($ei = mysqli_fetch_assoc($ei_res)) $email_items[] = $ei;

        $subject   = 'Your Order ' . $order['order_number'] . ' is Confirmed! — Frank Gadgets';
        $html_body = build_order_email($order, $email_items);
        send_order_email($order['customer_email'], $subject, $html_body, $order['customer_name']);
        $email_sent = true;
    }
}

$items = mysqli_query($conn, "SELECT oi.*, p.id as pid FROM order_items oi LEFT JOIN products p ON oi.product_id=p.id WHERE oi.order_id=$id");
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='pending'"))['c'];
$msg_unread = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM messages WHERE is_read = 0"))['c'];
$statuses = ['pending','processing','shipped','delivered','cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order <?= $order['order_number'] ?> — Frank Gadgets Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
*{-webkit-font-smoothing:antialiased}body{font-family:'Inter',sans-serif}
.sidebar-link{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;font-size:14px;font-weight:500;color:#6b7280;transition:all .2s;text-decoration:none}
.sidebar-link:hover{background:#f3f4f6;color:#111827}.sidebar-link.active{background:#eff6ff;color:#2563eb;font-weight:600}
#sidebar-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:40;display:none}#sidebar-overlay.open{display:block}
@media(max-width:768px){#sidebar{position:fixed;top:0;left:0;bottom:0;z-index:50;transform:translateX(-100%);transition:transform .3s ease}#sidebar.open{transform:translateX(0)}}
.mobile-topbar{display:none;position:sticky;top:0;z-index:30;background:#fff;border-bottom:1px solid #f3f4f6;padding:0 16px;height:56px;align-items:center;justify-content:space-between}
@media(max-width:768px){.mobile-topbar{display:flex}}
</style>
</head>
<body class="bg-gray-50">

<div class="mobile-topbar">
    <button onclick="toggleSidebar()" class="p-2 -ml-2 rounded-lg hover:bg-gray-100"><svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    <span class="font-bold text-sm text-gray-900"><?= $order['order_number'] ?></span>
    <a href="orders.php" class="p-2 -mr-2 text-xs text-blue-600 font-semibold">All Orders</a>
</div>
<div id="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="flex h-screen overflow-hidden">
    <aside id="sidebar" class="w-56 bg-white border-r border-gray-100 flex flex-col flex-shrink-0">
        <div class="p-5 border-b border-gray-100"><div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-8 h-8 bg-white border border-gray-200 rounded-lg flex items-center justify-center overflow-hidden p-0.5"><img src="../assets/images/logo.png" alt="FG" class="w-full h-full object-contain"></div><div><p class="font-bold text-sm text-gray-900 leading-none">Frank Gadgets</p><p class="text-xs text-gray-400">Admin Panel</p></div></div><button onclick="toggleSidebar()" class="md:hidden p-1.5 rounded-lg hover:bg-gray-100 -mr-1"><svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg></button></div></div>
        <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
            <a href="index.php" class="sidebar-link">📊 Dashboard</a>
            <a href="orders.php" class="sidebar-link active">📦 Orders<?php if($pending_count>0):?><span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?=$pending_count?></span><?php endif;?></a>
            <a href="products.php" class="sidebar-link">🛍️ Products</a>
            <a href="add-product.php" class="sidebar-link">➕ Add Product</a>
            <a href="categories.php" class="sidebar-link">📂 Categories</a>
            <a href="messages.php" class="sidebar-link">💬 Messages<?php if($msg_unread>0):?><span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?=$msg_unread?></span><?php endif;?></a>
        </nav>
        <div class="p-3 border-t border-gray-100"><a href="logout.php" class="sidebar-link text-red-500 hover:bg-red-50">🚪 Logout</a><a href="../index.php" target="_blank" class="sidebar-link">🌐 View Store</a></div>
    </aside>

    <main class="flex-1 overflow-y-auto">
        <div class="p-6 max-w-4xl mx-auto">

            <div class="flex items-center gap-3 mb-6 flex-wrap">
                <a href="orders.php" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg></a>
                <h1 class="text-xl font-bold text-gray-900">Order <?= $order['order_number'] ?></h1>
                <?php if(isset($email_sent) && $email_sent):?>
                <span class="text-xs bg-green-100 text-green-700 font-semibold px-3 py-1.5 rounded-xl">✅ Confirmation email sent</span>
                <?php endif;?>
                <span class="text-xs text-gray-400"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="lg:col-span-2 space-y-5">

                    <!-- Items -->
                    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100"><h2 class="font-bold text-gray-900">Order Items</h2></div>
                        <table class="w-full text-sm">
                            <thead><tr class="border-b border-gray-100"><th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Product</th><th class="text-center px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Qty</th><th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Price</th><th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Total</th></tr></thead>
                            <tbody>
                            <?php while($item=mysqli_fetch_assoc($items)):?>
                            <tr class="border-b border-gray-50">
                                <td class="px-5 py-3.5"><?php if($item['pid']):?><a href="../product.php?id=<?=$item['pid']?>" target="_blank" class="font-medium text-gray-800 hover:text-blue-600"><?=htmlspecialchars($item['product_name'])?></a><?php else:?><span class="font-medium text-gray-800"><?=htmlspecialchars($item['product_name'])?></span><?php endif;?></td>
                                <td class="px-3 py-3.5 text-center text-gray-600"><?=$item['quantity']?></td>
                                <td class="px-5 py-3.5 text-right text-gray-600">₦<?=number_format($item['price'])?></td>
                                <td class="px-5 py-3.5 text-right font-semibold">₦<?=number_format($item['price']*$item['quantity'])?></td>
                            </tr>
                            <?php endwhile;?>
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-gray-100"><td colspan="3" class="px-5 py-3 text-right text-sm text-gray-500">Subtotal</td><td class="px-5 py-3 text-right font-semibold text-sm">₦<?=number_format($order['subtotal'])?></td></tr>
                                <tr><td colspan="3" class="px-5 py-2 text-right text-sm text-gray-500">Shipping</td><td class="px-5 py-2 text-right font-semibold text-sm"><?=$order['shipping_fee']==0?'<span class="text-green-600">Free</span>':'₦'.number_format($order['shipping_fee'])?></td></tr>
                                <tr class="border-t border-gray-200"><td colspan="3" class="px-5 py-3 text-right font-bold">Total</td><td class="px-5 py-3 text-right font-black text-blue-600">₦<?=number_format($order['total'])?></td></tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Customer -->
                    <div class="bg-white rounded-2xl shadow-sm p-5">
                        <h2 class="font-bold text-gray-900 mb-4">Customer Details</h2>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div><p class="text-xs text-gray-400 mb-1">Name</p><p class="font-medium"><?=htmlspecialchars($order['customer_name'])?></p></div>
                            <div><p class="text-xs text-gray-400 mb-1">Phone</p><p class="font-medium"><?=htmlspecialchars($order['customer_phone'])?></p></div>
                            <div><p class="text-xs text-gray-400 mb-1">Email</p><p class="font-medium"><?=htmlspecialchars($order['customer_email'])?></p></div>
                            <div><p class="text-xs text-gray-400 mb-1">Payment</p><p class="font-medium capitalize"><?=str_replace('_',' ',$order['payment_method'])?></p></div>
                            <div class="col-span-2"><p class="text-xs text-gray-400 mb-1">Delivery Address</p><p class="font-medium"><?=htmlspecialchars($order['shipping_address'])?>, <?=htmlspecialchars($order['city'])?>, <?=htmlspecialchars($order['state'])?></p></div>
                            <?php if($order['notes']):?><div class="col-span-2"><p class="text-xs text-gray-400 mb-1">Notes</p><p class="font-medium"><?=htmlspecialchars($order['notes'])?></p></div><?php endif;?>
                        </div>
                    </div>

                    <!-- Receipt link -->
                    <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center justify-between">
                        <div>
                            <p class="font-semibold text-sm">Customer Receipt</p>
                            <p class="text-xs text-gray-400 mt-0.5">View or print the receipt for this order</p>
                        </div>
                        <a href="../receipt.php?order_id=<?= $order['id'] ?>" target="_blank" class="bg-blue-600 text-white text-sm font-semibold px-4 py-2 rounded-xl hover:bg-blue-700 transition-colors">View Receipt</a>
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <div class="bg-white rounded-2xl shadow-sm p-5">
                        <h2 class="font-bold text-gray-900 mb-4">Order Status</h2>
                        <form method="POST" class="space-y-3">
                            <?php foreach($statuses as $s):?>
                            <label class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer transition-all <?=$order['status']===$s?'border-blue-500 bg-blue-50':'border-gray-200 hover:border-blue-300'?>">
                                <input type="radio" name="status" value="<?=$s?>" <?=$order['status']===$s?'checked':''?> class="accent-blue-600">
                                <span class="text-sm font-medium capitalize"><?=ucfirst($s)?></span>
                            </label>
                            <?php endforeach;?>
                            <button type="submit" class="w-full bg-blue-600 text-white font-semibold py-2.5 rounded-xl hover:bg-blue-700 transition-colors text-sm mt-2">Update Status</button>
                        </form>
                    </div>

                    <!-- Quick actions -->
                    <div class="bg-white rounded-2xl shadow-sm p-5 mt-5 space-y-2">
                        <h2 class="font-bold text-gray-900 mb-3">Quick Actions</h2>
                        <a href="https://wa.me/<?=preg_replace('/[^0-9]/','', $order['customer_phone'])?>" target="_blank" class="flex items-center gap-2 text-sm text-gray-600 hover:text-green-600 py-1.5">💬 WhatsApp Customer</a>
                        <a href="mailto:<?=htmlspecialchars($order['customer_email'])?>" class="flex items-center gap-2 text-sm text-gray-600 hover:text-blue-600 py-1.5">✉️ Email Customer</a>
                        <a href="../receipt.php?order_id=<?=$order['id']?>" target="_blank" class="flex items-center gap-2 text-sm text-gray-600 hover:text-blue-600 py-1.5">🧾 View Receipt</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebar-overlay').classList.toggle('open');document.body.style.overflow=document.getElementById('sidebar').classList.contains('open')?'hidden':'';}</script>
</body></html>