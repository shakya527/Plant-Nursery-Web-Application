-- =============================================================================
-- PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
-- FILE      : greenthumb_schema.sql
-- PURPOSE   : Master database schema — defines all tables, constraints,
--             indexes, and seed data required to run the GreenThumb system.
-- AUTHOR    : GreenThumb Dev Team
-- DATE      : 2026-07-11
-- =============================================================================
--
-- HOW TO IMPORT:
--   Option A (phpMyAdmin): Open phpMyAdmin → Create database → Import this file.
--   Option B (CLI)       : mysql -u root -p < greenthumb_schema.sql
--
-- OBJECTIVE COVERAGE (aligned to project objectives):
--   ✅ Obj 1 – Customer Catalog  : `plants` table stores all rubber plant data
--   ✅ Obj 2 – Secure Ordering   : `orders` + `order_items` capture purchase
--                                  and shipping details
--   ✅ Obj 3 – Farmer CRUD       : `plants` table is fully managed by farmers
--   ✅ Obj 4 – Security & Tech   : Passwords stored as bcrypt hashes;
--                                  PHP queries use prepared statements
--   ✅ Obj 5 – Invoice & Reports : `orders` + `order_items` power
--                                  view_invoice.php and income_report.php
-- =============================================================================


-- -----------------------------------------------------------------------------
-- STEP 0: Create & Select the Database
-- -----------------------------------------------------------------------------
-- Drop the database first (only during development/re-import).
-- REMOVE the DROP line in a live/production environment to prevent data loss.
DROP DATABASE IF EXISTS greenthumb_db;

CREATE DATABASE greenthumb_db
    CHARACTER SET utf8mb4        -- Full Unicode support (emoji, multilingual)
    COLLATE utf8mb4_unicode_ci;  -- Case-insensitive, accent-aware comparison

-- All subsequent statements run inside greenthumb_db
USE greenthumb_db;


-- =============================================================================
-- TABLE 1: users
-- =============================================================================
-- PURPOSE  : Central authentication and role table.
--            Supports two roles:
--              • 'farmer'   – can manage plant inventory (Obj 3)
--              • 'customer' – can browse catalog and place orders (Obj 1 & 2)
--
-- SECURITY : Passwords are NEVER stored as plain-text.
--            PHP hashes passwords with password_hash(..., PASSWORD_BCRYPT)
--            before inserting; `password_hash` stores the 60-char bcrypt result.
-- =============================================================================
CREATE TABLE users (
    -- Primary key — auto-incremented; referenced by orders.customer_id
    user_id       INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- Full display name shown in the UI (e.g., invoice header)
    full_name     VARCHAR(120)    NOT NULL,

    -- Unique email used for login; indexed for fast lookup during authentication
    email         VARCHAR(180)    NOT NULL,

    -- Bcrypt hash produced by PHP password_hash(); NEVER store plain-text here
    password_hash VARCHAR(255)    NOT NULL,

    -- Role-based access control (RBAC):
    --   'farmer'   → access to plant management dashboard (Obj 3)
    --   'customer' → access to catalog, cart, and order history (Obj 1 & 2)
    role          ENUM('farmer', 'customer') NOT NULL DEFAULT 'customer',

    -- Phone number for shipping contact (Obj 2 – capture shipping details safely)
    phone         VARCHAR(20)     NULL,

    -- Physical address used as default pre-fill on the checkout form
    address       TEXT            NULL,

    -- Soft-disable an account without deleting its historical order data
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,

    -- Audit timestamps — auto-managed by MySQL
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                           ON UPDATE CURRENT_TIMESTAMP,

    -- -------------------------------------------------------------------------
    -- CONSTRAINTS
    -- -------------------------------------------------------------------------
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email)   -- Prevents duplicate account registrations
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci
COMMENT='All system users (farmers and customers) with role-based access control.';


-- =============================================================================
-- TABLE 2: plant_categories
-- =============================================================================
-- PURPOSE  : Organises rubber plant varieties into logical groups
--            (e.g., "Indoor", "Outdoor", "Rare Varieties").
--            Farmers assign categories to plants during CRUD (Obj 3).
--            Customers filter the catalog by category (Obj 1).
-- =============================================================================
CREATE TABLE plant_categories (
    category_id   INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- Category name displayed in dropdowns and filter menus
    category_name VARCHAR(100)    NOT NULL,

    -- Optional longer description visible on catalog/category filter page
    description   TEXT            NULL,

    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (category_id),
    UNIQUE KEY uq_category_name (category_name)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci
COMMENT='Lookup table for rubber plant categories (e.g., Indoor, Outdoor, Rare).';


-- =============================================================================
-- TABLE 3: plants
-- =============================================================================
-- PURPOSE  : The core product catalog.
--            • Customers browse this table (Obj 1 – accurate info & stock levels)
--            • Farmers perform full CRUD on this table (Obj 3 – manage types,
--              quantities, pricing, and stocks)
--
-- STOCK    : `stock_quantity` is decremented on each confirmed order.
--            It is checked BEFORE adding to cart to prevent overselling.
--
-- PRICING  : `price_per_unit` uses DECIMAL to avoid floating-point rounding
--            errors that occur with PHP float arithmetic on monetary values.
-- =============================================================================
CREATE TABLE plants (
    plant_id        INT UNSIGNED        NOT NULL AUTO_INCREMENT,

    -- FK to plant_categories; NULL allowed if plant is uncategorised
    category_id     INT UNSIGNED        NULL,

    -- Common name shown to customers (e.g., "Ficus Elastica Burgundy")
    plant_name      VARCHAR(150)        NOT NULL,

    -- Scientific/botanical name for professional accuracy on the catalog page
    scientific_name VARCHAR(150)        NULL,

    -- Rich product description: care tips, light needs, watering schedule (Obj 1)
    description     TEXT                NULL,

    -- Price per individual plant unit
    -- DECIMAL(10,2) safely stores values up to 99,999,999.99 without rounding
    price_per_unit  DECIMAL(10, 2)      NOT NULL DEFAULT 0.00,

    -- Real-time stock level displayed on catalog; checked before ordering (Obj 1)
    stock_quantity  INT UNSIGNED        NOT NULL DEFAULT 0,

    -- Minimum units a customer must order per transaction (e.g., seedlings = 3)
    min_order_qty   INT UNSIGNED        NOT NULL DEFAULT 1,

    -- Filename of the uploaded product image, stored in /uploads/plants/ folder
    image_filename  VARCHAR(255)        NULL,

    -- Farmer can hide a plant from the public catalog without deleting it (Obj 3)
    is_available    TINYINT(1)          NOT NULL DEFAULT 1,

    -- Tracks which farmer created/last managed this record (Obj 3 audit trail)
    created_by      INT UNSIGNED        NULL,   -- FK → users.user_id (role=farmer)
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                 ON UPDATE CURRENT_TIMESTAMP,

    -- -------------------------------------------------------------------------
    -- CONSTRAINTS
    -- -------------------------------------------------------------------------
    PRIMARY KEY (plant_id),

    -- Enforce business rules: no negative prices or zero minimum orders
    CONSTRAINT chk_price_positive   CHECK (price_per_unit >= 0),
    CONSTRAINT chk_stock_positive   CHECK (stock_quantity >= 0),
    CONSTRAINT chk_min_order        CHECK (min_order_qty  >= 1),

    -- Performance indexes for common query patterns
    INDEX idx_plants_category   (category_id),   -- Catalog category filter
    INDEX idx_plants_available  (is_available),  -- "Show available only" filter
    INDEX idx_plants_created_by (created_by),    -- Farmer's "My Plants" dashboard

    -- -------------------------------------------------------------------------
    -- FOREIGN KEYS
    -- -------------------------------------------------------------------------
    CONSTRAINT fk_plants_category
        FOREIGN KEY (category_id)
        REFERENCES plant_categories (category_id)
        ON UPDATE CASCADE    -- Category name change propagates automatically
        ON DELETE SET NULL,  -- Deleting a category uncategorises its plants (safe)

    CONSTRAINT fk_plants_created_by
        FOREIGN KEY (created_by)
        REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL   -- Farmer account deletion does NOT erase plant records
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci
COMMENT='Product catalog: all rubber plant varieties, pricing, and stock levels.';


-- =============================================================================
-- TABLE 4: orders
-- =============================================================================
-- PURPOSE  : Records each customer order as one header row.
--            • Shipping address is captured AT ORDER TIME (Obj 2 – shipping
--              details captured safely) — NOT read from users.address —
--              so historical invoices remain accurate if address changes later.
--            • `total_amount` is a stored aggregate so invoice/report pages
--              load instantly without re-aggregating line items (Obj 5).
--
-- STATUS LIFECYCLE:
--   pending → confirmed → shipped → delivered
--                       ↘ cancelled
-- =============================================================================
CREATE TABLE orders (
    order_id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- FK to users (must be role='customer'); NULL not allowed on a placed order
    customer_id       INT UNSIGNED    NOT NULL,

    -- ── Shipping Address Snapshot (Obj 2) ────────────────────────────────────
    -- Stored independently from users.address so invoices are always accurate
    shipping_name     VARCHAR(120)    NOT NULL,   -- Recipient full name
    shipping_phone    VARCHAR(20)     NOT NULL,   -- Delivery contact number
    shipping_address  TEXT            NOT NULL,   -- Street / building / area
    shipping_city     VARCHAR(80)     NOT NULL,
    shipping_state    VARCHAR(80)     NOT NULL,
    shipping_postcode VARCHAR(20)     NOT NULL,
    shipping_country  VARCHAR(60)     NOT NULL DEFAULT 'Malaysia',

    -- Order lifecycle status (drives dashboard filters and invoice status badge)
    order_status      ENUM(
                          'pending',    -- Placed, awaiting farmer confirmation
                          'confirmed',  -- Farmer accepted; being prepared
                          'shipped',    -- Plants dispatched to courier
                          'delivered',  -- Customer confirmed receipt
                          'cancelled'   -- Cancelled before shipment
                      ) NOT NULL DEFAULT 'pending',

    -- Denormalised grand total (line items sum + shipping_cost)
    -- Stored for instant invoice rendering and income report queries (Obj 5)
    total_amount      DECIMAL(10, 2)  NOT NULL DEFAULT 0.00,

    -- Shipping fee applied at the time of order (may vary by location/weight)
    shipping_cost     DECIMAL(10, 2)  NOT NULL DEFAULT 0.00,

    -- Optional delivery instructions from the customer
    customer_notes    TEXT            NULL,

    -- Order date: the primary dimension for income_report.php date filters (Obj 5)
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,

    -- -------------------------------------------------------------------------
    -- CONSTRAINTS
    -- -------------------------------------------------------------------------
    PRIMARY KEY (order_id),

    CONSTRAINT chk_total_positive    CHECK (total_amount  >= 0),
    CONSTRAINT chk_shipping_positive CHECK (shipping_cost >= 0),

    -- Performance indexes for common query patterns
    INDEX idx_orders_status     (order_status),   -- Farmer dashboard status filter
    INDEX idx_orders_customer   (customer_id),    -- Customer "My Orders" page
    INDEX idx_orders_created_at (created_at),     -- Income report date-range query

    -- -------------------------------------------------------------------------
    -- FOREIGN KEYS
    -- -------------------------------------------------------------------------
    CONSTRAINT fk_orders_customer
        FOREIGN KEY (customer_id)
        REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT   -- Cannot delete a user who has historical orders
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci
COMMENT='Order headers: one row per purchase, with shipping snapshot and status.';


-- =============================================================================
-- TABLE 5: order_items
-- =============================================================================
-- PURPOSE  : Line-item breakdown of each order.
--            One row = one plant type within one order.
--
--            • `unit_price` is a PRICE SNAPSHOT at the time of purchase.
--              If the farmer changes the price later, historical invoices
--              remain correct (Obj 5 – accurate printable bills).
--
--            • `plant_name` is also snapshotted so invoices display the
--              correct name even if the plant is later renamed or deleted.
--
--            • `line_total` = unit_price × quantity stored to simplify
--              invoice queries — zero arithmetic needed in PHP (Obj 5).
-- =============================================================================
CREATE TABLE order_items (
    item_id       INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- FK to orders; CASCADE ensures items are removed with their parent order
    order_id      INT UNSIGNED    NOT NULL,

    -- FK to plants; SET NULL preserves the invoice row if plant is deleted
    plant_id      INT UNSIGNED    NULL,

    -- Plant name snapshot — invoice remains correct even if plant is renamed
    plant_name    VARCHAR(150)    NOT NULL,

    -- Number of units ordered for this specific plant in this order
    quantity      INT UNSIGNED    NOT NULL,

    -- Price per unit AT THE TIME OF ORDERING (price snapshot for Obj 5 accuracy)
    unit_price    DECIMAL(10, 2)  NOT NULL,

    -- Computed line total (unit_price × quantity), stored for fast invoice joins
    line_total    DECIMAL(10, 2)  NOT NULL,

    -- -------------------------------------------------------------------------
    -- CONSTRAINTS
    -- -------------------------------------------------------------------------
    PRIMARY KEY (item_id),

    CONSTRAINT chk_qty_positive        CHECK (quantity   >= 1),
    CONSTRAINT chk_unit_price_positive CHECK (unit_price >= 0),
    CONSTRAINT chk_line_total_positive CHECK (line_total >= 0),

    -- Performance indexes
    INDEX idx_order_items_order (order_id),  -- "Get all items for order #X" (invoice)
    INDEX idx_order_items_plant (plant_id),  -- "Units sold of plant #Y" (reports)

    -- -------------------------------------------------------------------------
    -- FOREIGN KEYS
    -- -------------------------------------------------------------------------
    CONSTRAINT fk_items_order
        FOREIGN KEY (order_id)
        REFERENCES orders (order_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,   -- Items auto-deleted when parent order is deleted

    CONSTRAINT fk_items_plant
        FOREIGN KEY (plant_id)
        REFERENCES plants (plant_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL   -- Invoice rows preserved even if plant record is deleted
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci
COMMENT='Order line items: price and name snapshots for accurate invoice history.';


-- =============================================================================
-- SEED DATA — Default Records
-- =============================================================================
-- These inserts populate the database with safe initial data so the
-- application is immediately functional after import.
-- ⚠️  Change all seed passwords before deploying to a production server!
-- =============================================================================

-- ── Seed: Plant Categories ────────────────────────────────────────────────────
INSERT INTO plant_categories (category_name, description) VALUES
('Indoor Varieties',   'Rubber plants suitable for low-light indoor environments.'),
('Outdoor Varieties',  'Hardy rubber plants that thrive in outdoor gardens.'),
('Rare Cultivars',     'Uncommon and collector rubber plant varieties.'),
('Starter Plants',     'Small, affordable plants ideal for beginners.');

-- ── Seed: Default Farmer Account ─────────────────────────────────────────────
-- Password: Farmer@123
-- Hash generated with: password_hash('Farmer@123', PASSWORD_BCRYPT, ['cost'=>12])
-- ⚠️  CHANGE THIS PASSWORD immediately after first login!
INSERT INTO users (full_name, email, password_hash, role, phone, address) VALUES
(
    'GreenThumb Admin',
    'farmer@greenthumb.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'farmer',
    '+601112345678',
    'GreenThumb Nursery, Jalan Hijau, 50000 Kuala Lumpur, Malaysia'
);

-- ── Seed: Sample Customer Account ────────────────────────────────────────────
-- Password: Customer@123
-- ⚠️  Remove or change this before production deployment!
INSERT INTO users (full_name, email, password_hash, role, phone, address) VALUES
(
    'Ahmad Bin Ali',
    'customer@greenthumb.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'customer',
    '+601198765432',
    'No 10, Jalan Bunga, 40150 Shah Alam, Selangor, Malaysia'
);

-- ── Seed: Sample Plant Inventory ─────────────────────────────────────────────
-- Created by farmer (user_id = 1); demonstrates Obj 1 catalog & Obj 3 CRUD
INSERT INTO plants
    (category_id, plant_name, scientific_name, description,
     price_per_unit, stock_quantity, min_order_qty, image_filename, created_by)
VALUES
(
    1,
    'Ficus Elastica Burgundy',
    'Ficus elastica "Burgundy"',
    'Iconic rubber plant with deep burgundy-red leaves. Tolerates low light — ideal for living rooms and offices. Grows up to 3m indoors. Water every 1-2 weeks.',
    45.00, 120, 1, NULL, 1
),
(
    1,
    'Ficus Elastica Tineke',
    'Ficus elastica "Tineke"',
    'Stunning variegated rubber plant with cream, pink, and green marbled leaves. A favourite among collectors. Requires bright indirect light.',
    65.00, 75, 1, NULL, 1
),
(
    1,
    'Ficus Elastica Robusta',
    'Ficus elastica "Robusta"',
    'Classic rubber plant with large, glossy dark-green leaves. Extremely hardy and low-maintenance. Perfect for first-time plant owners.',
    35.00, 200, 1, NULL, 1
),
(
    2,
    'Ficus Elastica Decora (Outdoor)',
    'Ficus elastica "Decora"',
    'Large outdoor variety that can grow into an impressive ornamental tree. Loves full sun and warm climates. Suited for Malaysian gardens.',
    80.00, 50, 1, NULL, 1
),
(
    3,
    'Ficus Elastica Abidjan',
    'Ficus elastica "Abidjan"',
    'Rare cultivar with near-black foliage. Highly sought after by collectors. Limited stock available. Prefers bright indirect light.',
    150.00, 20, 1, NULL, 1
),
(
    4,
    'Rubber Plant Seedling (10cm)',
    'Ficus elastica (seedling)',
    'Young healthy rubber plant seedling, approximately 10cm tall. Perfect starter plant for beginners. Supplied in a small nursery pot.',
    12.00, 500, 3, NULL, 1
);


-- =============================================================================
-- END OF SCHEMA
-- =============================================================================
-- Tables created:
--   1. users           – Auth & RBAC (Farmer / Customer)
--   2. plant_categories – Product grouping & catalog filter lookups
--   3. plants          – Product catalog with stock & pricing  (Obj 1 & 3)
--   4. orders          – Order headers with shipping snapshot  (Obj 2 & 5)
--   5. order_items     – Line items with price snapshot        (Obj 5)
--
-- Ready for STEP 2: config/db.php — PHP database connection file
-- =============================================================================
