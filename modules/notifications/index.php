<?php
/**
 * RCS HRMS Pro - Notifications Page
 * Displays all notifications for the logged-in user
 */

$pageTitle = 'Notifications';

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    setFlash('error', 'Please login to access this page');
    redirect('index.php?page=auth/login');
}

// Handle mark as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['mark_read'], $userId]);
    redirect('index.php?page=notifications');
}

// Handle mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
    setFlash('success', 'All notifications marked as read');
    redirect('index.php?page=notifications');
}

// Get filter
$filter = $_GET['filter'] ?? 'all';

// Build query
$where = "WHERE user_id = :user_id";
$params = ['user_id' => $userId];

if ($filter === 'unread') {
    $where .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $where .= " AND is_read = 1";
}

// Get notifications with pagination
$page = isset($_GET['pg']) ? (int)$_GET['pg'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Count total
$stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications $where");
$stmt->execute($params);
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($total / $perPage);

// Get notifications
$stmt = $db->prepare("SELECT * FROM notifications $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts for tabs
$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 1");
$stmt->execute([$userId]);
$readCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// If notifications table doesn't exist, show sample notifications
if (empty($notifications) && !tableExists($db, 'notifications')) {
    // Create sample notifications from compliance alerts
    $complianceAlerts = $compliance->checkDeadlineAlerts();
    foreach ($complianceAlerts as $alert) {
        $notifications[] = [
            'id' => 0,
            'title' => $alert['title'],
            'message' => $alert['message'],
            'type' => $alert['type'],
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'link' => 'index.php?page=compliance/dashboard'
        ];
    }
    $unreadCount = count($complianceAlerts);
}

// Helper function to check if table exists
function tableExists($db, $tableName) {
    try {
        $stmt = $db->query("SELECT 1 FROM $tableName LIMIT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-bell me-2"></i>Notifications
                    <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-danger ms-2"><?php echo $unreadCount; ?> unread</span>
                    <?php endif; ?>
                </h5>
                <div class="card-actions">
                    <?php if ($unreadCount > 0): ?>
                    <a href="?page=notifications&mark_all_read=1" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-check-all me-1"></i>Mark All as Read
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Filter Tabs -->
            <div class="card-body border-bottom py-2">
                <ul class="nav nav-tabs card-header-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" 
                           href="?page=notifications&filter=all">
                            All <span class="badge bg-secondary ms-1"><?php echo $unreadCount + $readCount; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'unread' ? 'active' : ''; ?>" 
                           href="?page=notifications&filter=unread">
                            Unread <span class="badge bg-danger ms-1"><?php echo $unreadCount; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'read' ? 'active' : ''; ?>" 
                           href="?page=notifications&filter=read">
                            Read <span class="badge bg-success ms-1"><?php echo $readCount; ?></span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Notifications List -->
            <div class="card-body p-0">
                <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-bell-slash fs-1 text-muted"></i>
                    <h5 class="mt-3 text-muted">No notifications</h5>
                    <p class="text-muted">You're all caught up!</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notif): ?>
                    <div class="list-group-item list-group-item-action <?php echo empty($notif['is_read']) ? 'list-group-item-light' : ''; ?>">
                        <div class="d-flex w-100 justify-content-between align-items-start">
                            <div class="me-3">
                                <?php
                                $icon = 'bi-bell';
                                $iconColor = 'text-primary';
                                if (!empty($notif['type'])) {
                                    switch ($notif['type']) {
                                        case 'danger':
                                        case 'error':
                                            $icon = 'bi-exclamation-circle';
                                            $iconColor = 'text-danger';
                                            break;
                                        case 'warning':
                                            $icon = 'bi-exclamation-triangle';
                                            $iconColor = 'text-warning';
                                            break;
                                        case 'success':
                                            $icon = 'bi-check-circle';
                                            $iconColor = 'text-success';
                                            break;
                                        case 'info':
                                            $icon = 'bi-info-circle';
                                            $iconColor = 'text-info';
                                            break;
                                    }
                                }
                                ?>
                                <i class="bi <?php echo $icon; ?> <?php echo $iconColor; ?> fs-4"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <h6 class="mb-1 <?php echo empty($notif['is_read']) ? 'fw-bold' : ''; ?>">
                                        <?php echo sanitize($notif['title'] ?? 'Notification'); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php 
                                        $created = strtotime($notif['created_at']);
                                        $now = time();
                                        $diff = $now - $created;
                                        
                                        if ($diff < 60) {
                                            echo 'Just now';
                                        } elseif ($diff < 3600) {
                                            echo floor($diff / 60) . ' min ago';
                                        } elseif ($diff < 86400) {
                                            echo floor($diff / 3600) . ' hours ago';
                                        } elseif ($diff < 604800) {
                                            echo floor($diff / 86400) . ' days ago';
                                        } else {
                                            echo date('d M Y', $created);
                                        }
                                        ?>
                                    </small>
                                </div>
                                <p class="mb-1 text-muted"><?php echo sanitize($notif['message'] ?? $notif['description'] ?? ''); ?></p>
                                <div class="d-flex gap-2">
                                    <?php if (!empty($notif['link'])): ?>
                                    <a href="<?php echo sanitize($notif['link']); ?>" class="btn btn-sm btn-outline-primary">
                                        View Details
                                    </a>
                                    <?php endif; ?>
                                    <?php if (empty($notif['is_read']) && !empty($notif['id'])): ?>
                                    <a href="?page=notifications&mark_read=<?php echo $notif['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary">
                                        Mark as Read
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=notifications&filter=<?php echo sanitize($filter); ?>&pg=<?php echo $page - 1; ?>">Previous</a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=notifications&filter=<?php echo sanitize($filter); ?>&pg=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=notifications&filter=<?php echo sanitize($filter); ?>&pg=<?php echo $page + 1; ?>">Next</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
