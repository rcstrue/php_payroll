<?php
/**
 * RCS HRMS Pro - Issue Asset to Employee
 * Manpower Supplier - Asset Issuance
 */
require_once '../../config/config.php';
require_once '../../includes/database.php';
require_once '../../includes/class.auth.php';

$auth = new Auth($db);
if (!$auth->isLoggedIn()) {
    redirect('index.php?page=auth/login');
}

if (!in_array($_SESSION['role_code'], ['admin', 'hr_executive', 'manager'])) {
    setFlash('error', 'Access denied');
    redirect('index.php?page=dashboard');
}

$pageTitle = 'Issue Asset';
$page = 'assets/issue';
$errors = [];

$issuance = [
    'employee_id' => $_GET['employee_id'] ?? '',
    'asset_id' => $_GET['asset_id'] ?? '',
    'quantity' => 1,
    'issue_date' => date('Y-m-d'),
    'expected_return_date' => '',
    'issue_condition' => 'good',
    'issue_remarks' => ''
];

// Get available assets
$assets = $db->query("SELECT * FROM assets WHERE is_active = 1 AND available_quantity > 0 ORDER BY asset_name")->fetchAll(PDO::FETCH_ASSOC);

// Get active employees
$employees = $db->query("SELECT id, employee_code, full_name FROM employees WHERE status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issuance['employee_id'] = (int)$_POST['employee_id'];
    $issuance['asset_id'] = (int)$_POST['asset_id'];
    $issuance['quantity'] = (int)($_POST['quantity'] ?? 1);
    $issuance['issue_date'] = sanitize($_POST['issue_date']);
    $issuance['expected_return_date'] = !empty($_POST['expected_return_date']) ? sanitize($_POST['expected_return_date']) : null;
    $issuance['issue_condition'] = sanitize($_POST['issue_condition'] ?? 'good');
    $issuance['issue_remarks'] = sanitize($_POST['issue_remarks'] ?? '');
    
    // Validate
    if (empty($issuance['employee_id'])) {
        $errors[] = 'Please select an employee';
    }
    if (empty($issuance['asset_id'])) {
        $errors[] = 'Please select an asset';
    }
    if ($issuance['quantity'] < 1) {
        $errors[] = 'Quantity must be at least 1';
    }
    
    // Check available quantity
    $stmt = $db->prepare("SELECT available_quantity, asset_name FROM assets WHERE id = ?");
    $stmt->execute([$issuance['asset_id']]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$asset || $asset['available_quantity'] < $issuance['quantity']) {
        $errors[] = 'Insufficient quantity available';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Insert issuance record
            $stmt = $db->prepare("INSERT INTO employee_assets 
                (employee_id, asset_id, quantity, issue_date, expected_return_date, 
                issue_condition, issue_remarks, status, issued_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'issued', ?)");
            
            $stmt->execute([
                $issuance['employee_id'],
                $issuance['asset_id'],
                $issuance['quantity'],
                $issuance['issue_date'],
                $issuance['expected_return_date'],
                $issuance['issue_condition'],
                $issuance['issue_remarks'],
                $_SESSION['user_id']
            ]);
            
            // Update available quantity
            $stmt = $db->prepare("UPDATE assets SET available_quantity = available_quantity - ? WHERE id = ?");
            $stmt->execute([$issuance['quantity'], $issuance['asset_id']]);
            
            $db->commit();
            
            setFlash('success', "Asset '{$asset['asset_name']}' issued successfully");
            redirect('index.php?page=assets/issued');
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

include '../../templates/header.php';
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php?page=assets/list">Assets</a></li>
                    <li class="breadcrumb-item active">Issue Asset</li>
                </ol>
            </nav>
            <h1 class="page-title">Issue Asset to Employee</h1>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?php echo $error; ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST">
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Issue Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Employee</label>
                            <select name="employee_id" id="employee_id" class="form-select" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" 
                                        <?php echo $issuance['employee_id'] == $emp['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($emp['full_name']); ?> (<?php echo sanitize($emp['employee_code']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Asset</label>
                            <select name="asset_id" id="asset_id" class="form-select" required>
                                <option value="">Select Asset</option>
                                <?php foreach ($assets as $asset): ?>
                                <option value="<?php echo $asset['id']; ?>" 
                                        data-available="<?php echo $asset['available_quantity']; ?>"
                                        data-returnable="<?php echo $asset['is_returnable']; ?>"
                                        <?php echo $issuance['asset_id'] == $asset['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($asset['asset_name']); ?> 
                                    (<?php echo $asset['available_quantity']; ?> available)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Quantity</label>
                            <input type="number" name="quantity" class="form-control" 
                                   value="<?php echo $issuance['quantity']; ?>" min="1" id="quantity" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Issue Date</label>
                            <input type="date" name="issue_date" class="form-control" 
                                   value="<?php echo $issuance['issue_date']; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expected Return Date</label>
                            <input type="date" name="expected_return_date" class="form-control" 
                                   value="<?php echo $issuance['expected_return_date']; ?>" id="expectedReturn">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Condition at Issue</label>
                            <select name="issue_condition" class="form-select">
                                <option value="new" <?php echo $issuance['issue_condition'] == 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="good" <?php echo $issuance['issue_condition'] == 'good' ? 'selected' : ''; ?>>Good</option>
                                <option value="fair" <?php echo $issuance['issue_condition'] == 'fair' ? 'selected' : ''; ?>>Fair</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Remarks</label>
                            <input type="text" name="issue_remarks" class="form-control" 
                                   value="<?php echo $issuance['issue_remarks']; ?>" placeholder="Any notes">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    <h5 class="card-title mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Issue Asset
                        </button>
                        <a href="index.php?page=assets/list" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                    
                    <hr>
                    
                    <div class="alert alert-info">
                        <strong>Tip:</strong> Make sure to collect acknowledgement from employee for issued items.
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
include '../../templates/footer.php';
?>
