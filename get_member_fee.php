<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$member_id = intval($_GET['member_id']);
$today = date('Y-m-d');

// Get member details
$member_query = $conn->prepare("SELECT * FROM member WHERE id = ?");
$member_query->bind_param("i", $member_id);
$member_query->execute();
$member = $member_query->get_result()->fetch_assoc();

if (!$member) {
    echo json_encode(['error' => 'Member not found']);
    exit;
}

// Check membership status - SAME LOGIC AS manual-entry.php
$membership_query = $conn->prepare("
    SELECT m.*, mp.membership_name 
    FROM membership m 
    INNER JOIN membership_plans mp ON m.membership_plan_id = mp.id 
    WHERE m.member_id = ? AND m.status = 'Active' AND m.end_date >= ? 
    ORDER BY m.end_date DESC LIMIT 1
");
$membership_query->bind_param("is", $member_id, $today);
$membership_query->execute();
$membership_result = $membership_query->get_result();

$has_active_membership = $membership_result->num_rows > 0;

// AUTO-DETERMINE STATUS AND FEE
$member_status = 'Non-Member';
$fee = get_setting('non_member_rate');
$description = 'No active membership';

if ($has_active_membership) {
    $membership = $membership_result->fetch_assoc();
    $member_status = 'Active';
    $fee = 0;
    $description = 'Active membership: ' . $membership['membership_name'];
} elseif ($member['is_student']) {
    $member_status = 'Student';
    $fee = get_setting('student_rate');
    $description = 'Student rate applies';
} else {
    $expired_check = $conn->prepare("SELECT * FROM membership WHERE member_id = ? AND status = 'Expired' LIMIT 1");
    $expired_check->bind_param("i", $member_id);
    $expired_check->execute();
    if ($expired_check->get_result()->num_rows > 0) {
        $member_status = 'Expired';
        $fee = get_setting('member_discount_rate');
        $description = 'Expired member discount';
    }
}

echo json_encode([
    'status' => $member_status,
    'fee' => floatval($fee),
    'description' => $description
]);
?>