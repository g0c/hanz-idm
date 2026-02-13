<?php
/**
 * v1.0.5 - DEBUG VERSION
 */
require_once 'db_config.php';
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Koristimo trim da maknemo eventualne razmake iz forme
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM hanz_admins WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        // TEST 1: Postoji li uopće taj username?
        $error = "DEBUG: Korisnik '$username' NE POSTOJI u bazi.";
    } elseif (!password_verify($password, $user['password'])) {
        // TEST 2: Ako postoji, zašto hash ne prolazi?
        $error = "DEBUG: Korisnik pronađen, ali password_verify kaže NE. Password koji si unio: '$password'";
    } else {
        // USPJEH
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['rola'] = $user['rola'];
        $_SESSION['ime_prezime'] = $user['ime_prezime'];
        header("Location: dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hanžeković & Partneri | IDM Login</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #1a1a1a; /* Tamno siva pozadina stranice */
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            margin: 0; 
        }
        .login-container { 
            background: #ffffff; /* Bijela kartica za crni logo */
            padding: 2.5rem; 
            border-radius: 2px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.5); 
            width: 100%; 
            max-width: 400px; 
        }
        .logo-wrapper {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo-wrapper img {
            width: 100%; /* Koristi punu širinu kontejnera za logo */
            max-width: 280px;
            height: auto;
        }
        h2 { 
            text-align: center; 
            color: #333; 
            font-weight: 400; 
            margin-bottom: 2rem; 
            font-size: 1rem; 
            text-transform: uppercase; 
            letter-spacing: 1.5px;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }
        .form-group { margin-bottom: 1.2rem; }
        label { display: block; margin-bottom: .5rem; color: #666; font-size: 0.85rem; text-transform: uppercase; }
        input { 
            width: 100%; 
            padding: 12px; 
            background: #f9f9f9; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box; 
            color: #333;
            outline: none;
            transition: border 0.3s;
        }
        input:focus { border-color: #999; }
        button { 
            width: 100%; 
            padding: 12px; 
            background: #1a1a1a; 
            color: #fff; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 14px; 
            font-weight: bold;
            margin-top: 1rem; 
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: background 0.3s;
        }
        button:hover { background: #333; }
        .error { color: #c0392b; text-align: center; margin-bottom: 1rem; font-size: 14px; font-weight: 500; }
        .footer { text-align: center; margin-top: 2.5rem; font-size: 10px; color: #bbb; letter-spacing: 1px; }
    </style>
</head>
<body>

<div class="login-container">
    <div class="logo-wrapper">
        <img src="images/hanzekovic-logo.svg" alt="Hanžeković & Partneri Logo">
    </div>
    
    <h2>Identity Management</h2>
    
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="username">Korisničko ime</label>
            <input type="text" id="username" name="username" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Zaporka</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit">Prijavi se</button>
    </form>
</div>

<div class="footer" style="position: absolute; bottom: 20px;">
    DEVELOPED BY PIOPET
</div>

</body>
</html>