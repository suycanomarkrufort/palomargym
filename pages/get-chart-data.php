<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// Error handling
try {
    if (!is_logged_in()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // Get last 7 days data for revenue and check-ins
    $dates = [];
    $revenue_data = [];
    $checkins_data = [];

    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dates[] = date('M d', strtotime($date));
        
        // Get revenue for this date
        $revenue_query = $conn->prepare("SELECT COALESCE(SUM(fee_charged), 0) as total FROM attendance WHERE date = ?");
        if (!$revenue_query) {
            throw new Exception("Revenue query preparation failed: " . $conn->error);
        }
        $revenue_query->bind_param("s", $date);
        $revenue_query->execute();
        $revenue_result = $revenue_query->get_result();
        if (!$revenue_result) {
            throw new Exception("Revenue query execution failed");
        }
        $revenue_row = $revenue_result->fetch_assoc();
        $revenue_data[] = floatval($revenue_row['total']);
        
        // Get check-ins count for this date
        $checkins_query = $conn->prepare("SELECT COUNT(*) as count FROM attendance WHERE date = ?");
        if (!$checkins_query) {
            throw new Exception("Check-ins query preparation failed: " . $conn->error);
        }
        $checkins_query->bind_param("s", $date);
        $checkins_query->execute();
        $checkins_result = $checkins_query->get_result();
        if (!$checkins_result) {
            throw new Exception("Check-ins query execution failed");
        }
        $checkins_row = $checkins_result->fetch_assoc();
        $checkins_data[] = intval($checkins_row['count']);
    }

    // Get member status distribution - SIMPLIFIED VERSION
    // Check if membership table exists first
    $tables_result = $conn->query("SHOW TABLES LIKE 'membership'");
    
    $status_labels = [];
    $status_values = [];
    
    if ($tables_result && $tables_result->num_rows > 0) {
        // Membership table exists
        $status_query = $conn->prepare("
            SELECT 
                CASE 
                    WHEN ms.end_date >= CURDATE() AND ms.status = 'Active' THEN 'Active'
                    WHEN ms.end_date < CURDATE() THEN 'Expired'
                    WHEN ms.status IS NULL THEN 'No Membership'
                    ELSE ms.status
                END as status_type,
                COUNT(DISTINCT m.id) as count
            FROM member m
            LEFT JOIN membership ms ON m.id = ms.member_id
            GROUP BY status_type
            HAVING count > 0
            ORDER BY count DESC
        ");
        
        if ($status_query) {
            $status_query->execute();
            $status_result = $status_query->get_result();

            if ($status_result) {
                while ($row = $status_result->fetch_assoc()) {
                    $status_labels[] = $row['status_type'] ?: 'Unknown';
                    $status_values[] = intval($row['count']);
                }
            }
        }
    }
    
    // If no status data, use simple member count as fallback
    if (empty($status_labels)) {
        $member_count_query = $conn->query("SELECT COUNT(*) as count FROM member");
        if ($member_count_query) {
            $member_count = $member_count_query->fetch_assoc();
            $status_labels = ['Total Members'];
            $status_values = [intval($member_count['count'])];
        }
    }

    // Return all chart data
    echo json_encode([
        'success' => true,
        'revenue' => [
            'labels' => $dates,
            'values' => $revenue_data
        ],
        'checkins' => [
            'labels' => $dates,
            'values' => $checkins_data
        ],
        'status' => [
            'labels' => $status_labels,
            'values' => $status_values
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Chart data error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error loading chart data',
        'error' => $e->getMessage()
    ]);
}
?>