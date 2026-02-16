<?php
/**
 * v1.0.6 - EXTERNAL CSS VERSION
 */
require_once 'db_config.php';
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM hanz_admins WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "DEBUG: Korisnik '$username' NE POSTOJI u bazi.";
    } elseif (!password_verify($password, $user['password'])) {
        $error = "DEBUG: Korisnik pronađen, ali password_verify kaže NE.";
    } else {
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
    <link rel="stylesheet" href="static/style.css">
</head>
<body class="login-page">

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

<div class="footer">
    DEVELOPED BY PIOPET
</div>

</body>
</html>