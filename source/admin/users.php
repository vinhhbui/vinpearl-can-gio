<?php
session_start();
require_once '../includes/db_connect.php';

// Xử lý Form Submit (Sửa/Xóa)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'edit') {
            // Cập nhật thông tin user
            $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, phone=?, ranking=? WHERE id=?");
            $stmt->execute([
                $_POST['username'], 
                $_POST['email'], 
                $_POST['phone'], 
                $_POST['ranking'], 
                $_POST['id']
            ]);
        } elseif ($_POST['action'] == 'delete') {
            // Xóa user (Cần cẩn thận vì có thể ảnh hưởng đến booking cũ)
            // Ở đây ta xóa trực tiếp, thực tế nên dùng soft delete (is_active = 0)
            $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
            $stmt->execute([$_POST['id']]);
        }
        header("Location: users.php");
        exit;
    }
}

// Lấy danh sách users
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
include 'components/header.php';
?>

<div class="d-flex">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content flex-grow-1 p-4">
        <div class="d-flex justify-content-between mb-4">
            <h2>Quản lý Tài khoản Khách hàng</h2>
            <!-- Nút thêm mới thường ít dùng cho user vì họ tự đăng ký, nhưng có thể thêm nếu cần -->
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Tên đăng nhập</th>
                            <th>Email</th>
                            <th>Số điện thoại</th>
                            <th>Hạng thành viên</th>
                            <th>Ngày tham gia</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>#<?php echo $u['id']; ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['phone'] ?? '---'); ?></td>
                            <td>
                                <?php 
                                    $badge_color = 'secondary';
                                    if ($u['ranking'] == 'Gold') $badge_color = 'warning text-dark';
                                    if ($u['ranking'] == 'Platinum') $badge_color = 'info text-dark';
                                    if ($u['ranking'] == 'Diamond') $badge_color = 'primary';
                                ?>
                                <span class="badge bg-<?php echo $badge_color; ?>">
                                    <?php echo htmlspecialchars($u['ranking'] ?? 'Standard'); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick='editUser(<?php echo json_encode($u); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tài khoản này? Hành động này không thể hoàn tác.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sửa User -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cập nhật thông tin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="userId">
                
                <div class="mb-3">
                    <label class="form-label">Tên đăng nhập</label>
                    <input type="text" name="username" id="userName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="userEmail" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" name="phone" id="userPhone" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Hạng thành viên</label>
                    <select name="ranking" id="userRanking" class="form-select">
                        <option value="Standard">Standard</option>
                        <option value="Silver">Silver</option>
                        <option value="Gold">Gold</option>
                        <option value="Platinum">Platinum</option>
                        <option value="Diamond">Diamond</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(user) {
    document.getElementById('userId').value = user.id;
    document.getElementById('userName').value = user.username;
    document.getElementById('userEmail').value = user.email;
    document.getElementById('userPhone').value = user.phone || '';
    document.getElementById('userRanking').value = user.ranking || 'Standard';
    
    new bootstrap.Modal(document.getElementById('userModal')).show();
}
</script>

<?php include 'components/footer.php'; ?>// filepath: d:\_apps\xampp\htdocs\admin\users.php
<?php
session_start();
require_once '../includes/db_connect.php';

// Xử lý Form Submit (Sửa/Xóa)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'edit') {
            // Cập nhật thông tin user
            $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, phone=?, ranking=? WHERE id=?");
            $stmt->execute([
                $_POST['username'], 
                $_POST['email'], 
                $_POST['phone'], 
                $_POST['ranking'], 
                $_POST['id']
            ]);
        } elseif ($_POST['action'] == 'delete') {
            // Xóa user (Cần cẩn thận vì có thể ảnh hưởng đến booking cũ)
            // Ở đây ta xóa trực tiếp, thực tế nên dùng soft delete (is_active = 0)
            $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
            $stmt->execute([$_POST['id']]);
        }
        header("Location: users.php");
        exit;
    }
}

// Lấy danh sách users
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
include 'components/header.php';
?>

<div class="d-flex">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content flex-grow-1 p-4">
        <div class="d-flex justify-content-between mb-4">
            <h2>Quản lý Tài khoản Khách hàng</h2>
            <!-- Nút thêm mới thường ít dùng cho user vì họ tự đăng ký, nhưng có thể thêm nếu cần -->
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Tên đăng nhập</th>
                            <th>Email</th>
                            <th>Số điện thoại</th>
                            <th>Hạng thành viên</th>
                            <th>Ngày tham gia</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>#<?php echo $u['id']; ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['phone'] ?? '---'); ?></td>
                            <td>
                                <?php 
                                    $badge_color = 'secondary';
                                    if ($u['ranking'] == 'Gold') $badge_color = 'warning text-dark';
                                    if ($u['ranking'] == 'Platinum') $badge_color = 'info text-dark';
                                    if ($u['ranking'] == 'Diamond') $badge_color = 'primary';
                                ?>
                                <span class="badge bg-<?php echo $badge_color; ?>">
                                    <?php echo htmlspecialchars($u['ranking'] ?? 'Standard'); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick='editUser(<?php echo json_encode($u); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tài khoản này? Hành động này không thể hoàn tác.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sửa User -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cập nhật thông tin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="userId">
                
                <div class="mb-3">
                    <label class="form-label">Tên đăng nhập</label>
                    <input type="text" name="username" id="userName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="userEmail" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" name="phone" id="userPhone" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Hạng thành viên</label>
                    <select name="ranking" id="userRanking" class="form-select">
                        <option value="Standard">Standard</option>
                        <option value="Silver">Silver</option>
                        <option value="Gold">Gold</option>
                        <option value="Platinum">Platinum</option>
                        <option value="Diamond">Diamond</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(user) {
    document.getElementById('userId').value = user.id;
    document.getElementById('userName').value = user.username;
    document.getElementById('userEmail').value = user.email;
    document.getElementById('userPhone').value = user.phone || '';
    document.getElementById('userRanking').value = user.ranking || 'Standard';
    
    new bootstrap.Modal(document.getElementById('userModal')).show();
}
</script>

<?php include 'components/footer.php'; ?>