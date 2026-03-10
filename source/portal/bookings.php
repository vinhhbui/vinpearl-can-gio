<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

// Lấy email của user
$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Xử lý các filter
$status_filter = $_GET['status'] ?? 'all';
$search_query = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'newest';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where_conditions = ["b.guest_email = ?"];
$params = [$user['email']];

if ($status_filter !== 'all') {
    $where_conditions[] = "b.booking_status = ?";
    $params[] = $status_filter;
}

if (!empty($search_query)) {
    $where_conditions[] = "(b.booking_reference LIKE ? OR r.room_type_name LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$where_clause = implode(' AND ', $where_conditions);

// Build ORDER BY clause
$order_clause = match($sort_by) {
    'oldest' => 'b.created_at ASC',
    'checkin_asc' => 'b.checkin_date ASC',
    'checkin_desc' => 'b.checkin_date DESC',
    'price_high' => 'b.total_price DESC',
    'price_low' => 'b.total_price ASC',
    default => 'b.created_at DESC'
};

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM bookings b 
              JOIN rooms r ON b.room_id = r.id 
              WHERE $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_bookings = $stmt->fetchColumn();
$total_pages = ceil($total_bookings / $per_page);

// Get bookings with pagination
$sql = "SELECT b.*, r.room_type_name, r.image_url,
        (SELECT image_url FROM room_images WHERE room_id = b.room_id AND is_primary = 1 LIMIT 1) as primary_image
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.id 
        WHERE $where_clause
        ORDER BY $order_clause
        LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN booking_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN booking_status = 'checked_in' THEN 1 ELSE 0 END) as checked_in,
    SUM(CASE WHEN booking_status = 'checked_out' THEN 1 ELSE 0 END) as checked_out,
    SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,

    SUM(total_price) as total_spent
    FROM bookings WHERE (user_id = ? OR guest_email = ?) and (booking_status = 'checked_out' OR booking_status = 'checked_in' OR booking_status = 'confirmed')";
$stmt = $pdo->prepare($stats_sql);
$stmt->execute([$user_id, $user['email']]);
$stats = $stmt->fetch();

$page_title = 'Đơn đặt phòng của tôi - Cổng khách hàng';
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<!-- Breadcrumb -->
<div class="bg-gray-900 py-10">
    <div class="container mx-auto px-4">
        <nav class="text-sm">
            <ol class="list-none p-0 inline-flex items-center text-gray-300">
                <li class="flex items-center">
                    <a href="../index.php" class="hover:text-white transition">Trang chủ</a>
                    <svg class="fill-current w-3 h-3 mx-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
                </li>
                <li class="flex items-center">
                    <a href="index.php" class="hover:text-white transition">Cổng khách hàng</a>
                    <svg class="fill-current w-3 h-3 mx-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
                </li>
                <li>
                    <span class="text-white font-bold">Đơn đặt phòng</span>
                </li>
            </ol>
        </nav>
    </div>
</div>

<div class="min-h-screen bg-gray-50 py-12">
    <div class="container mx-auto px-4">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-serif font-bold text-gray-900 mb-2">Đơn đặt phòng của tôi</h1>
            <p class="text-gray-600">Quản lý và theo dõi tất cả các đơn đặt phòng của bạn</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500 mb-1">Tổng đơn</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total']; ?></p>
            </div>
            <div class="bg-yellow-50 rounded-lg shadow p-4">
                <p class="text-sm text-yellow-700 mb-1">Chờ xác nhận</p>
                <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['pending']; ?></p>
            </div>
            <div class="bg-green-50 rounded-lg shadow p-4">
                <p class="text-sm text-green-700 mb-1">Đã xác nhận</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $stats['confirmed']; ?></p>
            </div>
            <div class="bg-blue-50 rounded-lg shadow p-4">
                <p class="text-sm text-blue-700 mb-1">Đã nhận phòng</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo $stats['checked_in']; ?></p>
            </div>
            <div class="bg-purple-50 rounded-lg shadow p-4">
                <p class="text-sm text-purple-700 mb-1">Hoàn thành</p>
                <p class="text-2xl font-bold text-purple-600"><?php echo $stats['checked_out']; ?></p>
            </div>
            <div class="bg-red-50 rounded-lg shadow p-4">
                <p class="text-sm text-red-700 mb-1">Đã hủy</p>
                <p class="text-2xl font-bold text-red-600"><?php echo $stats['cancelled']; ?></p>
            </div>
        </div>

        <!-- Total Spent -->
        <div class="bg-gradient-to-r from-accent to-accent-dark text-white rounded-lg p-6 mb-8 shadow-xl">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm opacity-90 mb-1">Tổng chi tiêu</p>
                    <p class="text-3xl font-bold"><?php echo number_format($stats['total_spent'], 0, ',', '.'); ?> VNĐ</p>
                </div>
                <div class="bg-white bg-opacity-20 p-4 rounded-full">
                    <i class="fas fa-wallet text-3xl"></i>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tìm kiếm</label>
                        <input 
                            type="text" 
                            name="search" 
                            value="<?php echo htmlspecialchars($search_query); ?>"
                            placeholder="Mã đặt phòng hoặc loại phòng..."
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent"
                        >
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Trạng thái</label>
                        <select 
                            name="status"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent"
                        >
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Chờ xác nhận</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Đã xác nhận</option>
                            <option value="checked_in" <?php echo $status_filter === 'checked_in' ? 'selected' : ''; ?>>Đã nhận phòng</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Hoàn thành</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                        </select>
                    </div>

                    <!-- Sort By -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Sắp xếp</label>
                        <select 
                            name="sort"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent"
                        >
                            <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                            <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Cũ nhất</option>
                            <option value="checkin_asc" <?php echo $sort_by === 'checkin_asc' ? 'selected' : ''; ?>>Ngày nhận phòng (tăng dần)</option>
                            <option value="checkin_desc" <?php echo $sort_by === 'checkin_desc' ? 'selected' : ''; ?>>Ngày nhận phòng (giảm dần)</option>
                            <option value="price_high" <?php echo $sort_by === 'price_high' ? 'selected' : ''; ?>>Giá (cao đến thấp)</option>
                            <option value="price_low" <?php echo $sort_by === 'price_low' ? 'selected' : ''; ?>>Giá (thấp đến cao)</option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="bg-accent text-white px-6 py-2 rounded-lg hover:bg-accent-dark transition font-semibold">
                        <i class="fas fa-filter mr-2"></i>Áp dụng
                    </button>
                    <a href="bookings.php" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 transition font-semibold">
                        <i class="fas fa-redo mr-2"></i>Đặt lại
                    </a>
                </div>
            </form>
        </div>

        <!-- Bookings List -->
        <?php if (empty($bookings)): ?>
            <div class="bg-white rounded-lg shadow-lg p-12 text-center">
                <svg class="w-20 h-20 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Không tìm thấy đơn đặt phòng</h3>
                <p class="text-gray-600 mb-6">Thử thay đổi bộ lọc hoặc tìm kiếm khác</p>
                <a href="../booking/rooms.php" class="inline-block bg-accent text-white px-8 py-3 rounded-lg hover:bg-accent-dark transition font-semibold">
                    Đặt phòng mới
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($bookings as $booking): 
                    $status_colors = [
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'confirmed' => 'bg-green-100 text-green-800',
                        'checked_in' => 'bg-blue-100 text-blue-800',
                        'completed' => 'bg-purple-100 text-purple-800',
                        'cancelled' => 'bg-red-100 text-red-800'
                    ];
                    $status_color = $status_colors[$booking['booking_status']] ?? 'bg-gray-100 text-gray-800';
                    
                    $status_labels = [
                        'pending' => 'Chờ xác nhận',
                        'confirmed' => 'Đã xác nhận',
                        'checked_in' => 'Đã nhận phòng',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Đã hủy'
                    ];
                    $status_label = $status_labels[$booking['booking_status']] ?? $booking['booking_status'];
                    
                    // Xử lý đường dẫn ảnh
                    $rawImg = $booking['primary_image'] ?? $booking['image_url'] ?? '';
                    if ($rawImg && !preg_match("~^(?:f|ht)tps?://~i", $rawImg)) {
                        $image_url = '../' . $rawImg;
                    } else {
                        $image_url = $rawImg ?: 'https://placehold.co/400x300/e8dcc4/888?text=Room';
                    }
                ?>
                <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition">
                    <div class="md:flex">
                        <!-- Image -->
                        <div class="md:w-1/4">
                            <img src="<?php echo htmlspecialchars($image_url); ?>" alt="Room" class="w-full h-full object-cover">
                        </div>
                        
                        <!-- Content -->
                        <div class="md:w-3/4 p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 mb-1">
                                        <?php echo htmlspecialchars($booking['room_type_name']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-barcode mr-1"></i>
                                        Mã: <span class="font-mono font-semibold"><?php echo htmlspecialchars($booking['booking_reference']); ?></span>
                                    </p>
                                </div>
                                <span class="px-4 py-2 <?php echo $status_color; ?> rounded-full text-sm font-semibold">
                                    <?php echo $status_label; ?>
                                </span>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                <div>
                                    <p class="text-xs text-gray-500 mb-1"><i class="fas fa-calendar-check mr-1"></i>Nhận phòng</p>
                                    <p class="font-semibold text-gray-900"><?php echo date('d/m/Y', strtotime($booking['checkin_date'])); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 mb-1"><i class="fas fa-calendar-times mr-1"></i>Trả phòng</p>
                                    <p class="font-semibold text-gray-900"><?php echo date('d/m/Y', strtotime($booking['checkout_date'])); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 mb-1"><i class="fas fa-users mr-1"></i>Khách</p>
                                    <p class="font-semibold text-gray-900"><?php echo ($booking['num_adults'] + $booking['num_children']); ?> người</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 mb-1"><i class="fas fa-calendar-alt mr-1"></i>Số đêm</p>
                                    <p class="font-semibold text-gray-900">
                                        <?php 
                                        $checkin = new DateTime($booking['checkin_date']);
                                        $checkout = new DateTime($booking['checkout_date']);
                                        echo $checkin->diff($checkout)->days; 
                                        ?> đêm
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-gray-200">
                                <div>
                                    <p class="text-sm text-gray-500">Tổng tiền</p>
                                    <p class="text-2xl font-bold text-accent">
                                        <?php echo number_format($booking['total_price'], 0, ',', '.'); ?> VNĐ
                                    </p>
                                </div>
                                <div class="flex gap-2">
                                    <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" 
                                       class="bg-accent text-white px-6 py-2 rounded-lg hover:bg-accent-dark transition font-semibold text-sm">
                                        <i class="fas fa-eye mr-2"></i>Chi tiết
                                    </a>
                                    <?php if ($booking['booking_status'] === 'confirmed' && strtotime($booking['checkin_date']) > time()): ?>
                                    <a href="online_checkin.php?booking_id=<?php echo $booking['id']; ?>" 
                                       class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition font-semibold text-sm">
                                        <i class="fas fa-check-circle mr-2"></i>Check-in
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>&sort=<?php echo $sort_by; ?>" 
                       class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>&sort=<?php echo $sort_by; ?>" 
                       class="px-4 py-2 <?php echo $i === $page ? 'bg-accent text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-lg transition">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>&sort=<?php echo $sort_by; ?>" 
                       class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>

            <p class="text-center text-gray-600 mt-4">
                Hiển thị <?php echo ($offset + 1); ?>-<?php echo min($offset + $per_page, $total_bookings); ?> 
                trong tổng số <?php echo $total_bookings; ?> đơn
            </p>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<?php include '../includes/footer.php'; ?>