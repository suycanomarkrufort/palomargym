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
$guest_name_success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $guest_name = sanitize_input($_POST['guest_name']);
    $is_student = isset($_POST['is_student']) ? 1 : 0;
    
    $today = date('Y-m-d');
    $current_time = date('H:i:s');
    
    // Determine fee
    $member_status = $is_student ? 'Student' : 'Non-Member';
    $fee = $is_student ? get_setting('student_rate') : get_setting('non_member_rate');
    
    $attendance_id = generate_id('ATT', 8);
    
    // Insert walk-in attendance
    $insert_query = $conn->prepare("INSERT INTO attendance (attendance_id, user_id, user_type, guest_name, date, check_in_time, member_status, fee_charged) VALUES (?, NULL, 'Walk-in', ?, ?, ?, ?, ?)");
    $insert_query->bind_param("sssssd", $attendance_id, $guest_name, $today, $current_time, $member_status, $fee);
    
    if ($insert_query->execute()) {
        log_activity('Walk-in Entry', 'Guest walk-in recorded: ' . $guest_name . ' (' . $member_status . ')', get_user_id());
        $success = true;
        $guest_name_success = $guest_name;
    } else {
        $error = 'Failed to record walk-in entry: ' . $insert_query->error;
    }
}

// Set page title
$page_title = "Guest Walk-in";

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

        .walkin-container {
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
        .walkin-card {
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

        /* Rate info cards */
        .rate-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }

        .rate-card {
            background: #1C1C1C;
            border: 2px solid #2A2A2A;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
        }

        /* Active rate card — red highlight */
        .rate-card.active {
            background: rgba(204, 28, 28, 0.1);
            border-color: #CC1C1C;
        }

        .rate-label {
            font-size: 11px;
            color: #777777;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .rate-amount {
            font-size: 30px;
            font-weight: 800;
            color: #FFFFFF;
        }

        /* Rate type label — red accent */
        .rate-type {
            font-size: 12px;
            color: #CC1C1C;
            font-weight: 700;
            margin-top: 5px;
            letter-spacing: 0.5px;
        }

        /* Form */
        .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #777777;
            display: block;
            margin-bottom: 8px;
        }

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

        .form-control::placeholder { color: #777777; }

        .form-control:focus {
            border-color: #CC1C1C !important;
            box-shadow: 0 0 0 3px rgba(204, 28, 28, 0.12) !important;
        }

        /* Student checkbox card */
        .checkbox-card {
            background: #1C1C1C;
            border: 2px solid #2A2A2A;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #CCCCCC;
        }

        .checkbox-card:hover {
            border-color: rgba(204, 28, 28, 0.4);
        }

        .checkbox-card input[type="checkbox"] {
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: #CC1C1C;
        }

        .checkbox-card label {
            cursor: pointer;
            font-weight: 600;
            flex: 1;
            color: #CCCCCC;
        }

        /* Submit button — red */
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
        }

        .btn-primary:hover {
            background: #A01515 !important;
            transform: translateY(-2px);
        }

        .form-group { margin-bottom: 20px; }

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
            padding: 40px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            animation: slideUp 0.3s ease;
            text-align: center;
            color: #CCCCCC !important;
        }

        .alert-icon {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 44px;
            margin: 0 auto 20px;
        }

        /* Success icon — navy */
        .alert-icon.success {
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            border: 2px solid rgba(26, 58, 143, 0.3);
        }

        /* Error icon — red */
        .alert-icon.error {
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            border: 2px solid rgba(204, 28, 28, 0.3);
        }

        .alert-title {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 10px;
            color: #FFFFFF !important;
            letter-spacing: 0.5px;
        }

        .alert-body {
            color: #CCCCCC !important;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .alert-body strong { color: #FFFFFF !important; }

        .alert-btn {
            width: 100%;
            padding: 13px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        /* Add Another — red */
        .alert-btn.success {
            background: #CC1C1C;
            color: #FFFFFF;
        }

        .alert-btn.success:hover { background: #A01515; }

        /* Error OK — dark */
        .alert-btn.secondary {
            background: #1C1C1C !important;
            color: #CCCCCC !important;
            border: 1px solid #2A2A2A !important;
            margin-top: 10px;
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
    
    <div class="walkin-container">
        
        <a href="qr-scan.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Scanner
        </a>
        
        <div class="walkin-card">
            
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2 class="card-title">GUEST / WALK-IN</h2>
                <p class="card-subtitle">Quick entry for non-members</p>
            </div>
            
            <div class="rate-info">
                <div class="rate-card" id="non-member-rate">
                    <div class="rate-label">Regular Rate</div>
                    <div class="rate-amount">₱<?php echo number_format(get_setting('non_member_rate'), 0); ?></div>
                    <div class="rate-type">NON-MEMBER</div>
                </div>
                <div class="rate-card" id="student-rate">
                    <div class="rate-label">Discounted</div>
                    <div class="rate-amount">₱<?php echo number_format(get_setting('student_rate'), 0); ?></div>
                    <div class="rate-type">STUDENT</div>
                </div>
            </div>
            
            <form method="POST" id="walkinForm">
                
                <div class="form-group">
                    <label class="form-label">GUEST NAME</label>
                    <input type="text" name="guest_name" class="form-control" placeholder="Enter guest name..." required>
                </div>
                
                <div class="checkbox-card" onclick="toggleCheckbox()">
                    <input type="checkbox" name="is_student" id="is_student" onchange="updateRate()">
                    <label for="is_student">
                        <i class="fas fa-graduation-cap" style="color: #CC1C1C; margin-right: 6px;"></i> Guest is a STUDENT (₱<?php echo number_format(get_setting('student_rate'), 0); ?> rate)
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 16px;">
                    <i class="fas fa-check"></i> CHECK-IN GUEST (₱<span id="display-fee"><?php echo number_format(get_setting('non_member_rate'), 0); ?></span>)
                </button>
                
            </form>
            
        </div>
        
    </div>
    
    <!-- Success Modal -->
    <div class="alert-modal" id="successModal">
        <div class="alert-modal-content">
            <div class="alert-icon success">
                <i class="fas fa-check"></i>
            </div>
            <h2 class="alert-title">GUEST CHECKED IN!</h2>
            <p class="alert-body"><strong><?php echo htmlspecialchars($guest_name_success); ?></strong> has been checked in successfully</p>
            <button class="alert-btn success" onclick="addAnother()">
                <i class="fas fa-plus"></i> ADD ANOTHER GUEST
            </button>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="alert-modal" id="errorModal">
        <div class="alert-modal-content">
            <div class="alert-icon error">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h2 class="alert-title">Error</h2>
            <p class="alert-body"><?php echo $error; ?></p>
            <button class="alert-btn secondary" onclick="closeErrorModal()">OK</button>
        </div>
    </div>
    
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/bottom-nav.php'; ?>
    
    <?php 
    // Extra scripts for this page
    $extra_scripts = "
    <script>
        const studentRate = " . get_setting('student_rate') . ";
        const nonMemberRate = " . get_setting('non_member_rate') . ";
        
        function toggleCheckbox() {
            const checkbox = document.getElementById('is_student');
            checkbox.checked = !checkbox.checked;
            updateRate();
        }
        
        function updateRate() {
            const isStudent = document.getElementById('is_student').checked;
            const displayFee = document.getElementById('display-fee');
            const nonMemberCard = document.getElementById('non-member-rate');
            const studentCard = document.getElementById('student-rate');
            
            if (isStudent) {
                displayFee.textContent = studentRate.toLocaleString();
                nonMemberCard.classList.remove('active');
                studentCard.classList.add('active');
            } else {
                displayFee.textContent = nonMemberRate.toLocaleString();
                nonMemberCard.classList.add('active');
                studentCard.classList.remove('active');
            }
        }
        
        function addAnother() {
            document.getElementById('successModal').classList.remove('active');
            document.getElementById('walkinForm').reset();
            updateRate();
        }

        function closeErrorModal() {
            document.getElementById('errorModal').classList.remove('active');
        }
        
        // Initialize
        updateRate();
        
        // Show modals if there's a success or error
        " . ($success ? "document.getElementById('successModal').classList.add('active'); setTimeout(addAnother, 3000);" : "") . "
        " . ($error ? "document.getElementById('errorModal').classList.add('active');" : "") . "

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('alert-modal')) {
                addAnother();
                closeErrorModal();
            }
        });
    </script>
    ";
    
    include '../includes/footer.php'; 
    ?>