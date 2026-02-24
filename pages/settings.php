<?php
require_once '../config/database.php';

if (!is_logged_in()) {
    redirect('../login.php');
}

// Check if user is staff or admin
$user_id = get_user_id();
$is_staff_user = is_staff();
$table = $is_staff_user ? 'staff' : 'admin';

// Get user info
$user_query = $conn->prepare("SELECT * FROM $table WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

if (!$user) {
    redirect('../login.php');
}

// Use $user data for both $admin variable (for sidebar) and settings
$admin = $user;

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

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Update Profile
    if (isset($_POST['action']) && $_POST['action'] == 'update_profile') {
        $display_name = sanitize_input($_POST['display_name']);
        
        // Handle photo upload
        $photo = $user['photo'];
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
            $target_dir = "../assets/uploads/";
            
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $photo = ($is_staff_user ? 'staff_' : 'admin_') . time() . '.' . $file_extension;
            $target_file = $target_dir . $photo;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                if ($user['photo'] && file_exists($target_dir . $user['photo'])) {
                    unlink($target_dir . $user['photo']);
                }
            } else {
                $photo = $user['photo'];
            }
        }
        
        $update_query = $conn->prepare("UPDATE $table SET first_name = ?, photo = ? WHERE id = ?");
        $update_query->bind_param("ssi", $display_name, $photo, $user_id);
        
        if ($update_query->execute()) {
            log_activity('Update Profile', 'Profile updated', $user_id);
            $success = 'Profile updated successfully!';
            
            $user_query->execute();
            $user = $user_query->get_result()->fetch_assoc();
            $admin = $user;
        } else {
            $error = 'Failed to update profile';
        }
    }
    
    // Update Password
    if (isset($_POST['action']) && $_POST['action'] == 'update_password') {
        $new_password = $_POST['new_password'];
        
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $password_query = $conn->prepare("UPDATE $table SET password = ? WHERE id = ?");
            $password_query->bind_param("si", $hashed_password, $user_id);
            
            if ($password_query->execute()) {
                log_activity('Update Password', 'Password changed', $user_id);
                $success = 'Password updated successfully!';
            } else {
                $error = 'Failed to update password';
            }
        }
    }
    
    // Export Database (Admin only)
    if (isset($_POST['action']) && $_POST['action'] == 'export_database' && is_admin()) {
        log_activity('Export Database', 'Database backup exported', $user_id);
        $success = 'export_initiated';
    }
}

// Set page title
$page_title = "Settings";

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
            background: #0A0A0A !important;
            color: #CCCCCC !important;
            padding-bottom: 100px;
        }

        .settings-container {
            padding: 20px;
            max-width: 1000px;
            margin: 0 auto;
        }

        /* Page title */
        .page-title {
            color: #FFFFFF;
            font-size: 32px;
            font-weight: 800;
            font-style: italic;
            margin-bottom: 30px;
        }

        /* "SETTINGS" — red accent */
        .page-title .highlight { color: #CC1C1C; }

        /* Settings section cards — dark surface */
        .settings-section {
            background: #141414 !important;
            border: 1px solid #2A2A2A;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }

        /* Section header with red bottom border */
        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #CC1C1C;
        }

        /* Section icon — red brand */
        .section-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #CC1C1C, #A01515);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            font-size: 22px;
        }

        .section-title { flex: 1; }

        .section-title h2 {
            color: #FFFFFF;
            font-size: 18px;
            font-weight: 700;
            font-style: italic;
            margin: 0 0 5px 0;
        }

        .section-title p {
            color: #777777;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }

        /* Profile display area */
        .profile-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 30px;
            background: #1C1C1C;
            border: 1px solid #2A2A2A;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        /* Avatar — red ring */
        .profile-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #CC1C1C, #A01515);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            overflow: hidden;
            border: 3px solid rgba(204, 28, 28, 0.5);
            box-shadow: 0 6px 20px rgba(204, 28, 28, 0.3);
        }

        .profile-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-avatar-large i {
            font-size: 50px;
            color: #FFFFFF;
        }

        .profile-name {
            color: #FFFFFF;
            font-size: 22px;
            font-weight: 800;
            font-style: italic;
            margin-bottom: 10px;
        }

        /* Role badge — red */
        .profile-role {
            color: #FFFFFF;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(204, 28, 28, 0.2);
            border: 1px solid rgba(204, 28, 28, 0.4);
            color: #FF5555;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 700;
        }

        /* Form labels */
        .settings-label {
            display: block;
            color: #777777;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        /* Form inputs — dark */
        .settings-input {
            width: 100%;
            padding: 14px 15px;
            background: #1C1C1C !important;
            border: 1px solid #2A2A2A !important;
            border-radius: 10px;
            color: #FFFFFF !important;
            font-size: 15px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            box-sizing: border-box;
            outline: none;
        }

        .settings-input:focus {
            border-color: #CC1C1C !important;
            box-shadow: 0 0 0 3px rgba(204, 28, 28, 0.12) !important;
        }

        .settings-input::placeholder { color: #555555; }

        /* Primary button — red */
        .settings-button {
            width: 100%;
            padding: 15px;
            background: #CC1C1C;
            border: none;
            border-radius: 10px;
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 700;
            font-style: italic;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(204, 28, 28, 0.35);
        }

        .settings-button:hover {
            background: #A01515;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(204, 28, 28, 0.45);
        }

        /* Secondary button — dark surface */
        .settings-button-secondary {
            background: #1C1C1C !important;
            border: 1px solid #2A2A2A !important;
            color: #CCCCCC !important;
            margin-bottom: 15px;
            box-shadow: none !important;
        }

        .settings-button-secondary:hover {
            background: #252525 !important;
            border-color: rgba(204, 28, 28, 0.4) !important;
            color: #FFFFFF !important;
            transform: none !important;
        }

        /* Logout button — outlined red */
        .logout-button {
            background: transparent !important;
            color: #FF5555 !important;
            border: 2px solid rgba(204, 28, 28, 0.5) !important;
            box-shadow: none !important;
        }

        .logout-button:hover {
            background: rgba(204, 28, 28, 0.15) !important;
            border-color: #CC1C1C !important;
            color: #FF5555 !important;
            transform: none !important;
        }

        /* Warning box — amber/gold tone */
        .warning-box {
            background: rgba(255, 193, 7, 0.07);
            border: 1px solid rgba(255, 193, 7, 0.25);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
        }

        .warning-box i { color: #ffc107; font-size: 20px; }

        .warning-box p {
            color: #CCCCCC;
            font-size: 13px;
            font-weight: 600;
            margin: 0;
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

        /* Warning — amber */
        .alert-icon.warning {
            background: rgba(255, 165, 0, 0.1);
            color: #FFA500;
            border: 1px solid rgba(255, 165, 0, 0.25);
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
            font-size: 14px;
        }

        /* OK — navy */
        .alert-btn.success {
            background: #1A3A8F;
            color: #FFFFFF;
        }

        .alert-btn.success:hover { background: #243FA0; }

        /* Danger — red */
        .alert-btn.danger {
            background: #CC1C1C;
            color: #FFFFFF;
        }

        .alert-btn.danger:hover { background: #A01515; }

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

        @media (max-width: 768px) {
            .page-title { font-size: 24px; }
            .settings-section { padding: 20px; }
            .profile-avatar-large { width: 100px; height: 100px; }
            .profile-name { font-size: 18px; }
        }
    </style>
    
    <div class="settings-container">
        
        <h1 class="page-title">PROFILE <span class="highlight">SETTINGS</span></h1>
        
        <!-- Profile Section -->
        <div class="settings-section">
            <div class="profile-display">
                <div class="profile-avatar-large">
                    <?php if (!empty($user['photo'])): ?>
                        <img src="<?php echo $base_url; ?>assets/uploads/<?php echo htmlspecialchars($user['photo']); ?>" id="preview-avatar">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="profile-name"><?php echo strtoupper($user['first_name'] . ' ' . $user['last_name']); ?></div>
                <div class="profile-role"><?php echo $is_staff_user ? 'STAFF' : 'ADMIN'; ?></div>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                
                <label class="settings-label">DISPLAY NAME</label>
                <input type="text" name="display_name" class="settings-input" value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" placeholder="Enter display name">
                
                <label class="settings-label">PROFILE PICTURE</label>
                <input type="file" name="profile_photo" accept="image/*" class="settings-input" onchange="previewImage(event)">
                
                <button type="submit" class="settings-button">
                    <i class="fas fa-save"></i> UPDATE ACCOUNT
                </button>
            </form>
        </div>
        
        <!-- Password Section -->
        <div class="settings-section">
            <div class="section-header">
                <div style="flex: 1;">
                    <label class="settings-label">NEW PASSWORD</label>
                </div>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_password">
                <input type="password" name="new_password" class="settings-input" placeholder="Leave blank to keep current...">
                
                <button type="submit" class="settings-button">
                    <i class="fas fa-save"></i> UPDATE PASSWORD
                </button>
            </form>
        </div>
        
        <!-- Logout Section -->
        <div class="settings-section">
            <button class="settings-button logout-button" onclick="confirmLogout()">
                <i class="fas fa-sign-out-alt"></i> TERMINATE SESSION
            </button>
        </div>
        
        <!-- Database Maintenance (Admin Only) -->
        <?php if (is_admin()): ?>
        <div class="settings-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="section-title">
                    <h2>DATABASE MAINTENANCE</h2>
                    <p>ADMIN ONLY PROTOCOLS - ENCRYPTED BACKUPS</p>
                </div>
            </div>
            
            <div class="warning-box">
                <i class="fas fa-shield-alt"></i>
                <p>SECURE MODE: All backups are encrypted with AES-256. Only this system can decrypt them using the secret key.</p>
            </div>
            
            <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <p>WARNING: IMPORTING DATA WILL COMPLETELY OVERWRITE THE CURRENT SYSTEM STATE. PROCEED WITH EXTREME CAUTION.</p>
            </div>
            
            <form method="POST" id="exportForm">
                <input type="hidden" name="action" value="export_database">
                <button type="button" class="settings-button settings-button-secondary" onclick="exportDatabase()">
                    <i class="fas fa-download"></i>
                    <div style="text-align: left; flex: 1;">
                        <div>EXPORT ENCRYPTED BACKUP</div>
                        <div style="font-size: 10px; font-weight: normal; opacity: 0.6;">GENERATE SECURE .WPGB FILE</div>
                    </div>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </form>
            
            <button class="settings-button settings-button-secondary" onclick="document.getElementById('importFile').click()">
                <i class="fas fa-upload"></i>
                <div style="text-align: left; flex: 1;">
                    <div>RESTORE FROM ENCRYPTED BACKUP</div>
                    <div style="font-size: 10px; font-weight: normal; opacity: 0.6;">UPLOAD .WPGB FILE TO RESTORE DATABASE</div>
                </div>
                <i class="fas fa-chevron-right"></i>
            </button>
            <input type="file" id="importFile" accept=".wpgb" style="display: none;" onchange="importDatabase(this)">
        </div>
        <?php endif; ?>
        
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
                <p id="successMessage"></p>
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
                <p id="errorMessage"></p>
            </div>
            <div class="alert-footer">
                <button class="alert-btn secondary" onclick="closeErrorModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="alert-modal" id="logoutModal">
        <div class="alert-modal-content">
            <div class="alert-header">
                <div class="alert-icon warning">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <div class="alert-title">
                    <h3>Terminate Session</h3>
                </div>
            </div>
            <div class="alert-body">
                <p>Are you sure you want to terminate this session and log out?</p>
            </div>
            <div class="alert-footer">
                <button class="alert-btn secondary" onclick="closeLogoutModal()">Cancel</button>
                <button class="alert-btn danger" onclick="proceedLogout()">
                    <i class="fas fa-sign-out-alt"></i> Log Out
                </button>
            </div>
        </div>
    </div>

    <!-- Import File Warning Modal -->
    <div class="alert-modal" id="importWarningModal">
        <div class="alert-modal-content">
            <div class="alert-header">
                <div class="alert-icon warning">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="alert-title">
                    <h3>WARNING</h3>
                </div>
            </div>
            <div class="alert-body">
                <p>This will overwrite ALL current data with the backup.</p>
                <p style="margin-top: 10px;"><strong>Are you absolutely sure you want to continue?</strong></p>
            </div>
            <div class="alert-footer">
                <button class="alert-btn secondary" onclick="closeImportWarningModal()">Cancel</button>
                <button class="alert-btn danger" onclick="showFinalConfirmation()">Continue</button>
            </div>
        </div>
    </div>

    <!-- Import Final Confirmation Modal -->
    <div class="alert-modal" id="importFinalModal">
        <div class="alert-modal-content">
            <div class="alert-header">
                <div class="alert-icon warning">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="alert-title">
                    <h3>FINAL CONFIRMATION</h3>
                </div>
            </div>
            <div class="alert-body">
                <p>This action cannot be undone. The system will be restored to the backup state.</p>
                <p style="margin-top: 10px;"><strong>Proceed with database restore?</strong></p>
            </div>
            <div class="alert-footer">
                <button class="alert-btn secondary" onclick="closeFinalModal()">Cancel</button>
                <button class="alert-btn danger" onclick="proceedImport()">
                    <i class="fas fa-upload"></i> Restore Now
                </button>
            </div>
        </div>
    </div>

    <!-- Import Success Modal -->
    <div class="alert-modal" id="importSuccessModal">
        <div class="alert-modal-content">
            <div class="alert-header">
                <div class="alert-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="alert-title">
                    <h3>Restore Complete</h3>
                </div>
            </div>
            <div class="alert-body">
                <p id="importSuccessMessage"></p>
            </div>
            <div class="alert-footer">
                <button class="alert-btn success" onclick="location.reload()">
                    <i class="fas fa-sync"></i> Reload System
                </button>
            </div>
        </div>
    </div>
    
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/bottom-nav.php'; ?>
    
    <?php 
    $extra_scripts = "
    <script>
        let importFileInput = null;

        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const avatar = document.getElementById('preview-avatar');
                    if (avatar) {
                        avatar.src = e.target.result;
                    } else {
                        document.querySelector('.profile-avatar-large').innerHTML = '<img src=\"' + e.target.result + '\" id=\"preview-avatar\">';
                    }
                };
                reader.readAsDataURL(file);
            }
        }

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
        
        function confirmLogout() {
            document.getElementById('logoutModal').classList.add('active');
        }

        function closeLogoutModal() {
            document.getElementById('logoutModal').classList.remove('active');
        }

        function proceedLogout() {
            window.location.href = '../logout.php';
        }
        
        function exportDatabase() {
            showLoading();
            
            fetch('export-database.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Export failed');
                    }
                    return response.blob();
                })
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    
                    const date = new Date();
                    const dateStr = date.getFullYear() + 
                                  String(date.getMonth() + 1).padStart(2, '0') + 
                                  String(date.getDate()).padStart(2, '0') + '_' +
                                  String(date.getHours()).padStart(2, '0') + 
                                  String(date.getMinutes()).padStart(2, '0') + 
                                  String(date.getSeconds()).padStart(2, '0');
                    
                    a.download = 'wolf_palomar_backup_' + dateStr + '.wpgb';
                    
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    hideLoading();
                    showSuccessModal('Encrypted backup exported successfully!');
                })
                .catch(error => {
                    hideLoading();
                    showErrorModal('Error exporting database: ' + error.message);
                    console.error('Export error:', error);
                });
        }
        
        function importDatabase(input) {
            const file = input.files[0];
            if (!file) return;
            
            const fileExt = file.name.split('.').pop().toLowerCase();
            if (fileExt !== 'wpgb') {
                showErrorModal('Invalid file! Only .wpgb (encrypted backup) files are allowed.');
                input.value = '';
                return;
            }
            
            importFileInput = input;
            document.getElementById('importWarningModal').classList.add('active');
        }

        function closeImportWarningModal() {
            document.getElementById('importWarningModal').classList.remove('active');
            if (importFileInput) {
                importFileInput.value = '';
                importFileInput = null;
            }
        }

        function showFinalConfirmation() {
            document.getElementById('importWarningModal').classList.remove('active');
            document.getElementById('importFinalModal').classList.add('active');
        }

        function closeFinalModal() {
            document.getElementById('importFinalModal').classList.remove('active');
            if (importFileInput) {
                importFileInput.value = '';
                importFileInput = null;
            }
        }

        function proceedImport() {
            if (!importFileInput || !importFileInput.files[0]) return;
            
            document.getElementById('importFinalModal').classList.remove('active');
            showLoading();
            
            const formData = new FormData();
            formData.append('backup_file', importFileInput.files[0]);
            
            fetch('import-database.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    let message = 'Tables imported: ' + data.imported_tables + '\\n';
                    if (data.skipped_tables > 0) {
                        message += 'Tables skipped: ' + data.skipped_tables + '\\n';
                    }
                    if (data.export_date) {
                        message += 'Backup date: ' + data.export_date + '\\n';
                    }
                    if (data.exported_by) {
                        message += 'Exported by: ' + data.exported_by;
                    }
                    
                    document.getElementById('importSuccessMessage').textContent = message;
                    document.getElementById('importSuccessModal').classList.add('active');
                } else {
                    showErrorModal('Import failed: ' + (data.message || 'Unknown error'));
                }
                importFileInput.value = '';
                importFileInput = null;
            })
            .catch(error => {
                hideLoading();
                showErrorModal('Error importing database: ' + error.message);
                console.error('Import error:', error);
                if (importFileInput) {
                    importFileInput.value = '';
                    importFileInput = null;
                }
            });
        }

        " . ($success && $success !== 'export_initiated' ? "showSuccessModal('" . addslashes($success) . "');" : "") . "
        " . ($error ? "showErrorModal('" . addslashes($error) . "');" : "") . "

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('alert-modal')) {
                closeSuccessModal();
                closeErrorModal();
                closeLogoutModal();
                closeImportWarningModal();
                closeFinalModal();
            }
        });
    </script>
    ";
    
    include '../includes/footer.php'; 
    ?>