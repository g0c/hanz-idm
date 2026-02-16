<?php
/**
 * v4.4.0 - AUTO SCHEMA SYNC
 * Opis: Uspoređuje PHP shemu s MySQL tablicom i dodaje stupce koji fale.
 */
require_once __DIR__ . '/db_config.php';

echo "<pre>Započinjem sinkronizaciju baze...\n";

try {
    // 1. Dohvati trenutne kolone iz baze
    $stmt = $pdo->query("DESCRIBE hanz_identities");
    $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 2. Spoji sve ključeve iz obje sheme (Client + IT)
    $all_schema_fields = array_merge($form_schema, $it_schema);

    // 3. Mapiranje PHP tipova u SQL tipove
    $type_map = [
        'text'     => 'VARCHAR(255) NULL',
        'email'    => 'VARCHAR(255) NULL',
        'date'     => 'DATE NULL',
        'textarea' => 'TEXT NULL',
        'select'   => 'VARCHAR(100) NULL',
        'checkbox' => 'TINYINT(1) DEFAULT 0'
    ];

    $added_count = 0;

    foreach ($all_schema_fields as $field_name => $config) {
        // Ako kolona ne postoji u bazi, dodaj je
        if (!in_array($field_name, $existing_columns)) {
            $sql_type = $type_map[$config['type']] ?? 'VARCHAR(255) NULL';
            
            $alter_sql = "ALTER TABLE hanz_identities ADD `$field_name` $sql_type";
            $pdo->exec($alter_sql);
            
            echo "✅ Dodana kolona: <strong>$field_name</strong> ($sql_type)\n";
            $added_count++;
        }
    }

    if ($added_count === 0) {
        echo "😎 Baza je već usklađena sa shemom. Nema novih izmjena.\n";
    } else {
        echo "\n🚀 Sinkronizacija uspješna! Ukupno dodano: $added_count polja.\n";
    }

} catch (Exception $e) {
    die("❌ Greška pri sinkronizaciji: " . $e->getMessage());
}

echo "\n<a href='dashboard.php'>Povratak na Dashboard</a></pre>";