<?php

session_start();
require_once '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Lấy dữ liệu và làm sạch
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // 2. Kiểm tra dữ liệu bắt buộc
    if (empty($name) || empty($email) || empty($message)) {
        $_SESSION['error'] = 'Vui lòng điền đầy đủ Họ tên, Email và Nội dung tin nhắn.';
        header('Location: ../contact.php');
        exit;
    }

    // 3. Lưu vào cơ sở dữ liệu
    try {
        $sql = "INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$name, $email, $subject, $message])) {
            $_SESSION['success'] = 'Cảm ơn bạn! Tin nhắn đã được gửi thành công. Chúng tôi sẽ phản hồi sớm nhất.';
        } else {
            $_SESSION['error'] = 'Có lỗi xảy ra khi gửi tin nhắn. Vui lòng thử lại.';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Lỗi hệ thống: ' . $e->getMessage();
    }

    // 4. Quay lại trang liên hệ
    header('Location: ../contact.php');
    exit;
} else {
    // Nếu truy cập trực tiếp file này mà không post
    header('Location: ../contact.php');
    exit;
}