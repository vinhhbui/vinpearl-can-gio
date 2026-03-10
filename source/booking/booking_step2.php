<?php
require '../includes/db_connect.php';
require '../includes/RoomAvailability.php';

// Lấy dữ liệu từ GET
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$checkin_str = isset($_GET['checkin']) ? $_GET['checkin'] : '';
$checkout_str = isset($_GET['checkout']) ? $_GET['checkout'] : '';
$adults = isset($_GET['adults']) ? intval($_GET['adults']) : 1;
$children = isset($_GET['children']) ? intval($_GET['children']) : 0;

// Validate dates
if (empty($checkin_str) || empty($checkout_str)) {
    header('Location: rooms.php');
    exit;
}

// --- Lấy Dữ liệu Phòng từ CSDL ---
$roomAvailability = new RoomAvailability($pdo);
$room_details = $roomAvailability->getRoomDetails($room_id);

if (!$room_details) {
    // Nếu không có room_id hoặc không tìm thấy phòng, quay về trang chọn phòng
    header('Location: rooms.php');
    exit;
}

// Tính số đêm
try {
    $checkin_date = new DateTime($checkin_str);
    $checkout_date = new DateTime($checkout_str);
    $interval = $checkin_date->diff($checkout_date);
    $num_nights = $interval->days > 0 ? $interval->days : 1; // Tối thiểu 1 đêm
} catch (Exception $e) {
    // Nếu ngày không hợp lệ, quay về trang chủ
    header('Location: ../index.php');
    exit;
}


$base_room_cost = $num_nights * $room_details['price_per_night'];

// Kiểm tra khả năng nhận phòng sớm và trả phòng muộn

$can_late_checkout = $roomAvailability->canLateCheckout($room_id, $checkin_str, $checkout_str);

// Định giá cho các tùy chọn nhận phòng sớm/trả phòng muộn
$early_checkin_fee = 500000; // 500k VND
$late_checkout_fee = 500000; // 500k VND

// --- Lấy Smart Upsell từ Database ---
$smart_upsell_offer = null;
$checkin_timestamp = strtotime($checkin_str);
$day_of_week = date('N', $checkin_timestamp); // 1 (Thứ 2) - 7 (Chủ Nhật)

try {
    $stmt = $pdo->prepare("SELECT * FROM upsell_offers WHERE is_active = 1 ORDER BY priority DESC");
    $stmt->execute();
    $all_upsells = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_upsells as $upsell) {
        $condition_data = json_decode($upsell['condition_data'], true);
        $matches = false;
        
        switch ($upsell['condition_type']) {
            case 'couple_weekend':
                if (isset($condition_data['adults']) && $condition_data['adults'] == $adults 
                    && isset($condition_data['children']) && $condition_data['children'] == $children
                    && isset($condition_data['days']) && in_array($day_of_week, $condition_data['days'])) {
                    $matches = true;
                }
                break;
            case 'family':
                if (isset($condition_data['has_children']) && $condition_data['has_children'] && $children > 0) {
                    $matches = true;
                }
                break;
            case 'weekday':
                if (isset($condition_data['days']) && in_array($day_of_week, $condition_data['days'])) {
                    $matches = true;
                }
                break;
            case 'custom':
                if (isset($condition_data['min_nights']) && $num_nights >= $condition_data['min_nights']) {
                    $matches = true;
                }
                break;
        }
        
        if ($matches) {
            $smart_upsell_offer = $upsell;
            break; // Lấy upsell đầu tiên match (priority cao nhất)
        }
    }
} catch (PDOException $e) {
    // Log error nếu cần
}

// --- Lấy Standard Add-ons từ Database ---
$standard_addons = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM standard_addons WHERE is_active = 1 ORDER BY display_order ASC");
    $stmt->execute();
    $standard_addons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log error nếu cần
}

// === KẾT THÚC LOGIC ===

// Đặt tiêu đề cho trang này
$page_title = 'Bước 2: Tùy chọn & Nâng cấp - Vinpearl Cần Giờ';
// Gọi header
include '../includes/header.php';
?>
<!-- Font Awesome để lấy icon cho upsell (thêm vào header nếu muốn dùng chung) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">


<!-- Booking Step 2 Section -->
<main id="booking-step2" class="py-24 bg-gray-50">
    <div class="container mx-auto px-4">
        
        <!-- Form chính, bao bọc cả 2 cột và submit đến bước 3 -->
        <form action="booking_step3.php" method="POST">
            <!-- Truyền tất cả dữ liệu quan trọng qua các trường ẩn -->
            <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
            <input type="hidden" name="checkin" value="<?php echo $checkin_str; ?>">
            <input type="hidden" name="checkout" value="<?php echo $checkout_str; ?>">
            <input type="hidden" name="adults" value="<?php echo $adults; ?>">
            <input type="hidden" name="children" value="<?php echo $children; ?>">
            <input type="hidden" name="num_nights" value="<?php echo $num_nights; ?>">
            <input type="hidden" name="base_room_cost" value="<?php echo $base_room_cost; ?>">

            <div class="flex flex-col lg:flex-row gap-12">
                
                <!-- Main Content: Options (2/3 width) -->
                <div class="lg:w-2/3">
                    <h1 class="text-3xl font-serif font-bold text-gray-900 mb-8">Kì nghỉ của riêng bạn</h1>

                    <!-- Early Check-in / Late Checkout Options -->
                    <div class="bg-white p-8 rounded-lg shadow-lg mb-10">
                        <h2 class="text-2xl font-serif font-semibold text-gray-900 mb-6">
                            <i class="fas fa-clock text-accent mr-2"></i>
                            Tùy chọn Thời gian
                        </h2>
                        
                        <div class="space-y-4">
                            <!-- Early Check-in -->
                            <!-- <label for="early_checkin" class="flex items-center p-4 border rounded-lg cursor-pointer <?php echo $can_early_checkin ? 'hover:bg-gray-50' : 'bg-gray-100 opacity-60 cursor-not-allowed'; ?> transition">
                                <input type="checkbox" 
                                       id="early_checkin" 
                                       name="early_checkin" 
                                       value="1"
                                       data-type="early_checkin"
                                       data-price="<?php echo $early_checkin_fee; ?>"
                                       <?php echo !$can_early_checkin ? 'disabled' : ''; ?>
                                       class="w-5 h-5 text-accent rounded focus:ring-accent mr-4">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-800">
                                        Nhận phòng sớm (12:00 PM)
                                        <span class="text-accent ml-2">+<?php echo number_format($early_checkin_fee, 0, ',', '.'); ?> VNĐ</span>
                                    </div>
                                    <?php if (!$can_early_checkin): ?>
                                        <p class="text-sm text-red-600 mt-1">
                                            <i class="fas fa-exclamation-circle mr-1"></i>
                                            Không khả dụng - Có xung đột với đặt phòng khác
                                        </p>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-600 mt-1">Nhận phòng từ 12:00 PM thay vì 14:00 PM</p>
                                    <?php endif; ?>
                                </div>
                            </label> -->

                            <!-- Late Checkout -->
                            <label for="late_checkout" class="flex items-center p-4 border rounded-lg cursor-pointer <?php echo $can_late_checkout ? 'hover:bg-gray-50' : 'bg-gray-100 opacity-60 cursor-not-allowed'; ?> transition">
                                <input type="checkbox" 
                                       id="late_checkout" 
                                       name="late_checkout" 
                                       value="1"
                                       data-type="late_checkout"
                                       data-price="<?php echo $late_checkout_fee; ?>"
                                       <?php echo !$can_late_checkout ? 'disabled' : ''; ?>
                                       class="w-5 h-5 text-accent rounded focus:ring-accent mr-4">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-800">
                                        Trả phòng muộn (16:00 PM)
                                        <span class="text-accent ml-2">+<?php echo number_format($late_checkout_fee, 0, ',', '.'); ?> VNĐ</span>
                                    </div>
                                    <?php if (!$can_late_checkout): ?>
                                        <p class="text-sm text-red-600 mt-1">
                                            <i class="fas fa-exclamation-circle mr-1"></i>
                                            Không khả dụng - Có xung đột với đặt phòng khác
                                        </p>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-600 mt-1">Trả phòng lúc 16:00 PM thay vì 12:00 PM</p>
                                    <?php endif; ?>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Smart Upsell Section (Dynamic) -->
                    <?php if ($smart_upsell_offer): ?>
                    <div class="bg-gradient-to-r from-accent to-blue-800 text-white p-8 rounded-lg shadow-lg mb-10">
                        <h2 class="text-3xl font-serif font-bold mb-4 flex items-center">
                            <i class="fas <?php echo htmlspecialchars($smart_upsell_offer['icon']); ?> mr-3"></i>
                            <?php echo htmlspecialchars($smart_upsell_offer['title']); ?>
                        </h2>
                        <p class="text-lg mb-6"><?php echo htmlspecialchars($smart_upsell_offer['description']); ?></p>
                        <label for="upsell_<?php echo $smart_upsell_offer['id']; ?>" class="flex items-center bg-white text-accent font-semibold p-4 rounded-md cursor-pointer hover:bg-gray-100 transition">
                            <input type="checkbox" 
                                   id="upsell_<?php echo $smart_upsell_offer['id']; ?>" 
                                   name="upsell_<?php echo $smart_upsell_offer['id']; ?>" 
                                   value="<?php echo $smart_upsell_offer['price']; ?>" 
                                   data-type="upsell"
                                   data-name="<?php echo htmlspecialchars($smart_upsell_offer['title']); ?>"
                                   class="w-6 h-6 text-accent rounded focus:ring-accent mr-4">
                            <span class="text-lg">Thêm vào đặt phòng với giá chỉ +<?php echo number_format($smart_upsell_offer['price'], 0, ',', '.'); ?> VNĐ</span>
                        </label>
                    </div>
                    <?php endif; ?>

                    <!-- Standard Add-ons (Dynamic from DB) -->
                    <div class="bg-white p-8 rounded-lg shadow-lg mb-10">
                        <h2 class="text-2xl font-serif font-semibold text-gray-900 mb-6">Dịch vụ Bổ sung</h2>
                        <div class="space-y-4">
                            <?php foreach ($standard_addons as $addon): ?>
                            <?php
                                // Tính giá hiển thị dựa trên price_type
                                $display_price = $addon['price'];
                                $price_label = number_format($display_price, 0, ',', '.');
                                
                                switch ($addon['price_type']) {
                                    case 'per_person':
                                        $price_label .= ' VNĐ / người';
                                        break;
                                    case 'per_night':
                                        $price_label .= ' VNĐ / ngày';
                                        break;
                                    case 'per_person_per_night':
                                        $price_label .= ' VNĐ / người / ngày';
                                        break;
                                    case 'per_booking':
                                        $price_label .= ' VNĐ / xe / ngày';
                                        break;
                                    default:
                                        $price_label .= ' VNĐ';
                                }
                            ?>
                            <label for="addon_<?php echo $addon['id']; ?>" class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                                <input type="checkbox" 
                                       id="addon_<?php echo $addon['id']; ?>" 
                                       name="addon_<?php echo $addon['id']; ?>" 
                                       value="<?php echo $addon['price']; ?>"
                                       data-type="<?php echo $addon['price_type']; ?>"
                                       data-name="<?php echo htmlspecialchars($addon['name']); ?>"
                                       class="w-5 h-5 text-accent rounded focus:ring-accent mr-4">
                                <div class="flex-1">
                                    <?php if ($addon['icon']): ?>
                                    <i class="fas <?php echo htmlspecialchars($addon['icon']); ?> text-accent mr-2"></i>
                                    <?php endif; ?>
                                    <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($addon['name']); ?> (+<?php echo $price_label; ?>)</span>
                                    <?php if ($addon['description']): ?>
                                    <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($addon['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </label>

                            <!-- Extra controls for non-fixed price types -->
                            <?php if ($addon['price_type'] !== 'fixed'): ?>
                            <div class="mt-2 mb-4 ml-9 flex gap-3 items-center addon-controls" id="addon_controls_<?php echo $addon['id']; ?>" style="display:none;">
                                <?php if (in_array($addon['price_type'], ['per_person','per_person_per_night'])): ?>
                                    <label class="text-sm text-gray-600">Số người:
                                        <input type="number" name="addon_qty_<?php echo $addon['id']; ?>" min="1" max="<?php echo ($adults + $children); ?>" value="<?php echo ($adults + $children); ?>" class="ml-2 w-20 px-2 py-1 border rounded">
                                    </label>
                                <?php endif; ?>

                                <?php if (in_array($addon['price_type'], ['per_night','per_person_per_night', 'per_booking'])): ?>
                                    <label class="text-sm text-gray-600">Số ngày:
                                        <input type="number" name="addon_nights_<?php echo $addon['id']; ?>" min="1" max="<?php echo max(1, $num_nights); ?>" value="<?php echo $num_nights; ?>" class="ml-2 w-20 px-2 py-1 border rounded">
                                    </label>
                                <?php endif; ?>
                                
                                <?php if (in_array($addon['price_type'], ['per_booking'])): ?>
                                    <label class="text-sm text-gray-600">Số xe:
                                        <input type="number" name="addon_qty_<?php echo $addon['id']; ?>" min="1" max="5" value="1" class="ml-2 w-20 px-2 py-1 border rounded">
                                    </label>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Room Preferences -->
                    <div class="bg-white p-8 rounded-lg shadow-lg">
                        <h2 class="text-2xl font-serif font-semibold text-gray-900 mb-6">Thiết lập Phòng theo Sở thích</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="pref_theme" class="block text-sm font-semibold text-gray-700 mb-2">Chủ đề Trang trí</label>
                                <select id="pref_theme" name="pref_theme" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent bg-white">
                                    <option>Tiêu chuẩn (Mặc định)</option>
                                    <option>Trăng mật (Cánh hoa hồng, nến)</option>
                                    <option>Sinh nhật (Bóng bay, thiệp chúc mừng)</option>
                                    <option>Kỷ niệm (Rượu vang, sô-cô-la)</option>
                                </select>
                            </div>
                            <div>
                                <label for="pref_temp" class="block text-sm font-semibold text-gray-700 mb-2">Nhiệt độ phòng</label>
                                <select id="pref_temp" name="pref_temp" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent bg-white">
                                    <option>Tiêu chuẩn (25°C)</option>
                                    <option>Mát (22°C)</option>
                                    <option>Ấm (27°C)</option>
                                    <option>Không mở trước</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-6">
                             <label for="pref_notes" class="block text-sm font-semibold text-gray-700 mb-2">Yêu cầu Đặc biệt</label>
                             <textarea id="pref_notes" name="pref_notes" rows="4" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent" placeholder="Ví dụ: phòng tầng cao, gần thang máy..."></textarea>
                        </div>
                    </div>

                </div>

                <!-- Sidebar: Booking Summary -->
                <aside class="lg:w-1/3">
                    <div class="sticky top-24 bg-white p-8 rounded-lg shadow-lg">
                        <h3 class="text-2xl font-serif font-semibold text-gray-900 mb-6">Tóm tắt Đơn hàng</h3>
                        
                        <div class="flex items-center gap-4 mb-6">
                            <img src="../<?php echo htmlspecialchars($room_details['image_url']); ?>" alt="<?php echo htmlspecialchars($room_details['room_type_name']); ?>" class="w-1/3 h-auto object-cover rounded-md">
                            <div>
                                <h4 class="font-semibold text-lg text-gray-800"><?php echo htmlspecialchars($room_details['room_type_name']); ?></h4>
                                <p class="text-sm text-gray-600"><?php echo $adults; ?> Người lớn, <?php echo $children; ?> Trẻ em</p>
                            </div>
                        </div>

                        <div class="space-y-3 border-t border-b py-4">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Check-in:</span>
                                <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($checkin_str); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Check-out:</span>
                                <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($checkout_str); ?></span>
                            </div>
                             <div class="flex justify-between">
                                <span class="text-gray-600">Số đêm:</span>
                                <span class="font-semibold text-gray-800"><?php echo $num_nights; ?></span>
                            </div>
                        </div>

                        <div class="space-y-3 pt-4 mb-6">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tiền phòng:</span>
                                <span class="font-semibold text-gray-800"><?php echo number_format($base_room_cost, 0, ',', '.'); ?> VNĐ</span>
                            </div>
                            <div class="flex justify-between text-gray-600 italic">
                                <span>Phí & Thuế:</span>
                                <span>(Tính ở bước sau)</span>
                            </div>
                        </div>
                        
                        <button type="submit" class="bg-accent text-white px-6 py-4 rounded-lg font-semibold w-full hover:bg-accent-dark transition-all duration-300 text-lg">
                            Tiếp tục (Thanh toán)
                        </button>
                    </div>
                </aside>

            </div>
        </form>

    </div>
</main>

<?php
// Gọi footer
include '../includes/footer.php';
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle addon controls when checkbox checked/unchecked
    document.querySelectorAll('input[id^="addon_"]').forEach(function(chk) {
        var id = chk.id.replace('addon_', '');
        var controls = document.getElementById('addon_controls_' + id);
        function update() {
            if (!controls) return;
            controls.style.display = chk.checked ? 'flex' : 'none';
        }
        chk.addEventListener('change', update);
        // init
        update();
    });

    // Also handle smart upsell checkbox (if it exists) to keep consistent behavior
    document.querySelectorAll('input[id^="upsell_"]').forEach(function(chk) {
        // (upsell currently doesn't need qty/nights) - placeholder if needed later
    });
});
</script>