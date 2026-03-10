<?php include __DIR__ . '/components/header.php'; ?>

<div class="d-flex">
    <?php include __DIR__ . '/components/sidebar.php'; ?>
    
    <div class="main-content flex-grow-1">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Quản lý đặt phòng</h2>
            <a href="booking-create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Đặt phòng mới
            </a>
        </div>
        
        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Từ ngày</label>
                        <input type="date" name="from_date" class="form-control" 
                               value="<?php echo $_GET['from_date'] ?? date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Đến ngày</label>
                        <input type="date" name="to_date" class="form-control" 
                               value="<?php echo $_GET['to_date'] ?? date('Y-m-d', strtotime('+7 days')); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="">Tất cả</option>
                            <option value="pending" <?php echo ($_GET['status'] ?? '') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo ($_GET['status'] ?? '') == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="checked_in" <?php echo ($_GET['status'] ?? '') == 'checked_in' ? 'selected' : ''; ?>>Checked In</option>
                            <option value="checked_out" <?php echo ($_GET['status'] ?? '') == 'checked_out' ? 'selected' : ''; ?>>Checked Out</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Tìm kiếm
                        </button>
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
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $from_date = $conn->real_escape_string($_GET['from_date'] ?? date('Y-m-d'));
                            $to_date = $conn->real_escape_string($_GET['to_date'] ?? date('Y-m-d', strtotime('+7 days')));
                            $status = $conn->real_escape_string($_GET['status'] ?? '');
                            
                            $query = "
                                SELECT b.*, r.room_type_name, rn.room_number
                                FROM bookings b
                                JOIN rooms r ON b.room_id = r.id
                                LEFT JOIN room_numbers rn ON b.room_number_id = rn.id
                                WHERE (b.checkin_date BETWEEN '$from_date' AND '$to_date'
                                    OR b.checkout_date BETWEEN '$from_date' AND '$to_date')
                            ";
                            
                            if ($status) {
                                $query .= " AND b.booking_status = '$status'";
                            }
                            
                            $query .= " ORDER BY b.checkin_date DESC";
                            
                            $bookings = $conn->query($query);
                            
                            $status_colors = [
                                'pending' => 'warning',
                                'confirmed' => 'info',
                                'checked_in' => 'success',
                                'checked_out' => 'secondary',
                                'cancelled' => 'danger'
                            ];
                            
                            if ($bookings && $bookings->num_rows > 0):
                                while ($booking = $bookings->fetch_assoc()):
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($booking['booking_reference']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($booking['guest_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($booking['guest_phone']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($booking['room_type_name']); ?></td>
                                <td>
                                    <?php echo $booking['room_number'] ? htmlspecialchars($booking['room_number']) : '<span class="badge bg-secondary">Chưa gán</span>'; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($booking['checkin_date'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($booking['checkout_date'])); ?></td>
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
                                <td colspan="8" class="text-center">Không tìm thấy booking nào</td>
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