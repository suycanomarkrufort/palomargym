<?php
require_once 'config/database.php';

if (is_logged_in()) {
    // Determine user type and redirect URL
    $user_id = get_user_id();
    $is_staff_user = is_staff();
    $user_type = $is_staff_user ? 'Staff' : 'Admin';
    $redirect_url = $is_staff_user ? 'login.php' : 'login.php';
    
    // Log activity before logout
    log_activity($user_type . ' Logout', $user_type . ' logged out', $user_id);
    
    // Destroy session
    session_destroy();
    
    redirect($redirect_url);
} else {
    redirect('login.php');
}
?>