<?php
session_start();
require_once '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $stmt = $pdo->prepare("INSERT INTO promotions (code, title, discount_type, discount_value, min_amount, max_discount_amount, usage_limit, usage_limit_per_user, valid_from, valid_to, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$_POST['code'], $_POST['title'], $_POST['type'], $_POST['value'], $_POST['min_amount'], $_POST['max_discount_amount'], $_POST['usage_limit'], $_POST['usage_limit_per_user'], $_POST['from'], $_POST['to']]);
        } elseif ($_POST['action'] == 'edit') {
            // Logic sửa mã giảm giá
            $stmt = $pdo->prepare("UPDATE promotions SET code=?, title=?, discount_type=?, discount_value=?, min_amount=?, max_discount_amount=?, usage_limit=?, usage_limit_per_user=?, valid_from=?, valid_to=? WHERE id=?");
            $stmt->execute([$_POST['code'], $_POST['title'], $_POST['type'], $_POST['value'], $_POST['min_amount'], $_POST['max_discount_amount'], $_POST['usage_limit'], $_POST['usage_limit_per_user'], $_POST['from'], $_POST['to'], $_POST['id']]);
        } elseif ($_POST['action'] == 'delete') {
            $stmt = $pdo->prepare("DELETE FROM promotions WHERE id=?");
            $stmt->execute([$_POST['id']]);
        }
        header("Location: promotions.php");
        exit;
    }
}

$promos = $pdo->query("SELECT * FROM promotions ORDER BY id DESC")->fetchAll();
include 'components/header.php';
?>

<div class="d-flex">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content flex-grow-1 p-4">
        <div class="d-flex justify-content-between mb-4">
            <h2>Quản lý Mã Giảm Giá</h2>
            <!-- Thêm hàm resetForm để xóa dữ liệu cũ khi bấm tạo mới -->
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#promoModal" onclick="resetForm()">
                <i class="fas fa-plus"></i> Tạo Mã Mới
            </button>
        </div>

        <table class="table table-striped bg-white align-middle">
            <thead>
                <tr>
                    <th>Mã Code</th>
                    <th>Tên chương trình</th>
                    <th>Giảm giá</th>
                    <th>Điều kiện</th> <!-- Cột mới hiển thị điều kiện áp dụng -->
                    <th>Hiệu lực</th>
                    <th>Trạng thái</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($promos as $p): ?>
                <tr>
                    <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($p['code']); ?></span></td>
                    <td><?php echo htmlspecialchars($p['title']); ?></td>
                    <td>
                        <strong class="text-danger">
                            <?php echo $p['discount_type'] == 'percentage' ? $p['discount_value'].'%' : number_format($p['discount_value']).' đ'; ?>
                        </strong>
                    </td>
                    <td>
                        <!-- Hiển thị điều kiện rõ ràng -->
                        <small class="d-block text-muted">Min: <?php echo number_format($p['min_amount']); ?> đ</small>
                        <?php if(($p['max_discount_amount'] ?? 0) > 0): ?>
                            <small class="d-block text-muted">Max giảm: <?php echo number_format($p['max_discount_amount']); ?> đ</small>
                        <?php endif; ?>
                        <?php if(($p['usage_limit'] ?? 0) > 0): ?>
                            <small class="d-block text-muted">Giới hạn: <?php echo number_format($p['usage_limit']); ?> lượt</small>
                        <?php endif; ?>
                        <?php if(($p['usage_limit_per_user'] ?? 0) > 0): ?>
                            <small class="d-block text-muted">Max/người: <?php echo number_format($p['usage_limit_per_user']); ?> lượt</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small><?php echo date('d/m/Y', strtotime($p['valid_from'])); ?> <br> đến <?php echo date('d/m/Y', strtotime($p['valid_to'])); ?></small>
                    </td>
                    <td>
                        <?php if(strtotime($p['valid_to']) < time()): ?>
                            <span class="badge bg-secondary">Hết hạn</span>
                        <?php else: ?>
                            <span class="badge bg-success">Đang chạy</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <!-- Nút Sửa: Gọi hàm JS editPromo -->
                            <button class="btn btn-sm btn-warning" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#promoModal"
                                    onclick='editPromo(<?php echo json_encode($p); ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>

                            <form method="POST" onsubmit="return confirm('Xóa mã này?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Thêm/Sửa Mã -->
<div class="modal fade" id="promoModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="promoForm">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tạo Mã Khuyến Mãi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Input hidden để chứa ID khi sửa và Action -->
                <input type="hidden" name="id" id="p_id">
                <input type="hidden" name="action" id="p_action" value="add">

                <div class="mb-3">
                    <label>Mã Code (VD: SUMMER2024)</label>
                    <input type="text" name="code" id="p_code" class="form-control text-uppercase" required>
                </div>
                <div class="mb-3">
                    <label>Tên chương trình</label>
                    <input type="text" name="title" id="p_title" class="form-control" required>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label>Loại giảm</label>
                        <select name="type" id="p_type" class="form-select">
                            <option value="percentage">Phần trăm (%)</option>
                            <option value="fixed">Số tiền cố định</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label>Giá trị</label>
                        <input type="number" name="value" id="p_value" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label>Đơn tối thiểu (VNĐ)</label>
                    <input type="number" name="min_amount" id="p_min" class="form-control" value="0">
                </div>
                <div class="mb-3">
                    <label>Giảm tối đa (VNĐ) (Chỉ áp dụng cho %)</label>
                    <input type="number" name="max_discount_amount" id="p_max" class="form-control" value="0" placeholder="Nhập 0 nếu không giới hạn">
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label>Tổng lượt dùng tối đa</label>
                        <input type="number" name="usage_limit" id="p_limit" class="form-control" value="0" placeholder="0 = KGH">
                    </div>
                    <div class="col-6">
                        <label>Lượt dùng tối đa/người</label>
                        <input type="number" name="usage_limit_per_user" id="p_limit_user" class="form-control" value="1" placeholder="0 = KGH">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <label>Từ ngày</label>
                        <input type="date" name="from" id="p_from" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label>Đến ngày</label>
                        <input type="date" name="to" id="p_to" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary" id="btnSubmit">Lưu mã</button>
            </div>
        </form>
    </div>
</div>

<script>
// Hàm reset form về trạng thái thêm mới
function resetForm() {
    document.getElementById('promoForm').reset();
    document.getElementById('p_action').value = 'add';
    document.getElementById('p_id').value = '';
    document.getElementById('modalTitle').innerText = 'Tạo Mã Khuyến Mãi';
    document.getElementById('btnSubmit').innerText = 'Tạo mã';
}

// Hàm điền dữ liệu vào form để sửa
function editPromo(data) {
    document.getElementById('p_action').value = 'edit';
    document.getElementById('p_id').value = data.id;
    document.getElementById('p_code').value = data.code;
    document.getElementById('p_title').value = data.title;
    document.getElementById('p_type').value = data.discount_type;
    document.getElementById('p_value').value = data.discount_value;
    document.getElementById('p_min').value = data.min_amount;
    document.getElementById('p_max').value = data.max_discount_amount || 0;
    document.getElementById('p_limit').value = data.usage_limit || 0;
    document.getElementById('p_limit_user').value = data.usage_limit_per_user || 1;
    document.getElementById('p_from').value = data.valid_from;
    document.getElementById('p_to').value = data.valid_to;

    document.getElementById('modalTitle').innerText = 'Cập Nhật Mã Khuyến Mãi';
    document.getElementById('btnSubmit').innerText = 'Cập nhật';
}
</script>

<?php include 'components/footer.php'; ?>