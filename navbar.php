<?php
// Clean navbar.php
require_once __DIR__ . '/lang/translate.php';
require_once 'session_check.php';
require_once 'permissions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$user_role = isset($_SESSION["role"]) ? $_SESSION["role"] : null;
$user_full_name = isset($_SESSION["full_name"]) ? htmlspecialchars($_SESSION["full_name"]) : "Guest";
// Extract first name only for logout button
$user_first_name = explode(' ', $user_full_name)[0];

$active_page = basename($_SERVER["PHP_SELF"]);
$logo_path = "images/Logo/Final_Logo White-02.png"; // Cropped white/gold logo for dark navbar

$current_lang = get_lang_code();
$switch_lang = ($current_lang == 'ar') ? 'en' : 'ar';
$switch_lang_text = ($current_lang == 'ar') ? t('switch_language_en') : t('switch_language_ar');

$query_params = $_GET;
$query_params['lang'] = $switch_lang;
$switch_lang_url = basename($_SERVER['PHP_SELF']) . '?' . http_build_query($query_params);

?>
<nav class="navbar">
    <div class="navbar-header">
        <!-- Hamburger Menu Button -->
        <button class="hamburger" id="hamburger-btn" aria-label="Toggle navigation menu" aria-expanded="false">
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </button>
        <div class="logo-container">
            <a href="dashboard.php" class="company-name-link">
                <img src="<?php echo $logo_path; ?>" alt="<?php echo t('broman_real_estate_logo'); ?>" class="logo">
            </a>
        </div>
    </div>
    <ul class="nav-links" id="nav-links">
        <?php if ($is_logged_in): ?>
            <li><a href="dashboard.php" class="<?php echo ($active_page == "dashboard.php") ? "active" : ""; ?>"><?php echo t('dashboard'); ?></a></li>
            <?php if (isset($_SESSION['listing_context'])): ?>
            <li class="dropdown <?php echo (in_array($active_page, ["index_properties.php", "residential_properties.php", "coastal_properties.php", "commercial_properties.php", "edit_property.php"])) ? "active" : ""; ?>">
                <a href="javascript:void(0)" class="dropbtn"><?php echo t('properties'); ?> <span class="arrow">&#9662;</span></a>
                <div class="dropdown-content">
                    <a href="index_properties.php" class="<?php echo ($active_page == "index_properties.php") ? "active-sub" : ""; ?>"><?php echo t('all_properties'); ?></a>
                    <a href="residential_properties.php" class="<?php echo ($active_page == "residential_properties.php") ? "active-sub" : ""; ?>"><?php echo t('residential_properties'); ?></a>
                    <a href="coastal_properties.php" class="<?php echo ($active_page == "coastal_properties.php") ? "active-sub" : ""; ?>"><?php echo t('coastal_properties'); ?></a>
                    <a href="commercial_properties.php" class="<?php echo ($active_page == "commercial_properties.php") ? "active-sub" : ""; ?>"><?php echo t('commercial_administrative_medical_hotel'); ?></a>
                </div>
            </li>
            <?php if (can_manage_properties()): ?>
            <li class="dropdown <?php echo (in_array($active_page, ["add_residential_property.php", "add_coastal_property.php", "add_other_property.php"])) ? "active" : ""; ?>">
                 <a href="javascript:void(0)" class="dropbtn"><?php echo t('add_new_property'); ?> <span class="arrow">&#9662;</span></a>
                 <div class="dropdown-content">
                    <a href="add_residential_property.php" class="<?php echo ($active_page == "add_residential_property.php") ? "active-sub" : ""; ?>"><?php echo t('add_residential_property_title'); ?></a>
                    <a href="add_coastal_property.php" class="<?php echo ($active_page == "add_coastal_property.php") ? "active-sub" : ""; ?>"><?php echo t('add_coastal_property_title'); ?></a>
                    <a href="add_other_property.php" class="<?php echo ($active_page == "add_other_property.php") ? "active-sub" : ""; ?>"><?php echo t('add_other_property_title'); ?></a>
                 </div>
            </li>
            <?php endif; ?>
            <?php if (can_manage_properties()): ?>
                <li><a href="admin_duplicate_properties.php" class="<?php echo ($active_page == "admin_duplicate_properties.php") ? "active" : ""; ?>"><?php echo t('duplicates_review'); ?></a></li>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (can_manage_employees()): ?>
                <li><a href="employees_list.php" class="<?php echo ($active_page == "employees_list.php" || $active_page == "add_employee.php" || $active_page == "edit_employee.php") ? "active" : ""; ?>"><?php echo t('employees'); ?></a></li>
            <?php endif; ?>
      <?php if (is_manager()): ?>
        <li><a href="manager_activity.php" class="<?php echo ($active_page == "manager_activity.php") ? "active" : ""; ?>"><?php echo t('activity_logs') ? t('activity_logs') : (get_lang_code() === 'ar' ? 'سجل النشاط' : 'Activity Log'); ?></a></li>
      <?php endif; ?>
      <!-- Reports link removed as requested -->
            <li>
                <form action="logout.php" method="POST" style="display: inline;">
                    <button type="submit" class="btn-logout"><?php echo sprintf(t('logout_user'), $user_first_name); ?></button>
                </form>
            </li>
        <?php else: ?>
            <li><a href="login.php" class="<?php echo ($active_page == "login.php") ? "active" : ""; ?>"><?php echo t('login'); ?></a></li>
        <?php endif; ?>
         <li><a href="<?php echo htmlspecialchars($switch_lang_url); ?>" class="lang-switch"><?php echo $switch_lang_text; ?></a></li>
    </ul>
</nav>

<style>
/* CSS Reset for full-width navbar - Force Full Viewport Solution */
html, body {
    margin: 0 !important;
    padding: 0 !important;
    overflow-x: hidden; /* Prevent horizontal scroll from 100vw trick */
}

/* Force navbar to break out of any parent container */
nav.navbar {
    width: 100vw !important;
    max-width: 100vw !important;
    margin-left: calc(-50vw + 50%) !important;
    margin-right: calc(-50vw + 50%) !important;
    left: 0 !important;
    right: 0 !important;
    border-radius: 0 !important;
    /* Handle notch/safe-area on modern mobile devices */
    padding-top: max(0.80rem, env(safe-area-inset-top));
    padding-left: max(1rem, env(safe-area-inset-left));
    padding-right: max(1rem, env(safe-area-inset-right));
}

/* Premium Navbar Styles - Ultra Dark Navy with Gold Accents */
.navbar {
    background: linear-gradient(135deg, #020d24 0%, #0a1a3a 100%);
    padding: 0.80rem 2rem;
    margin: 0;
    width: 100vw !important;
    max-width: 100vw !important;
    box-sizing: border-box;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.15);
    position: sticky;
    top: 0;
    left: 0 !important;
    right: 0 !important;
    z-index: 1000;
    border-bottom: 3px solid #b08a0e;
    border-radius: 0 !important;
}

.navbar .logo-container {
    display: flex;
    align-items: center;
}

.navbar .logo-container a {
    display: flex;
    align-items: center;
}

.navbar .logo {
    height: 42px;
    background: transparent;
    padding: 0;
    border-radius: 0;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
    transition: all 0.3s ease;
}

.navbar .logo:hover {
    transform: scale(1.03);
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
}

.navbar ul {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 0.4rem;
    align-items: center;
}

.navbar ul li a {
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    padding: 0.55rem 1rem;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.navbar ul li a:hover,
.navbar ul li a.active {
    color: #b08a0e;
    background: linear-gradient(135deg, rgba(176, 138, 14, 0.15) 0%, rgba(176, 138, 14, 0.08) 100%);
    box-shadow: inset 0 -2px 0 #b08a0e, 0 2px 8px rgba(176, 138, 14, 0.15);
}

.navbar ul li a.active {
    color: #c9a011;
    background: linear-gradient(135deg, rgba(176, 138, 14, 0.2) 0%, rgba(176, 138, 14, 0.1) 100%);
    box-shadow: inset 0 -3px 0 #b08a0e, 0 4px 12px rgba(176, 138, 14, 0.2);
}

.navbar ul li.dropdown {
    position: relative;
    display: inline-block;
}

/* Dropdown parent link styling */
.navbar ul li.dropdown > a.dropbtn {
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    padding: 0.55rem 1rem;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.navbar ul li.dropdown:hover > a.dropbtn,
.navbar ul li.dropdown.active > a.dropbtn {
    color: #b08a0e;
    background: linear-gradient(135deg, rgba(176, 138, 14, 0.15) 0%, rgba(176, 138, 14, 0.08) 100%);
    box-shadow: inset 0 -2px 0 #b08a0e, 0 2px 8px rgba(176, 138, 14, 0.15);
}

.navbar ul li.dropdown .dropdown-content {
    display: none;
    position: absolute;
    background: rgba(255, 255, 255, 0.97);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    min-width: 220px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.18);
    z-index: 1001;
    border-radius: 12px;
    overflow: hidden;
    top: 100%;
    left: 0;
    padding: 0.5rem;
    border: 1px solid rgba(0, 0, 0, 0.08);
    animation: dropdownSlide 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes dropdownSlide {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.navbar ul li.dropdown:hover .dropdown-content {
    display: block;
}

.navbar ul li.dropdown .dropdown-content a {
    color: #2c3e50 !important;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    display: block;
    font-weight: 500;
    white-space: nowrap;
}

.navbar ul li.dropdown .dropdown-content a:hover {
    background: rgba(176, 138, 14, 0.1);
    color: #020d24 !important;
}

.navbar ul li.dropdown .dropdown-content a.active-sub {
    background: rgba(176, 138, 14, 0.15);
    color: #020d24 !important;
    font-weight: 600;
}

/* Arrow for dropdown */
.navbar ul li.dropdown a.dropbtn .arrow {
    display: inline-block;
    margin-left: 5px;
    font-size: 0.75em;
    transition: transform 0.3s ease;
}

.navbar ul li.dropdown:hover a.dropbtn .arrow {
    transform: rotate(180deg);
}

/* Premium Logout Button */
.navbar .btn-logout {
    background: linear-gradient(135deg, #b08a0e 0%, #8a6b08 100%);
    color: #fff;
    border: none;
    padding: 0.55rem 1.25rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 16px rgba(176, 138, 14, 0.25);
}

.navbar .btn-logout:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(176, 138, 14, 0.35);
}

/* Language Switch */
.navbar ul li a.lang-switch {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
    padding: 0.45rem 0.85rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 6px;
}

.navbar ul li a.lang-switch:hover {
    border-color: #b08a0e;
    color: #b08a0e;
    background: rgba(176, 138, 14, 0.08);
}

/* Adjustments for RTL */
html[dir="rtl"] .navbar ul li.dropdown .dropdown-content {
    left: auto;
    right: 0;
}

html[dir="rtl"] .navbar ul li.dropdown .dropdown-content a {
    text-align: right;
}

html[dir="rtl"] .navbar ul li.dropdown a.dropbtn .arrow {
    margin-left: 0;
    margin-right: 5px;
}

/* Navbar Header - Contains logo and hamburger */
.navbar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

/* RTL: Reverse flex direction so hamburger stays on left, logo on right */
html[dir="rtl"] .navbar-header {
    flex-direction: row-reverse;
}

/* Hamburger Button - Hidden on Desktop */
.hamburger {
    display: none;
    flex-direction: column;
    justify-content: space-around;
    width: 30px;
    height: 24px;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0;
    z-index: 10;
    margin-left: 15px; /* Space from edge in RTL */
}

.hamburger .bar {
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, #c9a011 0%, #b08a0e 100%);
    border-radius: 10px;
    transition: all 0.3s ease;
}

/* Hamburger Animation when active */
.hamburger.active .bar:nth-child(1) {
    transform: rotate(45deg) translate(6px, 6px);
}

.hamburger.active .bar:nth-child(2) {
    opacity: 0;
    transform: translateX(-20px);
}

.hamburger.active .bar:nth-child(3) {
    transform: rotate(-45deg) translate(6px, -6px);
}

/* Desktop Styles */
@media (min-width: 993px) {
    .navbar-header {
        width: auto;
    }
    
    .navbar .nav-links {
        display: flex !important;
    }
}

/* Responsive - Tablet and Mobile */
@media (max-width: 992px) {
    .navbar {
        padding: 0.75rem 1rem;
        flex-direction: column;
        gap: 0;
    }
    
    .navbar-header {
        width: 100%;
    }
    
    .hamburger {
        display: flex;
    }
    
    .navbar .nav-links {
        display: none;
        flex-direction: column;
        width: 100%;
        background: linear-gradient(180deg, #0a1a3a 0%, #061328 100%);
        margin-top: 1rem;
        padding: 0.5rem 0;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        max-height: 0;
        transition: max-height 0.4s ease, padding 0.3s ease;
    }
    
    .navbar .nav-links.active {
        display: flex;
        max-height: 600px;
        padding: 1rem 0;
    }
    
    .navbar .nav-links li {
        width: 100%;
        text-align: center;
    }
    
    .navbar ul li a {
        padding: 0.85rem 1.5rem;
        font-size: 1rem;
        border-radius: 0;
        justify-content: center;
    }
    
    .navbar ul li a:hover,
    .navbar ul li a.active {
        background: linear-gradient(90deg, rgba(176, 138, 14, 0.2) 0%, rgba(176, 138, 14, 0.1) 100%);
        box-shadow: inset 4px 0 0 #b08a0e;
    }
    
    html[dir="rtl"] .navbar ul li a:hover,
    html[dir="rtl"] .navbar ul li a.active {
        box-shadow: inset -4px 0 0 #b08a0e;
    }
    
    /* Mobile Dropdown Styles */
    .navbar ul li.dropdown {
        display: block;
        width: 100%;
    }
    
    .navbar ul li.dropdown > a.dropbtn {
        justify-content: center;
        padding: 0.85rem 1.5rem;
        font-size: 1rem;
    }
    
    .navbar ul li.dropdown .dropdown-content {
        position: static;
        display: none;
        width: 100%;
        background: rgba(0, 0, 0, 0.2);
        box-shadow: none;
        border-radius: 0;
        padding: 0;
        animation: none;
        border: none;
        margin: 0;
    }
    
    .navbar ul li.dropdown.open .dropdown-content {
        display: block;
    }
    
    .navbar ul li.dropdown .dropdown-content a {
        color: rgba(255, 255, 255, 0.85) !important;
        padding: 0.75rem 2rem;
        font-size: 0.9rem;
        text-align: center;
        background: transparent;
    }
    
    .navbar ul li.dropdown .dropdown-content a:hover,
    .navbar ul li.dropdown .dropdown-content a.active-sub {
        background: rgba(176, 138, 14, 0.15) !important;
        color: #c9a011 !important;
    }
    
    /* Mobile: Tap to toggle dropdown instead of hover */
    .navbar ul li.dropdown:hover .dropdown-content {
        display: none;
    }
    
    .navbar ul li.dropdown.open .dropdown-content {
        display: block;
    }
    
    /* Arrow rotation for mobile */
    .navbar ul li.dropdown:hover a.dropbtn .arrow {
        transform: none;
    }
    
    .navbar ul li.dropdown.open a.dropbtn .arrow {
        transform: rotate(180deg);
    }
    
    .navbar .logo {
        height: 38px;
    }
    
    /* Logout button mobile style */
    .navbar .btn-logout {
        width: 80%;
        margin: 0.5rem auto;
        display: block;
        text-align: center;
    }
    
    /* Language switch mobile */
    .navbar ul li a.lang-switch {
        margin-top: 0.5rem;
    }
    
    /* ============================================
       GLOBAL MOBILE FIX - Safe Content Margins
       Prevents border/shadow clipping from overflow-x: hidden
       ============================================ */
    .container,
    .container-fluid,
    .main-content,
    .wrapper,
    .premium-container {
        width: calc(100% - 24px) !important;
        margin-left: auto !important;
        margin-right: auto !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        box-sizing: border-box !important;
    }
}

/* Extra small devices */
@media (max-width: 480px) {
    .navbar {
        padding: 0.5rem 0.75rem;
    }
    
    .navbar .logo {
        height: 32px;
    }
    
    .navbar ul li a {
        font-size: 0.95rem;
        padding: 0.75rem 1rem;
    }
}
</style>

<!-- Mobile Navigation JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.getElementById('hamburger-btn');
    const navLinks = document.getElementById('nav-links');
    const dropdowns = document.querySelectorAll('.navbar .dropdown');
    
    // Toggle mobile menu
    if (hamburger && navLinks) {
        hamburger.addEventListener('click', function() {
            hamburger.classList.toggle('active');
            navLinks.classList.toggle('active');
            
            // Update aria-expanded
            const isExpanded = hamburger.classList.contains('active');
            hamburger.setAttribute('aria-expanded', isExpanded);
        });
    }
    
    // Handle dropdowns on mobile (tap to toggle)
    dropdowns.forEach(function(dropdown) {
        const dropbtn = dropdown.querySelector('.dropbtn');
        if (dropbtn) {
            dropbtn.addEventListener('click', function(e) {
                // Only prevent default on mobile
                if (window.innerWidth <= 992) {
                    e.preventDefault();
                    
                    // Close other dropdowns
                    dropdowns.forEach(function(other) {
                        if (other !== dropdown) {
                            other.classList.remove('open');
                        }
                    });
                    
                    // Toggle current dropdown
                    dropdown.classList.toggle('open');
                }
            });
        }
    });
    
    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.navbar')) {
            if (hamburger) hamburger.classList.remove('active');
            if (navLinks) navLinks.classList.remove('active');
            dropdowns.forEach(function(dropdown) {
                dropdown.classList.remove('open');
            });
        }
    });
    
    // Close menu on window resize if going to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            if (hamburger) hamburger.classList.remove('active');
            if (navLinks) navLinks.classList.remove('active');
            dropdowns.forEach(function(dropdown) {
                dropdown.classList.remove('open');
            });
        }
    });
});
</script>

