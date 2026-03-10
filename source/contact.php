<?php
// Đặt tiêu đề cho trang này
$page_title = 'Liên hệ - Vinpearl Cần Giờ';
// Gọi header
include 'includes/header.php';
// Đảm bảo session đã được start (thường header.php đã có, nhưng kiểm tra lại nếu cần)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!-- Page Header -->
<header class="relative h-[300px] md:h-[400px] flex items-center justify-center text-white text-center">
    <!-- Background Image -->
    <div class="absolute inset-0">
        <img src="images/index/lienhe.jpg" 
             alt="Contact Us" 
             class="w-full h-full object-cover">
    </div>
    <!-- Overlay -->
    <div class="absolute inset-0 bg-black bg-opacity-60"></div>
    
    <!-- Content -->
    <div class="relative z-10 p-4">
        <h1 class="text-4xl md:text-6xl font-serif font-bold mb-4">Liên hệ</h1>
        <p class="text-lg md:text-xl max-w-2xl mx-auto">Chúng tôi rất mong được lắng nghe từ bạn</p>
    </div>
</header>

<!-- Contact Section -->
<main id="contact-page" class="py-24 bg-white">
    <div class="container mx-auto px-4">
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
            
            <!-- Contact Form (2/3 width) -->
            <div class="lg:col-span-2 bg-gray-50 p-8 rounded-lg shadow-lg">
                <h2 class="text-3xl font-serif font-bold text-gray-900 mb-6">Gửi tin nhắn cho chúng tôi</h2>

                <!-- HIỂN THỊ THÔNG BÁO LỖI HOẶC THÀNH CÔNG -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <strong class="font-bold">Thành công!</strong>
                        <span class="block sm:inline"><?php echo $_SESSION['success']; ?></span>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <strong class="font-bold">Lỗi!</strong>
                        <span class="block sm:inline"><?php echo $_SESSION['error']; ?></span>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                <!-- KẾT THÚC PHẦN THÔNG BÁO -->

                <form action="api/submit_contact.php" method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Họ tên</label>
                            <input type="text" id="name" name="name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent" required>
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                            <input type="email" id="email" name="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent" required>
                        </div>
                    </div>
                    <div>
                        <label for="subject" class="block text-sm font-semibold text-gray-700 mb-2">Chủ đề</label>
                        <input type="text" id="subject" name="subject" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent">
                    </div>
                    <div>
                        <label for="message" class="block text-sm font-semibold text-gray-700 mb-2">Nội dung tin nhắn</label>
                        <textarea id="message" name="message" rows="6" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent" required></textarea>
                    </div>
                    <div>
                        <button type="submit" class="bg-accent text-white px-8 py-3 rounded-lg font-semibold hover:bg-accent-dark transition-all duration-300 shadow-lg">
                            Gửi tin nhắn
                        </button>
                    </div>
                </form>
            </div>

            <!-- Contact Info (1/3 width) -->
            <div class="space-y-8">
                <div class="bg-gray-50 p-8 rounded-lg shadow-lg">
                    <h3 class="text-2xl font-serif font-semibold text-gray-900 mb-4">Thông tin liên hệ</h3>
                    <ul class="space-y-4 text-gray-700">
                        <li class="flex items-start space-x-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0 mt-1 text-accent"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            <span>Khu đô thị lấn biển, TpHCM</span>
                        </li>
                        <li class="flex items-center space-x-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0 text-accent"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            <span>(+84) 228-833-474</span>
</span>
                        </li>
                        <li class="flex items-center space-x-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0 text-accent"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                            <span>info@vinpearlcangio.com</span>
                        </li>
                    </ul>
                </div>
                <!-- Map -->
                <div class.="rounded-lg shadow-lg overflow-hidden">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d15697.53985944209!2d106.92864114487405!3d10.39096776665068!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x317569004e7a591d%3A0x29a3bd5112a0fa55!2sVinhomes%20Green%20Paradise%20-%20C%E1%BA%A7n%20Gi%E1%BB%9D!5e0!3m2!1svi!2sus!4v1762673373766!5m2!1svi!2sus" width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>

        </div>
    </div>
</main>

<?php
// Gọi footer
include 'includes/footer.php';
?>