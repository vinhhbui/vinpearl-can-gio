<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar bg-dark text-white p-3" style="min-height: 100vh; width: 250px;">
    <h4 class="mb-4 text-center py-2 border-bottom">Admin Panel</h4>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?> text-white" href="index.php">
                <i class="fas fa-chart-line me-2"></i> Thống kê
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'rooms.php') ? 'active' : ''; ?> text-white" href="rooms.php">
                <i class="fas fa-bed me-2"></i> Quản lý Phòng
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'services.php') ? 'active' : ''; ?> text-white" href="services.php">
                <i class="fas fa-concierge-bell me-2"></i> Dịch vụ & Tiện ích
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'promotions.php') ? 'active' : ''; ?> text-white" href="promotions.php">
                <i class="fas fa-ticket-alt me-2"></i> Mã giảm giá
            </a>
        </li>
        
        <!-- Thêm mục Quản lý Tin tức & Bình luận -->
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'news.php' || $current_page == 'news_form.php') ? 'active' : ''; ?> text-white" href="news.php">
                <i class="fas fa-newspaper me-2"></i> Quản lý Tin tức
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'comments.php') ? 'active' : ''; ?> text-white" href="comments.php">
                <i class="fas fa-comments me-2"></i> Quản lý Bình luận
            </a>
        </li>

        <!-- Thêm mục Quản lý Tài khoản -->
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?> text-white" href="users.php">
                <i class="fas fa-users me-2"></i> Quản lý Tài khoản
            </a>
        </li>
        
        <!-- Ví dụ thêm vào sidebar -->
        <li class="nav-item">
            <a href="messages.php" class="nav-link text-white">
                <i class="fas fa-envelope me-2"></i> Tin nhắn
            </a>
        </li>

        <li class="nav-item mt-4 pt-4 border-top">
            <a class="nav-link text-danger" href="../logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Đăng xuất
            </a>
        </li>
        
    </ul>
</div>