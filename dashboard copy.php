<?php
/**
 * v4.3.2 - LOCALIZED DASHBOARD (FIXED MULTI-SEND)
 */
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mailer.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$rola = $_SESSION['rola'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_identity') {
    try {
        // 1. DINAMIČKI INSERT U BAZU
        $cols = []; $slots = []; $vals = [];
        foreach ($form_schema as $key => $cfg) {
            $cols[] = $key;
            $slots[] = ":$key";
            $vals[":$key"] = $_POST[$key] ?? null;
        }
        $sql = "INSERT INTO hanz_identities (" . implode(',', $cols) . ") VALUES (" . implode(',', $slots) . ")";
        $pdo->prepare($sql)->execute($vals);

        // 2. PRIPREMA PODATAKA ZA MAIL
        $subjekt = "NOVI ZAHTJEV: " . $_POST['ime'] . " " . $_POST['prezime'];
        
        // Ovdje možemo definirati kome se šalje (IT TIM), ali to je ipak u db_config.php jer tamo držimo sve liste i konfiguracije, pa je lakše održavati na jednom mjestu.
        //$it_primatelji = ['gkonjic@piopet.hr', 'lhancic@piopet.hr', 'lkoscec@piopet.hr'];
        
        // 3. GENERIRANJE HTML TABLICE (TIJELO MAILA)
        $mailRows = "";
        foreach ($form_schema as $key => $cfg) {
            $val = !empty($_POST[$key]) ? nl2br(htmlspecialchars($_POST[$key])) : '-';
            $mailRows .= "<tr>
                            <td style='padding:10px; border-bottom:1px solid #eee; color:#666; font-size:13px;'>{$cfg['label']}</td>
                            <td style='padding:10px; border-bottom:1px solid #eee; color:#333; font-weight:bold;'>$val</td>
                          </tr>";
        }

        $tijelo = "<html><body style='font-family:Segoe UI,Arial,sans-serif; background:#f4f4f4; padding:20px;'>
                    <div style='max-width:600px; margin:0 auto; background:#fff; border:1px solid #e0e0e0;'>
                        <div style='background:#1a1a1a; color:#fff; padding:25px; text-align:center;'>
                            <h2 style='margin:0; font-weight:400;'>HANŽEKOVIĆ & PARTNERI</h2>
                            <p style='font-size:11px; color:#888;'>NOVI ZAHTJEV ZA IDENTITET</p>
                        </div>
                        <div style='padding:30px;'>
                            <p>Zaprimljen je novi zahtjev:</p>
                            <table style='width:100%; border-collapse:collapse; margin-top:20px;'>$mailRows</table>
                        </div>
                    </div></body></html>";

        // 4. SLANJE MAILA (Sada je tijelo definirano, pa neće baciti grešku)
        // Šaljemo niz primatelja (provjeri da je mailer.php ažuriran da podržava nizove)
        posalji_obavijest($it_primatelji, $subjekt, $tijelo);

        // 5. REDIRECT
        $_SESSION['f_msg'] = "Zahtjev je uspješno poslan!";
        $_SESSION['f_type'] = "success";
        header("Location: dashboard.php");
        exit;

    } catch (PDOException $e) {
        $_SESSION['f_msg'] = "Greška: " . $e->getMessage();
        header("Location: dashboard.php");
        exit;
    }
}

$identities = ($rola === 'admin') ? $pdo->query("SELECT * FROM hanz_identities ORDER BY updated_at DESC")->fetchAll() : [];
$message = $_SESSION['f_msg'] ?? ''; 
unset($_SESSION['f_msg']);
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <title>Hanžeković & Partneri | IDM</title>
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    
    <link rel="stylesheet" href="./static/style.css">
</head>
<body class="dashboard-page">
<header>
    <div><strong>Hanžeković & Partneri</strong> | IDM Dashboard</div>
    <a href="logout.php" style="color:#ff6b6b; text-decoration:none;">Odjava</a>
</header>
<div class="container">
    <?php if ($message): ?>
        <div style="padding:1rem; background:#d4edda; margin-bottom:1rem; border-radius:4px;"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <h3>NOVI ZAHTJEV</h3>
        <form method="POST">
            <input type="hidden" name="action" value="new_identity">
            <div class="form-grid">
                <?php foreach ($form_schema as $key => $f): ?>
                    <div class="<?php echo ($f['type'] === 'textarea') ? 'full-width' : ''; ?>">
                        <label><?php echo $f['label']; ?></label>
                        <?php if ($f['type'] === 'select'): ?>
                            <select name="<?php echo $key; ?>" required>
                                <option value="" disabled selected>-- Odaberi --</option>
                                <?php foreach ($f['options'] as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($f['type'] === 'textarea'): ?>
                            <textarea name="<?php echo $key; ?>" rows="2"></textarea>
                        <?php else: ?>
                            <input type="<?php echo $f['type']; ?>" name="<?php echo $key; ?>" required>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div><br>
            <button type="submit">Spremi i pošalji IT-u</button>
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
                    <td><strong><?php echo htmlspecialchars($idm['ime']." ".$idm['prezime']); ?></strong><br><small><?php echo htmlspecialchars($idm['trazi_osoba']); ?></small></td>
                    <td><?php echo htmlspecialchars($idm['odjel']); ?></td>
                    <td><?php echo date('d.m.Y', strtotime($idm['datum_dolaska'])); ?></td>
                    <td><small><?php echo date('d.m.Y H:i', strtotime($idm['created_at'])); ?></small></td>
                    <td><strong><?php echo date('d.m.Y H:i', strtotime($idm['updated_at'])); ?></strong></td>
                    <td><span class="status status-<?php echo $idm['status']; ?>"><?php echo str_replace('_', ' ', $idm['status']); ?></span></td>
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
        $('#identitiesTable').DataTable({
            "pageLength": 10, 
            "order": [[ 5, "desc" ]],
            "language": {
                "search": "Pretraži:", 
                "lengthMenu": "Prikaži _MENU_ zapisa",
                "info": "Prikaz _START_ do _END_ od _TOTAL_ zahtjeva",
                "paginate": { "next": "Sljedeća", "previous": "Prethodna" }
            }
        });
    });
</script>
</body>
</html>