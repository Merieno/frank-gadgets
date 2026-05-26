<?php
require 'auth.php';
include '../config/db.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = clean($conn, $_POST['name'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $brand       = clean($conn, $_POST['brand'] ?? '');
    $price       = floatval($_POST['price'] ?? 0);
    $old_price   = floatval($_POST['old_price'] ?? 0);
    $stock       = intval($_POST['stock'] ?? 0);
    $description = clean($conn, $_POST['description'] ?? '');
    $specs       = clean($conn, $_POST['specs'] ?? '');
    $status      = clean($conn, $_POST['status'] ?? 'active');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_new      = isset($_POST['is_new']) ? 1 : 0;
    $slug        = make_slug($name);

    if (!$name)        $errors[] = 'Product name is required.';
    if (!$category_id) $errors[] = 'Category is required.';
    if ($price <= 0)   $errors[] = 'Valid price is required.';

    // Check slug unique
    $existing_slug = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM products WHERE slug='$slug' LIMIT 1"));
    if ($existing_slug) $slug .= '-' . time();

    if (empty($errors)) {
        $old_p = $old_price > 0 ? $old_price : 'NULL';
        mysqli_query($conn, "
            INSERT INTO products (name, slug, category_id, brand, price, old_price, stock, description, specs, status, is_featured, is_new)
            VALUES ('$name', '$slug', $category_id, '$brand', $price, $old_p, $stock, '$description', '$specs', '$status', $is_featured, $is_new)
        ");
        $product_id = mysqli_insert_id($conn);

        // Handle image uploads
        if (!empty($_FILES['images']['name'][0])) {
            $upload_dir = UPLOADS_PATH;
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            $is_first = true;
            foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
                if ($_FILES['images']['error'][$i] !== 0) continue;
                if (!in_array($_FILES['images']['type'][$i], $allowed)) continue;
                $ext      = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                $filename = $slug . '-' . time() . '-' . $i . '.' . $ext;
                if (move_uploaded_file($tmp, $upload_dir . $filename)) {
                    $is_main = $is_first ? 1 : 0;
                    mysqli_query($conn, "INSERT INTO product_images (product_id, image, is_main) VALUES ($product_id, '$filename', $is_main)");
                    $is_first = false;
                }
            }
        }

        header('Location: products.php?added=1');
        exit;
    }
}

$cats_res = mysqli_query($conn, "SELECT * FROM categories ORDER BY sort_order");
$all_cats = [];
while ($c = mysqli_fetch_assoc($cats_res)) $all_cats[] = $c;
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='pending'"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Product — Frank Gadgets Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { -webkit-font-smoothing: antialiased; }
body { font-family: 'Inter', sans-serif; }
.sidebar-link { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; font-size:14px; font-weight:500; color:#6b7280; transition:all 0.2s; text-decoration:none; }
.sidebar-link:hover { background:#f3f4f6; color:#111827; }
.sidebar-link.active { background:#eff6ff; color:#2563eb; font-weight:600; }
.form-input { width:100%; border:1.5px solid #e5e7eb; border-radius:10px; padding:10px 12px; font-size:14px; outline:none; transition:border-color 0.2s; font-family:inherit; }
.form-input:focus { border-color:#2563eb; }
.form-label { font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; display:block; }
.upload-area { border:2px dashed #e5e7eb; border-radius:12px; padding:32px; text-align:center; cursor:pointer; transition:all 0.2s; }
.upload-area:hover { border-color:#2563eb; background:#f0f7ff; }
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
            <a href="orders.php"      class="sidebar-link">📦 Orders
                <?php if($pending_count>0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="products.php"    class="sidebar-link">🛍️ Products</a>
            <a href="add-product.php" class="sidebar-link active">➕ Add Product</a>
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
                <a href="products.php" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                </a>
                <h1 class="text-2xl font-bold text-gray-900">Add Product</h1>
            </div>

            <?php if(!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 rounded-2xl p-4 mb-5">
                <?php foreach($errors as $e): ?>
                <p class="text-sm text-red-600">• <?php echo htmlspecialchars($e); ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                <!-- Main fields -->
                <div class="lg:col-span-2 space-y-5">

                    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
                        <h2 class="font-bold text-gray-900">Basic Information</h2>
                        <div>
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($_POST['name']??''); ?>" placeholder="e.g. iPhone 15 Pro Max 256GB" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Category *</label>
                                <select name="category_id" class="form-input" required>
                                    <option value="">Select category</option>
                                    <?php foreach($all_cats as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($_POST['category_id']??'')==$c['id']?'selected':''; ?>>
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Brand</label>
                                <input type="text" name="brand" class="form-input" value="<?php echo htmlspecialchars($_POST['brand']??''); ?>" placeholder="e.g. Apple">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-input" rows="5" placeholder="Product description..."><?php echo htmlspecialchars($_POST['description']??''); ?></textarea>
                        </div>
                        <div>
                            <label class="form-label">Specifications <span class="text-gray-400 font-normal">(one per line: Key: Value)</span></label>
                            <textarea name="specs" class="form-input" rows="5" placeholder="Display: 6.7 inch OLED&#10;Battery: 4422mAh&#10;RAM: 8GB"><?php echo htmlspecialchars($_POST['specs']??''); ?></textarea>
                        </div>
                    </div>

                    <!-- Images -->
                    <div class="bg-white rounded-2xl shadow-sm p-5">
                        <h2 class="font-bold text-gray-900 mb-4">Product Images</h2>
                        <div class="upload-area" onclick="document.getElementById('images').click()">
                            <div class="text-4xl mb-3">📸</div>
                            <p class="font-semibold text-gray-700 text-sm">Click to upload images</p>
                            <p class="text-xs text-gray-400 mt-1">JPG, PNG, WEBP · First image will be the main image</p>
                        </div>
                        <input type="file" name="images[]" id="images" multiple accept="image/*" class="hidden" onchange="previewImages(this)">
                        <div id="preview" class="flex flex-wrap gap-3 mt-4"></div>
                    </div>

                </div>

                <!-- Sidebar fields -->
                <div class="space-y-5">

                    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
                        <h2 class="font-bold text-gray-900">Pricing & Stock</h2>
                        <div>
                            <label class="form-label">Price (₦) *</label>
                            <input type="number" name="price" class="form-input" value="<?php echo $_POST['price']??''; ?>" placeholder="0.00" step="0.01" min="0" required>
                        </div>
                        <div>
                            <label class="form-label">Old Price (₦) <span class="text-gray-400 font-normal">optional</span></label>
                            <input type="number" name="old_price" class="form-input" value="<?php echo $_POST['old_price']??''; ?>" placeholder="0.00" step="0.01" min="0">
                        </div>
                        <div>
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" name="stock" class="form-input" value="<?php echo $_POST['stock']??0; ?>" min="0">
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
                        <h2 class="font-bold text-gray-900">Status & Flags</h2>
                        <div>
                            <label class="form-label">Status</label>
                            <select name="status" class="form-input">
                                <option value="active"       <?php echo ($_POST['status']??'active')==='active'?'selected':''; ?>>Active</option>
                                <option value="draft"        <?php echo ($_POST['status']??'')==='draft'?'selected':''; ?>>Draft</option>
                                <option value="out_of_stock" <?php echo ($_POST['status']??'')==='out_of_stock'?'selected':''; ?>>Out of Stock</option>
                            </select>
                        </div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_featured" value="1" <?php echo isset($_POST['is_featured'])?'checked':''; ?> class="w-4 h-4 accent-blue-600 rounded">
                            <span class="text-sm font-medium text-gray-700">Featured Product</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_new" value="1" <?php echo isset($_POST['is_new'])?'checked':'checked'; ?> class="w-4 h-4 accent-blue-600 rounded">
                            <span class="text-sm font-medium text-gray-700">Mark as New</span>
                        </label>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-xl hover:bg-blue-700 transition-colors">
                        Add Product →
                    </button>
                    <a href="products.php" class="block text-center text-sm text-gray-400 hover:text-gray-600 transition-colors">Cancel</a>
                </div>

            </div>
            </form>
        </div>
    </main>
</div>

<script>
function previewImages(input) {
    const preview = document.getElementById('preview');
    preview.innerHTML = '';
    Array.from(input.files).forEach((file, i) => {
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.className = 'relative';
            div.innerHTML = `
                <img src="${e.target.result}" class="w-20 h-20 object-cover rounded-xl border-2 ${i===0?'border-blue-500':'border-gray-200'}">
                ${i===0?'<span class="absolute -top-1 -right-1 bg-blue-600 text-white text-xs px-1.5 py-0.5 rounded-full">Main</span>':''}
            `;
            preview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}
</script>
</body>
</html>