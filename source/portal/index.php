<?php

// I.3.1. Bảng điều khiển - Customer Portal Dashboard
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

// Get user info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, email, phone, ranking, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get upcoming bookings
$stmt = $pdo->prepare("SELECT b.*, r.room_type_name, r.image_url 
                        FROM bookings b 
                        JOIN rooms r ON b.room_id = r.id 
                        WHERE b.user_id = ? AND b.checkin_date >= CURDATE() AND b.booking_status != 'cancelled' AND b.booking_status != 'checked_out'
                        ORDER BY b.checkin_date ASC LIMIT 3");
$stmt->execute([$user_id]);
$upcoming_bookings = $stmt->fetchAll();

// Get total bookings count
// $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings WHERE user_id = ?");
// $stmt->execute([$user_id]);
// $total_bookings = $stmt->fetch()['total'];
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN booking_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN booking_status = 'checked_in' THEN 1 ELSE 0 END) as checked_in,
    SUM(CASE WHEN booking_status = 'checked_out' THEN 1 ELSE 0 END) as checked_out,
    SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(total_price) as total_spent
    FROM bookings WHERE guest_email = ?";
$stmt = $pdo->prepare($stats_sql);
$stmt->execute([$user['email']]);
$stats = $stmt->fetch();


// --- BẮT ĐẦU ĐOẠN CODE THÊM MỚI ---
// Xử lý logic tra cứu đặt phòng
$lookup_result = null;
$lookup_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_code'])) {
    $code = trim($_POST['booking_code']);

    if (!empty($code)) {
        // Tìm kiếm booking theo mã và phải thuộc về user đang đăng nhập
        $stmt = $pdo->prepare("
            SELECT b.*, r.room_type_name, r.image_url, 
                   b.booking_reference as booking_code,
                   (b.num_adults + b.num_children) as guests
            FROM bookings b 
            JOIN rooms r ON b.room_id = r.id 
            WHERE b.booking_reference = ? AND b.guest_email = ?
        ");
        // Sửa $user_id thành $user['email'] để khớp với điều kiện guest_email
        $stmt->execute([$code, $user['email']]);
        $result = $stmt->fetch();

        if ($result) {
            $lookup_result = $result;
        } else {
            $lookup_error = "Không tìm thấy đơn đặt phòng với mã này hoặc đơn không thuộc về bạn.";
        }
    } else {
        $lookup_error = "Vui lòng nhập mã đặt phòng.";
    }
}
// --- KẾT THÚC ĐOẠN CODE THÊM MỚI ---



$page_title = 'Bảng điều khiển - Cổng Thông tin Khách hàng';
include '../includes/header.php';
?>

<!-- Breadcrumb -->
<div class="bg-gray-900 py-10">
    <div class="container mx-auto px-4">
        <nav class="text-sm">
            <ol class="list-none p-0 inline-flex items-center text-gray-300">
                <li class="flex items-center">
                    <a href="../index.php" class="hover:text-white transition">Trang chủ</a>
                    <svg class="fill-current w-3 h-3 mx-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
                        <path
                            d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z" />
                    </svg>
                </li>
                <li class="flex items-center">
                    <span class="text-gray-400">Cổng khách hàng</span>
                    <svg class="fill-current w-3 h-3 mx-3 text-gray-500" xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 320 512">
                        <path
                            d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z" />
                    </svg>
                </li>
                <li>
                    <span class="text-white font-bold"><?php echo htmlspecialchars($user['username']); ?></span>
                </li>
            </ol>
        </nav>
    </div>
</div>

<div class="min-h-screen bg-gray-50 py-12">
    <div class="container mx-auto px-4">
        <!-- Welcome Section -->
        <div class="bg-gradient-to-r from-accent to-accent-dark text-white rounded-lg p-8 mb-8 shadow-xl">
            <h1 class="text-4xl font-serif font-bold mb-2">Chào mừng trở lại,
                <?php echo htmlspecialchars($user['username']); ?>!</h1>
            <p class="text-lg opacity-90">Hạng thành viên: <span
                    class="font-bold"><?php echo $user['ranking']; ?></span></p>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Tổng đặt phòng</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total']; ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Sắp tới</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo count($upcoming_bookings); ?></p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Hạng</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $user['ranking']; ?></p>
                    </div>
                    <div class="bg-yellow-100 p-3 rounded-full">
                        <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Thành viên từ</p>
                        <p class="text-lg font-bold text-gray-900">
                            <?php echo date('Y', strtotime($user['created_at'])); ?></p>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-full">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column - Quick Actions -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Booking Lookup by Code -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-2xl font-serif font-bold text-gray-900 mb-4">Tra cứu đặt phòng</h2>
                    <form method="POST" class="flex flex-col sm:flex-row gap-3">
                        <input type="text" name="booking_code" placeholder="Nhập mã đặt phòng (ví dụ: VPCxxxxxxxxx)"
                            value="<?php echo isset($_POST['booking_code']) ? htmlspecialchars($_POST['booking_code']) : ''; ?>"
                            class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-accent"
                            required />
                        <button type="submit"
                            class="bg-accent text-white px-6 py-2 rounded-lg hover:bg-accent-dark transition">
                            Tra cứu
                        </button>
                    </form>

                    <?php if ($lookup_error): ?>
                        <p class="mt-3 text-sm text-red-600"><?php echo htmlspecialchars($lookup_error); ?></p>
                    <?php endif; ?>

                    <?php if ($lookup_result): ?>
                        <?php
                        // Xử lý ảnh lookup
                        $lookupImg = $lookup_result['image_url'] ?? '';
                        if ($lookupImg && !preg_match("~^(?:f|ht)tps?://~i", $lookupImg)) {
                            $lookupImg = '../' . $lookupImg;
                        }
                        if (empty($lookupImg))
                            $lookupImg = 'https://placehold.co/100x100?text=Room';
                        ?>
                        <div class="mt-6 border border-gray-200 rounded-lg p-4 flex items-center space-x-4">
                            <img src="<?php echo htmlspecialchars($lookupImg); ?>" alt="Room"
                                class="w-24 h-24 object-cover rounded-lg">
                            <div class="flex-1">
                                <h3 class="font-semibold text-lg text-gray-900">
                                    <?php echo htmlspecialchars($lookup_result['room_type_name']); ?>
                                </h3>
                                <p class="text-gray-600 text-sm">
                                    Mã đặt phòng: <span
                                        class="font-mono font-semibold"><?php echo htmlspecialchars($lookup_result['booking_code']); ?></span>
                                </p>
                                <p class="text-gray-600 text-sm">
                                    <?php echo date('d/m/Y', strtotime($lookup_result['checkin_date'])); ?> -
                                    <?php echo date('d/m/Y', strtotime($lookup_result['checkout_date'])); ?>
                                </p>
                                <p class="text-gray-600 text-sm">
                                    Số khách: <?php echo (int) $lookup_result['guests'] ?? 1; ?>
                                </p>
                                <p class="text-accent font-semibold">
                                    <?php echo number_format($lookup_result['total_price'], 0, ',', '.'); ?> VNĐ
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                                    <?php echo htmlspecialchars($lookup_result['booking_status'] ?? $lookup_result['status'] ?? 'unknown'); ?>
                                </span>
                                <div class="mt-3">
                                    <a href="booking_detail.php?id=<?php echo (int) $lookup_result['id']; ?>"
                                        class="text-accent font-semibold hover:text-accent-dark transition">
                                        Xem chi tiết &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Bookings -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-2xl font-serif font-bold text-gray-900 mb-4">Đặt phòng sắp tới</h2>
                    <?php if (empty($upcoming_bookings)): ?>
                        <p class="text-gray-500">Bạn chưa có đặt phòng nào sắp tới.</p>
                        <a href="../booking/rooms.php"
                            class="inline-block mt-4 bg-accent text-white px-6 py-2 rounded-lg hover:bg-accent-dark transition">Đặt
                            phòng ngay</a>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($upcoming_bookings as $booking): ?>
                                <?php
                                // Xử lý ảnh upcoming
                                $upImg = $booking['image_url'] ?? '';
                                if ($upImg && !preg_match("~^(?:f|ht)tps?://~i", $upImg)) {
                                    $upImg = '../' . $upImg;
                                }
                                if (empty($upImg))
                                    $upImg = 'https://placehold.co/100x100?text=Room';
                                ?>
                                <div
                                    class="border border-gray-200 rounded-lg p-4 flex items-center space-x-4 hover:shadow-md transition">
                                    <img src="<?php echo htmlspecialchars($upImg); ?>" alt="Room"
                                        class="w-24 h-24 object-cover rounded-lg">
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-lg text-gray-900">
                                            <?php echo htmlspecialchars($booking['room_type_name']); ?></h3>
                                        <p class="text-gray-600 text-sm">
                                            <?php echo date('d/m/Y', strtotime($booking['checkin_date'])); ?> -
                                            <?php echo date('d/m/Y', strtotime($booking['checkout_date'])); ?>
                                        </p>
                                        <p class="text-accent font-semibold">
                                            <?php echo number_format($booking['total_price'], 0, ',', '.'); ?> VNĐ</p>
                                    </div>
                                    <div class="flex flex-col items-end gap-2">
                                        <span
                                            class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm"><?php echo $booking['booking_status']; ?></span>
                                        <a href="booking_detail.php?id=<?php echo (int) $booking['id']; ?>"
                                            class="text-accent font-semibold hover:text-accent-dark transition text-sm">
                                            Xem chi tiết &rarr;
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="bookings.php"
                            class="inline-block mt-4 text-accent font-semibold hover:text-accent-dark transition">Xem tất cả
                            &rarr;</a>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-2xl font-serif font-bold text-gray-900 mb-4">Hành động nhanh</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="online_checkin.php"
                            class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-lg hover:border-accent hover:bg-accent hover:bg-opacity-5 transition">
                            <svg class="w-10 h-10 text-accent mb-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm font-semibold text-center">Check-in</span>
                        </a>
                        <a href="room_service.php"
                            class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-lg hover:border-accent hover:bg-accent hover:bg-opacity-5 transition">
                            <svg class="w-10 h-10 text-accent mb-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span class="text-sm font-semibold text-center">Dịch vụ Phòng</span>
                        </a>
                        <a href="book_amenity.php"
                            class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-lg hover:border-accent hover:bg-accent hover:bg-opacity-5 transition">
                            <svg class="w-10 h-10 text-accent mb-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-sm font-semibold text-center">Đặt Tiện ích</span>
                        </a>
                        <a href="promotions.php"
                            class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-lg hover:border-accent hover:bg-accent hover:bg-opacity-5 transition">
                            <svg class="w-10 h-10 text-accent mb-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24"></svg>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                            </svg>
                            <span class="text-sm font-semibold text-center">Ưu đãi</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column - Sidebar -->
            <div class="space-y-8">
                <!-- Profile Summary -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-serif font-bold text-gray-900 mb-4">Hồ sơ của bạn</h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-500">Email</p>
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Điện thoại</p>
                            <p class="font-semibold text-gray-900">
                                <?php echo htmlspecialchars($user['phone'] ?? 'Chưa cập nhật'); ?></p>
                        </div>
                        <a href="profile.php"
                            class="inline-block mt-4 text-accent font-semibold hover:text-accent-dark transition">Chỉnh
                            sửa hồ sơ &rarr;</a>
                    </div>
                </div>

                <!-- Member Benefits -->
                <div
                    class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-lg shadow-lg p-6 border-2 border-yellow-200">
                    <h3 class="text-xl font-serif font-bold text-gray-900 mb-4">Đặc quyền
                        <?php echo $user['ranking']; ?></h3>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0" fill="currentColor"
                                viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span>Check-in ưu tiên</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0" fill="currentColor"
                                viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span>Nâng cấp phòng miễn phí (tùy tình trạng)</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0" fill="currentColor"
                                viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span>Giảm 10% cho dịch vụ Spa</span>
                        </li>
                        <li class="flex items-start"></li>
                        <svg class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <span>Điểm thưởng tích lũy</span>
                    </li>
                </div> <a href="../contact.php"
                    class="inline-block w-full text-center bg-gray-800 text-white px-6 py-3 rounded-lg hover:bg-gray-900 transition">Liên
                    hệ hỗ trợ</a>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>