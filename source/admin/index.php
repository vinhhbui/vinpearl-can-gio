<?php
session_start();
require_once '../includes/db_connect.php';
// require_once '../includes/admin_auth_check.php'; // Bạn nên tạo file này để check quyền admin

// --- XỬ LÝ BỘ LỌC (TỪ THÁNG - ĐẾN THÁNG) ---
$start_month = $_GET['start_month'] ?? '';
$end_month = $_GET['end_month'] ?? '';
$view_type = $_GET['view_type'] ?? 'month'; // Mặc định xem theo tháng

$filter_condition = "";
$join_filter_condition = ""; // Dành cho câu query LEFT JOIN (có alias b.)
$params = [];
$date_label = 'Tất cả thời gian';

if (!empty($start_month) && !empty($end_month)) {
    $filter_condition = " AND DATE_FORMAT(created_at, '%Y-%m') BETWEEN :start AND :end ";
    $join_filter_condition = " AND DATE_FORMAT(b.created_at, '%Y-%m') BETWEEN :start AND :end ";
    $params['start'] = $start_month;
    $params['end'] = $end_month;
    $date_label = "Từ $start_month đến $end_month";
} elseif (!empty($start_month)) {
    $filter_condition = " AND DATE_FORMAT(created_at, '%Y-%m') >= :start ";
    $join_filter_condition = " AND DATE_FORMAT(b.created_at, '%Y-%m') >= :start ";
    $params['start'] = $start_month;
    $date_label = "Từ $start_month";
} elseif (!empty($end_month)) {
    $filter_condition = " AND DATE_FORMAT(created_at, '%Y-%m') <= :end ";
    $join_filter_condition = " AND DATE_FORMAT(b.created_at, '%Y-%m') <= :end ";
    $params['end'] = $end_month;
    $date_label = "Đến $end_month";
}

// 1. Thống kê doanh thu (Tổng tiền từ các booking đã thanh toán/hoàn thành)
$revenue_sql = "SELECT SUM(total_price) as total 
                FROM bookings 
                WHERE booking_status IN ('confirmed', 'checked_in', 'checked_out') 
                $filter_condition";
$stmt = $pdo->prepare($revenue_sql);
$stmt->execute($params);
$revenue = $stmt->fetch()['total'] ?? 0;

// 2. Thống kê số lượng booking
$count_sql = "SELECT COUNT(*) FROM bookings WHERE 1=1 $filter_condition";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$bookings_count = $stmt->fetchColumn();

// 3. Thống kê phòng (Không đổi theo tháng)
$total_rooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$active_rooms = $total_rooms; 

// 4. Doanh thu theo tháng/tuần (Biểu đồ xu hướng)
if ($view_type === 'week') {
    // Group theo tuần (Tuần %v của năm %x - chuẩn ISO)
    $chart_sql = "
        SELECT DATE_FORMAT(created_at, 'Tuần %v/%x') as time_label, SUM(total_price) as revenue 
        FROM bookings 
        WHERE booking_status != 'cancelled' 
        $filter_condition
        GROUP BY time_label, YEARWEEK(created_at, 1)
        ORDER BY YEARWEEK(created_at, 1) DESC
    ";
} else {
    // Group theo tháng
    $chart_sql = "
        SELECT DATE_FORMAT(created_at, '%Y-%m') as time_label, SUM(total_price) as revenue 
        FROM bookings 
        WHERE booking_status != 'cancelled' 
        $filter_condition
        GROUP BY time_label 
        ORDER BY time_label DESC
    ";
}

if (empty($filter_condition)) {
    $chart_sql .= " LIMIT 12"; // Giới hạn số lượng cột hiển thị mặc định
}

$stmt = $pdo->prepare($chart_sql);
$stmt->execute($params);
$chart_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Doanh thu theo loại phòng
$room_type_sql = "
    SELECT 
        r.id,
        r.room_type_name,
        r.image_url,
        r.price_per_night,
        COUNT(b.id) as total_bookings,
        COALESCE(SUM(b.total_price), 0) as total_revenue,
        COALESCE(SUM(DATEDIFF(b.checkout_date, b.checkin_date)), 0) as total_nights
    FROM rooms r
    LEFT JOIN bookings b ON r.id = b.room_id 
        AND b.booking_status IN ('confirmed', 'checked_in', 'checked_out')
        $join_filter_condition
    GROUP BY r.id, r.room_type_name, r.image_url, r.price_per_night
    ORDER BY total_revenue DESC
";

$stmt = $pdo->prepare($room_type_sql);
$stmt->execute($params);
$revenue_by_room_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tính tổng doanh thu để tính phần trăm
$total_revenue_all = array_sum(array_column($revenue_by_room_type, 'total_revenue'));

include 'components/header.php'; 
?>

<div class="d-flex">
    <?php include 'components/sidebar.php'; ?>
    
    <div class="main-content flex-grow-1 p-4 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Tổng quan Doanh nghiệp</h2>
            
            <!-- FORM LỌC KHOẢNG THỜI GIAN -->
            <form method="GET" class="d-flex gap-2 align-items-center bg-white p-2 rounded shadow-sm" id="filterForm">
                <label class="fw-bold text-secondary mb-0"><i class="fas fa-filter me-1"></i>Lọc:</label>
                
                <!-- Input ẩn để giữ trạng thái view_type khi submit form lọc ngày -->
                <input type="hidden" name="view_type" id="viewTypeInput" value="<?php echo htmlspecialchars($view_type); ?>">

                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light">Từ</span>
                    <input type="month" name="start_month" class="form-control" 
                           value="<?php echo htmlspecialchars($start_month); ?>">
                </div>
                
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light">Đến</span>
                    <input type="month" name="end_month" class="form-control" 
                           value="<?php echo htmlspecialchars($end_month); ?>">
                </div>

                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                
                <?php if(!empty($start_month) || !empty($end_month)): ?>
                    <a href="index.php" class="btn btn-sm btn-outline-danger" title="Xóa lọc"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Cards Thống kê -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Tổng Doanh Thu</h6>
                        <small class="d-block mb-2 opacity-75"><?php echo $date_label; ?></small>
                        <h3 class="mb-0"><?php echo number_format($revenue, 0, ',', '.'); ?> VNĐ</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Tổng Đơn Đặt</h6>
                        <small class="d-block mb-2 opacity-75"><?php echo $date_label; ?></small>
                        <h3 class="mb-0"><?php echo $bookings_count; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Tổng số Phòng</h6>
                        <small class="d-block mb-2 opacity-75">Hiện tại</small>
                        <h3 class="mb-0"><?php echo $total_rooms; ?></h3>
                        <small>Đang hoạt động: <?php echo $active_rooms; ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <h6 class="card-title">Loại Phòng Bán Chạy</h6>
                        <small class="d-block mb-2 opacity-75"><?php echo $date_label; ?></small>
                        <h5 class="mb-0"><?php echo htmlspecialchars($revenue_by_room_type[0]['room_type_name'] ?? 'N/A'); ?></h5>
                        <small><?php echo number_format($revenue_by_room_type[0]['total_bookings'] ?? 0); ?> đơn đặt</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Biểu đồ & Bảng -->
        <div class="row g-4 mb-4">
            <div class="col-md-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Xu hướng doanh thu</h5>
                        <div class="d-flex align-items-center gap-2">
                            <!-- Nút chuyển đổi chế độ xem -->
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary <?php echo $view_type === 'month' ? 'active' : ''; ?>" onclick="changeViewType('month')">Theo Tháng</button>
                                <button type="button" class="btn btn-outline-primary <?php echo $view_type === 'week' ? 'active' : ''; ?>" onclick="changeViewType('week')">Theo Tuần</button>
                            </div>
                            <span class="badge bg-light text-dark border ms-2"><?php echo empty($filter_condition) ? 'Gần nhất' : $date_label; ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Tỷ lệ doanh thu theo loại phòng</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="roomTypePieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bảng doanh thu chi tiết theo loại phòng -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Chi tiết Doanh thu</h5>
                        <span class="badge bg-primary"><?php echo count($revenue_by_room_type); ?> loại phòng</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Loại Phòng</th>
                                        <th class="text-center">Giá/Đêm</th>
                                        <th class="text-center">Số Booking</th>
                                        <th class="text-center">Tổng Đêm</th>
                                        <th class="text-end">Doanh Thu</th>
                                        <th style="width: 200px;">Tỷ Lệ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($revenue_by_room_type as $index => $room): 
                                        $percentage = $total_revenue_all > 0 ? ($room['total_revenue'] / $total_revenue_all) * 100 : 0;
                                        $progress_class = $index == 0 ? 'bg-success' : ($index == 1 ? 'bg-primary' : 'bg-info');

                                        // Xử lý hiển thị ảnh
                                        $imgUrl = $room['image_url'] ?? '';
                                        if ($imgUrl && !preg_match("~^(?:f|ht)tps?://~i", $imgUrl)) {
                                            $imgUrl = '../' . $imgUrl;
                                        }
                                        if (empty($imgUrl)) {
                                            $imgUrl = 'https://placehold.co/50x35/e8dcc4/888?text=Room';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo htmlspecialchars($imgUrl); ?>" 
                                                     width="50" height="35" class="object-fit-cover rounded me-2 border">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($room['room_type_name']); ?></strong>
                                                    <?php if ($index == 0 && $room['total_revenue'] > 0): ?>
                                                        <span class="badge bg-success ms-1"><i class="fas fa-crown"></i> Top 1</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="text-primary fw-bold"><?php echo number_format($room['price_per_night'], 0, ',', '.'); ?> ₫</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary"><?php echo $room['total_bookings']; ?> đơn</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info text-dark"><?php echo $room['total_nights']; ?> đêm</span>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-success"><?php echo number_format($room['total_revenue'], 0, ',', '.'); ?> ₫</strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1" style="height: 8px;">
                                                    <div class="progress-bar <?php echo $progress_class; ?>" 
                                                         style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                                <span class="ms-2 small fw-bold" style="min-width: 45px;">
                                                    <?php echo number_format($percentage, 1); ?>%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($revenue_by_room_type) || $total_revenue_all == 0): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                            Chưa có dữ liệu doanh thu <?php echo $date_label; ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                                <?php if ($total_revenue_all > 0): ?>
                                <tfoot class="table-light">
                                    <tr class="fw-bold">
                                        <td>Tổng cộng</td>
                                        <td class="text-center">-</td>
                                        <td class="text-center">
                                            <?php echo array_sum(array_column($revenue_by_room_type, 'total_bookings')); ?> đơn
                                        </td>
                                        <td class="text-center">
                                            <?php echo array_sum(array_column($revenue_by_room_type, 'total_nights')); ?> đêm
                                        </td>
                                        <td class="text-end text-success">
                                            <?php echo number_format($total_revenue_all, 0, ',', '.'); ?> ₫
                                        </td>
                                        <td>100%</td>
                                    </tr>
                                </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Hàm thay đổi chế độ xem (Tháng/Tuần)
function changeViewType(type) {
    document.getElementById('viewTypeInput').value = type;
    document.getElementById('filterForm').submit();
}

// Biểu đồ doanh thu
const ctx = document.getElementById('revenueChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column(array_reverse($chart_stats), 'time_label')); ?>,
        datasets: [{
            label: 'Doanh thu (VNĐ)',
            data: <?php echo json_encode(array_column(array_reverse($chart_stats), 'revenue')); ?>,
            backgroundColor: '#0d6efd'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return new Intl.NumberFormat('vi-VN').format(value) + ' ₫';
                    }
                }
            }
        }
    }
});

// Biểu đồ tròn doanh thu theo loại phòng
const pieCtx = document.getElementById('roomTypePieChart');
const roomTypeData = <?php echo json_encode($revenue_by_room_type); ?>;

const pieColors = [
    '#198754', '#0d6efd', '#0dcaf0', '#ffc107', '#dc3545', 
    '#6f42c1', '#fd7e14', '#20c997', '#6610f2', '#d63384'
];

new Chart(pieCtx, {
    type: 'doughnut',
    data: {
        labels: roomTypeData.map(r => r.room_type_name),
        datasets: [{
            data: roomTypeData.map(r => r.total_revenue),
            backgroundColor: pieColors.slice(0, roomTypeData.length),
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    padding: 10,
                    font: {
                        size: 11
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const value = context.raw;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return `${new Intl.NumberFormat('vi-VN').format(value)} ₫ (${percentage}%)`;
                    }
                }
            }
        }
    }
});
</script>

<?php include 'components/footer.php'; ?>