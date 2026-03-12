<?php
/**
 * RCS HRMS Pro - Compliance Dashboard
 */

$pageTitle = 'Compliance Dashboard';

// Get compliance data
$dashboardData = $compliance->getDashboardData();
$notifications = $compliance->getNotifications(10);

// Get monthly summary
$currentMonth = date('n');
$currentYear = date('Y');
$monthlySummary = $compliance->getMonthlySummary($currentMonth, $currentYear);

// Get minimum wage alerts
$stmt = $db->query(
    "SELECT s.state_name, MAX(mw.effective_from) as last_update, mw.worker_category
     FROM minimum_wages mw
     JOIN states s ON mw.state_id = s.id
     WHERE mw.is_active = 1
     GROUP BY s.id
     HAVING last_update < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)"
);
$wageAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get compliance calendar
$stmt = $db->prepare(
    "SELECT * FROM compliance_calendar 
     WHERE due_date >= CURDATE() AND is_active = 1
     ORDER BY due_date LIMIT 10"
);
$stmt->execute();
$upcomingDeadlines = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <!-- Compliance Status Cards -->
    <div class="col-12">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon <?php echo $monthlySummary['total_pf_employee'] > 0 ? 'primary' : 'secondary'; ?>">
                    <i class="bi bi-piggy-bank"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">PF Liability (Current Month)</div>
                    <div class="stat-value"><?php echo formatCurrency($monthlySummary['pf_employee'] + $monthlySummary['pf_employer'] + $monthlySummary['eps_employer']); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon <?php echo $monthlySummary['total_esi_employee'] > 0 ? 'success' : 'secondary'; ?>">
                    <i class="bi bi-hospital"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">ESI Liability (Current Month)</div>
                    <div class="stat-value"><?php echo formatCurrency($monthlySummary['esi_employee'] + $monthlySummary['esi_employer']); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="bi bi-receipt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Professional Tax</div>
                    <div class="stat-value"><?php echo formatCurrency($monthlySummary['professional_tax']); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon <?php echo count($wageAlerts) > 0 ? 'danger' : 'success'; ?>">
                    <i class="bi bi-currency-rupee"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Wage Updates Needed</div>
                    <div class="stat-value"><?php echo count($wageAlerts); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Upcoming Deadlines -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-calendar-check me-2"></i>Upcoming Deadlines</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($upcomingDeadlines)): ?>
                    <div class="list-group-item text-center py-4 text-muted">No upcoming deadlines</div>
                    <?php else: ?>
                    <?php foreach ($upcomingDeadlines as $d): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><?php echo sanitize($d['compliance_name']); ?></h6>
                                <small class="text-muted"><?php echo sanitize($d['form_number'] ?? ''); ?></small>
                            </div>
                            <div class="text-end">
                                <div class="badge bg-<?php 
                                    $daysUntil = (strtotime($d['due_date']) - time()) / 86400;
                                    echo $daysUntil <= 3 ? 'danger' : ($daysUntil <= 7 ? 'warning' : 'success');
                                ?>">
                                    <?php echo formatDate($d['due_date']); ?>
                                </div>
                                <div class="small text-muted"><?php echo $d['frequency']; ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notifications -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-bell me-2"></i>Notifications</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($notifications)): ?>
                    <div class="list-group-item text-center py-4 text-muted">No notifications</div>
                    <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-1"><?php echo sanitize($n['title']); ?></h6>
                                <p class="mb-1 small text-muted"><?php echo sanitize($n['description']); ?></p>
                            </div>
                            <small class="text-muted"><?php echo formatDate($n['created_at']); ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="index.php?page=compliance/pf" class="btn btn-outline-primary w-100 py-3">
                            <i class="bi bi-piggy-bank d-block fs-4 mb-1"></i>
                            PF Returns (ECR)
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="index.php?page=compliance/esi" class="btn btn-outline-success w-100 py-3">
                            <i class="bi bi-hospital d-block fs-4 mb-1"></i>
                            ESI Returns
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="index.php?page=compliance/pt" class="btn btn-outline-warning w-100 py-3">
                            <i class="bi bi-receipt d-block fs-4 mb-1"></i>
                            PT Returns
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="index.php?page=compliance/minimum-wages" class="btn btn-outline-info w-100 py-3">
                            <i class="bi bi-currency-rupee d-block fs-4 mb-1"></i>
                            Minimum Wages
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statutory Forms -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-file-earmark-text me-2"></i>Statutory Forms</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="index.php?page=forms/form-v" class="btn btn-outline-secondary w-100 py-2">
                            <i class="bi bi-file-text me-1"></i>Form V (Register of Workmen)
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="index.php?page=forms/form-xvi" class="btn btn-outline-secondary w-100 py-2">
                            <i class="bi bi-file-text me-1"></i>Form XVI (Muster Roll)
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="index.php?page=forms/form-xvii" class="btn btn-outline-secondary w-100 py-2">
                            <i class="bi bi-file-text me-1"></i>Form XVII (Wage Register)
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="index.php?page=forms/form-f2" class="btn btn-outline-secondary w-100 py-2">
                            <i class="bi bi-file-text me-1"></i>Form F2 (Return of Employees)
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="index.php?page=forms/appointment" class="btn btn-outline-secondary w-100 py-2">
                            <i class="bi bi-file-text me-1"></i>Appointment Letter
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="index.php?page=forms/nomination&type=pf" class="btn btn-outline-secondary w-100 py-2">
                            <i class="bi bi-file-text me-1"></i>PF Nomination (Form 2)
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="index.php?page=forms/nomination&type=gratuity" class="btn btn-outline-secondary w-100 py-2">
                            <i class="bi bi-file-text me-1"></i>Gratuity Nomination
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="index.php?page=report/custom" class="btn btn-outline-secondary w-100 py-2">
                            <i class="bi bi-file-text me-1"></i>Custom Report Builder
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
