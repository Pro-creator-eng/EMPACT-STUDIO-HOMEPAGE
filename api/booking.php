<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

api_check_honeypot();
$name = api_field('name', 120, true);
$email = api_email_or_reject(api_field('email', 254, true));
$company = api_field('company', 160, true);
$phone = api_field('phone', 50, true);
$expert = api_field('expert', 80, true);
$date = api_field('date', 10, true);
$time = api_field('time', 5, true);
$interest = api_field('interest', 160);
$notes = api_notes('notes', 3000);

$experts = ['Marlon & Elias', 'Marlon Staatz', 'Elias Ambrosius'];
$times = ['09:30', '11:00', '13:30', '15:00', '16:30', '18:00'];
if (!in_array($expert, $experts, true) || !in_array($time, $times, true)) {
    api_respond(400, ['success' => false, 'message' => 'Bitte wählen Sie eine gültige Ansprechperson und Uhrzeit.']);
}

$timezone = new DateTimeZone('Europe/Berlin');
$requestedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
$dateErrors = DateTimeImmutable::getLastErrors();
if (
    $requestedDate === false
    || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
    || $requestedDate->format('Y-m-d') !== $date
) {
    api_respond(400, ['success' => false, 'message' => 'Bitte wählen Sie ein gültiges Datum.']);
}

$today = new DateTimeImmutable('today', $timezone);
$daysAhead = (int) $today->diff($requestedDate)->format('%r%a');
if ($daysAhead < 1 || $daysAhead > 30 || (int) $requestedDate->format('N') > 5) {
    api_respond(400, ['success' => false, 'message' => 'Bitte wählen Sie einen angezeigten Werktag.']);
}

$businessDaysAhead = 0;
$dateCursor = $today;
while ($dateCursor < $requestedDate) {
    $dateCursor = $dateCursor->modify('+1 day');
    if ((int) $dateCursor->format('N') <= 5) {
        $businessDaysAhead++;
    }
}
if ($businessDaysAhead > 5) {
    api_respond(400, ['success' => false, 'message' => 'Bitte wählen Sie einen der angezeigten Werktage.']);
}

$body = "Neue Terminanfrage über die EMPACT-STUDIO-Website\n";
$body .= "WICHTIG: Dies ist eine Anfrage, noch keine bestätigte Kalenderbuchung.\n\n";
$body .= "Wunschtermin: " . $requestedDate->format('d.m.Y') . " um {$time} Uhr (Europe/Berlin)\n";
$body .= "Gewünschte Ansprechperson: {$expert}\n";
$body .= "Name: {$name}\n";
$body .= "E-Mail: {$email}\n";
$body .= "Betrieb: {$company}\n";
$body .= "Telefon: {$phone}\n";
$body .= "Interesse: " . ($interest !== '' ? $interest : 'Nicht angegeben') . "\n";
$body .= "Nachricht: " . ($notes !== '' ? $notes : 'Keine zusätzliche Nachricht') . "\n";

if (!api_send_email('Neue Terminanfrage – EMPACT STUDIO', $body, $email)) {
    error_log('EMPACT booking request email could not be sent.');
    api_respond(503, ['success' => false, 'message' => 'Der E-Mail-Versand ist momentan nicht verfügbar. Bitte versuchen Sie es später erneut oder schreiben Sie direkt an hallo@empact-studio.de.']);
}

api_respond(200, ['success' => true, 'message' => 'Ihre Terminanfrage ist eingegangen. Wir prüfen die Wunschzeit und melden uns persönlich zur Bestätigung. Der Termin ist erst nach unserer Rückmeldung verbindlich.']);
