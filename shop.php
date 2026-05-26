<?php
session_start();
include 'config/db.php';

// ── Filters from GET ──────────────────────────────────────────────
$search   = isset($_GET['q'])        ? clean($conn, $_GET['q'])        : '';
$cat_slug = isset($_GET['category']) ? clean($conn, $_GET['category']) : '';
$sort     = isset($_GET['sort'])     ? clean($conn, $_GET['sort'])     : 'newest';
$min_p    = isset($_GET['min'])      ? intval($_GET['min'])            : 0;
$max_p    = isset($_GET['max'])      ? intval($_GET['max'])            : 0;
$page     = isset($_GET['page'])     ? max(1, intval($_GET['page']))   : 1;
$per_page = 12;
$offset   = ($page - 1) * $per_page;

// ── Get all categories ────────────────────────────────────────────
$cats_res = mysqli_query($conn, "SELECT * FROM categories ORDER BY sort_order ASC");
$all_cats = [];
while ($c = mysqli_fetch_assoc($cats_res)) $all_cats[] = $c;

// Find active category id
$active_cat_id = 0;
foreach ($all_cats as $c) {
    if ($c['slug'] === $cat_slug) { $active_cat_id = $c['id']; break; }
}

// ── Build WHERE ───────────────────────────────────────────────────
$where = "p.status = 'active'";
if ($active_cat_id) $where .= " AND p.category_id = $active_cat_id";
if ($search)        $where .= " AND (p.name LIKE '%$search%' OR p.brand LIKE '%$search%' OR p.description LIKE '%$search%')";
if ($min_p > 0)     $where .= " AND p.price >= $min_p";
if ($max_p > 0)     $where .= " AND p.price <= $max_p";

// ── Sort ──────────────────────────────────────────────────────────
$order = match($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'name_asc'   => 'p.name ASC',
    'featured'   => 'p.is_featured DESC, p.created_at DESC',
    default      => 'p.created_at DESC',
};

// ── Count total ───────────────────────────────────────────────────
$count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM products p WHERE $where");
$total_products = mysqli_fetch_assoc($count_res)['total'];
$total_pages    = ceil($total_products / $per_page);

// ── Fetch products ────────────────────────────────────────────────
$products_res = mysqli_query($conn, "
    SELECT p.*, c.name as category_name,
           (SELECT image FROM product_images WHERE product_id=p.id AND is_main=1 LIMIT 1) as main_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE $where
    ORDER BY $order
    LIMIT $per_page OFFSET $offset
");

$cart_count = get_cart_count($conn);

// ── Price range for this category ─────────────────────────────────
$price_range = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT MIN(price) as min_p, MAX(price) as max_p FROM products p WHERE p.status='active'"
    . ($active_cat_id ? " AND p.category_id=$active_cat_id" : "")
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $search ? "Search: $search" : ($cat_slug ? ucfirst(str_replace('-',' ',$cat_slug)) : 'All Products'); ?> — Frank Gadgets</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: {
        extend: {
            fontFamily: { sans: ['Inter','sans-serif'] },
            colors: {
                fg: {
                    blue:       '#0071e3',
                    'blue-dark':'#0058b0',
                    dark:       '#1d1d1f',
                    gray:       '#6e6e73',
                    light:      '#f5f5f7',
                }
            }
        }
    }
}
</script>
<style>
* { -webkit-font-smoothing: antialiased; }
html { scroll-behavior: smooth; }

.navbar-blur {
    background: rgba(255,255,255,0.85);
    backdrop-filter: saturate(180%) blur(20px);
    -webkit-backdrop-filter: saturate(180%) blur(20px);
    border-bottom: 1px solid rgba(0,0,0,0.08);
}
.cart-badge {
    position: absolute; top: -6px; right: -6px;
    background: #0071e3; color: #fff;
    font-size: 10px; font-weight: 700;
    width: 18px; height: 18px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}
.product-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
.product-card:hover { transform: translateY(-5px); box-shadow: 0 20px 60px rgba(0,0,0,0.11); }
.product-card img { transition: transform 0.5s ease; }
.product-card:hover img { transform: scale(1.04); }
.oos-overlay {
    position: absolute; inset: 0;
    background: rgba(255,255,255,0.75);
    backdrop-filter: blur(2px);
    display: flex; align-items: center; justify-content: center;
}
.filter-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 980px;
    font-size: 13px; font-weight: 500;
    border: 1.5px solid #e5e7eb;
    cursor: pointer; transition: all 0.2s;
    background: white; color: #1d1d1f;
    white-space: nowrap;
}
.filter-chip:hover, .filter-chip.active {
    background: #0071e3; color: white; border-color: #0071e3;
}
.sidebar-section { border-bottom: 1px solid #f0f0f0; padding-bottom: 20px; margin-bottom: 20px; }
.range-slider {
    -webkit-appearance: none; width: 100%; height: 4px;
    border-radius: 2px; background: #e5e7eb; outline: none;
}
.range-slider::-webkit-slider-thumb {
    -webkit-appearance: none; width: 18px; height: 18px;
    border-radius: 50%; background: #0071e3; cursor: pointer;
}
.section-eyebrow {
    font-size: 12px; font-weight: 600;
    letter-spacing: 0.08em; text-transform: uppercase; color: #0071e3;
}
.btn-primary {
    background: #0071e3; color: #fff;
    padding: 10px 24px; border-radius: 980px;
    font-size: 14px; font-weight: 500;
    transition: background 0.2s; display: inline-block;
}
.btn-primary:hover { background: #0058b0; }
/* Skeleton loader */
.skeleton { animation: shimmer 1.5s infinite linear; background: linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%); background-size: 200% 100%; }
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
/* Mobile filter drawer */
#filter-drawer { transition: transform 0.35s ease; }
.search-bar {
    background: rgba(0,0,0,0.06); border: none; border-radius: 980px;
    padding: 8px 16px 8px 38px; font-size: 14px; outline: none;
    transition: background 0.2s, width 0.3s; width: 180px;
}
.search-bar:focus { background: rgba(0,0,0,0.1); width: 240px; }
</style>
</head>
<body class="bg-white font-sans text-fg-dark">

<!-- ═══ NAVBAR ═══ -->
<nav class="navbar-blur fixed top-0 left-0 right-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
        <a href="index.php" class="flex items-center flex-shrink-0">
            <img src="assets/images/logo.png" alt="Frank Gadgets" class="h-10 w-auto">
        </a>

        <div class="hidden md:flex items-center gap-6">
            <?php foreach(array_slice($all_cats,0,5) as $c): ?>
            <a href="shop.php?category=<?php echo $c['slug']; ?>"
               class="text-sm <?php echo $c['slug']===$cat_slug?'text-fg-blue font-semibold':'text-fg-gray hover:text-fg-dark'; ?> transition-colors">
                <?php echo htmlspecialchars($c['name']); ?>
            </a>
            <?php endforeach; ?>
            <a href="shop.php" class="text-sm <?php echo !$cat_slug&&!$search?'text-fg-blue font-semibold':'text-fg-gray hover:text-fg-dark'; ?> transition-colors">All</a>
        </div>

        <div class="flex items-center gap-4">
            <div class="hidden md:flex items-center relative">
                <svg class="absolute left-3 w-4 h-4 text-fg-gray" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" placeholder="Search" class="search-bar" id="searchInput"
                    value="<?php echo htmlspecialchars($search); ?>"
                    onkeydown="if(event.key==='Enter') window.location='shop.php?q='+this.value">
            </div>
            <a href="cart.php" class="relative p-1">
                <svg class="w-6 h-6 text-fg-dark" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
                <?php if($cart_count > 0): ?>
                <span class="cart-badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>
            <button class="md:hidden p-1" id="menuBtn">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </div>
    <!-- Mobile menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-100 px-4 py-4">
        <div class="flex flex-col gap-3">
            <?php foreach($all_cats as $c): ?>
            <a href="shop.php?category=<?php echo $c['slug']; ?>"
               class="text-sm text-fg-gray py-2 border-b border-gray-50">
                <?php echo htmlspecialchars($c['name']); ?>
            </a>
            <?php endforeach; ?>
            <div class="pt-2">
                <input type="text" placeholder="Search products..."
                    class="w-full bg-gray-100 rounded-xl px-4 py-3 text-sm outline-none"
                    value="<?php echo htmlspecialchars($search); ?>"
                    onkeydown="if(event.key==='Enter') window.location='shop.php?q='+this.value">
            </div>
        </div>
    </div>
</nav>

<!-- ═══ PAGE HEADER ═══ -->
<div class="pt-14 bg-fg-light">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
        <p class="section-eyebrow mb-2">
            <?php echo $search ? 'Search Results' : ($cat_slug ? 'Browse' : 'Store'); ?>
        </p>
        <h1 class="text-4xl md:text-5xl font-black tracking-tight mb-2">
            <?php
            if ($search) echo 'Results for "'.htmlspecialchars($search).'"';
            elseif ($cat_slug) echo ucfirst(str_replace('-',' ',$cat_slug));
            else echo 'All Products';
            ?>
        </h1>
        <p class="text-fg-gray text-sm"><?php echo $total_products; ?> product<?php echo $total_products!=1?'s':''; ?> found</p>
    </div>
</div>

<!-- ═══ CATEGORY CHIPS ═══ -->
<div class="bg-white border-b border-gray-100 sticky top-14 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center gap-2 overflow-x-auto no-scrollbar">
        <a href="shop.php<?php echo $search?'?q='.urlencode($search):''; ?>"
           class="filter-chip <?php echo !$cat_slug?'active':''; ?>">All</a>
        <?php foreach($all_cats as $c): ?>
        <a href="shop.php?category=<?php echo $c['slug']; ?><?php echo $search?'&q='.urlencode($search):''; ?>"
           class="filter-chip <?php echo $c['slug']===$cat_slug?'active':''; ?>">
            <?php echo htmlspecialchars($c['name']); ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══ MAIN CONTENT ═══ -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <div class="flex gap-8">

        <!-- ── SIDEBAR ── -->
        <aside class="hidden lg:block w-56 flex-shrink-0">

            <!-- Sort -->
            <div class="sidebar-section">
                <h3 class="text-xs font-semibold text-fg-gray uppercase tracking-widest mb-4">Sort By</h3>
                <?php
                $sorts = [
                    'newest'     => 'Newest First',
                    'featured'   => 'Featured',
                    'price_asc'  => 'Price: Low to High',
                    'price_desc' => 'Price: High to Low',
                    'name_asc'   => 'Name A–Z',
                ];
                foreach($sorts as $val => $label):
                    $params = array_merge($_GET, ['sort'=>$val, 'page'=>1]);
                    $url = 'shop.php?' . http_build_query($params);
                ?>
                <a href="<?php echo $url; ?>"
                   class="flex items-center gap-2 py-2 text-sm <?php echo $sort===$val?'text-fg-blue font-semibold':'text-fg-gray hover:text-fg-dark'; ?> transition-colors">
                    <?php if($sort===$val): ?>
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <?php else: ?>
                    <span class="w-3.5 h-3.5"></span>
                    <?php endif; ?>
                    <?php echo $label; ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Price filter -->
            <div class="sidebar-section">
                <h3 class="text-xs font-semibold text-fg-gray uppercase tracking-widest mb-4">Price Range</h3>
                <form method="GET" id="priceForm">
                    <?php if($cat_slug): ?><input type="hidden" name="category" value="<?php echo $cat_slug; ?>"><?php endif; ?>
                    <?php if($search):   ?><input type="hidden" name="q" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
                    <?php if($sort!=='newest'): ?><input type="hidden" name="sort" value="<?php echo $sort; ?>"><?php endif; ?>
                    <div class="flex gap-2 mb-3">
                        <div class="flex-1">
                            <label class="text-xs text-fg-gray mb-1 block">Min (₦)</label>
                            <input type="number" name="min" value="<?php echo $min_p?:''; ?>"
                                placeholder="0"
                                class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm outline-none focus:border-fg-blue">
                        </div>
                        <div class="flex-1">
                            <label class="text-xs text-fg-gray mb-1 block">Max (₦)</label>
                            <input type="number" name="max" value="<?php echo $max_p?:''; ?>"
                                placeholder="Any"
                                class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm outline-none focus:border-fg-blue">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-fg-blue text-white py-2 rounded-xl text-sm font-medium hover:bg-fg-blue-dark transition-colors">
                        Apply
                    </button>
                    <?php if($min_p || $max_p): ?>
                    <a href="shop.php?<?php echo http_build_query(array_diff_key($_GET,['min'=>'','max'=>'','page'=>''])); ?>"
                       class="block text-center text-xs text-fg-gray hover:text-fg-blue mt-2 transition-colors">Clear filter</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Categories in sidebar -->
            <div>
                <h3 class="text-xs font-semibold text-fg-gray uppercase tracking-widest mb-4">Categories</h3>
                <?php foreach($all_cats as $c): ?>
                <a href="shop.php?category=<?php echo $c['slug']; ?>"
                   class="flex items-center justify-between py-2 text-sm <?php echo $c['slug']===$cat_slug?'text-fg-blue font-semibold':'text-fg-gray hover:text-fg-dark'; ?> transition-colors">
                    <span><?php echo htmlspecialchars($c['name']); ?></span>
                </a>
                <?php endforeach; ?>
                <a href="shop.php"
                   class="flex items-center justify-between py-2 text-sm <?php echo !$cat_slug?'text-fg-blue font-semibold':'text-fg-gray hover:text-fg-dark'; ?> transition-colors">
                    <span>All Products</span>
                </a>
            </div>

        </aside>

        <!-- ── PRODUCTS GRID ── -->
        <div class="flex-1 min-w-0">

            <!-- Toolbar -->
            <div class="flex items-center justify-between mb-6 gap-4">
                <p class="text-sm text-fg-gray">
                    Showing <span class="font-semibold text-fg-dark"><?php echo min($offset+1,$total_products); ?>–<?php echo min($offset+$per_page,$total_products); ?></span> of <span class="font-semibold text-fg-dark"><?php echo $total_products; ?></span>
                </p>
                <div class="flex items-center gap-3">
                    <!-- Mobile filter button -->
                    <button onclick="document.getElementById('filter-drawer').classList.toggle('-translate-x-full')"
                        class="lg:hidden flex items-center gap-2 text-sm border border-gray-200 px-3 py-2 rounded-xl hover:border-fg-blue transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/>
                        </svg>
                        Filter
                    </button>
                    <!-- Sort dropdown (mobile/tablet) -->
                    <select onchange="window.location=this.value"
                        class="text-sm border border-gray-200 rounded-xl px-3 py-2 outline-none focus:border-fg-blue bg-white cursor-pointer">
                        <?php foreach($sorts as $val => $label):
                            $params = array_merge($_GET, ['sort'=>$val,'page'=>1]);
                        ?>
                        <option value="shop.php?<?php echo http_build_query($params); ?>"
                            <?php echo $sort===$val?'selected':''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Active filters display -->
            <?php if($search || $min_p || $max_p || $cat_slug): ?>
            <div class="flex flex-wrap gap-2 mb-5">
                <?php if($search): ?>
                <span class="inline-flex items-center gap-1.5 bg-blue-50 text-fg-blue text-xs font-medium px-3 py-1.5 rounded-full">
                    Search: <?php echo htmlspecialchars($search); ?>
                    <a href="shop.php?<?php echo http_build_query(array_diff_key($_GET,['q'=>'','page'=>''])); ?>" class="hover:opacity-70">×</a>
                </span>
                <?php endif; ?>
                <?php if($cat_slug): ?>
                <span class="inline-flex items-center gap-1.5 bg-blue-50 text-fg-blue text-xs font-medium px-3 py-1.5 rounded-full">
                    <?php echo ucfirst(str_replace('-',' ',$cat_slug)); ?>
                    <a href="shop.php?<?php echo http_build_query(array_diff_key($_GET,['category'=>'','page'=>''])); ?>" class="hover:opacity-70">×</a>
                </span>
                <?php endif; ?>
                <?php if($min_p || $max_p): ?>
                <span class="inline-flex items-center gap-1.5 bg-blue-50 text-fg-blue text-xs font-medium px-3 py-1.5 rounded-full">
                    ₦<?php echo number_format($min_p); ?> – <?php echo $max_p?'₦'.number_format($max_p):'Any'; ?>
                    <a href="shop.php?<?php echo http_build_query(array_diff_key($_GET,['min'=>'','max'=>'','page'=>''])); ?>" class="hover:opacity-70">×</a>
                </span>
                <?php endif; ?>
                <a href="shop.php" class="text-xs text-fg-gray hover:text-fg-dark underline self-center">Clear all</a>
            </div>
            <?php endif; ?>

            <!-- Product grid -->
            <?php if($total_products === 0): ?>
            <div class="text-center py-24">
                <div class="text-6xl mb-4">🔍</div>
                <h3 class="text-xl font-bold mb-2">No products found</h3>
                <p class="text-fg-gray text-sm mb-6">Try adjusting your filters or search term.</p>
                <a href="shop.php" class="btn-primary">Browse All Products</a>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
                <?php while($p = mysqli_fetch_assoc($products_res)):
                    $img      = $p['main_image'] ? UPLOADS_URL . $p['main_image'] : 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=400&q=80';
                    $is_oos   = $p['status'] === 'out_of_stock';
                    $discount = ($p['old_price'] && $p['old_price'] > $p['price'])
                        ? round((($p['old_price'] - $p['price']) / $p['old_price']) * 100)
                        : 0;
                ?>
                <a href="product.php?id=<?php echo $p['id']; ?>"
                   class="product-card bg-white border border-gray-100 rounded-3xl overflow-hidden block relative group shadow-sm">

                    <!-- Image -->
                    <div class="relative bg-fg-light overflow-hidden" style="height:210px">
                        <img src="<?php echo htmlspecialchars($img); ?>"
                             alt="<?php echo htmlspecialchars($p['name']); ?>"
                             class="w-full h-full object-cover"
                             loading="lazy"
                             onerror="this.src='https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=400&q=80'">

                        <!-- Badges -->
                        <div class="absolute top-3 left-3 flex flex-col gap-1">
                            <?php if($p['is_new']): ?>
                            <span class="bg-fg-blue text-white text-xs font-semibold px-2 py-0.5 rounded-full">New</span>
                            <?php endif; ?>
                            <?php if($p['is_featured']): ?>
                            <span class="bg-amber-400 text-white text-xs font-semibold px-2 py-0.5 rounded-full">Featured</span>
                            <?php endif; ?>
                            <?php if($discount > 0): ?>
                            <span class="bg-red-500 text-white text-xs font-semibold px-2 py-0.5 rounded-full">-<?php echo $discount; ?>%</span>
                            <?php endif; ?>
                        </div>

                        <!-- Wishlist placeholder -->
                        <button onclick="event.preventDefault()"
                            class="absolute top-3 right-3 w-8 h-8 bg-white rounded-full shadow flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg class="w-4 h-4 text-fg-gray" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                        </button>

                        <?php if($is_oos): ?>
                        <div class="oos-overlay">
                            <span class="bg-fg-dark text-white text-xs font-semibold px-3 py-1.5 rounded-full">Out of Stock</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Info -->
                    <div class="p-4">
                        <p class="text-xs text-fg-gray mb-1"><?php echo htmlspecialchars($p['brand'] ?? $p['category_name']); ?></p>
                        <h3 class="font-semibold text-sm leading-tight mb-3 line-clamp-2 text-fg-dark">
                            <?php echo htmlspecialchars($p['name']); ?>
                        </h3>
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <span class="text-base font-bold text-fg-dark"><?php echo format_price($p['price']); ?></span>
                                <?php if($p['old_price']): ?>
                                <span class="text-xs text-fg-gray line-through ml-1"><?php echo format_price($p['old_price']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if(!$is_oos): ?>
                            <button
                                onclick="event.preventDefault(); addToCart(<?php echo $p['id']; ?>, this)"
                                class="flex-shrink-0 bg-fg-blue text-white text-xs font-semibold px-3 py-2 rounded-xl hover:bg-fg-blue-dark transition-colors">
                                + Cart
                            </button>
                            <?php else: ?>
                            <span class="text-xs bg-gray-100 text-gray-400 px-3 py-2 rounded-xl">Sold Out</span>
                            <?php endif; ?>
                        </div>
                    </div>

                </a>
                <?php endwhile; ?>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="flex items-center justify-center gap-2 mt-12">
                <?php if($page > 1): ?>
                <a href="shop.php?<?php echo http_build_query(array_merge($_GET,['page'=>$page-1])); ?>"
                   class="w-10 h-10 flex items-center justify-center border border-gray-200 rounded-xl hover:border-fg-blue hover:text-fg-blue transition-colors text-sm">
                    ‹
                </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page-2);
                $end   = min($total_pages, $page+2);
                for($i=$start; $i<=$end; $i++):
                ?>
                <a href="shop.php?<?php echo http_build_query(array_merge($_GET,['page'=>$i])); ?>"
                   class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-medium transition-colors
                   <?php echo $i===$page?'bg-fg-blue text-white':'border border-gray-200 hover:border-fg-blue hover:text-fg-blue'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>

                <?php if($page < $total_pages): ?>
                <a href="shop.php?<?php echo http_build_query(array_merge($_GET,['page'=>$page+1])); ?>"
                   class="w-10 h-10 flex items-center justify-center border border-gray-200 rounded-xl hover:border-fg-blue hover:text-fg-blue transition-colors text-sm">
                    ›
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div><!-- /products -->
    </div><!-- /flex -->
</div><!-- /main -->

<!-- ═══ MOBILE FILTER DRAWER ═══ -->
<div id="filter-drawer" class="fixed inset-y-0 left-0 w-72 bg-white z-[60] shadow-2xl -translate-x-full transform overflow-y-auto p-6 lg:hidden">
    <div class="flex items-center justify-between mb-6">
        <h3 class="font-bold text-lg">Filters</h3>
        <button onclick="document.getElementById('filter-drawer').classList.add('-translate-x-full')"
            class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100">×</button>
    </div>

    <!-- Sort -->
    <div class="sidebar-section">
        <h4 class="text-xs font-semibold text-fg-gray uppercase tracking-widest mb-4">Sort By</h4>
        <?php foreach($sorts as $val => $label):
            $params = array_merge($_GET, ['sort'=>$val,'page'=>1]);
            $url = 'shop.php?' . http_build_query($params);
        ?>
        <a href="<?php echo $url; ?>"
           class="flex items-center gap-2 py-2 text-sm <?php echo $sort===$val?'text-fg-blue font-semibold':'text-fg-gray'; ?>">
            <?php echo $label; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Price -->
    <div class="sidebar-section">
        <h4 class="text-xs font-semibold text-fg-gray uppercase tracking-widest mb-4">Price (₦)</h4>
        <form method="GET">
            <?php if($cat_slug): ?><input type="hidden" name="category" value="<?php echo $cat_slug; ?>"><?php endif; ?>
            <?php if($search):   ?><input type="hidden" name="q" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
            <div class="flex gap-2 mb-3">
                <input type="number" name="min" value="<?php echo $min_p?:'' ;?>" placeholder="Min"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-fg-blue">
                <input type="number" name="max" value="<?php echo $max_p?:'' ;?>" placeholder="Max"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-fg-blue">
            </div>
            <button type="submit" class="w-full bg-fg-blue text-white py-2.5 rounded-xl text-sm font-medium">Apply</button>
        </form>
    </div>

    <!-- Categories -->
    <div>
        <h4 class="text-xs font-semibold text-fg-gray uppercase tracking-widest mb-4">Category</h4>
        <a href="shop.php" class="block py-2 text-sm <?php echo !$cat_slug?'text-fg-blue font-semibold':'text-fg-gray'; ?>">All Products</a>
        <?php foreach($all_cats as $c): ?>
        <a href="shop.php?category=<?php echo $c['slug']; ?>"
           class="block py-2 text-sm <?php echo $c['slug']===$cat_slug?'text-fg-blue font-semibold':'text-fg-gray'; ?>">
            <?php echo htmlspecialchars($c['name']); ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<!-- Drawer overlay -->
<div id="drawer-overlay" class="fixed inset-0 bg-black/40 z-50 hidden lg:hidden"
    onclick="document.getElementById('filter-drawer').classList.add('-translate-x-full'); this.classList.add('hidden')">
</div>

<!-- ═══ FOOTER ═══ -->
<footer class="bg-fg-dark text-white pt-16 pb-8 mt-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-12">
            <div>
                <div class="mb-4">
                    <img src="assets/images/logo.png" alt="Frank Gadgets" class="h-10 w-auto brightness-0 invert">
                </div>
                <p class="text-gray-400 text-sm leading-relaxed">Nigeria's most trusted premium gadget store.</p>
            </div>
            <div>
                <h4 class="font-semibold text-sm mb-4 text-gray-300">Shop</h4>
                <div class="flex flex-col gap-2">
                    <?php foreach(array_slice($all_cats,0,5) as $c): ?>
                    <a href="shop.php?category=<?php echo $c['slug']; ?>" class="text-gray-400 text-sm hover:text-white transition-colors"><?php echo htmlspecialchars($c['name']); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h4 class="font-semibold text-sm mb-4 text-gray-300">Help</h4>
                <div class="flex flex-col gap-2">
                    <a href="#" class="text-gray-400 text-sm hover:text-white">Track Order</a>
                    <a href="#" class="text-gray-400 text-sm hover:text-white">Returns</a>
                    <a href="#" class="text-gray-400 text-sm hover:text-white">Contact Us</a>
                </div>
            </div>
            <div>
                <h4 class="font-semibold text-sm mb-4 text-gray-300">Contact</h4>
                <div class="flex flex-col gap-2 text-gray-400 text-sm">
                    <span>Lagos, Nigeria</span>
                    <span>hello@frankgadgets.com</span>
                </div>
            </div>
        </div>
        <div class="border-t border-gray-800 pt-8 flex items-center justify-between">
            <p class="text-gray-500 text-xs">© 2026 Frank Gadgets. All rights reserved.</p>
            <a href="admin/login.php" class="text-gray-600 text-xs hover:text-gray-400">Admin</a>
        </div>
    </div>
</footer>

<!-- ═══ TOAST ═══ -->
<div id="toast" class="fixed bottom-6 right-6 z-50 transform translate-y-20 opacity-0 transition-all duration-300">
    <div class="bg-fg-dark text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-3 text-sm font-medium">
        <svg class="w-4 h-4 text-green-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <span id="toast-msg">Added to cart!</span>
        <a href="cart.php" class="text-fg-blue font-semibold ml-1 hover:underline">View Cart</a>
    </div>
</div>

<script>
// Mobile menu
document.getElementById('menuBtn').addEventListener('click', () => {
    document.getElementById('mobile-menu').classList.toggle('hidden');
});

// Filter drawer overlay
const filterBtn = document.querySelector('[onclick*="filter-drawer"]');
if(filterBtn){
    filterBtn.addEventListener('click', () => {
        document.getElementById('drawer-overlay').classList.toggle('hidden');
    });
}

// Add to cart
function addToCart(productId, btn){
    const original = btn.textContent;
    btn.textContent = '...';
    btn.disabled = true;

    fetch('cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=add&product_id=' + productId + '&quantity=1'
    })
    .then(r => r.json())
    .then(data => {
        if(data.success){
            showToast('Added to cart!');
            const badge = document.querySelector('.cart-badge');
            if(badge) badge.textContent = data.cart_count;
        } else {
            showToast(data.message || 'Something went wrong', true);
        }
    })
    .catch(() => showToast('Network error', true))
    .finally(() => { btn.textContent = original; btn.disabled = false; });
}

function showToast(msg){
    const toast = document.getElementById('toast');
    document.getElementById('toast-msg').textContent = msg;
    toast.classList.remove('translate-y-20','opacity-0');
    setTimeout(() => toast.classList.add('translate-y-20','opacity-0'), 3000);
}

// Navbar scroll shadow
window.addEventListener('scroll', () => {
    document.querySelector('nav').style.boxShadow =
        window.scrollY > 10 ? '0 1px 20px rgba(0,0,0,0.08)' : 'none';
});
</script>
</body>
</html>