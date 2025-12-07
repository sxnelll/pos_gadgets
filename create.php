<?php
include 'db_config.php';

$message = '';
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- 1. Data Retrieval ---
    $sku = $conn->real_escape_string($_POST['sku'] ?? '');
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $price = $_POST['price'] ?? '';
    $cost = $_POST['cost'] ?? ''; 
    $stock_level = $_POST['stock_level'] ?? '';
    $image_path = ''; // Initialize image path
    
    // --- 2. Validation ---
    
    if (empty($sku)) { $errors[] = "SKU is required."; }
    if (empty($name)) { $errors[] = "Product Name is required."; }
    if (!is_numeric($price) || $price < 0) { $errors[] = "Price must be a valid non-negative number."; }
    if (!is_numeric($cost) || $cost < 0) { $errors[] = "Cost must be a valid non-negative number."; }
    if (!filter_var($stock_level, FILTER_VALIDATE_INT) || $stock_level < 0) { $errors[] = "Stock Level must be a whole non-negative number."; }
    
    // Check for Duplicate SKU
    if (empty($errors) && !empty($sku)) {
        $sql_check = "SELECT product_id FROM products WHERE sku = '$sku'";
        $result_check = $conn->query($sql_check);
        
        if ($result_check->num_rows > 0) {
            $errors[] = "The SKU '$sku' already exists. Please use a unique SKU.";
        }
    }
    
    // --- 3. Image Upload Handling ---
    if (empty($errors) && isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        
        // Use a unique name based on the SKU to avoid filename conflicts
        $new_filename = $sku . '.' . strtolower($file_extension); 
        $target_file = $target_dir . $new_filename;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check if file is an actual image
        $check = getimagesize($_FILES['product_image']['tmp_name']);
        if($check === false) {
            $errors[] = "File is not an image.";
        }
        
        // Allow certain file formats
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
            $errors[] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        }
        
        // Move the file if no errors
        if (empty($errors)) {
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                $image_path = $conn->real_escape_string($target_file);
            } else {
                $errors[] = "Sorry, there was an error uploading your file.";
            }
        }
    }
    
    // --- 4. Insertion if valid ---
    if (empty($errors)) {
        $sql = "INSERT INTO products (sku, name, price, cost, stock_level, image_path) 
                VALUES ('$sku', '$name', '$price', '$cost', '$stock_level', '$image_path')";
        
        if ($conn->query($sql) === TRUE) {
            $message = "✅ New product created successfully: **" . htmlspecialchars($name) . "**";
            header("Location: index.php?message=" . urlencode($message));
            exit();
        } else {
            $message = "❌ Error creating product: " . $conn->error;
        }
    } else {
        $message = "❌ **Creation Failed:** Please fix the following errors.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Product</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f9; }
        .container { max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { color: #5cb85c; border-bottom: 2px solid #5cb85c; padding-bottom: 10px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input[type="text"], input[type="number"], input[type="file"] { width: 95%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; }
        button { background-color: #5cb85c; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        .error { color: red; font-weight: bold; margin-top: 10px; }
        .error-list { color: red; margin-left: 20px; }
    </style>
</head>
<body>

<div class="container">
    <h2>➕ Add New Gadget</h2>
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

    <form method="POST" action="create.php" enctype="multipart/form-data">
        <label for="sku">SKU (Stock Keeping Unit):</label>
        <input type="text" name="sku" id="sku" required value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>">

        <label for="name">Product Name:</label>
        <input type="text" name="name" id="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
        
        <label for="cost">Cost (Internal Purchase Price ₱):</label>
        <input type="number" name="cost" id="cost" step="0.01" required value="<?php echo htmlspecialchars($_POST['cost'] ?? ''); ?>">

        <label for="price">Price (Selling Price ₱):</label>
        <input type="number" name="price" id="price" step="0.01" required value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">

        <label for="stock_level">Stock Level (Qty):</label>
        <input type="number" name="stock_level" id="stock_level" required value="<?php echo htmlspecialchars($_POST['stock_level'] ?? ''); ?>">
        
        <label for="product_image">Product Image (JPG, PNG, GIF):</label>
        <input type="file" name="product_image" id="product_image" accept="image/jpeg, image/png, image/gif"> 

        <button type="submit">Create Gadget</button>
    </form>
</div>

</body>
</html>