<?php
session_start();
// Include db_config.php to get database connection
include 'db_config.php';

// --- ACCESS CONTROL: CUSTOMER ONLY ---
// If no role is set, redirect to the access page
if (!isset($_SESSION['user_role'])) {
    header("Location: access.php");
    exit();
}
// --- END ACCESS CONTROL ---

// Fetch all available products (only those in stock for the customer view)
$sql = "SELECT product_id, sku, name, price, stock_level, image_path FROM products WHERE stock_level > 0 ORDER BY name ASC";
$result = $conn->query($sql);

$products = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
$products_json = json_encode($products);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Online Gadget Shop</title>
    <style>
        /* --- General Layout --- */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            background-color: #f0f2f5; 
            display: flex; 
        }
        .main-content {
            flex-grow: 1; 
            padding: 20px;
            max-width: 1200px;
            margin: auto;
        }
        
        /* --- Headings and Links --- */
        h2 { 
            color: #3f51b5; 
            border-bottom: 3px solid #3f51b5; 
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3f51b5;
            text-decoration: none;
            font-weight: bold;
        }

        /* --- Product Grid Styles --- */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }
        .product-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
            padding-bottom: 15px;
        }
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        
        .product-image-container {
            width: 100%;
            height: 200px;
            background-color: #f8f8f8;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.9em;
            color: #555;
            cursor: pointer;
        }
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-details { padding: 15px; }
        .product-details h3 { font-size: 1.3em; color: #3f51b5; margin: 5px 0 10px 0; }
        .product-price { font-size: 1.6em; font-weight: bold; color: #d9534f; margin-bottom: 15px; }
        .add-to-cart-btn {
            background-color: #5cb85c;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .add-to-cart-btn:hover:not(:disabled) { background-color: #4cae4c; }
        .out-of-stock { background-color: #aaa; cursor: not-allowed; }
        
        /* --- Cart Sidebar Styles (ENHANCED) --- */
        .cart-sidebar {
            width: 350px;
            background-color: #fcfcfc;
            border-left: 1px solid #e0e0e0;
            padding: 25px;
            box-shadow: -4px 0 10px rgba(0, 0, 0, 0.05);
            min-height: 100vh;
            position: sticky;
            top: 0;
        }

        .cart-header {
            border-bottom: 2px solid #3f51b5;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .cart-header h3 {
            color: #3f51b5;
            margin: 0;
            font-size: 1.6em;
        }
        .cart-hint {
            color: #999;
            font-size: 0.9em;
            margin-top: 5px;
        }

        #customer-cart-list { 
            list-style: none; 
            padding: 0; 
            margin-bottom: 20px;
        }
        #customer-cart-list li { 
            border-bottom: 1px dashed #e0e0e0; 
            padding: 12px 0; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .cart-item-name { 
            flex-grow: 1; 
            font-size: 0.95em;
        }
        .cart-item-name strong {
            display: block;
            margin-bottom: 3px;
        }

        .cart-qty-controls {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
        }
        .qty-btn {
            background-color: #5c6bc0;
            color: white;
            border: none;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            cursor: pointer;
            font-weight: bold;
            font-size: 1.2em;
            line-height: 1;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: background-color 0.2s;
        }
        .qty-btn:hover { background-color: #3f51b5; }
        .qty-display {
            width: 25px;
            text-align: center;
            font-weight: bold;
            font-size: 1em;
        }
        
        .remove-btn { 
            background-color: #dc3545 !important;
            margin-left: 10px;
            font-size: 0.9em;
            width: 24px;
            height: 24px;
        }
        .remove-btn:hover { background-color: #c82333 !important; }

        /* --- Summary and Buttons --- */
        .cart-summary-box {
            padding-top: 15px;
            border-top: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .cart-summary {
            font-size: 1.3em;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .total-price {
            color: #d9534f;
        }
        .tax-note {
            display: block;
            text-align: right;
            color: #777;
            font-size: 0.85em;
        }
        
        .checkout-mock-btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            margin-top: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s, transform 0.1s;
        }

        .primary-btn {
            background-color: #3f51b5;
            color: white;
        }
        .primary-btn:hover:not(:disabled) { 
            background-color: #303f9f;
            transform: translateY(-1px);
        }

        .secondary-btn {
            background-color: #e0e0e0;
            color: #333;
        }
        .secondary-btn:hover { 
            background-color: #ccc; 
        }
        .checkout-mock-btn:disabled { 
            background-color: #ccc; 
            color: #888;
            cursor: not-allowed;
            transform: none;
        }
        
        /* --- Modal (Quick View) Styles --- */
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            padding-top: 60px;
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 80%;
            max-width: 700px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            animation-name: animatetop;
            animation-duration: 0.4s;
            display: flex;
            gap: 20px;
        }
        @keyframes animatetop {
            from {top: -300px; opacity: 0} 
            to {top: 0; opacity: 1}
        }
        .modal-image-container { flex: 1; }
        .modal-image-container img { width: 100%; height: auto; border-radius: 8px; }
        .modal-details { flex: 1; text-align: left; }
        .modal-details h2 { margin-top: 0; font-size: 2em; border: none; }
        .modal-price { font-size: 2.5em; color: #d9534f; margin-bottom: 10px; }
        .modal-stock { font-size: 1.1em; font-weight: 600; color: #4CAF50; margin-bottom: 20px; }
        .modal-description { margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; color: #555; }

        .close-btn {
            color: #aaa;
            float: right;
            font-size: 38px;
            font-weight: bold;
            line-height: 1;
        }
        .close-btn:hover,
        .close-btn:focus {
            color: #333;
            text-decoration: none;
            cursor: pointer;
        }
        
        /* --- Toast Notification --- */
        #toast {
            visibility: hidden;
            min-width: 250px;
            margin-left: -125px;
            background-color: #3f51b5;
            color: #fff;
            text-align: center;
            border-radius: 8px;
            padding: 16px;
            position: fixed;
            z-index: 200;
            left: 50%;
            bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        #toast.show {
            visibility: visible;
            -webkit-animation: fadein 0.5s, fadeout 0.5s 2.5s;
            animation: fadein 0.5s, fadeout 0.5s 2.5s;
        }
        @-webkit-keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
        @keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
        @-webkit-keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }
        @keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }

    </style>
</head>
<body>

<div class="main-content">
    <div class="container">
        <a href="logout.php" class="back-link">← Return to Access Selection</a>
        <h2>🛍️ Browse Our Latest Gadgets</h2>
        
        <div class="product-grid" id="product-list">
        </div>
    </div>
</div>

<div class="cart-sidebar">
    <div class="cart-header">
        <h3>🛒 Your Cart</h3>
        <p id="cart-message" class="cart-hint"></p>
    </div>
    
    <ul id="customer-cart-list" class="cart-items-container">
    </ul>
    
    <div class="cart-summary-box">
        <div class="cart-summary">
            <span>Subtotal:</span>
            <span id="cart-total-display" class="total-price">₱0.00</span>
        </div>
        <small class="tax-note">Shipping and taxes calculated at checkout.</small>
    </div>
    
    <button class="checkout-mock-btn primary-btn" id="checkout-btn" disabled onclick="mockCheckout()">
        Secure Checkout →
    </button>
    <button class="checkout-mock-btn secondary-btn" onclick="clearCart()">
        Clear Cart
    </button>
</div>

<div id="quickViewModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal()">&times;</span>
        <div class="modal-image-container">
            <img id="modal-product-image" src="" alt="Product Image">
        </div>
        <div class="modal-details">
            <h2 id="modal-product-name"></h2>
            <p id="modal-product-price" class="modal-price"></p>
            <p id="modal-product-stock" class="modal-stock"></p>
            
            <p class="modal-description">
                **Details:** <span id="modal-product-description"></span>
                <br>
                <small style="color: #999;">SKU: <span id="modal-product-sku"></span></small>
            </p>
            
            <button class="add-to-cart-btn" id="modal-add-to-cart-btn" style="margin-top: 20px;">
                Add to Cart
            </button>
        </div>
    </div>
</div>

<div id="toast">Item added to cart!</div>


<script>
    const ALL_PRODUCTS = <?php echo $products_json; ?>;
    let cart = JSON.parse(localStorage.getItem('customer_cart')) || [];
    const productList = document.getElementById('product-list');
    const cartList = document.getElementById('customer-cart-list');
    const cartTotalDisplay = document.getElementById('cart-total-display');
    const checkoutBtn = document.getElementById('checkout-btn');
    const cartMessage = document.getElementById('cart-message');
    const modal = document.getElementById('quickViewModal');

    function saveCart() {
        localStorage.setItem('customer_cart', JSON.stringify(cart));
    }

    function showToast(message) {
        const toast = document.getElementById("toast");
        toast.textContent = message;
        toast.className = "show";
        setTimeout(function(){ toast.className = toast.className.replace("show", ""); }, 3000);
    }

    // FIX: All quantity buttons use single quotes for reliable function call
    function changeQuantity(id, change) {
        const fullProduct = ALL_PRODUCTS.find(p => p.product_id === id);
        if (!fullProduct) return;

        const existingItem = cart.find(item => item.id === id);
        if (!existingItem) return;

        const newQuantity = existingItem.quantity + change;
        
        if (newQuantity <= 0) {
            cart = cart.filter(item => item.id !== id);
        } else if (newQuantity > fullProduct.stock_level) {
            showToast(`Error: Only ${fullProduct.stock_level} unit(s) in stock.`);
            return;
        } else {
            existingItem.quantity = newQuantity;
        }

        saveCart();
        renderCart();
        renderProducts();
    }


    function addToCart(product) {
        const fullProduct = ALL_PRODUCTS.find(p => p.product_id === product.product_id);
        if (!fullProduct) return;

        const existingItem = cart.find(item => item.id === product.product_id);
        
        const currentCartQty = existingItem ? existingItem.quantity : 0;
        
        if (currentCartQty >= fullProduct.stock_level) {
            showToast(`Error: ${product.name} is out of stock or quantity limit reached.`);
            return; 
        }

        if (existingItem) {
            existingItem.quantity += 1;
        } else {
            cart.push({ 
                id: parseInt(product.product_id), 
                name: product.name, 
                price: parseFloat(product.price), 
                quantity: 1
            });
        }
        saveCart();
        renderCart();
        renderProducts(); 
        showToast(`${product.name} added to cart!`);
    }
    
    function clearCart() {
        if (confirm("Are you sure you want to clear your shopping cart?")) {
            cart = [];
            saveCart();
            renderCart();
            renderProducts(); 
            showToast("Cart cleared.");
        }
    }

    // UPDATED: Sends AJAX request to process_order.php
    async function mockCheckout() {
        if (cart.length === 0) {
            showToast("Your cart is empty!");
            return;
        }
        
        const finalTotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

        const orderData = {
            cart: cart,
            total: finalTotal.toFixed(2)
        };
        
        checkoutBtn.disabled = true;
        checkoutBtn.textContent = 'Processing...';

        try {
            const response = await fetch('process_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(orderData)
            });

            const result = await response.json();

            if (result.success) {
                alert(`Order successful! Your total is ₱${finalTotal.toFixed(2)}. The order has been recorded. (${result.message})`);
                cart = [];
                saveCart();
                renderCart();
                renderProducts(); // Re-render products to show updated stock levels
                showToast("Order placed successfully!");
            } else {
                alert(`Order failed: ${result.message}`);
                showToast("Order failed. Please check stock.");
            }
        } catch (error) {
            console.error('Checkout error:', error);
            alert('An unexpected error occurred during checkout.');
        } finally {
            // Check if the button is still disabled before re-enabling
            if (checkoutBtn.textContent === 'Processing...') {
                 checkoutBtn.disabled = false;
                 checkoutBtn.textContent = 'Secure Checkout →';
            }
           
        }
    }

    function renderCart() {
        cartList.innerHTML = '';
        let total = 0;

        if (cart.length === 0) {
            cartMessage.textContent = 'Your cart is empty. Start shopping!';
            checkoutBtn.disabled = true;
            cartTotalDisplay.textContent = '₱0.00';
            return;
        }
        
        cartMessage.textContent = 'Items in your cart:'; 

        cart.forEach(item => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;
            
            const li = document.createElement('li');
            const fullProduct = ALL_PRODUCTS.find(p => p.product_id === item.id);
            const canIncrease = fullProduct && item.quantity < fullProduct.stock_level;
            
            li.innerHTML = `
                <div class="cart-item-name">
                    <strong>${item.name}</strong>
                    <span style="font-size: 0.9em; color: #555;">₱${item.price.toFixed(2)} ea.</span>
                </div>
                <div class="cart-qty-controls">
                    <button class="qty-btn" onclick='changeQuantity(${item.id}, -1)'>-</button>
                    <span class="qty-display">${item.quantity}</span>
                    <button class="qty-btn" ${canIncrease ? '' : 'disabled style="opacity: 0.5; cursor: not-allowed;"'} onclick='changeQuantity(${item.id}, 1)'>+</button>
                    
                    <span style="font-weight: bold; width: 60px; text-align: right; margin-left: 10px;">₱${itemTotal.toFixed(2)}</span>
                    
                    <button class="qty-btn remove-btn" 
                            onclick='changeQuantity(${item.id}, ${-item.quantity})'>
                        &times;
                    </button>
                </div>
            `;
            cartList.appendChild(li);
        });

        cartTotalDisplay.textContent = '₱' + total.toFixed(2);
        checkoutBtn.disabled = false;
    }

    function renderProducts() {
        productList.innerHTML = ''; 
        
        if (ALL_PRODUCTS.length === 0) {
            productList.innerHTML = '<p style="text-align: center; grid-column: 1 / -1;">Sorry, the shop is currently empty.</p>';
            return;
        }

        ALL_PRODUCTS.forEach(product => {
            const card = document.createElement('div');
            card.className = 'product-card';
            
            const imagePath = product.image_path || '';
            const cartItem = cart.find(item => item.id === parseInt(product.product_id));
            const currentCartQty = cartItem ? cartItem.quantity : 0;
            const remainingStock = product.stock_level - currentCartQty;
            const isStocked = remainingStock > 0;
            
            let mediaContent;
            if (imagePath) {
                mediaContent = `<img src="${imagePath}" class="product-image" alt="${product.name}" onclick='openModal(${JSON.stringify(product).replace(/"/g, '&quot;')})'>`;
            } else {
                mediaContent = `<div onclick='openModal(${JSON.stringify(product).replace(/"/g, '&quot;')})'>Image Unavailable</div>`;
            }

            const productJsonSafe = JSON.stringify(product).replace(/"/g, '&quot;'); 
            
            const buttonClass = isStocked ? 'add-to-cart-btn' : 'add-to-cart-btn out-of-stock';
            const buttonText = isStocked ? 'Add to Cart' : 'Out of Stock';

            const stockText = isStocked ? `In Stock (${remainingStock} left)` : 'Out of Stock';


            card.innerHTML = `
                <div class="product-image-container">
                    ${mediaContent}
                </div>
                <div class="product-details">
                    <h3>${product.name}</h3>
                    <p class="product-price">₱${parseFloat(product.price).toFixed(2)}</p>
                    <p style="font-size: 0.9em; color: ${isStocked ? '#4CAF50' : 'red'}; margin-top: -10px;">${stockText}</p>
                    <button class="${buttonClass}" 
                        ${isStocked ? `onclick='addToCart(${productJsonSafe})'` : 'disabled'}>
                        ${buttonText}
                    </button>
                </div>
            `;
            
            productList.appendChild(card);
        });
    }

    // --- Modal Functions ---
    function openModal(product) {
        document.getElementById('modal-product-name').textContent = product.name;
        document.getElementById('modal-product-price').textContent = `₱${parseFloat(product.price).toFixed(2)}`;
        
        const cartItem = cart.find(item => item.id === parseInt(product.product_id));
        const currentCartQty = cartItem ? cartItem.quantity : 0;
        const remainingStock = product.stock_level - currentCartQty;
        const isStocked = remainingStock > 0;

        const stockText = isStocked ? `Available: ${remainingStock} unit(s)` : 'Currently Sold Out';
        document.getElementById('modal-product-stock').textContent = stockText;
        document.getElementById('modal-product-stock').style.color = isStocked ? '#4CAF50' : 'red';
        
        document.getElementById('modal-product-description').textContent = `A cutting-edge gadget: ${product.name}, perfect for all your modern needs. Fast, reliable, and stylish.`;
        document.getElementById('modal-product-sku').textContent = product.sku;

        const imageElement = document.getElementById('modal-product-image');
        imageElement.src = product.image_path || '';
        if (!product.image_path) {
            imageElement.alt = 'No Image Available';
            imageElement.style.display = 'none';
        } else {
            imageElement.alt = product.name;
            imageElement.style.display = 'block';
        }

        const modalBtn = document.getElementById('modal-add-to-cart-btn');
        modalBtn.onclick = () => {
            addToCart(product);
            closeModal();
        };
        modalBtn.disabled = !isStocked;
        modalBtn.textContent = isStocked ? 'Add to Cart' : 'Out of Stock';
        modalBtn.style.backgroundColor = isStocked ? '#5cb85c' : '#aaa';

        modal.style.display = 'block';
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }


    // Initial load: Display products and load the cart
    renderProducts();
    renderCart();
</script>
</body>
</html>