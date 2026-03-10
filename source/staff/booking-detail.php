<?php
include __DIR__ . '/components/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// 1. Xử lý Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // THÊM CHECK-OUT MUỘN
    if (isset($_POST['action']) && $_POST['action'] === 'add_late_checkout') {
        $late_checkout_fee = (float)$_POST['late_checkout_fee'];
        
        // Kiểm tra xem đã có late_checkout chưa
        $check_existing = $conn->query("SELECT late_checkout, total_price FROM bookings WHERE id = $id")->fetch_assoc();
        
        if ($check_existing['late_checkout'] == 1) {
            $error = "Khách đã chọn check-out muộn rồi!";
        } else {
            $stmt = $conn->prepare("
                UPDATE bookings 
                SET late_checkout = 1, 
                    late_checkout_fee = ?,
                    total_price = total_price + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("ddi", $late_checkout_fee, $late_checkout_fee, $id);
            
            if ($stmt->execute()) {
                // TẠO DỊCH VỤ BỔ SUNG ĐỂ THANH TOÁN
                $addon_name = "Check-out Muộn";
                $stmt_addon = $conn->prepare("
                    INSERT INTO booking_addons (booking_id, addon_id, addon_name, addon_price, quantity, status, created_at) 
                    VALUES (?, -1, ?, ?, 1, 'pending', NOW())
                ");
                $stmt_addon->bind_param("isd", $id, $addon_name, $late_checkout_fee);
                $stmt_addon->execute();

                $new_total = $check_existing['total_price'] + $late_checkout_fee;
                $message = "✓ Đã thêm dịch vụ Check-out Muộn thành công!<br>" .
                          "<strong>Thời gian:</strong> Từ 12:00 → 16:00<br>" .
                          "<strong>Phí:</strong> " . number_format($late_checkout_fee) . " VNĐ<br>" .
                          "<strong>Tổng tiền cũ:</strong> " . number_format($check_existing['total_price']) . " VNĐ<br>" .
                          "<strong>Tổng tiền mới:</strong> " . number_format($new_total) . " VNĐ";
            } else {
                $error = "Lỗi cập nhật: " . $conn->error;
            }
        }
    }

    // HỦY CHECK-OUT MUỘN
    if (isset($_POST['action']) && $_POST['action'] === 'remove_late_checkout') {
        $check_existing = $conn->query("SELECT late_checkout, late_checkout_fee, total_price FROM bookings WHERE id = $id")->fetch_assoc();
        
        if ($check_existing['late_checkout'] == 0) {
            $error = "Khách chưa chọn check-out muộn!";
        } else {
            $refund_fee = $check_existing['late_checkout_fee'];
            
            $stmt = $conn->prepare("
                UPDATE bookings 
                SET late_checkout = 0, 
                    late_checkout_fee = 0,
                    total_price = total_price - ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("di", $refund_fee, $id);
            
            if ($stmt->execute()) {
                // HỦY DỊCH VỤ BỔ SUNG TƯƠNG ỨNG
                $conn->query("
                    UPDATE booking_addons 
                    SET status = 'cancelled', 
                        staff_notes = CONCAT(IFNULL(staff_notes, ''), '\n[System] Hủy Check-out muộn') 
                    WHERE booking_id = $id 
                    AND addon_name = 'Check-out Muộn' 
                    AND status != 'cancelled'
                ");

                $new_total = $check_existing['total_price'] - $refund_fee;
                $message = "✓ Đã hủy dịch vụ Check-out Muộn<br>" .
                          "<strong>Hoàn tiền:</strong> " . number_format($refund_fee) . " VNĐ<br>" .
                          "<strong>Tổng tiền mới:</strong> " . number_format($new_total) . " VNĐ";
            } else {
                $error = "Lỗi cập nhật: " . $conn->error;
            }
        }
    }

    // CẬP NHẬT PHÍ CHECK-OUT MUỘN
    if (isset($_POST['action']) && $_POST['action'] === 'update_late_checkout') {
        $new_fee = (float)$_POST['late_checkout_fee'];
        $check_existing = $conn->query("SELECT late_checkout, late_checkout_fee, total_price FROM bookings WHERE id = $id")->fetch_assoc();
        
        if ($check_existing['late_checkout'] == 0) {
            $error = "Khách chưa chọn check-out muộn!";
        } else {
            $old_fee = $check_existing['late_checkout_fee'];
            
            if ($new_fee != $old_fee) {
                $diff = $new_fee - $old_fee;
                
                $stmt = $conn->prepare("
                    UPDATE bookings 
                    SET late_checkout_fee = ?,
                        total_price = total_price + ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("ddi", $new_fee, $diff, $id);
                
                if ($stmt->execute()) {
                    // CẬP NHẬT GIÁ TRONG DỊCH VỤ BỔ SUNG
                    $stmt_addon = $conn->prepare("
                        UPDATE booking_addons 
                        SET addon_price = ? 
                        WHERE booking_id = $id 
                        AND addon_name = 'Check-out Muộn' 
                        AND status != 'cancelled'
                    ");
                    $stmt_addon->bind_param("di", $new_fee, $id);
                    $stmt_addon->execute();

                    $new_total = $check_existing['total_price'] + $diff;
                    $message = "✓ Đã cập nhật phí Check-out Muộn!<br>" .
                              "<strong>Phí cũ:</strong> " . number_format($old_fee) . " VNĐ<br>" .
                              "<strong>Phí mới:</strong> " . number_format($new_fee) . " VNĐ<br>" .
                              "<strong>Tổng tiền mới:</strong> " . number_format($new_total) . " VNĐ";
                    
                    $booking['late_checkout_fee'] = $new_fee;
                    $booking['total_price'] = $new_total;
                } else {
                    $error = "Lỗi cập nhật: " . $conn->error;
                }
            }
        }
    }

    // CẬP NHẬT TRẠNG THÁI DỊCH VỤ (Thay thế cho pay_addon_counter)
    if (isset($_POST['action']) && $_POST['action'] === 'update_addon_status') {
        $addon_id = (int)$_POST['addon_id'];
        $new_status = $_POST['new_status']; // confirmed hoặc cancelled
        $staff_name = $_SESSION['full_name'] ?? 'Nhân viên';
        
        if ($new_status === 'confirmed') {
            // Nếu chọn Đã thanh toán -> Xử lý như cũ
            $payment_method = $_POST['payment_method'] ?? 'cash';
            $payment_labels = [
                'cash' => 'Tiền mặt',
                'card' => 'Thẻ ngân hàng',
                'momo' => 'MoMo',
                'vnpay' => 'VNPay'
            ];
            $payment_label = $payment_labels[$payment_method] ?? $payment_method;
            $note_content = "Thanh toán tại quầy - $payment_label - NV: $staff_name";
        } else {
            // Nếu chọn Hủy -> Không cần phương thức thanh toán
            $note_content = "Hủy bởi NV: $staff_name";
        }
        
        $stmt = $conn->prepare("
            UPDATE booking_addons 
            SET status = ?, 
                staff_notes = CONCAT(IFNULL(staff_notes, ''), '\n[', NOW(), '] ', ?)
            WHERE id = ? AND booking_id = ?
        ");
        $stmt->bind_param("ssii", $new_status, $note_content, $addon_id, $id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Đã cập nhật trạng thái dịch vụ thành công!";
        } else {
            $error = "Không thể cập nhật. Dịch vụ có thể đã được xử lý.";
        }
    }
    
    // THANH TOÁN TẤT CẢ DỊCH VỤ PENDING
    if (isset($_POST['action']) && $_POST['action'] === 'pay_all_addons') {
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $staff_name = $_SESSION['full_name'] ?? 'Nhân viên';
        
        $payment_labels = [
            'cash' => 'Tiền mặt',
            'card' => 'Thẻ ngân hàng',
            'momo' => 'MoMo',
            'vnpay' => 'VNPay'
        ];
        $payment_label = $payment_labels[$payment_method] ?? $payment_method;
        
        $stmt = $conn->prepare("
            UPDATE booking_addons 
            SET status = 'confirmed', 
                staff_notes = CONCAT(IFNULL(staff_notes, ''), '\n[', NOW(), '] Thanh toán tại quầy - ', ?, ' - NV: ', ?)
            WHERE booking_id = ? AND addon_id = -1 AND status = 'pending'
        ");
        $stmt->bind_param("ssi", $payment_label, $staff_name, $id);
        
        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            if ($affected > 0) {
                $message = "Đã thanh toán $affected dịch vụ thành công! (Phương thức: $payment_label)";
            } else {
                $error = "Không có dịch vụ nào cần thanh toán.";
            }
        } else {
            $error = "Có lỗi xảy ra khi thanh toán.";
        }
    }
    
    // A. Cập nhật trạng thái (Check-in/Check-out/Cancel)
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $new_status = $_POST['status'];
        
        // Validate: Nếu check-in thì bắt buộc phải có số phòng
        if ($new_status === 'checked_in') {
            $check_room = $conn->query("SELECT room_number_id FROM bookings WHERE id = $id")->fetch_assoc();
            if (empty($check_room['room_number_id'])) {
                $error = "Không thể Check-in: Vui lòng phân công số phòng trước!";
            } else {
                // Cập nhật trạng thái VÀ thời gian check-in thực tế
                $stmt = $conn->prepare("UPDATE bookings SET booking_status = ?, checkin_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $new_status, $id);
                if ($stmt->execute()) $message = "Đã check-in thành công!";
            }
        } elseif ($new_status === 'checked_out') {
            // Cập nhật trạng thái VÀ thời gian check-out thực tế
            $stmt = $conn->prepare("UPDATE bookings SET booking_status = ?, checkout_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $new_status, $id);
            
            if ($stmt->execute()) {
                // LOGIC MỚI: Khi check-out, chuyển phòng sang trạng thái 'dirty' (Cần dọn)
                $check_room = $conn->query("SELECT room_number_id FROM bookings WHERE id = $id")->fetch_assoc();
                if (!empty($check_room['room_number_id'])) {
                    $r_id = $check_room['room_number_id'];
                    $conn->query("UPDATE room_numbers SET status = 'dirty' WHERE id = $r_id");
                }
                $message = "Đã check-out thành công! Phòng đã chuyển sang trạng thái Cần dọn.";
            }
        } else {
            // Các trạng thái khác
            $stmt = $conn->prepare("UPDATE bookings SET booking_status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $id);
            if ($stmt->execute()) $message = "Cập nhật trạng thái thành công!";
        }
    }

    // B. Cập nhật Phân công phòng & Loại phòng
    if (isset($_POST['action']) && $_POST['action'] === 'assign_room') {
        $new_room_id = (int)$_POST['room_id'];
        $room_number_id = !empty($_POST['room_number_id']) ? (int)$_POST['room_number_id'] : NULL;
        
        // Lấy thông tin booking hiện tại và giá phòng
        $current_booking = $conn->query("
            SELECT b.room_id, b.total_price, b.num_nights, r.price_per_night as current_price, r.room_type_name 
            FROM bookings b 
            JOIN rooms r ON b.room_id = r.id 
            WHERE b.id = $id
        ")->fetch_assoc();
        
        // Lấy giá phòng mới VÀ tên phòng mới
        $new_room_info = $conn->query("
            SELECT price_per_night, room_type_name FROM rooms WHERE id = $new_room_id
        ")->fetch_assoc();
        
        // Kiểm tra giá phòng
        if ($new_room_id != $current_booking['room_id']) {
            // Đang đổi loại phòng
            if ($new_room_info['price_per_night'] < $current_booking['current_price']) {
                // LOGIC MỚI: Cho phép chuyển xuống phòng giá thấp hơn (Downgrade / Hủy nâng cấp)
                
                $sql = "UPDATE bookings SET room_id = ?, room_number_id = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $new_room_id, $room_number_id, $id);
                
                if ($stmt->execute()) {
                    // Tính số tiền chênh lệch để giảm trừ
                    $price_diff_per_night = $current_booking['current_price'] - $new_room_info['price_per_night'];
                    $refund_amount = $price_diff_per_night * $current_booking['num_nights'];
                    
                    // Cập nhật tổng tiền
                    $new_total = $current_booking['total_price'] - $refund_amount;
                    $conn->query("UPDATE bookings SET total_price = $new_total WHERE id = $id");

                    // 1. Tự động HỦY các phiếu thu "Nâng cấp phòng" đang chờ (Pending) nếu có
                    $conn->query("
                        UPDATE booking_addons 
                        SET status = 'cancelled', 
                            staff_notes = CONCAT(IFNULL(staff_notes, ''), '\n[System] Hủy tự động do chuyển về phòng giá thấp hơn') 
                        WHERE booking_id = $id 
                        AND addon_id = -1 
                        AND status = 'pending' 
                        AND addon_name LIKE 'Nâng cấp hạng phòng%'
                    ");

                    // 2. Nếu không có phiếu pending (tức là đã thanh toán rồi hoặc chưa tạo), 
                    // tạo một record "Hoàn tiền chênh lệch" để ghi nhận
                    if ($conn->affected_rows == 0) {
                         $addon_name = "Hoàn tiền chênh lệch phòng (" . $current_booking['room_type_name'] . " ➝ " . $new_room_info['room_type_name'] . ")";
                         // Lưu ý: Giá trị âm để thể hiện hoàn tiền hoặc ghi chú
                         // Tuy nhiên, hệ thống addon thường tính tổng dương. 
                         // Ở đây ta chỉ ghi log hoặc tạo addon với status 'completed' và note là hoàn tiền.
                         // Cách đơn giản nhất: Trừ trực tiếp vào total_price (đã làm ở trên) và thông báo.
                    }

                    $message = "Đã chuyển đổi sang phòng giá thấp hơn thành công!<br>" .
                               "Đã giảm trừ: <strong>" . number_format($refund_amount) . " VNĐ</strong> khỏi tổng tiền.<br>" .
                               "Các khoản phí nâng cấp chưa thanh toán (nếu có) đã được hủy.";
                } else {
                    $error = "Lỗi cập nhật: " . $conn->error;
                }
            } else {
                // Cho phép chuyển phòng (UPGRADE hoặc NGANG GIÁ)
                $sql = "UPDATE bookings SET room_id = ?, room_number_id = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $new_room_id, $room_number_id, $id);
                
                if ($stmt->execute()) {
                    // TÍNH PHỤ THU: Chênh lệch giá × số đêm
                    $price_diff_per_night = $new_room_info['price_per_night'] - $current_booking['current_price'];
                    $upgrade_fee = $price_diff_per_night * $current_booking['num_nights'];
                    
                    if ($upgrade_fee > 0) {
                        // 1. TẠO DỊCH VỤ BỔ SUNG (ADDON) ĐỂ THANH TOÁN
                        $addon_name = "Nâng cấp hạng phòng (" . $current_booking['room_type_name'] . " ➝ " . $new_room_info['room_type_name'] . ")";
                        
                        $stmt_addon = $conn->prepare("
                            INSERT INTO booking_addons (booking_id, addon_id, addon_name, addon_price, quantity, status, created_at) 
                            VALUES (?, -1, ?, ?, ?, 'pending', NOW())
                        ");
                        // Giá là chênh lệch 1 đêm, số lượng là số đêm
                        $stmt_addon->bind_param("isdi", $id, $addon_name, $price_diff_per_night, $current_booking['num_nights']);
                        $stmt_addon->execute();

                        // 2. CẬP NHẬT TỔNG TIỀN BOOKING
                        $new_total = $current_booking['total_price'] + $upgrade_fee;
                        $conn->query("UPDATE bookings SET total_price = $new_total WHERE id = $id");

                        $message = "Đã nâng cấp phòng thành công!<br>" .
                                   "Đã tạo phiếu thu dịch vụ: <strong>" . number_format($upgrade_fee) . " VNĐ</strong><br>" .
                                   "Vui lòng kiểm tra mục <strong>Dịch vụ bổ sung</strong> để thanh toán.";
                    } else {
                        $message = "Đã cập nhật phòng thành công! (Cùng mức giá)";
                    }
                } else {
                    $error = "Lỗi cập nhật: " . $conn->error;
                }
            }
        } else {
            // Chỉ đổi số phòng trong cùng loại phòng (không thay đổi giá)
            $sql = "UPDATE bookings SET room_id = ?, room_number_id = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $new_room_id, $room_number_id, $id);
            
            if ($stmt->execute()) {
                $message = "Đã cập nhật số phòng!";
            } else {
                $error = "Lỗi cập nhật: " . $conn->error;
            }
        }
    }
}

// 2. Lấy thông tin Booking hiện tại
$query = "
    SELECT b.*, r.room_type_name, r.price_per_night as current_room_price, rn.room_number, u.full_name as user_account
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    LEFT JOIN room_numbers rn ON b.room_number_id = rn.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.id = $id
";
$booking = $conn->query($query)->fetch_assoc();

if (!$booking) {
    echo "<div class='alert alert-danger'>Không tìm thấy đơn đặt phòng!</div>";
    include __DIR__ . '/components/footer.php';
    exit;
}

// 3. Lấy danh sách Loại phòng (LẤY TẤT CẢ ĐỂ CÓ THỂ DOWNGRADE)
$current_price = $booking['current_room_price'];
$room_types = $conn->query("
    SELECT *, 
           CASE WHEN price_per_night > $current_price THEN price_per_night - $current_price ELSE 0 END as price_diff,
           CASE WHEN price_per_night < $current_price THEN $current_price - price_per_night ELSE 0 END as refund_diff
    FROM rooms 
    WHERE is_active = 1 
    ORDER BY price_per_night ASC
");

// 4. Lấy danh sách Phòng vật lý TRỐNG (để phân công)
$current_room_id = $booking['room_id'];
$checkin = $booking['checkin_date'];
$checkout = $booking['checkout_date'];

$available_rooms_query = "
    SELECT rn.id, rn.room_number, rn.floor
    FROM room_numbers rn
    WHERE rn.room_id = $current_room_id
    AND rn.status = 'available'
    AND rn.id NOT IN (
        SELECT b.room_number_id 
        FROM bookings b 
        WHERE b.room_number_id IS NOT NULL 
        AND b.id != $id
        AND b.booking_status NOT IN ('cancelled', 'checked_out')
        AND (b.checkin_date < '$checkout' AND b.checkout_date > '$checkin')
    )
    ORDER BY rn.room_number ASC
";
$available_rooms = $conn->query($available_rooms_query);

// 5. Lấy danh sách dịch vụ bổ sung của booking này
$addons_query = $conn->query("
    SELECT * FROM booking_addons 
    WHERE booking_id = $id AND addon_id = -1 
    ORDER BY created_at DESC
");
$booking_addons = [];
$total_addons = 0;
$total_pending = 0;
while ($addon = $addons_query->fetch_assoc()) {
    $booking_addons[] = $addon;
    $amount = $addon['addon_price'] * $addon['quantity'];
    $total_addons += $amount;
    if ($addon['status'] === 'pending') {
        $total_pending += $amount;
    }
}

// Đã xóa đoạn code cộng thủ công phí Late Checkout vào $total_addons ở đây
// Vì bây giờ nó đã nằm trong bảng booking_addons và được tính trong vòng lặp trên

$addon_status_labels = [
    'pending' => ['name' => 'Chờ thanh toán', 'color' => 'warning'],
    'confirmed' => ['name' => 'Đã thanh toán', 'color' => 'info'],
    'completed' => ['name' => 'Hoàn thành', 'color' => 'success'],
    'cancelled' => ['name' => 'Đã hủy', 'color' => 'secondary']
];

?>

<div class="d-flex">
    <?php include __DIR__ . '/components/sidebar.php'; ?>
    
    <div class="main-content flex-grow-1 p-4">
        <!-- Breadcrumb & Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="booking.php" class="text-decoration-none text-secondary"><i class="fas fa-arrow-left"></i> Quay lại</a>
                <h2 class="mt-2">Chi tiết Booking #<?php echo htmlspecialchars($booking['booking_reference']); ?></h2>
            </div>
            <div>
                <span class="badge bg-<?php 
                    echo match($booking['booking_status']) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'checked_in' => 'success',
                        'checked_out' => 'secondary',
                        'cancelled' => 'danger',
                        default => 'secondary'
                    };
                ?> fs-5">
                    <?php echo ucfirst($booking['booking_status']); ?>
                </span>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Cột Trái: Thông tin chi tiết -->
            <div class="col-md-8">
                <!-- Thông tin khách hàng -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-user"></i> Thông tin khách hàng</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Họ và tên</label>
                                <div class="fw-bold"><?php echo htmlspecialchars($booking['guest_name']); ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Số điện thoại</label>
                                <div><?php echo htmlspecialchars($booking['guest_phone']); ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Email</label>
                                <div><?php echo htmlspecialchars($booking['guest_email']); ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Quốc tịch</label>
                                <div><?php echo htmlspecialchars($booking['guest_country']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thông tin đặt phòng -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Thông tin lưu trú</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Ngày nhận phòng (Check-in)</label>
                                <div class="fw-bold text-primary"><?php echo date('d/m/Y', strtotime($booking['checkin_date'])); ?></div>
                                <?php if (!empty($booking['checkin_at'])): ?>
                                    <small class="text-success"><i class="fas fa-clock"></i> Thực tế: <?php echo date('H:i d/m/Y', strtotime($booking['checkin_at'])); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Ngày trả phòng (Check-out)</label>
                                <div class="fw-bold text-primary"><?php echo date('d/m/Y', strtotime($booking['checkout_date'])); ?></div>
                                <?php if (!empty($booking['checkout_at'])): ?>
                                    <small class="text-secondary"><i class="fas fa-clock"></i> Thực tế: <?php echo date('H:i d/m/Y', strtotime($booking['checkout_at'])); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Số đêm</label>
                                <div><?php echo $booking['num_nights']; ?> đêm</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Số khách</label>
                                <div><?php echo $booking['num_adults']; ?> lớn, <?php echo $booking['num_children']; ?> trẻ em</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted small">Tổng tiền</label>
                                <div class="fw-bold text-success fs-5"><?php echo number_format($booking['total_price']); ?> VNĐ</div>
                            </div>

                            <!-- Late Checkout Info -->
                            <?php if ($booking['late_checkout'] == 1): ?>
                            <div class="col-12">
                                <div class="alert alert-success d-flex align-items-center justify-content-between">
                                    <div class="flex-grow-1">
                                        <i class="fas fa-check-circle me-3 fs-5 text-success"></i>
                                        <div class="d-inline-block">
                                            <strong>Khách đã chọn Check-out Muộn</strong>
                                            <br>
                                            <small>Thời gian trả phòng: <strong>16:00 chiều</strong> (thêm 4 giờ)</small>
                                            <br>
                                            <small class="text-success fw-bold">
                                                <i class="fas fa-money-bill-wave"></i> Phí: <?php echo number_format($booking['late_checkout_fee']); ?> VNĐ
                                            </small>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="openModal('addLateCheckoutModal')">
                                        <i class="fas fa-cog"></i> Quản lý
                                    </button>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-warning d-flex align-items-center justify-content-between">
                                    <div>
                                        <i class="fas fa-clock text-warning me-2"></i>
                                        <div class="d-inline-block">
                                            <strong>Check-out tiêu chuẩn: 12:00 trưa</strong>
                                            <br>
                                            <small>Khách chưa chọn check-out muộn</small>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="openModal('addLateCheckoutModal')">
                                        <i class="fas fa-plus"></i> Thêm Late Checkout
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="col-12">
                                <label class="text-muted small">Ghi chú đặc biệt</label>
                                <div class="p-2 bg-light rounded border">
                                    <?php echo $booking['special_requests'] ? nl2br(htmlspecialchars($booking['special_requests'])) : '<em>Không có ghi chú</em>'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DỊCH VỤ BỔ SUNG -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-concierge-bell"></i> Dịch vụ bổ sung</h5>
                        <?php if ($total_pending > 0): ?>
                        <span class="badge bg-warning text-dark">
                            <?php echo count(array_filter($booking_addons, fn($a) => $a['status'] === 'pending')); ?> chờ thanh toán
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($booking_addons)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                            <p>Chưa có dịch vụ bổ sung nào.</p>
                        </div>
                        <?php else: ?>
                        
                        <!-- Tổng kết -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="bg-light rounded p-3">
                                    <small class="text-muted">Tổng dịch vụ</small>
                                    <div class="fs-5 fw-bold text-primary"><?php echo number_format($total_addons); ?> VNĐ</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="bg-warning bg-opacity-25 rounded p-3">
                                    <small class="text-muted">Chưa thanh toán</small>
                                    <div class="fs-5 fw-bold text-warning"><?php echo number_format($total_pending); ?> VNĐ</div>
                                </div>
                            </div>
                        </div>

                        <!-- Thanh toán tất cả -->
                        <?php if ($total_pending > 0): ?>
                        <div class="alert alert-warning d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong><?php echo number_format($total_pending); ?> VNĐ</strong> chưa thanh toán
                            </div>
                            <button type="button" class="btn btn-success btn-sm" onclick="openModal('payAllModal')">
                                <i class="fas fa-cash-register"></i> Thanh toán tất cả
                            </button>
                        </div>
                        <?php endif; ?>

                        <!-- Danh sách dịch vụ -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Dịch vụ</th>
                                        <th class="text-center">SL</th>
                                        <th class="text-end">Đơn giá</th>
                                        <th class="text-end">Thành tiền</th>
                                        <th class="text-center">Trạng thái</th>
                                        <th class="text-center">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($booking_addons as $addon): 
                                        $status_info = $addon_status_labels[$addon['status']] ?? $addon_status_labels['pending'];
                                        $amount = $addon['addon_price'] * $addon['quantity'];
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($addon['addon_name']); ?></div>
                                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($addon['created_at'])); ?></small>
                                        </td>
                                        <td class="text-center"><?php echo $addon['quantity']; ?></td>
                                        <td class="text-end"><?php echo number_format($addon['addon_price']); ?></td>
                                        <td class="text-end fw-bold"><?php echo number_format($amount); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $status_info['color']; ?>">
                                                <?php echo $status_info['name']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($addon['status'] === 'pending'): ?>
                                            <button type="button" class="btn btn-primary btn-sm" 
                                                    data-id="<?php echo $addon['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($addon['addon_name']); ?>"
                                                    data-amount="<?php echo $amount; ?>"

                                                    onclick="openUpdateStatusModal(this)">
                                                <i class="fas fa-edit"></i> Cập nhật
                                            </button>
                                            <?php elseif ($addon['status'] === 'confirmed'): ?>
                                            <span class="text-success"><i class="fas fa-check"></i> Đã thu</span>
                                            <?php elseif ($addon['status'] === 'cancelled'): ?>
                                            <span class="text-secondary"><i class="fas fa-ban"></i> Đã hủy</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <!-- Đã xóa đoạn code hiển thị thủ công dòng Late Checkout ở đây -->
                                    <!-- Vì bây giờ nó sẽ hiển thị như một dịch vụ bình thường trong vòng lặp foreach ở trên -->
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Cột Phải: Thao tác quản lý -->
            <div class="col-md-4">
                
                <!-- 1. Check-in / Đổi trạng thái -->
                <div class="card mb-4 border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-tasks"></i> Trạng thái Booking</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_status">
                            <div class="mb-3">
                                <label class="form-label">Cập nhật trạng thái:</label>
                                <select name="status" class="form-select mb-3">
                                    <option value="pending" <?php echo $booking['booking_status'] == 'pending' ? 'selected' : ''; ?>>Pending (Chờ xử lý)</option>
                                    <option value="confirmed" <?php echo $booking['booking_status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed (Đã xác nhận)</option>
                                    <option value="checked_in" <?php echo $booking['booking_status'] == 'checked_in' ? 'selected' : ''; ?>>Checked In (Đã nhận phòng)</option>
                                    <option value="checked_out" <?php echo $booking['booking_status'] == 'checked_out' ? 'selected' : ''; ?>>Checked Out (Đã trả phòng)</option>
                                    <option value="cancelled" <?php echo $booking['booking_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled (Hủy)</option>
                                </select>
                                
                                <?php if ($booking['booking_status'] == 'confirmed'): ?>
                                    <button type="submit" name="status" value="checked_in" class="btn btn-success w-100 mb-2">
                                        <i class="fas fa-check-circle"></i> CHECK-IN KHÁCH
                                    </button>
                                <?php elseif ($booking['booking_status'] == 'checked_in'): ?>
                                    <button type="submit" name="status" value="checked_out" class="btn btn-warning w-100 mb-2">
                                        <i class="fas fa-sign-out-alt"></i> CHECK-OUT
                                    </button>
                                <?php endif; ?>

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save"></i> Cập nhật trạng thái
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 2. Phân công phòng / Nâng cấp phòng -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-door-open"></i> Phân công / Nâng cấp phòng</h5>
                    </div>
                    <div class="card-body">
                        <!-- Hiển thị giá hiện tại -->
                        <div class="alert alert-info py-2 mb-3">
                            <small class="d-block"><strong>Phòng hiện tại:</strong> <?php echo htmlspecialchars($booking['room_type_name']); ?></small>
                            <small class="d-block"><strong>Giá/đêm:</strong> <?php echo number_format($booking['current_room_price']); ?> VNĐ</small>
                        </div>

                        <form method="POST" id="roomAssignForm">
                            <input type="hidden" name="action" value="assign_room">
                            
                            <!-- Loại phòng -->
                            <div class="mb-3">
                                <label class="form-label">Loại phòng</label>
                                <select name="room_id" id="roomTypeSelect" class="form-select">
                                    <?php while($type = $room_types->fetch_assoc()): ?>
                                        <option value="<?php echo $type['id']; ?>" 
                                            data-price="<?php echo $type['price_per_night']; ?>"
                                            <?php echo $type['id'] == $booking['room_id'] ? 'selected' : ''; ?>>

                                            <?php echo htmlspecialchars($type['room_type_name']); ?> 
                                            - <?php echo number_format($type['price_per_night']); ?> VNĐ
                                            
                                            <?php if ($type['price_diff'] > 0): ?>
                                                <span class="text-success">(Nâng cấp: +<?php echo number_format($type['price_diff'] * $booking['num_nights']); ?>)</span>
                                            <?php elseif ($type['refund_diff'] > 0): ?>
                                                <span class="text-danger">(Hoàn tiền: -<?php echo number_format($type['refund_diff'] * $booking['num_nights']); ?>)</span>
                                            <?php endif; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <div class="form-text text-muted">
                                    <i class="fas fa-info-circle"></i> Chọn loại phòng khác để thay đổi. Hệ thống sẽ tự động tính toán chênh lệch.
                                </div>
                            </div>

                            <!-- Số phòng cụ thể -->
                            <div class="mb-3">
                                <label class="form-label">Số phòng (Room Number)</label>
                                <select name="room_number_id" class="form-select <?php echo empty($booking['room_number_id']) ? 'border-danger' : 'border-success'; ?>">
                                    <option value="">-- Chưa gán phòng --</option>
                                    
                                    <!-- Hiển thị phòng hiện tại nếu có -->
                                    <?php if ($booking['room_number_id']): ?>
                                        <option value="<?php echo $booking['room_number_id']; ?>" selected>
                                            Phòng <?php echo $booking['room_number']; ?> (Hiện tại)
                                        </option>
                                    <?php endif; ?>

                                    <!-- Danh sách phòng trống -->
                                    <?php 
                                    if ($available_rooms && $available_rooms->num_rows > 0):
                                        while($room = $available_rooms->fetch_assoc()): 
                                            if ($room['id'] == $booking['room_number_id']) continue;
                                    ?>
                                        <option value="<?php echo $room['id']; ?>">
                                            Phòng <?php echo $room['room_number']; ?> (Tầng <?php echo $room['floor']; ?>) - Sẵn sàng
                                        </option>
                                    <?php 
                                        endwhile;
                                    endif; 
                                    ?>
                                </select>
                                <?php if (empty($booking['room_number_id'])): ?>
                                    <div class="text-danger small mt-1"><i class="fas fa-exclamation-circle"></i> Cần gán phòng trước khi Check-in</div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="fas fa-exchange-alt"></i> Lưu thay đổi phòng
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div> <!-- Đóng main-content -->
</div> <!-- Đóng d-flex -->

<!-- ============================================================== -->
<!-- CUSTOM MODAL STYLES -->
<!-- ============================================================== -->
<style>
    /* Modal Overlay */
    .custom-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1050;
        justify-content: center;
        align-items: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .custom-modal-overlay.show {
        display: flex;
        opacity: 1;
    }

    /* Modal Content */
    .custom-modal {
        background-color: #fff;
        border-radius: 0.5rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        width: 100%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        transform: translateY(-20px);
        transition: transform 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .custom-modal-overlay.show .custom-modal {
        transform: translateY(0);
    }

    /* Modal Header */
    .custom-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
    }

    .custom-modal-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 500;
    }

    .custom-modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        line-height: 1;
        color: #000;
        opacity: 0.5;
        cursor: pointer;
        padding: 0;
    }

    .custom-modal-close:hover {
        opacity: 0.75;
    }

    .custom-modal-close.white {
        color: #fff;
        opacity: 0.8;
    }
    
    .custom-modal-close.white:hover {
        opacity: 1;
    }

    /* Modal Body */
    .custom-modal-body {
        padding: 1rem;
        flex: 1 1 auto;
    }

    /* Modal Footer */
    .custom-modal-footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        padding: 0.75rem;
        border-top: 1px solid #dee2e6;
        border-bottom-right-radius: 0.5rem;
        border-bottom-left-radius: 0.5rem;
        gap: 0.5rem;
    }

    /* Utility classes for modal colors */
    .bg-primary-modal { background-color: #0d6efd; color: white; }
    .bg-warning-modal { background-color: #ffc107; color: #000; }
    .bg-success-modal { background-color: #198754; color: white; }
</style>

<!-- Modal Cập nhật trạng thái dịch vụ (Thay thế payAddonModal) -->
<div id="updateAddonStatusModal" class="custom-modal-overlay">
    <div class="custom-modal">
        <div class="custom-modal-header bg-primary-modal">
            <h5 class="custom-modal-title"><i class="fas fa-edit"></i> Cập nhật trạng thái dịch vụ</h5>
            <button type="button" class="custom-modal-close white" onclick="closeModal('updateAddonStatusModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="custom-modal-body">
                <input type="hidden" name="action" value="update_addon_status">
                <input type="hidden" name="addon_id" id="updateAddonId">
                
                <div class="bg-light rounded p-3 mb-4">
                    <div class="mb-2">
                        <small class="text-muted">Dịch vụ:</small>
                        <div class="fw-bold" id="updateAddonName"></div>
                    </div>
                    <div>
                        <small class="text-muted">Số tiền:</small>
                        <div class="fs-4 fw-bold text-primary" id="updateAddonAmount"></div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Trạng thái mới</label>
                    <select name="new_status" id="addonStatusSelect" class="form-select form-select-lg" onchange="togglePaymentMethods()">
                        <option value="confirmed" selected>✅ Đã thanh toán (Confirmed)</option>
                        <option value="cancelled">🚫 Hủy dịch vụ (Cancelled)</option>
                    </select>
                </div>

                <div id="paymentMethodSection">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Phương thức thanh toán</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="payment_method" id="pay_cash" value="cash" checked>
                                <label class="btn btn-outline-success w-100 py-2" for="pay_cash">
                                    <i class="fas fa-money-bill-wave me-1"></i> Tiền mặt
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="payment_method" id="pay_card" value="card">
                                <label class="btn btn-outline-primary w-100 py-2" for="pay_card">
                                    <i class="fas fa-credit-card me-1"></i> Thẻ
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="payment_method" id="pay_momo" value="momo">
                                <label class="btn btn-outline-danger w-100 py-2" for="pay_momo">
                                    <i class="fas fa-mobile-alt me-1"></i> MoMo
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="payment_method" id="pay_vnpay" value="vnpay">
                                <label class="btn btn-outline-info w-100 py-2" for="pay_vnpay">
                                    <i class="fas fa-qrcode me-1"></i> VNPay
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('updateAddonStatusModal')">Đóng</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Lưu thay đổi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Thanh toán tất cả -->
<div id="payAllModal" class="custom-modal-overlay">
    <div class="custom-modal">
        <div class="custom-modal-header bg-warning-modal">
            <h5 class="custom-modal-title"><i class="fas fa-cash-register"></i> Thanh toán tất cả dịch vụ</h5>
            <button type="button" class="custom-modal-close" onclick="closeModal('payAllModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="custom-modal-body">
                <input type="hidden" name="action" value="pay_all_addons">
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Thanh toán tất cả dịch vụ đang chờ thanh toán.
                </div>
                
                <div class="bg-warning bg-opacity-25 rounded p-4 text-center mb-4">
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted d-block">Tổng tiền dịch vụ</small>
                            <div class="fs-5 fw-bold text-primary"><?php echo number_format($total_addons); ?> VNĐ</div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Chưa thanh toán</small>
                            <div class="fs-5 fw-bold text-warning"><?php echo number_format($total_pending); ?> VNĐ</div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Phương thức thanh toán</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="payment_method" id="payall_cash" value="cash" checked>
                            <label class="btn btn-outline-success w-100 py-3" for="payall_cash">
                                <i class="fas fa-money-bill-wave fa-lg d-block mb-1"></i>
                                Tiền mặt
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="payment_method" id="payall_card" value="card">
                            <label class="btn btn-outline-primary w-100 py-3" for="payall_card">
                                <i class="fas fa-credit-card fa-lg d-block mb-1"></i>
                                Thẻ
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="payment_method" id="payall_momo" value="momo">
                            <label class="btn btn-outline-danger w-100 py-3" for="payall_momo">
                                <i class="fas fa-mobile-alt fa-lg d-block mb-1"></i>
                                MoMo
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="payment_method" id="payall_vnpay" value="vnpay">
                            <label class="btn btn-outline-info w-100 py-3" for="payall_vnpay">
                                <i class="fas fa-qrcode fa-lg d-block mb-1"></i>
                                VNPay
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('payAllModal')">Hủy</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Xác nhận thu tiền
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Thêm/Hủy Check-out Muộn -->
<div id="addLateCheckoutModal" class="custom-modal-overlay">
    <div class="custom-modal">
        <div class="custom-modal-header bg-warning-modal">
            <h5 class="custom-modal-title"><i class="fas fa-clock"></i> 
                <?php echo $booking['late_checkout'] == 1 ? 'Quản lý Check-out Muộn' : 'Thêm Check-out Muộn'; ?>
            </h5>
            <button type="button" class="custom-modal-close" onclick="closeModal('addLateCheckoutModal')">&times;</button>
        </div>
        
        <?php if ($booking['late_checkout'] == 1): ?>
            <!-- Trường hợp: Đã có late checkout -->
            <div class="custom-modal-body">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    Khách đã chọn <strong>Check-out Muộn</strong>
                </div>

                <div class="bg-success bg-opacity-25 rounded p-4 text-center mb-4">
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted d-block">Check-out thường</small>
                            <div class="fw-bold">12:00</div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Check-out muộn</small>
                            <div class="fw-bold text-success fs-5">16:00 ✓</div>
                        </div>
                    </div>
                </div>

                <!-- Form Cập nhật phí -->
                <form method="POST" class="mb-4">
                    <input type="hidden" name="action" value="update_late_checkout">
                    <label class="form-label fw-bold">Điều chỉnh phí Check-out Muộn</label>
                    <div class="input-group">
                        <input type="number" 
                               name="late_checkout_fee" 
                               class="form-control" 
                               value="<?php echo $booking['late_checkout_fee']; ?>" 
                               min="0"
                               required>
                        <span class="input-group-text">VNĐ</span>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Cập nhật
                        </button>
                    </div>
                    <small class="text-muted">Thay đổi phí sẽ cập nhật lại tổng tiền Booking.</small>
                </form>

                <div class="bg-success bg-opacity-10 rounded p-3 mb-4">
                    <div class="d-flex justify-content-between">
                        <span>Tổng tiền hiện tại:</span>
                        <strong><?php echo number_format($booking['total_price']); ?> VNĐ</strong>
                    </div>
                </div>

                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i>
                    Nếu hủy dịch vụ, khách sẽ được hoàn lại <strong><?php echo number_format($booking['late_checkout_fee']); ?> VNĐ</strong>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addLateCheckoutModal')">Đóng</button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="remove_late_checkout">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Hủy Check-out Muộn
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- Trường hợp: Chưa có late checkout -->
            <form method="POST">
                <div class="custom-modal-body">
                    <input type="hidden" name="action" value="add_late_checkout">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        Cho phép khách trả phòng sau 12:00 trưa với phí bổ sung.
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Thời gian trả phòng</label>
                        <div class="row">
                            <div class="col-6">
                                <div class="p-3 border rounded bg-light text-center">
                                    <small class="text-muted">Check-out tiêu chuẩn</small>
                                    <div class="fs-6 fw-bold">12:00 trưa</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 border-2 border-warning rounded bg-warning bg-opacity-25 text-center">
                                    <small class="text-muted">Check-out muộn</small>
                                    <div class="fs-6 fw-bold text-warning">16:00 chiều</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Phí Check-out Muộn</label>
                        <div class="input-group mb-2">
                            <input type="number" 
                                   name="late_checkout_fee" 
                                   id="lateCheckoutFeeInput"
                                   class="form-control form-control-lg" 
                                   value="500000" 
                                   min="0"
                                   placeholder="Nhập phí" 
                                   required>
                            <span class="input-group-text">VNĐ</span>
                        </div>
                        <small class="form-text text-muted">Giá mặc định: 500,000 VNĐ (thêm 4 giờ)</small>
                    </div>

                    <div class="bg-light rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Tổng tiền hiện tại:</span>
                            <strong><?php echo number_format($booking['total_price']); ?> VNĐ</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3 text-warning">
                            <span> Phí Late Checkout:</span>
                            <strong id="lateCheckoutFeeDisplay">500,000 VNĐ</strong>
                        </div>
                        <div class="border-top pt-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Tổng tiền mới:</span>
                                <strong class="text-success fs-5" id="newTotalDisplay"><?php echo number_format($booking['total_price'] + 500000); ?> VNĐ</strong>
                            </div>
                        </div>
                    </div>

                    <div class="bg-warning bg-opacity-10 rounded p-3 border border-warning">
                        <i class="fas fa-lightbulb text-warning"></i>
                        <strong>Lưu ý:</strong> Khách sẽ có thêm 4 giờ để trả phòng (từ 12:00 → 16:00). Phí này sẽ được cộng vào tổng hóa đơn.
                    </div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addLateCheckoutModal')">Hủy</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-check"></i> Xác nhận thêm Late Checkout
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
// Custom Modal Functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = ''; // Restore scrolling
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('custom-modal-overlay')) {
        closeModal(event.target.id);
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const openModals = document.querySelectorAll('.custom-modal-overlay.show');
        openModals.forEach(modal => closeModal(modal.id));
    }
});

// Update tổng tiền khi thay đổi phí Late Checkout
document.addEventListener('DOMContentLoaded', function() {
    const feeInput = document.getElementById('lateCheckoutFeeInput');
    
    if (feeInput) {
        feeInput.addEventListener('input', function(e) {
            const fee = parseInt(e.target.value) || 0;
            const baseTotal = <?php echo $booking['total_price']; ?>;
            const newTotal = baseTotal + fee;
            
            document.getElementById('lateCheckoutFeeDisplay').textContent = 
                new Intl.NumberFormat('vi-VN').format(fee) + ' VNĐ';
            document.getElementById('newTotalDisplay').textContent = 
                new Intl.NumberFormat('vi-VN').format(newTotal) + ' VNĐ';
        });
    }
});

function openUpdateStatusModal(btn) {
    const addonId = btn.getAttribute('data-id');
    const addonName = btn.getAttribute('data-name');
    const amount = btn.getAttribute('data-amount');

    document.getElementById('updateAddonId').value = addonId;
    document.getElementById('updateAddonName').textContent = addonName;
    document.getElementById('updateAddonAmount').textContent = 
        new Intl.NumberFormat('vi-VN').format(amount) + ' VNĐ';
    
    // Reset form về mặc định
    document.getElementById('addonStatusSelect').value = 'confirmed';
    togglePaymentMethods();

    openModal('updateAddonStatusModal');
}

function togglePaymentMethods() {
    const status = document.getElementById('addonStatusSelect').value;
    const paymentSection = document.getElementById('paymentMethodSection');
    
    if (status === 'confirmed') {
        paymentSection.style.display = 'block';
    } else {
        paymentSection.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/components/footer.php'; ?>