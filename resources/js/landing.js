// Smooth scrolling
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        // Only handle same-page fragment links
        const href = this.getAttribute('href');
        if (!href || href.length < 2) return;
        const target = document.querySelector(href);
        if (!target) return;
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth' });
    });
});

// Fade in animation on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, observerOptions);

document.querySelectorAll('.fade-in').forEach(el => {
    observer.observe(el);
});

// FAQ Toggle
window.toggleFaq = function(index) {
    const content = document.getElementById(`faq-content-${index}`);
    const icon = document.getElementById(`faq-icon-${index}`);
    if (!content || !icon) return;
    const isHidden = content.classList.contains('hidden');
    content.classList.toggle('hidden', !isHidden);
    icon.classList.toggle('rotate-45', isHidden);
};

// Mobile menu toggle
window.toggleMobileMenu = function() {
    const mobileMenu = document.getElementById('mobile-menu');
    const menuButtonIcon = document.querySelector('[onclick="toggleMobileMenu()"] i');
    if (!mobileMenu || !menuButtonIcon) return;
    const isHidden = mobileMenu.classList.contains('hidden');
    mobileMenu.classList.toggle('hidden', !isHidden);
    menuButtonIcon.classList.toggle('fa-bars', !isHidden);
    menuButtonIcon.classList.toggle('fa-times', isHidden);
};

// Close mobile menu when clicking a link inside it
document.addEventListener('DOMContentLoaded', function() {
    const mobileLinks = document.querySelectorAll('#mobile-menu a[href^="#"]');
    mobileLinks.forEach(link => {
        link.addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobile-menu');
            const menuButtonIcon = document.querySelector('[onclick="toggleMobileMenu()"] i');
            if (mobileMenu && menuButtonIcon) {
                mobileMenu.classList.add('hidden');
                menuButtonIcon.classList.remove('fa-times');
                menuButtonIcon.classList.add('fa-bars');
            }
        });
    });
});

// Card hover raise effect
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.glass, .glass-dark');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.transition = 'transform 0.3s ease';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});


