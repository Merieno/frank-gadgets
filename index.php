<?php
session_start();
include 'config/db.php';

// Get featured products
$featured = mysqli_query($conn, "
    SELECT p.*, c.name as category_name,
    (SELECT image FROM product_images WHERE product_id=p.id AND is_main=1 LIMIT 1) as main_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.is_featured = 1 AND p.status = 'active'
    ORDER BY p.created_at DESC LIMIT 8
");

// Get categories with product count
$cats = mysqli_query($conn, "
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
    GROUP BY c.id
    ORDER BY c.sort_order ASC
");

// Get newest products
$newest = mysqli_query($conn, "
    SELECT p.*,
    (SELECT image FROM product_images WHERE product_id=p.id AND is_main=1 LIMIT 1) as main_image
    FROM products p
    WHERE p.is_new = 1 AND p.status = 'active'
    ORDER BY p.created_at DESC LIMIT 4
");

$cart_count = get_cart_count($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Frank Gadgets — Premium Tech Store</title>
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
            },
            animation: {
                'fade-up': 'fadeUp 0.7s ease forwards',
                'fade-in': 'fadeIn 0.5s ease forwards',
            },
            keyframes: {
                fadeUp: { '0%': { opacity:0, transform:'translateY(30px)' }, '100%': { opacity:1, transform:'translateY(0)' } },
                fadeIn: { '0%': { opacity:0 }, '100%': { opacity:1 } },
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
    .hero-gradient {
        background: linear-gradient(135deg, #1d1d1f 0%, #515154 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .product-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
    .product-card:hover { transform: translateY(-6px); box-shadow: 0 20px 60px rgba(0,0,0,0.12); }
    .product-card img { transition: transform 0.5s ease; }
    .product-card:hover img { transform: scale(1.05); }

    .cat-card { transition: all 0.3s ease; }
    .cat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.1); }

    .oos-overlay {
        position: absolute; inset: 0;
        background: rgba(255,255,255,0.75);
        backdrop-filter: blur(2px);
        display: flex; align-items: center; justify-content: center;
    }
    .reveal { opacity: 0; transform: translateY(30px); transition: all 0.7s ease; }
    .reveal.visible { opacity: 1; transform: translateY(0); }

    .section-eyebrow {
        font-size: 12px; font-weight: 600;
        letter-spacing: 0.08em; text-transform: uppercase;
        color: #0071e3;
    }
    .cart-badge {
        position: absolute; top: -6px; right: -6px;
        background: #0071e3; color: #fff;
        font-size: 10px; font-weight: 700;
        width: 18px; height: 18px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        line-height: 1;
    }
    .promo-bg {
        background: linear-gradient(135deg, #1d1d1f 0%, #2d2d30 100%);
    }
    .btn-primary {
        background: #0071e3; color: #fff;
        padding: 12px 28px; border-radius: 980px;
        font-size: 15px; font-weight: 500;
        transition: background 0.2s ease, transform 0.2s ease;
        display: inline-block; text-decoration: none;
    }
    .btn-primary:hover { background: #0058b0; transform: scale(1.02); }

    .btn-ghost {
        border: 1.5px solid #0071e3; color: #0071e3;
        padding: 11px 28px; border-radius: 980px;
        font-size: 15px; font-weight: 500;
        transition: all 0.2s ease;
        display: inline-block; text-decoration: none;
    }
    .btn-ghost:hover { background: #0071e3; color: #fff; }

    #mobile-menu { transition: all 0.35s ease; }

    .search-bar {
        background: rgba(0,0,0,0.06);
        border: none; border-radius: 980px;
        padding: 8px 16px 8px 38px;
        font-size: 14px; outline: none;
        transition: background 0.2s ease, width 0.3s ease;
        width: 180px;
    }
    .search-bar:focus { background: rgba(0,0,0,0.1); width: 240px; }
</style>
</head>
<body class="bg-white font-sans text-fg-dark">

<!-- ============ NAVBAR ============ -->
<nav class="navbar-blur fixed top-0 left-0 right-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">

        <!-- Logo -->
        <a href="index.php" class="flex items-center flex-shrink-0">
            <img src="assets/images/logo.png" alt="Frank Gadgets" class="h-10 w-auto">
        </a>

        <!-- Nav links (desktop) -->
        <div class="hidden md:flex items-center gap-6">
            <?php
            mysqli_data_seek($cats, 0);
            $nav_cats = [];
            while($c = mysqli_fetch_assoc($cats)) $nav_cats[] = $c;
            foreach(array_slice($nav_cats, 0, 5) as $c):
            ?>
            <a href="shop.php?category=<?php echo $c['slug']; ?>"
               class="text-sm text-fg-gray hover:text-fg-dark transition-colors duration-200">
                <?php echo htmlspecialchars($c['name']); ?>
            </a>
            <?php endforeach; ?>
            <a href="shop.php" class="text-sm text-fg-gray hover:text-fg-dark transition-colors">All</a>
        </div>

        <!-- Right side -->
        <div class="flex items-center gap-4">
            <!-- Search -->
            <div class="hidden md:flex items-center relative">
                <svg class="absolute left-3 w-4 h-4 text-fg-gray" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" placeholder="Search" class="search-bar" id="searchInput"
                    onkeydown="if(event.key==='Enter') window.location='shop.php?q='+this.value">
            </div>
            <!-- Cart -->
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
            <!-- Mobile menu toggle -->
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
            <?php foreach($nav_cats as $c): ?>
            <a href="shop.php?category=<?php echo $c['slug']; ?>"
               class="text-sm text-fg-gray py-2 border-b border-gray-50">
                <?php echo htmlspecialchars($c['name']); ?>
            </a>
            <?php endforeach; ?>
            <a href="shop.php" class="text-sm text-fg-blue font-medium py-2">View All Products →</a>
            <div class="pt-2">
                <input type="text" placeholder="Search products..."
                    class="w-full bg-gray-100 rounded-xl px-4 py-3 text-sm outline-none"
                    onkeydown="if(event.key==='Enter') window.location='shop.php?q='+this.value">
            </div>
        </div>
    </div>
</nav>

<!-- ============ HERO ============ -->
<section class="pt-14 bg-fg-light overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-20 md:py-28 flex flex-col md:flex-row items-center gap-12">

        <!-- Left -->
        <div class="flex-1 text-center md:text-left reveal">
            <p class="section-eyebrow mb-4">New Arrivals 2026</p>
            <h1 class="text-5xl md:text-7xl font-black leading-none tracking-tight mb-6">
                <span class="hero-gradient">The Future<br>of Tech.</span><br>
                <span class="text-fg-blue">In Your Hands.</span>
            </h1>
            <p class="text-lg md:text-xl text-fg-gray font-light max-w-lg mx-auto md:mx-0 mb-10 leading-relaxed">
                Discover the world's most premium gadgets — phones, laptops, wearables and more.
                Delivered to your doorstep across Nigeria.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center md:justify-start">
                <a href="shop.php" class="btn-primary">Shop Now</a>
                <a href="shop.php?category=phones" class="btn-ghost">Explore Phones</a>
            </div>
            <!-- Trust badges -->
            <div class="flex items-center gap-6 mt-10 justify-center md:justify-start">
                <div class="flex items-center gap-2 text-xs text-fg-gray">
                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    100% Genuine
                </div>
                <div class="flex items-center gap-2 text-xs text-fg-gray">
                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Fast Delivery
                </div>
                <div class="flex items-center gap-2 text-xs text-fg-gray">
                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Warranty
                </div>
            </div>
        </div>

        <!-- Right — Hero image grid -->
        <div class="flex-1 reveal" style="animation-delay:0.2s">
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white rounded-3xl p-6 shadow-lg flex items-center justify-center aspect-square">
                    <img src="https://images.unsplash.com/photo-1695048133142-1a20484d2569?w=400&q=80" alt="iPhone" class="w-full h-full object-contain drop-shadow-2xl">
                </div>
                <div class="bg-white rounded-3xl p-6 shadow-lg flex items-center justify-center aspect-square mt-8">
                    <img src="https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=400&q=80" alt="MacBook" class="w-full h-full object-cover rounded-2xl">
                </div>
                <div class="bg-white rounded-3xl p-6 shadow-lg flex items-center justify-center aspect-square -mt-8">
                    <img src="https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&q=80" alt="Watch" class="w-full h-full object-contain drop-shadow-xl">
                </div>
                <div class="bg-white rounded-3xl p-6 shadow-lg flex items-center justify-center aspect-square">
                    <img src="https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400&q=80" alt="Headphones" class="w-full h-full object-contain drop-shadow-xl">
                </div>
            </div>
        </div>

    </div>
</section>

<!-- ============ MARQUEE BRANDS ============ -->
<div class="bg-white border-y border-gray-100 py-5 overflow-hidden">
    <div class="flex gap-12 animate-none" style="white-space:nowrap">
        <?php
        $brands = ['Apple','Samsung','Sony','Dell','HP','LG','OnePlus','Google','Bose','Logitech','Apple','Samsung','Sony','Dell','HP','LG','OnePlus','Google','Bose','Logitech'];
        foreach($brands as $b):
        ?>
        <span class="text-sm font-semibold text-gray-300 inline-block"><?php echo $b; ?></span>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============ CATEGORIES ============ -->
<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-12 reveal">
            <p class="section-eyebrow mb-3">Browse By Category</p>
            <h2 class="text-4xl md:text-5xl font-bold tracking-tight">Shop by Type.</h2>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-4 reveal">
            <?php
            $cat_icons = [
                'phones'        => '📱',
                'laptops'       => '💻',
                'tablets'       => '🖥️',
                'headphones'    => '🎧',
                'smart-watches' => '⌚',
                'accessories'   => '🔌',
                'gaming'        => '🎮',
                'cameras'       => '📷',
            ];
            foreach($nav_cats as $c):
                $icon = $cat_icons[$c['slug']] ?? '📦';
            ?>
            <a href="shop.php?category=<?php echo $c['slug']; ?>"
               class="cat-card bg-fg-light rounded-2xl p-4 flex flex-col items-center gap-3 text-center group cursor-pointer">
                <span class="text-3xl group-hover:scale-110 transition-transform duration-300"><?php echo $icon; ?></span>
                <span class="text-xs font-semibold text-fg-dark"><?php echo htmlspecialchars($c['name']); ?></span>
                <span class="text-xs text-fg-gray"><?php echo $c['product_count']; ?> items</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ FEATURED PRODUCTS ============ -->
<section class="py-20 bg-fg-light">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex items-end justify-between mb-12 reveal">
            <div>
                <p class="section-eyebrow mb-3">Handpicked For You</p>
                <h2 class="text-4xl md:text-5xl font-bold tracking-tight">Featured Picks.</h2>
            </div>
            <a href="shop.php" class="hidden md:inline-flex items-center gap-2 text-fg-blue font-medium text-sm hover:underline">
                View all
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php
        mysqli_data_seek($featured, 0);
        while($p = mysqli_fetch_assoc($featured)):
            $img      = $p['main_image'] ? UPLOADS_URL . $p['main_image'] : 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=400&q=80';
            $is_oos   = $p['status'] === 'out_of_stock';
            $discount = ($p['old_price'] && $p['old_price'] > $p['price'])
                ? round((($p['old_price'] - $p['price']) / $p['old_price']) * 100)
                : 0;
        ?>
        <a href="product.php?id=<?php echo $p['id']; ?>"
           class="product-card bg-white rounded-3xl overflow-hidden shadow-sm block relative group">
            <div class="relative bg-fg-light overflow-hidden" style="height:220px">
                <img src="<?php echo htmlspecialchars($img); ?>"
                     alt="<?php echo htmlspecialchars($p['name']); ?>"
                     class="w-full h-full object-cover"
                     onerror="this.src='https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=400&q=80'">
                <div class="absolute top-3 left-3 flex flex-col gap-1">
                    <?php if($p['is_new']): ?>
                    <span class="bg-fg-blue text-white text-xs font-semibold px-2 py-1 rounded-full">New</span>
                    <?php endif; ?>
                    <?php if($discount > 0): ?>
                    <span class="bg-red-500 text-white text-xs font-semibold px-2 py-1 rounded-full">-<?php echo $discount; ?>%</span>
                    <?php endif; ?>
                </div>
                <?php if($is_oos): ?>
                <div class="oos-overlay rounded-none">
                    <span class="bg-fg-dark text-white text-xs font-semibold px-3 py-1.5 rounded-full">Out of Stock</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="p-5">
                <p class="text-xs text-fg-gray mb-1"><?php echo htmlspecialchars($p['brand'] ?? $p['category_name']); ?></p>
                <h3 class="font-semibold text-fg-dark text-sm leading-tight mb-3 line-clamp-2">
                    <?php echo htmlspecialchars($p['name']); ?>
                </h3>
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-lg font-bold text-fg-dark"><?php echo format_price($p['price']); ?></span>
                    <?php if($p['old_price']): ?>
                    <span class="text-sm text-fg-gray line-through"><?php echo format_price($p['old_price']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if(!$is_oos): ?>
                <button onclick="event.preventDefault(); addToCart(<?php echo $p['id']; ?>, this)"
                    class="w-full bg-fg-blue text-white text-sm font-medium py-2.5 rounded-xl hover:bg-fg-blue-dark transition-colors duration-200">
                    Add to Cart
                </button>
                <?php else: ?>
                <button disabled class="w-full bg-gray-100 text-gray-400 text-sm font-medium py-2.5 rounded-xl cursor-not-allowed">
                    Out of Stock
                </button>
                <?php endif; ?>
            </div>
        </a>
        <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- ============ PROMO BANNER ============ -->
<section class="promo-bg py-20 reveal">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-col md:flex-row items-center justify-between gap-8">
        <div class="text-white text-center md:text-left">
            <p class="text-xs font-semibold tracking-widest text-blue-400 uppercase mb-3">Limited Time</p>
            <h2 class="text-4xl md:text-5xl font-black tracking-tight mb-4">Up to 30% Off<br>Top Brands.</h2>
            <p class="text-gray-400 text-lg font-light">Shop deals on Apple, Samsung, Sony and more.</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-4">
            <a href="shop.php" class="btn-primary text-center">Shop Deals</a>
            <a href="shop.php?category=phones" class="border border-white text-white py-3 px-7 rounded-full font-medium hover:bg-white hover:text-fg-dark transition-all duration-200 text-center text-sm">
                See Phones
            </a>
        </div>
    </div>
</section>

<!-- ============ NEW ARRIVALS ============ -->
<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-12 reveal">
            <p class="section-eyebrow mb-3">Just Dropped</p>
            <h2 class="text-4xl md:text-5xl font-bold tracking-tight">New Arrivals.</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php
        mysqli_data_seek($newest, 0);
        while($p = mysqli_fetch_assoc($newest)):
            $img    = $p['main_image'] ? UPLOADS_URL . $p['main_image'] : 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=400&q=80';
            $is_oos = $p['status'] === 'out_of_stock';
        ?>
        <a href="product.php?id=<?php echo $p['id']; ?>"
           class="product-card bg-fg-light rounded-3xl overflow-hidden block group">
            <div class="bg-white m-4 rounded-2xl overflow-hidden flex items-center justify-center" style="height:200px">
                <img src="<?php echo htmlspecialchars($img); ?>"
                     alt="<?php echo htmlspecialchars($p['name']); ?>"
                     class="h-full w-full object-cover"
                     onerror="this.src='https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=400&q=80'">
            </div>
            <div class="px-5 pb-5">
                <span class="text-xs text-fg-blue font-semibold">New</span>
                <h3 class="font-semibold text-sm mt-1 mb-2 leading-tight"><?php echo htmlspecialchars($p['name']); ?></h3>
                <div class="flex items-center justify-between">
                    <span class="font-bold"><?php echo format_price($p['price']); ?></span>
                    <?php if(!$is_oos): ?>
                    <button onclick="event.preventDefault(); addToCart(<?php echo $p['id']; ?>, this)"
                        class="text-xs bg-fg-blue text-white px-3 py-1.5 rounded-full hover:bg-fg-blue-dark transition-colors">
                        + Cart
                    </button>
                    <?php else: ?>
                    <span class="text-xs bg-gray-200 text-gray-500 px-3 py-1.5 rounded-full">Out of Stock</span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- ============ WHY FRANK GADGETS ============ -->
<section class="py-20 bg-fg-light reveal">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-16">
            <p class="section-eyebrow mb-3">Why Choose Us</p>
            <h2 class="text-4xl md:text-5xl font-bold tracking-tight">The Frank Difference.</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <?php
            $features = [
                ['icon'=>'🔒','title'=>'100% Genuine','desc'=>'Every product is verified authentic. No counterfeits, ever.'],
                ['icon'=>'🚚','title'=>'Fast Delivery','desc'=>'Swift nationwide delivery. Lagos same-day available.'],
                ['icon'=>'🛡️','title'=>'Warranty','desc'=>'All products come with full manufacturer warranty.'],
                ['icon'=>'💬','title'=>'Expert Support','desc'=>'Our tech experts are available 7 days a week.'],
            ];
            foreach($features as $f):
            ?>
            <div class="text-center">
                <div class="text-5xl mb-5"><?php echo $f['icon']; ?></div>
                <h3 class="font-bold text-lg mb-2"><?php echo $f['title']; ?></h3>
                <p class="text-fg-gray text-sm leading-relaxed"><?php echo $f['desc']; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ FOOTER ============ -->
<footer class="bg-fg-dark text-white pt-16 pb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">

        <div class="grid grid-cols-2 md:grid-cols-4 gap-10 mb-12">

            <!-- Brand col -->
            <div class="col-span-2 md:col-span-1">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-9 h-9 bg-white rounded-lg flex items-center justify-center overflow-hidden p-1 flex-shrink-0">
                        <img src="assets/images/logo.png" alt="FG" class="w-full h-full object-contain">
                    </div>
                    <span class="font-bold text-white text-base tracking-tight">Frank Gadgets</span>
                </div>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Nigeria's most trusted premium gadget store. Quality tech, delivered to you.
                </p>
            </div>

            <!-- Shop -->
            <div>
                <h4 class="font-semibold text-sm mb-4 text-gray-300">Shop</h4>
                <div class="flex flex-col gap-2">
                    <?php foreach(array_slice($nav_cats, 0, 5) as $c): ?>
                    <a href="shop.php?category=<?php echo $c['slug']; ?>"
                       class="text-gray-400 text-sm hover:text-white transition-colors">
                        <?php echo htmlspecialchars($c['name']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Help -->
            <div>
                <h4 class="font-semibold text-sm mb-4 text-gray-300">Help</h4>
                <div class="flex flex-col gap-2">
                    <a href="#" class="text-gray-400 text-sm hover:text-white transition-colors">Track Order</a>
                    <a href="#" class="text-gray-400 text-sm hover:text-white transition-colors">Returns</a>
                    <a href="#" class="text-gray-400 text-sm hover:text-white transition-colors">Warranty</a>
                    <a href="#" class="text-gray-400 text-sm hover:text-white transition-colors">Contact Us</a>
                </div>
            </div>

            <!-- Contact -->
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
            <p class="text-gray-500 text-xs">© 2026 Frank Gadgets. All rights reserved.</p>
            <div class="flex items-center gap-4">
                <a href="admin/login.php" class="text-gray-600 text-xs hover:text-gray-400 transition-colors">Admin</a>
                <a href="#" class="text-gray-600 text-xs hover:text-gray-400 transition-colors">Privacy</a>
                <a href="#" class="text-gray-600 text-xs hover:text-gray-400 transition-colors">Terms</a>
            </div>
        </div>

    </div>
</footer>

<!-- ============ TOAST NOTIFICATION ============ -->
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

// Scroll reveal
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => { if(e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.12 });
document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

// Add to Cart
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
            else {
                const b = document.createElement('span');
                b.className = 'cart-badge';
                b.textContent = data.cart_count;
                document.querySelector('a[href="cart.php"]').style.position = 'relative';
                document.querySelector('a[href="cart.php"]').appendChild(b);
            }
        } else {
            showToast(data.message || 'Something went wrong');
        }
    })
    .catch(() => showToast('Network error'))
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