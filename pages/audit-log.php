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

// Get date range filter (Default: last 30 days)
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// UPDATED QUERY: Limit to 10 results only
$logs_query = $conn->prepare("
    SELECT al.*, a.first_name, a.last_name, a.photo 
    FROM activity_logs al 
    LEFT JOIN admin a ON al.acted_by = a.id 
    WHERE DATE(al.acted_at) BETWEEN ? AND ?
    ORDER BY al.acted_at DESC
    LIMIT 10
");
$logs_query->bind_param("ss", $date_from, $date_to);
$logs_query->execute();
$logs_result = $logs_query->get_result();

// Set page title
$page_title = "System Audit Log";

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
            background: #0A0A0A !important;
            color: #CCCCCC !important;
        }

        .audit-container {
            padding: 20px;
            padding-bottom: 100px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Page title */
        .page-title {
            color: #FFFFFF;
            font-size: 32px;
            font-weight: 800;
            font-style: italic;
            margin-bottom: 30px;
        }

        /* "HISTORY" — red brand accent */
        .page-title .highlight { color: #CC1C1C; }

        /* Filter section — dark surface */
        .filter-section {
            background: #141414 !important;
            border: 1px solid #2A2A2A;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }

        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .filter-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Filter icon — red brand */
        .filter-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #CC1C1C, #A01515);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            font-size: 18px;
        }

        .filter-title {
            font-size: 14px;
            font-weight: 700;
            color: #777777;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Limit badge — navy */
        .limit-badge {
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            border: 1px solid rgba(26, 58, 143, 0.3);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .date-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .input-group { display: flex; flex-direction: column; }

        .input-label {
            font-size: 11px;
            font-weight: 700;
            color: #777777;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        /* Date inputs — dark */
        .date-input {
            padding: 12px;
            background: #1C1C1C !important;
            border: 1px solid #2A2A2A !important;
            border-radius: 10px;
            font-size: 14px;
            color: #FFFFFF !important;
            transition: all 0.3s ease;
            outline: none;
        }

        .date-input:focus {
            border-color: #CC1C1C !important;
            box-shadow: 0 0 0 3px rgba(204, 28, 28, 0.12) !important;
        }

        /* Apply filter button — red */
        .apply-filter-btn {
            padding: 12px 25px;
            background: #CC1C1C;
            border: none;
            border-radius: 10px;
            color: #FFFFFF;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(204, 28, 28, 0.35);
        }

        .apply-filter-btn:hover {
            background: #A01515;
            transform: translateY(-2px);
        }

        /* Logs container — dark surface */
        .logs-container {
            background: #141414 !important;
            border: 1px solid #2A2A2A;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }

        .log-entry {
            display: flex;
            gap: 20px;
            padding: 18px 0;
            border-bottom: 1px solid #2A2A2A;
            transition: all 0.3s ease;
        }

        .log-entry:last-child { border-bottom: none; }

        .log-entry:hover {
            background: rgba(204, 28, 28, 0.04);
            border-radius: 10px;
            padding-left: 10px;
            padding-right: 10px;
        }

        /* Avatar — red brand */
        .log-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #CC1C1C, #A01515);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid rgba(204, 28, 28, 0.3);
        }

        .log-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .log-avatar i {
            font-size: 20px;
            color: #FFFFFF;
        }

        .log-content { flex: 1; }

        /* User name — silver/wolf fur */
        .log-user {
            font-size: 15px;
            font-weight: 700;
            color: #B0B0B0;
            font-style: italic;
            margin-bottom: 5px;
        }

        /* Description text */
        .log-description {
            font-size: 14px;
            color: #CCCCCC;
            margin-bottom: 8px;
            line-height: 1.5;
        }

        /* Meta tags */
        .log-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #777777;
        }

        .log-meta i { color: #CC1C1C; }

        /* Timestamp */
        .log-timestamp {
            text-align: right;
            color: #777777;
            font-size: 12px;
            white-space: nowrap;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #777777;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.25;
            color: #CC1C1C;
            display: block;
        }

        .empty-state h3 { color: #B0B0B0; margin-bottom: 8px; }
        .empty-state p  { color: #777777; }

        @media (max-width: 768px) {
            .date-inputs { grid-template-columns: 1fr; }
            .log-entry { flex-direction: column; gap: 10px; }
            .log-timestamp { text-align: left; }
        }
    </style>
    
    <div class="audit-container">
        
        <h1 class="page-title">SYSTEM <span class="highlight">HISTORY</span></h1>
        
        <!-- Date Range Filter -->
        <div class="filter-section">
            <div class="filter-header">
                <div class="filter-header-left">
                    <div class="filter-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="filter-title">DATE RANGE AUDIT</div>
                </div>
                <div class="limit-badge">SHOWING LATEST 10</div>
            </div>
            
            <form method="GET" action="">
                <div class="date-inputs">
                    <div class="input-group">
                        <label class="input-label">FROM</label>
                        <input type="date" name="date_from" class="date-input" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="input-group">
                        <label class="input-label">TO</label>
                        <input type="date" name="date_to" class="date-input" value="<?php echo $date_to; ?>">
                    </div>
                </div>
                <button type="submit" class="apply-filter-btn">
                    <i class="fas fa-search"></i> APPLY FILTER
                </button>
            </form>
        </div>
        
        <!-- Activity Logs -->
        <div class="logs-container">
            <?php if ($logs_result->num_rows > 0): ?>
                <?php while($log = $logs_result->fetch_assoc()): ?>
                    <div class="log-entry">
                        <div class="log-avatar">
                            <?php if ($log['photo']): ?>
                                <img src="<?php echo $base_url; ?>assets/uploads/<?php echo $log['photo']; ?>">
                            <?php else: ?>
                                <i class="fas fa-shield-alt"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="log-content">
                            <div class="log-user">
                                <?php echo strtoupper($log['first_name'] ? $log['first_name'] . ' ' . $log['last_name'] : 'SYSTEM'); ?>
                            </div>
                            <div class="log-description">
                                "<?php echo $log['description']; ?>"
                            </div>
                            <div class="log-meta">
                                <span>
                                    <i class="fas fa-tag"></i> 
                                    <?php echo $log['activity_type']; ?>
                                </span>
                                <span>
                                    <i class="fas fa-hashtag"></i> 
                                    <?php echo $log['activity_id']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="log-timestamp">
                            <?php echo date('m/d/Y, g:i:s A', strtotime($log['acted_at'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h3>No Activity Logs Found</h3>
                    <p>No system activities recorded for the selected date range</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
    
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/bottom-nav.php'; ?>
    <?php include '../includes/footer.php'; ?>