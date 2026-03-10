<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Lấy danh sách booking active của user
$stmt = $pdo->prepare("
    SELECT b.*, r.room_type_name 
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE b.guest_email = ? 
    AND b.booking_status IN ('pending', 'confirmed', 'checked_in')
    AND b.checkout_date >= CURDATE()
    ORDER BY b.checkin_date ASC
");
$stmt->execute([$user['email']]);
$active_bookings = $stmt->fetchAll();

// Lấy booking_id được chọn (mặc định là booking đầu tiên)
$selected_booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : (isset($active_bookings[0]) ? $active_bookings[0]['id'] : 0);

// Xử lý thanh toán online
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_addon') {
    $addon_id = intval($_POST['addon_id']);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    
    try {
        // Cập nhật trạng thái addon thành confirmed (đã thanh toán)
        $stmt = $pdo->prepare("
            UPDATE booking_addons 
            SET status = 'confirmed', 
                staff_notes = CONCAT(IFNULL(staff_notes, ''), '\n[', NOW(), '] Khách tự thanh toán online - ', ?)
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$payment_method, $addon_id]);
        
        if ($stmt->rowCount() > 0) {
            $success_message = "Thanh toán thành công! Dịch vụ đã được xác nhận.";
        } else {
            $error_message = "Không thể thanh toán. Dịch vụ có thể đã được xử lý.";
        }
    } catch (Exception $e) {
        $error_message = "Có lỗi xảy ra: " . $e->getMessage();
    }
}

// Xử lý form đặt dịch vụ
$success_message = $success_message ?? null;
$error_message = $error_message ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service_type'])) {
    $booking_id = intval($_POST['booking_id']);
    $service_type = $_POST['service_type'];
    $service_date = $_POST['service_date'];
    $service_time = $_POST['service_time'] ?? null;
    $num_people = intval($_POST['num_people'] ?? 1);
    $notes = $_POST['notes'] ?? '';
    
    // Xác định giá dịch vụ
    $service_prices = [
        'restaurant' => 0,
        'spa' => 800000,
        'tour' => 450000,
        'motorbike' => 200000,
        'vinwonders' => 600000
    ];
    
    $service_names = [
        'restaurant' => 'Đặt bàn Nhà hàng',
        'spa' => 'Đặt lịch Spa',
        'tour' => 'Tour du lịch Cần Giờ',
        'motorbike' => 'Thuê xe máy',
        'vinwonders' => 'Vé VinWonders'
    ];
    
    $price = $service_prices[$service_type] ?? 0;
    $service_name = $service_names[$service_type] ?? 'Dịch vụ khác';
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO booking_addons 
            (booking_id, addon_id, addon_name, addon_price, quantity, status, created_at)
            VALUES (?, -1, ?, ?, ?, 'pending', NOW())
        ");
        
        $description = $service_name;
        if ($service_date) {
            $description .= " - Ngày: " . date('d/m/Y', strtotime($service_date));
        }
        if ($service_time) {
            $description .= " lúc " . $service_time;
        }
        if ($notes) {
            $description .= " (" . $notes . ")";
        }
        
        $stmt->execute([$booking_id, $description, $price, $num_people]);
        
        // Cập nhật tổng tiền booking
        $stmt = $pdo->prepare("
            UPDATE bookings 
            SET addons_total = addons_total + ?,
                total_price = total_price + ?
            WHERE id = ?
        ");
        $addon_total = $price * $num_people;
        $stmt->execute([$addon_total, $addon_total, $booking_id]);
        
        $success_message = "Đặt dịch vụ thành công! Tổng phí: " . number_format($addon_total, 0, ',', '.') . " VNĐ. Vui lòng thanh toán để xác nhận.";
        $selected_booking_id = $booking_id;
    } catch (Exception $e) {
        $error_message = "Có lỗi xảy ra: " . $e->getMessage();
    }
}

// Lấy thông tin booking được chọn
$selected_booking = null;
if ($selected_booking_id > 0) {
    foreach ($active_bookings as $booking) {
        if ($booking['id'] == $selected_booking_id) {
            $selected_booking = $booking;
            break;
        }
    }
}

// Lấy dịch vụ bổ sung của booking được chọn
$my_addons = [];
$total_addons = 0;
$total_pending = 0;
if ($selected_booking_id > 0) {
    $stmt = $pdo->prepare("
        SELECT ba.*, b.booking_reference, r.room_type_name
        FROM booking_addons ba
        JOIN bookings b ON ba.booking_id = b.id
        JOIN rooms r ON b.room_id = r.id
        WHERE ba.booking_id = ?
        AND ba.addon_id = -1
        ORDER BY ba.created_at DESC
    ");
    $stmt->execute([$selected_booking_id]);
    $my_addons = $stmt->fetchAll();
    
    foreach ($my_addons as $addon) {
        $amount = $addon['addon_price'] * $addon['quantity'];
        $total_addons += $amount;
        if ($addon['status'] === 'pending') {
            $total_pending += $amount;
        }
    }
}

$page_title = 'Đặt Dịch vụ & Tiện ích';
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

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
                    <span class="text-white font-bold">Đặt Dịch vụ</span>
                </li>
            </ol>
        </nav>
    </div>
</div>

<div class="min-h-screen bg-gray-50 py-12">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-serif font-bold text-gray-900 mb-8">Đặt Dịch vụ & Tiện ích</h1>

        <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-lg mb-6">
            <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-lg mb-6">
            <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <?php if (empty($active_bookings)): ?>
        <div class="bg-white rounded-lg shadow-lg p-8 text-center">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Chưa có đặt phòng</h2>
            <p class="text-gray-600 mb-6">Bạn cần có đặt phòng để sử dụng các dịch vụ bổ sung.</p>
            <a href="../booking/rooms.php" class="bg-accent text-white px-6 py-3 rounded-lg hover:bg-accent-dark transition">
                Đặt phòng ngay
            </a>
        </div>
        <?php else: ?>

        <!-- Chọn Booking -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-serif font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-hotel mr-3 text-accent"></i>
                Chọn đặt phòng
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($active_bookings as $booking): ?>
                <a href="?booking_id=<?php echo $booking['id']; ?>" 
                   class="block p-4 border-2 rounded-lg transition hover:shadow-lg <?php echo $booking['id'] == $selected_booking_id ? 'border-accent bg-accent bg-opacity-5' : 'border-gray-200 hover:border-accent'; ?>">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($booking['room_type_name']); ?></h3>
                            <p class="text-sm text-gray-600 mt-1">
                                <i class="fas fa-calendar mr-1"></i>
                                <?php echo date('d/m/Y', strtotime($booking['checkin_date'])); ?> - 
                                <?php echo date('d/m/Y', strtotime($booking['checkout_date'])); ?>
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                Mã: <?php echo htmlspecialchars($booking['booking_reference']); ?>
                            </p>
                        </div>
                        <?php if ($booking['id'] == $selected_booking_id): ?>
                        <i class="fas fa-check-circle text-accent text-2xl"></i>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($selected_booking): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left: Service Booking Forms -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Restaurant Reservation -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex items-center mb-4">
                        <div class="bg-red-100 p-3 rounded-full mr-4">
                            <i class="fas fa-utensils text-red-600 text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-serif font-bold text-gray-900">Đặt bàn Nhà hàng</h2>
                            <p class="text-gray-600 text-sm">Miễn phí đặt bàn - Đặt trước 30p</p>
                        </div>
                    </div>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="service_type" value="restaurant">
                        <input type="hidden" name="booking_id" value="<?php echo $selected_booking_id; ?>">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Ngày đặt bàn</label>
                                <input type="date" name="service_date" required min="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Giờ</label>
                                <select name="service_time" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent">
                                    <option value="11:00">11:00 AM</option>
                                    <option value="12:00">12:00 PM</option>
                                    <option value="18:00" selected>6:00 PM</option>
                                    <option value="19:00">7:00 PM</option>
                                    <option value="20:00">8:00 PM</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Số người</label>
                            <input type="number" name="num_people" min="1" max="10" value="2" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Ghi chú đặc biệt</label>
                            <textarea name="notes" rows="2" placeholder="Vị trí ngồi, món ăn đặc biệt..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition font-semibold">
                            <i class="fas fa-utensils mr-2"></i>Đặt bàn ngay
                        </button>
                    </form>
                </div>

                <!-- Spa Booking -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex items-center mb-4">
                        <div class="bg-pink-100 p-3 rounded-full mr-4">
                            <i class="fas fa-spa text-pink-600 text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-serif font-bold text-gray-900">Đặt lịch Spa</h2>
                            <p class="text-gray-600 text-sm">800,000 VNĐ/người (90 phút)</p>
                        </div>
                    </div>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="service_type" value="spa">
                        <input type="hidden" name="booking_id" value="<?php echo $selected_booking_id; ?>">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Ngày</label>
                                <input type="date" name="service_date" required min="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Giờ</label>
                                <select name="service_time" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent">
                                    <option value="09:00">9:00 AM</option>
                                    <option value="11:00">11:00 AM</option>
                                    <option value="14:00">2:00 PM</option>
                                    <option value="16:00">4:00 PM</option>
                                    <option value="18:00">6:00 PM</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Số người</label>
                            <input type="number" name="num_people" min="1" max="4" value="1" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Loại liệu trình</label>
                            <select name="notes" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent">
                                <option>Massage toàn thân</option>
                                <option>Chăm sóc da mặt</option>
                                <option>Massage đá nóng</option>
                                <option>Liệu trình kết hợp</option>
                            </select>
                        </div>
                        <button type="submit" class="w-full bg-pink-600 text-white px-6 py-3 rounded-lg hover:bg-pink-700 transition font-semibold">
                            <i class="fas fa-spa mr-2"></i>Đặt lịch Spa
                        </button>
                    </form>
                </div>

                <!-- Tour Booking -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex items-center mb-4">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-binoculars text-green-600 text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-serif font-bold text-gray-900">Tour du lịch Cần Giờ</h2>
                            <p class="text-gray-600 text-sm">450,000 VNĐ/người</p>
                        </div>
                    </div>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="service_type" value="tour">
                        <input type="hidden" name="booking_id" value="<?php echo $selected_booking_id; ?>">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Ngày tham quan</label>
                            <input type="date" name="service_date" required min="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Số người</label>
                            <input type="number" name="num_people" min="1" max="20" value="2" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Gói tour</label>
                            <select name="notes" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent">
                                <option>Rừng ngập mặn + Khu sinh thái</option>
                                <option>Đảo Khỉ + Bãi biển</option>
                                <option>Full day tour (7 tiếng)</option>
                            </select>
                        </div>
                        <button type="submit" class="w-full bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition font-semibold">
                            <i class="fas fa-binoculars mr-2"></i>Đặt tour
                        </button>
                    </form>
                </div>

                <!-- Motorbike Rental -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex items-center mb-4">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-motorcycle text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-serif font-bold text-gray-900">Thuê xe máy</h2>
                            <p class="text-gray-600 text-sm">200,000 VNĐ/xe/ngày</p>
                        </div>
                    </div>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="service_type" value="motorbike">
                        <input type="hidden" name="booking_id" value="<?php echo $selected_booking_id; ?>">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Từ ngày</label>
                                <input type="date" name="service_date" required min="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Số xe</label>
                                <input type="number" name="num_people" min="1" max="5" value="1" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Ghi chú</label>
                            <textarea name="notes" rows="2" placeholder="Số ngày thuê, yêu cầu đặc biệt..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
                            <i class="fas fa-motorcycle mr-2"></i>Thuê xe
                        </button>
                    </form>
                </div>

                <!-- VinWonders Tickets -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex items-center mb-4">
                        <div class="bg-purple-100 p-3 rounded-full mr-4">
                            <i class="fas fa-ticket-alt text-purple-600 text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-serif font-bold text-gray-900">Vé VinWonders</h2>
                            <p class="text-gray-600 text-sm">600,000 VNĐ/người</p>
                        </div>
                    </div>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="service_type" value="vinwonders">
                        <input type="hidden" name="booking_id" value="<?php echo $selected_booking_id; ?>">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Ngày tham quan</label>
                            <input type="date" name="service_date" required min="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Số vé</label>
                            <input type="number" name="num_people" min="1" max="10" value="2" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent">
                        </div>
                        <button type="submit" class="w-full bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition font-semibold">
                            <i class="fas fa-ticket-alt mr-2"></i>Mua vé
                        </button>
                    </form>
                </div>

            </div>

            <!-- Right: My Addons Summary & Payment -->
            <div class="space-y-6">
                
                <!-- Pending Payment Alert -->
                <?php if ($total_pending > 0): ?>
                <div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-4 animate-pulse">
                    <div class="flex items-center mb-3">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl mr-3"></i>
                        <div>
                            <h4 class="font-bold text-yellow-800">Có dịch vụ chờ thanh toán!</h4>
                            <p class="text-sm text-yellow-700">Vui lòng thanh toán để xác nhận dịch vụ</p>
                        </div>
                    </div>
                    <div class="text-center">
                        <span class="text-2xl font-bold text-yellow-800"><?php echo number_format($total_pending, 0, ',', '.'); ?> VNĐ</span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Services List Card -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-serif font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-clipboard-list mr-2 text-accent"></i>
                        Dịch vụ đã đặt
                    </h3>
                    
                    <div class="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                        <p class="text-sm text-blue-900 font-semibold">
                            <i class="fas fa-hotel mr-2"></i>
                            <?php echo htmlspecialchars($selected_booking['room_type_name']); ?>
                        </p>
                        <p class="text-xs text-blue-700 mt-1">
                            Mã: <?php echo htmlspecialchars($selected_booking['booking_reference']); ?>
                        </p>
                    </div>
                    
                    <?php if (empty($my_addons)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-inbox text-gray-300 text-4xl mb-3"></i>
                        <p class="text-gray-500 text-sm">Chưa có dịch vụ bổ sung cho phòng này.</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-3 max-h-[400px] overflow-y-auto">
                        <?php 
                        $addon_status_labels = [
                            'pending' => ['name' => 'Chờ thanh toán', 'bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'icon' => 'fa-clock'],
                            'confirmed' => ['name' => 'Đã thanh toán', 'bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'icon' => 'fa-check'],
                            'completed' => ['name' => 'Hoàn thành', 'bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'fa-check-circle'],
                            'cancelled' => ['name' => 'Đã hủy', 'bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'icon' => 'fa-times-circle']
                        ];
                        foreach ($my_addons as $addon): 
                            $status_info = $addon_status_labels[$addon['status']] ?? $addon_status_labels['pending'];
                            $addon_amount = $addon['addon_price'] * $addon['quantity'];
                        ?>
                        <div class="border <?php echo $addon['status'] === 'pending' ? 'border-yellow-300 bg-yellow-50' : 'border-gray-200'; ?> rounded-lg p-3 hover:shadow-md transition">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($addon['addon_name']); ?></p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-calendar-alt mr-1"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($addon['created_at'])); ?>
                                    </p>
                                    <span class="inline-flex items-center mt-2 px-2 py-1 rounded text-xs font-semibold <?php echo $status_info['bg'] . ' ' . $status_info['text']; ?>">
                                        <i class="fas <?php echo $status_info['icon']; ?> mr-1"></i>
                                        <?php echo $status_info['name']; ?>
                                    </span>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-accent text-sm">
                                        <?php echo number_format($addon_amount, 0, ',', '.'); ?> VNĐ
                                    </p>
                                    <p class="text-xs text-gray-500">x<?php echo $addon['quantity']; ?></p>
                                </div>
                            </div>
                            
                            <!-- Nút thanh toán cho dịch vụ pending -->
                            <?php if ($addon['status'] === 'pending' && $addon_amount > 0): ?>
                            <div class="mt-3 pt-3 border-t border-yellow-200">
                                <button type="button" 
                                        onclick="openPaymentModal(<?php echo $addon['id']; ?>, '<?php echo htmlspecialchars(addslashes($addon['addon_name'])); ?>', <?php echo $addon_amount; ?>)"
                                        class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white px-4 py-2 rounded-lg hover:from-green-600 hover:to-green-700 transition font-semibold text-sm">
                                    <i class="fas fa-credit-card mr-2"></i>Thanh toán ngay
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-600">Tổng dịch vụ:</span>
                            <span class="font-bold text-lg text-gray-900">
                                <?php echo number_format($total_addons, 0, ',', '.'); ?> VNĐ
                            </span>
                        </div>
                        <?php if ($total_pending > 0): ?>
                        <div class="flex justify-between items-center mb-4 text-yellow-700">
                            <span><i class="fas fa-clock mr-1"></i> Chờ thanh toán:</span>
                            <span class="font-bold"><?php echo number_format($total_pending, 0, ',', '.'); ?> VNĐ</span>
                        </div>
                        
                        <!-- Thanh toán tất cả -->
                        <button type="button" 
                                onclick="openPaymentAllModal(<?php echo $selected_booking_id; ?>, <?php echo $total_pending; ?>)"
                                class="w-full bg-gradient-to-r from-accent to-accent-dark text-white px-4 py-3 rounded-lg hover:opacity-90 transition font-bold mb-3">
                            <i class="fas fa-wallet mr-2"></i>Thanh toán tất cả (<?php echo number_format($total_pending, 0, ',', '.'); ?> VNĐ)
                        </button>
                        <?php endif; ?>
                        
                        <a href="booking_detail.php?id=<?php echo $selected_booking_id; ?>" class="block text-center bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900 transition text-sm font-semibold">
                            <i class="fas fa-file-invoice mr-2"></i>Xem chi tiết đặt phòng
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Payment Methods Info -->
                <div class="bg-gradient-to-br from-accent to-accent-dark text-white rounded-lg shadow-xl p-6">
                    <h3 class="text-lg font-bold mb-4 flex items-center">
                        <i class="fas fa-credit-card mr-2"></i>
                        Phương thức thanh toán
                    </h3>
                    
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="bg-white bg-opacity-20 rounded-lg p-3 text-center hover:bg-opacity-30 transition cursor-pointer">
                            <i class="fas fa-qrcode text-3xl mb-2"></i>
                            <p class="text-sm font-semibold">VNPay QR</p>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-lg p-3 text-center hover:bg-opacity-30 transition cursor-pointer">
                            <i class="fas fa-mobile-alt text-3xl mb-2"></i>
                            <p class="text-sm font-semibold">MoMo</p>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-lg p-3 text-center hover:bg-opacity-30 transition cursor-pointer">
                            <i class="fas fa-university text-3xl mb-2"></i>
                            <p class="text-sm font-semibold">Chuyển khoản</p>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-lg p-3 text-center hover:bg-opacity-30 transition cursor-pointer">
                            <i class="fas fa-money-bill-wave text-3xl mb-2"></i>
                            <p class="text-sm font-semibold">Tại quầy</p>
                        </div>
                    </div>
                    
                    <div class="text-xs opacity-80">
                        <p><i class="fas fa-shield-alt mr-1"></i> Thanh toán an toàn & bảo mật</p>
                        <p class="mt-1"><i class="fas fa-undo mr-1"></i> Hoàn tiền trong 24h nếu hủy</p>
                    </div>
                </div>

                <!-- Contact Support -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-headset text-green-600 text-xl mr-3 mt-1"></i>
                        <div>
                            <h4 class="font-semibold text-green-900 mb-2">Cần hỗ trợ?</h4>
                            <p class="text-sm text-green-800 mb-2">Liên hệ lễ tân 24/7:</p>
                            <p class="text-sm font-bold text-green-900">
                                <i class="fas fa-phone mr-1"></i> 1900 xxxx
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- Modal Thanh toán -->
<div id="paymentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
        <div class="bg-gradient-to-r from-accent to-accent-dark text-white p-6">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold"><i class="fas fa-credit-card mr-2"></i>Thanh toán dịch vụ</h3>
                <button onclick="closePaymentModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <p class="text-sm text-gray-600 mb-1">Dịch vụ:</p>
                <p id="paymentServiceName" class="font-semibold text-gray-900"></p>
                <div class="mt-3 pt-3 border-t border-gray-200">
                    <p class="text-sm text-gray-600">Số tiền thanh toán:</p>
                    <p id="paymentAmount" class="text-2xl font-bold text-accent"></p>
                </div>
            </div>
            
            <form id="paymentForm" method="POST">
                <input type="hidden" name="action" value="pay_addon">
                <input type="hidden" name="addon_id" id="paymentAddonId">
                
                <p class="font-semibold text-gray-900 mb-3">Chọn phương thức thanh toán:</p>
                
                <div class="space-y-3 mb-6">
                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:border-accent transition payment-method">
                        <input type="radio" name="payment_method" value="vnpay" class="mr-3" checked>
                        <i class="fas fa-qrcode text-blue-600 text-xl mr-3"></i>
                        <div>
                            <p class="font-semibold">VNPay QR</p>
                            <p class="text-xs text-gray-500">Quét mã thanh toán</p>
                        </div>
                    </label>
                    
                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:border-accent transition payment-method">
                        <input type="radio" name="payment_method" value="momo" class="mr-3">
                        <i class="fas fa-mobile-alt text-pink-600 text-xl mr-3"></i>
                        <div>
                            <p class="font-semibold">Ví MoMo</p>
                            <p class="text-xs text-gray-500">Thanh toán qua ứng dụng</p>
                        </div>
                    </label>
                    
                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:border-accent transition payment-method">
                        <input type="radio" name="payment_method" value="bank_transfer" class="mr-3">
                        <i class="fas fa-university text-green-600 text-xl mr-3"></i>
                        <div>
                            <p class="font-semibold">Chuyển khoản ngân hàng</p>
                            <p class="text-xs text-gray-500">Vietcombank, Techcombank...</p>
                        </div>
                    </label>
                    
                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:border-accent transition payment-method">
                        <input type="radio" name="payment_method" value="cash" class="mr-3">
                        <i class="fas fa-money-bill-wave text-yellow-600 text-xl mr-3"></i>
                        <div>
                            <p class="font-semibold">Thanh toán tại quầy</p>
                            <p class="text-xs text-gray-500">Tiền mặt hoặc thẻ khi check-out</p>
                        </div>
                    </label>
                </div>
                
                <button type="submit" class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-3 rounded-lg hover:from-green-600 hover:to-green-700 transition font-bold">
                    <i class="fas fa-check-circle mr-2"></i>Xác nhận thanh toán
                </button>
            </form>
        </div>
    </div>
</div>

<style>
.payment-method:has(input:checked) {
    border-color: #c9a55c;
    background-color: rgba(201, 165, 92, 0.05);
}
</style>

<script>
function openPaymentModal(addonId, serviceName, amount) {
    document.getElementById('paymentAddonId').value = addonId;
    document.getElementById('paymentServiceName').textContent = serviceName;
    document.getElementById('paymentAmount').textContent = new Intl.NumberFormat('vi-VN').format(amount) + ' VNĐ';
    document.getElementById('paymentModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function openPaymentAllModal(bookingId, totalAmount) {
    // Thanh toán tất cả - có thể mở rộng logic ở đây
    alert('Chức năng thanh toán tất cả đang được phát triển.\nVui lòng thanh toán từng dịch vụ hoặc đến quầy lễ tân.');
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Đóng modal khi click bên ngoài
document.getElementById('paymentModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePaymentModal();
    }
});

// Đóng modal khi nhấn ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePaymentModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>