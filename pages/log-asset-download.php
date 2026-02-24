<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['member_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$member_id = intval($_POST['member_id']);

// Get member info
$member_query = $conn->prepare("SELECT first_name, last_name FROM member WHERE id = ?");
$member_query->bind_param("i", $member_id);
$member_query->execute();
$member = $member_query->get_result()->fetch_assoc();

if ($member) {
    log_activity('Download QR Asset', 'QR Code downloaded for: ' . $member['first_name'] . ' ' . $member['last_name'], $_SESSION['admin_id']);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Member not found']);
}
?>