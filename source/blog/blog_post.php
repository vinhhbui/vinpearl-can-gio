<?php
session_start(); // Khởi động session để kiểm tra đăng nhập
require_once '../includes/db_connect.php'; 

// 1. Lấy ID bài viết
$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
$stmt->execute([$id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header("Location: blog.php");
    exit();
}

// 2. Xử lý gửi bình luận (Chỉ khi đã đăng nhập)
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $content = trim($_POST['content']);
    
    // SỬA: Lấy username từ session làm tên hiển thị
    $user_name = $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'User'; 
    $user_email = $_SESSION['user_email'] ?? 'no-email@domain.com';

    if (!empty($content)) {
        // Bỏ cột is_anonymous trong câu lệnh INSERT
        $stmt = $pdo->prepare("INSERT INTO comments (news_id, user_name, user_email, content, status) VALUES (?, ?, ?, ?, 'pending')");
        if ($stmt->execute([$id, $user_name, $user_email, $content])) {
            $msg = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                        <strong class="font-bold">Thành công!</strong>
                        <span class="block sm:inline">Bình luận đã gửi và đang chờ duyệt.</span>
                    </div>';
        } else {
            $msg = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                        <span class="block sm:inline">Có lỗi xảy ra, vui lòng thử lại.</span>
                    </div>';
        }
    }
}

// 3. Lấy danh sách bình luận
$stmt = $pdo->prepare("SELECT * FROM comments WHERE news_id = ? AND status = 'approved' ORDER BY created_at DESC");
$stmt->execute([$id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = $post['title'];
include '../includes/header.php'; 
?>

<!-- Hero Section -->
<header class="relative h-[300px] md:h-[500px] flex items-center justify-center text-white text-center">
    <div class="absolute inset-0">
        <img src="<?php echo !empty($post['image_url']) ? '../' . $post['image_url'] : 'https://placehold.co/1920x600?text=Blog+Detail'; ?>" 
             class="w-full h-full object-cover">
    </div>
    <div class="absolute inset-0 bg-black bg-opacity-60"></div>
    <div class="relative z-10 p-4 container mx-auto">
        <span class="inline-block py-1 px-3 rounded bg-accent text-white text-sm font-bold mb-4">
            <?php echo htmlspecialchars($post['category']); ?>
        </span>
        <h1 class="text-3xl md:text-5xl font-serif font-bold mb-4 leading-tight">
            <?php echo htmlspecialchars($post['title']); ?>
        </h1>
        <p class="text-lg opacity-90">
            <i class="fas fa-calendar-alt mr-2"></i><?php echo date('d/m/Y', strtotime($post['created_at'])); ?>
        </p>
    </div>
</header>

<!-- Main Content -->
<main class="py-16 bg-white">
    <div class="container mx-auto px-4 max-w-4xl">
        
        <!-- Nội dung bài viết -->
        <article class="prose lg:prose-xl mx-auto text-gray-800 leading-relaxed mb-12 ck-content">
            <?php 
                // SỬA: Hiển thị trực tiếp nội dung HTML từ editor
                // Lưu ý: Chỉ làm điều này nếu bạn tin tưởng người quản trị (admin)
                echo $post['content']; 
            ?>
        </article>

        <div class="border-t border-b border-gray-200 py-6 flex justify-between items-center mb-12">
            <a href="blog.php" class="text-gray-600 hover:text-accent transition font-medium">&larr; Quay lại danh sách</a>
        </div>

        <!-- Khu vực Bình luận -->
        <div id="comments-section">
            <h3 class="text-2xl font-serif font-bold mb-8 text-gray-900">
                Bình luận (<?php echo count($comments); ?>)
            </h3>
            
            <!-- Danh sách bình luận -->
            <div class="space-y-8 mb-12">
                <?php if (count($comments) > 0): ?>
                    <?php foreach($comments as $cmt): ?>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-bold text-xl">
                                <?php echo strtoupper(substr($cmt['user_name'], 0, 1)); ?>
                            </div>
                        </div>
                        <div class="flex-grow bg-gray-50 p-4 rounded-lg rounded-tl-none">
                            <div class="flex justify-between items-center mb-2">
                                <h5 class="font-bold text-gray-900">
                                    <?php echo htmlspecialchars($cmt['user_name']); ?>
                                </h5>
                                <span class="text-xs text-gray-500"><?php echo date('d/m/Y H:i', strtotime($cmt['created_at'])); ?></span>
                            </div>
                            <p class="text-gray-700"><?php echo htmlspecialchars($cmt['content']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500 italic text-center py-4">Chưa có bình luận nào.</p>
                <?php endif; ?>
            </div>

            <!-- Form bình luận -->
            <div class="bg-white border border-gray-200 p-8 rounded-lg shadow-sm">
                <h4 class="text-xl font-bold mb-6 border-l-4 border-accent pl-3">Gửi bình luận của bạn</h4>
                
                <?php echo $msg; ?>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="POST" action="">
                        <div class="mb-4">
                            <div class="flex items-center gap-2 text-gray-600 mb-2">
                                <i class="fas fa-user-circle"></i>
                                <!-- SỬA: Hiển thị username trong form -->
                                <span>Đang bình luận với tên: <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></strong></span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2 font-medium">Nội dung <span class="text-red-500">*</span></label>
                            <textarea name="content" rows="4" required 
                                      class="w-full p-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-accent transition"></textarea>
                        </div>

                        <button type="submit" class="bg-accent text-white px-8 py-3 rounded hover:bg-accent-dark transition font-semibold shadow-md">
                            Gửi bình luận
                        </button>
                    </form>
                <?php else: ?>
                    <div class="text-center py-8 bg-gray-50 rounded border border-dashed border-gray-300">
                        <p class="text-gray-600 mb-4 text-lg">Vui lòng đăng nhập để tham gia thảo luận.</p>
                        <div class="flex justify-center gap-4">
                            <a href="../login.php?redirect=blog/blog_post.php?id=<?php echo $id; ?>" class="bg-accent text-white px-6 py-2 rounded hover:bg-accent-dark transition font-medium">Đăng nhập</a>
                            <a href="../register.php" class="text-accent border border-accent px-6 py-2 rounded hover:bg-accent hover:text-white transition font-medium">Đăng ký</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>