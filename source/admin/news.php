<?php

session_start();
require_once '../includes/db_connect.php';

// Xử lý xóa bài viết
if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
    $stmt->execute([$_GET['delete_id']]);
    header("Location: news.php");
    exit();
}

// Lấy danh sách tin tức
$stmt = $pdo->query("SELECT * FROM news ORDER BY created_at DESC");
$news_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'components/header.php';
?>

<div class="d-flex">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content flex-grow-1 p-4 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Quản lý Tin tức</h2>
            <a href="news_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Thêm bài viết</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Hình ảnh</th>
                            <th>Tiêu đề</th>
                            <th>Danh mục</th>
                            <th>Ngày đăng</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($news_list as $news): ?>
                        <tr>
                            <td>
                                <?php if($news['image_url']): ?>
                                    <img src="../<?php echo htmlspecialchars($news['image_url']); ?>" width="60" height="40" class="object-fit-cover rounded">
                                <?php else: ?>
                                    <span class="text-muted">No img</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($news['title']); ?></td>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($news['category']); ?></span></td>
                            <td><?php echo date('d/m/Y', strtotime($news['created_at'])); ?></td>
                            <td>
                                <a href="news_form.php?id=<?php echo $news['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                <a href="news.php?delete_id=<?php echo $news['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc muốn xóa?')"><i class="fas fa-trash"></i></a>
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