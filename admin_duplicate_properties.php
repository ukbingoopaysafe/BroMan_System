<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/lang/translate.php';
require_once __DIR__ . '/session_check.php';
require_once __DIR__ . '/property_image_helper.php';
require_once __DIR__ . '/security.php';

if (!is_logged_in() || !can_manage_properties()) {
    $_SESSION["error_message"] = t('not_authorized');
    header("Location: dashboard.php");
    exit;
}

function normalize_phone_for_query($phone) {
    if ($phone === null) return null;
    $s = trim($phone);
    if ($s === '') return '';
    // unify egypt code +20/0020 to 0
    if (strpos($s, '+20') === 0) {
        $s = '0' . substr($s, 3);
    } elseif (strpos($s, '0020') === 0) {
        $s = '0' . substr($s, 4);
    }
    // remove symbols and spaces
    $s = str_replace(['+', ' ', '-', '(', ')'], '', $s);
    return $s;
}

$function_translate_defined = function_exists('t');
function translate_roles_csv($csv)
{
    if ($csv === null || $csv === '') return '';
    $parts = array_map('trim', explode(',', $csv));
    $translated = [];
    foreach ($parts as $p) {
        switch ($p) {
            case 'seller':
                $translated[] = function_exists('t') ? t('seller') : 'Seller';
                break;
            case 'sales_manager':
                $translated[] = function_exists('t') ? t('sales_manager') : 'Sales Manager';
                break;
            case 'salesperson':
                $translated[] = function_exists('t') ? t('salesperson') : 'Salesperson';
                break;
            default:
                $translated[] = $p;
        }
    }
    $sep = (function_exists('get_direction') && get_direction() === 'rtl') ? '، ' : ', ';
    return implode($sep, $translated);
}

function render_role_badges($csv)
{
    if ($csv === null || $csv === '') return '';
    $parts = array_map('trim', explode(',', $csv));
    $badges = [];
    foreach ($parts as $p) {
        $label = $p;
        $cls = 'role-badge-neutral';
        if ($p === 'seller') { $label = function_exists('t') ? t('seller') : 'Seller'; $cls = 'role-badge-seller'; }
        elseif ($p === 'sales_manager') { $label = function_exists('t') ? t('sales_manager') : 'Sales Manager'; $cls = 'role-badge-manager'; }
        elseif ($p === 'salesperson') { $label = function_exists('t') ? t('salesperson') : 'Salesperson'; $cls = 'role-badge-sales'; }
        $badges[] = '<span class="role-badge ' . $cls . '">' . htmlspecialchars($label) . '</span>';
    }
    return implode(' ', $badges);
}

function render_employees_badges($employees_data, $property_ids_csv, $conn)
{
    if ($employees_data === null || $employees_data === '') return '<span class="summary-empty">-</span>';
    $parts = explode('||', $employees_data);
    $employee_map = [];
    foreach ($parts as $part) {
        if (strpos($part, '|') === false) continue;
        list($name, $emp_id) = explode('|', $part, 2);
        $emp_id = (int)$emp_id;
        $name = trim($name);
        if ($name === '' || $name === 'Unknown') $name = function_exists('t') ? t('unknown') : 'Unknown';
        if (!isset($employee_map[$emp_id])) {
            $employee_map[$emp_id] = ['name' => $name, 'count' => 0];
        }
    }
    // Count properties per employee
    if (!empty($employee_map) && $property_ids_csv) {
        $ids = array_filter(array_map('intval', explode(',', $property_ids_csv)));
        if (!empty($ids)) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $sql = "SELECT EmployeeID, COUNT(*) AS cnt FROM properties WHERE PropertyID IN ($in) GROUP BY EmployeeID";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $eid = (int)$row['EmployeeID'];
                if (isset($employee_map[$eid])) {
                    $employee_map[$eid]['count'] = (int)$row['cnt'];
                } elseif ($eid === 0 || $row['EmployeeID'] === null) {
                    // Count NULL/0 employees
                    if (isset($employee_map[0])) {
                        $employee_map[0]['count'] += (int)$row['cnt'];
                    }
                }
            }
            $stmt->close();
        }
    }
    $badges = [];
    foreach ($employee_map as $emp) {
        $text = htmlspecialchars($emp['name']);
        if ($emp['count'] > 0) {
            $text .= ' (' . $emp['count'] . ')';
        }
        $badges[] = '<span class="employee-badge">' . $text . '</span>';
    }
    return !empty($badges) ? implode(' ', $badges) : '<span class="summary-empty">-</span>';
}

function render_listing_types_badges($csv)
{
    if ($csv === null || $csv === '') return '<span class="summary-empty">-</span>';
    $parts = array_filter(array_map('trim', explode(',', $csv)));
    if (empty($parts)) return '<span class="summary-empty">-</span>';
    $badges = [];
    foreach ($parts as $lt) {
        $cls = strtolower($lt) === 'resale' ? 'type-badge-resale' : 'type-badge-primary';
        $badges[] = '<span class="type-badge ' . $cls . '">' . htmlspecialchars($lt) . '</span>';
    }
    return implode(' ', $badges);
}

function render_locations_badges($csv)
{
    if ($csv === null || $csv === '') return '<span class="summary-empty">-</span>';
    $parts = array_filter(array_map('trim', explode('||', $csv)));
    if (empty($parts)) return '<span class="summary-empty">-</span>';
    $badges = [];
    foreach ($parts as $loc) {
        if (trim($loc) !== '') {
            $badges[] = '<span class="location-badge">' . htmlspecialchars($loc) . '</span>';
        }
    }
    return !empty($badges) ? implode(' ', $badges) : '<span class="summary-empty">-</span>';
}

$active_phone = isset($_GET['phone']) ? normalize_phone_for_query($_GET['phone']) : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Filter by status: 'all', 'needs_review', 'reviewed'
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';
if (!in_array($status_filter, ['all', 'needs_review', 'reviewed'])) {
    $status_filter = 'all';
}

// Sort options: 'status', 'properties_count', 'date', with direction 'asc' or 'desc'
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'properties_count';
if (!in_array($sort_by, ['status', 'properties_count', 'date'])) {
    $sort_by = 'properties_count';
}
$sort_dir = isset($_GET['sort_dir']) ? strtoupper($_GET['sort_dir']) : 'DESC';
if (!in_array($sort_dir, ['ASC', 'DESC'])) {
    $sort_dir = 'DESC';
}

$groups = [];
$total_rows = 0;

if ($active_phone) {
    // Fetch single group details with review status
    $sql = "SELECT g.normalized_phone, g.properties_count, g.property_ids, g.phone_roles,
                   MIN(p.duplicate_status) AS min_status,
                   MAX(p.duplicate_status) AS max_status,
                   SUM(CASE WHEN p.duplicate_status = 1 THEN 1 ELSE 0 END) AS needs_review_count,
                   SUM(CASE WHEN p.duplicate_status = 2 THEN 1 ELSE 0 END) AS reviewed_count
            FROM v_duplicate_property_phone_groups g
            JOIN v_property_phones_normalized v ON v.normalized_phone = g.normalized_phone
            JOIN properties p ON p.PropertyID = v.PropertyID
            WHERE g.normalized_phone = ?
            GROUP BY g.normalized_phone, g.properties_count, g.property_ids, g.phone_roles";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('s', $active_phone);
        $stmt->execute();
        $res = $stmt->get_result();
        $group = $res->fetch_assoc();
        if ($group) {
            $groups[] = $group;
            $ids_csv = $group['property_ids'];
            $ids = array_filter(array_map('intval', explode(',', $ids_csv)));
            $properties = [];
            if (!empty($ids)) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $types = str_repeat('i', count($ids));
                $sql2 = "SELECT p.PropertyID, p.Title, p.Location, p.listing_type, p.seller_name, p.seller_phone, 
                                p.sales_manager_name, p.sales_manager_phone, p.salesperson_name, p.salesperson_phone, 
                                p.DateAdded, p.EmployeeID, p.price_cash, p.price_installments, p.meter_price_cash,
                                e.FullName AS employee_name
                         FROM properties p
                         LEFT JOIN employees e ON p.EmployeeID = e.EmployeeID
                         WHERE p.PropertyID IN ($in) ORDER BY p.PropertyID DESC";
                $stmt2 = $conn->prepare($sql2);
                $stmt2->bind_param($types, ...$ids);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                while ($row = $res2->fetch_assoc()) {
                    // Get property image
                    $row['image_path'] = getPropertyCardImage($row['PropertyID'], $conn);
                    $properties[] = $row;
                }
                $stmt2->close();
            }
        }
        $stmt->close();
    }
} else {
    // Build WHERE clause for status filter
    $where_clause = "";
    $having_clause = "";
    if ($status_filter === 'needs_review') {
        $having_clause = " HAVING SUM(CASE WHEN p.duplicate_status = 1 THEN 1 ELSE 0 END) > 0";
    } elseif ($status_filter === 'reviewed') {
        $having_clause = " HAVING SUM(CASE WHEN p.duplicate_status = 2 THEN 1 ELSE 0 END) = g.properties_count AND g.properties_count > 0";
    }
    
    // Build ORDER BY clause
    $order_clause = "";
    switch ($sort_by) {
        case 'status':
            // Order by: needs_review first (has status 1), then reviewed (all status 2)
            $order_clause = "ORDER BY 
                CASE 
                    WHEN SUM(CASE WHEN p.duplicate_status = 1 THEN 1 ELSE 0 END) > 0 THEN 1 
                    WHEN SUM(CASE WHEN p.duplicate_status = 2 THEN 1 ELSE 0 END) = g.properties_count THEN 2 
                    ELSE 3 
                END " . $sort_dir . ", 
                g.properties_count DESC, 
                g.normalized_phone ASC";
            break;
        case 'properties_count':
            $order_clause = "ORDER BY g.properties_count " . $sort_dir . ", g.normalized_phone ASC";
            break;
        case 'date':
            $order_clause = "ORDER BY MAX(p.DateAdded) " . $sort_dir . ", g.normalized_phone ASC";
            break;
        default:
            $order_clause = "ORDER BY g.properties_count DESC, g.normalized_phone ASC";
    }
    
    // Count total groups with filter
    $count_sql = "SELECT COUNT(*) AS c
                  FROM (
                      SELECT g.normalized_phone
                      FROM v_duplicate_property_phone_groups g
                      JOIN v_property_phones_normalized v ON v.normalized_phone = g.normalized_phone
                      JOIN properties p ON p.PropertyID = v.PropertyID
                      GROUP BY g.normalized_phone, g.properties_count, g.property_ids, g.phone_roles
                      $having_clause
                  ) AS filtered_groups";
    $count_res = $conn->query($count_sql);
    if ($count_res) {
        $total_rows = (int)$count_res->fetch_assoc()['c'];
    }
    
    // Factual summary: employees, listing types, locations, date range, review status
    $sql = "SELECT g.normalized_phone,
                   g.properties_count,
                   g.property_ids,
                   g.phone_roles,
                   MIN(p.DateAdded) AS first_added,
                   MAX(p.DateAdded) AS last_added,
                   GROUP_CONCAT(DISTINCT p.listing_type ORDER BY p.listing_type SEPARATOR ',') AS listing_types,
                   GROUP_CONCAT(DISTINCT CONCAT(IFNULL(e.FullName, 'Unknown'), '|', IFNULL(p.EmployeeID, 0)) SEPARATOR '||') AS employees_data,
                   GROUP_CONCAT(DISTINCT IFNULL(p.Location, CONCAT(IFNULL(p.governorate,''), IF(p.governorate IS NOT NULL AND p.district IS NOT NULL, ' - ', ''), IFNULL(p.district,''))) ORDER BY IFNULL(p.Location, CONCAT(IFNULL(p.governorate,''), IF(p.governorate IS NOT NULL AND p.district IS NOT NULL, ' - ', ''), IFNULL(p.district,''))) SEPARATOR '||') AS distinct_locations,
                   MIN(p.duplicate_status) AS min_status,
                   MAX(p.duplicate_status) AS max_status,
                   SUM(CASE WHEN p.duplicate_status = 1 THEN 1 ELSE 0 END) AS needs_review_count,
                   SUM(CASE WHEN p.duplicate_status = 2 THEN 1 ELSE 0 END) AS reviewed_count
            FROM v_duplicate_property_phone_groups g
            JOIN v_property_phones_normalized v ON v.normalized_phone = g.normalized_phone
            JOIN properties p ON p.PropertyID = v.PropertyID
            LEFT JOIN employees e ON p.EmployeeID = e.EmployeeID
            GROUP BY g.normalized_phone, g.properties_count, g.property_ids, g.phone_roles
            $having_clause
            $order_clause
            LIMIT ? OFFSET ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ii', $per_page, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $groups[] = $row;
        }
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="<?php echo get_lang_code(); ?>" dir="<?php echo get_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('duplicate_properties_review_title'); ?></title>
    <link rel="icon" href="images/Logo%20Broman.svg" type="image/svg+xml">
    <link rel="shortcut icon" href="images/Logo%20Broman.svg">
    <link rel="apple-touch-icon" href="images/Logo%20Broman.svg">
    <link rel="stylesheet" href="modern_styles.css">
    <?php if (get_direction() === 'rtl'): ?>
        <link rel="stylesheet" href="rtl_styles.css">
    <?php endif; ?>
    <link rel="stylesheet" href="enhanced_property_table_styles.css">
    <link rel="stylesheet" href="clickable_rows.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<?php require_once __DIR__ . '/navbar.php'; ?>

<div class="container">
    <?php
    // Display flash messages (success/error)
    require_once __DIR__ . '/flash.php';
    $fs = get_flash_messages();
    if (!empty($fs)) {
        $first_id = array_key_first($fs);
        echo "<div class='message message-success'>" . htmlspecialchars($fs[$first_id]) . "</div>";
        if (function_exists('clear_flash')) { clear_flash($first_id); }
    }
    if (isset($_SESSION["error_message"])) {
        echo "<div class='message message-error'>" . htmlspecialchars($_SESSION["error_message"]) . "</div>";
        unset($_SESSION["error_message"]);
    }
    ?>
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin:18px 0;">
        <div>
            <h1 style="margin:0;">&rlm;<?php echo t('duplicate_properties_review_title'); ?></h1>
        </div>
    </div>

    <form method="get" action="admin_duplicate_properties.php" class="search-form" style="display:flex;gap:10px;margin:12px 0;align-items:center;">
        <input type="text" class="search-input" name="phone" value="<?php echo htmlspecialchars($_GET['phone'] ?? ''); ?>" placeholder="<?php echo t('search_phone_placeholder'); ?>" style="flex:1;max-width:420px;" />
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> <?php echo t('search'); ?></button>
        <?php if ($active_phone): ?>
            <a href="admin_duplicate_properties.php" class="btn btn-light"><i class="fas fa-times"></i> <?php echo t('clear'); ?></a>
        <?php endif; ?>
    </form>

    <?php if (!$active_phone): ?>
        <!-- Filters and Sort Controls -->
        <div class="filters-container" style="display:flex;gap:12px;margin:16px 0;flex-wrap:wrap;align-items:center;padding:16px;background:#f8f9fa;border-radius:8px;">
            <!-- Status Filter -->
            <div class="filter-group" style="display:flex;align-items:center;gap:8px;">
                <label for="status_filter" style="font-weight:600;color:#495057;white-space:nowrap;">
                    <i class="fas fa-filter"></i> <?php echo t('filter_by_status'); ?>:
                </label>
                <select name="status_filter" id="status_filter" class="filter-select" onchange="applyFilters()" style="padding:8px 12px;border:1px solid #ced4da;border-radius:6px;background:#fff;font-size:0.9rem;min-width:180px;">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>><?php echo t('all_groups'); ?></option>
                    <option value="needs_review" <?php echo $status_filter === 'needs_review' ? 'selected' : ''; ?>><?php echo t('needs_review_only'); ?></option>
                    <option value="reviewed" <?php echo $status_filter === 'reviewed' ? 'selected' : ''; ?>><?php echo t('reviewed_only'); ?></option>
                </select>
            </div>
            
            <!-- Sort By -->
            <div class="sort-group" style="display:flex;align-items:center;gap:8px;">
                <label for="sort_by" style="font-weight:600;color:#495057;white-space:nowrap;">
                    <i class="fas fa-sort"></i> <?php echo t('sort_by'); ?>:
                </label>
                <select name="sort_by" id="sort_by" class="filter-select" onchange="applyFilters()" style="padding:8px 12px;border:1px solid #ced4da;border-radius:6px;background:#fff;font-size:0.9rem;min-width:160px;">
                    <option value="properties_count" <?php echo $sort_by === 'properties_count' ? 'selected' : ''; ?>><?php echo t('sort_by_properties_count'); ?></option>
                    <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>><?php echo t('sort_by_status'); ?></option>
                    <option value="date" <?php echo $sort_by === 'date' ? 'selected' : ''; ?>><?php echo t('sort_by_date'); ?></option>
                </select>
            </div>
            
            <!-- Sort Direction -->
            <div class="sort-direction-group" style="display:flex;align-items:center;gap:8px;">
                <label for="sort_dir" style="font-weight:600;color:#495057;white-space:nowrap;">
                    <?php echo t('direction'); ?>:
                </label>
                <select name="sort_dir" id="sort_dir" class="filter-select" onchange="applyFilters()" style="padding:8px 12px;border:1px solid #ced4da;border-radius:6px;background:#fff;font-size:0.9rem;min-width:120px;">
                    <option value="DESC" <?php echo $sort_dir === 'DESC' ? 'selected' : ''; ?>><?php echo t('sort_desc'); ?></option>
                    <option value="ASC" <?php echo $sort_dir === 'ASC' ? 'selected' : ''; ?>><?php echo t('sort_asc'); ?></option>
                </select>
            </div>
            
            <!-- Clear Filters Button -->
            <?php if ($status_filter !== 'all' || $sort_by !== 'properties_count' || $sort_dir !== 'DESC'): ?>
                <a href="admin_duplicate_properties.php" class="btn btn-light" style="margin-inline-start:auto;">
                    <i class="fas fa-times"></i> <?php echo t('reset_filters'); ?>
                </a>
            <?php endif; ?>
        </div>
        
    <?php endif; ?>

    <?php if (!$active_phone): ?>
        <div class="table-responsive">
            <table class="properties-table">
                <thead>
                    <tr>
                        <th><?php echo t('status'); ?></th>
                        <th><?php echo t('phone_number'); ?></th>
                        <th><?php echo t('properties_count'); ?></th>
                        <th><?php echo t('employees'); ?></th>
                        <th><?php echo t('listing_types'); ?></th>
                        <th><?php echo t('locations_summary'); ?></th>
                        <th><?php echo t('date_range'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($groups)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="no-properties">
                                    <i class="fas fa-check-circle"></i>
                                    <h3><?php echo t('no_duplicates_found'); ?></h3>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($groups as $g): 
                            // Determine review status
                            $needs_review_count = (int)($g['needs_review_count'] ?? 0);
                            $reviewed_count = (int)($g['reviewed_count'] ?? 0);
                            $total_count = (int)$g['properties_count'];
                            
                            // Status: "Needs Review" if ANY property has status 1, "Reviewed" if ALL have status 2
                            $status = 'needs_review'; // default
                            $status_label = t('needs_review');
                            $status_class = 'status-needs-review';
                            $status_icon = 'fa-exclamation-circle';
                            
                            if ($reviewed_count === $total_count && $total_count > 0) {
                                $status = 'reviewed';
                                $status_label = t('reviewed');
                                $status_class = 'status-reviewed';
                                $status_icon = 'fa-check-circle';
                            } elseif ($needs_review_count > 0) {
                                $status = 'needs_review';
                                $status_label = t('needs_review');
                                $status_class = 'status-needs-review';
                                $status_icon = 'fa-exclamation-circle';
                            }
                        ?>
                            <tr class="group-row" data-group-phone="<?php echo htmlspecialchars($g['normalized_phone']); ?>">
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>" title="<?php echo htmlspecialchars($status_label); ?>">
                                        <i class="fas <?php echo $status_icon; ?>"></i>
                                        <span class="status-text"><?php echo htmlspecialchars($status_label); ?></span>
                                    </span>
                                </td>
                                <td>
                                    <div class="phone-cell">
                                        <span class="phone-text"><?php echo htmlspecialchars($g['normalized_phone']); ?></span>
                                        <button type="button" class="btn btn-light btn-icon" title="Copy" onclick="copyPhone(event, '<?php echo htmlspecialchars($g['normalized_phone']); ?>')"><i class="fas fa-copy"></i></button>
                                    </div>
                                </td>
                                <td><?php echo (int)$g['properties_count']; ?></td>
                                <td><?php echo render_employees_badges($g['employees_data'] ?? null, $g['property_ids'] ?? null, $conn); ?></td>
                                <td><?php echo render_listing_types_badges($g['listing_types'] ?? null); ?></td>
                                <td><?php echo render_locations_badges($g['distinct_locations'] ?? null); ?></td>
                                <td>
                                    <?php
                                      $first = $g['first_added'] ?? null;
                                      $last = $g['last_added'] ?? null;
                                      if ($first && $last) {
                                          $first_fmt = date('Y-m-d', strtotime($first));
                                          $last_fmt = date('Y-m-d', strtotime($last));
                                          if ($first_fmt === $last_fmt) {
                                              echo '<span class="date-range">' . htmlspecialchars($first_fmt) . '</span>';
                                          } else {
                                              echo '<span class="date-range">' . htmlspecialchars($first_fmt) . ' → ' . htmlspecialchars($last_fmt) . '</span>';
                                          }
                                      } else {
                                          echo '<span class="summary-empty">-</span>';
                                      }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        if ($total_rows > $per_page) {
            $total_pages = (int)ceil($total_rows / $per_page);
            echo '<div class="pagination" style="margin: 10px 0;">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $isActive = ($i === $page);
                $cls = $isActive ? 'style="background:#333;color:#fff;border-radius:6px;padding:6px 10px;margin:0 4px;display:inline-block;"' : 'style="border:1px solid #ccc;border-radius:6px;padding:6px 10px;margin:0 4px;display:inline-block;"';
                $page_url = '?page=' . $i;
                if ($status_filter !== 'all') {
                    $page_url .= '&status_filter=' . urlencode($status_filter);
                }
                if ($sort_by !== 'properties_count') {
                    $page_url .= '&sort_by=' . urlencode($sort_by);
                }
                if ($sort_dir !== 'DESC') {
                    $page_url .= '&sort_dir=' . urlencode($sort_dir);
                }
                echo '<a ' . $cls . ' href="' . htmlspecialchars($page_url) . '">' . $i . '</a>';
            }
            echo '</div>';
        }
        ?>
    <?php else: ?>
        <?php $g = $groups[0] ?? null; ?>
        <?php if (!$g): ?>
            <div class="no-properties"><i class="fas fa-info-circle"></i> <?php echo t('group_not_found'); ?></div>
        <?php else: ?>
            <div style="margin-top:12px;">
                <div style="padding:12px 16px;border-bottom:1px solid #e9ecef;background:#f8f9fa;border-radius:8px 8px 0 0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <div>
                        <strong><?php echo t('phone_number'); ?>:</strong> <?php echo htmlspecialchars($g['normalized_phone']); ?>
                        &nbsp; | &nbsp;
                        <strong><?php echo t('properties_count'); ?>:</strong> <?php echo (int)$g['properties_count']; ?>
                        &nbsp; | &nbsp;
                        <strong><?php echo t('roles_seen'); ?>:</strong> <?php echo htmlspecialchars(translate_roles_csv($g['phone_roles'])); ?>
                    </div>
                    <div>
                        <?php
                        // Determine current review status
                        $needs_review_count = (int)($g['needs_review_count'] ?? 0);
                        $reviewed_count = (int)($g['reviewed_count'] ?? 0);
                        $total_count = (int)$g['properties_count'];
                        $is_reviewed = ($reviewed_count === $total_count && $total_count > 0);
                        ?>
                        <label class="toggle-switch" id="reviewToggleContainer">
                            <input type="checkbox" id="reviewToggle" <?php echo $is_reviewed ? 'checked' : ''; ?> onchange="toggleGroupReview('<?php echo htmlspecialchars($g['normalized_phone']); ?>', this.checked)">
                            <span class="toggle-slider"></span>
                            <span class="toggle-label" id="toggleLabel"><?php echo $is_reviewed ? t('reviewed') : t('needs_review'); ?></span>
                        </label>
                    </div>
                </div>
                
                <!-- Comparison Cards Grid -->
                <div class="comparison-cards-grid" id="comparisonCards">
                    <?php foreach ($properties as $p): 
                        $price_display = '';
                        if (!empty($p['price_cash'])) {
                            $price_display = number_format((float)$p['price_cash'], 0) . ' ' . (function_exists('t') ? t('egp') : 'EGP');
                        } elseif (!empty($p['price_installments'])) {
                            $installments_text = function_exists('t') ? t('installments') : 'Installments';
                            $price_display = number_format((float)$p['price_installments'], 0) . ' ' . (function_exists('t') ? t('egp') : 'EGP') . ' (' . $installments_text . ')';
                        } elseif (!empty($p['meter_price_cash'])) {
                            $meter_text = function_exists('t') ? t('meter') : 'm²';
                            $price_display = number_format((float)$p['meter_price_cash'], 0) . ' ' . (function_exists('t') ? t('egp') : 'EGP') . ' / ' . $meter_text;
                        } else {
                            $price_display = '-';
                        }
                    ?>
                        <div class="comparison-card" data-property-id="<?php echo (int)$p['PropertyID']; ?>">
                            <!-- Property Image -->
                            <div class="card-image">
                                <?php if (!empty($p['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="<?php echo htmlspecialchars($p['Title']); ?>" onerror="this.src='images/placeholder-property.jpg'; this.onerror=null;">
                                <?php else: ?>
                                    <div class="card-image-placeholder">
                                        <i class="fas fa-home fa-3x"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-id-badge">#<?php echo (int)$p['PropertyID']; ?></div>
                            </div>
                            
                            <!-- Card Content -->
                            <div class="card-content">
                                <!-- Title -->
                                <div class="card-field" data-field="title">
                                    <div class="field-label"><?php echo t('title'); ?></div>
                                    <div class="field-value"><?php echo htmlspecialchars($p['Title'] ?: '-'); ?></div>
                                </div>
                                
                                <!-- Price -->
                                <div class="card-field" data-field="price" data-price-value="<?php echo htmlspecialchars($price_display); ?>">
                                    <div class="field-label"><?php echo t('price'); ?></div>
                                    <div class="field-value"><?php echo htmlspecialchars($price_display); ?></div>
                                </div>
                                
                                <!-- Location -->
                                <div class="card-field" data-field="location">
                                    <div class="field-label"><?php echo t('location'); ?></div>
                                    <div class="field-value"><?php echo htmlspecialchars($p['Location'] ?: '-'); ?></div>
                                </div>
                                
                                <!-- Employee -->
                                <div class="card-field" data-field="employee">
                                    <div class="field-label"><?php echo t('added_by'); ?></div>
                                    <div class="field-value"><?php echo htmlspecialchars($p['employee_name'] ?: (function_exists('t') ? t('unknown') : 'Unknown')); ?></div>
                                </div>
                                
                                <!-- Date -->
                                <div class="card-field" data-field="date">
                                    <div class="field-label"><?php echo t('date_added'); ?></div>
                                    <div class="field-value"><?php echo htmlspecialchars($p['DateAdded'] ?: '-'); ?></div>
                                </div>
                                
                                <!-- Listing Type -->
                                <div class="card-field" data-field="listing_type">
                                    <div class="field-label"><?php echo t('listing_type'); ?></div>
                                    <div class="field-value">
                                        <span class="type-badge <?php echo strtolower($p['listing_type'] ?? '') === 'resale' ? 'type-badge-resale' : 'type-badge-primary'; ?>">
                                            <?php echo htmlspecialchars($p['listing_type'] ?: '-'); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Contact Roles (Phone Number Owners) -->
                                <div class="card-roles-section">
                                    <div class="roles-header">
                                        <i class="fas fa-phone-alt"></i> <?php echo t('contact_roles'); ?>
                                    </div>
                                    <div class="roles-list">
                                        <?php 
                                        $has_any_role = false;
                                        
                                        // Seller
                                        $seller_name = trim($p['seller_name'] ?? '');
                                        $seller_phone = trim($p['seller_phone'] ?? '');
                                        if (!empty($seller_name) || !empty($seller_phone)) {
                                            $has_any_role = true;
                                            $seller_parts = array_filter([$seller_name, $seller_phone]);
                                            $seller_display = !empty($seller_parts) ? implode(' - ', $seller_parts) : '-';
                                        ?>
                                            <div class="role-item" data-role="seller" data-role-value="<?php echo htmlspecialchars(strtolower($seller_display)); ?>">
                                                <span class="role-label"><?php echo t('seller'); ?>:</span>
                                                <span class="role-value"><?php echo htmlspecialchars($seller_display); ?></span>
                                            </div>
                                        <?php } ?>
                                        
                                        <!-- Sales Manager -->
                                        <?php 
                                        $manager_name = trim($p['sales_manager_name'] ?? '');
                                        $manager_phone = trim($p['sales_manager_phone'] ?? '');
                                        if (!empty($manager_name) || !empty($manager_phone)) {
                                            $has_any_role = true;
                                            $manager_parts = array_filter([$manager_name, $manager_phone]);
                                            $manager_display = !empty($manager_parts) ? implode(' - ', $manager_parts) : '-';
                                        ?>
                                            <div class="role-item" data-role="sales_manager" data-role-value="<?php echo htmlspecialchars(strtolower($manager_display)); ?>">
                                                <span class="role-label"><?php echo t('sales_manager'); ?>:</span>
                                                <span class="role-value"><?php echo htmlspecialchars($manager_display); ?></span>
                                            </div>
                                        <?php } ?>
                                        
                                        <!-- Salesperson -->
                                        <?php 
                                        $salesperson_name = trim($p['salesperson_name'] ?? '');
                                        $salesperson_phone = trim($p['salesperson_phone'] ?? '');
                                        if (!empty($salesperson_name) || !empty($salesperson_phone)) {
                                            $has_any_role = true;
                                            $salesperson_parts = array_filter([$salesperson_name, $salesperson_phone]);
                                            $salesperson_display = !empty($salesperson_parts) ? implode(' - ', $salesperson_parts) : '-';
                                        ?>
                                            <div class="role-item" data-role="salesperson" data-role-value="<?php echo htmlspecialchars(strtolower($salesperson_display)); ?>">
                                                <span class="role-label"><?php echo t('salesperson'); ?>:</span>
                                                <span class="role-value"><?php echo htmlspecialchars($salesperson_display); ?></span>
                                            </div>
                                        <?php } ?>
                                        
                                        <?php if (!$has_any_role): ?>
                                            <div class="role-item role-empty">
                                                <span class="role-value"><?php echo t('no_contact_info'); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Card Actions -->
                            <div class="card-actions">
                                <a class="btn btn-outline-primary btn-sm" href="view_property.php?id=<?php echo (int)$p['PropertyID']; ?>">
                                    <i class="fas fa-eye"></i> <?php echo t('view'); ?>
                                </a>
                                <a class="btn btn-outline-secondary btn-sm" href="edit_property.php?id=<?php echo (int)$p['PropertyID']; ?>">
                                    <i class="fas fa-edit"></i> <?php echo t('edit'); ?>
                                </a>
                                <?php if (can_delete_properties()): ?>
                                    <form method="POST" action="delete_property.php" style="display:inline;" onsubmit="return confirm('<?php echo t('confirm_delete'); ?>');">
                                        <?php echo csrf_input(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int)$p['PropertyID']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> <?php echo t('delete'); ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
<script src="property_row_click.js"></script>
<script>
  // Navigate when clicking anywhere on a group row (except on controls)
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.group-row').forEach(function(row){
      row.style.cursor = 'pointer';
      row.addEventListener('click', function(e){
        if (e.target.closest('a,button')) return;
        const phone = this.dataset.groupPhone;
        if (phone) {
          window.location.href = 'admin_duplicate_properties.php?phone=' + encodeURIComponent(phone);
        }
      });
    });
  });

  function copyPhone(ev, phone){
    ev.stopPropagation();
    navigator.clipboard.writeText(phone).catch(function(){});
    const btn = ev.currentTarget;
    const old = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i>';
    setTimeout(function(){ btn.innerHTML = old; }, 900);
  }

  // Comparison highlighting: detect and highlight matching fields
  (function highlightMatches(){
    const cards = document.querySelectorAll('#comparisonCards .comparison-card');
    if (cards.length < 2) return; // Need at least 2 cards to compare

    // Fields to compare
    const fieldsToCompare = ['title', 'price', 'location', 'employee', 'date', 'listing_type'];
    
    // Also compare contact roles (seller, sales_manager, salesperson)
    const roleItems = Array.from(document.querySelectorAll('.role-item[data-role]'));
    if (roleItems.length >= 2) {
      const roleGroups = {};
      roleItems.forEach(function(el){
        const roleType = el.getAttribute('data-role');
        const value = el.getAttribute('data-role-value') || '';
        if (value === '' || value === '-') return;
        
        const key = roleType + '|' + value;
        if (!roleGroups[key]) {
          roleGroups[key] = [];
        }
        roleGroups[key].push(el);
      });
      
      // Highlight groups with 2+ matches
      Object.keys(roleGroups).forEach(function(key){
        const group = roleGroups[key];
        if (group.length >= 2) {
          group.forEach(function(el){
            el.classList.add('highlight-match');
          });
        }
      });
    }

    fieldsToCompare.forEach(function(fieldType){
      const fieldElements = Array.from(document.querySelectorAll('.card-field[data-field="' + fieldType + '"]'));
      if (fieldElements.length < 2) return;

      // Group by value
      const valueGroups = {};
      fieldElements.forEach(function(el){
        let value = '';
        if (fieldType === 'price') {
          value = el.getAttribute('data-price-value') || '';
        } else {
          const valueEl = el.querySelector('.field-value');
          value = valueEl ? valueEl.textContent.trim() : '';
        }
        
        // Normalize value for comparison (case-insensitive, trim)
        value = value.toLowerCase().trim();
        if (value === '' || value === '-') return;

        if (!valueGroups[value]) {
          valueGroups[value] = [];
        }
        valueGroups[value].push(el);
      });

      // Highlight groups with 2+ matches
      Object.keys(valueGroups).forEach(function(value){
        const group = valueGroups[value];
        if (group.length >= 2) {
          group.forEach(function(el){
            el.classList.add('highlight-match');
          });
        }
      });
    });
  })();

  // Apply filters and sort
  function applyFilters(){
    const statusFilter = document.getElementById('status_filter').value;
    const sortBy = document.getElementById('sort_by').value;
    const sortDir = document.getElementById('sort_dir').value;
    
    const params = new URLSearchParams();
    if (statusFilter !== 'all') {
      params.append('status_filter', statusFilter);
    }
    if (sortBy !== 'properties_count') {
      params.append('sort_by', sortBy);
    }
    if (sortDir !== 'DESC') {
      params.append('sort_dir', sortDir);
    }
    
    const queryString = params.toString();
    window.location.href = 'admin_duplicate_properties.php' + (queryString ? '?' + queryString : '');
  }

  // Toggle group review status
  function toggleGroupReview(phone, isChecked){
    const toggle = document.getElementById('reviewToggle');
    const label = document.getElementById('toggleLabel');
    const container = document.getElementById('reviewToggleContainer');
    
    // Disable toggle during request
    toggle.disabled = true;
    container.style.opacity = '0.6';
    container.style.pointerEvents = 'none';
    
    fetch('api/mark_group_reviewed.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ phone: phone })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update label based on new status
        if (data.new_status === 2) {
          label.textContent = '<?php echo t('reviewed'); ?>';
        } else {
          label.textContent = '<?php echo t('needs_review'); ?>';
        }
        // Reload page to show updated status everywhere
        setTimeout(function() {
          window.location.reload();
        }, 300);
      } else {
        // Revert toggle on error
        toggle.checked = !isChecked;
        alert('<?php echo t('error_occurred'); ?>: ' + (data.message || '<?php echo t('unknown_error'); ?>'));
        toggle.disabled = false;
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      // Revert toggle on error
      toggle.checked = !isChecked;
      alert('<?php echo t('error_occurred'); ?>: ' + error.message);
      toggle.disabled = false;
      container.style.opacity = '1';
      container.style.pointerEvents = 'auto';
    });
  }
</script>
<style>
.search-input{
  height:40px;
  padding:0 14px;
  border:1px solid #ced4da;
  border-radius:10px;
  background:#fff;
  color:#333;
  outline:none;
  box-shadow: inset 0 1px 2px rgba(0,0,0,0.03);
  transition:border-color .2s ease, box-shadow .2s ease;
}
.search-input::placeholder{ color:#adb5bd; }
.search-input:focus{
  border-color: var(--primary-color, #007bff);
  box-shadow: 0 0 0 3px rgba(0,123,255,0.12);
}
html[dir="rtl"] .search-input{ text-align:right; }

/* Roles badges */
.role-badge{display:inline-block;padding:4px 8px;border-radius:12px;font-size:.8rem;font-weight:600}
.role-badge-seller{background:#e9f5ff;color:#0d6efd;border:1px solid #b6dbff}
.role-badge-manager{background:#eaf7ea;color:#198754;border:1px solid #c8e6c9}
.role-badge-sales{background:#fff3cd;color:#9c6f00;border:1px solid #ffe19c}
.role-badge-neutral{background:#f1f3f5;color:#495057;border:1px solid #e9ecef}

/* Phone cell with copy */
.phone-cell{display:flex;align-items:center;gap:8px}
.btn-icon{padding:6px 8px;border-radius:8px}
.group-row:hover{background:#f7faff}

/* Listing type badges */
.type-badge{display:inline-block;padding:3px 8px;border-radius:10px;font-size:.75rem;margin-top:6px;margin-inline-end:6px;border:1px solid transparent}
.type-badge-primary{background:#e7f1ff;color:#0d6efd;border-color:#bcd8ff}
.type-badge-resale{background:#fff0e6;color:#d35400;border-color:#ffd1b3}

/* Employee badges */
.employee-badge{display:inline-block;padding:4px 10px;border-radius:12px;font-size:.85rem;margin:2px 4px 2px 0;background:#e7f1ff;color:#0d6efd;border:1px solid #bcd8ff;font-weight:500}
html[dir="rtl"] .employee-badge{margin:2px 0 2px 4px}

/* Location badges */
.location-badge{display:inline-block;padding:4px 10px;border-radius:12px;font-size:.85rem;margin:2px 4px 2px 0;background:#f0f4f8;color:#495057;border:1px solid #dee2e6;font-weight:500}
html[dir="rtl"] .location-badge{margin:2px 0 2px 4px}

/* Date range */
.date-range{font-size:.9rem;color:#495057;font-weight:500;white-space:nowrap}

/* Summary empty */
.summary-empty{color:#adb5bd;font-style:italic}

/* Comparison Cards Grid */
.comparison-cards-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));
  gap:20px;
  padding:20px;
  background:#f8f9fa;
  border-radius:0 0 8px 8px;
}

.comparison-card{
  background:#fff;
  border-radius:12px;
  box-shadow:0 2px 8px rgba(0,0,0,0.08);
  overflow:hidden;
  transition:transform .2s ease, box-shadow .2s ease;
  display:flex;
  flex-direction:column;
}

.comparison-card:hover{
  transform:translateY(-2px);
  box-shadow:0 4px 12px rgba(0,0,0,0.12);
}

.card-image{
  position:relative;
  width:100%;
  height:200px;
  overflow:hidden;
  background:#e9ecef;
}

.card-image img{
  width:100%;
  height:100%;
  object-fit:cover;
}

.card-image-placeholder{
  width:100%;
  height:100%;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#adb5bd;
  background:#f1f3f5;
}

.card-id-badge{
  position:absolute;
  top:8px;
  right:8px;
  background:rgba(0,0,0,0.7);
  color:#fff;
  padding:4px 10px;
  border-radius:12px;
  font-size:.75rem;
  font-weight:600;
}

.card-content{
  padding:16px;
  flex:1;
}

.card-field{
  margin-bottom:12px;
  padding:8px;
  border-radius:6px;
  transition:background-color .2s ease;
}

.card-field.highlight-match{
  background-color:#e8f5e9;
  border-left:3px solid #4caf50;
}

.card-field .field-label{
  font-size:.75rem;
  color:#6c757d;
  font-weight:600;
  text-transform:uppercase;
  letter-spacing:0.5px;
  margin-bottom:4px;
}

.card-field .field-value{
  font-size:.95rem;
  color:#212529;
  font-weight:500;
  word-break:break-word;
}

/* Contact Roles Section */
.card-roles-section{
  margin-top:16px;
  padding-top:16px;
  border-top:2px solid #e9ecef;
}

.roles-header{
  font-size:.8rem;
  color:#6c757d;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:0.5px;
  margin-bottom:10px;
  display:flex;
  align-items:center;
  gap:6px;
}

.roles-list{
  display:flex;
  flex-direction:column;
  gap:8px;
}

.role-item{
  padding:8px 10px;
  border-radius:6px;
  background:#f8f9fa;
  border-left:3px solid transparent;
  transition:background-color .2s ease, border-color .2s ease;
  font-size:.85rem;
}

.role-item.highlight-match{
  background-color:#e8f5e9;
  border-left-color:#4caf50;
}

.role-item[data-role="seller"]{
  border-left-color:#0d6efd;
}

.role-item[data-role="sales_manager"]{
  border-left-color:#198754;
}

.role-item[data-role="salesperson"]{
  border-left-color:#ffc107;
}

.role-label{
  font-weight:600;
  color:#495057;
  margin-inline-end:6px;
}

.role-value{
  color:#212529;
  word-break:break-word;
}

.role-empty{
  color:#adb5bd;
  font-style:italic;
  text-align:center;
}

.card-actions{
  padding:12px 16px;
  border-top:1px solid #e9ecef;
  display:flex;
  gap:8px;
  flex-wrap:wrap;
}

.card-actions .btn{
  flex:1;
  min-width:80px;
  text-align:center;
  font-size:.85rem;
  padding:6px 12px;
}

.btn-sm{
  padding:6px 12px;
  font-size:.85rem;
}

@media (max-width:768px){
  .comparison-cards-grid{
    grid-template-columns:1fr;
    gap:16px;
    padding:16px;
  }
}

/* Status Badges */
.status-badge{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:6px 12px;
  border-radius:12px;
  font-size:.85rem;
  font-weight:600;
  white-space:nowrap;
}

.status-badge i{
  font-size:.9rem;
}

.status-needs-review{
  background:#fff3cd;
  color:#856404;
  border:1px solid #ffeaa7;
}

.status-reviewed{
  background:#d1e7dd;
  color:#0f5132;
  border:1px solid #a3cfbb;
}

.status-text{
  font-size:.85rem;
}

/* Ensure delete button is red */
.card-actions .btn-danger,
.card-actions button.btn-danger{
  background-color:#dc3545 !important;
  color:#fff !important;
  border-color:#dc3545 !important;
}

.card-actions .btn-danger:hover,
.card-actions button.btn-danger:hover{
  background-color:#c82333 !important;
  border-color:#bd2130 !important;
}

/* Toggle Switch */
.toggle-switch {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  user-select: none;
  transition: opacity 0.2s ease;
}

.toggle-switch input[type="checkbox"] {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: relative;
  width: 56px;
  height: 32px;
  background-color: #adb5bd;
  border-radius: 16px;
  transition: background-color 0.3s ease;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.toggle-slider::before {
  content: "";
  position: absolute;
  width: 24px;
  height: 24px;
  left: 4px;
  top: 50%;
  transform: translateY(-50%);
  background-color: white;
  border-radius: 50%;
  transition: left 0.3s ease, transform 0.3s ease;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-switch input[type="checkbox"]:checked + .toggle-slider {
  background-color: #7F52FF;
}

.toggle-switch input[type="checkbox"]:checked + .toggle-slider::before {
  left: calc(100% - 24px - 4px);
  transform: translateY(-50%);
}

.toggle-switch input[type="checkbox"]:not(:checked) + .toggle-slider {
  background-color: #adb5bd;
}

.toggle-switch input[type="checkbox"]:not(:checked) + .toggle-slider::before {
  left: 4px;
  transform: translateY(-50%);
}

html[dir="rtl"] .toggle-switch input[type="checkbox"]:checked + .toggle-slider::before {
  left: 4px;
  transform: translateY(-50%);
}

html[dir="rtl"] .toggle-switch input[type="checkbox"]:not(:checked) + .toggle-slider::before {
  left: calc(100% - 24px - 4px);
  transform: translateY(-50%);
}

.toggle-switch input[type="checkbox"]:disabled + .toggle-slider {
  opacity: 0.6;
  cursor: not-allowed;
}

.toggle-label {
  font-size: 0.95rem;
  font-weight: 600;
  color: #495057;
  min-width: 100px;
  text-align: left;
}

html[dir="rtl"] .toggle-label {
  text-align: right;
}

.toggle-switch:hover .toggle-slider {
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}
</style>
</html>
</body>
</html>


