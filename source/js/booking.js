// js/booking.js

document.addEventListener('DOMContentLoaded', function() {
    const checkinDate = document.getElementById('checkin');
    const checkoutDate = document.getElementById('checkout');

    if (checkinDate && checkoutDate) {
        // Đảm bảo ngày check-out không thể trước ngày check-in
        checkinDate.addEventListener('change', function() {
            if (checkoutDate.value && checkoutDate.value < checkinDate.value) {
                checkoutDate.value = checkinDate.value;
            }
            checkoutDate.min = checkinDate.value;
        });

        // Đặt ngày tối thiểu cho cả hai là ngày hôm nay
        const today = new Date().toISOString().split('T')[0];
        if (!checkinDate.value) {
            checkinDate.min = today;
        }
        if (!checkoutDate.value) {
            checkoutDate.min = today;
        }
    }
});