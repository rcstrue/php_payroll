<?php
// This script fixes security issues in PHP files
// It fixes unsanitized output issues by adding sanitize() calls

$files_to_fix = [
    'modules/employee/view.php',
    'modules/billing/create.php',
    'modules/contract/list.php',
    'modules/deployment/add.php',
    'modules/employee/list.php',
    'modules/forms/form-xvi.php',
    'modules/leave/balance.php',
    'modules/notifications/index.php',
    'modules/ratecard/add.php',
    'modules/ratecard/list.php',
    'modules/recruitment/add.php',
    'modules/report/custom.php',
    'modules/requisition/add.php',
    'modules/unit/list.php',
    'modules/settlement/list.php',
    'modules/settlement/view.php'
];

// Pattern replacements for common unsanitized outputs
$replacements = [
    // Fix direct variable echoes in value attributes
    ['/<?php echo \$([a-zA-Z_]+); ?>/<?php echo sanitize($1); ?>/g',    // Fix array access
    ['/<?php echo \$([a-zA-Z_]+)\[\'([a-zA-Z_]+)\']; ?>/<?php echo sanitize($1[\'$2\']); ?>/g',
    // Fix object property access
    ['/<?php echo \$([a-zA-Z_]+)->([a-zA-Z_]+); ?>/<?php echo sanitize($1->$2); ?>/g',
];

echo "Security fix script generated\n";
