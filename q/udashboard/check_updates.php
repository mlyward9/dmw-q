<?php
require_once __DIR__ . '/../config/conn.php';
header('Content-Type: application/json');

$stmt = $conn->prepare("SELECT MAX(updated_at) as lastUpdate FROM queue");
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode(['lastUpdate' => $result['lastUpdate'] ?? date('Y-m-d H:i:s')]);