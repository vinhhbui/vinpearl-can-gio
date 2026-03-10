<?php
session_start();
require_once '../includes/db_connect.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];

// Lấy thông tin user để kiểm tra email
$stmt_user = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user_email = $stmt_user->fetchColumn();

// Lấy thông tin Booking + Room - Kiểm tra theo cả user_id VÀ guest_email
$stmt = $pdo->prepare("
    SELECT b.*, r.room_type_name, r.image_url, r.bed_type, r.room_size_sqm 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    WHERE b.id = ? AND (b.user_id = ? OR b.guest_email = ?)
");
$stmt->execute([$booking_id, $user_id, $user_email]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: index.php'); // Hoặc trang 404
    exit;
}

// Lấy thông tin Add-ons (Dịch vụ)
$stmt_addons = $pdo->prepare("SELECT * FROM booking_addons WHERE booking_id = ?");
$stmt_addons->execute([$booking_id]);
$addons = $stmt_addons->fetchAll(PDO::FETCH_ASSOC);

$stmt_addons_after = $pdo->prepare("SELECT * FROM booking_addons WHERE booking_id = ? AND addon_id = -1");
$stmt_addons_after->execute([$booking_id]);
$addons_after = $stmt_addons_after->fetchAll(PDO::FETCH_ASSOC);

// --- THÊM MỚI: Lấy tiện nghi phòng ---
$stmt_features = $pdo->prepare("SELECT feature_name, icon FROM room_features WHERE room_id = ?");
$stmt_features->execute([$booking['room_id']]);
$room_features = $stmt_features->fetchAll(PDO::FETCH_ASSOC);
// --- KẾT THÚC THÊM MỚI ---

// --- THÊM MỚI: Tạo dữ liệu cho QR Code ---
$qr_data = json_encode([
    'booking_ref' => $booking['booking_reference'],
    'guest_name' => $booking['guest_name'],
    'guest_email' => $booking['guest_email'],
    'room_type' => $booking['room_type_name'],
    'checkin' => $booking['checkin_date'],
    'checkout' => $booking['checkout_date'],
    'adults' => $booking['num_adults'],
    'children' => $booking['num_children']
]);

// Tạo QR Code URL sử dụng API miễn phí
$qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qr_data);
// --- KẾT THÚC THÊM MỚI ---

$page_title = 'Chi tiết Đặt phòng #' . $booking['booking_reference'];
include '../includes/header.php';
?>

<div class="bg-gray-100 min-h-screen py-12">
    <div class="container mx-auto px-4">
        <div class="mb-6 flex items-center text-sm text-gray-600">
            <a href="index.php" class="hover:text-accent">Cổng khách hàng</a>
            <i class="fas fa-chevron-right mx-2 text-xs"></i>
            <span class="text-gray-900 font-semibold">Booking #<?php echo htmlspecialchars($booking['booking_reference']); ?></span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 <?php echo $booking['booking_status'] == 'confirmed' ? 'border-green-500' : ($booking['booking_status'] == 'cancelled' ? 'border-red-500' : 'border-yellow-500'); ?>">
                    <div class="flex justify-between items-start">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 mb-1">
                                <?php echo htmlspecialchars($booking['room_type_name']); ?>
                            </h1>
                            <p class="text-gray-500 text-sm">Mã đặt phòng: <span class="font-mono font-bold text-gray-700"><?php echo $booking['booking_reference']; ?></span></p>
                        </div>
                        <span class="px-4 py-2 rounded-full text-sm font-bold 
                            <?php echo $booking['booking_status'] == 'confirmed' ? 'bg-green-100 text-green-800' : ($booking['booking_status'] == 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                            <?php 
                                switch($booking['booking_status']) {
                                    case 'confirmed': echo 'Đã xác nhận'; break;
                                    case 'cancelled': echo 'Đã hủy'; break;
                                    case 'pending': echo 'Chờ thanh toán'; break;
                                    case 'checked_in': echo 'Đã nhận phòng'; break;
                                    case 'checked_out': echo 'Đã trả phòng'; break;
                                    default: echo $booking['booking_status'];
                                }
                            ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 border-t pt-6">
                        <div>
                            <p class="text-xs text-gray-500 uppercase font-bold">Check-in</p>
                            <p class="text-lg font-semibold text-gray-800"><?php echo date('d/m/Y', strtotime($booking['checkin_date'])); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase font-bold">Check-out</p>
                            <p class="text-lg font-semibold text-gray-800"><?php echo date('d/m/Y', strtotime($booking['checkout_date'])); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase font-bold">Số đêm</p>
                            <p class="text-lg font-semibold text-gray-800"><?php echo $booking['num_nights']; ?> đêm</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase font-bold">Khách</p>
                            <p class="text-lg font-semibold text-gray-800"><?php echo $booking['num_adults']; ?> Lớn, <?php echo $booking['num_children']; ?> Trẻ</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <?php 
                        // Xử lý đường dẫn ảnh
                        $imgUrl = $booking['image_url'] ?? '';
                        if ($imgUrl && !preg_match("~^(?:f|ht)tps?://~i", $imgUrl)) {
                            $imgUrl = '../' . $imgUrl;
                        }
                        if (empty($imgUrl)) {
                            $imgUrl = 'https://placehold.co/800x400/e8dcc4/888?text=Room+Image';
                        }
                    ?>
                    <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="Room" class="w-full h-64 object-cover">
                    <div class="p-6">
                        <h3 class="font-bold text-gray-900 mb-4">Thông tin phòng</h3>
                        <div class="flex gap-6 text-gray-600 text-sm">
                            <span><i class="fas fa-bed mr-2 text-accent"></i><?php echo $booking['bed_type']; ?></span>
                            <span><i class="fas fa-ruler-combined mr-2 text-accent"></i><?php echo $booking['room_size_sqm']; ?>m²</span>
                            <?php if($booking['pref_theme']): ?>
                                <span><i class="fas fa-paint-brush mr-2 text-accent"></i>Theme: <?php echo htmlspecialchars($booking['pref_theme']); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- --- THÊM MỚI: Hiển thị danh sách tiện nghi --- -->
                        <?php if (!empty($room_features)): ?>
                        <div class="mt-5 pt-4 border-t border-gray-100">
                            <h4 class="font-semibold text-gray-800 mb-3 text-sm">Tiện nghi có sẵn:</h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-y-2 gap-x-4">
                                <?php foreach ($room_features as $feature): ?>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas <?php echo htmlspecialchars($feature['icon']); ?> w-6 text-center mr-2 text-accent opacity-70"></i>
                                        <span><?php echo htmlspecialchars($feature['feature_name']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <!-- --- KẾT THÚC THÊM MỚI --- -->

                        <?php if($booking['special_requests']): ?>
                        <div class="mt-4 bg-yellow-50 p-3 rounded border border-yellow-100 text-sm text-yellow-800">
                            <strong>Ghi chú đặc biệt:</strong> <?php echo htmlspecialchars($booking['special_requests']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($addons) || $booking['late_checkout']): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="font-bold text-gray-900 mb-4 text-xl border-b pb-2">Dịch vụ & Tiện ích đã chọn</h3>
                    <div class="space-y-4">
                        <?php if ($booking['late_checkout']): ?>
                        <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="bg-blue-200 p-2 rounded-full mr-3"><i class="fas fa-clock text-blue-700"></i></div>
                                <div>
                                    <p class="font-semibold text-gray-800">Trả phòng muộn (16:00)</p>
                                    <p class="text-xs text-gray-500">Dịch vụ cộng thêm</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php foreach ($addons as $addon): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border border-gray-100">
                            <div class="flex items-center">
                                <div class="bg-accent bg-opacity-20 p-2 rounded-full mr-3">
                                    <i class="fas <?php echo $addon['addon_type'] == 'upsell' ? 'fa-star' : 'fa-concierge-bell'; ?> text-accent"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($addon['addon_name']); ?></p>
                                    <p class="text-xs text-gray-500">
                                        Số lượng: <?php echo $addon['quantity']; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="font-bold text-accent">
                                <?php echo number_format($addon['addon_price'], 0, ',', '.'); ?> VNĐ
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6 ">
                    <h3 class="font-bold text-gray-900 mb-6 text-xl">Chi tiết Thanh toán</h3>
                    
                    <div class="space-y-3 text-sm border-b pb-4 mb-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Giá phòng gốc</span>
                            <span class="font-medium"><?php echo number_format($booking['base_price'], 0, ',', '.'); ?> VNĐ</span>
                        </div>
                        
                        <?php if ($booking['addons_total'] > 0): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tổng dịch vụ</span>
                            <span class="font-medium"><?php echo number_format($booking['addons_total'], 0, ',', '.'); ?> VNĐ</span>
                        </div>
                        <?php endif; ?>

                        <div class="flex justify-between text-gray-500 italic">
                            <span>Thuế & Phí</span>
                            <span><?php echo number_format($booking['tax_amount'] + $booking['service_fee'], 0, ',', '.'); ?> VNĐ</span>
                        </div>
                    </div>

                    <div class="flex justify-between items-center mb-6">
                        <span class="font-bold text-lg text-gray-900">Tổng cộng</span>
                        <span class="font-bold text-xl text-accent"><?php echo number_format($booking['total_price'], 0, ',', '.'); ?> VNĐ</span>
                    </div>

                    <?php if($booking['payment_status'] == 'paid'): ?>
                        <div class="w-full bg-green-100 text-green-800 text-center py-3 rounded-lg font-bold mb-4">
                            <i class="fas fa-check-circle mr-2"></i> Đã thanh toán
                        </div>
                    <?php else: ?>
                        <button class="w-full bg-accent text-white py-3 rounded-lg font-bold hover:bg-accent-dark transition">
                            Thanh toán ngay
                        </button>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <a href="../contact.php" class="text-sm text-gray-500 hover:text-accent">Cần hỗ trợ về đơn này?</a>
                    </div>
                </div>

                <!-- --- THÊM MỚI: QR Code Section --- -->
                <div class="bg-white rounded-lg shadow-md p-6 mt-6">
                    <h3 class="font-bold text-gray-900 mb-4 text-xl text-center">Mã QR Thông tin</h3>
                    
                    <div class="flex flex-col items-center">
                        <div class="bg-gray-50 p-4 rounded-lg border-2 border-dashed border-gray-300 mb-4">
                            <img src="<?php echo htmlspecialchars($qr_code_url); ?>" 
                                 alt="QR Code" 
                                 class="w-64 h-64"
                                 id="qr-code-image">
                        </div>
                        
                        <p class="text-center text-sm text-gray-600 mb-4">
                            Xuất trình mã QR này tại quầy lễ tân để check-in nhanh chóng hoặc truy cập các khu tiện ích
                        </p>
                        
                        <div class="flex gap-2 w-full">
                            <button onclick="downloadQR()" 
                                    class="flex-1 bg-accent text-white py-2 px-4 rounded-lg text-sm font-semibold hover:bg-accent-dark transition">
                                <i class="fas fa-download mr-2"></i>Tải xuống
                            </button>
                            <button onclick="printQR()" 
                                    class="flex-1 bg-gray-600 text-white py-2 px-4 rounded-lg text-sm font-semibold hover:bg-gray-700 transition">
                                <i class="fas fa-print mr-2"></i>In mã QR
                            </button>
                        </div>
                        
                        <div class="mt-4 p-3 bg-blue-50 rounded-lg w-full">
                            <p class="text-xs text-blue-800 text-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                Mã QR có hiệu lực từ ngày <?php echo date('d/m/Y', strtotime($booking['checkin_date'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <!-- --- KẾT THÚC THÊM MỚI --- -->
            </div>
        </div>

        <!-- Add-ons Section (thêm trước phần Payment Summary) -->
        <?php if (!empty($addons)): ?>
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h3 class="text-xl font-serif font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-plus-circle text-accent mr-2"></i>
                Dịch vụ bổ sung đã đặt thêm
            </h3>
            <div class="space-y-3">
                <?php foreach ($addons_after as $addon): ?>
                <div class="flex justify-between items-center border-b border-gray-200 pb-3">
                    <div>
                        <p class="font-semibold text-gray-900">
                            <?php echo htmlspecialchars($addon['addon_name']); ?>
                        </p>
                        <p class="text-sm text-gray-600">
                            Số lượng: <?php echo $addon['quantity']; ?>
                        </p>
                        <p class="text-xs text-gray-500">
                            Đặt lúc: <?php echo date('d/m/Y H:i', strtotime($addon['created_at'])); ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-accent">
                            <?php echo number_format($addon['addon_price'] * $addon['quantity'], 0, ',', '.'); ?> VNĐ
                        </p>
                        <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full">
                            <?php echo $addon['addon_type'] === 'upsell' ? 'Ưu đãi' : 'Tiêu chuẩn'; ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function downloadQR() {
    const qrImage = document.getElementById('qr-code-image');
    const link = document.createElement('a');
    link.href = qrImage.src;
    link.download = 'booking_<?php echo $booking['booking_reference']; ?>_qr.png';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function printQR() {
    const printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>In mã QR - <?php echo $booking['booking_reference']; ?></title>');
    printWindow.document.write('<style>body{text-align:center;font-family:Arial;padding:20px;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h2>Mã QR Check-in</h2>');
    printWindow.document.write('<p>Booking: <strong><?php echo $booking['booking_reference']; ?></strong></p>');
    printWindow.document.write('<p><?php echo htmlspecialchars($booking['guest_name']); ?></p>');
    printWindow.document.write('<p><?php echo date('d/m/Y', strtotime($booking['checkin_date'])); ?> - <?php echo date('d/m/Y', strtotime($booking['checkout_date'])); ?></p>');
    printWindow.document.write('<img src="<?php echo $qr_code_url; ?>" style="margin:20px auto;"/>');
    printWindow.document.write('<p style="font-size:12px;color:#666;">Vinpearl Cần Giờ Resort & Spa</p>');
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php include '../includes/footer.php'; ?>