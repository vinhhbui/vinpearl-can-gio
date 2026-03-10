<?php
// Đặt tiêu đề cho trang này
$page_title = 'Phòng & Suites - Vinpearl Cần Giờ';
// Gọi header
include '../includes/header.php';
// Gọi file kết nối CSDL
require_once '../includes/db_connect.php';
require_once '../includes/RoomAvailability.php';

// --- 1. Lấy các tham số từ URL ---
$checkin = isset($_GET['checkin']) ? htmlspecialchars($_GET['checkin']) : '';
$checkout = isset($_GET['checkout']) ? htmlspecialchars($_GET['checkout']) : '';
$adults = !empty($_GET['adults']) ? (int)$_GET['adults'] : 2;
$children = isset($_GET['children']) ? (int)$_GET['children'] : 0;
$sort = $_GET['sort'] ?? 'price_asc';
$max_price = !empty($_GET['max_price']) ? (int)$_GET['max_price'] : null;

$total_guests = $adults + $children;

// Validate dates nếu có
$has_dates = !empty($checkin) && !empty($checkout);
$date_valid = false;
$num_nights = 0;

if ($has_dates) {
    try {
        $checkin_date = new DateTime($checkin);
        $checkout_date = new DateTime($checkout);
        $today = new DateTime('today');
        
        if ($checkin_date >= $today && $checkout_date > $checkin_date) {
            $date_valid = true;
            $interval = $checkin_date->diff($checkout_date);
            $num_nights = $interval->days;
        }
    } catch (Exception $e) {
        $date_valid = false;
    }
}

// --- 2. Lấy danh sách phòng ---
$rooms = [];

if ($has_dates && $date_valid) {
    // Nếu có ngày hợp lệ, kiểm tra tình trạng trống
    $roomAvailability = new RoomAvailability($pdo);
    $rooms = $roomAvailability->getAvailableRooms($checkin, $checkout, $adults, $children);
    
    // Áp dụng lọc giá nếu có
    if ($max_price !== null) {
        $rooms = array_filter($rooms, function($room) use ($max_price) {
            return $room['price_per_night'] <= $max_price;
        });
    }
    
    // Sắp xếp
    usort($rooms, function($a, $b) use ($sort) {
        if ($sort === 'price_desc') {
            return $b['price_per_night'] <=> $a['price_per_night'];
        }
        return $a['price_per_night'] <=> $b['price_per_night'];
    });
} else {
    // Không có ngày, hiển thị tất cả phòng
    $sql = "SELECT id, room_type_name, description, price_per_night, max_occupancy, max_adults, max_children, num_beds, bed_type, room_size_sqm, image_url FROM rooms WHERE is_active = 1";
    $conditions = [];
    $params = [];

    if ($max_price !== null) {
        $conditions[] = "price_per_night <= ?";
        $params[] = $max_price;
    }

    if ($total_guests > 0) {
        $conditions[] = "max_occupancy >= ?";
        $params[] = $total_guests;
        $conditions[] = "max_adults >= ?";
        $params[] = $adults;
        $conditions[] = "max_children >= ?";
        $params[] = $children;
    }

    if (count($conditions) > 0) {
        $sql .= " AND " . implode(' AND ', $conditions);
    }

    switch ($sort) {
        case 'price_desc':
            $sql .= " ORDER BY price_per_night DESC";
            break;
        case 'price_asc':
        default:
            $sql .= " ORDER BY price_per_night ASC";
            break;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $all_rooms_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // --- XỬ LÝ LỌC TRÙNG LẶP LOẠI PHÒNG ---
        // Chỉ giữ lại 1 đại diện cho mỗi loại phòng (room_type_name)
        $rooms = [];
        $seen_types = [];

        foreach ($all_rooms_raw as $room_item) {
            $type_name = $room_item['room_type_name'];
            
            // Nếu chưa có loại phòng này trong danh sách hiển thị
            if (!in_array($type_name, $seen_types)) {
                $seen_types[] = $type_name;
                
                // Đếm tổng số lượng phòng vật lý của loại này (để hiển thị "Tổng có X phòng")
                // $count = 0;
                // foreach($all_rooms_raw as $r) {
                //     if($r['room_type_name'] === $type_name) $count++;
                // }
                // $room_item['available_count'] = $count; // Lưu số lượng

                // Lấy features (tiện ích) chỉ cho phòng đại diện này
                $stmt_feat = $pdo->prepare("SELECT feature_name, icon FROM room_features WHERE room_id = ?");
                $stmt_feat->execute([$room_item['id']]);
                $room_item['features'] = $stmt_feat->fetchAll(PDO::FETCH_ASSOC);

                // Thêm vào danh sách kết quả cuối cùng
                $rooms[] = $room_item;
            }
        }
        // ---------------------------------------

    } catch (PDOException $e) {
        $rooms = [];
    }
}
?>

<!-- Page Header -->
<header class="relative h-[300px] md:h-[400px] flex items-center justify-center text-white text-center">
    <!-- Background Image -->
    <div class="absolute inset-0">
        <img src="..\images\index\vinpearl_lux.jpg" 
             alt="Our Rooms" 
             class="w-full h-full object-cover">
    </div>
    <!-- Overlay -->
    <div class="absolute inset-0 bg-black bg-opacity-60"></div>
    
    <!-- Content -->
    <div class="relative z-10 p-4">
        <h1 class="text-4xl md:text-6xl font-serif font-bold mb-4">Phòng & Suites</h1>
        <p class="text-lg md:text-xl max-w-2xl mx-auto">Nơi sự sang trọng và thoải mái giao thoa</p>
    </div>
</header>

<!-- Rooms Grid Section -->
<main id="rooms-list" class="py-24 bg-gray-50">
    <div class="container mx-auto px-4">
        
        <!-- Form Bộ lọc -->
        <form method="GET" action="rooms.php" class="bg-white p-6 rounded-lg shadow-md mb-12">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
                <!-- Check-in Date -->
                <div>
                    <label for="checkin" class="block text-sm font-semibold text-gray-700 mb-1">Ngày nhận phòng</label>
                    <input type="date" id="checkin" name="checkin" value="<?php echo htmlspecialchars($checkin); ?>" 
                           min="<?php echo date('Y-m-d'); ?>"
                           class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-accent">
                </div>
                
                <!-- Check-out Date -->
                <div>
                    <label for="checkout" class="block text-sm font-semibold text-gray-700 mb-1">Ngày trả phòng</label>
                    <input type="date" id="checkout" name="checkout" value="<?php echo htmlspecialchars($checkout); ?>" 
                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                           class="w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-accent">
                </div>
                
                <!-- Người lớn -->
                <div>
                    <label for="adults" class="block text-sm font-semibold text-gray-700 mb-1">Người lớn</label>
                    <select id="adults" name="adults" class="w-full border border-gray-300 rounded-lg p-2 bg-white focus:outline-none focus:ring-2 focus:ring-accent">
                        <?php for($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php if($adults == $i) echo 'selected'; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <!-- Trẻ em -->
                <div>
                    <label for="children" class="block text-sm font-semibold text-gray-700 mb-1">Trẻ em</label>
                    <select id="children" name="children" class="w-full border border-gray-300 rounded-lg p-2 bg-white focus:outline-none focus:ring-2 focus:ring-accent">
                        <?php for($i = 0; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php if($children == $i) echo 'selected'; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <!-- Giá tối đa -->
                <div>
                    <label for="max_price" class="block text-sm font-semibold text-gray-700 mb-1">Giá tối đa</label>
                    <select id="max_price" name="max_price" class="w-full border border-gray-300 rounded-lg p-2 bg-white focus:outline-none focus:ring-2 focus:ring-accent">
                        <option value="">Tất cả</option>
                        <option value="5000000" <?php if ($max_price == 5000000) echo 'selected'; ?>>≤ 5tr</option>
                        <option value="7500000" <?php if ($max_price == 7500000) echo 'selected'; ?>>≤ 7.5tr</option>
                        <option value="15000000" <?php if ($max_price == 15000000) echo 'selected'; ?>>≤ 15tr</option>
                        <option value="25000000" <?php if ($max_price == 25000000) echo 'selected'; ?>>≤ 25tr</option>
                    </select>
                </div>
                
                <!-- Nút Tìm kiếm -->
                <div>
                    <button type="submit" class="w-full bg-accent text-white px-4 py-2 rounded-lg font-semibold hover:bg-accent-dark transition-all">
                        <i class="fas fa-search mr-2"></i>Tìm kiếm
                    </button>
                </div>
            </div>
            
            <!-- Sắp xếp (dòng riêng) -->
            <div class="mt-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <label for="sort" class="text-sm font-semibold text-gray-700">Sắp xếp:</label>
                    <select id="sort" name="sort" onchange="this.form.submit()" class="border border-gray-300 rounded-lg p-2 bg-white focus:outline-none focus:ring-2 focus:ring-accent">
                        <option value="price_asc" <?php if ($sort == 'price_asc') echo 'selected'; ?>>Giá thấp → cao</option>
                        <option value="price_desc" <?php if ($sort == 'price_desc') echo 'selected'; ?>>Giá cao → thấp</option>
                    </select>
                </div>
                
                <?php if ($has_dates && $date_valid): ?>
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-calendar-check text-accent mr-1"></i>
                        <span><?php echo $num_nights; ?> đêm • <?php echo date('d/m', strtotime($checkin)); ?> - <?php echo date('d/m', strtotime($checkout)); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <!-- Kết quả tìm kiếm -->
        <div class="mb-8">
            <h2 class="text-2xl font-serif font-bold text-gray-800">
                <?php if (!empty($rooms)): ?>
                    Tìm thấy <?php echo count($rooms); ?> loại phòng phù hợp
                    <?php if ($has_dates && $date_valid): ?>
                        <span class="text-base font-normal text-gray-600 ml-2">(còn trống trong khoảng thời gian bạn chọn)</span>
                    <?php endif; ?>
                <?php else: ?>
                    Không tìm thấy phòng nào phù hợp
                <?php endif; ?>
            </h2>
        </div>

        <!-- Rooms Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            
            <?php if (!empty($rooms)): ?>
                <?php foreach ($rooms as $room): ?>
                <!-- Room Card -->
                <div class="bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden transform hover:shadow-2xl hover:-translate-y-2 transition-all duration-500">
                    <div class="relative overflow-hidden">
                        <?php 
                            // Xử lý đường dẫn ảnh: Nếu không phải URL (http/https) thì thêm ../
                            $imgRaw = $room['image_url'] ?? ''; 

                            // Logic: Nếu có ảnh và không phải link online (http/https) thì thêm ../
                            if (!empty($imgRaw) && !preg_match("~^(?:f|ht)tps?://~i", $imgRaw)) {
                                $imgUrl = '../' . $imgRaw;
                            } else {
                                $imgUrl = $imgRaw;
                            }

                            // Fallback: Nếu không có ảnh thì dùng ảnh mặc định
                            if (empty($imgUrl)) {
                                $imgUrl = 'https://placehold.co/600x400/ddd/888?text=No+Image';
                            }
                        ?>
                        <img src="<?php echo htmlspecialchars($imgUrl); ?>" 
                             alt="Hình ảnh phòng" 
                             class="w-full h-64 object-cover transition-transform duration-500 hover:scale-110">
                        <div class="absolute top-4 left-4 bg-accent text-white px-4 py-1 rounded-full text-sm font-bold">
                            <?php echo number_format($room['price_per_night'], 0, ',', '.'); ?> VNĐ / đêm
                        </div>
                        <?php if (isset($room['available_count'])): ?>
                            <div class="absolute top-4 right-4 bg-green-500 text-white px-3 py-1 rounded-full text-xs font-semibold">
                                Còn <?php echo $room['available_count']; ?> phòng
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-6 flex flex-col">
                        <h3 class="text-2xl font-serif font-semibold text-gray-900 mb-3">
                            <?php echo htmlspecialchars($room['room_type_name']); ?>
                        </h3>
                        <p class="text-gray-600 mb-4 flex-grow">
                            <?php echo htmlspecialchars($room['description'] ?? 'Mô tả phòng đang được cập nhật.'); ?>
                        </p>
                        
                        <!-- Room Info -->
                        <div class="flex flex-wrap items-center gap-4 mb-4 text-sm text-gray-600">
                            <span class="flex items-center">
                                <i class="fas fa-users mr-1 text-accent"></i>
                                <?php echo $room['max_occupancy']; ?> khách
                            </span>
                            <?php if (isset($room['num_beds'])): ?>
                            <span class="flex items-center">
                                <i class="fas fa-bed mr-1 text-accent"></i>
                                <?php echo $room['num_beds']; ?> giường
                            </span>
                            <?php endif; ?>
                            <?php if (isset($room['room_size_sqm'])): ?>
                            <span class="flex items-center">
                                <i class="fas fa-ruler-combined mr-1 text-accent"></i>
                                <?php echo $room['room_size_sqm']; ?> m²
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Features -->
                        <?php if (!empty($room['features'])): ?>
                            <div class="flex flex-wrap gap-2 mb-4">
                                <?php foreach (array_slice($room['features'], 0, 3) as $feature): ?>
                                    <span class="inline-flex items-center text-xs text-gray-600 bg-gray-100 px-2 py-1 rounded">
                                        <i class="fas <?php echo $feature['icon']; ?> mr-1 text-accent"></i>
                                        <?php echo htmlspecialchars($feature['feature_name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Price Info -->
                        <?php if ($has_dates && $date_valid): ?>
                            <div class="mb-4 p-3 bg-blue-50 rounded-lg">
                                <div class="text-sm text-gray-600">Tổng cho <?php echo $num_nights; ?> đêm:</div>
                                <div class="text-xl font-bold text-accent">
                                    <?php echo number_format($room['price_per_night'] * $num_nights, 0, ',', '.'); ?> VNĐ
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- CTA Button -->
                        <?php
                        // Tạo URL để chuyển sang room detail với đầy đủ thông tin
                        $detail_url = 'room_detail.php?id=' . $room['id'];
                        $detail_params = [];
                        if ($has_dates && $date_valid) {
                            $detail_params['checkin'] = $checkin;
                            $detail_params['checkout'] = $checkout;
                        }
                        $detail_params['adults'] = $adults;
                        $detail_params['children'] = $children;
                        if (!empty($detail_params)) {
                            $detail_url .= '&' . http_build_query($detail_params);
                        }
                        ?>
                        <a href="<?php echo $detail_url; ?>" 
                           class="inline-block text-center mt-auto bg-accent text-white px-6 py-3 rounded-lg font-semibold hover:bg-accent-dark transition">
                            Xem chi tiết
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full text-center">
                    <div class="bg-white p-12 rounded-lg shadow-md">
                        <i class="fas fa-bed text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-600 text-lg mb-4">Rất tiếc, không có phòng nào phù hợp với tiêu chí của bạn.</p>
                        <a href="rooms.php" class="inline-block bg-accent text-white px-6 py-3 rounded-lg font-semibold hover:bg-accent-dark transition">
                            Xem tất cả phòng
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkinInput = document.getElementById('checkin');
    const checkoutInput = document.getElementById('checkout');

    function updateCheckoutMinDate() {
        if (checkinInput.value) {
            const checkinDate = new Date(checkinInput.value);
            checkinDate.setDate(checkinDate.getDate() + 1);
            const nextDay = checkinDate.toISOString().split('T')[0];
            checkoutInput.min = nextDay;

            // Nếu ngày trả phòng hiện tại sớm hơn ngày nhận phòng mới + 1, hãy cập nhật nó
            if (checkoutInput.value && checkoutInput.value < nextDay) {
                checkoutInput.value = nextDay;
            }
        }
    }

    // Cập nhật khi ngày nhận phòng thay đổi
    checkinInput.addEventListener('change', updateCheckoutMinDate);

    // Cập nhật khi tải trang trong trường hợp ngày đã được điền sẵn
    updateCheckoutMinDate();
});
</script>

<?php
// Gọi footer
include '../includes/footer.php';
?>