<?php

session_start();
require_once '../includes/db_connect.php';

$id = $_GET['id'] ?? null;
$news = ['title' => '', 'content' => '', 'category' => '', 'image_url' => ''];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
    $stmt->execute([$id]);
    $news = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $content = $_POST['content']; // Nội dung này giờ sẽ chứa HTML từ CKEditor
    $category = $_POST['category'];
    $image_url = $news['image_url'];

    // Xử lý upload ảnh đại diện (Featured Image)
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "../uploads/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        
        $filename = time() . '_' . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $filename;
        
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_url = "uploads/" . $filename;
        }
    }

    if ($id) {
        $sql = "UPDATE news SET title=?, content=?, category=?, image_url=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $content, $category, $image_url, $id]);
    } else {
        $sql = "INSERT INTO news (title, content, category, image_url) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $content, $category, $image_url]);
    }
    
    header("Location: news.php");
    exit();
}

include 'components/header.php';
?>

<!-- Thêm CSS để chỉnh chiều cao editor -->
<style>
    .ck-editor__editable_inline {
        min-height: 400px;
    }
</style>

<div class="d-flex">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content flex-grow-1 p-4 bg-light">
        <h2><?php echo $id ? 'Sửa bài viết' : 'Thêm bài viết mới'; ?></h2>
        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Tiêu đề bài viết</label>
                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($news['title']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nội dung chi tiết</label>
                                <!-- Textarea này sẽ được thay thế bởi CKEditor -->
                                <textarea name="content" id="editor" class="form-control"><?php echo htmlspecialchars($news['content']); ?></textarea>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Danh mục</label>
                                <select name="category" class="form-select">
                                    <option value="Tin tức" <?php echo $news['category'] == 'Tin tức' ? 'selected' : ''; ?>>Tin tức</option>
                                    <option value="Ẩm thực" <?php echo $news['category'] == 'Ẩm thực' ? 'selected' : ''; ?>>Ẩm thực</option>
                                    <option value="Sức khỏe" <?php echo $news['category'] == 'Sức khỏe' ? 'selected' : ''; ?>>Sức khỏe</option>
                                    <option value="Du lịch" <?php echo $news['category'] == 'Du lịch' ? 'selected' : ''; ?>>Du lịch</option>
                                    <option value="Sự kiện" <?php echo $news['category'] == 'Sự kiện' ? 'selected' : ''; ?>>Sự kiện</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Ảnh đại diện (Thumbnail)</label>
                                <input type="file" name="image" class="form-control">
                                <?php if($news['image_url']): ?>
                                    <div class="mt-2">
                                        <img src="../<?php echo htmlspecialchars($news['image_url']); ?>" class="img-thumbnail" style="max-width: 100%">
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <hr>
                            <button type="submit" class="btn btn-success w-100 py-2"><i class="fas fa-save"></i> Lưu bài viết</button>
                            <a href="news.php" class="btn btn-secondary w-100 mt-2">Hủy bỏ</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Nhúng CKEditor 5 từ CDN -->
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
<script>
    ClassicEditor
        .create(document.querySelector('#editor'), {
            // SỬA: Đổi từ ckfinder sang simpleUpload
            simpleUpload: {
                uploadUrl: 'upload_image.php'
            },
            // Cấu hình thanh công cụ (Toolbar)
            toolbar: [
                'heading', '|',
                'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|',
                'outdent', 'indent', '|',
                'imageUpload', 'blockQuote', 'insertTable', 'mediaEmbed', 'undo', 'redo'
            ],
            // Cấu hình xử lý ảnh (Căn chỉnh, resize)
            image: {
                toolbar: [
                    'imageTextAlternative', 'toggleImageCaption', 'imageStyle:inline',
                    'imageStyle:block', 'imageStyle:side'
                ]
            }
        })
        .catch(error => {
            console.error(error);
        });
</script>

<?php include 'components/footer.php'; ?>