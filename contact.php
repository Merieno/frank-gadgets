<?php
session_start();
include 'config/db.php';

// Get categories for navbar
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
$success = false;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $message === '') {
        $error = 'Please fill in your name, email, and message.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if messages table exists, create if not
        $table_check = $conn->query("SHOW TABLES LIKE 'messages'");
        if ($table_check->num_rows === 0) {
            $conn->query("
                CREATE TABLE messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(200) NOT NULL,
                    email VARCHAR(200) NOT NULL,
                    phone VARCHAR(50) DEFAULT NULL,
                    subject VARCHAR(200) DEFAULT NULL,
                    message TEXT NOT NULL,
                    is_read TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }

        $stmt = $conn->prepare("INSERT INTO messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $name, $email, $phone, $subject, $message);

        if ($stmt->execute()) {
            $success = true;

            // Optional: send email notification
            // Uncomment and set your email:
            /*
            $to      = 'hello@frankgadgets.com';
            $subj    = 'New Contact Message: ' . ($subject ?: 'No subject');
            $body    = "Name: $name\nEmail: $email\nPhone: $phone\n\nMessage:\n$message";
            $headers = "From: noreply@frankgadgets.com\r\nReply-To: $email";
            mail($to, $subj, $body, $headers);
            */
        } else {
            $error = 'Failed to send message. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact Us — Frank Gadgets</title>
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
                <span class="text-xs text-fg-dark font-medium">Contact Us</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-bold tracking-tight mt-4">Contact Us</h1>
            <p class="text-fg-gray mt-2 text-sm">Our team is available 7 days a week. We'd love to hear from you.</p>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-10 space-y-6">

        <!-- ── CONTACT CARDS ── -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <a href="https://wa.me/2348001234528" target="_blank"
               class="bg-white rounded-3xl shadow-sm p-6 text-center hover:shadow-md transition-shadow group">
                <div class="w-14 h-14 bg-green-50 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-105 transition-transform">
                    <svg class="w-7 h-7 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </div>
                <h3 class="font-bold text-sm mb-1">WhatsApp</h3>
                <p class="text-xs text-fg-gray mb-2">Chat instantly</p>
                <span class="text-xs text-fg-blue font-semibold">Message us →</span>
            </a>

            <a href="mailto:hello@frankgadgets.com"
               class="bg-white rounded-3xl shadow-sm p-6 text-center hover:shadow-md transition-shadow group">
                <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-105 transition-transform">
                    <svg class="w-7 h-7 text-fg-blue" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                </div>
                <h3 class="font-bold text-sm mb-1">Email</h3>
                <p class="text-xs text-fg-gray mb-2">Reply within 24h</p>
                <span class="text-xs text-fg-blue font-semibold">hello@frankgadgets.com</span>
            </a>

            <div class="bg-white rounded-3xl shadow-sm p-6 text-center">
                <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <h3 class="font-bold text-sm mb-1">Visit Us</h3>
                <p class="text-xs text-fg-gray mb-2">Lagos, Nigeria</p>
                <span class="text-xs text-fg-gray">Mon–Sat, 9am – 6pm</span>
            </div>
        </div>

        <!-- ── SUCCESS MESSAGE ── -->
        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-100 rounded-3xl p-6 flex items-center gap-4">
            <div class="bg-green-500 rounded-full p-2 flex-shrink-0">
                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            </div>
            <div>
                <p class="font-bold text-green-800">Message sent!</p>
                <p class="text-sm text-green-700 mt-0.5">Thanks for reaching out. We'll get back to you within 24 hours.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── CONTACT FORM ── -->
        <div class="bg-white rounded-3xl shadow-sm p-6 md:p-8">
            <h2 class="font-bold text-lg mb-6">Send Us a Message</h2>

            <form method="POST" action="contact.php">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-input" placeholder="Your name"
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-input" placeholder="you@email.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="form-label">Subject</label>
                        <select name="subject" class="form-input" style="appearance:auto;">
                            <option value="">Select a topic</option>
                            <option <?= ($_POST['subject'] ?? '') === 'Track my order' ? 'selected' : '' ?>>Track my order</option>
                            <option <?= ($_POST['subject'] ?? '') === 'Product enquiry' ? 'selected' : '' ?>>Product enquiry</option>
                            <option <?= ($_POST['subject'] ?? '') === 'Payment issue' ? 'selected' : '' ?>>Payment issue</option>
                            <option <?= ($_POST['subject'] ?? '') === 'General question' ? 'selected' : '' ?>>General question</option>
                            <option <?= ($_POST['subject'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Phone Number (optional)</label>
                        <input type="tel" name="phone" class="form-input" placeholder="+234 800 0000 000"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-5">
                    <label class="form-label">Message *</label>
                    <textarea name="message" class="form-input" rows="5" placeholder="Tell us how we can help…" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                </div>

                <?php if ($error): ?>
                <div class="mb-4 flex items-center gap-3 bg-red-50 border border-red-100 text-red-700 rounded-2xl px-4 py-3 text-sm font-medium">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn-primary w-full text-center" style="border-radius:12px; padding:14px;">
                    Send Message →
                </button>
            </form>
        </div>

        <!-- ── FAQ / QUICK LINKS ── -->
        <div class="bg-white rounded-3xl shadow-sm p-6 md:p-8">
            <h2 class="font-bold text-base mb-5">Quick Help</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a href="track.php" class="flex items-center gap-3 bg-fg-light rounded-2xl p-4 hover:bg-gray-100 transition-colors group">
                    <span class="text-xl">📦</span>
                    <div>
                        <p class="font-semibold text-sm group-hover:text-fg-blue transition-colors">Track your order</p>
                        <p class="text-xs text-fg-gray mt-0.5">Get live delivery updates</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-300 ml-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </a>
                <a href="shop.php" class="flex items-center gap-3 bg-fg-light rounded-2xl p-4 hover:bg-gray-100 transition-colors group">
                    <span class="text-xl">🛍️</span>
                    <div>
                        <p class="font-semibold text-sm group-hover:text-fg-blue transition-colors">Browse products</p>
                        <p class="text-xs text-fg-gray mt-0.5">Find what you're looking for</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-300 ml-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            </div>
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
                    <a href="track.php" class="text-gray-400 text-sm hover:text-white transition-colors">Track Order</a>
                    <a href="contact.php" class="text-white text-sm font-medium">Contact Us</a>
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
