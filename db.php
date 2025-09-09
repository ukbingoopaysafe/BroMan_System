<?php
// Database connection settings
// IMPORTANT: For a production environment, store credentials securely outside the webroot
// (e.g., using environment variables or a dedicated, restricted configuration file).

// Attempt to get credentials from environment variables (best practice)
$host = getenv('DB_HOST') ?: "127.0.0.1";
$user = getenv('DB_USER') ?: "root";
$pass = getenv('DB_PASS') ?: "";
$dbname = getenv('DB_NAME') ?: "broman_db";

/**
 * Return the database credentials used by this application.
 * Other modules should call this helper instead of re-parsing environment variables.
 */
function get_db_credentials(): array {
    $h = getenv('DB_HOST') ?: "127.0.0.1";
    $u = getenv('DB_USER') ?: "root";
    $p = getenv('DB_PASS') ?: "";
    $d = getenv('DB_NAME') ?: "broman_db";
    return ['host' => $h, 'user' => $u, 'pass' => $p, 'dbname' => $d];
}

// Use mysqli_report to turn errors into exceptions for better handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $dbname);

    // Set character set to UTF-8
    if (!$conn->set_charset("utf8mb4")) {
        // Log the error, but don't necessarily die immediately unless critical
        error_log("Critical Error: Could not set character set utf8mb4: " . $conn->error);
        // Depending on application needs, you might throw an exception or handle this differently
        // For now, we log it and continue, but this is risky for data integrity.
    }

} catch (mysqli_sql_exception $e) {
    // Log the detailed error for administrators
    error_log("Database connection failed: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");

    // Display a generic, user-friendly error message
    // In a real application, you might redirect to an error page or display a message within the layout.
    die("Sorry, we are experiencing technical difficulties. Please try again later or contact support.");
}

/**
 * Helper: safe_bind_param
 * Validates that the types string length matches the number of variables
 * and then invokes bind_param on the provided statement.
 *
 * Usage: safe_bind_param($stmt, 'issd', $a, $b, $c, $d);
 */
function safe_bind_param(mysqli_stmt $stmt, string $types /*, mixed ...$vars */)
{
    $vars = array_slice(func_get_args(), 2);
    $typesLen = strlen($types);
    $varsCount = count($vars);
    if ($typesLen !== $varsCount) {
        // Log detailed debug info and throw an exception with a helpful message
        $msg = sprintf("bind_param mismatch: types string length=%d but vars count=%d", $typesLen, $varsCount);
        error_log("DB: " . $msg . "; types='$types'; vars=[" . implode(', ', array_map(function($v){ return is_null($v)?'NULL':(is_scalar($v)?(string)$v:gettype($v)); }, $vars)) . "]");
        throw new InvalidArgumentException($msg);
    }

    // mysqli_stmt::bind_param requires parameters to be passed by reference.
    // Build an array of references to the variables.
    $refs = [];
    foreach ($vars as $key => &$val) { $refs[$key] = &$val; }

    // Prepend the types string
    array_unshift($refs, $types);

    // Call bind_param with unpacked references
    return $stmt->bind_param(...$refs);
}

/**
 * Helper: get_enum_values
 * Returns an array of allowed ENUM values for a given table column.
 */
function get_enum_values(mysqli $conn, string $table, string $column): array
{
    $schema = $conn->real_escape_string($conn->query("SELECT DATABASE() as db")->fetch_assoc()['db']);
    $table_esc = $conn->real_escape_string($table);
    $col_esc = $conn->real_escape_string($column);

    $sql = "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $schema . "' AND TABLE_NAME = '" . $table_esc . "' AND COLUMN_NAME = '" . $col_esc . "' LIMIT 1";
    try {
        $res = $conn->query($sql);
        if ($res && $row = $res->fetch_assoc()) {
            $type = $row['COLUMN_TYPE']; // e.g. "enum('a','b')"
            if (preg_match("/^enum\((.*)\)$/i", $type, $matches)) {
                $vals = str_getcsv($matches[1], ',', "'");
                // Trim surrounding quotes if any
                return array_map(function($v) { return trim($v, "'"); }, $vals);
            }
        }
    } catch (Exception $e) {
        error_log("get_enum_values error: " . $e->getMessage());
    }
    return [];
}

/**
 * Helper: normalize_seller_type_for_db
 * Ensures the posted seller_type matches one of the allowed ENUM values in the DB.
 * If the DB hasn't been migrated to the new taxonomy, this function will attempt
 * to map common new values to their legacy equivalents, otherwise returns null.
 */
function normalize_seller_type_for_db(mysqli $conn, $value)
{
    if ($value === null) return null;
    $val = trim((string)$value);
    if ($val === '') return null;

    $allowed = get_enum_values($conn, 'properties', 'seller_type');
    if (in_array($val, $allowed, true)) {
        return $val;
    }

    // Mapping from new taxonomy to legacy enum values (best-effort)
    $map = [
        // new => legacy
        'مالك' => 'مالك مباشر',
        'وسيط' => 'مكتب عقاري',
        'وكيل' => 'وكيل',
        'بواب' => 'وكيل', // nothing exact in legacy; map to 'وكيل' as fallback
        'شركة مطورة' => 'مطور عقاري',
        'شركة تشطيب' => 'مطور عقاري',
    ];

    if (isset($map[$val]) && in_array($map[$val], $allowed, true)) {
        return $map[$val];
    }

    // Last attempt: try partial matches (e.g., contains)
    foreach ($allowed as $a) {
        if (mb_stripos($a, $val) !== false || mb_stripos($val, $a) !== false) {
            return $a;
        }
    }

    // Not allowed: return null so DB stores NULL instead of an empty/invalid enum value
    return null;
}

/**
 * Helper: allowed_seller_types
 * Returns the canonical list of seller types used in the UI.
 */
function allowed_seller_types(): array
{
    return [
        'مالك',
        'وسيط',
        'وكيل',
        'بواب',
        'شركة مطورة',
        'شركة تشطيب',
    ];
}

// Optional: Remove error reporting back to default if needed elsewhere,
// but generally, letting exceptions bubble up is cleaner.
// mysqli_report(MYSQLI_REPORT_OFF);


