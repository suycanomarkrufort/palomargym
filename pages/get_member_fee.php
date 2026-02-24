<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['member_id'])) {
    echo json_encode(['success' => false, 'message' => 'Member ID required']);
    exit;
}

$member_id = intval($_GET['member_id']);
$today = date('Y-m-d');

// Get member details
$member_query = $conn->prepare("SELECT * FROM member WHERE id = ?");
$member_query->bind_param("i", $member_id);
$member_query->execute();
$member_result = $member_query->get_result();

if ($member_result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Member not found']);
    exit;
}

$member = $member_result->fetch_assoc();

// Check active membership - **FLEXIBLE: Get fee_type and per_visit_fee**
$membership_query = $conn->prepare("
    SELECT m.*, mp.membership_name, mp.fee_type, mp.per_visit_fee
    FROM membership m 
    INNER JOIN membership_plans mp ON m.membership_plan_id = mp.id 
    WHERE m.member_id = ? AND m.status = 'Active' AND m.end_date >= ? 
    ORDER BY m.end_date DESC LIMIT 1
");
$membership_query->bind_param("is", $member_id, $today);
$membership_query->execute();
$membership_result = $membership_query->get_result();

$has_active_membership = $membership_result->num_rows > 0;

// Determine fee and status
$fee = 0;
$status = 'Non-Member';
$description = '';

if ($has_active_membership) {
    $membership = $membership_result->fetch_assoc();
    $status = 'Active Member';
    
    // **FLEXIBLE FEE LOGIC**
    if ($membership['fee_type'] == 'unlimited') {
        // UNLIMITED ACCESS
        $fee = 0;
        $description = $membership['membership_name'] . ' - Unlimited access (₱0 per visit)';
    } else {
        // PER-VISIT FEE
        $fee = floatval($membership['per_visit_fee']);
        $regular_rate = floatval(get_setting('non_member_rate'));
        $discount = $regular_rate - $fee;
        $description = $membership['membership_name'] . ' - ₱' . number_format($fee, 0) . ' per visit (₱' . number_format($discount, 0) . ' discount)';
    }
} elseif ($member['is_student']) {
    $status = 'Student';
    $fee = floatval(get_setting('student_rate'));
    $description = 'Student discount rate';
} else {
    // Check if expired member
    $expired_check = $conn->prepare("SELECT * FROM membership WHERE member_id = ? AND status = 'Expired' LIMIT 1");
    $expired_check->bind_param("i", $member_id);
    $expired_check->execute();
    
    if ($expired_check->get_result()->num_rows > 0) {
        $status = 'Expired Member';
        $fee = floatval(get_setting('member_discount_rate'));
        $description = 'Expired membership - discounted rate';
    } else {
        $status = 'Walk-in';
        $fee = floatval(get_setting('non_member_rate'));
        $description = 'Regular walk-in rate';
    }
}

echo json_encode([
    'success' => true,
    'fee' => $fee,
    'status' => $status,
    'description' => $description
]);