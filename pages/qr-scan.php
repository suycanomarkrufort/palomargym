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

$page_title = "QR Scanner";

include '../includes/header.php';
?>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <style>
        /* =============================================
           WOLF PALOMAR FITNESS GYM — COLOR PALETTE
           --color-bg:        #0A0A0A
           --color-surface:   #141414
           --color-surface-2: #1C1C1C
           --color-border:    #2A2A2A
           --color-primary:   #CC1C1C
           --color-primary-dk:#A01515
           --color-navy:      #1A3A8F
           --color-silver:    #B0B0B0
           --color-white:     #FFFFFF
           --color-text:      #CCCCCC
           --color-muted:     #777777
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

        * { box-sizing: border-box; }

        body {
            background: var(--color-bg) !important;
            color: var(--color-text);
            padding-bottom: 120px;
        }

        /* ── Scanner Layout ── */
        .scanner-container {
            padding: 20px;
            max-width: 500px;
            margin: 0 auto;
        }

        .scanner-header {
            text-align: center;
            margin: 28px 0 24px;
        }

        .scanner-header h1 {
            font-size: 24px;
            font-weight: 900;
            text-transform: uppercase;
            color: var(--color-white);
            letter-spacing: 1px;
        }

        .scanner-header h1 span { color: var(--color-primary); }

        .scanner-header p {
            color: var(--color-muted);
            font-size: 13px;
            margin-top: 6px;
        }

        /* ── Scanner Frame ── */
        .scanner-frame {
            position: relative;
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 24px;
            overflow: hidden;
            background: #000;
            border: 3px solid var(--color-border);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.6);
            transition: border-color 0.3s ease;
        }

        /* Corner accent decorations */
        .scanner-frame::after {
            content: "";
            position: absolute;
            inset: 12px;
            border: 2px solid transparent;
            border-radius: 18px;
            background:
                linear-gradient(var(--color-bg), var(--color-bg)) padding-box,
                linear-gradient(135deg, var(--color-primary) 0%, transparent 50%, transparent 50%, var(--color-primary) 100%) border-box;
            z-index: 5;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        /* Scan animation line — red */
        .scanner-frame.active {
            border-color: var(--color-primary);
            box-shadow: 0 8px 30px rgba(204, 28, 28, 0.25);
        }

        .scanner-frame.active::before {
            content: "";
            position: absolute;
            top: 10%;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--color-primary);
            box-shadow: 0 0 14px rgba(204, 28, 28, 0.8);
            z-index: 10;
            animation: scanMove 2s infinite ease-in-out;
        }

        @keyframes scanMove {
            0%   { top: 10%; }
            50%  { top: 90%; }
            100% { top: 10%; }
        }

        #qr-reader {
            width: 100% !important;
            border: none !important;
        }

        /* ── Buttons ── */
        .scanner-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 20px;
        }

        /* Primary — red (Start/Stop) */
        .btn-main {
            grid-column: span 2;
            padding: 17px;
            border-radius: 14px;
            font-weight: 800;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 15px;
            background: var(--color-primary);
            color: #FFFFFF;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            box-shadow: 0 4px 18px rgba(204, 28, 28, 0.4);
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .btn-main:hover {
            background: var(--color-primary-dk);
            transform: translateY(-2px);
            box-shadow: 0 6px 22px rgba(204, 28, 28, 0.5);
        }

        /* Stop button — darker red */
        .btn-stop {
            background: #8B0000 !important;
            box-shadow: 0 4px 18px rgba(139, 0, 0, 0.4) !important;
        }

        .btn-stop:hover {
            background: #700000 !important;
        }

        /* Sub buttons — dark surface */
        .btn-sub {
            background: var(--color-surface);
            color: var(--color-text);
            padding: 13px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 12px;
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid var(--color-border);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-sub:hover {
            background: var(--color-surface-2);
            border-color: var(--color-primary);
            color: var(--color-white);
        }

        /* ── Modal Overlay ── */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            z-index: 1999;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.show { display: block; opacity: 1; }

        /* ── Result Card ── */
        .result-card {
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) scale(0.85);
            width: 88%;
            max-width: 380px;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 28px;
            padding: 28px 24px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.7);
            z-index: 2000;
            text-align: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .result-card.show {
            opacity: 1;
            visibility: visible;
            transform: translate(-50%, -50%) scale(1);
        }

        /* Member photo in result card */
        .member-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--color-border);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
            margin-bottom: 14px;
        }

        /* Member name in result */
        .result-card h2 {
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 6px;
            color: var(--color-white);
            font-size: 20px;
        }

        /* Plan badge */
        .plan-badge {
            background: var(--color-surface-2);
            color: var(--color-silver);
            padding: 5px 15px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 10px;
            text-transform: uppercase;
            border: 1px solid var(--color-border);
            letter-spacing: 0.5px;
        }

        /* Action details box */
        .action-details {
            background: var(--color-surface-2);
            border-radius: 16px;
            padding: 14px;
            margin-top: 14px;
            border: 1px solid var(--color-border);
        }

        .action-details .time-display {
            font-size: 22px;
            font-weight: 800;
            color: var(--color-white);
        }

        .action-details .action-label {
            color: var(--color-muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .action-details .expiry-label {
            color: var(--color-muted);
            font-size: 12px;
            margin-top: 4px;
        }
    </style>

    <div class="scanner-container">

        <div class="scanner-header">
            <h1>WOLF PALOMAR <span>GYM</span></h1>
            <p>Place QR code inside the box</p>
        </div>

        <div class="scanner-frame" id="scanner-frame">
            <div id="qr-reader"></div>
        </div>

        <div class="scanner-buttons">
            <button class="btn-main" id="start-scan-btn">
                <i class="fas fa-camera"></i> START SCANNING
            </button>
            <button class="btn-main btn-stop" id="stop-scan-btn" style="display: none;">
                <i class="fas fa-stop"></i> STOP SCANNER
            </button>
            <a href="manual-entry.php" class="btn-sub">
                <i class="fas fa-keyboard"></i> MANUAL ENTRY
            </a>
            <a href="guest-walkin.php" class="btn-sub">
                <i class="fas fa-user-clock"></i> GUEST ONLY
            </a>
        </div>

    </div>

    <!-- Centered Modal -->
    <div class="modal-overlay" id="modal-overlay"></div>
    <div class="result-card" id="result-card">
        <div id="result-content"></div>
    </div>

    <?php include '../includes/sidebar.php'; ?>

    <?php include '../includes/bottom-nav.php'; ?>

   <?php
    $extra_scripts = "
    <script>
        let html5QrCode;
        const startBtn      = document.getElementById('start-scan-btn');
        const stopBtn       = document.getElementById('stop-scan-btn');
        const scannerFrame  = document.getElementById('scanner-frame');
        const resultCard    = document.getElementById('result-card');
        const overlay       = document.getElementById('modal-overlay');
        const resultContent = document.getElementById('result-content');

        function initScanner() {
            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode('qr-reader');
            }
        }

        async function startScanning() {
            try {
                initScanner();

                if (html5QrCode.isScanning) {
                    await html5QrCode.stop();
                }

                closeModal();

                // CONFIG FIX: Added aspectRatio: 1.0 to force square
                const config = {
                    fps: 10,
                    aspectRatio: 1.0, 
                    qrbox: (w, h) => {
                        let size = Math.floor(Math.min(w, h) * 0.75);
                        return { width: size, height: size };
                    }
                };

                await html5QrCode.start({ facingMode: 'environment' }, config, onScanSuccess);
                
                scannerFrame.classList.add('active');
                startBtn.style.display = 'none';
                stopBtn.style.display  = 'flex';

            } catch (err) {
                if (err !== 'Ignore') {
                    console.error('Scanner Error:', err);
                    alert('Camera Error: ' + err);
                }
            }
        }

        async function stopScanning() {
            try {
                if (html5QrCode && html5QrCode.isScanning) {
                    await html5QrCode.stop();
                    scannerFrame.classList.remove('active');
                    startBtn.style.display = 'flex';
                    stopBtn.style.display  = 'none';
                }
            } catch (err) {
                console.error('Stop failed:', err);
            }
        }

        function onScanSuccess(decodedText) {
            if (window.navigator.vibrate) window.navigator.vibrate(100);
            stopScanning().then(() => {
                processAttendance(decodedText);
            });
        }

        function processAttendance(qrCode) {
            resultContent.innerHTML = '<p style=\"color:#CCCCCC; padding:20px;\">Verifying Scan...</p>';
            overlay.classList.add('show');
            resultCard.classList.add('show');

            fetch('process-attendance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'qr_code=' + encodeURIComponent(qrCode)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    let statusColor = '#CC1C1C';
                    if (data.member.status === 'Active')  statusColor = '#4CAF50';
                    if (data.member.status === 'Expired') statusColor = '#FF5555';
                    if (data.member.status === 'Student') statusColor = '#4A7AFF';

                    resultContent.innerHTML = `
                        <div style=\"position:relative; display:inline-block;\">
                            \${data.member.photo
                                ? `<img src=\"../assets/uploads/\${data.member.photo}\" class=\"member-photo\" style=\"border-color:\${statusColor}\">`
                                : `<div style=\"width:100px; height:100px; border-radius:50%; background:linear-gradient(135deg,#CC1C1C,#A01515); display:flex; align-items:center; justify-content:center; margin:0 auto 14px; border:3px solid \${statusColor};\"><i class=\"fas fa-user\" style=\"font-size:40px; color:#fff;\"></i></div>`
                            }
                        </div>
                        <h2>\${data.member.name}</h2>
                        <div class=\"plan-badge\">\${data.member.plan}</div>
                        <p style=\"font-weight:700; margin-bottom:14px; color:#CCCCCC;\">
                            Status: <span style=\"color:\${statusColor}\">\${data.member.status}</span>
                        </p>
                        <div class=\"action-details\">
                            <div class=\"action-label\">\${data.action.replace('-', ' ')} successful</div>
                            <div class=\"time-display\">\${data.time}</div>
                            \${data.membership_expiry ? `<div class=\"expiry-label\">Valid until: \${data.membership_expiry}</div>` : ''}
                        </div>
                    `;

                    if (data.member.status.toLowerCase() === 'active') {
                        setTimeout(() => { window.location.href = 'logbook.php'; }, 2500);
                    } else {
                        setTimeout(closeModalAndRestart, 5000);
                    }
                } else {
                    resultContent.innerHTML = `
                        <div style=\"width:80px; height:80px; border-radius:50%; background:rgba(204,28,28,0.15); border:1px solid rgba(204,28,28,0.3); display:flex; align-items:center; justify-content:center; margin:0 auto 16px;\">
                            <i class=\"fas fa-times-circle\" style=\"font-size:40px; color:#FF5555;\"></i>
                        </div>
                        <h2>SCAN FAILED</h2>
                        <p style=\"color:#777; margin-top:8px;\">\${data.message}</p>
                    `;
                    setTimeout(closeModalAndRestart, 4000);
                }
            })
            .catch((error) => {
                console.error(error);
                alert('Error connecting to server.');
                closeModalAndRestart();
            });
        }

        function closeModal() {
            resultCard.classList.remove('show');
            overlay.classList.remove('show');
        }

        function closeModalAndRestart() {
            closeModal();
            setTimeout(() => { 
                startScanning(); 
            }, 500);
        }

        startBtn.addEventListener('click', startScanning);
        stopBtn.addEventListener('click',  stopScanning);
    </script>
    ";

    include '../includes/footer.php';
    ?>