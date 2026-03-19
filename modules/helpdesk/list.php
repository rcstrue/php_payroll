<?php
$pageTitle = 'Helpdesk';

$db->exec("CREATE TABLE IF NOT EXISTS helpdesk_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(20) NOT NULL,
    employee_id VARCHAR(36),
    subject VARCHAR(255) NOT NULL,
    description TEXT,
    category ENUM('hr','payroll','it','admin','other') DEFAULT 'hr',
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$statusFilter = $_GET['status'] ?? '';
$tickets = $db->query("SELECT t.*, e.full_name, e.employee_code FROM helpdesk_tickets t LEFT JOIN employees e ON t.employee_id = e.id ORDER BY 
    CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END, t.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'open' => $db->query("SELECT COUNT(*) FROM helpdesk_tickets WHERE status='open'")->fetchColumn() ?: 0,
    'progress' => $db->query("SELECT COUNT(*) FROM helpdesk_tickets WHERE status='in_progress'")->fetchColumn() ?: 0,
    'resolved' => $db->query("SELECT COUNT(*) FROM helpdesk_tickets WHERE status='resolved'")->fetchColumn() ?: 0,
];

$statusColors = ['open'=>'danger','in_progress'=>'warning','resolved'=>'success','closed'=>'secondary'];
$priorityColors = ['low'=>'secondary','medium'=>'info','high'=>'warning','urgent'=>'danger'];
$priorities = ['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'];
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0"><i class="bi bi-headset me-2"></i>Helpdesk Tickets</h4>
            <a href="index.php?page=helpdesk/add" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Ticket</a>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body text-center"><h6>Open</h6><h3><?php echo $stats['open']; ?></h3></div></div></div>
            <div class="col-md-3"><div class="card bg-warning text-dark"><div class="card-body text-center"><h6>In Progress</h6><h3><?php echo $stats['progress']; ?></h3></div></div></div>
            <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center"><h6>Resolved</h6><h3><?php echo $stats['resolved']; ?></h3></div></div></div>
            <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body text-center"><h6>Total</h6><h3><?php echo array_sum($stats); ?></h3></div></div></div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Ticket</th><th>Subject</th><th>Employee</th><th>Priority</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if (empty($tickets)): ?>
                            <tr><td colspan="7" class="text-center py-4">No tickets found</td></tr>
                            <?php else: ?>
                            <?php foreach ($tickets as $t): ?>
                            <tr>
                                <td><a href="index.php?page=helpdesk/add&id=<?php echo $t['id']; ?>"><strong><?php echo sanitize($t['ticket_number']); ?></strong></a></td>
                                <td><?php echo sanitize($t['subject']); ?></td>
                                <td><?php echo $t['full_name'] ? sanitize($t['full_name']) . ' (' . $t['employee_code'] . ')' : '-'; ?></td>
                                <td><span class="badge bg-<?php echo $priorityColors[$t['priority']] ?? 'secondary'; ?>"><?php echo ucfirst($t['priority']); ?></span></td>
                                <td><span class="badge bg-<?php echo $statusColors[$t['status']] ?? 'secondary'; ?>"><?php echo str_replace('_', ' ', ucfirst($t['status'])); ?></span></td>
                                <td><small><?php echo formatDateTime($t['created_at']); ?></small></td>
                                <td><a href="index.php?page=helpdesk/add&id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
