<?php
require_once '../config/database.php';

if (!is_logged_in()) {
    redirect('../login.php');
}

if (!is_admin()) {
    redirect('../index.php');
}

$user_id = get_user_id();
$is_staff_user = is_staff();
$table = $is_staff_user ? 'staff' : 'admin';

$user_query = $conn->prepare("SELECT * FROM $table WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$admin = $user_query->get_result()->fetch_assoc();

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_rates') {
        $non_member_rate = floatval($_POST['non_member_rate']);
        $student_rate = floatval($_POST['student_rate']);
        
        $update1 = $conn->prepare("UPDATE gym_settings SET setting_value = ? WHERE setting_key = 'non_member_rate'");
        $update1->bind_param("s", $non_member_rate);
        
        $update2 = $conn->prepare("UPDATE gym_settings SET setting_value = ? WHERE setting_key = 'student_rate'");
        $update2->bind_param("s", $student_rate);
        
        if ($update1->execute() && $update2->execute()) {
            log_activity('Update Walk-in Rates', 'Non-Member: ₱' . $non_member_rate . ', Student: ₱' . $student_rate, $_SESSION['admin_id']);
            $success = true;
        } else {
            $error = 'Failed to update rates!';
        }
    }
}

$non_member_rate = floatval(get_setting('non_member_rate'));
$student_rate = floatval(get_setting('student_rate'));

$today = date('Y-m-d');
$revenue_query_goal = $conn->prepare("SELECT COALESCE(SUM(fee_charged), 0) as total FROM attendance WHERE date = ?");
$revenue_query_goal->bind_param("s", $today);
$revenue_query_goal->execute();
$revenue_result = $revenue_query_goal->get_result()->fetch_assoc();
$today_revenue = $revenue_result['total'];

$daily_goal = get_setting('daily_goal');
$goal_percentage = ($daily_goal > 0) ? round(($today_revenue / $daily_goal) * 100) : 0;

$page_title = "Walk-in Rate Management";
$current_page = "walkin-rates.php";

include '../includes/header.php';
?>

<style>
    /* =============================================
       WOLF PALOMAR FITNESS GYM — COLOR PALETTE
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
        padding: 20px;
        padding-bottom: 120px;
    }

    .main-content h1 {
        color: var(--color-white);
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: 1px;
    }

    .main-content h1 i,
    .main-content h3 i {
        color: var(--color-primary);
    }

    .main-content h3 {
        color: var(--color-white);
        font-size: 1.1rem;
        font-weight: 600;
    }

    /* ==========================================
       RATE DISPLAY CARDS
       ========================================== */
    .rate-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .rate-card {
        background: var(--color-surface);
        border-radius: 20px;
        padding: 30px;
        border: 1px solid var(--color-border);
        text-align: center;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
    }

    /* Top accent bar — red gradient */
    .rate-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--color-primary), var(--color-navy));
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.4s ease;
    }

    .rate-card:hover {
        border-color: rgba(204, 28, 28, 0.4);
        transform: translateY(-4px);
        box-shadow: 0 10px 28px rgba(0, 0, 0, 0.5), 0 0 20px rgba(204, 28, 28, 0.1);
    }

    .rate-card:hover::before {
        transform: scaleX(1);
    }

    /* Rate icon — alternating red / navy */
    .rate-icon {
        width: 75px;
        height: 75px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 32px;
    }

    .rate-icon.non-member {
        background: linear-gradient(135deg, rgba(204, 28, 28, 0.25), rgba(204, 28, 28, 0.08));
        color: var(--color-primary);
        border: 2px solid rgba(204, 28, 28, 0.35);
    }

    .rate-icon.student {
        background: linear-gradient(135deg, rgba(26, 58, 143, 0.25), rgba(26, 58, 143, 0.08));
        color: #4A7AFF;
        border: 2px solid rgba(26, 58, 143, 0.35);
    }

    .rate-label {
        font-size: 0.75rem;
        color: var(--color-muted);
        text-transform: uppercase;
        letter-spacing: 1.2px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .rate-amount {
        font-size: 48px;
        font-weight: 700;
        color: #FF5555;
        margin-bottom: 8px;
        line-height: 1;
    }

    .rate-description {
        font-size: 13px;
        color: var(--color-muted);
    }

    /* ==========================================
       UPDATE FORM SECTION
       ========================================== */
    .update-section {
        background: var(--color-surface);
        border-radius: 20px;
        padding: 30px;
        border: 1px solid var(--color-border);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
        margin-bottom: 20px;
    }

    .rate-input-group {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .rate-input-box {
        background: var(--color-surface-2);
        padding: 20px;
        border-radius: 14px;
        border: 1px solid var(--color-border);
        transition: all 0.3s ease;
    }

    .rate-input-box:focus-within {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(204, 28, 28, 0.12);
        background: rgba(204, 28, 28, 0.04);
    }

    .input-label {
        font-size: 0.72rem;
        color: var(--color-muted);
        text-transform: uppercase;
        letter-spacing: 1.2px;
        font-weight: 600;
        margin-bottom: 12px;
        display: block;
    }

    .input-label i {
        color: var(--color-primary);
        margin-right: 5px;
    }

    .rate-input {
        width: 100%;
        font-size: 40px;
        font-weight: 700;
        border: none;
        background: transparent;
        color: var(--color-white);
        text-align: center;
        outline: none;
        box-sizing: border-box;
    }

    .rate-input::placeholder {
        color: var(--color-border);
    }

    /* Submit button */
    .btn-save {
        width: 100%;
        padding: 14px 20px;
        background: var(--color-primary);
        color: var(--color-white);
        border: none;
        border-radius: 12px;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: 0.5px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-save:hover {
        background: var(--color-primary-dk);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(204, 28, 28, 0.4);
    }

    .btn-save:active {
        transform: translateY(0);
    }

    /* Info note — dark navy tinted */
    .info-note {
        margin-top: 20px;
        padding: 14px 18px;
        background: rgba(26, 58, 143, 0.12);
        border-radius: 10px;
        border-left: 4px solid var(--color-navy);
        color: var(--color-text);
        font-size: 0.88rem;
        line-height: 1.5;
    }

    .info-note strong {
        color: #4A7AFF;
    }

    .info-note i {
        color: #4A7AFF;
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
        z-index: 10001;
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

    /* Success — navy */
    .alert-icon.success {
        background: rgba(26, 58, 143, 0.2);
        color: #4A7AFF;
        border: 1px solid rgba(26, 58, 143, 0.35);
    }

    /* Error — red */
    .alert-icon.error {
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

    /* Success OK — navy */
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

    /* Cancel / Dismiss */
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

    /* Responsive */
    @media (max-width: 768px) {
        .main-content { padding-bottom: 140px; }
        .rate-amount  { font-size: 38px; }
        .rate-input   { font-size: 32px; }
        .rate-icon    { width: 60px; height: 60px; font-size: 26px; }
    }

    @media (max-width: 480px) {
        .update-section, .rate-card { padding: 20px; }
        .rate-amount { font-size: 32px; }
        .rate-input  { font-size: 28px; }
    }
</style>

<div class="main-content">
    
    <h1 style="margin-bottom: 25px;">
        <i class="fas fa-money-bill-wave"></i> WALK-IN RATE MANAGEMENT
    </h1>
    
    <!-- Current Rates Display -->
    <div class="rate-cards">
        <div class="rate-card">
            <div class="rate-icon non-member">
                <i class="fas fa-user"></i>
            </div>
            <div class="rate-label">Regular Rate</div>
            <div class="rate-amount">₱<?php echo number_format($non_member_rate, 0); ?></div>
            <div class="rate-description">Non-Member Walk-in</div>
        </div>
        
        <div class="rate-card">
            <div class="rate-icon student">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="rate-label">Student Rate</div>
            <div class="rate-amount">₱<?php echo number_format($student_rate, 0); ?></div>
            <div class="rate-description">Student Discount</div>
        </div>
    </div>
    
    <!-- Update Rates Form -->
    <div class="update-section">
        <h3 style="margin-bottom: 20px;">
            <i class="fas fa-edit"></i> Update Walk-in Rates
        </h3>
        
        <form method="POST">
            <input type="hidden" name="action" value="update_rates">
            
            <div class="rate-input-group">
                <div class="rate-input-box">
                    <label class="input-label">
                        <i class="fas fa-user"></i> Non-Member Rate (₱)
                    </label>
                    <input type="number" name="non_member_rate" class="rate-input" 
                           value="<?php echo $non_member_rate; ?>" 
                           min="0" step="1" required 
                           placeholder="80">
                </div>
                
                <div class="rate-input-box">
                    <label class="input-label">
                        <i class="fas fa-graduation-cap"></i> Student Rate (₱)
                    </label>
                    <input type="number" name="student_rate" class="rate-input" 
                           value="<?php echo $student_rate; ?>" 
                           min="0" step="1" required 
                           placeholder="50">
                </div>
            </div>
            
            <button type="submit" class="btn-save">
                <i class="fas fa-save"></i> SAVE CHANGES
            </button>
        </form>
        
        <div class="info-note">
            <strong><i class="fas fa-info-circle"></i> Note:</strong> Changes will apply immediately to all walk-in check-ins.
        </div>
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
            <p>Walk-in rates updated successfully!</p>
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
    function closeSuccessModal() {
        document.getElementById('successModal').classList.remove('active');
    }

    function closeErrorModal() {
        document.getElementById('errorModal').classList.remove('active');
    }

    " . ($success ? "
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('successModal').classList.add('active');
    });
    " : "") . "

    " . ($error ? "
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('errorModal').classList.add('active');
    });
    " : "") . "

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