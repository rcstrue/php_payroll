<?php
/**
 * RCS HRMS Pro - Zones API Endpoint
 */

define('RCS_HRMS', true);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/database.php';

header('Content-Type: application/json');

$stateId = $_GET['state_id'] ?? null;

if ($stateId) {
    $stmt = $db->prepare("SELECT * FROM zones WHERE state_id = ? AND is_active = 1 ORDER BY zone_name");
    $stmt->execute([(int)$stateId]);
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($zones);
} else {
    $stmt = $db->query("SELECT z.*, s.state_name FROM zones z JOIN states s ON z.state_id = s.id WHERE z.is_active = 1 ORDER BY s.state_name, z.zone_name");
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($zones);
}
