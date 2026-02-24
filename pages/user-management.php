<?php
require_once '../config/database.php';

if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_staff'])) {
    $first_name = sanitize_input($_POST['first_name']);
    $last_name  = sanitize_input($_POST['last_name']);
    $email      = sanitize_input($_POST['email']);
    $phone      = sanitize_input($_POST['phone_number']);
    $position   = sanitize_input($_POST['position']);
    $password   = $_POST['password'];

    // Server-side phone validation — exactly 11 digits (if provided)
    if (!empty($phone)) {
        $phone_clean = preg_replace('/\D/', '', $phone);
        if (strlen($phone_clean) !== 11) {
            $error = 'Phone number must be exactly 11 digits (e.g., 09XXXXXXXXX).';
        }
    }

    if (empty($error)) {
        $check_staff = $conn->query("SELECT id FROM staff WHERE email = '$email'");
        $check_admin = $conn->query("SELECT id FROM admin WHERE email = '$email'");

        if ($check_staff->num_rows > 0 || $check_admin->num_rows > 0) {
            $error = "Email already exists!";
        } else {
            $staff_id        = 'STF' . strtoupper(substr(uniqid(), -6));
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO staff (staff_id, first_name, last_name, email, phone_number, position, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $staff_id, $first_name, $last_name, $email, $phone, $position, $hashed_password);

            if ($stmt->execute()) {
                log_activity('Add Staff', "New staff added: $first_name $last_name ($position)", $_SESSION['admin_id']);
                $success = "Staff account created successfully!";
            } else {
                $error = "Failed to create staff account.";
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $staff_id = intval($_GET['delete']);
    $stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();

    if ($staff) {
        $conn->query("DELETE FROM staff WHERE id = $staff_id");
        log_activity('Delete Staff', "Staff deleted: {$staff['first_name']} {$staff['last_name']}", $_SESSION['admin_id']);
        $success = "Staff deleted successfully!";
    }
}

$staff_list = $conn->query("SELECT * FROM staff ORDER BY created_at DESC");

$page_title = "User Management";
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

        body { background: var(--color-bg); color: var(--color-text); }

        .user-container { min-height: 100vh; padding: 20px; padding-bottom: 100px; }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--color-muted);
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: color 0.3s ease;
        }

        .back-button:hover { color: var(--color-primary); }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-title { font-size: 1.75rem; font-weight: 700; color: var(--color-white); letter-spacing: 0.5px; }

        .btn-add {
            background: var(--color-primary);
            color: var(--color-white);
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-add:hover {
            background: var(--color-primary-dk);
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(204,28,28,0.4);
        }

        .staff-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .staff-card {
            background: var(--color-surface);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid var(--color-border);
            box-shadow: 0 2px 10px rgba(0,0,0,0.4);
            transition: all 0.3s ease;
        }

        .staff-card:hover {
            transform: translateY(-4px);
            border-color: rgba(204,28,28,0.35);
            box-shadow: 0 8px 24px rgba(0,0,0,0.5), 0 0 16px rgba(204,28,28,0.08);
        }

        .staff-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }

        .staff-avatar {
            width: 60px; height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #CC1C1C, #A01515);
            display: flex; align-items: center; justify-content: center;
            color: var(--color-white);
            font-size: 22px; font-weight: 700;
            border: 2px solid rgba(204,28,28,0.3);
            flex-shrink: 0;
        }

        .staff-info h3 { font-size: 17px; font-weight: 700; margin-bottom: 4px; color: var(--color-white); }
        .staff-info p  { font-size: 12px; color: var(--color-muted); margin: 0; }

        .staff-details {
            margin: 15px 0; padding: 15px 0;
            border-top: 1px solid var(--color-border);
            border-bottom: 1px solid var(--color-border);
        }

        .detail-item {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 10px; font-size: 13px; color: var(--color-text);
        }

        .detail-item:last-child { margin-bottom: 0; }
        .detail-item i { color: var(--color-primary); width: 18px; flex-shrink: 0; }

        .staff-actions { display: flex; gap: 10px; margin-top: 15px; }

        .btn-edit {
            flex: 1; padding: 10px;
            background: rgba(26,58,143,0.15); color: #4A7AFF;
            border: 1px solid rgba(26,58,143,0.25); border-radius: 8px;
            font-weight: 600; cursor: pointer; transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center;
            gap: 6px; font-size: 0.85rem;
        }

        .btn-edit:hover { background: rgba(26,58,143,0.3); transform: translateY(-1px); }

        .btn-delete {
            flex: 1; padding: 10px;
            background: rgba(204,28,28,0.1); color: #FF5555;
            border: 1px solid rgba(204,28,28,0.22); border-radius: 8px;
            font-weight: 600; cursor: pointer; transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center;
            gap: 6px; font-size: 0.85rem;
        }

        .btn-delete:hover { background: rgba(204,28,28,0.22); transform: translateY(-1px); }

        /* ── Add Staff Modal ── */
        .modal {
            display: none; position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.75); z-index: 9999;
            align-items: center; justify-content: center;
        }

        .modal.active { display: flex; }

        .modal-content {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 20px; padding: 30px;
            width: 90%; max-width: 500px;
            max-height: 90vh; overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.7);
            color: var(--color-text);
        }

        .modal-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 25px;
        }

        .modal-header h2 { font-size: 1.25rem; font-weight: 700; color: var(--color-white); }
        .modal-header h2 i { color: var(--color-primary); margin-right: 8px; }

        .btn-close {
            background: var(--color-surface-2);
            border: 1px solid var(--color-border);
            width: 36px; height: 36px; border-radius: 50%;
            font-size: 18px; cursor: pointer; color: var(--color-muted);
            display: flex; align-items: center; justify-content: center;
            transition: all 0.3s ease;
        }

        .btn-close:hover {
            background: rgba(204,28,28,0.15); color: #FF5555;
            border-color: rgba(204,28,28,0.3);
        }

        .form-group { margin-bottom: 18px; }

        .form-label {
            display: block; font-size: 0.72rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: 1.2px;
            color: var(--color-muted); margin-bottom: 8px;
        }

        .form-control {
            width: 100%; padding: 11px 14px;
            background: var(--color-surface-2);
            border: 1px solid var(--color-border);
            border-radius: 10px; font-size: 0.9rem;
            color: var(--color-white); outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(204,28,28,0.15);
        }

        .form-control::placeholder { color: #444; }

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
            font-size: 0.73rem;
            margin-top: 5px;
            color: var(--color-muted);
            transition: color 0.3s ease;
        }

        .phone-hint.invalid { color: #FF5555; }
        .phone-hint.valid   { color: #22c55e; }

        .btn-submit {
            width: 100%;
            background: var(--color-primary); color: var(--color-white);
            border: none; padding: 13px; border-radius: 10px;
            font-weight: 700; font-size: 0.9rem; letter-spacing: 0.5px;
            cursor: pointer; margin-top: 10px; transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }

        .btn-submit:hover {
            background: var(--color-primary-dk);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(204,28,28,0.4);
        }

        /* ── Alert Modals ── */
        .alert-modal {
            display: none; position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.75); z-index: 10001;
            align-items: center; justify-content: center;
            padding: 20px; animation: fadeIn 0.3s ease;
        }

        .alert-modal.active { display: flex; }

        .alert-modal-content {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 16px; padding: 30px;
            max-width: 400px; width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.04);
            animation: slideUp 0.3s ease; color: var(--color-text);
        }

        .alert-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }

        .alert-icon {
            width: 55px; height: 55px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; flex-shrink: 0;
        }

        .alert-icon.success { background: rgba(26,58,143,0.2); color: #4A7AFF; border: 1px solid rgba(26,58,143,0.35); }
        .alert-icon.error   { background: rgba(204,28,28,0.15); color: #FF5555; border: 1px solid rgba(204,28,28,0.3); }
        .alert-icon.warning { background: rgba(204,28,28,0.15); color: #FF5555; border: 1px solid rgba(204,28,28,0.3); }

        .alert-title h3 { margin: 0; font-size: 20px; font-weight: 700; color: var(--color-white); }

        .alert-body { margin-bottom: 25px; color: var(--color-text); line-height: 1.6; font-size: 15px; }
        .alert-body strong { color: var(--color-white); }

        .danger-note { color: #FF5555; margin-top: 8px; font-size: 0.875rem; }

        .alert-footer { display: flex; gap: 10px; }

        .alert-btn {
            flex: 1; padding: 12px 20px; border: none;
            border-radius: 10px; font-weight: 700; cursor: pointer;
            transition: all 0.3s ease; font-size: 0.9rem;
        }

        .alert-btn.success { background: var(--color-navy); color: var(--color-white); border: 1px solid rgba(26,58,143,0.5); }
        .alert-btn.success:hover { background: #243FA0; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(26,58,143,0.4); }
        .alert-btn.danger  { background: var(--color-primary); color: var(--color-white); border: 1px solid rgba(204,28,28,0.5); }
        .alert-btn.danger:hover { background: var(--color-primary-dk); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(204,28,28,0.4); }
        .alert-btn.secondary { background: var(--color-surface-2); color: var(--color-text); border: 1px solid var(--color-border); }
        .alert-btn.secondary:hover { background: #252525; border-color: rgba(204,28,28,0.3); }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        @media (max-width: 768px) {
            .staff-grid { grid-template-columns: 1fr; }
            .page-title { font-size: 1.4rem; }
        }
    </style>
    
    <div class="user-container">
        <a href="<?php echo $base_url; ?>index.php" class="back-button">
            <i class="fas fa-arrow-left"></i> BACK TO DASHBOARD
        </a>
        
        <div class="header-row">
            <h1 class="page-title">User Management</h1>
            <button class="btn-add" onclick="openModal()">
                <i class="fas fa-user-plus"></i> ADD STAFF
            </button>
        </div>
        
        <div class="staff-grid">
            <?php while ($staff = $staff_list->fetch_assoc()): ?>
                <div class="staff-card">
                    <div class="staff-header">
                        <div class="staff-avatar">
                            <?php echo strtoupper(substr($staff['first_name'], 0, 1)); ?>
                        </div>
                        <div class="staff-info">
                            <h3><?php echo $staff['first_name'] . ' ' . $staff['last_name']; ?></h3>
                            <p><?php echo $staff['position'] ?: 'Staff Member'; ?></p>
                        </div>
                    </div>
                    
                    <div class="staff-details">
                        <div class="detail-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo $staff['email']; ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo $staff['phone_number'] ?: 'N/A'; ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-id-badge"></i>
                            <span><?php echo $staff['staff_id']; ?></span>
                        </div>
                    </div>
                    
                    <div class="staff-actions">
                        <button class="btn-edit" onclick="editStaff(<?php echo $staff['id']; ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn-delete" onclick="confirmDeleteStaff(<?php echo $staff['id']; ?>, '<?php echo addslashes($staff['first_name'] . ' ' . $staff['last_name']); ?>')">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
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

    <!-- Delete Confirmation Modal -->
    <div class="alert-modal" id="deleteModal">
        <div class="alert-modal-content">
            <div class="alert-header">
                <div class="alert-icon warning"><i class="fas fa-trash-alt"></i></div>
                <div class="alert-title"><h3>Delete Staff</h3></div>
            </div>
            <div class="alert-body">
                <p>Are you sure you want to delete <strong id="deleteStaffName"></strong>?</p>
                <p class="danger-note"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
            </div>
            <div class="alert-footer">
                <button class="alert-btn secondary" onclick="closeDeleteModal()">Cancel</button>
                <button class="alert-btn danger" onclick="proceedDelete()">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
    
    <!-- Add Staff Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Add New Staff</h2>
                <button class="btn-close" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST" id="addStaffForm">
                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" required placeholder="Enter first name">
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" required placeholder="Enter last name">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required placeholder="staff@email.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone_number" id="staff_phone" class="form-control"
                           placeholder="09XXXXXXXXX" maxlength="11">
                    <div class="phone-hint" id="staff_phone_hint">11 digits required (e.g. 09XXXXXXXXX)</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Position</label>
                    <input type="text" name="position" class="form-control" placeholder="e.g. Fitness Trainer, Receptionist">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min. 6 characters">
                </div>
                <button type="submit" name="add_staff" class="btn-submit">
                    <i class="fas fa-user-plus"></i> CREATE STAFF ACCOUNT
                </button>
            </form>
        </div>
    </div>
    
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/bottom-nav.php'; ?>
    
    <?php 
    $extra_scripts = "
    <script>
        let deleteStaffId = null;

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
        }

        function updatePhoneState(input, hint, required) {
            const len = input.value.length;

            if (len === 0 && !required) {
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
            } else if (len > 0) {
                input.classList.remove('phone-valid');
                input.classList.add('phone-invalid');
                hint.className = 'phone-hint invalid';
                hint.textContent = len + '/11 digits entered';
            } else {
                input.classList.remove('phone-invalid', 'phone-valid');
                hint.className = 'phone-hint';
                hint.textContent = '11 digits required (e.g. 09XXXXXXXXX)';
            }
        }

        setupPhoneField('staff_phone', 'staff_phone_hint', false);

        // ── Form submit guard ─────────────────────────────────────────
        document.getElementById('addStaffForm').addEventListener('submit', function (e) {
            const phone = document.getElementById('staff_phone').value;
            if (phone.length > 0 && phone.length !== 11) {
                e.preventDefault();
                showErrorModal('Phone number must be exactly 11 digits (e.g., 09XXXXXXXXX).');
                document.getElementById('staff_phone').focus();
            }
        });

        // ── Modal controls ────────────────────────────────────────────
        function showSuccessModal(message) {
            document.getElementById('successMessage').textContent = message;
            document.getElementById('successModal').classList.add('active');
        }
        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }
        function showErrorModal(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('errorModal').classList.add('active');
        }
        function closeErrorModal() {
            document.getElementById('errorModal').classList.remove('active');
        }
        function confirmDeleteStaff(id, name) {
            deleteStaffId = id;
            document.getElementById('deleteStaffName').textContent = name;
            document.getElementById('deleteModal').classList.add('active');
        }
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            deleteStaffId = null;
        }
        function proceedDelete() {
            if (deleteStaffId) window.location.href = '?delete=' + deleteStaffId;
        }
        function openModal() {
            document.getElementById('addModal').classList.add('active');
        }
        function closeModal() {
            document.getElementById('addModal').classList.remove('active');
        }
        function editStaff(id) {
            window.location.href = 'edit-staff.php?id=' + id;
        }

        " . ($success ? "showSuccessModal('" . addslashes($success) . "');" : "") . "
        " . ($error   ? "showErrorModal('"   . addslashes($error)   . "');" : "") . "

        window.onclick = function(event) {
            if (event.target == document.getElementById('addModal')) closeModal();
            if (event.target.classList.contains('alert-modal')) {
                closeSuccessModal();
                closeErrorModal();
                closeDeleteModal();
            }
        }
    </script>
    ";
    
    include '../includes/footer.php'; 
    ?>