<?php
/**
 * POST /api/enquiry.php — the contact form.
 * Stores the enquiry, emails the studio (address set in the panel) and, if enabled, thanks the sender.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(405, ['ok' => false, 'error' => 'Method not allowed.']);
}
// same-origin only
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url(site_base(), PHP_URL_HOST)) {
    json_out(403, ['ok' => false, 'error' => 'Not allowed.']);
}
$in = fn(string $k, int $max = 190) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);

// the hidden field real people never see; bots fill it. Pretend success.
if ($in('website_url') !== '') {
    json_out(200, ['ok' => true]);
}
if (!rate_ok('enquiry', 5, 600)) {
    json_out(429, ['ok' => false, 'error' => 'Too many messages in a short time. Please try again in a few minutes.']);
}

$r = [
    'name' => $in('f-name'), 'company' => $in('f-company'), 'email' => $in('f-email'), 'website' => $in('f-web', 255),
    'location' => $in('f-loc'), 'dates' => $in('f-dates'), 'type' => $in('f-type', 120), 'details' => $in('f-details', 8000),
    'source' => $in('source'), 'page' => $in('page', 255),
];
$missing = [];
foreach (['name' => 'Name', 'company' => 'Company / Brand', 'email' => 'Email', 'location' => 'Project location', 'type' => 'Project type', 'details' => 'Project details'] as $k => $label) {
    if ($r[$k] === '') {
        $missing[] = $label;
    }
}
if ($missing) {
    json_out(422, ['ok' => false, 'error' => 'Please fill in: ' . implode(', ', $missing) . '.']);
}
if (!filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
    json_out(422, ['ok' => false, 'error' => 'Please enter a valid email address.']);
}
if (($_POST['consent'] ?? '') !== '1') {
    json_out(422, ['ok' => false, 'error' => 'Please confirm you agree so we can reply.']);
}

db()->prepare('INSERT INTO ' . t('enquiries') . ' (created_at, name, company, email, website, location, dates, type, details, source, page, ip, status)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([date('Y-m-d H:i:s'), $r['name'], $r['company'], $r['email'], $r['website'], $r['location'], $r['dates'], $r['type'],
        $r['details'], $r['source'], $r['page'], client_ip(), 'new']);
$id = (int) db()->lastInsertId();

[$ok, $msg] = mail_enquiry_team($r);
[$ok2, $msg2] = mail_enquiry_autoreply($r);
db()->prepare('UPDATE ' . t('enquiries') . ' SET mail_status = ? WHERE id = ?')
    ->execute([mb_substr(($ok ? 'team: ok' : 'team: ' . $msg) . ' · ' . ($ok2 ? 'reply: ok' : 'reply: ' . $msg2), 0, 250), $id]);

// the enquiry is saved even when mail fails, so the visitor still sees success; the panel shows the mail error
json_out(200, ['ok' => true]);
