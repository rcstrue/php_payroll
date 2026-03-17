<?php
/**
 * API Endpoint: Get Next Unit Code
 * Returns the next available unit code
 */

// This is an API endpoint, return JSON
header('Content-Type: application/json');

try {
    // Get the highest unit code from the database
    $stmt = $db->query("SELECT unit_code FROM units WHERE unit_code IS NOT NULL AND unit_code != '' ORDER BY id DESC LIMIT 1");
    $lastUnit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextCode = 'UNT001';
    
    if ($lastUnit && !empty($lastUnit['unit_code'])) {
        // Extract the numeric part and increment
        $lastCode = $lastUnit['unit_code'];
        
        // Check if it has numbers at the end
        if (preg_match('/(\d+)$/', $lastCode, $matches)) {
            $num = (int)$matches[1] + 1;
            // Keep the prefix and pad the number
            $prefix = preg_replace('/\d+$/', '', $lastCode);
            if (empty($prefix)) {
                $prefix = 'UNT';
            }
            $nextCode = $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
        } else {
            $nextCode = 'UNT001';
        }
    }
    
    echo json_encode(['success' => true, 'unit_code' => $nextCode]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'unit_code' => 'UNT001']);
}
