<?php
// 1. DATABASE CONFIGURATION
require_once 'config/database.php';

// 2. INCLUDE PHPMAILER
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Redirect kung naka-login na
if (function_exists('is_logged_in') && is_logged_in()) {
    redirect('index.php');
}

$success = '';
$error = '';
$page_state = 'forgot';
$valid_token = false;
$user_data = null;

// ============================================================
// CHECK IF TOKEN EXISTS (From Email Link)
// ============================================================
if (isset($_GET['token'])) {
    $token = sanitize_input($_GET['token']);
    
    $stmt = $conn->prepare("SELECT 'admin' as type, id, email, first_name, reset_token_expires FROM admin WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt = $conn->prepare("SELECT 'staff' as type, id, email, first_name, reset_token_expires FROM staff WHERE reset_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        
        if (strtotime($user_data['reset_token_expires']) > time()) {
            $valid_token = true;
            $page_state = 'reset';
        } else {
            $error = "This reset link has expired. Please request a new one.";
            $page_state = 'forgot';
        }
    } else {
        $error = "Invalid reset link.";
        $page_state = 'forgot';
    }
    $stmt->close();
}

// ============================================================
// HANDLE FORM SUBMISSIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ========== FORGOT PASSWORD FORM ==========
    if (isset($_POST['action']) && $_POST['action'] == 'forgot') {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        
        if (!empty($email)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format";
            } else {
                $stmt = $conn->prepare("SELECT 'admin' as type, id, email, first_name FROM admin WHERE email = ? 
                                        UNION 
                                        SELECT 'staff' as type, id, email, first_name FROM staff WHERE email = ?");
                $stmt->bind_param("ss", $email, $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    $table = $user['type'];
                    $user_id = $user['id'];
                    
                    $update_sql = "UPDATE $table SET reset_token = ?, reset_token_expires = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ssi", $token, $expires, $user_id);
                    $update_stmt->execute();
                    
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
                    $host = $_SERVER['HTTP_HOST'];
                    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
                    if ($scriptDir == '/' || $scriptDir == '\\' || $scriptDir == '.') {
                        $scriptDir = '';
                    }
                    
                    $reset_link = $protocol . $host . $scriptDir . "/forgot-password.php?token=" . urlencode($token);
                    
                    $mail = new PHPMailer(true);
                    
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'sirpalomargym@gmail.com'; 
                        $mail->Password   = 'xhvb ptjb wkyl sirt';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;
                        
                        $mail->setFrom('no-reply@palomargym.com', 'Wolf Palomar Gym');
                        $mail->addAddress($email, $user['first_name']);
                        
                        $mail->isHTML(true);
                        $mail->Subject = 'Password Reset - Wolf Palomar Gym';
                        
                        $mail->Body = "
                        <html>
                        <head>
                            <style>
                                body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #0A0A0A; }
                                .container { max-width: 600px; margin: 0 auto; background: #141414; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.5); border: 1px solid #2A2A2A; }
                                .header { background: #000; color: #CC1C1C; padding: 30px 20px; text-align: center; }
                                .header h1 { margin: 0; font-size: 24px; letter-spacing: 2px; }
                                .content { padding: 40px 30px; color: #CCCCCC; }
                                .button { background: #CC1C1C; color: #fff; padding: 15px 35px; text-decoration: none; 
                                         border-radius: 50px; display: inline-block; font-weight: 800; margin: 25px 0; 
                                         box-shadow: 0 4px 6px rgba(204, 28, 28, 0.4); }
                                .footer { text-align: center; padding: 20px; background: #0A0A0A; color: #777; font-size: 12px; }
                                .link-text { word-break: break-all; color: #777; font-size: 13px; margin-top: 10px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>WOLF PALOMAR GYM</h1>
                                </div>
                                <div class='content'>
                                    <h2 style='margin-top: 0; color: #FFFFFF;'>Password Reset Request</h2>
                                    <p>Hi <strong style='color:#FFFFFF;'>{$user['first_name']}</strong>,</p>
                                    <p>We received a request to reset the password for your account. If you made this request, please click the button below:</p>
                                    <div style='text-align: center;'>
                                        <a href='$reset_link' class='button'>RESET PASSWORD</a>
                                    </div>
                                    <p>Or copy and paste this link into your browser:</p>
                                    <p class='link-text'>$reset_link</p>
                                    <hr style='border: 0; border-top: 1px solid #2A2A2A; margin: 20px 0;'>
                                    <p style='font-size: 13px; color: #777;'><strong style='color:#CCCCCC;'>Note:</strong> This link will expire in 1 hour for security reasons.</p>
                                    <p style='font-size: 13px; color: #777;'>If you didn't request this change, you can safely ignore this email.</p>
                                </div>
                                <div class='footer'>
                                    <p>© " . date('Y') . " Wolf Palomar Gym Management System</p>
                                    <p>Automated Message - Do Not Reply</p>
                                </div>
                            </div>
                        </body>
                        </html>
                        ";
                        
                        $mail->AltBody = "Hi {$user['first_name']},\n\nClick this link to reset your password: $reset_link\n\nThis link expires in 1 hour.";
                        
                        $mail->send();
                        
                        if(function_exists('log_activity')) {
                            log_activity('Password Reset Request', "Reset link sent to: $email", $user_id);
                        }
                        
                        $success = "Password reset link has been sent to your email!";
                        
                    } catch (Exception $e) {
                        $error = "System could not send the email. Please check your internet connection or contact admin.";
                    }
                    
                    $update_stmt->close();
                } else {
                    $success = "If that email exists in our system, a reset link has been sent.";
                }
                $stmt->close();
            }
        } else {
            $error = 'Please enter your email address';
        }
    }
    
    // ========== RESET PASSWORD FORM ==========
    if (isset($_POST['action']) && $_POST['action'] == 'reset' && $valid_token) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $token = sanitize_input($_POST['token']);
        
        if ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $table = $user_data['type'];
            $user_id = $user_data['id'];
            
            if ($table == 'admin') {
                $update_stmt = $conn->prepare("UPDATE admin SET 
                             password = ?,
                             reset_token = NULL,
                             reset_token_expires = NULL 
                             WHERE id = ?");
            } else {
                $update_stmt = $conn->prepare("UPDATE staff SET 
                             password = ?,
                             reset_token = NULL,
                             reset_token_expires = NULL 
                             WHERE id = ?");
            }
            
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                log_activity('Password Reset', "Password reset completed for: {$user_data['email']}", $user_id);
                $page_state = 'complete';
            } else {
                $error = "Failed to update password. Please try again.";
            }
            
            $update_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_state == 'reset' ? 'Reset Password' : 'Forgot Password'; ?> | Wolf Palomar Gym</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
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
            margin: 0; padding: 0;
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
        }

        /* Main card — dark surface */
        .reset-card {
            background: #141414;
            border: 1px solid #2A2A2A;
            border-radius: 32px;
            padding: 40px 30px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7), 0 0 40px rgba(204, 28, 28, 0.05);
            text-align: center;
        }

        /* Icon — red brand */
        .reset-icon {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, #CC1C1C, #A01515);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: #FFFFFF;
            font-size: 32px;
            box-shadow: 0 10px 25px rgba(204, 28, 28, 0.4);
        }

        /* Success icon — navy */
        .reset-icon.success {
            background: linear-gradient(135deg, #1A3A8F, #243FA0);
            box-shadow: 0 10px 25px rgba(26, 58, 143, 0.4);
        }

        .reset-header h1 {
            color: #FFFFFF;
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .reset-header p {
            color: #777777;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-label {
            display: block;
            color: #777777;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
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

        /* Inputs — dark */
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
            box-shadow: 0 0 0 3px rgba(204, 28, 28, 0.12) !important;
        }

        /* Buttons — red */
        .btn-reset, .btn-login {
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-transform: uppercase;
            box-shadow: 0 4px 18px rgba(204, 28, 28, 0.4);
            text-decoration: none;
        }

        .btn-reset:hover, .btn-login:hover {
            background: #A01515;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(204, 28, 28, 0.5);
        }

        /* Success message — navy tint */
        .success-message {
            background: rgba(26, 58, 143, 0.12);
            color: #4A7AFF;
            border: 1px solid rgba(26, 58, 143, 0.3);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: left;
        }

        /* Error message — red tint */
        .error-message {
            background: rgba(204, 28, 28, 0.1);
            color: #FF5555;
            border: 1px solid rgba(204, 28, 28, 0.3);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: left;
        }

        /* Back link — silver */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #777777;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            margin-top: 25px;
            transition: color 0.3s;
        }

        .back-link:hover { color: #CCCCCC; }

        /* Password requirements box — navy tint */
        .password-requirements {
            background: rgba(26, 58, 143, 0.1);
            border: 1px solid rgba(26, 58, 143, 0.25);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: left;
        }

        .password-requirements h4 {
            font-size: 12px;
            color: #4A7AFF;
            margin-bottom: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .password-requirements ul {
            list-style: none;
            padding: 0; margin: 0;
        }

        .password-requirements li {
            font-size: 12px;
            color: #4A7AFF;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .password-requirements li i { font-size: 8px; }

        /* Countdown progress bar — navy */
        .countdown-progress {
            width: 100%; height: 4px;
            background: #2A2A2A;
            border-radius: 2px;
            margin-top: 20px;
            overflow: hidden;
        }

        .countdown-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #1A3A8F, #4A7AFF);
            width: 100%;
            animation: countdown-shrink 5s linear;
        }

        @keyframes countdown-shrink {
            from { width: 100%; }
            to   { width: 0%; }
        }

        .btn-reset.loading, .btn-login.loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .btn-reset.loading i, .btn-login.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin { 100% { transform: rotate(360deg); } }

        @media (max-width: 480px) {
            .reset-card { padding: 35px 20px; }
        }
    </style>
</head>
<body>
    
    <div class="reset-card">
        
        <?php if ($page_state == 'complete'): ?>
            <!-- ========== SUCCESS STATE ========== -->
            <div class="reset-icon success">
                <i class="fas fa-check"></i>
            </div>
            <div class="reset-header">
                <h1>Password Reset Successful!</h1>
                <p>Your password has been successfully reset. You can now login with your new password.</p>
                <p style="color: #777777; font-size: 13px; margin-top: 15px;">
                    Redirecting to login page in <strong style="color:#FFFFFF;"><span id="countdown">5</span></strong> seconds...
                </p>
                <div class="countdown-progress">
                    <div class="countdown-progress-bar"></div>
                </div>
            </div>
            <a href="login.php" class="btn-login" style="margin-top: 25px;">
                GO TO LOGIN NOW <i class="fas fa-arrow-right"></i>
            </a>
            
            <script>
                let seconds = 5;
                const countdownElement = document.getElementById('countdown');
                
                const countdown = setInterval(function() {
                    seconds--;
                    countdownElement.textContent = seconds;
                    if (seconds <= 0) {
                        clearInterval(countdown);
                        window.location.href = 'login.php';
                    }
                }, 1000);
            </script>
            
        <?php elseif ($page_state == 'reset' && $valid_token): ?>
            <!-- ========== RESET PASSWORD STATE ========== -->
            <div class="reset-icon">
                <i class="fas fa-lock"></i>
            </div>
            
            <div class="reset-header">
                <h1>Create New Password</h1>
                <p>Enter your new password below.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <div class="password-requirements">
                <h4><i class="fas fa-info-circle"></i> Password Requirements:</h4>
                <ul>
                    <li><i class="fas fa-circle"></i> At least 6 characters long</li>
                    <li><i class="fas fa-circle"></i> Mix of letters and numbers recommended</li>
                </ul>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-control" placeholder="Enter new password" required minlength="6" autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required minlength="6">
                    </div>
                </div>
                
                <button type="submit" class="btn-reset">
                    RESET PASSWORD <i class="fas fa-check"></i>
                </button>
            </form>
            
        <?php else: ?>
            <!-- ========== FORGOT PASSWORD STATE ========== -->
            <div class="reset-icon">
                <i class="fas fa-key"></i>
            </div>
            
            <div class="reset-header">
                <h1>Forgot Password?</h1>
                <p>Enter your email address and we'll send you a link to reset your password.</p>
            </div>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo $success; ?></div>
                </div>
                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" onsubmit="document.querySelector('.btn-reset').classList.add('loading');">
                    <input type="hidden" name="action" value="forgot">
                    
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" class="form-control" placeholder="name@palomargym.com" required autofocus value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-reset">
                        SEND RESET LINK <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
                
                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            <?php endif; ?>
        <?php endif; ?>
        
    </div>

</body>
</html>