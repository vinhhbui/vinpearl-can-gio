<?php
session_start();
require_once '../includes/db_connect.php';

$error_msg = '';
$success_msg = '';
$reopen_modal_for_type = null; // Để mở lại modal sau khi thao tác

// --- HÀM HỖ TRỢ UPLOAD ẢNH ---
function uploadImage($file, $target_dir = "../uploads/rooms/") {
    if (!isset($file['name']) || empty($file['name'])) return null;
    
    // Tạo thư mục nếu chưa tồn tại
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $filename = time() . '_' . basename($file["name"]);
    $target_file = $target_dir . $filename;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Kiểm tra định dạng ảnh
    $valid_extensions = array("jpg", "jpeg", "png", "gif", "webp");
    if (!in_array($imageFileType, $valid_extensions)) {
        throw new Exception("Chỉ chấp nhận file ảnh (JPG, JPEG, PNG, GIF, WEBP).");
    }

    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        // Trả về đường dẫn để lưu vào DB (bỏ ../ ở đầu để dùng cho thẻ img)
        return 'uploads/rooms/' . $filename;
    }
    return null;
}

// --- XỬ LÝ FORM SUBMIT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            // 1. XỬ LÝ LOẠI PHÒNG (ROOM TYPES)
            if ($_POST['action'] == 'add_type') {
                $pdo->beginTransaction();
                try {
                    // Xử lý ảnh đại diện
                    $mainImageUrl = '';
                    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
                        $mainImageUrl = uploadImage($_FILES['image_file']);
                    }

                    // THÊM display_order VÀO CÂU INSERT
                    $stmt = $pdo->prepare("INSERT INTO rooms (room_type_name, price_per_night, description, max_occupancy, room_size_sqm, view_type, image_url, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([
                        $_POST['name'], 
                        $_POST['price'], 
                        $_POST['desc'], 
                        $_POST['capacity'], 
                        $_POST['size'], 
                        $_POST['view'], 
                        $mainImageUrl, // Sử dụng đường dẫn file upload
                        $_POST['display_order'] ?? 0 
                    ]);
                    $roomId = $pdo->lastInsertId();

                    // Thêm Features
                    if (!empty($_POST['features']['name'])) {
                        $fStmt = $pdo->prepare("INSERT INTO room_features (room_id, feature_name, icon) VALUES (?, ?, ?)");
                        foreach ($_POST['features']['name'] as $index => $fName) {
                            if (!empty($fName)) {
                                $fIcon = $_POST['features']['icon'][$index] ?? 'fa-check';
                                $fStmt->execute([$roomId, $fName, $fIcon]);
                            }
                        }
                    }

                    // Thêm Images (Gallery) - Xử lý Upload nhiều file
                    if (!empty($_FILES['gallery_images']['name'][0])) {
                        $iStmt = $pdo->prepare("INSERT INTO room_images (room_id, image_url, image_title, display_order) VALUES (?, ?, ?, ?)");
                        
                        foreach ($_FILES['gallery_images']['name'] as $key => $val) {
                            if ($_FILES['gallery_images']['error'][$key] == 0) {
                                $file = [
                                    'name' => $_FILES['gallery_images']['name'][$key],
                                    'type' => $_FILES['gallery_images']['type'][$key],
                                    'tmp_name' => $_FILES['gallery_images']['tmp_name'][$key],
                                    'error' => $_FILES['gallery_images']['error'][$key],
                                    'size' => $_FILES['gallery_images']['size'][$key]
                                ];
                                
                                $galleryUrl = uploadImage($file);
                                if ($galleryUrl) {
                                    $iTitle = $_POST['gallery_titles'][$key] ?? '';
                                    $iStmt->execute([$roomId, $galleryUrl, $iTitle, $key]);
                                }
                            }
                        }
                    }

                    $pdo->commit();
                    $success_msg = "Thêm loại phòng thành công!";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            } 
            elseif ($_POST['action'] == 'edit_type') {
                $pdo->beginTransaction();
                try {
                    // Xử lý ảnh đại diện: Nếu có upload mới thì dùng mới, không thì dùng cũ
                    $mainImageUrl = $_POST['current_image']; // Lấy ảnh cũ từ hidden input
                    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
                        $mainImageUrl = uploadImage($_FILES['image_file']);
                    }

                    // THÊM display_order VÀO CÂU UPDATE
                    $stmt = $pdo->prepare("UPDATE rooms SET room_type_name=?, price_per_night=?, description=?, max_occupancy=?, room_size_sqm=?, view_type=?, image_url=?, display_order=? WHERE id=?");
                    $stmt->execute([
                        $_POST['name'], 
                        $_POST['price'], 
                        $_POST['desc'], 
                        $_POST['capacity'], 
                        $_POST['size'], 
                        $_POST['view'], 
                        $mainImageUrl, 
                        $_POST['display_order'] ?? 0, 
                        $_POST['id']
                    ]);
                    $roomId = $_POST['id'];

                    // Cập nhật Features (Xóa cũ thêm mới)
                    $pdo->prepare("DELETE FROM room_features WHERE room_id = ?")->execute([$roomId]);
                    if (!empty($_POST['features']['name'])) {
                        $fStmt = $pdo->prepare("INSERT INTO room_features (room_id, feature_name, icon) VALUES (?, ?, ?)");
                        foreach ($_POST['features']['name'] as $index => $fName) {
                            if (!empty($fName)) {
                                $fIcon = $_POST['features']['icon'][$index] ?? 'fa-check';
                                $fStmt->execute([$roomId, $fName, $fIcon]);
                            }
                        }
                    }

                    // Cập nhật Images (Xóa tất cả cũ, thêm lại danh sách giữ lại + danh sách mới)
                    $pdo->prepare("DELETE FROM room_images WHERE room_id = ?")->execute([$roomId]);
                    $iStmt = $pdo->prepare("INSERT INTO room_images (room_id, image_url, image_title, display_order) VALUES (?, ?, ?, ?)");
                    $displayOrder = 0;

                    // 1. Thêm lại các ảnh cũ (được giữ lại)
                    if (!empty($_POST['existing_images']['url'])) {
                        foreach ($_POST['existing_images']['url'] as $index => $iUrl) {
                            if (!empty($iUrl)) {
                                $iTitle = $_POST['existing_images']['title'][$index] ?? '';
                                $iStmt->execute([$roomId, $iUrl, $iTitle, $displayOrder++]);
                            }
                        }
                    }

                    // 2. Upload và thêm các ảnh mới
                    if (!empty($_FILES['gallery_images']['name'][0])) {
                        foreach ($_FILES['gallery_images']['name'] as $key => $val) {
                            if ($_FILES['gallery_images']['error'][$key] == 0) {
                                $file = [
                                    'name' => $_FILES['gallery_images']['name'][$key],
                                    'type' => $_FILES['gallery_images']['type'][$key],
                                    'tmp_name' => $_FILES['gallery_images']['tmp_name'][$key],
                                    'error' => $_FILES['gallery_images']['error'][$key],
                                    'size' => $_FILES['gallery_images']['size'][$key]
                                ];
                                
                                $galleryUrl = uploadImage($file);
                                if ($galleryUrl) {
                                    $iTitle = $_POST['gallery_titles'][$key] ?? '';
                                    $iStmt->execute([$roomId, $galleryUrl, $iTitle, $displayOrder++]);
                                }
                            }
                        }
                    }

                    $pdo->commit();
                    $success_msg = "Cập nhật loại phòng thành công!";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            } 
            elseif ($_POST['action'] == 'delete_type') {
                $stmt = $pdo->prepare("DELETE FROM rooms WHERE id=?");
                $stmt->execute([$_POST['id']]);
                $success_msg = "Đã xóa loại phòng!";
            }

            // 2. XỬ LÝ PHÒNG VẬT LÝ (PHYSICAL ROOMS)
            elseif ($_POST['action'] == 'add_room') {
                // Kiểm tra trùng số phòng
                $check = $pdo->prepare("SELECT COUNT(*) FROM room_numbers WHERE room_number = ?");
                $check->execute([$_POST['room_number']]);
                if ($check->fetchColumn() > 0) {
                    throw new Exception("Số phòng " . $_POST['room_number'] . " đã tồn tại!");
                }

                $stmt = $pdo->prepare("INSERT INTO room_numbers (room_id, room_number, floor, status, notes) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['room_type_id'], $_POST['room_number'], $_POST['floor'], $_POST['status'], $_POST['notes'] ?? '']);
                $success_msg = "Thêm phòng số " . $_POST['room_number'] . " thành công!";
                $reopen_modal_for_type = $_POST['room_type_id']; // Mở lại modal
            }
            elseif ($_POST['action'] == 'edit_room') {
                $stmt = $pdo->prepare("UPDATE room_numbers SET room_number=?, floor=?, status=?, notes=? WHERE id=?");
                $stmt->execute([$_POST['room_number'], $_POST['floor'], $_POST['status'], $_POST['notes'] ?? '', $_POST['room_id']]);
                $success_msg = "Cập nhật phòng thành công!";
                
                // Lấy room_type_id để mở lại modal
                $getRoomType = $pdo->prepare("SELECT room_id FROM room_numbers WHERE id = ?");
                $getRoomType->execute([$_POST['room_id']]);
                $reopen_modal_for_type = $getRoomType->fetchColumn();
            }
            elseif ($_POST['action'] == 'delete_room') {
                // Lấy room_type_id trước khi xóa
                $getRoomType = $pdo->prepare("SELECT room_id FROM room_numbers WHERE id = ?");
                $getRoomType->execute([$_POST['room_id']]);
                $reopen_modal_for_type = $getRoomType->fetchColumn();
                
                $stmt = $pdo->prepare("DELETE FROM room_numbers WHERE id=?");
                $stmt->execute([$_POST['room_id']]);
                $success_msg = "Đã xóa phòng vật lý!";
            }
        }
    } catch (Exception $e) {
        if ($e->getCode() == '23000') {
            $error_msg = "Lỗi dữ liệu: Không thể xóa vì dữ liệu đang được sử dụng (có booking hoặc ràng buộc khác).";
        } else {
            $error_msg = "Lỗi: " . $e->getMessage();
        }
        // Giữ lại modal nếu có lỗi
        if (isset($_POST['room_type_id'])) {
            $reopen_modal_for_type = $_POST['room_type_id'];
        }
    }
}

// --- LẤY DỮ LIỆU ---
// 1. Lấy danh sách loại phòng - SẮP XẾP THEO display_order TĂNG DẦN
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY display_order ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);

// 2. Lấy danh sách phòng vật lý
$physical_rooms = $pdo->query("
    SELECT rn.*, r.room_type_name 
    FROM room_numbers rn 
    JOIN rooms r ON rn.room_id = r.id 
    ORDER BY rn.room_number ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 3. Lấy danh sách tiện nghi (Features)
$features = $pdo->query("SELECT * FROM room_features ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// 4. Lấy danh sách hình ảnh (Images)
$images = $pdo->query("SELECT * FROM room_images ORDER BY is_primary DESC, display_order ASC")->fetchAll(PDO::FETCH_ASSOC);

// Gom nhóm dữ liệu theo room_id
$rooms_by_type = [];
foreach ($physical_rooms as $pr) {
    $rooms_by_type[$pr['room_id']][] = $pr;
}

$features_by_type = [];
foreach ($features as $f) {
    $features_by_type[$f['room_id']][] = $f;
}

$images_by_type = [];
foreach ($images as $img) {
    $images_by_type[$img['room_id']][] = $img;
}

// Tạo map room types để JS dùng
$room_types_map = [];
foreach ($rooms as $r) {
    $r['features'] = $features_by_type[$r['id']] ?? [];
    $r['images'] = $images_by_type[$r['id']] ?? [];
    $room_types_map[$r['id']] = $r;
}

include 'components/header.php';
?>

<div class="d-flex">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content flex-grow-1 p-4">
        <div class="d-flex justify-content-between mb-4">
            <h2>Quản lý Loại Phòng & Phòng</h2>
            <button class="btn btn-primary" onclick="openAddTypeModal()">
                <i class="fas fa-plus"></i> Thêm Loại Phòng Mới
            </button>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error_msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($success_msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Bảng Danh sách Loại Phòng -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-primary"><i class="fas fa-layer-group me-2"></i>Danh sách Loại Phòng</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">TT</th> <!-- Thêm cột TT -->
                            <th>ID</th>
                            <th>Ảnh</th>
                            <th>Tên loại phòng</th>
                            <th>Giá/Đêm</th>
                            <th>Thông số</th>
                            <th>Số lượng phòng</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room): 
                            $count = isset($rooms_by_type[$room['id']]) ? count($rooms_by_type[$room['id']]) : 0;
                            // Xử lý đường dẫn ảnh hiển thị
                            $displayImg = $room['image_url'] ? '../' . $room['image_url'] : 'https://placehold.co/50';
                        ?>
                        <tr>
                            <!-- Hiển thị thứ tự -->
                            <td><span class="badge bg-secondary rounded-pill"><?php echo $room['display_order']; ?></span></td>
                            <td><?php echo $room['id']; ?></td>
                            <td>
                                <img src="<?php echo htmlspecialchars($displayImg); ?>" 
                                     width="60" height="40" class="object-fit-cover rounded border">
                            </td>
                            <td class="fw-bold"><?php echo htmlspecialchars($room['room_type_name']); ?></td>
                            <td class="text-success fw-bold"><?php echo number_format($room['price_per_night'], 0, ',', '.'); ?> ₫</td>
                            <td>
                                <small class="d-block"><i class="fas fa-user me-1"></i> <?php echo $room['max_occupancy']; ?> người</small>
                                <small class="d-block"><i class="fas fa-ruler-combined me-1"></i> <?php echo $room['room_size_sqm']; ?> m²</small>
                            </td>
                            <td>
                                <span class="badge bg-info text-dark"><?php echo $count; ?> phòng</span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary me-1" onclick="viewType(<?php echo $room['id']; ?>)">
                                    <i class="fas fa-eye"></i> Xem
                                </button>
                                <button class="btn btn-sm btn-outline-info me-1" data-room-type-id="<?php echo $room['id']; ?>" onclick="managePhysicalRooms(<?php echo $room['id']; ?>)">
                                    <i class="fas fa-list"></i> QL Phòng
                                </button>
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editType(<?php echo $room['id']; ?>)">
                                    <i class="fas fa-edit"></i> Sửa
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Xóa loại phòng này sẽ xóa tất cả các phòng con. Bạn chắc chắn chứ?');">
                                    <input type="hidden" name="action" value="delete_type">
                                    <input type="hidden" name="id" value="<?php echo $room['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL 1: Thêm/Sửa Loại Phòng (Room Type) -->
<div class="modal fade" id="typeModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <!-- THÊM enctype="multipart/form-data" -->
        <form method="POST" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title" id="typeModalTitle">Thêm Loại Phòng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="typeAction" value="add_type">
                <input type="hidden" name="id" id="typeId">
                
                <div class="row g-3">
                    <!-- Thông tin cơ bản -->
                    <div class="col-md-6">
                        <label class="form-label">Tên loại phòng</label>
                        <input type="text" name="name" id="typeName" class="form-control" required>
                    </div>
                    <div class="col-md-4"> <!-- Giảm col-md-6 xuống 4 -->
                        <label class="form-label">Giá mỗi đêm (VNĐ)</label>
                        <input type="number" name="price" id="typePrice" class="form-control" required>
                    </div>
                    <div class="col-md-2"> <!-- Thêm input Thứ tự -->
                        <label class="form-label">Thứ tự hiển thị</label>
                        <input type="number" name="display_order" id="typeOrder" class="form-control" value="0">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Sức chứa (Người)</label>
                        <input type="number" name="capacity" id="typeCapacity" class="form-control" value="2">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Diện tích (m²)</label>
                        <input type="number" name="size" id="typeSize" class="form-control" value="30">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Hướng nhìn</label>
                        <input type="text" name="view" id="typeView" class="form-control">
                    </div>
                    
                    <!-- THAY ĐỔI INPUT ẢNH ĐẠI DIỆN -->
                    <div class="col-12">
                        <label class="form-label">Ảnh đại diện</label>
                        <div class="d-flex align-items-center gap-3">
                            <div id="currentImagePreview" class="d-none">
                                <img src="" id="imgPreviewSrc" class="rounded border" style="width: 80px; height: 60px; object-fit: cover;">
                                <input type="hidden" name="current_image" id="currentImageValue">
                            </div>
                            <input type="file" name="image_file" id="typeImageFile" class="form-control" accept="image/*">
                        </div>
                        <small class="text-muted">Để trống nếu không muốn thay đổi ảnh.</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Mô tả ngắn</label>
                        <textarea name="desc" id="typeDesc" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Mô tả dài</label>
                        <textarea name="long_desc" id="typeLongDesc" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Quản lý Tiện nghi (Features) -->
                    <div class="col-md-6">
                        <h6 class="mb-3">Tiện nghi phòng <button type="button" class="btn btn-sm btn-outline-success ms-2" onclick="addFeatureInput()"><i class="fas fa-plus"></i> Thêm</button></h6>
                        <div id="featuresContainer">
                            <!-- JS will populate this -->
                        </div>
                    </div>

                    <!-- Quản lý Hình ảnh (Images) -->
                    <div class="col-md-6">
                        <h6 class="mb-3">Thư viện ảnh <button type="button" class="btn btn-sm btn-outline-success ms-2" onclick="addImageInput()"><i class="fas fa-plus"></i> Thêm</button></h6>
                        <!-- Container cho ảnh cũ (khi edit) -->
                        <div id="existingImagesContainer" class="mb-2"></div>
                        <!-- Container cho input upload mới -->
                        <div id="imagesContainer">
                            <!-- JS will populate this -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: Quản lý Phòng Vật Lý (Physical Rooms) -->
<div class="modal fade" id="physicalRoomsModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Danh sách phòng: <span id="currentRoomTypeName" class="fw-bold text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Form thêm phòng nhanh -->
                <form method="POST" class="row g-2 align-items-end mb-4 p-3 bg-light border rounded" id="addRoomForm">
                    <input type="hidden" name="action" value="add_room">
                    <input type="hidden" name="room_type_id" id="currentRoomTypeId">
                    
                    <div class="col-md-3">
                        <label class="small fw-bold">Số phòng <span class="text-danger">*</span></label>
                        <input type="text" name="room_number" id="addRoomNumber" class="form-control form-control-sm" placeholder="VD: 101" required>
                    </div>
                    <div class="col-md-2">
                        <label class="small fw-bold">Tầng</label>
                        <input type="number" name="floor" id="addRoomFloor" class="form-control form-control-sm" value="1">
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold">Trạng thái</label>
                        <select name="status" id="addRoomStatus" class="form-select form-select-sm">
                            <option value="available">Trống</option>
                            <option value="maintenance">Bảo trì</option>
                            <option value="cleaning">Đang dọn</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small fw-bold">Ghi chú</label>
                        <input type="text" name="notes" id="addRoomNotes" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-plus"></i> Thêm</button>
                    </div>
                </form>

                <!-- Danh sách phòng -->
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="table-secondary">
                            <tr>
                                <th>Số phòng</th>
                                <th>Tầng</th>
                                <th>Trạng thái</th>
                                <th>Ghi chú</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="physicalRoomsList">
                            <!-- JS sẽ render dữ liệu vào đây -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL 3: Sửa Phòng Vật Lý -->
<div class="modal fade" id="editPhysicalRoomModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sửa Phòng</h5>
                <button type="button" class="btn-close" onclick="closeEditModal()"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="edit_room">
                <input type="hidden" name="room_id" id="editPRId">
                
                <div class="mb-3">
                    <label class="form-label">Số phòng <span class="text-danger">*</span></label>
                    <input type="text" name="room_number" id="editPRNumber" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tầng</label>
                    <input type="number" name="floor" id="editPRFloor" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" id="editPRStatus" class="form-select">
                        <option value="available">Trống</option>
                        <option value="occupied">Đang ở</option>
                        <option value="maintenance">Bảo trì</option>
                        <option value="cleaning">Đang dọn</option>
                        <option value="dirty">Chưa dọn</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ghi chú</label>
                    <input type="text" name="notes" id="editPRNotes" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>


<!-- MODAL 4: Xem Chi Tiết Loại Phòng -->
<div class="modal fade" id="viewRoomModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết Loại Phòng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5">
                        <img id="viewRoomImage" src="" class="img-fluid rounded mb-3 w-100 object-fit-cover" style="height: 300px;" alt="Room Image">
                        
                        <!-- Gallery -->
                        <h6 class="fw-bold mt-3">Thư viện ảnh</h6>
                        <div class="row g-2" id="viewRoomGallery">
                            <!-- JS will populate -->
                        </div>
                    </div>
                    <div class="col-md-7">
                        <h4 id="viewRoomName" class="fw-bold text-primary"></h4>
                        <p class="text-success fw-bold fs-4" id="viewRoomPrice"></p>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-user me-2 text-muted"></i> Sức chứa: <span id="viewRoomCapacity" class="fw-bold"></span> người</li>
                                    <li><i class="fas fa-ruler-combined me-2 text-muted"></i> Diện tích: <span id="viewRoomSize" class="fw-bold"></span> m²</li>
                                </ul>
                            </div>
                            <div class="col-6">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-mountain me-2 text-muted"></i> Hướng: <span id="viewRoomView" class="fw-bold"></span></li>
                                </ul>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold border-bottom pb-2">Mô tả</h6>
                            <p class="text-muted" id="viewRoomDesc"></p>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold border-bottom pb-2">Tiện nghi</h6>
                            <div id="viewRoomFeatures" class="d-flex flex-wrap gap-2">
                                <!-- JS will populate -->
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
                <h5>Danh sách phòng vật lý</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Số phòng</th>
                                <th>Tầng</th>
                                <th>Trạng thái</th>
                                <th>Ghi chú</th>
                            </tr>
                        </thead>
                        <tbody id="viewRoomPhysicalList">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<style>
    #physicalRoomsList td {
        vertical-align: middle;
    }
    .feature-tag {
        background-color: #e9ecef;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
    }
    .feature-tag i {
        margin-right: 5px;
        color: #0d6efd;
    }
    /* CSS cho Grid chọn Icon */
    .icon-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 5px;
        max-height: 200px;
        overflow-y: auto;
        padding: 5px;
    }
    .icon-option {
        width: 100%;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #dee2e6;
        background: #fff;
        cursor: pointer;
        border-radius: 4px;
        transition: all 0.2s;
    }
    .icon-option:hover {
        background-color: #f8f9fa;
        border-color: #0d6efd;
        color: #0d6efd;
    }
    /* Style cho icon đang được chọn */
    .icon-option.active {
        background-color: #e7f1ff;
        border-color: #0d6efd;
        color: #0d6efd;
        font-weight: bold;
    }
</style>

<script>
const allRoomTypes = <?php echo json_encode($room_types_map ?: '{}'); ?>;
const allPhysicalRooms = <?php echo json_encode($rooms_by_type ?: '{}'); ?>;

// Danh sách các Icon phổ biến cho khách sạn
const commonIcons = [
    'fa-wifi', 'fa-tv', 'fa-snowflake', 'fa-wind', 'fa-parking', 
    'fa-swimming-pool', 'fa-utensils', 'fa-spa', 'fa-dumbbell', 
    'fa-cocktail', 'fa-concierge-bell', 'fa-tshirt', 'fa-bath', 
    'fa-shower', 'fa-bed', 'fa-coffee', 'fa-lock', 'fa-key',
    'fa-phone', 'fa-laptop', 'fa-glass-martini', 'fa-smoking-ban',
    'fa-wheelchair', 'fa-baby-carriage', 'fa-paw', 'fa-music',
    'fa-check', 'fa-star', 'fa-heart'
];

// Helper to safely get or create modal instance
function getModalInstance(id) {
    const el = document.getElementById(id);
    if (!el) return null;
    let instance = bootstrap.Modal.getInstance(el);
    if (!instance) {
        instance = new bootstrap.Modal(el);
    }
    return instance;
}

// CẬP NHẬT HÀM addFeatureInput
function addFeatureInput(name = '', icon = 'fa-check') {
    const container = document.getElementById('featuresContainer');
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    
    // Tạo HTML cho danh sách icon
    let iconGridHtml = '<div class="dropdown-menu p-2"><div class="icon-grid">';
    commonIcons.forEach(ic => {
        // Kiểm tra xem icon này có phải là icon hiện tại không để thêm class active
        const isActive = (ic === icon) ? 'active' : '';
        iconGridHtml += `
            <div class="icon-option ${isActive}" onclick="selectIcon(this, '${ic}')" title="${ic}">
                <i class="fas ${ic}"></i>
            </div>
        `;
    });
    iconGridHtml += '</div></div>';

    div.innerHTML = `
        <!-- Nút hiển thị Icon (Preview) -->
        <button class="btn btn-outline-secondary dropdown-toggle icon-preview-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas ${icon}"></i>
        </button>
        
        <!-- Input ẩn chứa giá trị icon để gửi về server khi bấm Lưu -->
        <input type="hidden" name="features[icon][]" class="icon-input" value="${escapeHtml(icon)}">
        
        <!-- Dropdown Menu -->
        ${iconGridHtml}

        <!-- Input tên tiện nghi -->
        <input type="text" name="features[name][]" class="form-control form-control-sm" placeholder="Tên tiện nghi (VD: Wifi miễn phí)" value="${escapeHtml(name)}">
        
        <!-- Nút xóa -->
        <button type="button" class="btn btn-outline-danger" onclick="this.closest('.input-group').remove()"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(div);
}

// CẬP NHẬT HÀM selectIcon (Xử lý hiển thị ngay lập tức)
function selectIcon(element, iconClass) {
    const inputGroup = element.closest('.input-group');
    
    // 1. Cập nhật giá trị cho input ẩn (Quan trọng để PHP lưu được)
    const hiddenInput = inputGroup.querySelector('.icon-input');
    hiddenInput.value = iconClass;
    
    // 2. Cập nhật hình ảnh trên nút NGAY LẬP TỨC (Visual Feedback)
    const btnIcon = inputGroup.querySelector('.icon-preview-btn i');
    // Reset class và gán class icon mới
    btnIcon.className = `fas ${iconClass}`;

    // 3. Highlight icon được chọn trong danh sách dropdown
    const allOptions = inputGroup.querySelectorAll('.icon-option');
    allOptions.forEach(opt => opt.classList.remove('active'));
    element.classList.add('active');

    // 4. Đóng menu dropdown sau khi chọn
    const dropdownToggle = inputGroup.querySelector('.dropdown-toggle');
    // Sử dụng getOrCreateInstance để đảm bảo lấy được instance
    const dropdownInstance = bootstrap.Dropdown.getOrCreateInstance(dropdownToggle);
    dropdownInstance.hide();
}

function addImageInput() {
    const container = document.getElementById('imagesContainer');
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.innerHTML = `
        <input type="file" name="gallery_images[]" class="form-control form-control-sm" accept="image/*" required>
        <input type="text" name="gallery_titles[]" class="form-control form-control-sm" placeholder="Tiêu đề ảnh">
        <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(div);
}

// HÀM MỚI: Hiển thị ảnh cũ trong chế độ Edit
function addExistingImageInput(url, title) {
    const container = document.getElementById('existingImagesContainer');
    const div = document.createElement('div');
    div.className = 'd-flex align-items-center justify-content-between border rounded p-2 mb-2 bg-light';
    
    // Đường dẫn hiển thị (thêm ../ nếu cần)
    const displayUrl = url.startsWith('http') ? url : '../' + url;

    div.innerHTML = `
        <div class="d-flex align-items-center">
            <img src="${displayUrl}" class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">
            <input type="text" name="existing_images[title][]" class="form-control form-control-sm" value="${escapeHtml(title)}" placeholder="Tiêu đề">
            <input type="hidden" name="existing_images[url][]" value="${escapeHtml(url)}">
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="this.closest('div.d-flex').remove()"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(div);
}

function openAddTypeModal() {
    document.getElementById('typeModalTitle').innerText = 'Thêm Loại Phòng';
    document.getElementById('typeAction').value = 'add_type';
    document.getElementById('typeId').value = '';
    document.getElementById('typeName').value = '';
    document.getElementById('typePrice').value = '';
    document.getElementById('typeOrder').value = '0'; 
    document.getElementById('typeCapacity').value = '2';
    document.getElementById('typeSize').value = '30';
    document.getElementById('typeView').value = '';
    
    // Reset Image Inputs
    document.getElementById('typeImageFile').value = '';
    document.getElementById('currentImagePreview').classList.add('d-none');
    document.getElementById('currentImageValue').value = '';

    document.getElementById('typeDesc').value = '';
    document.getElementById('typeLongDesc').value = ''; // Reset long desc
    
    document.getElementById('featuresContainer').innerHTML = '';
    document.getElementById('imagesContainer').innerHTML = '';
    document.getElementById('existingImagesContainer').innerHTML = ''; // Clear existing images
    
    addFeatureInput();
    addImageInput();
    
    getModalInstance('typeModal').show();
}

function editType(roomId) {
    const room = allRoomTypes[roomId];
    if (!room) return;

    document.getElementById('typeModalTitle').innerText = 'Sửa Loại Phòng';
    document.getElementById('typeAction').value = 'edit_type';
    document.getElementById('typeId').value = room.id;
    
    document.getElementById('typeName').value = room.room_type_name || '';
    document.getElementById('typePrice').value = room.price_per_night || '';
    document.getElementById('typeOrder').value = room.display_order || '0';
    document.getElementById('typeCapacity').value = room.max_occupancy || '';
    document.getElementById('typeSize').value = room.room_size_sqm || '';
    document.getElementById('typeView').value = room.view_type || '';
    
    // Xử lý hiển thị ảnh đại diện cũ
    document.getElementById('typeImageFile').value = ''; // Reset file input
    if (room.image_url) {
        document.getElementById('currentImagePreview').classList.remove('d-none');
        document.getElementById('imgPreviewSrc').src = '../' + room.image_url;
        document.getElementById('currentImageValue').value = room.image_url;
    } else {
        document.getElementById('currentImagePreview').classList.add('d-none');
        document.getElementById('currentImageValue').value = '';
    }

    document.getElementById('typeDesc').value = room.description || '';
    document.getElementById('typeLongDesc').value = room.long_description || '';
    
    // Populate Features
    const fContainer = document.getElementById('featuresContainer');
    fContainer.innerHTML = '';
    if (room.features && room.features.length > 0) {
        room.features.forEach(f => addFeatureInput(f.feature_name, f.icon));
    } else {
        addFeatureInput();
    }

    // Populate Images (Gallery)
    const iContainer = document.getElementById('imagesContainer');
    const existingContainer = document.getElementById('existingImagesContainer');
    iContainer.innerHTML = '';
    existingContainer.innerHTML = '';
    
    if (room.images && room.images.length > 0) {
        // Hiển thị các ảnh cũ
        room.images.forEach(img => addExistingImageInput(img.image_url, img.image_title));
    }
    // Luôn thêm 1 dòng upload mới trống
    addImageInput();
    
    getModalInstance('typeModal').show();
}

function viewType(roomId) {
    const room = allRoomTypes[roomId];
    if (!room) {
        console.error('Không tìm thấy loại phòng ID:', roomId);
        return;
    }

    document.getElementById('viewRoomName').innerText = room.room_type_name;
    document.getElementById('viewRoomPrice').innerText = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(room.price_per_night);
    document.getElementById('viewRoomCapacity').innerText = room.max_occupancy;
    document.getElementById('viewRoomSize').innerText = room.room_size_sqm;
    document.getElementById('viewRoomView').innerText = room.view_type || 'Không có';
    document.getElementById('viewRoomDesc').innerText = room.description || 'Chưa có mô tả';
    
    // Xử lý đường dẫn ảnh view
    const mainImgUrl = room.image_url ? '../' + room.image_url : 'https://placehold.co/400x300';
    document.getElementById('viewRoomImage').src = mainImgUrl;

    // Render Features
    const fContainer = document.getElementById('viewRoomFeatures');
    fContainer.innerHTML = '';
    if (room.features && room.features.length > 0) {
        room.features.forEach(f => {
            const span = document.createElement('span');
            span.className = 'feature-tag';
            span.innerHTML = `<i class="fas ${f.icon}"></i> ${escapeHtml(f.feature_name)}`;
            fContainer.appendChild(span);
        });
    } else {
        fContainer.innerHTML = '<span class="text-muted">Chưa có tiện nghi nào.</span>';
    }

    // Render Gallery
    const gContainer = document.getElementById('viewRoomGallery');
    gContainer.innerHTML = '';
    if (room.images && room.images.length > 0) {
        room.images.forEach(img => {
            const imgUrl = img.image_url ? '../' + img.image_url : 'https://placehold.co/100';
            const div = document.createElement('div');
            div.className = 'col-4';
            div.innerHTML = `
                <img src="${imgUrl}" class="img-fluid rounded border gallery-thumb" 
                     onclick="document.getElementById('viewRoomImage').src='${imgUrl}'"
                     alt="${escapeHtml(img.image_title)}">
            `;
            gContainer.appendChild(div);
        });
    }

    // Render physical rooms
    const tbody = document.getElementById('viewRoomPhysicalList');
    tbody.innerHTML = '';
    const rooms = allPhysicalRooms[room.id] || [];
    
    if (rooms.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Chưa có phòng nào.</td></tr>';
    } else {
        rooms.forEach(r => {
             const statusBadges = {
                'available': '<span class="badge bg-success">Trống</span>',
                'occupied': '<span class="badge bg-danger">Đang ở</span>',
                'maintenance': '<span class="badge bg-warning text-dark">Bảo trì</span>',
                'cleaning': '<span class="badge bg-info">Đang dọn</span>',
                'dirty': '<span class="badge bg-secondary">Chưa dọn</span>'
            };
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${escapeHtml(r.room_number)}</td>
                <td>${r.floor || '-'}</td>
                <td>${statusBadges[r.status] || r.status}</td>
                <td>${escapeHtml(r.notes || '')}</td>
            `;
            tbody.appendChild(row);
        });
    }

    getModalInstance('viewRoomModal').show();
}

function managePhysicalRooms(typeId) {
    const room = allRoomTypes[typeId];
    if (!room) return;

    document.getElementById('currentRoomTypeName').innerText = room.room_type_name;
    document.getElementById('currentRoomTypeId').value = room.id;
    
    const tbody = document.getElementById('physicalRoomsList');
    tbody.innerHTML = '';
    
    const rooms = allPhysicalRooms[typeId] || [];
    if (rooms.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Chưa có phòng nào</td></tr>';
    } else {
        rooms.forEach(r => {
            const statusLabels = {
                'available': 'Trống',
                'occupied': 'Đang ở',
                'maintenance': 'Bảo trì',
                'cleaning': 'Đang dọn',
                'dirty': 'Chưa dọn'
            };
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${escapeHtml(r.room_number)}</td>
                <td>${r.floor}</td>
                <td>${statusLabels[r.status] || r.status}</td>
                <td>${escapeHtml(r.notes || '')}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick='openEditRoomModal(${JSON.stringify(r)})'>
                        <i class="fas fa-edit"></i>
                    </button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa phòng này?');">
                        <input type="hidden" name="action" value="delete_room">
                        <input type="hidden" name="room_id" value="${r.id}">
                        <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            `;
            tbody.appendChild(row);
        });
    }
    
    getModalInstance('physicalRoomsModal').show();
}

function openEditRoomModal(room) {
    document.getElementById('editPRId').value = room.id;
    document.getElementById('editPRNumber').value = room.room_number;
    document.getElementById('editPRFloor').value = room.floor;
    document.getElementById('editPRStatus').value = room.status;
    document.getElementById('editPRNotes').value = room.notes || '';
    
    // Hide manage modal, show edit modal
    getModalInstance('physicalRoomsModal').hide();
    getModalInstance('editPhysicalRoomModal').show();
}

function closeEditModal() {
    getModalInstance('editPhysicalRoomModal').hide();
    // Re-open manage modal
    getModalInstance('physicalRoomsModal').show();
}

function escapeHtml(text) {
  if (!text) return '';
  return text
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
}

// Auto reopen modal if needed (after PHP submit)
<?php if ($reopen_modal_for_type): ?>
document.addEventListener('DOMContentLoaded', function() {
    managePhysicalRooms(<?php echo $reopen_modal_for_type; ?>);
});
<?php endif; ?>
</script>

<?php include 'components/footer.php'; ?>
