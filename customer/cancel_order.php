<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    exit('Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    $customer_id = (int)$_SESSION['user_id'];

   // 🚨 Corrected to o.order_status
    $order = db_query_one(
        "SELECT order_id, order_status FROM orders WHERE order_id = ? AND customer_id = ?",
        "ii",
        [$order_id, $customer_id]
    );

    if ($order && strtolower($order['order_status']) === 'pending') {
        try {
            global $conn;
            $conn->begin_transaction();

            $items = db_query("SELECT plant_id, quantity FROM order_items WHERE order_id = ?", "i", [$order_id]);

            foreach ($items as $item) {
                db_query(
                    "UPDATE plants SET stock_quantity = stock_quantity + ? WHERE plant_id = ?",
                    "ii",
                    [$item['quantity'], $item['plant_id']]
                );
            }

            db_query("DELETE FROM order_items WHERE order_id = ?", "i", [$order_id]);
            db_query("DELETE FROM orders WHERE order_id = ?", "i", [$order_id]);

            $conn->commit();
            echo "<script>alert('Order cancelled successfully!'); window.location='my_orders.php';</script>";
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location='my_orders.php';</script>";
            exit;
        }
    } else {
        echo "<script>alert('This order cannot be cancelled.'); window.location='my_orders.php';</script>";
        exit;
    }
} else {
    header('Location: my_orders.php');
    exit;
}
