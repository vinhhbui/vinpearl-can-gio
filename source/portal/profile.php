<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Xử lý cập nhật thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    try {
        // Validation tên người dùng
        if (empty($username)) {
            throw new Exception("Tên hiển thị không được để trống!");
        }

        if (strlen($username) < 3) {
            throw new Exception("Tên hiển thị phải có ít nhất 3 ký tự!");
        }

        if (strlen($username) > 50) {
            throw new Exception("Tên hiển thị không được vượt quá 50 ký tự!");
        }

        // Validation họ tên đầy đủ
        if (!empty($full_name)) {
            if (strlen($full_name) < 3) {
                throw new Exception("Họ tên đầy đủ phải có ít nhất 3 ký tự!");
            }

            if (strlen($full_name) > 150) {
                throw new Exception("Họ tên đầy đủ không được vượt quá 150 ký tự!");
            }

            if (!preg_match('/^[\p{L}\s]+$/u', $full_name)) {
                throw new Exception("Họ tên chỉ được chứa chữ cái và khoảng trắng!");
            }
        }

        // Kiểm tra xem tên có chứa ký tự đặc biệt không (tùy chọn)
        if (!preg_match('/^[\p{L}\p{N}\s]+$/u', $username)) {
            throw new Exception("Tên hiển thị chỉ được chứa chữ cái, số và khoảng trắng!");
        }

        // Lấy thông tin hiện tại
        $stmt = $pdo->prepare("SELECT password_hash, username, full_name, phone, address FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();

        $changes_made = [];

        // Kiểm tra xem có thay đổi tên không
        if ($username !== $user_data['username']) {
            $changes_made[] = 'tên hiển thị';
        }

        // Kiểm tra xem có thay đổi họ tên không
        if ($full_name !== ($user_data['full_name'] ?? '')) {
            $changes_made[] = 'họ tên đầy đủ';
        }

        // Kiểm tra thay đổi số điện thoại
        if ($phone !== ($user_data['phone'] ?? '')) {
            $changes_made[] = 'số điện thoại';
        }

        // Kiểm tra thay đổi địa chỉ
        if ($address !== ($user_data['address'] ?? '')) {
            $changes_made[] = 'địa chỉ';
        }

        // Cập nhật thông tin cơ bản
        $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute([$username, $full_name, $phone, $address, $user_id]);

        // Nếu muốn đổi mật khẩu
        if (!empty($current_password) && !empty($new_password)) {
            if (!password_verify($current_password, $user_data['password_hash'])) {
                throw new Exception("Mật khẩu hiện tại không đúng!");
            }

            if ($new_password !== $confirm_password) {
                throw new Exception("Mật khẩu mới không khớp!");
            }

            if (strlen($new_password) < 6) {
                throw new Exception("Mật khẩu mới phải có ít nhất 6 ký tự!");
            }

            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$new_password_hash, $user_id]);
            $changes_made[] = 'mật khẩu';
        }

        // Tạo thông báo thành công chi tiết
        if (!empty($changes_made)) {
            $success_message = "Đã cập nhật " . implode(', ', $changes_made) . " thành công!";
        } else {
            $success_message = "Cập nhật thông tin thành công!";
        }

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Lấy thông tin người dùng
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Lấy thống kê
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings WHERE (user_id = ? OR guest_email = ?)");
$stmt->execute([$user_id, $user['email']]);
$total_bookings = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(total_price) as total_spent FROM bookings WHERE (user_id = ? OR guest_email = ?) AND (booking_status = 'checked_out' OR  booking_status = 'checked_in' OR booking_status = 'confirmed')");
$stmt->execute([$user_id, $user['email']]);
$total_spent = $stmt->fetchColumn() ?? 0;

// --- THÊM MỚI: Tự động cập nhật hạng thành viên dựa trên tổng chi tiêu ---
$new_ranking = 'Silver'; // Mặc định
$ranking_color = 'text-gray-600'; // Màu cho badge
$ranking_bg = 'bg-gray-100'; // Background cho badge
$ranking_icon = 'fa-medal'; // Icon mặc định

if ($total_spent >= 100000000) { // 100 triệu trở lên
    $new_ranking = 'Diamond';
    $ranking_color = 'text-blue-600';
    $ranking_bg = 'bg-blue-100';
    $ranking_icon = 'fa-gem';
} elseif ($total_spent >= 50000000) { // 50 triệu đến 100 triệu
    $new_ranking = 'Gold';
    $ranking_color = 'text-yellow-600';
    $ranking_bg = 'bg-yellow-100';
    $ranking_icon = 'fa-crown';
}

// Cập nhật ranking vào database nếu khác với ranking hiện tại
if ($user['ranking'] !== $new_ranking) {
    $stmt = $pdo->prepare("UPDATE users SET ranking = ? WHERE id = ?");
    $stmt->execute([$new_ranking, $user_id]);
    $user['ranking'] = $new_ranking; // Cập nhật biến local
}
// --- KẾT THÚC THÊM MỚI ---

$page_title = 'Hồ sơ cá nhân - Cổng khách hàng';
include '../includes/header.php';
?>

<!-- Breadcrumb -->
<div class="bg-gray-900 py-10">
    <div class="container mx-auto px-4">
        <nav class="text-sm">
            <ol class="list-none p-0 inline-flex items-center text-gray-300">
                <li class="flex items-center">
                    <a href="../index.php" class="hover:text-white transition">Trang chủ</a>
                    <svg class="fill-current w-3 h-3 mx-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
                </li>
                <li class="flex items-center">
                    <a href="index.php" class="hover:text-white transition">Cổng khách hàng</a>
                    <svg class="fill-current w-3 h-3 mx-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
                </li>
                <li>
                    <span class="text-white font-bold">Hồ sơ cá nhân</span>
                </li>
            </ol>
        </nav>
    </div>
</div>

<div class="min-h-screen bg-gray-50 py-12">
    <div class="container mx-auto px-4">
        <div class="max-w-5xl mx-auto">
            <!-- Header -->
            <div class="bg-gradient-to-r from-accent to-accent-dark text-white rounded-lg p-8 mb-8 shadow-xl">
                <div class="flex items-center gap-6">
                    <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center text-accent text-4xl font-bold">
                        <?php echo strtoupper(substr($user['full_name'] ?: $user['username'], 0, 1)); ?>
                    </div>
                    <div class="flex-1">
                        <h1 class="text-3xl font-serif font-bold mb-2"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h1>
                        <div class="flex items-center gap-3">
                            <p class="text-lg opacity-90">Hạng:</p>
                            <span class="inline-flex items-center gap-2 bg-white <?php echo $ranking_color; ?> px-4 py-1 rounded-full font-bold text-lg">
                                <i class="fas <?php echo $ranking_icon; ?>"></i>
                                <?php echo $user['ranking']; ?>
                            </span>
                        </div>
                        <p class="text-sm opacity-75 mt-1">Thành viên từ <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>

                <!-- Thanh tiến độ đến hạng tiếp theo -->
                <?php if ($new_ranking !== 'Diamond'): ?>
                <div class="mt-6 bg-white bg-opacity-20 rounded-lg p-4">
                    <?php
                    $next_milestone = ($new_ranking === 'Silver') ? 50000000 : 100000000;
                    $progress_percent = min(100, ($total_spent / $next_milestone) * 100);
                    $remaining = $next_milestone - $total_spent;
                    $next_rank = ($new_ranking === 'Silver') ? 'Gold' : 'Diamond';
                    ?>
                    <div class="flex justify-between items-center mb-2">
                        <p class="text-sm font-semibold">Tiến độ đến hạng <?php echo $next_rank; ?></p>
                        <p class="text-sm">Còn <?php echo number_format($remaining, 0, ',', '.'); ?>đ</p>
                    </div>
                    <div class="w-full bg-white bg-opacity-30 rounded-full h-3">
                        <div class="bg-white h-3 rounded-full transition-all duration-500" style="width: <?php echo $progress_percent; ?>%"></div>
                    </div>
                    <p class="text-xs mt-2 opacity-75">
                        <?php echo number_format($total_spent, 0, ',', '.'); ?>đ / <?php echo number_format($next_milestone, 0, ',', '.'); ?>đ
                    </p>
                </div>
                <?php else: ?>
                <div class="mt-6 bg-white bg-opacity-20 rounded-lg p-4 text-center">
                    <i class="fas fa-trophy text-3xl mb-2"></i>
                    <p class="font-bold">Chúc mừng! Bạn đã đạt hạng cao nhất - Diamond</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Tổng đặt phòng</p>
                            <p class="text-3xl font-bold text-gray-900"><?php echo $total_bookings; ?></p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-calendar-check text-blue-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Tổng chi tiêu</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_spent, 0, ',', '.'); ?> đ</p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-dollar-sign text-green-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Hạng thành viên</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="inline-flex items-center gap-2 <?php echo $ranking_bg; ?> <?php echo $ranking_color; ?> px-3 py-1 rounded-full font-bold text-xl">
                                    <i class="fas <?php echo $ranking_icon; ?>"></i>
                                    <?php echo $user['ranking']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="<?php echo $ranking_bg; ?> p-3 rounded-full">
                            <i class="fas <?php echo $ranking_icon; ?> <?php echo $ranking_color; ?> text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bảng thông tin chi tiết về hạng -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-info-circle text-accent"></i>
                    Thông tin về hạng thành viên
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="border-l-4 border-gray-400 pl-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="fas fa-medal text-gray-600 text-xl"></i>
                            <h4 class="font-bold text-gray-900">Silver</h4>
                        </div>
                        <p class="text-sm text-gray-600">Hạng mặc định</p>
                        <p class="text-xs text-gray-500 mt-1">Dưới 50 triệu đồng</p>
                    </div>

                    <div class="border-l-4 border-yellow-400 pl-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="fas fa-crown text-yellow-600 text-xl"></i>
                            <h4 class="font-bold text-yellow-600">Gold</h4>
                        </div>
                        <p class="text-sm text-gray-600">Ưu đãi đặc biệt</p>
                        <p class="text-xs text-gray-500 mt-1">Từ 50 - 100 triệu đồng</p>
                    </div>

                    <div class="border-l-4 border-blue-400 pl-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="fas fa-gem text-blue-600 text-xl"></i>
                            <h4 class="font-bold text-blue-600">Diamond</h4>
                        </div>
                        <p class="text-sm text-gray-600">Ưu đãi cao cấp</p>
                        <p class="text-xs text-gray-500 mt-1">Từ 100 triệu đồng trở lên</p>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="bg-green-50 border border-green-200 text-green-800 px-6 py-4 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-check-circle mr-3 text-xl"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-exclamation-circle mr-3 text-xl"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <!-- Profile Form -->
            <form method="POST" class="bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-2xl font-serif font-bold text-gray-900 mb-6">Thông tin cá nhân</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">
                            Họ tên đầy đủ <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="full_name" 
                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent" 
                               minlength="3"
                               maxlength="150"
                               placeholder="Nguyễn Văn A">
                        <p class="text-xs text-gray-500 mt-1">Họ tên đầy đủ của bạn (3-150 ký tự)</p>
                    </div>

                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">
                            Tên hiển thị <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="username" 
                               value="<?php echo htmlspecialchars($user['username']); ?>" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent" 
                               required
                               minlength="3"
                               maxlength="50"
                               placeholder="Nhập tên hiển thị của bạn">
                        <p class="text-xs text-gray-500 mt-1">Tên này sẽ hiển thị công khai (3-50 ký tự)</p>
                    </div>

                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-gray-100 cursor-not-allowed" disabled>
                        <p class="text-xs text-gray-500 mt-1">Email không thể thay đổi</p>
                    </div>

                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Số điện thoại</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent" 
                               placeholder="0912345678"
                               pattern="[0-9]{10,11}">
                        <p class="text-xs text-gray-500 mt-1">Nhập số điện thoại 10-11 chữ số</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-gray-700 font-semibold mb-2">
                            Địa chỉ <span class="text-gray-400 text-sm font-normal">(Tùy chọn)</span>
                        </label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent" 
                               placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố"
                               maxlength="200">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Địa chỉ liên hệ để nhận thông tin và ưu đãi (không bắt buộc)
                        </p>
                    </div>
                </div>

                <hr class="my-8">

                <h2 class="text-2xl font-serif font-bold text-gray-900 mb-6">Đổi mật khẩu</h2>
                <p class="text-gray-600 text-sm mb-4">Để bảo mật tài khoản, bạn có thể thay đổi mật khẩu bất kỳ lúc nào</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Mật khẩu hiện tại</label>
                        <input type="password" name="current_password" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent" 
                               placeholder="Nhập mật khẩu hiện tại">
                    </div>

                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Mật khẩu mới</label>
                        <input type="password" name="new_password" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent" 
                               placeholder="Mật khẩu mới (tối thiểu 6 ký tự)">
                    </div>

                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Xác nhận mật khẩu mới</label>
                        <input type="password" name="confirm_password" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent" 
                               placeholder="Nhập lại mật khẩu mới">
                    </div>
                </div>

                <div class="flex gap-4 pt-6">
                    <button type="submit" class="bg-accent text-white px-8 py-3 rounded-lg font-semibold hover:bg-accent-dark transition flex items-center gap-2">
                        <i class="fas fa-save"></i>
                        Lưu thay đổi
                    </button>
                    <a href="index.php" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-lg font-semibold hover:bg-gray-300 transition">
                        Hủy
                    </a>
                </div>
            </form>

            <!-- Delete Account Section -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 mt-8">
                <h3 class="text-xl font-bold text-red-800 mb-2">Vùng nguy hiểm</h3>
                <p class="text-red-700 text-sm mb-4">Xóa tài khoản sẽ không thể khôi phục. Vui lòng liên hệ hỗ trợ nếu bạn muốn xóa tài khoản.</p>
                <a href="../contact.php" class="inline-block bg-red-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-red-700 transition">
                    Yêu cầu xóa tài khoản
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>