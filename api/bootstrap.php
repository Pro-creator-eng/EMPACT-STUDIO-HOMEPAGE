<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    api_respond(405, ['success' => false, 'message' => 'Diese Anfrage-Methode wird nicht unterstützt.']);
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || strlen($rawBody) > 16000) {
    api_respond(413, ['success' => false, 'message' => 'Die Anfrage ist zu groß.']);
}

$apiPayload = json_decode($rawBody, true);
if (!is_array($apiPayload)) {
    api_respond(400, ['success' => false, 'message' => 'Die Formulardaten konnten nicht gelesen werden.']);
}

$configPath = getenv('EMPACT_CONFIG_FILE');
if (!is_string($configPath) || $configPath === '') {
    $configPath = dirname(__DIR__, 2) . '/private/empact-config.php';
}

if (!is_file($configPath) || !is_readable($configPath)) {
    error_log('EMPACT form configuration is missing or unreadable.');
    api_respond(503, ['success' => false, 'message' => 'Das Formular ist noch nicht eingerichtet. Bitte kontaktieren Sie uns direkt.']);
}

$apiMailConfig = require $configPath;
if (
    !is_array($apiMailConfig)
    || !filter_var($apiMailConfig['recipient'] ?? null, FILTER_VALIDATE_EMAIL)
    || !filter_var($apiMailConfig['sender_email'] ?? null, FILTER_VALIDATE_EMAIL)
    || !is_string($apiMailConfig['sender_name'] ?? null)
) {
    error_log('EMPACT form configuration has invalid values.');
    api_respond(503, ['success' => false, 'message' => 'Das Formular ist momentan nicht verfügbar. Bitte kontaktieren Sie uns direkt.']);
}

function api_field(string $key, int $maxLength, bool $required = false): string
{
    global $apiPayload;

    $value = $apiPayload[$key] ?? '';
    if (!is_string($value)) {
        api_respond(400, ['success' => false, 'message' => 'Bitte prüfen Sie Ihre Eingaben.']);
    }

    $value = trim(str_replace(["\r", "\n", "\t", "\0"], ' ', $value));
    if (($required && $value === '') || strlen($value) > $maxLength) {
        api_respond(400, ['success' => false, 'message' => 'Bitte prüfen Sie die Pflichtfelder und deren Länge.']);
    }

    return $value;
}

function api_notes(string $key, int $maxLength): string
{
    global $apiPayload;

    $value = $apiPayload[$key] ?? '';
    if (!is_string($value)) {
        api_respond(400, ['success' => false, 'message' => 'Bitte prüfen Sie Ihre Nachricht.']);
    }

    $value = trim(str_replace("\0", '', $value));
    if (strlen($value) > $maxLength) {
        api_respond(400, ['success' => false, 'message' => 'Die Nachricht ist zu lang.']);
    }

    return $value;
}

function api_check_honeypot(): void
{
    global $apiPayload;

    if (api_field('website', 200) !== '') {
        api_respond(400, ['success' => false, 'message' => 'Die Anfrage konnte nicht verarbeitet werden.']);
    }
}

function api_send_email(string $subject, string $body, string $replyTo): bool
{
    global $apiMailConfig;

    if (!function_exists('mail')) {
        return false;
    }

    $senderName = str_replace(["\r", "\n"], '', $apiMailConfig['sender_name']);
    $encodedSender = '=?UTF-8?B?' . base64_encode($senderName) . '?=';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = "From: {$encodedSender} <{$apiMailConfig['sender_email']}>\r\n";
    $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return mail($apiMailConfig['recipient'], $encodedSubject, $body, $headers);
}

function api_email_or_reject(string $email): string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        api_respond(400, ['success' => false, 'message' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.']);
    }

    return $email;
}
