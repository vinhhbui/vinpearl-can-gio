<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Lấy thông tin user từ database nếu đã đăng nhập
$current_user = null;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/db_connect.php';
    try {
        $stmt = $pdo->prepare("SELECT username, full_name, ranking FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Cập nhật session với thông tin mới nhất
        if ($current_user) {
            $_SESSION['username'] = $current_user['username'];
            $_SESSION['ranking'] = $current_user['ranking'];
        }
    } catch (PDOException $e) {
        // Nếu có lỗi, sử dụng thông tin từ session
        $current_user = [
            'username' => $_SESSION['username'] ?? 'User',
            'full_name' => $_SESSION['full_name'] ?? null,
            'ranking' => $_SESSION['ranking'] ?? 'Silver'
        ];
    }
}

// Xác định tên hiển thị (ưu tiên full_name, nếu không có thì dùng username)
$display_name = $current_user ? ($current_user['full_name'] ?: $current_user['username']) : 'User';
$user_ranking = $current_user['ranking'] ?? 'Silver';

// Màu và icon cho ranking
$ranking_colors = [
    'Silver' => 'text-gray-400',
    'Gold' => 'text-yellow-400',
    'Diamond' => 'text-blue-400'
];
$ranking_icons = [
    'Silver' => 'fa-medal',
    'Gold' => 'fa-crown',
    'Diamond' => 'fa-gem'
];
$ranking_color = $ranking_colors[$user_ranking] ?? 'text-gray-400';
$ranking_icon = $ranking_icons[$user_ranking] ?? 'fa-medal';
?>
<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Tiêu đề động -->
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Vinpearl Cần Giờ - Luxury Hotel & Resort'; ?></title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Custom Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#010c20ff',
                        secondary: '#f3f4f6',
                        accent: '#002366',
                        'accent-dark': '#7e98c9ff',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        serif: ['Lora', 'serif'],
                    }
                }
            }
        }
    </script>
    
    <style>
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #7e7fc5ff; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #a68b6a; }
        .animate-fade-in-down { animation: fadeInDown 1s ease-out; }
        @keyframes fadeInDown {
            0% { opacity: 0; transform: translateY(-20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="font-sans text-gray-800 antialiased">

    <!-- Navigation -->
    <nav class="bg-primary shadow-lg fixed w-full z-50 transition-all duration-300" id="navbar">
        <div class="container mx-auto px-4 flex justify-between items-center h-20">
            <!-- Logo -->
            <a href="/index.php" class="text-white text-3xl font-serif font-bold tracking-widest hover:text-accent transition">VINPEARL CẦN GIỜ</a>
            
            <!-- Desktop Nav Links -->
            <div class="hidden md:flex space-x-8 items-center">
                <a href="/index.php" class="text-white text-sm uppercase tracking-wider font-medium hover:text-accent transition">Trang chủ</a>
                <a href="/index.php#about" class="text-white text-sm uppercase tracking-wider font-medium hover:text-accent transition">Giới thiệu</a>
                <a href="/booking/rooms.php" class="text-white text-sm uppercase tracking-wider font-medium hover:text-accent transition">Phòng nghỉ</a>
                <a href="/amenities.php" class="text-white text-sm uppercase tracking-wider font-medium hover:text-accent transition">Dịch vụ</a>
                <a href="../blog/blog.php" class="text-white text-sm uppercase tracking-wider font-medium hover:text-accent transition">Tin tức</a>
                <a href="/contact.php" class="text-white text-sm uppercase tracking-wider font-medium hover:text-accent transition">Liên hệ</a>
            </div>
            
            <!-- Auth / User Menu -->
            <?php if (isset($_SESSION['user_id']) && $current_user): ?>
                <div class="relative group hidden md:block">
                    <button class="flex items-center space-x-2 text-white hover:text-accent transition focus:outline-none py-2">
                        <div class="flex flex-col items-end">
                            <span class="font-medium text-sm text-white">
                                Xin chào, <?php echo htmlspecialchars($display_name); ?>
                            </span>
                            <span class="text-xs <?php echo $ranking_color; ?> flex items-center gap-1">
                                <i class="fas <?php echo $ranking_icon; ?>"></i>
                                <?php echo htmlspecialchars($user_ranking); ?>
                            </span>
                        </div>
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <!-- Dropdown -->
                    <div class="absolute right-0 mt-2 w-56 bg-white rounded-md shadow-lg py-1 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50 transform origin-top-right">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <p class="text-sm text-gray-500">Đăng nhập với tên</p>
                            <p class="text-sm font-bold text-gray-900 truncate">
                                <?php echo htmlspecialchars($display_name); ?>
                            </p>
                            <div class="flex items-center gap-1 mt-1">
                                <i class="fas <?php echo $ranking_icon; ?> text-xs <?php echo str_replace('text-', 'text-', $ranking_color); ?>"></i>
                                <span class="text-xs font-semibold <?php echo str_replace('text-', 'text-', $ranking_color); ?>">
                                    <?php echo htmlspecialchars($user_ranking); ?>
                                </span>
                            </div>
                        </div>
                        <a href="/portal/index.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-accent transition">
                            <i class="fas fa-columns w-5 text-center mr-2"></i>Bảng điều khiển
                        </a>
                        <a href="/portal/bookings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-accent transition">
                            <i class="fas fa-calendar-check w-5 text-center mr-2"></i>Đơn đặt phòng
                        </a>
                        <a href="/portal/book_amenity.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-accent transition">
                            <i class="fas fa-concierge-bell w-5 text-center mr-2"></i>Đặt dịch vụ
                        </a>
                        <a href="/portal/profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-accent transition">
                            <i class="fas fa-user w-5 text-center mr-2"></i>Hồ sơ cá nhân
                        </a>
                        <div class="border-t border-gray-100"></div>
                        <a href="/auth.php?action=logout" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
                            <i class="fas fa-sign-out-alt w-5 text-center mr-2"></i>Đăng xuất
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/login.php" class="hidden md:inline-block bg-accent text-white px-6 py-2 rounded-sm text-sm font-semibold hover:bg-accent-dark transition shadow-md uppercase tracking-wider">
                    Đăng nhập
                </a>
            <?php endif; ?>
            
            <!-- Mobile Menu Button -->
            <button id="mobileMenuButton" class="md:hidden text-white text-2xl focus:outline-none">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <!-- Mobile Menu (Dropdown) -->
        <div id="mobileMenu" class="hidden md:hidden bg-primary border-t border-gray-800">
            <a href="/index.php" class="block text-white py-3 px-4 hover:bg-gray-800 border-b border-gray-800">Trang chủ</a>
            <a href="/booking/rooms.php" class="block text-white py-3 px-4 hover:bg-gray-800 border-b border-gray-800">Phòng nghỉ</a>
            <a href="/amenities.php" class="block text-white py-3 px-4 hover:bg-gray-800 border-b border-gray-800">Dịch vụ</a>
            <a href="/contact.php" class="block text-white py-3 px-4 hover:bg-gray-800 border-b border-gray-800">Liên hệ</a>
            
            <?php if (isset($_SESSION['user_id']) && $current_user): ?>
                <div class="bg-gray-800 p-4">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 rounded-full bg-accent flex items-center justify-center text-white font-bold text-lg mr-3">
                            <?php echo strtoupper(substr($display_name, 0, 1)); ?>
                        </div>
                        <div>
                            <div class="text-white font-medium"><?php echo htmlspecialchars($display_name); ?></div>
                            <div class="text-xs <?php echo $ranking_color; ?> flex items-center gap-1">
                                <i class="fas <?php echo $ranking_icon; ?>"></i>
                                <?php echo htmlspecialchars($user_ranking); ?>
                            </div>
                        </div>
                    </div>
                    <a href="/portal/index.php" class="block text-gray-300 py-2 hover:text-white">
                        <i class="fas fa-columns w-6"></i> Bảng điều khiển
                    </a>
                    <a href="/portal/bookings.php" class="block text-gray-300 py-2 hover:text-white">
                        <i class="fas fa-calendar-check w-6"></i> Đơn đặt phòng
                    </a>
                    <a href="/portal/book_amenity.php" class="block text-gray-300 py-2 hover:text-white">
                        <i class="fas fa-concierge-bell w-6"></i> Đặt dịch vụ
                    </a>
                    <a href="/portal/profile.php" class="block text-gray-300 py-2 hover:text-white">
                        <i class="fas fa-user w-6"></i> Hồ sơ cá nhân
                    </a>
                    <a href="/auth.php?action=logout" class="block text-red-400 py-2 hover:text-red-300 mt-2">
                        <i class="fas fa-sign-out-alt w-6"></i> Đăng xuất
                    </a>
                </div>
            <?php else: ?>
                <a href="/login.php" class="block text-accent py-3 px-4 font-bold hover:bg-gray-800">Đăng nhập / Đăng ký</a>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- Spacer for Fixed Navbar -->
    <div class="h-20"></div>

    <script>
        // Mobile Menu Toggle
        const btn = document.getElementById('mobileMenuButton');
        const menu = document.getElementById('mobileMenu');
        const navbar = document.getElementById('navbar');

        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });
        
        // Navbar Scroll Effect
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('bg-opacity-95', 'backdrop-blur-sm');
            } else {
                navbar.classList.remove('bg-opacity-95', 'backdrop-blur-sm');
            }
        });
    </script>