<?php
/**
 * v4.2.2 - DYNAMIC ENGINE + UNIFIED FONTS
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

// 1. OBRADA POST ZAHTJEVA (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_identity') {
    try {
        $cols = []; $slots = []; $vals = [];
        foreach ($form_schema as $key => $cfg) {
            $cols[] = $key;
            $slots[] = ":$key";
            $vals[":$key"] = $_POST[$key] ?? null;
        }

        $sql = "INSERT INTO hanz_identities (" . implode(',', $cols) . ") VALUES (" . implode(',', $slots) . ")";
        $pdo->prepare($sql)->execute($vals);

        // Dinamički HTML Mail
        $primatelj = 'gkonjic@piopet.hr';
        $subjekt = "NOVI ZAHTJEV: " . $_POST['ime'] . " " . $_POST['prezime'];
        
        $mailRows = "";
        foreach ($form_schema as $key => $cfg) {
            $val = !empty($_POST[$key]) ? nl2br(htmlspecialchars($_POST[$key])) : '-';
            $mailRows .= "<tr><td style='padding:10px; border-bottom:1px solid #eee; width:35%; color:#666; font-size:13px;'>{$cfg['label']}</td>
                          <td style='padding:10px; border-bottom:1px solid #eee; color:#333; font-weight:bold; font-size:14px;'>$val</td></tr>";
        }

        $tijelo = "<html><body style='font-family:Segoe UI,Arial,sans-serif; background:#f4f4f4; padding:20px;'>
                    <div style='max-width:600px; margin:0 auto; background:#fff; border:1px solid #e0e0e0;'>
                        <div style='background:#1a1a1a; color:#fff; padding:25px; text-align:center;'>
                            <h2 style='margin:0; font-weight:400;'>HANŽEKOVIĆ & PARTNERI</h2>
                        </div>
                        <div style='padding:30px;'>
                            <p style='color:#666;'>Zaprimljen je novi zahtjev za kreiranje identiteta:</p>
                            <table style='width:100%; border-collapse:collapse; margin-top:20px;'>$mailRows</table>
                        </div>
                    </div></body></html>";

        posalji_obavijest($primatelj, $subjekt, $tijelo);

        $_SESSION['f_msg'] = "Zahtjev uspješno poslan!";
        $_SESSION['f_type'] = "success";
        header("Location: dashboard.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['f_msg'] = "Greška: " . $e->getMessage();
        $_SESSION['f_type'] = "error";
        header("Location: dashboard.php");
        exit;
    }
}

$identities = ($rola === 'admin') ? $pdo->query("SELECT * FROM hanz_identities ORDER BY updated_at DESC")->fetchAll() : [];
$message = $_SESSION['f_msg'] ?? '';
$msg_type = $_SESSION['f_type'] ?? '';
unset($_SESSION['f_msg'], $_SESSION['f_type']);
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <title>Hanžeković & Partneri | IDM</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        /* UNIFICIRANI FONTOVI */
        body, input, select, textarea, button { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; margin: 0; }
        header { background: #1a1a1a; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .container { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .card { background: #fff; padding: 1.5rem; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        
        /* DATA TABLES FONT FIX */
        .dataTables_wrapper, 
        .dataTables_wrapper .dataTables_filter input, 
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            font-family: 'Segoe UI', sans-serif !important;
            font-size: 0.9rem;
            color: #444 !important;
        }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .full-width { grid-column: span 2; }
        label { display: block; font-size: 0.85rem; color: #666; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #1a1a1a; color: #fff; border: none; padding: 12px 20px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        
        .status { padding: 4px 8px; border-radius: 12px; font-size: 0.72rem; font-weight: bold; text-transform: uppercase; }
        .status-novi { background: #f8d7da; color: #721c24; }
        .status-u_obradi { background: #fff3cd; color: #856404; }
        .status-zavrseno { background: #d4edda; color: #155724; }
        .time-info { font-size: 0.75rem; color: #888; display: block; }
    </style>
</head>
<body>

<header>
    <div><strong>Hanžeković & Partneri</strong> | IDM Dashboard</div>
    <a href="logout.php" style="color:#ff6b6b; text-decoration:none; border:1px solid #ff6b6b; padding:5px 10px; border-radius:4px;">Odjava</a>
</header>

<div class="container">
    <?php if ($message): ?>
        <div style="padding:1rem; margin-bottom:1rem; border-radius:4px; background:<?php echo $msg_type=='success'?'#d4edda':'#f8d7da'; ?>;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3>Novi Zahtjev</h3>
        <form method="POST">
            <input type="hidden" name="action" value="new_identity">
            <div class="form-grid">
                <?php foreach ($form_schema as $key => $f): ?>
                    <div class="<?php echo ($f['type'] === 'textarea') ? 'full-width' : ''; ?>">
                        <label><?php echo $f['label']; ?></label>
                        <?php if ($f['type'] === 'select'): ?>
                            <select name="<?php echo $key; ?>" <?php echo $f['required'] ? 'required' : ''; ?>>
                                <option value="" disabled selected>-- Odaberi --</option>
                                <?php foreach ($f['options'] as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($f['type'] === 'textarea'): ?>
                            <textarea name="<?php echo $key; ?>" rows="2"></textarea>
                        <?php else: ?>
                            <input type="<?php echo $f['type']; ?>" name="<?php echo $key; ?>" <?php echo $f['required'] ? 'required' : ''; ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div><br>
            <button type="submit">Spremi i pošalji IT Supportu</button>
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
                    <td><span class="time-info" style="color:#1a1a1a; font-weight:bold;"><?php echo date('d.m.Y H:i', strtotime($idm['updated_at'])); ?></span></td>
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
        $('#identitiesTable').DataTable({
            "pageLength": 10,
            "order": [[ 5, "desc" ]], // Sortiranje po koloni: Zadnja promjena
            "language": {
                "search": "🔍 Pretraži:",
                "lengthMenu": "Prikaži _MENU_ zapisa",
                "info": "Prikaz _START_ do _END_ od _TOTAL_ zahtjeva",
                "paginate": { "next": "Sljedeća >", "previous": "< Prethodna" }
            }
        });
    });
</script>
</body>
</html>