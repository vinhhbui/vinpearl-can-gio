<?php

include __DIR__ . '/components/header.php';

// Xử lý cập nhật trạng thái
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $addon_id = (int)$_POST['addon_id'];
        $new_status = $_POST['status'];
        $staff_notes = trim($_POST['staff_notes'] ?? '');
        
        $stmt = $conn->prepare("UPDATE booking_addons SET status = ?, staff_notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $new_status, $staff_notes, $addon_id);
        
        if ($stmt->execute()) {
            $message = "Cập nhật trạng thái thành công!";
        } else {
            $error = "Lỗi: " . $conn->error;
        }
    }
    
    if ($_POST['action'] === 'delete_addon') {
        $addon_id = (int)$_POST['addon_id'];
        
        // Lấy thông tin addon trước khi xóa để cập nhật tổng tiền booking
        $addon_info = $conn->query("SELECT booking_id, addon_price, quantity FROM booking_addons WHERE id = $addon_id")->fetch_assoc();
        
        if ($addon_info) {
            $refund_amount = $addon_info['addon_price'] * $addon_info['quantity'];
            $booking_id = $addon_info['booking_id'];
            
            // Xóa addon
            $stmt = $conn->prepare("DELETE FROM booking_addons WHERE id = ?");
            $stmt->bind_param("i", $addon_id);
            
            if ($stmt->execute()) {
                // Cập nhật lại tổng tiền booking
                $conn->query("UPDATE bookings SET addons_total = addons_total - $refund_amount, total_price = total_price - $refund_amount WHERE id = $booking_id");
                $message = "Đã xóa dịch vụ và hoàn tiền " . number_format($refund_amount, 0, ',', '.') . " VNĐ!";
            } else {
                $error = "Lỗi: " . $conn->error;
            }
        }
    }
}

// Bộ lọc
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Query lấy danh sách dịch vụ bổ sung
$sql = "
    SELECT ba.*, 
           b.booking_reference, b.guest_name, b.guest_phone, b.guest_email,
           b.checkin_date, b.checkout_date, b.booking_status,
           rn.room_number,
           r.room_type_name
    FROM booking_addons ba
    JOIN bookings b ON ba.booking_id = b.id
    LEFT JOIN room_numbers rn ON b.room_number_id = rn.id
    LEFT JOIN rooms r ON b.room_id = r.id
    WHERE ba.addon_id = -1
";

if ($filter_status !== '') {
    $sql .= " AND ba.status = '" . $conn->real_escape_string($filter_status) . "'";
}
if ($filter_type !== '') {
    $sql .= " AND ba.addon_name LIKE '%" . $conn->real_escape_string($filter_type) . "%'";
}
if ($filter_date !== '') {
    $sql .= " AND DATE(ba.created_at) = '" . $conn->real_escape_string($filter_date) . "'";
}

$sql .= " ORDER BY 
    CASE ba.status 
        WHEN 'pending' THEN 1 
        WHEN 'confirmed' THEN 2 
        WHEN 'completed' THEN 3 
        WHEN 'cancelled' THEN 4 
    END,
    ba.created_at DESC";

$result = $conn->query($sql);

// Thống kê
$stats = [
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'total_revenue' => 0
];

$stats_query = $conn->query("
    SELECT status, SUM(addon_price * quantity) as revenue, COUNT(*) as count 
    FROM booking_addons 
    WHERE addon_id = -1 
    GROUP BY status
");
if ($stats_query) {
    while ($row = $stats_query->fetch_assoc()) {
        if (isset($stats[$row['status']])) {
            $stats[$row['status']] = $row['count'];
        }
        if ($row['status'] != 'cancelled') {
            $stats['total_revenue'] += $row['revenue'];
        }
    }
}

// Danh sách loại dịch vụ
$service_types = [
    'restaurant' => ['name' => 'Đặt bàn Nhà hàng', 'icon' => 'fa-utensils', 'color' => 'danger'],
    'spa' => ['name' => 'Đặt lịch Spa', 'icon' => 'fa-spa', 'color' => 'pink'],
    'tour' => ['name' => 'Tour du lịch', 'icon' => 'fa-binoculars', 'color' => 'success'],
    'motorbike' => ['name' => 'Thuê xe máy', 'icon' => 'fa-motorcycle', 'color' => 'info'],
    'vinwonders' => ['name' => 'Vé VinWonders', 'icon' => 'fa-ticket-alt', 'color' => 'purple']
];

$status_labels = [
    'pending' => ['name' => 'Chờ thanh toán', 'color' => 'warning'],
    'confirmed' => ['name' => 'Đã thanh toán', 'color' => 'info'],
    'completed' => ['name' => 'Hoàn thành', 'color' => 'success'],
    'cancelled' => ['name' => 'Đã hủy', 'color' => 'secondary']
];

// Hàm detect loại dịch vụ từ addon_name
function detectServiceType($addon_name) {
    $addon_lower = mb_strtolower($addon_name);
    if (strpos($addon_lower, 'nhà hàng') !== false || strpos($addon_lower, 'restaurant') !== false || strpos($addon_lower, 'đặt bàn') !== false) {
        return 'restaurant';
    }
    if (strpos($addon_lower, 'spa') !== false || strpos($addon_lower, 'massage') !== false) {
        return 'spa';
    }
    if (strpos($addon_lower, 'tour') !== false || strpos($addon_lower, 'cần giờ') !== false) {
        return 'tour';
    }
    if (strpos($addon_lower, 'xe máy') !== false || strpos($addon_lower, 'motorbike') !== false || strpos($addon_lower, 'thuê xe') !== false) {
        return 'motorbike';
    }
    if (strpos($addon_lower, 'vinwonders') !== false || strpos($addon_lower, 'vé') !== false) {
        return 'vinwonders';
    }
    return null;
}
?>

<div class="d-flex">
    <?php include __DIR__ . '/components/sidebar.php'; ?>
    
    <div class="main-content flex-grow-1 p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-concierge-bell"></i> Dịch vụ & Tiện ích bổ sung</h2>
                <p class="text-muted mb-0">Quản lý các dịch vụ đã đặt bởi khách hàng</p>
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
                            <small class="text-uppercase opacity-75">Chờ thanh toán</small>
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
                            <small class="text-uppercase opacity-75">Đã thanh toán</small>
                            <h3 class="mb-0"><?php echo $stats['confirmed']; ?></h3>
                        </div>
                        <i class="fas fa-check fa-2x opacity-50"></i>
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
                <div class="card bg-dark text-white h-100">
                    <div class="card-body d-flex justify-content-between align-items-center py-3">
                        <div>
                            <small class="text-uppercase opacity-75">Tổng doanh thu</small>
                            <h4 class="mb-0"><?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?></h4>
                        </div>
                        <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
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
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Loại dịch vụ</label>
                        <select name="type" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($service_types as $key => $val): ?>
                                <option value="<?php echo $val['name']; ?>" <?php echo $filter_type === $val['name'] ? 'selected' : ''; ?>>
                                    <?php echo $val['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Ngày đặt</label>
                        <input type="date" name="date" class="form-control" value="<?php echo $filter_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Lọc
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="amenity-bookings.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Danh sách dịch vụ -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Phòng</th>
                                <th>Khách hàng</th>
                                <th>Dịch vụ</th>
                                <th>Số lượng</th>
                                <th>Thành tiền</th>
                                <th>Trạng thái</th>
                                <th>Ngày đặt</th>
                                <th class="text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($addon = $result->fetch_assoc()): 
                                    $detected_type = detectServiceType($addon['addon_name']);
                                    $type_info = $detected_type ? $service_types[$detected_type] : ['icon' => 'fa-tag', 'color' => 'secondary', 'name' => 'Khác'];
                                    $status_info = $status_labels[$addon['status']] ?? $status_labels['pending'];
                                    $total_amount = $addon['addon_price'] * $addon['quantity'];
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $addon['id']; ?></strong></td>
                                    <td>
                                        <?php if ($addon['room_number']): ?>
                                            <span class="badge bg-dark fs-6"><?php echo $addon['room_number']; ?></span>
                                            <br><small class="text-muted"><?php echo $addon['room_type_name']; ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Chưa gán</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($addon['guest_name']); ?></div>
                                        <small class="text-muted"><?php echo $addon['guest_phone']; ?></small>
                                        <br><small class="badge bg-light text-dark"><?php echo $addon['booking_reference']; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $type_info['color']; ?> mb-1">
                                            <i class="fas <?php echo $type_info['icon']; ?>"></i> <?php echo $type_info['name']; ?>
                                        </span>
                                        <br>
                                        <small class="text-muted" style="max-width: 200px; display: block; white-space: normal;">
                                            <?php echo htmlspecialchars($addon['addon_name']); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?php echo $addon['quantity']; ?></span>
                                    </td>
                                    <td>
                                        <strong class="text-primary"><?php echo number_format($total_amount, 0, ',', '.'); ?> VNĐ</strong>
                                        <?php if ($addon['addon_price'] > 0 && $addon['quantity'] > 1): ?>
                                            <br><small class="text-muted"><?php echo number_format($addon['addon_price'], 0, ',', '.'); ?> x <?php echo $addon['quantity']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_info['color']; ?>">
                                            <?php echo $status_info['name']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo date('d/m/Y H:i', strtotime($addon['created_at'])); ?></small>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-light btn-addon-action"
                                            data-id="<?php echo $addon['id']; ?>"
                                            data-room="<?php echo $addon['room_number'] ?? ''; ?>"
                                            data-guest="<?php echo htmlspecialchars($addon['guest_name']); ?>"
                                            data-service="<?php echo htmlspecialchars($addon['addon_name']); ?>"
                                            data-amount="<?php echo number_format($total_amount, 0, ',', '.'); ?>"
                                            data-status="<?php echo $addon['status']; ?>"
                                            data-notes="<?php echo htmlspecialchars($addon['staff_notes'] ?? ''); ?>"
                                            data-booking-id="<?php echo $addon['booking_id']; ?>">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                        <p class="text-muted mb-0">Không có dịch vụ bổ sung nào</p>
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
<div id="addonActionMenu" class="addon-action-menu">
    <div class="menu-header">
        <span id="menuAddonTitle">Dịch vụ #1</span>
        <button type="button" class="btn-close btn-close-white btn-sm" onclick="closeAddonMenu()"></button>
    </div>
    <div class="menu-body">
        <div class="menu-info mb-3 p-2 bg-light rounded">
            <div id="menuAddonRoom"></div>
            <div id="menuAddonGuest"></div>
            <div id="menuAddonService" class="small text-muted mt-1"></div>
            <div id="menuAddonAmount" class="fw-bold text-primary mt-1"></div>
        </div>
        
        <div class="menu-section-title"><i class="fas fa-sync-alt"></i> Cập nhật trạng thái</div>
        <div class="menu-item" data-status="pending" onclick="updateAddonStatus('pending')">
            <i class="fas fa-clock text-warning"></i> Chờ thanh toán
        </div>
        <div class="menu-item" data-status="confirmed" onclick="updateAddonStatus('confirmed')">
            <i class="fas fa-check text-info"></i> Đã thanh toán
        </div>
        <div class="menu-item" data-status="completed" onclick="updateAddonStatus('completed')">
            <i class="fas fa-check-circle text-success"></i> Hoàn thành
        </div>
        <div class="menu-item" data-status="cancelled" onclick="updateAddonStatus('cancelled')">
            <i class="fas fa-times-circle text-secondary"></i> Hủy bỏ
        </div>
        
        <div class="menu-divider"></div>
        
        <div class="menu-item" onclick="openNotesModal()">
            <i class="fas fa-edit text-primary"></i> Thêm ghi chú
        </div>
        <a href="#" id="menuViewBooking" class="menu-item">
            <i class="fas fa-eye text-info"></i> Xem Booking
        </a>
        <div class="menu-item text-danger" onclick="deleteAddon()">
            <i class="fas fa-trash"></i> Xóa & Hoàn tiền
        </div>
    </div>
</div>

<div id="menuOverlay" class="menu-overlay" onclick="closeAddonMenu()"></div>

<!-- Form ẩn -->
<form id="globalActionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" id="globalAction">
    <input type="hidden" name="addon_id" id="globalAddonId">
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
                            <option value="pending">Chờ thanh toán</option>
                            <option value="confirmed">Đã thanh toán</option>
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

.addon-action-menu {
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
.addon-action-menu.show {
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
    max-height: 450px;
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

.btn-addon-action {
    border: 1px solid #dee2e6;
    transition: all 0.2s;
}
.btn-addon-action:hover {
    background-color: #e9ecef;
}

.bg-pink {
    background-color: #e91e8c !important;
    color: white !important;
}
.bg-purple {
    background-color: #6f42c1 !important;
}
</style>

<script>
let currentAddon = {
    id: null,
    status: '',
    notes: '',
    bookingId: null
};

// Gắn sự kiện cho nút thao tác
document.querySelectorAll('.btn-addon-action').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        
        currentAddon.id = this.dataset.id;
        currentAddon.status = this.dataset.status;
        currentAddon.notes = this.dataset.notes;
        currentAddon.bookingId = this.dataset.bookingId;
        
        document.getElementById('menuAddonTitle').textContent = 'Dịch vụ #' + currentAddon.id;
        document.getElementById('menuAddonRoom').innerHTML = this.dataset.room ? 
            '<i class="fas fa-door-open"></i> Phòng: <strong>' + this.dataset.room + '</strong>' : '';
        document.getElementById('menuAddonGuest').innerHTML = this.dataset.guest ? 
            '<i class="fas fa-user"></i> Khách: ' + this.dataset.guest : '';
        document.getElementById('menuAddonService').textContent = this.dataset.service;
        document.getElementById('menuAddonAmount').innerHTML = '<i class="fas fa-money-bill"></i> ' + this.dataset.amount + ' VNĐ';
        
        // Ẩn trạng thái hiện tại
        document.querySelectorAll('.menu-item[data-status]').forEach(function(item) {
            if (item.dataset.status === currentAddon.status) {
                item.classList.add('hidden');
            } else {
                item.classList.remove('hidden');
            }
        });
        
        // Cập nhật link booking
        document.getElementById('menuViewBooking').href = 'booking-detail.php?id=' + currentAddon.bookingId;
        
        // Tính vị trí menu
        const rect = this.getBoundingClientRect();
        const menu = document.getElementById('addonActionMenu');
        
        let left = rect.right - 300;
        let top = rect.bottom + 5;
        
        if (left < 10) left = 10;
        if (top + 450 > window.innerHeight) top = rect.top - 450;
        if (top < 10) top = 10;
        
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
        
        menu.classList.add('show');
        document.getElementById('menuOverlay').classList.add('show');
    });
});

function closeAddonMenu() {
    document.getElementById('addonActionMenu').classList.remove('show');
    document.getElementById('menuOverlay').classList.remove('show');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAddonMenu();
});

function updateAddonStatus(status) {
    closeAddonMenu();
    document.getElementById('globalAction').value = 'update_status';
    document.getElementById('globalAddonId').value = currentAddon.id;
    document.getElementById('globalStatus').value = status;
    document.getElementById('globalNotes').value = currentAddon.notes;
    document.getElementById('globalActionForm').submit();
}

function deleteAddon() {
    closeAddonMenu();
    if (confirm('Bạn có chắc chắn muốn xóa dịch vụ #' + currentAddon.id + '?\nSố tiền sẽ được hoàn lại vào tổng booking.')) {
        document.getElementById('globalAction').value = 'delete_addon';
        document.getElementById('globalAddonId').value = currentAddon.id;
        document.getElementById('globalActionForm').submit();
    }
}

function openNotesModal() {
    closeAddonMenu();
    document.getElementById('staffNotesInput').value = currentAddon.notes;
    document.getElementById('statusSelect').value = '';
    var myModal = new bootstrap.Modal(document.getElementById('notesModal'));
    myModal.show();
}

function saveNotes() {
    const notes = document.getElementById('staffNotesInput').value;
    const status = document.getElementById('statusSelect').value || currentAddon.status;
    
    document.getElementById('globalAction').value = 'update_status';
    document.getElementById('globalAddonId').value = currentAddon.id;
    document.getElementById('globalStatus').value = status;
    document.getElementById('globalNotes').value = notes;
    document.getElementById('globalActionForm').submit();
}
</script>

<?php include __DIR__ . '/components/footer.php'; ?>