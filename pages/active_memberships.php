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

// Variables required by sidebar.php
$today = date('Y-m-d');
$revenue_query = $conn->prepare("SELECT COALESCE(SUM(fee_charged), 0) as total FROM attendance WHERE date = ?");
$revenue_query->bind_param("s", $today);
$revenue_query->execute();
$revenue_result = $revenue_query->get_result()->fetch_assoc();
$today_revenue = $revenue_result['total'];
$daily_goal = get_setting('daily_goal');
$goal_percentage = ($daily_goal > 0) ? round(($today_revenue / $daily_goal) * 100) : 0;

// Get all membership plans for renew dropdown
$renew_plans_query = "SELECT * FROM membership_plans ORDER BY membership_duration_days ASC";
$renew_plans_result = $conn->query($renew_plans_query);

// Get active memberships
$active_memberships_query = "
    SELECT m.id as membership_id, m.member_id, m.start_date, m.end_date, m.status,
           mem.first_name, mem.last_name, mem.photo,
           mp.membership_name, mp.membership_price, mp.membership_duration_days,
           mp.fee_type, mp.per_visit_fee
    FROM membership m
    INNER JOIN member mem ON m.member_id = mem.id
    INNER JOIN membership_plans mp ON m.membership_plan_id = mp.id
    WHERE m.status = 'Active'
    ORDER BY m.end_date ASC
";
$active_memberships = $conn->query($active_memberships_query);

// Pre-process rows
$rows = [];
$expiring_count = 0;
$unlimited_count = 0;
$per_visit_count = 0;
while ($m = $active_memberships->fetch_assoc()) {
    $dr = ceil((strtotime($m['end_date']) - time()) / (60 * 60 * 24));
    if ($dr <= 7) $expiring_count++;
    $ft = isset($m['fee_type']) ? $m['fee_type'] : 'unlimited';
    if ($ft === 'unlimited') $unlimited_count++; else $per_visit_count++;
    $m['_days_remaining'] = $dr;
    $rows[] = $m;
}
$total = count($rows);

// Handle renew POST
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'renew') {
        $membership_id = intval($_POST['membership_id']);
        $extend_days = intval($_POST['extend_days']);

        $membership_query = $conn->prepare("SELECT * FROM membership WHERE id = ?");
        $membership_query->bind_param("i", $membership_id);
        $membership_query->execute();
        $membership = $membership_query->get_result()->fetch_assoc();

        if ($membership) {
            $new_end_date = date('Y-m-d', strtotime($membership['end_date'] . ' + ' . $extend_days . ' days'));
            $update_query = $conn->prepare("UPDATE membership SET end_date = ?, status = 'Active' WHERE id = ?");
            $update_query->bind_param("si", $new_end_date, $membership_id);
            if ($update_query->execute()) {
                log_activity('Renew Membership', 'Membership renewed for ' . $extend_days . ' days', $_SESSION['admin_id']);
                header("Location: active_memberships.php?success=renewed");
                exit;
            } else {
                $error = 'Failed to renew membership!';
            }
        }
    }

    if ($_POST['action'] == 'cancel') {
        $membership_id = intval($_POST['membership_id']);
        $cancel_query = $conn->prepare("UPDATE membership SET status = 'Cancelled' WHERE id = ?");
        $cancel_query->bind_param("i", $membership_id);
        if ($cancel_query->execute()) {
            log_activity('Cancel Membership', 'Membership cancelled', $_SESSION['admin_id']);
            header("Location: active_memberships.php?success=cancelled");
            exit;
        } else {
            $error = 'Failed to cancel membership!';
        }
    }
}

$page_title = "Active Memberships";
$current_page = "memberships.php";

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

    body { background: var(--color-bg); color: var(--color-text); }

    .main-content { padding-bottom: 120px !important; }

    /* ---- Back button ---- */
    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        color: var(--color-text);
        padding: 9px 18px;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s ease;
        margin-bottom: 22px;
    }
    .back-btn:hover {
        border-color: rgba(204,28,28,0.4);
        color: var(--color-white);
        background: var(--color-surface-2);
    }
    .back-btn i { color: var(--color-primary); }

    /* ---- Page heading ---- */
    .page-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 14px;
        margin-bottom: 24px;
    }
    .page-heading h1 {
        color: var(--color-white);
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: 1px;
        margin: 0;
    }
    .page-heading h1 i { color: var(--color-primary); }

    /* ---- Stats strip ---- */
    .stats-strip {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 26px;
    }
    .stat-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: 14px;
        padding: 18px 16px;
        text-align: center;
        transition: border-color 0.25s ease;
    }
    .stat-card:hover { border-color: rgba(204,28,28,0.35); }
    .stat-number {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
        color: var(--color-primary);
    }
    .stat-label {
        font-size: 0.7rem;
        color: var(--color-muted);
        text-transform: uppercase;
        letter-spacing: 0.9px;
        margin-top: 6px;
    }

    /* ---- Search / filter bar ---- */
    .filter-bar {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .search-wrap {
        flex: 1;
        min-width: 200px;
        position: relative;
    }
    .search-wrap i {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--color-muted);
        font-size: 14px;
    }
    .search-input {
        width: 100%;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: 10px;
        padding: 10px 14px 10px 38px;
        color: var(--color-white);
        font-size: 0.88rem;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.25s ease;
    }
    .search-input:focus { border-color: var(--color-primary); }
    .search-input::placeholder { color: var(--color-muted); }

    .filter-select {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: 10px;
        padding: 10px 14px;
        color: var(--color-text);
        font-size: 0.88rem;
        outline: none;
        cursor: pointer;
        transition: border-color 0.25s ease;
    }
    .filter-select:focus { border-color: var(--color-primary); }
    .filter-select option { background: var(--color-surface-2); }

    /* ---- Table container ---- */
    .table-container {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,0.45);
    }

    .members-table {
        width: 100%;
        border-collapse: collapse;
    }

    .members-table thead {
        background: var(--color-surface-2);
    }

    .members-table th {
        padding: 14px 16px;
        text-align: left;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.1px;
        color: var(--color-silver);
        border-bottom: 1px solid var(--color-border);
        white-space: nowrap;
    }

    .members-table tbody tr {
        border-bottom: 1px solid #1C1C1C;
        transition: background 0.2s ease;
    }
    .members-table tbody tr:last-child { border-bottom: none; }
    .members-table tbody tr:hover { background: rgba(255,255,255,0.025); }

    .members-table td {
        padding: 14px 16px;
        font-size: 0.88rem;
        color: var(--color-text);
        vertical-align: middle;
    }

    /* ---- Member cell ---- */
    .member-cell {
        display: flex;
        align-items: center;
        gap: 11px;
    }
    .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #CC1C1C, #A01515);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border: 2px solid rgba(204,28,28,0.3);
        overflow: hidden;
    }
    .avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
    .avatar i { color: #fff; font-size: 15px; }
    .member-name { font-weight: 600; color: var(--color-white); font-size: 0.9rem; }

    /* ---- Badges ---- */
    .badge {
        display: inline-block;
        padding: 4px 11px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }
    .badge-unlimited {
        background: rgba(204,28,28,0.15);
        color: #FF5555;
        border: 1px solid rgba(204,28,28,0.3);
    }
    .badge-pervisit {
        background: rgba(26,58,143,0.2);
        color: #4A7AFF;
        border: 1px solid rgba(26,58,143,0.35);
    }
    .badge-active {
        background: rgba(34,197,94,0.12);
        color: #4ade80;
        border: 1px solid rgba(34,197,94,0.25);
    }
    .badge-expiring {
        background: rgba(26,58,143,0.2);
        color: #4A7AFF;
        border: 1px solid rgba(26,58,143,0.35);
    }

    .days-ok   { color: var(--color-muted); font-size: 0.75rem; margin-top: 3px; }
    .days-warn { color: #4A7AFF; font-size: 0.75rem; margin-top: 3px; }

    /* ---- Action buttons ---- */
    .action-wrap { display: flex; gap: 8px; align-items: center; }

    .btn-renew {
        padding: 7px 14px;
        font-size: 12px;
        background: rgba(26,58,143,0.2);
        color: #4A7AFF;
        border: 1px solid rgba(26,58,143,0.35);
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }
    .btn-renew:hover {
        background: rgba(26,58,143,0.35);
        transform: translateY(-1px);
    }

    .btn-cancel {
        padding: 7px 14px;
        font-size: 12px;
        background: rgba(204,28,28,0.1);
        color: #FF5555;
        border: 1px solid rgba(204,28,28,0.25);
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }
    .btn-cancel:hover {
        background: rgba(204,28,28,0.2);
        transform: translateY(-1px);
    }

    /* ---- Empty state ---- */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--color-muted);
    }
    .empty-state i { font-size: 52px; opacity: 0.25; margin-bottom: 14px; display: block; }

    /* ---- Modals ---- */
    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.75);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    .modal.active { display: flex; }

    .modal-content {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: 16px;
        padding: 30px;
        max-width: 460px;
        width: 92%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 40px rgba(0,0,0,0.7);
        color: var(--color-text);
    }
    .modal-content h2 {
        color: var(--color-white);
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 22px;
    }
    .modal-content h2 i { color: var(--color-primary); }
    .form-group { margin-bottom: 18px; }
    .form-label {
        display: block;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.1px;
        color: var(--color-muted);
        margin-bottom: 8px;
    }
    .form-control {
        width: 100%;
        background: var(--color-surface-2);
        border: 1px solid var(--color-border);
        color: var(--color-white);
        border-radius: 10px;
        padding: 11px 14px;
        font-size: 0.9rem;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.25s ease;
    }
    .form-control:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(204,28,28,0.12); }
    .form-control[readonly] { background: #111; color: var(--color-muted); cursor: default; }
    .form-control option { background: var(--color-surface-2); }

    .modal-footer { display: flex; gap: 10px; margin-top: 24px; }
    .btn-primary {
        flex: 1; padding: 12px; background: var(--color-primary); color: #fff;
        border: none; border-radius: 10px; font-size: 0.9rem; font-weight: 700;
        cursor: pointer; transition: all 0.25s ease;
        display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    }
    .btn-primary:hover { background: var(--color-primary-dk); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(204,28,28,0.35); }
    .btn-secondary {
        flex: 1; padding: 12px; background: var(--color-surface-2); color: var(--color-text);
        border: 1px solid var(--color-border); border-radius: 10px; font-size: 0.9rem;
        font-weight: 600; cursor: pointer; transition: all 0.25s ease;
        display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    }
    .btn-secondary:hover { background: #252525; border-color: rgba(204,28,28,0.3); }

    /* Alert modal */
    .alert-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.75);
        z-index: 10001;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .alert-modal.active { display: flex; animation: fadeIn 0.25s ease; }
    .alert-modal-content {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: 16px;
        padding: 28px;
        max-width: 380px;
        width: 92%;
        box-shadow: 0 10px 40px rgba(0,0,0,0.7);
        animation: slideUp 0.28s ease;
    }
    .alert-icon-wrap {
        width: 54px; height: 54px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 24px; margin-bottom: 16px;
    }
    .alert-icon-wrap.success { background: rgba(26,58,143,0.18); color: #4A7AFF; border: 1px solid rgba(26,58,143,0.3); }
    .alert-icon-wrap.danger  { background: rgba(204,28,28,0.15); color: #FF5555; border: 1px solid rgba(204,28,28,0.3); }
    .alert-modal-content h3 { color: var(--color-white); font-size: 1.1rem; font-weight: 700; margin: 0 0 10px; }
    .alert-modal-content p  { color: var(--color-text); font-size: 0.92rem; line-height: 1.55; margin: 0 0 22px; }
    .alert-modal-content p strong { color: var(--color-white); }
    .danger-note { color: #FF5555 !important; font-size: 0.82rem !important; margin-top: 6px !important; }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { transform: translateY(40px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

    /* ---- Mobile cards (replaces table on small screens) ---- */
    .member-card {
        display: none; /* hidden on desktop */
    }

    /* ---- Responsive ---- */
    @media (max-width: 768px) {
        .main-content { padding-bottom: 140px !important; }
        .stats-strip { grid-template-columns: repeat(2, 1fr); }
        .stat-number { font-size: 1.6rem; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-select { width: 100%; }
    }

    @media (max-width: 600px) {
        .main-content { padding-bottom: 160px !important; }
        .stats-strip { grid-template-columns: repeat(2, 1fr); gap: 10px; }

        /* Hide table, show cards */
        .table-container { background: transparent !important; border: none !important; box-shadow: none !important; border-radius: 0 !important; padding: 0 !important; }
        .table-container table,
        .table-container thead,
        .table-container tbody,
        .table-container th,
        .table-container td,
        .table-container tr { display: none !important; }

        .member-card {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 16px;
            background: transparent;
        }

        .mcard {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 14px;
            padding: 16px;
            transition: background 0.2s ease, border-color 0.2s ease;
        }
        .mcard:hover { background: var(--color-surface-2); border-color: rgba(204,28,28,0.3); }

        .mcard-top {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .mcard-info { flex: 1; min-width: 0; }

        .mcard-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--color-white);
        }

        .mcard-plan {
            font-size: 0.78rem;
            color: var(--color-muted);
            margin-top: 2px;
        }

        .mcard-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
        }

        .mcard-expire {
            font-size: 0.82rem;
            color: var(--color-text);
        }

        .mcard-expire span {
            display: block;
            font-size: 0.72rem;
            margin-top: 2px;
        }

        .mcard-actions {
            display: flex;
            gap: 10px;
        }

        .mcard-actions .btn-renew,
        .mcard-actions .btn-cancel {
            flex: 1;
            justify-content: center;
            padding: 10px;
            font-size: 13px;
        }
    }
</style>

<div class="main-content">

    <!-- Back -->
    <a href="memberships.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Memberships
    </a>

    <!-- Heading -->
    <div class="page-heading">
        <h1><i class="fas fa-users"></i> ACTIVE MEMBERSHIPS</h1>
    </div>

    <!-- Stats -->
    <div class="stats-strip">
        <div class="stat-card">
            <div class="stat-number"><?php echo $total; ?></div>
            <div class="stat-label">Total Active</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color:#4A7AFF;"><?php echo $expiring_count; ?></div>
            <div class="stat-label">Expiring Soon</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color:#FF5555;"><?php echo $unlimited_count; ?></div>
            <div class="stat-label">Unlimited</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color:#B0B0B0;"><?php echo $per_visit_count; ?></div>
            <div class="stat-label">Per Visit</div>
        </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" class="search-input" id="searchInput" placeholder="Search member name or plan...">
        </div>
        <select class="filter-select" id="statusFilter" onchange="filterTable()">
            <option value="all">All Status</option>
            <option value="active">Active</option>
            <option value="expiring">Expiring Soon</option>
        </select>
        <select class="filter-select" id="feeFilter" onchange="filterTable()">
            <option value="all">All Fee Types</option>
            <option value="unlimited">Unlimited</option>
            <option value="pervisit">Per Visit</option>
        </select>
    </div>

    <!-- Table -->
    <div class="table-container">
        <?php if (count($rows) > 0): ?>
        <table class="members-table" id="membersTable">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Plan</th>
                    <th>Start Date</th>
                    <th>Expires</th>
                    <th>Fee Type</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php foreach ($rows as $membership):
                    $dr = $membership['_days_remaining'];
                    $is_expiring = $dr <= 7;
                    $ft = isset($membership['fee_type']) ? $membership['fee_type'] : 'unlimited';
                    $pv = isset($membership['per_visit_fee']) ? $membership['per_visit_fee'] : 0;
                    $status_str = $is_expiring ? 'expiring' : 'active';
                    $fee_str = $ft === 'unlimited' ? 'unlimited' : 'pervisit';
                    $full_name = $membership['first_name'] . ' ' . $membership['last_name'];
                ?>
                <tr data-name="<?php echo strtolower($full_name); ?>" 
                    data-plan="<?php echo strtolower($membership['membership_name']); ?>"
                    data-status="<?php echo $status_str; ?>"
                    data-fee="<?php echo $fee_str; ?>">
                    <td>
                        <div class="member-cell">
                            <div class="avatar">
                                <?php if ($membership['photo']): ?>
                                    <img src="<?php echo $base_url; ?>assets/uploads/<?php echo $membership['photo']; ?>">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <span class="member-name"><?php echo $full_name; ?></span>
                        </div>
                    </td>
                    <td><?php echo $membership['membership_name']; ?></td>
                    <td><?php echo date('M d, Y', strtotime($membership['start_date'])); ?></td>
                    <td>
                        <?php echo date('M d, Y', strtotime($membership['end_date'])); ?>
                        <div class="<?php echo $is_expiring ? 'days-warn' : 'days-ok'; ?>">
                            <?php echo $dr; ?> days left
                        </div>
                    </td>
                    <td>
                        <span class="badge <?php echo $ft === 'unlimited' ? 'badge-unlimited' : 'badge-pervisit'; ?>">
                            <?php echo $ft === 'unlimited' ? 'UNLIMITED' : '₱' . number_format($pv, 0) . '/visit'; ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?php echo $is_expiring ? 'badge-expiring' : 'badge-active'; ?>">
                            <?php echo $is_expiring ? 'EXPIRING' : 'ACTIVE'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-wrap">
                            <button class="btn-renew" onclick="openRenewModal(<?php echo $membership['membership_id']; ?>, '<?php echo addslashes($full_name); ?>')">
                                <i class="fas fa-redo"></i> Renew
                            </button>
                            <button class="btn-cancel" onclick="confirmCancel(<?php echo $membership['membership_id']; ?>, '<?php echo addslashes($full_name); ?>')">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Mobile card layout (shown on small screens instead of table) -->
        <div class="member-card" id="memberCards">
            <?php foreach ($rows as $membership):
                $dr = $membership['_days_remaining'];
                $is_expiring = $dr <= 7;
                $ft = isset($membership['fee_type']) ? $membership['fee_type'] : 'unlimited';
                $pv = isset($membership['per_visit_fee']) ? $membership['per_visit_fee'] : 0;
                $status_str = $is_expiring ? 'expiring' : 'active';
                $fee_str = $ft === 'unlimited' ? 'unlimited' : 'pervisit';
                $full_name = $membership['first_name'] . ' ' . $membership['last_name'];
            ?>
            <div class="mcard"
                 data-name="<?php echo strtolower($full_name); ?>"
                 data-plan="<?php echo strtolower($membership['membership_name']); ?>"
                 data-status="<?php echo $status_str; ?>"
                 data-fee="<?php echo $fee_str; ?>">

                <div class="mcard-top">
                    <div class="avatar">
                        <?php if ($membership['photo']): ?>
                            <img src="<?php echo $base_url; ?>assets/uploads/<?php echo $membership['photo']; ?>">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="mcard-info">
                        <div class="mcard-name"><?php echo $full_name; ?></div>
                        <div class="mcard-plan"><?php echo $membership['membership_name']; ?></div>
                    </div>
                    <span class="badge <?php echo $is_expiring ? 'badge-expiring' : 'badge-active'; ?>">
                        <?php echo $is_expiring ? 'EXPIRING' : 'ACTIVE'; ?>
                    </span>
                </div>

                <div class="mcard-meta">
                    <div class="mcard-expire">
                        Expires: <?php echo date('M d, Y', strtotime($membership['end_date'])); ?>
                        <span class="<?php echo $is_expiring ? 'days-warn' : 'days-ok'; ?>">
                            <?php echo $dr; ?> days left
                        </span>
                    </div>
                    <span class="badge <?php echo $ft === 'unlimited' ? 'badge-unlimited' : 'badge-pervisit'; ?>">
                        <?php echo $ft === 'unlimited' ? 'UNLIMITED' : '₱' . number_format($pv, 0) . '/visit'; ?>
                    </span>
                </div>

                <div class="mcard-actions">
                    <button class="btn-renew" onclick="openRenewModal(<?php echo $membership['membership_id']; ?>, '<?php echo addslashes($full_name); ?>')">
                        <i class="fas fa-redo"></i> Renew
                    </button>
                    <button class="btn-cancel" onclick="confirmCancel(<?php echo $membership['membership_id']; ?>, '<?php echo addslashes($full_name); ?>')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No active memberships found.</p>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Renew Modal -->
<div class="modal" id="renewModal">
    <div class="modal-content">
        <h2><i class="fas fa-redo"></i> Renew Membership</h2>
        <form method="POST">
            <input type="hidden" name="action" value="renew">
            <input type="hidden" name="membership_id" id="renew_membership_id">
            <div class="form-group">
                <label class="form-label">Member</label>
                <input type="text" class="form-control" id="renew_member_name" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Extend by (days) *</label>
                <select name="extend_days" class="form-control" required>
                    <option value="">Choose extension period</option>
                    <?php 
                    $renew_plans_result->data_seek(0);
                    while($rp = $renew_plans_result->fetch_assoc()): ?>
                        <option value="<?php echo $rp['membership_duration_days']; ?>">
                            <?php echo $rp['membership_duration_days']; ?> Days — <?php echo $rp['membership_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small style="color:var(--color-muted);font-size:12px;margin-top:5px;display:block;">
                    <i class="fas fa-info-circle"></i> Based on available membership plans
                </small>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Renew</button>
                <button type="button" class="btn-secondary" onclick="closeModal('renewModal')"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Cancel Confirm Modal -->
<div class="alert-modal" id="cancelModal">
    <div class="alert-modal-content">
        <div class="alert-icon-wrap danger"><i class="fas fa-ban"></i></div>
        <h3>Cancel Membership</h3>
        <p>Are you sure you want to cancel the membership of <strong id="cancelMemberName"></strong>?<br>
        <span class="danger-note"><i class="fas fa-exclamation-triangle"></i> This cannot be undone.</span></p>
        <div class="modal-footer">
            <form method="POST" style="flex:1;display:flex;gap:10px;">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="membership_id" id="cancel_membership_id">
                <button type="submit" class="btn-primary" style="background:var(--color-primary);">
                    <i class="fas fa-ban"></i> Yes, Cancel
                </button>
                <button type="button" class="btn-secondary" onclick="closeModal('cancelModal')">
                    <i class="fas fa-arrow-left"></i> Go Back
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Success Alert -->
<div class="alert-modal" id="successModal">
    <div class="alert-modal-content">
        <div class="alert-icon-wrap success"><i class="fas fa-check-circle"></i></div>
        <h3>Success!</h3>
        <p id="successMsg"></p>
        <div class="modal-footer">
            <button class="btn-primary" onclick="closeModal('successModal')"><i class="fas fa-check"></i> OK</button>
        </div>
    </div>
</div>

<!-- Cancel Membership Form (hidden) -->
<form id="cancelForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="cancel">
    <input type="hidden" name="membership_id" id="cancel_id_hidden">
</form>

<?php include '../includes/sidebar.php'; ?>
<?php include '../includes/bottom-nav.php'; ?>

<?php
$extra_scripts = "
<script>
    function openRenewModal(id, name) {
        document.getElementById('renew_membership_id').value = id;
        document.getElementById('renew_member_name').value = name;
        document.getElementById('renewModal').classList.add('active');
    }

    function confirmCancel(id, name) {
        document.getElementById('cancel_membership_id').value = id;
        document.getElementById('cancelMemberName').textContent = name;
        document.getElementById('cancelModal').classList.add('active');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    // Close on overlay click
    document.querySelectorAll('.modal, .alert-modal').forEach(function(m) {
        m.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('active'); });
    });

    // Search + filter
    function filterTable() {
        const search  = document.getElementById('searchInput').value.toLowerCase();
        const status  = document.getElementById('statusFilter').value;
        const fee     = document.getElementById('feeFilter').value;

        // Filter table rows
        document.querySelectorAll('#tableBody tr').forEach(function(row) {
            const nameMatch   = row.dataset.name.includes(search) || row.dataset.plan.includes(search);
            const statusMatch = status === 'all' || row.dataset.status === status;
            const feeMatch    = fee === 'all' || row.dataset.fee === fee;
            row.style.display = (nameMatch && statusMatch && feeMatch) ? '' : 'none';
        });

        // Filter mobile cards
        document.querySelectorAll('#memberCards .mcard').forEach(function(card) {
            const nameMatch   = card.dataset.name.includes(search) || card.dataset.plan.includes(search);
            const statusMatch = status === 'all' || card.dataset.status === status;
            const feeMatch    = fee === 'all' || card.dataset.fee === fee;
            card.style.display = (nameMatch && statusMatch && feeMatch) ? '' : 'none';
        });
    }

    document.getElementById('searchInput').addEventListener('input', filterTable);

    " . (isset($_GET['success']) ? "
    const t = '" . $_GET['success'] . "';
    document.getElementById('successMsg').textContent = t === 'renewed' ? 'Membership renewed successfully!' : 'Membership cancelled.';
    document.getElementById('successModal').classList.add('active');
    " : "") . "
</script>
";

include '../includes/footer.php';
?>