<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['qr_code'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$scanned_qr = sanitize_input($_POST['qr_code']);
$today = date('Y-m-d');
$current_time = date('H:i:s');

// --- DECRYPT STATIC SECURE QR ---
try {
    $decrypted = str_rot13(base64_decode($scanned_qr));
    $parts = explode('|', $decrypted);

    if (count($parts) < 2) {
        throw new Exception("Invalid QR Format");
    }

    $member_id = $parts[0];
    $provided_signature = $parts[1];

    $secret = defined('SYSTEM_SECRET_KEY') ? SYSTEM_SECRET_KEY : 'wolf_secret_key';
    $expected_signature = substr(hash_hmac('sha256', $member_id, $secret), 0, 8);

    if ($provided_signature !== $expected_signature) {
        throw new Exception("Counterfeit QR detected");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'SECURITY ALERT: Invalid QR Code.']);
    exit;
}

// 1. Get Member
$member_query = $conn->prepare("SELECT * FROM member WHERE id = ?");
$member_query->bind_param("i", $member_id);
$member_query->execute();
$member_result = $member_query->get_result();

if ($member_result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Member not found']);
    exit;
}

$member = $member_result->fetch_assoc();

// 2. Check Membership Status - **FLEXIBLE: Get fee_type and per_visit_fee**
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

$has_active = $membership_result->num_rows > 0;
$membership = $has_active ? $membership_result->fetch_assoc() : null;

$member_status = 'Non-Member';
$plan_name = 'No Active Plan';
$fee = floatval(get_setting('non_member_rate')) ?? 80;

// **FLEXIBLE FEE LOGIC - Works with ANY plan!**
if ($has_active) {
    $member_status = 'Active';
    $plan_name = $membership['membership_name'];
    
    // Check fee type from plan settings
    if ($membership['fee_type'] == 'unlimited') {
        // UNLIMITED ACCESS - No fee per visit
        $fee = 0;
    } else {
        // PER-VISIT FEE - Use plan's custom fee
        $fee = floatval($membership['per_visit_fee']);
    }
} elseif ($member['is_student']) {
    $member_status = 'Student';
    $fee = floatval(get_setting('student_rate')) ?? 50;
}

// 3. Attendance Logic (Check-in/Out)
$active_checkin = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ? AND check_out_time IS NULL LIMIT 1");
$active_checkin->bind_param("is", $member_id, $today);
$active_checkin->execute();
$attendance_today = $active_checkin->get_result();

$action = 'check-in';
if ($attendance_today->num_rows > 0) {
    $action = 'check-out';
    $row = $attendance_today->fetch_assoc();
    $update = $conn->prepare("UPDATE attendance SET check_out_time = ? WHERE id = ?");
    $update->bind_param("si", $current_time, $row['id']);
    $success = $update->execute();
} else {
    $attendance_id = "ATT" . strtoupper(substr(uniqid(), -8));
    $insert = $conn->prepare("INSERT INTO attendance (attendance_id, user_id, user_type, date, check_in_time, member_status, fee_charged) VALUES (?, ?, 'Member', ?, ?, ?, ?)");
    $insert->bind_param("sisssd", $attendance_id, $member_id, $today, $current_time, $member_status, $fee);
    $success = $insert->execute();
}

if ($success) {
    echo json_encode([
        'success' => true,
        'action' => $action,
        'time' => date('g:i A', strtotime($current_time)),
        'member' => [
            'name' => $member['first_name'] . ' ' . $member['last_name'],
            'photo' => $member['photo'],
            'status' => $member_status,
            'plan' => $plan_name
        ],
        'membership_expiry' => ($membership) ? date('M d, Y', strtotime($membership['end_date'])) : null
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}