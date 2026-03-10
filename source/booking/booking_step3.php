<?php
// === LOGIC XỬ LÝ ===
// Gọi file kết nối CSDL
require '../includes/db_connect.php';

// Khởi tạo session nếu chưa có
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// === LẤY CÀI ĐẶT TỪ DATABASE ===
$site_settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value, setting_type FROM site_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Chuyển đổi giá trị theo kiểu dữ liệu
        $value = $row['setting_value'];
        switch ($row['setting_type']) {
            case 'number':
                $value = floatval($value);
                break;
            case 'boolean':
                $value = ($value === '1' || $value === 'true');
                break;
            case 'json':
                $value = json_decode($value, true);
                break;
        }
        $site_settings[$row['setting_key']] = $value;
    }
} catch (PDOException $e) {
    // Giá trị mặc định nếu không lấy được từ database
    $site_settings = [
        'tax_rate' => 0.08,
        'service_fee_rate' => 0.05,
        'site_name' => 'Vinpearl Resort & Spa Cần Giờ',
        'site_email' => 'contact@vinpearl-cangio.com',
        'site_phone' => '1900 xxxx',
        'checkin_time' => '14:00',
        'checkout_time' => '12:00'
    ];
}

// Lấy thuế và phí dịch vụ từ settings
$tax_rate = isset($site_settings['tax_rate']) ? $site_settings['tax_rate'] : 0.08;
$service_fee_rate = isset($site_settings['service_fee_rate']) ? $site_settings['service_fee_rate'] : 0.05;

$user_data = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'country' => 'Việt Nam' // Mặc định
];

if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_result) {
            $user_data = array_merge($user_data, $user_result);
        }
    } catch (PDOException $e) {
        // Bỏ qua lỗi nếu không lấy được thông tin
    }
}

// Lấy dữ liệu từ form POST (từ booking_step2.php)
$room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
$checkin_str = isset($_POST['checkin']) ? htmlspecialchars($_POST['checkin']) : '';
$checkout_str = isset($_POST['checkout']) ? htmlspecialchars($_POST['checkout']) : '';
$adults = isset($_POST['adults']) ? intval($_POST['adults']) : 0;
$children = isset($_POST['children']) ? intval($_POST['children']) : 0;
$num_nights = isset($_POST['num_nights']) ? intval($_POST['num_nights']) : 1;
$base_room_cost = isset($_POST['base_room_cost']) ? floatval($_POST['base_room_cost']) : 0;

// Lấy các tùy chọn cá nhân hóa
$pref_theme = isset($_POST['pref_theme']) ? htmlspecialchars($_POST['pref_theme']) : 'Tiêu chuẩn';
$pref_temp = isset($_POST['pref_temp']) ? htmlspecialchars($_POST['pref_temp']) : '22°C';
$pref_notes = isset($_POST['pref_notes']) ? htmlspecialchars($_POST['pref_notes']) : '';

// --- Lấy Dữ liệu Phòng từ CSDL ---
$room_details = null;
if ($room_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT room_type_name as name, image_url as image FROM rooms WHERE id = :id");
        $stmt->execute([':id' => $room_id]);
        $room_details = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $room_details = null;
    }
}

if (!$room_details) {
    // Nếu không có room_id hoặc không tìm thấy phòng, quay về trang chọn phòng
    header('Location: ../booking_step1.php');
    exit;
}

// --- Tính toán TỔNG CHI PHÍ (Quan trọng: Tính toán lại ở server) ---
$total_price = $base_room_cost;
$addons_summary = []; // Mảng để lưu tóm tắt các add-on

// NEW: Handle Early Checkin & Late Checkout Logic
$late_checkout = isset($_POST['late_checkout']) ? 1 : 0;

if ($late_checkout) {
    $price = 500000;
    $total_price += $price;
    $addons_summary[] = [
        'type' => 'option',
        'name' => 'Trả phòng muộn (16:00 PM)',
        'price' => $price
    ];
}

// Lấy tất cả upsell offers từ DB
$upsells_map = [];
try {
    $stmt = $pdo->query("SELECT id, offer_id, title, price FROM upsell_offers WHERE is_active = 1");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $upsells_map[$row['id']] = $row;
    }
} catch (PDOException $e) {}

// Lấy tất cả standard addons từ DB
$addons_map = [];
try {
    $stmt = $pdo->query("SELECT id, name, price, price_type FROM standard_addons WHERE is_active = 1");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $addons_map[$row['id']] = $row;
    }
} catch (PDOException $e) {}

// Xử lý upsell đã chọn
foreach ($_POST as $key => $value) {
    if (strpos($key, 'upsell_') === 0) {
        $upsell_id = intval(str_replace('upsell_', '', $key));
        if (isset($upsells_map[$upsell_id])) {
            $upsell = $upsells_map[$upsell_id];
            $price = floatval($upsell['price']);
            $total_price += $price;
            $addons_summary[] = [
                'type' => 'upsell',
                'name' => $upsell['title'],
                'price' => $price
            ];
        }
    }
}

// Xử lý standard addons đã chọn
foreach ($_POST as $key => $value) {
    if (strpos($key, 'addon_') === 0 && strpos($key, 'addon_qty_') === false && strpos($key, 'addon_nights_') === false) {
        $addon_id = intval(str_replace('addon_', '', $key));
        if (isset($addons_map[$addon_id])) {
            $addon = $addons_map[$addon_id];
            $base_price = floatval($addon['price']);

            $qty_people = (isset($_POST['addon_qty_' . $addon_id]) && intval($_POST['addon_qty_' . $addon_id]) > 0)
                ? intval($_POST['addon_qty_' . $addon_id])
                : ($adults + $children);

            $qty_nights = (isset($_POST['addon_nights_' . $addon_id]) && intval($_POST['addon_nights_' . $addon_id]) > 0)
                ? intval($_POST['addon_nights_' . $addon_id])
                : $num_nights;

            $final_price = $base_price;
            $price_label = $addon['name'];

            switch ($addon['price_type']) {
                case 'per_person':
                    $final_price = $base_price * $qty_people;
                    $price_label .= " (" . number_format($base_price, 0, ',', '.') . " x {$qty_people} khách)";
                    break;
                case 'per_night':
                    $final_price = $base_price * $qty_nights;
                    $price_label .= " (" . number_format($base_price, 0, ',', '.') . " x {$qty_nights} ngày)";
                    break;
                case 'per_person_per_night':
                    $final_price = $base_price * $qty_people * $qty_nights;
                    $price_label .= " (" . number_format($base_price, 0, ',', '.') . " x {$qty_people} khách x {$qty_nights} ngày)";
                    break;
                case 'per_booking':
                    $final_price = $base_price * $qty_people * $qty_nights;
                    $price_label .= " (" . number_format($base_price, 0, ',', '.') . " x {$qty_people} xe x {$qty_nights} ngày)";
                    break;
                default:
                    $final_price = $base_price;
            }

            $total_price += $final_price;
            $addons_summary[] = [
                'type' => 'standard',
                'name' => $price_label,
                'price' => $final_price,
                'people' => $qty_people,
                'nights' => $qty_nights
            ];
        }
    }
}

// Tính Thuế & Phí trước khi áp dụng mã (sử dụng giá trị từ database)
$tax_amount = $total_price * $tax_rate;
$service_fee_amount = $total_price * $service_fee_rate;
$total_price_final = $total_price + $tax_amount + $service_fee_amount;

// --- XỬ LÝ MÃ KHUYẾN MÃI ---
$promo_code = isset($_POST['promo_code']) ? trim($_POST['promo_code']) : '';
$promo_discount = 0;
$promo_message = '';
$promo_details = null;
$promo_class = '';

if (!empty($promo_code)) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM promotions 
            WHERE code = :code 
            AND is_active = 1 
            AND valid_from <= CURDATE() 
            AND valid_to >= CURDATE()
            AND (usage_limit IS NULL OR used_count < usage_limit)
        ");
        $stmt->execute([':code' => $promo_code]);
        $promo_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($promo_details) {
            $meets_min_nights = $num_nights >= $promo_details['min_nights'];
            $meets_min_amount = $total_price >= $promo_details['min_amount'];
            
            if ($meets_min_nights && $meets_min_amount) {
                if ($promo_details['discount_type'] === 'percentage') {
                    $promo_discount = $total_price * ($promo_details['discount_value'] / 100);
                    
                    if ($promo_details['max_discount'] && $promo_discount > $promo_details['max_discount']) {
                        $promo_discount = $promo_details['max_discount'];
                    }
                    
                    $promo_message = "✓ Mã giảm {$promo_details['discount_value']}% đã được áp dụng thành công!";
                } else {
                    $promo_discount = $promo_details['discount_value'];
                    $promo_message = "✓ Mã giảm " . number_format($promo_discount, 0, ',', '.') . " VNĐ đã được áp dụng thành công!";
                }
                $promo_class = 'success';
            } else {
                $promo_class = 'error';
                $promo_message = "✗ Mã không đủ điều kiện áp dụng:\n";
                
                if (!$meets_min_nights) {
                    $promo_message .= "• Yêu cầu tối thiểu {$promo_details['min_nights']} đêm (Hiện tại: {$num_nights} đêm)\n";
                }
                if (!$meets_min_amount) {
                    $promo_message .= "• Yêu cầu giá trị đơn hàng tối thiểu " . number_format($promo_details['min_amount'], 0, ',', '.') . " VNĐ (Hiện tại: " . number_format($total_price, 0, ',', '.') . " VNĐ)";
                }
            }
        } else {
            $promo_class = 'error';
            $promo_message = "✗ Mã không hợp lệ hoặc đã hết hạn.";
        }
    } catch (PDOException $e) {
        $promo_class = 'error';
        $promo_message = "✗ Lỗi khi kiểm tra mã khuyến mãi.";
    }
}

// Tính lại giá sau khi áp dụng mã (sử dụng thuế suất từ database)
$subtotal_after_promo = $total_price - $promo_discount;
$tax_amount = $subtotal_after_promo * $tax_rate;
$service_fee_amount = $subtotal_after_promo * $service_fee_rate;
$total_price_final = $subtotal_after_promo + $tax_amount + $service_fee_amount;

// Lưu thông tin vào session
$_SESSION['booking_data'] = [
    'room_id' => $room_id,
    'room_name' => $room_details['name'],
    'checkin' => $checkin_str,
    'checkout' => $checkout_str,
    'adults' => $adults,
    'children' => $children,
    'num_nights' => $num_nights,
    'base_room_cost' => $base_room_cost,
    'pref_theme' => $pref_theme,
    'pref_temp' => $pref_temp,
    'pref_notes' => $pref_notes,
    'late_checkout' => $late_checkout,
    'addons_summary' => $addons_summary,
    'promo_code' => $promo_code,
    'promo_discount' => $promo_discount,
    'promo_details' => $promo_details,
    'total_price' => $total_price,
    'tax_rate' => $tax_rate,
    'service_fee_rate' => $service_fee_rate,
    'tax_amount' => $tax_amount,
    'service_fee_amount' => $service_fee_amount,
    'total_price_final' => $total_price_final
];

// === KẾT THÚC LOGIC ===

// Đặt tiêu đề cho trang này
$page_title = 'Bước 3: Thanh toán - ' . ($site_settings['site_name'] ?? 'Vinpearl Cần Giờ');
// Gọi header
include '../includes/header.php';
?>

<!-- Booking Step 3 Section -->
<main id="booking-step3" class="py-24 bg-gray-50">
    <div class="container mx-auto px-4">

        <form action="booking_process.php" method="POST" id="booking-form">
            <!-- Hidden inputs giữ nguyên dữ liệu -->
            <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
            <input type="hidden" name="room_name" value="<?php echo htmlspecialchars($room_details['name']); ?>">
            <input type="hidden" name="checkin" value="<?php echo $checkin_str; ?>">
            <input type="hidden" name="checkout" value="<?php echo $checkout_str; ?>">
            <input type="hidden" name="adults" value="<?php echo $adults; ?>">
            <input type="hidden" name="children" value="<?php echo $children; ?>">
            <input type="hidden" name="num_nights" value="<?php echo $num_nights; ?>">
            <input type="hidden" name="pref_theme" value="<?php echo $pref_theme; ?>">
            <input type="hidden" name="pref_temp" value="<?php echo $pref_temp; ?>">
            <input type="hidden" name="pref_notes" value="<?php echo $pref_notes; ?>">
            <input type="hidden" name="base_room_cost" value="<?php echo $base_room_cost; ?>">
            <input type="hidden" name="total_price" value="<?php echo $total_price; ?>">
            <input type="hidden" name="tax_rate" value="<?php echo $tax_rate; ?>">
            <input type="hidden" name="service_fee_rate" value="<?php echo $service_fee_rate; ?>">
            <input type="hidden" name="tax_amount" value="<?php echo $tax_amount; ?>">
            <input type="hidden" name="service_fee_amount" value="<?php echo $service_fee_amount; ?>">
            <input type="hidden" name="total_price_final" value="<?php echo $total_price_final; ?>">
            <input type="hidden" name="addons_summary_json"
                value="<?php echo htmlspecialchars(json_encode($addons_summary)); ?>">
            <input type="hidden" name="promo_code" value="<?php echo htmlspecialchars($promo_code); ?>">
            <input type="hidden" name="promo_discount" value="<?php echo $promo_discount; ?>">
            <input type="hidden" name="late_checkout" value="<?php echo $late_checkout; ?>">

            <!-- Giữ lại các addon đã chọn -->
            <?php foreach ($_POST as $key => $value): ?>
            <?php if (strpos($key, 'addon_') === 0 || strpos($key, 'upsell_') === 0): ?>
            <input type="hidden" name="<?php echo htmlspecialchars($key); ?>"
                value="<?php echo htmlspecialchars($value); ?>">
            <?php endif; ?>
            <?php endforeach; ?>

            <div class="flex flex-col lg:flex-row gap-12">

                <!-- Main Content -->
                <div class="lg:w-2/3">
                    <h1 class="text-3xl font-serif font-bold text-gray-900 mb-8">Hoàn tất Đặt phòng</h1>

                    <!-- Login/Register Suggestion -->
                    <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="bg-blue-50 border border-blue-200 text-blue-800 px-6 py-5 rounded-lg mb-10 text-center">
                        <p class="font-semibold">Bạn đã có tài khoản?</p>
                        <p class="text-sm mb-3">
                            <a href="../login.php" class="font-bold underline hover:text-accent">Đăng nhập</a> để điền
                            thông tin nhanh hơn, nhận ưu đãi độc quyền và xem lại lịch sử đặt phòng của bạn.
                        </p>
                        <p class="text-sm">Chưa có tài khoản? <a href="../register.php"
                                class="font-bold underline hover:text-accent">Đăng ký ngay</a>.</p>
                    </div>
                    <?php endif; ?>

                    <!-- Guest Information -->
                    <div class="bg-white p-8 rounded-lg shadow-lg mb-10">
                        <h2 class="text-2xl font-serif font-semibold text-gray-900 mb-6">Thông tin Khách hàng</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="full_name" class="block text-sm font-semibold text-gray-700 mb-2">Họ tên đầy
                                    đủ</label>
                                <input type="text" id="full_name" name="full_name"
                                    value="<?php echo htmlspecialchars($user_data['full_name']); ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent"
                                    placeholder="Nguyễn Văn A" required>
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                                <input type="email" id="email" name="email"
                                    value="<?php echo htmlspecialchars($user_data['email']); ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent"
                                    placeholder="nguyenvana@email.com" required>
                            </div>
                            <div>
                                <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">Số điện
                                    thoại</label>
                                <input type="tel" id="phone" name="phone"
                                    value="<?php echo htmlspecialchars($user_data['phone']); ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent"
                                    placeholder="0901234567" required>
                            </div>
                            <div>
                                <label for="country" class="block text-sm font-semibold text-gray-700 mb-2">Quốc
                                    gia</label>
                                <input type="text" id="country" name="country"
                                    value="<?php echo htmlspecialchars($user_data['country']); ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent"
                                    placeholder="Việt Nam" required>
                            </div>
                        </div>
                    </div>

                    <!-- PHẦN MÃ KHUYẾN MÃI -->
                    <div class="bg-white p-8 rounded-lg shadow-lg mb-10">
                        <h2 class="text-2xl font-serif font-semibold text-gray-900 mb-6">Mã Khuyến mãi</h2>

                        <div class="flex gap-3">
                            <input type="text" id="promo_code_input" name="promo_code_display"
                                value="<?php echo htmlspecialchars($promo_code); ?>"
                                class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent"
                                placeholder="Nhập mã khuyến mãi (VD: WELCOME2025)">
                            <button type="button" onclick="applyPromoCode()"
                                class="bg-accent text-white px-8 py-3 rounded-lg font-semibold hover:bg-accent-dark transition-all duration-300">
                                Áp dụng
                            </button>
                        </div>

                        <?php if (!empty($promo_message)): ?>
                        <div
                            class="mt-4 p-4 rounded-lg <?php echo $promo_class === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
                            <p
                                class="<?php echo $promo_class === 'success' ? 'text-green-800' : 'text-red-800'; ?> text-sm whitespace-pre-line font-semibold">
                                <?php echo $promo_message; ?>
                            </p>
                            <?php if ($promo_class === 'success' && $promo_discount > 0): ?>
                            <p class="text-green-700 text-xs mt-2">
                                Bạn tiết kiệm được: <strong><?php echo number_format($promo_discount, 0, ',', '.'); ?>
                                    VNĐ</strong>
                            </p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Hiển thị các mã khuyến mãi có sẵn -->
                        <div class="mt-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Khuyến mãi đang diễn ra</h3>
                            <div class="space-y-3">
                                <?php
                                try {
                                    $stmt = $pdo->query("
                                        SELECT code, title, description, discount_type, discount_value, 
                                               min_nights, min_amount, max_discount, valid_to
                                        FROM promotions 
                                        WHERE is_active = 1 
                                        AND valid_from <= CURDATE() 
                                        AND valid_to >= CURDATE()
                                        AND (usage_limit IS NULL OR used_count < usage_limit)
                                        ORDER BY discount_value DESC
                                        LIMIT 5
                                    ");
                                    while ($promo = $stmt->fetch(PDO::FETCH_ASSOC)):
                                ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:border-accent transition cursor-pointer"
                                    onclick="fillPromoCode('<?php echo $promo['code']; ?>')">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h4 class="font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($promo['title']); ?></h4>
                                            <p class="text-sm text-gray-600 mt-1">
                                                <?php echo htmlspecialchars($promo['description']); ?></p>
                                            <div class="flex flex-wrap gap-2 mt-2">
                                                <?php if ($promo['min_nights'] > 1): ?>
                                                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">
                                                    <i class="fas fa-moon mr-1"></i>Tối thiểu
                                                    <?php echo $promo['min_nights']; ?> đêm
                                                </span>
                                                <?php endif; ?>
                                                <?php if ($promo['min_amount'] > 0): ?>
                                                <span class="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded">
                                                    <i class="fas fa-tag mr-1"></i>Tối thiểu
                                                    <?php echo number_format($promo['min_amount'], 0, ',', '.'); ?> VNĐ
                                                </span>
                                                <?php endif; ?>
                                                <?php if ($promo['discount_type'] === 'percentage' && $promo['max_discount']): ?>
                                                <span class="text-xs bg-orange-100 text-orange-800 px-2 py-1 rounded">
                                                    <i class="fas fa-exclamation-circle mr-1"></i>Tối đa
                                                    <?php echo number_format($promo['max_discount'], 0, ',', '.'); ?>
                                                    VNĐ
                                                </span>
                                                <?php endif; ?>
                                                <span class="text-xs bg-gray-100 text-gray-800 px-2 py-1 rounded">
                                                    <i class="far fa-calendar mr-1"></i>HSD:
                                                    <?php echo date('d/m/Y', strtotime($promo['valid_to'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4 text-right">
                                            <span
                                                class="inline-block bg-accent text-white px-3 py-1 rounded-full font-mono font-bold text-sm">
                                                <?php echo $promo['code']; ?>
                                            </span>
                                            <p class="text-accent font-bold mt-1">
                                                <?php 
                                                    if ($promo['discount_type'] === 'percentage') {
                                                        echo "-{$promo['discount_value']}%";
                                                    } else {
                                                        echo "-" . number_format($promo['discount_value'], 0, ',', '.') . " VNĐ";
                                                    }
                                                    ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php 
                                    endwhile;
                                } catch (PDOException $e) {
                                    // Không hiển thị gì nếu có lỗi
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Details -->
                    <div class="bg-white p-8 rounded-lg shadow-lg">
                        <h2 class="text-2xl font-serif font-semibold text-gray-900 mb-6">Chọn Phương thức Thanh toán
                        </h2>

                        <!-- Payment Method Selection -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <label for="payment_qr"
                                class="flex items-center p-4 border rounded-lg cursor-pointer has-[:checked]:bg-accent-light has-[:checked]:border-accent">
                                <input type="radio" id="payment_qr" name="payment_method" value="qr_code"
                                    class="w-5 h-5 text-accent focus:ring-accent" checked>
                                <span class="ml-3 font-semibold text-gray-800">Thanh toán qua mã QR (PayOS)</span>
                            </label>
                            <label for="payment_card"
                                class="flex items-center p-4 border rounded-lg cursor-pointer has-[:checked]:bg-accent-light has-[:checked]:border-accent">
                                <input type="radio" id="payment_card" name="payment_method" value="international_card"
                                    class="w-5 h-5 text-accent focus:ring-accent">
                                <span class="ml-3 font-semibold text-gray-800">Thanh toán bằng thẻ quốc tế</span>
                            </label>
                        </div>

                        <!-- QR Code Info -->
                        <div id="qr-code-info">
                            <p class="text-gray-600 bg-gray-50 p-4 rounded-lg">
                                Bạn sẽ được chuyển đến trang thanh toán an toàn của PayOS để quét mã QR bằng ứng dụng
                                ngân hàng hoặc ví điện tử của bạn.
                            </p>
                        </div>

                        <!-- Card Details Form (Initially hidden) -->
                        <div id="card-details-form" class="hidden space-y-4">
                            <div>
                                <label for="card_name" class="block text-sm font-semibold text-gray-700 mb-2">Tên trên
                                    thẻ</label>
                                <input type="text" id="card_name" name="card_name"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent"
                                    placeholder="NGUYEN VAN A">
                            </div>
                            <div>
                                <label for="card_number" class="block text-sm font-semibold text-gray-700 mb-2">Số
                                    thẻ</label>
                                <input type="text" id="card_number" name="card_number"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent"
                                    placeholder="4000 1234 5678 9010">
                            </div>
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label for="card_expiry" class="block text-sm font-semibold text-gray-700 mb-2">Ngày
                                        hết hạn (MM/YY)</label>
                                    <input type="text" id="card_expiry" name="card_expiry"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent"
                                        placeholder="12/28">
                                </div>
                                <div>
                                    <label for="card_cvc"
                                        class="block text-sm font-semibold text-gray-700 mb-2">CVC</label>
                                    <input type="text" id="card_cvc" name="card_cvc"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent"
                                        placeholder="123">
                                </div>
                            </div>
                        </div>

                        <div class="pt-6">
                            <label class="flex items-center">
                                <input type="checkbox" name="terms"
                                    class="w-5 h-5 text-accent rounded focus:ring-accent" required>
                                <span class="ml-3 text-gray-700">Tôi đồng ý với các <a href="#"
                                        class="text-accent hover:underline">Điều khoản & Điều kiện</a></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Sidebar: Final Summary -->
                <aside class="lg:w-1/3">
                    <div class="sticky top-24 bg-white p-8 rounded-lg shadow-lg">
                        <h3 class="text-2xl font-serif font-semibold text-gray-900 mb-6">Chi tiết Đơn hàng</h3>

                        <div class="mb-6">
                            <h4 class="font-semibold text-lg text-gray-800">
                                <?php echo htmlspecialchars($room_details['name']); ?></h4>
                            <p class="text-sm text-gray-600"><?php echo $num_nights; ?> đêm (<?php echo $checkin_str; ?>
                                - <?php echo $checkout_str; ?>)</p>
                            <p class="text-sm text-gray-600"><?php echo $adults; ?> Người lớn, <?php echo $children; ?>
                                Trẻ em</p>
                        </div>

                        <div class="space-y-3 border-t border-b py-4">
                            <h4 class="font-semibold text-lg text-gray-800">Chi tiết Hóa đơn</h4>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tiền phòng</span>
                                <span
                                    class="font-semibold text-gray-800"><?php echo number_format($base_room_cost, 0, ',', '.'); ?>
                                    VNĐ</span>
                            </div>

                            <!-- Hiển thị các add-ons đã chọn -->
                            <?php foreach ($addons_summary as $addon): ?>
                            <div class="flex justify-between">
                                <span
                                    class="text-gray-600 text-sm"><?php echo htmlspecialchars($addon['name']); ?></span>
                                <span
                                    class="font-semibold text-gray-800"><?php echo number_format($addon['price'], 0, ',', '.'); ?>
                                    VNĐ</span>
                            </div>
                            <?php endforeach; ?>

                            <div class="flex justify-between border-t pt-2 mt-2">
                                <span class="text-gray-600">Tổng phụ</span>
                                <span
                                    class="font-semibold text-gray-800"><?php echo number_format($total_price, 0, ',', '.'); ?>
                                    VNĐ</span>
                            </div>

                            <?php if ($promo_discount > 0): ?>
                            <div class="flex justify-between text-green-600">
                                <span>Giảm giá (<?php echo htmlspecialchars($promo_code); ?>)</span>
                                <span class="font-semibold">-<?php echo number_format($promo_discount, 0, ',', '.'); ?>
                                    VNĐ</span>
                            </div>
                            <div class="flex justify-between border-t pt-2">
                                <span class="text-gray-600">Sau giảm giá</span>
                                <span
                                    class="font-semibold text-gray-800"><?php echo number_format($subtotal_after_promo, 0, ',', '.'); ?>
                                    VNĐ</span>
                            </div>
                            <?php endif; ?>

                            <!-- <div class="flex justify-between">
                                <span class="text-gray-600">Thuế VAT (<?php echo round($tax_rate * 100); ?>%)</span>
                                <span class="font-semibold text-gray-800"><?php echo number_format($tax_amount, 0, ',', '.'); ?> VNĐ</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Phí dịch vụ (<?php echo round($service_fee_rate * 100); ?>%)</span>
                                <span class="font-semibold text-gray-800"><?php echo number_format($service_fee_amount, 0, ',', '.'); ?> VNĐ</span>
                            </div> -->
                        </div>

                        <div class="flex justify-between items-center mt-6">
                            <span class="text-2xl font-serif font-bold text-gray-900">TỔNG CỘNG</span>
                            <span
                                class="text-2xl font-serif font-bold text-accent"><?php echo number_format($total_price_final, 0, ',', '.'); ?>
                                VNĐ</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Phí dịch vụ & Thuế (Đã bao gồm)</span>
                        </div>


                        <?php if ($promo_discount > 0): ?>
                        <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg text-center">
                            <p class="text-green-800 font-semibold text-sm">
                                🎉 Bạn đã tiết kiệm được <?php echo number_format($promo_discount, 0, ',', '.'); ?> VNĐ!
                            </p>
                        </div>
                        <?php endif; ?>

                        <button type="submit"
                            class="bg-accent text-white mt-8 px-6 py-4 rounded-lg font-semibold w-full hover:bg-accent-dark transition-all duration-300 text-lg">
                            Xác nhận & Thanh toán
                        </button>
                    </div>
                </aside>
            </div>
        </form>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentQrRadio = document.getElementById('payment_qr');
    const paymentCardRadio = document.getElementById('payment_card');
    const qrCodeInfo = document.getElementById('qr-code-info');
    const cardDetailsForm = document.getElementById('card-details-form');
    const cardInputs = cardDetailsForm.querySelectorAll('input');

    function togglePaymentMethod() {
        if (paymentCardRadio.checked) {
            qrCodeInfo.classList.add('hidden');
            cardDetailsForm.classList.remove('hidden');
            cardInputs.forEach(input => input.required = true);
        } else {
            qrCodeInfo.classList.remove('hidden');
            cardDetailsForm.classList.add('hidden');
            cardInputs.forEach(input => input.required = false);
        }
    }

    paymentQrRadio.addEventListener('change', togglePaymentMethod);
    paymentCardRadio.addEventListener('change', togglePaymentMethod);

    // Initial check
    togglePaymentMethod();
});

function applyPromoCode() {
    const promoCode = document.getElementById('promo_code_input').value.trim();
    const bookingForm = document.getElementById('booking-form');

    // Tạo một form ẩn để submit
    const tempForm = document.createElement('form');
    tempForm.method = 'POST';
    tempForm.action = '';

    // Copy tất cả các input từ booking form
    const allInputs = bookingForm.querySelectorAll('input[type="hidden"]');
    allInputs.forEach(input => {
        const clonedInput = input.cloneNode(true);
        tempForm.appendChild(clonedInput);
    });

    // Thêm promo code
    const promoInput = document.createElement('input');
    promoInput.type = 'hidden';
    promoInput.name = 'promo_code';
    promoInput.value = promoCode;
    tempForm.appendChild(promoInput);

    // Submit form
    document.body.appendChild(tempForm);
    tempForm.submit();
}

function fillPromoCode(code) {
    document.getElementById('promo_code_input').value = code;
    applyPromoCode();
}
</script>