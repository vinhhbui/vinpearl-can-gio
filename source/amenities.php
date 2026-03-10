<?php
// Đặt tiêu đề cho trang này
$page_title = 'Tiện ích & Dịch vụ - Vinpearl Cần Giờ';

// Khởi động session để kiểm tra đăng nhập
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['user_id']);

// Gọi header
include 'includes/header.php';
?>

<!-- Page Header -->
<header class="relative h-[300px] md:h-[400px] flex items-center justify-center text-white text-center">
    <!-- Background Image -->
    <div class="absolute inset-0">
        <img src="images\index\spa.jpg" 
             alt="Our Services" 
             class="w-full h-full object-cover">
    </div>
    <!-- Overlay -->
    <div class="absolute inset-0 bg-black bg-opacity-60"></div>
    
    <!-- Content -->
    <div class="relative z-10 p-4">
        <h1 class="text-4xl md:text-6xl font-serif font-bold mb-4">Tiện ích & Dịch vụ</h1>
        <p class="text-lg md:text-xl max-w-2xl mx-auto">Tận hưởng những dịch vụ đẳng cấp thế giới của chúng tôi</p>
    </div>
</header>

<!-- Services Grid Section -->
<main id="services-list" class="py-24 bg-gray-50">
    <div class="container mx-auto px-4">
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
            
            <!-- Service Card 1: Restaurant -->
            <div class="bg-white p-8 rounded-lg shadow-lg text-center transform hover:shadow-2xl hover:-translate-y-2 transition-all duration-500">
                <div class="flex-shrink-0 bg-accent text-white p-4 rounded-full inline-block mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h14a2 2 0 0 0 2-2V2"></path><path d="M5 11v10"></path><path d="M19 11v10"></path><path d="M12 11v10"></path></svg>
                </div>
                <h3 class="text-2xl font-serif font-semibold text-gray-900 mb-3">Nhà hàng Gourmet</h3>
                <p class="text-gray-600 mb-4">Trải nghiệm ẩm thực cao cấp với các món ăn Á-Âu được chế biến bởi các đầu bếp hàng đầu.</p>
                <a href="#" onclick="checkLoginAndRedirect('portal/book_amenity.php'); return false;" class="font-semibold text-accent hover:text-accent-dark transition">Đặt bàn ngay &rarr;</a>
            </div>

            <!-- Service Card 2: Swimming Pool -->
            <div class="bg-white p-8 rounded-lg shadow-lg text-center transform hover:shadow-2xl hover:-translate-y-2 transition-all duration-500">
                <div class="flex-shrink-0 bg-accent text-white p-4 rounded-full inline-block mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6H8a4 4 0 0 0-4 4v2"></path><path d="M10 10v0a4 4 0 0 0 4 4h2"></path><path d="M16 14v0a4 4 0 0 0 4-4v-4"></path></svg>
                </div>
                <h3 class="text-2xl font-serif font-semibold text-gray-900 mb-3">Hồ bơi Vô cực</h3>
                <p class="text-gray-600 mb-4">Thư giãn và trẻ hóa trong hồ bơi vô cực tuyệt đẹp với tầm nhìn ra biển Cần Giờ.</p>
                <a href="#" class="font-semibold text-accent hover:text-accent-dark transition">Tìm hiểu thêm &rarr;</a>
            </div>

            <!-- Service Card 3: Spa & Wellness -->
            <div class="bg-white p-8 rounded-lg shadow-lg text-center transform hover:shadow-2xl hover:-translate-y-2 transition-all duration-500">
                <div class="flex-shrink-0 bg-accent text-white p-4 rounded-full inline-block mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"></path><path d="M12 18v4"></path><path d="M4 12H2"></path><path d="M22 12h-2"></path><path d="m19.07 4.93-1.41 1.41"></path><path d="m6.34 17.66-1.41 1.41"></path><path d="m19.07 19.07-1.41-1.41"></path><path d="m6.34 6.34-1.41-1.41"></path><circle cx="12" cy="12" r="4"></circle></svg>
                </div>
                <h3 class="text-2xl font-serif font-semibold text-gray-900 mb-3">Spa & Sức khỏe</h3>
                <p class="text-gray-600 mb-4">Spa đầy đủ dịch vụ của chúng tôi cung cấp một loạt các liệu pháp để phục hồi cơ thể và tâm trí của bạn.</p>
                <a href="#" onclick="checkLoginAndRedirect('portal/book_amenity.php'); return false;" class="font-semibold text-accent hover:text-accent-dark transition">Xem Gói Spa &rarr;</a>
            </div>

            <!-- Service Card 4: Fitness Center (Mới) -->
            <div class="bg-white p-8 rounded-lg shadow-lg text-center transform hover:shadow-2xl hover:-translate-y-2 transition-all duration-500">
                <div class="flex-shrink-0 bg-accent text-white p-4 rounded-full inline-block mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2z"></path><path d="M12 12H8l4-4 4 4h-4v4"></path></svg> <!-- Placeholder Icon -->
                </div>
                <h3 class="text-2xl font-serif font-semibold text-gray-900 mb-3">Trung tâm Thể hình</h3>
                <p class="text-gray-600 mb-4">Duy trì thói quen tập luyện của bạn với các thiết bị Technogym hiện đại nhất.</p>
                <a href="#" class="font-semibold text-accent hover:text-accent-dark transition">Giờ mở cửa &rarr;</a>
            </div>

            <!-- Service Card 5: Kid's Club (Mới) -->
            <div class="bg-white p-8 rounded-lg shadow-lg text-center transform hover:shadow-2xl hover:-translate-y-2 transition-all duration-500">
                <div class="flex-shrink-0 bg-accent text-white p-4 rounded-full inline-block mb-6">
                     <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12m-2 0a2 2 0 1 0 4 0 2 2 0 1 0-4 0"></path><path d="M4 4v16h16V4Z"></path><path d="M12 4v4"></path><path d="M12 16v4"></path><path d="M4 12h4"></path><path d="M16 12h4"></path></svg> <!-- Placeholder Icon -->
                </div>
                <h3 class="text-2xl font-serif font-semibold text-gray-900 mb-3">Câu lạc bộ Trẻ em</h3>
                <p class="text-gray-600 mb-4">Một không gian vui chơi an toàn và sáng tạo dành cho các vị khách nhỏ tuổi của chúng tôi.</p>
                <a href="#" class="font-semibold text-accent hover:text-accent-dark transition">Xem Hoạt động &rarr;</a>
            </div>

            <!-- Service Card 6: Conference Hall (Mới) -->
            <div class="bg-white p-8 rounded-lg shadow-lg text-center transform hover:shadow-2xl hover:-translate-y-2 transition-all duration-500">
                <div class="flex-shrink-0 bg-accent text-white p-4 rounded-full inline-block mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8V6a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v2"></path><path d="M2 12h20"></path><path d="M6 12v6a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-6"></path></svg> <!-- Placeholder Icon -->
                </div>
                <h3 class="text-2xl font-serif font-semibold text-gray-900 mb-3">Hội nghị & Sự kiện</h3>
                <p class="text-gray-600 mb-4">Các phòng hội nghị linh hoạt và được trang bị đầy đủ cho mọi sự kiện của công ty.</p>
                <a href="contact.php" class="font-semibold text-accent hover:text-accent-dark transition">Gửi Yêu cầu &rarr;</a>
            </div>

        </div>

    </div>
</main>

<script>
// Function để kiểm tra đăng nhập và redirect
function checkLoginAndRedirect(targetUrl) {
    <?php if ($is_logged_in): ?>
        // Đã đăng nhập - redirect đến trang đích
        window.location.href = '/' + targetUrl;
    <?php else: ?>
        // Chưa đăng nhập - redirect đến trang login
        window.location.href = '/login.php?error=login_required';
    <?php endif; ?>
}
</script>

<?php
// Gọi footer
include 'includes/footer.php';
?>