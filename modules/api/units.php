<?php
/**
 * API - Get Units by Client
 */

header('Content-Type: application/json');

$clientParam = $_GET['client'] ?? '';

if (empty($clientParam)) {
    echo json_encode(['units' => []]);
    exit;
}

$units = $unit->getByClient($clientParam);

echo json_encode(['units' => $units]);
