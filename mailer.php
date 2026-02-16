<?php
/**
 * v4.3.2 - MULTI-RECIPIENT FIX
 * Slanje maila jednom ili više primatelja putem Azure Graph API.
 */
require_once __DIR__ . '/db_config.php';

function posalji_obavijest($to, $subject, $body) {
    global $azure_conf;

    if (!isset($azure_conf)) {
        error_log("MAILER ERROR: Postavke nisu učitane.");
        return false;
    }

    // 1. Priprema liste primatelja za Graph API format
    $toRecipients = [];
    if (is_array($to)) {
        foreach ($to as $email) {
            if (!empty($email)) {
                $toRecipients[] = ['emailAddress' => ['address' => trim($email)]];
            }
        }
    } else {
        if (!empty($to)) {
            $toRecipients[] = ['emailAddress' => ['address' => trim($to)]];
        }
    }

    // Provjera imamo li uopće primatelja
    if (empty($toRecipients)) {
        error_log("IDM MAILER: Nema definiranih primatelja.");
        return false;
    }

    // 2. DOHVAT TOKENA (Standardni OAuth2)
    $url = "https://login.microsoftonline.com/{$azure_conf['tenantId']}/oauth2/v2.0/token";
    $params = [
        'client_id'     => $azure_conf['clientId'],
        'client_secret' => $azure_conf['clientSecret'],
        'scope'         => 'https://graph.microsoft.com/.default',
        'grant_type'    => 'client_credentials',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $token = $response['access_token'] ?? null;
    if (!$token) return false;

    // 3. SLANJE MAILA
    $sendUrl = "https://graph.microsoft.com/v1.0/users/{$azure_conf['senderEmail']}/sendMail";
    $payload = [
        'message' => [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML', 
                'content' => $body
            ],
            'toRecipients' => $toRecipients, // Ovdje ide ispravno formatirani niz
        ],
        'saveToSentItems' => 'false'
    ];

    $ch = curl_init($sendUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status != 202) {
        error_log("IDM MAILER: Greška $status. Odgovor: $res");
        return false;
    }
    return true;
}