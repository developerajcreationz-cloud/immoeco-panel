<?php
/**
 * Contact-form mail handler for the Ahmad Jan site.
 *
 * Requires PHP + the mail() function to be available on the host (true on
 * Hostinger shared/Business plans out of the box). Receives the form's
 * fields as JSON, validates/sanitizes them, and emails the submission to
 * the address below.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// ---- config -----------------------------------------------------------
$recipient = 'developerajcreationz@gmail.com';
$siteName  = 'Ahmad Jan — Contact Form';

// ---- only accept POST --------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ---- read + decode body -------------------------------------------------
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST; // fallback for a normal form-encoded submit
}

function field(array $data, string $key, int $maxLen = 500): string
{
    $val = isset($data[$key]) ? (string) $data[$key] : '';
    $val = trim($val);
    // strip anything that could be used for header injection in an email client
    $val = preg_replace('/[\r\n]+/', ' ', $val) ?? '';
    if (function_exists('mb_substr')) {
        $val = mb_substr($val, 0, $maxLen);
    } else {
        $val = substr($val, 0, $maxLen);
    }
    return $val;
}

// Honeypot: a hidden field named "company" that real visitors never fill in.
$honeypot = field($data, 'company', 100);
if ($honeypot !== '') {
    // Silently pretend success so bots don't learn anything, but send nothing.
    echo json_encode(['success' => true]);
    exit;
}

$name       = field($data, 'name', 120);
$email      = field($data, 'email', 200);
$store      = field($data, 'store', 300);
$budgetMin  = field($data, 'budgetMin', 20);
$budgetMax  = field($data, 'budgetMax', 20);
$videosMin  = field($data, 'videosMin', 20);
$videosMax  = field($data, 'videosMax', 20);

$errors = [];
if ($name === '') {
    $errors[] = 'name';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'email';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please fill in a valid name and email.', 'fields' => $errors]);
    exit;
}

// ---- build the email -----------------------------------------------------
$subject = "New lead from the site: {$name}";

$bodyLines = [
    "New submission from the Ahmad Jan contact form.",
    "",
    "Name:              {$name}",
    "Email:             {$email}",
    "Shopify store:     " . ($store !== '' ? $store : '—'),
    "Budget per video:  " . ($budgetMin !== '' || $budgetMax !== '' ? "\${$budgetMin} - \${$budgetMax}" : '—'),
    "Ad videos / month: " . ($videosMin !== '' || $videosMax !== '' ? "{$videosMin} - {$videosMax}" : '—'),
    "",
    "Submitted: " . date('Y-m-d H:i:s T'),
];
$body = implode("\n", $bodyLines);

$fromDomain = 'localhost';
if (!empty($_SERVER['HTTP_HOST'])) {
    $fromDomain = preg_replace('/[^A-Za-z0-9.\-]/', '', $_SERVER['HTTP_HOST']);
}
$fromAddress = 'no-reply@' . $fromDomain;

$headers = [];
$headers[] = "From: {$siteName} <{$fromAddress}>";
$headers[] = "Reply-To: {$name} <{$email}>";
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'X-Mailer: PHP/' . phpversion();

$sent = @mail($recipient, $subject, $body, implode("\r\n", $headers));

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not send the message right now. Please try again shortly, or email us directly.',
    ]);
}
