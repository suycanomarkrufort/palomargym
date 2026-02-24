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

$success = false;
$error = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $member_id = intval($_POST['member_id']);
    $action = $_POST['action']; // check-in or check-out
    
    $today = date('Y-m-d');
    $current_time = date('H:i:s');
    
    // Get member info
    $member_query = $conn->prepare("SELECT * FROM member WHERE id = ?");
    $member_query->bind_param("i", $member_id);
    $member_query->execute();
    $member = $member_query->get_result()->fetch_assoc();
    
    if (!$member) {
        $error = 'Member not found!';
    } else {
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
        
        // Determine status and fee
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
        
        if ($action == 'check-in') {
            $check_query = $conn->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ? AND check_out_time IS NULL");
            $check_query->bind_param("is", $member_id, $today);
            $check_query->execute();
            
            if ($check_query->get_result()->num_rows > 0) {
                $error = 'Member is already checked in!';
            } else {
                $attendance_id = generate_id('ATT', 8);
                $insert_query = $conn->prepare("INSERT INTO attendance (attendance_id, user_id, user_type, date, check_in_time, member_status, fee_charged) VALUES (?, ?, 'Member', ?, ?, ?, ?)");
                $insert_query->bind_param("sisssd", $attendance_id, $member_id, $today, $current_time, $member_status, $fee);
                
                if ($insert_query->execute()) {
                    log_activity('Manual Check-in', $member['first_name'] . ' ' . $member['last_name'] . ' checked in manually', $_SESSION['admin_id']);
                    $success = true;
                    $success_message = $member['first_name'] . ' ' . $member['last_name'] . ' has been checked in successfully!';
                } else {
                    $error = 'Failed to record check-in';
                }
            }
        } else {
            $active_query = $conn->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ? AND check_out_time IS NULL ORDER BY check_in_time DESC LIMIT 1");
            $active_query->bind_param("is", $member_id, $today);
            $active_query->execute();
            $active = $active_query->get_result()->fetch_assoc();
            
            if (!$active) {
                $error = 'No active check-in found for this member!';
            } else {
                $update_query = $conn->prepare("UPDATE attendance SET check_out_time = ? WHERE id = ?");
                $update_query->bind_param("si", $current_time, $active['id']);
                
                if ($update_query->execute()) {
                    log_activity('Manual Check-out', $member['first_name'] . ' ' . $member['last_name'] . ' checked out manually', $_SESSION['admin_id']);
                    $success = true;
                    $success_message = $member['first_name'] . ' ' . $member['last_name'] . ' has been checked out successfully!';
                } else {
                    $error = 'Failed to record check-out';
                }
            }
        }
    }
}

// Get all members for selection
$members_query = "SELECT * FROM member ORDER BY first_name ASC";
$members_result = $conn->query($members_query);

// Set page title
$page_title = "Manual Entry";

// Include header
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
            background-color: #0A0A0A !important;
            color: #CCCCCC !important;
        }

        .manual-container {
            padding: 20px;
            padding-bottom: 100px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Back link — navy accent */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4A7AFF;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #FFFFFF;
            transform: translateX(-4px);
        }

        /* Main card — dark surface */
        .manual-card {
            background: #141414 !important;
            border: 1px solid #2A2A2A;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }

        .card-header {
            text-align: center;
            margin-bottom: 30px;
        }

        /* Card icon — red brand */
        .card-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #CC1C1C, #A01515);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            color: #FFFFFF;
            box-shadow: 0 6px 20px rgba(204, 28, 28, 0.4);
        }

        .card-title {
            font-size: 22px;
            font-weight: 800;
            color: #FFFFFF;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }

        .card-subtitle {
            color: #777777;
            font-size: 14px;
        }

        /* Form labels */
        .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #777777;
            display: block;
            margin-bottom: 8px;
        }

        /* Form controls */
        .form-control {
            width: 100%;
            background: #1C1C1C !important;
            border: 1px solid #2A2A2A !important;
            color: #FFFFFF !important;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: #CC1C1C !important;
            box-shadow: 0 0 0 3px rgba(204, 28, 28, 0.12) !important;
        }

        select.form-control option {
            background: #1C1C1C;
            color: #FFFFFF;
        }

        /* Action buttons — check-in / check-out selector */
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }

        .action-btn {
            padding: 15px;
            border: 2px solid #2A2A2A;
            background: #1C1C1C;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #777777;
            font-size: 0.9rem;
        }

        /* Active check-in — red */
        .action-btn.active {
            background: rgba(204, 28, 28, 0.15);
            border-color: #CC1C1C;
            color: #FF5555;
        }

        .action-btn:hover:not(.active) {
            border-color: rgba(204, 28, 28, 0.4);
            color: #CCCCCC;
        }

        /* Submit button */
        .btn-primary {
            background: #CC1C1C !important;
            color: #FFFFFF !important;
            border: none !important;
            border-radius: 10px;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            background: #A01515 !important;
            transform: translateY(-2px);
        }

        /* Form group spacing */
        .form-group {
            margin-bottom: 24px;
        }

        /* Alert Modals */
        .alert-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.75);
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

        .alert-footer { display: flex; gap: 10px; }

        .alert-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        /* Success OK — navy */
        .alert-btn.success {
            background: #1A3A8F;
            color: #FFFFFF;
        }

        .alert-btn.success:hover { background: #243FA0; }

        /* Cancel — dark */
        .alert-btn.secondary {
            background: #1C1C1C !important;
            color: #CCCCCC !important;
            border: 1px solid #2A2A2A !important;
        }

        .alert-btn.secondary:hover { background: #252525 !important; }

        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
    </style>
    
    <div class="manual-container">
        
        <a href="qr-scan.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Scanner
        </a>
        
        <div class="manual-card">
            
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-keyboard"></i>
                </div>
                <h2 class="card-title">MANUAL ENTRY</h2>
                <p class="card-subtitle">Record attendance without QR scanning</p>
            </div>
            
            <form method="POST">
                
                <div class="form-group">
                    <label class="form-label">SELECT MEMBER</label>
                    <select name="member_id" class="form-control" required>
                        <option value="">Choose a member...</option>
                        <?php while($member = $members_result->fetch_assoc()): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo $member['first_name'] . ' ' . $member['last_name']; ?> - <?php echo $member['member_id']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">ACTION</label>
                    <div class="action-buttons">
                        <label class="action-btn active">
                            <input type="radio" name="action" value="check-in" checked style="display: none;">
                            <i class="fas fa-sign-in-alt"></i> CHECK-IN
                        </label>
                        <label class="action-btn">
                            <input type="radio" name="action" value="check-out" style="display: none;">
                            <i class="fas fa-sign-out-alt"></i> CHECK-OUT
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 16px;">
                    <i class="fas fa-save"></i> RECORD ATTENDANCE
                </button>
                
            </form>
            
        </div>
        
    </div>

    <!-- Success Modal -->
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
                <p><?php echo $success_message; ?></p>
            </div>
            <div class="alert-footer">
                <button class="alert-btn success" onclick="closeSuccessModal()">
                    <i class="fas fa-check"></i> OK
                </button>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
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
                <p><?php echo $error; ?></p>
            </div>
            <div class="alert-footer">
                <button class="alert-btn secondary" onclick="closeErrorModal()">OK</button>
            </div>
        </div>
    </div>
    
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/bottom-nav.php'; ?>
    
    <?php 
    $extra_scripts = "
    <script>
        // Toggle action buttons
        const actionButtons = document.querySelectorAll('.action-btn');
        actionButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                actionButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });

        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }

        function closeErrorModal() {
            document.getElementById('errorModal').classList.remove('active');
        }

        // Show modals if there's a success or error
        " . ($success ? "document.getElementById('successModal').classList.add('active');" : "") . "
        " . ($error ? "document.getElementById('errorModal').classList.add('active');" : "") . "

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('alert-modal')) {
                closeSuccessModal();
                closeErrorModal();
            }
        });
    </script>
    ";
    
    include '../includes/footer.php'; 
    ?>