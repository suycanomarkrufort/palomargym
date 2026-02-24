<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Member ID is required']);
    exit;
}

$member_id = intval($_POST['id']);

// Check if member exists and is deleted
$check_query = $conn->prepare("SELECT id, first_name, last_name, deleted_at FROM member WHERE id = ?");
$check_query->bind_param("i", $member_id);
$check_query->execute();
$result = $check_query->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Member not found']);
    exit;
}

$member = $result->fetch_assoc();
$member_name = $member['first_name'] . ' ' . $member['last_name'];

if ($member['deleted_at'] === null) {
    echo json_encode(['success' => false, 'message' => 'Member is not in recycle bin']);
    exit;
}

// Restore member - set deleted_at to NULL
$restore_query = $conn->prepare("UPDATE member SET deleted_at = NULL WHERE id = ?");
$restore_query->bind_param("i", $member_id);

if ($restore_query->execute()) {
    // Log activity
    log_activity('Restore Member', 'Member restored from recycle bin: ' . $member_name, $_SESSION['admin_id']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Member restored successfully'
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to restore member: ' . $conn->error
    ]);
}
?>