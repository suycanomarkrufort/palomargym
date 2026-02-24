<?php
require_once 'config/database.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('login.php');
}

// Get user info (admin or staff)
$user_id = get_user_id();
$is_staff_user = is_staff();
$table = $is_staff_user ? 'staff' : 'admin';

$user_query = $conn->prepare("SELECT * FROM $table WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$admin = $user_query->get_result()->fetch_assoc();

// Get today's stats
$today = date('Y-m-d');

// Total revenue today
$revenue_query = $conn->prepare("SELECT COALESCE(SUM(fee_charged), 0) as total FROM attendance WHERE date = ?");
$revenue_query->bind_param("s", $today);
$revenue_query->execute();
$revenue_result = $revenue_query->get_result()->fetch_assoc();
$today_revenue = $revenue_result['total'];

// Floor traffic
$traffic_query = $conn->prepare("SELECT COUNT(*) as count FROM attendance WHERE date = ? AND check_out_time IS NULL");
$traffic_query->bind_param("s", $today);
$traffic_query->execute();
$traffic_result = $traffic_query->get_result()->fetch_assoc();
$current_traffic = $traffic_result['count'];

// Total checked in today
$checkins_query = $conn->prepare("SELECT COUNT(*) as count FROM attendance WHERE date = ?");
$checkins_query->bind_param("s", $today);
$checkins_query->execute();
$checkins_result = $checkins_query->get_result()->fetch_assoc();
$total_checkins = $checkins_result['count'];

// Count active members
$active_members_query = $conn->prepare("
    SELECT COUNT(DISTINCT m.id) as count 
    FROM member m
    LEFT JOIN membership ms ON m.id = ms.member_id
    WHERE ms.status = 'Active' AND ms.end_date >= CURDATE()
");
$active_members_query->execute();
$active_members_result = $active_members_query->get_result()->fetch_assoc();
$active_members_count = $active_members_result['count'];

// Total members
$total_members_query = $conn->prepare("SELECT COUNT(*) as count FROM member");
$total_members_query->execute();
$total_members_result = $total_members_query->get_result()->fetch_assoc();
$total_members_count = $total_members_result['count'];

// Get latest check-ins
$latest_query = $conn->prepare("
    SELECT 
        a.*,
        m.first_name,
        m.last_name,
        m.photo,
        CASE 
            WHEN a.user_id IS NOT NULL THEN CONCAT(m.first_name, ' ', m.last_name)
            ELSE a.guest_name
        END as display_name,
        CASE 
            WHEN a.user_id IS NOT NULL THEN m.photo
            ELSE NULL
        END as display_photo,
        CASE
            WHEN a.user_id IS NOT NULL THEN 'Member'
            ELSE 'Guest'
        END as person_type
    FROM attendance a 
    LEFT JOIN member m ON a.user_id = m.id 
    WHERE a.date = ? 
    ORDER BY a.check_in_time DESC 
    LIMIT 5
");
$latest_query->bind_param("s", $today);
$latest_query->execute();
$latest_checkins = $latest_query->get_result();

// Get daily goal
$daily_goal = get_setting('daily_goal');
$goal_percentage = ($daily_goal > 0) ? round(($today_revenue / $daily_goal) * 100) : 0;

// Set page title
$page_title = "Dashboard";

// Include header
include 'includes/header.php';
?>

<style>
    /* =============================================
       WOLF PALOMAR FITNESS GYM — COLOR PALETTE
       Based on logo: Black bg, Red shield, 
       Navy blue center, White text, Silver accents
       =============================================
       --color-bg:         #0A0A0A  (deep black)
       --color-surface:    #141414  (card background)
       --color-surface-2:  #1C1C1C  (header/hover)
       --color-border:     #2A2A2A  (borders)
       --color-primary:    #CC1C1C  (shield red — CTAs, accents)
       --color-primary-dk: #A01515  (dark red)
       --color-navy:       #1A3A8F  (shield navy — secondary accent)
       --color-silver:     #B0B0B0  (wolf fur — muted text)
       --color-white:      #FFFFFF  (pure white — headings)
       --color-text:       #CCCCCC  (body text)
       --color-muted:      #777777  (labels/placeholders)
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

    .main-content {
        padding: 2rem;
        max-width: 1600px;
        margin: 0 auto;
    }

    /* Dashboard Header */
    .dashboard-header {
        margin-bottom: 2.5rem;
        animation: fadeInDown 0.6s ease-out;
    }

    .dashboard-title {
        font-size: 2rem;
        font-weight: 700;
        background: linear-gradient(135deg, #FFFFFF 0%, #CC1C1C 60%, #1A3A8F 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin: 0;
        letter-spacing: 1px;
    }

    .dashboard-subtitle {
        color: var(--color-muted);
        font-size: 0.95rem;
        margin-top: 0.5rem;
        font-weight: 400;
    }

    /* Stats Cards Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
        animation: fadeInUp 0.6s ease-out 0.2s both;
    }

    /* Stat Card */
    .stat-card {
        background: var(--color-surface);
        border-radius: 20px;
        padding: 1.75rem;
        border: 1px solid var(--color-border);
        position: relative;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #CC1C1C, #1A3A8F);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.4s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        border-color: rgba(204, 28, 28, 0.4);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 20px rgba(204, 28, 28, 0.15);
    }

    .stat-card:hover::before {
        transform: scaleX(1);
    }

    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.25rem;
    }

    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        position: relative;
        overflow: hidden;
        flex-shrink: 0;
    }

    .stat-icon i {
        position: relative;
        z-index: 1;
    }

    /* Revenue icon — red (primary shield color) */
    .stat-icon.revenue {
        background: linear-gradient(135deg, rgba(204, 28, 28, 0.25), rgba(204, 28, 28, 0.08));
        color: #CC1C1C;
        border: 1px solid rgba(204, 28, 28, 0.3);
    }

    /* Traffic icon — navy (shield center) */
    .stat-icon.traffic {
        background: linear-gradient(135deg, rgba(26, 58, 143, 0.25), rgba(26, 58, 143, 0.08));
        color: #4A7AFF;
        border: 1px solid rgba(26, 58, 143, 0.4);
    }

    /* Members icon — silver (wolf fur) */
    .stat-icon.members {
        background: linear-gradient(135deg, rgba(176, 176, 176, 0.2), rgba(176, 176, 176, 0.05));
        color: var(--color-silver);
        border: 1px solid rgba(176, 176, 176, 0.25);
    }

    .stat-label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: var(--color-muted);
        margin-bottom: 0.5rem;
    }

    .stat-value {
        font-size: 2.25rem;
        font-weight: 700;
        color: var(--color-white);
        line-height: 1;
        margin-bottom: 0.75rem;
        word-break: break-word;
    }

    .stat-trend {
        font-size: 0.85rem;
        color: #CC1C1C;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .stat-trend i {
        font-size: 0.75rem;
    }

    .stat-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--color-border);
        font-size: 0.85rem;
        color: var(--color-muted);
    }

    .stat-footer-value {
        color: #CC1C1C;
        font-weight: 600;
    }

    /* Charts Section */
    .charts-section {
        margin: 2.5rem 0;
        animation: fadeInUp 0.6s ease-out 0.4s both;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--color-white);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .section-title i {
        color: #CC1C1C;
        font-size: 1.1rem;
    }

    .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .chart-card {
        background: var(--color-surface);
        border-radius: 20px;
        padding: 2rem;
        border: 1px solid var(--color-border);
        transition: all 0.3s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
    }

    .chart-card:hover {
        border-color: rgba(204, 28, 28, 0.35);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 20px rgba(204, 28, 28, 0.1);
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .chart-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--color-white);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .chart-title i {
        color: #CC1C1C;
        font-size: 0.9rem;
    }

    .btn-download {
        background: rgba(204, 28, 28, 0.12);
        color: #CC1C1C;
        border: 1px solid rgba(204, 28, 28, 0.25);
        padding: 0.5rem 1rem;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-download:hover {
        background: rgba(204, 28, 28, 0.22);
        border-color: rgba(204, 28, 28, 0.5);
        transform: translateY(-2px);
    }

    .chart-canvas-wrapper {
        position: relative;
        height: 300px;
    }

    canvas {
        max-height: 300px;
    }

    /* Table Section */
    .table-section {
        animation: fadeInUp 0.6s ease-out 0.6s both;
    }

    .table-container {
        background: var(--color-surface);
        border-radius: 20px;
        padding: 2rem;
        border: 1px solid var(--color-border);
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
    }

    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .table-title {
        font-size: 1.15rem;
        font-weight: 600;
        color: var(--color-white);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .table-title i {
        color: #CC1C1C;
    }

    .btn-view-all {
        background: transparent;
        color: #CC1C1C;
        border: 1px solid rgba(204, 28, 28, 0.35);
        padding: 0.6rem 1.25rem;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-view-all:hover {
        background: rgba(204, 28, 28, 0.12);
        border-color: rgba(204, 28, 28, 0.6);
        transform: translateX(5px);
    }

    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    thead {
        background: var(--color-surface-2);
    }

    th {
        padding: 1rem;
        text-align: left;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--color-silver);
        border-bottom: 2px solid var(--color-border);
    }

    tbody tr {
        transition: all 0.3s ease;
        border-bottom: 1px solid #1E1E1E;
    }

    tbody tr:hover {
        background: var(--color-surface-2);
        transform: scale(1.01);
    }

    td {
        padding: 1.25rem 1rem;
        color: var(--color-text);
        font-size: 0.9rem;
    }

    .member-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .member-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #CC1C1C, #A01515);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        flex-shrink: 0;
        border: 2px solid rgba(204, 28, 28, 0.3);
    }

    .member-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .member-avatar i {
        color: #FFFFFF;
        font-size: 1.2rem;
    }

    .member-details {
        flex: 1;
    }

    .member-name {
        font-weight: 600;
        color: var(--color-white);
        margin-bottom: 0.25rem;
    }

    .member-type {
        color: var(--color-muted);
        font-size: 0.75rem;
        font-style: italic;
    }

    .status-badge {
        padding: 0.4rem 1rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-block;
    }

    /* Active — use red (primary brand) */
    .status-active {
        background: rgba(204, 28, 28, 0.15);
        color: #FF4444;
        border: 1px solid rgba(204, 28, 28, 0.35);
    }

    /* Student — use navy */
    .status-student {
        background: rgba(26, 58, 143, 0.2);
        color: #4A7AFF;
        border: 1px solid rgba(26, 58, 143, 0.4);
    }

    /* Expired — dimmed silver */
    .status-expired {
        background: rgba(100, 100, 100, 0.2);
        color: #888888;
        border: 1px solid rgba(100, 100, 100, 0.3);
    }

    .status-other {
        background: rgba(176, 176, 176, 0.1);
        color: var(--color-muted);
        border: 1px solid rgba(176, 176, 176, 0.2);
    }

    .time-value {
        color: var(--color-white);
        font-weight: 500;
    }

    .status-active-dot {
        color: #CC1C1C;
        font-size: 0.6rem;
        margin-right: 0.5rem;
    }

    .fee-value {
        color: #FF4444;
        font-weight: 700;
        font-size: 1rem;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--color-muted);
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        opacity: 0.3;
    }

    .empty-state p {
        font-size: 1rem;
        margin: 0;
    }

    /* Animations */
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-30px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .charts-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; padding-bottom: 80px; }
        .dashboard-header { margin-bottom: 1.5rem; }
        .dashboard-title { font-size: 1.35rem; line-height: 1.3; word-break: break-word; }
        .dashboard-subtitle { font-size: 0.85rem; line-height: 1.4; }
        .stats-grid { grid-template-columns: 1fr; gap: 1rem; }
        .stat-card { padding: 1.25rem; }
        .stat-card-header { flex-direction: row; gap: 1rem; margin-bottom: 1rem; }
        .stat-icon { width: 48px; height: 48px; font-size: 1.25rem; }
        .stat-label { font-size: 0.7rem; margin-bottom: 0.35rem; }
        .stat-value { font-size: 1.65rem; margin-bottom: 0.5rem; }
        .stat-trend { font-size: 0.8rem; }
        .stat-footer { margin-top: 0.75rem; padding-top: 0.75rem; font-size: 0.8rem; }
        .charts-section { margin: 1.5rem 0; }
        .section-header { flex-direction: column; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
        .section-title { font-size: 1.1rem; }
        .charts-grid { grid-template-columns: 1fr; gap: 1rem; }
        .chart-card { padding: 1.25rem; }
        .chart-header { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
        .chart-title { font-size: 0.95rem; }
        .btn-download { width: 100%; justify-content: center; }
        .table-container { padding: 1.25rem; overflow-x: auto; }
        .table-header { flex-direction: column; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
        .table-title { font-size: 1rem; }
        .btn-view-all { width: 100%; justify-content: center; }
        table { min-width: 600px; }
        th { padding: 0.75rem; font-size: 0.7rem; }
        td { padding: 1rem 0.75rem; font-size: 0.85rem; }
        .member-avatar { width: 40px; height: 40px; }
        .member-name { font-size: 0.9rem; }
        .member-type { font-size: 0.7rem; }
        .status-badge { padding: 0.35rem 0.75rem; font-size: 0.7rem; }
        .empty-state { padding: 3rem 1.5rem; }
        .empty-state i { font-size: 3rem; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.75rem; padding-bottom: 80px; }
        .dashboard-title { font-size: 1.15rem; }
        .dashboard-subtitle { font-size: 0.8rem; }
        .stat-value { font-size: 1.5rem; }
        .stat-card { padding: 1rem; }
        .chart-card { padding: 1rem; }
        .table-container { padding: 1rem; }
        .stat-icon { width: 42px; height: 42px; font-size: 1.1rem; }
        .stat-label { font-size: 0.65rem; }
        .stat-trend { font-size: 0.75rem; }
        .stat-footer { font-size: 0.75rem; }
    }

    /* Loading Spinner */
    .chart-loading {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 300px;
        color: var(--color-muted);
    }

    .chart-loading i {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        animation: spin 1s linear infinite;
        color: #CC1C1C;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }

    /* Progress Bar for Goals */
    .goal-progress {
        width: 100%;
        height: 6px;
        background: var(--color-border);
        border-radius: 10px;
        overflow: hidden;
        margin-top: 0.75rem;
    }

    .goal-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #CC1C1C, #A01515);
        border-radius: 10px;
        transition: width 1s ease;
        position: relative;
        overflow: hidden;
    }

    .goal-progress-bar::after {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        animation: shimmer 2s infinite;
    }

    @keyframes shimmer {
        0%   { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
</style>

<!-- Main Content -->
<div class="main-content">
    
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h1 class="dashboard-title">WOLF PALOMAR GYM MANAGER</h1>
        <p class="dashboard-subtitle">Real-time insights and analytics for your gym</p>
    </div>
    
    <!-- Stats Cards -->
    <div class="stats-grid">
        
        <!-- Revenue Card -->
        <div class="stat-card">
            <div class="stat-card-header">
                <div style="flex: 1;">
                    <div class="stat-label">Total Net Revenue</div>
                    <div class="stat-value">₱<?php echo number_format($today_revenue, 2); ?></div>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line"></i>
                        <span>Daily Goal: <?php echo $goal_percentage; ?>%</span>
                    </div>
                    <div class="goal-progress">
                        <div class="goal-progress-bar" style="width: <?php echo min($goal_percentage, 100); ?>%"></div>
                    </div>
                </div>
                <div class="stat-icon revenue">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
        </div>
        
        <!-- Traffic Card -->
        <div class="stat-card">
            <div class="stat-card-header">
                <div style="flex: 1;">
                    <div class="stat-label">Floor Traffic</div>
                    <div class="stat-value"><?php echo $current_traffic; ?><span style="font-size: 1.5rem; color: #555555;">/30</span></div>
                    <div class="stat-trend">
                        <i class="fas fa-user-clock"></i>
                        <span>On-site Now</span>
                    </div>
                </div>
                <div class="stat-icon traffic">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-footer">
                <span>Total Check-ins Today</span>
                <span class="stat-footer-value"><?php echo $total_checkins; ?></span>
            </div>
        </div>
        
        <!-- Members Card -->
        <div class="stat-card">
            <div class="stat-card-header">
                <div style="flex: 1;">
                    <div class="stat-label">Active Members</div>
                    <div class="stat-value"><?php echo $active_members_count; ?></div>
                    <div class="stat-trend">
                        <i class="fas fa-user-check"></i>
                        <span>With Valid Membership</span>
                    </div>
                </div>
                <div class="stat-icon members">
                    <i class="fas fa-id-card"></i>
                </div>
            </div>
            <div class="stat-footer">
                <span>Total Registered</span>
                <span class="stat-footer-value"><?php echo $total_members_count; ?></span>
            </div>
        </div>
        
    </div>

    <!-- Charts Section -->
    <div class="charts-section">
        <div class="section-header">
            <div class="section-title">
                <i class="fas fa-chart-area"></i>
                Analytics & Insights
            </div>
        </div>

        <div class="charts-grid">
            <!-- Revenue Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-line"></i>
                        Revenue Trend (Last 7 Days)
                    </h3>
                    <button onclick="downloadChart('revenueChart', 'revenue-trend')" class="btn-download">
                        <i class="fas fa-download"></i>
                        Download
                    </button>
                </div>
                <div class="chart-canvas-wrapper">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Check-ins Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-bar"></i>
                        Check-ins Trend (Last 7 Days)
                    </h3>
                    <button onclick="downloadChart('checkinsChart', 'checkins-trend')" class="btn-download">
                        <i class="fas fa-download"></i>
                        Download
                    </button>
                </div>
                <div class="chart-canvas-wrapper">
                    <canvas id="checkinsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Member Status Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="fas fa-chart-pie"></i>
                    Member Status Distribution
                </h3>
                <button onclick="downloadChart('statusChart', 'member-status')" class="btn-download">
                    <i class="fas fa-download"></i>
                    Download
                </button>
            </div>
            <div style="max-width: 450px; margin: 0 auto; height: 300px;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Recent Check-ins -->
    <div class="table-section">
        <div class="table-container">
            <div class="table-header">
                <h3 class="table-title">
                    <i class="fas fa-clipboard-check"></i>
                    Latest Check-ins
                </h3>
                <a href="pages/logbook.php" class="btn-view-all">
                    View All
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <?php if ($latest_checkins->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Status</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Fee</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($checkin = $latest_checkins->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="member-info">
                                        <div class="member-avatar">
                                            <?php if ($checkin['display_photo']): ?>
                                                <img src="assets/uploads/<?php echo $checkin['display_photo']; ?>" alt="Avatar">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="member-details">
                                            <div class="member-name"><?php echo htmlspecialchars($checkin['display_name']); ?></div>
                                            <?php if ($checkin['person_type'] == 'Guest'): ?>
                                                <div class="member-type">Guest</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php 
                                        echo strtolower($checkin['member_status']); 
                                    ?>">
                                        <?php echo strtoupper($checkin['member_status']); ?>
                                    </span>
                                </td>
                                <td class="time-value"><?php echo date('g:i A', strtotime($checkin['check_in_time'])); ?></td>
                                <td>
                                    <?php if ($checkin['check_out_time']): ?>
                                        <span class="time-value"><?php echo date('g:i A', strtotime($checkin['check_out_time'])); ?></span>
                                    <?php else: ?>
                                        <span style="color: #CC1C1C; font-weight: 600;">
                                            <span class="status-active-dot">●</span> Active
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="fee-value">₱<?php echo number_format($checkin['fee_charged'], 2); ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No check-ins recorded for today</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
</div>

<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/bottom-nav.php'; ?>

<?php 
// Extra scripts
$extra_scripts = "
<script src='https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'></script>
<script>
    // Dark theme defaults for Chart.js
    Chart.defaults.color = '#888888';
    Chart.defaults.borderColor = '#2A2A2A';
    
    function initializeCharts() {
        fetch('pages/get-chart-data.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.chart-loading').forEach(el => el.remove());
                    document.querySelectorAll('canvas').forEach(canvas => canvas.style.display = 'block');
                    
                    // Revenue Chart — Red brand color
                    new Chart(document.getElementById('revenueChart'), {
                        type: 'line',
                        data: {
                            labels: data.revenue.labels,
                            datasets: [{
                                label: 'Revenue',
                                data: data.revenue.values,
                                borderColor: '#CC1C1C',
                                backgroundColor: 'rgba(204, 28, 28, 0.12)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 5,
                                pointHoverRadius: 7,
                                pointBackgroundColor: '#CC1C1C',
                                pointBorderColor: '#FFFFFF',
                                pointBorderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#1C1C1C',
                                    titleColor: '#FFFFFF',
                                    bodyColor: '#AAAAAA',
                                    borderColor: '#CC1C1C',
                                    borderWidth: 2,
                                    padding: 12,
                                    displayColors: false,
                                    callbacks: {
                                        label: (context) => '₱' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2})
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { callback: (value) => '₱' + value.toLocaleString(), color: '#777777' },
                                    grid: { color: '#2A2A2A' }
                                },
                                x: { grid: { display: false }, ticks: { color: '#777777' } }
                            }
                        }
                    });

                    // Check-ins Chart — Navy blue accent
                    new Chart(document.getElementById('checkinsChart'), {
                        type: 'bar',
                        data: {
                            labels: data.checkins.labels,
                            datasets: [{
                                label: 'Check-ins',
                                data: data.checkins.values,
                                backgroundColor: '#1A3A8F',
                                hoverBackgroundColor: '#CC1C1C',
                                borderRadius: 10,
                                borderSkipped: false
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#1C1C1C',
                                    titleColor: '#FFFFFF',
                                    bodyColor: '#AAAAAA',
                                    borderColor: '#CC1C1C',
                                    borderWidth: 2,
                                    padding: 12,
                                    displayColors: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1, color: '#777777' },
                                    grid: { color: '#2A2A2A' }
                                },
                                x: { grid: { display: false }, ticks: { color: '#777777' } }
                            }
                        }
                    });

                    // Status Doughnut Chart — full logo palette
                    new Chart(document.getElementById('statusChart'), {
                        type: 'doughnut',
                        data: {
                            labels: data.status.labels,
                            datasets: [{
                                data: data.status.values,
                                backgroundColor: ['#CC1C1C', '#1A3A8F', '#555555', '#B0B0B0'],
                                borderColor: '#141414',
                                borderWidth: 3,
                                hoverOffset: 10
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        color: '#AAAAAA',
                                        padding: 20,
                                        font: { size: 13, weight: '500' },
                                        usePointStyle: true,
                                        pointStyle: 'circle'
                                    }
                                },
                                tooltip: {
                                    backgroundColor: '#1C1C1C',
                                    titleColor: '#FFFFFF',
                                    bodyColor: '#AAAAAA',
                                    borderColor: '#CC1C1C',
                                    borderWidth: 2,
                                    padding: 12
                                }
                            }
                        }
                    });
                }
            })
            .catch(error => console.error('Chart error:', error));
    }

    function downloadChart(chartId, filename) {
        const canvas = document.getElementById(chartId);
        const url = canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.download = filename + '-' + new Date().toISOString().split('T')[0] + '.png';
        link.href = url;
        link.click();
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (typeof Chart !== 'undefined') {
            document.querySelectorAll('canvas').forEach(canvas => {
                const wrapper = canvas.closest('.chart-canvas-wrapper') || canvas.parentElement;
                const loading = document.createElement('div');
                loading.className = 'chart-loading';
                loading.innerHTML = '<i class=\"fas fa-spinner fa-spin\"></i><p>Loading chart data...</p>';
                canvas.style.display = 'none';
                wrapper.insertBefore(loading, canvas);
            });
            initializeCharts();
        }
    });

    // Auto-refresh stats every 10 seconds
    setInterval(() => {
        fetch('pages/get-dashboard-stats.php')
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    document.querySelectorAll('.stat-value')[0].textContent = '₱' + d.today_revenue.toLocaleString('en-US', {minimumFractionDigits: 2});
                    document.querySelectorAll('.stat-value')[1].innerHTML = d.current_traffic + '<span style=\"font-size: 1.5rem; color: #555555;\">/30</span>';
                    document.querySelectorAll('.stat-value')[2].textContent = d.active_members;
                }
            });
    }, 10000);
</script>
";

include 'includes/footer.php'; 
?>