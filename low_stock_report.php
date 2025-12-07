<?php
session_start();
include 'db_config.php';

// --- ACCESS CONTROL: MANAGER ONLY ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'manager') {
    header("Location: access.php");
    exit();
}
// --- END ACCESS CONTROL ---

// --- 1. Fetch Low Stock Data ---
// Query to find products where stock is at or below the reorder point
$sql = "
    SELECT 
        product_id,
        name,
        sku,
        stock_level,
        reorder_point,
        cost,
        price
    FROM 
        products
    WHERE 
        stock_level <= reorder_point 
    ORDER BY 
        stock_level ASC";

$result = $conn->query($sql);
$low_stock_products = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $row['order_quantity'] = $row['reorder_point'] * 2; // Suggested order quantity (e.g., double the reorder point)
        $low_stock_products[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Low Stock Report</title>
    <style>
        /* --- GLOBAL & LAYOUT STYLES --- */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f4f7f9; color: #333; }
        .container { max-width: 1200px; margin: 30px auto; padding: 30px; border-radius: 12px; background: white; box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1); }
        h2 { color: #d32f2f; border-bottom: 3px solid #ffcdd2; padding-bottom: 15px; margin-bottom: 25px; font-weight: 600; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background-color: #3f51b5; padding: 15px 30px; color: white; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .nav-title { font-size: 1.5em; font-weight: 700; }
        .nav-links { list-style: none; display: flex; margin: 0; padding: 0; }
        .nav-links li { margin-left: 20px; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; padding: 8px 12px; border-radius: 6px; transition: background-color 0.3s; }
        .nav-links a:hover { background-color: #5c6bc0; }
        
        /* --- TABLE STYLES --- */
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 20px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 0.95em; }
        th { background-color: #f44336; color: white; font-weight: 600; text-transform: uppercase; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr:hover { background-color: #ffebee; }
        
        /* --- STATUS AND ACTION STYLES --- */
        .low-stock { background-color: #ffdddd !important; font-weight: 600; color: #d32f2f; }
        .order-btn { padding: 8px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; transition: background-color 0.2s; font-size: 0.9em; font-weight: 500; }
        .order-btn:hover { background-color: #45a049; }
        .alert { padding: 15px; background-color: #fff8e1; border: 1px solid #ffb300; border-radius: 6px; color: #555; margin-bottom: 20px; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-title">💻 POS Gadget Manager</div>
    <ul class="nav-links">
        <li><a href="index.php">📦 Inventory</a></li>
        <li><a href="product_management.php">🛠️ Products</a></li>
        <li><a href="sales_report.php">📈 Sales Report</a></li>
        <li><a href="low_stock_report.php">⚠️ Low Stock Report</a></li>
    </ul>
</nav>

<div class="container">
    <h2>⚠️ Low Stock Inventory Report</h2>
    <div class="alert">
        This report shows all products where the **Stock Level** is less than or equal to the **Reorder Point**. These items require immediate attention.
    </div>
    
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Product Name</th>
                <th>Current Stock</th>
                <th>Reorder Point</th>
                <th>Cost (Per Unit)</th>
                <th>Suggested Order Qty</th>
                <th>Estimated Order Cost</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (!empty($low_stock_products)) {
                foreach($low_stock_products as $product) {
                    $estimated_cost = $product['order_quantity'] * $product['cost'];
                    
                    echo "<tr class='low-stock'>";
                    echo "<td>" . htmlspecialchars($product["sku"]) . "</td>";
                    echo "<td>" . htmlspecialchars($product["name"]) . "</td>";
                    echo "<td>" . number_format($product["stock_level"], 0) . "</td>";
                    echo "<td>" . number_format($product["reorder_point"], 0) . "</td>";
                    echo "<td>₱" . number_format($product["cost"], 2) . "</td>";
                    echo "<td>**" . number_format($product["order_quantity"], 0) . "**</td>";
                    echo "<td>₱" . number_format($estimated_cost, 2) . "</td>";
                    echo "<td><a href='product_management.php?edit_id=" . $product["product_id"] . "' class='order-btn'>Place Order</a></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8' style='text-align: center; color: #4CAF50; font-weight: bold;'>🎉 All products are currently stocked above their reorder points!</td></tr>";
            }
            ?>
        </tbody>
    </table>
    
</div>

</body>
</html>