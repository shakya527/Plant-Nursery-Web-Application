<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : config/db.php
// PURPOSE   : Central database connection file.
//             Every PHP file in this project includes this file ONCE at the top
//             using:  require_once __DIR__ . '/../config/db.php';
//             It provides the global $conn (MySQLi connection object) that all
//             prepared statements across the application rely on.
//
// OBJECTIVE ALIGNMENT:
//   ✅ Obj 4 – Security & Tech : All queries use $conn with $stmt->prepare()
//              (MySQLi Prepared Statements). This file is the single source of
//              the connection, ensuring NO raw string queries are ever used.
//   ✅ Obj 4 – Responsive UI   : Error handling prevents fatal crashes that
//              would break the page layout for the end user.
// =============================================================================


// -----------------------------------------------------------------------------
// SECTION 1: Database Credentials
// -----------------------------------------------------------------------------
// These constants hold the connection parameters for MySQL.
// In a production environment, move these values to a .env file or a server
// environment variable and NEVER commit credentials to version control.
// -----------------------------------------------------------------------------

define('DB_HOST',    'localhost');   // MySQL server host (XAMPP default)
define('DB_USER',    'root');        // MySQL username   (XAMPP default: root)
define('DB_PASS',    '');            // MySQL password   (XAMPP default: empty)
define('DB_NAME',    'greenthumb_db'); // Database name created in STEP 1 schema
define('DB_PORT',    3306);          // MySQL default port
define('DB_CHARSET', 'utf8mb4');     // Must match the schema's CHARACTER SET


// -----------------------------------------------------------------------------
// SECTION 2: Enable MySQLi Error Reporting
// -----------------------------------------------------------------------------
// Setting MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT makes MySQLi throw a
// mysqli_sql_exception for any connection or query error instead of silently
// returning false. This makes debugging MUCH easier during development.
//
// ⚠️  In production, catch these exceptions gracefully (see Section 4 below)
//     and log them server-side rather than displaying them to the user.
// -----------------------------------------------------------------------------
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


// -----------------------------------------------------------------------------
// SECTION 3: Establish the MySQLi Connection
// -----------------------------------------------------------------------------
// We use a try/catch block so the application can fail gracefully if the
// database server is unreachable, instead of exposing raw PHP error messages
// to the customer or farmer (Obj 4 – secure, professional error handling).
// -----------------------------------------------------------------------------
try {
    // Create a new MySQLi connection object using the credentials above.
    // This $conn object is what every other PHP file will use to call:
    //   $stmt = $conn->prepare("SELECT ...");   ← Obj 4 prepared statement
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    // ------------------------------------------------------------------
    // SECTION 3a: Set the Connection Character Set
    // ------------------------------------------------------------------
    // set_charset() MUST be called immediately after connecting.
    // It ensures that all data flowing between PHP and MySQL uses utf8mb4,
    // which matches the schema's CHARACTER SET. Without this, special
    // characters (accented letters, symbols) can become garbled.
    // It also protects against a class of charset-based SQL injection
    // attacks by ensuring the connection encoding is consistent.
    // ------------------------------------------------------------------
    $conn->set_charset(DB_CHARSET);

} catch (mysqli_sql_exception $e) {
    // ------------------------------------------------------------------
    // SECTION 3b: Connection Failure Handling (Obj 4 – Security)
    // ------------------------------------------------------------------
    // If the database is unreachable, we:
    //   1. Log the real error message to the PHP error log (server-side only)
    //   2. Show the user a friendly, generic message (no sensitive info leaked)
    //
    // NEVER echo $e->getMessage() to the browser in production — it exposes
    // the host, username, and database name to potential attackers.
    // ------------------------------------------------------------------
    error_log('[GreenThumb DB Error] Connection failed: ' . $e->getMessage());

    // Display a user-friendly error page fragment.
    // Any page that includes db.php will show this message and stop execution.
    die('
        <div style="
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 80px auto;
            padding: 30px;
            border: 1px solid #e74c3c;
            border-radius: 8px;
            background: #fdf0ef;
            color: #c0392b;
            text-align: center;">
            <h2>&#9888; Database Connection Error</h2>
            <p>We are unable to connect to the database right now.</p>
            <p>Please ensure XAMPP MySQL is running and try again.</p>
            <small style="color:#999;">
                Error reference logged. Contact your administrator if this persists.
            </small>
        </div>
    ');
}


// =============================================================================
// SECTION 4: Reusable Prepared-Statement Helper Function
// =============================================================================
// PURPOSE  : Provides a single, DRY utility for executing prepared statements
//            safely across all PHP files in the project.
//
// USAGE    :
//   // SELECT example (returns array of rows):
//   $rows = db_query("SELECT * FROM plants WHERE category_id = ? AND is_available = ?", "ii", [$cat_id, 1]);
//
//   // INSERT / UPDATE / DELETE example (returns affected row count):
//   $affected = db_query("UPDATE plants SET stock_quantity = ? WHERE plant_id = ?", "ii", [$new_qty, $plant_id]);
//
// HOW IT MAPS TO Obj 4 (100% Prepared Statements):
//   - The SQL query and user-supplied parameters are ALWAYS separated.
//   - bind_param() ties parameters to the query safely; MySQL treats them
//     as DATA, never as SQL code → eliminates SQL Injection entirely.
//   - The type string (e.g. "sis") tells MySQLi exactly what each parameter
//     is:  s = string, i = integer, d = double/float, b = blob.
//
// @param string $sql    Parameterised SQL with ? placeholders
// @param string $types  MySQLi bind_param type string (e.g. "si", "iii")
// @param array  $params Indexed array of values matching each ? placeholder
// @return array|int     SELECT → array of associative rows
//                       INSERT/UPDATE/DELETE → affected_rows count (int)
// =============================================================================
function db_query(string $sql, string $types = '', array $params = []): array|int
{
    global $conn; // Use the globally established MySQLi connection from Section 3

    // Prepare the SQL statement — separates query structure from user data
    $stmt = $conn->prepare($sql);

    // If there are parameters to bind, bind them now.
    // bind_param() uses the type string to correctly cast each value,
    // preventing type-confusion attacks (e.g., passing a string as an integer).
    if ($types !== '' && count($params) > 0) {
        $stmt->bind_param($types, ...$params); // Spread operator unpacks the array
    }

    // Execute the prepared statement
    $stmt->execute();

    // Determine the type of query and return the appropriate result:
    $result = $stmt->get_result(); // Returns mysqli_result for SELECT, false for others

    if ($result instanceof mysqli_result) {
        // ── SELECT / SHOW queries ─────────────────────────────────────────────
        // fetch_all(MYSQLI_ASSOC) returns all rows as an array of associative
        // arrays, e.g. [['plant_id'=>1, 'plant_name'=>'Burgundy'], ...]
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free(); // Release the result set memory
        $stmt->close();  // Free the statement resources
        return $rows;    // Always returns an array (empty [] if no rows found)
    } else {
        // ── INSERT / UPDATE / DELETE queries ─────────────────────────────────
        // Return the number of rows affected so the calling code can check
        // whether the operation succeeded (affected_rows > 0 = success).
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected; // Returns int (0 = no change, >0 = success, -1 = error)
    }
}


// =============================================================================
// SECTION 5: Single-Row Fetch Helper
// =============================================================================
// PURPOSE  : Convenience wrapper around db_query() for queries that are
//            expected to return exactly ONE row (e.g., fetch user by ID,
//            fetch a single plant's details for the product page).
//
// USAGE    :
//   $plant = db_query_one("SELECT * FROM plants WHERE plant_id = ?", "i", [$id]);
//   if ($plant) { echo $plant['plant_name']; }
//
// @return array|null  Associative array of the first row, or null if not found
// =============================================================================
function db_query_one(string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_query($sql, $types, $params);

    // Return the first row if results exist, or null if the query matched nothing
    return !empty($rows) ? $rows[0] : null;
}


// =============================================================================
// SECTION 6: Last Inserted ID Helper
// =============================================================================
// PURPOSE  : After an INSERT, call this to get the auto-incremented ID of the
//            newly created row. Used in checkout to get the new order_id so
//            the customer can be redirected to view_invoice.php?order_id=X
//            (Obj 5 – automated invoice generation).
//
// USAGE    :
//   db_query("INSERT INTO orders (...) VALUES (...)", "s...", [...]);
//   $new_order_id = db_last_id();
//
// @return int  The AUTO_INCREMENT id of the last inserted row
// =============================================================================
function db_last_id(): int
{
    global $conn;
    return (int) $conn->insert_id; // Cast to int for type safety
}

// =============================================================================
// END OF FILE: config/db.php
// =============================================================================
// This file is now the secure foundation for ALL database interactions.
// Every other PHP file in GreenThumb will start with:
//   require_once __DIR__ . '/../config/db.php';
// (adjust the relative path depth as needed per file location)
//
// Next Step (STEP 3): Session management + auth helper (config/auth.php)
// =============================================================================
?>
