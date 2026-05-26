<?php
require 'auth.php';
include '../config/db.php';

$id = intval($_GET['id'] ?? 0);
$product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE id=$id LIMIT 1"));
if (!$product) { header('Location: products.php'); exit; }

$images_res = mysqli_query($conn, "SELECT * FROM product_images WHERE product_id=$id ORDER BY is_main DESC");
$images = [];
while ($img = mysqli_fetch_assoc($images_res)) $images[] = $img;

$errors = [];

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

    if (!$name)        $errors[] = 'Product name required.';
    if (!$category_id) $errors[] = 'Category required.';
    if ($price <= 0)   $errors[] = 'Valid price required.';

    if (empty($errors)) {
        $old_p = $old_price > 0 ? $old_price : 'NULL';
        mysqli_query($conn, "
            UPDATE products SET
                name='$name', category_id=$category_id, brand='$brand',
                price=$price, old_price=$old_p, stock=$stock,
                description='$description', specs='$specs',
                status='$status', is_featured=$is_featured, is_new=$is_new
            WHERE id=$id
        ");

        // Delete selected images
        if (!empty($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $img_id) {
                $img_id = intval($img_id);
                $img = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM product_images WHERE id=$img_id AND product_id=$id LIMIT 1"));
                if ($img) {
                    @unlink(UPLOADS_PATH . $img['image']);
                    mysqli_query($conn, "DELETE FROM product_images WHERE id=$img_id");
                }
            }
        }

        // Set main image
        if (!empty($_POST['main_image_id'])) {
            $mid = intval($_POST['main_image_id']);
            mysqli_query($conn, "UPDATE product_images SET is_main=0 WHERE product_id=$id");
            mysqli_query($conn, "UPDATE product_images SET is_main=1 WHERE id=$mid AND product_id=$id");
        }

        // New images
        if (!empty($_FILES['new_images']['name'][0])) {
            $upload_dir = UPLOADS_PATH;
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $slug = $product['slug'];
            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            foreach ($_FILES['new_images']['tmp_name'] as $i => $tmp) {
                if ($_FILES['new_images']['error'][$i] !== 0) continue;
                if (!in_array($_FILES['new_images']['type'][$i], $allowed)) continue;
                $ext = pathinfo($_FILES['new_images']['name'][$i], PATHINFO_EXTENSION);
                $filename = $slug . '-' . time() . '-' . $i . '.' . $ext;
                if (move_uploaded_file($tmp, $upload_dir . $filename)) {
                    mysqli_query($conn, "INSERT INTO product_images (product_id, image, is_main) VALUES ($id, '$filename', 0)");
                }
            }
        }

        header('Location: products.php?updated=1');
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
<title>Edit Product — Frank Gadgets Admin</title>
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
            <a href="products.php"    class="sidebar-link active">🛍️ Products</a>
            <a href="add-product.php" class="sidebar-link">➕ Add Product</a>
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
                <h1 class="text-2xl font-bold text-gray-900">Edit Product</h1>
                <a href="../product.php?id=<?php echo $id; ?>" target="_blank" class="text-xs text-blue-600 hover:underline ml-auto">View on store →</a>
            </div>

            <?php if(!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 rounded-2xl p-4 mb-5">
                <?php foreach($errors as $e): ?><p class="text-sm text-red-600">• <?php echo htmlspecialchars($e); ?></p><?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                <div class="lg:col-span-2 space-y-5">
                    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
                        <h2 class="font-bold text-gray-900">Basic Information</h2>
                        <div>
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Category *</label>
                                <select name="category_id" class="form-input" required>
                                    <?php foreach($all_cats as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $product['category_id']==$c['id']?'selected':''; ?>>
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Brand</label>
                                <input type="text" name="brand" class="form-input" value="<?php echo htmlspecialchars($product['brand']??''); ?>">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-input" rows="5"><?php echo htmlspecialchars($product['description']??''); ?></textarea>
                        </div>
                        <div>
                            <label class="form-label">Specifications <span class="text-gray-400 font-normal">(Key: Value per line)</span></label>
                            <textarea name="specs" class="form-input" rows="5"><?php echo htmlspecialchars($product['specs']??''); ?></textarea>
                        </div>
                    </div>

                    <!-- Existing images -->
                    <?php if(!empty($images)): ?>
                    <div class="bg-white rounded-2xl shadow-sm p-5">
                        <h2 class="font-bold text-gray-900 mb-4">Current Images</h2>
                        <div class="flex flex-wrap gap-4">
                            <?php foreach($images as $img): ?>
                            <div class="relative">
                                <img src="<?php echo UPLOADS_URL . $img['image']; ?>" alt="" class="w-24 h-24 object-cover rounded-xl border-2 <?php echo $img['is_main']?'border-blue-500':'border-gray-200'; ?>">
                                <?php if($img['is_main']): ?>
                                <span class="absolute -top-1 -right-1 bg-blue-600 text-white text-xs px-1.5 py-0.5 rounded-full">Main</span>
                                <?php endif; ?>
                                <div class="mt-2 space-y-1">
                                    <?php if(!$img['is_main']): ?>
                                    <label class="flex items-center gap-1 text-xs cursor-pointer">
                                        <input type="radio" name="main_image_id" value="<?php echo $img['id']; ?>"> Set main
                                    </label>
                                    <?php endif; ?>
                                    <label class="flex items-center gap-1 text-xs text-red-500 cursor-pointer">
                                        <input type="checkbox" name="delete_images[]" value="<?php echo $img['id']; ?>"> Delete
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Add new images -->
                    <div class="bg-white rounded-2xl shadow-sm p-5">
                        <h2 class="font-bold text-gray-900 mb-4">Add More Images</h2>
                        <input type="file" name="new_images[]" multiple accept="image/*"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                </div>

                <div class="space-y-5">
                    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
                        <h2 class="font-bold text-gray-900">Pricing & Stock</h2>
                        <div>
                            <label class="form-label">Price (₦) *</label>
                            <input type="number" name="price" class="form-input" value="<?php echo $product['price']; ?>" step="0.01" min="0" required>
                        </div>
                        <div>
                            <label class="form-label">Old Price (₦)</label>
                            <input type="number" name="old_price" class="form-input" value="<?php echo $product['old_price']??''; ?>" step="0.01" min="0">
                        </div>
                        <div>
                            <label class="form-label">Stock</label>
                            <input type="number" name="stock" class="form-input" value="<?php echo $product['stock']; ?>" min="0">
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-4">
                        <h2 class="font-bold text-gray-900">Status & Flags</h2>
                        <div>
                            <label class="form-label">Status</label>
                            <select name="status" class="form-input">
                                <option value="active"       <?php echo $product['status']==='active'?'selected':''; ?>>Active</option>
                                <option value="draft"        <?php echo $product['status']==='draft'?'selected':''; ?>>Draft</option>
                                <option value="out_of_stock" <?php echo $product['status']==='out_of_stock'?'selected':''; ?>>Out of Stock</option>
                            </select>
                        </div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_featured" value="1" <?php echo $product['is_featured']?'checked':''; ?> class="w-4 h-4 accent-blue-600">
                            <span class="text-sm font-medium text-gray-700">Featured Product</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_new" value="1" <?php echo $product['is_new']?'checked':''; ?> class="w-4 h-4 accent-blue-600">
                            <span class="text-sm font-medium text-gray-700">Mark as New</span>
                        </label>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-xl hover:bg-blue-700 transition-colors">
                        Save Changes →
                    </button>
                </div>

            </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>