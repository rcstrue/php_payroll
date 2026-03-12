<?php
/**
 * RCS HRMS Pro - Attendance Report
 */

$pageTitle = 'Attendance Report';

// Get filter parameters
$clientFilter = isset($_GET['client']) ? sanitize($_GET['client']) : '';
$unitFilter = isset($_GET['unit']) ? sanitize($_GET['unit']) : '';
$monthFilter = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$yearFilter = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Get clients for filter
$clients = $db->query("SELECT DISTINCT COALESCE(c.name, c.client_name, e.client_name) as client_name FROM employees e LEFT JOIN clients c ON e.client_id = c.id WHERE e.client_name IS NOT NULL AND e.client_name != '' ORDER BY client_name")->fetchAll(PDO::FETCH_ASSOC);

// Initialize units array
$units = [];

// Build query
$where = "MONTH(a.attendance_date) = ? AND YEAR(a.attendance_date) = ?";
$params = ['month' => $monthFilter, 'year' => $yearFilter];

if ($clientFilter) {
    $where .= " AND COALESCE(c.name, c.client_name, e.client_name) = :client";
    $params['client'] = $clientFilter;
}

if ($unitFilter) {
    $where .= " AND COALESCE(u.name, u.unit_name, e.unit_name) = :unit";
    $params['unit'] = $unitFilter;
}

// Get units for selected client
if ($clientFilter) {
    $stmt = $db->prepare("SELECT DISTINCT COALESCE(u.name, u.unit_name, e.unit_name) as unit_name FROM employees e LEFT JOIN units u ON e.unit_id = u.id WHERE COALESCE(e.client_name, (SELECT name FROM clients WHERE id = e.client_id)) = ? AND e.unit_name IS NOT NULL AND e.unit_name != '' ORDER BY unit_name");
    $stmt->execute([$clientFilter]);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get attendance summary
$sql = "SELECT 
    COALESCE(c.name, c.client_name, e.client_name) as client_name,
    COALESCE(u.name, u.unit_name, e.unit_name) as unit_name,
    COUNT(*) as total_records,
    SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
    SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
    SUM(CASE WHEN a.status = 'Weekly Off' THEN 1 ELSE 0 END) as weekly_off,
    SUM(a.overtime_hours) as total_ot
    FROM attendance a
    LEFT JOIN employees e ON a.employee_id = e.employee_code
    LEFT JOIN clients c ON e.client_id = c.id
    LEFT JOIN units u ON e.unit_id = u.id
    WHERE {$where}
    GROUP BY COALESCE(c.name, c.client_name, e.client_name), COALESCE(u.name, u.unit_name, e.unit_name)";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-bar-chart-line me-2"></i>Attendance Summary</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 mb-3">
                    <input type="hidden" name="page" value="attendance/report">
                    <div class="col-md-3">
                        <label class="form-label">Client</label>
                        <select class="form-select" name="client" id="clientFilter">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?php echo sanitize($c['client_name']); ?>" <?php echo $clientFilter == $c['client_name'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($c['client_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Unit</label>
                        <select class="form-select" name="unit" id="unitFilter">
                            <option value="">All Units</option>
                            <?php foreach ($units as $u): ?>
                            <option value="<?php echo sanitize($u['unit_name']); ?>" <?php echo $unitFilter == $u['unit_name'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($u['unit_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Month</label>
                        <select class="form-select" name="month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $monthFilter ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Year</label>
                        <select class="form-select" name="year">
                            <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $yearFilter ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i> Search
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportReport()">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                    </div>
                </form>
                
                <!-- Summary Cards -->
                <div class="row g-3 mb-4">
                    <?php if (!empty($summary)): ?>
                        <?php foreach ($summary as $data): ?>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center py-3">
                                    <h6 class="text-muted mb-1"><?php echo sanitize($data['client_name'] ?? 'Unknown'); ?> - <?php echo sanitize($data['unit_name'] ?? 'Unknown'); ?></h6>
                                    <div class="row">
                                        <div class="col-4">
                                            <div class="border rounded p-2 text-center">
                                                <small class="text-muted">Total</small>
                                                <h5 class="mb-0"><?php echo number_format($data['total_records'] ?? 0); ?></h5>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="border rounded p-2 text-center bg-success bg-opacity-10">
                                                <small class="text-success">Present</small>
                                                <h5 class="mb-0 text-success"><?php echo number_format($data['present'] ?? 0); ?></h5>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="border rounded p-2 text-center bg-danger bg-opacity-10">
                                                <small class="text-danger">Absent</small>
                                                <h5 class="mb-0 text-danger"><?php echo number_format($data['absent'] ?? 0); ?></h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">No attendance data found for the selected filters.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportReport() {
    var params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'index.php?' + params.toString();
}

$(document).ready(function() {
    $('#clientFilter').change(function() {
        var client = $(this).val();
        if (client) {
            $.get('index.php?page=api/units', { client_name: client }, function(data) {
                var options = '<option value="">All Units</option>';
                data.forEach(function(u) {
                    options += '<option value="' + u.unit_name + '">' + u.unit_name + '</option>';
                });
                $('#unitFilter').html(options);
            });
        } else {
            $('#unitFilter').html('<option value="">All Units</option>');
        }
    });
});
</script>
