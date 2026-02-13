<?php
/**
 * v3.1.1 - FIX MAIL DIZAJN
 * Projekt: Hanžeković & Partneri IDM
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mailer.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['rola'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: dashboard.php"); exit; }
$message = '';
$msg_type = 'success';

$stmt = $pdo->prepare("SELECT * FROM hanz_identities WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { die("Zahtjev nije pronađen."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novi_status = $_POST['status'];
    $stari_status = $item['status'];

    try {
        $stmt = $pdo->prepare("UPDATE hanz_identities SET domain_username = ?, email_adresa = ?, vpn_potreban = ?, laptop_serijski = ?, it_napomena = ?, status = ? WHERE id = ?");
        $stmt->execute([$_POST['domain_username'], $_POST['email_adresa'], isset($_POST['vpn_potreban']) ? 1 : 0, $_POST['laptop_serijski'], $_POST['it_napomena'], $novi_status, $id]);

        // ----------------------------------------------------------
        // MAIL ZA KLIJENTA (ZAVRŠNI) - SVI PODACI UKLJUČENI
        // ----------------------------------------------------------
        if ($novi_status === 'zavrseno' && $stari_status !== 'zavrseno') {
            $podnositelj = $item['trazi_osoba'];
            $primatelj_mail = $hanz_requestors[$podnositelj] ?? 'gkonjic@piopet.hr';
            $subjekt = "ZAVRŠENO: Kreiran identitet - " . $item['ime'] . " " . $item['prezime'];
            
            // Linkovi za ocjenu
            $baseUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            $linkExc = "$baseUrl/feedback.php?id=$id&rating=excellent";
            $linkAvg = "$baseUrl/feedback.php?id=$id&rating=average";
            $linkBad = "$baseUrl/feedback.php?id=$id&rating=poor";

            // CSS za mail
            $styleTable = "width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 15px;";
            $styleTdLabel = "padding: 8px 10px; border-bottom: 1px solid #eeeeee; width: 40%; color: #666; vertical-align:top;";
            $styleTdValue = "padding: 8px 10px; border-bottom: 1px solid #eeeeee; font-weight: bold; color: #333;";
            $itNapomena = !empty($_POST['it_napomena']) ? nl2br(htmlspecialchars($_POST['it_napomena'])) : 'Nema napomene.';

            $tijelo = "
            <html>
            <body style='font-family: \"Segoe UI\", Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px;'>
                <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e0e0e0; box-shadow: 0 2px 10px rgba(0,0,0,0.05);'>
                    
                    <div style='background-color: #1a1a1a; color: #ffffff; padding: 25px; text-align: center;'>
                        <h2 style='margin: 0; font-weight: 400; letter-spacing: 1px;'>HANŽEKOVIĆ & PARTNERI</h2>
                        <p style='margin: 5px 0 0; font-size: 11px; text-transform: uppercase; color: #888;'>STATUS ZAHTJEVA: ZAVRŠENO</p>
                    </div>

                    <div style='padding: 30px;'>
                        <p style='font-size: 16px; margin-top:0;'>Poštovani/a <strong>$podnositelj</strong>,</p>
                        <p style='color: #666; line-height: 1.5;'>Obavještavamo vas da je korisnički identitet uspješno kreiran.</p>
                        
                        <div style='background-color: #f9f9f9; padding: 1px 15px 15px 15px; border-top: 3px solid #1a1a1a; margin: 20px 0;'>
                            <p style='font-size:12px; text-transform:uppercase; color:#999; margin-bottom:5px; margin-top:15px;'>OSNOVNI PODACI</p>
                            <table style='width: 100%; font-size: 14px; margin-bottom: 15px;'>
                                <tr><td style='$styleTdLabel'>Korisnik:</td><td style='$styleTdValue'>" . $item['ime'] . " " . $item['prezime'] . "</td></tr>
                                <tr><td style='$styleTdLabel'>Odjel / Uloga:</td><td style='$styleTdValue'>" . $item['odjel'] . "</td></tr>
                                <tr><td style='$styleTdLabel'>Datum dolaska:</td><td style='$styleTdValue'>" . date('d.m.Y', strtotime($item['datum_dolaska'])) . "</td></tr>
                                <tr><td style='$styleTdLabel'>Zahtjev podnio/la:</td><td style='$styleTdValue'>" . $item['trazi_osoba'] . "</td></tr>
                            </table>

                            <p style='font-size:12px; text-transform:uppercase; color:#999; margin-bottom:5px;'>TEHNIČKI PODACI</p>
                            <table style='width: 100%; font-size: 14px;'>
                                <tr><td style='$styleTdLabel'>Korisničko ime:</td><td style='$styleTdValue; font-family:monospace; font-size:15px; color:#d63384;'>" . $_POST['domain_username'] . "</td></tr>
                                <tr><td style='$styleTdLabel'>E-mail adresa:</td><td style='$styleTdValue'><a href='mailto:" . $_POST['email_adresa'] . "' style='color:#333; text-decoration:none;'>" . $_POST['email_adresa'] . "</a></td></tr>
                                <tr><td style='$styleTdLabel'>Laptop S/N:</td><td style='$styleTdValue'>" . ($_POST['laptop_serijski'] ?: 'Nije dodijeljen') . "</td></tr>
                                <tr><td style='$styleTdLabel'>VPN Pristup:</td><td style='$styleTdValue'>" . (isset($_POST['vpn_potreban']) ? 'DA' : 'NE') . "</td></tr>
                            </table>
                        </div>

                        <p style='font-size: 13px; color: #888; margin-bottom: 5px;'>Napomena IT odjela:</p>
                        <p style='font-size: 14px; font-style: italic; color: #555; margin-top: 0;'>$itNapomena</p>

                        <hr style='border: 0; border-top: 1px solid #eee; margin: 30px 0;'>
                        
                        <div style='text-align: center;'>
                            <p style='font-weight: bold; margin-bottom: 20px;'>Kako ste zadovoljni uslugom?</p>
                            <div>
                                <a href='$linkExc' style='display:inline-block; padding:10px 20px; background:#28a745; color:white; text-decoration:none; border-radius:4px; font-weight:bold; margin:0 5px;'>Izvrsno &#128522;</a>
                                <a href='$linkAvg' style='display:inline-block; padding:10px 20px; background:#ffc107; color:black; text-decoration:none; border-radius:4px; font-weight:bold; margin:0 5px;'>Dobro &#128528;</a>
                                <a href='$linkBad' style='display:inline-block; padding:10px 20px; background:#dc3545; color:white; text-decoration:none; border-radius:4px; font-weight:bold; margin:0 5px;'>Loše &#128545;</a>
                            </div>
                        </div>
                    </div>
                    
                    <div style='background-color: #f9f9f9; padding: 15px; text-align: center; font-size: 11px; color: #999; border-top: 1px solid #eee;'>
                        Hanžeković & Partneri d.o.o.<br>
                        " . date('d.m.Y H:i') . "
                    </div>
                </div>
            </body>
            </html>";

            posalji_obavijest($primatelj_mail, $subjekt, $tijelo);
            $message = "Spremljeno! Službeni mail potvrde poslan.";
        } else {
            $message = "Podaci uspješno ažurirani.";
        }
        
        $stmt = $pdo->prepare("SELECT * FROM hanz_identities WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();

    } catch (PDOException $e) {
        $message = "Greška: " . $e->getMessage(); $msg_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <title>Obrada zahtjeva | Hanžeković IDM</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; margin: 0; padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 2rem; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #1a1a1a; padding-bottom: 10px; margin-top: 0; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .section-title { grid-column: span 2; background: #eee; padding: 8px 12px; font-weight: bold; margin-top: 15px; font-size: 0.9rem; }
        label { display: block; font-size: 0.8rem; color: #666; margin-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .info-box { background: #f9f9f9; padding: 12px; border-left: 4px solid #1a1a1a; }
        .btn-save { background: #1a1a1a; color: white; border: none; padding: 12px 25px; cursor: pointer; border-radius: 4px; font-weight: bold; width: 100%; }
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" style="text-decoration: none; color: #666; font-size: 0.9rem;">&larr; Povratak na popis</a>
    <h2>Obrada: <?php echo htmlspecialchars($item['ime'] . " " . $item['prezime']); ?></h2>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="grid">
        <div class="section-title">Podaci o zahtjevu (Klijent)</div>
        <div class="info-box">
            <label>Zahtjev podnio/la</label>
            <strong><?php echo htmlspecialchars($item['trazi_osoba']); ?></strong>
        </div>
        <div class="info-box">
            <label>Odjel / Datum dolaska</label>
            <strong><?php echo htmlspecialchars($item['odjel']); ?> / <?php echo date('d.m.Y', strtotime($item['datum_dolaska'])); ?></strong>
        </div>
        <div style="grid-column: span 2;">
            <label>Napomena klijenta:</label>
            <div style="padding: 10px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 4px; font-size: 0.9rem;">
                <?php echo nl2br(htmlspecialchars($item['klijent_napomena'] ?: 'Nema napomene.')); ?>
            </div>
        </div>

        <form action="" method="POST" class="grid" style="grid-column: span 2; margin: 0;">
            <div class="section-title">IT Support Akcije</div>
            <div><label>Domain Username</label><input type="text" name="domain_username" value="<?php echo htmlspecialchars($item['domain_username']); ?>"></div>
            <div><label>Email Adresa</label><input type="email" name="email_adresa" value="<?php echo htmlspecialchars($item['email_adresa']); ?>"></div>
            <div><label>Laptop S/N</label><input type="text" name="laptop_serijski" value="<?php echo htmlspecialchars($item['laptop_serijski']); ?>"></div>
            <div>
                <label>Status Zahtjeva</label>
                <select name="status">
                    <option value="novi" <?php if($item['status'] == 'novi') echo 'selected'; ?>>Novi</option>
                    <option value="u_obradi" <?php if($item['status'] == 'u_obradi') echo 'selected'; ?>>U obradi</option>
                    <option value="zavrseno" <?php if($item['status'] == 'zavrseno') echo 'selected'; ?>>Završeno (Šalje mail)</option>
                    <option value="stornirano" <?php if($item['status'] == 'stornirano') echo 'selected'; ?>>Stornirano</option>
                </select>
            </div>
            <div style="grid-column: span 2;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="vpn_potreban" <?php if($item['vpn_potreban']) echo 'checked'; ?> style="width: auto;"> VPN Pristup omogućen
                </label>
            </div>
            <div style="grid-column: span 2;">
                <label>Interna IT napomena</label>
                <textarea name="it_napomena" rows="3"><?php echo htmlspecialchars($item['it_napomena']); ?></textarea>
            </div>
            <div style="grid-column: span 2; margin-top: 10px;"><button type="submit" class="btn-save">Spremi promjene i obavijesti korisnika</button></div>
        </form>
    </div>
</div>
</body>
</html>