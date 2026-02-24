<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$today = date('Y-m-d');

// Total revenue today
$revenue_query = $conn->prepare("SELECT COALESCE(SUM(fee_charged), 0) as total FROM attendance WHERE date = ?");
$revenue_query->bind_param("s", $today);
$revenue_query->execute();
$revenue_result = $revenue_query->get_result()->fetch_assoc();
$today_revenue = floatval($revenue_result['total']);

// Floor traffic (people checked in today without checkout)
$traffic_query = $conn->prepare("SELECT COUNT(*) as count FROM attendance WHERE date = ? AND check_out_time IS NULL");
$traffic_query->bind_param("s", $today);
$traffic_query->execute();
$traffic_result = $traffic_query->get_result()->fetch_assoc();
$current_traffic = intval($traffic_result['count']);

// Count active members
$active_members_query = $conn->prepare("
    SELECT COUNT(DISTINCT m.id) as count 
    FROM member m
    LEFT JOIN membership ms ON m.id = ms.member_id
    WHERE ms.status = 'Active' AND ms.end_date >= CURDATE()
");
$active_members_query->execute();
$active_members_result = $active_members_query->get_result()->fetch_assoc();
$active_members = intval($active_members_result['count']);

// Get daily goal
$daily_goal = floatval(get_setting('daily_goal'));
$goal_percentage = ($daily_goal > 0) ? round(($today_revenue / $daily_goal) * 100) : 0;

echo json_encode([
    'success' => true,
    'today_revenue' => $today_revenue,
    'current_traffic' => $current_traffic,
    'active_members' => $active_members,
    'daily_goal' => $daily_goal,
    'goal_percentage' => $goal_percentage
]);
?>