<?php
/**
 * Mail: a small SMTP client (no libraries; the same one The Aesthetic Court runs on) and the enquiry messages.
 * Without SMTP settings it falls back to PHP mail(), so enquiries still arrive before the client fills them in.
 */

/* ---------------------------------------------------------------
   Credentials at rest are encrypted with APP_SECRET, so a database
   dump does not hand over the mailbox password in clear text.
   --------------------------------------------------------------- */
function secret_encrypt(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    $key = hash('sha256', APP_SECRET, true);
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}

function secret_decrypt(string $blob): string
{
    if ($blob === '') {
        return '';
    }
    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 17) {
        return '';
    }
    $key = hash('sha256', APP_SECRET, true);
    $iv  = substr($raw, 0, 16);
    $out = openssl_decrypt(substr($raw, 16), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $out === false ? '' : $out;
}

/* ---------------------------------------------------------------
   Minimal SMTP conversation. Supports implicit SSL (465) and
   STARTTLS (587), with AUTH LOGIN.
   --------------------------------------------------------------- */
class Smtp
{
    private $sock = null;
    private array $log = [];

    public function __construct(
        private string $host,
        private int $port,
        private string $secure,   // '', 'ssl', 'tls'
        private string $user,
        private string $pass,
        private int $timeout = 15
    ) {}

    public function log(): array { return $this->log; }

    private function put(string $line): void
    {
        $this->log[] = '> ' . (stripos($line, 'AUTH') === 0 || strlen($line) > 120 ? '[hidden]' : $line);
        fwrite($this->sock, $line . "\r\n");
    }

    private function expect(array $codes): string
    {
        $data = '';
        while (($line = fgets($this->sock, 1024)) !== false) {
            $data .= $line;
            /* multiline replies look like "250-..." and end with "250 ..." */
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $this->log[] = '< ' . trim($data);
        $code = (int) substr(trim($data), 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP a răspuns: ' . trim($data));
        }
        return $data;
    }

    public function send(array $from, array $to, string $subject, string $text, string $html): bool
    {
        $target = ($this->secure === 'ssl' ? 'ssl://' : '') . $this->host;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $this->sock = @stream_socket_client(
            $target . ':' . $this->port, $errno, $errstr, $this->timeout,
            STREAM_CLIENT_CONNECT, $ctx
        );
        if (!$this->sock) {
            throw new RuntimeException('Nu m-am putut conecta la ' . $this->host . ':' . $this->port . ' (' . $errstr . ')');
        }
        stream_set_timeout($this->sock, $this->timeout);

        $this->expect([220]);
        $ehlo = gethostname() ?: 'localhost';
        $this->put('EHLO ' . $ehlo);
        $this->expect([250]);

        if ($this->secure === 'tls') {
            $this->put('STARTTLS');
            $this->expect([220]);
            if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Nu am putut porni TLS.');
            }
            $this->put('EHLO ' . $ehlo);
            $this->expect([250]);
        }

        if ($this->user !== '') {
            $this->put('AUTH LOGIN');
            $this->expect([334]);
            $this->put(base64_encode($this->user));
            $this->expect([334]);
            $this->put(base64_encode($this->pass));
            $this->expect([235]);
        }

        $this->put('MAIL FROM:<' . $from['email'] . '>');
        $this->expect([250]);
        foreach ($to as $rcpt) {
            $this->put('RCPT TO:<' . $rcpt . '>');
            $this->expect([250, 251]);
        }

        $this->put('DATA');
        $this->expect([354]);

        $boundary = 'cs' . bin2hex(random_bytes(8));
        $headers = [
            'From: ' . mime_name($from['name']) . ' <' . $from['email'] . '>',
            'To: ' . implode(', ', $to),
            'Subject: ' . mime_subject($subject),
            'MIME-Version: 1.0',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . (parse_url(site_base(), PHP_URL_HOST) ?: 'localhost') . '>',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        if (!empty($from['reply'])) {
            $headers[] = 'Reply-To: ' . $from['reply'];
        }

        $body = implode("\r\n", $headers) . "\r\n\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text)) . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "\r\n"
            . '--' . $boundary . "--\r\n";

        /* dot-stuffing, per the protocol */
        $body = preg_replace('/^\./m', '..', $body);

        fwrite($this->sock, $body . "\r\n.\r\n");
        $this->expect([250]);

        $this->put('QUIT');
        @fclose($this->sock);
        return true;
    }
}

function mime_subject(string $s): string
{
    return preg_match('/[\x80-\xFF]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}
function mime_name(string $s): string
{
    return preg_match('/[\x80-\xFF]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : '"' . $s . '"';
}

/* ---------------------------------------------------------------
   One entry point. Returns [ok, message].
   --------------------------------------------------------------- */
function send_mail(array $to, string $subject, string $html, string $text = '', string $replyTo = ''): array
{
    $to = array_values(array_filter(array_map('trim', $to), fn($x) => filter_var($x, FILTER_VALIDATE_EMAIL)));
    if (!$to) {
        return [false, 'Niciun destinatar valid.'];
    }
    if ($text === '') {
        $text = trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>#i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
    }
    $host = parse_url(site_base(), PHP_URL_HOST) ?: 'localhost';
    $from = [
        'name'  => setting('mail.from_name', 'Chasing Stories'),
        'email' => setting('mail.from_email', 'noreply@' . preg_replace('/^www\./', '', $host)),
        'reply' => $replyTo,
    ];
    if (setting('smtp.host') === '') {
        $headers = 'From: ' . mime_name($from['name']) . ' <' . $from['email'] . ">\r\n"
            . ($replyTo ? 'Reply-To: ' . $replyTo . "\r\n" : '')
            . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        $ok = @mail(implode(',', $to), mime_subject($subject), $html, $headers);
        return [$ok, $ok ? 'trimis prin mail() (SMTP neconfigurat)' : 'mail() a eșuat: configurează SMTP în Contact și email'];
    }
    try {
        $smtp = new Smtp(setting('smtp.host'), (int) setting('smtp.port', '587'), setting('smtp.secure', 'tls'),
            setting('smtp.user'), secret_decrypt(setting('smtp.pass')));
        $smtp->send($from, $to, $subject, $text, $html);
        return [true, 'trimis prin SMTP'];
    } catch (Throwable $e) {
        return [false, $e->getMessage()];
    }
}

/* Plain, brand-quiet shell: tables and inline styles, because mail clients are fussy. */
function mail_shell(string $kicker, string $title, string $bodyHtml): string
{
    $brand = e(setting('copy.brand', 'Chasing Stories'));
    return '<!doctype html><html><body style="margin:0;background:#F1F2EF;font-family:Helvetica,Arial,sans-serif;color:#0F1514">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F1F2EF"><tr><td align="center" style="padding:32px 16px">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF">
<tr><td style="padding:28px 32px 8px"><div style="font-family:Georgia,serif;letter-spacing:.22em;text-transform:uppercase;font-size:13px">' . $brand . '</div></td></tr>
<tr><td style="padding:24px 32px 0"><div style="font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#5B6462">' . e($kicker) . '</div>
<h1 style="font-family:Georgia,serif;font-weight:400;font-size:26px;line-height:1.25;margin:10px 0 18px">' . e($title) . '</h1></td></tr>
<tr><td style="padding:0 32px 32px;font-size:15px;line-height:1.6">' . $bodyHtml . '</td></tr>
</table></td></tr></table></body></html>';
}

function mail_enquiry_team(array $r): array
{
    $rows = [
        'Name' => $r['name'], 'Company / Brand' => $r['company'], 'Email' => $r['email'], 'Website / Instagram' => $r['website'],
        'Project location' => $r['location'], 'Preferred dates' => $r['dates'], 'Services' => $r['type'], 'Came from' => $r['source'],
    ];
    $t = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:18px">';
    foreach ($rows as $k => $v) {
        if ((string) $v === '') {
            continue;
        }
        $t .= '<tr><td style="padding:7px 0;border-bottom:1px solid #E6E8E4;color:#5B6462;font-size:12px;letter-spacing:.06em;text-transform:uppercase;width:42%">' . e($k)
            . '</td><td style="padding:7px 0;border-bottom:1px solid #E6E8E4">' . e($v) . '</td></tr>';
    }
    $t .= '</table><div style="white-space:pre-wrap">' . e($r['details']) . '</div>'
        . '<p style="margin-top:24px;font-size:13px;color:#5B6462">Reply to this email to answer ' . e($r['name']) . ' directly. All enquiries are also in the admin panel.</p>';
    $to = array_map('trim', explode(',', setting('mail.to', 'begin@chasingstories.org')));
    return send_mail($to, 'New enquiry: ' . ($r['company'] ?: $r['name']), mail_shell('New enquiry', $r['company'] ?: $r['name'], $t), '', $r['email']);
}

function mail_enquiry_autoreply(array $r): array
{
    if (setting('mail.autoreply', '1') !== '1') {
        return [true, 'autoreply off'];
    }
    $body = nl2br(e(setting('mail.autoreply_text', "Thank you for contacting Chasing Stories. We have received your enquiry and will reply within two working days.\n\nIn the meantime, you can explore our recent work at " . site_base() . "/work.")));
    return send_mail([$r['email']], setting('mail.autoreply_subject', 'Thank you for your enquiry'),
        mail_shell('Enquiry received', 'Thank you, ' . $r['name'] . '.', $body . '<p style="margin-top:24px">Chasing Stories<br><span style="color:#5B6462">Available worldwide</span></p>'),
        '', setting('mail.to', ''));
}
