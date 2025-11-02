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

$active_page = basename($_SERVER["PHP_SELF"]);
$logo_path = "images/broman_logo.png";

$current_lang = get_lang_code();
$switch_lang = ($current_lang == 'ar') ? 'en' : 'ar';
$switch_lang_text = ($current_lang == 'ar') ? t('switch_language_en') : t('switch_language_ar');

$query_params = $_GET;
$query_params['lang'] = $switch_lang;
$switch_lang_url = basename($_SERVER['PHP_SELF']) . '?' . http_build_query($query_params);

?>
<nav class="navbar">
    <div class="logo-container">
        <a href="dashboard.php" class="company-name-link">
            <img src="<?php echo $logo_path; ?>" alt="<?php echo t('broman_real_estate_logo'); ?>" class="logo">
        </a>
    </div>
    <ul>
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
                    <button type="submit" class="btn-logout"><?php echo sprintf(t('logout_user'), $user_full_name); ?></button>
                </form>
            </li>
        <?php else: ?>
            <li><a href="login.php" class="<?php echo ($active_page == "login.php") ? "active" : ""; ?>"><?php echo t('login'); ?></a></li>
        <?php endif; ?>
         <li><a href="<?php echo htmlspecialchars($switch_lang_url); ?>" class="lang-switch"><?php echo $switch_lang_text; ?></a></li>
    </ul>
</nav>

<style>
.navbar ul li.dropdown {
  position: relative;
  display: inline-block;
}

.navbar ul li.dropdown .dropdown-content {
  display: none;
  position: absolute;
  background-color: #f9f9f9;
  min-width: 200px; /* Adjust as needed */
  box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
  z-index: 1;
  border-radius: 4px;
  overflow: hidden; /* Ensures border-radius applies to children */
  top: 100%; /* Position below the parent */
  left: 0;
}

.navbar ul li.dropdown:hover .dropdown-content {
  display: block;
}

.navbar ul li.dropdown .dropdown-content a {
  color: black;
  padding: 12px 16px;
  text-decoration: none;
  display: block;
  text-align: left; /* Adjust if using RTL */
  white-space: nowrap;
}

.navbar ul li.dropdown .dropdown-content a:hover {
  background-color: #f1f1f1;
}

.navbar ul li.dropdown .dropdown-content a.active-sub {
  background-color: #ddd; /* Style for active sub-item */
  font-weight: bold;
}

/* Arrow for dropdown */
.navbar ul li.dropdown a.dropbtn .arrow {
  display: inline-block;
  margin-left: 5px; /* Adjust spacing */
  font-size: 0.8em;
  transition: transform 0.3s ease;
}

.navbar ul li.dropdown:hover a.dropbtn .arrow {
  transform: rotate(180deg);
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

</style>
</style>

