<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in (admin or staff can delete)
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Both admin and staff can delete members
if (!is_admin() && !is_staff()) {
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

// Check if member exists and is not already deleted
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

if ($member['deleted_at'] !== null) {
    echo json_encode(['success' => false, 'message' => 'Member is already in recycle bin']);
    exit;
}

// Perform soft delete - set deleted_at to current timestamp
$delete_query = $conn->prepare("UPDATE member SET deleted_at = NOW() WHERE id = ?");
$delete_query->bind_param("i", $member_id);

if ($delete_query->execute()) {
    // Log activity - use appropriate user session
    $user_id = is_admin() ? $_SESSION['admin_id'] : $_SESSION['staff_id'];
    $user_type = is_admin() ? 'Admin' : 'Staff';
    
    log_activity('Move to Recycle Bin', $user_type . ' moved member to recycle bin: ' . $member_name, $user_id);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Member moved to recycle bin successfully'
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to move member to recycle bin: ' . $conn->error
    ]);
}
?>