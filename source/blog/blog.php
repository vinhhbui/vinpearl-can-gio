<?php
// Đặt tiêu đề cho trang này
$page_title = 'Blog & Tin tức - Vinpearl Cần Giờ';

// SỬA ĐƯỜNG DẪN INCLUDE (Thêm ../ để ra thư mục gốc)
include '../includes/header.php';
require_once '../includes/db_connect.php'; // Kết nối DB

// Phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 6;
$offset = ($page - 1) * $limit;

// Tìm kiếm & Lọc
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND title LIKE ?";
    $params[] = "%$search%";
}
if ($category) {
    $where .= " AND category = ?";
    $params[] = $category;
}

// Lấy tổng số bài để phân trang
$stmt = $pdo->prepare("SELECT COUNT(*) FROM news $where");
$stmt->execute($params);
$total_posts = $stmt->fetchColumn();
$total_pages = ceil($total_posts / $limit);

// Lấy danh sách bài viết
$sql = "SELECT * FROM news $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách chuyên mục (để hiển thị sidebar)
$cats = $pdo->query("SELECT category, COUNT(*) as count FROM news GROUP BY category")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Page Header -->
<header class="relative h-[300px] md:h-[400px] flex items-center justify-center text-white text-center">
    <!-- Background Image -->
    <div class="absolute inset-0">
        <img src="../images/index/news.png" 
             alt="Our Blog" 
             class="w-full h-full object-cover">
    </div>
    <!-- Overlay -->
    <div class="absolute inset-0 bg-black bg-opacity-60"></div>
    
    <!-- Content -->
    <div class="relative z-10 p-4">
        <h1 class="text-4xl md:text-6xl font-serif font-bold mb-4">Tin tức & Sự kiện</h1>
        <p class="text-lg md:text-xl max-w-2xl mx-auto">Cập nhật những tin tức, ưu đãi và cẩm nang du lịch mới nhất</p>
    </div>
</header>

<!-- Blog List Section -->
<main id="blog-list" class="py-24 bg-white">
    <div class="container mx-auto px-4">
        <div class="flex flex-col lg:flex-row gap-12">
            
            <!-- Main Blog Grid (2/3 width) -->
            <div class="lg:w-2/3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <?php if(count($posts) > 0): ?>
                        <?php foreach($posts as $post): ?>
                        <div class="border border-gray-200 rounded-lg shadow-lg overflow-hidden transform hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 flex flex-col h-full">
                            <!-- SỬA: Thêm ../ vào trước đường dẫn ảnh -->
                            <img src="<?php echo !empty($post['image_url']) ? '../' . $post['image_url'] : 'https://placehold.co/600x400?text=No+Image'; ?>" 
                                 alt="<?php echo htmlspecialchars($post['title']); ?>" 
                                 class="w-full h-64 object-cover">
                            <div class="p-6 flex flex-col flex-grow">
                                <div class="text-sm text-gray-500 mb-2">
                                    <?php echo date('M d, Y', strtotime($post['created_at'])); ?> | <?php echo htmlspecialchars($post['category']); ?>
                                </div>
                                <h3 class="text-2xl font-serif font-semibold text-gray-900 mb-3 line-clamp-2">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </h3>
                                <p class="text-gray-600 mb-4 line-clamp-3 flex-grow">
                                    <?php echo htmlspecialchars(substr(strip_tags($post['content']), 0, 150)) . '...'; ?>
                                </p>
                                <a href="blog_post.php?id=<?php echo $post['id']; ?>" class="font-semibold text-accent hover:text-accent-dark transition mt-auto">Đọc thêm &rarr;</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-2 text-center py-10 text-gray-500">Không tìm thấy bài viết nào.</div>
                    <?php endif; ?>
                </div>

                <!-- Phân trang -->
                <?php if($total_pages > 1): ?>
                <div class="mt-16 text-center">
                    <nav class="inline-flex rounded-md shadow-sm -space-x-px">
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium 
                               <?php echo $i == $page ? 'bg-accent text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar (1/3 width) -->
            <aside class="lg:w-1/3">
                <div class="sticky top-24">
                    <!-- Search -->
                    <div class="bg-gray-50 p-6 rounded-lg shadow-md mb-8">
                        <h4 class="text-xl font-serif font-semibold text-gray-900 mb-4">Tìm kiếm</h4>
                        <form class="flex" method="GET" action="blog.php">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nhập từ khóa..." class="w-full p-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-2 focus:ring-accent">
                            <button type="submit" class="bg-accent text-white p-2 rounded-r-md hover:bg-accent-dark transition">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            </button>
                        </form>
                    </div>

                    <!-- Categories -->
                    <div class="bg-gray-50 p-6 rounded-lg shadow-md mb-8">
                        <h4 class="text-xl font-serif font-semibold text-gray-900 mb-4">Chuyên mục</h4>
                        <ul class="space-y-2">
                            <li><a href="blog.php" class="text-gray-600 hover:text-accent transition">Tất cả</a></li>
                            <?php foreach($cats as $cat): ?>
                            <li>
                                <a href="blog.php?category=<?php echo urlencode($cat['category']); ?>" class="text-gray-600 hover:text-accent transition">
                                    <?php echo htmlspecialchars($cat['category']); ?> (<?php echo $cat['count']; ?>)
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </aside>

        </div>
    </div>
</main>

<?php
// Gọi footer
include '../includes/footer.php';
?>