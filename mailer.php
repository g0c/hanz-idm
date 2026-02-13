<?php
/**
 * v3.3.0 - SECURE MAILER
 * Opis: Slanje putem Azure Graph API, ali tajne čita iz db_config.php.
 */

// Osiguravamo da su tajne dostupne
require_once __DIR__ . '/db_config.php';

function posalji_obavijest($to, $subject, $body) {
    // Dohvaćamo tajne iz globalnog scope-a (iz db_config.php)
    global $azure_conf;

    // Provjera postoje li postavke (za svaki slučaj)
    if (!isset($azure_conf)) {
        error_log("MAILER ERROR: Azure postavke nisu pronađene u db_config.php!");
        return false;
    }

    $tenantId     = $azure_conf['tenantId'];
    $clientId     = $azure_conf['clientId'];
    $clientSecret = $azure_conf['clientSecret'];
    $senderEmail  = $azure_conf['senderEmail'];

    // 1. DOHVAT TOKENA
    $url = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token";
    $params = [
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'scope'         => 'https://graph.microsoft.com/.default',
        'grant_type'    => 'client_credentials',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $token = $response['access_token'] ?? null;
    if (!$token) {
        error_log("IDM MAILER: Token nije generiran! Provjeri Azure postavke.");
        return false;
    }

    // 2. SLANJE MAILA
    $sendUrl = "https://graph.microsoft.com/v1.0/users/$senderEmail/sendMail";
    $payload = [
        'message' => [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML', 
                'content' => $body
            ],
            'toRecipients' => [['emailAddress' => ['address' => $to]]],
        ],
        'saveToSentItems' => 'false'
    ];

    $ch = curl_init($sendUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json; charset=utf-8"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response_mail = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status != 202) {
        error_log("IDM MAILER: Greška $status. Odgovor: $response_mail");
        return false;
    }

    return true;
}
?>