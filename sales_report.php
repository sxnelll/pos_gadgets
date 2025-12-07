<?php
session_start();
include 'db_config.php';

// --- ACCESS CONTROL: MANAGER ONLY ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'manager') {
    header("Location: access.php");
    exit();
}
// --- END ACCESS CONTROL ---

// --- 1. Fetch Sales Data (Overall Summary) ---
// Use UNION ALL to combine transactions from 'orders' (Customer) and 'sales' (POS)
$sql_sales = "
    (
        -- Transactions from Customer Checkout (orders/order_items)
        SELECT 
            o.order_id AS id,
            o.order_date AS date,
            o.total_amount AS amount,
            o.customer_id, 
            'Online Checkout' AS source,
            NULL AS payment_method, -- Not tracked on the customer side currently, use NULL placeholder
            (SELECT COUNT(item_id) FROM order_items WHERE order_id = o.order_id) AS total_items_sold
        FROM 
            orders o
    )
    UNION ALL
    (
        -- Transactions from POS Terminal (sales/sale_items)
        SELECT 
            s.sale_id AS id,
            s.sale_date AS date,
            s.total_amount AS amount,
            s.customer_id, 
            'POS Terminal' AS source,
            s.payment_method, 
            (SELECT COUNT(item_id) FROM sale_items WHERE sale_id = s.sale_id) AS total_items_sold
        FROM 
            sales s
    )
    ORDER BY date DESC
";

$result_sales = $conn->query($sql_sales);
$sales_data = [];
$total_revenue = 0;
$total_transactions = 0;
$total_gross_profit = 0; 

if ($result_sales->num_rows > 0) {
    while($row = $result_sales->fetch_assoc()) {
        $total_revenue += $row['amount']; // Use 'amount' alias
        $total_transactions++;
        
        // --- Dynamic Cost Calculation ---
        $transaction_id = $conn->real_escape_string($row['id']);
        $source_table = ($row['source'] === 'Online Checkout') ? 'order_items' : 'sale_items';
        $id_column = ($row['source'] === 'Online Checkout') ? 'order_id' : 'sale_id';

        // SQL to calculate profit dynamically based on source
        $sql_profit = "
            SELECT SUM(oi.quantity * p.cost) AS total_cost 
            FROM $source_table oi
            JOIN products p ON oi.product_id = p.product_id
            WHERE oi.$id_column = '$transaction_id'";
            
        $result_profit = $conn->query($sql_profit);
        $total_cost = $result_profit->fetch_assoc()['total_cost'] ?? 0;
        
        $row['total_cost'] = $total_cost;
        $row['gross_profit'] = $row['amount'] - $total_cost;
        
        $total_gross_profit += $row['gross_profit']; 
        
        $sales_data[] = $row; 
    }
}
$average_transaction_value = ($total_transactions > 0) ? $total_revenue / $total_transactions : 0;
$overall_gross_margin = ($total_revenue > 0) ? ($total_gross_profit / $total_revenue) * 100 : 0;


// --- 2. Fetch Details (for View Details link) ---
$details_html = "";
if (isset($_GET['id'])) { 
    $transaction_id = $conn->real_escape_string($_GET['id']);
    $source = $conn->real_escape_string($_GET['source'] ?? 'Online Checkout'); 

    // Determine tables/columns based on source
    $items_table = ($source === 'Online Checkout') ? 'order_items' : 'sale_items';
    $items_id = ($source === 'Online Checkout') ? 'order_id' : 'sale_id';
    $price_column = ($source === 'Online Checkout') ? 'price_at_sale' : 'unit_price';
    
    // Fix for "Unknown column 'payment_method'" error: 
    // Dynamically build the SELECT query based on the table name.
    if ($source === 'Online Checkout') {
        // 'orders' table only has order_date and no payment_method column, so we select NULL for payment_method
        $sql_info = "SELECT order_date AS transaction_date, NULL AS payment_method, customer_id FROM orders WHERE order_id = '$transaction_id'";
    } else {
        // 'sales' table has sale_date and payment_method column
        $sql_info = "SELECT sale_date AS transaction_date, payment_method, customer_id FROM sales WHERE sale_id = '$transaction_id'";
    }

    $info_result = $conn->query($sql_info);
    $transaction_info = $info_result->fetch_assoc();
    
    // --- Error Check ---
    if (!$transaction_info) {
        $details_html = "<p style='color: red;'>Error: Could not retrieve transaction information for ID $transaction_id. Check database connection or ID existence.</p>";
    } else {
        
        $sql_details = "
            SELECT 
                oi.quantity,
                oi.$price_column AS unit_price, 
                p.sku,
                p.name,
                p.cost, 
                (oi.quantity * oi.$price_column) AS line_revenue,
                (oi.quantity * p.cost) AS line_cost 
            FROM 
                $items_table oi
            JOIN 
                products p ON oi.product_id = p.product_id
            WHERE 
                oi.$items_id = '$transaction_id'"; 
                
        $result_details = $conn->query($sql_details);
        
        $sale_total_revenue = 0;
        $sale_total_cost = 0;
        
        if ($result_details->num_rows > 0) {
            $customer_display = ($transaction_info['customer_id'] > 0) ? 'ID: ' . htmlspecialchars($transaction_info['customer_id']) : 'N/A';
            $payment_method_display = $transaction_info['payment_method'] ?? $source; // If payment_method is NULL, display the source

            $details_html .= "<h3>Details for Transaction ID: $transaction_id 
                                <span style='font-size: 0.8em; font-weight: normal; color: #3f51b5;'>
                                    (Source: {$source})
                                </span>
                              </h3>";
            
            $details_html .= "<p style='margin-bottom: 15px;'>
                                <strong>Date:</strong> " . date("Y-m-d H:i", strtotime($transaction_info['transaction_date'])) . " | 
                                <strong>Method:</strong> " . $payment_method_display . "
                              </p>";

            $details_html .= "<table class='details-table'><thead><tr>
                                <th>Product (SKU)</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Line Revenue</th>
                                <th>Line Cost</th>
                                <th>Gross Profit</th>
                              </tr></thead><tbody>";
                              
            while ($detail = $result_details->fetch_assoc()) {
                $line_profit = $detail['line_revenue'] - $detail['line_cost'];
                $sale_total_revenue += $detail['line_revenue'];
                $sale_total_cost += $detail['line_cost'];
                
                $details_html .= "<tr>";
                $details_html .= "<td>" . htmlspecialchars($detail['name']) . " (" . htmlspecialchars($detail['sku']) . ")</td>";
                $details_html .= "<td>" . number_format($detail['quantity'], 0) . "</td>";
                $details_html .= "<td>₱" . number_format($detail['unit_price'], 2) . "</td>";
                $details_html .= "<td>₱" . number_format($detail['line_revenue'], 2) . "</td>";
                $details_html .= "<td>₱" . number_format($detail['line_cost'], 2) . "</td>";
                $details_html .= "<td>**₱" . number_format($line_profit, 2) . "**</td>";
                $details_html .= "</tr>";
            }
            
            $sale_gross_profit = $sale_total_revenue - $sale_total_cost;
            $sale_gross_margin = ($sale_total_revenue > 0) ? ($sale_gross_profit / $sale_total_revenue) * 100 : 0;
            
            $details_html .= "<tr class='summary-row'>";
            $details_html .= "<td colspan='3'>**TOTALS**</td>";
            $details_html .= "<td>**₱" . number_format($sale_total_revenue, 2) . "**</td>";
            $details_html .= "<td>**₱" . number_format($sale_total_cost, 2) . "**</td>";
            $details_html .= "<td>**₱" . number_format($sale_gross_profit, 2) . "**<br><small>(" . number_format($sale_gross_margin, 2) . "% Margin)</small></td>";
            $details_html .= "</tr>";
            
            $details_html .= "</tbody></table>";
        } else {
            $details_html = "<p>No items found for this transaction ID.</p>";
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Report</title>
    <style>
        /* --- GLOBAL & LAYOUT STYLES --- */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f4f7f9; color: #333; }
        .container { max-width: 1200px; margin: 30px auto; padding: 30px; border-radius: 12px; background: white; box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1); }
        h2 { color: #3f51b5; border-bottom: 3px solid #e0e0e0; padding-bottom: 15px; margin-bottom: 25px; font-weight: 600; }
        h3 { color: #555; margin-top: 25px; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background-color: #3f51b5; padding: 15px 30px; color: white; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .nav-title { font-size: 1.5em; font-weight: 700; }
        .nav-links { list-style: none; display: flex; margin: 0; padding: 0; }
        .nav-links li { margin-left: 20px; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; padding: 8px 12px; border-radius: 6px; transition: background-color 0.3s; }
        .nav-links a:hover { background-color: #5c6bc0; }
        .analytics-dashboard { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi-box { flex: 1; background-color: #e3f2fd; border-radius: 8px; padding: 20px; border-bottom: 4px solid #3f51b5; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05); transition: transform 0.2s; }
        .kpi-box.profit { background-color: #e8f5e9; border-bottom-color: #4CAF50; }
        .kpi-box:hover { transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1); }
        .kpi-title { font-size: 0.9em; color: #555; margin-bottom: 8px; text-transform: uppercase; font-weight: 600; }
        .kpi-value { font-size: 2.2em; font-weight: 700; color: #0d47a1; }
        .kpi-box.profit .kpi-value { color: #2e7d32; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 20px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 0.95em; }
        th { background-color: #5c6bc0; color: white; font-weight: 600; text-transform: uppercase; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr:hover { background-color: #f0f8ff; }
        .details-table { margin-top: 10px; background-color: #fff; border: 1px solid #ddd; }
        .details-table th { background-color: #9fa8da; color: #333; }
        .summary-row { font-weight: bold; background-color: #fff3e0 !important; border-top: 2px solid #ffb74d; }
        .view-btn { padding: 8px 12px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; transition: background-color 0.2s; font-size: 0.9em; }
        .view-btn:hover { background-color: #45a049; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-title">💻 POS Gadget Manager</div>
    <ul class="nav-links">
        <li><a href="index.php">📦 Inventory</a></li>
        <li><a href="sales_report.php">📈 Sales Report</a></li>
        <li><a href="low_stock_report.php">⚠️ Low Stock Report</a></li>
    </ul>
</nav>

<div class="container">
    <h2>📈 Sales Report & Analytics</h2>

    <div class="analytics-dashboard">
        
        <div class="kpi-box">
            <div class="kpi-title">Total Revenue</div>
            <div class="kpi-value">₱<?php echo number_format($total_revenue, 2); ?></div>
        </div>

        <div class="kpi-box profit">
            <div class="kpi-title">Total Gross Profit</div>
            <div class="kpi-value">₱<?php echo number_format($total_gross_profit, 2); ?></div>
        </div>
        
        <div class="kpi-box profit">
            <div class="kpi-title">Gross Margin</div>
            <div class="kpi-value"><?php echo number_format($overall_gross_margin, 2); ?>%</div>
        </div>

        <div class="kpi-box">
            <div class="kpi-title">Total Transactions</div>
            <div class="kpi-value"><?php echo number_format($total_transactions, 0); ?></div>
        </div>
        
    </div>
    
    <h3>All Transactions</h3>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Source</th>
                <th>Date/Time</th>
                <th>Revenue</th>
                <th>Gross Profit</th>
                <th>Method</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (!empty($sales_data)) {
                foreach($sales_data as $row) {
                    $method_display = $row['payment_method'] ?: $row['source'];
                    
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row["id"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["source"]) . "</td>";
                    echo "<td>" . date("Y-m-d H:i", strtotime($row["date"])) . "</td>";
                    echo "<td>₱" . number_format($row["amount"], 2) . "</td>";
                    echo "<td>**₱" . number_format($row["gross_profit"], 2) . "**</td>"; 
                    echo "<td>" . $method_display . "</td>";
                    // Pass both 'id' and 'source' to the detail link
                    echo "<td><a href='sales_report.php?id=" . $row["id"] . "&source=" . urlencode($row['source']) . "' class='view-btn'>View Details</a></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='7' style='text-align: center;'>No transactions recorded yet.</td></tr>";
            }
            ?>
        </tbody>
    </table>
    
    <hr>

    <div id="sale-details">
        <?php echo $details_html; ?>
    </div>
</div>

</body>
</html>