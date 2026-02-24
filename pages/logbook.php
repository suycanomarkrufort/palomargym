<?php
require_once '../config/database.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../login.php');
}

// Get user info (admin or staff)
$user_id = get_user_id();
$is_staff_user = is_staff();
$table = $is_staff_user ? 'staff' : 'admin';

$user_query = $conn->prepare("SELECT * FROM $table WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$admin = $user_query->get_result()->fetch_assoc();

// Handle manual check-in request
if (isset($_POST['manual_checkin'])) {
    $checkin_type = $_POST['checkin_type'];
    
    $today = date('Y-m-d');
    $current_time = date('H:i:s');
    
    if ($checkin_type == 'guest') {
        // Walk-in Guest Check-in
        $guest_name = sanitize_input($_POST['guest_name']);
        $is_student = isset($_POST['is_student']) ? 1 : 0;
        
        // --- FIXED: STATUS LOGIC (Student or Non-Member) ---
        $member_status = $is_student ? 'Student' : 'Non-Member';
        
        // Fee calculation
        $fee = $is_student ? floatval(get_setting('student_rate')) : floatval(get_setting('non_member_rate'));
        
        // Generate Unique ID
        $att_uniq_id = uniqid('ATT');

        // Insert Query (user_id is NULL)
        $insert_query = $conn->prepare("INSERT INTO attendance (attendance_id, user_id, user_type, guest_name, member_status, date, check_in_time, fee_charged) VALUES (?, NULL, 'Walk-in', ?, ?, ?, ?, ?)");
        $insert_query->bind_param("sssssd", $att_uniq_id, $guest_name, $member_status, $today, $current_time, $fee);
        
        if ($insert_query->execute()) {
            log_activity('Manual Check-in', 'Guest: ' . $guest_name . ' checked in manually', get_user_id());
            header("Location: logbook.php?date=" . $today . "&success=1");
            exit;
        }
    } else {
        // Member Check-in
        $member_id = intval($_POST['member_id']);
        
        // Get member details
        $member_query = $conn->prepare("SELECT * FROM member WHERE id = ?");
        $member_query->bind_param("i", $member_id);
        $member_query->execute();
        $member = $member_query->get_result()->fetch_assoc();
        
        if ($member) {
            // Check membership status
            $membership_query = $conn->prepare("
                SELECT m.*, mp.membership_name, mp.membership_plan_id 
                FROM membership m 
                INNER JOIN membership_plans mp ON m.membership_plan_id = mp.id 
                WHERE m.member_id = ? AND m.status = 'Active' AND m.end_date >= ? 
                ORDER BY m.end_date DESC LIMIT 1
            ");
            $membership_query->bind_param("is", $member_id, $today);
            $membership_query->execute();
            $membership_result = $membership_query->get_result();
            
            $has_active_membership = $membership_result->num_rows > 0;
            
            $member_status = 'Non-Member';
            $fee = floatval(get_setting('non_member_rate'));
            
            if ($has_active_membership) {
                $membership = $membership_result->fetch_assoc();
                $member_status = 'Active';
                
                if ($membership['membership_plan_id'] == 'PLAN001') {
                    $fee = 0;
                } else {
                    $fee = floatval(get_setting('member_discount_rate'));
                }
            } elseif ($member['is_student']) {
                $member_status = 'Student';
                $fee = floatval(get_setting('student_rate'));
            } else {
                $expired_check = $conn->prepare("SELECT * FROM membership WHERE member_id = ? AND status = 'Expired' LIMIT 1");
                $expired_check->bind_param("i", $member_id);
                $expired_check->execute();
                if ($expired_check->get_result()->num_rows > 0) {
                    $member_status = 'Expired';
                    $fee = floatval(get_setting('member_discount_rate'));
                }
            }
            
            // Check if already checked in today
            $check_duplicate = $conn->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ? AND check_out_time IS NULL");
            $check_duplicate->bind_param("is", $member_id, $today);
            $check_duplicate->execute();
            
            if ($check_duplicate->get_result()->num_rows == 0) {
                $att_uniq_id = uniqid('ATT');

                $insert_query = $conn->prepare("INSERT INTO attendance (attendance_id, user_id, user_type, member_status, date, check_in_time, fee_charged) VALUES (?, ?, 'Member', ?, ?, ?, ?)");
                $insert_query->bind_param("sisssd", $att_uniq_id, $member_id, $member_status, $today, $current_time, $fee);
                
                if ($insert_query->execute()) {
                    log_activity('Manual Check-in', $member['first_name'] . ' ' . $member['last_name'] . ' checked in manually', get_user_id());
                    header("Location: logbook.php?date=" . $today . "&success=1");
                    exit;
                }
            } else {
                header("Location: logbook.php?date=" . $today . "&error=duplicate");
                exit;
            }
        }
    }
}

// Handle checkout request
if (isset($_POST['checkout_id'])) {
    $checkout_id = $_POST['checkout_id'];
    $checkout_time = date('H:i:s');
    
    $update_query = $conn->prepare("UPDATE attendance SET check_out_time = ? WHERE id = ?");
    $update_query->bind_param("si", $checkout_time, $checkout_id);
    $update_query->execute();
    
    header("Location: logbook.php?date=" . (isset($_GET['date']) ? $_GET['date'] : date('Y-m-d')));
    exit;
}

// Handle UNDO checkout request
if (isset($_POST['undo_checkout_id'])) {
    $undo_id = $_POST['undo_checkout_id'];
    
    $undo_query = $conn->prepare("UPDATE attendance SET check_out_time = NULL WHERE id = ?");
    $undo_query->bind_param("i", $undo_id);
    $undo_query->execute();
    
    header("Location: logbook.php?date=" . (isset($_GET['date']) ? $_GET['date'] : date('Y-m-d')));
    exit;
}

// Get date filter (default to today)
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$day_name = date('l', strtotime($filter_date));

// Get today's date for goal tracking
$today = date('Y-m-d');

// Get today's revenue for goal tracking
$revenue_query_goal = $conn->prepare("SELECT COALESCE(SUM(fee_charged), 0) as total FROM attendance WHERE date = ?");
$revenue_query_goal->bind_param("s", $today);
$revenue_query_goal->execute();
$revenue_result = $revenue_query_goal->get_result()->fetch_assoc();
$today_revenue = $revenue_result['total'];

// Get daily goal
$daily_goal = get_setting('daily_goal');
$goal_percentage = ($daily_goal > 0) ? round(($today_revenue / $daily_goal) * 100) : 0;

// Get attendance records
$attendance_query = $conn->prepare("
    SELECT a.*, m.first_name, m.last_name, m.photo 
    FROM attendance a 
    LEFT JOIN member m ON a.user_id = m.id 
    WHERE a.date = ? 
    ORDER BY a.check_in_time DESC
");
$attendance_query->bind_param("s", $filter_date);
$attendance_query->execute();
$attendance_result = $attendance_query->get_result();

// Get total revenue for filtered date
$revenue_query = $conn->prepare("SELECT COALESCE(SUM(fee_charged), 0) as total FROM attendance WHERE date = ?");
$revenue_query->bind_param("s", $filter_date);
$revenue_query->execute();
$revenue = $revenue_query->get_result()->fetch_assoc()['total'];

// Get all members for manual check-in
$members_query = "SELECT id, first_name, last_name, photo FROM member WHERE deleted_at IS NULL ORDER BY first_name ASC";
$members_result = $conn->query($members_query);

// Get pricing from settings
$walkin_fee = floatval(get_setting('non_member_rate'));
$student_fee = floatval(get_setting('student_rate'));
$expired_fee = floatval(get_setting('member_discount_rate'));

// Set page title
$page_title = "Gym Logbook";

// Include header
include '../includes/header.php';
?>
    
    <style>
        /* =============================================
           WOLF PALOMAR FITNESS GYM — COLOR PALETTE
           Based on logo: Black bg, Red shield,
           Navy blue center, White text, Silver accents
           =============================================
           --color-bg:        #0A0A0A  deep black
           --color-surface:   #141414  cards
           --color-surface-2: #1C1C1C  hover/header
           --color-border:    #2A2A2A  borders
           --color-primary:   #CC1C1C  shield red
           --color-primary-dk:#A01515  dark red
           --color-navy:      #1A3A8F  shield navy
           --color-silver:    #B0B0B0  wolf fur
           --color-white:     #FFFFFF  headings
           --color-text:      #CCCCCC  body text
           --color-muted:     #777777  labels
           ============================================= */
        :root {
            --color-bg:        #0A0A0A;
            --color-surface:   #141414;
            --color-surface-2: #1C1C1C;
            --color-border:    #2A2A2A;
            --color-primary:   #CC1C1C;
            --color-primary-dk:#A01515;
            --color-navy:      #1A3A8F;
            --color-silver:    #B0B0B0;
            --color-white:     #FFFFFF;
            --color-text:      #CCCCCC;
            --color-muted:     #777777;
        }

        body {
            background: var(--color-bg);
            color: var(--color-text);
        }

        /* OVERLAY FIX */
        .sidebar-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998 !important;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .bottom-nav  { z-index: 900  !important; }
        .sidebar     { z-index: 9999 !important; }

        body.sidebar-active { overflow: hidden !important; }

        /* Main */
        .main-content {
            padding: 20px;
            padding-bottom: 120px !important;
            box-sizing: border-box;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-white);
            margin-bottom: 20px;
            letter-spacing: 1px;
        }

        .page-title i {
            color: var(--color-primary);
            margin-right: 8px;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Logbook Header Card */
        .logbook-header {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 20px;
        }

        /* Day Selector */
        .day-selector {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
            background: var(--color-surface-2);
            padding: 8px;
            border-radius: 12px;
            overflow-x: auto;
            gap: 4px;
        }

        .day-btn {
            padding: 10px 18px;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--color-muted);
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .day-btn:hover {
            color: var(--color-white);
            background: rgba(204, 28, 28, 0.12);
        }

        .day-btn.active {
            background: var(--color-primary);
            color: var(--color-white);
            box-shadow: 0 4px 12px rgba(204, 28, 28, 0.4);
        }

        /* Revenue Display */
        .revenue-display {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px 20px;
            background: linear-gradient(135deg, rgba(204, 28, 28, 0.2) 0%, rgba(160, 21, 21, 0.1) 100%);
            border: 1px solid rgba(204, 28, 28, 0.3);
            border-radius: 12px;
            color: var(--color-white);
        }

        .revenue-icon {
            width: 50px;
            height: 50px;
            background: rgba(204, 28, 28, 0.2);
            border: 1px solid rgba(204, 28, 28, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #FF5555;
            flex-shrink: 0;
        }

        .revenue-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--color-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .revenue-amount {
            font-size: 28px;
            font-weight: 700;
            color: var(--color-white);
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: var(--color-surface);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--color-border);
        }

        thead { background: var(--color-surface-2); }

        th {
            padding: 1rem;
            text-align: left;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--color-silver);
            border-bottom: 2px solid var(--color-border);
        }

        tbody tr {
            border-bottom: 1px solid #1E1E1E;
            transition: background 0.2s ease;
        }

        tbody tr:hover { background: var(--color-surface-2); }

        td {
            padding: 1rem;
            color: var(--color-text);
            font-size: 0.9rem;
        }

        /* Member avatar in table */
        .table-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #CC1C1C, #A01515);
            border: 2px solid rgba(204, 28, 28, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }

        .table-avatar i {
            color: var(--color-white);
            font-size: 1rem;
        }

        /* Status badges */
        .status-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .status-active   { background: rgba(204,28,28,0.15);  color: #FF5555; border: 1px solid rgba(204,28,28,0.3); }
        .status-student  { background: rgba(26,58,143,0.2);   color: #4A7AFF; border: 1px solid rgba(26,58,143,0.35); }
        .status-expired  { background: rgba(100,100,100,0.2); color: #888888; border: 1px solid rgba(100,100,100,0.3); }
        .status-other    { background: rgba(176,176,176,0.1); color: var(--color-muted); border: 1px solid rgba(176,176,176,0.2); }

        /* Checkout / Undo buttons */
        .checkout-btn {
            padding: 7px 14px;
            border: none;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(204, 28, 28, 0.18);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.3);
            letter-spacing: 0.3px;
        }

        .checkout-btn:hover {
            background: rgba(204, 28, 28, 0.3);
            transform: scale(1.05);
        }

        .undo-btn {
            padding: 7px 14px;
            border: none;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            border: 1px solid rgba(26, 58, 143, 0.3);
            margin-left: 8px;
            letter-spacing: 0.3px;
        }

        .undo-btn:hover {
            background: rgba(26, 58, 143, 0.35);
            transform: scale(1.05);
        }

        /* Active in gym indicator */
        .still-in {
            color: var(--color-primary);
            font-weight: 600;
            font-size: 0.85rem;
        }

        /* Checked out time */
        .checked-out-time {
            color: var(--color-silver);
            font-weight: 500;
        }

        /* Fee column */
        .fee-value {
            color: #FF5555;
            font-weight: 700;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px;
            background: var(--color-surface);
            border-radius: 16px;
            border: 1px solid var(--color-border);
            color: var(--color-muted);
        }

        /* FAB — red brand color */
        .fab-container {
            position: fixed;
            bottom: 90px;
            right: 20px;
            z-index: 999;
        }

        .fab {
            width: 60px;
            height: 60px;
            background: var(--color-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(204, 28, 28, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            color: var(--color-white);
            font-size: 24px;
        }

        .fab:hover {
            transform: scale(1.1);
            background: var(--color-primary-dk);
            box-shadow: 0 6px 30px rgba(204, 28, 28, 0.6);
        }

        .fab:active { transform: scale(0.95); }

        /* ===== MODAL STYLES ===== */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.active { display: flex; }

        .modal-content {
            background: #141414 !important;
            border: 1px solid #2A2A2A !important;
            border-radius: 16px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            animation: slideUp 0.3s ease;
            color: #CCCCCC !important;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--color-white);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-title i { color: var(--color-primary); }

        /* Check-in type cards */
        .checkin-type-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }

        .type-card {
            padding: 20px;
            border: 2px solid var(--color-border);
            background: var(--color-surface-2);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--color-muted);
        }

        .type-card:hover {
            border-color: rgba(204, 28, 28, 0.4);
            color: var(--color-text);
        }

        .type-card.active {
            border-color: var(--color-primary);
            background: rgba(204, 28, 28, 0.1);
            color: var(--color-white);
        }

        .type-card i {
            font-size: 30px;
            margin-bottom: 10px;
            display: block;
        }

        .type-card.active i { color: var(--color-primary); }

        .type-card div { font-weight: 600; font-size: 0.9rem; }

        .checkin-form-section { display: none; }
        .checkin-form-section.active { display: block; }

        /* Form controls inside modal */
        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--color-muted);
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            width: 100%;
            background: var(--color-surface-2);
            border: 1px solid var(--color-border);
            color: var(--color-white);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }

        .form-control::placeholder { color: var(--color-muted); }

        .form-control:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(204, 28, 28, 0.12);
        }

        select.form-control option {
            background: var(--color-surface-2);
            color: var(--color-white);
        }

        /* Student checkbox card */
        .checkbox-card {
            background: var(--color-surface-2);
            border: 2px solid var(--color-border);
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--color-text);
        }

        .checkbox-card:hover { border-color: rgba(204, 28, 28, 0.4); }

        .checkbox-card.active {
            border-color: var(--color-primary);
            background: rgba(204, 28, 28, 0.1);
        }

        .checkbox-card input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--color-primary);
        }

        .checkbox-card label {
            cursor: pointer;
            font-weight: 600;
            flex: 1;
            color: var(--color-text);
        }

        /* Fee display in modal */
        .fee-display {
            background: rgba(204, 28, 28, 0.08);
            border: 2px solid rgba(204, 28, 28, 0.3);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            margin-bottom: 20px;
        }

        .fee-label {
            font-size: 11px;
            color: var(--color-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .fee-amount {
            font-size: 32px;
            font-weight: 700;
            color: #FF5555;
        }

        /* Modal buttons */
        .btn-primary {
            background: var(--color-primary);
            color: var(--color-white);
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-primary:hover { background: var(--color-primary-dk); }

        .btn-secondary {
            background: var(--color-surface-2);
            color: var(--color-text);
            border: 1px solid var(--color-border);
            border-radius: 8px;
            padding: 12px 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-secondary:hover { background: #252525; }

        /* ===== ALERT MODAL STYLES ===== */
        .alert-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10001;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }

        .alert-modal.active { display: flex; }

        .alert-modal-content {
            background: #141414 !important;
            border: 1px solid #2A2A2A !important;
            border-radius: 16px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            animation: slideUp 0.3s ease;
            color: #CCCCCC !important;
        }

        .alert-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .alert-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
        }

        /* Success — navy */
        .alert-icon.success {
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            border: 1px solid rgba(26, 58, 143, 0.3);
        }

        /* Error — red */
        .alert-icon.error {
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.3);
        }

        /* Warning — silver/muted for undo/checkout */
        .alert-icon.warning {
            background: rgba(176, 176, 176, 0.1);
            color: var(--color-silver);
            border: 1px solid rgba(176, 176, 176, 0.2);
        }

        .alert-title { flex: 1; }

        .alert-title h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #FFFFFF !important;
        }

        .alert-body {
            margin-bottom: 25px;
            color: #CCCCCC !important;
            line-height: 1.6;
            font-size: 15px;
        }

        .alert-body strong { color: #FFFFFF !important; }

        .alert-footer { display: flex; gap: 10px; }

        .alert-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        /* Success OK — navy */
        .alert-btn.success {
            background: var(--color-navy);
            color: var(--color-white);
        }

        .alert-btn.success:hover { background: #243FA0; }

        /* Danger — red (for permanent actions like checkout) */
        .alert-btn.danger {
            background: var(--color-primary);
            color: var(--color-white);
        }

        .alert-btn.danger:hover { background: var(--color-primary-dk); }

        /* Warning — navy for undo (reversible action) */
        .alert-btn.warning {
            background: var(--color-navy);
            color: var(--color-white);
        }

        .alert-btn.warning:hover { background: #243FA0; }

        /* Cancel — dark */
        .alert-btn.secondary {
            background: #1C1C1C !important;
            color: #CCCCCC !important;
            border: 1px solid #2A2A2A !important;
        }

        .alert-btn.secondary:hover { background: #252525 !important; }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .fab-container { bottom: 80px; right: 15px; }
            .fab { width: 55px; height: 55px; font-size: 22px; }
            .modal-content { padding: 20px; }
            .checkin-type-selector { grid-template-columns: 1fr; }
        }
    </style>
    
    <div class="main-content">
        <h1 class="page-title">
            <i class="fas fa-clock"></i> GYM LOGBOOK
        </h1>
        
        <div class="logbook-header">
            <div class="day-selector">
                <?php
                $days = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
                $selected_day_index = date('w', strtotime($filter_date));

                foreach ($days as $index => $day):
                    $active_class = ($index == $selected_day_index) ? 'active' : '';
                ?>
                    <button class="day-btn <?php echo $active_class; ?>" onclick="changeDay(<?php echo $index; ?>)">
                        <?php echo $day; ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <div class="revenue-display">
                <div class="revenue-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div style="flex: 1;">
                    <div class="revenue-label">
                        <?php echo strtoupper($day_name); ?> FLOOR REVENUE
                    </div>
                    <div class="revenue-amount">
                        ₱<?php echo number_format($revenue, 2); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <?php if ($attendance_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Status</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Fee</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($record = $attendance_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="table-avatar">
                                            <?php if ($record['photo']): ?>
                                                <img src="<?php echo $base_url; ?>assets/uploads/<?php echo $record['photo']; ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div style="white-space: nowrap; color: var(--color-white);">
                                            <?php 
                                            if ($record['user_type'] == 'Walk-in' && $record['guest_name']) {
                                                echo '<strong>' . htmlspecialchars($record['guest_name']) . '</strong> <span style="font-size:10px; color: var(--color-muted);">(Guest)</span>';
                                            } else {
                                                echo $record['first_name'] . ' ' . $record['last_name'];
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $status = strtolower($record['member_status']);
                                    $badge_class = 'status-other';
                                    if ($status == 'active')  $badge_class = 'status-active';
                                    elseif ($status == 'student')  $badge_class = 'status-student';
                                    elseif ($status == 'expired')  $badge_class = 'status-expired';
                                    ?>
                                    <span class="status-badge <?php echo $badge_class; ?>">
                                        <?php echo strtoupper($record['member_status']); ?>
                                    </span>
                                </td>
                                <td style="white-space: nowrap; color: var(--color-white);">
                                    <?php echo date('g:i A', strtotime($record['check_in_time'])); ?>
                                </td>
                                <td style="white-space: nowrap;">
                                    <?php 
                                    if ($record['check_out_time']) {
                                        echo '<div style="display: flex; align-items: center; gap: 8px;">';
                                        echo '<span class="checked-out-time"><i class="fas fa-check-circle" style="color: var(--color-silver);"></i> ' . date('g:i A', strtotime($record['check_out_time'])) . '</span>';
                                        echo '<button type="button" class="undo-btn" onclick="confirmUndo(' . $record['id'] . ')">
                                                <i class="fas fa-undo"></i> UNDO
                                              </button>';
                                        echo '</div>';
                                    } else {
                                        if ($record['user_type'] == 'Walk-in') {
                                            echo '<button type="button" class="checkout-btn" onclick="confirmCheckout(' . $record['id'] . ', \'' . addslashes($record['guest_name']) . '\')">
                                                    <i class="fas fa-sign-out-alt"></i> OUT
                                                  </button>';
                                        } else {
                                            echo '<span class="still-in"><i class="fas fa-dumbbell"></i> IN</span>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td><span class="fee-value">₱<?php echo number_format($record['fee_charged'], 2); ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3; display: block; margin-bottom: 16px;"></i>
                    <h3 style="color: var(--color-muted);">No records for this date</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Floating Action Button -->
    <div class="fab-container">
        <button class="fab" onclick="openCheckinModal()" title="Manual Check-in">
            <i class="fas fa-plus"></i>
        </button>
    </div>
    
    <!-- Manual Check-in Modal -->
    <div class="modal" id="checkinModal">
        <div class="modal-content">
            <h2 class="modal-title">
                <i class="fas fa-user-plus"></i> Manual Check-in
            </h2>
            
            <div class="checkin-type-selector">
                <div class="type-card active" onclick="selectCheckinType('guest')">
                    <i class="fas fa-walking"></i>
                    <div>Walk-in Guest</div>
                </div>
                <div class="type-card" onclick="selectCheckinType('member')">
                    <i class="fas fa-user-friends"></i>
                    <div>Member</div>
                </div>
            </div>
            
            <form method="POST">
                <input type="hidden" name="manual_checkin" value="1">
                <input type="hidden" name="checkin_type" id="checkin_type" value="guest">
                
                <!-- Guest Form -->
                <div class="checkin-form-section active" id="guest-form">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="form-label">Guest Name *</label>
                        <input type="text" name="guest_name" class="form-control" placeholder="Enter guest name">
                    </div>
                    
                    <div class="checkbox-card" onclick="toggleStudentCheckbox()">
                        <input type="checkbox" name="is_student" id="is_student" onchange="updateGuestFee()">
                        <label for="is_student">
                            <i class="fas fa-graduation-cap" style="color: var(--color-primary); margin-right: 6px;"></i> Guest is a STUDENT
                        </label>
                    </div>
                    
                    <div class="fee-display">
                        <div class="fee-label">Fee to be charged</div>
                        <div class="fee-amount">₱<span id="guest-fee"><?php echo number_format($walkin_fee, 0); ?></span></div>
                    </div>
                </div>
                
                <!-- Member Form -->
                <div class="checkin-form-section" id="member-form">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="form-label">Select Member *</label>
                        <select name="member_id" id="member_select" class="form-control" onchange="updateMemberFee()">
                            <option value="">Choose a member</option>
                            <?php while($member = $members_result->fetch_assoc()): ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo $member['first_name'] . ' ' . $member['last_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="fee-display">
                        <div class="fee-label">Fee to be charged (Auto-calculated)</div>
                        <div class="fee-amount">₱<span id="member-fee">0.00</span></div>
                        <small style="color: var(--color-muted); font-size: 12px; display: block; margin-top: 8px;" id="member-status-info">
                            Select a member to see fee
                        </small>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="submit" class="btn-primary" style="flex: 1;">
                        <i class="fas fa-check"></i> Check In NOW
                    </button>
                    <button type="button" class="btn-secondary" style="flex: 1;" onclick="closeCheckinModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Success Alert Modal -->
    <div class="alert-modal" id="successModal">
        <div class="alert-modal-content">
            <div class="alert-header">
                <div class="alert-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="alert-title">
                    <h3>Success!</h3>
                </div>
            </div>
            <div class="alert-body">
                <p>Check-in recorded successfully!</p>
            </div>
            <div class="alert-footer">
                <button class="alert-btn success" onclick="closeSuccessModal()">
                    <i class="fas fa-check"></i> OK
                </button>
            </div>
        </div>
    </div>

    <!-- Error Alert Modal -->
    <div class="alert-modal" id="errorModal">
        <div class="alert-modal-content">
            <div class="alert-header">
                <div class="alert-icon error">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="alert-title">
                    <h3>Error</h3>
                </div>
            </div>
            <div class="alert-body">
                <p id="errorMessage">An error occurred.</p>
            </div>
            <div class="alert-footer">
                <button class="alert-btn secondary" onclick="closeErrorModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Checkout Confirmation Modal -->
    <div class="alert-modal" id="checkoutModal">
        <div class="alert-modal-content">
            <div class="alert-header">
                <div class="alert-icon warning">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <div class="alert-title">
                    <h3>Check Out Guest</h3>
                </div>
            </div>
            <div class="alert-body">
                <p>Are you sure you want to check out <strong id="checkoutGuestName"></strong>?</p>
            </div>
            <div class="alert-footer">
                <button class="alert-btn secondary" onclick="closeCheckoutModal()">Cancel</button>
                <button class="alert-btn danger" onclick="proceedCheckout()">
                    <i class="fas fa-sign-out-alt"></i> Check Out
                </button>
            </div>
        </div>
    </div>

    <!-- Undo Confirmation Modal -->
    <div class="alert-modal" id="undoModal">
        <div class="alert-modal-content">
            <div class="alert-header">
                <div class="alert-icon warning">
                    <i class="fas fa-undo"></i>
                </div>
                <div class="alert-title">
                    <h3>Undo Checkout</h3>
                </div>
            </div>
            <div class="alert-body">
                <p>Are you sure you want to undo this checkout?</p>
            </div>
            <div class="alert-footer">
                <button class="alert-btn secondary" onclick="closeUndoModal()">Cancel</button>
                <button class="alert-btn warning" onclick="proceedUndo()">
                    <i class="fas fa-undo"></i> Undo
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden forms for POST requests -->
    <form id="checkoutForm" method="POST" style="display: none;">
        <input type="hidden" name="checkout_id" id="checkoutIdInput">
    </form>

    <form id="undoForm" method="POST" style="display: none;">
        <input type="hidden" name="undo_checkout_id" id="undoIdInput">
    </form>
    
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/bottom-nav.php'; ?>
    
    <?php 
    $extra_scripts = "
    <script>
        const WALKIN_FEE = " . $walkin_fee . ";
        const STUDENT_FEE = " . $student_fee . ";
        const EXPIRED_FEE = " . $expired_fee . ";
        
        let checkoutId = null;
        let undoId = null;

        " . (isset($_GET['success']) ? "
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('successModal').classList.add('active');
        });
        " : "") . "

        " . (isset($_GET['error']) ? "
        document.addEventListener('DOMContentLoaded', function() {
            const errorType = '" . $_GET['error'] . "';
            const errorMsg = errorType === 'duplicate' ? 'Member already checked in today!' : 'Failed to process check-in.';
            document.getElementById('errorMessage').textContent = errorMsg;
            document.getElementById('errorModal').classList.add('active');
        });
        " : "") . "

        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('success');
                window.history.replaceState({}, '', url);
            }
        }

        function closeErrorModal() {
            document.getElementById('errorModal').classList.remove('active');
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('error');
                window.history.replaceState({}, '', url);
            }
        }

        function confirmCheckout(id, name) {
            checkoutId = id;
            document.getElementById('checkoutGuestName').textContent = name;
            document.getElementById('checkoutModal').classList.add('active');
        }

        function closeCheckoutModal() {
            document.getElementById('checkoutModal').classList.remove('active');
            checkoutId = null;
        }

        function proceedCheckout() {
            if (checkoutId) {
                document.getElementById('checkoutIdInput').value = checkoutId;
                document.getElementById('checkoutForm').submit();
            }
        }

        function confirmUndo(id) {
            undoId = id;
            document.getElementById('undoModal').classList.add('active');
        }

        function closeUndoModal() {
            document.getElementById('undoModal').classList.remove('active');
            undoId = null;
        }

        function proceedUndo() {
            if (undoId) {
                document.getElementById('undoIdInput').value = undoId;
                document.getElementById('undoForm').submit();
            }
        }

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('alert-modal')) {
                closeSuccessModal();
                closeErrorModal();
                closeCheckoutModal();
                closeUndoModal();
            }
        });

        function changeDay(targetDayIdx) {
            let date = new Date();
            let currentDay = date.getDay();
            let diff = targetDayIdx - currentDay;
            date.setDate(date.getDate() + diff);
            let formattedDate = date.toISOString().split('T')[0];
            window.location.href = 'logbook.php?date=' + formattedDate;
        }

        function openCheckinModal() {
            document.getElementById('checkinModal').classList.add('active');
        }

        function closeCheckinModal() {
            document.getElementById('checkinModal').classList.remove('active');
        }

        function selectCheckinType(type) {
            document.getElementById('checkin_type').value = type;

            document.querySelectorAll('.type-card').forEach(card => card.classList.remove('active'));
            event.target.closest('.type-card').classList.add('active');

            document.querySelectorAll('.checkin-form-section').forEach(section => section.classList.remove('active'));

            if (type === 'guest') {
                document.getElementById('guest-form').classList.add('active');
            } else {
                document.getElementById('member-form').classList.add('active');
            }
        }

        function toggleStudentCheckbox() {
            const checkbox = document.getElementById('is_student');
            checkbox.checked = !checkbox.checked;
            updateGuestFee();
        }

        function updateGuestFee() {
            const isStudent = document.getElementById('is_student').checked;
            const fee = isStudent ? STUDENT_FEE : WALKIN_FEE;
            document.getElementById('guest-fee').textContent = fee.toLocaleString();

            const checkboxCard = document.querySelector('.checkbox-card');
            if (isStudent) {
                checkboxCard.classList.add('active');
            } else {
                checkboxCard.classList.remove('active');
            }
        }

        function updateMemberFee() {
            const memberId = document.getElementById('member_select').value;

            if (!memberId) {
                document.getElementById('member-fee').textContent = '0.00';
                document.getElementById('member-status-info').textContent = 'Select a member to see fee';
                return;
            }

            fetch('get_member_fee.php?member_id=' + memberId)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('member-fee').textContent = data.fee.toFixed(2);
                    document.getElementById('member-status-info').textContent = 'Status: ' + data.status + ' - ' + data.description;
                })
                .catch(error => console.error('Error:', error));
        }

        document.getElementById('checkinModal').addEventListener('click', function(e) {
            if (e.target === this) closeCheckinModal();
        });
    </script>
    ";
    
    include '../includes/footer.php'; 
    ?>