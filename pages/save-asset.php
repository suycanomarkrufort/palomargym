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

$update_query = $conn->prepare("UPDATE member SET asset_saved = 1 WHERE id = ?");
$update_query->bind_param("i", $member_id);

if ($update_query->execute()) {
    echo json_encode(['success' => true, 'message' => 'Asset saved to Vault!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save asset.']);
}