<?php
/**
 * v3.0.0 - FINAL
 * Projekt: Hanžeković & Partneri IDM
 * Opis: Centralna konfiguracija baze i šifrarnika.
 */

$host = 'localhost';
$db   = 'hanz_idm_db';
$user = 'xxxxxxxx'; // PROVJERI USERA
$pass = 'xxxxxxxx'; // PROVJERI LOZINKU
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Greška spajanja na bazu: " . $e->getMessage());
}

// ==========================================
// CENTRALNI ŠIFRARNICI
// ==========================================

// 1. Lista odjela (14 stavki)
$hanz_departments = [
    "Administracija",
    "Arhiva",
    "Financije i računovodstvo",
    "Informatika",
    "Odjel ljudskih potencijala",
    "Odvjetnici",
    "Odvjetnički vježbenici",
    "Odvjetnički vježbenici s pravosudnim",
    "Ostali",
    "Partneri",
    "Pisarnica",
    "Porta",
    "Prevoditelji",
    "Voditeljica održavanja i sigurnosti"
];

// 2. Lista zahtjevatora (Ime => Email)
$hanz_requestors = [
    'Marija Marić' => 'm.maric@hanzekovic.hr',
    'Ivan Horvat'  => 'i.horvat@hanzekovic.hr',
    'Petra Petrić' => 'p.petric@hanzekovic.hr',
    'Goran Konjić' => 'gkonjic@piopet.hr',
    'Marta Franković-Polanović' => 'marta.frankovic-polanovic@hanzekovic.hr'
];
?>
