<?php
require 'auth.php';
include '../config/db.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: products.php'); exit; }

// Get product images to delete from disk
$images_res = mysqli_query($conn, "SELECT image FROM product_images WHERE product_id=$id");
while ($img = mysqli_fetch_assoc($images_res)) {
    $file = UPLOADS_PATH . $img['image'];
    if (file_exists($file)) @unlink($file);
}

// Delete from DB (cascade handles product_images & cart)
mysqli_query($conn, "DELETE FROM products WHERE id=$id");

header('Location: products.php?deleted=1');
exit;