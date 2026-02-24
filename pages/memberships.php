<?php
require_once '../config/database.php';

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

// Get today's date for goal tracking
$today = date('Y-m-d');

// Get today's revenue for goal tracking
$revenue_query = $conn->prepare("SELECT COALESCE(SUM(fee_charged), 0) as total FROM attendance WHERE date = ?");
$revenue_query->bind_param("s", $today);
$revenue_query->execute();
$revenue_result = $revenue_query->get_result()->fetch_assoc();
$today_revenue = $revenue_result['total'];

// Get daily goal
$daily_goal = get_setting('daily_goal');
$goal_percentage = ($daily_goal > 0) ? round(($today_revenue / $daily_goal) * 100) : 0;

// Get all members without active membership
$members_query = "SELECT m.* FROM member m 
    LEFT JOIN membership ms ON m.id = ms.member_id AND ms.status = 'Active'
    WHERE ms.id IS NULL
    ORDER BY m.first_name ASC";
$members_result = $conn->query($members_query);

// Get all membership plans
$plans_query = "SELECT * FROM membership_plans ORDER BY membership_duration_days ASC";
$plans_result = $conn->query($plans_query);

// Duplicate query for renew dropdown
$renew_plans_query = "SELECT * FROM membership_plans ORDER BY membership_duration_days ASC";
$renew_plans_result = $conn->query($renew_plans_query);

// Get active memberships
$active_memberships_query = "
    SELECT m.id as membership_id, m.member_id, m.start_date, m.end_date, m.status,
           mem.first_name, mem.last_name, mem.photo,
           mp.membership_name, mp.membership_price, mp.membership_duration_days,
           mp.fee_type, mp.per_visit_fee
    FROM membership m
    INNER JOIN member mem ON m.member_id = mem.id
    INNER JOIN membership_plans mp ON m.membership_plan_id = mp.id
    WHERE m.status = 'Active'
    ORDER BY m.end_date ASC
";
$active_memberships = $conn->query($active_memberships_query);

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] == 'create_plan') {
        $plan_name = sanitize_input($_POST['plan_name']);
        $plan_price = floatval($_POST['plan_price']);
        $plan_duration = intval($_POST['plan_duration']);
        $fee_type = sanitize_input($_POST['fee_type']);
        $per_visit_fee = ($fee_type == 'per_visit') ? floatval($_POST['per_visit_fee']) : 0.00;
        
        $insert_plan = $conn->prepare("INSERT INTO membership_plans (membership_name, membership_price, membership_duration_days, fee_type, per_visit_fee) VALUES (?, ?, ?, ?, ?)");
        $insert_plan->bind_param("sdisd", $plan_name, $plan_price, $plan_duration, $fee_type, $per_visit_fee);
        
        if ($insert_plan->execute()) {
            log_activity('Create Plan', 'New membership plan created: ' . $plan_name, $_SESSION['admin_id']);
            header("Location: memberships.php?success=plan_created");
            exit;
        } else {
            $error = 'Failed to create plan!';
        }
    }
    
    if ($_POST['action'] == 'edit_plan') {
        $plan_id = intval($_POST['plan_id']);
        $plan_name = sanitize_input($_POST['plan_name']);
        $plan_price = floatval($_POST['plan_price']);
        $plan_duration = intval($_POST['plan_duration']);
        $fee_type = sanitize_input($_POST['fee_type']);
        $per_visit_fee = ($fee_type == 'per_visit') ? floatval($_POST['per_visit_fee']) : 0.00;
        
        $update_plan = $conn->prepare("UPDATE membership_plans SET membership_name = ?, membership_price = ?, membership_duration_days = ?, fee_type = ?, per_visit_fee = ? WHERE id = ?");
        $update_plan->bind_param("sdisdi", $plan_name, $plan_price, $plan_duration, $fee_type, $per_visit_fee, $plan_id);
        
        if ($update_plan->execute()) {
            log_activity('Edit Plan', 'Membership plan updated: ' . $plan_name, $_SESSION['admin_id']);
            header("Location: memberships.php?success=plan_updated");
            exit;
        } else {
            $error = 'Failed to update plan!';
        }
    }
    
    if ($_POST['action'] == 'delete_plan') {
        $plan_id = intval($_POST['plan_id']);
        
        $check_usage = $conn->prepare("SELECT COUNT(*) as count FROM membership WHERE membership_plan_id = ? AND status = 'Active'");
        $check_usage->bind_param("i", $plan_id);
        $check_usage->execute();
        $usage_result = $check_usage->get_result()->fetch_assoc();
        
        if ($usage_result['count'] > 0) {
            header("Location: memberships.php?error=plan_in_use");
            exit;
        } else {
            $delete_plan = $conn->prepare("DELETE FROM membership_plans WHERE id = ?");
            $delete_plan->bind_param("i", $plan_id);
            
            if ($delete_plan->execute()) {
                log_activity('Delete Plan', 'Membership plan deleted', $_SESSION['admin_id']);
                header("Location: memberships.php?success=plan_deleted");
                exit;
            } else {
                $error = 'Failed to delete plan!';
            }
        }
    }
    
    if ($_POST['action'] == 'assign') {
        $member_id = intval($_POST['member_id']);
        $plan_id = intval($_POST['plan_id']);
        $start_date = sanitize_input($_POST['start_date']);
        
        $plan_query = $conn->prepare("SELECT * FROM membership_plans WHERE id = ?");
        $plan_query->bind_param("i", $plan_id);
        $plan_query->execute();
        $plan = $plan_query->get_result()->fetch_assoc();
        
        if ($plan) {
            $end_date = date('Y-m-d', strtotime($start_date . ' + ' . $plan['membership_duration_days'] . ' days'));
            
            $check_query = $conn->prepare("SELECT * FROM membership WHERE member_id = ? AND status = 'Active'");
            $check_query->bind_param("i", $member_id);
            $check_query->execute();
            
            if ($check_query->get_result()->num_rows > 0) {
                $error = 'Member already has an active membership!';
            } else {
                $insert_query = $conn->prepare("INSERT INTO membership (member_id, membership_plan_id, start_date, end_date, status) VALUES (?, ?, ?, ?, 'Active')");
                $insert_query->bind_param("iiss", $member_id, $plan_id, $start_date, $end_date);
                
                if ($insert_query->execute()) {
                    $member_query = $conn->prepare("SELECT first_name, last_name FROM member WHERE id = ?");
                    $member_query->bind_param("i", $member_id);
                    $member_query->execute();
                    $member = $member_query->get_result()->fetch_assoc();
                    
                    log_activity('Assign Membership', $member['first_name'] . ' ' . $member['last_name'] . ' subscribed to ' . $plan['membership_name'], $_SESSION['admin_id']);
                    $success = true;
                }
            }
        } else {
            $error = 'Invalid membership plan!';
        }
    }
    
    if ($_POST['action'] == 'renew') {
        $membership_id = intval($_POST['membership_id']);
        $extend_days = intval($_POST['extend_days']);
        
        $membership_query = $conn->prepare("SELECT * FROM membership WHERE id = ?");
        $membership_query->bind_param("i", $membership_id);
        $membership_query->execute();
        $membership = $membership_query->get_result()->fetch_assoc();
        
        if ($membership) {
            $current_end = $membership['end_date'];
            $new_end_date = date('Y-m-d', strtotime($current_end . ' + ' . $extend_days . ' days'));
            
            $update_query = $conn->prepare("UPDATE membership SET end_date = ?, status = 'Active' WHERE id = ?");
            $update_query->bind_param("si", $new_end_date, $membership_id);
            
            if ($update_query->execute()) {
                log_activity('Renew Membership', 'Membership renewed for ' . $extend_days . ' days', $_SESSION['admin_id']);
                $success = true;
            } else {
                $error = 'Failed to renew membership!';
            }
        }
    }
    
    if ($_POST['action'] == 'cancel') {
        $membership_id = intval($_POST['membership_id']);
        
        $cancel_query = $conn->prepare("UPDATE membership SET status = 'Cancelled' WHERE id = ?");
        $cancel_query->bind_param("i", $membership_id);
        
        if ($cancel_query->execute()) {
            log_activity('Cancel Membership', 'Membership cancelled', $_SESSION['admin_id']);
            $success = true;
        } else {
            $error = 'Failed to cancel membership!';
        }
    }
    
    if ($success) {
        header("Location: memberships.php?success=1");
        exit;
    }
}

$page_title = "Membership Management";
$current_page = "memberships.php";

include '../includes/header.php';
?>
    
    <style>
        /* =============================================
           WOLF PALOMAR FITNESS GYM — COLOR PALETTE
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

        .main-content {
            padding-bottom: 120px !important;
        }

        /* Page Headings */
        .main-content h1 {
            color: var(--color-white);
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .main-content h1 i,
        .main-content h2 i,
        .main-content h3 i {
            color: var(--color-primary);
        }

        .main-content h2 {
            color: var(--color-white);
            font-size: 1.2rem;
            font-weight: 600;
        }

        .main-content h3 {
            color: var(--color-white);
            font-size: 1.1rem;
            font-weight: 600;
        }

        /* ==========================================
           PLAN CARDS
           ========================================== */
        .membership-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .plan-card {
            background: var(--color-surface);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid var(--color-border);
            transition: all 0.3s ease;
            position: relative;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
        }
        
        .plan-card:hover {
            border-color: rgba(204, 28, 28, 0.4);
            transform: translateY(-5px);
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.5), 0 0 20px rgba(204, 28, 28, 0.1);
        }

        /* Plan edit/delete buttons */
        .plan-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 8px;
        }
        
        .plan-action-btn {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .btn-edit-plan {
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            border: 1px solid rgba(26, 58, 143, 0.3);
        }
        
        .btn-edit-plan:hover {
            background: rgba(26, 58, 143, 0.35);
            transform: scale(1.1);
        }
        
        .btn-delete-plan {
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.25);
        }
        
        .btn-delete-plan:hover {
            background: rgba(204, 28, 28, 0.28);
            transform: scale(1.1);
        }
        
        .plan-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .plan-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, rgba(204, 28, 28, 0.25), rgba(204, 28, 28, 0.08));
            border: 2px solid rgba(204, 28, 28, 0.35);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 26px;
            color: var(--color-primary);
        }
        
        .plan-name {
            font-size: 20px;
            font-weight: bold;
            color: var(--color-white);
            margin-bottom: 5px;
        }
        
        .plan-duration {
            color: var(--color-muted);
            font-size: 14px;
        }
        
        .plan-price {
            font-size: 36px;
            font-weight: bold;
            color: #FF5555;
            text-align: center;
            margin: 20px 0;
        }
        
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .plan-features li {
            padding: 10px 0;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--color-text);
            font-size: 0.9rem;
        }

        .plan-features li:last-child {
            border-bottom: none;
        }
        
        .plan-features i {
            color: var(--color-primary);
        }

        .plan-card .btn-assign {
            width: 100%;
            padding: 12px;
            background: var(--color-primary);
            color: var(--color-white);
            border: none;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .plan-card .btn-assign:hover {
            background: var(--color-primary-dk);
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(204, 28, 28, 0.4);
        }

        .add-plan-card {
            background: rgba(204, 28, 28, 0.05);
            border: 2px dashed rgba(204, 28, 28, 0.4);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            min-height: 280px;
            transition: all 0.3s ease;
        }
        
        .add-plan-card:hover {
            background: rgba(204, 28, 28, 0.1);
            border-color: rgba(204, 28, 28, 0.7);
            transform: translateY(-5px);
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.4), 0 0 20px rgba(204, 28, 28, 0.1);
        }
        
        .add-plan-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(204, 28, 28, 0.2), rgba(204, 28, 28, 0.05));
            border: 2px solid rgba(204, 28, 28, 0.4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: var(--color-primary);
            margin-bottom: 15px;
        }

        .add-plan-card h3 {
            color: var(--color-primary) !important;
        }

        /* ==========================================
           FEE TYPE BADGES
           ========================================== */
        .fee-type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .fee-unlimited {
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.3);
        }
        
        .fee-per-visit {
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            border: 1px solid rgba(26, 58, 143, 0.35);
        }

        /* Status badges */
        .status-active {
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            border: 1px solid rgba(204, 28, 28, 0.3);
            display: inline-block;
        }
        
        .status-expiring {
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            border: 1px solid rgba(26, 58, 143, 0.35);
            display: inline-block;
        }

        .days-ok    { color: var(--color-muted); }
        .days-warn  { color: #4A7AFF; }

        /* Renew button */
        .btn-renew {
            padding: 8px 15px;
            font-size: 12px;
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            border: 1px solid rgba(26, 58, 143, 0.35);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-renew:hover {
            background: rgba(26, 58, 143, 0.35);
            transform: translateY(-1px);
        }

        /* Table avatar */
        .table-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #CC1C1C, #A01515);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 2px solid rgba(204, 28, 28, 0.3);
            overflow: hidden;
        }

        .table-avatar i {
            color: var(--color-white);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 50px;
            color: var(--color-muted);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        /* ==========================================
           VIEW ACTIVE MEMBERSHIPS BUTTON
           ========================================== */
        .view-active-btn:hover .view-active-chevron {
            transform: translateX(4px);
            color: var(--color-primary);
        }

        .view-active-btn {
            width: 100%;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 16px;
            padding: 20px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0;
            margin-bottom: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.4);
            text-align: left;
        }

        .view-active-btn:hover {
            border-color: rgba(204, 28, 28, 0.5);
            background: var(--color-surface-2);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.5), 0 0 18px rgba(204,28,28,0.1);
        }

        .view-active-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .view-active-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, rgba(204,28,28,0.22), rgba(204,28,28,0.06));
            border: 1px solid rgba(204,28,28,0.35);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: var(--color-primary);
            flex-shrink: 0;
        }

        .view-active-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--color-white);
            letter-spacing: 0.5px;
        }

        .view-active-sub {
            font-size: 0.78rem;
            color: var(--color-muted);
            margin-top: 3px;
        }

        .view-active-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .view-active-count {
            font-size: 2rem;
            font-weight: 800;
            color: var(--color-primary);
            line-height: 1;
        }

        .expiring-badge {
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            border: 1px solid rgba(26,58,143,0.35);
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            white-space: nowrap;
        }

        .view-active-chevron {
            color: var(--color-muted);
            font-size: 14px;
            transition: transform 0.3s ease;
        }

        /* ==========================================
           FORM MODALS
           ========================================== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 16px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.7);
            color: var(--color-text);
        }

        .modal-content h2 {
            color: var(--color-white);
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .modal-content h2 i {
            color: var(--color-primary);
        }

        .modal-content .form-label,
        .modal-content label.form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--color-muted);
            margin-bottom: 8px;
        }

        .modal-content .form-control,
        .modal-content input[type="text"],
        .modal-content input[type="number"],
        .modal-content input[type="date"],
        .modal-content select {
            width: 100%;
            background: var(--color-surface-2);
            border: 1px solid var(--color-border);
            color: var(--color-white);
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box;
        }

        .modal-content .form-control:focus,
        .modal-content input:focus,
        .modal-content select:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(204, 28, 28, 0.15);
        }

        .modal-content .form-control[readonly],
        .modal-content input[readonly] {
            background: #111111;
            color: var(--color-muted);
            cursor: default;
        }

        .modal-content select option {
            background: var(--color-surface-2);
            color: var(--color-white);
        }

        .modal-content small {
            color: var(--color-muted);
            font-size: 12px;
        }

        .modal-content .form-group {
            margin-bottom: 20px;
        }

        .modal-content .btn-modal-primary {
            flex: 1;
            padding: 12px 20px;
            background: var(--color-primary);
            color: var(--color-white);
            border: none;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .modal-content .btn-modal-primary:hover {
            background: var(--color-primary-dk);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(204, 28, 28, 0.4);
        }

        .modal-content .btn-modal-secondary {
            flex: 1;
            padding: 12px 20px;
            background: var(--color-surface-2);
            color: var(--color-text);
            border: 1px solid var(--color-border);
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .modal-content .btn-modal-secondary:hover {
            background: #252525;
            border-color: rgba(204, 28, 28, 0.3);
        }

        /* ==========================================
           ALERT MODALS
           ========================================== */
        .alert-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            z-index: 10002;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }

        .alert-modal.active {
            display: flex;
        }

        .alert-modal-content {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 16px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.04);
            animation: slideUp 0.3s ease;
            color: var(--color-text);
        }

        .alert-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .alert-icon {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
        }

        .alert-icon.success {
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            border: 1px solid rgba(26, 58, 143, 0.35);
        }

        .alert-icon.error {
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.3);
        }

        .alert-icon.warning {
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.3);
        }

        .alert-title h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: var(--color-white);
        }

        .alert-body {
            margin-bottom: 25px;
            color: var(--color-text);
            line-height: 1.6;
            font-size: 15px;
        }

        .alert-body strong {
            color: var(--color-white);
        }

        .alert-body .danger-note {
            color: #FF5555;
            margin-top: 8px;
            font-size: 0.875rem;
        }

        .alert-footer {
            display: flex;
            gap: 10px;
        }

        .alert-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            letter-spacing: 0.3px;
        }

        .alert-btn.success {
            background: var(--color-navy);
            color: var(--color-white);
            border: 1px solid rgba(26, 58, 143, 0.5);
        }

        .alert-btn.success:hover {
            background: #243FA0;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(26, 58, 143, 0.4);
        }

        .alert-btn.danger {
            background: var(--color-primary);
            color: var(--color-white);
            border: 1px solid rgba(204, 28, 28, 0.5);
        }

        .alert-btn.danger:hover {
            background: var(--color-primary-dk);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(204, 28, 28, 0.4);
        }

        .alert-btn.secondary {
            background: var(--color-surface-2);
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }

        .alert-btn.secondary:hover {
            background: #252525;
            border-color: rgba(204, 28, 28, 0.3);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
        
        /* ==========================================
           RESPONSIVE
           ========================================== */
        @media (max-width: 768px) {
            .main-content { padding-bottom: 140px !important; }
            .membership-grid { grid-template-columns: 1fr; gap: 15px; }
            .plan-card { padding: 20px; }
            .plan-price { font-size: 32px; }
            .modal-content { padding: 20px; max-width: 95%; }
            .drawer-body { padding: 0 15px 40px; }
            .drawer-header { padding: 16px 15px; }
            .drawer-stats { gap: 8px; }
            .drawer-stat-number { font-size: 1.3rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding-bottom: 150px !important; }
            .plan-icon { width: 50px; height: 50px; font-size: 22px; }
            .plan-name { font-size: 18px; }
            .plan-price { font-size: 28px; }
            .add-plan-icon { width: 65px; height: 65px; font-size: 28px; }
            .member-dates { display: none; }
            .view-active-btn { padding: 16px; }
            .view-active-count { font-size: 1.6rem; }
        }
    </style>
    
    <div class="main-content">
        
        <h1 style="margin-bottom: 25px;">
            <i class="fas fa-id-card-alt"></i> MEMBERSHIP MANAGEMENT
        </h1>
        
        <!-- Membership Plans -->
        <h2 style="margin-bottom: 20px;">
            <i class="fas fa-th-large"></i> Membership Plans
            <span style="font-size: 13px; color: var(--color-muted); font-weight: normal; margin-left: 10px;">
                (Click + to add new plan)
            </span>
        </h2>
        
        <!-- =============================================
             VIEW ACTIVE MEMBERSHIPS BUTTON
             ============================================= -->
        <?php
        $active_total = $active_memberships->num_rows;
        $expiring_count = 0;
        $active_memberships->data_seek(0);
        while ($m = $active_memberships->fetch_assoc()) {
            $dr = ceil((strtotime($m['end_date']) - time()) / (60 * 60 * 24));
            if ($dr <= 7) $expiring_count++;
        }
        ?>
        <a href="active_memberships.php" class="view-active-btn" style="text-decoration:none;">
            <div class="view-active-left">
                <div class="view-active-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div class="view-active-title">Active Memberships</div>
                    <div class="view-active-sub">Tap to view all enrolled members</div>
                </div>
            </div>
            <div class="view-active-right">
                <div class="view-active-count"><?php echo $active_total; ?></div>
                <?php if ($expiring_count > 0): ?>
                    <span class="expiring-badge"><?php echo $expiring_count; ?> expiring</span>
                <?php endif; ?>
                <i class="fas fa-chevron-right view-active-chevron"></i>
            </div>
        </a>

        <div class="membership-grid">
            <!-- Add New Plan Card -->
            <div class="plan-card add-plan-card" onclick="openCreatePlanModal()">
                <div class="add-plan-icon">
                    <i class="fas fa-plus"></i>
                </div>
                <h3 style="margin: 0;">Add New Plan</h3>
            </div>
            
            <?php 
            $plans_result->data_seek(0);
            while($plan = $plans_result->fetch_assoc()): 
                $fee_type = isset($plan['fee_type']) ? $plan['fee_type'] : 'unlimited';
                $per_visit_fee = isset($plan['per_visit_fee']) ? $plan['per_visit_fee'] : 0;
            ?>
                <div class="plan-card">
                    <div class="plan-actions">
                        <button class="plan-action-btn btn-edit-plan" onclick='openEditPlanModal(<?php echo json_encode([
                            "id" => $plan["id"],
                            "name" => $plan["membership_name"],
                            "price" => $plan["membership_price"],
                            "duration" => $plan["membership_duration_days"],
                            "fee_type" => $fee_type,
                            "per_visit_fee" => $per_visit_fee
                        ]); ?>)' title="Edit Plan">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="plan-action-btn btn-delete-plan" onclick="confirmDeletePlan(<?php echo $plan['id']; ?>, '<?php echo addslashes($plan['membership_name']); ?>')" title="Delete Plan">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    
                    <div class="plan-header">
                        <div class="plan-icon">
                            <i class="fas fa-crown"></i>
                        </div>
                        <div class="plan-name"><?php echo $plan['membership_name']; ?></div>
                        <div class="plan-duration"><?php echo $plan['membership_duration_days']; ?> Days</div>
                        <span class="fee-type-badge <?php echo $fee_type == 'unlimited' ? 'fee-unlimited' : 'fee-per-visit'; ?>">
                            <?php echo $fee_type == 'unlimited' ? '∞ UNLIMITED' : '₱' . number_format($per_visit_fee, 0) . ' PER VISIT'; ?>
                        </span>
                    </div>
                    
                    <div class="plan-price">
                        ₱<?php echo number_format($plan['membership_price'], 2); ?>
                    </div>
                    
                    <ul class="plan-features">
                        <?php if ($fee_type == 'unlimited'): ?>
                            <li><i class="fas fa-check"></i> Unlimited Gym Access</li>
                            <li><i class="fas fa-check"></i> No Daily Fees (₱0)</li>
                        <?php else: ?>
                            <li><i class="fas fa-check"></i> Gym Access for <?php echo $plan['membership_duration_days']; ?> Days</li>
                            <li><i class="fas fa-check"></i> ₱<?php echo number_format($per_visit_fee, 0); ?> per visit</li>
                            <li><i class="fas fa-check"></i> Discounted rate</li>
                        <?php endif; ?>
                        <li><i class="fas fa-check"></i> Free Equipment Use</li>
                        <li><i class="fas fa-check"></i> <?php echo $plan['membership_duration_days']; ?> Days Validity</li>
                    </ul>
                    
                    <button class="btn-assign" onclick="openAssignModal(<?php echo $plan['id']; ?>, '<?php echo addslashes($plan['membership_name']); ?>', <?php echo $plan['membership_price']; ?>, <?php echo $plan['membership_duration_days']; ?>)">
                        <i class="fas fa-user-plus"></i> Assign to Member
                    </button>
                </div>
            <?php endwhile; ?>
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
                <p id="successMessage"></p>
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
                <p id="errorMessage"></p>
            </div>
            <div class="alert-footer">
                <button class="alert-btn secondary" onclick="closeErrorModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Delete Plan Confirmation Modal -->
    <div class="alert-modal" id="deletePlanModal">
        <div class="alert-modal-content">
            <div class="alert-header">
                <div class="alert-icon warning">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <div class="alert-title">
                    <h3>Delete Plan</h3>
                </div>
            </div>
            <div class="alert-body">
                <p>Are you sure you want to delete <strong id="deletePlanName"></strong>?</p>
                <p class="danger-note"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone!</p>
            </div>
            <div class="alert-footer">
                <button class="alert-btn secondary" onclick="closeDeletePlanModal()">Cancel</button>
                <button class="alert-btn danger" onclick="proceedDeletePlan()">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Plan Form (hidden) -->
    <form method="POST" id="deletePlanForm" style="display: none;">
        <input type="hidden" name="action" value="delete_plan">
        <input type="hidden" name="plan_id" id="delete_plan_id">
    </form>
    
    <!-- Create Plan Modal -->
    <div class="modal" id="createPlanModal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">
                <i class="fas fa-plus-circle"></i> Create New Plan
            </h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_plan">
                
                <div class="form-group">
                    <label class="form-label">Plan Name *</label>
                    <input type="text" name="plan_name" class="form-control" placeholder="e.g., Monthly Membership" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Enrollment Fee (₱) *</label>
                    <input type="number" name="plan_price" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                    <small>One-time payment to activate membership</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Duration (Days) *</label>
                    <input type="number" name="plan_duration" class="form-control" min="1" placeholder="30" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Access Type *</label>
                    <select name="fee_type" id="create_fee_type" class="form-control" onchange="togglePerVisitFee('create')" required>
                        <option value="unlimited">Unlimited Access (₱0 per visit)</option>
                        <option value="per_visit">Pay Per Visit</option>
                    </select>
                </div>
                
                <div class="form-group" id="create_per_visit_fee_group" style="display: none;">
                    <label class="form-label">Fee Per Visit (₱) *</label>
                    <input type="number" name="per_visit_fee" id="create_per_visit_fee" class="form-control" step="0.01" min="0" placeholder="60.00">
                    <small>Amount charged each time member checks in</small>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="submit" class="btn-modal-primary">
                        <i class="fas fa-check"></i> Create Plan
                    </button>
                    <button type="button" class="btn-modal-secondary" onclick="closeModal('createPlanModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Plan Modal -->
    <div class="modal" id="editPlanModal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">
                <i class="fas fa-edit"></i> Edit Plan
            </h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="edit_plan">
                <input type="hidden" name="plan_id" id="edit_plan_id">
                
                <div class="form-group">
                    <label class="form-label">Plan Name *</label>
                    <input type="text" name="plan_name" id="edit_plan_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Enrollment Fee (₱) *</label>
                    <input type="number" name="plan_price" id="edit_plan_price" class="form-control" step="0.01" min="0" required>
                    <small>One-time payment to activate membership</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Duration (Days) *</label>
                    <input type="number" name="plan_duration" id="edit_plan_duration" class="form-control" min="1" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Access Type *</label>
                    <select name="fee_type" id="edit_fee_type" class="form-control" onchange="togglePerVisitFee('edit')" required>
                        <option value="unlimited">Unlimited Access (₱0 per visit)</option>
                        <option value="per_visit">Pay Per Visit</option>
                    </select>
                </div>
                
                <div class="form-group" id="edit_per_visit_fee_group" style="display: none;">
                    <label class="form-label">Fee Per Visit (₱) *</label>
                    <input type="number" name="per_visit_fee" id="edit_per_visit_fee" class="form-control" step="0.01" min="0" placeholder="60.00">
                    <small>Amount charged each time member checks in</small>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="submit" class="btn-modal-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <button type="button" class="btn-modal-secondary" onclick="closeModal('editPlanModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Assign Membership Modal -->
    <div class="modal" id="assignModal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">
                <i class="fas fa-user-plus"></i> Assign Membership
            </h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="plan_id" id="assign_plan_id">
                
                <div class="form-group">
                    <label class="form-label">Selected Plan</label>
                    <input type="text" class="form-control" id="assign_plan_name" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Price</label>
                    <input type="text" class="form-control" id="assign_plan_price" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Select Member *</label>
                    <select name="member_id" class="form-control" required>
                        <option value="">Choose a member</option>
                        <?php 
                        $members_result->data_seek(0);
                        while($member = $members_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo $member['first_name'] . ' ' . $member['last_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Start Date *</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">End Date (Auto-calculated)</label>
                    <input type="text" class="form-control" id="assign_end_date" readonly>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="submit" class="btn-modal-primary">
                        <i class="fas fa-check"></i> Assign
                    </button>
                    <button type="button" class="btn-modal-secondary" onclick="closeModal('assignModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Renew Membership Modal -->
    <div class="modal" id="renewModal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">
                <i class="fas fa-redo"></i> Renew Membership
            </h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="renew">
                <input type="hidden" name="membership_id" id="renew_membership_id">
                
                <div class="form-group">
                    <label class="form-label">Member</label>
                    <input type="text" class="form-control" id="renew_member_name" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Extend by (days) *</label>
                    <select name="extend_days" class="form-control" required>
                        <option value="">Choose extension period</option>
                        <?php 
                        $renew_plans_result->data_seek(0);
                        while($renew_plan = $renew_plans_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $renew_plan['membership_duration_days']; ?>">
                                <?php echo $renew_plan['membership_duration_days']; ?> Days (<?php echo $renew_plan['membership_name']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small style="margin-top: 5px; display: block;">
                        <i class="fas fa-info-circle"></i> Based on your available membership plans
                    </small>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="submit" class="btn-modal-primary">
                        <i class="fas fa-check"></i> Renew
                    </button>
                    <button type="button" class="btn-modal-secondary" onclick="closeModal('renewModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/bottom-nav.php'; ?>
    
    <?php 
    $extra_scripts = "
    <script>
        let planDuration = 0;
        let deletePlanId = null;

        /* ---- Drawer ---- */
        function openActiveMembershipsDrawer() {} // unused, kept for safety
        function closeActiveMembershipsDrawer() {}

        /* ---- Per-visit fee toggle ---- */
        function togglePerVisitFee(mode) {
            const feeType = document.getElementById(mode + '_fee_type').value;
            const perVisitGroup = document.getElementById(mode + '_per_visit_fee_group');
            const perVisitInput = document.getElementById(mode + '_per_visit_fee');
            if (feeType === 'per_visit') {
                perVisitGroup.style.display = 'block';
                perVisitInput.required = true;
            } else {
                perVisitGroup.style.display = 'none';
                perVisitInput.required = false;
                perVisitInput.value = '0.00';
            }
        }

        /* ---- Alert modals ---- */
        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }

        function closeErrorModal() {
            document.getElementById('errorModal').classList.remove('active');
        }

        function showSuccessModal(message) {
            document.getElementById('successMessage').textContent = message;
            document.getElementById('successModal').classList.add('active');
        }

        function showErrorModal(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('errorModal').classList.add('active');
        }

        function confirmDeletePlan(planId, planName) {
            deletePlanId = planId;
            document.getElementById('deletePlanName').textContent = planName;
            document.getElementById('deletePlanModal').classList.add('active');
        }

        function closeDeletePlanModal() {
            document.getElementById('deletePlanModal').classList.remove('active');
            deletePlanId = null;
        }

        function proceedDeletePlan() {
            if (deletePlanId) {
                document.getElementById('delete_plan_id').value = deletePlanId;
                document.getElementById('deletePlanForm').submit();
            }
        }

        /* ---- Plan modals ---- */
        function openCreatePlanModal() {
            document.getElementById('createPlanModal').classList.add('active');
        }
        
        function openEditPlanModal(planData) {
            document.getElementById('edit_plan_id').value = planData.id;
            document.getElementById('edit_plan_name').value = planData.name;
            document.getElementById('edit_plan_price').value = planData.price;
            document.getElementById('edit_plan_duration').value = planData.duration;
            document.getElementById('edit_fee_type').value = planData.fee_type || 'unlimited';
            document.getElementById('edit_per_visit_fee').value = planData.per_visit_fee || 0;
            togglePerVisitFee('edit');
            document.getElementById('editPlanModal').classList.add('active');
        }
        
        function openAssignModal(planId, planName, planPrice, duration) {
            planDuration = duration;
            document.getElementById('assign_plan_id').value = planId;
            document.getElementById('assign_plan_name').value = planName;
            document.getElementById('assign_plan_price').value = '₱' + planPrice.toLocaleString();
            const startDate = document.querySelector('#assignModal input[name=\"start_date\"]').value || new Date().toISOString().split('T')[0];
            calculateEndDate(startDate);
            document.getElementById('assignModal').classList.add('active');
        }
        
        function openRenewModal(membershipId, memberName) {
            document.getElementById('renew_membership_id').value = membershipId;
            document.getElementById('renew_member_name').value = memberName;
            document.getElementById('renewModal').classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function calculateEndDate(startDate) {
            const start = new Date(startDate);
            const end = new Date(start);
            end.setDate(end.getDate() + planDuration);
            const formattedEnd = end.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            document.getElementById('assign_end_date').value = formattedEnd;
        }

        " . (isset($_GET['success']) ? "
        const successType = '" . $_GET['success'] . "';
        let successMsg = 'Membership updated successfully!';
        if (successType === 'plan_created') successMsg = 'Membership plan created successfully!';
        if (successType === 'plan_updated') successMsg = 'Membership plan updated successfully!';
        if (successType === 'plan_deleted') successMsg = 'Membership plan deleted successfully!';
        showSuccessModal(successMsg);
        " : "") . "

        " . (isset($_GET['error']) ? "
        const errorType = '" . $_GET['error'] . "';
        const errorMsg = errorType === 'plan_in_use' ? 'Cannot delete plan — it is currently being used by active memberships!' : 'An error occurred';
        showErrorModal(errorMsg);
        " : "") . "
        
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.querySelector('#assignModal input[name=\"start_date\"]');
            if (startDateInput) {
                startDateInput.addEventListener('change', function() {
                    calculateEndDate(this.value);
                });
            }
            
            // Close modals on overlay click
            document.querySelectorAll('.modal, .alert-modal').forEach(function(modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) this.classList.remove('active');
                });
            });
        });
    </script>
    ";
    
    include '../includes/footer.php'; 
    ?>