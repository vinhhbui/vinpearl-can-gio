<?php

// Khởi tạo session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require '../includes/db_connect.php';
require '../includes/email_config.php';
require '../includes/RoomAvailability.php';

require '../vendor/autoload.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Kiểm tra xem có dữ liệu POST không
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../booking_step1.php');
    exit;
}

// Lấy thông tin khách hàng
$full_name = isset($_POST['full_name']) ? htmlspecialchars(trim($_POST['full_name'])) : '';
$email = isset($_POST['email']) ? htmlspecialchars(trim($_POST['email'])) : '';
$phone = isset($_POST['phone']) ? htmlspecialchars(trim($_POST['phone'])) : '';
$country = isset($_POST['country']) ? htmlspecialchars(trim($_POST['country'])) : 'Việt Nam';

// Validate thông tin khách hàng
if (empty($full_name) || empty($email) || empty($phone)) {
    $_SESSION['error'] = 'Vui lòng điền đầy đủ thông tin khách hàng';
    header('Location: booking_step3.php');
    exit;
}

// Lấy thông tin đặt phòng từ session hoặc POST
$booking_data = $_SESSION['booking_data'] ?? [];

if (empty($booking_data)) {
    // Nếu không có trong session, lấy từ POST
    $room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
    $room_name = isset($_POST['room_name']) ? htmlspecialchars($_POST['room_name']) : '';
    $checkin = isset($_POST['checkin']) ? htmlspecialchars($_POST['checkin']) : '';
    $checkout = isset($_POST['checkout']) ? htmlspecialchars($_POST['checkout']) : '';
    $adults = isset($_POST['adults']) ? intval($_POST['adults']) : 0;
    $children = isset($_POST['children']) ? intval($_POST['children']) : 0;
    $num_nights = isset($_POST['num_nights']) ? intval($_POST['num_nights']) : 0;
    $base_room_cost = isset($_POST['base_room_cost']) ? floatval($_POST['base_room_cost']) : 0;
    $total_price = isset($_POST['total_price']) ? floatval($_POST['total_price']) : 0;
    $tax_amount = isset($_POST['tax_amount']) ? floatval($_POST['tax_amount']) : 0;
    $service_fee_amount = isset($_POST['service_fee_amount']) ? floatval($_POST['service_fee_amount']) : 0;
    $total_price_final = isset($_POST['total_price_final']) ? floatval($_POST['total_price_final']) : 0;
    $pref_theme = isset($_POST['pref_theme']) ? htmlspecialchars($_POST['pref_theme']) : null;
    $pref_temp = isset($_POST['pref_temp']) ? htmlspecialchars($_POST['pref_temp']) : null;
    $pref_notes = isset($_POST['pref_notes']) ? htmlspecialchars($_POST['pref_notes']) : null;
    // $early_checkin = isset($_POST['early_checkin']) ? intval($_POST['early_checkin']) : 0;
    $late_checkout = isset($_POST['late_checkout']) ? intval($_POST['late_checkout']) : 0;
    
    // Parse addons
    $addons_summary = [];
    if (isset($_POST['addons_summary_json'])) {
        $addons_summary = json_decode($_POST['addons_summary_json'], true) ?? [];
    }
} else {
    // Lấy từ session
    extract($booking_data);
}

// Validate dữ liệu đặt phòng
if (empty($room_id) || empty($checkin) || empty($checkout) || $num_nights <= 0) {
    $_SESSION['error'] = 'Thông tin đặt phòng không hợp lệ';
    header('Location: ../booking_step1.php');
    exit;
}

// Lấy payment method
$payment_method = isset($_POST['payment_method']) ? htmlspecialchars($_POST['payment_method']) : 'qr_code';

// Lấy thông tin mã khuyến mãi
$promo_code = isset($_POST['promo_code']) ? trim($_POST['promo_code']) : '';
$promo_discount = isset($_POST['promo_discount']) ? floatval($_POST['promo_discount']) : 0;

try {
    // Bắt đầu transaction
    $pdo->beginTransaction();
    
    // === ASSIGN SPECIFIC ROOM NUMBER ===
    // Create RoomAvailability instance to assign a specific room
    $roomAvailability = new RoomAvailability($pdo);
    
    // Retrieve a specific room number ID that is available
    $room_number_id = $roomAvailability->assignRoomNumber($room_id, $checkin, $checkout, $late_checkout);
    
    // Verify that we got a room assignment
    if ($room_number_id === null) {
        throw new Exception('Rất tiếc, loại phòng này vừa hết chỗ hoặc không tìm thấy phòng trống phù hợp.');
    }
    
    // Tạo mã booking reference (VD: VPC20250110001)
    $booking_reference = 'VPC' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Kiểm tra xem mã đã tồn tại chưa (tránh trùng lặp)
    $stmt = $pdo->prepare("SELECT id FROM bookings WHERE booking_reference = :ref");
    $stmt->execute([':ref' => $booking_reference]);
    if ($stmt->fetch()) {
        $booking_reference = 'VPC' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    // Lấy user_id nếu đã đăng nhập
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // Tính toán addons_total
    $addons_total = 0;
    if (!empty($addons_summary)) {
        foreach ($addons_summary as $addon) {
            $addons_total += floatval($addon['price'] ?? 0);
        }
    }
    
    // Insert vào bảng bookings with room_number_id
    $sql = "INSERT INTO bookings (
        booking_reference, user_id, room_id, room_number_id,
        guest_name, guest_email, guest_phone, guest_country,
        checkin_date, checkout_date, num_nights, num_adults, num_children,
        base_price, addons_total, tax_amount, service_fee, total_price,
        payment_status, payment_method,
        pref_theme, pref_temperature, special_requests,
        booking_status, booking_date, confirmed_at,
        late_checkout
    ) VALUES (
        :booking_ref, :user_id, :room_id, :room_number_id,
        :guest_name, :guest_email, :guest_phone, :guest_country,
        :checkin, :checkout, :num_nights, :adults, :children,
        :base_price, :addons_total, :tax_amount, :service_fee, :total_price,
        :payment_status, :payment_method,
        :pref_theme, :pref_temp, :special_requests,
        'confirmed', NOW(), NOW(),
        :late_checkout
    )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':booking_ref' => $booking_reference,
        ':user_id' => $user_id,
        ':room_id' => $room_id,
        ':room_number_id' => $room_number_id,  // NEW: Assign specific room
        ':guest_name' => $full_name,
        ':guest_email' => $email,
        ':guest_phone' => $phone,
        ':guest_country' => $country,
        ':checkin' => $checkin,
        ':checkout' => $checkout,
        ':num_nights' => $num_nights,
        ':adults' => $adults,
        ':children' => $children,
        ':base_price' => $base_room_cost,
        ':addons_total' => $addons_total,
        ':tax_amount' => $tax_amount,
        ':service_fee' => $service_fee_amount,
        ':total_price' => $total_price_final,
        ':payment_status' => 'paid',
        ':payment_method' => $payment_method,
        ':pref_theme' => $pref_theme,
        ':pref_temp' => $pref_temp,
        ':special_requests' => $pref_notes,
        ':late_checkout' => $late_checkout
    ]);
    
    $booking_id = $pdo->lastInsertId();
    
    // Insert add-ons nếu có
    if (!empty($addons_summary) && is_array($addons_summary)) {
        $sql_addon = "INSERT INTO booking_addons (
            booking_id, addon_type, addon_id, addon_name, addon_price, quantity
        ) VALUES (
            :booking_id, :addon_type, :addon_id, :addon_name, :addon_price, :quantity
        )";
        $stmt_addon = $pdo->prepare($sql_addon);
        
        foreach ($addons_summary as $addon) {
            $addon_type = $addon['type'] ?? 'standard';
            $addon_id = 0; // Sẽ lấy từ POST nếu cần
            
            $stmt_addon->execute([
                ':booking_id' => $booking_id,
                ':addon_type' =>  $addon_type,
                ':addon_id' => $addon_id,
                ':addon_name' => $addon['name'] ?? '',
                ':addon_price' => floatval($addon['price'] ?? 0),
                ':quantity' => intval($addon['people'] ?? 1)
            ]);
        }
    }
    
    // Cập nhật usage_count cho promotion nếu có
    if (!empty($promo_code) && $promo_discount > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE promotions SET used_count = used_count + 1 WHERE code = ?");
            $stmt->execute([$promo_code]);
        } catch (PDOException $e) {
            // Log error nhưng không làm gián đoạn quá trình booking
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Lưu thông tin vào session để hiển thị trang confirm
    $_SESSION['booking_success'] = [
        'booking_reference' => $booking_reference,
        'booking_id' => $booking_id,
        'full_name' => $full_name,
        'email' => $email,
        'phone' => $phone,
        'room_name' => $room_name,
        'checkin' => $checkin,
        'checkout' => $checkout,
        'num_nights' => $num_nights,
        'adults' => $adults,
        'children' => $children,
        'total_price_final' => $total_price_final,
        'addons_summary' => $addons_summary // Thêm thông tin add-ons
    ];
    
    // Gửi email xác nhận
    $emailSent = sendBookingConfirmationEmail($_SESSION['booking_success']);
    
    if (!$emailSent) {
        error_log("Không thể gửi email xác nhận cho booking #$booking_reference");
    }
    
    // Xóa dữ liệu booking tạm
    unset($_SESSION['booking_data']);
    
    // Redirect đến trang xác nhận thành công
    header('Location: booking_confirm.php?status=success&ref=' . $booking_reference);
    exit;
    
} catch (PDOException $e) {
    // Rollback nếu có lỗi
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Lỗi lưu booking: " . $e->getMessage());
    
    $_SESSION['error'] = 'Đã có lỗi xảy ra khi xử lý đặt phòng. Vui lòng thử lại.';
    header('Location: booking_step3.php');
    exit;
} catch (Exception $e) {
    // Rollback for other exceptions (e.g., room assignment failed)
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Lỗi đặt phòng: " . $e->getMessage());
    
    $_SESSION['error'] = $e->getMessage();
    header('Location: booking_step3.php');
    exit;
}

/**
 * Gửi email xác nhận đặt phòng sử dụng PHPMailer
 */
function sendBookingConfirmationEmail($booking_info) {
    $mail = new PHPMailer(true);
    
    try {
        // Cấu hình SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Thông tin người gửi và người nhận
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($booking_info['email'], $booking_info['full_name']);
        
        // Thêm CC cho admin (tùy chọn)
        // $mail->addCC(CONTACT_EMAIL);
        
        // Nội dung email
        $mail->isHTML(true);
        $mail->Subject = "Xác nhận đặt phòng #{$booking_info['booking_reference']} - Vinpearl Cần Giờ";
        
        // Tạo nội dung email HTML
        $emailBody = getEmailTemplate($booking_info);
        $mail->Body = $emailBody;
        
        // Nội dung plain text (fallback)
        $mail->AltBody = "
Kính gửi {$booking_info['full_name']},

Cảm ơn bạn đã chọn Vinpearl Cần Giờ. Đơn đặt phòng của bạn đã được xác nhận.

Mã đặt phòng: {$booking_info['booking_reference']}
Phòng: {$booking_info['room_name']}
Check-in: {$booking_info['checkin']}
Check-out: {$booking_info['checkout']}
Số đêm: {$booking_info['num_nights']}
Số khách: {$booking_info['adults']} người lớn, {$booking_info['children']} trẻ em
Tổng thanh toán: " . number_format($booking_info['total_price_final'], 0, ',', '.') . " VNĐ

Trạng thái: Đã xác nhận

Liên hệ:
Email: " . CONTACT_EMAIL . "
Hotline: " . CONTACT_PHONE . "
Địa chỉ: " . RESORT_ADDRESS . "

Trân trọng,
Vinpearl Cần Giờ
        ";
        
        // Gửi email
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Lỗi gửi email: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Template email HTML
 */
function getEmailTemplate($booking_info) {
    // Tính toán các giá trị
    $addons_html = '';
    if (!empty($booking_info['addons_summary'])) {
        foreach ($booking_info['addons_summary'] as $addon) {
            $addons_html .= "
            <tr>
                <td style='padding: 8px; font-size: 14px; color: #666;'>{$addon['name']}</td>
                <td style='padding: 8px; font-size: 14px; text-align: right; font-weight: bold;'>" . number_format($addon['price'], 0, ',', '.') . " VNĐ</td>
            </tr>";
        }
    }
    
    $html = "
    <!DOCTYPE html>
    <html lang='vi'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Xác nhận đặt phòng</title>
        <style>
            body { margin: 0; padding: 0; font-family: 'Arial', sans-serif; background-color: #f4f4f4; }
            .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #022366 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
            .header p { margin: 8px 0 0; font-size: 16px; opacity: 0.9; }
            .content { padding: 30px 20px; background: #f9f9f9; }
            .content p { color: #333; line-height: 1.8; margin: 0 0 15px; }
            .booking-card { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #022366; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
            .booking-card h3 { margin: 0 0 15px; color: #022366; font-size: 18px; }
            .booking-card table { width: 100%; border-collapse: collapse; }
            .booking-card td { padding: 10px 0; border-bottom: 1px solid #eee; font-size: 14px; }
            .booking-card td:first-child { color: #666; font-weight: 600; }
            .booking-card td:last-child { text-align: right; }
            .total-row { background: #f0f5ff; font-size: 18px !important; font-weight: bold !important; }
            .total-row td { padding: 15px 10px !important; color: #022366 !important; border: none !important; }
            .status-badge { display: inline-block; padding: 6px 16px; background: #d4edda; color: #155724; border-radius: 20px; font-size: 13px; font-weight: bold; margin: 10px 0; }
            .contact-info { background: #fff; padding: 20px; margin: 20px 0; border-radius: 4px; }
            .contact-info p { margin: 8px 0; font-size: 14px; color: #555; }
            .footer { background: #333; color: #999; text-align: center; padding: 20px; font-size: 12px; }
            .footer p { margin: 5px 0; }
            .btn { display: inline-block; padding: 14px 30px; background: #022366; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 20px 0; }
            .btn:hover { background: #5568d3; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Xác nhận Đặt phòng</h1>
                <p>Vinpearl Cần Giờ</p>
            </div>
            
            <div class='content'>
                <p>Kính gửi <strong>{$booking_info['full_name']}</strong>,</p>
                <p>Cảm ơn bạn đã chọn Vinpearl Cần Giờ! Chúng tôi vui mừng thông báo rằng đơn đặt phòng của bạn đã được xác nhận thành công.</p>
                
                <div class='booking-card'>
                    <h3>Thông tin đặt phòng</h3>
                    <table>
                        <tr>
                            <td>Mã đặt phòng:</td>
                            <td><strong style='color: #022366; font-size: 16px;'>{$booking_info['booking_reference']}</strong></td>
                        </tr>
                        <tr>
                            <td>Loại phòng:</td>
                            <td>{$booking_info['room_name']}</td>
                        </tr>
                        <tr>
                            <td>Check-in:</td>
                            <td>" . date('d/m/Y (l)', strtotime($booking_info['checkin'])) . "</td>
                        </tr>
                        <tr>
                            <td>Check-out:</td>
                            <td>" . date('d/m/Y (l)', strtotime($booking_info['checkout'])) . "</td>
                        </tr>
                        <tr>
                            <td>Số đêm:</td>
                            <td>{$booking_info['num_nights']} đêm</td>
                        </tr>
                        <tr>
                            <td>Số khách:</td>
                            <td>{$booking_info['adults']} người lớn, {$booking_info['children']} trẻ em</td>
                        </tr>
                    </table>
                </div>
                
                <div class='booking-card'>
                    <h3>Chi tiết thanh toán</h3>
                    <table>
                        {$addons_html}
                        <td style='padding: 8px; font-size: 14px; color: #666;'>Tổng giá phòng</td>
                        <td style='padding: 8px; font-size: 14px; text-align: right; font-weight: bold;'>" . number_format($booking_info['total_price_final'] - array_sum(array_column($booking_info['addons_summary'] ?? [], 'price')), 0, ',', '.') . " VNĐ</td>
                        <tr class='total-row'>
                            <td>TỔNG THANH TOÁN</td>
                            <td style='text-align: right; font-weight: bold;'>" . number_format($booking_info['total_price_final'], 0, ',', '.') . " VNĐ</td>
                        </tr>
                    </table>
                </div>
                
                <p style='text-align: center;'>
                    <span class='status-badge'>Đã xác nhận & Thanh toán</span>
                </p>
                
                <p>Chúng tôi rất mong được phục vụ bạn và gia đình trong chuyến nghỉ dưỡng sắp tới. Nếu bạn có bất kỳ yêu cầu đặc biệt nào, vui lòng liên hệ với chúng tôi.</p>
                
                <div class='contact-info'>
                    <p><strong>Thông tin liên hệ:</strong></p>
                    <p>Email: " . CONTACT_EMAIL . "</p>
                    <p>Hotline: " . CONTACT_PHONE . "</p>
                    <p>Địa chỉ: " . RESORT_ADDRESS . "</p>
                </div>
                
                <p style='text-align: center;'>
                    <a href='https://vinpearl-cangio.com/booking_confirm.php?ref={$booking_info['booking_reference']}' class='btn'>Xem chi tiết đặt phòng</a>
                </p>
            </div>
            
            <div class='footer'>
                <p>Email này được gửi tự động, vui lòng không trả lời trực tiếp.</p>
                <p>&copy; 2025 Vinpearl Cần Giờ Resort & Spa. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}