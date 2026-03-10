# Room Availability and Booking Logic Refactoring

## Summary
Refactored the room availability and booking logic to properly work with the three-table database structure:
1. **Room Types** (`rooms` table) - General category information
2. **Room Details** (`room_numbers` table) - Specific room instances
3. **Bookings** (`bookings` table) - Reservation records

## Changes Made

### 1. Fixed `RoomAvailability.php`

#### Problem:
- The availability counting logic was incorrectly counting ALL bookings for a room type
- It didn't properly check which specific `room_numbers` were booked
- **Critical Fix**: The database trigger sets `room_numbers.status` to 'occupied' immediately when a booking is created. Filtering by `status = 'available'` was incorrectly hiding rooms that are currently occupied (or booked for future) but should be available for *other* dates.

#### Solution:
- **`getAvailableRoomCountForType()` method**: Now correctly counts `room_numbers` that are NOT in maintenance and NOT booked during the requested date range.
  ```sql
  SELECT COUNT(*) as available_count
  FROM room_numbers rn
  WHERE rn.room_id = :room_id
    AND rn.status != 'maintenance' -- Include 'occupied'/'cleaning' rooms as they are valid inventory
    AND rn.id NOT IN (
        -- Find room_numbers with overlapping bookings
        SELECT DISTINCT b.room_number_id
        FROM bookings b
        WHERE b.room_number_id IS NOT NULL
          AND b.room_id = :room_id
          AND b.booking_status NOT IN ('cancelled', 'no_show', 'checked_out')
          AND b.checkin_date < :checkout
          AND b.checkout_date > :checkin
    )
  ```

- **`getAvailableRooms()` method**: Updated to use the corrected counting logic for each room type.
- **`getAvailableRoomNumbers()` method**: Updated to use `status != 'maintenance'`.

### 2. Updated `booking_process.php`

#### Problem:
- Bookings were created WITHOUT assigning a specific `room_number_id`
- This meant the availability logic couldn't track which physical rooms were booked

#### Solution:
- Added `require '../includes/RoomAvailability.php';` to import the class
- Before creating a booking, now assigns a specific room:
  ```php
  $roomAvailability = new RoomAvailability($pdo);
  $room_number_id = $roomAvailability->assignRoomNumber($room_id, $checkin, $checkout);
  
  if ($room_number_id === null) {
      throw new Exception('Cannot assign specific room. May be fully booked.');
  }
  ```

- Updated the INSERT query to include `room_number_id`:
  ```sql
  INSERT INTO bookings (
      booking_reference, user_id, room_id, room_number_id,  -- Added room_number_id
      ...
  )
  ```

## How It Works Now

### Availability Check Flow:
1. User selects check-in/check-out dates
2. System queries `rooms` table for matching room types
3. For each room type, system:
   - Counts total `room_numbers` that are operational (not in maintenance)
   - Subtracts `room_numbers` that have confirmed bookings overlapping the requested dates
   - Returns the difference as available count
4. Only room types with available_count > 0 are shown

### Booking Flow:
1. User selects a room type and confirms booking
2. System calls `assignRoomNumber()` to find an available `room_number`  
3. System creates booking record with:
   - `room_id`: The room type
   - `room_number_id`: The specific physical room assigned
4. This ensures future availability checks exclude this specific room

## Key Benefits

✅ **Accurate Availability**: Counts actual available physical rooms, not just bookings  
✅ **Prevent Overbooking**: Each booking is assigned to a specific room  
✅ **Date Overlap Logic**: Proper handling of check-in/check-out date conflicts  
✅ **Trigger Compatibility**: Works correctly even with the `tr_booking_created` trigger setting status to 'occupied'  

## Files Modified
- `d:\_apps\xampp\htdocs\includes\RoomAvailability.php` - Core availability logic
- `d:\_apps\xampp\htdocs\booking\booking_process.php` - Booking creation with room assignment
