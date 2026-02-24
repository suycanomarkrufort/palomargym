<?php
// Calculate weekly revenue (current week)
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

// Get walk-in fees for the week
$week_walkin_query = $conn->query("SELECT COALESCE(SUM(fee_charged), 0) as total FROM attendance WHERE date BETWEEN '$week_start' AND '$week_end'");
$week_walkin = $week_walkin_query->fetch_assoc()['total'];

// Get membership payments for the week (created during this week)
$week_membership_query = $conn->query("
    SELECT COALESCE(SUM(mp.membership_price), 0) as total 
    FROM membership m 
    JOIN membership_plans mp ON m.membership_plan_id = mp.id 
    WHERE DATE(m.created_at) BETWEEN '$week_start' AND '$week_end'
");
$week_membership = $week_membership_query->fetch_assoc()['total'];

$week_revenue = $week_walkin + $week_membership;

// Calculate monthly revenue (current month)
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// Get walk-in fees for the month
$month_walkin_query = $conn->query("SELECT COALESCE(SUM(fee_charged), 0) as total FROM attendance WHERE date BETWEEN '$month_start' AND '$month_end'");
$month_walkin = $month_walkin_query->fetch_assoc()['total'];

// Get membership payments for the month
$month_membership_query = $conn->query("
    SELECT COALESCE(SUM(mp.membership_price), 0) as total 
    FROM membership m 
    JOIN membership_plans mp ON m.membership_plan_id = mp.id 
    WHERE DATE(m.created_at) BETWEEN '$month_start' AND '$month_end'
");
$month_membership = $month_membership_query->fetch_assoc()['total'];

$month_revenue = $month_walkin + $month_membership;

// Get weekly and monthly goals
$weekly_goal = get_setting('weekly_goal') ?: 35000;
$monthly_goal = get_setting('monthly_goal') ?: 150000;

// Calculate percentages
$weekly_percentage = $weekly_goal > 0 ? round(($week_revenue / $weekly_goal) * 100) : 0;
$monthly_percentage = $monthly_goal > 0 ? round(($month_revenue / $monthly_goal) * 100) : 0;
?>

<!-- Sidebar -->
<div class="sidebar">
    
    <!-- Sidebar Header - Profile -->
    <div class="sidebar-header">
        <div class="profile-section">
            <div class="profile-avatar">
                <?php if ($admin['photo']): ?>
                    <img src="<?php echo $base_url; ?>assets/uploads/<?php echo $admin['photo']; ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h3><?php echo strtoupper($admin['first_name'] . ' ' . $admin['last_name']); ?></h3>
                <p><?php echo $admin['email']; ?></p>
                <span class="profile-badge"><?php echo $is_staff_user ? 'TRAINER/COACH' : 'SYSTEM CONTROLLER'; ?></span>
            </div>
        </div>
        
        <!-- Today's Goal - Now Clickable with Cycling Views -->
        <div class="goal-section" id="goalSection" style="cursor: pointer; user-select: none;">
            <div class="goal-header">
                <span class="goal-title" id="goalTitle">TODAY'S GOAL</span>
                <span class="goal-percentage" id="goalPercentage"><?php echo $goal_percentage; ?>%</span>
            </div>
            <div class="goal-progress">
                <div class="goal-progress-bar" id="goalProgressBar" style="width: <?php echo min($goal_percentage, 100); ?>%;"></div>
            </div>
            <div class="goal-details">
                <span class="collected" id="goalDetails">COLLECTED ₱<?php echo number_format($today_revenue, 0); ?> / <?php echo number_format($daily_goal, 0); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Menu -->
    <div class="sidebar-menu">
        
        <a href="<?php echo $base_url; ?>index.php" class="menu-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i>
            <span>DASHBOARD OVERVIEW</span>
        </a>
        
        <div class="menu-item menu-dropdown">
            <i class="fas fa-layer-group"></i>
            <span>SYSTEM MANAGEMENT</span>
            <i class="fas fa-chevron-down dropdown-icon" style="margin-left: auto;"></i>
        </div>
        <div class="dropdown-content">
            <a href="<?php echo $base_url; ?>pages/members.php" class="dropdown-item">
                <i class="fas fa-users"></i> MEMBERS
            </a>
            <?php if (is_admin()): ?>
            <a href="<?php echo $base_url; ?>pages/memberships.php" class="dropdown-item">
                <i class="fas fa-id-card-alt"></i> MEMBERSHIPS
            </a>
            <a href="<?php echo $base_url; ?>pages/walkin-rates.php" class="dropdown-item">
                <i class="fas fa-money-bill-wave"></i> WALK-IN RATES
            </a>
            <?php endif; ?>
            <a href="<?php echo $base_url; ?>pages/id-maker.php" class="dropdown-item">
                <i class="fas fa-id-card"></i> ID MAKER
            </a>
            <a href="<?php echo $base_url; ?>pages/id-vault.php" class="dropdown-item">
                <i class="fas fa-archive"></i> ID VAULT
            </a>
            <?php if (is_admin()): ?>
            <a href="<?php echo $base_url; ?>pages/user-management.php" class="dropdown-item">
                <i class="fas fa-user-cog"></i> USER MANAGEMENT
            </a>
            <?php endif; ?>
            <?php if (is_staff()): ?>
            <a href="<?php echo $base_url; ?>pages/staff-report.php" class="dropdown-item">
                <i class="fas fa-exclamation-triangle"></i> SUBMIT REPORT
            </a>
            <?php endif; ?>
            <?php if (is_admin()): ?>
            <a href="<?php echo $base_url; ?>pages/goal-center.php" class="dropdown-item">
                <i class="fas fa-bullseye"></i> GOAL CENTER
            </a>
            <?php endif; ?>
        </div>
        
        <a href="<?php echo $base_url; ?>pages/staff-feedback.php" class="menu-item <?php echo ($current_page == 'staff-feedback.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            <span>REPORTS & FEEDBACK</span>
        </a>
        
        <?php if (is_admin()): ?>
        <a href="<?php echo $base_url; ?>pages/audit-log.php" class="menu-item <?php echo ($current_page == 'audit-log.php') ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span>SYSTEM AUDIT LOG</span>
        </a>
        <?php endif; ?>
        
        <a href="<?php echo $base_url; ?>pages/settings.php" class="menu-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>GENERAL SETTINGS</span>
        </a>
        
        <a href="#" class="menu-item" style="border-top: 1px solid #2A2A2A; margin-top: 10px; color: #FF5555;" onclick="confirmSidebarLogout(event)">
            <i class="fas fa-sign-out-alt"></i>
            <span>LOGOUT</span>
        </a>
        
        <div style="padding: 25px; border-top: 1px solid #2A2A2A; text-align: center; color: #777777; font-size: 11px;">
            <p>© WOLF OS V2.5.6-STABLE</p>
            <p>WOLF PALOMAR SYSTEMS © 2026</p>
        </div>
        
    </div>
    
</div>

<!-- Sidebar Logout Confirmation Modal -->
<div class="alert-modal" id="sidebarLogoutModal">
    <div class="alert-modal-content">
        <div class="alert-header">
            <div class="alert-icon warning">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <div class="alert-title">
                <h3>Logout</h3>
            </div>
        </div>
        <div class="alert-body">
            <p>Are you sure you want to logout?</p>
        </div>
        <div class="alert-footer">
            <button class="alert-btn secondary" onclick="closeSidebarLogoutModal()">Cancel</button>
            <button class="alert-btn danger" onclick="proceedSidebarLogout()">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </div>
</div>

<style>
/* =============================================
   WOLF PALOMAR FITNESS GYM — COLOR PALETTE
   Alert Modal Styles for Sidebar
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

/* Logout / Warning — red (primary brand) */
.alert-icon.warning {
    background: rgba(204, 28, 28, 0.15);
    color: #FF5555;
    border: 1px solid rgba(204, 28, 28, 0.3);
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

.alert-title {
    flex: 1;
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

/* Logout / Confirm Danger — red */
.alert-btn.danger {
    background: var(--color-primary);
    color: var(--color-white);
    border: 1px solid rgba(204, 28, 28, 0.5);
}

.alert-btn.danger:hover {
    background: var(--color-primary-dk);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(204, 28, 28, 0.4);
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

/* Cancel / Dismiss — dark surface */
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
</style>

<script>
// Goal Cycling Functionality
(function() {
    // Goal data from PHP
    const goalData = {
        daily: {
            title: "TODAY'S GOAL",
            collected: <?php echo $today_revenue; ?>,
            target: <?php echo $daily_goal; ?>,
            percentage: <?php echo $goal_percentage; ?>
        },
        weekly: {
            title: "WEEKLY GOAL",
            collected: <?php echo $week_revenue; ?>,
            target: <?php echo $weekly_goal; ?>,
            percentage: <?php echo $weekly_percentage; ?>
        },
        monthly: {
            title: "MONTHLY GOAL",
            collected: <?php echo $month_revenue; ?>,
            target: <?php echo $monthly_goal; ?>,
            percentage: <?php echo $monthly_percentage; ?>
        }
    };
    
    // Current view state
    let currentView = localStorage.getItem('goalView') || 'daily';
    const viewCycle = ['daily', 'weekly', 'monthly'];
    
    // Elements
    const goalSection = document.getElementById('goalSection');
    const goalTitle = document.getElementById('goalTitle');
    const goalPercentage = document.getElementById('goalPercentage');
    const goalProgressBar = document.getElementById('goalProgressBar');
    const goalDetails = document.getElementById('goalDetails');
    
    // Update display
    function updateGoalDisplay(view) {
        const data = goalData[view];
        
        goalTitle.textContent = data.title;
        goalPercentage.textContent = data.percentage + '%';
        goalProgressBar.style.width = Math.min(data.percentage, 100) + '%';
        goalDetails.textContent = 'COLLECTED ₱' + data.collected.toLocaleString('en-PH', {maximumFractionDigits: 0}) + 
                                  ' / ' + data.target.toLocaleString('en-PH', {maximumFractionDigits: 0});
    }
    
    // Initialize with saved view
    updateGoalDisplay(currentView);
    
    // Click handler - cycle through views
    goalSection.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Get next view in cycle
        const currentIndex = viewCycle.indexOf(currentView);
        const nextIndex = (currentIndex + 1) % viewCycle.length;
        currentView = viewCycle[nextIndex];
        
        // Save to localStorage
        localStorage.setItem('goalView', currentView);
        
        // Update display with smooth transition
        goalSection.style.opacity = '0.7';
        setTimeout(() => {
            updateGoalDisplay(currentView);
            goalSection.style.opacity = '1';
        }, 150);
    });
    
    // Add hover effect
    goalSection.addEventListener('mouseenter', function() {
        this.style.transform = 'scale(1.02)';
        this.style.transition = 'all 0.2s ease';
    });
    
    goalSection.addEventListener('mouseleave', function() {
        this.style.transform = 'scale(1)';
    });
})();

// Sidebar Logout Confirmation Functions
function confirmSidebarLogout(event) {
    event.preventDefault();
    document.getElementById('sidebarLogoutModal').classList.add('active');
}

function closeSidebarLogoutModal() {
    document.getElementById('sidebarLogoutModal').classList.remove('active');
}

function proceedSidebarLogout() {
    window.location.href = '<?php echo $base_url; ?>logout.php';
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    if (event.target.id === 'sidebarLogoutModal') {
        closeSidebarLogoutModal();
    }
});
</script>