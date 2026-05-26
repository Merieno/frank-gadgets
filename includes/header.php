<?php
/**
 * includes/header.php
 * Usage: include 'includes/header.php';
 * Set $page_title before including, e.g. $page_title = 'Shop';
 */
$page_title = isset($page_title) ? $page_title . ' — Frank Gadgets' : 'Frank Gadgets — Premium Tech Store';
$cart_count = isset($conn) ? get_cart_count($conn) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($page_title); ?></title>
<meta name="description" content="<?php echo isset($page_desc) ? htmlspecialchars($page_desc) : 'Frank Gadgets — Nigeria\'s most trusted premium gadget store. Phones, laptops, wearables and more.'; ?>">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<!-- Tailwind -->
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
                }
            }
        }
    }
}
</script>

<!-- Main CSS -->
<link rel="stylesheet" href="<?php echo isset($base_path) ? $base_path : ''; ?>assets/css/style.css">
</head>
<body class="bg-white font-sans text-fg-dark">