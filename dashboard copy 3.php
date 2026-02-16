<?php
/**
 * v4.4.3 - DASHBOARD (RESTORED BUTTON)
 */
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mailer.php';

session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
$rola = $_SESSION['rola'];

$message = $_SESSION['f_msg'] ?? ''; 
$msg_type = $_SESSION['f_type'] ?? ''; 
unset($_SESSION['f_msg'], $_SESSION['f_type']);

$oib_error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_identity') {
    
    // SERVER PROVJERA OIB-a
    $uneseni_oib = $_POST['oib'] ?? '';
    if (!empty($uneseni_oib) && (strlen($uneseni_oib) !== 11 || !ctype_digit($uneseni_oib))) {
        $message = "GREŠKA: OIB mora imati točno 11 znamenki!";
        $msg_type = "error";
        $oib_error = true;
    } else {
        try {
            $cols = []; $slots = []; $vals = [];
            foreach ($form_schema as $key => $cfg) {
                $cols[] = $key; $slots[] = ":$key"; $vals[":$key"] = $_POST[$key] ?? null;
            }
            $sql = "INSERT INTO hanz_identities (" . implode(',', $cols) . ") VALUES (" . implode(',', $slots) . ")";
            $pdo->prepare($sql)->execute($vals);
            
            // --- BITNO: DOHVAĆAMO ID NOVOG ZAHTJEVA ---
            $new_id = $pdo->lastInsertId();
            
            // Konstruiramo link (automatski prepoznaje server i putanju)
            $baseUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            $editLink = "$baseUrl/edit_identity.php?id=$new_id";

            // MAIL
            $subjekt = "NOVI ZAHTJEV: " . $_POST['ime'] . " " . $_POST['prezime'];
            $mailRows = "";
            foreach ($form_schema as $key => $cfg) {
                $val = !empty($_POST[$key]) ? nl2br(htmlspecialchars($_POST[$key])) : '-';
                $mailRows .= "<tr><td style='padding:10px; border-bottom:1px solid #eee;'>{$cfg['label']}</td><td style='padding:10px; border-bottom:1px solid #eee; font-weight:bold;'>$val</td></tr>";
            }
            
            // VRAĆEN GUMB U HTML
            $tijelo = "<html><body style='font-family:Segoe UI,Arial; background:#f4f4f4; padding:20px;'>
                        <div style='max-width:600px; margin:0 auto; background:#fff; border:1px solid #e0e0e0;'>
                            <div style='background:#1a1a1a; color:#fff; padding:25px; text-align:center;'>
                                <h2 style='margin:0;'>HANŽEKOVIĆ & PARTNERI</h2>
                                <p>NOVI ZAHTJEV #$new_id</p>
                            </div>
                            <div style='padding:30px;'>
                                <table style='width:100%;'>$mailRows</table>
                                <div style='margin-top:30px; text-align:center;'>
                                    <a href='$editLink' style='background:#1a1a1a; color:#fff; padding:12px 25px; text-decoration:none; border-radius:4px; font-weight:bold; display:inline-block;'>OTVORI I OBRADI ZAHTJEV</a>
                                </div>
                            </div>
                        </div></body></html>";

            // KORISTIMO VARIJABLU IZ DB_CONFIG (Popravljeno u prošlom koraku)
            $primatelji = isset($it_primatelji) ? $it_primatelji : ['gkonjic@piopet.hr'];
            posalji_obavijest($primatelji, $subjekt, $tijelo);

            $_SESSION['f_msg'] = "Zahtjev uspješno poslan!";
            $_SESSION['f_type'] = "success";
            header("Location: dashboard.php"); exit;

        } catch (PDOException $e) { 
            $message = "Greška: " . $e->getMessage(); 
            $msg_type = "error";
        }
    }
}

$identities = ($rola === 'admin') ? $pdo->query("SELECT * FROM hanz_identities ORDER BY updated_at DESC")->fetchAll() : [];
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <title>Hanžeković & Partneri | IDM</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="static/style.css">
    <style>
        .input-error { border: 2px solid #dc3545 !important; background-color: #fff8f8 !important; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; }
        .alert-success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; }
    </style>
</head>
<body class="dashboard-page">

<header>
    <div><strong>Hanžeković & Partneri</strong> | IDM Dashboard</div>
    <a href="logout.php" class="logout-link">Odjava</a>
</header>

<div class="container">
    <?php if ($message): ?>
        <div class="<?php echo ($msg_type === 'error') ? 'alert-error' : 'alert-success'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <h3>NOVI ZAHTJEV</h3>
        <form method="POST">
            <input type="hidden" name="action" value="new_identity">
            <div class="form-grid">
                <?php foreach ($form_schema as $key => $f): ?>
                    <?php 
                        $value = isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : '';
                        $errorClass = ($key === 'oib' && $oib_error) ? 'input-error' : '';
                    ?>
                    
                    <div class="<?php echo ($f['type'] === 'textarea') ? 'full-width' : ''; ?>">
                        <label><?php echo $f['label']; ?></label>
                        
                        <?php if ($f['type'] === 'select'): ?>
                            <select name="<?php echo $key; ?>" <?php echo $f['required'] ? 'required' : ''; ?>>
                                <option value="" disabled <?php echo empty($value) ? 'selected' : ''; ?>>-- Odaberi --</option>
                                <?php foreach ($f['options'] as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($value == $opt) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($opt); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($f['type'] === 'textarea'): ?>
                            <textarea name="<?php echo $key; ?>" rows="2"><?php echo $value; ?></textarea>

                        <?php else: ?>
                            <input 
                                type="<?php echo $f['type']; ?>" 
                                name="<?php echo $key; ?>" 
                                value="<?php echo $value; ?>"
                                class="<?php echo $errorClass; ?>"
                                <?php echo $f['required'] ? 'required' : ''; ?>
                                
                                <?php 
                                if ($key === 'oib') {
                                    echo 'maxlength="11" '; 
                                    echo 'oninput="this.value = this.value.replace(/[^0-9]/g, \'\').slice(0, 11)" ';
                                    echo 'placeholder="Unesi točno 11 znamenki" ';
                                }
                                ?>
                            >
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
            <thead><tr><th>ID</th><th>Identitet</th><th>Odjel</th><th>Dolazak</th><th>Zadnja promjena</th><th>Status</th><th>Akcija</th></tr></thead>
            <tbody>
                <?php foreach ($identities as $idm): ?>
                <tr>
                    <td data-order="<?php echo $idm['id']; ?>">#<?php echo $idm['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($idm['ime']." ".$idm['prezime']); ?></strong><br><small><?php echo htmlspecialchars($idm['trazi_osoba']); ?></small></td>
                    <td><?php echo htmlspecialchars($idm['odjel']); ?></td>
                    <td><?php echo date('d.m.Y', strtotime($idm['datum_dolaska'])); ?></td>
                    <td><strong><?php echo date('d.m.Y H:i', strtotime($idm['updated_at'])); ?></strong></td>
                    <td><span class="status status-<?php echo $idm['status']; ?>"><?php echo str_replace('_', ' ', $idm['status']); ?></span></td>
                    <td><a href="edit_identity.php?id=<?php echo $idm['id']; ?>" style="font-weight:bold;">Obradi</a></td>
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
            "pageLength": 10, "order": [[ 4, "desc" ]],
            "language": {
                "search": "Pretraži:",
                "lengthMenu": "Prikaži _MENU_ zapisa",
                "info": "Prikaz _START_ do _END_ od _TOTAL_ zahtjeva",
                "infoEmpty": "Nema zapisa za prikaz",
                "infoFiltered": "(filtrirano od ukupno _MAX_ zapisa)",
                "zeroRecords": "Nema rezultata",
                "paginate": { "next": "Sljedeća", "previous": "Prethodna" }
            }
        });
    });
</script>
</body>
</html>