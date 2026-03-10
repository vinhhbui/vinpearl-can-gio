<?php

// Authentication check for protected pages
if (!isset($_SESSION)) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    // Store the intended destination
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header('Location: /auth/login.php?error=login_required');
    exit();
}

// Optional: Check if session is expired (1 hour timeout)
$session_timeout = 3600; // 1 hour in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    // Session expired
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /auth/login.php?error=session_expired');
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Kiểm tra xem user đã đăng nhập chưa
if (!isset($_SESSION['user_id'])) {
    // Lưu URL hiện tại để redirect về sau khi đăng nhập
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Redirect về trang login với query parameter
    header("Location: /login.php?error=login_required");
    exit();
}

// Tùy chọn: Kiểm tra vai trò nếu cần
if (isset($required_role) && $_SESSION['role'] !== $required_role) {
    $_SESSION['error'] = 'Bạn không có quyền truy cập trang này.';
    header("Location: /index.php");
    exit();
}