<?php
/**
 * RCS HRMS Pro - Attendance View
 */

$pageTitle = 'Attendance';

// Get filters
$clientFilter = isset($_GET['client']) ? sanitize($_GET['client']) : '';
$unitFilter = isset($_GET['unit']) ? sanitize($_GET['unit']) : '';
$monthFilter = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$yearFilter = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Get clients for dropdown
$clients = $client->getList();

// Get units based on selected client
$units = [];
if ($clientFilter) {
    // Find client by name
    $clientData = $db->prepare("SELECT id FROM clients WHERE name = ?");
    $clientData->execute([$clientFilter]);
    $clientRow = $clientData->fetch(PDO::FETCH_ASSOC);
    if ($clientRow) {
        $units = $unit->getByClient($clientRow['id']);
    }
}

// Build attendance query
$where = ["MONTH(a.attendance_date) = :month", "YEAR(a.attendance_date) = :year"];
$params = [':month' => $monthFilter, ':year' => $yearFilter];

if (!empty($clientFilter)) {
    $where[] = "e.client_name = :client";
    $params[':client'] = $clientFilter;
}

if (!empty($unitFilter)) {
    $where[] = "e.unit_name = :unit";
    $params[':unit'] = $unitFilter;
}

if (!empty($statusFilter)) {
    $where[] = "a.status = :status";
    $params[':status'] = $statusFilter;
}

$whereClause = implode(' AND ', $where);

// Get attendance records with pagination
$page = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Count total
$countSql = "SELECT COUNT(*) as total FROM attendance a 
             LEFT JOIN employees e ON a.employee_id = e.employee_code 
             WHERE $whereClause";
$countStmt = $db->prepare($countSql);
$countStmt->execute(array_filter($params, fn($k) => $k !== ':limit' && $k !== ':offset', ARRAY_FILTER_USE_KEY));
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get attendance data
$sql = "SELECT a.*, 
               e.full_name, e.employee_code, e.client_name, e.unit_name, e.designation
        FROM attendance a 
        LEFT JOIN employees e ON a.employee_id = e.employee_code 
        WHERE $whereClause 
        ORDER BY e.unit_name, e.full_name 
        LIMIT $perPage OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute(array_filter($params, fn($k) => $k !== ':limit' && $k !== ':offset', ARRAY_FILTER_USE_KEY));
$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary
$summarySql = "SELECT 
               COUNT(*) as total_records,
               SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
               SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
               SUM(CASE WHEN a.status = 'Weekly Off' THEN 1 ELSE 0 END) as weekly_off,
               SUM(CASE WHEN a.status = 'Holiday' THEN 1 ELSE 0 END) as holiday,
               SUM(CASE WHEN a.status LIKE '%Leave%' THEN 1 ELSE 0 END) as leaves,
               SUM(a.overtime_hours) as total_overtime
               FROM attendance a 
               LEFT JOIN employees e ON a.employee_id = e.employee_code 
               WHERE $whereClause";

$summaryStmt = $db->prepare($summarySql);
$summaryStmt->execute(array_filter($params, fn($k) => $k !== ':limit' && $k !== ':offset', ARRAY_FILTER_USE_KEY));
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

// Month names for dropdown
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Status options
$statuses = ['Present', 'Absent', 'Weekly Off', 'Holiday', 'Paid Leave', 'Unpaid Leave', 'Sick Leave', 'Casual Leave', 'Half Day'];
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-calendar-check me-2"></i>Attendance Records</h5>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <form method="GET" class="row g-2 mb-4">
                    <input type="hidden" name="page" value="attendance/view">
                    
                    <div class="col-md-2">
                        <label class="form-label small">Month</label>
                        <select class="form-select form-select-sm" name="month">
                            <?php foreach ($months as $num => $name): ?>
                            <option value="<?php echo $num; ?>" <?php echo $monthFilter == $num ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label small">Year</label>
                        <select class="form-select form-select-sm" name="year">
                            <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $yearFilter == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label small">Client</label>
                        <select class="form-select form-select-sm" name="client" id="clientFilter">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?php echo sanitize($c['name']); ?>" <?php echo $clientFilter == $c['name'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($c['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label small">Unit</label>
                        <select class="form-select form-select-sm" name="unit" id="unitFilter">
                            <option value="">All Units</option>
                            <?php foreach ($units as $u): ?>
                            <option value="<?php echo sanitize($u['name']); ?>" <?php echo $unitFilter == $u['name'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($u['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label small">Status</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="">All Status</option>
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $statusFilter == $s ? 'selected' : ''; ?>>
                                <?php echo $s; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-12 mt-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-search me-1"></i>Filter
                        </button>
                        <a href="index.php?page=attendance/view" class="btn btn-secondary btn-sm">Clear</a>
                        <button type="button" class="btn btn-success btn-sm" onclick="exportAttendance()">
                            <i class="bi bi-download me-1"></i>Export
                        </button>
                    </div>
                </form>
                
                <!-- Summary Cards -->
                <div class="row g-2 mb-3">
                    <div class="col-md-2">
                        <div class="card bg-light">
                            <div class="card-body py-2 text-center">
                                <small class="text-muted">Total Records</small>
                                <h5 class="mb-0"><?php echo number_format($summary['total_records'] ?? 0); ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success bg-opacity-10">
                            <div class="card-body py-2 text-center">
                                <small class="text-success">Present</small>
                                <h5 class="mb-0 text-success"><?php echo number_format($summary['present'] ?? 0); ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-danger bg-opacity-10">
                            <div class="card-body py-2 text-center">
                                <small class="text-danger">Absent</small>
                                <h5 class="mb-0 text-danger"><?php echo number_format($summary['absent'] ?? 0); ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info bg-opacity-10">
                            <div class="card-body py-2 text-center">
                                <small class="text-info">Weekly Off</small>
                                <h5 class="mb-0 text-info"><?php echo number_format($summary['weekly_off'] ?? 0); ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-warning bg-opacity-10">
                            <div class="card-body py-2 text-center">
                                <small class="text-warning">Leaves</small>
                                <h5 class="mb-0 text-warning"><?php echo number_format($summary['leaves'] ?? 0); ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-secondary bg-opacity-10">
                            <div class="card-body py-2 text-center">
                                <small class="text-secondary">OT Hours</small>
                                <h5 class="mb-0"><?php echo number_format($summary['total_overtime'] ?? 0, 1); ?>h</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0 pt-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Emp Code</th>
                                <th>Employee Name</th>
                                <th>Client</th>
                                <th>Unit</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>In Time</th>
                                <th>Out Time</th>
                                <th>Hours</th>
                                <th>OT</th>
                                <th>Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($attendance)): ?>
                                <?php foreach ($attendance as $a): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?php echo sanitize($a['employee_code'] ?? $a['employee_id']); ?></span></td>
                                    <td><?php echo sanitize($a['full_name'] ?? '-'); ?></td>
                                    <td><?php echo sanitize($a['client_name'] ?? '-'); ?></td>
                                    <td><?php echo sanitize($a['unit_name'] ?? '-'); ?></td>
                                    <td><?php echo formatDate($a['attendance_date']); ?></td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'Present' => 'success',
                                            'Absent' => 'danger',
                                            'Weekly Off' => 'info',
                                            'Holiday' => 'primary',
                                            'Half Day' => 'warning'
                                        ];
                                        $color = $statusColors[$a['status']] ?? (strpos($a['status'], 'Leave') !== false ? 'warning' : 'secondary');
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo sanitize($a['status']); ?></span>
                                    </td>
                                    <td><?php echo $a['in_time'] ? date('H:i', strtotime($a['in_time'])) : '-'; ?></td>
                                    <td><?php echo $a['out_time'] ? date('H:i', strtotime($a['out_time'])) : '-'; ?></td>
                                    <td><?php echo $a['working_hours'] ? number_format($a['working_hours'], 1) : '-'; ?></td>
                                    <td><?php echo $a['overtime_hours'] > 0 ? number_format($a['overtime_hours'], 1) . 'h' : '-'; ?></td>
                                    <td><small class="text-muted"><?php echo sanitize($a['source'] ?? 'Manual'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">No attendance records found for the selected filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total > $perPage): ?>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $perPage, $total); ?> of <?php echo number_format($total); ?> records</small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=attendance/view&month=<?php echo $monthFilter; ?>&year=<?php echo $yearFilter; ?>&client=<?php echo urlencode($clientFilter); ?>&unit=<?php echo urlencode($unitFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&pg=<?php echo $page - 1; ?>">Previous</a>
                                </li>
                                <?php endif; ?>
                                
                                <?php 
                                $totalPages = ceil($total / $perPage);
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                for ($i = $startPage; $i <= $endPage; $i++): 
                                ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=attendance/view&month=<?php echo $monthFilter; ?>&year=<?php echo $yearFilter; ?>&client=<?php echo urlencode($clientFilter); ?>&unit=<?php echo urlencode($unitFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&pg=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=attendance/view&month=<?php echo $monthFilter; ?>&year=<?php echo $yearFilter; ?>&client=<?php echo urlencode($clientFilter); ?>&unit=<?php echo urlencode($unitFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&pg=<?php echo $page + 1; ?>">Next</a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Load units when client changes
$('#clientFilter').change(function() {
    var clientName = $(this).val();
    if (clientName) {
        $.get('index.php?page=api/units', {client: clientName}, function(data) {
            var units = JSON.parse(data);
            $('#unitFilter').html('<option value="">All Units</option>');
            units.forEach(function(u) {
                $('#unitFilter').append('<option value="' + u.name + '">' + u.name + '</option>');
            });
        });
    } else {
        $('#unitFilter').html('<option value="">All Units</option>');
    }
});

function exportAttendance() {
    var params = $('#clientFilter').closest('form').serialize();
    window.location.href = 'index.php?page=attendance/export&' + params;
}
</script>
