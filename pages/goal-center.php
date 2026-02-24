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

$success = false;
$error = '';

// Get current goals
$daily_goal = get_setting('daily_goal');
$weekly_goal = get_setting('weekly_goal') ?: 35000;
$monthly_goal = get_setting('monthly_goal') ?: 150000;

// Calculate goal percentage
$goal_percentage = ($daily_goal > 0) ? round(($today_revenue / $daily_goal) * 100) : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $daily_target   = floatval($_POST['daily_target']);
    $weekly_target  = floatval($_POST['weekly_target']);
    $monthly_target = floatval($_POST['monthly_target']);

    update_setting('daily_goal', $daily_target);

    $check_weekly = $conn->query("SELECT * FROM gym_settings WHERE setting_key = 'weekly_goal'");
    if ($check_weekly->num_rows == 0) {
        $conn->query("INSERT INTO gym_settings (setting_key, setting_value, description) VALUES ('weekly_goal', '$weekly_target', 'Weekly revenue goal')");
    } else {
        update_setting('weekly_goal', $weekly_target);
    }

    $check_monthly = $conn->query("SELECT * FROM gym_settings WHERE setting_key = 'monthly_goal'");
    if ($check_monthly->num_rows == 0) {
        $conn->query("INSERT INTO gym_settings (setting_key, setting_value, description) VALUES ('monthly_goal', '$monthly_target', 'Monthly revenue goal')");
    } else {
        update_setting('monthly_goal', $monthly_target);
    }

    log_activity('Update Goals', 'Revenue targets updated', $_SESSION['admin_id']);

    $success = true;
    $daily_goal   = $daily_target;
    $weekly_goal  = $weekly_target;
    $monthly_goal = $monthly_target;
    $goal_percentage = ($daily_goal > 0) ? round(($today_revenue / $daily_goal) * 100) : 0;
}

// Set page title
$page_title = "Goal Center";

// Include header
include '../includes/header.php';
?>
    
    <style>
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

        /* Unique Class Names (gc- prefix) to avoid sidebar conflict */
        .gc-container {
            min-height: 80vh; 
            padding: 24px 20px;
            padding-bottom: 130px;
        }

        .gc-back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--color-silver);
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 28px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            padding: 10px 18px;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 10px;
        }

        .gc-back-button i { color: var(--color-primary); }

        .gc-back-button:hover {
            color: var(--color-white);
            background: var(--color-surface-2);
            border-color: var(--color-primary);
        }

        .gc-card {
            background: var(--color-surface);
            border-radius: 18px;
            padding: 36px 32px;
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid var(--color-border);
            box-shadow: 0 4px 30px rgba(0,0,0,0.5);
        }

        .gc-header {
            margin-bottom: 36px;
            padding-bottom: 22px;
            border-bottom: 2px solid var(--color-primary);
        }

        .gc-title {
            font-size: 28px;
            font-weight: 900;
            color: var(--color-white);
            font-style: italic;
            letter-spacing: 1px;
            margin: 0;
        }

        .gc-title .highlight { color: var(--color-primary); }

        .gc-form-group { margin-bottom: 26px; }

        .gc-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--color-silver);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 10px;
        }

        .gc-label i { color: var(--color-primary); font-size: 13px; }

        .gc-input-wrapper { position: relative; }

        .gc-currency-symbol {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-muted);
            font-size: 20px;
            font-weight: 700;
            pointer-events: none;
            z-index: 1;
        }

        .gc-input {
            width: 100%;
            padding: 18px 20px 18px 48px;
            background: var(--color-surface-2);
            border: 1px solid var(--color-border);
            border-radius: 12px;
            font-size: 22px;
            font-weight: 700;
            color: var(--color-white);
            font-family: inherit;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .gc-input::placeholder { color: var(--color-muted); font-weight: 400; }

        .gc-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(204,28,28,0.12);
            background: #202020;
        }

        .gc-input::-webkit-inner-spin-button,
        .gc-input::-webkit-outer-spin-button { -webkit-appearance: none; }
        .gc-input[type=number] { -moz-appearance: textfield; }

        .gc-divider {
            border: none;
            border-top: 1px solid var(--color-border);
            margin: 4px 0 26px;
        }

        .gc-commit-button {
            width: 100%;
            padding: 18px;
            background: var(--color-primary);
            border: none;
            border-radius: 12px;
            color: #FFFFFF;
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            font-style: italic;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 4px 18px rgba(204,28,28,0.35);
            font-family: inherit;
        }

        .gc-commit-button:hover {
            background: var(--color-primary-dk);
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(204,28,28,0.5);
        }

        .gc-commit-button:active { transform: translateY(0); }

        /* Modal Styles */
        .alert-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.75);
            z-index: 10001;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }

        .alert-modal.active { display: flex; }

        .alert-modal-content {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 16px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.7);
            animation: slideUp 0.3s ease;
        }

        .alert-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 18px;
        }

        .alert-icon {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
        }

        .alert-icon.success {
            background: rgba(26,58,143,0.2);
            color: #4A7AFF;
            border: 1px solid rgba(26,58,143,0.3);
        }

        .alert-title h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: var(--color-white);
        }

        .alert-body {
            margin-bottom: 22px;
            color: var(--color-text);
            line-height: 1.6;
            font-size: 14px;
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
            font-family: inherit;
        }

        .alert-btn.success { background: var(--color-primary); color: #FFFFFF; }
        .alert-btn.success:hover { background: var(--color-primary-dk); }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }

        @media (max-width: 420px) {
            .gc-card  { padding: 22px 16px; }
            .gc-title { font-size: 22px; }
            .gc-input { font-size: 18px; }
        }
    </style>

    <!-- WRAP CONTENT IN MAIN-CONTENT DIV -->
    <div class="main-content">
        <div class="gc-container">

            <a href="<?php echo $base_url; ?>index.php" class="gc-back-button">
                <i class="fas fa-arrow-left"></i>
                BACK TO DASHBOARD
            </a>

            <div class="gc-card">

                <div class="gc-header">
                    <h1 class="gc-title">SYSTEM <span class="highlight">GROWTH GOALS</span></h1>
                </div>

                <form method="POST">

                    <div class="gc-form-group">
                        <label class="gc-label">
                            <i class="fas fa-sun"></i> TODAY REVENUE TARGET (PHP)
                        </label>
                        <div class="gc-input-wrapper">
                            <span class="gc-currency-symbol">₱</span>
                            <input type="number" name="daily_target" class="gc-input"
                                   value="<?php echo number_format($daily_goal, 0, '', ''); ?>"
                                   placeholder="5000" min="0" step="100" required>
                        </div>
                    </div>

                    <hr class="gc-divider">

                    <div class="gc-form-group">
                        <label class="gc-label">
                            <i class="fas fa-calendar-week"></i> WEEKLY REVENUE TARGET (PHP)
                        </label>
                        <div class="gc-input-wrapper">
                            <span class="gc-currency-symbol">₱</span>
                            <input type="number" name="weekly_target" class="gc-input"
                                   value="<?php echo number_format($weekly_goal, 0, '', ''); ?>"
                                   placeholder="35000" min="0" step="1000" required>
                        </div>
                    </div>

                    <hr class="gc-divider">

                    <div class="gc-form-group">
                        <label class="gc-label">
                            <i class="fas fa-calendar-alt"></i> MONTHLY REVENUE TARGET (PHP)
                        </label>
                        <div class="gc-input-wrapper">
                            <span class="gc-currency-symbol">₱</span>
                            <input type="number" name="monthly_target" class="gc-input"
                                   value="<?php echo number_format($monthly_goal, 0, '', ''); ?>"
                                   placeholder="150000" min="0" step="5000" required>
                        </div>
                    </div>

                    <button type="submit" class="gc-commit-button">
                        <i class="fas fa-bullseye"></i> &nbsp;COMMIT TARGETS
                    </button>

                </form>
            </div>
        </div>
    </div>
    <!-- END MAIN CONTENT -->

    <!-- INCLUDE SIDEBAR -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Success Modal -->
    <div class="alert-modal" id="successModal">
        <div class="alert-modal-content">
            <div class="alert-header">
                <div class="alert-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="alert-title"><h3>Targets Updated!</h3></div>
            </div>
            <div class="alert-body">
                <p>Revenue goals have been successfully committed.</p>
            </div>
            <div class="alert-footer">
                <button class="alert-btn success" onclick="closeSuccessModal()">
                    <i class="fas fa-check"></i> OK
                </button>
            </div>
        </div>
    </div>

    <?php include '../includes/bottom-nav.php'; ?>
    
    <?php 
    // Extra scripts for this page
    $extra_scripts = "
    <script>
        const inputs = document.querySelectorAll('.gc-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.value = this.value.replace(/,/g, '');
            });
            input.addEventListener('blur', function() {
                if (this.value) {
                    const val = parseFloat(this.value.replace(/,/g, ''));
                    if (!isNaN(val)) this.value = Math.round(val);
                }
            });
        });

        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }

        " . ($success ? "document.getElementById('successModal').classList.add('active');" : "") . "

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('alert-modal')) {
                closeSuccessModal();
            }
        });
    </script>
    ";
    
    include '../includes/footer.php'; 
    ?>