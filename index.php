<?php
session_start(); 
include 'db_config.php'; // Includes DB_SERVER, DB_USERNAME, etc.

$message = "";

// --- 1. HANDLE ADD/EDIT FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Basic sanitization
    $sku = $conn->real_escape_string($_POST['sku']);
    $name = $conn->real_escape_string($_POST['name']);
    $description = $conn->real_escape_string($_POST['description']);
    $price = $conn->real_escape_string($_POST['price']);
    $cost = $conn->real_escape_string($_POST['cost']);
    $stock_level = $conn->real_escape_string($_POST['stock_level']);
    $reorder_point = $conn->real_escape_string($_POST['reorder_point']); // New: Added Reorder Point
    $product_id = isset($_POST['product_id']) ? $conn->real_escape_string($_POST['product_id']) : null;
    $image_path = "";
    
    // --- Image Upload Handling ---
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $target_dir = "images/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $imageFileType = strtolower(pathinfo($_FILES["product_image"]["name"], PATHINFO_EXTENSION));
        // Generate a unique filename using timestamp and SKU
        $image_path = $target_dir . $sku . "-" . time() . "." . $imageFileType;
        
        $check = getimagesize($_FILES["product_image"]["tmp_name"]);
        if($check !== false) {
            if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $image_path)) {
                // Success: $image_path now holds the file path
            } else {
                $message = "❌ Error uploading image.";
                $image_path = ""; // Reset path if upload fails
            }
        } else {
            $message = "❌ File is not an image.";
            $image_path = "";
        }
    } else if ($product_id && isset($_POST['old_image_path'])) {
        // For EDIT, if no new file is uploaded, retain the old path
        $image_path = $conn->real_escape_string($_POST['old_image_path']);
    }


    if ($product_id) {
        // Update product
        $sql = "UPDATE products SET 
                sku='$sku', 
                name='$name', 
                description='$description', 
                price='$price', 
                cost='$cost',
                stock_level='$stock_level',
                reorder_point='$reorder_point',
                image_path='$image_path'
                WHERE product_id='$product_id'";
        $message = $conn->query($sql) ? "✅ Product **$name** updated successfully!" : "❌ Error updating product: " . $conn->error;
    } else {
        // Add new product
        $sql = "INSERT INTO products (sku, name, description, price, cost, stock_level, reorder_point, image_path) 
                VALUES ('$sku', '$name', '$description', '$price', '$cost', '$stock_level', '$reorder_point', '$image_path')";
        $message = $conn->query($sql) ? "✅ New product **$name** added successfully!" : "❌ Error adding product: " . $conn->error;
    }
} 

// --- 2. HANDLE DELETE ACTION ---
if (isset($_GET['delete_id'])) {
    $delete_id = $conn->real_escape_string($_GET['delete_id']);
    // Optional: Delete the image file before deleting the record
    $sql_image = "SELECT image_path FROM products WHERE product_id = '$delete_id'";
    $result_image = $conn->query($sql_image);
    if ($result_image->num_rows > 0) {
        $row = $result_image->fetch_assoc();
        if (!empty($row['image_path']) && file_exists($row['image_path'])) {
            unlink($row['image_path']);
        }
    }
    
    // Delete the record
    $sql = "DELETE FROM products WHERE product_id='$delete_id'";
    $message = $conn->query($sql) ? "✅ Product deleted successfully!" : "❌ Error deleting product: " . $conn->error;
}


// --- 3. FETCH ALL DATA FOR TABLE DISPLAY (NO PHP search filter) ---
$sql = "SELECT product_id, sku, name, description, price, cost, stock_level, reorder_point, image_path FROM products ORDER BY product_id DESC";
$result = $conn->query($sql);

$products = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// --- 4. Prepare Edit Data (Always done before the HTML output) ---
$edit_product = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $conn->real_escape_string($_GET['edit_id']);
    // Search in the $products array for the item to edit, avoiding another DB query
    foreach ($products as $p) {
        if ($p['product_id'] == $edit_id) {
            $edit_product = $p;
            break;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Management</title>
    <style>
        /* ... (CSS STYLES REMAINS THE SAME) ... */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f4f7f9; color: #333; }
        .container { max-width: 1300px; margin: 30px auto; padding: 30px; border-radius: 12px; background: white; box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1); }
        h2 { color: #3f51b5; border-bottom: 3px solid #e0e0e0; padding-bottom: 15px; margin-bottom: 25px; font-weight: 600; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background-color: #3f51b5; padding: 15px 30px; color: white; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); width: 100%; }
        .nav-title { font-size: 1.5em; font-weight: 700; }
        .nav-links { list-style: none; display: flex; margin: 0; padding: 0; }
        .nav-links li { margin-left: 20px; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; padding: 8px 12px; border-radius: 6px; transition: background-color 0.3s; }
        .nav-links a:hover { background-color: #5c6bc0; }

        /* --- FORM & INPUT STYLES --- */
        .form-section, .list-section { margin-bottom: 40px; padding: 25px; border: 1px solid #e0e0e0; border-radius: 8px; }
        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
        input[type="text"], input[type="number"], textarea, input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        textarea { resize: vertical; }
        input:focus, textarea:focus { border-color: #3f51b5; outline: none; }
        .btn-primary { background-color: #3f51b5; color: white; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; font-size: 1em; font-weight: bold; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #303f9f; }
        .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: 600; }
        .message.success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .message.error { background-color: #fbebee; color: #c62828; border: 1px solid #ef9a9a; }
        
        /* --- TABLE STYLES --- */
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 20px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 0.95em; }
        th { background-color: #5c6bc0; color: white; font-weight: 600; text-transform: uppercase; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr:hover { background-color: #f0f8ff; }
        .btn-edit, .btn-delete { padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9em; margin-right: 5px; }
        .btn-edit { background-color: #ff9800; color: white; }
        .btn-edit:hover { background-color: #fb8c00; }
        .btn-delete { background-color: #f44336; color: white; }
        .btn-delete:hover { background-color: #e53935; }
        .stock-low { color: #d32f2f; font-weight: bold; }
        .stock-ok { color: #388e3c; }
        
        /* --- IMAGE DISPLAY --- */
        .product-thumbnail { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid #eee; }
        .current-image-preview { margin-top: 10px; }
        .current-image-preview img { width: 100px; height: 100px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
        
        /* --- SEARCH STYLES --- */
        .search-input { width: 350px; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 1em; margin-bottom: 20px; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-title">💻 POS Gadget Manager</div>
    <ul class="nav-links">
        <li><a href="index.php">📦 Inventory</a></li>
        <li><a href="pos_terminal.php">🛒 POS Terminal</a></li>
        <li><a href="sales_report.php">📈 Sales Report</a></li>
        <li><a href="low_stock_report.php">⚠️ Low Stock Report</a></li>
    </ul>
</nav>

<div class="container">
    <h2>📦 Inventory Management</h2>

    <?php if ($message): ?>
        <div class="message <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="form-section">
        <h3><?php echo isset($_GET['edit_id']) ? '✏️ Edit Product' : '➕ Add New Product'; ?></h3>
        
        <form method="POST" action="index.php" enctype="multipart/form-data">
            <input type="hidden" name="product_id" value="<?php echo $edit_product['product_id'] ?? ''; ?>">
            <input type="hidden" name="old_image_path" value="<?php echo $edit_product['image_path'] ?? ''; ?>">

            <div class="form-grid">
                <div>
                    <label for="sku">SKU</label>
                    <input type="text" id="sku" name="sku" value="<?php echo $edit_product['sku'] ?? ''; ?>" required>
                </div>
                <div>
                    <label for="name">Product Name</label>
                    <input type="text" id="name" name="name" value="<?php echo $edit_product['name'] ?? ''; ?>" required>
                </div>
                <div>
                    <label for="stock_level">Stock Level</label>
                    <input type="number" id="stock_level" name="stock_level" value="<?php echo $edit_product['stock_level'] ?? 0; ?>" min="0" required>
                </div>
                <div>
                    <label for="reorder_point">Reorder Point</label>
                    <input type="number" id="reorder_point" name="reorder_point" value="<?php echo $edit_product['reorder_point'] ?? 5; ?>" min="0" required>
                </div>
                <div>
                    <label for="price">Selling Price (₱)</label>
                    <input type="number" id="price" name="price" value="<?php echo $edit_product['price'] ?? 0.00; ?>" step="0.01" min="0" required>
                </div>
                <div>
                    <label for="cost">Unit Cost (₱)</label>
                    <input type="number" id="cost" name="cost" value="<?php echo $edit_product['cost'] ?? 0.00; ?>" step="0.01" min="0" required>
                </div>
                <div style="grid-column: span 2;">
                    <label for="product_image">Product Image</label>
                    <input type="file" id="product_image" name="product_image" accept="image/*">
                    <?php if (isset($edit_product['image_path']) && !empty($edit_product['image_path'])): ?>
                        <div class="current-image-preview">
                            Current: <img src="<?php echo htmlspecialchars($edit_product['image_path']); ?>" alt="Current Product Image">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3"><?php echo $edit_product['description'] ?? ''; ?></textarea>
            
            <div style="margin-top: 20px; text-align: right;">
                <button type="submit" class="btn-primary">
                    <?php echo isset($_GET['edit_id']) ? 'Save Changes' : 'Add Product'; ?>
                </button>
                <?php if (isset($_GET['edit_id'])): ?>
                    <a href="index.php" class="btn-primary" style="background-color: #9e9e9e; text-decoration: none;">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="list-section">
        <h3>📋 Product List</h3>
        
        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="🔍 Search by Product Name or SKU..." class="search-input">

        <table id="productTable">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th>Price (₱)</th>
                    <th>Cost (₱)</th>
                    <th>Stock</th>
                    <th>Reorder Point</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($products)) {
                    foreach($products as $product) {
                        // Check against the reorder_point instead of a fixed number (5)
                        $stock_class = $product['stock_level'] <= $product['reorder_point'] ? 'stock-low' : 'stock-ok';
                        
                        $image_tag = !empty($product['image_path']) && file_exists($product['image_path']) 
                            ? "<img src='".htmlspecialchars($product['image_path'])."' class='product-thumbnail' alt='Product Image'>"
                            : "<div class='product-thumbnail' style='background-color:#eee; display:flex; align-items:center; justify-content:center; font-size:10px; color:#999;'>No Image</div>";
                        
                        echo "<tr>";
                        echo "<td>".$image_tag."</td>";
                        echo "<td>" . htmlspecialchars($product["sku"]) . "</td>";
                        echo "<td>" . htmlspecialchars($product["name"]) . "</td>";
                        echo "<td>₱" . number_format($product["price"], 2) . "</td>";
                        echo "<td>₱" . number_format($product["cost"], 2) . "</td>";
                        echo "<td class='$stock_class'>" . number_format($product["stock_level"], 0) . "</td>";
                        echo "<td>" . number_format($product["reorder_point"], 0) . "</td>";
                        echo "<td>";
                        echo "<a href='index.php?edit_id=" . $product["product_id"] . "' class='btn-edit'>Edit</a>";
                        echo "<a href='index.php?delete_id=" . $product["product_id"] . "' class='btn-delete' onclick='return confirm(\"Are you sure you want to delete this product?\")'>Delete</a>";
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='8' style='text-align: center;'>No products found. Start by adding one above!</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function filterTable() {
        // Declare variables
        let input, filter, table, tr, td_sku, td_name, i, txtValue;
        
        // 1. Get the input field and convert its value to uppercase for case-insensitive search
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        
        // 2. Get the table and all rows
        table = document.getElementById("productTable");
        tr = table.getElementsByTagName("tr");

        // 3. Loop through all table rows, starting from index 1 (to skip the header row)
        for (i = 1; i < tr.length; i++) {
            let matchFound = false;

            // Check SKU column (Index 1: Image is index 0, SKU is index 1)
            td_sku = tr[i].getElementsByTagName("td")[1];
            if (td_sku) {
                txtValue = td_sku.textContent || td_sku.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    matchFound = true;
                }
            }

            // Check Product Name column (Index 2)
            td_name = tr[i].getElementsByTagName("td")[2];
            if (!matchFound && td_name) {
                txtValue = td_name.textContent || td_name.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    matchFound = true;
                }
            }

            // 4. Show or hide the row based on whether a match was found
            if (matchFound) {
                tr[i].style.display = ""; // Show the row
            } else {
                tr[i].style.display = "none"; // Hide the row
            }
        }
    }
</script>

</body>
</html>