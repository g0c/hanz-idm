<?php
/**
 * v4.4.0 - RESTORED EDITOR
 */
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mailer.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rola'] !== 'admin') { header("Location: index.php"); exit; }
$id = $_GET['id'] ?? null;
if (!$id) { header("Location: dashboard.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM hanz_identities WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { die("Zahtjev nije pronađen."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $updates = []; $vals = [':id' => $id, ':status' => $_POST['status']];
        foreach ($it_schema as $key => $cfg) {
            $updates[] = "$key = :$key";
            $vals[":$key"] = ($cfg['type'] === 'checkbox') ? (isset($_POST[$key]) ? 1 : 0) : ($_POST[$key] ?? null);
        }
        $sql = "UPDATE hanz_identities SET " . implode(', ', $updates) . ", status = :status WHERE id = :id";
        $pdo->prepare($sql)->execute($vals);

        // SLANJE ZAVRŠNOG MAILA
        if ($_POST['status'] === 'zavrseno' && $item['status'] !== 'zavrseno') {
            $podnositelj = $item['trazi_osoba'];
            $primatelj_mail = $hanz_requestors[$podnositelj] ?? 'gkonjic@piopet.hr';
            
            $htmlClientData = "";
            foreach ($form_schema as $key => $cfg) {
                $val = !empty($item[$key]) ? htmlspecialchars($item[$key]) : '-';
                $htmlClientData .= "<tr><td style='padding:8px; color:#666; border-bottom:1px solid #eee; width:40%;'>{$cfg['label']}</td><td style='padding:8px; font-weight:bold; border-bottom:1px solid #eee;'>$val</td></tr>";
            }
            $htmlItData = "";
            foreach ($it_schema as $key => $cfg) {
                if ($key === 'it_napomena') continue;
                $val = ($cfg['type'] === 'checkbox') ? (isset($_POST[$key]) ? 'DA' : 'NE') : ($_POST[$key] ?? '-');
                $htmlItData .= "<tr><td style='padding:8px; color:#666; border-bottom:1px solid #eee; width:40%;'>{$cfg['label']}</td><td style='padding:8px; font-weight:bold; border-bottom:1px solid #eee;'>$val</td></tr>";
            }
            
            $baseUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            $tijelo = "<html><body style='font-family:Segoe UI,Arial; background:#f4f4f4; padding:20px;'>
                        <div style='max-width:600px; margin:0 auto; background:#fff; border:1px solid #e0e0e0;'>
                            <div style='background:#1a1a1a; color:#fff; padding:25px; text-align:center;'>
                                <h2 style='margin:0;'>HANŽEKOVIĆ & PARTNERI</h2>
                                <p style='font-size:11px; color:#888;'>OBAVIJEST O REALIZACIJI</p>
                            </div>
                            <div style='padding:30px;'>
                                <p>Poštovani/a <strong>$podnositelj</strong>, korisnički identitet je spreman:</p>
                                <p style='font-size:12px; color:#999; text-transform:uppercase;'>Podaci o zahtjevu</p>
                                <table style='width:100%; border-collapse:collapse; margin-bottom:20px;'>$htmlClientData</table>
                                <p style='font-size:12px; color:#999; text-transform:uppercase;'>Tehnički podaci za pristup</p>
                                <table style='width:100%; border-collapse:collapse;'>$htmlItData</table>
                                <p style='margin-top:20px; font-size:14px; font-style:italic;'>Napomena IT-a: " . nl2br(htmlspecialchars($_POST['it_napomena'])) . "</p>
                                <hr style='border:0; border-top:1px solid #eee; margin:30px 0;'>
                                <div style='text-align:center;'>
                                    <p style='font-weight:bold;'>Koliko ste zadovoljni uslugom?</p>
                                    <a href='$baseUrl/feedback.php?id=$id&rating=excellent' style='display:inline-block; padding:10px 15px; background:#28a745; color:#fff; text-decoration:none; border-radius:4px;'>Izvrsno</a>
                                    <a href='$baseUrl/feedback.php?id=$id&rating=average' style='display:inline-block; padding:10px 15px; background:#ffc107; color:#000; text-decoration:none; border-radius:4px;'>Dobro</a>
                                    <a href='$baseUrl/feedback.php?id=$id&rating=poor' style='display:inline-block; padding:10px 15px; background:#dc3545; color:#fff; text-decoration:none; border-radius:4px;'>Loše</a>
                                </div>
                            </div>
                        </div></body></html>";
            
            posalji_obavijest($primatelj_mail, "ZAVRŠENO: " . $item['ime'] . " " . $item['prezime'], $tijelo);
        }
        header("Location: edit_identity.php?id=$id&msg=ok"); exit;
    } catch (PDOException $e) { die("Greška: " . $e->getMessage()); }
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <title>Obrada zahtjeva | Hanžeković & Partneri</title>
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <link rel="stylesheet" href="static/style.css">
</head>
<body class="dashboard-page">
<div class="container">
    <a href="dashboard.php" class="back-link">&larr; Povratak na Dashboard</a>
    
    <div class="card">
        <h2>Obrada zahtjeva: <?php echo htmlspecialchars($item['ime'] . " " . $item['prezime']); ?></h2>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert-success" style="background:#d4edda; color:#155724; padding:15px; margin-bottom:20px; border-radius:4px; font-weight:bold;">Promjene su uspješno spremljene!</div>
        <?php endif; ?>

        <div class="grid">
        <div class="section-title">Podaci iz zahtjeva</div>
            <?php foreach ($form_schema as $key => $cfg): ?>
                <div class="info-card">
                    <label><?php echo $cfg['label']; ?></label>
                    <strong>
                        <?php 
                        $raw_val = $item[$key] ?? '-';
                        // Ako je polje tipa date, prikaži HR format
                        if ($cfg['type'] === 'date' && $raw_val !== '-') {
                            echo date('d.m.Y', strtotime($raw_val));
                        } else {
                            echo nl2br(htmlspecialchars($raw_val));
                        }
                        ?>
                    </strong>
                </div>
            <?php endforeach; ?>

            <form action="" method="POST" class="grid full-width" style="margin: 0;">
                <div class="section-title">IT Realizacija</div>
                <?php foreach ($it_schema as $key => $cfg): ?>
                    <div class="<?php echo ($cfg['type'] === 'textarea') ? 'full-width' : ''; ?>">
                        <label><?php echo $cfg['label']; ?></label>
                        <?php if ($cfg['type'] === 'textarea'): ?>
                            <textarea name="<?php echo $key; ?>" rows="3" placeholder="<?php echo $cfg['placeholder'] ?? ''; ?>"><?php echo htmlspecialchars($item[$key]); ?></textarea>
                        <?php elseif ($cfg['type'] === 'checkbox'): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="<?php echo $key; ?>" <?php echo $item[$key] ? 'checked' : ''; ?>> 
                                <?php echo $cfg['text'] ?? 'Omogućeno'; ?>
                            </label>
                        <?php else: ?>
                            <input type="<?php echo $cfg['type']; ?>" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($item[$key]); ?>" placeholder="<?php echo $cfg['placeholder'] ?? ''; ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="full-width">
                    <label>Status zahtjeva</label>
                    <select name="status" class="status-select">
                        <option value="novi" <?php echo ($item['status'] == 'novi') ? 'selected' : ''; ?>>Novi zahtjev</option>
                        <option value="u_obradi" <?php echo ($item['status'] == 'u_obradi') ? 'selected' : ''; ?>>U obradi</option>
                        <option value="zavrseno" <?php echo ($item['status'] == 'zavrseno') ? 'selected' : ''; ?>>Završeno (Šalje obavijest)</option>
                        <option value="stornirano" <?php echo ($item['status'] == 'stornirano') ? 'selected' : ''; ?>>Stornirano</option>
                    </select>
                </div>
                
                <div class="full-width" style="margin-top:20px;">
                    <button type="submit" class="btn-save">Spremi promjene [i obavijesti naručitelja (ako je završeno)]</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>