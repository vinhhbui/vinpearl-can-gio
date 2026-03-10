<?php

session_start();
require_once '../includes/db_connect.php';

// Duyệt bình luận
if (isset($_GET['approve_id'])) {
    $stmt = $pdo->prepare("UPDATE comments SET status = 'approved' WHERE id = ?");
    $stmt->execute([$_GET['approve_id']]);
    header("Location: comments.php");
    exit();
}

// Xóa bình luận
if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->execute([$_GET['delete_id']]);
    header("Location: comments.php");
    exit();
}

// Lấy danh sách bình luận kèm tên bài viết
$sql = "SELECT c.*, n.title as news_title 
        FROM comments c 
        JOIN news n ON c.news_id = n.id 
        ORDER BY c.created_at DESC";
$comments = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

include 'components/header.php';
?>

<div class="d-flex">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content flex-grow-1 p-4 bg-light">
        <h2>Quản lý Bình luận</h2>
        <div class="card shadow-sm mt-3">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Người dùng</th>
                            <th>Nội dung</th>
                            <th>Bài viết</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comments as $cmt): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($cmt['user_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($cmt['user_email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($cmt['content']); ?></td>
                            <td><?php echo htmlspecialchars($cmt['news_title']); ?></td>
                            <td>
                                <?php if($cmt['status'] == 'approved'): ?>
                                    <span class="badge bg-success">Đã duyệt</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Chờ duyệt</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($cmt['status'] == 'pending'): ?>
                                    <a href="comments.php?approve_id=<?php echo $cmt['id']; ?>" class="btn btn-sm btn-success" title="Duyệt"><i class="fas fa-check"></i></a>
                                <?php endif; ?>
                                <a href="comments.php?delete_id=<?php echo $cmt['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Xóa bình luận này?')" title="Xóa"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'components/footer.php'; ?>