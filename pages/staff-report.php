<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['staff_id'])) {
    redirect('../staff-login.php');
}

$staff_id = $_SESSION['staff_id'];
$is_staff_user = true;

$user_query = $conn->prepare("SELECT * FROM staff WHERE id = ?");
$user_query->bind_param("i", $staff_id);
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

$success = false;

$members_result = $conn->query("SELECT id, first_name, last_name, member_id FROM member ORDER BY first_name ASC");

$all_members = [];
while ($m = $members_result->fetch_assoc()) {
    $all_members[] = [
        'id'   => (string)$m['id'], // string for consistent JS key comparison
        'name' => $m['first_name'] . ' ' . $m['last_name'],
        'mid'  => $m['member_id']
    ];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tagged_members = isset($_POST['tagged_members']) ? json_encode($_POST['tagged_members']) : '[]';
    $report_details = sanitize_input($_POST['report_details']);
    
    $insert_query = $conn->prepare("INSERT INTO staff_reports (staff_id, tagged_members, report_details) VALUES (?, ?, ?)");
    $insert_query->bind_param("iss", $staff_id, $tagged_members, $report_details);
    
    if ($insert_query->execute()) {
        $success = true;
    }
}

$page_title = "Staff Audit Report";
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
            --color-white:     #FFFFFF;
            --color-text:      #CCCCCC;
            --color-muted:     #777777;
        }

        body, .main-content { background: var(--color-bg) !important; color: var(--color-text) !important; }

        .report-container { padding: 20px; max-width: 800px; margin: 0 auto; padding-bottom: 100px; }

        .back-link {
            display: inline-flex; align-items: center; gap: 8px;
            color: #4A7AFF; text-decoration: none; margin-bottom: 20px;
            font-size: 14px; font-weight: 600; transition: all 0.3s ease;
        }
        .back-link:hover { color: var(--color-white); transform: translateX(-4px); }

        .report-card {
            background: var(--color-surface) !important;
            border: 1px solid var(--color-border); border-radius: 16px;
            padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        }

        .report-title { font-size: 26px; font-weight: 800; font-style: italic; color: var(--color-white); margin-bottom: 30px; }

        .section-label {
            color: var(--color-muted); font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;
        }

        .search-wrapper { position: relative; margin-bottom: 8px; }

        .search-wrapper input {
            width: 100%; padding: 12px 40px 12px 15px;
            background: var(--color-surface-2) !important;
            border: 1px solid var(--color-border) !important;
            border-radius: 10px; color: var(--color-white) !important;
            font-size: 14px; outline: none; transition: border-color 0.3s ease; box-sizing: border-box;
        }
        .search-wrapper input::placeholder { color: #555; }
        .search-wrapper input:focus { border-color: var(--color-primary) !important; box-shadow: 0 0 0 3px rgba(204,28,28,0.12); }
        .search-wrapper i { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: var(--color-muted); pointer-events: none; }

        .member-dropdown {
            background: var(--color-surface-2); border: 1px solid var(--color-border);
            border-radius: 10px; max-height: 250px; overflow-y: auto;
            margin-bottom: 14px; display: none; box-shadow: 0 8px 24px rgba(0,0,0,0.5);
        }
        .member-dropdown.open { display: block; }
        .member-dropdown::-webkit-scrollbar { width: 5px; }
        .member-dropdown::-webkit-scrollbar-thumb { background: #3A3A3A; border-radius: 10px; }

        .dropdown-item {
            padding: 11px 14px; border-bottom: 1px solid var(--color-border);
            cursor: pointer; transition: background 0.15s ease;
            color: var(--color-text); font-size: 14px;
            display: flex; align-items: center; gap: 10px; user-select: none;
        }
        .dropdown-item:last-child { border-bottom: none; }
        .dropdown-item:hover { background: #252525; color: var(--color-white); }
        .dropdown-item.selected { background: rgba(204,28,28,0.13); color: #FF6868; font-weight: 600; }

        .check-box {
            width: 17px; height: 17px; border-radius: 4px; border: 1.5px solid #444;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; font-size: 10px; transition: all 0.15s ease;
        }
        .dropdown-item.selected .check-box { background: var(--color-primary); border-color: var(--color-primary); color: white; }

        .dropdown-empty { padding: 18px; text-align: center; color: var(--color-muted); font-size: 13px; }

        .chips-wrap { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; min-height: 4px; }

        .chip {
            background: rgba(204,28,28,0.16); color: #FF7070;
            border: 1px solid rgba(204,28,28,0.32); padding: 5px 11px;
            border-radius: 20px; font-size: 12px; font-weight: 700;
            display: flex; align-items: center; gap: 7px;
        }
        .chip-x {
            width: 16px; height: 16px; background: rgba(255,255,255,0.1);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 11px; transition: background 0.2s;
        }
        .chip-x:hover { background: rgba(255,255,255,0.25); }

        .report-textarea {
            width: 100%; min-height: 200px; padding: 15px;
            background: var(--color-surface-2) !important; border: 1px solid var(--color-border) !important;
            border-radius: 10px; color: var(--color-text) !important;
            font-size: 14px; resize: vertical; margin-bottom: 20px;
            font-family: inherit; outline: none; transition: border-color 0.3s ease; box-sizing: border-box;
        }
        .report-textarea::placeholder { color: #555; }
        .report-textarea:focus { border-color: var(--color-primary) !important; box-shadow: 0 0 0 3px rgba(204,28,28,0.12); }

        .submit-btn {
            width: 100%; padding: 15px; background: var(--color-primary); border: none;
            border-radius: 10px; color: var(--color-white); font-size: 15px;
            font-weight: 700; font-style: italic; text-transform: uppercase;
            cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(204,28,28,0.4);
        }
        .submit-btn:hover { background: var(--color-primary-dk); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(204,28,28,0.5); }

        .alert-modal {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.75); z-index: 10001;
            align-items: center; justify-content: center;
            padding: 20px; animation: fadeIn 0.3s ease;
        }
        .alert-modal.active { display: flex; }

        .alert-modal-content {
            background: var(--color-surface) !important; border: 1px solid var(--color-border);
            border-radius: 16px; padding: 30px; max-width: 400px; width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.6); animation: slideUp 0.3s ease;
        }

        .alert-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }

        .alert-icon {
            width: 56px; height: 56px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 26px; flex-shrink: 0;
        }
        .alert-icon.success { background: rgba(26,58,143,0.2); color: #4A7AFF; border: 1px solid rgba(26,58,143,0.3); }

        .alert-title h3 { margin: 0; font-size: 20px; font-weight: 700; color: var(--color-white); }
        .alert-body { margin-bottom: 25px; color: var(--color-text); line-height: 1.6; font-size: 15px; }
        .alert-footer { display: flex; gap: 10px; }

        .alert-btn { flex: 1; padding: 12px 20px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; transition: all 0.3s ease; font-size: 14px; }
        .alert-btn.success { background: var(--color-navy); color: var(--color-white); }
        .alert-btn.success:hover { background: #243FA0; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        @media (max-width: 768px) {
            .report-container { padding: 15px; }
            .report-card { padding: 20px; }
            .report-title { font-size: 22px; }
        }
    </style>
    
    <div class="report-container">
        
        <a href="staff-feedback.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Feedback Pool
        </a>
        
        <div class="report-card">
            <h1 class="report-title">STAFF AUDIT REPORT</h1>
            
            <form method="POST" id="reportForm">
                
                <div style="margin-bottom: 30px;">
                    <div class="section-label">TAG INVOLVED ASSETS</div>
                    
                    <div class="search-wrapper">
                        <input type="text" id="memberSearch" placeholder="Search asset database..." autocomplete="off">
                        <i class="fas fa-search"></i>
                    </div>

                    <div class="member-dropdown" id="memberDropdown"></div>
                    <div class="chips-wrap" id="chipsWrap"></div>
                </div>
                
                <div style="margin-bottom: 30px;">
                    <div class="section-label">REPORT DETAILS</div>
                    <textarea name="report_details" class="report-textarea" placeholder="Describe the issue or observation..." required></textarea>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> SUBMIT OFFICIAL REPORT
                </button>
                
            </form>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="alert-modal" id="successModal">
        <div class="alert-modal-content">
            <div class="alert-header">
                <div class="alert-icon success"><i class="fas fa-check-circle"></i></div>
                <div class="alert-title"><h3>Success!</h3></div>
            </div>
            <div class="alert-body"><p>Report submitted successfully!</p></div>
            <div class="alert-footer">
                <button class="alert-btn success" onclick="closeSuccessModal()">
                    <i class="fas fa-check"></i> OK
                </button>
            </div>
        </div>
    </div>
    
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/bottom-nav.php'; ?>
    
    <?php 
    $members_json = json_encode($all_members);
    $success_js = $success ? "document.getElementById('successModal').classList.add('active');" : "";

    $extra_scripts = <<<SCRIPTS
    <script>
        const ALL_MEMBERS = $members_json;
        let selected = {}; // { id: { id, name, mid } }

        const searchInput = document.getElementById('memberSearch');
        const dropdown    = document.getElementById('memberDropdown');
        const chipsWrap   = document.getElementById('chipsWrap');

        // ── Render dropdown list ──────────────────────────────────────
        function renderDropdown(query) {
            const q = query.trim().toLowerCase();
            if (!q) { dropdown.classList.remove('open'); return; }

            const hits = ALL_MEMBERS.filter(m =>
                (m.name + ' ' + m.mid).toLowerCase().includes(q)
            );

            if (hits.length === 0) {
                dropdown.innerHTML = '<div class="dropdown-empty">No members found</div>';
            } else {
                dropdown.innerHTML = hits.map(m => {
                    const isSel = !!selected[m.id];
                    const chk   = isSel ? '<i class="fas fa-check"></i>' : '';
                    return '<div class="dropdown-item ' + (isSel ? 'selected' : '') + '" '
                         + 'data-member-id="' + m.id + '">'
                         + '<span class="check-box">' + chk + '</span>'
                         + '<span>' + m.name + ' &mdash; <span style="color:#777;font-size:12px">' + m.mid + '</span></span>'
                         + '</div>';
                }).join('');

                // mousedown fires BEFORE blur — keeps dropdown open when clicking items
                dropdown.querySelectorAll('.dropdown-item').forEach(function(el) {
                    el.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        toggleMember(this.getAttribute('data-member-id'));
                    });
                });
            }

            dropdown.classList.add('open');
        }

        // ── Toggle selection ──────────────────────────────────────────
        function toggleMember(id) {
            // id is always a string (PHP cast + data-attribute)
            const member = ALL_MEMBERS.find(m => String(m.id) === String(id));
            if (!member) return;

            if (selected[id]) {
                delete selected[id];
            } else {
                selected[id] = member;
            }

            renderDropdown(searchInput.value);
            renderChips();
        }

        // ── Remove chip ───────────────────────────────────────────────
        function removeSelected(id) {
            delete selected[id];
            renderDropdown(searchInput.value);
            renderChips();
        }

        // ── Render chips ──────────────────────────────────────────────
        function renderChips() {
            chipsWrap.innerHTML = Object.values(selected).map(m =>
                '<div class="chip">'
                + m.name.toUpperCase()
                + '<span class="chip-x" onclick="removeSelected(' + m.id + ')">×</span>'
                + '</div>'
            ).join('');
        }

        // ── Search listener ───────────────────────────────────────────
        searchInput.addEventListener('input', function () {
            renderDropdown(this.value);
        });

        searchInput.addEventListener('focus', function () {
            if (this.value.trim()) renderDropdown(this.value);
        });

        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        });

        // ── Form submit ───────────────────────────────────────────────
        document.getElementById('reportForm').addEventListener('submit', function (e) {
            e.preventDefault();
            this.querySelectorAll('input[name="tagged_members[]"]').forEach(el => el.remove());

            Object.values(selected).forEach(function(m) {
                var input   = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'tagged_members[]';
                input.value = m.name;
                document.getElementById('reportForm').appendChild(input);
            });

            this.submit();
        });

        // ── Success modal ─────────────────────────────────────────────
        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }

        $success_js

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('alert-modal')) closeSuccessModal();
        });
    </script>
SCRIPTS;
    
    include '../includes/footer.php'; 
    ?>