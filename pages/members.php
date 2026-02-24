<?php
require_once '../config/database.php';

// Check if user is logged in
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

// Get today's date
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

// Get count of deleted members for recycle bin badge
$deleted_count_query = "SELECT COUNT(*) as count FROM member WHERE deleted_at IS NOT NULL";
$deleted_count_result = $conn->query($deleted_count_query);
$deleted_count = $deleted_count_result->fetch_assoc()['count'];

// Get all members (excluding deleted ones)
$members_query = "SELECT m.*, 
    (SELECT COUNT(*) FROM attendance WHERE user_id = m.id) as total_visits,
    (SELECT membership_name FROM membership_plans mp 
     INNER JOIN membership ms ON mp.id = ms.membership_plan_id 
     WHERE ms.member_id = m.id AND ms.status = 'Active' LIMIT 1) as current_plan
    FROM member m 
    WHERE m.deleted_at IS NULL
    ORDER BY m.created_at DESC";
$members_result = $conn->query($members_query);

// Set page title
$page_title = "Member Database";

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
            background: var(--color-bg);
            color: var(--color-text);
        }

        /* Base Styles */
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
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-right {
            display: flex; 
            align-items: center; 
            gap: 15px;
            flex: 1;
            justify-content: flex-end;
        }
        
        /* Recycle bin — amber/orange kept as warning indicator */
        .recycle-bin-btn {
            background: rgba(204, 28, 28, 0.12);
            color: var(--color-primary);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: all 0.3s ease;
            position: relative;
            font-size: 20px;
            flex-shrink: 0;
            border: 1px solid rgba(204, 28, 28, 0.25);
        }
        
        .recycle-bin-btn:hover {
            background: rgba(204, 28, 28, 0.22);
            transform: scale(1.1);
        }
        
        .recycle-bin-badge {
            background: var(--color-primary);
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 10px;
            min-width: 18px;
            text-align: center;
            position: absolute;
            top: -5px;
            right: -5px;
            font-weight: bold;
            border: 2px solid var(--color-bg);
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            width: 100%;
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
        
        /* Member Card */
        .member-card {
            background: var(--color-surface);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            flex-wrap: wrap;
        }
        
        .member-card:hover {
            border-color: rgba(204, 28, 28, 0.35);
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.4), 0 0 16px rgba(204, 28, 28, 0.08);
            transform: translateY(-2px);
        }
        
        /* Avatar — red gradient from logo */
        .member-avatar-large {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #CC1C1C, #A01515);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 2px solid rgba(204, 28, 28, 0.3);
            overflow: hidden;
        }

        .member-avatar-large i {
            font-size: 28px;
            color: #FFFFFF;
        }
        
        .member-info {
            flex: 1;
            min-width: 200px;
        }
        
        .member-name {
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 6px;
            word-break: break-word;
            color: var(--color-white);
        }

        /* Badges */
        .badge-id {
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            border: 1px solid rgba(204, 28, 28, 0.3);
            display: inline-block;
        }

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

        .badge-plan {
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
        
        /* Action Buttons */
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
        
        /* Edit — navy blue */
        .action-btn.edit {
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            border: 1px solid rgba(26, 58, 143, 0.3);
        }

        .action-btn.edit:hover {
            background: rgba(26, 58, 143, 0.35);
            transform: scale(1.1);
        }
        
        /* Delete — red */
        .action-btn.delete {
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.25);
        }

        .action-btn.delete:hover {
            background: rgba(204, 28, 28, 0.28);
            transform: scale(1.1);
        }

        /* Report — silver/amber warning */
        .action-btn.report {
            background: rgba(176, 176, 176, 0.1);
            color: var(--color-silver);
            border: 1px solid rgba(176, 176, 176, 0.25);
        }

        .action-btn.report:hover {
            background: rgba(176, 176, 176, 0.2);
            transform: scale(1.1);
        }
        
        /* FAB — primary red */
        .fab-button {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: var(--color-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(204, 28, 28, 0.5);
            cursor: pointer;
            z-index: 100;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .fab-button:hover {
            transform: scale(1.1);
            background: var(--color-primary-dk);
            box-shadow: 0 6px 28px rgba(204, 28, 28, 0.6);
        }
        
        .fab-button i {
            font-size: 24px;
            color: #FFFFFF;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px;
            background: var(--color-surface);
            border-radius: 14px;
            border: 1px solid var(--color-border);
        }

        .empty-state i {
            font-size: 64px;
            color: var(--color-border);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: var(--color-muted);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--color-muted);
            opacity: 0.7;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .header-left {
                justify-content: center;
            }

            .header-right {
                width: 100%;
                justify-content: space-between;
            }

            .search-box {
                max-width: none;
                flex: 1;
            }

            .member-card {
                padding: 15px;
                position: relative;
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

        /* Delete modal icon — amber warning */
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

        .modal-body .warning-note {
            color: var(--color-primary);
            margin-top: 10px;
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

        /* Confirm delete — red */
        .modal-btn.primary {
            background: var(--color-primary);
            color: white;
        }

        .modal-btn.primary:hover {
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
            color: #FFFFFF;
        }

        .modal-btn.success:hover {
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
    </style>
    
    <div class="main-content">
        
        <div class="page-header">
            <div class="header-left">
                <h1><i class="fas fa-shield-alt"></i> MEMBER DATABASE</h1>
            </div>
            <div class="header-right">
                <?php if (is_admin()): ?>
                <a href="recycle-bin.php" class="recycle-bin-btn" title="Recycle Bin">
                    <i class="fas fa-trash-restore"></i>
                    <?php if ($deleted_count > 0): ?>
                    <span class="recycle-bin-badge"><?php echo $deleted_count; ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                <div class="search-box">
                    <input type="text" class="form-control" placeholder="Search database..." id="search-member" data-search=".member-card">
                    <button class="btn btn-secondary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <?php if ($members_result->num_rows > 0): ?>
            <?php while($member = $members_result->fetch_assoc()): ?>
                <div class="member-card">
                    <div class="member-avatar-large">
                        <?php if ($member['photo']): ?>
                            <img src="<?php echo $base_url; ?>assets/uploads/<?php echo $member['photo']; ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="member-info">
                        <div class="member-name">
                            <?php echo strtoupper($member['first_name'] . ' ' . $member['last_name']); ?>
                        </div>
                        <div>
                            <span class="badge-id"># <?php echo $member['member_id']; ?></span>
                            <?php if ($member['is_student']): ?>
                                <span class="badge-student">STUDENT</span>
                            <?php endif; ?>
                            <?php if ($member['current_plan']): ?>
                                <span class="badge-plan"><?php echo $member['current_plan']; ?></span>
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
                        <?php if (is_admin()): ?>
                        <button class="action-btn edit" onclick="editMember(<?php echo $member['id']; ?>)" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php endif; ?>
                        
                        <?php if (is_admin() || is_staff()): ?>
                        <button class="action-btn delete" onclick="confirmDeleteMember(<?php echo $member['id']; ?>, '<?php echo addslashes($member['first_name'] . ' ' . $member['last_name']); ?>')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                        
                        <?php if (is_staff()): ?>
                        <button class="action-btn report" onclick="reportMember(<?php echo $member['id']; ?>, '<?php echo addslashes($member['first_name'] . ' ' . $member['last_name']); ?>')" title="Report">
                            <i class="fas fa-exclamation-triangle"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>No members found</h3>
                <p>Start by adding your first member</p>
            </div>
        <?php endif; ?>
        
    </div>
    
    <!-- Floating Add Button (Admin Only) -->
    <?php if (is_admin()): ?>
    <a href="add-member.php" class="fab-button">
        <i class="fas fa-plus"></i>
    </a>
    <?php endif; ?>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon delete">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <div class="modal-title">
                    <h3>Move to Recycle Bin</h3>
                </div>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to move <strong id="deleteMemberName"></strong> to recycle bin?</p>
                <p class="warning-note">You can restore this member from the recycle bin later.</p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn secondary" onclick="closeDeleteModal()">Cancel</button>
                <button class="modal-btn primary" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Move to Bin
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
    $extra_scripts = "
    <script>
        let deleteId = null;
        let deleteName = '';

        function editMember(id) {
            window.location.href = 'edit-member.php?id=' + id;
        }
        
        function reportMember(id, name) {
            sessionStorage.setItem('reportMember', JSON.stringify({ id: id, name: name }));
            window.location.href = 'staff-report.php';
        }
        
        function confirmDeleteMember(id, name) {
            deleteId = id;
            deleteName = name;
            document.getElementById('deleteMemberName').textContent = name;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        function confirmDelete() {
            if (!deleteId) return;

            closeDeleteModal();
            showLoading();
            
            fetch('delete-member.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + deleteId
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showSuccessModal('Member moved to recycle bin successfully!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showErrorModal(data.message || 'Failed to move member to recycle bin');
                }
                deleteId = null;
                deleteName = '';
            })
            .catch(error => {
                hideLoading();
                showErrorModal('An error occurred while moving the member to recycle bin');
                console.error('Delete error:', error);
                deleteId = null;
                deleteName = '';
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

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                closeDeleteModal();
                closeSuccessModal();
                closeErrorModal();
            }
        });
    </script>
    ";
    
    include '../includes/footer.php'; 
    ?>