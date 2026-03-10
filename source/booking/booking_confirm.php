<?php
// Luôn bắt đầu session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kết nối database
require '../includes/db_connect.php';

// Lấy trạng thái từ URL
$status = isset($_GET['status']) ? htmlspecialchars($_GET['status']) : '';
$booking_reference = isset($_GET['ref']) ? htmlspecialchars($_GET['ref']) : '';

// Kiểm tra xem có thông tin booking trong session không
$booking_details = null;
$success = false;

if ($status == 'success' && isset($_SESSION['booking_success'])) {
    $booking_details = $_SESSION['booking_success'];
    $success = true;
    
    // Xóa session sau khi hiển thị
    unset($_SESSION['booking_success']);
} elseif ($booking_reference) {
    // Nếu không có trong session, query từ database
    try {
        $stmt = $pdo->prepare("
            SELECT 
                b.*,
                r.room_type_name as room_name
            FROM bookings b
            JOIN rooms r ON b.room_id = r.id
            WHERE b.booking_reference = :ref
            LIMIT 1
        ");
        $stmt->execute([':ref' => $booking_reference]);
        $booking_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking_details) {
            $success = true;
            // Chuyển đổi tên cột để tương thích
            $booking_details['full_name'] = $booking_details['guest_name'];
            $booking_details['email'] = $booking_details['guest_email'];
            $booking_details['checkin'] = $booking_details['checkin_date'];
            $booking_details['checkout'] = $booking_details['checkout_date'];
            $booking_details['adults'] = $booking_details['num_adults'];
            $booking_details['children'] = $booking_details['num_children'];
        }
    } catch (PDOException $e) {
        error_log("Lỗi query booking: " . $e->getMessage());
    }
}

$page_title = $success ? 'Xác nhận Đặt phòng - Vinpearl Cần Giờ' : 'Đặt phòng Thất bại - Vinpearl Cần Giờ';

if ($success && $booking_details && !isset($booking_details['addons_summary'])) {
    // Nếu dữ liệu lấy từ DB (qua ref), ta cần query thêm bảng booking_addons
    try {
        $stmt_addons = $pdo->prepare("SELECT addon_name as name, addon_price as price, quantity, addon_type FROM booking_addons WHERE booking_id = ?");
        $stmt_addons->execute([$booking_details['id']]);
        $booking_details['addons_summary'] = $stmt_addons->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $booking_details['addons_summary'] = [];
    }
}



include '../includes/header.php';
?>

<!-- Booking Confirmation Section -->
<main id="booking-confirm" class="py-24 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="max-w-3xl mx-auto bg-white p-10 rounded-lg shadow-2xl text-center">
            
            <?php if ($success && $booking_details): ?>
                <!-- Success Message -->
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <h1 class="text-3xl md:text-4xl font-serif font-bold text-gray-900 mb-4">
                    Cảm ơn, <?php echo htmlspecialchars($booking_details['full_name']); ?>!
                </h1>
                <p class="text-lg text-gray-700 mb-6">Đặt phòng của bạn đã được xác nhận thành công.</p>
                
                <!-- Booking Details -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 mb-8 text-left space-y-4">
                    <div class="flex justify-between items-center border-b pb-4">
                        <span class="text-gray-600">Mã đặt phòng:</span>
                        <span class="font-semibold text-accent text-lg"><?php echo htmlspecialchars($booking_details['booking_reference']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Email xác nhận:</span>
                        <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($booking_details['email']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Trạng thái:</span>
                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-semibold">✅ Đã xác nhận</span>
                    </div>
                    <div class="border-t pt-4 mt-4 flex justify-between">
                        <span class="text-gray-600">Phòng:</span>
                        <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($booking_details['room_name']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Check-in:</span>
                        <span class="font-semibold text-gray-800"><?php echo date('d/m/Y', strtotime($booking_details['checkin'])); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Check-out:</span>
                        <span class="font-semibold text-gray-800"><?php echo date('d/m/Y', strtotime($booking_details['checkout'])); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Số đêm:</span>
                        <span class="font-semibold text-gray-800"><?php echo $booking_details['num_nights']; ?> đêm</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Khách:</span>
                        <span class="font-semibold text-gray-800">
                            <?php echo $booking_details['adults']; ?> Người lớn
                            <?php if ($booking_details['children'] > 0): ?>
                                , <?php echo $booking_details['children']; ?> Trẻ em
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="border-t pt-4 mt-4 flex justify-between">
                        <span class="text-xl font-bold text-gray-900">Tổng cộng:</span>
                        <span class="font-bold text-accent text-xl">
                            <?php echo number_format($booking_details['total_price_final'] ?? $booking_details['total_price'], 0, ',', '.'); ?> VNĐ
                        </span>
                    </div>
                </div>

                <!-- Email Confirmation Notice -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
                    <p class="text-blue-800"><strong>📧 Email xác nhận đã được gửi!</strong></p>
                    <p class="text-blue-700 text-sm mt-2">
                        Vui lòng kiểm tra email tại <?php echo htmlspecialchars($booking_details['email']); ?>
                    </p>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex flex-col md:flex-row justify-center gap-4">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="../portal/booking_detail.php" class="bg-accent text-white px-8 py-3 rounded-lg font-semibold hover:bg-accent-dark transition-all duration-300 shadow-lg">
                            Xem đơn đặt phòng của tôi
                        </a>
                    <?php endif; ?>
                    <a href="../index.php" class="bg-gray-200 text-gray-800 px-8 py-3 rounded-lg font-semibold hover:bg-gray-300 transition-all duration-300">
                        Trở về Trang chủ
                    </a>
                    <a href="../booking/rooms.php" class="bg-white border-2 border-accent text-accent px-8 py-3 rounded-lg font-semibold hover:bg-accent hover:text-white transition-all duration-300">
                        Đặt phòng mới
                    </a>
                </div>

            <?php else: ?>
                <!-- Error Message -->
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="text-3xl md:text-4xl font-serif font-bold text-gray-900 mb-4">Không tìm thấy thông tin đặt phòng</h1>
                <p class="text-lg text-gray-700 mb-8">
                    Đã có lỗi xảy ra hoặc thông tin đặt phòng không tồn tại.
                </p>
                <a href="../booking_step1.php" class="bg-accent text-white px-8 py-3 rounded-lg font-semibold hover:bg-accent-dark transition-all duration-300 shadow-lg">
                    Quay lại Đặt phòng
                </a>
            <?php endif; ?>

        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>