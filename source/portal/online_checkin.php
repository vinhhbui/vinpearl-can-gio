<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

// Lấy thông tin user để kiểm tra email
$stmt_user = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch();

// Lấy các booking sắp check-in (trong vòng 7 ngày tới hoặc trong kỳ lưu trú)
$stmt = $pdo->prepare("
    SELECT b.*, r.room_type_name, r.image_url, r.bed_type, r.room_size_sqm
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    WHERE (b.user_id = ? OR b.guest_email = ?) 
    AND b.checkin_date >= CURDATE() 
    AND b.checkin_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND b.booking_status IN ('confirmed', 'pending')
    ORDER BY b.checkin_date ASC
");
$stmt->execute([$user_id, $user['email']]);
$eligible_bookings = $stmt->fetchAll();

$page_title = 'Check-in trực tuyến - Cổng khách hàng';
include '../includes/header.php';
?>

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
                    <span class="text-white font-bold">Check-in trực tuyến</span>
                </li>
            </ol>
        </nav>
    </div>
</div>

<div class="min-h-screen bg-gray-50 py-12">
    <div class="container mx-auto px-4">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="bg-gradient-to-r from-accent to-accent-dark text-white rounded-lg p-8 mb-8 shadow-xl">
                <h1 class="text-4xl font-serif font-bold mb-2">Check-in trực tuyến</h1>
                <p class="text-lg opacity-90">Quét mã QR tại quầy lễ tân để check-in nhanh chóng</p>
            </div>

            <?php if (empty($eligible_bookings)): ?>
                <!-- No Bookings -->
                <div class="bg-white rounded-lg shadow-lg p-12 text-center">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-calendar-times text-gray-400 text-4xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Không có đặt phòng sắp tới</h2>
                    <p class="text-gray-600 mb-6">Bạn chưa có đặt phòng nào trong vòng 7 ngày tới để check-in.</p>
                    <a href="../booking/rooms.php" class="inline-block bg-accent text-white px-8 py-3 rounded-lg hover:bg-accent-dark transition font-semibold">
                        Đặt phòng ngay
                    </a>
                </div>
            <?php else: ?>
                <!-- Instructions -->
                <div class="bg-blue-50 border-l-4 border-blue-500 p-6 mb-8 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-600 text-2xl mr-4 mt-1"></i>
                        <div>
                            <h3 class="font-bold text-blue-900 mb-2">Hướng dẫn sử dụng mã QR</h3>
                            <ul class="text-blue-800 text-sm space-y-1">
                                <li>1. Tải xuống hoặc chụp ảnh mã QR của đặt phòng</li>
                                <li>2. Xuất trình mã QR tại quầy lễ tân khi đến khách sạn</li>
                                <li>3. Nhân viên sẽ quét mã và hoàn tất thủ tục check-in cho bạn</li>
                                <li>4. Nhận chìa khóa phòng và tận hưởng kỳ nghỉ!</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Booking Cards with QR -->
                <div class="space-y-6">
                    <?php foreach ($eligible_bookings as $booking): ?>
                        <?php
                        // Tạo dữ liệu QR Code
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
                        $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qr_data);
                        
                        // Tính số ngày còn lại
                        $days_until_checkin = max(0, floor((strtotime($booking['checkin_date']) - time()) / 86400));

                        // Xử lý đường dẫn ảnh
                        $imgUrl = $booking['image_url'] ?? '';
                        if ($imgUrl && !preg_match("~^(?:f|ht)tps?://~i", $imgUrl)) {
                            $imgUrl = '../' . $imgUrl;
                        }
                        if (empty($imgUrl)) {
                            $imgUrl = 'https://placehold.co/200x200/e8dcc4/888?text=Room';
                        }
                        ?>

                        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                            <div class="grid grid-cols-1 lg:grid-cols-3">
                                <!-- Left: Room Info -->
                                <div class="lg:col-span-2 p-8">
                                    <div class="flex items-start gap-4 mb-6">
                                        <img src="<?php echo htmlspecialchars($imgUrl); ?>" 
                                             alt="<?php echo htmlspecialchars($booking['room_type_name']); ?>" 
                                             class="w-32 h-32 object-cover rounded-lg">
                                        <div class="flex-1">
                                            <div class="flex items-start justify-between mb-2">
                                                <h3 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($booking['room_type_name']); ?></h3>
                                                <?php if ($days_until_checkin === 0): ?>
                                                    <span class="bg-green-100 text-green-800 px-4 py-1 rounded-full text-sm font-semibold">
                                                        <i class="fas fa-check-circle mr-1"></i>Hôm nay
                                                    </span>
                                                <?php elseif ($days_until_checkin <= 3): ?>
                                                    <span class="bg-orange-100 text-orange-800 px-4 py-1 rounded-full text-sm font-semibold">
                                                        <i class="fas fa-clock mr-1"></i>Còn <?php echo $days_until_checkin; ?> ngày
                                                    </span>
                                                <?php else: ?>
                                                    <span class="bg-blue-100 text-blue-800 px-4 py-1 rounded-full text-sm font-semibold">
                                                        Còn <?php echo $days_until_checkin; ?> ngày
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="space-y-2 text-gray-600">
                                                <p class="flex items-center gap-2">
                                                    <i class="fas fa-barcode text-accent w-5"></i>
                                                    <span class="font-mono font-semibold text-gray-900"><?php echo htmlspecialchars($booking['booking_reference']); ?></span>
                                                </p>
                                                <p class="flex items-center gap-2">
                                                    <i class="fas fa-user text-accent w-5"></i>
                                                    <?php echo htmlspecialchars($booking['guest_name']); ?>
                                                </p>
                                                <p class="flex items-center gap-2">
                                                    <i class="fas fa-calendar-alt text-accent w-5"></i>
                                                    <?php echo date('d/m/Y', strtotime($booking['checkin_date'])); ?> 
                                                    <i class="fas fa-arrow-right text-gray-400 text-sm"></i>
                                                    <?php echo date('d/m/Y', strtotime($booking['checkout_date'])); ?>
                                                </p>
                                                <p class="flex items-center gap-2">
                                                    <i class="fas fa-users text-accent w-5"></i>
                                                    <?php echo $booking['num_adults']; ?> người lớn
                                                    <?php if ($booking['num_children'] > 0): ?>
                                                        + <?php echo $booking['num_children']; ?> trẻ em
                                                    <?php endif; ?>
                                                </p>
                                                <p class="flex items-center gap-2">
                                                    <i class="fas fa-bed text-accent w-5"></i>
                                                    <?php echo htmlspecialchars($booking['bed_type']); ?> • <?php echo $booking['room_size_sqm']; ?>m²
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="border-t pt-6">
                                        <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" 
                                           class="text-accent font-semibold hover:text-accent-dark transition inline-flex items-center gap-2">
                                            <i class="fas fa-info-circle"></i>
                                            Xem chi tiết đặt phòng
                                        </a>
                                    </div>
                                </div>

                                <!-- Right: QR Code -->
                                <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-8 flex flex-col items-center justify-center border-l">
                                    <h4 class="font-bold text-gray-900 mb-4 text-center">Mã QR Check-in</h4>
                                    
                                    <div class="bg-white p-4 rounded-lg shadow-md mb-4">
                                        <img src="<?php echo htmlspecialchars($qr_code_url); ?>" 
                                             alt="QR Code" 
                                             class="w-48 h-48"
                                             id="qr-<?php echo $booking['id']; ?>">
                                    </div>

                                    <p class="text-center text-xs text-gray-600 mb-4 px-4">
                                        Xuất trình mã này tại quầy lễ tân
                                    </p>

                                    <div class="flex gap-2 w-full">
                                        <button onclick="downloadQR(<?php echo $booking['id']; ?>, '<?php echo $booking['booking_reference']; ?>')" 
                                                class="flex-1 bg-accent text-white py-2 px-3 rounded-lg text-sm font-semibold hover:bg-accent-dark transition flex items-center justify-center gap-2">
                                            <i class="fas fa-download"></i>
                                            <span class="hidden sm:inline">Tải xuống</span>
                                        </button>
                                        <button onclick="printQR(<?php echo $booking['id']; ?>, '<?php echo addslashes($booking['booking_reference']); ?>', '<?php echo addslashes($booking['guest_name']); ?>', '<?php echo date('d/m/Y', strtotime($booking['checkin_date'])); ?>', '<?php echo date('d/m/Y', strtotime($booking['checkout_date'])); ?>')" 
                                                class="flex-1 bg-gray-600 text-white py-2 px-3 rounded-lg text-sm font-semibold hover:bg-gray-700 transition flex items-center justify-center gap-2">
                                            <i class="fas fa-print"></i>
                                            <span class="hidden sm:inline">In</span>
                                        </button>
                                    </div>

                                    <div class="mt-4 p-3 bg-blue-50 rounded-lg w-full">
                                        <p class="text-xs text-blue-800 text-center">
                                            <i class="fas fa-clock mr-1"></i>
                                            Check-in: <?php echo date('d/m/Y', strtotime($booking['checkin_date'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Tips Section -->
                <div class="bg-white rounded-lg shadow-lg p-6 mt-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <i class="fas fa-lightbulb text-yellow-500"></i>
                        Mẹo hữu ích
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-check-circle text-green-600 mt-1"></i>
                            <p>Đến sớm từ 14:00 để được phục vụ tốt nhất</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <i class="fas fa-check-circle text-green-600 mt-1"></i>
                            <p>Mang theo CMND/Passport để xác thực</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <i class="fas fa-check-circle text-green-600 mt-1"></i>
                            <p>Liên hệ trước nếu cần late check-in</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function downloadQR(bookingId, bookingRef) {
    const qrImage = document.getElementById('qr-' + bookingId);
    const link = document.createElement('a');
    link.href = qrImage.src;
    link.download = 'checkin_qr_' + bookingRef + '.png';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function printQR(bookingId, bookingRef, guestName, checkin, checkout) {
    const qrImage = document.getElementById('qr-' + bookingId);
    const printWindow = window.open('', '', 'height=600,width=800');
    
    printWindow.document.write('<html><head><title>Mã QR Check-in - ' + bookingRef + '</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: Arial, sans-serif; text-align: center; padding: 40px; }');
    printWindow.document.write('h1 { color: #333; margin-bottom: 10px; }');
    printWindow.document.write('.info { margin: 20px 0; line-height: 1.8; }');
    printWindow.document.write('.qr-container { margin: 30px 0; }');
    printWindow.document.write('.footer { margin-top: 40px; font-size: 12px; color: #666; }');
    printWindow.document.write('@media print { body { padding: 20px; } }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h1>Mã QR Check-in</h1>');
    printWindow.document.write('<div class="info">');
    printWindow.document.write('<p><strong>Mã đặt phòng:</strong> ' + bookingRef + '</p>');
    printWindow.document.write('<p><strong>Khách hàng:</strong> ' + guestName + '</p>');
    printWindow.document.write('<p><strong>Check-in:</strong> ' + checkin + '</p>');
    printWindow.document.write('<p><strong>Check-out:</strong> ' + checkout + '</p>');
    printWindow.document.write('</div>');
    printWindow.document.write('<div class="qr-container">');
    printWindow.document.write('<img src="' + qrImage.src + '" style="border: 2px solid #ddd; padding: 10px;"/>');
    printWindow.document.write('</div>');
    printWindow.document.write('<div class="footer">');
    printWindow.document.write('<p>Vinpearl Cần Giờ Resort & Spa</p>');
    printWindow.document.write('<p>Vui lòng xuất trình mã QR này tại quầy lễ tân</p>');
    printWindow.document.write('</div>');
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php include '../includes/footer.php'; ?>