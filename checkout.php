<?php
session_start();
include 'config/db.php';

$sid = mysqli_real_escape_string($conn, session_id());

// Redirect if cart empty
$cart_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM cart WHERE session_id='$sid'"));
if (!$cart_check['cnt']) { header('Location: cart.php'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = clean($conn, $_POST['name']    ?? '');
    $email   = clean($conn, $_POST['email']   ?? '');
    $phone   = clean($conn, $_POST['phone']   ?? '');
    $address = clean($conn, $_POST['address'] ?? '');
    $city    = clean($conn, $_POST['city']    ?? '');
    $state   = clean($conn, $_POST['state']   ?? '');
    $notes   = clean($conn, $_POST['notes']   ?? '');
    $payment = clean($conn, $_POST['payment'] ?? 'pay_on_delivery');

    if (!$name)    $errors[] = 'Full name is required.';
    if (!$email || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!$phone)   $errors[] = 'Phone number is required.';
    if (!$address) $errors[] = 'Address is required.';
    if (!$city)    $errors[] = 'City is required.';
    if (!$state)   $errors[] = 'State is required.';

    if (empty($errors)) {
        // Fetch cart items
        $items_res = mysqli_query($conn, "
            SELECT c.quantity, p.id as product_id, p.name, p.price
            FROM cart c JOIN products p ON c.product_id=p.id
            WHERE c.session_id='$sid'
        ");
        $order_items = [];
        $subtotal = 0;
        while ($r = mysqli_fetch_assoc($items_res)) {
            $order_items[] = $r;
            $subtotal += $r['price'] * $r['quantity'];
        }
        $shipping = 0;
        $total = $subtotal; // Shipping added later
        $order_no = generate_order_number();

        // Insert order — using only columns that exist in your table
        $insert = mysqli_query($conn, "
            INSERT INTO orders (order_number, customer_name, customer_email, customer_phone,
                shipping_address, city, state, notes, payment_method,
                subtotal, shipping_fee, total, status)
            VALUES ('$order_no', '$name', '$email', '$phone',
                '$address', '$city', '$state', '$notes', '$payment',
                $subtotal, $shipping, $total, 'pending')
        ");

        if (!$insert) {
            // If those columns don't exist yet, try minimal insert
            mysqli_query($conn, "
                INSERT INTO orders (order_number, subtotal, total, payment_method)
                VALUES ('$order_no', $subtotal, $total, '$payment')
            ");
        }

        $order_id = mysqli_insert_id($conn);

        // Insert order items
        foreach ($order_items as $oi) {
            $pid   = $oi['product_id'];
            $oname = mysqli_real_escape_string($conn, $oi['name']);
            mysqli_query($conn, "
                INSERT INTO order_items (order_id, product_id, product_name, quantity, price)
                VALUES ($order_id, $pid, '$oname', {$oi['quantity']}, {$oi['price']})
            ");
            mysqli_query($conn, "UPDATE products SET stock = stock - {$oi['quantity']} WHERE id=$pid AND stock > 0");
        }

        // Clear cart
        mysqli_query($conn, "DELETE FROM cart WHERE session_id='$sid'");

        header("Location: order-success.php?order=$order_no");
        exit;
    }
}

// Fetch cart for display
$items_res = mysqli_query($conn, "
    SELECT c.quantity, p.name, p.price,
           (SELECT image FROM product_images WHERE product_id=p.id AND is_main=1 LIMIT 1) as main_image
    FROM cart c JOIN products p ON c.product_id=p.id
    WHERE c.session_id='$sid'
");
$items    = [];
$subtotal = 0;
while ($r = mysqli_fetch_assoc($items_res)) { $items[] = $r; $subtotal += $r['price'] * $r['quantity']; }
$shipping = 0;
$total = $subtotal; // Shipping added later

$cats_res = mysqli_query($conn, "SELECT * FROM categories ORDER BY sort_order ASC");
$all_cats = [];
while ($c = mysqli_fetch_assoc($cats_res)) $all_cats[] = $c;

$nigeria_states = ['Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno',
    'Cross River','Delta','Ebonyi','Edo','Ekiti','Enugu','FCT - Abuja','Gombe','Imo',
    'Jigawa','Kaduna','Kano','Katsina','Kebbi','Kogi','Kwara','Lagos','Nasarawa',
    'Niger','Ogun','Ondo','Osun','Oyo','Plateau','Rivers','Sokoto','Taraba','Yobe','Zamfara'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout — Frank Gadgets</title>
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
.form-input {
    width: 100%; border: 1.5px solid #e5e7eb; border-radius: 12px;
    padding: 12px 14px; font-size: 14px; outline: none;
    transition: border-color 0.2s; background: white; font-family: inherit;
}
.form-input:focus { border-color: #0071e3; }
.form-label { font-size: 13px; font-weight: 600; color: #1d1d1f; margin-bottom: 6px; display: block; }
.payment-option { border: 1.5px solid #e5e7eb; border-radius: 12px; padding: 14px 16px; cursor: pointer; transition: all 0.2s; }
.payment-option:has(input:checked) { border-color: #0071e3; background: #f0f7ff; }
.step-badge { width: 28px; height: 28px; background: #0071e3; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
</style>
</head>
<body class="bg-fg-light font-sans text-fg-dark">

<nav class="navbar-blur fixed top-0 left-0 right-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
        <a href="index.php" class="flex items-center flex-shrink-0">
            <img src="assets/images/logo.png" alt="Frank Gadgets" class="h-10 w-auto">
        </a>
        <div class="hidden md:flex items-center gap-2 text-xs font-medium">
            <span class="text-fg-blue">🛒 Cart</span>
            <span class="text-fg-gray mx-2">›</span>
            <span class="text-fg-dark font-semibold">📋 Checkout</span>
            <span class="text-fg-gray mx-2">›</span>
            <span class="text-fg-gray">✅ Confirmation</span>
        </div>
        <a href="cart.php" class="text-sm text-fg-blue font-medium hover:underline">← Back to Cart</a>
    </div>
</nav>

<div class="pt-14 min-h-screen">
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-10">

    <h1 class="text-3xl font-bold tracking-tight mb-8">Checkout</h1>

    <?php if(!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 rounded-2xl p-4 mb-6">
        <p class="font-semibold text-red-700 text-sm mb-2">Please fix the following:</p>
        <ul class="list-disc list-inside space-y-1">
            <?php foreach($errors as $e): ?>
            <li class="text-red-600 text-sm"><?php echo htmlspecialchars($e); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" id="checkout-form">
    <div class="flex flex-col lg:flex-row gap-8">

        <div class="flex-1 space-y-6">

            <!-- Contact -->
            <div class="bg-white rounded-2xl p-6 shadow-sm">
                <div class="flex items-center gap-3 mb-5">
                    <div class="step-badge">1</div>
                    <h2 class="font-bold text-base">Contact Information</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($_POST['name']??''); ?>" placeholder="John Doe" required>
                    </div>
                    <div>
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($_POST['email']??''); ?>" placeholder="john@email.com" required>
                    </div>
                    <div>
                        <label class="form-label">Phone *</label>
                        <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($_POST['phone']??''); ?>" placeholder="+234 800 000 0000" required>
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="bg-white rounded-2xl p-6 shadow-sm">
                <div class="flex items-center gap-3 mb-5">
                    <div class="step-badge">2</div>
                    <h2 class="font-bold text-base">Delivery Address</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="form-label">Street Address *</label>
                        <input type="text" name="address" class="form-input" value="<?php echo htmlspecialchars($_POST['address']??''); ?>" placeholder="12 Example Street, Lekki" required>
                    </div>
                    <div>
                        <label class="form-label">City *</label>
                        <input type="text" name="city" class="form-input" value="<?php echo htmlspecialchars($_POST['city']??''); ?>" placeholder="Lagos" required>
                    </div>
                    <div>
                        <label class="form-label">State *</label>
                        <select name="state" class="form-input" required>
                            <option value="">Select state</option>
                            <?php foreach($nigeria_states as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo ($_POST['state']??'')===$st?'selected':''; ?>><?php echo $st; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label">Order Notes <span class="text-fg-gray font-normal">(optional)</span></label>
                        <textarea name="notes" class="form-input" rows="3" placeholder="Special delivery instructions..."><?php echo htmlspecialchars($_POST['notes']??''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Payment -->
            <div class="bg-white rounded-2xl p-6 shadow-sm">
                <div class="flex items-center gap-3 mb-5">
                    <div class="step-badge">3</div>
                    <h2 class="font-bold text-base">Payment Method</h2>
                </div>
                <div class="space-y-3">
                    <label class="payment-option block">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="payment" value="pay_on_delivery" <?php echo ($_POST['payment']??'pay_on_delivery')==='pay_on_delivery'?'checked':''; ?> class="accent-fg-blue w-4 h-4">
                            <div>
                                <p class="font-semibold text-sm">Pay on Delivery</p>
                                <p class="text-xs text-fg-gray">Cash or POS when your order arrives</p>
                            </div>
                            <span class="ml-auto text-2xl">💵</span>
                        </div>
                    </label>
                    <label class="payment-option block">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="payment" value="bank_transfer" <?php echo ($_POST['payment']??'')==='bank_transfer'?'checked':''; ?> class="accent-fg-blue w-4 h-4">
                            <div>
                                <p class="font-semibold text-sm">Bank Transfer</p>
                                <p class="text-xs text-fg-gray">Transfer to our account before delivery</p>
                            </div>
                            <span class="ml-auto text-2xl">🏦</span>
                        </div>
                    </label>
                </div>
            </div>

        </div>

        <!-- Order summary -->
        <div class="lg:w-80 flex-shrink-0">
            <div class="bg-white rounded-2xl p-6 shadow-sm sticky top-20">
                <h2 class="font-bold text-base mb-5">Order Summary</h2>
                <div class="space-y-3 mb-5">
                    <?php foreach($items as $item):
                        $img = $item['main_image'] ? UPLOADS_URL . $item['main_image'] : 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=100&q=80';
                    ?>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-fg-light rounded-lg overflow-hidden flex-shrink-0">
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium line-clamp-1"><?php echo htmlspecialchars($item['name']); ?></p>
                            <p class="text-xs text-fg-gray">Qty: <?php echo $item['quantity']; ?></p>
                        </div>
                        <span class="text-sm font-semibold flex-shrink-0"><?php echo format_price($item['price'] * $item['quantity']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="border-t border-gray-100 pt-4 space-y-2 text-sm mb-5">
                    <div class="flex justify-between">
                        <span class="text-fg-gray">Subtotal</span>
                        <span><?php echo format_price($subtotal); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-fg-gray">Shipping</span>
                        <span class="text-amber-600 font-medium">To be confirmed</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-100">
                        <span class="font-bold">Total</span>
                        <span class="font-bold text-lg text-fg-blue"><?php echo format_price($total); ?></span>
                    </div>
                </div>
                <button type="submit" class="w-full bg-fg-blue text-white font-semibold py-4 rounded-xl hover:bg-fg-blue-dark transition-colors text-sm">
                    Place Order →
                </button>
                <p class="text-xs text-center text-fg-gray mt-3">🔒 Your information is secure</p>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mt-3">
                    <p class="text-xs text-amber-700 font-medium text-center">📦 Delivery fee will be confirmed when we call you</p>
                </div>
            </div>
        </div>

    </div>
    </form>
</div>
</div>

<footer class="bg-fg-dark text-white pt-10 pb-6 mt-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center overflow-hidden p-1">
                <img src="assets/images/logo.png" alt="FG" class="w-full h-full object-contain">
            </div>
            <span class="font-bold text-white text-sm">Frank Gadgets</span>
        </div>
        <p class="text-gray-500 text-xs">© <?php echo date('Y'); ?> Frank Gadgets. All rights reserved.</p>
    </div>
</footer>

<script>
window.addEventListener('scroll', () => {
    document.querySelector('nav').style.boxShadow =
        window.scrollY > 10 ? '0 1px 20px rgba(0,0,0,0.08)' : 'none';
});
</script>
</body>
</html>