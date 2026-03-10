<?php
// 1. KHỞI ĐỘNG SESSION VÀ KIỂM TRA ĐĂNG NHẬP NGAY ĐẦU FILE
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: portal/index.php');
    exit;
}

// 2. SAU ĐÓ MỚI GỌI HEADER
$error_param = $_GET['error'] ?? null;

// Xử lý thông báo
$error_message = null;
$info_message = null;
$success_message = null;

// Ưu tiên lấy từ query parameter
if ($error_param === 'login_required') {
    $info_message = 'Vui lòng đăng nhập để tiếp tục.';
} elseif (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
} elseif (isset($_SESSION['info'])) {
    $info_message = $_SESSION['info'];
    unset($_SESSION['info']);
} elseif (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

$page_title = 'Đăng nhập - Vinpearl Cần Giờ';
include 'includes/header.php';
?>

<!-- Hero Section with Background -->
<div class="relative bg-gray-900 py-20">
    <div class="absolute inset-0 overflow-hidden">
        <img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1920&q=80" 
             alt="Hotel" class="w-full h-full object-cover opacity-30">
    </div>
    <div class="container mx-auto px-4 relative z-10">
        <h1 class="text-5xl font-serif font-bold text-white text-center mb-4">Đăng nhập</h1>
        <p class="text-xl text-gray-300 text-center">Chào mừng bạn trở lại với Vinpearl Cần Giờ</p>
    </div>
</div>

<!-- Login Form -->
<div class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="max-w-md mx-auto">
            
            <!-- Messages -->
            <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($info_message): ?>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                <i class="fas fa-info-circle mr-2"></i>
                <span><?php echo htmlspecialchars($info_message); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
            <?php endif; ?>

            <!-- Login Card -->
            <div class="bg-white rounded-lg shadow-2xl p-8">
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-accent rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-user text-white text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-serif font-bold text-gray-900">Đăng nhập tài khoản</h2>
                </div>

                <form method="POST" action="auth.php" class="space-y-6">
                    <input type="hidden" name="action" value="login">
                    
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                            Email hoặc Tên tài khoản
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input 
                                type="text" 
                                id="email" 
                                name="email" 
                                required 
                                class="pl-10 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent transition"
                                placeholder="Nhập email hoặc tên tài khoản"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                            Mật khẩu
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required 
                                class="pl-10 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent transition"
                                placeholder="Nhập mật khẩu"
                            >
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center">
                            <input type="checkbox" class="w-4 h-4 text-accent border-gray-300 rounded focus:ring-accent">
                            <span class="ml-2 text-sm text-gray-600">Ghi nhớ đăng nhập</span>
                        </label>
                        <a href="forgot_password.php" class="text-sm text-accent hover:text-accent-dark font-medium">
                            Quên mật khẩu?
                        </a>
                    </div>

                    <button 
                        type="submit" 
                        class="w-full bg-accent text-white py-3 rounded-lg hover:bg-accent-dark transition font-semibold shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                    >
                        <i class="fas fa-sign-in-alt mr-2"></i>Đăng nhập
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-gray-600">
                        Chưa có tài khoản? 
                        <a href="register.php" class="text-accent hover:text-accent-dark font-semibold">
                            Đăng ký ngay
                        </a>
                    </p>
                </div>

                <!-- Social Login (Optional) -->
                <!-- <div class="mt-6 pt-6 border-t border-gray-200">
                    <p class="text-center text-sm text-gray-600 mb-4">Hoặc đăng nhập với</p>
                    <div class="grid grid-cols-2 gap-3">
                        <button class="flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            <i class="fab fa-google text-red-500 mr-2"></i>
                            <span class="text-sm font-medium text-gray-700">Google</span>
                        </button>
                        <button class="flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            <i class="fab fa-facebook text-blue-600 mr-2"></i>
                            <span class="text-sm font-medium text-gray-700">Facebook</span>
                        </button>
                    </div>
                </div> -->
            </div>

            <!-- Security Notice -->
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-shield-alt text-blue-600 text-xl mr-3"></i>
                    <div>
                        <h4 class="font-semibold text-blue-900 text-sm mb-1">Bảo mật & An toàn</h4>
                        <p class="text-xs text-blue-800">
                            Thông tin của bạn được mã hóa và bảo vệ theo tiêu chuẩn quốc tế.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>