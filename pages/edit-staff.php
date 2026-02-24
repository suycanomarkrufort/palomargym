<?php
require_once '../config/database.php';

if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

// Get user info (admin only)
$user_id = get_user_id();
$is_staff_user = is_staff();
$table = 'admin';

$user_query = $conn->prepare("SELECT * FROM $table WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$admin = $user_query->get_result()->fetch_assoc();

$today = date('Y-m-d');
$revenue_query = $conn->prepare("SELECT COALESCE(SUM(fee_charged), 0) as total FROM attendance WHERE date = ?");
$revenue_query->bind_param("s", $today);
$revenue_query->execute();
$revenue_result = $revenue_query->get_result()->fetch_assoc();
$today_revenue = $revenue_result['total'];

$daily_goal = get_setting('daily_goal');
$goal_percentage = ($daily_goal > 0) ? round(($today_revenue / $daily_goal) * 100) : 0;

$success = '';
$error = '';
$staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();

if (!$staff) {
    redirect('user-management.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name    = sanitize_input($_POST['first_name']);
    $last_name     = sanitize_input($_POST['last_name']);
    $email         = sanitize_input($_POST['email']);
    $phone         = sanitize_input($_POST['phone_number']);
    $position      = sanitize_input($_POST['position']);
    $gender        = sanitize_input($_POST['gender']);
    $date_of_birth = !empty($_POST['date_of_birth']) ? sanitize_input($_POST['date_of_birth']) : NULL;
    $address       = sanitize_input($_POST['address']);

    // Server-side phone validation — exactly 11 digits (if provided)
    if (!empty($phone)) {
        $phone_clean = preg_replace('/\D/', '', $phone);
        if (strlen($phone_clean) !== 11) {
            $error = 'Phone number must be exactly 11 digits (e.g., 09XXXXXXXXX).';
        }
    }

    if (empty($error)) {
        $check_staff = $conn->query("SELECT id FROM staff WHERE email = '$email' AND id != $staff_id");
        $check_admin = $conn->query("SELECT id FROM admin WHERE email = '$email'");

        if ($check_staff->num_rows > 0 || $check_admin->num_rows > 0) {
            $error = "Email is already in use by another user!";
        } else {
            $stmt = $conn->prepare("UPDATE staff SET 
                                   first_name = ?, last_name = ?, email = ?, phone_number = ?,
                                   position = ?, gender = ?, date_of_birth = ?, address = ?
                                   WHERE id = ?");
            $stmt->bind_param("ssssssssi", $first_name, $last_name, $email, $phone, $position, $gender, $date_of_birth, $address, $staff_id);

            if ($stmt->execute()) {
                log_activity('Edit Staff', "Staff updated: $first_name $last_name", $_SESSION['admin_id']);
                $success = "Staff details updated successfully!";

                $stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
                $stmt->bind_param("i", $staff_id);
                $stmt->execute();
                $staff = $stmt->get_result()->fetch_assoc();
            } else {
                $error = "Failed to update staff details.";
            }
        }
    }

    // Handle password change if provided
    if (empty($error) && !empty($_POST['new_password'])) {
        $new_password     = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $error = "Passwords do not match!";
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters!";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $conn->query("UPDATE staff SET password = '$hashed_password' WHERE id = $staff_id");
            log_activity('Change Password', "Password changed for staff: {$staff['first_name']} {$staff['last_name']}", $_SESSION['admin_id']);
            $success = "Password updated successfully!";
        }
    }
}

$page_title = "Edit Staff";
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

        body { background: var(--color-bg) !important; color: var(--color-text); }
        .main-content { background: var(--color-bg) !important; }

        .edit-container { min-height: 100vh; padding: 20px; padding-bottom: 120px; }

        .back-button {
            display: inline-flex; align-items: center; gap: 10px;
            color: var(--color-silver); text-decoration: none;
            font-weight: 700; font-size: 13px; margin-bottom: 28px;
            text-transform: uppercase; letter-spacing: 1px;
            transition: all 0.3s ease; padding: 10px 18px;
            background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 10px;
        }

        .back-button:hover { color: var(--color-white); background: var(--color-surface-2); border-color: var(--color-primary); }
        .back-button i { color: var(--color-primary); }

        .edit-card {
            background: var(--color-surface); border-radius: 16px; padding: 36px;
            max-width: 800px; margin: 0 auto;
            border: 1px solid var(--color-border); box-shadow: 0 4px 24px rgba(0,0,0,0.5);
        }

        .card-header { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid var(--color-primary); }

        .card-title {
            font-size: 26px; font-weight: 700; color: var(--color-white);
            display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
        }

        .card-title i { color: var(--color-primary); }

        .staff-badge {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dk));
            color: #FFFFFF; padding: 6px 14px; border-radius: 50px;
            font-size: 12px; font-weight: 700; letter-spacing: 0.5px;
        }

        .form-section { margin-bottom: 32px; }

        .section-title {
            font-size: 15px; font-weight: 700; color: var(--color-white);
            margin-bottom: 18px; display: flex; align-items: center; gap: 10px;
            text-transform: uppercase; letter-spacing: 0.8px;
        }

        .section-title i { color: var(--color-primary); font-size: 16px; }

        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 4px; }
        .form-group { margin-bottom: 18px; }

        .form-label {
            display: block; font-size: 0.78rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.8px;
            color: var(--color-silver); margin-bottom: 7px;
        }

        .form-control {
            width: 100%; padding: 11px 14px;
            background: var(--color-surface-2); border: 1px solid var(--color-border);
            border-radius: 8px; font-size: 14px; color: var(--color-white);
            font-family: inherit; transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box;
        }

        .form-control::placeholder { color: var(--color-muted); }

        .form-control:focus {
            outline: none; border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(204,28,28,0.12);
        }

        select.form-control option { background: var(--color-surface-2); color: var(--color-white); }
        textarea.form-control { min-height: 100px; resize: vertical; }

        /* Phone validation states */
        .form-control.phone-invalid {
            border-color: #FF5555 !important;
            box-shadow: 0 0 0 3px rgba(204,28,28,0.2) !important;
        }

        .form-control.phone-valid {
            border-color: #22c55e !important;
            box-shadow: 0 0 0 3px rgba(34,197,94,0.12) !important;
        }

        .phone-hint {
            font-size: 0.73rem; margin-top: 5px;
            color: var(--color-muted); transition: color 0.3s ease;
        }

        .phone-hint.invalid { color: #FF5555; }
        .phone-hint.valid   { color: #22c55e; }

        .password-section {
            background: rgba(204,28,28,0.05); padding: 22px;
            border-radius: 12px; border: 1px dashed rgba(204,28,28,0.3);
        }

        .password-hint {
            background: rgba(26,58,143,0.15); border: 1px solid rgba(26,58,143,0.3);
            color: #7A9FFF; padding: 12px 14px; border-radius: 8px;
            font-size: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
        }

        .password-hint i { color: #4A7AFF; font-size: 14px; }

        .btn-group { display: flex; gap: 15px; margin-top: 28px; }

        .btn {
            flex: 1; padding: 13px; border: none; border-radius: 10px;
            font-weight: 700; cursor: pointer; transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center;
            gap: 8px; font-size: 14px; text-transform: uppercase;
            letter-spacing: 0.5px; font-family: inherit;
        }

        .btn-save { background: var(--color-primary); color: #FFFFFF; }
        .btn-save:hover { background: var(--color-primary-dk); transform: translateY(-2px); box-shadow: 0 4px 16px rgba(204,28,28,0.4); }

        .btn-cancel { background: var(--color-surface-2); color: var(--color-text); text-decoration: none; border: 1px solid var(--color-border); }
        .btn-cancel:hover { background: #222; border-color: #444; }

        .alert-modal {
            display: none; position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.75); z-index: 10001;
            align-items: center; justify-content: center;
            padding: 20px; animation: fadeIn 0.3s ease;
        }

        .alert-modal.active { display: flex; }

        .alert-modal-content {
            background: var(--color-surface); border: 1px solid var(--color-border);
            border-radius: 16px; padding: 30px; max-width: 400px; width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.7); animation: slideUp 0.3s ease;
        }

        .alert-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }

        .alert-icon {
            width: 60px; height: 60px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 28px;
        }

        .alert-icon.success { background: rgba(26,58,143,0.2); color: #4A7AFF; border: 1px solid rgba(26,58,143,0.3); }
        .alert-icon.error   { background: rgba(204,28,28,0.15); color: #FF5555; border: 1px solid rgba(204,28,28,0.25); }

        .alert-title h3 { margin: 0; font-size: 20px; font-weight: 700; color: var(--color-white); }
        .alert-body { margin-bottom: 22px; color: var(--color-text); line-height: 1.6; font-size: 14px; }
        .alert-footer { display: flex; gap: 10px; }

        .alert-btn {
            flex: 1; padding: 12px 20px; border: none; border-radius: 8px;
            font-weight: 700; cursor: pointer; transition: all 0.3s ease;
            font-size: 14px; font-family: inherit;
        }

        .alert-btn.success { background: var(--color-primary); color: #FFFFFF; }
        .alert-btn.success:hover { background: var(--color-primary-dk); }
        .alert-btn.secondary { background: var(--color-surface-2); color: var(--color-text); border: 1px solid var(--color-border); }
        .alert-btn.secondary:hover { background: #252525; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        @media (max-width: 768px) {
            .edit-card  { padding: 20px 16px; }
            .form-row   { grid-template-columns: 1fr; }
            .btn-group  { flex-direction: column-reverse; }
            .card-title { font-size: 20px; }
        }
    </style>

    <div class="edit-container">

        <a href="user-management.php" class="back-button">
            <i class="fas fa-arrow-left"></i> BACK TO USER MANAGEMENT
        </a>

        <div class="edit-card">
            <div class="card-header">
                <h1 class="card-title">
                    <i class="fas fa-user-edit"></i>
                    Edit Staff Member
                    <span class="staff-badge"><?php echo htmlspecialchars($staff['staff_id']); ?></span>
                </h1>
            </div>

            <form method="POST" id="editStaffForm">

                <!-- Personal Information -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-user"></i> Personal Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control"
                                   value="<?php echo htmlspecialchars($staff['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control"
                                   value="<?php echo htmlspecialchars($staff['last_name']); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-control">
                                <option value="">Select Gender</option>
                                <option value="Male"   <?php echo $staff['gender']=='Male'   ? 'selected':''; ?>>Male</option>
                                <option value="Female" <?php echo $staff['gender']=='Female' ? 'selected':''; ?>>Female</option>
                                <option value="Other"  <?php echo $staff['gender']=='Other'  ? 'selected':''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control"
                                   value="<?php echo $staff['date_of_birth']; ?>">
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-address-book"></i> Contact Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?php echo htmlspecialchars($staff['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone_number" id="staff_phone" class="form-control"
                                   placeholder="09XXXXXXXXX" maxlength="11"
                                   value="<?php echo htmlspecialchars($staff['phone_number']); ?>">
                            <div class="phone-hint" id="staff_phone_hint">11 digits required (e.g. 09XXXXXXXXX)</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control"><?php echo htmlspecialchars($staff['address']); ?></textarea>
                    </div>
                </div>

                <!-- Work Information -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-briefcase"></i> Work Information</h3>

                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" class="form-control"
                               value="<?php echo htmlspecialchars($staff['position']); ?>"
                               placeholder="e.g. Fitness Trainer, Receptionist">
                    </div>
                </div>

                <!-- Password Change (Optional) -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-key"></i> Change Password (Optional)</h3>

                    <div class="password-section">
                        <div class="password-hint">
                            <i class="fas fa-info-circle"></i>
                            Leave blank if you don't want to change the password
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control"
                                       placeholder="Enter new password" minlength="6">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control"
                                       placeholder="Confirm new password" minlength="6">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="btn-group">
                    <a href="user-management.php" class="btn btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- Success Alert Modal -->
    <div class="alert-modal" id="successModal">
        <div class="alert-modal-content">
            <div class="alert-header">
                <div class="alert-icon success"><i class="fas fa-check-circle"></i></div>
                <div class="alert-title"><h3>Success!</h3></div>
            </div>
            <div class="alert-body"><p id="successMessage"></p></div>
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
                <div class="alert-icon error"><i class="fas fa-exclamation-circle"></i></div>
                <div class="alert-title"><h3>Error</h3></div>
            </div>
            <div class="alert-body"><p id="errorMessage"></p></div>
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
        // ── Phone field real-time validation ──────────────────────────
        function setupPhoneField(inputId, hintId, required) {
            const input = document.getElementById(inputId);
            const hint  = document.getElementById(hintId);
            if (!input) return;

            input.addEventListener('input', function () {
                let digits = this.value.replace(/\D/g, '');
                if (digits.length > 11) digits = digits.slice(0, 11);
                this.value = digits;
                updatePhoneState(this, hint, required);
            });

            input.addEventListener('blur', function () {
                updatePhoneState(this, hint, required);
            });

            // Validate prefilled value on page load
            if (input.value.length > 0) updatePhoneState(input, hint, required);
        }

        function updatePhoneState(input, hint, required) {
            const len = input.value.length;

            if (len === 0) {
                input.classList.remove('phone-invalid', 'phone-valid');
                hint.className = 'phone-hint';
                hint.textContent = '11 digits required (e.g. 09XXXXXXXXX)';
                return;
            }

            if (len === 11) {
                input.classList.remove('phone-invalid');
                input.classList.add('phone-valid');
                hint.className = 'phone-hint valid';
                hint.textContent = '✓ Valid phone number';
            } else {
                input.classList.remove('phone-valid');
                input.classList.add('phone-invalid');
                hint.className = 'phone-hint invalid';
                hint.textContent = len + '/11 digits entered';
            }
        }

        setupPhoneField('staff_phone', 'staff_phone_hint', false);

        // ── Form submit guard ─────────────────────────────────────────
        document.getElementById('editStaffForm').addEventListener('submit', function (e) {
            const phone = document.getElementById('staff_phone').value;
            if (phone.length > 0 && phone.length !== 11) {
                e.preventDefault();
                document.getElementById('errorMessage').textContent = 'Phone number must be exactly 11 digits (e.g., 09XXXXXXXXX).';
                document.getElementById('errorModal').classList.add('active');
                document.getElementById('staff_phone').focus();
            }
        });

        // ── Modal controls ────────────────────────────────────────────
        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }

        function closeErrorModal() {
            document.getElementById('errorModal').classList.remove('active');
        }

        " . ($success ? "document.getElementById('successMessage').textContent = '" . addslashes($success) . "'; document.getElementById('successModal').classList.add('active');" : "") . "
        " . ($error   ? "document.getElementById('errorMessage').textContent = '"   . addslashes($error)   . "'; document.getElementById('errorModal').classList.add('active');"   : "") . "

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