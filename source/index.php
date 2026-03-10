<?php
// Đặt tiêu đề cho trang này, biến $page_title sẽ được sử dụng trong header.php
$page_title = 'Vinpearl Cần Giờ Luxury Resort';
// Gọi header
include 'includes/header.php';
?>

    <!-- Hero Section -->
    <header class="relative h-screen min-h-[600px] flex items-center justify-center text-white text-center">
        <!-- Background Image -->
        <div class="absolute inset-0">
            <img src="https://vinhomegreenparadise.vn/wp-content/uploads/2025/09/vinpearl-themed-hotel-lau-dai-co-tich-giua-thien-duong-nghi-duong-vinhomes-green-paradise.webp" 
                 alt="Luxury Hotel Lobby" 
                 class="w-full h-full object-cover">
        </div>
        <!-- Overlay -->
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
        
        <!-- Content -->
        <div class="relative z-10 p-4">
            <h1 class="text-5xl md:text-7xl lg:text-8xl font-serif font-bold mb-4 animate-fade-in-down">Siêu thượng lưu thầm lặng</h1>
            <p class="text-xl md:text-2xl mb-8 max-w-2xl mx-auto">Resort đẳng cấp lấn biển</p>
            <a href="../booking/rooms.php" class="bg-accent text-white px-10 py-4 rounded-md font-semibold text-lg hover:bg-accent-dark transition-all duration-300 transform hover:scale-105 shadow-lg">
                Xem phòng ngay
            </a>
        </div>
    </header>

    <!-- Booking Form Section - Chuyển thẳng sang ../booking/rooms.php -->
    <section class="relative z-20 -mt-24">
        <div class="container mx-auto px-4">
            <div class="bg-white shadow-2xl rounded-lg p-8 md:p-12">
                <!-- Form này sẽ submit đến trang xử lý booking -->
                <form action="../booking/rooms.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 items-end">
                    <!-- Check In -->
                    <div>
                        <label for="checkin" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar-check text-accent mr-1"></i>
                            Check In
                        </label>
                        <input type="date" 
                               id="checkin" 
                               name="checkin" 
                               min="<?php echo date('Y-m-d'); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent" 
                               required>
                    </div>
                    <!-- Check Out -->
                    <div>
                        <label for="checkout" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-calendar-times text-accent mr-1"></i>
                            Check Out
                        </label>
                        <input type="date" 
                               id="checkout" 
                               name="checkout" 
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent" 
                               required>
                    </div>
                    <!-- Người lớn -->
                    <div>
                        <label for="adults" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user text-accent mr-1"></i>
                            Người lớn
                        </label>
                        <select id="adults" name="adults" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent bg-white">
                            <?php for($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php if($i == 2) echo 'selected'; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <!-- Trẻ em -->
                    <div>
                        <label for="children" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-child text-accent mr-1"></i>
                            Trẻ em
                        </label>
                        <select id="children" name="children" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent bg-white">
                            <?php for($i = 0; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <!-- Submit Button -->
                    <button type="submit" class="bg-accent text-white px-6 py-3 rounded-lg font-semibold w-full h-full hover:bg-accent-dark transition-all duration-300 text-lg">
                        <i class="fas fa-search mr-2"></i>Tìm phòng trống
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- About Us Section -->
    <section id="about" class="py-24 bg-gray-50">
        <div class="container mx-auto px-4 grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
            <!-- Text Content -->
            <div>
                <span class="text-sm font-semibold text-accent uppercase tracking-wider">Chào mừng đến Vinpearl Cần Giờ</span>
                <h2 class="text-4xl lg:text-5xl font-serif font-bold text-gray-900 my-4">Một Tầm Nhìn Mới Về Sự Sang Trọng</h2>
                <p class="text-gray-600 text-lg mb-6 leading-relaxed">
                    Vinpearl Cần Giờ là biểu tượng của sự sang trọng, mang đến sự thoải mái vô song và dịch vụ bespoke. Nằm giữa trung tâm, chúng tôi cung cấp một lối thoát yên bình khỏi sự hối hả hàng ngày.
                </p>
                <p class="text-gray-600 mb-8">
                    Cam kết của chúng tôi về sự xuất sắc được thể hiện trong từng chi tiết, từ các phòng được thiết kế trang nhã đến các tiện nghi đẳng cấp thế giới.
                </p>
                <a href="blog/blog_post.php?id=4" class="bg-gray-800 text-white px-8 py-3 rounded-md font-semibold hover:bg-gray-900 transition-all duration-300 shadow-lg">
                    Tìm hiểu thêm
                </a>
            </div>
            <!-- Images -->
            <div class="relative h-80 md:h-[500px]">
                <img src="..\images\index\lounge.jpg" 
                     alt="Hotel Lounge" 
                     class="rounded-lg shadow-xl absolute top-0 left-0 w-4/5 h-auto object-cover transform hover:scale-105 transition-transform duration-500">
                <img src="..\images\index\detail.jpg" 
                     alt="Hotel Detail" 
                     class="rounded-lg shadow-xl absolute bottom-0 right-0 w-1/2 h-auto object-cover border-8 border-white transform hover:scale-105 transition-transform duration-500">
            </div>
        </div>
    </section>

    <!-- Rooms & Suites Section -->
    <section id="rooms" class="py-24 bg-white">
        <div class="container mx-auto px-4">
            <!-- Section Header -->
            <div class="text-center mb-16">
                <span class="text-sm font-semibold text-accent uppercase tracking-wider">Phòng của chúng tôi</span>
                <h2 class="text-4xl lg:text-5xl font-serif font-bold text-gray-900 mt-2">Phòng & Suites</h2>
            </div>
            
            <!-- Rooms Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
<?php
// Include DB connection
include_once 'includes/db_connect.php';

// Fetch rooms with features
$rooms = [];
try {
    // Lấy tất cả phòng active, sắp xếp theo thứ tự hiển thị
    $stmt = $pdo->query("
        SELECT 
            r.id, 
            r.room_type_name, 
            r.description, 
            r.price_per_night, 
            r.max_occupancy,
            r.max_adults,
            r.max_children,
            r.num_beds,
            r.bed_type,
            r.room_size_sqm,
            r.image_url
        FROM rooms r
        WHERE r.is_active = 1
        ORDER BY r.display_order ASC, r.price_per_night ASC
    ");
    $all_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // --- XỬ LÝ LỌC TRÙNG VÀ GIỚI HẠN 3 PHÒNG ---
    $unique_rooms = [];
    $seen_types = [];

    foreach ($all_rooms as $room) {
        // Nếu đã đủ 3 phòng thì dừng lại
        if (count($unique_rooms) >= 3) {
            break;
        }

        // Kiểm tra trùng tên loại phòng
        if (!in_array($room['room_type_name'], $seen_types)) {
            $seen_types[] = $room['room_type_name'];
            
            // Lấy features cho phòng này
            $stmt_feat = $pdo->prepare("SELECT feature_name, icon FROM room_features WHERE room_id = ? LIMIT 3");
            $stmt_feat->execute([$room['id']]);
            $room['features'] = $stmt_feat->fetchAll(PDO::FETCH_ASSOC);
            
            $unique_rooms[] = $room;
        }
    }
} catch (PDOException $e) {
    // Log error
}

// Render rooms
if (count($unique_rooms) === 0) {
    echo '<div class="col-span-full text-center text-gray-600">Không có phòng nào. Vui lòng kiểm tra lại sau.</div>';
} else {
    foreach ($unique_rooms as $room) {
        $image = htmlspecialchars($room['image_url'] ?: 'https://placehold.co/600x400/ddd/888?text=No+Image');
        $name = htmlspecialchars($room['room_type_name']);
        $desc = htmlspecialchars($room['description']);
        $price = number_format((float)$room['price_per_night'], 0, ',', '.');
        
        echo <<<HTML
                <div class="border border-gray-200 rounded-lg shadow-lg overflow-hidden transform hover:shadow-2xl hover:-translate-y-2 transition-all duration-500">
                    <div class="relative overflow-hidden">
                        <img src="{$image}" alt="{$name}" class="w-full h-64 object-cover transition-transform duration-500 hover:scale-110">
                        <div class="absolute top-4 left-4 bg-white text-accent px-4 py-1 rounded-full text-sm font-semibold">{$price} VNĐ / đêm</div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-2xl font-serif font-semibold text-gray-900 mb-3">{$name}</h3>
                        <p class="text-gray-600 mb-4">{$desc}</p>
                        
                        <!-- Room Info -->
                        <div class="flex flex-wrap items-center gap-4 mb-4 text-sm text-gray-600">
                            <span class="flex items-center">
                                <i class="fas fa-users mr-1 text-accent"></i>
                                {$room['max_occupancy']} khách
                            </span>
                            <span class="flex items-center">
                                <i class="fas fa-bed mr-1 text-accent"></i>
                                {$room['num_beds']} giường
                            </span>
                            <span class="flex items-center">
                                <i class="fas fa-ruler-combined mr-1 text-accent"></i>
                                {$room['room_size_sqm']} m²
                            </span>
                        </div>
                        
HTML;
        
        // Features
        if (!empty($room['features'])) {
            echo '<div class="flex flex-wrap gap-2 mb-4">';
            foreach ($room['features'] as $feature) {
                $feat_name = htmlspecialchars($feature['feature_name']);
                $feat_icon = htmlspecialchars($feature['icon']);
                echo "<span class='inline-flex items-center text-xs text-gray-600 bg-gray-100 px-2 py-1 rounded'>";
                echo "<i class='fas {$feat_icon} mr-1 text-accent'></i>{$feat_name}";
                echo "</span>";
            }
            echo '</div>';
        }
        
        echo <<<HTML
                        <a href="booking/room_detail.php?id={$room['id']}" class="inline-block mt-6 text-accent font-semibold hover:text-accent-dark transition">Xem chi tiết &rarr;</a>
                    </div>
                </div>
HTML;
    }
}
?>
            </div>
            
            <!-- View All Button -->
            <div class="text-center mt-12">
                <a href="../booking/rooms.php" class="inline-block bg-accent text-white px-8 py-3 rounded-lg font-semibold hover:bg-accent-dark transition-all duration-300 text-lg">
                    Xem tất cả phòng
                </a>
            </div>
        </div>
    </section>

    <!-- Services/Features Section -->
    <section id="services" class="py-24 bg-gray-50">
        <div class="container mx-auto px-4 grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
            <!-- Image -->
            <div>
                <img src="..\images\index\ho_boi.jpg" 
                     alt="Hotel Pool" 
                     class="rounded-lg shadow-xl w-full h-full object-cover">
            </div>
            <!-- Text & Features -->
            <div>
                <span class="text-sm font-semibold text-accent uppercase tracking-wider">Tiện ích</span>
                <h2 class="text-4xl lg:text-5xl font-serif font-bold text-gray-900 my-4">Tiện ích tại Vinpearl Cần Giờ</h2>
                <p class="text-gray-600 text-lg mb-10 leading-relaxed">
                    Chúng tôi cung cấp một loạt các dịch vụ và tiện ích để làm cho kỳ nghỉ của bạn thực sự khó quên.
                </p>
                <ul class="space-y-8">
                    <!-- Feature 1 -->
                    <li class="flex items-start space-x-4">
                        <div class="flex-shrink-0 bg-accent text-white p-3 rounded-full">
                            <!-- Restaurant Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h14a2 2 0 0 0 2-2V2"></path><path d="M5 11v10"></path><path d="M19 11v10"></path><path d="M12 11v10"></path></svg>
                        </div>
                        <div>
                            <h4 class="text-2xl font-serif font-semibold text-gray-900">Nhà hàng</h4>
                            <p class="text-gray-600 mt-1">Trải nghiệm ẩm thực cao cấp tại nhà hàng trong nhà của chúng tôi với ẩm thực gourmet.</p>
                        </div>
                    </li>
                    <!-- Feature 2 -->
                    <li class="flex items-start space-x-4">
                        <div class="flex-shrink-0 bg-accent text-white p-3 rounded-full">
                            <!-- Pool Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6H8a4 4 0 0 0-4 4v2"></path><path d="M10 10v0a4 4 0 0 0 4 4h2"></path><path d="M16 14v0a4 4 0 0 0 4-4v-4"></path></svg>
                        </div>
                        <div>
                            <h4 class="text-2xl font-serif font-semibold text-gray-900">Hồ bơi</h4>
                            <p class="text-gray-600 mt-1">Thư giãn và trẻ hóa trong hồ bơi vô cực tuyệt đẹp của chúng tôi.</p>
                        </div>
                    </li>
                    <!-- Feature 3 -->
                    <li class="flex items-start space-x-4">
                        <div class="flex-shrink-0 bg-accent text-white p-3 rounded-full">
                            <!-- Spa Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"></path><path d="M12 18v4"></path><path d="M4 12H2"></path><path d="M22 12h-2"></path><path d="m19.07 4.93-1.41 1.41"></path><path d="m6.34 17.66-1.41 1.41"></path><path d="m19.07 19.07-1.41-1.41"></path><path d="m6.34 6.34-1.41-1.41"></path><circle cx="12" cy="12" r="4"></circle></svg>
                        </div>
                        <div>
                            <h4 class="text-2xl font-serif font-semibold text-gray-900">Spa & Sức khỏe</h4>
                            <p class="text-gray-600 mt-1">Spa đầy đủ dịch vụ của chúng tôi cung cấp một loạt các liệu pháp để phục hồi cơ thể và tâm trí của bạn.</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-24 relative" style="background-image: url('../images/index/night_hotel.jpg'); background-size: cover; background-position: center; background-attachment: fixed;">
        <!-- Overlay -->
        <div class="absolute inset-0 bg-black bg-opacity-75"></div>
        
        <div class="container mx-auto px-4 relative z-10">
            <!-- Section Header -->
            <div class="text-center mb-16">
                <span class="text-sm font-semibold text-accent uppercase tracking-wider">Đánh giá</span>
                <h2 class="text-4xl lg:text-5xl font-serif font-bold text-white mt-2">Khách của chúng tôi nói gì</h2>
            </div>
            
            <!-- Testimonials Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-white">
                <!-- Testimonial Card 1 -->
                <div class="bg-white bg-opacity-10 p-8 rounded-lg backdrop-blur-sm border border-white border-opacity-20 text-center">
                    <p class="text-lg italic mb-6">"Đây là trải nghiệm khách sạn tuyệt vời nhất tôi từng có. Nhân viên vô cùng chu đáo, và phòng ốc thì sang trọng."</p>
                    <div class="font-bold text-xl font-serif">Sarah Johnson</div>
                    <div class="text-sm text-gray-300">Cặp đôi từ New York</div>
                </div>
                <!-- Testimonial Card 2 -->
                <div class="bg-white bg-opacity-10 p-8 rounded-lg backdrop-blur-sm border border-white border-opacity-20 text-center">
                    <p class="text-lg italic mb-6">"Nhà hàng đẳng cấp thế giới, và tầm nhìn ra hồ bơi không thể quên được. Chúng tôi đã lên kế hoạch quay trở lại."</p>
                    <div class="font-bold text-xl font-serif">Michael Chen</div>
                    <div class="text-sm text-gray-300">Khách đi công tác</div>
                </div>
                <!-- Testimonial Card 3 -->
                <div class="bg-white bg-opacity-10 p-8 rounded-lg backdrop-blur-sm border border-white border-opacity-20 text-center">
                    <p class="text-lg italic mb-6">"Một kỳ nghỉ hoàn hảo. Spa rất thư giãn, và sự chú ý đến chi tiết trong phòng thật hoàn hảo. Rất khuyến khích!"</p>
                    <div class="font-bold text-xl font-serif">Emily Rodriguez</div>
                    <div class="text-sm text-gray-300">Kỳ nghỉ gia đình</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Blog Section -->
    <section id="blog" class="py-24 bg-white">
        <div class="container mx-auto px-4">
            <!-- Section Header -->
            <div class="text-center mb-16">
                <span class="text-sm font-semibold text-accent uppercase tracking-wider">Blog của chúng tôi</span>
                <h2 class="text-4xl lg:text-5xl font-serif font-bold text-gray-900 mt-2">Tin tức & Sự kiện</h2>
            </div>
            
            <!-- Blog Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php
                // Lấy 3 bài viết mới nhất từ database
                try {
                    $stmt_blog = $pdo->query("SELECT * FROM news ORDER BY created_at DESC LIMIT 3");
                    $latest_posts = $stmt_blog->fetchAll(PDO::FETCH_ASSOC);

                    if (count($latest_posts) > 0) {
                        foreach ($latest_posts as $post) {
                            $blog_img = !empty($post['image_url']) ? $post['image_url'] : 'https://placehold.co/600x400/ddd/888?text=No+Image';
                            $blog_date = date('M d, Y', strtotime($post['created_at']));
                            $blog_title = htmlspecialchars($post['title']);
                            // Lấy đoạn trích ngắn từ nội dung (bỏ thẻ HTML)
                            $blog_excerpt = htmlspecialchars(substr(strip_tags($post['content']), 0, 100)) . '...';
                            $blog_link = "blog/blog_post.php?id=" . $post['id']; // Đường dẫn đến trang chi tiết

                            echo <<<HTML
                            <div class="border border-gray-200 rounded-lg shadow-lg overflow-hidden transform hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 flex flex-col h-full">
                                <img src="{$blog_img}" alt="{$blog_title}" class="w-full h-64 object-cover">
                                <div class="p-6 flex flex-col flex-grow">
                                    <div class="text-sm text-gray-500 mb-2">{$blog_date} | {$post['category']}</div>
                                    <h3 class="text-2xl font-serif font-semibold text-gray-900 mb-3 line-clamp-2">{$blog_title}</h3>
                                    <p class="text-gray-600 mb-4 line-clamp-3 flex-grow">{$blog_excerpt}</p>
                                    <a href="{$blog_link}" class="font-semibold text-accent hover:text-accent-dark transition mt-auto">Đọc thêm &rarr;</a>
                                </div>
                            </div>
HTML;
                        }
                    } else {
                        echo '<div class="col-span-3 text-center text-gray-500">Chưa có bài viết nào.</div>';
                    }
                } catch (PDOException $e) {
                    echo '<div class="col-span-3 text-center text-red-500">Lỗi tải tin tức.</div>';
                }
                ?>
            </div>
            
            <!-- View All Button -->
            <div class="text-center mt-12">
                <a href="blog/blog.php" class="inline-block bg-accent text-white px-8 py-3 rounded-lg font-semibold hover:bg-accent-dark transition-all duration-300 text-lg">
                    Xem tất cả tin tức
                </a>
            </div>
        </div>
    </section>

<script>
// Auto-update checkout date when checkin changes
document.getElementById('checkin').addEventListener('change', function() {
    const checkinDate = new Date(this.value);
    const checkoutDate = new Date(checkinDate);
    checkoutDate.setDate(checkoutDate.getDate() + 1);
    
    const checkoutInput = document.getElementById('checkout');
    const formattedDate = checkoutDate.toISOString().split('T')[0];
    
    if (!checkoutInput.value || new Date(checkoutInput.value) <= checkinDate) {
        checkoutInput.value = formattedDate;
    }
    checkoutInput.min = formattedDate;
});
</script>

<?php
// Gọi footer
include 'includes/footer.php';
?>