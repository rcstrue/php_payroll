<?php
/**
 * RCS HRMS Pro - 404 Error Page
 */

$pageTitle = 'Page Not Found';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-8 text-center">
        <div class="card">
            <div class="card-body py-5">
                <h1 class="display-1 text-muted">404</h1>
                <h2 class="mb-4">Page Not Found</h2>
                
                <div class="alert alert-info text-start">
                    <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Debug Information</h6>
                    <hr>
                    <p class="mb-1"><strong>Requested Page:</strong> <code><?php echo sanitize($_GET['page'] ?? 'unknown'); ?></code></p>
                    <p class="mb-1"><strong>Expected File:</strong> <code>modules/<?php echo sanitize($_GET['page'] ?? 'unknown'); ?>.php</code></p>
                    <p class="mb-1"><strong>Full URL:</strong> <code><?php echo sanitize($_SERVER['REQUEST_URI'] ?? ''); ?></code></p>
                    <p class="mb-0"><strong>Timestamp:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                </div>
                
                <p class="text-muted mb-4">
                    The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.
                </p>
                
                <div class="d-flex justify-content-center gap-2">
                    <a href="index.php?page=dashboard" class="btn btn-primary">
                        <i class="bi bi-house me-1"></i>Go to Dashboard
                    </a>
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Go Back
                    </a>
                </div>
                
                <div class="mt-4">
                    <small class="text-muted">
                        If you believe this is an error, please contact the system administrator.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
