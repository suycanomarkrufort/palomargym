<?php
require_once '../config/database.php';

// ADMIN ONLY - Staff cannot access recycle bin
// Only admins can view, restore, and permanently delete members
if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

// Get user info
$user_id = get_user_id();
$user_query = $conn->prepare("SELECT * FROM admin WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$admin = $user_query->get_result()->fetch_assoc();

// Get today's date
$today = date('Y-m-d');

// Get today's revenue for sidebar
$revenue_query = $conn->prepare("SELECT COALESCE(SUM(fee_charged), 0) as total FROM attendance WHERE date = ?");
$revenue_query->bind_param("s", $today);
$revenue_query->execute();
$revenue_result = $revenue_query->get_result()->fetch_assoc();
$today_revenue = $revenue_result['total'];

// Get daily goal for sidebar
$daily_goal = get_setting('daily_goal');
$goal_percentage = ($daily_goal > 0) ? round(($today_revenue / $daily_goal) * 100) : 0;

// Set staff_user variable for sidebar compatibility
$is_staff_user = false;

// Get all deleted members
$deleted_query = "SELECT m.*, 
    (SELECT COUNT(*) FROM attendance WHERE user_id = m.id) as total_visits,
    (SELECT membership_name FROM membership_plans mp 
     INNER JOIN membership ms ON mp.id = ms.membership_plan_id 
     WHERE ms.member_id = m.id AND ms.status = 'Active' LIMIT 1) as current_plan,
    DATEDIFF(NOW(), m.deleted_at) as days_in_bin
    FROM member m 
    WHERE m.deleted_at IS NOT NULL
    ORDER BY m.deleted_at DESC";
$deleted_result = $conn->query($deleted_query);

// Set page title
$page_title = "Recycle Bin";

// Include header
include '../includes/header.php';
?>
    
    <style>
        /* =============================================
           WOLF PALOMAR FITNESS GYM — COLOR PALETTE
           Based on logo: Black bg, Red shield,
           Navy blue center, White text, Silver accents
           =============================================
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
            background: var(--color-bg);
            color: var(--color-text);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h1 {
            color: var(--color-white);
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 1px;
            margin: 0;
        }

        .page-header h1 i {
            color: var(--color-primary);
            margin-right: 8px;
        }

        /* Back button — navy accent (matches members.php edit button style) */
        .back-btn {
            background: rgba(26, 58, 143, 0.15);
            color: #4A7AFF;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: bold;
            border: 1px solid rgba(26, 58, 143, 0.3);
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(26, 58, 143, 0.28);
            transform: translateX(-5px);
        }

        .search-box {
            display: flex;
            gap: 10px;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            flex: 1;
            background: var(--color-surface-2);
            border: 1px solid var(--color-border);
            color: var(--color-white);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .search-box input::placeholder {
            color: var(--color-muted);
        }

        .search-box input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(204, 28, 28, 0.12);
        }

        .search-box .btn-secondary {
            background: var(--color-primary);
            color: var(--color-white);
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .search-box .btn-secondary:hover {
            background: var(--color-primary-dk);
        }

        /* Member Card — dark surface, red-left border accent */
        .member-card {
            background: var(--color-surface);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid var(--color-border);
            border-left: 4px solid var(--color-primary);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            flex-wrap: wrap;
        }

        .member-card:hover {
            border-color: rgba(204, 28, 28, 0.5);
            border-left-color: var(--color-primary);
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.4), 0 0 16px rgba(204, 28, 28, 0.1);
            transform: translateY(-2px);
        }

        /* Avatar — dimmed red gradient (deleted = faded opacity) */
        .member-avatar-large {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(204, 28, 28, 0.3), rgba(160, 21, 21, 0.15));
            border: 2px solid rgba(204, 28, 28, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
            opacity: 0.75;
        }

        .member-avatar-large i {
            font-size: 28px;
            color: var(--color-primary);
        }

        .member-info {
            flex: 1;
            min-width: 200px;
        }

        /* Name — silver/muted to visually indicate deleted state */
        .member-name {
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 6px;
            color: var(--color-silver);
            word-break: break-word;
        }

        /* Deleted badge — muted red */
        .deleted-badge {
            background: rgba(204, 28, 28, 0.12);
            color: #FF5555;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            border: 1px solid rgba(204, 28, 28, 0.25);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Member ID badge — silver (wolf fur) */
        .badge-id {
            background: rgba(176, 176, 176, 0.1);
            color: var(--color-silver);
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 5px;
            border: 1px solid rgba(176, 176, 176, 0.2);
            display: inline-block;
        }

        /* Student badge — navy */
        .badge-student {
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 5px;
            border: 1px solid rgba(26, 58, 143, 0.35);
            display: inline-block;
        }

        .member-details {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 13px;
            color: var(--color-muted);
            margin-top: 8px;
        }

        .member-details i {
            color: var(--color-primary);
            margin-right: 4px;
        }

        .member-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 15px;
        }

        /* Restore — navy blue (matches edit button in members.php) */
        .action-btn.restore {
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            border: 1px solid rgba(26, 58, 143, 0.3);
        }

        .action-btn.restore:hover {
            background: rgba(26, 58, 143, 0.35);
            transform: scale(1.1);
        }

        /* Permanent delete — red (matches delete button in members.php) */
        .action-btn.permanent-delete {
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.25);
        }

        .action-btn.permanent-delete:hover {
            background: rgba(204, 28, 28, 0.28);
            transform: scale(1.1);
        }

        /* Empty bin — dark surface */
        .empty-bin {
            text-align: center;
            padding: 60px;
            background: var(--color-surface);
            border-radius: 14px;
            border: 1px solid var(--color-border);
        }

        .empty-bin i {
            font-size: 64px;
            color: var(--color-border);
            margin-bottom: 20px;
            display: block;
        }

        .empty-bin h3 {
            color: var(--color-muted);
            margin-bottom: 10px;
        }

        .empty-bin p {
            color: var(--color-muted);
            opacity: 0.7;
        }

        /* Info Banner — navy accent */
        .info-banner {
            background: rgba(26, 58, 143, 0.1);
            border-left: 4px solid var(--color-navy);
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .info-banner i {
            color: #4A7AFF;
            font-size: 20px;
            flex-shrink: 0;
        }

        .info-banner-text {
            flex: 1;
            color: var(--color-text);
            font-size: 14px;
        }

        .info-banner-text strong {
            color: var(--color-white);
        }

        /* Admin notice banner */
        .admin-notice {
            background: linear-gradient(135deg, rgba(204, 28, 28, 0.2) 0%, rgba(160, 21, 21, 0.1) 100%);
            border: 1px solid rgba(204, 28, 28, 0.3);
            color: var(--color-white);
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: bold;
        }

        .admin-notice i {
            color: var(--color-primary);
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
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
            max-width: 400px;
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
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        /* Restore modal icon — navy */
        .modal-icon.restore {
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            border: 1px solid rgba(26, 58, 143, 0.3);
        }

        /* Delete modal icon — red */
        .modal-icon.delete {
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.3);
        }

        /* Success modal icon — navy */
        .modal-icon.success {
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            border: 1px solid rgba(26, 58, 143, 0.3);
        }

        /* Error modal icon — red */
        .modal-icon.error {
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.25);
        }

        .modal-title {
            flex: 1;
        }

        .modal-title h3 {
            margin: 0;
            font-size: 20px;
            font-weight: bold;
            color: var(--color-white);
        }

        .modal-body {
            margin-bottom: 25px;
            color: var(--color-text);
            line-height: 1.6;
        }

        .modal-body strong {
            color: var(--color-white);
        }

        .modal-body .restore-note {
            color: #4A7AFF;
            margin-top: 10px;
            font-size: 0.9rem;
        }

        .modal-body .danger-note {
            color: #FF5555;
            margin-top: 10px;
            font-weight: bold;
            font-size: 0.9rem;
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
            font-size: 0.9rem;
        }

        /* Restore confirm — navy */
        .modal-btn.primary {
            background: var(--color-navy);
            color: var(--color-white);
        }

        .modal-btn.primary:hover {
            background: #243FA0;
        }

        /* Permanent delete — red */
        .modal-btn.danger {
            background: var(--color-primary);
            color: var(--color-white);
        }

        .modal-btn.danger:hover {
            background: var(--color-primary-dk);
        }

        /* Cancel — dark surface */
        .modal-btn.secondary {
            background: var(--color-surface-2);
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }

        .modal-btn.secondary:hover {
            background: #252525;
        }

        /* Success OK — navy */
        .modal-btn.success {
            background: var(--color-navy);
            color: var(--color-white);
        }

        .modal-btn.success:hover {
            background: #243FA0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: none;
                width: 100%;
            }

            .member-card {
                padding: 15px;
            }

            .member-avatar-large {
                width: 50px;
                height: 50px;
            }

            .member-name {
                font-size: 15px;
            }

            .member-details {
                flex-direction: column;
                gap: 5px;
            }

            .member-actions {
                width: 100%;
                margin-top: 15px;
                padding-top: 10px;
                border-top: 1px solid var(--color-border);
                justify-content: flex-end;
            }
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
    
    <div class="main-content">
        
        <!-- Admin Only Notice -->
        <div class="admin-notice">
            <i class="fas fa-shield-alt"></i>
            <span>ADMIN ONLY — Only administrators can view and restore deleted members</span>
        </div>
        
        <div class="page-header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <a href="members.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back
                </a>
                <h1><i class="fas fa-trash-restore"></i> RECYCLE BIN</h1>
            </div>
            <div class="search-box">
                <input type="text" class="form-control" placeholder="Search deleted members..." id="search-member" data-search=".member-card">
                <button class="btn btn-secondary">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>

        <?php if ($deleted_result->num_rows > 0): ?>
            <div class="info-banner">
                <i class="fas fa-info-circle"></i>
                <div class="info-banner-text">
                    <strong><?php echo $deleted_result->num_rows; ?> member(s)</strong> in recycle bin.
                    You can restore them or permanently delete them.
                </div>
            </div>

            <?php while($member = $deleted_result->fetch_assoc()): ?>
                <div class="member-card">
                    <div class="member-avatar-large">
                        <?php if ($member['photo']): ?>
                            <img src="<?php echo $base_url; ?>assets/uploads/<?php echo $member['photo']; ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; opacity: 0.7;">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="member-info">
                        <div class="member-name">
                            <?php echo strtoupper($member['first_name'] . ' ' . $member['last_name']); ?>
                        </div>
                        <div>
                            <span class="deleted-badge">
                                <i class="fas fa-trash"></i>
                                Deleted <?php echo $member['days_in_bin']; ?> day(s) ago
                            </span>
                            <span class="badge-id"># <?php echo $member['member_id']; ?></span>
                            <?php if ($member['is_student']): ?>
                                <span class="badge-student">STUDENT</span>
                            <?php endif; ?>
                        </div>
                        <div class="member-details">
                            <?php if ($member['email']): ?>
                                <span><i class="fas fa-envelope"></i> <?php echo $member['email']; ?></span>
                            <?php endif; ?>
                            <?php if ($member['phone_number']): ?>
                                <span><i class="fas fa-phone"></i> <?php echo $member['phone_number']; ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-calendar-check"></i> <?php echo $member['total_visits']; ?> visits</span>
                        </div>
                    </div>
                    
                    <div class="member-actions">
                        <button class="action-btn restore" onclick="confirmRestore(<?php echo $member['id']; ?>, '<?php echo addslashes($member['first_name'] . ' ' . $member['last_name']); ?>')" title="Restore member">
                            <i class="fas fa-undo"></i>
                        </button>
                        <button class="action-btn permanent-delete" onclick="confirmPermanentDelete(<?php echo $member['id']; ?>, '<?php echo addslashes($member['first_name'] . ' ' . $member['last_name']); ?>')" title="Delete permanently">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-bin">
                <i class="fas fa-trash"></i>
                <h3>Recycle bin is empty</h3>
                <p>No deleted members to show</p>
            </div>
        <?php endif; ?>
        
    </div>

    <!-- Restore Confirmation Modal -->
    <div class="modal-overlay" id="restoreModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon restore">
                    <i class="fas fa-undo"></i>
                </div>
                <div class="modal-title">
                    <h3>Restore Member</h3>
                </div>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to restore <strong id="restoreMemberName"></strong>?</p>
                <p class="restore-note">This member will be moved back to the active member list.</p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn secondary" onclick="closeRestoreModal()">Cancel</button>
                <button class="modal-btn primary" onclick="confirmRestoreAction()">
                    <i class="fas fa-undo"></i> Restore
                </button>
            </div>
        </div>
    </div>

    <!-- Permanent Delete Confirmation Modal -->
    <div class="modal-overlay" id="permanentDeleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon delete">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="modal-title">
                    <h3>Permanent Delete</h3>
                </div>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to <strong style="color: #FF5555;">PERMANENTLY DELETE</strong> <strong id="permanentDeleteMemberName"></strong>?</p>
                <p class="danger-note">⚠️ WARNING: This action CANNOT be undone! All member data, attendance records, and history will be permanently removed from the database.</p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn secondary" onclick="closePermanentDeleteModal()">Cancel</button>
                <button class="modal-btn danger" onclick="confirmPermanentDeleteAction()">
                    <i class="fas fa-times"></i> Delete Forever
                </button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal-overlay" id="successModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="modal-title">
                    <h3>Success!</h3>
                </div>
            </div>
            <div class="modal-body">
                <p id="successMessage"></p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn success" onclick="closeSuccessModal()">OK</button>
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
                <p id="errorMessage"></p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn secondary" onclick="closeErrorModal()">OK</button>
            </div>
        </div>
    </div>
    
    <?php include '../includes/sidebar.php'; ?>
    
    <?php include '../includes/bottom-nav.php'; ?>
    
    <?php 
    // Extra scripts for this page
    $extra_scripts = "
    <script>
        let restoreId = null;
        let restoreName = '';
        let permanentDeleteId = null;
        let permanentDeleteName = '';

        function confirmRestore(id, name) {
            restoreId = id;
            restoreName = name;
            document.getElementById('restoreMemberName').textContent = name;
            document.getElementById('restoreModal').classList.add('active');
        }

        function closeRestoreModal() {
            document.getElementById('restoreModal').classList.remove('active');
        }

        function confirmRestoreAction() {
            if (!restoreId) return;

            closeRestoreModal();
            showLoading();
            
            fetch('restore-member.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + restoreId
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showSuccessModal('Member restored successfully!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showErrorModal(data.message || 'Failed to restore member');
                }
                restoreId = null;
                restoreName = '';
            })
            .catch(error => {
                hideLoading();
                showErrorModal('An error occurred while restoring the member');
                console.error('Restore error:', error);
                restoreId = null;
                restoreName = '';
            });
        }

        function confirmPermanentDelete(id, name) {
            permanentDeleteId = id;
            permanentDeleteName = name;
            document.getElementById('permanentDeleteMemberName').textContent = name;
            document.getElementById('permanentDeleteModal').classList.add('active');
        }

        function closePermanentDeleteModal() {
            document.getElementById('permanentDeleteModal').classList.remove('active');
        }

        function confirmPermanentDeleteAction() {
            if (!permanentDeleteId) return;

            closePermanentDeleteModal();
            showLoading();
            
            fetch('permanent-delete-member.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + permanentDeleteId
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showSuccessModal('Member permanently deleted!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showErrorModal(data.message || 'Failed to delete member permanently');
                }
                permanentDeleteId = null;
                permanentDeleteName = '';
            })
            .catch(error => {
                hideLoading();
                showErrorModal('An error occurred while deleting the member');
                console.error('Permanent delete error:', error);
                permanentDeleteId = null;
                permanentDeleteName = '';
            });
        }

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

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                closeRestoreModal();
                closePermanentDeleteModal();
                closeSuccessModal();
                closeErrorModal();
            }
        });
    </script>
    ";
    
    include '../includes/footer.php'; 
    ?>