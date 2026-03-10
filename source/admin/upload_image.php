<?php
// Tắt hiển thị lỗi ra màn hình để tránh làm hỏng JSON trả về
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Hàm ghi log lỗi để debug (xem file debug_log.txt trong thư mục admin)
function logError($message) {
    file_put_contents('debug_log.txt', date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

// 1. Cấu hình đường dẫn
// Lưu vào: htdocs/images/blog/
$target_dir = "../images/blog/"; 

// 2. Tạo thư mục nếu chưa có
if (!file_exists($target_dir)) {
    if (!mkdir($target_dir, 0777, true)) {
        logError("Không thể tạo thư mục: " . $target_dir);
        echo json_encode(['error' => ['message' => 'Lỗi Server: Không thể tạo thư mục lưu ảnh.']]);
        exit;
    }
}

// 3. Xử lý Upload
if (isset($_FILES['upload']['name'])) {
    $file = $_FILES['upload'];
    
    // Kiểm tra lỗi upload của PHP
    if ($file['error'] !== UPLOAD_ERR_OK) {
        logError("Lỗi PHP Upload Code: " . $file['error']);
        echo json_encode(['error' => ['message' => 'Lỗi tải file lên server (Code: ' . $file['error'] . ')']]);
        exit;
    }

    $filename = time() . '_' . basename($file['name']);
    $target_file = $target_dir . $filename;
    
    // Kiểm tra đuôi file
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $valid_extensions = array("jpg", "jpeg", "png", "gif", "webp");

    if (in_array($imageFileType, $valid_extensions)) {
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            // Thành công! Trả về đường dẫn tuyệt đối
            // Lưu ý: Nếu web bạn nằm trong thư mục con (vd: localhost/myweb), hãy thêm /myweb vào trước
            echo json_encode([
                'url' => '/images/blog/' . $filename 
            ]);
        } else {
            logError("Không thể di chuyển file tới: " . $target_file);
            echo json_encode(['error' => ['message' => 'Không thể lưu file. Kiểm tra quyền ghi thư mục.']]);
        }
    } else {
        echo json_encode(['error' => ['message' => 'Chỉ chấp nhận file ảnh (jpg, png, gif, webp).']]);
    }
} else {
    echo json_encode(['error' => ['message' => 'Không tìm thấy file upload.']]);
}
?>