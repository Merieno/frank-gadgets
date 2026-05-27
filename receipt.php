<?php
session_start();
include 'config/db.php';

$order_id = intval($_GET['order_id'] ?? $_GET['id'] ?? 0);
if (!$order_id) { echo 'Order not found.'; exit; }

$order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE id=$order_id LIMIT 1"));
if (!$order) { echo 'Order not found.'; exit; }

$items = mysqli_query($conn, "
    SELECT oi.*, p.name as p_name,
           (SELECT image FROM product_images WHERE product_id=p.id AND is_main=1 LIMIT 1) as product_image
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = $order_id
");

$status_labels = [
    'pending' => 'Pending', 'processing' => 'Processing',
    'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt — <?= $order['order_number'] ?> — Frank Gadgets</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Inter', sans-serif; background: #f5f5f7; color: #1d1d1f;
        -webkit-font-smoothing: antialiased; padding: 24px 16px;
    }

    .receipt {
        max-width: 640px; margin: 0 auto; background: #fff;
        border-radius: 20px; box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    /* Header */
    .receipt-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #0071e3 100%);
        padding: 40px 32px; text-align: center; color: #fff;
    }
    .receipt-logo {
        width: 48px; height: 48px; background: rgba(255,255,255,0.15);
        border-radius: 12px; display: flex; align-items: center; justify-content: center;
        margin: 0 auto 16px; font-size: 20px; font-weight: 900;
    }
    .receipt-header h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
    .receipt-header p { font-size: 13px; opacity: 0.7; }
    .receipt-order-num {
        display: inline-block; background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 980px; padding: 6px 20px; margin-top: 16px;
        font-size: 13px; font-weight: 700; letter-spacing: 0.02em;
    }

    /* Body */
    .receipt-body { padding: 32px; }
    .section-title {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.08em; color: #0071e3; margin-bottom: 12px;
    }

    /* Info grid */
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 28px; }
    .info-box { background: #f5f5f7; border-radius: 12px; padding: 14px 16px; }
    .info-box label { font-size: 10px; font-weight: 600; color: #6e6e73; text-transform: uppercase; letter-spacing: 0.06em; display: block; margin-bottom: 3px; }
    .info-box span { font-size: 14px; font-weight: 600; color: #1d1d1f; }
    .info-box.full { grid-column: 1 / -1; }

    /* Items table */
    .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .items-table th {
        font-size: 10px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.06em; color: #6e6e73; padding: 8px 0;
        border-bottom: 1.5px solid #e5e7eb; text-align: left;
    }
    .items-table th:last-child { text-align: right; }
    .items-table td { padding: 14px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; vertical-align: top; }
    .items-table td:last-child { text-align: right; font-weight: 600; }
    .item-name { font-weight: 600; color: #1d1d1f; }
    .item-qty { font-size: 12px; color: #6e6e73; margin-top: 2px; }

    /* Totals */
    .totals { border-top: 1.5px solid #e5e7eb; padding-top: 16px; margin-bottom: 28px; }
    .total-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; color: #6e6e73; }
    .total-row.grand {
        border-top: 2px solid #1d1d1f; margin-top: 8px; padding-top: 12px;
        font-size: 18px; font-weight: 900; color: #1d1d1f;
    }
    .total-row.grand .amount { color: #0071e3; }

    /* Status badge */
    .status-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 16px; border-radius: 980px;
        font-size: 12px; font-weight: 700; text-transform: capitalize;
    }
    .status-pending    { background: #fef9c3; color: #854d0e; }
    .status-processing { background: #dbeafe; color: #1e40af; }
    .status-shipped    { background: #d1fae5; color: #065f46; }
    .status-delivered  { background: #dcfce7; color: #14532d; }
    .status-cancelled  { background: #fee2e2; color: #991b1b; }

    /* Payment info */
    .payment-row {
        display: flex; justify-content: space-between; align-items: center;
        background: #f5f5f7; border-radius: 12px; padding: 14px 16px; margin-bottom: 28px;
    }
    .payment-row .label { font-size: 12px; color: #6e6e73; font-weight: 500; }
    .payment-row .value { font-size: 14px; font-weight: 700; color: #1d1d1f; text-transform: capitalize; }

    /* Divider */
    .divider { height: 1px; background: #e5e7eb; margin: 28px 0; }

    /* Footer */
    .receipt-footer {
        text-align: center; padding: 24px 32px 32px;
        border-top: 1px solid #f3f4f6;
    }
    .receipt-footer p { font-size: 12px; color: #6e6e73; line-height: 1.8; }
    .receipt-footer .store-name { font-weight: 700; color: #1d1d1f; }
    .receipt-footer .thank-you {
        font-size: 16px; font-weight: 800; color: #1d1d1f; margin-bottom: 8px;
    }

    /* Print styles */
    .print-bar {
        max-width: 640px; margin: 0 auto 16px; display: flex; gap: 8px; justify-content: flex-end;
    }
    .print-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 10px 20px; border-radius: 980px; font-size: 13px;
        font-weight: 600; cursor: pointer; border: none; font-family: inherit;
        transition: all 0.2s;
    }
    .print-btn.primary { background: #0071e3; color: #fff; }
    .print-btn.primary:hover { background: #0058b0; }
    .print-btn.ghost { background: #fff; color: #1d1d1f; border: 1.5px solid #e5e7eb; }
    .print-btn.ghost:hover { border-color: #0071e3; color: #0071e3; }

    @media print {
        body { background: white; padding: 0; }
        .receipt { box-shadow: none; border-radius: 0; }
        .print-bar { display: none; }
        .receipt-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .status-badge, .info-box, .payment-row { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
    @media (max-width: 500px) {
        .receipt-body { padding: 20px 16px; }
        .receipt-header { padding: 28px 16px; }
        .info-grid { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>

<!-- Print / Download bar -->
<div class="print-bar">
    <button class="print-btn ghost" onclick="window.history.back()">← Back</button>
    <button class="print-btn primary" onclick="window.print()">🖨️ Print Receipt</button>
</div>

<div class="receipt">

    <!-- ── HEADER ── -->
    <div class="receipt-header">
        <div class="receipt-logo">F</div>
        <h1>Frank Gadgets</h1>
        <p>Premium Tech Store · Lagos, Nigeria</p>
        <div class="receipt-order-num"><?= htmlspecialchars($order['order_number']) ?></div>
    </div>

    <!-- ── BODY ── -->
    <div class="receipt-body">

        <!-- Status -->
        <div style="text-align:center; margin-bottom:28px;">
            <span class="status-badge status-<?= $order['status'] ?>">
                <?php if($order['status'] === 'delivered'): ?>✓ <?php endif; ?>
                <?= $status_labels[$order['status']] ?? ucfirst($order['status']) ?>
            </span>
        </div>

        <!-- Order info -->
        <p class="section-title">Order Information</p>
        <div class="info-grid">
            <div class="info-box">
                <label>Order Number</label>
                <span><?= htmlspecialchars($order['order_number']) ?></span>
            </div>
            <div class="info-box">
                <label>Date</label>
                <span><?= date('M j, Y · g:i A', strtotime($order['created_at'])) ?></span>
            </div>
        </div>

        <!-- Customer info -->
        <p class="section-title">Customer</p>
        <div class="info-grid">
            <div class="info-box">
                <label>Name</label>
                <span><?= htmlspecialchars($order['customer_name']) ?></span>
            </div>
            <div class="info-box">
                <label>Phone</label>
                <span><?= htmlspecialchars($order['customer_phone']) ?></span>
            </div>
            <div class="info-box">
                <label>Email</label>
                <span style="font-size:13px;"><?= htmlspecialchars($order['customer_email']) ?></span>
            </div>
            <div class="info-box">
                <label>Delivery Address</label>
                <span style="font-size:13px;"><?= htmlspecialchars($order['shipping_address']) ?><?php if(!empty($order['city'])): ?>, <?= htmlspecialchars($order['city']) ?><?php endif; ?><?php if(!empty($order['state'])): ?>, <?= htmlspecialchars($order['state']) ?><?php endif; ?></span>
            </div>
        </div>

        <!-- Items -->
        <p class="section-title">Items Ordered</p>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th style="text-align:right;">Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($item = mysqli_fetch_assoc($items)):
                $item_total = $item['price'] * $item['quantity'];
                $name = $item['p_name'] ?? $item['product_name'] ?? 'Product';
            ?>
            <tr>
                <td>
                    <div class="item-name"><?= htmlspecialchars($name) ?></div>
                    <div class="item-qty">Qty: <?= $item['quantity'] ?> × ₦<?= number_format($item['price']) ?></div>
                </td>
                <td>₦<?= number_format($item_total) ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="total-row">
                <span>Subtotal</span>
                <span>₦<?= number_format($order['subtotal'] ?? $order['total']) ?></span>
            </div>
            <div class="total-row">
                <span>Shipping</span>
                <span><?= (isset($order['shipping_fee']) && $order['shipping_fee'] > 0) ? '₦' . number_format($order['shipping_fee']) : 'Free' ?></span>
            </div>
            <div class="total-row grand">
                <span>Total</span>
                <span class="amount">₦<?= number_format($order['total']) ?></span>
            </div>
        </div>

        <!-- Payment -->
        <div class="payment-row">
            <div>
                <div class="label">Payment Method</div>
                <div class="value"><?= str_replace('_', ' ', $order['payment_method'] ?? 'N/A') ?></div>
            </div>
            <div style="text-align:right;">
                <div class="label">Payment Status</div>
                <div class="value" style="color:#16a34a;">Paid</div>
            </div>
        </div>

        <?php if (!empty($order['notes'])): ?>
        <p class="section-title">Order Notes</p>
        <div class="info-box full" style="margin-bottom:20px;">
            <span style="font-size:13px; font-weight:500;"><?= htmlspecialchars($order['notes']) ?></span>
        </div>
        <?php endif; ?>

    </div>

    <!-- ── FOOTER ── -->
    <div class="receipt-footer">
        <p class="thank-you">Thank you for shopping with us! 🎉</p>
        <p>
            <span class="store-name">Frank Gadgets</span><br>
            Lagos, Nigeria<br>
            +234 800 FRANK GADGET · hello@frankgadgets.com<br><br>
            <span style="font-size:11px; color:#9ca3af;">
                Receipt generated on <?= date('M j, Y \a\t g:i A') ?><br>
                This is an electronic receipt — no signature required.
            </span>
        </p>
    </div>

</div>

</body>
</html>