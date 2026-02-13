<?php
/**
 * v1.0.0
 * Projekt: Hanžeković & Partneri IDM
 * Opis: Prikupljanje feedbacka od zahtjevatora.
 */

require_once __DIR__ . '/db_config.php';

$id = $_GET['id'] ?? null;
$rating = $_GET['rating'] ?? null;

if ($id && $rating) {
    try {
        $stmt = $pdo->prepare("INSERT INTO hanz_feedback (identity_id, rating) VALUES (?, ?)");
        $stmt->execute([$id, $rating]);
    } catch (PDOException $e) {
        // Tiho zabilježi grešku ili zanemari ako je dupla ocjena
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Thank You | Hanžeković IDM</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f4f7f6; }
        .card { background: white; padding: 3rem; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
        h1 { color: #2ecc71; margin-bottom: 1rem; }
        p { color: #666; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Hvala vam!</h1>
        <p>Vaša povratna informacija je zabilježena. To nam pomaže unaprijediti naše IT usluge podrške.</p>
        <p>Sada možete zatvoriti ovaj prozor.</p>
    </div>
</body>
</html>