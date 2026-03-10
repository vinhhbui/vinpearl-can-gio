// Mobile Menu Toggle
const menuBtn = document.getElementById('mobileMenuButton');
const mobileMenu = document.getElementById('mobileMenu');

if (menuBtn && mobileMenu) {
    menuBtn.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
    });
}

// Navbar scroll effect
const navbar = document.getElementById('navbar');
if (navbar) {
    window.onscroll = () => {
        if (window.scrollY > 50) {
            navbar.classList.add('bg-gray-900', 'shadow-lg');
            // navbar.classList.add('scrolled'); // Bạn cũng có thể dùng class 'scrolled' từ main.css
            navbar.classList.remove('bg-transparent');
        } else {
            navbar.classList.remove('bg-gray-900', 'shadow-lg');
            // navbar.classList.remove('scrolled');
            navbar.classList.add('bg-transparent');
        }
    };
}