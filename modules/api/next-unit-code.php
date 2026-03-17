<?php
/**
 * API Endpoint: Get Next Unit Code
 * Returns the next available unit code
 */

header('Content-Type: application/json');

try {
    // Get the highest unit code from the database
    $stmt = $db->query("SELECT unit_code FROM units WHERE unit_code IS NOT NULL AND unit_code != '' ORDER BY unit_code DESC LIMIT 1");
    $lastUnit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextCode = 'UNIT001';
    
    if ($lastUnit && !empty($lastUnit['unit_code'])) {
        // Extract the numeric part and increment
        $lastCode = $lastUnit['unit_code'];
        
        // Check if it starts with UNIT and has numbers
        if (preg_match('/UNIT(\d+)/i', $lastCode, $matches)) {
            $num = (int)$matches[1] + 1;
            $nextCode = 'UNIT' . str_pad($num, 3, '0', STR_PAD_LEFT);
        } else {
            // Try to extract any numbers from the code
            if (preg_match('/(\d+)/', $lastCode, $matches)) {
                $num = (int)$matches[1] + 1;
                $nextCode = 'UNIT' . str_pad($num, 3, '0', STR_PAD_LEFT);
            }
        }
    }
    
    echo json_encode(['success' => true, 'unit_code' => $nextCode]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
