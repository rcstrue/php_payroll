<?php
/**
 * Attendance POST Handler - Runs before any output
 */

// Handle attendance save
if (isset($_POST['save_attendance'])) {
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
        foreach ($employeeCodes as $empCode) {
            $totalPresent = isset($_POST['total_present'][$empCode]) ? (float)$_POST['total_present'][$empCode] : 0;
            $totalExtra = isset($_POST['total_extra'][$empCode]) ? (float)$_POST['total_extra'][$empCode] : 0;
            $otHours = isset($_POST['overtime_hours'][$empCode]) ? (float)$_POST['overtime_hours'][$empCode] : 0;
            $totalWO = isset($_POST['total_wo'][$empCode]) ? (int)$_POST['total_wo'][$empCode] : 0;
            
            // Insert or update using ON DUPLICATE KEY
            $stmt = $db->prepare("
                INSERT INTO attendance_summary 
                (employee_id, unit_id, month, year, total_present, total_extra, overtime_hours, total_wo, source)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Manual')
                ON DUPLICATE KEY UPDATE 
                    total_present = VALUES(total_present),
                    total_extra = VALUES(total_extra),
                    overtime_hours = VALUES(overtime_hours),
                    total_wo = VALUES(total_wo),
                    source = 'Manual',
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$empCode, $unitId, $month, $year, $totalPresent, $totalExtra, $otHours, $totalWO]);
            $savedCount++;
        }
        
        $_SESSION['flash'] = array('type' => 'success', 'message' => "Attendance saved! {$savedCount} employees updated.");
        
    } catch (Exception $e) {
        $_SESSION['flash'] = array('type' => 'error', 'message' => 'Error saving attendance: ' . $e->getMessage());
    }
    
    // Redirect
    header("Location: index.php?page=attendance/add&client_id={$clientId}&unit_id={$unitId}&month={$month}&year={$year}&load=1");
    exit;
}
