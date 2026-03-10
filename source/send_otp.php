<?php
// 1. Bắt đầu buffer để kiểm soát output
ob_start();

// Các khai báo namespace nên đặt ở đầu file
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

session_start();
ini_set('display_errors', 0); // Tắt hiển thị lỗi ra màn hình
header('Content-Type: application/json; charset=utf-8');

$response = [];

try {
    // Kiểm tra method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Load thư viện và config
    if (!file_exists('vendor/autoload.php')) {
        throw new Exception('Thiếu thư viện PHPMailer.');
    }
    require_once 'vendor/autoload.php';
    
    // Lưu ý: Đảm bảo các file này không echo bất cứ gì ra màn hình
    require_once 'includes/db_connect.php'; 
    require_once 'includes/email_config.php';

    // Lấy và làm sạch dữ liệu đầu vào
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate cơ bản
    if (empty($username) || empty($email) || empty($password)) {
        throw new Exception('Vui lòng điền đầy đủ thông tin.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Định dạng email không hợp lệ.');
    }

    // Check Email tồn tại (Sử dụng Prepared Statement - Tốt)
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Email này đã được đăng ký.');
    }
    $stmt->close(); // Đóng statement sau khi dùng

    // --- BẢO MẬT: Hash password ngay lập tức ---
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // OTP Logic
    $otp = rand(100000, 999999);
    
    // Lưu thông tin vào Session (Lưu hash, KHÔNG lưu plain text)
    $temp_register_data = [
        'username' => $username,
        'email' => $email,
        'phone' => $phone,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT) // Hash 1 lần ở đây
    ];

    $_SESSION['temp_register_data'] = $temp_register_data;
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_expiry'] = time() + 300; // 5 phút

    // Mailer Logic
    $mail = new PHPMailer(true);
    // Cấu hình server mail
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = (SMTP_ENCRYPTION === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    // Người gửi & Người nhận
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress($email, $username);

    // Nội dung email
    $mail->isHTML(true);
    $mail->Subject = 'Mã xác thực đăng ký tài khoản - Vinpearl Cần Giờ';
    
    // Sử dụng heredoc syntax cho HTML gọn gàng hơn
    $mail->Body = <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 8px;">
            <h2 style="color: #333;">Xác thực tài khoản</h2>
            <p>Xin chào <strong>$username</strong>,</p>
            <p>Cảm ơn bạn đã đăng ký dịch vụ tại Vinpearl Cần Giờ. Đây là mã OTP của bạn:</p>
            <div style="background-color: #f9f9f9; padding: 15px; text-align: center; border-radius: 5px;">
                <h1 style="color: #d4af37; letter-spacing: 8px; margin: 0;">$otp</h1>
            </div>
            <p style="color: #666; font-size: 12px; margin-top: 20px;">Mã này có hiệu lực trong 5 phút. Vui lòng không chia sẻ mã này cho bất kỳ ai.</p>
        </div>
HTML;

    $mail->send();
    $response = [
        'status'  => 'success', 
        'message' => 'Mã OTP đã được gửi đến email ' . $email
    ];

} catch (Exception $e) {
    // Ghi log lỗi vào file của server để debug thay vì hiển thị chi tiết lỗi kỹ thuật cho user (tuỳ chọn)
    // error_log($e->getMessage()); 
    
    $response = [
        'status'  => 'error', 
        'message' => $e->getMessage()
    ];
}

// 2. Xóa sạch buffer trước khi in JSON
ob_clean();
echo json_encode($response);
exit;
?>