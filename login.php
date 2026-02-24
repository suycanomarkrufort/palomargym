<?php
require_once 'config/database.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    if (!empty($email) && !empty($password)) {
        $stmt = $conn->prepare("SELECT * FROM admin WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['user_type'] = 'admin';
                log_activity('Login', 'Admin logged in: ' . $admin['email'], $admin['id']);
                redirect('index.php');
            } else {
                $error = 'Invalid email or password';
            }
        } else {
            $stmt->close();
            $stmt = $conn->prepare("SELECT * FROM staff WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $staff = $result->fetch_assoc();
                if (password_verify($password, $staff['password'])) {
                    $_SESSION['staff_id'] = $staff['id'];
                    $_SESSION['staff_name'] = $staff['first_name'] . ' ' . $staff['last_name'];
                    $_SESSION['staff_email'] = $staff['email'];
                    $_SESSION['user_type'] = 'staff';
                    log_activity('Staff Login', 'Staff logged in: ' . $staff['email'], $staff['id']);
                    redirect('index.php');
                } else {
                    $error = 'Invalid email or password';
                }
            } else {
                $error = 'Invalid email or password';
            }
        }
        $stmt->close();
    } else {
        $error = 'Please fill in all fields';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Wolf Palomar Gym</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #0A0A0A;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
        }

        /* ==================== PRELOADER ==================== */
        .preload-screen {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: #000000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            transition: opacity 0.8s cubic-bezier(0.7, 0, 0.3, 1), transform 0.8s cubic-bezier(0.7, 0, 0.3, 1);
        }

        .preload-screen.hidden {
            opacity: 0;
            transform: translateY(-20px);
            pointer-events: none;
            display: none;
        }

        /* Grid background — red tint */
        .grid-bg {
            position: absolute;
            width: 200%; height: 200%;
            background-image:
                linear-gradient(rgba(204, 28, 28, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(204, 28, 28, 0.04) 1px, transparent 1px);
            background-size: 40px 40px;
            transform: perspective(500px) rotateX(60deg);
            animation: gridMove 20s linear infinite;
            top: -50%;
            z-index: 1;
        }

        @keyframes gridMove {
            0%   { transform: perspective(500px) rotateX(60deg) translateY(0); }
            100% { transform: perspective(500px) rotateX(60deg) translateY(40px); }
        }

        .preload-content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .logo-container {
            position: relative;
            margin-bottom: 40px;
            overflow: hidden;
        }

        .big-logo {
            font-size: clamp(40px, 12vw, 80px);
            font-weight: 900;
            text-transform: uppercase;
            line-height: 0.9;
            letter-spacing: -3px;
            color: transparent;
            -webkit-text-stroke: 1px rgba(255,255,255,0.2);
            position: relative;
        }

        .big-logo span { display: block; position: relative; }

        /* WOLF — white fill */
        .big-logo .text-fill {
            position: absolute;
            top: 0; left: 0;
            width: 0%; height: 100%;
            color: #fff;
            overflow: hidden;
            white-space: nowrap;
            border-right: 4px solid #CC1C1C;
            animation: fillText 1.2s cubic-bezier(0.2, 1, 0.3, 1) forwards;
            -webkit-text-stroke: 0;
        }

        /* PALOMAR — red fill */
        .big-logo .palomar-layer .text-fill {
            color: #CC1C1C;
            animation-delay: 0.3s;
        }

        @keyframes fillText {
            0%   { width: 0%; }
            100% { width: 100%; border-right-color: transparent; }
        }

        /* Loading bar — red */
        .loading-bar-container {
            width: 200px; height: 2px;
            background: rgba(255,255,255,0.08);
            position: relative;
            overflow: hidden;
            border-radius: 4px;
        }

        .loading-bar-fill {
            position: absolute;
            top: 0; left: 0;
            height: 100%; width: 100%;
            background: #CC1C1C;
            transform: translateX(-100%);
            animation: loadProgress 1.5s ease-in-out forwards;
            box-shadow: 0 0 15px rgba(204, 28, 28, 0.8);
        }

        @keyframes loadProgress {
            0%   { transform: translateX(-100%); }
            100% { transform: translateX(0%); }
        }

        .loading-percentage {
            margin-top: 15px;
            font-family: 'Courier New', monospace;
            color: #CC1C1C;
            font-size: 12px;
            letter-spacing: 2px;
        }

        /* ==================== SUBMIT LOADING OVERLAY ==================== */
        .loading-screen {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-screen.active {
            display: flex;
            animation: fadeInOverlay 0.3s ease-out;
        }

        @keyframes fadeInOverlay {
            from { opacity: 0; backdrop-filter: blur(0px); }
            to   { opacity: 1; backdrop-filter: blur(8px); }
        }

        /* Pulse loader — red */
        .pulse-loader {
            position: relative;
            width: 80px; height: 80px;
        }

        .pulse-ring {
            position: absolute;
            width: 100%; height: 100%;
            border-radius: 50%;
            border: 2px solid #CC1C1C;
            animation: ripple 1.5s cubic-bezier(0, 0.2, 0.8, 1) infinite;
            opacity: 0;
        }

        .pulse-ring:nth-child(2) { animation-delay: -0.5s; }

        .pulse-core {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 40px; height: 40px;
            background: #CC1C1C;
            border-radius: 50%;
            box-shadow: 0 0 30px rgba(204, 28, 28, 0.7);
            animation: heartBeat 1.2s infinite;
        }

        @keyframes ripple {
            0%   { transform: scale(0.8); opacity: 1; border-width: 4px; }
            100% { transform: scale(2.5); opacity: 0; border-width: 0px; }
        }

        @keyframes heartBeat {
            0%   { transform: translate(-50%, -50%) scale(0.95); box-shadow: 0 0 0 0 rgba(204, 28, 28, 0.7); }
            70%  { transform: translate(-50%, -50%) scale(1.1);  box-shadow: 0 0 0 20px rgba(204, 28, 28, 0); }
            100% { transform: translate(-50%, -50%) scale(0.95); box-shadow: 0 0 0 0 rgba(204, 28, 28, 0); }
        }

        .authenticating-text {
            position: absolute;
            top: calc(50% + 60px);
            color: #CCCCCC;
            letter-spacing: 4px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .authenticating-text::after {
            content: '';
            animation: dots 1.5s infinite;
        }

        @keyframes dots {
            0%, 20% { content: ''; }
            40%      { content: '.'; }
            60%      { content: '..'; }
            80%, 100%{ content: '...'; }
        }

        /* ==================== MAIN CONTENT ==================== */
        .main-wrapper {
            opacity: 0;
            transform: scale(0.95);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        .main-wrapper.visible {
            opacity: 1;
            transform: scale(1);
        }

        /* Login card — dark surface */
        .login-card {
            background: #141414;
            border: 1px solid #2A2A2A;
            border-radius: 32px;
            padding: 40px 30px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7), 0 0 40px rgba(204, 28, 28, 0.06);
            text-align: center;
        }

        /* Header */
        .login-header h1 {
            color: #FFFFFF;
            font-size: clamp(22px, 5vw, 26px);
            font-weight: 800;
            letter-spacing: -1px;
            text-transform: uppercase;
        }

        /* "PALOMAR" — red accent */
        .login-header .highlight { color: #CC1C1C; }

        .login-header p {
            color: #777777;
            font-size: 13px;
            margin-top: 8px;
            line-height: 1.5;
            padding: 0 10px;
        }

        /* Admin/Staff badge — navy */
        .user-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(26, 58, 143, 0.2);
            color: #4A7AFF;
            border: 1px solid rgba(26, 58, 143, 0.35);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            margin: 20px 0 30px 0;
            letter-spacing: 0.5px;
        }

        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }

        .form-label {
            display: block;
            color: #777777;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
            margin-left: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper { position: relative; }

        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #555555;
            font-size: 14px;
        }

        /* Input — dark */
        .form-control {
            width: 100%;
            background: #1C1C1C !important;
            border: 1px solid #2A2A2A !important;
            border-radius: 12px;
            padding: 14px 15px 14px 48px;
            color: #FFFFFF !important;
            font-size: 15px;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-control::placeholder { color: #444444; }

        .form-control:focus {
            border-color: #CC1C1C !important;
            background: #1C1C1C !important;
            box-shadow: 0 0 0 3px rgba(204, 28, 28, 0.12) !important;
        }

        /* Forgot password — red */
        .forgot-password {
            display: block;
            text-align: right;
            margin-top: 8px;
            color: #CC1C1C;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: opacity 0.3s;
        }

        .forgot-password:hover { opacity: 0.75; }

        /* Login button — red */
        .btn-login {
            width: 100%;
            background: #CC1C1C;
            color: #FFFFFF;
            border: none;
            border-radius: 14px;
            padding: 16px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-transform: uppercase;
            box-shadow: 0 5px 18px rgba(204, 28, 28, 0.4);
        }

        .btn-login:hover:not(:disabled) {
            background: #A01515;
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(204, 28, 28, 0.5);
        }

        .btn-login:disabled { opacity: 0.7; cursor: not-allowed; }

        .btn-login .btn-spinner {
            display: none;
            width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .btn-login.loading .btn-text    { display: none; }
        .btn-login.loading .btn-spinner { display: inline-block; }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Error message */
        .error-message {
            background: rgba(204, 28, 28, 0.1);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.3);
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25%       { transform: translateX(-10px); }
            75%       { transform: translateX(10px); }
        }

        /* Footer */
        .login-footer {
            margin-top: 35px;
            color: #555555;
            font-size: 11px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    
    <!-- Preloader — hidden instantly if there's an error -->
    <div class="preload-screen" id="preloadScreen" style="<?php echo !empty($error) ? 'display:none;' : ''; ?>">
        <div class="grid-bg"></div>
        
        <div class="preload-content">
            <div class="logo-container">
                <div class="big-logo">
                    <span class="wolf-layer">WOLF <div class="text-fill">WOLF</div></span>
                    <span class="palomar-layer">PALOMAR <div class="text-fill">PALOMAR</div></span>
                </div>
            </div>
            
            <div class="loading-bar-container">
                <div class="loading-bar-fill"></div>
            </div>
            <div class="loading-percentage">SYSTEM INITIALIZING</div>
        </div>
    </div>

    <!-- Submit loading overlay -->
    <div class="loading-screen" id="loadingScreen">
        <div class="pulse-loader">
            <div class="pulse-ring"></div>
            <div class="pulse-ring"></div>
            <div class="pulse-core"></div>
        </div>
        <div class="authenticating-text">Verifying Credentials</div>
    </div>

    <!-- Main login content -->
    <div class="main-wrapper <?php echo !empty($error) ? 'visible' : ''; ?>" id="mainContent">
        <div class="login-card">
            <div class="login-header">
                <h1>WOLF <span class="highlight">PALOMAR</span></h1>
                <p>QR Code Gym Membership and Attendance System</p>
                <div class="user-badge">
                    <i class="fas fa-shield-halved"></i> Admin/Staff Login
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-circle-exclamation"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" placeholder="name@palomargym.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required autofocus>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 25px;">
                    <label class="form-label">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="btn-text">LOGIN <i class="fas fa-arrow-right"></i></span>
                    <span class="btn-spinner"></span>
                </button>
            </form>
            
            <div class="login-footer">
                <p>© 2026 Wolf Palomar Gym • v2.5.6-STABLE</p>
            </div>
        </div>
    </div>

    <script>
        const hasError = <?php echo !empty($error) ? 'true' : 'false'; ?>;

        window.addEventListener('load', function() {
            const preloadScreen = document.getElementById('preloadScreen');
            const mainContent = document.getElementById('mainContent');
            
            if (!hasError) {
                setTimeout(function() {
                    preloadScreen.classList.add('hidden');
                    setTimeout(function() {
                        mainContent.classList.add('visible');
                    }, 300);
                }, 1800);
            }
        });

        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const loadingScreen = document.getElementById('loadingScreen');

        loginForm.addEventListener('submit', function(e) {
            if (loginForm.checkValidity()) {
                loginBtn.classList.add('loading');
                loginBtn.disabled = true;
                
                setTimeout(() => {
                    loadingScreen.classList.add('active');
                }, 300);
            }
        });

        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                loadingScreen.classList.remove('active');
                loginBtn.classList.remove('loading');
                loginBtn.disabled = false;
            }
        });
    </script>

</body>
</html>