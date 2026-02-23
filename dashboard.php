<?php
/**
 * v4.5.2 - FIX: Skriven OIB, Auto-size tablica, Satisfaction na dnu
 * Branding: Hanžeković & Partneri
 */
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mailer.php';

session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
$rola = $_SESSION['rola'];

$message = $_SESSION['f_msg'] ?? ''; 
$msg_type = $_SESSION['f_type'] ?? ''; 
unset($_SESSION['f_msg'], $_SESSION['f_type']);

// --- LOGIKA ZA NOVI ZAHTJEV ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_identity') {
    try {
        $cols = []; $slots = []; $vals = [];
        foreach ($form_schema as $key => $cfg) {
            // Preskačemo OIB ako nije poslan u formi (jer je skriven)
            if ($key === 'oib' && !isset($_POST[$key])) continue;
            
            $cols[] = $key; 
            $slots[] = ":$key"; 
            $vals[":$key"] = $_POST[$key] ?? null;
        }
        
        $sql = "INSERT INTO hanz_identities (" . implode(',', $cols) . ") VALUES (" . implode(',', $slots) . ")";
        $pdo->prepare($sql)->execute($vals);
        $new_id = $pdo->lastInsertId();
        
        $baseUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
        $editLink = "$baseUrl/edit_identity.php?id=$new_id";

        $subjekt = "NOVI ZAHTJEV: " . $_POST['ime'] . " " . $_POST['prezime'] . " (" . ($_POST['grad'] ?? '-') . ")";
        $mailRows = "";
        foreach ($form_schema as $key => $cfg) {
            $val = !empty($_POST[$key]) ? nl2br(htmlspecialchars($_POST[$key])) : '-';
            if ($cfg['type'] === 'date' && !empty($_POST[$key])) {
                $val = date('d.m.Y', strtotime($_POST[$key]));
            }
            $mailRows .= "<tr><td style='padding:10px; border-bottom:1px solid #eee;'>{$cfg['label']}</td><td style='padding:10px; border-bottom:1px solid #eee; font-weight:bold;'>$val</td></tr>";
        }
        
        $tijelo = "<html><body style='font-family:Segoe UI,Arial; background:#f4f4f4; padding:20px;'>
                    <div style='max-width:600px; margin:0 auto; background:#fff; border:1px solid #e0e0e0;'>
                        <div style='background:#1a1a1a; color:#fff; padding:25px; text-align:center;'>
                            <h2 style='margin:0;'>HANŽEKOVIĆ & PARTNERI</h2>
                            <p>NOVI ZAHTJEV #$new_id</p>
                        </div>
                        <div style='padding:30px;'><table style='width:100%;'>$mailRows</table>
                        <div style='margin-top:30px; text-align:center;'>
                            <a href='$editLink' style='background:#1a1a1a; color:#fff; padding:12px 25px; text-decoration:none; border-radius:4px; font-weight:bold; display:inline-block;'>OTVORI ZAHTJEV</a>
                        </div></div></div></body></html>";

        posalji_obavijest($it_primatelji, $subjekt, $tijelo);
        $_SESSION['f_msg'] = "Zahtjev uspješno poslan!";
        $_SESSION['f_type'] = "success";
        header("Location: dashboard.php"); exit;
    } catch (PDOException $e) { $message = "Greška: " . $e->getMessage(); $msg_type = "error"; }
}

$identities = ($rola === 'admin') ? $pdo->query("SELECT * FROM hanz_identities ORDER BY updated_at DESC")->fetchAll() : [];

// --- STATISTIKA FEEDBACKA ---
$stats = ['excellent' => 0, 'average' => 0, 'poor' => 0];
if ($rola === 'admin') {
    try {
        $stmt = $pdo->query("SELECT rating, COUNT(*) as count FROM hanz_feedback GROUP BY rating");
        while ($row = $stmt->fetch()) { $stats[$row['rating']] = $row['count']; }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <title>Hanžeković & Partneri | IDM</title>
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg?v=2">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="static/style.css">
    <style>
        /* Table Auto-size & Wrap fixes */
        .nowrap { white-space: nowrap !important; }
        #identitiesTable { font-size: 0.85rem; width: 100% !important; }
        #identitiesTable th, #identitiesTable td { padding: 12px 8px; vertical-align: middle; }
        .col-identity { min-width: 160px; }
        
        /* Satisfaction Icons Design */
        .satisfaction-card { margin-top: 30px; margin-bottom: 50px; border-top: 2px solid #eee; padding-top: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; text-align: center; }
        .stat-box { padding: 20px; border-radius: 6px; color: #fff; font-weight: bold; text-transform: uppercase; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .stat-exc { background-color: #28a745; }
        .stat-avg { background-color: #ffc107; color: #333; }
        .stat-poor { background-color: #dc3545; }
        .stat-num { display: block; font-size: 2.5rem; line-height: 1; margin-bottom: 5px; font-weight: 900; }
        .alert-success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; border: 1px solid #f5c6cb; }
    </style>
</head>
<body class="dashboard-page">

<header>
    <div><strong>Hanžeković & Partneri</strong> | IDM Dashboard</div>
    <a href="logout.php" class="logout-link">Odjava</a>
</header>

<div class="container">
    <?php if ($message): ?>
        <div class="<?php echo ($msg_type === 'error') ? 'alert-error' : 'alert-success'; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <h3>NOVI ZAHTJEV</h3>
        <form method="POST">
            <input type="hidden" name="action" value="new_identity">
            <div class="form-grid">
                <?php foreach ($form_schema as $key => $f): ?>
                    <?php 
                        // SAKRIVANJE OIB POLJA (Preskačemo iscrtavanje u formi)
                        if ($key === 'oib') continue; 

                        $value = isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : '';
                    ?>
                    <div class="<?php echo ($f['type'] === 'textarea') ? 'full-width' : ''; ?>">
                        <label><?php echo $f['label']; ?></label>
                        <?php if ($f['type'] === 'select'): ?>
                            <select name="<?php echo $key; ?>" <?php echo $f['required'] ? 'required' : ''; ?>>
                                <option value="" disabled <?php echo empty($value) ? 'selected' : ''; ?>>-- Odaberi --</option>
                                <?php foreach ($f['options'] as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($value == $opt) ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($f['type'] === 'textarea'): ?>
                            <textarea name="<?php echo $key; ?>" rows="2"><?php echo $value; ?></textarea>
                        <?php else: ?>
                            <input type="<?php echo $f['type']; ?>" name="<?php echo $key; ?>" value="<?php echo $value; ?>" <?php echo $f['required'] ? 'required' : ''; ?>
                                <?php if ($key === 'pin') echo 'maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, \'\')"'; ?>
                            >
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div><br>
            <button type="submit">POŠALJI ZAHTJEV</button>
        </form>
    </div>

    <?php if ($rola === 'admin'): ?>
    <div class="card">
        <h3>Pregled svih zahtjeva</h3>
        <table id="identitiesTable" class="display">
            <thead>
                <tr>
                    <th class="nowrap">ID</th>
                    <th class="col-identity">Identitet</th>
                    <th class="nowrap">Grad</th>
                    <th class="nowrap">Lokacija</th>
                    <th>Odjel</th>
                    <th class="nowrap">Dolazak</th>
                    <th class="nowrap">Zadnja promjena</th>
                    <th class="nowrap">Status</th>
                    <th class="nowrap">Akcija</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($identities as $idm): ?>
                <tr>
                    <td class="nowrap" data-order="<?php echo $idm['id']; ?>">#<?php echo $idm['id']; ?></td>
                    <td class="col-identity"><strong><?php echo htmlspecialchars($idm['ime']." ".$idm['prezime']); ?></strong><br><small><?php echo htmlspecialchars($idm['trazi_osoba']); ?></small></td>
                    <td class="nowrap"><?php echo htmlspecialchars($idm['grad'] ?? '-'); ?></td>
                    <td class="nowrap"><?php echo htmlspecialchars($idm['lokacija_rada'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($idm['odjel']); ?></td>
                    <td class="nowrap" data-order="<?php echo $idm['datum_dolaska']; ?>"><?php echo date('d.m.Y', strtotime($idm['datum_dolaska'])); ?></td>
                    <td class="nowrap" data-order="<?php echo $idm['updated_at']; ?>"><strong><?php echo date('d.m.Y H:i', strtotime($idm['updated_at'])); ?></strong></td>
                    <td class="nowrap"><span class="status status-<?php echo $idm['status']; ?>"><?php echo str_replace('_', ' ', $idm['status']); ?></span></td>
                    <td class="nowrap"><a href="edit_identity.php?id=<?php echo $idm['id']; ?>" style="font-weight:bold;">OBRADI</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card satisfaction-card">
        <h3>Korisničko zadovoljstvo</h3>
        <div class="stats-grid">
            <div class="stat-box stat-exc"><span class="stat-num"><?php echo $stats['excellent']; ?></span> Izvrsno 😃</div>
            <div class="stat-box stat-avg"><span class="stat-num"><?php echo $stats['average']; ?></span> Prosjećno 😐</div>
            <div class="stat-box stat-poor"><span class="stat-num"><?php echo $stats['poor']; ?></span> Loše 😞</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="footer">DEVELOPED BY PIOPET</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function() {
        $('#identitiesTable').DataTable({
            "pageLength": 10, "order": [[ 6, "desc" ]], "autoWidth": false,
            "language": { "search": "Pretraži:", "lengthMenu": "Prikaži _MENU_", "paginate": { "next": "Sljedeća", "previous": "Prethodna" } }
        });
    });
</script>
</body>
</html>