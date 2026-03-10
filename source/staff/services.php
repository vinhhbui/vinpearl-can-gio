<?php

include __DIR__ . '/components/header.php';

// Xử lý cập nhật trạng thái
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $request_id = (int)$_POST['request_id'];
        $new_status = $_POST['status'];
        $staff_notes = trim($_POST['staff_notes'] ?? '');
        
        $completed_at = ($new_status === 'completed') ? ", completed_at = NOW()" : "";
        
        $stmt = $conn->prepare("UPDATE room_service_requests SET request_status = ?, staff_notes = ?, updated_at = NOW() $completed_at WHERE id = ?");
        $stmt->bind_param("ssi", $new_status, $staff_notes, $request_id);
        
        if ($stmt->execute()) {
            $message = "Cập nhật trạng thái thành công!";
        } else {
            $error = "Lỗi: " . $conn->error;
        }
    }
    
    if ($_POST['action'] === 'delete_request') {
        $request_id = (int)$_POST['request_id'];
        $stmt = $conn->prepare("DELETE FROM room_service_requests WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        
        if ($stmt->execute()) {
            $message = "Đã xóa yêu cầu thành công!";
        } else {
            $error = "Lỗi: " . $conn->error;
        }
    }
}

// Bộ lọc
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : '';

// Query - sử dụng bảng room_service_requests
$sql = "
    SELECT rsr.*, 
           b.booking_reference, b.guest_name, b.guest_phone,
           rn.room_number,
           r.room_type_name
    FROM room_service_requests rsr
    LEFT JOIN bookings b ON rsr.booking_id = b.id
    LEFT JOIN room_numbers rn ON b.room_number_id = rn.id
    LEFT JOIN rooms r ON b.room_id = r.id
    WHERE 1=1
";

if ($filter_status !== '') {
    $sql .= " AND rsr.request_status = '" . $conn->real_escape_string($filter_status) . "'";
}
if ($filter_type !== '') {
    $sql .= " AND rsr.service_type = '" . $conn->real_escape_string($filter_type) . "'";
}
if ($filter_date !== '') {
    $sql .= " AND DATE(rsr.created_at) = '" . $conn->real_escape_string($filter_date) . "'";
}
if ($filter_priority !== '') {
    $sql .= " AND rsr.priority = '" . $conn->real_escape_string($filter_priority) . "'";
}

$sql .= " ORDER BY 
    CASE rsr.priority 
        WHEN 'emergency' THEN 1 
        WHEN 'urgent' THEN 2 
        WHEN 'normal' THEN 3 
    END,
    CASE rsr.request_status 
        WHEN 'pending' THEN 1 
        WHEN 'in_progress' THEN 2 
        WHEN 'completed' THEN 3 
        WHEN 'cancelled' THEN 4 
    END,
    rsr.created_at DESC";

$result = $conn->query($sql);

// Thống kê
$stats = [
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'urgent' => 0
];

$stats_query = $conn->query("SELECT request_status, priority, COUNT(*) as count FROM room_service_requests GROUP BY request_status, priority");
if ($stats_query) {
    while ($row = $stats_query->fetch_assoc()) {
        if (isset($stats[$row['request_status']])) {
            $stats[$row['request_status']] += $row['count'];
        }
        if (($row['priority'] == 'urgent' || $row['priority'] == 'emergency') && $row['request_status'] == 'pending') {
            $stats['urgent'] += $row['count'];
        }
    }
}

// Danh sách loại dịch vụ (theo bảng room_service_requests)
$service_types = [
    'housekeeping' => ['name' => 'Dọn phòng', 'icon' => 'fa-broom', 'color' => 'info'],
    'food_delivery' => ['name' => 'Đặt đồ ăn', 'icon' => 'fa-utensils', 'color' => 'danger'],
    'beverages' => ['name' => 'Đồ uống', 'icon' => 'fa-glass-water', 'color' => 'primary'],
    'maintenance' => ['name' => 'Sửa chữa', 'icon' => 'fa-tools', 'color' => 'warning'],
    'laundry' => ['name' => 'Giặt ủi', 'icon' => 'fa-tshirt', 'color' => 'success'],
    'extra_amenities' => ['name' => 'Vật dụng thêm', 'icon' => 'fa-box', 'color' => 'secondary'],
    'wake_up_call' => ['name' => 'Gọi thức', 'icon' => 'fa-bell', 'color' => 'dark'],
    'other' => ['name' => 'Khác', 'icon' => 'fa-ellipsis-h', 'color' => 'secondary']
];

$status_labels = [
    'pending' => ['name' => 'Chờ xử lý', 'color' => 'warning'],
    'in_progress' => ['name' => 'Đang xử lý', 'color' => 'info'],
    'completed' => ['name' => 'Hoàn thành', 'color' => 'success'],
    'cancelled' => ['name' => 'Đã hủy', 'color' => 'secondary']
];

$priority_labels = [
    'normal' => ['name' => 'Bình thường', 'color' => 'primary'],
    'urgent' => ['name' => 'Khẩn cấp', 'color' => 'warning'],
    'emergency' => ['name' => 'Rất khẩn cấp', 'color' => 'danger']
];
?>

<div class="d-flex">
    <?php include __DIR__ . '/components/sidebar.php'; ?>
    
    <div class="main-content flex-grow-1 p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-concierge-bell"></i> Yêu cầu dịch vụ phòng</h2>
                <p class="text-muted mb-0">Quản lý các yêu cầu dịch vụ từ khách hàng đang lưu trú</p>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Thống kê -->
        <div class="row mb-4 g-3">
            <div class="col">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body d-flex justify-content-between align-items-center py-3">
                        <div>
                            <small class="text-uppercase opacity-75">Chờ xử lý</small>
                            <h3 class="mb-0"><?php echo $stats['pending']; ?></h3>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card bg-info text-white h-100">
                    <div class="card-body d-flex justify-content-between align-items-center py-3">
                        <div>
                            <small class="text-uppercase opacity-75">Đang xử lý</small>
                            <h3 class="mb-0"><?php echo $stats['in_progress']; ?></h3>
                        </div>
                        <i class="fas fa-spinner fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card bg-success text-white h-100">
                    <div class="card-body d-flex justify-content-between align-items-center py-3">
                        <div>
                            <small class="text-uppercase opacity-75">Hoàn thành</small>
                            <h3 class="mb-0"><?php echo $stats['completed']; ?></h3>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card bg-danger text-white h-100">
                    <div class="card-body d-flex justify-content-between align-items-center py-3">
                        <div>
                            <small class="text-uppercase opacity-75">Khẩn cấp</small>
                            <h3 class="mb-0"><?php echo $stats['urgent']; ?></h3>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bộ lọc -->
        <div class="card mb-4">
            <div class="card-body bg-light">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($status_labels as $key => $val): ?>
                                <option value="<?php echo $key; ?>" <?php echo $filter_status === $key ? 'selected' : ''; ?>>
                                    <?php echo $val['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Loại dịch vụ</label>
                        <select name="type" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($service_types as $key => $val): ?>
                                <option value="<?php echo $key; ?>" <?php echo $filter_type === $key ? 'selected' : ''; ?>>
                                    <?php echo $val['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Độ ưu tiên</label>
                        <select name="priority" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($priority_labels as $key => $val): ?>
                                <option value="<?php echo $key; ?>" <?php echo $filter_priority === $key ? 'selected' : ''; ?>>
                                    <?php echo $val['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Ngày tạo</label>
                        <input type="date" name="date" class="form-control" value="<?php echo $filter_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Lọc
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="services.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Danh sách yêu cầu -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Phòng</th>
                                <th>Khách hàng</th>
                                <th>Loại dịch vụ</th>
                                <th>Nội dung</th>
                                <th>Thời gian mong muốn</th>
                                <th>Độ ưu tiên</th>
                                <th>Trạng thái</th>
                                <th>Thời gian tạo</th>
                                <th class="text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($req = $result->fetch_assoc()): 
                                    $type_info = $service_types[$req['service_type']] ?? $service_types['other'];
                                    $status_info = $status_labels[$req['request_status']] ?? $status_labels['pending'];
                                    $priority_info = $priority_labels[$req['priority']] ?? $priority_labels['normal'];
                                ?>
                                <tr class="<?php echo ($req['priority'] == 'urgent' || $req['priority'] == 'emergency') && $req['request_status'] == 'pending' ? 'table-danger' : ''; ?>">
                                    <td><strong>#<?php echo $req['id']; ?></strong></td>
                                    <td>
                                        <?php if ($req['room_number']): ?>
                                            <span class="badge bg-dark fs-6"><?php echo $req['room_number']; ?></span>
                                            <br><small class="text-muted"><?php echo $req['room_type_name']; ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($req['guest_name']): ?>
                                            <div class="fw-bold"><?php echo htmlspecialchars($req['guest_name']); ?></div>
                                            <small class="text-muted"><?php echo $req['guest_phone']; ?></small>
                                            <br><small class="badge bg-light text-dark"><?php echo $req['booking_reference']; ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $type_info['color']; ?>">
                                            <i class="fas <?php echo $type_info['icon']; ?>"></i> <?php echo $type_info['name']; ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 200px;">
                                        <div class="text-truncate" title="<?php echo htmlspecialchars($req['description']); ?>">
                                            <?php echo htmlspecialchars($req['description']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($req['preferred_time']): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($req['preferred_time'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Ngay lập tức</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $priority_info['color']; ?>">
                                            <?php if ($req['priority'] == 'emergency'): ?>
                                                <i class="fas fa-exclamation-circle"></i>
                                            <?php elseif ($req['priority'] == 'urgent'): ?>
                                                <i class="fas fa-exclamation"></i>
                                            <?php endif; ?>
                                            <?php echo $priority_info['name']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_info['color']; ?>">
                                            <?php echo $status_info['name']; ?>
                                        </span>
                                        <?php if ($req['completed_at']): ?>
                                            <br><small class="text-success">
                                                <i class="fas fa-check"></i> <?php echo date('H:i d/m', strtotime($req['completed_at'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo date('d/m/Y H:i', strtotime($req['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-light btn-request-action"
                                            data-id="<?php echo $req['id']; ?>"
                                            data-room="<?php echo $req['room_number'] ?? ''; ?>"
                                            data-guest="<?php echo htmlspecialchars($req['guest_name'] ?? ''); ?>"
                                            data-type="<?php echo $req['service_type']; ?>"
                                            data-description="<?php echo htmlspecialchars($req['description']); ?>"
                                            data-priority="<?php echo $req['priority']; ?>"
                                            data-status="<?php echo $req['request_status']; ?>"
                                            data-notes="<?php echo htmlspecialchars($req['staff_notes'] ?? ''); ?>"
                                            data-booking-id="<?php echo $req['booking_id'] ?? ''; ?>">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                        <p class="text-muted mb-0">Không có yêu cầu dịch vụ nào</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Menu thao tác -->
<div id="requestActionMenu" class="request-action-menu">
    <div class="menu-header">
        <span id="menuRequestTitle">Yêu cầu #1</span>
        <button type="button" class="btn-close btn-close-white btn-sm" onclick="closeRequestMenu()"></button>
    </div>
    <div class="menu-body">
        <div class="menu-info mb-3 p-2 bg-light rounded">
            <div id="menuRequestRoom"></div>
            <div id="menuRequestGuest"></div>
            <div id="menuRequestDesc" class="small text-muted mt-1"></div>
        </div>
        
        <div class="menu-section-title"><i class="fas fa-sync-alt"></i> Cập nhật trạng thái</div>
        <div class="menu-item" data-status="pending" onclick="updateStatus('pending')">
            <i class="fas fa-clock text-warning"></i> Chờ xử lý
        </div>
        <div class="menu-item" data-status="in_progress" onclick="updateStatus('in_progress')">
            <i class="fas fa-spinner text-info"></i> Đang xử lý
        </div>
        <div class="menu-item" data-status="completed" onclick="updateStatus('completed')">
            <i class="fas fa-check-circle text-success"></i> Hoàn thành
        </div>
        <div class="menu-item" data-status="cancelled" onclick="updateStatus('cancelled')">
            <i class="fas fa-times-circle text-secondary"></i> Hủy bỏ
        </div>
        
        <div class="menu-divider"></div>
        
        <div class="menu-item" onclick="openNotesModal()">
            <i class="fas fa-edit text-primary"></i> Thêm ghi chú
        </div>
        <a href="#" id="menuViewBooking" class="menu-item" style="display: none;">
            <i class="fas fa-eye text-info"></i> Xem Booking
        </a>
        <div class="menu-item text-danger" onclick="deleteRequest()">
            <i class="fas fa-trash"></i> Xóa yêu cầu
        </div>
    </div>
</div>

<div id="menuOverlay" class="menu-overlay" onclick="closeRequestMenu()"></div>

<!-- Form ẩn -->
<form id="globalActionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" id="globalAction">
    <input type="hidden" name="request_id" id="globalRequestId">
    <input type="hidden" name="status" id="globalStatus">
    <input type="hidden" name="staff_notes" id="globalNotes">
</form>

<!-- Modal Ghi chú -->
<div class="modal fade" id="notesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Ghi chú nhân viên</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="notesForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ghi chú</label>
                        <textarea id="staffNotesInput" class="form-control" rows="4" placeholder="Nhập ghi chú xử lý..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Cập nhật trạng thái</label>
                        <select id="statusSelect" class="form-select">
                            <option value="">-- Giữ nguyên --</option>
                            <option value="pending">Chờ xử lý</option>
                            <option value="in_progress">Đang xử lý</option>
                            <option value="completed">Hoàn thành</option>
                            <option value="cancelled">Hủy bỏ</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary w-100" onclick="saveNotes()">
                        <i class="fas fa-save"></i> Lưu
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
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

.request-action-menu {
    display: none;
    position: fixed;
    z-index: 9999;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    min-width: 280px;
    max-width: 320px;
    overflow: hidden;
    animation: menuSlideIn 0.2s ease;
}
.request-action-menu.show {
    display: block;
}

@keyframes menuSlideIn {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
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
    padding: 10px;
    max-height: 400px;
    overflow-y: auto;
}

.menu-section-title {
    font-size: 0.75rem;
    color: #6c757d;
    padding: 8px 10px 5px;
    font-weight: 600;
    text-transform: uppercase;
}

.menu-item {
    display: flex;
    align-items: center;
    padding: 10px;
    cursor: pointer;
    color: #333;
    text-decoration: none;
    border-radius: 6px;
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

.menu-info {
    font-size: 0.9rem;
}

.btn-request-action {
    border: 1px solid #dee2e6;
    transition: all 0.2s;
}
.btn-request-action:hover {
    background-color: #e9ecef;
}
</style>

<script>
let currentRequest = {
    id: null,
    status: '',
    notes: '',
    bookingId: null
};

// Gắn sự kiện cho nút thao tác
document.querySelectorAll('.btn-request-action').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        
        currentRequest.id = this.dataset.id;
        currentRequest.status = this.dataset.status;
        currentRequest.notes = this.dataset.notes;
        currentRequest.bookingId = this.dataset.bookingId;
        
        document.getElementById('menuRequestTitle').textContent = 'Yêu cầu #' + currentRequest.id;
        document.getElementById('menuRequestRoom').innerHTML = this.dataset.room ? 
            '<i class="fas fa-door-open"></i> Phòng: <strong>' + this.dataset.room + '</strong>' : '';
        document.getElementById('menuRequestGuest').innerHTML = this.dataset.guest ? 
            '<i class="fas fa-user"></i> Khách: ' + this.dataset.guest : '';
        document.getElementById('menuRequestDesc').textContent = this.dataset.description;
        
        // Ẩn trạng thái hiện tại
        document.querySelectorAll('.menu-item[data-status]').forEach(function(item) {
            if (item.dataset.status === currentRequest.status) {
                item.classList.add('hidden');
            } else {
                item.classList.remove('hidden');
            }
        });
        
        // Hiển thị link booking nếu có
        const bookingLink = document.getElementById('menuViewBooking');
        if (currentRequest.bookingId) {
            bookingLink.style.display = 'flex';
            bookingLink.href = 'booking-detail.php?id=' + currentRequest.bookingId;
        } else {
            bookingLink.style.display = 'none';
        }
        
        // Tính vị trí menu
        const rect = this.getBoundingClientRect();
        const menu = document.getElementById('requestActionMenu');
        
        let left = rect.right - 300;
        let top = rect.bottom + 5;
        
        if (left < 10) left = 10;
        if (top + 400 > window.innerHeight) top = rect.top - 400;
        if (top < 10) top = 10;
        
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
        
        menu.classList.add('show');
        document.getElementById('menuOverlay').classList.add('show');
    });
});

function closeRequestMenu() {
    document.getElementById('requestActionMenu').classList.remove('show');
    document.getElementById('menuOverlay').classList.remove('show');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeRequestMenu();
});

function updateStatus(status) {
    closeRequestMenu();
    document.getElementById('globalAction').value = 'update_status';
    document.getElementById('globalRequestId').value = currentRequest.id;
    document.getElementById('globalStatus').value = status;
    document.getElementById('globalNotes').value = currentRequest.notes;
    document.getElementById('globalActionForm').submit();
}

function deleteRequest() {
    closeRequestMenu();
    if (confirm('Bạn có chắc chắn muốn xóa yêu cầu #' + currentRequest.id + '?')) {
        document.getElementById('globalAction').value = 'delete_request';
        document.getElementById('globalRequestId').value = currentRequest.id;
        document.getElementById('globalActionForm').submit();
    }
}

function openNotesModal() {
    closeRequestMenu();
    document.getElementById('staffNotesInput').value = currentRequest.notes;
    document.getElementById('statusSelect').value = '';
    var myModal = new bootstrap.Modal(document.getElementById('notesModal'));
    myModal.show();
}

function saveNotes() {
    const notes = document.getElementById('staffNotesInput').value;
    const status = document.getElementById('statusSelect').value || currentRequest.status;
    
    document.getElementById('globalAction').value = 'update_status';
    document.getElementById('globalRequestId').value = currentRequest.id;
    document.getElementById('globalStatus').value = status;
    document.getElementById('globalNotes').value = notes;
    document.getElementById('globalActionForm').submit();
}
</script>

<?php include __DIR__ . '/components/footer.php'; ?>