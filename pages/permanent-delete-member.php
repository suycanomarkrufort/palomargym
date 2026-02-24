<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $member_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($member_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
        exit;
    }
    
    // Check if member exists and is deleted
    $check_query = $conn->prepare("SELECT id, first_name, last_name, deleted_at, photo FROM member WHERE id = ?");
    $check_query->bind_param("i", $member_id);
    $check_query->execute();
    $result = $check_query->get_result();
    
    if ($result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
        exit;
    }
    
    $member = $result->fetch_assoc();
    $member_name = $member['first_name'] . ' ' . $member['last_name'];
    
    if ($member['deleted_at'] === null) {
        echo json_encode(['success' => false, 'message' => 'Member must be in recycle bin before permanent deletion']);
        exit;
    }
    
    // Start transaction for safe deletion
    $conn->begin_transaction();
    
    try {
        // Delete related records first (to maintain referential integrity)
        
        // 1. Delete emergency contacts
        $delete_emergency = $conn->prepare("DELETE FROM emergency_contacts WHERE member_id = ?");
        $delete_emergency->bind_param("i", $member_id);
        $delete_emergency->execute();
        
        // 2. Delete health and fitness info
        $delete_health = $conn->prepare("DELETE FROM health_and_fitness_info WHERE member_id = ?");
        $delete_health->bind_param("i", $member_id);
        $delete_health->execute();
        
        // 3. Delete attendance records
        $delete_attendance = $conn->prepare("DELETE FROM attendance WHERE user_id = ?");
        $delete_attendance->bind_param("i", $member_id);
        $delete_attendance->execute();
        
        // 4. Delete membership records
        $delete_membership = $conn->prepare("DELETE FROM membership WHERE member_id = ?");
        $delete_membership->bind_param("i", $member_id);
        $delete_membership->execute();
        
        // 5. Delete member photo if exists
        if ($member['photo'] && file_exists("../assets/uploads/" . $member['photo'])) {
            unlink("../assets/uploads/" . $member['photo']);
        }
        
        // 6. Finally, delete the member record
        $delete_member = $conn->prepare("DELETE FROM member WHERE id = ?");
        $delete_member->bind_param("i", $member_id);
        $delete_member->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Log activity
        log_activity('Permanent Delete', 'Member permanently deleted: ' . $member_name, $_SESSION['admin_id']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Member permanently deleted successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to permanently delete member: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>