<?php
 
include __DIR__ . '/components/header.php'; ?>

<div class="d-flex">
    <?php include __DIR__ . '/components/sidebar.php'; ?>
    
    <div class="main-content flex-grow-1">
        <h2 class="mb-4">Quản lý đơn đặt phòng</h2>
        
        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Mã booking, tên khách..." 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">Tất cả trạng thái</option>
                            <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="checked_in" <?php echo (isset($_GET['status']) && $_GET['status'] == 'checked_in') ? 'selected' : ''; ?>>Checked In</option>
                            <option value="checked_out" <?php echo (isset($_GET['status']) && $_GET['status'] == 'checked_out') ? 'selected' : ''; ?>>Checked Out</option>
                            <option value="cancelled" <?php echo (isset($_GET['status']) && $_GET['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="checkin_from" class="form-control" placeholder="Check-in từ"
                               value="<?php echo isset($_GET['checkin_from']) ? $_GET['checkin_from'] : ''; ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="checkin_to" class="form-control" placeholder="Check-in đến"
                               value="<?php echo isset($_GET['checkin_to']) ? $_GET['checkin_to'] : ''; ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Tìm kiếm
                        </button>
                    </div>
                    <div class="col-md-1">
                        <a href="bookings.php" class="btn btn-secondary w-100">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Bookings Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Mã booking</th>
                                <th>Khách hàng</th>
                                <th>Loại phòng</th>
                                <th>Số phòng</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $where = "1=1";
                            
                            if (isset($_GET['search']) && !empty($_GET['search'])) {
                                $search = $conn->real_escape_string($_GET['search']);
                                $where .= " AND (b.booking_reference LIKE '%$search%' OR b.guest_name LIKE '%$search%' OR b.guest_email LIKE '%$search%')";
                            }
                            
                            if (isset($_GET['status']) && !empty($_GET['status'])) {
                                $status = $conn->real_escape_string($_GET['status']);
                                $where .= " AND b.booking_status = '$status'";
                            }
                            
                            if (isset($_GET['checkin_from']) && !empty($_GET['checkin_from'])) {
                                $checkin_from = $conn->real_escape_string($_GET['checkin_from']);
                                $where .= " AND b.checkin_date >= '$checkin_from'";
                            }
                            
                            if (isset($_GET['checkin_to']) && !empty($_GET['checkin_to'])) {
                                $checkin_to = $conn->real_escape_string($_GET['checkin_to']);
                                $where .= " AND b.checkin_date <= '$checkin_to'";
                            }
                            
                            $bookings = $conn->query("
                                SELECT b.*, r.room_type_name, rn.room_number
                                FROM bookings b
                                JOIN rooms r ON b.room_id = r.id
                                LEFT JOIN room_numbers rn ON b.room_number_id = rn.id
                                WHERE $where
                                ORDER BY b.created_at DESC
                            ");
                            
                            if ($bookings && $bookings->num_rows > 0):
                                while ($booking = $bookings->fetch_assoc()):
                                    $status_colors = [
                                        'pending' => 'warning',
                                        'confirmed' => 'success',
                                        'checked_in' => 'info',
                                        'checked_out' => 'secondary',
                                        'cancelled' => 'danger'
                                    ];
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($booking['booking_reference']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($booking['guest_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($booking['guest_email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($booking['room_type_name']); ?></td>
                                <td>
                                    <?php echo $booking['room_number'] ? htmlspecialchars($booking['room_number']) : '<span class="badge bg-secondary">Chưa gán</span>'; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($booking['checkin_date'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($booking['checkout_date'])); ?></td>
                                <td><?php echo number_format($booking['total_amount'], 0, ',', '.'); ?> VNĐ</td>
                                <td>
                                    <span class="badge bg-<?php echo $status_colors[$booking['booking_status']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($booking['booking_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="booking-detail.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="9" class="text-center">Không tìm thấy đơn đặt phòng</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/components/footer.php'; ?>