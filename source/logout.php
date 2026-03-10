<?php
// File: logout.php

// Luôn bắt đầu session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Xóa tất cả các biến session
$_SESSION = array();

// Hủy session
session_destroy();

// Chuyển hướng về trang chủ
header("Location: index.php");
exit;
?>