<?php

class RoomAvailability {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get the count of available physical rooms for a specific Room Type
     */
    public function getAvailableRoomCountForType($room_id, $checkin, $checkout) {
        try {
            $sql = "CALL sp_count_available_rooms(:room_id, :checkin, :checkout)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':room_id' => $room_id,
                ':checkin' => $checkin,
                ':checkout' => $checkout
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            
            return intval($result['available_count']);

        } catch (PDOException $e) {
            error_log("Availability Count Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get list of Room Types with their availability count
     */
    public function getAvailableRooms($checkin, $checkout, $adults = 2, $children = 0) {
        try {
            $total_guests = $adults + $children;
            
            $sql = "
                SELECT r.* FROM rooms r
                WHERE r.is_active = 1
                  AND r.max_occupancy >= :total_guests
                  AND r.max_adults >= :adults
                  AND r.max_children >= :children
                ORDER BY r.price_per_night ASC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':total_guests' => $total_guests,
                ':adults' => $adults,
                ':children' => $children
            ]);
            
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $available_rooms = [];

            foreach ($rooms as $room) {
                $count = $this->getAvailableRoomCountForType($room['id'], $checkin, $checkout);
                
                if ($count > 0) {
                    $room['available_count'] = $count;
                    $room['features'] = $this->getRoomFeatures($room['id']);
                    $available_rooms[] = $room;
                }
            }
            
            return $available_rooms;
            
        } catch (PDOException $e) {
            error_log("Get Available Rooms Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Assign a specific physical Room Number ID for a new booking.
     * UPDATED: Accepts early/late flags
     */
    public function assignRoomNumber($room_id, $checkin, $checkout, $late_checkout = 0) {
        try {
            // Bỏ tham số :early
            $sql = "CALL sp_assign_room(:room_id, :checkin, :checkout, :late)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':room_id' => $room_id,
                ':checkin' => $checkin,
                ':checkout' => $checkout,
                ':late' => $late_checkout
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            
            if ($result && isset($result['id'])) {
                return $result['id'];
            }
            return null;
        } catch (Exception $e) {
            error_log("Assign Room Error: " . $e->getMessage());
            return null;
        }
    }

    // Helper: Get features for display
    private function getRoomFeatures($room_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT feature_name, icon FROM room_features WHERE room_id = ?");
            $stmt->execute([$room_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return []; }
    }
    
    // Helper: Get Room Details
    public function getRoomDetails($room_id) {
         try {
            $stmt = $this->pdo->prepare("SELECT * FROM rooms WHERE id = :id AND is_active = 1");
            $stmt->execute([':id' => $room_id]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($room) {
                $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM room_numbers WHERE room_id = ?");
                $stmtCount->execute([$room_id]);
                $room['total_physical_rooms'] = $stmtCount->fetchColumn();
                
                $stmtImg = $this->pdo->prepare("SELECT image_url, image_title FROM room_images WHERE room_id = ? ORDER BY is_primary DESC");
                $stmtImg->execute([$room_id]);
                $room['images'] = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
                
                $room['features'] = $this->getRoomFeatures($room_id);
            }
            return $room;
        } catch (PDOException $e) { return null; }
    }
    
    // Real implementation for Early/Late checks
    // public function canEarlyCheckin($room_id, $checkin, $checkout) { 
    //     return $this->checkOptionAvailability($room_id, $checkin, $checkout, 1, 0);
    // }
    
    public function canLateCheckout($room_id, $checkin, $checkout) { 
        return $this->checkOptionAvailability($room_id, $checkin, $checkout, 1);
    }

    // Helper to call the stored procedure
    private function checkOptionAvailability($room_id, $checkin, $checkout, $late) {
        try {
            // Bỏ tham số :early
            $sql = "CALL sp_check_option_availability(:room_id, :checkin, :checkout, :late)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':room_id' => $room_id,
                ':checkin' => $checkin,
                ':checkout' => $checkout,
                ':late' => $late
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            
            return ($result && intval($result['count']) > 0);
        } catch (PDOException $e) {
            error_log("Option Check Error: " . $e->getMessage());
            return false; 
        }
    }
}
?>