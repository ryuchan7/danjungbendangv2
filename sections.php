<?php
function displayGallerySection() {
?>
    <!-- Gallery Section -->
    <section class="gallery-section" id="gallery">
        <div class="container">
            <h2>Explore Our Homestay</h2>
            <div class="gallery-grid">
                <div class="gallery-card" data-id="kitchen">
                    <img src="images/kitchen.jpg" alt="Kitchen" loading="lazy">
                    <div class="card-overlay">
                        <h5>Kitchen</h5>
                        <p>Modern appliances for seamless meal preparation.</p>
                    </div>
                </div>
                <div class="gallery-card" data-id="living_room">
                    <img src="images/living_room.jpg" alt="Living Room" loading="lazy">
                    <div class="card-overlay">
                        <h5>Living Room</h5>
                        <p>Elegant space with scenic paddy field views.</p>
                    </div>
                </div>
                <div class="gallery-card" data-id="tv">
                    <img src="images/tv.jpg" alt="TV" loading="lazy">
                    <div class="card-overlay">
                        <h5>Television</h5>
                        <p>Enjoy entertainment with a sleek, high-definition display.</p>
                    </div>
                </div>
                <div class="gallery-card" data-id="bedroom1">
                    <img src="images/bedroom1.jpg" alt="Bedroom 1" loading="lazy">
                    <div class="card-overlay">
                        <h5>Bedroom 1</h5>
                        <p>Luxurious double bed for ultimate comfort.</p>
                    </div>
                </div>
                <div class="gallery-card" data-id="bedroom2">
                    <img src="images/bedroom2.jpg" alt="Bedroom 2" loading="lazy">
                    <div class="card-overlay">
                        <h5>Bedroom 2</h5>
                        <p>Twin beds in a soothing environment.</p>
                    </div>
                </div>
                <div class="gallery-card" data-id="bedroom3">
                    <img src="images/bedroom3.jpg" alt="Bedroom 3" loading="lazy">
                    <div class="card-overlay">
                        <h5>Bedroom 3</h5>
                        <p>Twin beds in a soothing environment.</p>
                    </div>
                </div>
                <div class="gallery-card" data-id="bedroom4">
                    <img src="images/bedroom4.jpg" alt="Bedroom 4" loading="lazy">
                    <div class="card-overlay">
                        <h5>Bedroom 4</h5>
                        <p>Twin beds in a soothing environment.</p>
                    </div>
                </div>
                <div class="gallery-card" data-id="bathroom">
                    <img src="images/bathroom.jpg" alt="Bathroom" loading="lazy">
                    <div class="card-overlay">
                        <h5>Bathroom</h5>
                        <p>Sleek and spotless with premium amenities.</p>
                    </div>
                </div>
                <div class="gallery-card" data-id="bathroom2">
                    <img src="images/bathroom2.jpg" alt="Bathroom 2" loading="lazy">
                    <div class="card-overlay">
                        <h5>Bathroom 2</h5>
                        <p>Sleek and spotless with premium amenities.</p>
                    </div>
                </div>
                <div class="gallery-card" data-id="bathroom3">
                    <img src="images/bathroom3.jpg" alt="Bathroom 3" loading="lazy">
                    <div class="card-overlay">
                        <h5>Bathroom 3</h5>
                        <p>Sleek and spotless with premium amenities.</p>
                    </div>
                </div>
                <div class="gallery-card" data-id="patio">
                    <img src="images/patio.jpg" alt="Patio" loading="lazy">
                    <div class="card-overlay">
                        <h5>Patio</h5>
                        <p>Relax with stunning views of lush landscapes.</p>
                    </div>
                </div>
                <div class="gallery-card" data-id="washing-machine">
                    <img src="images/washingmachine.jpg" alt="Washing Machine" loading="lazy">
                    <div class="card-overlay">
                        <h5>Washing Machine</h5>
                        <p>Keep your clothes fresh with modern, efficient cleaning.</p>
                    </div>
                </div>
                <div class="gallery-card" data-id="table">
                    <img src="images/table.jpg" alt="Table" loading="lazy">
                    <div class="card-overlay">
                        <h5>Table</h5>
                        <p>Enjoy meals with elegant and comfortable seating.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php
}

function displayActivitiesSection() {
?>
    <!-- Guest Activities Section -->
    <section class="activities-section" id="activities">
        <div class="container">
            <h2>Guest Activities</h2>
            <div class="activities-grid">
                <div class="activity-card" data-activity="paddy-tour">
                    <i class="fas fa-tractor"></i>
                    <h4>Paddy Field Tour</h4>
                    <p>Guided tour through the lush paddy fields with local farmers.</p>
                </div>
                <div class="activity-card" data-activity="cooking-class">
                    <i class="fas fa-utensils"></i>
                    <h4>Malay Cooking Class</h4>
                    <p>Learn to cook authentic Malaysian dishes with local ingredients.</p>
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
    <div class="activity-modal" id="paddy-tour-modal">
        <div class="modal-content">
            <span class="close-modal">×</span>
            <h4>Paddy Field Tour</h4>
            <p>Join our guided tour through the stunning paddy fields of Kuala Kurau. Learn about rice cultivation from local farmers and enjoy breathtaking views. Perfect for nature lovers and photographers.</p>
            <p><strong>Duration:</strong> 2 hours</p>
            <p><strong>Price:</strong> RM 50 per person</p>
        </div>
    </div>
    <div class="activity-modal" id="cooking-class-modal">
        <div class="modal-content">
            <span class="close-modal">×</span>
            <h4>Malay Cooking Class</h4>
            <p>Immerse yourself in Malaysian culture by learning to cook traditional dishes. Our hands-on class includes a market visit to source fresh ingredients.</p>
            <p><strong>Duration:</strong> 3 hours</p>
            <p><strong>Price:</strong> RM 80 per person</p>
        </div>
    </div>
    <div class="activity-modal" id="fishing-trip-modal">
        <div class="modal-content">
            <span class="close-modal">×</span>
            <h4>Fishing Trip</h4>
            <p>Experience the life of a Kuala Kurau fisherman. Join a boat trip to catch fresh fish and learn traditional fishing techniques.</p>
            <p><strong>Duration:</strong> 4 hours</p>
            <p><strong>Price:</strong> RM 120 per person</p>
        </div>
    </div>
<?php
}
?>