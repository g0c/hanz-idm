<?php
/**
 * v1.0.0
 * Projekt: Hanžeković & Partneri IDM
 * Opis: Odjava korisnika iz sustava.
 * Developer: PIOPET
 */

session_start();
session_unset();
session_destroy();

header("Location: index.php");
exit;