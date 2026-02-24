<?php
// Common header variables and setup for all pages

// Determine base URL based on current file location
$current_file = $_SERVER['PHP_SELF'];
$base_url = (strpos($current_file, '/pages/') !== false) ? '../' : './';

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Get user info (already fetched in main file, but these variables need to be available)
// The main page should already have $admin, $is_staff_user, $goal_percentage, $today_revenue, and $daily_goal defined
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Wolf Palomar Gym</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>