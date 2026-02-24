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

if (!isset($_GET['id'])) {
    redirect('members.php');
}

$member_id = intval($_GET['id']);

$member_query = $conn->prepare("SELECT * FROM member WHERE id = ?");
$member_query->bind_param("i", $member_id);
$member_query->execute();
$member = $member_query->get_result()->fetch_assoc();

if (!$member) {
    redirect('members.php');
}

$emergency_query = $conn->prepare("SELECT * FROM emergency_contacts WHERE member_id = ? LIMIT 1");
$emergency_query->bind_param("i", $member_id);
$emergency_query->execute();
$emergency = $emergency_query->get_result()->fetch_assoc();

$health_query = $conn->prepare("SELECT * FROM health_and_fitness_info WHERE member_id = ? LIMIT 1");
$health_query->bind_param("i", $member_id);
$health_query->execute();
$health = $health_query->get_result()->fetch_assoc();

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

    // Server-side phone validation — exactly 11 digits
    $phone_clean = preg_replace('/\D/', '', $phone_number);
    if (strlen($phone_clean) !== 11) {
        $error = 'Phone number must be exactly 11 digits (e.g., 09XXXXXXXXX).';
    }

    // Validate emergency phone if provided
    $emergency_phone = sanitize_input($_POST['emergency_phone']);
    if (empty($error) && !empty($emergency_phone)) {
        $ep_clean = preg_replace('/\D/', '', $emergency_phone);
        if (strlen($ep_clean) !== 11) {
            $error = 'Emergency contact phone number must be exactly 11 digits.';
        }
    }

    if (empty($error)) {
        $photo = $member['photo'];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $target_dir = "../assets/uploads/";
            $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo = 'member_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $photo;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                if ($member['photo'] && file_exists($target_dir . $member['photo'])) {
                    unlink($target_dir . $member['photo']);
                }
            } else {
                $photo = $member['photo'];
            }
        }
        
        $stmt = $conn->prepare("UPDATE member SET first_name=?, last_name=?, email=?, phone_number=?, gender=?, date_of_birth=?, address=?, photo=?, is_student=? WHERE id=?");
        $stmt->bind_param("ssssssssii", $first_name, $last_name, $email, $phone_number, $gender, $date_of_birth, $address, $photo, $is_student, $member_id);
        
        if ($stmt->execute()) {
            $emergency_name = sanitize_input($_POST['emergency_name']);
            $emergency_relationship = sanitize_input($_POST['emergency_relationship']);
            $emergency_address = sanitize_input($_POST['emergency_address']);
            
            if ($emergency) {
                $emergency_stmt = $conn->prepare("UPDATE emergency_contacts SET emergency_contact_name=?, emergency_contact_phone=?, emergency_contact_relationship=?, emergency_contact_address=? WHERE member_id=?");
                $emergency_stmt->bind_param("ssssi", $emergency_name, $emergency_phone, $emergency_relationship, $emergency_address, $member_id);
                $emergency_stmt->execute();
            } else if (!empty($emergency_name) && !empty($emergency_phone)) {
                $emergency_stmt = $conn->prepare("INSERT INTO emergency_contacts (member_id, emergency_contact_name, emergency_contact_phone, emergency_contact_relationship, emergency_contact_address) VALUES (?, ?, ?, ?, ?)");
                $emergency_stmt->bind_param("issss", $member_id, $emergency_name, $emergency_phone, $emergency_relationship, $emergency_address);
                $emergency_stmt->execute();
            }
            
            $medical_conditions = sanitize_input($_POST['medical_conditions']);
            $physical_limitations = sanitize_input($_POST['physical_limitations']);
            $fitness_goals = sanitize_input($_POST['fitness_goals']);
            $current_medications = sanitize_input($_POST['current_medications']);
            
            if ($health) {
                $health_stmt = $conn->prepare("UPDATE health_and_fitness_info SET medical_condition_allergies=?, physical_limitations=?, fitness_goals=?, current_medications=? WHERE member_id=?");
                $health_stmt->bind_param("ssssi", $medical_conditions, $physical_limitations, $fitness_goals, $current_medications, $member_id);
                $health_stmt->execute();
            } else {
                $health_stmt = $conn->prepare("INSERT INTO health_and_fitness_info (member_id, medical_condition_allergies, physical_limitations, fitness_goals, current_medications) VALUES (?, ?, ?, ?, ?)");
                $health_stmt->bind_param("issss", $member_id, $medical_conditions, $physical_limitations, $fitness_goals, $current_medications);
                $health_stmt->execute();
            }
            
            log_activity('Edit Member', 'Member updated: ' . $first_name . ' ' . $last_name, $_SESSION['admin_id']);
            
            $success = true;
            $success_message = 'Member information for ' . $first_name . ' ' . $last_name . ' has been successfully updated!';
            
            $member_query->execute();
            $member = $member_query->get_result()->fetch_assoc();
        } else {
            $error = 'Failed to update member: ' . $conn->error;
        }
    }
}

// Set page title
$page_title = "Edit Member";

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

        .main-content { background: var(--color-bg); }
        body { background: var(--color-bg); color: var(--color-text); }

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

        .page-title-row h1 i { color: var(--color-primary); margin-right: 8px; }

        .form-card {
            background: var(--color-surface);
            border-radius: 14px;
            padding: 28px;
            border: 1px solid var(--color-border);
            margin-bottom: 20px;
            transition: border-color 0.3s ease;
        }

        .form-card:hover { border-color: rgba(204, 28, 28, 0.25); }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--color-primary);
        }

        .section-header i { font-size: 22px; color: var(--color-primary); }

        .section-header h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--color-white);
            letter-spacing: 0.5px;
            margin: 0;
        }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

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

        .form-control::placeholder { color: var(--color-muted); }

        .form-control:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(204, 28, 28, 0.12);
        }

        /* Phone validation states */
        .form-control.phone-invalid {
            border-color: #FF5555 !important;
            box-shadow: 0 0 0 3px rgba(204, 28, 28, 0.2) !important;
        }

        .form-control.phone-valid {
            border-color: #22c55e !important;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12) !important;
        }

        select.form-control option { background: var(--color-surface-2); color: var(--color-white); }
        textarea.form-control { resize: vertical; }
        .form-group { margin-bottom: 18px; }

        /* Phone hint */
        .phone-hint {
            font-size: 0.75rem;
            margin-top: 5px;
            color: var(--color-muted);
            transition: color 0.3s ease;
        }

        .phone-hint.invalid { color: #FF5555; }
        .phone-hint.valid   { color: #22c55e; }

        .photo-upload { text-align: center; margin-bottom: 24px; }

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

        .photo-preview img { width: 100%; height: 100%; object-fit: cover; }
        .photo-preview i { font-size: 48px; color: #FFFFFF; }

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

        .upload-btn:hover { background: rgba(204, 28, 28, 0.28); border-color: rgba(204, 28, 28, 0.5); }
        .upload-btn input { display: none; }

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

        .checkbox-group input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; accent-color: var(--color-navy); }
        .checkbox-group label { cursor: pointer; font-weight: bold; color: #4A7AFF; font-size: 0.9rem; }
        .checkbox-group label i { margin-right: 6px; }

        .form-actions { display: flex; gap: 15px; }

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

        .btn-cancel:hover { background: #222222; border-color: #444444; }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.75);
            z-index: 10001;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.active { display: flex; }

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

        .modal-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }

        .modal-icon {
            width: 60px; height: 60px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px;
        }

        .modal-icon.success { background: rgba(26, 58, 143, 0.2); color: #4A7AFF; border: 1px solid rgba(26, 58, 143, 0.3); }
        .modal-icon.error   { background: rgba(204, 28, 28, 0.15); color: #FF5555; border: 1px solid rgba(204, 28, 28, 0.25); }

        .modal-title h3 { margin: 0; font-size: 22px; font-weight: bold; color: var(--color-white); }

        .modal-body { margin-bottom: 25px; color: var(--color-text); line-height: 1.6; font-size: 15px; }

        .modal-footer { display: flex; gap: 10px; }

        .modal-btn {
            flex: 1; padding: 12px 20px;
            border: none; border-radius: 8px;
            font-weight: bold; cursor: pointer;
            transition: all 0.3s ease; font-size: 14px;
        }

        .modal-btn.success { background: var(--color-primary); color: #FFFFFF; }
        .modal-btn.success:hover { background: var(--color-primary-dk); }
        .modal-btn.secondary { background: var(--color-surface-2); color: var(--color-text); border: 1px solid var(--color-border); }
        .modal-btn.secondary:hover { background: #252525; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

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
            <h1><i class="fas fa-user-edit"></i> EDIT MEMBER</h1>
        </div>
        
        <form method="POST" enctype="multipart/form-data" id="editMemberForm">
            
            <!-- Personal Information -->
            <div class="form-card">
                <div class="section-header">
                    <i class="fas fa-user"></i>
                    <h3>PERSONAL INFORMATION</h3>
                </div>
                
                <div class="photo-upload">
                    <div class="photo-preview" id="photo-preview">
                        <?php if ($member['photo']): ?>
                            <img src="../assets/uploads/<?php echo $member['photo']; ?>">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <label class="upload-btn">
                        <i class="fas fa-camera"></i> Change Photo
                        <input type="file" name="photo" accept="image/*" onchange="previewPhoto(event)">
                    </label>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" value="<?php echo $member['first_name']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo $member['last_name']; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $member['email']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number *</label>
                        <input type="tel" name="phone_number" id="phone_number" class="form-control"
                               placeholder="09XXXXXXXXX" maxlength="11"
                               value="<?php echo $member['phone_number']; ?>" required>
                        <div class="phone-hint" id="phone_hint">11 digits required (e.g. 09XXXXXXXXX)</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Gender *</label>
                        <select name="gender" class="form-control" required>
                            <option value="Male"   <?php echo $member['gender'] == 'Male'   ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $member['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other"  <?php echo $member['gender'] == 'Other'  ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth *</label>
                        <input type="date" name="date_of_birth" class="form-control" value="<?php echo $member['date_of_birth']; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="3"><?php echo $member['address']; ?></textarea>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_student" id="is_student" <?php echo $member['is_student'] ? 'checked' : ''; ?>>
                    <label for="is_student">
                        <i class="fas fa-graduation-cap"></i> This member is a STUDENT
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
                        <input type="text" name="emergency_name" class="form-control"
                               value="<?php echo $emergency ? $emergency['emergency_contact_name'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Phone</label>
                        <input type="tel" name="emergency_phone" id="emergency_phone" class="form-control"
                               placeholder="09XXXXXXXXX" maxlength="11"
                               value="<?php echo $emergency ? $emergency['emergency_contact_phone'] : ''; ?>">
                        <div class="phone-hint" id="emergency_phone_hint">11 digits required (e.g. 09XXXXXXXXX)</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Relationship</label>
                        <input type="text" name="emergency_relationship" class="form-control"
                               value="<?php echo $emergency ? $emergency['emergency_contact_relationship'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Address</label>
                        <input type="text" name="emergency_address" class="form-control"
                               value="<?php echo $emergency ? $emergency['emergency_contact_address'] : ''; ?>">
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
                    <textarea name="medical_conditions" class="form-control" rows="2"><?php echo $health ? $health['medical_condition_allergies'] : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Physical Limitations</label>
                    <textarea name="physical_limitations" class="form-control" rows="2"><?php echo $health ? $health['physical_limitations'] : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Fitness Goals</label>
                    <textarea name="fitness_goals" class="form-control" rows="2"><?php echo $health ? $health['fitness_goals'] : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Current Medications</label>
                    <textarea name="current_medications" class="form-control" rows="2"><?php echo $health ? $health['current_medications'] : ''; ?></textarea>
                </div>
            </div>
            
            <!-- Submit Buttons -->
            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> UPDATE MEMBER
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
                    <h3>Updated!</h3>
                </div>
            </div>
            <div class="modal-body">
                <p id="successMessage"><?php echo $success_message; ?></p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn success" onclick="closeSuccessModal()">
                    <i class="fas fa-check"></i> Continue
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
                <button class="modal-btn secondary" onclick="closeErrorModal()">OK</button>
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

            // Strip non-numeric, enforce 11-digit max
            input.addEventListener('input', function () {
                let digits = this.value.replace(/\D/g, '');
                if (digits.length > 11) digits = digits.slice(0, 11);
                this.value = digits;
                updatePhoneState(this, hint, required);
            });

            input.addEventListener('blur', function () {
                updatePhoneState(this, hint, required);
            });

            // Validate existing value on page load
            if (input.value.length > 0) updatePhoneState(input, hint, required);
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
        document.getElementById('editMemberForm').addEventListener('submit', function (e) {
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

        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }

        function closeErrorModal() {
            document.getElementById('errorModal').classList.remove('active');
        }

        " . ($success ? "document.getElementById('successModal').classList.add('active');" : "") . "
        " . ($error   ? "document.getElementById('errorModal').classList.add('active');"  : "") . "

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                closeSuccessModal();
                closeErrorModal();
            }
        });
    </script>
    ";
    
    include '../includes/footer.php'; 
    ?>