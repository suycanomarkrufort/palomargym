<?php
require_once '../config/database.php';

if (!is_logged_in()) {
    redirect('../login.php');
}

$user_id = get_user_id();
$is_staff_user = is_staff();
$table = $is_staff_user ? 'staff' : 'admin';

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

$members_query = "SELECT * FROM member ORDER BY first_name ASC";
$members_result = $conn->query($members_query);

$page_title = "Asset ID Maker";
include '../includes/header.php';
?>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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

        /* ==========================================
           PAGE LAYOUT
           ========================================== */
        .id-maker-container {
            padding: 20px;
            padding-bottom: 100px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .id-maker-container h1 {
            color: var(--color-white);
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .id-maker-container h1 i,
        .id-maker-container h3 i {
            color: var(--color-primary);
        }

        .id-maker-container h3 {
            color: var(--color-white);
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .id-maker-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }

        /* ==========================================
           FORM SECTION
           ========================================== */
        .form-section {
            background: var(--color-surface);
            padding: 25px;
            border-radius: 16px;
            border: 1px solid var(--color-border);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
        }

        .form-section .form-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--color-muted);
            margin-bottom: 8px;
        }

        .form-section .form-label i {
            color: var(--color-primary);
            margin-right: 4px;
        }

        .form-section .form-control {
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

        .form-section .form-control:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(204, 28, 28, 0.15);
        }

        .form-section .form-control[readonly] {
            background: #111111;
            color: var(--color-muted);
            cursor: default;
        }

        .form-section .form-control::placeholder {
            color: #444;
        }

        .form-section .form-group {
            margin-bottom: 18px;
        }

        /* Divider inside form */
        .form-divider {
            border: none;
            border-top: 1px solid var(--color-border);
            margin: 24px 0 20px;
        }

        /* Action Buttons */
        .btn-save-asset {
            width: 100%;
            margin-bottom: 10px;
            padding: 13px 20px;
            background: var(--color-surface-2);
            color: var(--color-text);
            border: 1px solid var(--color-border);
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: 0.5px;
        }

        .btn-save-asset:hover {
            background: #252525;
            border-color: rgba(204, 28, 28, 0.35);
            color: var(--color-white);
        }

        .btn-print-card {
            width: 100%;
            padding: 13px 20px;
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
            letter-spacing: 0.5px;
        }

        .btn-print-card:hover {
            background: var(--color-primary-dk);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(204, 28, 28, 0.4);
        }

        /* ==========================================
           PREVIEW SECTION
           ========================================== */
        .preview-section {
            position: sticky;
            top: 20px;
        }

        /* Card toggle buttons */
        .card-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .toggle-btn {
            flex: 1;
            padding: 12px;
            border: 1px solid var(--color-border);
            background: var(--color-surface-2);
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--color-muted);
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        
        .toggle-btn.active {
            background: rgba(204, 28, 28, 0.15);
            border-color: rgba(204, 28, 28, 0.5);
            color: #FF5555;
        }

        .toggle-btn:hover:not(.active) {
            border-color: rgba(204, 28, 28, 0.3);
            color: var(--color-text);
        }

        /* Live preview label */
        .live-preview-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
            color: var(--color-muted);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .live-preview-label i {
            color: var(--color-primary);
        }

        /* ==========================================
           MEMBERSHIP CARD (the physical card design)
           NOTE: Card itself keeps dark branding theme
           ========================================== */
        .membership-card {
            width: 400px;
            height: 250px;
            border-radius: 15px;
            padding: 20px;
            position: relative;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.6);
            margin: 0 auto;
        }
        
        .card-front {
            background: linear-gradient(135deg, #000000 0%, #2a2a2a 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .card-back {
            background: white;
            border: 3px solid #e0e0e0;
            display: none;
        }
        
        .card-back.active {
            display: block;
        }
        
        .card-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .qr-code-container {
            width: 100px;
            height: 100px;
            background: white;
            padding: 8px;
            border-radius: 10px;
        }
        
        .gym-logo {
            text-align: right;
        }
        
        .gym-logo h2 {
            font-size: 20px;
            margin-bottom: 3px;
        }
        
        /* Status badge on card — keep red brand */
        .status-badge-card {
            background: var(--color-primary);
            color: white;
            padding: 4px 12px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
            margin-top: 5px;
        }
        
        .card-footer {
            border-top: 2px solid rgba(255, 255, 255, 0.2);
            padding-top: 12px;
        }
        
        .member-type {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 5px;
        }
        
        .member-name-card {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .card-details {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.8);
        }
        
        /* Card Back — keep white (it's a physical card design) */
        .card-back-content {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .terms-header {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            color: var(--color-primary);
        }
        
        .terms-header i {
            font-size: 20px;
            margin-right: 8px;
        }
        
        .terms-list {
            flex: 1;
            font-size: 11px;
            color: #555;
            line-height: 1.6;
        }
        
        .terms-list div {
            display: flex;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        
        .terms-list i {
            color: var(--color-primary);
            margin-right: 8px;
            margin-top: 3px;
        }
        
        .card-footer-back {
            border-top: 1px solid #e0e0e0;
            padding-top: 10px;
            text-align: center;
            font-size: 10px;
            color: #999;
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
        @media (max-width: 1024px) {
            .id-maker-grid { grid-template-columns: 1fr; }
            .preview-section { position: relative; top: 0; }
        }
        
        @media (max-width: 768px) {
            .membership-card { width: 100%; max-width: 350px; height: 220px; }
        }
    </style>
    
    <div class="id-maker-container">
        
        <h1 style="margin-bottom: 10px;">
            <i class="fas fa-id-card"></i> ASSET ID MAKER
        </h1>
        
        <div class="id-maker-grid">
            
            <!-- Form Section -->
            <div class="form-section">
                
                <h3 style="margin-bottom: 20px;">
                    <i class="fas fa-search"></i> MEMBER DISCOVERY
                </h3>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i> TYPE NAME TO SEARCH DATABASE...
                    </label>
                    <input type="text" class="form-control" id="member-search" placeholder="Search member name..." list="member-list">
                    <datalist id="member-list">
                        <?php while($member = $members_result->fetch_assoc()): ?>
                            <option value="<?php echo $member['first_name'] . ' ' . $member['last_name']; ?>" data-id="<?php echo $member['id']; ?>">
                        <?php endwhile; ?>
                    </datalist>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-phone"></i> CONTACT REFERENCE
                    </label>
                    <input type="text" class="form-control" id="contact-ref" placeholder="09XX-XXX-XXXX" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-shield-alt"></i> MEMBERSHIP TIER
                    </label>
                    <input type="text" class="form-control" id="membership-tier" placeholder="---" readonly>
                </div>
                
                <hr class="form-divider">

                <button class="btn-save-asset" onclick="saveAsset()">
                    <i class="fas fa-cloud-upload-alt"></i> SAVE ASSET INFO
                </button>
                <button class="btn-print-card" onclick="printCard()">
                    <i class="fas fa-print"></i> PRINT IDENTITY CARD
                </button>
                
            </div>
            
            <!-- Preview Section -->
            <div class="preview-section">
                
                <div class="card-toggle">
                    <button class="toggle-btn active" onclick="toggleCard('front')">
                        <i class="fas fa-id-badge"></i> FRONT
                    </button>
                    <button class="toggle-btn" onclick="toggleCard('back')">
                        <i class="fas fa-id-card"></i> BACK
                    </button>
                </div>
                
                <div class="live-preview-label">
                    <i class="fas fa-eye"></i>
                    <span>LIVE PREVIEW MODE</span>
                </div>
                
                <!-- Card Front -->
                <div class="membership-card card-front" id="card-front">
                    <div class="card-header-top">
                        <div class="qr-code-container">
                            <div id="qrcode" style="width: 100%; height: 100%;"></div>
                        </div>
                        <div class="gym-logo">
                            <h2>WOLF PALOMAR</h2>
                            <span class="status-badge-card">● WAITING...</span>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <div class="member-type">MEMBERSHIP HOLDER</div>
                        <div class="member-name-card">SELECT MEMBER</div>
                        <div class="card-details">
                            <div>
                                <i class="fas fa-calendar"></i>
                                <span>ISSUED: Jan 31, 2026</span>
                            </div>
                            <div>
                                <span style="font-size: 10px;">ADMINISTRATOR</span><br>
                                <strong style="font-size: 10px;">ADMINISTRATOR</strong>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Card Back -->
                <div class="membership-card card-back" id="card-back">
                    <div class="card-back-content">
                        <div class="terms-header">
                            <i class="fas fa-shield-alt"></i>
                            <h3 style="font-size: 14px; font-weight: bold; color: #000;">MEMBERSHIP TERMS</h3>
                        </div>
                        
                        <div class="terms-list">
                            <div>
                                <i class="fas fa-check-square"></i>
                                <span>This document must be presented at the terminal upon every gym entry.</span>
                            </div>
                            <div>
                                <i class="fas fa-check-square"></i>
                                <span>QR scanning is mandatory for digital logbook authentication.</span>
                            </div>
                            <div>
                                <i class="fas fa-check-square"></i>
                                <span>Non-transferable. Misuse may result in membership revocation.</span>
                            </div>
                        </div>
                        
                        <div class="card-footer-back">
                            <div style="display: flex; justify-content: center; align-items: center; gap: 5px; margin-bottom: 5px;">
                                <i class="fas fa-qrcode" style="font-size: 16px;"></i>
                            </div>
                            <div>WOLF PALOMAR DIGITAL ASSET V3.0</div>
                        </div>
                    </div>
                </div>
                
            </div>
            
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
    
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/bottom-nav.php'; ?>
    
    <?php 
    $extra_scripts = "
    <script src='https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js'></script>
    <script>
        let qrcode = null;
        let selectedMember = null;

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

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('alert-modal')) {
                closeSuccessModal();
                closeErrorModal();
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            qrcode = new QRCode(document.getElementById('qrcode'), {
                text: 'WOLF-PALOMAR-GYM',
                width: 84,
                height: 84,
                colorDark: '#000000',
                colorLight: '#ffffff',
            });
        });
        
        function toggleCard(side) {
            const frontCard = document.getElementById('card-front');
            const backCard = document.getElementById('card-back');
            const buttons = document.querySelectorAll('.toggle-btn');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            
            if (side === 'front') {
                frontCard.style.display = 'flex';
                backCard.style.display = 'none';
                buttons[0].classList.add('active');
            } else {
                frontCard.style.display = 'none';
                backCard.style.display = 'block';
                buttons[1].classList.add('active');
            }
        }
        
        document.getElementById('member-search').addEventListener('input', function(e) {
            const searchTerm = e.target.value;
            if (searchTerm.length > 2) {
                fetch('get-member.php?name=' + encodeURIComponent(searchTerm))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            selectedMember = data.member;
                            updateCardPreview(data.member);
                        }
                    });
            }
        });
        
        function updateCardPreview(member) {
            document.getElementById('qrcode').innerHTML = '';
            qrcode = new QRCode(document.getElementById('qrcode'), {
                text: member.qr_code,
                width: 84,
                height: 84,
                colorDark: '#000000',
                colorLight: '#ffffff',
            });
            
            document.querySelector('.member-name-card').textContent = member.first_name.toUpperCase() + ' ' + member.last_name.toUpperCase();
            document.querySelector('.status-badge-card').textContent = member.membership_status || '● WAITING...';
            document.getElementById('contact-ref').value = member.phone_number || 'N/A';
            document.getElementById('membership-tier').value = member.membership_plan || '---';
            
            document.querySelector('.member-type').textContent = member.is_student ? 'STUDENT MEMBER' : 'MEMBERSHIP HOLDER';
        }
        
        function saveAsset() {
            if (!selectedMember) {
                showErrorModal('Please select a member first');
                return;
            }
            
            showLoading();
            
            fetch('save-asset.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'member_id=' + selectedMember.id
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showSuccessModal('Asset information saved successfully! Redirecting to ID Vault...');
                    setTimeout(() => { window.location.href = 'id-vault.php'; }, 1500);
                } else {
                    showErrorModal(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                showErrorModal('Error saving asset');
            });
        }
        
        function printCard() {
            if (!selectedMember) {
                showErrorModal('Please select a member first');
                return;
            }
            
            const printWindow = window.open('', '', 'height=600,width=800');
            const cardFront = document.getElementById('card-front').cloneNode(true);
            const cardBack = document.getElementById('card-back').cloneNode(true);
            cardBack.style.display = 'flex';
            
            printWindow.document.write('<html><head><title>Print Membership Card - WOLF PALOMAR</title>');
            printWindow.document.write('<meta charset=\"UTF-8\">');
            printWindow.document.write('<link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css\">');
            printWindow.document.write('<style>');
            printWindow.document.write('@page { size: landscape; margin: 10mm; }');
            printWindow.document.write('* { margin: 0; padding: 0; box-sizing: border-box; }');
            printWindow.document.write('body { font-family: Arial, sans-serif; background: white; }');
            printWindow.document.write('.print-container { width: 100%; display: flex; flex-direction: column; align-items: center; gap: 15mm; padding: 10mm; }');
            printWindow.document.write('.membership-card { width: 85.6mm; height: 53.98mm; border-radius: 3mm; padding: 5mm; position: relative; margin: 0; page-break-inside: avoid; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }');
            printWindow.document.write('.card-front { background: linear-gradient(135deg, #000000 0%, #2a2a2a 100%); color: white; display: flex !important; flex-direction: column; justify-content: space-between; }');
            printWindow.document.write('.card-back { background: white; border: 2px solid #e0e0e0; display: flex !important; }');
            printWindow.document.write('.card-header-top { display: flex; justify-content: space-between; align-items: flex-start; }');
            printWindow.document.write('.qr-code-container { width: 22mm; height: 22mm; background: white; padding: 2mm; border-radius: 2mm; }');
            printWindow.document.write('.qr-code-container canvas, .qr-code-container img { width: 100% !important; height: 100% !important; display: block; }');
            printWindow.document.write('.gym-logo { text-align: right; }');
            printWindow.document.write('.gym-logo h2 { font-size: 13pt; margin: 0 0 1mm 0; font-weight: bold; letter-spacing: 0.5px; }');
            printWindow.document.write('.status-badge-card { background: #CC1C1C; color: white; padding: 1mm 3mm; border-radius: 2mm; font-size: 7pt; font-weight: bold; display: inline-block; margin-top: 1mm; }');
            printWindow.document.write('.card-footer { border-top: 1px solid rgba(255,255,255,0.3); padding-top: 3mm; margin-top: auto; }');
            printWindow.document.write('.member-type { font-size: 7pt; color: rgba(255,255,255,0.7); margin-bottom: 1mm; text-transform: uppercase; }');
            printWindow.document.write('.member-name-card { font-size: 11pt; font-weight: bold; margin-bottom: 2mm; }');
            printWindow.document.write('.card-details { display: flex; justify-content: space-between; font-size: 6pt; color: rgba(255,255,255,0.8); align-items: flex-end; }');
            printWindow.document.write('.card-back-content { height: 100%; display: flex; flex-direction: column; width: 100%; }');
            printWindow.document.write('.terms-header { display: flex; align-items: center; margin-bottom: 3mm; padding-bottom: 2mm; border-bottom: 2px solid #CC1C1C; }');
            printWindow.document.write('.terms-header i { font-size: 12pt; margin-right: 2mm; color: #CC1C1C; }');
            printWindow.document.write('.terms-header h3 { font-size: 9pt; font-weight: bold; color: #000; margin: 0; }');
            printWindow.document.write('.terms-list { flex: 1; font-size: 6.5pt; color: #333; line-height: 1.5; }');
            printWindow.document.write('.terms-list div { display: flex; align-items: flex-start; margin-bottom: 2mm; }');
            printWindow.document.write('.terms-list i { color: #CC1C1C; margin-right: 2mm; flex-shrink: 0; font-size: 6pt; margin-top: 0.5mm; }');
            printWindow.document.write('.terms-list span { flex: 1; }');
            printWindow.document.write('.card-footer-back { border-top: 1px solid #e0e0e0; padding-top: 2mm; text-align: center; font-size: 5.5pt; color: #999; margin-top: auto; }');
            printWindow.document.write('.card-footer-back i { font-size: 9pt; color: #CC1C1C; }');
            printWindow.document.write('@media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .membership-card { box-shadow: none !important; } }');
            printWindow.document.write('</style></head><body>');
            printWindow.document.write('<div class=\"print-container\">');
            printWindow.document.write('<div style=\"text-align: center; margin-bottom: 5mm;\"><h3 style=\"font-size: 12pt; color: #333;\">WOLF PALOMAR MEMBERSHIP CARD</h3><p style=\"font-size: 8pt; color: #666;\">Standard CR80 Format (85.6mm x 53.98mm)</p></div>');
            printWindow.document.write(cardFront.outerHTML);
            printWindow.document.write(cardBack.outerHTML);
            printWindow.document.write('<div style=\"text-align: center; margin-top: 5mm; font-size: 7pt; color: #999;\">Cut along the card edges • Keep both sides for reference</div>');
            printWindow.document.write('</div></body></html>');
            printWindow.document.close();
            
            setTimeout(() => {
                printWindow.focus();
                printWindow.print();
            }, 1000);
        }
    </script>
    ";
    
    include '../includes/footer.php'; 
    ?>