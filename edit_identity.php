<?php
/**
 * v4.5.1 - OBRADA ZAHTJEVA (PODRŠKA ZA NOVA POLJA)
 * Branding: Hanžeković & Partneri
 */
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mailer.php';

session_start();

// Provjera ovlasti
if (!isset($_SESSION['user_id']) || $_SESSION['rola'] !== 'admin') { 
    header("Location: index.php"); 
    exit; 
}

$id = $_GET['id'] ?? null;
if (!$id) { 
    header("Location: dashboard.php"); 
    exit; 
}

// Dohvat podataka o identitetu
$stmt = $pdo->prepare("SELECT * FROM hanz_identities WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) { 
    die("Zahtjev nije pronađen."); 
}

// OBRADA SPREMANJA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $updates = []; 
        $vals = [':id' => $id, ':status' => $_POST['status']];
        
        // Dinamičko ažuriranje IT polja iz sheme
        foreach ($it_schema as $key => $cfg) {
            $updates[] = "$key = :$key";
            $vals[":$key"] = ($cfg['type'] === 'checkbox') ? (isset($_POST[$key]) ? 1 : 0) : ($_POST[$key] ?? null);
        }
        
        $sql = "UPDATE hanz_identities SET " . implode(', ', $updates) . ", status = :status, updated_at = NOW() WHERE id = :id";
        $pdo->prepare($sql)->execute($vals);

        // SLANJE ZAVRŠNOG MAILA PODNOSITELJU
        if ($_POST['status'] === 'zavrseno' && $item['status'] !== 'zavrseno') {
            $podnositelj = $item['trazi_osoba'];
            $primatelj_mail = $hanz_requestors[$podnositelj] ?? 'gkonjic@piopet.hr';
            
            // Generiranje tablice s podacima kandidata (uključujući nova polja)
            $htmlClientData = "";
            foreach ($form_schema as $key => $cfg) {
                $val = !empty($item[$key]) ? htmlspecialchars($item[$key]) : '-';
                
                // Formatiranje datuma
                if ($cfg['type'] === 'date' && !empty($item[$key])) {
                    $val = date('d.m.Y', strtotime($item[$key]));
                }
                
                $htmlClientData .= "<tr><td style='padding:8px; color:#666; border-bottom:1px solid #eee; width:40%;'>{$cfg['label']}</td><td style='padding:8px; font-weight:bold; border-bottom:1px solid #eee;'>$val</td></tr>";
            }

            // Generiranje tablice s IT podacima (korisničko ime, mail, itd.)
            $htmlItData = "";
            foreach ($it_schema as $key => $cfg) {
                if ($key === 'it_napomena') continue; // Napomenu šaljemo odvojeno dolje
                $val = ($cfg['type'] === 'checkbox') ? (isset($_POST[$key]) ? 'DA' : 'NE') : ($_POST[$key] ?? '-');
                $htmlItData .= "<tr><td style='padding:8px; color:#666; border-bottom:1px solid #eee; width:40%;'>{$cfg['label']}</td><td style='padding:8px; font-weight:bold; border-bottom:1px solid #eee;'>$val</td></tr>";
            }
            
            $baseUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            $tijelo = "<html><body style='font-family:Segoe UI,Arial; background:#f4f4f4; padding:20px;'>
                        <div style='max-width:600px; margin:0 auto; background:#fff; border:1px solid #e0e0e0;'>
                            <div style='background:#1a1a1a; color:#fff; padding:25px; text-align:center;'>
                                <h2 style='margin:0;'>HANŽEKOVIĆ & PARTNERI</h2>
                                <p style='font-size:11px; color:#888;'>OBAVIJEST O REALIZACIJI ZAHTJEVA</p>
                            </div>
                            <div style='padding:30px;'>
                                <p>Poštovani/a <strong>$podnositelj</strong>, obavještavamo Vas da je korisnički identitet spreman za rad:</p>
                                
                                <p style='font-size:12px; color:#999; text-transform:uppercase; margin-top:20px;'>Podaci o kandidatu</p>
                                <table style='width:100%; border-collapse:collapse; margin-bottom:20px;'>$htmlClientData</table>
                                
                                <p style='font-size:12px; color:#999; text-transform:uppercase;'>Tehnički podaci za pristup</p>
                                <table style='width:100%; border-collapse:collapse;'>$htmlItData</table>
                                
                                <div style='margin-top:20px; padding:15px; background:#f9f9f9; border-left:4px solid #1a1a1a; font-size:14px;'>
                                    <strong>Napomena IT odjela:</strong><br>" . nl2br(htmlspecialchars($_POST['it_napomena'])) . "
                                </div>

                                <hr style='border:0; border-top:1px solid #eee; margin:30px 0;'>
                                
                                <div style='text-align:center;'>
                                    <p style='font-weight:bold;'>Molimo Vas, ocijenite brzinu i kvalitetu ove realizacije:</p>
                                    <a href='$baseUrl/feedback.php?id=$id&rating=excellent' style='display:inline-block; padding:10px 15px; background:#28a745; color:#fff; text-decoration:none; border-radius:4px; margin:5px;'>Izvrsno 😃</a>
                                    <a href='$baseUrl/feedback.php?id=$id&rating=average' style='display:inline-block; padding:10px 15px; background:#ffc107; color:#000; text-decoration:none; border-radius:4px; margin:5px;'>Dobro 😐</a>
                                    <a href='$baseUrl/feedback.php?id=$id&rating=poor' style='display:inline-block; padding:10px 15px; background:#dc3545; color:#fff; text-decoration:none; border-radius:4px; margin:5px;'>Loše 😞</a>
                                </div>
                            </div>
                        </div></body></html>";
            
            posalji_obavijest($primatelj_mail, "REALIZIRANO: " . $item['ime'] . " " . $item['prezime'], $tijelo);
        }

        header("Location: edit_identity.php?id=$id&msg=ok"); 
        exit;

    } catch (PDOException $e) { 
        die("Greška prilikom spremanja: " . $e->getMessage()); 
    }
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <title>Obrada zahtjeva | Hanžeković & Partneri</title>
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg?v=2">
    <link rel="stylesheet" href="static/style.css">
</head>
<body class="dashboard-page">

<header>
    <div><strong>Hanžeković & Partneri</strong> | IDM Editor</div>
    <a href="dashboard.php" class="logout-link" style="border-color: #ccc; color: #ccc;">Odustani</a>
</header>

<div class="container">
    <a href="dashboard.php" class="back-link">&larr; Povratak na Dashboard</a>
    
    <div class="card">
        <h2>Obrada zahtjeva #<?php echo $id; ?>: <?php echo htmlspecialchars($item['ime'] . " " . $item['prezime']); ?></h2>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert-success" style="background:#d4edda; color:#155724; padding:15px; margin-bottom:20px; border-radius:4px; font-weight:bold; border: 1px solid #c3e6cb;">
                Uspješno spremljeno i ažurirano!
            </div>
        <?php endif; ?>

        <div class="grid">
            <div class="section-title">Podaci iz zahtjeva (Klijent)</div>
            <?php foreach ($form_schema as $key => $cfg): ?>
                <div class="info-card">
                    <label><?php echo $cfg['label']; ?></label>
                    <strong>
                        <?php 
                        $raw_val = $item[$key] ?? '-';
                        if ($cfg['type'] === 'date' && $raw_val !== '-') {
                            echo date('d.m.Y', strtotime($raw_val));
                        } else {
                            echo nl2br(htmlspecialchars($raw_val));
                        }
                        ?>
                    </strong>
                </div>
            <?php endforeach; ?>

            <form action="" method="POST" class="grid full-width" style="margin: 0; gap: 1rem;">
                <div class="section-title">IT Realizacija & Parametri</div>
                
                <?php foreach ($it_schema as $key => $cfg): ?>
                    <div class="<?php echo ($cfg['type'] === 'textarea') ? 'full-width' : ''; ?>">
                        <label><?php echo $cfg['label']; ?></label>
                        
                        <?php if ($cfg['type'] === 'textarea'): ?>
                            <textarea name="<?php echo $key; ?>" rows="3" placeholder="<?php echo $cfg['placeholder'] ?? ''; ?>"><?php echo htmlspecialchars($item[$key] ?? ''); ?></textarea>
                        
                        <?php elseif ($cfg['type'] === 'checkbox'): ?>
                            <label class="checkbox-label" style="background: #fcfcfc; border: 1px solid #eee; padding: 10px; border-radius: 4px;">
                                <input type="checkbox" name="<?php echo $key; ?>" <?php echo (!empty($item[$key])) ? 'checked' : ''; ?>> 
                                <?php echo $cfg['text'] ?? 'Omogućeno'; ?>
                            </label>
                        
                        <?php else: ?>
                            <input type="<?php echo $cfg['type']; ?>" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($item[$key] ?? ''); ?>" placeholder="<?php echo $cfg['placeholder'] ?? ''; ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="full-width" style="background: #fffbe6; padding: 20px; border-radius: 4px; border: 1px solid #ffe58f; margin-top: 10px;">
                    <label style="color: #856404;">Status obrade</label>
                    <select name="status" class="status-select" style="border-color: #fadb14;">
                        <option value="novi" <?php echo ($item['status'] == 'novi') ? 'selected' : ''; ?>>Novi zahtjev</option>
                        <option value="u_obradi" <?php echo ($item['status'] == 'u_obradi') ? 'selected' : ''; ?>>U obradi</option>
                        <option value="zavrseno" <?php echo ($item['status'] == 'zavrseno') ? 'selected' : ''; ?>>Završeno (Šalje obavijest podnositelju)</option>
                        <option value="stornirano" <?php echo ($item['status'] == 'stornirano') ? 'selected' : ''; ?>>Stornirano</option>
                    </select>
                    <p style="font-size: 11px; color: #856404; margin-top: 5px;">* Odabirom 'Završeno' sustav šalje automatski mail s tehničkim podacima i linkom za feedback.</p>
                </div>
                
                <div class="full-width" style="margin-top:20px;">
                    <button type="submit" class="btn-save" style="background: #1a1a1a; height: 50px; font-size: 1.1rem;">POHRANI PROMJENE</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="footer">DEVELOPED BY PIOPET</div>

</body>
</html>