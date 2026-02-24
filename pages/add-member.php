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
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $email = sanitize_input($_POST['email']);
    $phone_number = sanitize_input($_POST['phone_number']);
    $gender = sanitize_input($_POST['gender']);
    $date_of_birth = sanitize_input($_POST['date_of_birth']);
    $address = sanitize_input($_POST['address']);
    $is_student = isset($_POST['is_student']) ? 1 : 0;
    
    $emergency_name = sanitize_input($_POST['emergency_name']);
    $emergency_phone = sanitize_input($_POST['emergency_phone']);
    $emergency_relationship = sanitize_input($_POST['emergency_relationship']);
    $emergency_address = sanitize_input($_POST['emergency_address']);
    
    $medical_conditions = sanitize_input($_POST['medical_conditions']);
    $physical_limitations = sanitize_input($_POST['physical_limitations']);
    $fitness_goals = sanitize_input($_POST['fitness_goals']);
    $current_medications = sanitize_input($_POST['current_medications']);

    // Server-side phone validation — exactly 11 digits
    $phone_clean = preg_replace('/\D/', '', $phone_number);
    if (strlen($phone_clean) !== 11) {
        $error = 'Phone number must be exactly 11 digits (e.g., 09XXXXXXXXX).';
    }

    // Validate emergency phone if provided
    if (empty($error) && !empty($emergency_phone)) {
        $ep_clean = preg_replace('/\D/', '', $emergency_phone);
        if (strlen($ep_clean) !== 11) {
            $error = 'Emergency contact phone number must be exactly 11 digits.';
        }
    }

    if (empty($error)) {
        $member_id = generate_id('MEM', 6);
        $qr_code = generate_id('QR', 10);
        
        $photo = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $target_dir = "../assets/uploads/";
            $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo = 'member_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $photo;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                // uploaded
            } else {
                $photo = '';
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO member (member_id, first_name, last_name, email, phone_number, gender, date_of_birth, address, photo, qr_code, is_student) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssi", $member_id, $first_name, $last_name, $email, $phone_number, $gender, $date_of_birth, $address, $photo, $qr_code, $is_student);
        
        if ($stmt->execute()) {
            $new_member_id = $conn->insert_id;
            
            if (!empty($emergency_name) && !empty($emergency_phone)) {
                $emergency_stmt = $conn->prepare("INSERT INTO emergency_contacts (member_id, emergency_contact_name, emergency_contact_phone, emergency_contact_relationship, emergency_contact_address) VALUES (?, ?, ?, ?, ?)");
                $emergency_stmt->bind_param("issss", $new_member_id, $emergency_name, $emergency_phone, $emergency_relationship, $emergency_address);
                $emergency_stmt->execute();
            }
            
            if (!empty($medical_conditions) || !empty($physical_limitations) || !empty($fitness_goals) || !empty($current_medications)) {
                $health_stmt = $conn->prepare("INSERT INTO health_and_fitness_info (member_id, medical_condition_allergies, physical_limitations, fitness_goals, current_medications) VALUES (?, ?, ?, ?, ?)");
                $health_stmt->bind_param("issss", $new_member_id, $medical_conditions, $physical_limitations, $fitness_goals, $current_medications);
                $health_stmt->execute();
            }
            
            log_activity('Add Member', 'New member added: ' . $first_name . ' ' . $last_name, $_SESSION['admin_id']);
            
            $success = true;
            $success_message = 'Member ' . $first_name . ' ' . $last_name . ' has been successfully added to the database!';
        } else {
            $error = 'Failed to add member: ' . $conn->error;
        }
    }
}

$page_title = "Add New Member";
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

        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            padding-bottom: 100px;
        }

        .page-title-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            background: var(--color-surface-2);
            border: 1px solid var(--color-border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--color-text);
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .back-btn:hover {
            background: var(--color-primary);
            color: var(--color-white);
            border-color: var(--color-primary);
        }

        .page-title-row h1 {
            color: var(--color-white);
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 1px;
            margin: 0;
        }

        .page-title-row h1 i {
            color: var(--color-primary);
            margin-right: 8px;
        }
        
        .form-card {
            background: var(--color-surface);
            border-radius: 14px;
            padding: 28px;
            border: 1px solid var(--color-border);
            margin-bottom: 20px;
            transition: border-color 0.3s ease;
        }

        .form-card:hover {
            border-color: rgba(204, 28, 28, 0.25);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--color-primary);
        }
        
        .section-header i {
            font-size: 22px;
            color: var(--color-primary);
        }
        
        .section-header h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--color-white);
            letter-spacing: 0.5px;
            margin: 0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--color-silver);
            margin-bottom: 7px;
        }

        .form-control {
            width: 100%;
            background: var(--color-surface-2);
            border: 1px solid var(--color-border);
            color: var(--color-white);
            border-radius: 8px;
            padding: 11px 14px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box;
        }

        .form-control::placeholder {
            color: var(--color-muted);
        }

        .form-control:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(204, 28, 28, 0.12);
        }

        /* Phone field invalid state */
        .form-control.phone-invalid {
            border-color: #FF5555 !important;
            box-shadow: 0 0 0 3px rgba(204, 28, 28, 0.2) !important;
        }

        /* Phone field valid state */
        .form-control.phone-valid {
            border-color: #22c55e !important;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12) !important;
        }

        select.form-control option {
            background: var(--color-surface-2);
            color: var(--color-white);
        }

        textarea.form-control {
            resize: vertical;
        }

        .form-group {
            margin-bottom: 18px;
        }

        /* Phone hint text */
        .phone-hint {
            font-size: 0.75rem;
            margin-top: 5px;
            color: var(--color-muted);
            transition: color 0.3s ease;
        }

        .phone-hint.invalid { color: #FF5555; }
        .phone-hint.valid   { color: #22c55e; }
        
        .photo-upload {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #CC1C1C, #A01515);
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 3px solid rgba(204, 28, 28, 0.3);
        }
        
        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .photo-preview i {
            font-size: 48px;
            color: #FFFFFF;
        }
        
        .upload-btn {
            display: inline-block;
            padding: 10px 22px;
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(204, 28, 28, 0.3);
        }
        
        .upload-btn:hover {
            background: rgba(204, 28, 28, 0.28);
            border-color: rgba(204, 28, 28, 0.5);
        }
        
        .upload-btn input {
            display: none;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: rgba(26, 58, 143, 0.12);
            border: 1px solid rgba(26, 58, 143, 0.25);
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--color-navy);
        }
        
        .checkbox-group label {
            cursor: pointer;
            font-weight: bold;
            color: #4A7AFF;
            font-size: 0.9rem;
        }

        .checkbox-group label i {
            margin-right: 6px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
        }

        .btn-submit {
            flex: 1;
            padding: 14px 20px;
            background: var(--color-primary);
            color: var(--color-white);
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: var(--color-primary-dk);
            box-shadow: 0 4px 16px rgba(204, 28, 28, 0.4);
            transform: translateY(-2px);
        }

        .btn-cancel {
            flex: 1;
            padding: 14px 20px;
            background: var(--color-surface-2);
            color: var(--color-text);
            border: 1px solid var(--color-border);
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.95rem;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #222222;
            border-color: #444444;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.75);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 16px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            animation: slideUp 0.3s ease;
        }

        .modal-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .modal-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .modal-icon.success {
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            border: 1px solid rgba(26, 58, 143, 0.3);
        }

        .modal-icon.error {
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.25);
        }

        .modal-title h3 {
            margin: 0;
            font-size: 22px;
            font-weight: bold;
            color: var(--color-white);
        }

        .modal-body {
            margin-bottom: 25px;
            color: var(--color-text);
            line-height: 1.6;
            font-size: 15px;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
        }

        .modal-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .modal-btn.secondary {
            background: var(--color-surface-2);
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }

        .modal-btn.secondary:hover {
            background: #252525;
        }

        .modal-btn.success {
            background: var(--color-primary);
            color: #FFFFFF;
        }

        .modal-btn.success:hover {
            background: var(--color-primary-dk);
        }

        .modal-btn.error-ok {
            background: var(--color-navy);
            color: #FFFFFF;
        }

        .modal-btn.error-ok:hover {
            background: #243FA0;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .form-card { padding: 18px; }
            .form-actions { flex-direction: column; }
        }
    </style>
    
    <div class="form-container">
        
        <div class="page-title-row">
            <a href="members.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1><i class="fas fa-user-plus"></i> ADD NEW MEMBER</h1>
        </div>
        
        <form method="POST" enctype="multipart/form-data" id="addMemberForm">
            
            <!-- Personal Information -->
            <div class="form-card">
                <div class="section-header">
                    <i class="fas fa-user"></i>
                    <h3>PERSONAL INFORMATION</h3>
                </div>
                
                <div class="photo-upload">
                    <div class="photo-preview" id="photo-preview">
                        <i class="fas fa-user"></i>
                    </div>
                    <label class="upload-btn">
                        <i class="fas fa-camera"></i> Upload Photo
                        <input type="file" name="photo" accept="image/*" onchange="previewPhoto(event)">
                    </label>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" placeholder="Enter first name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" placeholder="Enter last name" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="member@example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number *</label>
                        <input type="tel" name="phone_number" id="phone_number" class="form-control"
                               placeholder="09XXXXXXXXX" maxlength="11" required>
                        <div class="phone-hint" id="phone_hint">11 digits required (e.g. 09XXXXXXXXX)</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Gender *</label>
                        <select name="gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth *</label>
                        <input type="date" name="date_of_birth" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="3" placeholder="Complete address"></textarea>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_student" id="is_student">
                    <label for="is_student">
                        <i class="fas fa-graduation-cap"></i> This member is a STUDENT (₱30 rate applies)
                    </label>
                </div>
            </div>
            
            <!-- Emergency Contact -->
            <div class="form-card">
                <div class="section-header">
                    <i class="fas fa-phone-square-alt"></i>
                    <h3>EMERGENCY CONTACT</h3>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Contact Name</label>
                        <input type="text" name="emergency_name" class="form-control" placeholder="Full name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Phone</label>
                        <input type="tel" name="emergency_phone" id="emergency_phone" class="form-control"
                               placeholder="09XXXXXXXXX" maxlength="11">
                        <div class="phone-hint" id="emergency_phone_hint">11 digits required (e.g. 09XXXXXXXXX)</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Relationship</label>
                        <input type="text" name="emergency_relationship" class="form-control" placeholder="e.g., Mother, Father, Spouse">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Address</label>
                        <input type="text" name="emergency_address" class="form-control" placeholder="Complete address">
                    </div>
                </div>
            </div>
            
            <!-- Health and Fitness Info -->
            <div class="form-card">
                <div class="section-header">
                    <i class="fas fa-heartbeat"></i>
                    <h3>HEALTH & FITNESS INFORMATION</h3>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Medical Conditions / Allergies</label>
                    <textarea name="medical_conditions" class="form-control" rows="2" placeholder="List any medical conditions or allergies"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Physical Limitations</label>
                    <textarea name="physical_limitations" class="form-control" rows="2" placeholder="Any physical limitations or injuries"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Fitness Goals</label>
                    <textarea name="fitness_goals" class="form-control" rows="2" placeholder="What are your fitness goals?"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Current Medications</label>
                    <textarea name="current_medications" class="form-control" rows="2" placeholder="List any current medications"></textarea>
                </div>
            </div>
            
            <!-- Submit Buttons -->
            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> ADD MEMBER
                </button>
                <a href="members.php" class="btn-cancel">
                    <i class="fas fa-times"></i> CANCEL
                </a>
            </div>
            
        </form>
        
    </div>

    <!-- Success Modal -->
    <div class="modal-overlay" id="successModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="modal-title">
                    <h3>Member Added!</h3>
                </div>
            </div>
            <div class="modal-body">
                <p id="successMessage"><?php echo $success_message; ?></p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn secondary" onclick="addAnother()">
                    <i class="fas fa-plus"></i> Add Another
                </button>
                <button class="modal-btn success" onclick="viewMembers()">
                    <i class="fas fa-users"></i> View Members
                </button>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal-overlay" id="errorModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon error">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="modal-title">
                    <h3>Error</h3>
                </div>
            </div>
            <div class="modal-body">
                <p id="errorMessage"><?php echo $error; ?></p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn error-ok" onclick="closeErrorModal()">OK</button>
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

            // Strip non-numeric characters as user types, enforce 11-digit max
            input.addEventListener('input', function () {
                let digits = this.value.replace(/\D/g, '');
                if (digits.length > 11) digits = digits.slice(0, 11);
                this.value = digits;
                updatePhoneState(this, hint, required);
            });

            // Also validate on blur
            input.addEventListener('blur', function () {
                updatePhoneState(this, hint, required);
            });
        }

        function updatePhoneState(input, hint, required) {
            const len = input.value.length;

            if (len === 0 && !required) {
                // Optional field left blank — neutral state
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

        setupPhoneField('phone_number',    'phone_hint',           true);
        setupPhoneField('emergency_phone', 'emergency_phone_hint', false);

        // ── Form submit guard ─────────────────────────────────────────
        document.getElementById('addMemberForm').addEventListener('submit', function (e) {
            const phone = document.getElementById('phone_number').value;
            if (phone.length !== 11) {
                e.preventDefault();
                document.getElementById('errorMessage').textContent = 'Phone number must be exactly 11 digits (e.g., 09XXXXXXXXX).';
                document.getElementById('errorModal').classList.add('active');
                document.getElementById('phone_number').focus();
                return;
            }

            const ep = document.getElementById('emergency_phone').value;
            if (ep.length > 0 && ep.length !== 11) {
                e.preventDefault();
                document.getElementById('errorMessage').textContent = 'Emergency contact phone must be exactly 11 digits.';
                document.getElementById('errorModal').classList.add('active');
                document.getElementById('emergency_phone').focus();
                return;
            }
        });

        // ── Photo preview ─────────────────────────────────────────────
        function previewPhoto(event) {
            const preview = document.getElementById('photo-preview');
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src=\"' + e.target.result + '\">';
                };
                reader.readAsDataURL(file);
            }
        }

        function viewMembers() { window.location.href = 'members.php'; }
        function addAnother()  { window.location.href = 'add-member.php'; }

        function closeErrorModal() {
            document.getElementById('errorModal').classList.remove('active');
        }

        " . ($success ? "document.getElementById('successModal').classList.add('active');" : "") . "
        " . ($error   ? "document.getElementById('errorModal').classList.add('active');"  : "") . "

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                closeErrorModal();
            }
        });
    </script>
    ";
    
    include '../includes/footer.php'; 
    ?>