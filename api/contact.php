<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

api_check_honeypot();
$name = api_field('name', 120, true);
$email = api_email_or_reject(api_field('email', 254, true));
$company = api_field('company', 160);
$phone = api_field('phone', 50);
$interest = api_field('interest', 160);
$message = api_notes('message', 5000);

if ($message === '') {
    api_respond(400, ['success' => false, 'message' => 'Bitte geben Sie eine Nachricht ein.']);
}

$body = "Neue Kontaktanfrage über die EMPACT-STUDIO-Website\n\n";
$body .= "Name: {$name}\n";
$body .= "E-Mail: {$email}\n";
$body .= "Betrieb / Webseite: " . ($company !== '' ? $company : 'Nicht angegeben') . "\n";
$body .= "Telefon: " . ($phone !== '' ? $phone : 'Nicht angegeben') . "\n";
$body .= "Interesse: " . ($interest !== '' ? $interest : 'Nicht angegeben') . "\n\n";
$body .= "Nachricht:\n{$message}\n";

if (!api_send_email('Neue Kontaktanfrage – EMPACT STUDIO', $body, $email)) {
    error_log('EMPACT contact email could not be sent.');
    api_respond(503, ['success' => false, 'message' => 'Der E-Mail-Versand ist momentan nicht verfügbar. Bitte versuchen Sie es später erneut oder schreiben Sie direkt an hallo@empact-studio.de.']);
}

api_respond(200, ['success' => true, 'message' => 'Ihre Nachricht wurde übermittelt. Wir melden uns innerhalb von 24 Stunden bei Ihnen.']);
