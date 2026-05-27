<?php
session_start();
include 'config/db.php';

// Get categories for navbar (same as index.php)
$cats = mysqli_query($conn, "
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
    GROUP BY c.id
    ORDER BY c.sort_order ASC
");
$nav_cats = [];
while ($c = mysqli_fetch_assoc($cats)) $nav_cats[] = $c;

$cart_count = get_cart_count($conn);

// ── Handle form submission ──
$order    = null;
$error    = null;
$searched = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = trim($_POST['order_id'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $searched = true;

    if ($order_id === '' || $email === '') {
        $error = 'Please enter both your order number and email address.';
    } else {
        // Query orders table directly — no users table needed
        $stmt = $conn->prepare("
            SELECT *
            FROM orders
            WHERE (LOWER(order_number) = LOWER(?) OR id = ?)
              AND LOWER(customer_email) = LOWER(?)
            LIMIT 1
        ");
        $stmt->bind_param('sss', $order_id, $order_id, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Try matching by phone instead of email (in case customer used phone)
            $stmt2 = $conn->prepare("
                SELECT *
                FROM orders
                WHERE (LOWER(order_number) = LOWER(?) OR id = ?)
                  AND customer_phone = ?
                LIMIT 1
            ");
            $stmt2->bind_param('sss', $order_id, $order_id, $email);
            $stmt2->execute();
            $result2 = $stmt2->get_result();

            if ($result2->num_rows === 0) {
                $error = 'No order found. Please check your order number and email, then try again.';
            } else {
                $order = $result2->fetch_assoc();
            }
            $stmt2->close();
        } else {
            $order = $result->fetch_assoc();
        }
        $stmt->close();

        // Get order items if order was found
        if ($order) {
            $items_result = mysqli_query($conn, "
                SELECT oi.*, p.name as product_name,
                       (SELECT image FROM product_images WHERE product_id=p.id AND is_main=1 LIMIT 1) as product_image
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = {$order['id']}
            ");
            $order['items'] = [];
            while ($item = mysqli_fetch_assoc($items_result)) {
                $order['items'][] = $item;
            }
        }
    }
}

// ── Status config ──
$status_steps = [
    'pending'    => 1,
    'confirmed'  => 2,
    'processing' => 2,
    'shipped'    => 3,
    'out_for_delivery' => 3,
    'delivered'  => 4,
    'cancelled'  => 0,
];
$status_labels = [
    'pending'    => 'Order Placed',
    'confirmed'  => 'Confirmed',
    'processing' => 'Processing',
    'shipped'    => 'Shipped',
    'out_for_delivery' => 'Out for Delivery',
    'delivered'  => 'Delivered',
    'cancelled'  => 'Cancelled',
];
$status_messages = [
    'pending'    => ['title' => 'Order Received', 'sub' => 'We\'ve received your order and are reviewing it.'],
    'confirmed'  => ['title' => 'Order Confirmed', 'sub' => 'Your order has been confirmed and is being prepared.'],
    'processing' => ['title' => 'Being Packaged', 'sub' => 'Your items are being carefully packaged for delivery.'],
    'shipped'    => ['title' => 'On its Way!', 'sub' => 'Your order has been handed to our courier.'],
    'out_for_delivery' => ['title' => 'Out for Delivery', 'sub' => 'Your order is on its way to you today!'],
    'delivered'  => ['title' => 'Delivered ✓', 'sub' => 'Your order was delivered successfully. Enjoy!'],
    'cancelled'  => ['title' => 'Order Cancelled', 'sub' => 'This order has been cancelled. Contact us for help.'],
];

$current_step = $order ? ($status_steps[$order['status']] ?? 1) : 0;
$is_cancelled = $order && $order['status'] === 'cancelled';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Track Order — Frank Gadgets</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: {
        extend: {
            fontFamily: { sans: ['Inter', 'sans-serif'] },
            colors: {
                fg: {
                    blue:        '#0071e3',
                    'blue-dark': '#0058b0',
                    dark:        '#1d1d1f',
                    gray:        '#6e6e73',
                    light:       '#f5f5f7',
                    white:       '#ffffff',
                }
            }
        }
    }
}
</script>
<link rel="stylesheet" href="assets/css/style.css">
<style>
    * { -webkit-font-smoothing: antialiased; }
    .step-connector { height: 2px; flex: 1; }
    .step-done     { background: #0071e3; }
    .step-pending  { background: #e5e7eb; }
    .step-dot-done    { background: #0071e3; border-color: #0071e3; color: #fff; }
    .step-dot-current { background: #fff; border-color: #0071e3; color: #0071e3; }
    .step-dot-pending { background: #fff; border-color: #e5e7eb; color: #9ca3af; }
    .pulse-ring {
        animation: pulse-ring 1.5s ease-out infinite;
    }
    @keyframes pulse-ring {
        0%   { box-shadow: 0 0 0 0 rgba(0,113,227,0.4); }
        70%  { box-shadow: 0 0 0 10px rgba(0,113,227,0); }
        100% { box-shadow: 0 0 0 0 rgba(0,113,227,0); }
    }
</style>
</head>
<body class="bg-fg-light font-sans text-fg-dark">

<!-- ══════════ NAVBAR ══════════ -->
<nav class="navbar-blur fixed top-0 left-0 right-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
        <a href="index.php" class="flex items-center gap-2 flex-shrink-0">
            <img src="assets/images/logo.png" alt="Frank Gadgets" class="h-12 w-auto">
            <span class="font-bold text-fg-dark text-base tracking-tight">Frank Gadgets</span>
        </a>
        <div class="hidden md:flex items-center gap-6">
            <?php foreach(array_slice($nav_cats, 0, 5) as $c): ?>
            <a href="shop.php?category=<?= $c['slug'] ?>"
               class="text-sm text-fg-gray hover:text-fg-dark transition-colors"><?= htmlspecialchars($c['name']) ?></a>
            <?php endforeach; ?>
            <a href="shop.php" class="text-sm text-fg-gray hover:text-fg-dark transition-colors">All</a>
        </div>
        <div class="flex items-center gap-4">
            <a href="cart.php" class="relative p-1">
                <svg class="w-6 h-6 text-fg-dark" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
                <?php if($cart_count > 0): ?>
                <span class="cart-badge"><?= $cart_count ?></span>
                <?php endif; ?>
            </a>
            <button class="md:hidden p-1" id="menuBtn">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>
    <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-100 px-4 py-4">
        <div class="flex flex-col gap-3">
            <?php foreach($nav_cats as $c): ?>
            <a href="shop.php?category=<?= $c['slug'] ?>" class="text-sm text-fg-gray py-2 border-b border-gray-50"><?= htmlspecialchars($c['name']) ?></a>
            <?php endforeach; ?>
            <a href="shop.php" class="text-sm text-fg-blue font-medium py-2">View All →</a>
        </div>
    </div>
</nav>

<!-- ══════════ PAGE CONTENT ══════════ -->
<main class="pt-14 min-h-screen">

    <!-- Page header -->
    <div class="bg-white border-b border-gray-100">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-10">
            <div class="flex items-center gap-3 mb-1">
                <a href="index.php" class="text-xs text-fg-gray hover:text-fg-blue transition-colors">Home</a>
                <span class="text-gray-300 text-xs">›</span>
                <span class="text-xs text-fg-dark font-medium">Track Order</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-bold tracking-tight mt-4">Track Your Order</h1>
            <p class="text-fg-gray mt-2 text-sm">Enter your order number and email to get a live update on your delivery.</p>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-10 space-y-6">

        <!-- ── LOOKUP FORM ── -->
        <div class="bg-white rounded-3xl shadow-sm p-6 md:p-8">
            <h2 class="font-bold text-lg mb-5">Order Lookup</h2>
            <form method="POST" action="track.php">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="form-label">Order Number</label>
                        <input type="text" name="order_id" class="form-input"
                               placeholder="e.g. FG-20260001"
                               value="<?= htmlspecialchars($_POST['order_id'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input"
                               placeholder="you@email.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                <button type="submit" class="btn-primary w-full text-center" style="border-radius:12px; padding:13px;">
                    Track Order →
                </button>
            </form>

            <?php if ($searched && $error): ?>
            <div class="mt-4 flex items-center gap-3 bg-red-50 border border-red-100 text-red-700 rounded-2xl px-4 py-3 text-sm font-medium">
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($order): ?>
        <?php
            $status_info = $status_messages[$order['status']] ?? ['title' => ucfirst($order['status']), 'sub' => ''];
        ?>

        <!-- ── STATUS BANNER ── -->
        <div class="<?= $is_cancelled ? 'bg-red-50 border border-red-100' : 'bg-blue-50 border border-blue-100' ?> rounded-3xl p-6">
            <div class="flex items-center gap-4">
                <div class="<?= $is_cancelled ? 'bg-red-100' : 'bg-fg-blue pulse-ring' ?> rounded-full p-3 flex-shrink-0">
                    <?php if ($is_cancelled): ?>
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    <?php elseif ($order['status'] === 'delivered'): ?>
                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <?php else: ?>
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    <?php endif; ?>
                </div>
                <div>
                    <p class="font-bold text-lg <?= $is_cancelled ? 'text-red-700' : 'text-fg-dark' ?>"><?= htmlspecialchars($status_info['title']) ?></p>
                    <p class="text-sm <?= $is_cancelled ? 'text-red-600' : 'text-fg-gray' ?> mt-0.5"><?= htmlspecialchars($status_info['sub']) ?></p>
                </div>
            </div>
        </div>

        <!-- ── PROGRESS TRACKER ── -->
        <?php if (!$is_cancelled): ?>
        <div class="bg-white rounded-3xl shadow-sm p-6 md:p-8">
            <h2 class="font-bold text-base mb-8">Delivery Progress</h2>

            <!-- Step dots -->
            <div class="flex items-center">
                <?php
                $steps = [
                    ['label' => 'Order Placed',  'icon' => '📋'],
                    ['label' => 'Confirmed',      'icon' => '✅'],
                    ['label' => 'In Transit',     'icon' => '🚚'],
                    ['label' => 'Delivered',      'icon' => '🏠'],
                ];
                foreach ($steps as $i => $step):
                    $step_num = $i + 1;
                    if ($step_num < $current_step)       $dot_class = 'step-dot-done';
                    elseif ($step_num === $current_step) $dot_class = 'step-dot-current';
                    else                                 $dot_class = 'step-dot-pending';

                    $connector_class = ($step_num < $current_step) ? 'step-done' : 'step-pending';
                ?>
                <div class="flex flex-col items-center" style="min-width:52px">
                    <div class="w-10 h-10 rounded-full border-2 flex items-center justify-center font-bold text-sm transition-all duration-500 <?= $dot_class ?>">
                        <?php if ($step_num < $current_step): ?>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <?php else: ?>
                        <?= $step_num ?>
                        <?php endif; ?>
                    </div>
                    <span class="text-xs mt-2 font-medium text-center leading-tight <?= $step_num === $current_step ? 'text-fg-blue' : 'text-fg-gray' ?>" style="max-width:60px">
                        <?= $step['label'] ?>
                    </span>
                </div>
                <?php if ($i < count($steps) - 1): ?>
                <div class="step-connector <?= $connector_class ?> mb-5" style="margin:0 4px 20px;"></div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── ORDER DETAILS ── -->
        <div class="bg-white rounded-3xl shadow-sm p-6 md:p-8">
            <h2 class="font-bold text-base mb-5">Order Details</h2>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-fg-light rounded-2xl p-4">
                    <p class="text-xs text-fg-gray mb-1">Order Number</p>
                    <p class="font-bold text-sm"><?= htmlspecialchars($order['order_number'] ?? '#' . $order['id']) ?></p>
                </div>
                <div class="bg-fg-light rounded-2xl p-4">
                    <p class="text-xs text-fg-gray mb-1">Order Date</p>
                    <p class="font-bold text-sm"><?= date('M j, Y', strtotime($order['created_at'])) ?></p>
                </div>
                <div class="bg-fg-light rounded-2xl p-4">
                    <p class="text-xs text-fg-gray mb-1">Total Paid</p>
                    <p class="font-bold text-sm"><?= format_price($order['total_amount'] ?? $order['total'] ?? 0) ?></p>
                </div>
                <div class="bg-fg-light rounded-2xl p-4">
                    <p class="text-xs text-fg-gray mb-1">Status</p>
                    <p class="font-bold text-sm capitalize"><?= $status_labels[$order['status']] ?? ucfirst($order['status']) ?></p>
                </div>
            </div>

            <!-- Items -->
            <?php if (!empty($order['items'])): ?>
            <div class="border-t border-gray-100 pt-5">
                <p class="text-sm font-semibold text-fg-gray mb-4">Items in this order</p>
                <div class="space-y-3">
                    <?php foreach ($order['items'] as $item):
                        $img = $item['product_image'] ? UPLOADS_URL . $item['product_image'] : 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=100&q=80';
                    ?>
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 bg-fg-light rounded-xl overflow-hidden flex-shrink-0">
                            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>"
                                 class="w-full h-full object-cover"
                                 onerror="this.src='https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=100&q=80'">
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm leading-tight truncate"><?= htmlspecialchars($item['product_name']) ?></p>
                            <p class="text-xs text-fg-gray mt-0.5">Qty: <?= $item['quantity'] ?></p>
                        </div>
                        <p class="font-bold text-sm flex-shrink-0"><?= format_price($item['price'] * $item['quantity']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Delivery address -->
            <?php if (!empty($order['delivery_address']) || !empty($order['shipping_address'])): ?>
            <div class="border-t border-gray-100 mt-5 pt-5">
                <p class="text-sm font-semibold text-fg-gray mb-2">Delivery Address</p>
                <p class="text-sm text-fg-dark"><?= htmlspecialchars($order['delivery_address'] ?? $order['shipping_address'] ?? '') ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Receipt link -->
        <?php if (!empty($order['id'])): ?>
        <div class="bg-white rounded-3xl shadow-sm p-6 flex items-center justify-between">
            <div>
                <p class="font-semibold text-sm">Need your receipt?</p>
                <p class="text-xs text-fg-gray mt-0.5">Download or print a copy of your order receipt.</p>
            </div>
            <a href="receipt.php?order_id=<?= $order['id'] ?>" target="_blank"
               class="btn-primary text-sm flex-shrink-0" style="border-radius:12px; padding:10px 20px;">
                View Receipt
            </a>
        </div>
        <?php endif; ?>

        <?php endif; // end if ($order) ?>

        <!-- ── DELIVERY INFO ── -->
        <div class="bg-white rounded-3xl shadow-sm p-6 md:p-8">
            <h2 class="font-bold text-base mb-5">Delivery Information</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex gap-4 items-start bg-fg-light rounded-2xl p-4">
                    <span class="text-2xl">🏙️</span>
                    <div>
                        <p class="font-semibold text-sm">Lagos (Same-day)</p>
                        <p class="text-xs text-fg-gray mt-1 leading-relaxed">Orders placed before 12 pm are delivered the same day.</p>
                    </div>
                </div>
                <div class="flex gap-4 items-start bg-fg-light rounded-2xl p-4">
                    <span class="text-2xl">🌍</span>
                    <div>
                        <p class="font-semibold text-sm">Other States (1–3 days)</p>
                        <p class="text-xs text-fg-gray mt-1 leading-relaxed">Nationwide delivery. Tracking SMS sent on dispatch.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── NEED HELP? ── -->
        <div class="text-center py-4">
            <p class="text-sm text-fg-gray">Need help with your order?
                <a href="contact.php" class="text-fg-blue font-medium hover:underline">Contact us →</a>
            </p>
        </div>

    </div>
</main>

<!-- ══════════ FOOTER ══════════ -->
<footer class="bg-fg-dark text-white pt-16 pb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-10 mb-12">
            <div class="col-span-2 md:col-span-1">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-9 h-9 bg-white rounded-lg flex items-center justify-center overflow-hidden p-1 flex-shrink-0">
                        <img src="assets/images/logo.png" alt="FG" class="w-full h-full object-contain">
                    </div>
                    <span class="font-bold text-white text-base">Frank Gadgets</span>
                </div>
                <p class="text-gray-400 text-sm leading-relaxed">Nigeria's most trusted premium gadget store.</p>
            </div>
            <div>
                <h4 class="font-semibold text-sm mb-4 text-gray-300">Shop</h4>
                <div class="flex flex-col gap-2">
                    <?php foreach(array_slice($nav_cats, 0, 5) as $c): ?>
                    <a href="shop.php?category=<?= $c['slug'] ?>" class="text-gray-400 text-sm hover:text-white transition-colors"><?= htmlspecialchars($c['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h4 class="font-semibold text-sm mb-4 text-gray-300">Help</h4>
                <div class="flex flex-col gap-2">
                    <a href="track.php" class="text-white text-sm font-medium">Track Order</a>
                    <a href="contact.php" class="text-gray-400 text-sm hover:text-white transition-colors">Contact Us</a>
                </div>
            </div>
            <div>
                <h4 class="font-semibold text-sm mb-4 text-gray-300">Contact</h4>
                <div class="flex flex-col gap-2 text-gray-400 text-sm">
                    <span>Lagos, Nigeria</span>
                    <span>+234 800 FRANK GADGET</span>
                    <span>hello@frankgadgets.com</span>
                </div>
            </div>
        </div>
        <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row items-center justify-between gap-4">
            <p class="text-gray-500 text-xs">© <?= date('Y') ?> Frank Gadgets. All rights reserved.</p>
            <div class="flex items-center gap-4">
                <a href="admin/login.php" class="text-gray-600 text-xs hover:text-gray-400 transition-colors">Admin</a>
                <a href="#" class="text-gray-600 text-xs hover:text-gray-400 transition-colors">Privacy</a>
                <a href="#" class="text-gray-600 text-xs hover:text-gray-400 transition-colors">Terms</a>
            </div>
        </div>
    </div>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>