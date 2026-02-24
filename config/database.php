<?php
// Database Configuration for Wolf Palomar Gym System

// Database connection parameters
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'wolf_palomar_gym');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for proper emoji and special character support
$conn->set_charset("utf8mb4");

// System settings
define('SITE_NAME', 'Wolf Palomar Gym System');
define('SITE_VERSION', 'v2.5.6-STABLE');
define('SITE_URL', 'http://localhost/wolf-palomar-gym');
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');

/**
 * SECURITY KEY FOR QR CODE SIGNATURES
 * Huwag itong babaguhin kapag nakapag-print na ng mga ID.
 * Kapag binago ito, lahat ng lumang QR ay hindi na gagana (invalid signature).
 */
define('SYSTEM_SECRET_KEY', 'WolfPalomar_Secure_2026_Key_@#!');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone setting
date_default_timezone_set('Asia/Manila');

// Function to sanitize input
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}

// ============================================
// AUTHENTICATION FUNCTIONS (UNIFIED)
// ============================================

// Function to check if user is logged in (admin or staff)
function is_logged_in() {
    return isset($_SESSION['user_type']) && 
           (isset($_SESSION['admin_id']) || isset($_SESSION['staff_id']));
}

// Function to check if user is admin
function is_admin() {
    return isset($_SESSION['user_type']) && 
           $_SESSION['user_type'] === 'admin' && 
           isset($_SESSION['admin_id']);
}

// Function to check if user is staff
function is_staff() {
    return isset($_SESSION['user_type']) && 
           $_SESSION['user_type'] === 'staff' && 
           isset($_SESSION['staff_id']);
}

// Get current user ID (admin or staff)
function get_user_id() {
    if (is_admin()) {
        return $_SESSION['admin_id'];
    } elseif (is_staff()) {
        return $_SESSION['staff_id'];
    }
    return null;
}

// ALIAS for get_user_id (for backward compatibility)
function get_current_user_id() {
    return get_user_id();
}

// Get current user name
function get_current_user_name() {
    if (is_admin()) {
        return $_SESSION['admin_name'] ?? 'Admin';
    } elseif (is_staff()) {
        return $_SESSION['staff_name'] ?? 'Staff';
    }
    return 'Guest';
}

// Get current user type
function get_user_type() {
    return $_SESSION['user_type'] ?? null;
}

// Require admin access - redirect if not admin
function require_admin() {
    if (!is_admin()) {
        $_SESSION['error'] = 'Access denied. Admin privileges required.';
        redirect('index.php');
        exit();
    }
}

// Check if user has permission (admin has all permissions)
function has_permission($permission) {
    if (is_admin()) {
        return true; // Admin has all permissions
    }
    
    // Add staff permissions check here if needed
    // For now, staff has limited access
    return false;
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

// Function to redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

// Function to generate random ID
function generate_id($prefix = 'ID', $length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $prefix . $random_string;
}


// Function to log activity (UPDATED VERSION)
function log_activity($activity_type, $description, $acted_by = null) {
    global $conn;
    
    // If no acted_by provided, get current user
    if ($acted_by === null) {
        $acted_by = get_user_id();
    }
    
    // Get user type (admin or staff)
    $user_type = get_user_type();
    
    // Generate activity ID
    $activity_id = generate_id('ACT', 8);
    
    // Insert with user_type
    $stmt = $conn->prepare("INSERT INTO activity_logs (activity_id, activity_type, description, acted_by, user_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $activity_id, $activity_type, $description, $acted_by, $user_type);
    $stmt->execute();
    $stmt->close();
}

// Function to get gym setting
function get_setting($key) {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM gym_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return null;
}

// Function to update gym setting
function update_setting($key, $value) {
    global $conn;
    $stmt = $conn->prepare("UPDATE gym_settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->bind_param("ss", $value, $key);
    return $stmt->execute();
}
?>