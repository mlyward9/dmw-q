<?php
require_once __DIR__ . '/../config/conn.php';

header('Content-Type: application/json');

// Get unplayed notifications
$sql = "SELECT id, ticket_number, counter FROM ticket_notifications WHERE played = 0 ORDER BY id ASC LIMIT 1";
$result = $conn->query($sql);

if ($result && $row = $result->fetch_assoc()) {
    // Mark as played
    $id = $row['id'];
    $update_sql = "UPDATE ticket_notifications SET played = 1 WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    echo json_encode([
        'hasNotification' => true,
        'ticketNumber' => $row['ticket_number'],
        'counter' => $row['counter']
    ]);
} else {
    echo json_encode(['hasNotification' => false]);
}

$conn->close();
?>