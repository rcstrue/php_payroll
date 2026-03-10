<?php
/**
 * Advance POST Handler - Runs before any output
 */

// Handle advance save
if (isset($_POST['save_advance'])) {
    $unitId = (int)$_POST['unit_id'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    $employeeCodes = $_POST['employee_code'] ?? [];
    
    // Get client_id from unit
    $stmt = $db->prepare("SELECT client_id FROM units WHERE id = ?");
    $stmt->execute([$unitId]);
    $unitData = $stmt->fetch(PDO::FETCH_ASSOC);
    $clientId = $unitData ? $unitData['client_id'] : 0;
    
    $savedCount = 0;
    
    try {
        // Ensure employee_advances table exists
        $db->exec("CREATE TABLE IF NOT EXISTS `employee_advances` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `employee_id` int(11) NOT NULL,
            `unit_id` int(11) DEFAULT NULL,
            `month` int(2) NOT NULL,
            `year` int(4) NOT NULL,
            `adv1` decimal(10,2) DEFAULT 0.00,
            `adv2` decimal(10,2) DEFAULT 0.00,
            `office_advance` decimal(10,2) DEFAULT 0.00,
            `dress_advance` decimal(10,2) DEFAULT 0.00,
            `remarks` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_emp_month_year` (`employee_id`, `month`, `year`),
            KEY `idx_unit_month_year` (`unit_id`, `month`, `year`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        foreach ($employeeCodes as $empCode) {
            $adv1 = isset($_POST['adv1'][$empCode]) ? (float)$_POST['adv1'][$empCode] : 0;
            $adv2 = isset($_POST['adv2'][$empCode]) ? (float)$_POST['adv2'][$empCode] : 0;
            $officeAdv = isset($_POST['office_advance'][$empCode]) ? (float)$_POST['office_advance'][$empCode] : 0;
            $dressAdv = isset($_POST['dress_advance'][$empCode]) ? (float)$_POST['dress_advance'][$empCode] : 0;
            
            // Insert or update using ON DUPLICATE KEY
            $stmt = $db->prepare("
                INSERT INTO employee_advances 
                (employee_id, unit_id, month, year, adv1, adv2, office_advance, dress_advance)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    adv1 = VALUES(adv1),
                    adv2 = VALUES(adv2),
                    office_advance = VALUES(office_advance),
                    dress_advance = VALUES(dress_advance),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$empCode, $unitId, $month, $year, $adv1, $adv2, $officeAdv, $dressAdv]);
            $savedCount++;
        }
        
        $_SESSION['flash'] = array('type' => 'success', 'message' => "Advances saved! {$savedCount} employees updated.");
        
    } catch (Exception $e) {
        $_SESSION['flash'] = array('type' => 'error', 'message' => 'Error saving advances: ' . $e->getMessage());
    }
    
    // Redirect
    header("Location: index.php?page=advance/add&client_id={$clientId}&unit_id={$unitId}&month={$month}&year={$year}&load=1");
    exit;
}
