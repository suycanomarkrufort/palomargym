<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['name'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$search_name = sanitize_input($_GET['name']);

$query = $conn->prepare("
    SELECT m.*, 
    (SELECT membership_name FROM membership_plans mp 
     INNER JOIN membership ms ON mp.id = ms.membership_plan_id 
     WHERE ms.member_id = m.id AND ms.status = 'Active' 
     ORDER BY ms.end_date DESC LIMIT 1) as membership_plan,
    (SELECT status FROM membership 
     WHERE member_id = m.id AND status = 'Active' 
     ORDER BY end_date DESC LIMIT 1) as membership_status
    FROM member m 
    WHERE CONCAT(m.first_name, ' ', m.last_name) LIKE ? 
    LIMIT 1
");
$search_param = "%$search_name%";
$query->bind_param("s", $search_param);
$query->execute();
$result = $query->get_result();

if ($result->num_rows > 0) {
    $member = $result->fetch_assoc();
    
    $status_text = '● WAITING...';
    if ($member['membership_status'] == 'Active') {
        $status_text = '● MEMBERSHIP';
    } else if ($member['is_student']) {
        $status_text = '● STUDENT';
    }

    // --- STATIC SECURE QR GENERATION (2-PART: ID | HASH) ---
    $secret = defined('SYSTEM_SECRET_KEY') ? SYSTEM_SECRET_KEY : 'wolf_secret_key';
    $member_id = $member['id'];
    $hash = substr(hash_hmac('sha256', $member_id, $secret), 0, 8);
    
    // Ito ang token na laging pareho para sa member na ito
    $secure_token = base64_encode(str_rot13($member_id . '|' . $hash));
    
    echo json_encode([
        'success' => true,
        'member' => [
            'id' => $member['id'],
            'first_name' => $member['first_name'],
            'last_name' => $member['last_name'],
            'qr_code' => $secure_token, 
            'phone_number' => $member['phone_number'],
            'is_student' => $member['is_student'],
            'membership_plan' => $member['membership_plan'] ? $member['membership_plan'] : '---',
            'membership_status' => $status_text
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Member not found']);
}