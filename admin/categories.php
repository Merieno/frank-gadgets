<?php
require 'auth.php';
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name = clean($conn, $_POST['name'] ?? '');
        $slug = make_slug($name);
        $icon = clean($conn, $_POST['icon'] ?? '');
        $sort = intval($_POST['sort_order'] ?? 0);
        if ($name) mysqli_query($conn, "INSERT INTO categories (name, slug, icon, sort_order) VALUES ('$name', '$slug', '$icon', $sort)");
    }
    if ($_POST['action'] === 'delete') { $cid = intval($_POST['cat_id']); mysqli_query($conn, "DELETE FROM categories WHERE id=$cid"); }
    if ($_POST['action'] === 'edit') {
        $cid = intval($_POST['cat_id']); $name = clean($conn, $_POST['name'] ?? '');
        $icon = clean($conn, $_POST['icon'] ?? ''); $sort = intval($_POST['sort_order'] ?? 0);
        mysqli_query($conn, "UPDATE categories SET name='$name', icon='$icon', sort_order=$sort WHERE id=$cid");
    }
    header('Location: categories.php'); exit;
}

$cats = mysqli_query($conn, "SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id ORDER BY c.sort_order ASC");
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='pending'"))['c'];
$msg_unread = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM messages WHERE is_read = 0"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Categories — Frank Gadgets Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
*{-webkit-font-smoothing:antialiased}body{font-family:'Inter',sans-serif}
.sidebar-link{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;font-size:14px;font-weight:500;color:#6b7280;transition:all .2s;text-decoration:none}
.sidebar-link:hover{background:#f3f4f6;color:#111827}.sidebar-link.active{background:#eff6ff;color:#2563eb;font-weight:600}
.form-input{width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:9px 12px;font-size:14px;outline:none;transition:border-color .2s;font-family:inherit}
.form-input:focus{border-color:#2563eb}
#sidebar-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:40;display:none}#sidebar-overlay.open{display:block}
@media(max-width:768px){#sidebar{position:fixed;top:0;left:0;bottom:0;z-index:50;transform:translateX(-100%);transition:transform .3s ease}#sidebar.open{transform:translateX(0)}}
.mobile-topbar{display:none;position:sticky;top:0;z-index:30;background:#fff;border-bottom:1px solid #f3f4f6;padding:0 16px;height:56px;align-items:center;justify-content:space-between}
@media(max-width:768px){.mobile-topbar{display:flex}}
</style>
</head>
<body class="bg-gray-50">
<div class="mobile-topbar">
    <button onclick="toggleSidebar()" class="p-2 -ml-2 rounded-lg hover:bg-gray-100"><svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    <span class="font-bold text-sm text-gray-900">Categories</span>
    <div class="w-10"></div>
</div>
<div id="sidebar-overlay" onclick="toggleSidebar()"></div>
<div class="flex h-screen overflow-hidden">
    <aside id="sidebar" class="w-56 bg-white border-r border-gray-100 flex flex-col flex-shrink-0">
        <div class="p-5 border-b border-gray-100"><div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="w-8 h-8 bg-white border border-gray-200 rounded-lg flex items-center justify-center overflow-hidden p-0.5"><img src="../assets/images/logo.png" alt="FG" class="w-full h-full object-contain"></div><div><p class="font-bold text-sm text-gray-900 leading-none">Frank Gadgets</p><p class="text-xs text-gray-400">Admin Panel</p></div></div><button onclick="toggleSidebar()" class="md:hidden p-1.5 rounded-lg hover:bg-gray-100 -mr-1"><svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg></button></div></div>
        <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
            <a href="index.php" class="sidebar-link">📊 Dashboard</a>
            <a href="orders.php" class="sidebar-link">📦 Orders<?php if($pending_count>0):?><span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?=$pending_count?></span><?php endif;?></a>
            <a href="products.php" class="sidebar-link">🛍️ Products</a>
            <a href="add-product.php" class="sidebar-link">➕ Add Product</a>
            <a href="categories.php" class="sidebar-link active">📂 Categories</a>
            <a href="messages.php" class="sidebar-link">💬 Messages<?php if($msg_unread>0):?><span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?=$msg_unread?></span><?php endif;?></a>
        </nav>
        <div class="p-3 border-t border-gray-100"><a href="logout.php" class="sidebar-link text-red-500 hover:bg-red-50">🚪 Logout</a><a href="../index.php" target="_blank" class="sidebar-link">🌐 View Store</a></div>
    </aside>
    <main class="flex-1 overflow-y-auto">
        <div class="p-6 max-w-3xl mx-auto">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">Categories</h1>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-gray-900 mb-4">Add New Category</h2>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="add">
                        <div><label class="text-xs font-semibold text-gray-600 block mb-1">Name *</label><input type="text" name="name" class="form-input" placeholder="e.g. Tablets" required></div>
                        <div><label class="text-xs font-semibold text-gray-600 block mb-1">Icon (emoji)</label><input type="text" name="icon" class="form-input" placeholder="e.g. 📱"></div>
                        <div><label class="text-xs font-semibold text-gray-600 block mb-1">Sort Order</label><input type="number" name="sort_order" class="form-input" placeholder="0" value="0"></div>
                        <button type="submit" class="w-full bg-blue-600 text-white font-semibold py-2.5 rounded-xl hover:bg-blue-700 text-sm">Add Category</button>
                    </form>
                </div>
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100"><h2 class="font-bold text-gray-900">All Categories</h2></div>
                    <div class="divide-y divide-gray-50">
                    <?php $has=false;while($c=mysqli_fetch_assoc($cats)):$has=true;?>
                    <div class="px-5 py-3.5 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0"><span class="text-xl"><?=$c['icon']?:'📦'?></span><div class="min-w-0"><p class="font-medium text-sm text-gray-800 truncate"><?=htmlspecialchars($c['name'])?></p><p class="text-xs text-gray-400"><?=$c['product_count']?> products · <?=$c['slug']?></p></div></div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button onclick="editCat(<?=$c['id']?>,'<?=htmlspecialchars($c['name'],ENT_QUOTES)?>','<?=$c['icon']?>',<?=$c['sort_order']?>)" class="text-xs text-blue-600 hover:underline font-medium">Edit</button>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this category?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="cat_id" value="<?=$c['id']?>"><button type="submit" class="text-xs text-red-500 hover:underline font-medium">Delete</button></form>
                        </div>
                    </div>
                    <?php endwhile;if(!$has):?><p class="text-center py-8 text-sm text-gray-400">No categories yet</p><?php endif;?>
                    </div>
                </div>
            </div>
            <div id="edit-modal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center px-4">
                <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm">
                    <h3 class="font-bold text-gray-900 mb-4">Edit Category</h3>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="edit"><input type="hidden" name="cat_id" id="edit-cat-id">
                        <div><label class="text-xs font-semibold text-gray-600 block mb-1">Name</label><input type="text" name="name" id="edit-cat-name" class="form-input" required></div>
                        <div><label class="text-xs font-semibold text-gray-600 block mb-1">Icon (emoji)</label><input type="text" name="icon" id="edit-cat-icon" class="form-input"></div>
                        <div><label class="text-xs font-semibold text-gray-600 block mb-1">Sort Order</label><input type="number" name="sort_order" id="edit-cat-sort" class="form-input"></div>
                        <div class="flex gap-3"><button type="button" onclick="document.getElementById('edit-modal').classList.add('hidden')" class="flex-1 border border-gray-200 text-gray-600 font-medium py-2.5 rounded-xl hover:bg-gray-50 text-sm">Cancel</button><button type="submit" class="flex-1 bg-blue-600 text-white font-semibold py-2.5 rounded-xl hover:bg-blue-700 text-sm">Save</button></div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebar-overlay').classList.toggle('open');document.body.style.overflow=document.getElementById('sidebar').classList.contains('open')?'hidden':'';}
function editCat(id,name,icon,sort){document.getElementById('edit-cat-id').value=id;document.getElementById('edit-cat-name').value=name;document.getElementById('edit-cat-icon').value=icon;document.getElementById('edit-cat-sort').value=sort;document.getElementById('edit-modal').classList.remove('hidden');}
</script>
</body></html>
