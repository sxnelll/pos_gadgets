<?php
session_start();
include 'db_config.php';

// Set response header to JSON
header('Content-Type: application/json');

// 1. Basic Access and Method Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Ensure user is logged in as a customer
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Must be a logged-in customer.']);
    exit();
}

// Assuming we have a customer_id stored in the session for recording the order
// NOTE: For a real system, you would need to implement user login to get the ID.
// For now, we'll use a placeholder or assume the customer is the manager (if you haven't implemented a customer login table).
// Since we don't have a separate customer ID table yet, we'll use 0 as a placeholder ID.
$customer_id = 0; 

// Get JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);
$cart_items = $data['cart'];
$total_amount = $data['total'];

if (empty($cart_items)) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
    exit();
}

// Start transaction
$conn->begin_transaction();
try {
    // 2. Insert into orders table
    // Ensure you have a table named 'orders' with at least (order_id, customer_id, total_amount, order_date)
    $sql_order = "INSERT INTO orders (customer_id, total_amount, order_date) VALUES (?, ?, NOW())";
    $stmt_order = $conn->prepare($sql_order);
    $stmt_order->bind_param("id", $customer_id, $total_amount);
    
    if (!$stmt_order->execute()) {
        throw new Exception("Order insertion failed: " . $stmt_order->error);
    }
    
    $order_id = $conn->insert_id;

    // 3. Process Order Items and Update Stock
    $sql_item = "INSERT INTO order_items (order_id, product_id, quantity, price_at_sale) VALUES (?, ?, ?, ?)";
    $stmt_item = $conn->prepare($sql_item);

    $sql_stock_update = "UPDATE products SET stock_level = stock_level - ? WHERE product_id = ? AND stock_level >= ?";
    $stmt_stock = $conn->prepare($sql_stock_update);
    
    foreach ($cart_items as $item) {
        $product_id = $item['id'];
        $quantity = $item['quantity'];
        $price_at_sale = $item['price'];

        // a. Insert into order_items
        $stmt_item->bind_param("iiid", $order_id, $product_id, $quantity, $price_at_sale);
        if (!$stmt_item->execute()) {
            throw new Exception("Order item insertion failed for product " . $product_id);
        }

        // b. Update stock_level
        // Use quantity three times for the bound parameters: quantity, product_id, quantity (stock check)
        $stmt_stock->bind_param("iii", $quantity, $product_id, $quantity);
        if (!$stmt_stock->execute()) {
            throw new Exception("Stock update failed for product " . $product_id);
        }
        
        // c. Check if the update actually reduced the stock (prevent negative stock/over-selling)
        if ($stmt_stock->affected_rows === 0) {
            throw new Exception("Stock constraint violation or item out of stock for product " . $product_id);
        }
    }

    // 4. Commit Transaction
    $conn->commit();
    
    // Close prepared statements
    $stmt_order->close();
    $stmt_item->close();
    $stmt_stock->close();

    echo json_encode(['success' => true, 'message' => 'Order processed successfully. Order ID: ' . $order_id]);

} catch (Exception $e) {
    // 5. Rollback on Failure
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
}

$conn->close();
?>