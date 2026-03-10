<?php

session_start();
require_once '../includes/db_connect.php';

// Xử lý xóa tin nhắn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    header("Location: messages.php");
    exit;
}

// Lấy danh sách tin nhắn mới nhất
$messages = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();

include 'components/header.php';
?>

<div class="d-flex">
    <?php include 'components/sidebar.php'; ?>
    
    <div class="main-content flex-grow-1 p-4">
        <div class="d-flex justify-content-between mb-4">
            <h2>Hộp thư khách hàng</h2>
            <span class="badge bg-primary fs-6 align-self-center">
                Tổng: <?php echo count($messages); ?> tin nhắn
            </span>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">#</th>
                                <th width="20%">Khách hàng</th>
                                <th width="25%">Chủ đề</th>
                                <th width="30%">Nội dung tóm tắt</th>
                                <th width="10%">Thời gian</th>
                                <th width="10%">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $msg): ?>
                            <tr>
                                <td><?php echo $msg['id']; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($msg['name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($msg['email']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($msg['subject'] ?: 'Không có chủ đề'); ?>
                                </td>
                                <td>
                                    <span class="text-muted">
                                        <?php 
                                            $content = htmlspecialchars($msg['message']);
                                            echo strlen($content) > 50 ? substr($content, 0, 50) . '...' : $content; 
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo date('d/m/Y', strtotime($msg['created_at'])); ?></small>
                                    <br>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($msg['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-info text-white" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewMessageModal"
                                                onclick='viewMessage(<?php echo json_encode($msg); ?>)'>
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa tin nhắn này?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $msg['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (empty($messages)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3"></i><br>
                                    Chưa có tin nhắn nào từ khách hàng.
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

<!-- Modal Xem Chi Tiết Tin Nhắn -->
<div class="modal fade" id="viewMessageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết tin nhắn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 border-bottom pb-3">
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted d-block">Người gửi:</small>
                            <strong id="modalName" class="fs-5"></strong>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <small class="text-muted d-block">Thời gian:</small>
                            <span id="modalTime"></span>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">Email:</small> 
                        <a href="#" id="modalEmailLink" class="text-decoration-none">
                            <span id="modalEmail"></span>
                        </a>
                    </div>
                </div>
                
                <div class="mb-3">
                    <small class="text-muted d-block">Chủ đề:</small>
                    <h6 id="modalSubject" class="fw-bold text-primary"></h6>
                </div>

                <div class="bg-light p-3 rounded">
                    <small class="text-muted d-block mb-2">Nội dung:</small>
                    <p id="modalMessage" class="mb-0" style="white-space: pre-line;"></p>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="btnReply" class="btn btn-primary">
                    <i class="fas fa-reply"></i> Phản hồi qua Email
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewMessage(data) {
    document.getElementById('modalName').innerText = data.name;
    document.getElementById('modalEmail').innerText = data.email;
    document.getElementById('modalEmailLink').href = "mailto:" + data.email;
    document.getElementById('modalSubject').innerText = data.subject || '(Không có chủ đề)';
    document.getElementById('modalMessage').innerText = data.message;
    document.getElementById('modalTime').innerText = new Date(data.created_at).toLocaleString('vi-VN');
    
    // Cập nhật nút phản hồi
    document.getElementById('btnReply').href = "mailto:" + data.email + "?subject=Re: " + (data.subject || 'Liên hệ từ Vinpearl Cần Giờ');
}
</script>

<?php include 'components/footer.php'; ?>