<?php

$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar bg-dark text-white p-3">
    <h4 class="mb-4">Staff Panel</h4>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="index.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </li>
        <!-- <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" href="users.php">
                <i class="fas fa-users"></i> Quản lý người dùng
            </a>
        </li> -->
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'bookings.php') ? 'active' : ''; ?>" href="booking.php">
                <i class="fas fa-book"></i> Đơn đặt phòng
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'rooms.php') ? 'active' : ''; ?>" href="rooms.php">
                <i class="fas fa-bed"></i> Quản lý phòng
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'services.php') ? 'active' : ''; ?>" href="services.php">
                <i class="fas fa-concierge-bell"></i> Yêu cầu dịch vụ
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'amenity-bookings.php') ? 'active' : ''; ?>" href="amenity-bookings.php">
                <i class="fas fa-tools"></i> Yêu cầu tiện ích
            </a>
        </li>
        <li class="nav-item mt-4">
            <a class="nav-link text-danger" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Đăng xuất
            </a>
        </li>
    </ul>
</div>

<style>
.sidebar {
    min-height: 100vh;
    width: 250px;
}

.sidebar .nav-link {
    color: rgba(255, 255, 255, 0.75);
    padding: 10px 15px;
    border-radius: 5px;
    margin-bottom: 5px;
}

.sidebar .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
}

.sidebar .nav-link.active {
    background-color: #0d6efd;
    color: white;
}

.sidebar .nav-link i {
    margin-right: 10px;
    width: 20px;
}
</style>