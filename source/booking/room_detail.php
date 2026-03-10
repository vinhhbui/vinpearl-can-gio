<?php

require '../includes/db_connect.php';
require '../includes/RoomAvailability.php';

// Lấy room_id từ URL
$room_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Lấy thông tin tìm kiếm từ URL (nếu có)
$checkin = isset($_GET['checkin']) ? htmlspecialchars($_GET['checkin']) : '';
$checkout = isset($_GET['checkout']) ? htmlspecialchars($_GET['checkout']) : '';
$adults = isset($_GET['adults']) ? intval($_GET['adults']) : 1;
$children = isset($_GET['children']) ? intval($_GET['children']) : 0;

// Khởi tạo các biến
$has_dates = !empty($checkin) && !empty($checkout);
$date_valid = false;
$num_nights = 0;
$total_price = 0;
$available_rooms_count = 0;
$date_error = ''; // Lỗi liên quan đến ngày tháng, sức chứa
$availability_error = ''; // Lỗi liên quan đến phòng trống

// Lấy thông tin chi tiết phòng trước
try {
    $roomAvailability = new RoomAvailability($pdo);
    $room = $roomAvailability->getRoomDetails($room_id);
    
    if (!$room) {
        header('Location: rooms.php');
        exit;
    }

    // --- XỬ LÝ ĐƯỜNG DẪN ẢNH (Fix Path) ---
    // Hàm hỗ trợ kiểm tra và sửa đường dẫn
    if (!function_exists('fixImagePath')) {
        function fixImagePath($url) {
            if ($url && !preg_match("~^(?:f|ht)tps?://~i", $url)) {
                return '../' . $url;
            }
            return $url ?: 'https://placehold.co/600x400/ddd/888?text=No+Image';
        }
    }

    // Sửa đường dẫn ảnh đại diện
    $room['image_url'] = fixImagePath($room['image_url']);

    // Sửa đường dẫn gallery
    if (!empty($room['images'])) {
        foreach ($room['images'] as &$img) {
            $img['image_url'] = fixImagePath($img['image_url']);
        }
        unset($img); // Break reference
    }
    // ---------------------------------------

} catch (PDOException $e) {
    error_log("Error fetching room details: " . $e->getMessage());
    header('Location: rooms.php');
    exit;
}

// 1. Kiểm tra sức chứa trước tiên
$total_guests = $adults + $children;
if ($total_guests > $room['max_occupancy'] || $adults > $room['max_adults'] || $children > $room['max_children']) {
    $date_error = 'Số lượng khách đã chọn không phù hợp với sức chứa của phòng.';
}

// 2. Nếu có ngày và không có lỗi sức chứa, kiểm tra ngày và phòng trống
if ($has_dates && empty($date_error)) {
    try {
        $checkin_date = new DateTime($checkin);
        $checkout_date = new DateTime($checkout);
        $today = new DateTime('today');
        
        if ($checkin_date < $today) {
            $date_error = 'Ngày check-in không được là ngày trong quá khứ.';
        } elseif ($checkout_date <= $checkin_date) {
            $date_error = 'Ngày check-out phải sau ngày check-in.';
        } else {
            $date_valid = true;
            $interval = $checkin_date->diff($checkout_date);
            $num_nights = $interval->days;
            
            // Tính giá và kiểm tra phòng trống
            $total_price = $room['price_per_night'] * $num_nights;
            // SỬ DỤNG LOGIC MỚI TỪ ROOMS.PHP
            $available_rooms_count = $roomAvailability->getAvailableRoomCountForType($room_id, $checkin, $checkout);
            
            if ($available_rooms_count == 0) {
                $availability_error = 'Rất tiếc, loại phòng này đã hết trong khoảng thời gian bạn chọn.';
            }
        }
    } catch (Exception $e) {
        $date_error = 'Định dạng ngày không hợp lệ.';
    }
}

$page_title = $room['room_type_name'] . ' - Vinpearl Cần Giờ';
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">


<!-- Main Content -->
<main class="py-12 bg-white">
    <div class="container mx-auto px-4">
        
        <!-- Room Title & Info Bar -->
        <div class="mb-8">
            <h1 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-4">
                <?php echo htmlspecialchars($room['room_type_name']); ?>
            </h1>
            <div class="flex flex-wrap items-center gap-6 text-gray-600">
                <span class="flex items-center">
                    <i class="fas fa-users text-accent mr-2"></i>
                    Tối đa <?php echo $room['max_occupancy']; ?> khách (<?php echo $room['max_adults']; ?> người lớn, <?php echo $room['max_children']; ?> trẻ em)
                </span>
                <span class="flex items-center">
                    <i class="fas fa-bed text-accent mr-2"></i>
                    <?php echo $room['num_beds']; ?> giường <?php echo $room['bed_type']; ?>
                </span>
                <span class="flex items-center">
                    <i class="fas fa-ruler-combined text-accent mr-2"></i>
                    <?php echo $room['room_size_sqm']; ?> m²
                </span>
                <?php if ($room['view_type']): ?>
                <span class="flex items-center">
                    <i class="fas fa-mountain text-accent mr-2"></i>
                    <?php echo htmlspecialchars($room['view_type']); ?>
                </span>
                <?php endif; ?>
                <span class="flex items-center">
                    <i class="fas fa-door-open text-accent mr-2"></i>
                    <?php 
                    if ($has_dates && $date_valid && empty($availability_error)) {
                        echo 'Còn <strong>' . $available_rooms_count . '</strong> phòng trống';
                    } else {
                        echo 'Tổng số ' . $room['total_physical_rooms'] . ' phòng';
                    }
                    ?>
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Column: Images & Info (2/3) -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- Image Gallery -->
                <div class="bg-gray-100 rounded-lg overflow-hidden">
                    <!-- Main Image -->
                    <div id="mainImage" class="relative h-[400px] md:h-[500px]">
                        <?php 
                        $main_image = !empty($room['images']) ? $room['images'][0] : ['image_url' => $room['image_url'], 'image_title' => $room['room_type_name']];
                        ?>
                        <img src="<?php echo htmlspecialchars($main_image['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($main_image['image_title'] ?? $room['room_type_name']); ?>" 
                             class="w-full h-full object-cover">
                        <div class="absolute bottom-4 left-4 bg-black bg-opacity-70 text-white px-4 py-2 rounded-lg">
                            <span id="imageTitle"><?php echo htmlspecialchars($main_image['image_title'] ?? $room['room_type_name']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Thumbnail Gallery -->
                    <?php if (!empty($room['images']) && count($room['images']) > 1): ?>
                    <div class="grid grid-cols-4 md:grid-cols-6 gap-2 p-4 bg-white">
                        <?php foreach ($room['images'] as $index => $image): ?>
                        <button type="button" 
                                onclick="changeImage('<?php echo htmlspecialchars($image['image_url']); ?>', '<?php echo htmlspecialchars(addslashes($image['image_title'] ?? $room['room_type_name'])); ?>', this)"
                                class="thumbnail-btn relative h-20 rounded-lg overflow-hidden border-2 <?php echo $index === 0 ? 'border-accent' : 'border-gray-300'; ?> hover:border-accent transition">
                            <img src="<?php echo htmlspecialchars($image['image_url']); ?>" 
                                 alt="Thumbnail" 
                                 class="w-full h-full object-cover">
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div class="bg-white">
                    <h2 class="text-2xl font-serif font-bold text-gray-900 mb-4">Giới thiệu</h2>
                    <p class="text-gray-700 leading-relaxed mb-4">
                        <?php echo nl2br(htmlspecialchars($room['long_description'] ?? $room['description'])); ?>
                    </p>
                </div>

                <!-- Room Features -->
                <?php if (!empty($room['features'])): ?>
                <div class="bg-white">
                    <h2 class="text-2xl font-serif font-bold text-gray-900 mb-4">Tiện nghi phòng</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($room['features'] as $feature): ?>
                        <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                            <i class="fas <?php echo $feature['icon']; ?> text-accent text-xl mr-3"></i>
                            <span class="text-gray-800"><?php echo htmlspecialchars($feature['feature_name']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Detailed Amenities -->
                <?php if ($room['amenities_description']): ?>
                <div class="bg-gray-50 p-6 rounded-lg">
                    <h2 class="text-2xl font-serif font-bold text-gray-900 mb-4">Tiện ích chi tiết</h2>
                    <div class="text-gray-700 leading-relaxed">
                        <?php 
                        $amenities = explode(' • ', $room['amenities_description']);
                        echo '<ul class="grid grid-cols-1 md:grid-cols-2 gap-3">';
                        foreach ($amenities as $amenity) {
                            echo '<li class="flex items-start"><i class="fas fa-check-circle text-accent mr-2 mt-1"></i><span>' . htmlspecialchars($amenity) . '</span></li>';
                        }
                        echo '</ul>';
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Policies -->
                <div class="bg-white border border-gray-200 p-6 rounded-lg">
                    <h2 class="text-2xl font-serif font-bold text-gray-900 mb-4">Chính sách phòng</h2>
                    <div class="space-y-4 text-gray-700">
                        <div class="flex items-start">
                            <i class="fas fa-clock text-accent mr-3 mt-1"></i>
                            <div>
                                <div class="font-semibold">Check-in / Check-out</div>
                                <div class="text-sm">Check-in: 14:00 | Check-out: 12:00</div>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-undo text-accent mr-3 mt-1"></i>
                            <div>
                                <div class="font-semibold">Chính sách hủy phòng</div>
                                <div class="text-sm">Miễn phí hủy trước 48 giờ check-in. Sau đó sẽ tính phí 1 đêm.</div>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-ban-smoking text-accent mr-3 mt-1"></i>
                            <div>
                                <div class="font-semibold">Chính sách hút thuốc</div>
                                <div class="text-sm">Phòng không hút thuốc (có khu vực hút thuốc riêng)</div>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-paw text-accent mr-3 mt-1"></i>
                            <div>
                                <div class="font-semibold">Thú cưng</div>
                                <div class="text-sm">Không cho phép mang thú cưng</div>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-child text-accent mr-3 mt-1"></i>
                            <div>
                                <div class="font-semibold">Trẻ em</div>
                                <div class="text-sm">Trẻ em dưới 6 tuổi được miễn phí khi ở chung giường với người lớn</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Booking Widget (1/3) -->
            <aside class="lg:col-span-1">
                <div class="sticky top-24 bg-white border-2 border-accent rounded-lg shadow-xl p-6">
                    <div class="text-center mb-6">
                        <div class="text-4xl font-bold text-accent mb-2">
                            <?php echo number_format($room['price_per_night'], 0, ',', '.'); ?> VNĐ
                        </div>
                        <div class="text-sm text-gray-600">mỗi đêm</div>
                    </div>

                    <!-- Booking Form -->
                    <form action="booking_step2.php" method="GET" id="bookingForm" class="space-y-4">
                        <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                        
                        <div>
                            <label for="checkin" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar-check text-accent mr-1"></i>
                                Ngày nhận phòng
                            </label>
                            <input type="date" 
                                   id="checkin" 
                                   name="checkin" 
                                   value="<?php echo $checkin; ?>" 
                                   min="<?php echo date('Y-m-d'); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent"
                                   required>
                        </div>

                        <div>
                            <label for="checkout" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar-times text-accent mr-1"></i>
                                Ngày trả phòng
                            </label>
                            <input type="date" 
                                   id="checkout" 
                                   name="checkout" 
                                   value="<?php echo $checkout; ?>" 
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent"
                                   required>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="adults" class="block text-sm font-semibold text-gray-700 mb-2">Người lớn</label>
                                <select id="adults" 
                                        name="adults" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent bg-white">
                                    <?php for($i = 1; $i <= $room['max_adults']; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php if($adults == $i) echo 'selected'; ?>>
                                            <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div>
                                <label for="children" class="block text-sm font-semibold text-gray-700 mb-2">Trẻ em</label>
                                <select id="children" 
                                        name="children" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent bg-white">
                                    <?php for($i = 0; $i <= $room['max_children']; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php if($children == $i) echo 'selected'; ?>>
                                            <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <!-- WIDGET STATUS DISPLAY -->
                        <?php if ($has_dates && $date_valid && empty($availability_error) && empty($date_error)): ?>
                            <!-- Case 1: Available -->
                            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span class="font-semibold">Còn <?php echo $available_rooms_count; ?> phòng trống</span> trong khoảng thời gian này.
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600"><?php echo number_format($room['price_per_night'], 0, ',', '.'); ?> VNĐ x <?php echo $num_nights; ?> đêm</span>
                                    <span class="font-semibold"><?php echo number_format($total_price, 0, ',', '.'); ?> VNĐ</span>
                                </div>
                                <div class="border-t pt-2 flex justify-between font-bold text-lg">
                                    <span>Tổng cộng (Chưa gồm phí)</span>
                                    <span class="text-accent"><?php echo number_format($total_price, 0, ',', '.'); ?> VNĐ</span>
                                </div>
                            </div>
                        <?php elseif (!empty($availability_error)): ?>
                            <!-- Case 2: Unavailable -->
                            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <?php echo $availability_error; ?>
                            </div>
                        <?php elseif (!empty($date_error)): ?>
                            <!-- Case 3: Date or Guest Error -->
                            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg text-sm">
                                <i class="fas fa-info-circle mr-2"></i>
                                <?php echo $date_error; ?>
                            </div>
                        <?php else: ?>
                             <!-- Case 4: Initial state (no dates selected) -->
                             <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg text-sm">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                Vui lòng chọn ngày để kiểm tra giá và tình trạng phòng.
                            </div>
                        <?php endif; ?>

                        <button type="submit" 
                                <?php if (!empty($date_error) || !empty($availability_error) || !$date_valid) echo 'disabled'; ?>
                                class="w-full bg-accent text-white px-6 py-4 rounded-lg font-semibold text-lg hover:bg-accent-dark transition <?php if (!empty($date_error) || !empty($availability_error) || !$date_valid) echo 'opacity-50 cursor-not-allowed'; ?>">
                            <i class="fas fa-calendar-check mr-2"></i>
                            <?php 
                            if (!empty($availability_error)) {
                                echo 'Đã hết phòng';
                            } elseif (!empty($date_error)) {
                                echo 'Không thể đặt';
                            } elseif (!$date_valid) {
                                echo 'Chọn ngày để đặt';
                            } else {
                                echo 'Tiếp tục đặt phòng';
                            }
                            ?>
                        </button>

                        <div class="text-center text-sm text-gray-600">
                            <i class="fas fa-shield-alt text-accent mr-1"></i>
                            Đặt phòng an toàn & bảo mật
                        </div>
                    </form>

                    <!-- Contact Info -->
                    <div class="mt-6 pt-6 border-t">
                        <h3 class="font-semibold text-gray-900 mb-3">Cần hỗ trợ?</h3>
                        <div class="space-y-2 text-sm text-gray-600">
                            <div class="flex items-center">
                                <i class="fas fa-phone text-accent mr-2"></i>
                                <span>0222-555-474 (24/7)</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-envelope text-accent mr-2"></i>
                                <span>booking@vinpearlcangio.com</span>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

        </div>

        <!-- Similar Rooms Section -->
        <div class="mt-16">
            <h2 class="text-3xl font-serif font-bold text-gray-900 mb-8">Các phòng tương tự</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php
                // Lấy 3 phòng khác tương tự về giá
                $stmt = $pdo->prepare("
                    SELECT r.id, r.room_type_name, r.price_per_night, r.image_url
                    FROM rooms r 
                    WHERE r.id != ? AND r.is_active = 1 
                    ORDER BY ABS(r.price_per_night - ?) ASC 
                    LIMIT 3
                ");
                $stmt->execute([$room_id, $room['price_per_night']]);
                $similar_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($similar_rooms as $similar):
                    $similar_url = 'room_detail.php?id=' . $similar['id'];
                    if ($has_dates && $date_valid) {
                        $similar_url .= '&checkin=' . urlencode($checkin) . '&checkout=' . urlencode($checkout) . '&adults=' . $adults . '&children=' . $children;
                    }
                    
                    // Xử lý ảnh similar room
                    $simImgUrl = $similar['image_url'] ?? '';
                    if ($simImgUrl && !preg_match("~^(?:f|ht)tps?://~i", $simImgUrl)) {
                        $simImgUrl = '../' . $simImgUrl;
                    }
                    if (empty($simImgUrl)) {
                        $simImgUrl = 'https://placehold.co/600x400/ddd/888?text=No+Image';
                    }
                ?>
                <a href="<?php echo $similar_url; ?>" class="group block bg-white border rounded-lg overflow-hidden hover:shadow-xl transition">
                    <div class="relative h-48">
                        <img src="<?php echo htmlspecialchars($simImgUrl); ?>" 
                             alt="<?php echo htmlspecialchars($similar['room_type_name']); ?>" 
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-lg text-gray-900 mb-2"><?php echo htmlspecialchars($similar['room_type_name']); ?></h3>
                        <div class="text-accent font-bold"><?php echo number_format($similar['price_per_night'], 0, ',', '.'); ?> VNĐ / đêm</div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</main>

<script>
function changeImage(url, title, element) {
    document.getElementById('mainImage').querySelector('img').src = url;
    document.getElementById('imageTitle').textContent = title;
    
    // Update active thumbnail
    document.querySelectorAll('.thumbnail-btn').forEach(btn => {
        btn.classList.remove('border-accent');
        btn.classList.add('border-gray-300');
    });
    element.classList.remove('border-gray-300');
    element.classList.add('border-accent');
}

// Validate dates trước khi submit
document.getElementById('bookingForm').addEventListener('submit', function(e) {
    const checkinVal = document.getElementById('checkin').value;
    const checkoutVal = document.getElementById('checkout').value;
    
    if (!checkinVal || !checkoutVal) {
        e.preventDefault();
        alert('Vui lòng chọn ngày check-in và check-out.');
        return false;
    }
    
    const checkinDate = new Date(checkinVal);
    const checkoutDate = new Date(checkoutVal);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (checkinDate < today) {
        e.preventDefault();
        alert('Ngày check-in không được là ngày trong quá khứ.');
        return false;
    }
    
    if (checkoutDate <= checkinDate) {
        e.preventDefault();
        alert('Ngày check-out phải sau ngày check-in ít nhất 1 ngày.');
        return false;
    }
});

// Auto-update checkout date khi checkin changes
document.getElementById('checkin').addEventListener('change', function() {
    const checkinDate = new Date(this.value);
    if (isNaN(checkinDate)) return;

    const checkoutDate = new Date(checkinDate);
    checkoutDate.setDate(checkoutDate.getDate() + 1);
    
    const checkoutInput = document.getElementById('checkout');
    const formattedDate = checkoutDate.toISOString().split('T')[0];
    
    if (!checkoutInput.value || new Date(checkoutInput.value) <= checkinDate) {
        checkoutInput.value = formattedDate;
    }
    checkoutInput.min = formattedDate;
});

// Check availability khi thay đổi ngày (debounced)
let checkTimeout;
const dateInputs = document.querySelectorAll('#checkin, #checkout, #adults, #children');
dateInputs.forEach(input => {
    input.addEventListener('change', function() {
        clearTimeout(checkTimeout);
        checkTimeout = setTimeout(() => {
            const checkin = document.getElementById('checkin').value;
            const checkout = document.getElementById('checkout').value;
            const adults = document.getElementById('adults').value;
            const children = document.getElementById('children').value;
            
            const checkinDate = new Date(checkin);
            const checkoutDate = new Date(checkout);

            if (checkin && checkout && checkoutDate > checkinDate) {
                const url = new URL(window.location.href);
                url.searchParams.set('checkin', checkin);
                url.searchParams.set('checkout', checkout);
                url.searchParams.set('adults', adults);
                url.searchParams.set('children', children);
                window.location.href = url.toString();
            }
        }, 800);
    });
});

// Khởi tạo giá trị min cho checkout khi tải trang
document.addEventListener('DOMContentLoaded', function() {
    const checkinInput = document.getElementById('checkin');
    if (checkinInput.value) {
        const checkinDate = new Date(checkinInput.value);
        if (!isNaN(checkinDate)) {
            const checkoutDate = new Date(checkinDate);
            checkoutDate.setDate(checkoutDate.getDate() + 1);
            document.getElementById('checkout').min = checkoutDate.toISOString().split('T')[0];
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>