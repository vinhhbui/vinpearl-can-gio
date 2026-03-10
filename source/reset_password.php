<?php

session_start();
require_once 'includes/db_connect.php'; // Đã sửa từ db.php thành db_connect.php

$message = '';
$msg_type = '';
$show_form = true;

// Lấy tham số từ URL
$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';

if (empty($email) || empty($token)) {
    $message = "Liên kết không hợp lệ.";
    $msg_type = 'error';
    $show_form = false;
} else {
    // Kiểm tra token và thời hạn
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $message = "Liên kết đã hết hạn hoặc không hợp lệ.";
        $msg_type = 'error';
        $show_form = false;
    }
}

// Xử lý đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $message = "Mật khẩu xác nhận không khớp.";
        $msg_type = 'error';
    } else {
        // Hash mật khẩu mới
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Updated column name from 'password' to 'password_hash' to match database schema
        $update = $conn->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE email = ?");
        $update->bind_param("ss", $hashed_password, $email);
        
        if ($update->execute()) {
            $message = "Đổi mật khẩu thành công! Bạn sẽ được chuyển về trang đăng nhập.";
            $msg_type = 'success';
            $show_form = false;
            header("refresh:3;url=login.php");
        } else {
            $message = "Lỗi hệ thống, vui lòng thử lại.";
            $msg_type = 'error';
        }
    }
}

$page_title = 'Đặt lại mật khẩu - Vinpearl Cần Giờ';
include 'includes/header.php';
?>

<div class="py-16 bg-gray-50 min-h-screen flex items-center">
    <div class="container mx-auto px-4">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-2xl p-8">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-serif font-bold text-gray-900">Đặt lại mật khẩu mới</h2>
            </div>

            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $msg_type == 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($show_form): ?>
            <form method="POST" class="space-y-6">
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Mật khẩu mới</label>
                    <input type="password" id="password" name="password" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Xác nhận mật khẩu</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent">
                </div>

                <button type="submit" class="w-full bg-accent text-white py-3 rounded-lg hover:bg-accent-dark transition font-semibold">
                    Cập nhật mật khẩu
                </button>
            </form>
            <?php endif; ?>
            
            <?php if (!$show_form && $msg_type == 'error'): ?>
            <div class="mt-6 text-center">
                <a href="forgot_password.php" class="text-accent font-semibold hover:underline">
                    Gửi lại yêu cầu
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>