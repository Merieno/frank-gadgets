<?php
session_start();
include 'config/db.php';

$order_no = clean($conn, $_GET['order'] ?? '');
if (!$order_no) { header('Location: index.php'); exit; }

$order = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM orders WHERE order_number='$order_no' LIMIT 1"
));
if (!$order) { header('Location: index.php'); exit; }

$items_res = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id={$order['id']}");
$items = [];
while ($r = mysqli_fetch_assoc($items_res)) $items[] = $r;

$is_bank_transfer = ($order['payment_method'] === 'bank_transfer');
$is_confirmed     = in_array($order['status'] ?? 'pending', ['processing','shipped','delivered']);

// Build WhatsApp messages
$wa_items = implode(', ', array_map(fn($i) => ($i['product_name'] ?? $i['name'] ?? ''), $items));
$wa_number = '2347066293035';

$wa_pod_msg = urlencode(
    "Hi Frank Gadgets! I just placed order " . $order['order_number'] .
    " for " . format_price($order['total']) .
    ".\nItems: " . $wa_items .
    "\nName: " . $order['customer_name'] .
    "\nPhone: " . $order['customer_phone'] .
    "\nAddress: " . ($order['shipping_address']??'') . ', ' . ($order['city']??'') . ', ' . ($order['state']??'') .
    "\nPayment: Pay on Delivery\nPlease confirm my order."
);

$wa_bank_msg = urlencode(
    "Hi Frank Gadgets! I just transferred for order " . $order['order_number'] .
    " (" . format_price($order['total']) . ")." .
    "\nName: " . $order['customer_name'] .
    "\nPlease confirm receipt and process my order."
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order <?php echo $is_bank_transfer && !$is_confirmed ? 'Placed' : 'Confirmed'; ?> — Frank Gadgets</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { -webkit-font-smoothing: antialiased; }
body { font-family: 'Inter', sans-serif; background: #f5f5f7; color: #1d1d1f; }
.navbar-blur { background: rgba(255,255,255,0.9); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(0,0,0,0.08); }
@keyframes checkPop { 0%{transform:scale(0);opacity:0} 60%{transform:scale(1.15)} 100%{transform:scale(1);opacity:1} }
.check-anim { animation: checkPop 0.6s ease forwards; }
@keyframes fadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
.fade-up  { animation: fadeUp 0.5s ease forwards; }
.delay-1  { animation-delay: 0.15s; opacity: 0; }
.delay-2  { animation-delay: 0.3s;  opacity: 0; }
.delay-3  { animation-delay: 0.45s; opacity: 0; }
.palmpay-card { background: linear-gradient(135deg, #0a5c36 0%, #0d7a48 100%); border-radius: 24px; padding: 28px; color: white; box-shadow: 0 8px 32px rgba(10,92,54,0.35); }
.account-num { font-size: 36px; font-weight: 900; letter-spacing: 4px; color: white; }
.copy-btn { background: rgba(255,255,255,0.2); color: white; border: 1.5px solid rgba(255,255,255,0.4); padding: 8px 20px; border-radius: 980px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.copy-btn:hover { background: rgba(255,255,255,0.35); }
.copy-btn.copied { background: #16a34a; border-color: #16a34a; }
.amount-box { background: rgba(255,255,255,0.15); border: 1.5px dashed rgba(255,255,255,0.5); border-radius: 16px; padding: 16px 20px; text-align: center; }
.receipt-card { background: white; border-radius: 24px; overflow: hidden; box-shadow: 0 2px 16px rgba(0,0,0,0.06); }
.wa-btn { background: #25d366; color: white; display: flex; align-items: center; justify-content: center; gap: 10px; padding: 16px; border-radius: 16px; font-weight: 700; font-size: 15px; text-decoration: none; transition: background 0.2s, transform 0.2s; width: 100%; box-sizing: border-box; }
.wa-btn:hover { background: #1fbb58; transform: translateY(-1px); }
@media print {
    .no-print { display: none !important; }
    body { background: white !important; }
    .receipt-card { box-shadow: none !important; border: 1px solid #e5e7eb !important; }
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar-blur fixed top-0 left-0 right-0 z-50 no-print">
    <div style="max-width:700px;margin:0 auto;padding:0 20px;display:flex;align-items:center;justify-content:space-between;height:56px;">
        <a href="index.php"><img src="assets/images/logo.png" alt="Frank Gadgets" style="height:40px;width:auto;"></a>
        <a href="shop.php" style="font-size:14px;color:#0071e3;font-weight:600;text-decoration:none;">Continue Shopping →</a>
    </div>
</nav>

<div style="padding-top:56px;min-height:100vh;">
<div style="max-width:640px;margin:0 auto;padding:40px 20px 80px;">

<?php if($is_bank_transfer && !$is_confirmed): ?>
<!-- ═══ BANK TRANSFER PENDING ═══ -->

    <div style="text-align:center;margin-bottom:32px;" class="fade-up">
        <div style="width:72px;height:72px;background:#fef9c3;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px;">🏦</div>
        <h1 style="font-size:28px;font-weight:900;margin:0 0 8px;">Order Placed!</h1>
        <p style="color:#6e6e73;margin:0;">Complete your payment to confirm,<br><strong style="color:#1d1d1f;"><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
    </div>

    <div style="background:white;border-radius:16px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 1px 8px rgba(0,0,0,0.06);" class="fade-up delay-1">
        <div>
            <p style="font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#6e6e73;margin:0 0 4px;">Your Order Number</p>
            <p style="font-size:18px;font-weight:900;color:#0071e3;margin:0;letter-spacing:1px;"><?php echo $order['order_number']; ?></p>
        </div>
        <span style="background:#fef9c3;color:#854d0e;font-size:12px;font-weight:700;padding:4px 12px;border-radius:980px;">Awaiting Payment</span>
    </div>

    <!-- PalmPay Card -->
    <div class="palmpay-card fade-up delay-1" style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <div>
                <p style="font-size:12px;opacity:0.7;margin:0 0 4px;text-transform:uppercase;letter-spacing:0.08em;">Pay to</p>
                <p style="font-size:22px;font-weight:800;margin:0;">PalmPay</p>
            </div>
            <div style="background:white;border-radius:12px;padding:10px 16px;">
                <span style="color:#0a5c36;font-weight:900;font-size:16px;">PP</span>
            </div>
        </div>
        <div style="margin-bottom:20px;">
            <p style="font-size:11px;opacity:0.7;margin:0 0 8px;text-transform:uppercase;letter-spacing:0.08em;">Account Number</p>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <span class="account-num">7066293035</span>
                <button onclick="copyNumber(this)" class="copy-btn">Copy</button>
            </div>
        </div>
        <div style="margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.2);">
            <p style="font-size:11px;opacity:0.7;margin:0 0 4px;text-transform:uppercase;letter-spacing:0.08em;">Account Name</p>
            <p style="font-size:18px;font-weight:700;margin:0;">Terence Oguname</p>
        </div>
        <div class="amount-box">
            <p style="font-size:12px;opacity:0.8;margin:0 0 6px;text-transform:uppercase;letter-spacing:0.08em;">Transfer This Amount</p>
            <p style="font-size:36px;font-weight:900;margin:0;letter-spacing:1px;"><?php echo format_price($order['total']); ?></p>
            <p style="font-size:12px;opacity:0.7;margin:8px 0 0;">⚠️ Product price only · Delivery fee confirmed on call</p>
        </div>
    </div>

    <!-- Steps -->
    <div style="background:white;border-radius:20px;padding:24px;margin-bottom:24px;box-shadow:0 1px 8px rgba(0,0,0,0.06);" class="fade-up delay-2">
        <p style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#6e6e73;margin:0 0 16px;">After you transfer:</p>
        <div style="display:flex;flex-direction:column;gap:14px;">
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <div style="width:28px;height:28px;background:#0071e3;color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;">1</div>
                <p style="margin:0;font-size:14px;line-height:1.5;">Take a screenshot of your transfer receipt</p>
            </div>
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <div style="width:28px;height:28px;background:#0071e3;color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;">2</div>
                <p style="margin:0;font-size:14px;line-height:1.5;">Send it on WhatsApp with your order number <strong><?php echo $order['order_number']; ?></strong></p>
            </div>
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <div style="width:28px;height:28px;background:#0071e3;color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;">3</div>
                <p style="margin:0;font-size:14px;line-height:1.5;">We confirm and dispatch within 2 hours</p>
            </div>
        </div>
    </div>

    <!-- WhatsApp (Bank Transfer) -->
    <a href="https://wa.me/<?php echo $wa_number; ?>?text=<?php echo $wa_bank_msg; ?>"
       target="_blank" class="wa-btn fade-up delay-2" style="margin-bottom:12px;">
        <svg width="22" height="22" fill="white" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        Send Payment Proof on WhatsApp
    </a>
    <a href="shop.php" style="display:block;text-align:center;background:#f5f5f7;color:#1d1d1f;font-weight:600;font-size:14px;padding:14px;border-radius:14px;text-decoration:none;" class="fade-up delay-3">
        Back to Shop
    </a>

<?php else: ?>
<!-- ═══ ORDER CONFIRMED ═══ -->

    <div style="text-align:center;margin-bottom:32px;" class="fade-up">
        <div class="check-anim" style="width:80px;height:80px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
            <svg width="40" height="40" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h1 style="font-size:32px;font-weight:900;margin:0 0 8px;">Order Confirmed! 🎉</h1>
        <p style="color:#6e6e73;margin:0;font-size:16px;">Thank you, <strong style="color:#1d1d1f;"><?php echo htmlspecialchars($order['customer_name']); ?></strong>! Your order is confirmed.</p>
    </div>

    <!-- Receipt -->
    <div class="receipt-card fade-up delay-1" style="margin-bottom:20px;">
        <div style="background:#0071e3;padding:20px 24px;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <p style="color:rgba(255,255,255,0.7);font-size:11px;text-transform:uppercase;letter-spacing:0.08em;margin:0 0 4px;">Order Number</p>
                <p style="color:white;font-weight:900;font-size:20px;letter-spacing:2px;margin:0;"><?php echo $order['order_number']; ?></p>
            </div>
            <span style="background:white;color:#0071e3;font-size:12px;font-weight:700;padding:5px 14px;border-radius:980px;"><?php echo ucfirst($order['status'] ?? 'Pending'); ?></span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1px;background:#f3f4f6;">
            <div style="background:white;padding:16px 20px;"><p style="font-size:11px;color:#6e6e73;margin:0 0 4px;">Name</p><p style="font-size:14px;font-weight:600;margin:0;"><?php echo htmlspecialchars($order['customer_name']); ?></p></div>
            <div style="background:white;padding:16px 20px;"><p style="font-size:11px;color:#6e6e73;margin:0 0 4px;">Phone</p><p style="font-size:14px;font-weight:600;margin:0;"><?php echo htmlspecialchars($order['customer_phone']); ?></p></div>
            <div style="background:white;padding:16px 20px;"><p style="font-size:11px;color:#6e6e73;margin:0 0 4px;">Email</p><p style="font-size:14px;font-weight:600;margin:0;"><?php echo htmlspecialchars($order['customer_email']); ?></p></div>
            <div style="background:white;padding:16px 20px;"><p style="font-size:11px;color:#6e6e73;margin:0 0 4px;">Payment</p><p style="font-size:14px;font-weight:600;margin:0;text-transform:capitalize;"><?php echo str_replace('_',' ',$order['payment_method']); ?></p></div>
            <div style="background:white;padding:16px 20px;grid-column:1/3;"><p style="font-size:11px;color:#6e6e73;margin:0 0 4px;">Delivery Address</p><p style="font-size:14px;font-weight:600;margin:0;"><?php echo htmlspecialchars(($order['shipping_address']??'').', '.($order['city']??'').', '.($order['state']??'')); ?></p></div>
        </div>
        <div style="padding:20px 24px;">
            <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#6e6e73;margin:0 0 16px;">Items Ordered</p>
            <?php foreach($items as $item): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;background:#f5f5f7;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#6e6e73;flex-shrink:0;">×<?php echo $item['quantity']; ?></div>
                    <p style="font-size:14px;font-weight:500;margin:0;"><?php echo htmlspecialchars($item['product_name'] ?? $item['name'] ?? ''); ?></p>
                </div>
                <span style="font-size:14px;font-weight:700;white-space:nowrap;"><?php echo format_price($item['price'] * $item['quantity']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="border-top:1px solid #f3f4f6;padding:16px 24px;">
            <div style="display:flex;justify-content:space-between;font-size:14px;color:#6e6e73;margin-bottom:8px;"><span>Subtotal</span><span><?php echo format_price($order['subtotal']); ?></span></div>
            <div style="display:flex;justify-content:space-between;font-size:14px;margin-bottom:12px;"><span style="color:#6e6e73;">Shipping</span><span style="color:#d97706;font-weight:600;">To be confirmed</span></div>
            <div style="display:flex;justify-content:space-between;font-size:18px;font-weight:800;border-top:1px solid #f3f4f6;padding-top:12px;"><span>Total</span><span style="color:#0071e3;"><?php echo format_price($order['total']); ?></span></div>
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;margin-top:12px;">
                <p style="font-size:12px;color:#92400e;font-weight:600;margin:0;">📦 Delivery fee confirmed when we call · Pay product price first</p>
            </div>
        </div>
        <div style="background:#f5f5f7;padding:12px 24px;text-align:center;font-size:12px;color:#6e6e73;">
            Order placed <?php echo date('d F Y, H:i', strtotime($order['created_at'])); ?> · Frank Gadgets · hello@frankgadgets.com
        </div>
    </div>

    <!-- Buttons -->
    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:24px;" class="no-print fade-up delay-2">
        <button onclick="window.print()" style="background:#1d1d1f;color:white;font-weight:700;font-size:14px;padding:16px;border-radius:14px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;width:100%;">
            <svg width="18" height="18" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download / Print Receipt
        </button>
        <div style="display:flex;gap:12px;">
            <a href="shop.php" style="flex:1;background:#0071e3;color:white;font-weight:700;font-size:14px;padding:16px;border-radius:14px;text-align:center;text-decoration:none;">Continue Shopping</a>
            <a href="index.php" style="flex:1;background:white;color:#1d1d1f;font-weight:700;font-size:14px;padding:16px;border-radius:14px;text-align:center;text-decoration:none;border:1.5px solid #e5e7eb;">Back to Home</a>
        </div>
    </div>

    <!-- What happens next (Pay on Delivery) -->
    <?php if(!$is_bank_transfer): ?>
    <div style="background:white;border-radius:20px;padding:24px;margin-bottom:20px;box-shadow:0 1px 8px rgba(0,0,0,0.06);" class="no-print fade-up delay-2">
        <p style="font-weight:800;font-size:16px;margin:0 0 20px;">What happens next?</p>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div style="display:flex;align-items:flex-start;gap:12px;"><div style="width:36px;height:36px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">📞</div><div><p style="font-weight:600;font-size:14px;margin:0 0 4px;">We'll call to confirm</p><p style="font-size:13px;color:#6e6e73;margin:0;">Our team will call <?php echo htmlspecialchars($order['customer_phone']); ?> to confirm your order and delivery fee.</p></div></div>
            <div style="display:flex;align-items:flex-start;gap:12px;"><div style="width:36px;height:36px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">📦</div><div><p style="font-weight:600;font-size:14px;margin:0 0 4px;">We'll pack your order</p><p style="font-size:13px;color:#6e6e73;margin:0;">Items carefully packed and prepared for dispatch.</p></div></div>
            <div style="display:flex;align-items:flex-start;gap:12px;"><div style="width:36px;height:36px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">🚚</div><div><p style="font-weight:600;font-size:14px;margin:0 0 4px;">Fast delivery to your door</p><p style="font-size:13px;color:#6e6e73;margin:0;">Lagos: same/next day. Other states: 2–5 business days.</p></div></div>
        </div>
    </div>

    <!-- WhatsApp button (Pay on Delivery) -->
    <div class="no-print fade-up delay-3">
        <a href="https://wa.me/<?php echo $wa_number; ?>?text=<?php echo $wa_pod_msg; ?>"
           target="_blank" class="wa-btn" style="margin-bottom:8px;">
            <svg width="22" height="22" fill="white" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            Confirm My Order on WhatsApp
        </a>
        <p style="text-align:center;font-size:12px;color:#6e6e73;margin:8px 0 0;">Tap to send your full order details to us directly</p>
    </div>
    <?php endif; ?>

<?php endif; ?>

</div>
</div>

<footer style="background:#1d1d1f;color:white;padding:32px 20px;" class="no-print">
    <div style="max-width:640px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;background:white;border-radius:8px;overflow:hidden;padding:3px;">
                <img src="assets/images/logo.png" alt="FG" style="width:100%;height:100%;object-fit:contain;">
            </div>
            <span style="font-weight:700;font-size:15px;">Frank Gadgets</span>
        </div>
        <p style="color:#6b7280;font-size:12px;margin:0;">© <?php echo date('Y'); ?> Frank Gadgets. All rights reserved.</p>
    </div>
</footer>

<script>
function copyNumber(btn) {
    navigator.clipboard.writeText('7066293035').then(function() {
        btn.classList.add('copied');
        btn.textContent = '✓ Copied!';
        setTimeout(function() { btn.classList.remove('copied'); btn.textContent = 'Copy'; }, 2500);
    }).catch(function() {
        const el = document.createElement('textarea');
        el.value = '7066293035';
        document.body.appendChild(el); el.select();
        document.execCommand('copy'); document.body.removeChild(el);
        btn.textContent = '✓ Copied!';
        setTimeout(function() { btn.textContent = 'Copy'; }, 2500);
    });
}
window.addEventListener('scroll', function() {
    const nav = document.querySelector('nav');
    if (nav) nav.style.boxShadow = window.scrollY > 10 ? '0 1px 20px rgba(0,0,0,0.08)' : 'none';
});
</script>
</body>
</html>