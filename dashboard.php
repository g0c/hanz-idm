<?php
/**
 * v3.2.0 - FIX: PRG Pattern (Sprječava dupliranje na F5), Branding, Refresh fix.
 * Projekt: Hanžeković & Partneri IDM
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mailer.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$rola = $_SESSION['rola'];

// 1. OBRADA POŠILJANJA OBRASCA (Mora biti na samom vrhu)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_identity') {
    try {
        $stmt = $pdo->prepare("INSERT INTO hanz_identities (ime, prezime, trazi_osoba, oib, datum_dolaska, odjel, klijent_napomena) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['ime'], 
            $_POST['prezime'], 
            $_POST['trazi_osoba'], 
            $_POST['oib'], 
            $_POST['datum_dolaska'], 
            $_POST['odjel'], 
            $_POST['klijent_napomena']
        ]);

        // Slanje maila
        $primatelj = 'gkonjic@piopet.hr'; 
        $subjekt = "NOVI ZAHTJEV: " . $_POST['ime'] . " " . $_POST['prezime'] . " (" . $_POST['odjel'] . ")";
        
        // CSS
        $styleTable = "width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px;";
        $styleTdLabel = "padding: 10px; border-bottom: 1px solid #eeeeee; width: 35%; color: #666; vertical-align: top;";
        $styleTdValue = "padding: 10px; border-bottom: 1px solid #eeeeee; font-weight: bold; color: #333;";
        $napomena = !empty($_POST['klijent_napomena']) ? nl2br(htmlspecialchars($_POST['klijent_napomena'])) : '<span style="color:#ccc">Nema napomene</span>';

        // HTML Mail
        $tijelo = "
        <html>
        <body style='font-family: \"Segoe UI\", Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e0e0e0; box-shadow: 0 2px 10px rgba(0,0,0,0.05);'>
                <div style='background-color: #1a1a1a; color: #ffffff; padding: 25px; text-align: center;'>
                    <h2 style='margin: 0; font-weight: 400; letter-spacing: 1px;'>HANŽEKOVIĆ & PARTNERI</h2>
                    <p style='margin: 5px 0 0; font-size: 11px; text-transform: uppercase; color: #888;'>NOVI IT ZAHTJEV</p>
                </div>
                <div style='padding: 30px;'>
                    <p style='font-size: 16px; color: #333; margin-top: 0;'>Poštovani,</p>
                    <p style='color: #666; line-height: 1.5;'>Zaprimljen je novi zahtjev za kreiranje identiteta:</p>
                    <table style='$styleTable'>
                        <tr><td style='$styleTdLabel'>Kandidat:</td><td style='$styleTdValue; font-size: 16px;'>" . $_POST['ime'] . " " . $_POST['prezime'] . "</td></tr>
                        <tr><td style='$styleTdLabel'>OIB:</td><td style='$styleTdValue'>" . ($_POST['oib'] ?: '-') . "</td></tr>
                        <tr><td style='$styleTdLabel'>Odjel / Uloga:</td><td style='$styleTdValue'>" . $_POST['odjel'] . "</td></tr>
                        <tr><td style='$styleTdLabel'>Datum dolaska:</td><td style='$styleTdValue'>" . date('d.m.Y', strtotime($_POST['datum_dolaska'])) . "</td></tr>
                        <tr><td style='$styleTdLabel'>Zahtjev podnio/la:</td><td style='$styleTdValue; color: #d63384;'>" . $_POST['trazi_osoba'] . "</td></tr>
                        <tr><td style='$styleTdLabel; border-bottom: none;'>Napomena:</td><td style='$styleTdValue; border-bottom: none; font-weight: normal; background-color: #f9f9f9;'>$napomena</td></tr>
                    </table>
                    <div style='margin-top: 30px; text-align: center;'>
                        <a href='http://hanz-idm/dashboard.php' style='background-color: #1a1a1a; color: #ffffff; text-decoration: none; padding: 12px 25px; border-radius: 4px; font-weight: bold; font-size: 14px;'>Otvori IDM Sustav</a>
                    </div>
                </div>
                <div style='background-color: #f9f9f9; padding: 15px; text-align: center; font-size: 11px; color: #999; border-top: 1px solid #eee;'>
                    Hanžeković & Partneri d.o.o.<br>" . date('d.m.Y H:i') . "
                </div>
            </div>
        </body>
        </html>";

        posalji_obavijest($primatelj, $subjekt, $tijelo);

        // KLJUČNO: Postavi poruku u sesiju i preusmjeri (REDIRECT)
        $_SESSION['flash_msg'] = "Zahtjev uspješno poslan IT odjelu!";
        $_SESSION['flash_type'] = "success";
        header("Location: dashboard.php");
        exit; // Prekida izvršavanje, browser učitava stranicu ispočetka (GET)

    } catch (PDOException $e) {
        $_SESSION['flash_msg'] = "Greška baze podataka: " . $e->getMessage();
        $_SESSION['flash_type'] = "error";
        header("Location: dashboard.php");
        exit;
    }
}

// 2. DOHVAT PODATAKA (Izvršava se nakon redirecta, pa vidi nove podatke)
$identities = ($rola === 'admin') ? $pdo->query("SELECT * FROM hanz_identities ORDER BY updated_at DESC")->fetchAll() : [];

// 3. PRIKAZ PORUKA IZ SESIJE
$message = $_SESSION['flash_msg'] ?? '';
$msg_type = $_SESSION['flash_type'] ?? '';
// Očisti poruku da se ne prikazuje vječno
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <title>Hanžeković & Partneri | IDM Dashboard</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; margin: 0; }
        header { background: #1a1a1a; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .container { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .card { background: #fff; padding: 1.5rem; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .full-width { grid-column: span 2; }
        label { display: block; font-size: 0.85rem; color: #666; margin-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #1a1a1a; color: #fff; border: none; padding: 12px 20px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .status { padding: 4px 8px; border-radius: 12px; font-size: 0.72rem; font-weight: bold; text-transform: uppercase; }
        .status-novi { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-u_obradi { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .status-zavrseno { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .time-info { font-size: 0.75rem; color: #888; display: block; }
        .logout { color: #ff6b6b; text-decoration: none; border: 1px solid #ff6b6b; padding: 5px 15px; border-radius: 4px; }
    </style>
</head>
<body>

<header>
    <div><strong>Hanžeković & Partneri</strong> | IDM Dashboard</div>
    <a href="logout.php" class="logout">Odjava</a>
</header>

<div class="container">
    <?php if ($message): ?>
        <div style="padding:1rem; margin-bottom:1rem; border-radius:4px; background:<?php echo $msg_type=='success'?'#d4edda': ($msg_type=='warning'?'#fff3cd':'#f8d7da'); ?>;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3>Novi Zahtjev</h3>
        <form method="POST">
            <input type="hidden" name="action" value="new_identity">
            <div class="form-grid">
                <div>
                    <label><strong>Zahtjev podnosi (Partner/Manager)</strong></label>
                    <select name="trazi_osoba" required>
                        <option value="" disabled selected>-- Odaberite --</option>
                        <?php foreach ($hanz_requestors as $ime => $email): ?>
                            <option value="<?php echo htmlspecialchars($ime); ?>"><?php echo htmlspecialchars($ime); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div></div>
                <hr class="full-width" style="border: 0; border-top: 1px solid #eee; margin: 5px 0 15px 0;">
                <div><label>Ime kandidata</label><input type="text" name="ime" required></div>
                <div><label>Prezime kandidata</label><input type="text" name="prezime" required></div>
                <div><label>OIB (Opcija)</label><input type="text" name="oib"></div>
                <div>
                    <label>Odjel / Uloga</label>
                    <select name="odjel" required>
                        <option value="" disabled selected>-- Odaberite --</option>
                        <?php foreach ($hanz_departments as $odjel): ?>
                            <option value="<?php echo htmlspecialchars($odjel); ?>"><?php echo htmlspecialchars($odjel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Datum dolaska</label><input type="date" name="datum_dolaska" required></div>
                <div class="full-width"><label>Napomena</label><textarea name="klijent_napomena" rows="2"></textarea></div>
            </div><br>
            <button type="submit">Pošalji IT Supportu</button>
        </form>
    </div>

    <?php if ($rola === 'admin'): ?>
    <div class="card">
        <h3>Pregled svih zahtjeva</h3>
        <table id="identitiesTable" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Identitet</th>
                    <th>Odjel</th>
                    <th>Dolazak</th>
                    <th>Kreirano</th>
                    <th>Zadnja promjena</th>
                    <th>Status</th>
                    <th>Akcija</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($identities as $idm): ?>
                <tr>
                    <td>#<?php echo $idm['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($idm['ime']." ".$idm['prezime']); ?></strong><span class="time-info">Traži: <?php echo htmlspecialchars($idm['trazi_osoba']); ?></span></td>
                    <td><?php echo htmlspecialchars($idm['odjel']); ?></td>
                    <td><?php echo date('d.m.Y', strtotime($idm['datum_dolaska'])); ?></td>
                    <td><span class="time-info"><?php echo date('d.m.Y H:i', strtotime($idm['created_at'])); ?></span></td>
                    <td><span class="time-info"><?php echo date('d.m.Y H:i', strtotime($idm['updated_at'])); ?></span></td>
                    <td><span class="status status-<?php echo $idm['status']; ?>"><?php echo strtoupper(str_replace('_', ' ', $idm['status'])); ?></span></td>
                    <td><a href="edit_identity.php?id=<?php echo $idm['id']; ?>">Obradi</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
    $(document).ready(function() {
        // Provjera da li jQuery radi
        console.log("jQuery verzija: " + $.fn.jquery);
        
        // Inicijalizacija tablice
        $('#identitiesTable').DataTable({
            "destroy": true, // Uništi staru ako postoji (za svaki slučaj)
            "pageLength": 10, // Prikazuj 10 zapisa (budući da imaš 16, mora se pojaviti paginacija)
            "order": [[ 5, "desc" ]], // Sortiraj po zadnjoj promjeni (6. kolona, indeks 5)
            "language": {
                "search": "🔍 Pretraži:",
                "lengthMenu": "Prikaži _MENU_ zapisa",
                "zeroRecords": "Nema rezultata",
                "info": "Prikaz _START_ do _END_ od _TOTAL_ zahtjeva",
                "infoEmpty": "Nema podataka",
                "infoFiltered": "(filtrirano od _MAX_ ukupno)",
                "paginate": {
                    "first": "Prva",
                    "last": "Zadnja",
                    "next": "Sljedeća >",
                    "previous": "< Prethodna"
                }
            }
        });
        
        console.log("DataTables inicijaliziran.");
    });
</script>
</body>
</html>