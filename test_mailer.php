<?php
/**
 * Testiranje već kreiranog mailer.php fajla
 */

require_once 'mailer.php';

// Testiraj funkciju koju si definirao u mailer.php
$test_primatelj = "gkonjic@piopet.hr"; // Stavi svoj mail
$subjekt = "Test iz mailer.php";
$poruka = "<h3>Sustav je povezan!</h3><p>Ovo je test M365 integracije.</p>";

if (posalji_obavijest($test_primatelj, $subjekt, $poruka)) {
    echo "Sjajno! mailer.php radi i mail je poslan.";
} else {
    echo "Greška! Mail nije poslan. Provjeri logove ili SMTP postavke u mailer.php.";
}