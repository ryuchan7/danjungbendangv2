<?php
session_start();
require_once 'db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>D'Anjung Bendang Homestay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
    <style>
        :root {
            --primary: #1a3c5e;
            --secondary: #4a90e2;
            --accent: #ffd700;
            --text: #2d3748;
            --bg-light: #f7fafc;
            --bg-dark: #1a202c;
            --glass-bg: rgba(255, 255, 255, 0.15);
            --success: #28a745;
            --gradient: linear-gradient(135deg, #4a90e2, #1a3c5e);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            color: var(--text);
            line-height: 1.7;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        /* Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.9);
            padding: 1rem 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            z-index: 1000;
            top: 0;
        }

        .navbar.shrink {
            padding: 0.5rem 0;
            background: rgba(255, 255, 255, 0.95);
        }

        .navbar-brand {
            font-weight: 600;
            color: var(--primary) !important;
            font-size: 1.9rem;
            letter-spacing: 0.5px;
        }

        .navbar-nav .nav-link {
            color: var(--text) !important;
            font-weight: 500;
            margin-left: 1.5rem;
            padding: 0.5rem 1rem;
            position: relative;
            transition: color 0.3s ease;
        }

        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--accent);
            transition: width 0.3s ease;
        }

        .navbar-nav .nav-link:hover::after,
        .navbar-nav .nav-link.active::after {
            width: 100%;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: var(--secondary) !important;
        }

        .booking-btn {
            background: var(--secondary);
            color: #fff !important;
            padding: 0.6rem 1.8rem;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .booking-btn:hover {
            background: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        /* Carousel */
        .carousel-container {
            min-height: 100vh;
            position: relative;
            overflow: hidden;
        }

        .carousel-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #fff;
            text-align: center;
            z-index: 10;
            background: rgba(26, 60, 94, 0.8);
            padding: 2rem 3rem;
            border-radius: 10px;
            max-width: 600px;
        }

        .carousel-overlay h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.2;
            letter-spacing: 0.5px;
        }

        .carousel-overlay p {
            font-size: 1.2rem;
            font-weight: 300;
            margin-bottom: 2rem;
        }

        .hero-btn {
            padding: 0.8rem 2.5rem;
            background: var(--accent);
            border: none;
            border-radius: 25px;
            color: var(--primary);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .hero-btn:hover {
            background: var(--secondary);
            color: #fff;
            transform: translateY(-4px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }

        .carousel-item img {
            height: 100vh;
            object-fit: cover;
            filter: brightness(70%) contrast(1.2);
            transition: transform 10s ease;
        }

        .carousel-item.active img {
            transform: scale(1.1);
        }

        /* CTA Section */
        .cta-section {
            background: url('images/cta-bg.jpg') center/cover no-repeat;
            padding: 6rem 0;
            color: #fff;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(26, 60, 94, 0.7);
            z-index: 1;
        }

        .cta-section .container {
            position: relative;
            z-index: 2;
        }

        .cta-section h2 {
            font-weight: 600;
            margin-bottom: 1.2rem;
            font-size: 2.8rem;
        }

        .cta-section p {
            font-size: 1.3rem;
            max-width: 650px;
            margin: 0 auto 2.5rem;
        }

        .cta-btn {
            padding: 0.8rem 2.5rem;
            background: var(--accent);
            color: var(--primary);
            border: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .cta-btn:hover {
            background: #fff;
            color: var(--secondary);
            transform: translateY(-4px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }

        /* Gallery Section */
        .gallery-section {
            padding: 5rem 0;
            background: #fff;
        }

        .gallery-section h2 {
            color: var(--primary);
            font-weight: 600;
            text-align: center;
            margin-bottom: 2.5rem;
            font-size: 2.5rem;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .gallery-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            cursor: pointer;
        }

        .gallery-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .gallery-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            display: block;
        }

        .gallery-card .card-content {
            padding: 1rem;
            text-align: center;
        }

        .gallery-card .card-content h5 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .gallery-card .card-content p {
            font-size: 1rem;
            color: var(--text);
            margin-bottom: 0.8rem;
        }

        .gallery-card .card-content .view-details {
            background: var(--secondary);
            color: #fff;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .gallery-card .card-content .view-details:hover {
            background: var(--primary);
        }

        /* Lightbox */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            transition: opacity 0.3s ease;
        }

        .lightbox.show {
            display: flex;
            opacity: 1;
        }

        .lightbox-content {
            text-align: center;
            max-width: 95vw;
            max-height: 95vh;
        }

        .lightbox img {
            max-width: 95vw;
            max-height: 80vh;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .lightbox-caption {
            margin-top: 1rem;
            color: #fff;
        }

        .lightbox-caption h5 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .lightbox-caption p {
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .lightbox .close-btn {
            position: fixed;
            top: 20px;
            right: 30px;
            color: #fff;
            font-size: 2rem;
            cursor: pointer;
            transition: transform 0.3s ease;
            z-index: 2100;
        }

        .lightbox .close-btn:hover {
            transform: scale(1.2);
        }

        .lightbox-nav {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            border: none;
            padding: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .lightbox-nav:hover {
            background: var(--secondary);
        }

        .lightbox-nav.prev {
            left: 20px;
        }

        .lightbox-nav.next {
            right: 20px;
        }

        /* Homestay Details */
        .homestay-details {
            max-width: 1200px;
            margin: 7rem auto;
            padding: 5rem 3rem;
            background: linear-gradient(135deg, #ffffff, #e6f0fa);
            border-radius: 25px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
            opacity: 1;
            visibility: visible;
            display: block;
        }

        .homestay-details::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('images/paddy_field_pattern.png') repeat;
            opacity: 0.05;
            z-index: 0;
        }

        .homestay-details .container {
            position: relative;
            z-index: 2;
        }

        .homestay-details h2 {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 2rem;
            font-size: 3.2rem;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .homestay-details .intro {
            font-size: 1.3rem;
            text-align: center;
            max-width: 900px;
            margin: 0 auto 3rem;
            color: var(--text);
            font-weight: 300;
            line-height: 1.9;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2.5rem;
            margin-bottom: 4rem;
        }

        .detail-card {
            background: #fff;
            padding: 2.5rem;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .detail-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient);
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: -1;
        }

        .detail-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.2);
        }

        .detail-card:hover::after {
            opacity: 0.05;
        }

        .detail-card i {
            font-size: 3rem;
            color: var(--secondary);
            margin-bottom: 1.5rem;
            transition: color 0.3s ease;
        }

        .detail-card:hover i {
            color: var(--accent);
        }

        .detail-card h4 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.8rem;
        }

        .detail-card p {
            font-size: 1.1rem;
            color: var(--text);
            margin: 0;
        }

        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .amenity-card {
            perspective: 1000px;
            position: relative;
            height: 120px;
        }

        .amenity-card .card-inner {
            position: relative;
            width: 100%;
            height: 100%;
            transition: transform 0.6s;
            transform-style: preserve-3d;
        }

        .amenity-card:hover .card-inner {
            transform: rotateY(180deg);
        }

        .amenity-card .card-front,
        .amenity-card .card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }

        .amenity-card .card-back {
            transform: rotateY(180deg);
            background: var(--secondary);
            color: #fff;
        }

        .amenity-card i {
            font-size: 2.2rem;
            color: var(--secondary);
            margin-bottom: 1rem;
        }

        .amenity-card .card-back i {
            color: var(--accent);
        }

        .amenity-card p {
            margin: 0;
            font-size: 1rem;
            color: var(--text);
        }

        .amenity-card .card-back p {
            color: #fff;
            font-size: 0.9rem;
        }

        /* Local Attractions Section */
        .attractions-section {
            max-width: 1200px;
            margin: 7rem auto;
            padding: 5rem 3rem;
            background: #fff;
            border-radius: 25px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
            opacity: 1;
            visibility: visible;
            display: block;
        }

        .attractions-section h2 {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 2rem;
            font-size: 3rem;
            text-align: center;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .attractions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2.5rem;
        }

        .attraction-card {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            cursor: pointer;
        }

        .attraction-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .attraction-card:hover img {
            transform: scale(1.1);
        }

        .attraction-card .card-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(26, 60, 94, 0.9), transparent);
            color: #fff;
            padding: 1.5rem;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }

        .attraction-card:hover .card-overlay {
            transform: translateY(0);
        }

        .attraction-card .card-overlay h4 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .attraction-card .card-overlay p {
            font-size: 1rem;
            margin: 0;
        }

        .attraction-card .card-overlay .map-btn {
            display: inline-block;
            background: var(--accent);
            color: var(--primary);
            padding: 0.5rem 1.2rem;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            margin-top: 0.5rem;
            transition: all 0.3s ease;
        }

        .attraction-card .card-overlay .map-btn:hover {
            background: var(--secondary);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        /* Guest Activities Section */
        .activities-section {
            max-width: 1200px;
            margin: 7rem auto;
            padding: 5rem 3rem;
            background: var(--gradient);
            border-radius: 25px;
            color: #fff;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
            opacity: 1;
            visibility: visible;
            display: block;
        }

        .activities-section h2 {
            font-weight: 700;
            font-size: 3rem;
            margin-bottom: 2rem;
            text-align: center;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2.5rem;
        }

        .activity-card {
            background: #fff;
            padding: 2.5rem;
            border-radius: 20px;
            color: var(--text);
            transition: all 0.4s ease;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            cursor: pointer;
            position: relative;
        }

        .activity-card:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 25px rgba(0,0,0,0.2);
        }

        .activity-card i {
            font-size: 2.5rem;
            color: var(--secondary);
            margin-bottom: 1rem;
        }

        .activity-card h4 {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .activity-card p {
            font-size: 1.1rem;
            margin: 0;
        }

        /* Activity Modal */
        .activity-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .activity-modal.show {
            display: flex;
        }

        .modal-content {
            background: #fff;
            max-width: 600px;
            margin: 1rem;
            padding: 2rem;
            border-radius: 15px;
            position: relative;
            text-align: center;
        }

        .modal-content h4 {
            color: var(--primary);
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }

        .modal-content p {
            color: var(--text);
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        .modal-content .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 1.5rem;
            color: var(--text);
            cursor: pointer;
        }

        /* Contact Section */
        .contact-section {
            max-width: 1200px;
            margin: 7rem auto;
            background: var(--bg-dark);
            color: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 4rem;
            text-align: center;
            opacity: 1;
            visibility: visible;
            display: block;
        }

        .contact-section h3 {
            font-weight: 700;
            margin-bottom: 2rem;
            font-size: 2.5rem;
        }

        .contact-section .lead {
            font-weight: 400;
            margin-bottom: 1.8rem;
            font-size: 1.2rem;
        }

        .contact-section .social-icons a {
            color: #fff;
            font-size: 2rem;
            margin: 0 1.2rem;
            transition: all 0.3s ease;
        }

        .contact-section .social-icons a:hover {
            color: var(--accent);
            transform: scale(1.2) rotate(10deg);
        }

        /* Map Section */
        .map-section {
            max-width: 1200px;
            margin: 7rem auto;
            padding: 5rem 3rem;
            background: #fff;
            border-radius: 25px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
            opacity: 1;
            visibility: visible;
            display: block;
        }

        .map-section h2 {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 2rem;
            font-size: 3rem;
            text-align: center;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .map-section p {
            font-size: 1.3rem;
            text-align: center;
            max-width: 900px;
            margin: 0 auto 3rem;
            color: var(--text);
            font-weight: 300;
            line-height: 1.9;
        }

        .map-container {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            height: 0;
            overflow: hidden;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .map-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        @media (max-width: 992px) {
            .map-section {
                padding: 3rem;
            }
        }

        @media (max-width: 576px) {
            .map-section {
                padding: 2rem;
                margin: 4rem 1rem;
            }

            .map-section h2 {
                font-size: 2rem;
            }

            .map-section p {
                font-size: 1rem;
            }
        }

        /* Footer */
        footer {
            background: var(--bg-dark);
            color: #fff;
            padding: 2.5rem 0;
            text-align: center;
            font-weight: 300;
            font-size: 0.95rem;
        }

        /* Scroll to Top */
        .scroll-to-top {
            position: fixed;
            bottom: 2.5rem;
            right: 2.5rem;
            background: var(--secondary);
            color: #fff;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .scroll-to-top.show {
            opacity: 1;
        }

        .scroll-to-top:hover {
            background: var(--primary);
            transform: scale(1.1);
        }

        /* FAB Booking */
        .fab-booking {
            position: fixed;
            bottom: 7rem;
            right: 2.5rem;
            background: var(--secondary);
            color: #fff;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            z-index: 999;
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .fab-booking:hover {
            background: var(--primary);
            transform: scale(1.1);
        }

        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .carousel-overlay h1 {
                font-size: 2rem;
            }

            .carousel-overlay p {
                font-size: 1rem;
            }

            .gallery-card img {
                height: 220px;
            }

            .homestay-details,
            .attractions-section,
            .activities-section,
            .contact-section {
                padding: 3rem;
            }

            .gallery-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 576px) {
            .carousel-overlay {
                padding: 1.5rem 2rem;
            }

            .carousel-overlay h1 {
                font-size: 1.8rem;
            }

            .carousel-overlay p {
                font-size: 0.9rem;
            }

            .carousel-item img {
                height: 80vh;
            }

            .gallery-card img {
                height: 200px;
            }

            .homestay-details,
            .attractions-section,
            .activities-section,
            .contact-section {
                padding: 2rem;
                margin: 4rem 1rem;
            }

            .homestay-details h2,
            .attractions-section h2,
            .activities-section h2,
            .contact-section h3 {
                font-size: 2rem;
            }

            .amenity-card {
                height: 140px;
            }

            .gallery-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">D'Anjung Bendang Homestay</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link active" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#gallery">Gallery</a></li>
                    <li class="nav-item"><a class="nav-link" href="#details">Details</a></li>
                    <li class="nav-item"><a class="nav-link" href="#attractions">Attractions</a></li>
                    <li class="nav-item"><a class="nav-link" href="#activities">Activities</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                     <li class="nav-item"><a class="nav-link" href="#map">Map</a></li>
                    <li class="nav-item ms-3">
                        <a class="btn btn-info text-white" href="login.php" style="border-radius: 50px; padding: 6px 20px;">Login</a>
                    </li>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                         
                        <?php endif; ?>
                       
                    <?php else: ?>
                        <li class="nav-item">
                            <!-- Add login link if needed -->
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

<!-- Carousel -->
    <div class="carousel-container" id="home">
        <div class="carousel-overlay">
            <h1>Discover D'Anjung Bendang Homestay</h1>
            <p>A luxurious retreat in the serene heart of Kuala Kurau</p>
            <a href="https://wa.me/message/KHORXYAL2QH7N1" class="btn btn-warning" target="_blank">Book Now</a>
        </div>
        <div id="homestayCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="images/homestay1.jpg" class="d-block w-100" alt="Slide 1" loading="lazy">
                </div>
                <div class="carousel-item">
                    <img src="images/homestay2.jpg" class="d-block w-100" alt="Slide 2" loading="lazy">
                </div>
                <div class="carousel-item">
                    <img src="images/homestay3.jpg" class="d-block w-100" alt="Slide 3" loading="lazy">
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2>Unwind in Elegance</h2>
            <p>Secure your stay at D'Anjung Bendang Homestay for a memorable escape surrounded by nature.</p>
            <a href="https://wa.me/message/KHORXYAL2QH7N1" class="btn btn-warning btn-lg" target="_blank">Book Now</a>
        </div>
    </section>

    <!-- Gallery Section -->
    <section class="gallery-section" id="gallery">
        <div class="container">
            <h2>Explore Our Homestay</h2>
            <div class="gallery-grid">
                <div class="gallery-card" data-id="kitchen">
                    <img src="images/kitchen.jpg" alt="Kitchen" loading="lazy">
                    <div class="card-content">
                        <h5>Kitchen</h5>
                        <p>Modern appliances for seamless meal preparation.</p>
                        <button class="view-details">View Details</button>
                    </div>
                </div>
                <div class="gallery-card" data-id="living_room">
                    <img src="images/living_room.jpg" alt="Living Room" loading="lazy">
                    <div class="card-content">
                        <h5>Living Room</h5>
                        <p>Elegant space with scenic paddy field views.</p>
                        <button class="view-details">View Details</button>
                    </div>
                </div>
                <div class="gallery-card" data-id="tv">
                    <img src="images/tv.jpg" alt="TV" loading="lazy">
                    <div class="card-content">
                        <h5>Television</h5>
                        <p>Enjoy entertainment with a sleek, high-definition display.</p>
                        <button class="view-details">View Details</button>
                    </div>
                </div>
                <div class="gallery-card" data-id="bedroom1">
                    <img src="images/bedroom1.jpg" alt="Bedroom 1" loading="lazy">
                    <div class="card-content">
                        <h5>Bedroom 1</h5>
                        <p>Luxurious double bed for ultimate comfort.</p>
                        <button class="view-details">View Details</button>
                    </div>
                </div>
                <div class="gallery-card" data-id="bedroom2">
                    <img src="images/bedroom2.jpg" alt="Bedroom 2" loading="lazy">
                    <div class="card-content">
                        <h5>Bedroom 2</h5>
                        <p>Twin beds in a soothing environment.</p>
                        <button class="view-details">View Details</button>
                    </div>
                </div>
                <div class="gallery-card" data-id="bedroom3">
                    <img src="images/bedroom3.jpg" alt="Bedroom 3" loading="lazy">
                    <div class="card-content">
                        <h5>Bedroom 3</h5>
                        <p>Twin beds in a soothing environment.</p>
                        <button class="view-details">View Details</button>
                    </div>
                </div>
                <div class="gallery-card" data-id="bedroom4">
                    <img src="images/bedroom4.jpg" alt="Bedroom 4" loading="lazy">
                    <div class="card-content">
                        <h5>Bedroom 4</h5>
                        <p>Twin beds in a soothing environment.</p>
                        <button class="view-details">View Details</button>
                    </div>
                </div>
                <div class="gallery-card" data-id="bathroom">
                    <img src="images/bathroom.jpg" alt="Bathroom" loading="lazy">
                    <div class="card-content">
                        <h5>Bathroom</h5>
                        <p>Sleek and spotless with premium amenities.</p>
                        <button class="view-details">View Details</button>
                    </div>
                </div>
                <div class="gallery-card" data-id="bathroom2">
                    <img src="images/bathroom2.jpg" alt="Bathroom 2" loading="lazy">
                    <div class="card-content">
                        <h5>Bathroom 2</h5>
                        <p>Sleek and spotless with premium amenities.</p>
                        <button class="view-details">View Details</button>
                    </div>
                </div>
                <div class="gallery-card" data-id="bathroom3">
                    <img src="images/bathroom3.jpg" alt="Bathroom 3" loading="lazy">
                    <div class="card-content">
                        <h5>Bathroom 3</h5>
                        <p>Sleek and spotless with premium amenities.</p>
                        <button class="view-details">View Details</button>
                    </div>
                </div>
                <div class="gallery-card" data-id="patio">
                    <img src="images/patio.jpg" alt="Patio" loading="lazy">
                    <div class="card-content">
                        <h5>Patio</h5>
                        <p>Relax with stunning views of lush landscapes.</p>
                        <button class="view-details">View Details</button>
                    </div>
                </div>
                <div class="gallery-card" data-id="washing-machine">
                    <img src="images/washingmachine.jpg" alt="Washing Machine" loading="lazy">
                    <div class="card-content">
                        <h5>Washing Machine</h5>
                        <p>Keep your clothes fresh with modern, efficient cleaning.</p>
                        <button class="view-details">View Details</button>
                    </div>
                </div>
                <div class="gallery-card" data-id="table">
                    <img src="images/table.jpg" alt="Table" loading="lazy">
                    <div class="card-content">
                        <h5>Table</h5>
                        <p>Enjoy meals with elegant and comfortable seating.</p>
                        <button class="view-details">View Details</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox">
        <span class="close-btn" id="closeLightbox">×</span>
        <div class="lightbox-content">
            <img src="" alt="Full Image" id="lightboxImage">
            <div class="lightbox-caption">
                <h5 id="lightboxTitle"></h5>
                <p id="lightboxDescription"></p>
            </div>
        </div>
        <button class="lightbox-nav prev" id="prevImage"><i class="fas fa-chevron-left"></i></button>
        <button class="lightbox-nav next" id="nextImage"><i class="fas fa-chevron-right"></i></button>
    </div>

    <!-- Homestay Details -->
    <section class="homestay-details" id="details">
        <div class="container">
            <h2>About D'Anjung Bendang Homestay</h2>
            <p class="intro">Nestled amidst the tranquil paddy fields of Kuala Kurau, D'Anjung Bendang Homestay offers a perfect blend of modern luxury and rustic charm. Experience a rejuvenating escape with family or friends in our thoughtfully designed retreat.</p>
            <div class="details-grid">
                <div class="detail-card">
                    <i class="fas fa-bed"></i>
                    <h4>Spacious Bedrooms</h4>
                    <p>4 bedrooms for up to 15 guests.</p>
                </div>
                <div class="detail-card">
                    <i class="fas fa-shower"></i>
                    <h4>3 Modern Bathrooms</h4>
                    <p>Enjoy convenience and comfort with three well-equipped bathrooms for your stay.</p>
                </div>
                <div class="detail-card">
                    <i class="fas fa-clock"></i>
                    <h4>Check-In & Check-Out</h4>
                    <p>Check-In: 3:00 PM<br>Check-Out: 12:00 PM</p>
                </div>
                <div class="detail-card">
                    <i class="fas fa-dollar-sign"></i>
                    <h4>Affordable Rates</h4>
                    <p>Only RM 250/night for the entire homestay.</p>
                </div>
                <div class="detail-card">
                    <i class="fas fa-map-marker-alt"></i>
                    <h4>Prime Location</h4>
                    <p>Jalan Parit Haji Yusof, Kampung Batu Lapan, Kuala Kurau, Perak.</p>
                </div>
                <div class="detail-card">
                    <i class="fas fa-chair"></i>
                    <h4>Patio with Paddy Field Views</h4>
                    <p>Relax on our spacious patio while enjoying breathtaking views of the surrounding paddy fields.</p>
                </div>
            </div>
            <h3>Amenities</h3>
            <div class="amenities-grid">
                <div class="amenity-card">
                    <div class="card-inner">
                        <div class="card-front">
                            <i class="fas fa-tshirt"></i>
                            <p>Iron & Ironing Board</p>
                        </div>
                        <div class="card-back">
                            <i class="fas fa-tshirt"></i>
                            <p>Iron and board for wrinkle-free clothes</p>
                        </div>
                    </div>
                </div>
                <div class="amenity-card">
                    <div class="card-inner">
                        <div class="card-front">
                            <i class="fas fa-fan"></i>
                            <p>Air Conditioning</p>
                        </div>
                        <div class="card-back">
                            <i class="fas fa-snowflake"></i>
                            <p>Cool comfort in every room</p>
                        </div>
                    </div>
                </div>
                <div class="amenity-card">
                    <div class="card-inner">
                        <div class="card-front">
                            <i class="fas fa-utensils"></i>
                            <p>Fully Equipped Kitchen</p>
                        </div>
                        <div class="card-back">
                            <i class="fas fa-utensils"></i>
                            <p>Cook with modern appliances</p>
                        </div>
                    </div>
                </div>
                <div class="amenity-card">
                    <div class="card-inner">
                        <div class="card-front">
                            <i class="fas fa-car"></i>
                            <p>Free Parking</p>
                        </div>
                        <div class="card-back">
                            <i class="fas fa-car"></i>
                            <p>Spacious parking area</p>
                        </div>
                    </div>
                </div>
                <div class="amenity-card">
                    <div class="card-inner">
                        <div class="card-front">
                            <i class="fas fa-water"></i>
                            <p>Washing Machine</p>
                        </div>
                        <div class="card-back">
                            <i class="fas fa-water"></i>
                            <p>Modern laundry convenience</p>
                        </div>
                    </div>
                </div>
                <div class="amenity-card">
                    <div class="card-inner">
                        <div class="card-front">
                            <i class="fas fa-tint"></i>
                            <p>Water Filter</p>
                        </div>
                        <div class="card-back">
                            <i class="fas fa-tint"></i>
                            <p>Clean and purified water</p>
                        </div>
                    </div>
                </div>
                <div class="amenity-card">
                    <div class="card-inner">
                        <div class="card-front">
                            <i class="fas fa-snowflake"></i>
                            <p>Refrigerator</p>
                        </div>
                        <div class="card-back">
                            <i class="fas fa-snowflake"></i>
                            <p>Keep your food fresh</p>
                        </div>
                    </div>
                </div>
                <div class="amenity-card">
                    <div class="card-inner">
                        <div class="card-front">
                            <i class="fas fa-tv"></i>
                            <p>Television</p>
                        </div>
                        <div class="card-back">
                            <i class="fas fa-tv"></i>
                            <p>Entertainment for all</p>
                        </div>
                    </div>
                </div>
                <div class="amenity-card">
                    <div class="card-inner">
                        <div class="card-front">
                            <i class="fas fa-shower"></i>
                            <p>Water Heater</p>
                        </div>
                        <div class="card-back">
                            <i class="fas fa-shower"></i>
                            <p>Hot showers anytime</p>
                        </div>
                    </div>
                </div>
                <div class="amenity-card">
                    <div class="card-inner">
                        <div class="card-front">
                            <i class="fas fa-leaf"></i>
                            <p> Field View</p>
                        </div>
                        <div class="card-back">
                            <i class="fas fa-leaf"></i>
                            <p>Relax with nature views</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Local Attractions Section -->
    <section class="attractions-section" id="attractions">
        <div class="container">
            <h2>Local Attractions</h2>
            <div class="attractions-grid">
                <div class="attraction-card">
                    <img src="images/boatcruise.jpg" alt="Boat Cruise" loading="lazy">
                    <div class="card-overlay">
                        <h4>Kuala Kurau Boat Cruise</h4>
                        <p>Enjoy a tranquil boat cruise through scenic waterways, ideal for photography and relaxation.</p>
                        <a href="https://www.google.com/maps/search/?api=1&query=Kuala+Kurau,+Perak,+Malaysia" class="map-btn" target="_blank" rel="noopener noreferrer">View on Map</a>
                    </div>
                </div>
                <div class="attraction-card">
                    <img src="images/banpecah.jpg" alt="Fishing Village" loading="lazy">
                    <div class="card-overlay">
                        <h4>Ban Pecah</h4>
                        <p>Discover serene beaches, perfect for healing and relaxation.</p>
                        <a href="https://www.google.com/maps/search/?api=1&query=Ban+Pecah,+Kuala+Kurau,+Perak,+Malaysia" class="map-btn" target="_blank" rel="noopener noreferrer">View on Map</a>
                    </div>
                </div>
                <div class="attraction-card">
                    <img src="images/sawah.jpg" alt="Sawah Padi" loading="lazy">
                    <div class="card-overlay">
                        <h4>Paddy Field</h4>
                        <p>Explore vibrant paddy fields, ideal for scenic tours and relaxation.</p>
                        <a href="https://www.google.com/maps/search/?api=1&query=Paddy+Fields,+Kuala+Kurau,+Perak,+Malaysia" class="map-btn" target="_blank" rel="noopener noreferrer">View on Map</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Guest Activities Section -->
    <section class="activities-section" id="activities">
        <div class="container">
            <h2>Guest Activities</h2>
            <div class="activities-grid">
                <div class="activity-card" data-activity="paddy-visit">
                    <i class="fas fa-tractor"></i>
                    <h4>Paddy Field Visit</h4>
                    <p>Explore the stunning paddy fields of Kuala Kurau at your own pace.</p>
                </div>
                <div class="activity-card" data-activity="cooking-class">
                    <i class="fas fa-place-of-worship"></i>
                    <h4>Hua Seng Kong Buddhist Temple</h4>
                    <p>Visit the serene Hua Seng Kong Buddhist Temple for a cultural experience.</p>
                </div>
                <div class="activity-card" data-activity="fishing-trip">
                    <i class="fas fa-fish"></i>
                    <h4>Fishing Trip</h4>
                    <p>Join a fishing adventure with local fishermen in Kuala Kurau.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Activity Modals -->
    <div class="activity-modal" id="paddy-visit-modal">
        <div class="modal-content">
            <span class="close-modal">✖</span>
            <h4>Paddy Field Visit</h4>
            <p>Explore the stunning paddy fields of Kuala Kurau at your own pace. Discover rice cultivation and enjoy breathtaking views. Ideal for nature lovers and photographers.</p>
            <p><strong>Place:</strong> Kampung Parit Haji Yusof</p>
        </div>
    </div>
    <div class="activity-modal" id="cooking-class-modal">
        <div class="modal-content">
            <span class="close-modal">✖</span>
            <h4>Hua Seng Kong Buddhist Temple</h4>
            <p>Immerse yourself in Malaysian culture by visiting the Hua Seng Kong Buddhist Temple. Enjoy a free visit to this serene and sacred site.</p>
        </div>
    </div>
    <div class="activity-modal" id="fishing-trip-modal">
        <div class="modal-content">
            <span class="close-modal">✖</span>
            <h4>Fishing Trip</h4>
            <p>Experience the life of a Kuala Kurau fisherman. Join a boat trip to catch fresh fish and learn traditional fishing techniques.</p>
            <p><strong>Duration:</strong> 4 hours</p>
            <p><strong>Price:</strong> RM 120 per person</p>
        </div>
    </div>

    <!-- Contact Section -->
    <section class="contact-section" id="contact">
        <div class="container">
            <h3>Get in Touch</h3>
            <p class="lead"><i class="fas fa-phone me-2"></i>01173206136</p>
            <p class="lead"><i class="fas fa-envelope me-2"></i>d.anjungbendang@gmail.com</p>
            <div class="social-icons">
                <a href="https://www.facebook.com/share/1CiEpnKCy9/" aria-label="Facebook" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="https://www.tiktok.com/@d.anjungbendang?_t=ZS-8weLxXH7YlV&_r=1" aria-label="TikTok" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-tiktok"></i>
                </a>
                <a href="https://wa.me/601173206136" aria-label="WhatsApp" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-whatsapp"></i>
                </a>
                <a href="https://www.instagram.com/d.anjungbendang" aria-label="Instagram" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-instagram"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section class="map-section" id="map">
        <div class="container">
            <h2>Find Us Easily</h2>
            <p>Locate D'Anjung Bendang Homestay at Jalan Parit Haji Yusof, Kampung Batu Lapan, Kuala Kurau, Perak, and plan your visit with ease.</p>
            <div class="map-container">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15935.71740517489!2d100.61930567819824!3d5.013835260346186!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x304ac3c4b7b6b3f7%3A0xbec0f0a7e3f6a4a9!2sKuala%20Kurau%2C%20Perak%2C%20Malaysia!5e0!3m2!1sen!2sus!4v1698765432109!5m2!1sen!2sus" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <p>© 2025 D'Anjung Bendang Homestay. All rights reserved.</p>
    </footer>

    <!-- Scroll to Top -->
    <div class="scroll-to-top" onclick="scrollToTop()"><i class="fas fa-chevron-up"></i></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script>
        // Ensure GSAP and ScrollTrigger are available
        if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
            gsap.registerPlugin(ScrollTrigger);
        }

        // Set initial visibility for sections
        document.querySelectorAll('.homestay-details, .attractions-section, .activities-section, .gallery-section, .map-section').forEach(section => {
            section.style.opacity = '1';
            section.style.visibility = 'visible';
            section.style.display = 'block';
        });

        // Animations
        if (typeof gsap !== 'undefined') {
            gsap.from('.navbar', { y: -100, duration: 0.6, ease: 'power2.out' });
            gsap.from('.carousel-overlay', { opacity: 0, y: 30, duration: 0.6, delay: 0.2 });
            gsap.from('.cta-section', { opacity: 0, y: 30, duration: 0.6, scrollTrigger: { trigger: '.cta-section', start: 'top 80%' } });

            // Gallery Animation
            gsap.from('.gallery-section h2', { opacity: 0, y: 20, duration: 0.6, scrollTrigger: { trigger: '.gallery-section', start: 'top 80%' } });
            gsap.from('.gallery-card', { 
                opacity: 0, 
                y: 20, 
                stagger: 0.1, 
                duration: 0.5, 
                ease: 'power2.out', 
                scrollTrigger: { 
                    trigger: '.gallery-grid', 
                    start: 'top 80%',
                    toggleActions: 'play none none none'
                } 
            });

            // Other Section Animations
            gsap.from('.homestay-details h2', { opacity: 0, y: 20, duration: 0.6, scrollTrigger: { trigger: '.homestay-details', start: 'top 80%' } });
            gsap.from('.homestay-details .intro', { opacity: 0, y: 20, duration: 0.6, delay: 0.1, scrollTrigger: { trigger: '.homestay-details', start: 'top 80%' } });
            gsap.from('.detail-card', { 
                opacity: 0, 
                y: 20, 
                stagger: 0.1, 
                duration: 0.5, 
                ease: 'power2.out', 
                scrollTrigger: { trigger: '.details-grid', start: 'top 80%' } 
            });
            gsap.from('.amenity-card', { 
                opacity: 0, 
                y: 20, 
                stagger: 0.1, 
                duration: 0.5, 
                ease: 'power2.out', 
                scrollTrigger: { trigger: '.amenities-grid', start: 'top 80%' } 
            });
            gsap.from('.attractions-section h2', { opacity: 0, y: 20, duration: 0.6, scrollTrigger: { trigger: '.attractions-section', start: 'top 80%' } });
            gsap.from('.attraction-card', { 
                opacity: 0, 
                y: 20, 
                stagger: 0.1, 
                duration: 0.5, 
                ease: 'power2.out', 
                scrollTrigger: { trigger: '.attractions-grid', start: 'top 80%' } 
            });
            gsap.from('.activities-section h2', { opacity: 0, y: 20, duration: 0.6, scrollTrigger: { trigger: '.activities-section', start: 'top 80%' } });
            gsap.from('.activity-card', { 
                opacity: 0, 
                y: 20, 
                stagger: 0.1, 
                duration: 0.5, 
                ease: 'power2.out', 
                scrollTrigger: { trigger: '.activities-grid', start: 'top 80%' } 
            });
            gsap.from('.contact-section', { opacity: 0, y: 20, duration: 0.6, scrollTrigger: { trigger: '.contact-section', start: 'top 80%' } });
            gsap.from('.map-section h2', { opacity: 0, y: 20, duration: 0.6, scrollTrigger: { trigger: '.map-section', start: 'top 80%' } });
            gsap.from('.map-section p', { opacity: 0, y: 20, duration: 0.6, delay: 0.1, scrollTrigger: { trigger: '.map-section', start: 'top 80%' } });
            gsap.from('.map-container', { opacity: 0, y: 20, duration: 0.6, delay: 0.2, scrollTrigger: { trigger: '.map-section', start: 'top 80%' } });
        }

        // Debounced Scroll Handler
        let lastScroll = 0;
        const scrollHandler = () => {
            const currentScroll = window.scrollY;
            const navbar = document.querySelector('.navbar');
            const scrollBtn = document.querySelector('.scroll-to-top');
            navbar.classList.toggle('shrink', currentScroll > 50);
            scrollBtn.classList.toggle('show', currentScroll > 300);
            lastScroll = currentScroll;
        };

        let scrollTimeout;
        window.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(scrollHandler, 50);
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
        const lightboxTitle = document.getElementById('lightboxTitle');
        const lightboxDescription = document.getElementById('lightboxDescription');
        const closeLightbox = document.getElementById('closeLightbox');
        const prevImage = document.getElementById('prevImage');
        const nextImage = document.getElementById('nextImage');
        let currentImageIndex = 0;

        const galleryData = Array.from(galleryCards).map(card => ({
            src: card.querySelector('img').src,
            title: card.querySelector('h5').textContent,
            description: card.querySelector('p').textContent
        }));

        galleryCards.forEach((card, index) => {
            card.querySelector('.view-details').addEventListener('click', (e) => {
                e.preventDefault();
                currentImageIndex = index;
                updateLightbox();
                lightbox.classList.add('show');
                if (typeof gsap !== 'undefined') {
                    gsap.fromTo(lightbox.querySelector('.lightbox-content'), 
                        { opacity: 0, y: 20 },
                        { opacity: 1, y: 0, duration: 0.4, ease: 'power2.out' }
                    );
                }
            });
        });

        function updateLightbox() {
            lightboxImage.src = galleryData[currentImageIndex].src;
            lightboxTitle.textContent = galleryData[currentImageIndex].title;
            lightboxDescription.textContent = galleryData[currentImageIndex].description;
        }

        prevImage.addEventListener('click', () => {
            currentImageIndex = (currentImageIndex - 1 + galleryData.length) % galleryData.length;
            updateLightbox();
        });

        nextImage.addEventListener('click', () => {
            currentImageIndex = (currentImageIndex + 1) % galleryData.length;
            updateLightbox();
        });

        closeLightbox.addEventListener('click', () => {
            if (typeof gsap !== 'undefined') {
                gsap.to(lightbox.querySelector('.lightbox-content'), {
                    opacity: 0,
                    y: 20,
                    duration: 0.3,
                    ease: 'power2.in',
                    onComplete: () => {
                        lightbox.classList.remove('show');
                        lightboxImage.src = '';
                    }
                });
            } else {
                lightbox.classList.remove('show');
                lightboxImage.src = '';
            }
        });

        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox) {
                closeLightbox.click();
            }
        });

        // Keyboard Navigation for Lightbox
        document.addEventListener('keydown', (e) => {
            if (lightbox.classList.contains('show')) {
                if (e.key === 'Escape') {
                    closeLightbox.click();
                } else if (e.key === 'ArrowLeft') {
                    prevImage.click();
                } else if (e.key === 'ArrowRight') {
                    nextImage.click();
                }
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
                    if (typeof gsap !== 'undefined') {
                        gsap.fromTo(modal.querySelector('.modal-content'), 
                            { opacity: 0, y: 20 },
                            { opacity: 1, y: 0, duration: 0.5, ease: 'power2.out' }
                        );
                    }
                }
            });
        });

        closeModals.forEach(close => {
            close.addEventListener('click', () => {
                const modal = close.closest('.activity-modal');
                if (typeof gsap !== 'undefined') {
                    gsap.to(modal.querySelector('.modal-content'), {
                        opacity: 0,
                        y: 20,
                        duration: 0.3,
                        ease: 'power2.in',
                        onComplete: () => {
                            modal.classList.remove('show');
                        }
                    });
                } else {
                    modal.classList.remove('show');
                }
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
            document.querySelectorAll('.homestay-details, .attractions-section, .activities-section, .gallery-section, .map-section').forEach(section => {
                section.style.opacity = '1';
                section.style.visibility = 'visible';
                section.style.display = 'block';
            });
        }, 500);
    </script>
</body>
</html>