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

// Create notifications table if not exists
try {
    $db->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        type VARCHAR(50) DEFAULT 'info',
        link VARCHAR(500),
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_read (user_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Ignore
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
$typeFilter = $_GET['type'] ?? '';

// Build query
$where = "WHERE user_id = :user_id";
$params = ['user_id' => $userId];

if ($filter === 'unread') {
    $where .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $where .= " AND is_read = 1";
}

if (!empty($typeFilter)) {
    $where .= " AND type = :type";
    $params['type'] = $typeFilter;
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

// Get system alerts to show as notifications
$systemAlerts = [];

// Note: Pending Employee Approvals are now shown in the header notification popup only
// to avoid duplication. They can be accessed via: index.php?page=employee/list&status=pending

// 2. PF/ESI Compliance Deadlines
try {
    $pfStmt = $db->query("SELECT * FROM compliance_filings 
         WHERE filing_type = 'PF' AND status = 'pending' 
         AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
         ORDER BY due_date ASC LIMIT 1");
    $pfDeadline = $pfStmt ? $pfStmt->fetch(PDO::FETCH_ASSOC) : false;
    if ($pfDeadline) {
        $daysLeft = (strtotime($pfDeadline['due_date']) - time()) / 86400;
        $systemAlerts[] = [
            'id' => 'pf_' . $pfDeadline['id'],
            'title' => 'PF Filing Deadline',
            'message' => sprintf('PF return due on %s (%d days left)', $pfDeadline['due_date'], ceil($daysLeft)),
            'type' => $daysLeft < 3 ? 'danger' : 'warning',
            'link' => 'index.php?page=compliance/pf',
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'icon' => 'shield-check',
            'category' => 'compliance'
        ];
    }
    
    $esiStmt = $db->query("SELECT * FROM compliance_filings 
         WHERE filing_type = 'ESI' AND status = 'pending' 
         AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
         ORDER BY due_date ASC LIMIT 1");
    $esiDeadline = $esiStmt ? $esiStmt->fetch(PDO::FETCH_ASSOC) : false;
    if ($esiDeadline) {
        $daysLeft = (strtotime($esiDeadline['due_date']) - time()) / 86400;
        $systemAlerts[] = [
            'id' => 'esi_' . $esiDeadline['id'],
            'title' => 'ESI Filing Deadline',
            'message' => sprintf('ESI return due on %s (%d days left)', $esiDeadline['due_date'], ceil($daysLeft)),
            'type' => $daysLeft < 3 ? 'danger' : 'warning',
            'link' => 'index.php?page=compliance/esi',
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'icon' => 'hospital',
            'category' => 'compliance'
        ];
    }
} catch (Exception $e) {
    // Ignore
}

// 3. Payroll Processing Reminder
try {
    $currentMonth = date('n');
    $currentYear = date('Y');
    $prevMonth = $currentMonth - 1;
    $prevYear = $currentYear;
    if ($prevMonth == 0) {
        $prevMonth = 12;
        $prevYear--;
    }
    
    $payrollStmt = $db->prepare("SELECT COUNT(*) as count FROM payroll WHERE MONTH(created_at) = :month AND YEAR(created_at) = :year");
    $payrollStmt->execute(['month' => $currentMonth, 'year' => $currentYear]);
    $payrollProcessed = $payrollStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($payrollProcessed == 0 && date('j') > 5) {
        $systemAlerts[] = [
            'id' => 'payroll_reminder',
            'title' => 'Payroll Processing Pending',
            'message' => sprintf('Payroll for %s %s has not been processed yet', date('F', mktime(0,0,0,$prevMonth,1)), $prevYear),
            'type' => 'info',
            'link' => 'index.php?page=payroll/process',
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'icon' => 'cash-stack',
            'category' => 'payroll'
        ];
    }
} catch (Exception $e) {
    // Ignore
}

// 4. Contract Expiring Soon
try {
    $contractStmt = $db->query("SELECT id, contract_name, end_date 
         FROM contracts 
         WHERE status = 'active' 
         AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
         ORDER BY end_date ASC");
    $expiringContracts = $contractStmt ? $contractStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    foreach ($expiringContracts as $contract) {
        $daysLeft = ceil((strtotime($contract['end_date']) - time()) / 86400);
        $systemAlerts[] = [
            'id' => 'contract_' . $contract['id'],
            'title' => 'Contract Expiring Soon',
            'message' => sprintf('%s expires on %s (%d days left)', $contract['contract_name'], $contract['end_date'], $daysLeft),
            'type' => 'warning',
            'link' => 'index.php?page=contract/list',
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'icon' => 'file-earmark-text',
            'category' => 'contracts'
        ];
    }
} catch (Exception $e) {
    // Ignore
}

// 5. Attendance Not Uploaded
try {
    $prevMonth = date('n') - 1;
    $prevYear = date('Y');
    if ($prevMonth == 0) {
        $prevMonth = 12;
        $prevYear--;
    }
    
    $attendanceStmt = $db->prepare("SELECT COUNT(*) as count FROM attendance_monthly WHERE month = :month AND year = :year");
    $attendanceStmt->execute(['month' => $prevMonth, 'year' => $prevYear]);
    $attendanceUploaded = $attendanceStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($attendanceUploaded == 0 && date('j') > 5) {
        $systemAlerts[] = [
            'id' => 'attendance_reminder',
            'title' => 'Attendance Not Uploaded',
            'message' => sprintf('Monthly attendance for %s %s has not been uploaded', date('F', mktime(0,0,0,$prevMonth,1)), $prevYear),
            'type' => 'info',
            'link' => 'index.php?page=attendance/upload',
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'icon' => 'calendar-check',
            'category' => 'attendance'
        ];
    }
} catch (Exception $e) {
    // Ignore
}

// Merge system alerts with notifications
$allNotifications = array_merge($notifications, $systemAlerts);

// Sort by created_at descending
usort($allNotifications, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Apply pagination to merged results
$totalAll = count($allNotifications);
$totalPagesAll = ceil($totalAll / $perPage);
$allNotifications = array_slice($allNotifications, $offset, $perPage);

// Update counts
$unreadCount += count(array_filter($systemAlerts, fn($a) => empty($a['is_read'])));
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-bell me-2"></i>Notifications
                    <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-danger ms-2"><?php echo $unreadCount; ?> unread</span>
                    <?php endif; ?>
                </h5>
                <div class="d-flex gap-2">
                    <?php if ($unreadCount > 0): ?>
                    <a href="?page=notifications&mark_all_read=1" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-check-all me-1"></i>Mark All as Read
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Filter Tabs -->
            <div class="card-body border-bottom py-2">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <ul class="nav nav-tabs card-header-tabs">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" 
                                   href="?page=notifications&filter=all">
                                    All <span class="badge bg-secondary ms-1"><?php echo $totalAll; ?></span>
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
                    <div class="col-md-4">
                        <select class="form-select form-select-sm" onchange="location.href='?page=notifications&type='+this.value">
                            <option value="">All Types</option>
                            <option value="employee" <?php echo $typeFilter === 'employee' ? 'selected' : ''; ?>>Employee</option>
                            <option value="compliance" <?php echo $typeFilter === 'compliance' ? 'selected' : ''; ?>>Compliance</option>
                            <option value="payroll" <?php echo $typeFilter === 'payroll' ? 'selected' : ''; ?>>Payroll</option>
                            <option value="attendance" <?php echo $typeFilter === 'attendance' ? 'selected' : ''; ?>>Attendance</option>
                            <option value="warning" <?php echo $typeFilter === 'warning' ? 'selected' : ''; ?>>Warnings</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Notifications List -->
            <div class="card-body p-0">
                <?php if (empty($allNotifications)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-bell-slash fs-1 text-muted"></i>
                    <h5 class="mt-3 text-muted">No notifications</h5>
                    <p class="text-muted">You're all caught up!</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($allNotifications as $notif): ?>
                    <div class="list-group-item list-group-item-action <?php echo empty($notif['is_read']) ? 'bg-light' : ''; ?>">
                        <div class="d-flex w-100 justify-content-between align-items-start">
                            <div class="me-3">
                                <?php
                                $icon = $notif['icon'] ?? 'bell';
                                $iconColor = 'text-primary';
                                $type = $notif['type'] ?? 'info';
                                
                                switch ($type) {
                                    case 'danger':
                                    case 'error':
                                        $iconColor = 'text-danger';
                                        break;
                                    case 'warning':
                                        $iconColor = 'text-warning';
                                        break;
                                    case 'success':
                                        $iconColor = 'text-success';
                                        break;
                                    case 'info':
                                        $iconColor = 'text-info';
                                        break;
                                }
                                
                                // Override icon based on category
                                if (!empty($notif['category'])) {
                                    switch ($notif['category']) {
                                        case 'employee': $icon = 'person-plus'; break;
                                        case 'compliance': $icon = 'shield-check'; break;
                                        case 'payroll': $icon = 'cash-stack'; break;
                                        case 'attendance': $icon = 'calendar-check'; break;
                                        case 'contracts': $icon = 'file-earmark-text'; break;
                                    }
                                }
                                ?>
                                <i class="bi bi-<?php echo $icon; ?> <?php echo $iconColor; ?> fs-4"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <h6 class="mb-1 <?php echo empty($notif['is_read']) ? 'fw-bold' : ''; ?>">
                                        <?php echo sanitize($notif['title'] ?? 'Notification'); ?>
                                        <?php if (!empty($notif['category'])): ?>
                                        <span class="badge bg-secondary ms-1"><?php echo ucfirst($notif['category']); ?></span>
                                        <?php endif; ?>
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
                                            echo floor($diff / 3600) . 'h ago';
                                        } elseif ($diff < 604800) {
                                            echo floor($diff / 86400) . 'd ago';
                                        } else {
                                            echo date('d M', $created);
                                        }
                                        ?>
                                    </small>
                                </div>
                                <p class="mb-2 text-muted"><?php echo sanitize($notif['message'] ?? ''); ?></p>
                                <div class="d-flex gap-2">
                                    <?php if (!empty($notif['link'])): ?>
                                    <a href="<?php echo sanitize($notif['link']); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i>View
                                    </a>
                                    <?php endif; ?>
                                    <?php if (empty($notif['is_read']) && !empty($notif['id']) && strpos($notif['id'], '_') === false): ?>
                                    <a href="?page=notifications&mark_read=<?php echo $notif['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-check me-1"></i>Mark Read
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
            <?php if ($totalPagesAll > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=notifications&filter=<?php echo $filter; ?>&pg=<?php echo $page - 1; ?>">Previous</a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPagesAll, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=notifications&filter=<?php echo $filter; ?>&pg=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPagesAll): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=notifications&filter=<?php echo $filter; ?>&pg=<?php echo $page + 1; ?>">Next</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-3">
                        <a href="index.php?page=employee/list&status=pending" class="btn btn-outline-warning w-100">
                            <i class="bi bi-person-plus me-1"></i>Pending Approvals
                            <?php 
                            try {
                                $pendingStmt = $db->query("SELECT COUNT(*) as count FROM employees WHERE status LIKE 'pending%'");
                                $pending = $pendingStmt ? $pendingStmt->fetch(PDO::FETCH_ASSOC)['count'] : 0;
                                if ($pending > 0) echo "<span class=\"badge bg-warning text-dark ms-1\">$pending</span>";
                            } catch(Exception $e) {}
                            ?>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="index.php?page=payroll/process" class="btn btn-outline-primary w-100">
                            <i class="bi bi-cash-stack me-1"></i>Process Payroll
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="index.php?page=attendance/upload" class="btn btn-outline-info w-100">
                            <i class="bi bi-calendar-check me-1"></i>Upload Attendance
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="index.php?page=compliance/dashboard" class="btn btn-outline-success w-100">
                            <i class="bi bi-shield-check me-1"></i>Compliance
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
