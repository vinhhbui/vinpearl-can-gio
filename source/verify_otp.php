<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();

try {
    require_once 'includes/db_connect.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request');
    }

    $user_otp = trim($_POST['otp_code'] ?? '');

    if (!isset($_SESSION['otp']) || !isset($_SESSION['temp_register_data'])) {
        throw new Exception('Phiên giao dịch hết hạn. Vui lòng đăng ký lại.');
    }

    if (time() > $_SESSION['otp_expiry']) {
        throw new Exception('Mã OTP đã hết hạn.');
    }

    if ($user_otp != $_SESSION['otp']) {
        throw new Exception('Mã OTP không chính xác.');
    }

    // OTP đúng -> Lưu vào DB
    $data = $_SESSION['temp_register_data'];
    // Password đã được hash trong send_otp.php, không hash lại
    $hashed_password = $data['password_hash'];
    $role = 'customer';

    // Kiểm tra lại kết nối DB
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("INSERT INTO users (username, email, phone, password_hash, role, ranking) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $ranking = 'Standard';
    $stmt->bind_param("ssssss", $data['username'], $data['email'], $data['phone'], $hashed_password, $role, $ranking);

    if ($stmt->execute()) {
        // Xóa session
        unset($_SESSION['otp']);
        unset($_SESSION['otp_expiry']);
        unset($_SESSION['temp_register_data']);
        
        echo json_encode(['status' => 'success', 'message' => 'Đăng ký thành công!']);
    } else {
        throw new Exception('Lỗi lưu dữ liệu: ' . $stmt->error);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>