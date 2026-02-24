<?php
require_once '../config/database.php';

// Check kung logged in (Admin man o Staff)
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

// Query para makuha ang lahat ng reports galing sa staff_reports table
$reports_query = "
    SELECT sr.*, s.first_name, s.last_name 
    FROM staff_reports sr 
    LEFT JOIN staff s ON sr.staff_id = s.id 
    ORDER BY sr.created_at DESC
";
$reports_result = $conn->query($reports_query);

// Set page title
$page_title = "Feedback Pool";

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

        body,
        .main-content {
            background: #0A0A0A !important;
            color: #CCCCCC !important;
        }

        .feedback-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
            padding-bottom: 100px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        /* Page title */
        .page-title {
            font-size: 32px;
            font-weight: 800;
            font-style: italic;
            color: #FFFFFF;
        }

        /* "POOL" — red brand accent */
        .page-title span { color: #CC1C1C; }

        /* Back link — navy */
        .back-link {
            color: #4A7AFF;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #FFFFFF;
            transform: translateX(-4px);
        }

        /* Reports list container — dark surface */
        .reports-list {
            background: #141414 !important;
            border: 1px solid #2A2A2A;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }

        /* Individual report card */
        .report-card {
            background: #1C1C1C;
            border: 1px solid #2A2A2A;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .report-card:last-child { margin-bottom: 0; }

        .report-card:hover {
            border-color: rgba(204, 28, 28, 0.4);
            box-shadow: 0 2px 12px rgba(204, 28, 28, 0.08);
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* Reporter name */
        .reporter-name {
            font-weight: 700;
            color: #FFFFFF;
            font-size: 15px;
        }

        /* STAFF role badge — red on dark */
        .role-badge {
            font-size: 10px;
            background: rgba(204, 28, 28, 0.2);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.3);
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 6px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .report-date {
            color: #777777;
            font-size: 12px;
        }

        /* Tagged member pills */
        .report-members {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .member-tag {
            background: rgba(204, 28, 28, 0.15);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.3);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Report body text */
        .report-text {
            color: #CCCCCC;
            line-height: 1.6;
            white-space: pre-wrap;
            font-size: 14px;
        }

        /* FAB — red brand */
        .add-report-btn {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: #CC1C1C;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #FFFFFF;
            cursor: pointer;
            box-shadow: 0 4px 18px rgba(204, 28, 28, 0.5);
            border: none;
            z-index: 999;
            transition: all 0.3s ease;
        }

        .add-report-btn:hover {
            background: #A01515;
            transform: scale(1.1);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #777777;
        }

        .empty-state i {
            font-size: 50px;
            margin-bottom: 20px;
            opacity: 0.3;
            color: #CC1C1C;
        }

        .empty-state h3 {
            color: #B0B0B0;
            margin-bottom: 8px;
        }

        .empty-state p { color: #777777; }

        @media (max-width: 768px) {
            .page-title { font-size: 24px; }
            .report-header { flex-direction: column; }
            .add-report-btn { bottom: 90px; right: 20px; }
        }
    </style>
    
    <div class="feedback-container">
        
        <div class="page-header">
            <div>
                <h1 class="page-title">FEEDBACK <span>POOL</span></h1>
                <a href="<?php echo $base_url; ?>index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <div class="reports-list">
            <?php if ($reports_result && $reports_result->num_rows > 0): ?>
                <?php while($report = $reports_result->fetch_assoc()): ?>
                    <div class="report-card">
                        <div class="report-header">
                            <div>
                                <span class="reporter-name">
                                    <?php echo strtoupper($report['first_name'] . ' ' . $report['last_name']); ?>
                                </span>
                                <span class="role-badge">STAFF</span>
                            </div>
                            <div class="report-date">
                                <i class="far fa-clock"></i> 
                                <?php echo date('M d, Y | g:i A', strtotime($report['created_at'])); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($report['tagged_members'])): ?>
                            <div class="report-members">
                                <?php 
                                $members = json_decode($report['tagged_members'], true);
                                if (is_array($members)) {
                                    foreach ($members as $member): 
                                ?>
                                    <div class="member-tag">
                                        <i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($member); ?>
                                    </div>
                                <?php 
                                    endforeach; 
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="report-text"><?php echo nl2br(htmlspecialchars($report['report_details'])); ?></div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comment-slash"></i>
                    <h3>NO REPORTS FOUND</h3>
                    <p>There are no staff reports or feedback submitted yet.</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
    
    <!-- Ang Add Button ay lalabas lang kung STAFF ang naka-login -->
    <?php if ($is_staff_user): ?>
    <button class="add-report-btn" onclick="window.location.href='staff-report.php'" title="Submit New Report">
        <i class="fas fa-plus"></i>
    </button>
    <?php endif; ?>
    
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/bottom-nav.php'; ?>
    <?php include '../includes/footer.php'; ?>