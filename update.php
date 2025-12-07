<?php
include 'db_config.php';

$message = '';
$errors = [];
$product = null;
$product_id = $_GET['id'] ?? null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- POST: 1. Data Retrieval ---
    $product_id = $conn->real_escape_string($_POST['product_id'] ?? '');
    $sku = $conn->real_escape_string($_POST['sku'] ?? '');
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $price = $_POST['price'] ?? '';
    $cost = $_POST['cost'] ?? '';
    $stock_level = $_POST['stock_level'] ?? '';
    $current_image_path = $conn->real_escape_string($_POST['current_image_path'] ?? ''); // Hidden field for current path
    $image_path_to_save = $current_image_path; 

    // --- POST: 2. Validation ---
    
    if (empty($product_id)) { $errors[] = "Product ID is missing, cannot save."; }
    if (empty($sku)) { $errors[] = "SKU is required."; }
    if (empty($name)) { $errors[] = "Product Name is required."; }
    if (!is_numeric($price) || $price < 0) { $errors[] = "Price must be a valid non-negative number."; }
    if (!is_numeric($cost) || $cost < 0) { $errors[] = "Cost must be a valid non-negative number."; }
    if (!filter_var($stock_level, FILTER_VALIDATE_INT) || $stock_level < 0) { $errors[] = "Stock Level must be a whole non-negative number."; }

    // Check for Duplicate SKU (Ignoring the current product)
    if (empty($errors) && !empty($sku)) {
        $sql_check = "SELECT product_id FROM products WHERE sku = '$sku' AND product_id != '$product_id'";
        $result_check = $conn->query($sql_check);

        if ($result_check->num_rows > 0) {
            $errors[] = "The SKU '$sku' already exists on another product. Please use a unique SKU.";
        }
    }
    
    // --- 3. Image Upload Handling (If a new file is uploaded) ---
    if (empty($errors) && isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        $new_filename = $sku . '.' . strtolower($file_extension); 
        $target_file = $target_dir . $new_filename;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Basic image checks
        $check = getimagesize($_FILES['product_image']['tmp_name']);
        if($check === false) { $errors[] = "File is not an image."; }
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
            $errors[] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        }
        
        if (empty($errors)) {
            // Delete old file if it exists and is different from the new one
            if (!empty($current_image_path) && file_exists($current_image_path)) {
                unlink($current_image_path); 
            }
            
            // Move the new file
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                $image_path_to_save = $conn->real_escape_string($target_file);
            } else {
                $errors[] = "Sorry, there was an error uploading your new file.";
            }
        }
    }

    // --- POST: 4. Update if valid ---
    if (empty($errors)) {
        $sql = "UPDATE products SET
                sku='$sku',
                name='$name',
                price='$price',
                cost='$cost',
                stock_level='$stock_level',
                image_path='$image_path_to_save' 
                WHERE product_id='$product_id'";

        if ($conn->query($sql) === TRUE) {
            $message = "✅ Product updated successfully: **" . htmlspecialchars($name) . "**";
            header("Location: index.php?message=" . urlencode($message));
            exit();
        } else {
            $message = "❌ Error updating product: " . $conn->error;
        }
    } else {
        $message = "❌ **Update Failed:** Please fix the following errors.";
    }

} 

// --- GET: Load existing product data for the form ---
if ($product_id) {
    // Select the new 'image_path' column
    $sql = "SELECT product_id, sku, name, price, cost, stock_level, image_path FROM products WHERE product_id = '$product_id'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc(); 
        
        // If form submission failed (errors exist), re-inject the POST data
        if (!empty($errors)) {
            $product['sku'] = htmlspecialchars($_POST['sku'] ?? '');
            $product['name'] = htmlspecialchars($_POST['name'] ?? '');
            $product['price'] = htmlspecialchars($_POST['price'] ?? '');
            $product['cost'] = htmlspecialchars($_POST['cost'] ?? '');
            $product['stock_level'] = htmlspecialchars($_POST['stock_level'] ?? '');
            // Restore current image path for the hidden field if error occurred
            $product['image_path'] = htmlspecialchars($current_image_path); 
        }
    } else {
        $message = "❌ Product ID not found.";
    }
} else {
    $message = "❌ No Product ID specified for editing.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Product</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f9; }
        .container { max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { color: #f0ad4e; border-bottom: 2px solid #f0ad4e; padding-bottom: 10px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input[type="text"], input[type="number"], input[type="file"] { width: 95%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; }
        button { background-color: #f0ad4e; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        .error { color: red; font-weight: bold; margin-top: 10px; }
        .error-list { color: red; margin-left: 20px; }
        .current-image { max-width: 150px; height: auto; margin-top: 10px; border: 1px solid #ccc; }
    </style>
</head>
<body>

<div class="container">
    <h2>✏️ Edit Gadget (ID: <?php echo htmlspecialchars($product_id); ?>)</h2>
    <p><a href="index.php">← Back to Inventory</a></p>

    <?php if (!empty($message)): ?>
        <p class="error"><?php echo $message; ?></p>
        <?php if (!empty($errors)): ?>
            <ul class="error-list">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($product): ?>
    <form method="POST" action="update.php?id=<?php echo htmlspecialchars($product_id); ?>" enctype="multipart/form-data">
        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">
        <input type="hidden" name="current_image_path" value="<?php echo htmlspecialchars($product['image_path'] ?? ''); ?>">

        <label for="sku">SKU (Stock Keeping Unit):</label>
        <input type="text" name="sku" id="sku" required value="<?php echo htmlspecialchars($product['sku']); ?>">

        <label for="name">Product Name:</label>
        <input type="text" name="name" id="name" required value="<?php echo htmlspecialchars($product['name']); ?>">

        <label for="cost">Cost (Internal Purchase Price ₱):</label>
        <input type="number" name="cost" id="cost" step="0.01" required value="<?php echo htmlspecialchars($product['cost']); ?>">

        <label for="price">Price (Selling Price ₱):</label>
        <input type="number" name="price" id="price" step="0.01" required value="<?php echo htmlspecialchars($product['price']); ?>">

        <label for="stock_level">Stock Level (Qty):</label>
        <input type="number" name="stock_level" id="stock_level" required value="<?php echo htmlspecialchars($product['stock_level']); ?>">
        
        <label for="product_image">Product Image (New Upload):</label>
        <?php if (!empty($product['image_path'])): ?>
            <p>Current Image:</p>
            <img src="<?php echo htmlspecialchars($product['image_path']); ?>" class="current-image" alt="Current Product Image">
            <small>Upload a new file below to replace the current image.</small>
        <?php endif; ?>
        <input type="file" name="product_image" id="product_image" accept="image/jpeg, image/png, image/gif">

        <button type="submit">Save Changes</button>
    </form>
    <?php endif; ?>
</div>

</body>
</html>