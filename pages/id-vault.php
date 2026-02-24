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

$members_query = "
    SELECT m.*, 
    (SELECT mp.membership_name FROM membership ms 
     INNER JOIN membership_plans mp ON ms.membership_plan_id = mp.id 
     WHERE ms.member_id = m.id AND ms.status = 'Active' 
     ORDER BY ms.end_date DESC LIMIT 1) as membership_plan
    FROM member m 
    WHERE m.asset_saved = 1 
    ORDER BY m.first_name ASC
";
$members_result = $conn->query($members_query);

$page_title = "ID Vault Assets";
include '../includes/header.php';
?>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
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
        .vault-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            padding-bottom: 100px;
        }
        
        .vault-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .vault-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--color-white);
            letter-spacing: 1px;
        }

        .vault-header h1 i {
            color: var(--color-primary);
        }
        
        .vault-header p {
            color: var(--color-muted);
            font-size: 14px;
        }

        /* ==========================================
           ASSETS GRID
           ========================================== */
        .assets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        /* Asset Card */
        .asset-card {
            background: var(--color-surface);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid var(--color-border);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
        }
        
        .asset-card:hover {
            border-color: rgba(204, 28, 28, 0.4);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 20px rgba(204, 28, 28, 0.1);
            transform: translateY(-5px);
        }
        
        /* QR Code display box */
        .qr-display {
            width: 160px;
            height: 160px;
            margin: 0 auto 20px;
            padding: 10px;
            background: var(--color-white);
            border: 1px solid var(--color-border);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .qr-display img {
            max-width: 100%;
            height: auto;
        }
        
        /* Member name */
        .member-name {
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 5px;
            text-transform: uppercase;
            color: var(--color-white);
        }

        /* Member ID sub-text */
        .member-id-text {
            color: var(--color-muted);
            font-size: 12px;
            margin-bottom: 20px;
        }

        /* Status badge — red brand */
        .status-badge {
            font-size: 11px;
            font-weight: 700;
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.3);
            padding: 4px 12px;
            border-radius: 10px;
            display: inline-block;
            margin-bottom: 15px;
        }

        /* Registered (no active membership) — silver */
        .status-badge.registered {
            background: rgba(176, 176, 176, 0.1);
            color: var(--color-silver);
            border-color: rgba(176, 176, 176, 0.2);
        }

        /* Download button — dark surface style */
        .download-btn {
            width: 100%;
            padding: 12px;
            background: var(--color-surface-2);
            color: var(--color-text);
            border: 1px solid var(--color-border);
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            letter-spacing: 0.3px;
        }
        
        .download-btn:hover {
            background: rgba(26, 58, 143, 0.2);
            border-color: rgba(26, 58, 143, 0.4);
            color: #4A7AFF;
        }

        /* ==========================================
           EMPTY STATE
           ========================================== */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 20px;
            background: var(--color-surface);
            border-radius: 20px;
            border: 1px solid var(--color-border);
        }
        
        .empty-state i {
            font-size: 60px;
            color: var(--color-border);
            margin-bottom: 20px;
            display: block;
        }

        .empty-state h3 {
            color: var(--color-muted);
            margin-bottom: 10px;
            font-size: 1.2rem;
        }

        .empty-state p {
            color: var(--color-muted);
            opacity: 0.7;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }

        /* Open ID Maker button */
        .btn-open-maker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 25px;
            background: var(--color-primary);
            color: var(--color-white);
            border: none;
            border-radius: 10px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-open-maker:hover {
            background: var(--color-primary-dk);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(204, 28, 28, 0.4);
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

        /* Cancel — dark surface */
        .alert-btn.secondary {
            background: var(--color-surface-2);
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }

        .alert-btn.secondary:hover {
            background: #252525;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
    </style>
    
    <div class="vault-container">
        <div class="vault-header">
            <h1><i class="fas fa-shield-alt"></i> ID VAULT</h1>
            <p>Authorized and saved QR assets are stored here securely.</p>
        </div>
        
        <div class="assets-grid">
            <?php if ($members_result->num_rows > 0): ?>
                <?php while($member = $members_result->fetch_assoc()): ?>
                    <?php 
                        $secret = defined('SYSTEM_SECRET_KEY') ? SYSTEM_SECRET_KEY : 'wolf_secret_key';
                        $m_id = $member['id'];
                        $m_hash = substr(hash_hmac('sha256', $m_id, $secret), 0, 8);
                        $vault_token = base64_encode(str_rot13($m_id . '|' . $m_hash));
                        $is_active = !empty($member['membership_plan']);
                    ?>
                    <div class="asset-card" id="card-<?php echo $member['id']; ?>">

                        <div class="qr-display" id="qr-<?php echo $member['id']; ?>"></div>
                        
                        <span class="status-badge <?php echo $is_active ? '' : 'registered'; ?>">
                            <?php echo $is_active ? 'ACTIVE MEMBER' : 'REGISTERED'; ?>
                        </span>
                        
                        <div class="member-name"><?php echo $member['first_name'] . ' ' . $member['last_name']; ?></div>
                        <div class="member-id-text">ID: <?php echo $member['member_id']; ?></div>
                        
                        <button class="download-btn" onclick="downloadAsset('<?php echo $member['id']; ?>', '<?php echo addslashes($member['first_name']); ?>')">
                            <i class="fas fa-download"></i> DOWNLOAD PNG
                        </button>
                    </div>
                    
                    <script>
                        new QRCode(document.getElementById("qr-<?php echo $member['id']; ?>"), {
                            text: "<?php echo $vault_token; ?>",
                            width: 140,
                            height: 140,
                            colorDark : "#000000",
                            colorLight : "#ffffff",
                            correctLevel : QRCode.CorrectLevel.H
                        });
                    </script>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>Vault is Empty</h3>
                    <p>No assets saved. Go to ID Maker to save a member's identity card.</p>
                    <a href="id-maker.php" class="btn-open-maker">
                        <i class="fas fa-id-card"></i> Open ID Maker
                    </a>
                </div>
            <?php endif; ?>
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
    <script>
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

        function downloadAsset(id, name) {
            showLoading();
            const qrElement = document.getElementById('qr-' + id);
            
            html2canvas(qrElement, {
                backgroundColor: '#ffffff',
                scale: 5
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'QR_' + name.toUpperCase() + '_ASSET.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                hideLoading();
                showSuccessModal('QR Asset downloaded!');
            }).catch(err => {
                hideLoading();
                showErrorModal('Failed to process image');
            });
        }

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