<?php

session_start();
require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Lấy các booking đang active (đang lưu trú)
$stmt = $pdo->prepare("
    SELECT b.*, r.room_type_name, rn.room_number
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    LEFT JOIN room_numbers rn ON b.room_number_id = rn.id
    WHERE (b.user_id = ? OR b.guest_email = ?) 
    AND b.checkin_date <= CURDATE() 
    AND b.checkout_date >= CURDATE()
    AND b.booking_status = 'checked_in'
    ORDER BY b.checkin_date DESC
");
$stmt->execute([$user_id, $user['email']]);
$active_bookings = $stmt->fetchAll();

// Xử lý yêu cầu dịch vụ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $booking_id = intval($_POST['booking_id']);
    $service_type = trim($_POST['service_type']);
    $priority = trim($_POST['priority']);
    $description = trim($_POST['description']);
    $preferred_time = !empty($_POST['preferred_time']) ? $_POST['preferred_time'] : null;

    try {
        // Kiểm tra booking có thuộc về user không
        $stmt = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND (user_id = ? OR guest_email = ?)");
        $stmt->execute([$booking_id, $user_id, $user['email']]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Booking không hợp lệ!");
        }

        // Thêm yêu cầu vào database
        $stmt = $pdo->prepare("
            INSERT INTO room_service_requests 
            (booking_id, service_type, priority, description, preferred_time, request_status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$booking_id, $service_type, $priority, $description, $preferred_time]);

        $success_message = "Yêu cầu dịch vụ đã được gửi thành công! Chúng tôi sẽ xử lý trong thời gian sớm nhất.";
        
        // Reset POST data
        $_POST = array();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Lấy lịch sử yêu cầu
$stmt = $pdo->prepare("
    SELECT rsr.*, b.booking_reference, r.room_type_name, rn.room_number
    FROM room_service_requests rsr
    JOIN bookings b ON rsr.booking_id = b.id
    JOIN rooms r ON b.room_id = r.id
    LEFT JOIN room_numbers rn ON b.room_number_id = rn.id
    WHERE (b.user_id = ? OR b.guest_email = ?)
    ORDER BY rsr.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id, $user['email']]);
$service_history = $stmt->fetchAll();

$page_title = 'Dịch vụ phòng - Cổng khách hàng';
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
                    <span class="text-white font-bold">Dịch vụ phòng</span>
                </li>
            </ol>
        </nav>
    </div>
</div>

<div class="min-h-screen bg-gray-50 py-12">
    <div class="container mx-auto px-4">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="bg-gradient-to-r from-accent to-accent-dark text-white rounded-lg p-8 mb-8 shadow-xl">
                <div class="flex items-center gap-4">
                    <div class="bg-white bg-opacity-20 p-4 rounded-full">
                        <i class="fas fa-concierge-bell text-4xl"></i>
                    </div>
                    <div>
                        <h1 class="text-4xl font-serif font-bold mb-2">Dịch vụ phòng</h1>
                        <p class="text-lg opacity-90">Yêu cầu dịch vụ 24/7 - Chúng tôi luôn sẵn sàng phục vụ bạn</p>
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

            <?php if (empty($active_bookings)): ?>
                <!-- No Active Bookings -->
                <div class="bg-white rounded-lg shadow-lg p-12 text-center">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-bed text-gray-400 text-4xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Không có phòng đang lưu trú</h2>
                    <p class="text-gray-600 mb-6">Bạn cần có đặt phòng đang hoạt động để sử dụng dịch vụ này.</p>
                    <a href="../booking/rooms.php" class="inline-block bg-accent text-white px-8 py-3 rounded-lg hover:bg-accent-dark transition font-semibold">
                        Đặt phòng ngay
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left: Request Form -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-lg shadow-lg p-8">
                            <h2 class="text-2xl font-serif font-bold text-gray-900 mb-6">Yêu cầu dịch vụ mới</h2>

                            <form method="POST" class="space-y-6">
                                <!-- Chọn phòng -->
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">
                                        Chọn phòng <span class="text-red-500">*</span>
                                    </label>
                                    <select name="booking_id" required class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent">
                                        <option value="">-- Chọn phòng đang lưu trú --</option>
                                        <?php foreach ($active_bookings as $booking): ?>
                                            <option value="<?php echo $booking['id']; ?>">
                                                <?php echo htmlspecialchars($booking['room_type_name']); ?>
                                                <?php if ($booking['room_number']): ?>
                                                    - Phòng <?php echo htmlspecialchars($booking['room_number']); ?>
                                                <?php endif; ?>
                                                (<?php echo $booking['booking_reference']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Loại dịch vụ -->
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">
                                        Loại dịch vụ <span class="text-red-500">*</span>
                                    </label>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        <label class="relative flex flex-col items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-accent transition">
                                            <input type="radio" name="service_type" value="housekeeping" required class="sr-only peer">
                                            <i class="fas fa-broom text-3xl text-gray-400 mb-2 peer-checked:text-accent"></i>
                                            <span class="text-sm font-semibold text-gray-700 peer-checked:text-accent">Dọn phòng</span>
                                            <div class="absolute inset-0 border-2 border-accent rounded-lg opacity-0 peer-checked:opacity-100"></div>
                                        </label>

                                        <label class="relative flex flex-col items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-accent transition">
                                            <input type="radio" name="service_type" value="food_delivery" required class="sr-only peer">
                                            <i class="fas fa-utensils text-3xl text-gray-400 mb-2 peer-checked:text-accent"></i>
                                            <span class="text-sm font-semibold text-gray-700 peer-checked:text-accent">Đặt đồ ăn</span>
                                            <div class="absolute inset-0 border-2 border-accent rounded-lg opacity-0 peer-checked:opacity-100"></div>
                                        </label>

                                        <label class="relative flex flex-col items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-accent transition">
                                            <input type="radio" name="service_type" value="beverages" required class="sr-only peer">
                                            <i class="fas fa-glass-water text-3xl text-gray-400 mb-2 peer-checked:text-accent"></i>
                                            <span class="text-sm font-semibold text-gray-700 peer-checked:text-accent">Đặt nước</span>
                                            <div class="absolute inset-0 border-2 border-accent rounded-lg opacity-0 peer-checked:opacity-100"></div>
                                        </label>

                                        <label class="relative flex flex-col items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-accent transition">
                                            <input type="radio" name="service_type" value="maintenance" required class="sr-only peer">
                                            <i class="fas fa-tools text-3xl text-gray-400 mb-2 peer-checked:text-accent"></i>
                                            <span class="text-sm font-semibold text-gray-700 peer-checked:text-accent">Sửa chữa</span>
                                            <div class="absolute inset-0 border-2 border-accent rounded-lg opacity-0 peer-checked:opacity-100"></div>
                                        </label>

                                        <label class="relative flex flex-col items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-accent transition">
                                            <input type="radio" name="service_type" value="laundry" required class="sr-only peer">
                                            <i class="fas fa-tshirt text-3xl text-gray-400 mb-2 peer-checked:text-accent"></i>
                                            <span class="text-sm font-semibold text-gray-700 peer-checked:text-accent">Giặt ủi</span>
                                            <div class="absolute inset-0 border-2 border-accent rounded-lg opacity-0 peer-checked:opacity-100"></div>
                                        </label>

                                        <label class="relative flex flex-col items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-accent transition">
                                            <input type="radio" name="service_type" value="extra_amenities" required class="sr-only peer">
                                            <i class="fas fa-box text-3xl text-gray-400 mb-2 peer-checked:text-accent"></i>
                                            <span class="text-sm font-semibold text-gray-700 peer-checked:text-accent">Vật dụng</span>
                                            <div class="absolute inset-0 border-2 border-accent rounded-lg opacity-0 peer-checked:opacity-100"></div>
                                        </label>

                                        <label class="relative flex flex-col items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-accent transition">
                                            <input type="radio" name="service_type" value="wake_up_call" required class="sr-only peer">
                                            <i class="fas fa-bell text-3xl text-gray-400 mb-2 peer-checked:text-accent"></i>
                                            <span class="text-sm font-semibold text-gray-700 peer-checked:text-accent">Gọi thức</span>
                                            <div class="absolute inset-0 border-2 border-accent rounded-lg opacity-0 peer-checked:opacity-100"></div>
                                        </label>

                                        <label class="relative flex flex-col items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-accent transition">
                                            <input type="radio" name="service_type" value="other" required class="sr-only peer">
                                            <i class="fas fa-ellipsis-h text-3xl text-gray-400 mb-2 peer-checked:text-accent"></i>
                                            <span class="text-sm font-semibold text-gray-700 peer-checked:text-accent">Khác</span>
                                            <div class="absolute inset-0 border-2 border-accent rounded-lg opacity-0 peer-checked:opacity-100"></div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Mức độ ưu tiên -->
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">
                                        Mức độ ưu tiên <span class="text-red-500">*</span>
                                    </label>
                                    <select name="priority" required class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent">
                                        <option value="normal">Bình thường</option>
                                        <option value="urgent">Khẩn cấp</option>
                                        <option value="emergency">Rất khẩn cấp</option>
                                    </select>
                                </div>

                                <!-- Thời gian mong muốn -->
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">
                                        Thời gian mong muốn <span class="text-gray-400 text-sm font-normal">(Tùy chọn)</span>
                                    </label>
                                    <input type="time" name="preferred_time" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent">
                                    <p class="text-xs text-gray-500 mt-1">Để trống nếu muốn được phục vụ ngay lập tức</p>
                                </div>

                                <!-- Mô tả chi tiết -->
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2">
                                        Mô tả chi tiết <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="description" rows="5" required 
                                              class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent" 
                                              placeholder="Vui lòng mô tả chi tiết yêu cầu của bạn..."></textarea>
                                    <p class="text-xs text-gray-500 mt-1">Ví dụ: "Cần 2 chai nước suối lạnh" hoặc "Điều hòa không hoạt động"</p>
                                </div>

                                <button type="submit" name="submit_request" class="w-full bg-accent text-white py-4 rounded-lg font-bold hover:bg-accent-dark transition flex items-center justify-center gap-2">
                                    <i class="fas fa-paper-plane"></i>
                                    Gửi yêu cầu
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Right: Service Info & History -->
                    <div class="space-y-6">
                        <!-- Quick Info -->
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded-lg">
                            <h3 class="font-bold text-blue-900 mb-3 flex items-center gap-2">
                                <i class="fas fa-info-circle"></i>
                                Thông tin dịch vụ
                            </h3>
                            <ul class="text-blue-800 text-sm space-y-2">
                                <li class="flex items-start gap-2">
                                    <i class="fas fa-check text-blue-600 mt-1"></i>
                                    <span>Thời gian phản hồi: 5-15 phút</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fas fa-check text-blue-600 mt-1"></i>
                                    <span>Dịch vụ 24/7</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fas fa-check text-blue-600 mt-1"></i>
                                    <span>Miễn phí cho dọn phòng</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fas fa-check text-blue-600 mt-1"></i>
                                    <span>Theo dõi trạng thái trực tuyến</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Service Hours -->
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-clock text-accent"></i>
                                Giờ phục vụ
                            </h3>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Dọn phòng</span>
                                    <span class="font-semibold">9:00 - 17:00</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Đặt đồ ăn</span>
                                    <span class="font-semibold">24/7</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Giặt ủi</span>
                                    <span class="font-semibold">8:00 - 20:00</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Sửa chữa</span>
                                    <span class="font-semibold">24/7 (khẩn cấp)</span>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Support -->
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <h3 class="font-bold text-gray-900 mb-4">Liên hệ trực tiếp</h3>
                            <div class="space-y-3">
                                <a href="tel:1900xxxx" class="flex items-center gap-3 text-gray-700 hover:text-accent transition">
                                    <i class="fas fa-phone-alt text-accent"></i>
                                    <div>
                                        <p class="text-xs text-gray-500">Hotline</p>
                                        <p class="font-semibold">1900-xxxx</p>
                                    </div>
                                </a>
                                <a href="mailto:service@vinpearl.com" class="flex items-center gap-3 text-gray-700 hover:text-accent transition">
                                    <i class="fas fa-envelope text-accent"></i>
                                    <div>
                                        <p class="text-xs text-gray-500">Email</p>
                                        <p class="font-semibold text-sm">service@vinpearl.com</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Service History -->
                <?php if (!empty($service_history)): ?>
                <div class="bg-white rounded-lg shadow-lg p-8 mt-8">
                    <h2 class="text-2xl font-serif font-bold text-gray-900 mb-6">Lịch sử yêu cầu</h2>
                    <div class="space-y-4">
                        <?php foreach ($service_history as $request): ?>
                            <?php
                            $status_colors = [
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'in_progress' => 'bg-blue-100 text-blue-800',
                                'completed' => 'bg-green-100 text-green-800',
                                'cancelled' => 'bg-red-100 text-red-800'
                            ];
                            $status_labels = [
                                'pending' => 'Đang chờ',
                                'in_progress' => 'Đang xử lý',
                                'completed' => 'Hoàn thành',
                                'cancelled' => 'Đã hủy'
                            ];
                            $status_class = $status_colors[$request['request_status']] ?? 'bg-gray-100 text-gray-800';
                            $status_label = $status_labels[$request['request_status']] ?? $request['request_status'];

                            $service_icons = [
                                'housekeeping' => 'fa-broom',
                                'food_delivery' => 'fa-utensils',
                                'beverages' => 'fa-glass-water',
                                'maintenance' => 'fa-tools',
                                'laundry' => 'fa-tshirt',
                                'extra_amenities' => 'fa-box',
                                'wake_up_call' => 'fa-bell',
                                'other' => 'fa-ellipsis-h'
                            ];
                            $service_icon = $service_icons[$request['service_type']] ?? 'fa-concierge-bell';
                            ?>
                            <div class="border border-gray-200 rounded-lg p-5 hover:shadow-md transition">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-start gap-3">
                                        <div class="bg-accent bg-opacity-10 p-3 rounded-lg">
                                            <i class="fas <?php echo $service_icon; ?> text-accent text-xl"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-gray-900 capitalize">
                                                <?php echo str_replace('_', ' ', $request['service_type']); ?>
                                            </h3>
                                            <p class="text-sm text-gray-600">
                                                <?php echo htmlspecialchars($request['room_type_name']); ?>
                                                <?php if ($request['room_number']): ?>
                                                    - Phòng <?php echo htmlspecialchars($request['room_number']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                <i class="far fa-clock mr-1"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($request['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="px-3 py-1 <?php echo $status_class; ?> rounded-full text-xs font-semibold">
                                        <?php echo $status_label; ?>
                                    </span>
                                </div>
                                <p class="text-gray-700 text-sm bg-gray-50 p-3 rounded">
                                    <?php echo htmlspecialchars($request['description']); ?>
                                </p>
                                <?php if ($request['preferred_time']): ?>
                                    <p class="text-xs text-gray-500 mt-2">
                                        <i class="far fa-clock mr-1"></i>
                                        Thời gian mong muốn: <?php echo date('H:i', strtotime($request['preferred_time'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>