<?php

session_start();
require_once '../includes/db_connect.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Lấy danh sách mã khuyến mãi đang hoạt động
$active_promotions = [];
try {
    $stmt = $pdo->query("
        SELECT 
            id,
            code, 
            title, 
            description, 
            discount_type, 
            discount_value,
            min_nights,
            min_amount,
            max_discount,
            usage_limit,
            used_count,
            valid_from,
            valid_to,
            DATEDIFF(valid_to, CURDATE()) as days_left
        FROM promotions 
        WHERE is_active = 1 
        AND valid_from <= CURDATE() 
        AND valid_to >= CURDATE()
        AND (usage_limit IS NULL OR used_count < usage_limit)
        ORDER BY 
            CASE 
                WHEN discount_type = 'percentage' THEN discount_value 
                ELSE discount_value / 100000 
            END DESC
    ");
    $active_promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Không thể tải danh sách khuyến mãi.";
}

// Lấy danh sách mã đã lưu (saved promotions) từ session hoặc DB
$saved_promo_codes = isset($_SESSION['saved_promotions']) ? $_SESSION['saved_promotions'] : [];

// Xử lý lưu mã khuyến mãi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $promo_code = trim($_POST['promo_code']);
        if (!in_array($promo_code, $saved_promo_codes)) {
            $saved_promo_codes[] = $promo_code;
            $_SESSION['saved_promotions'] = $saved_promo_codes;
            $success_message = "Đã lưu mã khuyến mãi!";
        }
    } elseif ($_POST['action'] === 'remove') {
        $promo_code = trim($_POST['promo_code']);
        $saved_promo_codes = array_diff($saved_promo_codes, [$promo_code]);
        $_SESSION['saved_promotions'] = $saved_promo_codes;
        $success_message = "Đã xóa mã khuyến mãi khỏi danh sách đã lưu!";
    }
}

// Lấy thông tin chi tiết các mã đã lưu
$saved_promotions = [];
if (!empty($saved_promo_codes)) {
    $placeholders = str_repeat('?,', count($saved_promo_codes) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT 
            id,
            code, 
            title, 
            description, 
            discount_type, 
            discount_value,
            min_nights,
            min_amount,
            max_discount,
            valid_to,
            DATEDIFF(valid_to, CURDATE()) as days_left
        FROM promotions 
        WHERE code IN ($placeholders)
        AND is_active = 1
    ");
    $stmt->execute($saved_promo_codes);
    $saved_promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Thống kê mã đã sử dụng
$used_promotions_stats = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            promo_code,
            COUNT(*) as usage_count,
            SUM(CAST(JSON_EXTRACT(addons_summary_json, '$.promo_discount') AS DECIMAL(10,2))) as total_saved
        FROM bookings 
        WHERE guest_email = ? 
        AND promo_code IS NOT NULL 
        AND promo_code != ''
        GROUP BY promo_code
    ");
    $stmt->execute([$user['email']]);
    $used_promotions_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignore error
}

$page_title = 'Mã Ưu đãi - Cổng Thông tin Khách hàng';
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
                    <span class="text-white font-bold">Mã Ưu đãi</span>
                </li>
            </ol>
        </nav>
    </div>
</div>

<div class="min-h-screen bg-gray-50 py-12">
    <div class="container mx-auto px-4">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-serif font-bold text-gray-900 mb-2">Mã Ưu đãi & Khuyến mãi</h1>
            <p class="text-gray-600">Khám phá và lưu các mã giảm giá độc quyền cho chuyến đi tiếp theo của bạn</p>
        </div>

        <?php if (isset($success_message)): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-6 py-4 rounded-lg mb-6">
            <p class="font-semibold"><?php echo htmlspecialchars($success_message); ?></p>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-lg mb-6">
            <p class="font-semibold"><?php echo htmlspecialchars($error_message); ?></p>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- Featured Promotions -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-serif font-bold text-gray-900">Khuyến mãi đang diễn ra</h2>
                        <span class="px-4 py-2 bg-accent text-white rounded-full text-sm font-semibold">
                            <?php echo count($active_promotions); ?> Mã khả dụng
                        </span>
                    </div>

                    <?php if (empty($active_promotions)): ?>
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
                            </svg>
                            <p class="text-gray-500">Hiện tại không có mã khuyến mãi nào.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($active_promotions as $promo): 
                                $is_saved = in_array($promo['code'], $saved_promo_codes);
                                $percentage_used = $promo['usage_limit'] ? ($promo['used_count'] / $promo['usage_limit'] * 100) : 0;
                                $is_hot = $promo['days_left'] <= 7 || $percentage_used >= 80;
                            ?>
                            <div class="border-2 <?php echo $is_hot ? 'border-red-500' : 'border-gray-200'; ?> rounded-lg p-6 hover:shadow-lg transition relative overflow-hidden">
                                
                                <?php if ($is_hot): ?>
                                <div class="absolute top-0 right-0 bg-red-500 text-white px-4 py-1 text-xs font-bold transform rotate-12 translate-x-8 -translate-y-2">
                                    🔥 HOT
                                </div>
                                <?php endif; ?>

                                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                                    <!-- Left: Info -->
                                    <div class="flex-1">
                                        <div class="flex items-start gap-3 mb-3">
                                            <div class="bg-accent bg-opacity-10 p-3 rounded-lg">
                                                <svg class="w-8 h-8 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 class="text-xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($promo['title']); ?></h3>
                                                <p class="text-gray-600 text-sm mb-2"><?php echo htmlspecialchars($promo['description']); ?></p>
                                                
                                                <!-- Discount Badge -->
                                                <div class="inline-block bg-gradient-to-r from-accent to-accent-dark text-white px-4 py-2 rounded-full font-bold text-lg mb-3">
                                                    <?php 
                                                    if ($promo['discount_type'] === 'percentage') {
                                                        echo "GIẢM {$promo['discount_value']}%";
                                                        if ($promo['max_discount']) {
                                                            echo " (Tối đa " . number_format($promo['max_discount'], 0, ',', '.') . "đ)";
                                                        }
                                                    } else {
                                                        echo "GIẢM " . number_format($promo['discount_value'], 0, ',', '.') . "đ";
                                                    }
                                                    ?>
                                                </div>

                                                <!-- Conditions -->
                                                <div class="flex flex-wrap gap-2 mb-3">
                                                    <?php if ($promo['min_nights'] > 1): ?>
                                                        <span class="text-xs bg-blue-100 text-blue-800 px-3 py-1 rounded-full">
                                                            <i class="fas fa-moon mr-1"></i>Tối thiểu <?php echo $promo['min_nights']; ?> đêm
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($promo['min_amount'] > 0): ?>
                                                        <span class="text-xs bg-purple-100 text-purple-800 px-3 py-1 rounded-full">
                                                            <i class="fas fa-money-bill mr-1"></i>Tối thiểu <?php echo number_format($promo['min_amount'], 0, ',', '.'); ?>đ
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if ($promo['days_left'] <= 7): ?>
                                                        <span class="text-xs bg-red-100 text-red-800 px-3 py-1 rounded-full animate-pulse">
                                                            <i class="fas fa-clock mr-1"></i>Còn <?php echo $promo['days_left']; ?> ngày
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-xs bg-gray-100 text-gray-800 px-3 py-1 rounded-full">
                                                            <i class="fas fa-calendar mr-1"></i>HSD: <?php echo date('d/m/Y', strtotime($promo['valid_to'])); ?>
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if ($promo['usage_limit']): ?>
                                                        <span class="text-xs bg-orange-100 text-orange-800 px-3 py-1 rounded-full">
                                                            <i class="fas fa-users mr-1"></i>Còn <?php echo $promo['usage_limit'] - $promo['used_count']; ?>/<?php echo $promo['usage_limit']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right: Code & Actions -->
                                    <div class="flex flex-col items-end gap-3 min-w-[200px]">
                                        <!-- Promo Code -->
                                        <div class="bg-gray-100 border-2 border-dashed border-gray-400 rounded-lg px-6 py-3 text-center">
                                            <p class="text-xs text-gray-600 mb-1">MÃ KHUYẾN MÃI</p>
                                            <p class="font-mono font-bold text-2xl text-accent tracking-wider" id="code-<?php echo $promo['id']; ?>">
                                                <?php echo $promo['code']; ?>
                                            </p>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="flex gap-2 w-full">
                                            <button 
                                                onclick="copyCode('<?php echo $promo['code']; ?>', <?php echo $promo['id']; ?>)"
                                                class="flex-1 bg-accent text-white px-4 py-2 rounded-lg hover:bg-accent-dark transition font-semibold text-sm"
                                            >
                                                <i class="fas fa-copy mr-2"></i>Sao chép
                                            </button>
                                            
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="promo_code" value="<?php echo htmlspecialchars($promo['code']); ?>">
                                                <input type="hidden" name="action" value="<?php echo $is_saved ? 'remove' : 'save'; ?>">
                                                <button 
                                                    type="submit"
                                                    class="px-4 py-2 rounded-lg transition font-semibold text-sm <?php echo $is_saved ? 'bg-gray-200 text-gray-700 hover:bg-gray-300' : 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200'; ?>"
                                                >
                                                    <i class="fas fa-<?php echo $is_saved ? 'check' : 'bookmark'; ?>"></i>
                                                </button>
                                            </form>
                                        </div>

                                        <a href="../booking/rooms.php" class="w-full text-center bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900 transition font-semibold text-sm">
                                            <i class="fas fa-arrow-right mr-2"></i>Đặt ngay
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Sidebar -->
            <div class="space-y-8">
                
                <!-- Saved Promotions -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-serif font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-bookmark text-yellow-500 mr-2"></i>
                        Mã đã lưu (<?php echo count($saved_promotions); ?>)
                    </h3>
                    
                    <?php if (empty($saved_promotions)): ?>
                        <p class="text-gray-500 text-sm text-center py-6">
                            Chưa có mã nào được lưu.
                        </p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($saved_promotions as $saved): ?>
                                <div class="border border-gray-200 rounded-lg p-3">
                                    <div class="flex items-start justify-between mb-2">
                                        <div class="flex-1">
                                            <p class="font-semibold text-sm text-gray-900"><?php echo htmlspecialchars($saved['title']); ?></p>
                                            <p class="font-mono text-accent font-bold text-xs mt-1"><?php echo $saved['code']; ?></p>
                                        </div>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="promo_code" value="<?php echo htmlspecialchars($saved['code']); ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <button type="submit" class="text-gray-400 hover:text-red-500 transition">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <?php if ($saved['days_left'] <= 7): ?>
                                        <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">
                                            Còn <?php echo $saved['days_left']; ?> ngày
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Usage Statistics -->
                <?php if (!empty($used_promotions_stats)): ?>
                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg shadow-lg p-6 border-2 border-green-200">
                    <h3 class="text-xl font-serif font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-chart-line text-green-600 mr-2"></i>
                        Thống kê sử dụng
                    </h3>
                    
                    <div class="space-y-3">
                        <?php 
                        $total_savings = 0;
                        foreach ($used_promotions_stats as $stat): 
                            $total_savings += floatval($stat['total_saved']);
                        ?>
                            <div class="bg-white rounded-lg p-3">
                                <p class="font-mono font-bold text-sm text-accent"><?php echo htmlspecialchars($stat['promo_code']); ?></p>
                                <p class="text-xs text-gray-600">Đã dùng <?php echo $stat['usage_count']; ?> lần</p>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="border-t-2 border-green-200 pt-3 mt-3">
                            <p class="text-sm text-gray-700">Tổng tiết kiệm:</p>
                            <p class="text-2xl font-bold text-green-600">
                                <?php echo number_format($total_savings, 0, ',', '.'); ?> VNĐ
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tips -->
                <div class="bg-blue-50 rounded-lg shadow-lg p-6 border-2 border-blue-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
                        Mẹo sử dụng mã
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-blue-500 mr-2 mt-1"></i>
                            <span>Lưu mã yêu thích để sử dụng sau</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-blue-500 mr-2 mt-1"></i>
                            <span>Kiểm tra điều kiện tối thiểu trước khi đặt</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-blue-500 mr-2 mt-1"></i>
                            <span>Mã có giới hạn số lượng nên đặt sớm</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-blue-500 mr-2 mt-1"></i>
                            <span>Theo dõi ngày hết hạn thường xuyên</span>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function copyCode(code, promoId) {
    // Copy to clipboard
    navigator.clipboard.writeText(code).then(function() {
        // Show success message
        const codeElement = document.getElementById('code-' + promoId);
        const originalText = codeElement.innerHTML;
        codeElement.innerHTML = '<span class="text-green-600"><i class="fas fa-check mr-1"></i>Đã sao chép!</span>';
        
        setTimeout(function() {
            codeElement.innerHTML = originalText;
        }, 2000);
    }).catch(function(err) {
        alert('Không thể sao chép mã. Vui lòng thử lại.');
    });
}
</script>

<?php include '../includes/footer.php'; ?>