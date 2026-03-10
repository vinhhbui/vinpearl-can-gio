<?php
session_start();
require_once '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $stmt = $pdo->prepare("INSERT INTO standard_addons (name, price, price_type, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$_POST['name'], $_POST['price'], $_POST['price_type']]);
        } elseif ($_POST['action'] == 'delete') {
            $stmt = $pdo->prepare("DELETE FROM standard_addons WHERE id=?");
            $stmt->execute([$_POST['id']]);
        }
        header("Location: services.php");
        exit;
    }
}

$services = $pdo->query("SELECT * FROM standard_addons")->fetchAll();
include 'components/header.php';
?>

<div class="d-flex">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content flex-grow-1 p-4">
        <div class="d-flex justify-content-between mb-4">
            <h2>Quản lý Dịch vụ (Add-ons)</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#serviceModal">
                <i class="fas fa-plus"></i> Thêm Dịch vụ
            </button>
        </div>

        <table class="table table-hover bg-white">
            <thead>
                <tr>
                    <th>Tên dịch vụ</th>
                    <th>Giá (VNĐ)</th>
                    <th>Cách tính giá</th>
                    <th>Trạng thái</th>
                    <th>Xóa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $s): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                    <td><?php echo number_format($s['price'], 0, ',', '.'); ?></td>
                    <td>
                        <?php 
                        $types = [
                            'per_person' => 'Theo người',
                            'per_night' => 'Theo đêm',
                            'per_booking' => 'Một lần/Booking',
                            'per_person_per_night' => 'Người/Đêm'
                        ];
                        echo $types[$s['price_type']] ?? $s['price_type']; 
                        ?>
                    </td>
                    <td>
                        <span class="badge bg-<?php echo $s['is_active'] ? 'success' : 'secondary'; ?>">
                            <?php echo $s['is_active'] ? 'Hoạt động' : 'Ẩn'; ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Xóa dịch vụ này?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Thêm Dịch vụ -->
<div class="modal fade" id="serviceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm Dịch vụ Mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="mb-3">
                    <label>Tên dịch vụ (VD: Đưa đón sân bay)</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Giá tiền (VNĐ)</label>
                    <input type="number" name="price" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Cách tính giá</label>
                    <select name="price_type" class="form-select">
                        <option value="per_booking">Một lần cho cả Booking</option>
                        <option value="per_person">Theo số lượng người</option>
                        <option value="per_night">Theo số đêm</option>
                        <option value="per_person_per_night">Theo người & đêm</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Lưu dịch vụ</button>
            </div>
        </form>
    </div>
</div>
<?php include 'components/footer.php'; ?>