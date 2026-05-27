<?php
require 'auth.php';
include '../config/db.php';

// Sidebar counts
$pending_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status='pending'"))['c'];

// ── Handle actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = (int) $_POST['id'];
    $filter_param = isset($_GET['filter']) && $_GET['filter'] !== 'all' ? '?filter=' . $_GET['filter'] : '';

    switch ($_POST['action']) {
        case 'mark_read':
            $conn->query("UPDATE messages SET is_read = 1 WHERE id = $id");
            break;
        case 'mark_unread':
            $conn->query("UPDATE messages SET is_read = 0 WHERE id = $id");
            break;
        case 'delete':
            $conn->query("DELETE FROM messages WHERE id = $id");
            break;
    }
    header('Location: messages.php' . $filter_param);
    exit;
}

// ── Filter ──
$filter = $_GET['filter'] ?? 'all';
$where  = '';
if ($filter === 'unread') $where = 'WHERE is_read = 0';
if ($filter === 'read')   $where = 'WHERE is_read = 1';

// ── Search ──
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $safe   = $conn->real_escape_string($search);
    $where  = $where ? "$where AND" : 'WHERE';
    $where .= " (name LIKE '%$safe%' OR email LIKE '%$safe%' OR subject LIKE '%$safe%' OR message LIKE '%$safe%')";
}

// ── Fetch messages ──
$result   = $conn->query("SELECT * FROM messages $where ORDER BY created_at DESC");
$messages = [];
while ($row = $result->fetch_assoc()) $messages[] = $row;

// ── Counts ──
$total_count  = $conn->query("SELECT COUNT(*) as c FROM messages")->fetch_assoc()['c'];
$unread_count = $conn->query("SELECT COUNT(*) as c FROM messages WHERE is_read = 0")->fetch_assoc()['c'];
$read_count   = $total_count - $unread_count;
$msg_unread   = $unread_count;

// ── Selected message ──
$selected = null;
if (isset($_GET['view'])) {
    $view_id = (int) $_GET['view'];
    foreach ($messages as $m) {
        if ($m['id'] == $view_id) { $selected = $m; break; }
    }
    if ($selected && !$selected['is_read']) {
        $conn->query("UPDATE messages SET is_read = 1 WHERE id = $view_id");
        $selected['is_read'] = 1;
        $unread_count = max(0, $unread_count - 1);
        $read_count   = $total_count - $unread_count;
        $msg_unread   = $unread_count;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages — Frank Gadgets Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { -webkit-font-smoothing: antialiased; }
body { font-family: 'Inter', sans-serif; }
.sidebar-link {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; border-radius: 10px;
    font-size: 14px; font-weight: 500; color: #6b7280;
    transition: all 0.2s; text-decoration: none;
}
.sidebar-link:hover { background: #f3f4f6; color: #111827; }
.sidebar-link.active { background: #eff6ff; color: #2563eb; font-weight: 600; }

/* Message list */
.msg-item {
    display: flex; gap: 12px; padding: 14px 20px;
    border-bottom: 1px solid #f3f4f6; cursor: pointer;
    transition: background 0.1s; text-decoration: none; color: inherit;
}
.msg-item:hover { background: #fafafa; }
.msg-item.active { background: #eff6ff; }
.msg-item.unread { border-left: 3px solid #2563eb; }
.msg-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: #f3f4f6; display: flex; align-items: center;
    justify-content: center; font-size: 14px; font-weight: 700;
    color: #2563eb; flex-shrink: 0; text-transform: uppercase;
}
.msg-dot { width: 8px; height: 8px; border-radius: 50%; background: #2563eb; flex-shrink: 0; }

/* Filter tabs */
.filter-tab {
    padding: 5px 14px; border-radius: 980px; font-size: 12px; font-weight: 600;
    border: 1px solid #e5e7eb; background: white; color: #6b7280;
    cursor: pointer; transition: all 0.15s; text-decoration: none;
    display: inline-flex; align-items: center; gap: 4px;
}
.filter-tab:hover { background: #f3f4f6; color: #111827; }
.filter-tab.active { background: #2563eb; color: #fff; border-color: #2563eb; }
.filter-badge { font-size: 10px; min-width: 16px; text-align: center; padding: 0 4px; border-radius: 999px; }
.filter-tab.active .filter-badge { background: rgba(255,255,255,0.3); }
.filter-tab:not(.active) .filter-badge { background: #f3f4f6; color: #6b7280; }

/* Detail */
.detail-body {
    background: white; border: 1px solid #f3f4f6;
    border-radius: 16px; padding: 24px;
    font-size: 14px; line-height: 1.8; color: #374151; white-space: pre-wrap;
}
.action-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 10px; font-size: 13px;
    font-weight: 600; cursor: pointer; border: 1px solid #e5e7eb;
    background: white; color: #111827; transition: all 0.15s; font-family: inherit;
}
.action-btn:hover { background: #f3f4f6; }
.action-btn.primary { background: #2563eb; color: #fff; border-color: #2563eb; }
.action-btn.primary:hover { background: #1d4ed8; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #dc2626; }

/* ── Mobile sidebar ── */
#sidebar-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.4);
    z-index: 40; display: none;
}
#sidebar-overlay.open { display: block; }
@media (max-width: 768px) {
    #sidebar {
        position: fixed; top: 0; left: 0; bottom: 0;
        z-index: 50; transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    #sidebar.open { transform: translateX(0); }
}
.mobile-topbar {
    display: none; position: sticky; top: 0; z-index: 30;
    background: white; border-bottom: 1px solid #f3f4f6;
    padding: 0 16px; height: 56px;
    align-items: center; justify-content: space-between;
}
@media (max-width: 768px) {
    .mobile-topbar { display: flex; }
}
</style>
</head>
<body class="bg-gray-50">

<!-- ══════════ MOBILE TOP BAR ══════════ -->
<div class="mobile-topbar">
    <button onclick="toggleSidebar()" class="p-2 -ml-2 rounded-lg hover:bg-gray-100">
        <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
    <div class="flex items-center gap-2">
        <div class="w-7 h-7 bg-white border border-gray-200 rounded-lg flex items-center justify-center overflow-hidden p-0.5">
            <img src="../assets/images/logo.png" alt="FG" class="w-full h-full object-contain">
        </div>
        <span class="font-bold text-sm text-gray-900">Messages</span>
    </div>
    <div class="w-10"></div>
</div>

<!-- ══════════ SIDEBAR OVERLAY ══════════ -->
<div id="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="flex h-screen overflow-hidden">

    <!-- ══════════ SIDEBAR ══════════ -->
    <aside id="sidebar" class="w-56 bg-white border-r border-gray-100 flex flex-col flex-shrink-0">
        <div class="p-5 border-b border-gray-100">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-white border border-gray-200 rounded-lg flex items-center justify-center overflow-hidden p-0.5">
                        <img src="../assets/images/logo.png" alt="FG" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <p class="font-bold text-sm text-gray-900 leading-none">Frank Gadgets</p>
                        <p class="text-xs text-gray-400">Admin Panel</p>
                    </div>
                </div>
                <button onclick="toggleSidebar()" class="md:hidden p-1.5 rounded-lg hover:bg-gray-100 -mr-1">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
            <a href="index.php"       class="sidebar-link">📊 Dashboard</a>
            <a href="orders.php"      class="sidebar-link">📦 Orders
                <?php if($pending_orders > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?= $pending_orders ?></span>
                <?php endif; ?>
            </a>
            <a href="products.php"    class="sidebar-link">🛍️ Products</a>
            <a href="add-product.php" class="sidebar-link">➕ Add Product</a>
            <a href="categories.php"  class="sidebar-link">📂 Categories</a>
            <a href="messages.php"    class="sidebar-link active">💬 Messages
                <?php if($msg_unread > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?= $msg_unread ?></span>
                <?php endif; ?>
            </a>
        </nav>

        <div class="p-3 border-t border-gray-100">
            <div class="flex items-center gap-2 px-2 mb-2">
                <div class="w-7 h-7 bg-blue-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                    <?= strtoupper(substr($_SESSION['admin_name'], 0, 1)) ?>
                </div>
                <span class="text-sm font-medium text-gray-700 truncate"><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
            </div>
            <a href="logout.php" class="sidebar-link text-red-500 hover:bg-red-50 hover:text-red-600">🚪 Logout</a>
            <a href="../index.php" target="_blank" class="sidebar-link">🌐 View Store</a>
        </div>
    </aside>

    <!-- ══════════ MAIN ══════════ -->
    <main class="flex-1 overflow-y-auto">
        <div class="p-6 max-w-6xl mx-auto">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Messages</h1>
                    <p class="text-gray-500 text-sm"><?= $total_count ?> total · <?= $unread_count ?> unread</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 mb-5">
                <a href="messages.php" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All <span class="filter-badge"><?= $total_count ?></span></a>
                <a href="messages.php?filter=unread" class="filter-tab <?= $filter === 'unread' ? 'active' : '' ?>">Unread <span class="filter-badge"><?= $unread_count ?></span></a>
                <a href="messages.php?filter=read" class="filter-tab <?= $filter === 'read' ? 'active' : '' ?>">Read <span class="filter-badge"><?= $read_count ?></span></a>
                <form method="GET" class="ml-auto flex items-center gap-2">
                    <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= $filter ?>"><?php endif; ?>
                    <input type="text" name="q" placeholder="Search…" value="<?= htmlspecialchars($search) ?>"
                           class="border border-gray-200 rounded-xl px-4 py-2 text-sm outline-none focus:border-blue-500 w-40 sm:w-56" style="font-family:inherit;">
                    <button type="submit" class="bg-blue-600 text-white text-sm font-semibold px-4 py-2 rounded-xl hover:bg-blue-700">Search</button>
                </form>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

                <!-- MESSAGE LIST -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="max-h-[70vh] overflow-y-auto">
                        <?php if (empty($messages)): ?>
                        <div class="text-center py-16 px-4">
                            <p class="text-4xl mb-3">📭</p>
                            <p class="font-bold text-gray-900 mb-1">No messages</p>
                            <p class="text-sm text-gray-400"><?= $search ? 'Try a different search.' : 'Messages from your contact form will appear here.' ?></p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($messages as $m):
                            $is_active    = $selected && $selected['id'] == $m['id'];
                            $time         = strtotime($m['created_at']);
                            $is_today     = date('Y-m-d', $time) === date('Y-m-d');
                            $date_display = $is_today ? date('g:i A', $time) : date('M j', $time);
                            $initials     = strtoupper(mb_substr($m['name'], 0, 1));
                        ?>
                        <a href="messages.php?view=<?= $m['id'] ?><?= $filter !== 'all' ? '&filter=' . $filter : '' ?>"
                           class="msg-item <?= $is_active ? 'active' : '' ?> <?= !$m['is_read'] ? 'unread' : '' ?>">
                            <div class="msg-avatar"><?= $initials ?></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm <?= !$m['is_read'] ? 'font-bold text-gray-900' : 'font-medium text-gray-700' ?> truncate"><?= htmlspecialchars($m['name']) ?></p>
                                <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($m['subject'] ?: 'No subject') ?></p>
                                <p class="text-xs text-gray-400 truncate mt-0.5"><?= htmlspecialchars(mb_substr($m['message'], 0, 50)) ?>…</p>
                            </div>
                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                <span class="text-xs text-gray-400"><?= $date_display ?></span>
                                <?php if (!$m['is_read']): ?><div class="msg-dot"></div><?php endif; ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- MESSAGE DETAIL -->
                <div class="lg:col-span-3">
                    <?php if ($selected): ?>
                    <div class="bg-white rounded-2xl shadow-sm p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4"><?= htmlspecialchars($selected['subject'] ?: 'No subject') ?></h2>
                        <div class="flex items-center gap-3 mb-5 pb-5 border-b border-gray-100">
                            <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold text-base">
                                <?= strtoupper(mb_substr($selected['name'], 0, 1)) ?>
                            </div>
                            <div class="flex-1">
                                <p class="font-semibold text-sm"><?= htmlspecialchars($selected['name']) ?></p>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars($selected['email']) ?></p>
                            </div>
                            <span class="text-xs text-gray-400 hidden sm:block"><?= date('M j, Y \a\t g:i A', strtotime($selected['created_at'])) ?></span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 mb-5">
                            <div class="bg-gray-50 rounded-xl p-3">
                                <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-0.5">Email</p>
                                <p class="text-sm font-medium break-all"><?= htmlspecialchars($selected['email']) ?></p>
                            </div>
                            <div class="bg-gray-50 rounded-xl p-3">
                                <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-0.5">Phone</p>
                                <p class="text-sm font-medium"><?= htmlspecialchars($selected['phone'] ?: '—') ?></p>
                            </div>
                        </div>
                        <div class="detail-body mb-5"><?= nl2br(htmlspecialchars($selected['message'])) ?></div>
                        <div class="flex flex-wrap gap-2">
                            <a href="mailto:<?= htmlspecialchars($selected['email']) ?>?subject=Re: <?= urlencode($selected['subject'] ?: 'Your message') ?>" class="action-btn primary">✉️ Reply</a>
                            <?php if ($selected['phone']): ?>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $selected['phone']) ?>" target="_blank" class="action-btn">💬 WhatsApp</a>
                            <?php endif; ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="id" value="<?= $selected['id'] ?>">
                                <?php if ($selected['is_read']): ?>
                                <button type="submit" name="action" value="mark_unread" class="action-btn">📩 Unread</button>
                                <?php else: ?>
                                <button type="submit" name="action" value="mark_read" class="action-btn">✅ Read</button>
                                <?php endif; ?>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this message?')">
                                <input type="hidden" name="id" value="<?= $selected['id'] ?>">
                                <button type="submit" name="action" value="delete" class="action-btn danger">🗑️ Delete</button>
                            </form>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="bg-white rounded-2xl shadow-sm flex items-center justify-center" style="min-height:400px;">
                        <div class="text-center">
                            <p class="text-5xl mb-3">💬</p>
                            <p class="font-bold text-gray-900 mb-1">Select a message</p>
                            <p class="text-sm text-gray-400">Click a message to read it.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
    document.body.style.overflow = document.getElementById('sidebar').classList.contains('open') ? 'hidden' : '';
}
</script>
</body>
</html>