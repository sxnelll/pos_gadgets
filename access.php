<?php
session_start();

// --- CONFIGURATION ---
// Simple hardcoded passcode for Manager access
$manager_passcode = "4321"; 

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['role'])) {
        $role = $_POST['role'];

        if ($role == 'manager') {
            $submitted_passcode = trim($_POST['passcode'] ?? '');

            if ($submitted_passcode === $manager_passcode) {
                // SUCCESS: Grant manager access and redirect to the Inventory/Dashboard
                $_SESSION['user_role'] = 'manager';
                header("Location: index.php");
                exit();
            } else {
                $error = "❌ Invalid Manager Passcode. Please try again.";
            }
        } elseif ($role == 'customer') {
            // SUCCESS: Grant customer access and redirect to the Online Shop page
            $_SESSION['user_role'] = 'customer';
            header("Location: shop.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS Gadget Manager Access</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            background-color: #f0f2f5; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh;
        }
        .container { 
            text-align: center; 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-width: 600px;
        }
        h2 { 
            color: #3f51b5; 
            margin-bottom: 30px; 
            font-size: 1.8em;
        }
        .access-options {
            display: flex;
            gap: 40px;
            justify-content: center;
        }
        .access-box {
            background-color: #e8eaf6; 
            padding: 25px 35px;
            border-radius: 8px;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            width: 200px;
            display: flex; 
            flex-direction: column; 
            align-items: center;
        }
        .access-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(63, 81, 181, 0.2);
        }
        .access-box h3 {
            margin-top: 10px;
            margin-bottom: 5px;
            color: #3f51b5;
        }
        .access-box p {
            font-size: 3em;
            margin: 10px 0;
        }
        
        .manager-passcode-form {
            margin-top: 25px;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #fffde7;
            text-align: left;
            display: <?php echo $error ? 'block' : 'none'; ?>;
        }
        input[type="password"] {
            width: 95%;
            padding: 10px;
            margin: 10px 0 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1.1em;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: #3f51b5;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .btn-submit:hover {
            background-color: #303f9f;
        }
        .error {
            background-color: #fbebee;
            color: #c62828;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            border: 1px solid #ef9a9a;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Welcome to the POS Gadget Manager System</h2>
    <h3>Please select your role:</h3>

    <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="access-options">
        
        <div class="access-box" id="manager-box">
            <p>👨‍💼</p>
            <h3>Manager/Employee</h3>
            <small>Access Inventory, POS Terminal, and Reports.</small>
        </div>

        <div class="access-box" id="customer-box">
            <p>🛍️</p>
            <h3>Customer</h3>
            <small>Direct access to the Online Shop page.</small>
        </div>
    </div>
    
    <div class="manager-passcode-form" id="manager-form-container">
        <form method="POST" action="access.php">
            <input type="hidden" name="role" value="manager">
            <label for="passcode" style="font-weight: 600;">Enter Passcode:</label>
            <input type="password" id="passcode" name="passcode" required autofocus>
            <button type="submit" class="btn-submit">Manager Login</button>
        </form>
    </div>

    <form method="POST" action="access.php" id="customer-form" style="display:none;">
        <input type="hidden" name="role" value="customer">
    </form>
</div>

<script>
    document.getElementById('manager-box').addEventListener('click', function() {
        document.getElementById('manager-form-container').style.display = 'block';
        document.getElementById('passcode').focus();
    });

    document.getElementById('customer-box').addEventListener('click', function() {
        document.getElementById('customer-form').submit();
    });
</script>

</body>
</html>