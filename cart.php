<?php
session_start();
include 'config/db.php';

$sid = mysqli_real_escape_string($conn, session_id());

// ── AJAX handlers ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $pid = intval($_POST['product_id'] ?? 0);
        $qty = max(1, intval($_POST['quantity'] ?? 1));

        // Check product exists and is active
        $prod = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE id=$pid AND status='active' LIMIT 1"));
        if (!$prod) { echo json_encode(['success'=>false,'message'=>'Product not found']); exit; }

        // Check stock
        if ($prod['stock'] > 0) {
            $existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM cart WHERE session_id='$sid' AND product_id=$pid LIMIT 1"));
            if ($existing) {
                $new_qty = min($existing['quantity'] + $qty, $prod['stock']);
                mysqli_query($conn, "UPDATE cart SET quantity=$new_qty WHERE id={$existing['id']}");
            } else {
                mysqli_query($conn, "INSERT INTO cart (session_id, product_id, quantity) VALUES ('$sid', $pid, $qty)");
            }
        }

        $count = get_cart_count($conn);
        echo json_encode(['success'=>true, 'cart_count'=>$count]);
        exit;
    }

    if ($action === 'update') {
        $cart_id = intval($_POST['cart_id'] ?? 0);
        $qty     = max(1, intval($_POST['quantity'] ?? 1));
        mysqli_query($conn, "UPDATE cart SET quantity=$qty WHERE id=$cart_id AND session_id='$sid'");
        // Return new totals
        $item = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT c.quantity, p.price
            FROM cart c JOIN products p ON c.product_id=p.id
            WHERE c.id=$cart_id AND c.session_id='$sid'
        "));
        $subtotal = $item ? $item['price'] * $item['quantity'] : 0;
        // Cart total
        $total_res = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT SUM(p.price * c.quantity) as total
            FROM cart c JOIN products p ON c.product_id=p.id
            WHERE c.session_id='$sid'
        "));
        echo json_encode([
            'success'   => true,
            'subtotal'  => number_format($subtotal, 2),
            'cart_total'=> number_format($total_res['total'] ?? 0, 2),
            'cart_count'=> get_cart_count($conn),
        ]);
        exit;
    }

    if ($action === 'remove') {
        $cart_id = intval($_POST['cart_id'] ?? 0);
        mysqli_query($conn, "DELETE FROM cart WHERE id=$cart_id AND session_id='$sid'");
        $total_res = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT SUM(p.price * c.quantity) as total
            FROM cart c JOIN products p ON c.product_id=p.id
            WHERE c.session_id='$sid'
        "));
        echo json_encode([
            'success'    => true,
            'cart_total' => number_format($total_res['total'] ?? 0, 2),
            'cart_count' => get_cart_count($conn),
        ]);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action']);
    exit;
}

// ── Fetch cart items ──────────────────────────────────────────────
$items_res = mysqli_query($conn, "
    SELECT c.id as cart_id, c.quantity,
           p.id as product_id, p.name, p.price, p.old_price, p.stock, p.status, p.brand,
           (SELECT image FROM product_images WHERE product_id=p.id AND is_main=1 LIMIT 1) as main_image
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.session_id = '$sid'
    ORDER BY c.id DESC
");
$items = [];
while ($r = mysqli_fetch_assoc($items_res)) $items[] = $r;

$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
$shipping  = $subtotal > 0 ? ($subtotal >= 100000 ? 0 : 3500) : 0;
$total     = $subtotal + $shipping;

// Nav categories
$cats_res = mysqli_query($conn, "SELECT * FROM categories ORDER BY sort_order ASC");
$all_cats = [];
while ($c = mysqli_fetch_assoc($cats_res)) $all_cats[] = $c;

$cart_count = get_cart_count($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cart — Frank Gadgets</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: { extend: {
        fontFamily: { sans: ['Inter','sans-serif'] },
        colors: { fg: { blue:'#0071e3','blue-dark':'#0058b0', dark:'#1d1d1f', gray:'#6e6e73', light:'#f5f5f7' } }
    }}
}
</script>
<style>
* { -webkit-font-smoothing: antialiased; }
.navbar-blur {
    background: rgba(255,255,255,0.85);
    backdrop-filter: saturate(180%) blur(20px);
    border-bottom: 1px solid rgba(0,0,0,0.08);
}
.cart-badge {
    position: absolute; top: -6px; right: -6px;
    background: #0071e3; color: #fff;
    font-size: 10px; font-weight: 700;
    width: 18px; height: 18px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}
.qty-btn {
    width: 32px; height: 32px; border: 1.5px solid #e5e7eb;
    border-radius: 8px; display: flex; align-items: center;
    justify-content: center; cursor: pointer; font-size: 16px;
    transition: all 0.15s; background: white; flex-shrink: 0;
}
.qty-btn:hover { border-color: #0071e3; color: #0071e3; }
.cart-item { transition: opacity 0.3s ease; }
.cart-item.removing { opacity: 0; }
.search-bar {
    background: rgba(0,0,0,0.06); border: none; border-radius: 980px;
    padding: 8px 16px 8px 38px; font-size: 14px; outline: none;
    transition: background 0.2s, width 0.3s; width: 180px;
}
.search-bar:focus { background: rgba(0,0,0,0.1); width: 240px; }
</style>
</head>
<body class="bg-fg-light font-sans text-fg-dark">

<!-- NAVBAR -->
<nav class="navbar-blur fixed top-0 left-0 right-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
        <a href="index.php" class="flex items-center flex-shrink-0">
            <img src="assets/images/logo.png" alt="Frank Gadgets" class="h-10 w-auto">
        </a>
        <div class="hidden md:flex items-center gap-6">
            <?php foreach(array_slice($all_cats,0,5) as $c): ?>
            <a href="shop.php?category=<?php echo $c['slug']; ?>" class="text-sm text-fg-gray hover:text-fg-dark transition-colors">
                <?php echo htmlspecialchars($c['name']); ?>
            </a>
            <?php endforeach; ?>
            <a href="shop.php" class="text-sm text-fg-gray hover:text-fg-dark transition-colors">All</a>
        </div>
        <div class="flex items-center gap-4">
            <div class="hidden md:flex items-center relative">
                <svg class="absolute left-3 w-4 h-4 text-fg-gray" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" placeholder="Search" class="search-bar"
                    onkeydown="if(event.key==='Enter') window.location='shop.php?q='+this.value">
            </div>
            <a href="cart.php" class="relative p-1">
                <svg class="w-6 h-6 text-fg-blue" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
                <?php if($cart_count > 0): ?>
                <span class="cart-badge" id="cart-badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</nav>

<div class="pt-14 min-h-screen">
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-10">

    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight">Your Cart</h1>
        <p class="text-fg-gray text-sm mt-1"><?php echo count($items); ?> item<?php echo count($items)!=1?'s':''; ?></p>
    </div>

    <?php if(empty($items)): ?>
    <!-- Empty cart -->
    <div class="bg-white rounded-3xl p-16 text-center shadow-sm">
        <div class="text-7xl mb-6">🛒</div>
        <h2 class="text-2xl font-bold mb-3">Your cart is empty</h2>
        <p class="text-fg-gray mb-8">Looks like you haven't added anything yet.</p>
        <a href="shop.php" class="inline-block bg-fg-blue text-white font-semibold px-8 py-3.5 rounded-xl hover:bg-fg-blue-dark transition-colors">
            Start Shopping
        </a>
    </div>

    <?php else: ?>
    <div class="flex flex-col lg:flex-row gap-6">

        <!-- Cart items -->
        <div class="flex-1 space-y-4">
            <?php foreach($items as $item):
                $img = $item['main_image'] ? UPLOADS_URL . $item['main_image'] : 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=200&q=80';
                $is_oos = $item['status'] === 'out_of_stock' || $item['stock'] == 0;
            ?>
            <div class="cart-item bg-white rounded-2xl p-4 sm:p-5 shadow-sm flex gap-4 items-start"
                 id="item-<?php echo $item['cart_id']; ?>">

                <!-- Image -->
                <a href="product.php?id=<?php echo $item['product_id']; ?>"
                   class="flex-shrink-0 w-24 h-24 bg-fg-light rounded-xl overflow-hidden flex items-center justify-center">
                    <img src="<?php echo htmlspecialchars($img); ?>"
                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                         class="w-full h-full object-cover"
                         onerror="this.src='https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=200&q=80'">
                </a>

                <!-- Details -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <?php if($item['brand']): ?>
                            <p class="text-xs text-fg-gray mb-0.5"><?php echo htmlspecialchars($item['brand']); ?></p>
                            <?php endif; ?>
                            <a href="product.php?id=<?php echo $item['product_id']; ?>"
                               class="font-semibold text-sm leading-tight hover:text-fg-blue transition-colors line-clamp-2">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </a>
                            <?php if($is_oos): ?>
                            <p class="text-xs text-red-500 font-medium mt-1">⚠ Out of stock</p>
                            <?php endif; ?>
                        </div>
                        <!-- Remove -->
                        <button onclick="removeItem(<?php echo $item['cart_id']; ?>)"
                            class="flex-shrink-0 text-fg-gray hover:text-red-500 transition-colors p-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M18 6 6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center justify-between mt-3 flex-wrap gap-3">
                        <!-- Qty stepper -->
                        <div class="flex items-center gap-2">
                            <button class="qty-btn" onclick="updateQty(<?php echo $item['cart_id']; ?>, -1, <?php echo $item['quantity']; ?>, <?php echo $item['stock']; ?>)">−</button>
                            <span class="text-sm font-semibold w-6 text-center" id="qty-<?php echo $item['cart_id']; ?>"><?php echo $item['quantity']; ?></span>
                            <button class="qty-btn" onclick="updateQty(<?php echo $item['cart_id']; ?>, 1, <?php echo $item['quantity']; ?>, <?php echo $item['stock']; ?>)">+</button>
                        </div>
                        <!-- Price -->
                        <div class="text-right">
                            <p class="font-bold text-fg-dark" id="subtotal-<?php echo $item['cart_id']; ?>">
                                <?php echo format_price($item['price'] * $item['quantity']); ?>
                            </p>
                            <?php if($item['old_price']): ?>
                            <p class="text-xs text-fg-gray line-through"><?php echo format_price($item['old_price'] * $item['quantity']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Continue shopping -->
            <a href="shop.php" class="flex items-center gap-2 text-sm text-fg-blue font-medium hover:underline pt-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                Continue Shopping
            </a>
        </div>

        <!-- Order summary -->
        <div class="lg:w-80 flex-shrink-0">
            <div class="bg-white rounded-2xl p-6 shadow-sm sticky top-20">
                <h2 class="font-bold text-lg mb-5">Order Summary</h2>

                <div class="space-y-3 text-sm mb-5">
                    <div class="flex justify-between">
                        <span class="text-fg-gray">Subtotal</span>
                        <span class="font-medium" id="cart-subtotal"><?php echo format_price($subtotal); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-fg-gray">Shipping</span>
                        <span class="font-medium" id="cart-shipping">
                            <?php echo $shipping === 0 ? '<span class="text-green-600">Free</span>' : format_price($shipping); ?>
                        </span>
                    </div>
                    <?php if($shipping > 0): ?>
                    <p class="text-xs text-fg-gray">Free shipping on orders over ₦100,000</p>
                    <?php endif; ?>
                    <div class="border-t border-gray-100 pt-3 flex justify-between">
                        <span class="font-bold">Total</span>
                        <span class="font-bold text-lg" id="cart-total"><?php echo format_price($total); ?></span>
                    </div>
                </div>

                <a href="checkout.php"
                   class="block w-full bg-fg-blue text-white text-center font-semibold py-3.5 rounded-xl hover:bg-fg-blue-dark transition-colors mb-3">
                    Proceed to Checkout
                </a>
                <p class="text-xs text-center text-fg-gray">
                    🔒 Secure checkout
                </p>

                <!-- Trust -->
                <div class="mt-5 pt-5 border-t border-gray-100 space-y-2">
                    <div class="flex items-center gap-2 text-xs text-fg-gray">
                        <span>✅</span> 100% Genuine Products
                    </div>
                    <div class="flex items-center gap-2 text-xs text-fg-gray">
                        <span>🚚</span> Fast Nationwide Delivery
                    </div>
                    <div class="flex items-center gap-2 text-xs text-fg-gray">
                        <span>🛡️</span> Full Manufacturer Warranty
                    </div>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>

</div>
</div>

<!-- FOOTER -->
<footer class="bg-fg-dark text-white pt-12 pb-6 mt-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center overflow-hidden p-1">
                    <img src="assets/images/logo.png" alt="FG" class="w-full h-full object-contain">
                </div>
                <span class="font-bold text-white">Frank Gadgets</span>
            </div>
            <p class="text-gray-500 text-xs">© 2026 Frank Gadgets. All rights reserved.</p>
        </div>
    </div>
</footer>

<script>
const CURRENCY = '₦';

function fmt(n){ return CURRENCY + parseFloat(n).toLocaleString('en-NG', {minimumFractionDigits:2, maximumFractionDigits:2}); }

function updateQty(cartId, delta, currentQty, maxStock) {
    const newQty = Math.max(1, Math.min(maxStock || 999, currentQty + delta));
    if (newQty === currentQty) return;

    fetch('cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update&cart_id=${cartId}&quantity=${newQty}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('qty-' + cartId).textContent = newQty;
            document.getElementById('subtotal-' + cartId).textContent = fmt(data.subtotal);
            document.getElementById('cart-total').textContent = fmt(data.cart_total);
            // Update button's current qty for next click
            const btns = document.querySelectorAll(`[onclick*="updateQty(${cartId},"]`);
            btns.forEach(btn => {
                const match = btn.getAttribute('onclick').match(/updateQty\(\d+, (-?\d+), \d+/);
                if (match) {
                    btn.setAttribute('onclick', btn.getAttribute('onclick').replace(
                        /updateQty\(\d+, (-?\d+), \d+/,
                        `updateQty(${cartId}, ${match[1]}, ${newQty}`
                    ));
                }
            });
            if (data.cart_count !== undefined) updateBadge(data.cart_count);
        }
    });
}

function removeItem(cartId) {
    const el = document.getElementById('item-' + cartId);
    el.classList.add('removing');
    setTimeout(() => {
        fetch('cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=remove&cart_id=${cartId}`
        })
        .then(r => r.json())
        .then(data => {
            el.remove();
            document.getElementById('cart-total').textContent = fmt(data.cart_total);
            updateBadge(data.cart_count);
            if (data.cart_count === 0) location.reload();
        });
    }, 300);
}

function updateBadge(count) {
    let badge = document.getElementById('cart-badge');
    if (count > 0) {
        if (badge) badge.textContent = count;
    } else {
        if (badge) badge.remove();
    }
}

window.addEventListener('scroll', () => {
    document.querySelector('nav').style.boxShadow =
        window.scrollY > 10 ? '0 1px 20px rgba(0,0,0,0.08)' : 'none';
});
</script>
</body>
</html>