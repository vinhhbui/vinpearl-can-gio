<?php

include __DIR__ . '/components/header.php';

// Lấy room_number_id từ URL (nếu có - khi click từ sơ đồ phòng)
$room_number_id = isset($_GET['room_number_id']) ? intval($_GET['room_number_id']) : 0;

// Lấy thông tin tìm kiếm từ URL
$checkin = isset($_GET['checkin']) ? htmlspecialchars($_GET['checkin']) : date('Y-m-d');
$checkout = isset($_GET['checkout']) ? htmlspecialchars($_GET['checkout']) : date('Y-m-d', strtotime('+1 day'));
$adults = isset($_GET['adults']) ? intval($_GET['adults']) : 1;
$children = isset($_GET['children']) ? intval($_GET['children']) : 0;
$room_type_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;

// Validate dates
$checkin_dt = new DateTime($checkin);
$checkout_dt = new DateTime($checkout);
if ($checkout_dt <= $checkin_dt) {
    $checkout = date('Y-m-d', strtotime($checkin . ' +1 day'));
}

// Nếu có room_number_id, lấy room_type_id từ đó
$selected_room_number = null;
if ($room_number_id > 0) {
    $stmt = $conn->prepare("
        SELECT rn.*, r.id as room_id, r.room_type_name, r.price_per_night, r.max_occupancy, 
               r.max_adults, r.max_children, r.image_url, r.description
        FROM room_numbers rn
        JOIN rooms r ON rn.room_id = r.id
        WHERE rn.id = ?
    ");
    $stmt->bind_param("i", $room_number_id);
    $stmt->execute();
    $selected_room_number = $stmt->get_result()->fetch_assoc();
    
    if ($selected_room_number) {
        $room_type_id = $selected_room_number['room_id'];
    }
    $stmt->close();
}

// Lấy danh sách loại phòng
$room_types = $conn->query("SELECT * FROM rooms WHERE is_active = 1 ORDER BY price_per_night ASC");
$room_types_array = [];
while ($r = $room_types->fetch_assoc()) {
    $room_types_array[] = $r;
}

// Lấy danh sách dịch vụ bổ sung (từ bảng standard_addons)
$services = $conn->query("SELECT id, name, price FROM standard_addons WHERE is_active = 1 ORDER BY display_order ASC");
$services_array = [];
while ($s = $services->fetch_assoc()) {
    $services_array[] = $s;
}

// Nếu đã chọn loại phòng, lấy thông tin chi tiết
$selected_room_type = null;
$available_rooms = [];
$num_nights = 0;
$total_price = 0;

if ($room_type_id > 0) {
    // Lấy thông tin loại phòng
    $stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $room_type_id);
    $stmt->execute();
    $selected_room_type = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($selected_room_type && $checkin && $checkout) {
        // Tính số đêm
        $checkin_date = new DateTime($checkin);
        $checkout_date = new DateTime($checkout);
        $interval = $checkin_date->diff($checkout_date);
        $num_nights = $interval->days;
        $total_price = $selected_room_type['price_per_night'] * $num_nights;
        
        // Lấy danh sách phòng trống - Kiểm tra theo stored procedure
        // Hoặc dùng raw SQL với logic kiểm tra late_checkout
        $sql = "
            SELECT rn.id, rn.room_number, rn.floor, rn.status
            FROM room_numbers rn
            WHERE rn.room_id = ?
            AND rn.status != 'maintenance'
            AND NOT EXISTS (
                SELECT 1 
                FROM bookings b
                WHERE b.room_number_id = rn.id
                AND b.booking_status NOT IN ('cancelled', 'no_show', 'checked_out')
                AND (
                    -- Xung đột ngày tiêu chuẩn
                    (b.checkin_date < ? AND b.checkout_date > ?)
                    OR
                    -- Xung đột do khách cũ trả trễ
                    (b.checkout_date = ? AND b.late_checkout = 1)
                )
            )
            ORDER BY rn.floor ASC, rn.room_number ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $room_type_id, $checkout, $checkin, $checkin);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $available_rooms[] = $row;
        }
        $stmt->close();
    }
}

// Xử lý tạo booking
$booking_created = false;
$booking_error = '';
$new_booking_id = null;
$booking_reference = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_booking') {
    $post_room_number_id = intval($_POST['room_number_id']);
    $post_room_id = intval($_POST['room_id']);
    $post_checkin = $_POST['checkin'];
    $post_checkout = $_POST['checkout'];
    $post_adults = intval($_POST['adults']);
    $post_children = intval($_POST['children']);
    $guest_name = trim($_POST['guest_name']);
    $guest_email = trim($_POST['guest_email']);
    $guest_phone = trim($_POST['guest_phone']);
    $guest_address = trim($_POST['guest_address'] ?? '');
    $special_requests = trim($_POST['special_requests'] ?? '');
    $total_amount = floatval($_POST['total_amount']);
    $late_checkout = isset($_POST['late_checkout']) ? 1 : 0;
    
    // Validate
    if (empty($guest_name) || empty($guest_phone)) {
        $booking_error = "Vui lòng nhập tên và số điện thoại khách hàng!";
    } elseif ($post_room_number_id <= 0 || $post_room_id <= 0) {
        $booking_error = "Vui lòng chọn phòng trước khi tạo booking!";
    } else {
        // Kiểm tra phòng có tồn tại và trống không - Dùng logic chặt chẽ
        $stmt = $conn->prepare("
            SELECT rn.id FROM room_numbers rn
            WHERE rn.id = ? 
            AND rn.room_id = ?
            AND rn.status != 'maintenance'
            AND NOT EXISTS (
                SELECT 1 FROM bookings b 
                WHERE b.room_number_id = rn.id
                AND b.booking_status NOT IN ('cancelled', 'no_show', 'checked_out')
                AND (
                    (b.checkin_date < ? AND b.checkout_date > ?)
                    OR
                    (b.checkout_date = ? AND b.late_checkout = 1)
                )
            )
        ");
        $stmt->bind_param("iisss", $post_room_number_id, $post_room_id, $post_checkout, $post_checkin, $post_checkin);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            $booking_error = "Phòng đã được đặt hoặc không tồn tại!";
        } else {
            $stmt->close();
            
            // Tính số đêm
            $ci_date = new DateTime($post_checkin);
            $co_date = new DateTime($post_checkout);
            $num_nights_cal = $ci_date->diff($co_date)->days;
            
            // Tạo mã booking
            $booking_reference = 'VPC' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Tính base_price từ giá phòng
            $stmt = $conn->prepare("SELECT price_per_night FROM rooms WHERE id = ?");
            $stmt->bind_param("i", $post_room_id);
            $stmt->execute();
            $room_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $base_price = $room_data['price_per_night'] * $num_nights_cal;
            
            // Tính các phí
            $tax_rate = 0.08; // Từ site_settings
            $service_fee_rate = 0.05;
            $addons_total = 0;
            $late_checkout_fee = 0;
            
            // Insert booking
            $stmt = $conn->prepare("
                INSERT INTO bookings (
                    booking_reference,
                    room_id,
                    room_number_id,
                    guest_name,
                    guest_email,
                    guest_phone,
                    checkin_date,
                    checkout_date,
                    num_nights,
                    num_adults,
                    num_children,
                    base_price,
                    addons_total,
                    tax_amount,
                    service_fee,
                    total_price,
                    special_requests,
                    booking_status,
                    payment_status,
                    late_checkout,
                    late_checkout_fee,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, NOW())
            ");
            
            // Tính toán phí
            $tax_amount = $base_price * $tax_rate;
            $service_fee = $base_price * $service_fee_rate;
            
            // Nếu muốn late checkout, thêm phí (giả sử 500k từ add-on)
            if ($late_checkout) {
                $late_checkout_fee = 500000;
                $addons_total += $late_checkout_fee;
            }
            
            $final_total = $base_price + $addons_total + $tax_amount + $service_fee;
            
            $stmt->bind_param(
                "siisssssiiidddddsii",
                $booking_reference,
                $post_room_id,
                $post_room_number_id,
                $guest_name,
                $guest_email,
                $guest_phone,
                $post_checkin,
                $post_checkout,
                $num_nights_cal,
                $post_adults,
                $post_children,
                $base_price,
                $addons_total,
                $tax_amount,
                $service_fee,
                $final_total,
                $special_requests,
                $late_checkout,
                $late_checkout_fee
            );
            
            if ($stmt->execute()) {
                $new_booking_id = $conn->insert_id;
                $stmt->close();
                
                // Insert dịch vụ bổ sung nếu có
                if (isset($_POST['addons']) && is_array($_POST['addons'])) {
                    $addon_stmt = $conn->prepare("
                        INSERT INTO booking_addons (booking_id, addon_type, addon_id, addon_name, addon_price, quantity, status)
                        SELECT ?, 'standard', id, name, price, 1, 'confirmed'
                        FROM standard_addons
                        WHERE id = ?
                    ");
                    
                    foreach ($_POST['addons'] as $addon_id) {
                        $addon_id = intval($addon_id);
                        $addon_stmt->bind_param("ii", $new_booking_id, $addon_id);
                        $addon_stmt->execute();
                    }
                    $addon_stmt->close();
                }
                
                $booking_created = true;
            } else {
                $booking_error = "Lỗi tạo booking: " . $conn->error;
            }
        }
    }
}
?>

<div class="d-flex">
    <?php include __DIR__ . '/components/sidebar.php'; ?>
    
    <div class="main-content flex-grow-1 p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-plus-circle"></i> Tạo Booking mới</h2>
                <p class="text-muted mb-0">Đặt phòng cho khách hàng</p>
            </div>
            <a href="rooms.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Quay lại sơ đồ
            </a>
        </div>

        <?php if ($booking_created): ?>
            <!-- BOOKING SUCCESS -->
            <div class="card border-success">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                    </div>
                    <h3 class="text-success mb-3">Đặt phòng thành công!</h3>
                    <p class="lead mb-4">
                        Mã booking: <strong class="text-primary"><?php echo htmlspecialchars($booking_reference); ?></strong>
                    </p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="booking-detail.php?id=<?php echo $new_booking_id; ?>" class="btn btn-primary btn-lg">
                            <i class="fas fa-eye"></i> Xem chi tiết
                        </a>
                        <a href="booking-create.php" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-plus"></i> Tạo booking khác
                        </a>
                        <a href="rooms.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-th"></i> Về sơ đồ phòng
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>

            <?php if ($booking_error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($booking_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Left: Search & Room Selection -->
                <div class="col-lg-8">
                    
                    <!-- Step 1: Chọn ngày & loại phòng -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-search"></i> Bước 1: Chọn ngày & loại phòng</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <?php if ($room_number_id): ?>
                                    <input type="hidden" name="room_number_id" value="<?php echo $room_number_id; ?>">
                                <?php endif; ?>
                                
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Ngày nhận phòng</label>
                                    <input type="date" name="checkin" class="form-control" 
                                           value="<?php echo htmlspecialchars($checkin); ?>" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Ngày trả phòng</label>
                                    <input type="date" name="checkout" class="form-control" 
                                           value="<?php echo htmlspecialchars($checkout); ?>" 
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold">Người lớn</label>
                                    <select name="adults" class="form-select">
                                        <?php for($i = 1; $i <= 6; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $adults == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold">Trẻ em</label>
                                    <select name="children" class="form-select">
                                        <?php for($i = 0; $i <= 4; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $children == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> Tìm
                                    </button>
                                </div>
                                
                                <?php if (!$room_number_id): ?>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Loại phòng</label>
                                    <div class="row g-2">
                                        <?php foreach ($room_types_array as $rt): ?>
                                            <div class="col-md-4">
                                                <div class="form-check card p-3 <?php echo $room_type_id == $rt['id'] ? 'border-primary bg-light' : ''; ?> cursor-pointer">
                                                    <input class="form-check-input" type="radio" name="room_id" 
                                                           id="room_type_<?php echo $rt['id']; ?>" 
                                                           value="<?php echo $rt['id']; ?>"
                                                           <?php echo $room_type_id == $rt['id'] ? 'checked' : ''; ?>
                                                           onchange="this.form.submit()">
                                                    <label class="form-check-label w-100 cursor-pointer" for="room_type_<?php echo $rt['id']; ?>">
                                                        <strong><?php echo htmlspecialchars($rt['room_type_name']); ?></strong>
                                                        <br>
                                                        <span class="text-primary fw-bold"><?php echo number_format($rt['price_per_night'], 0, ',', '.'); ?> VNĐ</span>/đêm
                                                        <br>
                                                        <small class="text-muted">Max: <?php echo $rt['max_occupancy']; ?> khách</small>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <?php if ($selected_room_type || $selected_room_number): ?>
                    <!-- Step 2: Chọn phòng cụ thể -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-door-open"></i> Bước 2: Chọn phòng 
                                <span class="badge bg-light text-dark float-end">
                                    <?php echo count($available_rooms); ?> phòng trống
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($selected_room_number): ?>
                                <!-- Đã chọn sẵn phòng từ sơ đồ -->
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    Phòng đã được chọn sẵn: <strong><?php echo htmlspecialchars($selected_room_number['room_number']); ?></strong> 
                                    (<?php echo htmlspecialchars($selected_room_number['room_type_name']); ?> - Tầng <?php echo $selected_room_number['floor']; ?>)
                                </div>
                                <input type="hidden" id="selected_room_id" value="<?php echo $room_number_id; ?>">
                                <input type="hidden" id="selected_room_number" value="<?php echo htmlspecialchars($selected_room_number['room_number']); ?>">
                            <?php elseif (count($available_rooms) > 0): ?>
                                <p class="mb-3">Chọn phòng từ danh sách phòng trống:</p>
                                <div class="row g-2">
                                    <?php foreach ($available_rooms as $ar): ?>
                                        <div class="col-md-3 col-sm-4 col-6">
                                            <button type="button" 
                                                    class="btn btn-outline-success w-100 room-select-btn"
                                                    data-room-id="<?php echo $ar['id']; ?>"
                                                    data-room-number="<?php echo htmlspecialchars($ar['room_number']); ?>"
                                                    data-floor="<?php echo $ar['floor']; ?>">
                                                <i class="fas fa-door-open"></i> 
                                                <strong><?php echo htmlspecialchars($ar['room_number']); ?></strong>
                                                <br>
                                                <small class="text-muted">T<?php echo $ar['floor']; ?></small>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" id="selected_room_id" value="">
                                <input type="hidden" id="selected_room_number" value="">
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    Không có phòng trống trong khoảng thời gian này. Vui lòng chọn ngày khác.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Step 3: Thông tin khách hàng -->
                    <div class="card mb-4" id="guestInfoCard" style="<?php echo ($selected_room_number || count($available_rooms) > 0) ? '' : 'display:none;'; ?>">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-user"></i> Bước 3: Thông tin khách & dịch vụ</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="bookingForm">
                                <input type="hidden" name="action" value="create_booking">
                                <input type="hidden" name="room_id" id="form_room_id" value="<?php echo $room_type_id; ?>">
                                <input type="hidden" name="room_number_id" id="form_room_number_id" value="<?php echo $room_number_id; ?>">
                                <input type="hidden" name="checkin" value="<?php echo htmlspecialchars($checkin); ?>">
                                <input type="hidden" name="checkout" value="<?php echo htmlspecialchars($checkout); ?>">
                                <input type="hidden" name="adults" value="<?php echo $adults; ?>">
                                <input type="hidden" name="children" value="<?php echo $children; ?>">
                                <input type="hidden" name="total_amount" id="total_amount_input" value="<?php echo $total_price; ?>">
                                
                                <!-- Thông tin khách hàng -->
                                <h6 class="fw-bold mb-3">👤 Thông tin khách hàng</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Họ tên khách <span class="text-danger">*</span></label>
                                        <input type="text" name="guest_name" class="form-control" required placeholder="Nguyễn Văn A">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Số điện thoại <span class="text-danger">*</span></label>
                                        <input type="tel" name="guest_phone" class="form-control" required placeholder="0901234567">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Email</label>
                                        <input type="email" name="guest_email" class="form-control" placeholder="email@example.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Địa chỉ</label>
                                        <input type="text" name="guest_address" class="form-control" placeholder="Địa chỉ khách hàng">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Yêu cầu đặc biệt</label>
                                        <textarea name="special_requests" class="form-control" rows="3" placeholder="Ghi chú thêm (phòng yên tĩnh, tầng cao, v.v.)"></textarea>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <!-- Lựa chọn phòng -->
                                <h6 class="fw-bold mb-3">🏨 Dịch vụ bổ sung</h6>
                                
                                <!-- Late Checkout -->
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="late_checkout" 
                                                   name="late_checkout" value="1" data-price="500000">
                                            <label class="form-check-label fw-bold" for="late_checkout">
                                                🕐 Trả phòng muộn (Late Checkout) - 16:00
                                                <br>
                                                <small class="text-muted">Thêm <span class="late-checkout-price">500.000</span> VNĐ</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Các dịch vụ bổ sung khác -->
                                <?php if (count($services_array) > 0): ?>
                                <div class="mb-4">
                                    <label class="form-label fw-bold mb-3">Dịch vụ bổ sung khác:</label>
                                    <div class="row g-2">
                                        <?php foreach ($services_array as $service): ?>
                                            <div class="col-md-6">
                                                <div class="form-check card p-3">
                                                    <input class="form-check-input addon-checkbox" type="checkbox" 
                                                           name="addons[]" 
                                                           id="addon_<?php echo $service['id']; ?>"
                                                           value="<?php echo $service['id']; ?>"
                                                           data-price="<?php echo $service['price']; ?>"
                                                           data-name="<?php echo htmlspecialchars($service['name']); ?>">
                                                    <label class="form-check-label w-100" for="addon_<?php echo $service['id']; ?>">
                                                        <strong><?php echo htmlspecialchars($service['name']); ?></strong>
                                                        <br>
                                                        <small class="text-primary fw-bold"><?php echo number_format($service['price'], 0, ',', '.'); ?> VNĐ</small>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right: Booking Summary -->
                <div class="col-lg-4">
                    <div class="card sticky-top" style="top: 80px;">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-receipt"></i> Tóm tắt đặt phòng</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($selected_room_type || $selected_room_number): 
                                $display_room = $selected_room_type ?? [
                                    'room_type_name' => $selected_room_number['room_type_name'],
                                    'price_per_night' => $selected_room_number['price_per_night'],
                                    'image_url' => $selected_room_number['image_url']
                                ];
                            ?>
                                <!-- Room Image -->
                                <?php if (!empty($display_room['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($display_room['image_url']); ?>" 
                                     alt="Room" class="img-fluid rounded mb-3">
                                <?php endif; ?>
                                
                                <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($display_room['room_type_name']); ?></h5>
                                
                                <?php if ($selected_room_number): ?>
                                    <p class="mb-3 badge bg-success">
                                        <i class="fas fa-door-open"></i> 
                                        Phòng: <strong><?php echo htmlspecialchars($selected_room_number['room_number']); ?></strong>
                                    </p>
                                <?php else: ?>
                                    <p class="mb-3" id="displayRoomRow" style="display: none;">
                                        <i class="fas fa-door-open text-success"></i> 
                                        Phòng: <strong id="displayRoomNumber">---</strong>
                                    </p>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between mb-2 small">
                                    <span><i class="fas fa-calendar-check text-primary"></i> Nhận:</span>
                                    <strong><?php echo date('d/m/Y', strtotime($checkin)); ?> (14:00)</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2 small">
                                    <span><i class="fas fa-calendar-times text-danger"></i> Trả:</span>
                                    <strong><?php echo date('d/m/Y', strtotime($checkout)); ?> (12:00)</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-3 small">
                                    <span><i class="fas fa-moon text-info"></i> Số đêm:</span>
                                    <strong id="displayNights"><?php echo $num_nights; ?></strong>
                                </div>
                                
                                <hr>
                                
                                <!-- Chi tiết giá -->
                                <div class="small">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><?php echo number_format($display_room['price_per_night'], 0, ',', '.'); ?> VNĐ/đêm × <span id="dispNights"><?php echo $num_nights; ?></span></span>
                                        <span id="basePriceDisplay"><?php echo number_format($total_price, 0, ',', '.'); ?> VNĐ</span>
                                    </div>
                                </div>

                                <!-- Dịch vụ -->
                                <div id="servicesSummary" class="small"></div>
                                
                                <hr>
                                
                                <!-- Phí & Thuế -->
                                <div class="small text-muted">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Thuế VNĐ (8%):</span>
                                        <span id="taxDisplay">0 VNĐ</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Phí dịch vụ (5%):</span>
                                        <span id="feeDisplay">0 VNĐ</span>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between fw-bold fs-5 text-primary">
                                    <span>Tổng cộng:</span>
                                    <span id="finalTotal"><?php echo number_format($total_price, 0, ',', '.'); ?> VNĐ</span>
                                </div>
                                
                                <button type="submit" form="bookingForm" class="btn btn-success btn-lg w-100 mt-4" id="btnCreateBooking"
                                        <?php echo ($selected_room_number || count($available_rooms) > 0) ? '' : 'disabled'; ?>>
                                    <i class="fas fa-check-circle"></i> Tạo Booking
                                </button>
                                
                            <?php else: ?>
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-info-circle fa-3x mb-3 opacity-50"></i>
                                    <p>Vui lòng chọn ngày và loại phòng.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<style>
.room-select-btn {
    transition: all 0.2s;
    border-width: 2px;
}
.room-select-btn:hover {
    transform: translateY(-2px);
}
.room-select-btn.active {
    background-color: #198754 !important;
    color: white !important;
    border-color: #198754 !important;
    font-weight: bold;
}
.cursor-pointer {
    cursor: pointer;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const basePrice = <?php echo $total_price; ?>;
    const numNights = <?php echo $num_nights; ?>;
    
    // Chọn phòng
    document.querySelectorAll('.room-select-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.room-select-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const roomId = this.dataset.roomId;
            const roomNumber = this.dataset.roomNumber;
            const floor = this.dataset.floor;
            
            document.getElementById('selected_room_id').value = roomId;
            document.getElementById('selected_room_number').value = roomNumber;
            document.getElementById('form_room_number_id').value = roomId;
            
            const displayRow = document.getElementById('displayRoomRow');
            if (displayRow) displayRow.style.display = 'block';
            document.getElementById('displayRoomNumber').textContent = roomNumber + ' (T' + floor + ')';
            
            document.getElementById('guestInfoCard').style.display = 'block';
            document.getElementById('btnCreateBooking').disabled = false;
        });
    });
    
    // Xử lý dịch vụ bổ sung
    document.getElementById('late_checkout')?.addEventListener('change', updateTotal);
    
    document.querySelectorAll('.addon-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', updateTotal);
    });
    
    function updateTotal() {
        let addonsTotal = 0;
        const servicesSummary = document.getElementById('servicesSummary');
        servicesSummary.innerHTML = '';
        
        // Kiểm tra Late Checkout
        const lateCheckoutCbx = document.getElementById('late_checkout');
        if (lateCheckoutCbx && lateCheckoutCbx.checked) {
            addonsTotal += 500000;
            servicesSummary.innerHTML += `
                <div class="d-flex justify-content-between mb-1 text-success fw-bold">
                    <span>🕐 Trả phòng muộn</span>
                    <span>+ 500.000 VNĐ</span>
                </div>
            `;
        }
        
        // Các add-on khác
        document.querySelectorAll('.addon-checkbox:checked').forEach(function(checkbox) {
            const price = parseFloat(checkbox.dataset.price);
            const name = checkbox.dataset.name;
            addonsTotal += price;
            
            servicesSummary.innerHTML += `
                <div class="d-flex justify-content-between mb-1">
                    <span>${name}</span>
                    <span>+ ${new Intl.NumberFormat('vi-VN').format(price)} VNĐ</span>
                </div>
            `;
        });
        
        // Tính phí
        const taxRate = 0.08;
        const feeRate = 0.05;
        const taxAmount = basePrice * taxRate;
        const feeAmount = basePrice * feeRate;
        const finalTotal = basePrice + addonsTotal + taxAmount + feeAmount;
        
        document.getElementById('basePriceDisplay').textContent = new Intl.NumberFormat('vi-VN').format(basePrice) + ' VNĐ';
        document.getElementById('taxDisplay').textContent = new Intl.NumberFormat('vi-VN').format(taxAmount) + ' VNĐ';
        document.getElementById('feeDisplay').textContent = new Intl.NumberFormat('vi-VN').format(feeAmount) + ' VNĐ';
        document.getElementById('finalTotal').textContent = new Intl.NumberFormat('vi-VN').format(finalTotal) + ' VNĐ';
        document.getElementById('total_amount_input').value = finalTotal;
    }
    
    // Validate form
    document.getElementById('bookingForm')?.addEventListener('submit', function(e) {
        const roomId = document.getElementById('form_room_number_id').value;
        if (!roomId) {
            e.preventDefault();
            alert('Vui lòng chọn phòng trước khi tạo booking!');
            return false;
        }
    });
});
</script>

<?php include __DIR__ . '/components/footer.php'; ?>