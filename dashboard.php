<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'header.php';

// Array of image URLs (replace with your actual image paths or dynamic source)
$imageUrls = [
    'photos/photo1.png',    // Image 1: statistiques et analytics
    'photos/photos2.png',          // Image 2: publication sur l'Italie
    'photos/phtos3.png',        // Image 3: publication LuxePop
];

// Function to select a random image
function getRandomImage($urls) {
    $randomIndex = array_rand($urls);
    return $urls[$randomIndex];
}

// Get a random image for display
$randomImageUrl = getRandomImage($imageUrls);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>MultiPost - Tableau de bord</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
    :root {
        --primary-color: #168983;
        --secondary-color: #ffc107;
        --dark-color: #333;
        --light-color: #f8f9fc;
        --text-color: #4a4a4a;
    }

    body {
        background-color: #f8f9fc;
        font-family: 'Poppins', sans-serif;
        color: var(--text-color);
        overflow-x: hidden; /* Prevent horizontal scrollbar */
    }

    /* Sidebar styling */
    .sidebar {
        width: 250px;
        background-color: white;
        height: 100vh;
        position: fixed;
        left: -250px; /* Commence caché */
        top: 0;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(9, 9, 9, 0.15);
        z-index: 1000;
        overflow-y: auto;
        transition: all 0.3s ease;
    }

    .sidebar.open {
        left: 0; /* Visible quand ouvert */
    }

    .sidebar-logo {
        padding: 1.5rem 1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--primary-color);
        color: white;
        font-weight: 600;
        font-size: 1.2rem;
    }

    .sidebar-menu {
        padding: 1rem 0;
    }

    .sidebar-item {
        padding: 0.75rem 1.5rem;
        display: flex;
        align-items: center;
        color: var(--text-color);
        text-decoration: none;
        transition: all 0.3s;
        font-weight: 500;
        border-left: 3px solid transparent;
    }

    .sidebar-item:hover {
        color: var(--primary-color);
        background-color: rgba(0, 200, 179, 0.05);
    }

    .sidebar-item i {
        margin-right: 15px;
        width: 20px;
        text-align: center;
        font-size: 1.1rem;
    }

    .sidebar-item.active {
        color: var(--primary-color);
        border-left: 3px solid var(--primary-color);
        background-color: rgba(0, 200, 179, 0.1);
    }

    /* Create button */
    .create-btn {
        margin: 1.5rem;
        padding: 0.75rem;
        background-color: var(--primary-color);
        color: white;
        border: none;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 600;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.1);
    }

    .create-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    .create-btn i {
        margin-right: 8px;
        font-size: 1.1rem;
    }

    /* Content area */
    .content-area {
        padding: 1rem;
        transition: all 0.3s;
    }

    /* Menu icon in top left */
    .menu-bar {
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 900;
        display: flex;
        align-items: center;
        background-color: white;
        border-radius: 10px;
        padding: 10px;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .menu-icon {
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text-color);
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.2s;
    }

    .menu-icon:hover {
        background-color: rgba(0, 0, 0, 0.05);
    }

    /* Header with menu icon */
    .header {
        display: flex;
        align-items: center;
        padding: 1rem;
        background-color: white;
        margin-bottom: 1.5rem; /* Increased margin for more space */
        border-radius: 10px;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .header-content {
        flex-grow: 1;
    }

    .header-title {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--dark-color);
    }

    .header-subtitle {
        font-size: 0.9rem;
        color: #6c757d;
    }

    /* User profile icon */
    .user-profile {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1.2rem;
    }

    /* Dynamic Image Area - Diaporama */
    .dynamic-image-area {
        background-color: white;
        border-radius: 10px;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        margin-bottom: 0.2rem;
        overflow: hidden; /* Clip image to rounded corners */
        position: relative;
        height: 550px; /* Increased height for larger images */
    }

    .slideshow-container {
        width: 100%;
        height: 100%;
        position: relative;
    }

    .slideshow-slide {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        transition: opacity 1.5s ease-in-out;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .slideshow-slide.active {
        opacity: 1;
    }

    .dynamic-image {
        width: 100%;
        height: 100%;
        object-fit: contain; /* Changed to contain to show full images without cropping */
        border-radius: 10px; /* Apply rounded corners to the image as well */
        max-width: 90%; /* Prevent image from stretching too much */
        max-height: 90%; /* Prevent image from stretching too much */
    }

    /* Slideshow controls */
    .slideshow-controls {
        position: absolute;
        bottom: 15px;
        left: 0;
        right: 0;
        display: flex;
        justify-content: center;
        gap: 8px;
    }

    .slideshow-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.5);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .slideshow-dot.active {
        background-color: var(--primary-color);
        transform: scale(1.2);
    }

    /* Action Cards */
    .action-card {
        background-color: white;
        border-radius: 10px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        display: flex;
        align-items: center;
    }

    .action-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
    }

    .action-icon.primary {
        background-color: rgba(0, 200, 179, 0.1);
        color: var(--primary-color);
    }

    .action-icon.secondary {
        background-color: rgba(255, 193, 7, 0.1);
        color: var(--secondary-color);
    }

    .action-content {
        flex-grow: 1;
    }

    .action-title {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .action-description {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 0.75rem;
    }

    .action-button {
        padding: 0.5rem 1rem;
        border-radius: 5px;
        font-weight: 800;
        font-size: 0.85rem;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s;
    }

    .action-button.primary {
        background-color: var(--primary-color);
        color: white;
    }

    .action-button.primary:hover {
        background-color: #00b0a0;
    }

    .action-button.secondary {
        background-color: var(--secondary-color);
        color: white;
    }

    .action-button.secondary:hover {
        background-color: #e5ac00;
    }

    .action-button.outline {
        border: 1px solid #dee2e6;
        color: #6c757d;
        background-color: transparent;
        margin-left: 0.5rem;
    }

    .action-button.outline:hover {
        background-color: #f8f9fa;
    }

    /* Draft ideas section */
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .section-title {
        font-weight: 600;
        color: var(--dark-color);
    }

    .view-all {
        font-size: 0.85rem;
        color: var(--primary-color);
        text-decoration: none;
    }

    .drafts-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
    }

    .draft-card {
        background-color: white;
        border-radius: 10px;
        padding: 1rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .draft-header {
        display: flex;
        align-items: center;
        margin-bottom: 0.75rem;
    }

    .draft-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background-color: #1e6641;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        margin-right: 0.5rem;
    }

    .draft-author {
        font-weight: 500;
        font-size: 0.85rem;
        flex-grow: 1;
    }

    .draft-icon {
        color: #6c757d;
        font-size: 0.85rem;
    }

    .draft-title {
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .draft-text {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 0.5rem;
    }

    /* Mobile overlay */
    .overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 999;
        display: none;
    }

    .overlay.active {
        display: block;
    }

    /* Responsive */
    @media (min-width: 992px) {
        .content-area {
            padding: 2rem;
            padding-top: 80px; /* Add padding to top to account for fixed menu bar */
        }

        .drafts-container {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 991px) {
        .content-area {
            padding-top: 80px; /* Add padding to top to account for fixed menu bar */
        }
    }
</style>
</head>
<body>
    <div class="overlay" id="overlay"></div>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <span>MultiPost</span>
        </div>

        <div class="create-btn" id="createPostBtn">
            <i class="bi bi-plus-circle"></i>
            <span>Créer</span>
        </div>

        <div class="sidebar-menu">
            <a href="dashboard.php" class="sidebar-item active">
                <i class="bi bi-house-door"></i>
                <span>Accueil</span>
            </a>


            <a href="media.php" class="sidebar-item">
                <i class="bi bi-card-image"></i>
                <span>Médias</span>
            </a>

            <a href="explore.php" class="sidebar-item">
                <i class="bi bi-compass"></i>
                <span>Explorer</span>
            </a>


            <a href="accounts.php" class="sidebar-item">
                <i class="bi bi-globe"></i>
                <span>Comptes sociaux</span>
            </a>


            <a href="settings.php" class="sidebar-item">
                <i class="bi bi-gear"></i>
                <span>Paramètres</span>
            </a>


        </div>
    </div>

    <div class="menu-bar">
        <div class="menu-icon" onclick="toggleMenu()">
            <i class="bi bi-list"></i>
        </div>

    </div>

    <div class="content-area" id="mainContent">
        <div class="header">
            <div class="header-content">
                <h1 class="header-title">Bonjour, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Utilisateur'); ?>!</h1>
                <p class="header-subtitle">Aujourd'hui est le jour parfait pour développer votre présence sur les médias sociaux.</p>
            </div>

            <div class="user-profile">
                <?php echo substr($_SESSION['username'] ?? 'U', 0, 1); ?>
            </div>
        </div>

        <div class="dynamic-image-area">
            <div class="slideshow-container">
                <?php foreach ($imageUrls as $index => $url): ?>
                <div class="slideshow-slide <?php echo ($index === 0) ? 'active' : ''; ?>">
                    <img src="<?php echo $url; ?>" alt="Image du diaporama <?php echo $index + 1; ?>" class="dynamic-image">
                </div>
                <?php endforeach; ?>
                
                <div class="slideshow-controls">
                    <?php foreach ($imageUrls as $index => $url): ?>
                    <div class="slideshow-dot <?php echo ($index === 0) ? 'active' : ''; ?>" data-index="<?php echo $index; ?>"></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        

        <div class="section-header mt-4">
            <h2 class="section-title">Idées de brouillons</h2>
        </div>

        <div class="drafts-container">
            <div class="draft-card">
                <div class="draft-header">
                    <div class="draft-avatar">
                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="draft-author"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Utilisateur'); ?></div>
                    <div class="draft-icon"><i class="bi bi-info-circle"></i></div>
                </div>
                <h4 class="draft-title">Ne manquez jamais une étincelle de génie des médias sociaux !</h4>
                <p class="draft-text">#DraftIdeas est votre guichet unique pour capturer vos meilleures idées de contenu.</p>
            </div>

            <div class="draft-card">
                <div class="draft-header">
                    <div class="draft-avatar">
                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="draft-author"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Utilisateur'); ?></div>
                    <div class="draft-icon"><i class="bi bi-rocket"></i></div>
                </div>
                <h4 class="draft-title">Vous avez une idée à partager avec l'équipe ?</h4>
                <p class="draft-text">Assurez-vous d'enregistrer les Draft Ideas visibles pour les autres membres afin d'obtenir des commentaires.</p>
            </div>

            <div class="draft-card">
                <div class="draft-header">
                    <div class="draft-avatar">
                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="draft-author"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Utilisateur'); ?></div>
                    <div class="draft-icon"><i class="bi bi-robot"></i></div>
                </div>
                <h4 class="draft-title">Obtenez un peu d'aide de l'IA pour vos brouillons</h4>
                <p class="draft-text">Cliquez sur le bouton AI Assist pour générer des idées de contenu captivantes.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle sidebar
        function toggleMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');

            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        }

        // Close sidebar when clicking on overlay
        document.getElementById('overlay').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('open');
            this.classList.remove('active');
        });

        // Add click event for create post button
        document.getElementById('createPostBtn').addEventListener('click', function() {
            window.location.href = 'create_post.php';
        });

        // Slideshow functionality
        document.addEventListener('DOMContentLoaded', function() {
            const slides = document.querySelectorAll('.slideshow-slide');
            const dots = document.querySelectorAll('.slideshow-dot');
            let currentSlide = 0;
            let slideInterval;

            // Function to show a specific slide
            function showSlide(index) {
                // Remove active class from all slides and dots
                slides.forEach(slide => slide.classList.remove('active'));
                dots.forEach(dot => dot.classList.remove('active'));
                
                // Add active class to current slide and dot
                slides[index].classList.add('active');
                dots[index].classList.add('active');
                
                currentSlide = index;
            }

            // Function to show the next slide
            function nextSlide() {
                currentSlide = (currentSlide + 1) % slides.length;
                showSlide(currentSlide);
            }

            // Start automatic slideshow
            function startSlideshow() {
                slideInterval = setInterval(nextSlide, 7000); // Change slide every 7 seconds (increased from 5)
            }

            // Add click events to dots
            dots.forEach(dot => {
                dot.addEventListener('click', function() {
                    const slideIndex = parseInt(this.getAttribute('data-index'));
                    showSlide(slideIndex);
                    
                    // Reset the interval when manually changing slides
                    clearInterval(slideInterval);
                    startSlideshow();
                });
            });

            // Add swipe functionality for mobile
            let touchStartX = 0;
            let touchEndX = 0;
            
            const slideshowContainer = document.querySelector('.slideshow-container');
            
            slideshowContainer.addEventListener('touchstart', e => {
                touchStartX = e.changedTouches[0].screenX;
            });
            
            slideshowContainer.addEventListener('touchend', e => {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            });
            
            function handleSwipe() {
                // Swipe left (next slide)
                if (touchEndX < touchStartX - 50) {
                    nextSlide();
                    clearInterval(slideInterval);
                    startSlideshow();
                }
                
                // Swipe right (previous slide)
                if (touchEndX > touchStartX + 50) {
                    currentSlide = (currentSlide - 1 + slides.length) % slides.length;
                    showSlide(currentSlide);
                    clearInterval(slideInterval);
                    startSlideshow();
                }
            }

            // Start the slideshow
            startSlideshow();
        });
    </script>
</body>
</html>