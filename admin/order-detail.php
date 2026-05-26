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

    // Send confirmation email when status changes to processing
    if ($status === 'processing') {
        $email_items = [];
        $ei_res = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id=$id");
        while ($ei = mysqli_fetch_assoc($ei_res)) $email_items[] = $ei;

        // Re-fetch updated order
        $order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE id=$id LIMIT 1"));

        $subject   = 'Your Order ' . $order['order_number'] . ' is Confirmed! — Frank Gadgets';
        $html_body = build_order_email($order, $email_items);
        $sent = send_order_email($order['customer_email'], $subject, $html_body, $order['customer_name']);
        $email_sent = $sent;
        $email_error = !$sent;
    }
}

$items = mysqli_query($conn, "SELECT oi.*, p.id as pid FROM order_items oi LEFT JOIN products p ON oi.product_id=p.id WHERE oi.order_id=$id");
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='pending'"))['c'];
$statuses = ['pending','processing','shipped','delivered','cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order <?php echo $order['order_number']; ?> — Frank Gadgets Admin</title>
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
            <a href="logout.php" class="sidebar-link text-red-500 hover:bg-red-50">🚪 Logout</a>
            <a href="../index.php" target="_blank" class="sidebar-link">🌐 View Store</a>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto">
        <div class="p-6 max-w-4xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <a href="orders.php" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                </a>
                <h1 class="text-xl font-bold text-gray-900">Order <?php echo $order['order_number']; ?></h1>
                <?php if(isset($email_sent) && $email_sent): ?>
                <span class="text-xs bg-green-100 text-green-700 font-semibold px-3 py-1.5 rounded-xl">✅ Confirmation email sent to customer</span>
                <?php elseif(isset($email_error) && $email_error): ?>
                <span class="text-xs bg-red-100 text-red-700 font-semibold px-3 py-1.5 rounded-xl">⚠️ Email failed — check mailer.php settings</span>
                <?php endif; ?>
                <span class="text-xs text-gray-400"><?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                <!-- Left -->
                <div class="lg:col-span-2 space-y-5">

                    <!-- Items -->
                    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h2 class="font-bold text-gray-900">Order Items</h2>
                        </div>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100">
                                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Product</th>
                                    <th class="text-center px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Qty</th>
                                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Price</th>
                                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while($item = mysqli_fetch_assoc($items)): ?>
                            <tr class="border-b border-gray-50">
                                <td class="px-5 py-3.5">
                                    <?php if($item['pid']): ?>
                                    <a href="../product.php?id=<?php echo $item['pid']; ?>" target="_blank"
                                       class="font-medium text-gray-800 hover:text-blue-600 transition-colors">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="font-medium text-gray-800"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3.5 text-center text-gray-600"><?php echo $item['quantity']; ?></td>
                                <td class="px-5 py-3.5 text-right text-gray-600">₦<?php echo number_format($item['price']); ?></td>
                                <td class="px-5 py-3.5 text-right font-semibold">₦<?php echo number_format($item['price'] * $item['quantity']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-gray-100">
                                    <td colspan="3" class="px-5 py-3 text-right text-sm text-gray-500">Subtotal</td>
                                    <td class="px-5 py-3 text-right font-semibold text-sm">₦<?php echo number_format($order['subtotal']); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="px-5 py-2 text-right text-sm text-gray-500">Shipping</td>
                                    <td class="px-5 py-2 text-right font-semibold text-sm">
                                        <?php echo $order['shipping_fee']==0?'<span class="text-green-600">Free</span>':'₦'.number_format($order['shipping_fee']); ?>
                                    </td>
                                </tr>
                                <tr class="border-t border-gray-200">
                                    <td colspan="3" class="px-5 py-3 text-right font-bold">Total</td>
                                    <td class="px-5 py-3 text-right font-black text-blue-600">₦<?php echo number_format($order['total']); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Customer details -->
                    <div class="bg-white rounded-2xl shadow-sm p-5">
                        <h2 class="font-bold text-gray-900 mb-4">Customer Details</h2>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div><p class="text-xs text-gray-400 mb-1">Name</p><p class="font-medium"><?php echo htmlspecialchars($order['customer_name']); ?></p></div>
                            <div><p class="text-xs text-gray-400 mb-1">Phone</p><p class="font-medium"><?php echo htmlspecialchars($order['customer_phone']); ?></p></div>
                            <div><p class="text-xs text-gray-400 mb-1">Email</p><p class="font-medium"><?php echo htmlspecialchars($order['customer_email']); ?></p></div>
                            <div><p class="text-xs text-gray-400 mb-1">Payment</p><p class="font-medium capitalize"><?php echo str_replace('_',' ',$order['payment_method']); ?></p></div>
                            <div class="col-span-2"><p class="text-xs text-gray-400 mb-1">Delivery Address</p>
                                <p class="font-medium"><?php echo htmlspecialchars($order['shipping_address']); ?>, <?php echo htmlspecialchars($order['city']); ?>, <?php echo htmlspecialchars($order['state']); ?></p>
                            </div>
                            <?php if($order['notes']): ?>
                            <div class="col-span-2"><p class="text-xs text-gray-400 mb-1">Notes</p><p class="font-medium"><?php echo htmlspecialchars($order['notes']); ?></p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right: Status -->
                <div>
                    <div class="bg-white rounded-2xl shadow-sm p-5">
                        <h2 class="font-bold text-gray-900 mb-4">Order Status</h2>
                        <form method="POST" class="space-y-3">
                            <?php foreach($statuses as $s): ?>
                            <label class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer transition-all <?php echo $order['status']===$s?'border-blue-500 bg-blue-50':'border-gray-200 hover:border-blue-300'; ?>">
                                <input type="radio" name="status" value="<?php echo $s; ?>" <?php echo $order['status']===$s?'checked':''; ?> class="accent-blue-600">
                                <span class="text-sm font-medium capitalize"><?php echo ucfirst($s); ?></span>
                            </label>
                            <?php endforeach; ?>
                            <button type="submit" class="w-full bg-blue-600 text-white font-semibold py-2.5 rounded-xl hover:bg-blue-700 transition-colors text-sm mt-2">
                                Update Status
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>