<?php

include __DIR__ . '/components/header.php';

// Xử lý cập nhật trạng thái nhanh (nếu có POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $room_number_id = (int)$_POST['room_number_id'];
        $new_status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE room_numbers SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $room_number_id);
        
        if ($stmt->execute()) {
            $message = "Cập nhật trạng thái phòng thành công!";
        } else {
            $error = "Lỗi: " . $conn->error;
        }
    }
    
    // XỬ LÝ XÓA PHÒNG
    if ($_POST['action'] === 'delete_room') {
        $room_number_id = (int)$_POST['room_number_id'];
        $check = $conn->query("SELECT id FROM bookings WHERE room_number_id = $room_number_id AND booking_status IN ('checked_in', 'confirmed')");
        if ($check->num_rows > 0) {
            $error = "Không thể xóa phòng đang có khách hoặc có lịch đặt!";
        } else {
            $stmt = $conn->prepare("DELETE FROM room_numbers WHERE id = ?");
            $stmt->bind_param("i", $room_number_id);
            if ($stmt->execute()) {
                $message = "Đã xóa phòng thành công!";
            } else {
                $error = "Lỗi xóa phòng: " . $conn->error;
            }
        }
    }

    // XỬ LÝ SỬA PHÒNG
    if ($_POST['action'] === 'edit_room') {
        $id = (int)$_POST['room_number_id'];
        $num = $_POST['room_number'];
        $fl = (int)$_POST['floor'];
        $rid = (int)$_POST['room_id'];
        
        $stmt = $conn->prepare("UPDATE room_numbers SET room_number = ?, floor = ?, room_id = ? WHERE id = ?");
        $stmt->bind_param("siii", $num, $fl, $rid, $id);
        
        if ($stmt->execute()) {
            $message = "Cập nhật thông tin phòng thành công!";
        } else {
            $error = "Lỗi cập nhật: " . $conn->error;
        }
    }
}

// --- XỬ LÝ BỘ LỌC ---
$filter_floor = isset($_GET['floor']) ? $_GET['floor'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+5 day'));

$filter_date_in = isset($_GET['date_in']) && $_GET['date_in'] !== '' ? $_GET['date_in'] : $today;
$filter_date_out = isset($_GET['date_out']) && $_GET['date_out'] !== '' ? $_GET['date_out'] : $tomorrow;

if ($filter_date_in && $filter_date_out) {
    $sql = "
        SELECT rn.*, r.room_type_name, r.price_per_night,
               b.id as current_booking_id,
               b.guest_name,
               b.checkin_date,
               b.checkout_date,
               b.booking_status
        FROM room_numbers rn
        JOIN rooms r ON rn.room_id = r.id
        LEFT JOIN bookings b ON rn.id = b.room_number_id 
            AND b.booking_status IN ('checked_in', 'confirmed')
            AND (b.checkin_date < '$filter_date_out' AND b.checkout_date > '$filter_date_in')
    ";
} else {
    $sql = "
        SELECT rn.*, r.room_type_name, r.price_per_night,
               b.id as current_booking_id,
               b.guest_name,
               b.checkin_date,
               b.checkout_date,
               b.booking_status
        FROM room_numbers rn
        JOIN rooms r ON rn.room_id = r.id
        LEFT JOIN bookings b ON rn.id = b.room_number_id 
            AND b.booking_status IN ('checked_in', 'confirmed')
    ";
}

$where_clauses = [];

if ($filter_floor !== '') {
    $where_clauses[] = "rn.floor = " . (int)$filter_floor;
}

if ($filter_type !== '') {
    $where_clauses[] = "rn.room_id = " . (int)$filter_type;
}

if ($filter_status !== '') {
    if ($filter_status === 'checked_in') {
        $where_clauses[] = "b.booking_status = 'checked_in'";
    } elseif ($filter_status === 'confirmed') {
        $where_clauses[] = "b.booking_status = 'confirmed'";
    } elseif ($filter_status === 'occupied') {
        $where_clauses[] = "b.id IS NOT NULL";
    } else {
        $where_clauses[] = "rn.status = '$filter_status'";
        $where_clauses[] = "b.id IS NULL";
    }
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY rn.floor ASC, rn.room_number ASC";

$result = $conn->query($sql);

$floors = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $floors[$row['floor']][] = $row;
    }
}

$stats = [
    'available' => 0,
    'checked_in' => 0,
    'confirmed' => 0,
    'dirty' => 0,
    'cleaning' => 0,
    'maintenance' => 0
];

foreach ($floors as $floor_rooms) {
    foreach ($floor_rooms as $room) {
        if ($room['current_booking_id']) {
            if ($room['booking_status'] == 'checked_in') {
                $stats['checked_in']++;
            } else {
                $stats['confirmed']++;
            }
        } else {
            if (isset($stats[$room['status']])) {
                $stats[$room['status']]++;
            }
        }
    }
}

$room_types_list = $conn->query("SELECT * FROM rooms WHERE is_active = 1");
$floors_list = $conn->query("SELECT DISTINCT floor FROM room_numbers ORDER BY floor ASC");

$room_types_array = [];
while($t = $room_types_list->fetch_assoc()) {
    $room_types_array[] = $t;
}
?>

<div class="d-flex">
    <?php include __DIR__ . '/components/sidebar.php'; ?>
    
    <div class="main-content flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-th"></i> Sơ đồ phòng</h2>
            <div>
                <a href="rooms-list.php" class="btn btn-outline-primary me-2"><i class="fas fa-list"></i> Chế độ danh sách</a>
                <!-- <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal"><i class="fas fa-plus"></i> Thêm phòng</button> -->
            </div>
        </div>

        <!-- BỘ LỌC TÌM KIẾM -->
        <div class="card mb-4">
            <div class="card-body bg-light">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Tầng</label>
                        <select name="floor" class="form-select">
                            <option value="">Tất cả</option>
                            <?php while($f = $floors_list->fetch_assoc()): ?>
                                <option value="<?php echo $f['floor']; ?>" <?php echo $filter_floor == $f['floor'] ? 'selected' : ''; ?>>
                                    Tầng <?php echo $f['floor']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Loại phòng</label>
                        <select name="type" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach($room_types_array as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo $filter_type == $t['id'] ? 'selected' : ''; ?>>
                                    <?php echo $t['room_type_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="">Tất cả</option>
                            <option value="available" <?php echo $filter_status === 'available' ? 'selected' : ''; ?>>🟢 Trống</option>
                            <option value="checked_in" <?php echo $filter_status === 'checked_in' ? 'selected' : ''; ?>>🔴 Đang ở</option>
                            <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>🟠 Đã đặt</option>
                            <option value="dirty" <?php echo $filter_status === 'dirty' ? 'selected' : ''; ?>>🟤 Cần dọn</option>
                            <option value="cleaning" <?php echo $filter_status === 'cleaning' ? 'selected' : ''; ?>>🟡 Đang dọn</option>
                            <option value="maintenance" <?php echo $filter_status === 'maintenance' ? 'selected' : ''; ?>>⚫ Bảo trì</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Kiểm tra lịch trống (Từ - Đến)</label>
                        <div class="input-group">
                            <input type="date" name="date_in" class="form-control" value="<?php echo $filter_date_in; ?>">
                            <span class="input-group-text">-</span>
                            <input type="date" name="date_out" class="form-control" value="<?php echo $filter_date_out; ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Lọc</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Thống kê trạng thái -->
        <div class="row mb-4 g-2">
            <div class="col">
                <div class="card bg-success text-white h-100">
                    <div class="card-body d-flex justify-content-between align-items-center py-2">
                        <div>
                            <small class="text-uppercase opacity-75">Trống</small>
                            <h3 class="mb-0"><?php echo $stats['available']; ?></h3>
                        </div>
                        <i class="fas fa-door-open fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card bg-danger text-white h-100">
                    <div class="card-body d-flex justify-content-between align-items-center py-2">
                        <div>
                            <small class="text-uppercase opacity-75">Đang ở</small>
                            <h3 class="mb-0"><?php echo $stats['checked_in']; ?></h3>
                        </div>
                        <i class="fas fa-user-check fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-white h-100" style="background-color: #fd7e14;">
                    <div class="card-body d-flex justify-content-between align-items-center py-2">
                        <div>
                            <small class="text-uppercase opacity-75">Đã đặt</small>
                            <h3 class="mb-0"><?php echo $stats['confirmed']; ?></h3>
                        </div>
                        <i class="fas fa-calendar-check fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-white h-100" style="background-color: #8B4513;">
                    <div class="card-body d-flex justify-content-between align-items-center py-2">
                        <div>
                            <small class="text-uppercase opacity-75">Cần dọn</small>
                            <h3 class="mb-0"><?php echo $stats['dirty']; ?></h3>
                        </div>
                        <i class="fas fa-trash-alt fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body d-flex justify-content-between align-items-center py-2">
                        <div>
                            <small class="text-uppercase opacity-75">Đang dọn</small>
                            <h3 class="mb-0"><?php echo $stats['cleaning']; ?></h3>
                        </div>
                        <i class="fas fa-broom fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card bg-secondary text-white h-100">
                    <div class="card-body d-flex justify-content-between align-items-center py-2">
                        <div>
                            <small class="text-uppercase opacity-75">Bảo trì</small>
                            <h3 class="mb-0"><?php echo $stats['maintenance']; ?></h3>
                        </div>
                        <i class="fas fa-tools fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Hiển thị sơ đồ phòng theo tầng -->
        <?php foreach ($floors as $floor => $rooms): ?>
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-layer-group"></i> Tầng <?php echo $floor; ?></h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($rooms as $room): 
                            $bg_class = 'bg-success';
                            $icon = 'fa-door-open';
                            $status_text = 'Sẵn sàng';
                            $is_occupied = false;
                            $date_range = '';
                            $badge_class = '';

                            if ($room['current_booking_id']) {
                                $is_occupied = true;
                                $d_in = date('d/m', strtotime($room['checkin_date']));
                                $d_out = date('d/m', strtotime($room['checkout_date']));
                                $date_range = "$d_in - $d_out";
                                
                                if ($room['booking_status'] == 'checked_in') {
                                    $bg_class = 'bg-danger';
                                    $icon = 'fa-user-check';
                                    $status_text = 'Đang ở';
                                    $badge_class = 'bg-dark';
                                } else {
                                    $bg_class = 'room-confirmed';
                                    $icon = 'fa-calendar-check';
                                    $status_text = 'Đã đặt';
                                    $badge_class = 'bg-warning text-dark';
                                }
                            } elseif ($room['status'] == 'dirty') {
                                $bg_class = 'room-dirty';
                                $icon = 'fa-trash-alt';
                                $status_text = 'Cần dọn dẹp';
                            } elseif ($room['status'] == 'cleaning') {
                                $bg_class = 'bg-warning text-dark';
                                $icon = 'fa-broom';
                                $status_text = 'Đang dọn';
                            } elseif ($room['status'] == 'maintenance') {
                                $bg_class = 'bg-secondary';
                                $icon = 'fa-tools';
                                $status_text = 'Bảo trì';
                            }
                            
                            $text_class = (strpos($bg_class, 'text-dark') !== false) ? '' : 'text-white';
                        ?>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                            <div class="card h-100 <?php echo $bg_class; ?> <?php echo $text_class; ?> room-card">
                                <div class="card-body text-center p-3">
                                    <h4 class="card-title fw-bold mb-1"><?php echo htmlspecialchars($room['room_number']); ?></h4>
                                    <p class="card-text small mb-2 opacity-75"><?php echo htmlspecialchars($room['room_type_name']); ?></p>
                                    
                                    <div class="my-2">
                                        <i class="fas <?php echo $icon; ?> fa-2x"></i>
                                    </div>
                                    
                                    <p class="fw-bold mb-1 small text-uppercase"><?php echo $status_text; ?></p>
                                    
                                    <?php if ($is_occupied): ?>
                                        <div class="bg-white bg-opacity-25 rounded p-1 mb-2">
                                            <p class="small mb-0 fw-bold"><i class="far fa-calendar-alt"></i> <?php echo $date_range; ?></p>
                                        </div>
                                        <p class="small mb-2 text-truncate" title="<?php echo htmlspecialchars($room['guest_name']); ?>">
                                            <?php echo htmlspecialchars($room['guest_name']); ?>
                                        </p>
                                        <?php if ($badge_class): ?>
                                            <span class="badge <?php echo $badge_class; ?> mb-2">
                                                <?php echo $room['booking_status'] == 'checked_in' ? 'CHECK-IN' : 'CHỜ ĐẾN'; ?>
                                            </span>
                                        <?php endif; ?>
                                        <a href="booking-detail.php?id=<?php echo $room['current_booking_id']; ?>" class="btn btn-sm btn-light w-100">
                                            <i class="fas fa-eye"></i> Xem Booking
                                        </a>
                                    <?php else: ?>
                                        <!-- NÚT THAO TÁC - Mở menu trong body -->
                                        <button type="button" class="btn btn-sm btn-light w-100 mt-2 btn-room-action"
                                                data-room-id="<?php echo $room['id']; ?>"
                                                data-room-number="<?php echo htmlspecialchars($room['room_number'], ENT_QUOTES); ?>"
                                                data-room-floor="<?php echo $room['floor']; ?>"
                                                data-room-type-id="<?php echo $room['room_id']; ?>"
                                                data-room-status="<?php echo $room['status']; ?>">
                                            <i class="fas fa-cog"></i> Thao tác
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($floors)): ?>
            <div class="alert alert-info text-center">Không tìm thấy phòng nào phù hợp với bộ lọc.</div>
        <?php endif; ?>

    </div>
</div>

<!-- MENU DROPDOWN ĐƯỢC ĐẶT TRONG BODY - TRÁNH Z-INDEX -->
<div id="roomActionMenu" class="room-action-menu">
    <div class="menu-header">
        <span id="menuRoomTitle">Phòng 101</span>
        <button type="button" class="btn-close btn-close-white btn-sm" onclick="closeRoomMenu()"></button>
    </div>
    <div class="menu-body">
        <div class="menu-section">
            <div class="menu-section-title"><i class="fas fa-sync-alt"></i> Cập nhật trạng thái</div>
            <div class="menu-item" data-status="available" onclick="setStatus('available')">
                <i class="fas fa-check-circle text-success"></i> Sẵn sàng
            </div>
            <div class="menu-item" data-status="dirty" onclick="setStatus('dirty')">
                <i class="fas fa-trash-alt text-danger"></i> Cần dọn
            </div>
            <div class="menu-item" data-status="cleaning" onclick="setStatus('cleaning')">
                <i class="fas fa-broom text-warning"></i> Đang dọn
            </div>
            <div class="menu-item" data-status="maintenance" onclick="setStatus('maintenance')">
                <i class="fas fa-tools text-secondary"></i> Bảo trì
            </div>
        </div>
        <div class="menu-divider"></div>
        <div class="menu-section">
            <div class="menu-section-title"><i class="fas fa-cogs"></i> Quản lý</div>
            <a href="#" id="menuCreateBooking" class="menu-item">
                <i class="fas fa-plus-circle text-primary"></i> Tạo Booking
            </a>
        </div>
    </div>
</div>

<!-- Overlay khi menu mở -->
<div id="menuOverlay" class="menu-overlay" onclick="closeRoomMenu()"></div>

<!-- FORM ẨN -->
<form id="globalActionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" id="globalAction">
    <input type="hidden" name="room_number_id" id="globalRoomId">
    <input type="hidden" name="status" id="globalStatus">
</form>

<!-- Modal Thêm Phòng -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Thêm phòng mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="room-actions.php" method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Số phòng <span class="text-danger">*</span></label>
                        <input type="text" name="room_number" class="form-control" required placeholder="VD: 101">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tầng <span class="text-danger">*</span></label>
                        <input type="number" name="floor" class="form-control" required placeholder="VD: 1" min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Loại phòng <span class="text-danger">*</span></label>
                        <select name="room_id" class="form-select" required>
                            <?php foreach($room_types_array as $t): ?>
                                <option value="<?php echo $t['id']; ?>">
                                    <?php echo htmlspecialchars($t['room_type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Lưu</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sửa Phòng -->
<div class="modal fade" id="editRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Sửa thông tin phòng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editRoomForm">
                    <input type="hidden" name="action" value="edit_room">
                    <input type="hidden" name="room_number_id" id="edit_room_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Số phòng <span class="text-danger">*</span></label>
                        <input type="text" name="room_number" id="edit_room_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tầng <span class="text-danger">*</span></label>
                        <input type="number" name="floor" id="edit_floor" class="form-control" required min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Loại phòng <span class="text-danger">*</span></label>
                        <select name="room_id" id="edit_type_id" class="form-select" required>
                            <?php foreach($room_types_array as $t): ?>
                                <option value="<?php echo $t['id']; ?>">
                                    <?php echo htmlspecialchars($t['room_type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Cập nhật</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .room-card {
        transition: transform 0.2s, box-shadow 0.2s;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .room-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    }
    
    .room-confirmed {
        background-color: #fd7e14 !important;
        color: white !important;
    }
    
    .room-dirty {
        background-color: #8B4513 !important;
        color: white !important;
    }

    /* MENU THAO TÁC - Fixed position trong body */
    .menu-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.3);
        z-index: 9998;
    }
    .menu-overlay.show {
        display: block;
    }

    .room-action-menu {
        display: none;
        position: fixed;
        z-index: 9999;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        min-width: 220px;
        max-width: 280px;
        overflow: hidden;
        animation: menuSlideIn 0.2s ease;
    }
    .room-action-menu.show {
        display: block;
    }

    @keyframes menuSlideIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .menu-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: bold;
    }

    .menu-body {
        padding: 8px 0;
        max-height: 350px;
        overflow-y: auto;
    }

    .menu-section-title {
        font-size: 0.75rem;
        color: #6c757d;
        padding: 8px 15px 5px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .menu-item {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        cursor: pointer;
        color: #333;
        text-decoration: none;
        transition: background 0.15s;
    }
    .menu-item:hover {
        background-color: #f0f4ff;
    }
    .menu-item i {
        width: 24px;
        text-align: center;
        margin-right: 10px;
        font-size: 1rem;
    }
    .menu-item.text-danger:hover {
        background-color: #fff5f5;
    }
    .menu-item.hidden {
        display: none !important;
    }

    .menu-divider {
        height: 1px;
        background: #eee;
        margin: 8px 0;
    }
</style>

<script>
// Biến lưu thông tin phòng đang chọn
let currentRoom = {
    id: null,
    number: '',
    floor: null,
    typeId: null,
    status: ''
};

// Mở menu thao tác
document.querySelectorAll('.btn-room-action').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        
        // Lưu thông tin phòng
        currentRoom.id = this.dataset.roomId;
        currentRoom.number = this.dataset.roomNumber;
        currentRoom.floor = this.dataset.roomFloor;
        currentRoom.typeId = this.dataset.roomTypeId;
        currentRoom.status = this.dataset.roomStatus;

        // Cập nhật title menu
        document.getElementById('menuRoomTitle').textContent = 'Phòng ' + currentRoom.number;
        
        // Cập nhật link tạo booking - dẫn sang trang đặt phòng user với room_type_id
        const today = new Date().toISOString().split('T')[0];
        const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];
        document.getElementById('menuCreateBooking').href = '/booking/room_detail.php?id=' + currentRoom.typeId + '&checkin=' + today + '&checkout=' + tomorrow + '&adults=1&children=0';

        // Ẩn trạng thái hiện tại
        document.querySelectorAll('.menu-item[data-status]').forEach(function(item) {
            if (item.dataset.status === currentRoom.status) {
                item.classList.add('hidden');
            } else {
                item.classList.remove('hidden');
            }
        });

        // Tính vị trí menu
        const rect = this.getBoundingClientRect();
        const menu = document.getElementById('roomActionMenu');
        const menuWidth = 250;
        const menuHeight = 350;

        let left = rect.left;
        let top = rect.bottom + 5;

        // Điều chỉnh nếu tràn phải
        if (left + menuWidth > window.innerWidth) {
            left = window.innerWidth - menuWidth - 10;
        }
        // Điều chỉnh nếu tràn dưới
        if (top + menuHeight > window.innerHeight) {
            top = rect.top - menuHeight - 5;
        }
        // Đảm bảo không âm
        if (left < 10) left = 10;
        if (top < 10) top = 10;

        menu.style.left = left + 'px';
        menu.style.top = top + 'px';

        // Hiển thị menu và overlay
        menu.classList.add('show');
        document.getElementById('menuOverlay').classList.add('show');
    });
});

// Đóng menu
function closeRoomMenu() {
    document.getElementById('roomActionMenu').classList.remove('show');
    document.getElementById('menuOverlay').classList.remove('show');
}

// Đóng menu khi nhấn Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRoomMenu();
    }
});

// Cập nhật trạng thái
function setStatus(status) {
    closeRoomMenu();
    document.getElementById('globalAction').value = 'update_status';
    document.getElementById('globalRoomId').value = currentRoom.id;
    document.getElementById('globalStatus').value = status;
    document.getElementById('globalActionForm').submit();
}
</script>

<?php include __DIR__ . '/components/footer.php'; ?>