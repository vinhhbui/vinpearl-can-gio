<?php
// Nạp các namespace của PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Nạp autoloader của Composer
require 'vendor/autoload.php';

session_start();
require_once 'includes/db_connect.php';
require_once 'includes/email_config.php'; // Nạp file cấu hình email

$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email không hợp lệ.";
        $msg_type = 'error';
    } else {
        // Kiểm tra email có tồn tại không
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            // Tạo token ngẫu nhiên và thời gian hết hạn (1 giờ)
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Lưu token vào DB
            $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
            $update->bind_param("sss", $token, $expiry, $email);
            
            if ($update->execute()) {
                // Tạo link reset
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?email=" . urlencode($email) . "&token=" . $token;
                
                $subject = "Đặt lại mật khẩu - Vinpearl Cần Giờ";
                
                // Nội dung email HTML đẹp hơn một chút
                $content = "
                <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <h2>Yêu cầu đặt lại mật khẩu</h2>
                    <p>Xin chào,</p>
                    <p>Bạn vừa yêu cầu đặt lại mật khẩu cho tài khoản tại Vinpearl Cần Giờ.</p>
                    <p>Vui lòng nhấn vào nút bên dưới để tạo mật khẩu mới (liên kết hết hạn sau 1 giờ):</p>
                    <p>
                        <a href='{$reset_link}' style='background-color: #d4af37; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Đặt lại mật khẩu</a>
                    </p>
                    <hr>
                    <p style='font-size: 12px; color: #666;'>Nếu bạn không yêu cầu điều này, vui lòng bỏ qua email này.</p>
                </div>";

                // Khởi tạo PHPMailer
                $mail = new PHPMailer(true);

                try {
                    // Cấu hình Server (Lấy từ includes/email_config.php)
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USERNAME;
                    $mail->Password   = SMTP_PASSWORD;
                    $mail->SMTPSecure = SMTP_ENCRYPTION; // tls
                    $mail->Port       = SMTP_PORT;       // 587
                    $mail->CharSet    = 'UTF-8';         // Hỗ trợ tiếng Việt

                    // Người gửi và người nhận
                    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                    $mail->addAddress($email);

                    // Nội dung
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body    = $content;
                    $mail->AltBody = "Vui lòng truy cập link sau để reset mật khẩu: $reset_link"; // Cho trình duyệt không hỗ trợ HTML

                    $mail->send();
                    $message = "Vui lòng kiểm tra hộp thư (kể cả mục Spam).";
                    $msg_type = 'success';
                } catch (Exception $e) {
                    $message = "Không thể gửi email. Lỗi hệ thống: {$mail->ErrorInfo}";
                    $msg_type = 'error';
                }

            } else {
                $message = "Có lỗi xảy ra khi cập nhật dữ liệu, vui lòng thử lại.";
                $msg_type = 'error';
            }
        } else {
            $message = "Email này chưa được đăng ký trong hệ thống.";
            $msg_type = 'error';
        }
    }
}

$page_title = 'Quên mật khẩu - Vinpearl Cần Giờ';
include 'includes/header.php';
?>

<div class="py-16 bg-gray-50 min-h-screen flex items-center">
    <div class="container mx-auto px-4">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-2xl p-8">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-serif font-bold text-gray-900">Quên mật khẩu?</h2>
                <p class="text-gray-600 mt-2">Nhập email của bạn để nhận liên kết đặt lại mật khẩu.</p>
            </div>

            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $msg_type == 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email đăng ký</label>
                    <input type="email" id="email" name="email" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent"
                           placeholder="vidu@email.com">
                </div>

                <button type="submit" class="w-full bg-accent text-white py-3 rounded-lg hover:bg-accent-dark transition font-semibold">
                    Gửi liên kết xác nhận
                </button>
                
                <div class="text-center mt-4">
                    <a href="login.php" class="text-sm text-gray-600 hover:text-accent">Quay lại đăng nhập</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>