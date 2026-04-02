gsap.registerPlugin(ScrollTrigger);

// Force show sections
document.querySelectorAll('.homestay-details, .attractions-section, .activities-section, .gallery-section').forEach(section => {
    section.style.opacity = '1';
    section.style.visibility = 'visible';
    section.style.display = 'block';
});

// Animations
gsap.from('.navbar', { y: -100, duration: 0.8, ease: 'power2.out' });
gsap.from('.carousel-overlay', { opacity: 0, y: 50, duration: 0.8, delay: 0.2 });
gsap.from('.cta-section', { opacity: 0, y: 50, duration: 0.8, scrollTrigger: { trigger: '.cta-section', start: 'top 80%' } });

// Optimized Gallery Animation
gsap.from('.gallery-section h2', { opacity: 0, y: 30, duration: 0.6, scrollTrigger: { trigger: '.gallery-section', start: 'top 80%' } });
gsap.from('.gallery-card', { 
    opacity: 0, 
    y: 30, 
    stagger: 0.1, 
    duration: 0.5, 
    ease: 'power2.out', 
    scrollTrigger: { 
        trigger: '.gallery-grid', 
        start: 'top 80%',
        toggleActions: 'play none none none'
    } 
});

// Homestay Details Animations
gsap.from('.homestay-details h2', { opacity: 0, y: 30, duration: 0.8, scrollTrigger: { trigger: '.homestay-details', start: 'top 80%' } });
gsap.from('.homestay-details .intro', { opacity: 0, y: 30, duration: 0.8, delay: 0.2, scrollTrigger: { trigger: '.homestay-details', start: 'top 80%' } });
gsap.from('.detail-card', { 
    opacity: 0, 
    y: 50, 
    stagger: 0.2, 
    duration: 0.8, 
    ease: 'power2.out', 
    scrollTrigger: { trigger: '.details-grid', start: 'top 80%' } 
});
gsap.from('.amenity-card', { 
    opacity: 0, 
    scale: 0.8, 
    stagger: 0.1, 
    duration: 0.6, 
    ease: 'back.out(1.7)', 
    scrollTrigger: { trigger: '.amenities-grid', start: 'top 80%' } 
});

// Attractions Section Animations
gsap.from('.attractions-section h2', { opacity: 0, y: 30, duration: 0.8, scrollTrigger: { trigger: '.attractions-section', start: 'top 80%' } });
gsap.from('.attraction-card', { 
    opacity: 0, 
    y: 50, 
    stagger: 0.2, 
    duration: 0.8, 
    ease: 'power2.out', 
    scrollTrigger: { trigger: '.attractions-grid', start: 'top 80%' } 
});

// Activities Section Animations
gsap.from('.activities-section h2', { opacity: 0, y: 30, duration: 0.8, scrollTrigger: { trigger: '.activities-section', start: 'top 80%' } });
gsap.from('.activity-card', { 
    opacity: 0, 
    scale: 0.9, 
    stagger: 0.2, 
    duration: 0.8, 
    ease: 'power2.out', 
    scrollTrigger: { trigger: '.activities-grid', start: 'top 80%' } 
});

gsap.from('.contact-section', { opacity: 0, y: 50, duration: 0.8, scrollTrigger: { trigger: '.contact-section', start: 'top 80%' } });

// Navbar Shrink and Scroll to Top
window.addEventListener('scroll', () => {
    const navbar = document.querySelector('.navbar');
    navbar.classList.toggle('shrink', window.scrollY > 50);

    const scrollBtn = document.querySelector('.scroll-to-top');
    scrollBtn.classList.toggle('show', window.scrollY > 300);
});

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Smooth Scroll for Nav Links
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', function (e) {
        if (this.hash && document.querySelector(this.hash)) {
            e.preventDefault();
            document.querySelector(this.hash).scrollIntoView({ behavior: 'smooth' });
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            this.classList.add('active');
        }
    });
});

// Preload Gallery Images
const galleryImages = [
    'images/kitchen.jpg',
    'images/living_room.jpg',
    'images/tv.jpg',
    'images/bedroom1.jpg',
    'images/bedroom2.jpg',
    'images/bedroom3.jpg',
    'images/bedroom4.jpg',
    'images/bathroom.jpg',
    'images/bathroom2.jpg',
    'images/bathroom3.jpg',
    'images/patio.jpg',
    'images/washingmachine.jpg',
    'images/table.jpg'
];

galleryImages.forEach(src => {
    const img = new Image();
    img.src = src;
});

// Carousel Initialization
try {
    const homestayCarousel = new bootstrap.Carousel(document.querySelector('#homestayCarousel'), {
        interval: 5000,
        wrap: true,
        touch: true,
        ride: 'carousel'
    });
} catch (e) {
    console.error('Carousel initialization failed:', e);
}

// Gallery Card and Lightbox
const galleryCards = document.querySelectorAll('.gallery-card');
const lightbox = document.getElementById('lightbox');
const lightboxImage = document.getElementById('lightboxImage');
const closeLightbox = document.getElementById('closeLightbox');

galleryCards.forEach(card => {
    card.addEventListener('click', (e) => {
        e.preventDefault();
        const imgSrc = card.querySelector('img').src;
        lightboxImage.src = imgSrc;
        lightbox.classList.add('show');
        gsap.fromTo(lightboxImage, 
            { opacity: 0, scale: 0.8 },
            { opacity: 1, scale: 1, duration: 0.4, ease: 'power2.out' }
        );
    });
});

 READYcloseLightbox.addEventListener('click', () => {
    gsap.to(lightboxImage, {
        opacity: 0,
        scale: 0.8,
        duration: 0.3,
        ease: 'power2.in',
        onComplete: () => {
            lightbox.classList.remove('show');
            lightboxImage.src = ''; // Clear image to free memory
        }
    });
});

lightbox.addEventListener('click', (e) => {
    if (e.target === lightbox) {
        closeLightbox.click();
    }
});

// Keyboard Navigation for Lightbox
document.addEventListener('keydown', (e) => {
    if (lightbox.classList.contains('show') && e.key === 'Escape') {
        closeLightbox.click();
    }
});

// Activity Modals
const activityCards = document.querySelectorAll('.activity-card');
const activityModals = document.querySelectorAll('.activity-modal');
const closeModals = document.querySelectorAll('.close-modal');

activityCards.forEach(card => {
    card.addEventListener('click', () => {
        const modalId = card.dataset.activity + '-modal';
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            gsap.fromTo(modal.querySelector('.modal-content'), 
                { opacity: 0, y: 50 },
                { opacity: 1, y: 0, duration: 0.5, ease: 'power2.out' }
            );
        }
    });
});

closeModals.forEach(close => {
    close.addEventListener('click', () => {
        const modal = close.closest('.activity-modal');
        gsap.to(modal.querySelector('.modal-content'), {
            opacity: 0,
            y: 50,
            duration: 0.3,
            ease: 'power2.in',
            onComplete: () => {
                modal.classList.remove('show');
            }
        });
    });
});

activityModals.forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.querySelector('.close-modal').click();
        }
    });
});

// Lazy Load Images with Intersection Observer
const lazyImages = document.querySelectorAll('img[loading="lazy"]');
const observer = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const img = entry.target;
            img.src = img.dataset.src || img.src;
            observer.unobserve(img);
        }
    });
}, { rootMargin: '200px' });

lazyImages.forEach(img => observer.observe(img));

// Fallback to show sections
setTimeout(() => {
    document.querySelectorAll('.homestay-details, .attractions-section, .activities-section, .gallery-section').forEach(section => {
        section.style.opacity = '1';
        section.style.visibility = 'visible';
        section.style.display = 'block';
    });
}, 1000);