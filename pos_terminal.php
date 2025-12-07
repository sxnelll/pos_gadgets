<?php
session_start(); 
include 'db_config.php';

// --- TRANSACTION PROCESSING LOGIC ---
$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cart_data'])) {
    $conn->begin_transaction(); 

    try {
        // 1. Decode Cart Data
        $cart = json_decode($_POST['cart_data'], true);
        $total_amount = $_POST['total_amount'];
        $payment_method = $conn->real_escape_string($_POST['payment_method']);
        // Customer ID (Walk-in Customer ID 1 is assumed as default)
        $customer_id = isset($_POST['customer_id']) && is_numeric($_POST['customer_id']) ? 
                       $conn->real_escape_string($_POST['customer_id']) : 1; 

        // 2. INSERT into SALES table (now includes customer_id)
        $sale_date = date('Y-m-d H:i:s');
        $sql_sale = "INSERT INTO sales (sale_date, total_amount, payment_method, customer_id) 
                     VALUES ('$sale_date', '$total_amount', '$payment_method', '$customer_id')";
        
        if (!$conn->query($sql_sale)) {
            throw new Exception("Error recording sale: " . $conn->error);
        }

        $sale_id = $conn->insert_id; 

        // 3. INSERT into SALE_ITEMS and UPDATE PRODUCTS
        foreach ($cart as $item) {
            $product_id = $conn->real_escape_string($item['id']);
            $quantity = $conn->real_escape_string($item['quantity']);
            $unit_price = $conn->real_escape_string($item['price']);
            
            $sql_item = "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price) 
                         VALUES ('$sale_id', '$product_id', '$quantity', '$unit_price')";
            
            if (!$conn->query($sql_item)) {
                throw new Exception("Error recording sale item: " . $conn->error);
            }

            // Update product stock 
            $sql_stock = "UPDATE products SET stock_level = stock_level - '$quantity' 
                          WHERE product_id = '$product_id' AND stock_level >= '$quantity'";
            
            if (!$conn->query($sql_stock) || $conn->affected_rows == 0) {
                 throw new Exception("Stock update failed for product ID {$product_id}. Sale aborted.");
            }
        }

        $conn->commit();
        $message = "✅ Sale completed successfully! Transaction ID: **$sale_id**";
        echo '<script>localStorage.removeItem("pos_cart");</script>'; 

    } catch (Exception $e) {
        $conn->rollback();
        $message = "❌ Transaction Failed: " . $e->getMessage();
    }
}

// --- FETCH ALL PRODUCTS FOR INITIAL DISPLAY (Now includes 'cost') ---
$sql_all_products = "SELECT product_id, sku, name, price, cost, stock_level, image_path FROM products ORDER BY name ASC";
$result_all_products = $conn->query($sql_all_products);
$all_products = [];
while($row = $result_all_products->fetch_assoc()) {
    $all_products[] = $row;
}
$all_products_json = json_encode($all_products);

// --- FETCH ALL CUSTOMERS ---
$sql_customers = "SELECT customer_id, name FROM customers ORDER BY name ASC";
$result_customers = $conn->query($sql_customers);
$all_customers = [];
while($row = $result_customers->fetch_assoc()) {
    $all_customers[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS Terminal</title>
    <style>
        /* --- GLOBAL & LAYOUT STYLES --- */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            display: flex; 
            flex-direction: column;
            margin: 0; 
            height: 100vh; 
            background-color: #f4f7f9; 
            color: #333;
        }
        .main-content { 
            display: flex; 
            flex-grow: 1; 
            overflow: hidden; 
            padding: 20px;
            gap: 20px; 
        }
        
        .pane { 
            border-radius: 10px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
            padding: 25px; 
            background-color: white;
            display: flex;
            flex-direction: column;
        }
        .products-pane { 
            flex: 2; 
            overflow-y: hidden;
        }
        .cart-pane { 
            flex: 1; 
            background-color: #e8eaf6; 
            border: 1px solid #c5cae9; 
        }
        h2 { 
            border-bottom: 3px solid #3f51b5; 
            padding-bottom: 12px; 
            color: #3f51b5; 
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        /* --- Customer Selector Styles --- */
        .customer-selector {
            background-color: #f1f8e9; 
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border: 1px solid #a5d6a7;
        }
        .customer-selector label {
            font-weight: 600;
            color: #388e3c; 
            display: block;
            margin-bottom: 5px;
        }
        #customer_id {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 1em;
            background-color: white;
        }
        
        /* --- INPUTS & SEARCH --- */
        #search-input { 
            width: 100%; 
            padding: 12px 15px; 
            margin-bottom: 15px; 
            border: 1px solid #ccc; 
            border-radius: 6px; 
            font-size: 16px; 
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        #search-input:focus {
            border-color: #3f51b5;
            box-shadow: 0 0 0 3px rgba(63, 81, 181, 0.2);
            outline: none;
        }
        
        /* --- PRODUCT LIST --- */
        #product-results { 
            list-style: none; 
            padding: 0; 
            flex-grow: 1; 
            overflow-y: auto; 
            gap: 10px;
            display: flex;
            flex-direction: column;
        }
        #product-results li { 
            padding: 15px; 
            background: #fff; 
            border: 1px solid #eee; 
            border-radius: 8px;
            cursor: pointer; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            transition: background-color 0.2s;
        }
        #product-results li:hover { background-color: #f0f8ff; } 
        
        .product-info-box { display: flex; align-items: center; }
        .product-image-thumbnail { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; margin-right: 15px; border: 1px solid #ddd; }
        .add-btn { 
            background-color: #4CAF50; 
            color: white; 
            border: none; 
            padding: 10px 18px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: bold; 
            transition: background-color 0.2s;
        }
        .add-btn:hover:not(:disabled) { background-color: #45a049; }
        .add-btn:disabled { background-color: #aaa; cursor: not-allowed; }

        /* --- CART STYLES --- */
        #cart-list { 
            list-style: none; 
            padding: 0; 
            flex-grow: 1; 
            overflow-y: auto; 
            border-bottom: 1px solid #c5cae9;
        }
        #cart-list li { 
            background: #fff; 
            padding: 10px 15px; 
            border-radius: 4px; 
            margin-bottom: 8px; 
            display: grid;
            grid-template-columns: 2fr 1fr;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        /* NEW: Quantity Controls */
        .cart-qty-controls {
            display: flex;
            align-items: center;
            justify-content: flex-end; /* Align to the right side of the column */
            gap: 5px;
        }
        .qty-btn {
            background-color: #5c6bc0;
            color: white;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1.1em;
            line-height: 1;
            transition: background-color 0.2s;
        }
        .qty-btn:hover { background-color: #3f51b5; }
        .qty-display {
            width: 20px;
            text-align: center;
            font-weight: bold;
        }
        .remove-btn { 
            color: #d32f2f; 
            cursor: pointer; 
            margin-left: 10px; 
            font-weight: bold;
            font-size: 1.2em;
        }
        
        .cart-footer { padding: 15px 0; }
        
        /* NEW: Running Profit Display */
        .profit-display {
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 10px;
            color: #388e3c; /* Green for profit */
            border-top: 1px dashed #c5cae9;
            padding-top: 10px;
        }
        
        .total-display { 
            font-size: 1.6em; 
            font-weight: bold; 
            margin-bottom: 15px; 
            color: #d32f2f; 
        }
        
        .checkout-btn { 
            width: 100%; 
            padding: 18px; 
            background-color: #3f51b5; 
            color: white; 
            border: none; 
            font-size: 1.3em; 
            border-radius: 6px; 
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .checkout-btn:hover:not(:disabled) { background-color: #303f9f; }
        .checkout-btn:disabled { background-color: #aaa; }


        /* --- Change Calculator Styles (Enhanced) --- */
        .change-calculator {
            background-color: #ffffff;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border: 2px solid #5c6bc0; 
        }
        .change-calculator label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #555;
        }
        #cash-tendered-input {
            width: 95%;
            padding: 12px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1.2em;
            text-align: right;
            color: #000080; 
            font-weight: 700;
        }
        .change-due-display {
            font-size: 1.7em;
            font-weight: bold;
            color: #4CAF50; 
            display: flex;
            justify-content: space-between;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        .negative-change {
             color: #d9534f; 
        }
        
        /* --- Select & Utility Buttons --- */
        #payment_method {
            width: 100%; 
            padding: 10px; 
            margin-bottom: 15px;
            border-radius: 6px;
            border: 1px solid #ccc;
            background-color: #fff;
            font-size: 1em;
        }
        .clear-cart-btn {
            width: 100%; 
            padding: 12px; 
            margin-top: 10px; 
            background: #90a4ae; 
            color: white; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.2s;
        }
        .clear-cart-btn:hover { background-color: #78909c; }
        
        /* --- Navigation CSS (Reused for consistency) --- */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #3f51b5;
            padding: 15px 30px;
            color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%; 
        }
        .nav-title { font-size: 1.5em; font-weight: bold; }
        .nav-links { list-style: none; display: flex; margin: 0; padding: 0; }
        .nav-links li { margin-left: 20px; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s; }
        .nav-links a:hover { background-color: #5c6bc0; }
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

<div class="main-content">
    <div class="pane products-pane">
        <h2>🔎 Gadget Product List</h2>
        <input type="text" id="search-input" placeholder="Live Filter by SKU or Name...">
        <ul id="product-results"></ul>
    </div>

    <div class="pane cart-pane">
        <h2>🛒 Transaction Cart</h2>
        
        <?php if (!empty($message)): ?>
            <p style="padding: 10px; background: #e3f2fd; border: 1px solid #90caf9; border-radius: 4px;"><?php echo $message; ?></p>
        <?php endif; ?>
        
        <div class="customer-selector">
            <label for="customer_id">Current Customer:</label>
            <select name="customer_id" id="customer_id">
                <?php 
                foreach ($all_customers as $customer) {
                    echo "<option value='{$customer['customer_id']}'>{$customer['name']}</option>";
                }
                ?>
            </select>
        </div>

        <ul id="cart-list"></ul>
        
        <div class="cart-footer">
            <div class="profit-display">Gross Profit: <span id="cart-profit-display">₱0.00</span></div>
            
            <div class="total-display">Total: <span id="cart-total-display">₱0.00</span></div>
            
            <div class="change-calculator">
                <label for="cash-tendered-input">Cash Tendered (₱):</label>
                <input type="number" id="cash-tendered-input" placeholder="0.00" step="0.01" min="0" oninput="calculateChange()">
                
                <div class="change-due-display">
                    <span>Change Due:</span>
                    <span id="change-due">₱0.00</span>
                </div>
            </div>

            <form id="checkout-form" method="POST" action="pos_terminal.php" onsubmit="return prepareCheckout()">
                
                <select name="payment_method" id="payment_method" onchange="handlePaymentMethodChange()">
                    <option value="Cash">Cash</option>
                    <option value="Credit Card">Credit Card</option>
                    <option value="E-Wallet">E-Wallet</option>
                </select>
                
                <input type="hidden" name="cart_data" id="cart-data-input">
                <input type="hidden" name="total_amount" id="total-amount-input">
                <input type="hidden" name="customer_id" id="checkout-customer-id">

                <button type="submit" class="checkout-btn" id="checkout-btn" disabled>Complete Sale</button>
            </form>
            <button onclick="clearCart()" class="clear-cart-btn">Clear Cart</button>
        </div>
    </div>
</div>

<script>
    const ALL_PRODUCTS = <?php echo $all_products_json; ?>;
    
    let cart = JSON.parse(localStorage.getItem('pos_cart')) || [];
    const searchInput = document.getElementById('search-input');
    const resultsList = document.getElementById('product-results');
    const cartList = document.getElementById('cart-list');
    const totalDisplay = document.getElementById('cart-total-display');
    const profitDisplay = document.getElementById('cart-profit-display'); // NEW
    const checkoutBtn = document.getElementById('checkout-btn');
    const cashTenderedInput = document.getElementById('cash-tendered-input');
    const changeDueDisplay = document.getElementById('change-due');
    const paymentMethodSelect = document.getElementById('payment_method');
    const customerSelect = document.getElementById('customer_id'); 
    
    let currentTotal = 0.00;
    let currentProfit = 0.00; // NEW

    function saveCart() {
        localStorage.setItem('pos_cart', JSON.stringify(cart));
    }

    // NEW: Function to change quantity
    function changeQuantity(id, change) {
        const item = cart.find(item => item.id === id);
        if (!item) return;
        
        const newQuantity = item.quantity + change;
        
        if (newQuantity <= 0) {
            removeFromCart(id);
            return;
        }

        const productDetails = ALL_PRODUCTS.find(p => parseInt(p.product_id) === id);

        if (newQuantity > productDetails.stock_level) {
            alert(`Cannot add more than ${productDetails.stock_level} unit(s) of ${item.name}. Stock limit reached.`);
            return;
        }

        item.quantity = newQuantity;
        saveCart();
        renderCart();
        renderSearchResults(ALL_PRODUCTS); // Update stock display in product list
    }

    function addToCart(product) {
        const existingItem = cart.find(item => item.id === parseInt(product.product_id));
        
        const currentCartQty = existingItem ? existingItem.quantity : 0;
        if (currentCartQty >= product.stock_level) {
            alert(`Cannot add more than ${product.stock_level} unit(s) of ${product.name}. Stock limit reached.`);
            return; 
        }

        if (existingItem) {
            existingItem.quantity += 1;
        } else {
            cart.push({ 
                id: parseInt(product.product_id), 
                sku: product.sku, 
                name: product.name, 
                price: parseFloat(product.price), 
                cost: parseFloat(product.cost), // IMPORTANT: Added cost
                quantity: 1, 
                stock: product.stock_level,
                image_path: product.image_path 
            });
        }
        saveCart();
        renderCart();
        renderSearchResults(ALL_PRODUCTS); // Update stock display in product list
    }
    
    function removeFromCart(id) {
        cart = cart.filter(item => item.id !== id);
        saveCart();
        renderCart();
        renderSearchResults(ALL_PRODUCTS); // Update stock display in product list
    }
    
    function clearCart() {
        if (confirm("Are you sure you want to clear the entire cart?")) {
            cart = [];
            saveCart();
            renderCart();
            renderSearchResults(ALL_PRODUCTS); // Update stock display in product list
        }
    }

    function renderCart() {
        cartList.innerHTML = '';
        let total = 0;
        let profit = 0; // NEW

        if (cart.length === 0) {
            cartList.innerHTML = '<li><p style="text-align:center; color:#555;">Cart is empty. Scan or search items.</p></li>';
            checkoutBtn.disabled = true;
            totalDisplay.textContent = '₱0.00';
            profitDisplay.textContent = '₱0.00'; // NEW
            currentTotal = 0.00;
            currentProfit = 0.00; // NEW
        } else {
            cart.forEach(item => {
                const itemTotal = item.price * item.quantity;
                const itemProfit = (item.price - item.cost) * item.quantity; // NEW
                
                total += itemTotal;
                profit += itemProfit; // NEW
                
                const li = document.createElement('li');
                li.innerHTML = `
                    <div>
                        <strong>${item.name}</strong><br>
                        <span>₱${item.price.toFixed(2)} (${item.sku})</span>
                    </div>
                    <div class="cart-qty-controls">
                        <button class="qty-btn" onclick="changeQuantity(${item.id}, -1)">-</button>
                        <span class="qty-display">${item.quantity}</span>
                        <button class="qty-btn" onclick="changeQuantity(${item.id}, 1)">+</button>
                        <span style="font-weight: bold; width: 60px; text-align: right;">₱${itemTotal.toFixed(2)}</span>
                        <span class="remove-btn" onclick="removeFromCart(${item.id})">×</span>
                    </div>
                `;
                cartList.appendChild(li);
            });

            currentTotal = parseFloat(total.toFixed(2));
            currentProfit = parseFloat(profit.toFixed(2)); // NEW
            
            totalDisplay.textContent = '₱' + currentTotal.toFixed(2);
            profitDisplay.textContent = '₱' + currentProfit.toFixed(2); // NEW
            checkoutBtn.disabled = false;
        }
        
        calculateChange(); 
        handlePaymentMethodChange(); 
    }

    function renderSearchResults(products) {
        resultsList.innerHTML = '';
        const term = searchInput.value.toLowerCase();
        
        const filteredProducts = products.filter(product => {
            return product.name.toLowerCase().includes(term) || product.sku.toLowerCase().includes(term);
        });

        if (filteredProducts.length === 0) { 
            resultsList.innerHTML = '<li style="justify-content: center; color: #555;">No gadgets found matching your filter.</li>'; 
            return; 
        }
        
        filteredProducts.forEach(product => {
            const li = document.createElement('li');
            const stock = parseInt(product.stock_level);
            // Check cart for current quantity to calculate remaining stock
            const currentCartQty = (cart.find(item => item.id === parseInt(product.product_id))?.quantity || 0);
            const remainingStock = stock - currentCartQty;

            const status = remainingStock > 0 ? `Stock: ${remainingStock}` : '<span style="color:red;">OUT OF STOCK</span>';
            const canAddToCart = remainingStock > 0;
            
            // Note: Product JSON now includes 'cost'
            const productJsonSafe = JSON.stringify(product).replace(/"/g, '&quot;'); 
            
            const imageHtml = product.image_path 
                ? `<img src="${product.image_path}" class="product-image-thumbnail" alt="${product.name}">`
                : `<div class="product-image-thumbnail" style="background-color: #ddd; text-align: center; line-height: 50px; font-size: 10px;">No Pic</div>`;
            
            li.innerHTML = `
                <div class="product-info-box">
                    ${imageHtml}
                    <div>
                        <strong>${product.name}</strong> (SKU: ${product.sku})<br>
                        ₱${parseFloat(product.price).toFixed(2)} | ${status}
                    </div>
                </div>
                ${canAddToCart ? 
                    `<button class="add-btn" onclick='addToCart(${productJsonSafe})'>Add</button>` : 
                    `<button class="add-btn" disabled>Sold Out</button>`
                }`;
            resultsList.appendChild(li);
        });
    }

    function calculateChange() {
        const tendered = parseFloat(cashTenderedInput.value) || 0;
        const total = currentTotal; 
        
        let change = tendered - total;
        
        const formattedChange = change.toFixed(2);
        
        changeDueDisplay.classList.remove('negative-change');
        if (change >= 0) {
            changeDueDisplay.innerHTML = '₱' + formattedChange;
        } else {
            changeDueDisplay.innerHTML = '₱' + formattedChange.replace('-', '') + ' (Balance Due)';
            changeDueDisplay.classList.add('negative-change');
        }
    }

    function handlePaymentMethodChange() {
        const isCash = paymentMethodSelect.value === 'Cash';
        const calculatorDiv = document.querySelector('.change-calculator');

        if (isCash) {
            calculatorDiv.style.display = 'block';
            cashTenderedInput.setAttribute('required', 'required');
            
            const tendered = parseFloat(cashTenderedInput.value) || 0;
            
            // Check if cash tendered is less than total for cash payments
            if (currentTotal > 0 && tendered < currentTotal) {
                 checkoutBtn.disabled = true;
            } else if (currentTotal > 0) {
                 checkoutBtn.disabled = false;
            }
        } else {
            calculatorDiv.style.display = 'none';
            cashTenderedInput.removeAttribute('required');
            cashTenderedInput.value = '';
            changeDueDisplay.textContent = '₱0.00';
            changeDueDisplay.classList.remove('negative-change');
            if (currentTotal > 0) {
                 checkoutBtn.disabled = false;
            }
        }
        
        if (cart.length === 0) {
            checkoutBtn.disabled = true;
        }
    }
    
    function prepareCheckout() {
        if (cart.length === 0) { alert("The cart is empty. Cannot complete sale."); return false; }
        
        const paymentMethod = paymentMethodSelect.value;
        const tendered = parseFloat(cashTenderedInput.value) || 0;
        
        if (paymentMethod === 'Cash') {
            if (tendered < currentTotal) {
                alert("Payment failed: Cash tendered must be greater than or equal to the total amount.");
                return false;
            }
        }

        document.getElementById('cart-data-input').value = JSON.stringify(cart);
        document.getElementById('total-amount-input').value = currentTotal.toFixed(2);
        // Pass selected customer ID
        document.getElementById('checkout-customer-id').value = customerSelect.value; 
        
        return true; 
    }

    searchInput.addEventListener('input', () => renderSearchResults(ALL_PRODUCTS));
    
    // Initial calls
    renderSearchResults(ALL_PRODUCTS);
    renderCart();
    handlePaymentMethodChange();
    
    // Ensure the default customer is selected on load
    document.addEventListener('DOMContentLoaded', () => {
        const defaultCustomerOption = customerSelect.querySelector('option[value="1"]');
        if (defaultCustomerOption) {
            customerSelect.value = 1;
        }
    });

</script>
</body>
</html>