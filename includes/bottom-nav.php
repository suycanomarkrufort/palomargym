<!-- Bottom Navigation -->
<div class="bottom-nav">
    <a href="<?php echo $base_url; ?>index.php" class="nav-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
        <i class="fas fa-home"></i>
        <span>HOME</span>
    </a>
    
    <a href="<?php echo $base_url; ?>pages/members.php" class="nav-item <?php echo ($current_page == 'members.php') ? 'active' : ''; ?>">
        <i class="fas fa-users"></i>
        <span>MEMBERS</span>
    </a>
    
    <a href="<?php echo $base_url; ?>pages/qr-scan.php" class="nav-item <?php echo ($current_page == 'qr-scan.php') ? 'active' : ''; ?>">
        <div class="qr-scan-btn">
            <i class="fas fa-qrcode"></i>
        </div>
    </a>
    
    <a href="<?php echo $base_url; ?>pages/logbook.php" class="nav-item <?php echo ($current_page == 'logbook.php') ? 'active' : ''; ?>">
        <i class="fas fa-book"></i>
        <span>LOGBOOK</span>
    </a>
    
    <a href="#more" class="nav-item nav-more-btn">
        <i class="fas fa-th"></i>
        <span>MORE</span>
    </a>
</div>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay"></div>