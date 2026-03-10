<?php
// 1. KIỂM TRA ĐĂNG NHẬP TRƯỚC KHI INCLUDE HEADER
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: portal/index.php');
    exit;
}

$page_title = 'Đăng ký Tài khoản - Vinpearl Cần Giờ';
include 'includes/header.php';
?>

<header class="relative h-[250px] md:h-[300px] flex items-center justify-center text-white text-center">
    <div class="absolute inset-0">
        <img src="images/index/night_hotel.jpg" alt="Register" class="w-full h-full object-cover">
    </div>
    <div class="absolute inset-0 bg-black bg-opacity-60"></div>
    <div class="relative z-10 p-4">
        <h1 class="text-4xl md:text-5xl font-serif font-bold">Tạo Tài khoản</h1>
    </div>
</header>

<main class="py-20 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="max-w-lg mx-auto bg-white p-8 md:p-12 rounded-lg shadow-xl">
            
            <!-- Thông báo lỗi/thành công chung -->
            <div id="alert-message" class="hidden px-4 py-3 rounded relative mb-4 text-sm" role="alert"></div>

            <!-- BƯỚC 1: FORM ĐĂNG KÝ -->
            <div id="step-register">
                <h2 class="text-2xl font-serif font-bold text-gray-900 mb-6 text-center">Đăng ký Thành viên</h2>
                <form id="registerForm" class="space-y-6">
                    <div>
                        <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">Tên đăng nhập</label>
                        <input type="text" id="username" name="username" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent" required>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                        <input type="email" id="email" name="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent" required>
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">Số điện thoại</label>
                        <input type="tel" id="phone" name="phone" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent" required>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Mật khẩu</label>
                        <input type="password" id="password" name="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent" required>
                        
                        <div class="mt-2">
                            <div id="password-strength-bar" class="h-1 w-full bg-gray-200 rounded-full overflow-hidden mt-2">
                                <div id="password-strength-fill" class="h-full transition-all duration-300 w-0"></div>
                            </div>
                            <span id="password-strength-text" class="text-xs text-gray-500 block mt-1 text-right"></span>
                        </div>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Xác nhận Mật khẩu</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent" required>
                    </div>

                    <div>
                        <button type="submit" id="btn-register" class="bg-accent text-white px-6 py-3 rounded-lg font-semibold w-full hover:bg-accent-dark transition-all duration-300 text-lg flex justify-center items-center">
                            <span>Tiếp tục</span>
                            <svg id="loading-spinner" class="animate-spin ml-2 h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="text-center text-gray-600">
                        Bạn đã có tài khoản? <a href="login.php" class="text-accent font-semibold hover:underline">Đăng nhập ngay</a>
                    </div>
                </form>
            </div>

            <!-- BƯỚC 2: FORM NHẬP OTP (Ẩn mặc định) -->
            <div id="step-otp" class="hidden">
                <h2 class="text-2xl font-serif font-bold text-gray-900 mb-2 text-center">Xác thực Email</h2>
                <p class="text-gray-600 text-center mb-6">Mã OTP đã được gửi đến <strong id="otp-email-display"></strong>. Vui lòng kiểm tra hộp thư (kể cả mục Spam).</p>
                
                <form id="otpForm" class="space-y-6">
                    <div>
                        <label for="otp_code" class="block text-sm font-semibold text-gray-700 mb-2">Nhập mã OTP (6 số)</label>
                        <input type="text" id="otp_code" name="otp_code" maxlength="6" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent text-center text-2xl tracking-widest" required>
                    </div>

                    <div>
                        <button type="submit" id="btn-verify" class="bg-green-600 text-white px-6 py-3 rounded-lg font-semibold w-full hover:bg-green-700 transition-all duration-300 text-lg">
                            Xác nhận & Hoàn tất
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <button type="button" id="btn-resend" class="text-sm text-accent hover:underline">Gửi lại mã OTP</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</main>

<script>
// === Đánh giá độ mạnh mật khẩu ===
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('password-strength-fill');
    const strengthText = document.getElementById('password-strength-text');

    let score = 0;
    if (!password) {
        strengthBar.style.width = '0%';
        strengthText.textContent = '';
        return;
    }
    if (password.length >= 8) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    let width = '0%';
    let color = 'bg-gray-200';
    let text = '';

    switch (score) {
        case 1: width = '20%'; color = 'bg-red-500'; text = 'Rất yếu'; break;
        case 2: width = '40%'; color = 'bg-yellow-500'; text = 'Yếu'; break;
        case 3: width = '60%'; color = 'bg-blue-400'; text = 'Trung bình'; break;
        case 4: width = '80%'; color = 'bg-green-500'; text = 'Mạnh'; break;
        case 5: width = '100%'; color = 'bg-green-700'; text = 'Rất mạnh'; break;
        default: width = '10%'; color = 'bg-red-500'; text = 'Quá ngắn';
    }
    
    strengthBar.className = `h-full transition-all duration-300 ${color}`;
    strengthBar.style.width = width;
    strengthText.textContent = text;
});

// === Xử lý AJAX gửi OTP và Xác thực ===
const alertBox = document.getElementById('alert-message');
const stepRegister = document.getElementById('step-register');
const stepOtp = document.getElementById('step-otp');

function showAlert(msg, type) {
    alertBox.classList.remove('hidden', 'bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700');
    if (type === 'error') {
        alertBox.classList.add('bg-red-100', 'border-red-400', 'text-red-700');
    } else {
        alertBox.classList.add('bg-green-100', 'border-green-400', 'text-green-700');
    }
    alertBox.innerHTML = msg;
    alertBox.classList.remove('hidden');
}

// 1. Gửi form đăng ký -> Nhận OTP
document.getElementById('registerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('btn-register');
    const spinner = document.getElementById('loading-spinner');
    const formData = new FormData(this);

    // Validate password match
    if (formData.get('password') !== formData.get('confirm_password')) {
        showAlert('Mật khẩu xác nhận không khớp.', 'error');
        return;
    }

    // UI Loading
    btn.disabled = true;
    spinner.classList.remove('hidden');
    alertBox.classList.add('hidden');

    fetch('send_otp.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text()) // Đổi sang text() trước để debug nếu JSON lỗi
    .then(text => {
        // --- FIX: Lọc bỏ ký tự HTML thừa (nếu có) trước khi parse JSON ---
        const jsonStart = text.indexOf('{');
        const jsonEnd = text.lastIndexOf('}');
        let cleanText = text;
        
        if (jsonStart !== -1 && jsonEnd !== -1) {
            cleanText = text.substring(jsonStart, jsonEnd + 1);
        }
        // ----------------------------------------------------------------

        try {
            const data = JSON.parse(cleanText); // Thử parse JSON đã làm sạch
            
            if (data.status === 'success') {
                // Ẩn form đăng ký
                stepRegister.style.display = 'none'; 
                stepRegister.classList.add('hidden'); // Thêm class hidden cho chắc chắn
                
                // Hiện form OTP
                stepOtp.style.display = 'block';
                stepOtp.classList.remove('hidden');
                
                document.getElementById('otp-email-display').innerText = formData.get('email');
                showAlert(data.message, 'success');
            } else {
                showAlert(data.message, 'error');
                btn.disabled = false;
                spinner.classList.add('hidden');
            }
        } catch (e) {
            console.error('JSON Parse Error:', e);
            console.log('Raw Response:', text);
            // Hiển thị lỗi thô từ server để dễ debug (loại bỏ thẻ HTML để alert đẹp hơn)
            const errorMsg = text.replace(/<[^>]*>?/gm, '').substring(0, 150); 
            showAlert('Lỗi server: ' + errorMsg, 'error');
            btn.disabled = false;
            spinner.classList.add('hidden');
        }
    })
    .catch(error => {
        console.error('Fetch Error:', error);
        showAlert('Có lỗi kết nối, vui lòng thử lại.', 'error');
        btn.disabled = false;
        spinner.classList.add('hidden');
    });
});

// 2. Xác thực OTP -> Hoàn tất đăng ký
document.getElementById('otpForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const otp = document.getElementById('otp_code').value;
    
    fetch('verify_otp.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'otp_code=' + encodeURIComponent(otp)
    })
    .then(response => response.text()) // Đổi sang text() để xử lý an toàn
    .then(text => {
        // --- FIX: Lọc bỏ ký tự HTML thừa ---
        const jsonStart = text.indexOf('{');
        const jsonEnd = text.lastIndexOf('}');
        let cleanText = text;
        
        if (jsonStart !== -1 && jsonEnd !== -1) {
            cleanText = text.substring(jsonStart, jsonEnd + 1);
        }
        
        try {
            const data = JSON.parse(cleanText);
            if (data.status === 'success') {
                showAlert('Đăng ký thành công! Đang chuyển hướng...', 'success');
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 2000);
            } else {
                showAlert(data.message, 'error');
            }
        } catch (e) {
            console.error('Verify JSON Error:', e);
            showAlert('Lỗi xác thực: ' + text.replace(/<[^>]*>?/gm, ''), 'error');
        }
    })
    .catch(error => {
        showAlert('Lỗi kết nối.', 'error');
    });
});
</script>

<?php
include 'includes/footer.php';
?>