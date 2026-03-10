<?php
session_start();
require_once 'includes/db_connect.php';

// Helper function to sanitize input
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Helper function to redirect with message
function redirect_with_message($url, $type, $message) {
    $_SESSION[$type] = $message;
    header("Location: $url");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action == 'register') {
        $username = sanitize_input($_POST['username']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validation
        if ($password !== $confirm_password) {
            redirect_with_message('register.php', 'error', 'Mật khẩu xác nhận không khớp.');
        }

        if (strlen($password) < 6) {
            redirect_with_message('register.php', 'error', 'Mật khẩu phải có ít nhất 6 ký tự.');
        }

        // Check if email or username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        
        if ($stmt->rowCount() > 0) {
            redirect_with_message('register.php', 'error', 'Email hoặc Tên tài khoản đã tồn tại.');
        }

        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = $pdo->prepare("INSERT INTO users (username, email, phone, password_hash, role, ranking) VALUES (?, ?, ?, ?, 'customer', 'Silver')");
        
        if ($stmt->execute([$username, $email, $phone, $password_hash])) {
            $user_id = $pdo->lastInsertId();
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'customer';
            $_SESSION['ranking'] = 'Standard';
            
            redirect_with_message('portal/index.php', 'success', 'Đăng ký thành công! Chào mừng bạn đến với Vinpearl Cần Giờ.');
        } else {
            redirect_with_message('register.php', 'error', 'Có lỗi xảy ra. Vui lòng thử lại.');
        }

    } elseif ($action == 'login') {
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];

        // Allow login with email or username
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role, full_name FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch();

        if ($user) {
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role']; // Đảm bảo role được set
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['last_activity'] = time();

                // Check for redirect intention
                if (isset($_SESSION['redirect_after_login'])) {
                    $redirect_url = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                    header("Location: $redirect_url");
                } else {
                    // Redirect dựa trên role
                    if ($user['role'] === 'staff') {
                        header("Location: /staff/index.php");
                    } elseif ($user['role'] === 'admin') {
                        header("Location: /admin/index.php");
                    } 
                    else {
                        header("Location: /portal/index.php");
                    }
                }
                exit();
            } else {
                redirect_with_message('login.php', 'error', 'Mật khẩu không đúng.');
            }
        } else {
            redirect_with_message('login.php', 'error', 'Tài khoản không tồn tại.');
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
} else {
    header("Location: index.php");
    exit();
}
?>
