<?php
/**
 * includes/navbar.php
 * Requires: $conn (db), $all_cats (array), $cart_count (int)
 * Optional: $active_cat (slug string to highlight active link)
 * Usage: include 'includes/navbar.php';
 */

// Fetch categories if not already fetched
if (!isset($all_cats)) {
    $all_cats = [];
    $_cats_res = mysqli_query($conn, "SELECT * FROM categories ORDER BY sort_order ASC");
    while ($_c = mysqli_fetch_assoc($_cats_res)) $all_cats[] = $_c;
}

if (!isset($cart_count)) $cart_count = get_cart_count($conn);
$active_cat = $active_cat ?? '';
$base = isset($base_path) ? $base_path : '';
?>

<nav class="navbar-blur fixed top-0 left-0 right-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">

        <!-- Logo -->
        <a href="<?php echo $base; ?>index.php" class="flex items-center flex-shrink-0">
            <img src="<?php echo $base; ?>assets/images/logo.png" alt="Frank Gadgets" class="h-10 w-auto">
        </a>

        <!-- Desktop nav links -->
        <div class="hidden md:flex items-center gap-6">
            <?php foreach(array_slice($all_cats, 0, 5) as $c): ?>
            <a href="<?php echo $base; ?>shop.php?category=<?php echo $c['slug']; ?>"
               class="text-sm transition-colors duration-200
               <?php echo $active_cat === $c['slug'] ? 'text-fg-blue font-semibold' : 'text-fg-gray hover:text-fg-dark'; ?>">
                <?php echo htmlspecialchars($c['name']); ?>
            </a>
            <?php endforeach; ?>
            <a href="<?php echo $base; ?>shop.php"
               class="text-sm transition-colors <?php echo $active_cat === '' && basename($_SERVER['PHP_SELF']) === 'shop.php' ? 'text-fg-blue font-semibold' : 'text-fg-gray hover:text-fg-dark'; ?>">
                All
            </a>
        </div>

        <!-- Right side -->
        <div class="flex items-center gap-4">

            <!-- Search -->
            <div class="hidden md:flex items-center relative">
                <svg class="absolute left-3 w-4 h-4 text-fg-gray pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" placeholder="Search" class="search-bar" id="searchInput"
                    value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>"
                    onkeydown="if(event.key==='Enter'&&this.value.trim()) window.location='<?php echo $base; ?>shop.php?q='+encodeURIComponent(this.value.trim())">
            </div>

            <!-- Cart icon -->
            <a href="<?php echo $base; ?>cart.php" class="relative p-1">
                <svg class="w-6 h-6 text-fg-dark" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
                <?php if($cart_count > 0): ?>
                <span class="cart-badge" id="cart-badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>

            <!-- Mobile menu toggle -->
            <button class="md:hidden p-1" id="menuBtn" aria-label="Toggle menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-100 px-4 py-4">
        <div class="flex flex-col gap-1">
            <?php foreach($all_cats as $c): ?>
            <a href="<?php echo $base; ?>shop.php?category=<?php echo $c['slug']; ?>"
               class="text-sm py-2.5 px-3 rounded-xl border-b border-gray-50
               <?php echo $active_cat === $c['slug'] ? 'text-fg-blue font-semibold bg-blue-50' : 'text-fg-gray hover:text-fg-dark'; ?>">
                <?php echo htmlspecialchars($c['name']); ?>
            </a>
            <?php endforeach; ?>
            <a href="<?php echo $base; ?>shop.php" class="text-sm text-fg-blue font-medium py-2.5 px-3 mt-1">
                View All Products →
            </a>
            <!-- Mobile search -->
            <div class="pt-3">
                <input type="text" placeholder="Search products..."
                    class="w-full bg-gray-100 rounded-xl px-4 py-3 text-sm outline-none"
                    value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>"
                    onkeydown="if(event.key==='Enter'&&this.value.trim()) window.location='<?php echo $base; ?>shop.php?q='+encodeURIComponent(this.value.trim())">
            </div>
        </div>
    </div>
</nav>