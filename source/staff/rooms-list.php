<?php

include __DIR__ . '/components/header.php';

// --- 1. XỬ LÝ LOGIC (Giống rooms.php) ---

// Xử lý cập nhật trạng thái / Xóa / Sửa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Cập nhật trạng thái
    if ($_POST['action'] === 'update_status') {
        $room_number_id = (int)$_POST['room_number_id'];
        $new_status = $_POST['status'];
        $stmt = $conn->prepare("UPDATE room_numbers SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $room_number_id);
        if ($stmt->execute()) $message = "Cập nhật trạng thái thành công!";
        else $error = "Lỗi: " . $conn->error;
    }
    
    // Xóa phòng
    if ($_POST['action'] === 'delete_room') {
        $room_number_id = (int)$_POST['room_number_id'];
        $check = $conn->query("SELECT id FROM bookings WHERE room_number_id = $room_number_id AND booking_status IN ('checked_in', 'confirmed')");
        if ($check->num_rows > 0) {
            $error = "Không thể xóa phòng đang có khách hoặc có lịch đặt!";
        } else {
            $conn->query("DELETE FROM room_numbers WHERE id = $room_number_id");
            $message = "Đã xóa phòng thành công!";
        }
    }

    // Sửa phòng
    if ($_POST['action'] === 'edit_room') {
        $id = (int)$_POST['room_number_id'];
        $num = $_POST['room_number'];
        $fl = (int)$_POST['floor'];
        $rid = (int)$_POST['room_id'];
        $stmt = $conn->prepare("UPDATE room_numbers SET room_number = ?, floor = ?, room_id = ? WHERE id = ?");
        $stmt->bind_param("siii", $num, $fl, $rid, $id);
        if ($stmt->execute()) $message = "Cập nhật thông tin phòng thành công!";
    }
}

// --- 2. BỘ LỌC & TRUY VẤN ---
$filter_floor = isset($_GET['floor']) ? $_GET['floor'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$filter_date_in = isset($_GET['date_in']) && $_GET['date_in'] !== '' ? $_GET['date_in'] : $today;
$filter_date_out = isset($_GET['date_out']) && $_GET['date_out'] !== '' ? $_GET['date_out'] : $tomorrow;

// Query chính - thêm booking_status
if ($filter_date_in && $filter_date_out) {
    $sql = "
        SELECT rn.*, r.room_type_name, r.price_per_night,
               b.id as current_booking_id,
               b.guest_name,
               b.checkin_date,
               b.checkout_date,
               b.booking_reference,
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
               b.booking_reference,
               b.booking_status
        FROM room_numbers rn
        JOIN rooms r ON rn.room_id = r.id
        LEFT JOIN bookings b ON rn.id = b.room_number_id 
            AND b.booking_status IN ('checked_in', 'confirmed')
    ";
}

$where_clauses = [];
if ($filter_floor !== '') $where_clauses[] = "rn.floor = " . (int)$filter_floor;
if ($filter_type !== '') $where_clauses[] = "rn.room_id = " . (int)$filter_type;

// LOGIC LỌC TRẠNG THÁI - Giống rooms.php
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

if (!empty($where_clauses)) $sql .= " WHERE " . implode(' AND ', $where_clauses);

$sql .= " ORDER BY rn.floor ASC, rn.room_number ASC";
$result = $conn->query($sql);

// Thống kê - Giống rooms.php
$stats_result = $conn->query("
    SELECT rn.*, 
           b.id as current_booking_id,
           b.booking_status
    FROM room_numbers rn
    LEFT JOIN bookings b ON rn.id = b.room_number_id 
        AND b.booking_status IN ('checked_in', 'confirmed')
");

$stats = [
    'available' => 0,
    'checked_in' => 0,
    'confirmed' => 0,
    'dirty' => 0,
    'cleaning' => 0,
    'maintenance' => 0
];

while ($stat_room = $stats_result->fetch_assoc()) {
    if ($stat_room['current_booking_id']) {
        if ($stat_room['booking_status'] == 'checked_in') {
            $stats['checked_in']++;
        } else {
            $stats['confirmed']++;
        }
    } else {
        if (isset($stats[$stat_room['status']])) {
            $stats[$stat_room['status']]++;
        }
    }
}

// Dropdown data
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
            <h2><i class="fas fa-list"></i> Danh sách phòng</h2>
            <div>
                <a href="rooms.php" class="btn btn-outline-primary me-2"><i class="fas fa-th"></i> Chế độ sơ đồ</a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal"><i class="fas fa-plus"></i> Thêm phòng</button>
            </div>
        </div>

        <!-- BỘ LỌC -->
        <div class="card mb-4">
            <div class="card-body bg-light">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Tầng</label>
                        <select name="floor" class="form-select">
                            <option value="">Tất cả</option>
                            <?php while($f = $floors_list->fetch_assoc()): ?>
                                <option value="<?php echo $f['floor']; ?>" <?php echo $filter_floor == $f['floor'] ? 'selected' : ''; ?>>Tầng <?php echo $f['floor']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Loại phòng</label>
                        <select name="type" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach($room_types_array as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo $filter_type == $t['id'] ? 'selected' : ''; ?>><?php echo $t['room_type_name']; ?></option>
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
                        <label class="form-label fw-bold">Kiểm tra lịch (Từ - Đến)</label>
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

        <!-- BẢNG DANH SÁCH -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Phòng</th>
                                <th>Tầng</th>
                                <th>Loại phòng</th>
                                <th>Trạng thái</th>
                                <th>Khách hiện tại</th>
                                <th>Thời gian</th>
                                <th class="text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($room = $result->fetch_assoc()): 
                                    $status_badge = '<span class="badge bg-success">Sẵn sàng</span>';
                                    $is_occupied = false;
                                    $is_checked_in = false;
                                    
                                    if ($room['current_booking_id']) {
                                        $is_occupied = true;
                                        if ($room['booking_status'] == 'checked_in') {
                                            $status_badge = '<span class="badge bg-danger">Đang ở</span>';
                                            $is_checked_in = true;
                                        } else {
                                            $status_badge = '<span class="badge" style="background-color: #fd7e14;">Đã đặt</span>';
                                        }
                                    } elseif ($room['status'] == 'dirty') {
                                        $status_badge = '<span class="badge" style="background-color: #8B4513;">Cần dọn</span>';
                                    } elseif ($room['status'] == 'cleaning') {
                                        $status_badge = '<span class="badge bg-warning text-dark">Đang dọn</span>';
                                    } elseif ($room['status'] == 'maintenance') {
                                        $status_badge = '<span class="badge bg-secondary">Bảo trì</span>';
                                    }
                                ?>
                                <tr>
                                    <td><strong><?php echo $room['room_number']; ?></strong></td>
                                    <td><?php echo $room['floor']; ?></td>
                                    <td><?php echo $room['room_type_name']; ?></td>
                                    <td>
                                        <?php echo $status_badge; ?>
                                        <?php if ($is_occupied): ?>
                                            <br><small class="badge <?php echo $is_checked_in ? 'bg-dark' : 'bg-warning text-dark'; ?> mt-1">
                                                <?php echo $is_checked_in ? 'CHECK-IN' : 'CHỜ ĐẾN'; ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_occupied): ?>
                                            <div class="fw-bold"><?php echo $room['guest_name']; ?></div>
                                            <small class="text-muted"><?php echo $room['booking_reference']; ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_occupied): ?>
                                            <small>
                                                <?php echo date('d/m', strtotime($room['checkin_date'])); ?> - 
                                                <?php echo date('d/m', strtotime($room['checkout_date'])); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-light btn-room-action"
                                            data-room-id="<?php echo $room['id']; ?>"
                                            data-room-number="<?php echo $room['room_number']; ?>"
                                            data-room-floor="<?php echo $room['floor']; ?>"
                                            data-room-type-id="<?php echo $room['room_id']; ?>"
                                            data-room-status="<?php echo $room['status']; ?>"
                                            data-is-occupied="<?php echo $is_occupied ? '1' : '0'; ?>"
                                            data-booking-id="<?php echo $room['current_booking_id'] ?? ''; ?>">
                                            <i class="fas fa-ellipsis-v"></i> Thao tác
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center py-4">Không tìm thấy phòng nào.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MENU DROPDOWN TỰ BUILD - ĐẶT TRONG BODY -->
<div id="roomActionMenu" class="room-action-menu">
    <div class="menu-header">
        <span id="menuRoomTitle">Phòng 101</span>
        <button type="button" class="btn-close btn-close-white btn-sm" onclick="closeRoomMenu()"></button>
    </div>
    <div class="menu-body">
        <!-- Section cho phòng có khách -->
        <div id="menuOccupiedSection">
            <div class="menu-section-title"><i class="fas fa-user"></i> Đang có khách</div>
            <a href="#" id="menuViewBooking" class="menu-item">
                <i class="fas fa-eye text-primary"></i> Xem Booking
            </a>
        </div>

        <!-- Section cho phòng trống -->
        <div id="menuAvailableSection">
            <a href="#" id="menuCreateBooking" class="menu-item">
                <i class="fas fa-plus-circle text-primary"></i> Tạo Booking
            </a>
            <div class="menu-divider"></div>
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
    </div>
</div>

<!-- Overlay khi menu mở -->
<div id="menuOverlay" class="menu-overlay" onclick="closeRoomMenu()"></div>

<!-- FORM ẨN ĐỂ SUBMIT -->
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
                                <option value="<?php echo $t['id']; ?>"><?php echo $t['room_type_name']; ?></option>
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
                <form method="POST">
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
                                <option value="<?php echo $t['id']; ?>"><?php echo $t['room_type_name']; ?></option>
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
/* MENU THAO TÁC TỰ BUILD */
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
    max-height: 400px;
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
    color: #333;
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

.btn-room-action {
    border: 1px solid #dee2e6;
    transition: all 0.2s;
}
.btn-room-action:hover {
    background-color: #e9ecef;
    border-color: #adb5bd;
}
</style>

<script>
// Biến lưu thông tin phòng đang chọn
let currentRoom = {
    id: null,
    number: '',
    floor: null,
    typeId: null,
    status: '',
    isOccupied: false,
    bookingId: null
};

// Gắn sự kiện cho tất cả nút thao tác
document.querySelectorAll('.btn-room-action').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        
        // Lưu thông tin phòng
        currentRoom.id = this.dataset.roomId;
        currentRoom.number = this.dataset.roomNumber;
        currentRoom.floor = this.dataset.roomFloor;
        currentRoom.typeId = this.dataset.roomTypeId;
        currentRoom.status = this.dataset.roomStatus;
        currentRoom.isOccupied = this.dataset.isOccupied === '1';
        currentRoom.bookingId = this.dataset.bookingId;

        // Cập nhật title menu
        document.getElementById('menuRoomTitle').textContent = 'Phòng ' + currentRoom.number;

        // Hiển thị/ẩn sections dựa trên trạng thái phòng
        if (currentRoom.isOccupied) {
            document.getElementById('menuOccupiedSection').style.display = 'block';
            document.getElementById('menuAvailableSection').style.display = 'none';
            document.getElementById('menuViewBooking').href = 'booking-detail.php?id=' + currentRoom.bookingId;
        } else {
            document.getElementById('menuOccupiedSection').style.display = 'none';
            document.getElementById('menuAvailableSection').style.display = 'block';
            document.getElementById('menuCreateBooking').href = 'booking-create.php?room_number_id=' + currentRoom.id;
            
            // Ẩn trạng thái hiện tại
            document.querySelectorAll('.menu-item[data-status]').forEach(function(item) {
                if (item.dataset.status === currentRoom.status) {
                    item.classList.add('hidden');
                } else {
                    item.classList.remove('hidden');
                }
            });
        }

        // Tính vị trí menu
        const rect = this.getBoundingClientRect();
        const menu = document.getElementById('roomActionMenu');
        const menuWidth = 250;
        const menuHeight = 400;

        let left = rect.right - menuWidth;
        let top = rect.bottom + 5;

        // Điều chỉnh nếu tràn phải
        if (left < 10) {
            left = rect.left;
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
