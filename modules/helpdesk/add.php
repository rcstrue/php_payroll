<?php
$pageTitle = 'New Ticket';
$ticketData = null;
$isEdit = false;

$db->exec("CREATE TABLE IF NOT EXISTS helpdesk_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT,
    comment TEXT NOT NULL,
    is_internal TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

if (isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT t.*, e.full_name, e.employee_code FROM helpdesk_tickets t LEFT JOIN employees e ON t.employee_id = e.id WHERE t.id = ?");
    $stmt->execute([$_GET['id']]);
    $ticketData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($ticketData) { $pageTitle = 'Ticket #' . $ticketData['ticket_number']; $isEdit = true; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create_ticket') {
            $lastTicket = $db->query("SELECT MAX(id) FROM helpdesk_tickets")->fetchColumn();
            $ticketNumber = 'TKT-' . date('Y') . '-' . str_pad(($lastTicket ?? 0) + 1, 5, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("INSERT INTO helpdesk_tickets (ticket_number, employee_id, subject, description, category, priority, status, created_at) VALUES (?,?,?,?,?,'open',NOW())");
            $stmt->execute([$ticketNumber, !empty($_POST['employee_id']) ? $_POST['employee_id'] : null, sanitize($_POST['subject']), sanitize($_POST['description']), sanitize($_POST['category'] ?? 'hr'), sanitize($_POST['priority'] ?? 'medium')]);
            setFlash('success', 'Ticket created: ' . $ticketNumber);
            redirect('index.php?page=helpdesk/list');
        }
        
        if ($_POST['action'] === 'add_comment' && $isEdit && !empty($_POST['comment'])) {
            $stmt = $db->prepare("INSERT INTO helpdesk_comments (ticket_id, user_id, comment, is_internal) VALUES (?,?,?,?)");
            $stmt->execute([$ticketData['id'], $_SESSION['user_id'] ?? null, sanitize($_POST['comment']), isset($_POST['is_internal']) ? 1 : 0]);
            setFlash('success', 'Comment added!');
            redirect('index.php?page=helpdesk/add&id=' . $ticketData['id']);
        }
        
        if ($_POST['action'] === 'update_status' && $isEdit) {
            $stmt = $db->prepare("UPDATE helpdesk_tickets SET status = ? WHERE id = ?");
            $stmt->execute([sanitize($_POST['status']), $ticketData['id']]);
            setFlash('success', 'Status updated!');
            redirect('index.php?page=helpdesk/add&id=' . $ticketData['id']);
        }
    }
}

$categories = ['hr'=>'HR','payroll'=>'Payroll','it'=>'IT Support','admin'=>'Admin','other'=>'Other'];
$priorities = ['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'];
$statuses = ['open'=>'Open','in_progress'=>'In Progress','resolved'=>'Resolved','closed'=>'Closed'];
$statusColors = ['open'=>'danger','in_progress'=>'warning','resolved'=>'success','closed'=>'secondary'];

if ($isEdit) {
    $comments = $db->prepare("SELECT c.*, u.username, u.first_name, u.last_name FROM helpdesk_comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.ticket_id = ? ORDER BY c.created_at ASC");
    $comments->execute([$ticketData['id']]);
    $comments = $comments->fetchAll(PDO::FETCH_ASSOC);
} else { $comments = []; }
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-ticket-perforated me-2"></i><?php echo $isEdit ? 'Ticket #' . sanitize($ticketData['ticket_number']) : 'Create New Ticket'; ?></h5></div>
            <div class="card-body">
                <?php if ($isEdit): ?>
                <div class="row mb-4">
                    <div class="col-md-8"><h6>Subject</h6><p class="fw-bold"><?php echo sanitize($ticketData['subject']); ?></p></div>
                    <div class="col-md-4 text-end"><span class="badge bg-<?php echo $statusColors[$ticketData['status']] ?? 'secondary'; ?> fs-6"><?php echo $statuses[$ticketData['status']] ?? $ticketData['status']; ?></span></div>
                </div>
                <div class="bg-light p-3 rounded mb-4"><?php echo nl2br(sanitize($ticketData['description'])); ?></div>
                
                <?php if (!empty($comments)): ?>
                <h6>Comments</h6>
                <div class="list-group mb-4">
                    <?php foreach ($comments as $c): ?>
                    <div class="list-group-item <?php echo $c['is_internal'] ? 'list-group-item-warning' : ''; ?>">
                        <div class="d-flex justify-content-between"><strong><?php echo sanitize($c['first_name'] . ' ' . $c['last_name'] ?: $c['username'] ?: 'System'); ?></strong><small><?php echo formatDateTime($c['created_at']); ?></small></div>
                        <p class="mb-0 mt-2"><?php echo nl2br(sanitize($c['comment'])); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="mb-4">
                    <input type="hidden" name="action" value="add_comment">
                    <textarea name="comment" class="form-control mb-2" rows="2" required placeholder="Add a comment..."></textarea>
                    <div class="d-flex justify-content-between">
                        <div class="form-check"><input type="checkbox" class="form-check-input" name="is_internal" id="is_internal"><label class="form-check-label" for="is_internal">Internal note</label></div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-reply me-1"></i>Add Comment</button>
                    </div>
                </form>
                
                <hr>
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach ($statuses as $v=>$l): ?><option value="<?php echo $v; ?>" <?php echo $ticketData['status'] === $v ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Update Status</button>
                        </div>
                    </div>
                </form>
                
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="create_ticket">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label required">Subject</label>
                            <input type="text" class="form-control" name="subject" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <?php foreach ($categories as $v=>$l): ?><option value="<?php echo $v; ?>"><?php echo $l; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority">
                                <?php foreach ($priorities as $v=>$l): ?><option value="<?php echo $v; ?>" <?php echo $v === 'medium' ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label required">Description</label>
                            <textarea class="form-control" name="description" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <a href="index.php?page=helpdesk/list" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Ticket</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
