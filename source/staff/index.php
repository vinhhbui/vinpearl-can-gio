<?php 
// 1. LOGIC XỬ LÝ TÌM KIẾM QR (Thêm vào đầu file)
include __DIR__ . '/components/header.php'; 

// Xử lý khi nhận được mã QR từ URL
if (isset($_GET['qr_search'])) {
    // Trim để loại bỏ khoảng trắng thừa nếu có
    $ref = trim($conn->real_escape_string($_GET['qr_search']));
    
    // Tìm booking id dựa trên booking_reference
    $find_booking = $conn->query("SELECT id FROM bookings WHERE booking_reference = '$ref' LIMIT 1");
    
    if ($find_booking && $find_booking->num_rows > 0) {
        $booking_id = $find_booking->fetch_assoc()['id'];
        // Chuyển hướng sang trang chi tiết
        echo "<script>window.location.href = 'booking-detail.php?id=$booking_id';</script>";
        exit;
    } else {
        $qr_error = "Không tìm thấy đơn đặt phòng với mã: " . htmlspecialchars($ref);
    }
}
?>

<!-- Thêm thư viện quét QR -->
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

<div class="d-flex">
    <?php include __DIR__ . '/components/sidebar.php'; ?>
    
    <div class="main-content flex-grow-1">
        
        <!-- Header Dashboard & Nút Scan -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Dashboard</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#qrModal">
                <i class="fas fa-qrcode"></i> Quét QR Check-in
            </button>
        </div>

        <!-- Hiển thị lỗi nếu không tìm thấy QR -->
        <?php if (isset($qr_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $qr_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <?php
            $today = date('Y-m-d');
            
            // Today's check-ins
            $checkins_today = $conn->query("
                SELECT COUNT(*) as count FROM bookings 
                WHERE checkin_date = '$today' 
                AND booking_status IN ('confirmed', 'pending')
            ")->fetch_assoc()['count'];
            
            // Today's check-outs
            $checkouts_today = $conn->query("
                SELECT COUNT(*) as count FROM bookings 
                WHERE checkout_date = '$today' 
                AND booking_status = 'checked_in'
            ")->fetch_assoc()['count'];
            
            // Current occupied rooms
            $occupied_rooms = $conn->query("
                SELECT COUNT(*) as count FROM bookings 
                WHERE booking_status = 'checked_in'
            ")->fetch_assoc()['count'];
            
            // Pending service requests
            $pending_requests = $conn->query("
                SELECT COUNT(*) as count FROM bookings 
                WHERE booking_status = 'pending'
            ")->fetch_assoc()['count'];
            ?>
            
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Check-in hôm nay</h5>
                        <h2><?php echo $checkins_today; ?></h2>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Check-out hôm nay</h5>
                        <h2><?php echo $checkouts_today; ?></h2>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Phòng đang ở</h5>
                        <h2><?php echo $occupied_rooms; ?></h2>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Yêu cầu chờ xử lý</h5>
                        <h2><?php echo $pending_requests; ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Today's Check-ins -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Check-in hôm nay</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Mã đặt phòng</th>
                                <th>Khách hàng</th>
                                <th>Loại phòng</th>
                                <th>Số phòng</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $checkins = $conn->query("
                                SELECT b.*, r.room_type_name, rn.room_number
                                FROM bookings b
                                JOIN rooms r ON b.room_id = r.id
                                LEFT JOIN room_numbers rn ON b.room_number_id = rn.id
                                WHERE b.checkin_date = '$today'
                                AND b.booking_status IN ('confirmed', 'pending')
                                ORDER BY b.created_at DESC
                            ");
                            
                            if ($checkins && $checkins->num_rows > 0):
                                while ($booking = $checkins->fetch_assoc()):
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($booking['booking_reference']); ?></strong></td>
                                <td><?php echo htmlspecialchars($booking['guest_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['room_type_name']); ?></td>
                                <td>
                                    <?php echo $booking['room_number'] ? htmlspecialchars($booking['room_number']) : '<span class="badge bg-secondary">Chưa gán</span>'; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $booking['booking_status'] == 'confirmed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($booking['booking_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="booking-detail.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> Chi tiết
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="6" class="text-center">Không có booking check-in hôm nay</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Today's Check-outs -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Check-out hôm nay</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Mã đặt phòng</th>
                                <th>Khách hàng</th>
                                <th>Loại phòng</th>
                                <th>Số phòng</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $checkouts = $conn->query("
                                SELECT b.*, r.room_type_name, rn.room_number
                                FROM bookings b
                                JOIN rooms r ON b.room_id = r.id
                                LEFT JOIN room_numbers rn ON b.room_number_id = rn.id
                                WHERE b.checkout_date = '$today'
                                AND b.booking_status = 'checked_in'
                                ORDER BY b.created_at DESC
                            ");
                            
                            if ($checkouts && $checkouts->num_rows > 0):
                                while ($booking = $checkouts->fetch_assoc()):
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($booking['booking_reference']); ?></strong></td>
                                <td><?php echo htmlspecialchars($booking['guest_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['room_type_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['room_number']); ?></td>
                                <td>
                                    <span class="badge bg-success">Checked In</span>
                                </td>
                                <td>
                                    <a href="booking-detail.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> Chi tiết
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="6" class="text-center">Không có booking check-out hôm nay</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Quét QR -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-qrcode"></i> Quét mã đặt phòng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div id="reader" style="width: 100%"></div>
                <p class="mt-2 text-muted small">Đưa mã QR vào khung hình để tự động tìm kiếm.</p>
            </div>
        </div>
    </div>
</div>

<script>
    let html5QrcodeScanner = null;

    // Hàm xử lý khi quét thành công
    const onScanSuccess = (decodedText, decodedResult) => {
        // Ngay lập tức chuyển hướng, không cần clear scanner vì trang sẽ reload
        // Sử dụng encodeURIComponent để đảm bảo an toàn cho URL
        window.location.href = `index.php?qr_search=${encodeURIComponent(decodedText)}`;
    }

    // Sự kiện khi Modal được mở hoàn toàn
    document.getElementById('qrModal').addEventListener('shown.bs.modal', () => {
        // Khởi tạo scanner mới
        // qrbox: kích thước vùng quét
        // fps: số khung hình trên giây
        html5QrcodeScanner = new Html5QrcodeScanner(
            "reader", 
            { fps: 10, qrbox: { width: 250, height: 250 } },
            /* verbose= */ false
        );
        html5QrcodeScanner.render(onScanSuccess, (errorMessage) => {
            // parse error, ignore it.
        });
    });

    // Sự kiện khi Modal bắt đầu đóng
    document.getElementById('qrModal').addEventListener('hide.bs.modal', () => {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear().then(() => {
                // Dọn dẹp thành công
                html5QrcodeScanner = null;
                // Xóa nội dung trong div reader để tránh lỗi hiển thị khi mở lại
                document.getElementById('reader').innerHTML = "";
            }).catch(error => {
                console.error("Failed to clear html5QrcodeScanner. ", error);
            });
        }
    });
</script>

<?php include __DIR__ . '/components/footer.php'; ?>