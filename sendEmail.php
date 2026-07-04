<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$config = defaultMailConfig();
try {
    $config = loadMailConfig();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, 'Invalid request method.', 405);
    }

    $message = readContactMessage();
    $result = sendContactMessage($message, $config);

    if ($result['success']) {
        respond(true, 'Message sent successfully.');
    }

    logMailError($result['error'], $config);
    respond(false, contactFailureMessage($config), 500, $result['error']);
} catch (InvalidArgumentException $exception) {
    respond(false, $exception->getMessage(), 422);
} catch (Throwable $exception) {
    logMailError($exception->getMessage(), $config);
    respond(false, contactFailureMessage($config), 500, $exception->getMessage());
}

function loadMailConfig(): array
{
    $defaults = defaultMailConfig();

    $configPath = __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'mail.php';
    if (!is_file($configPath)) {
        return $defaults;
    }

    $loaded = require $configPath;
    if (!is_array($loaded)) {
        return $defaults;
    }

    return array_replace_recursive($defaults, $loaded);
}

function defaultMailConfig(): array
{
  
return [
    'transport' => 'smtp',
    'to' => 'mudassir9290@gmail.com',
    'from' => 'mudassir9290@gmail.com',
    'from_name' => 'Portfolio Contact',
    'debug' => true,
      
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'mudassir9290@gmail.com',
        'password' => '',
        'encryption' => 'tls',
        'timeout' => 15,
    ],
];
}

function readContactMessage(): array
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));

    if ($name === '' || $email === '' || $subject === '' || $body === '') {
        throw new InvalidArgumentException('Please fill in all fields.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Please enter a valid email address.');
    }

    if (strlen($body) > 5000) {
        throw new InvalidArgumentException('Please keep the message under 5000 characters.');
    }

    return [
        'name' => cleanHeader($name),
        'email' => $email,
        'subject' => cleanHeader($subject),
        'body' => normalizeLineEndings($body),
    ];
}

function sendContactMessage(array $message, array $config): array
{
    $transport = strtolower((string) ($config['transport'] ?? 'mail'));

    if ($transport === 'smtp') {
        return sendViaSmtp($message, $config);
    }

    return sendViaPhpMail($message, $config);
}

function sendViaPhpMail(array $message, array $config): array
{
    $to = getRecipients($config);
    $from = getSender($config);
    $headers = [
        'From' => formatAddress($from, (string) $config['from_name']),
        'Reply-To' => formatAddress($message['email'], $message['name']),
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Mailer' => 'Portfolio contact form',
    ];

    $additionalParams = '';
    if (PHP_OS_FAMILY !== 'Windows') {
        $additionalParams = '-f' . $from;
    }

    $sent = $additionalParams === ''
        ? @mail(implode(', ', $to), $message['subject'], buildPlainTextBody($message), buildHeaderString($headers))
        : @mail(implode(', ', $to), $message['subject'], buildPlainTextBody($message), buildHeaderString($headers), $additionalParams);

    if ($sent) {
        return ['success' => true, 'error' => ''];
    }

    $phpMailSettings = sprintf(
        'PHP mail() returned false. SMTP=%s, smtp_port=%s, sendmail_path=%s',
        (string) ini_get('SMTP'),
        (string) ini_get('smtp_port'),
        (string) ini_get('sendmail_path')
    );

    return ['success' => false, 'error' => $phpMailSettings];
}

function sendViaSmtp(array $message, array $config): array
{
    $smtp = array_replace([
        'host' => '',
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls',
        'timeout' => 15,
    ], (array) ($config['smtp'] ?? []));

    $host = trim((string) $smtp['host']);
    $port = (int) $smtp['port'];
    $username = (string) $smtp['username'];
    $password = (string) $smtp['password'];
    $encryption = strtolower((string) $smtp['encryption']);
    $timeout = (int) $smtp['timeout'];
    $from = getSender($config);
    $to = getRecipients($config);

    if ($host === '') {
        return ['success' => false, 'error' => 'SMTP host is missing in config/mail.php.'];
    }

    if ($port <= 0) {
        return ['success' => false, 'error' => 'SMTP port is invalid in config/mail.php.'];
    }

    $target = $encryption === 'ssl' ? 'ssl://' . $host . ':' . $port : $host . ':' . $port;
    $socket = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return ['success' => false, 'error' => sprintf('Could not connect to SMTP server %s: %s', $target, $errstr ?: $errno)];
    }

    stream_set_timeout($socket, $timeout);

    try {
        smtpRead($socket, [220]);
        smtpCommand($socket, 'EHLO ' . smtpClientName(), [250]);

        if ($encryption === 'tls') {
            smtpCommand($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS negotiation failed.');
            }
            smtpCommand($socket, 'EHLO ' . smtpClientName(), [250]);
        }

        if ($username !== '' || $password !== '') {
            smtpCommand($socket, 'AUTH LOGIN', [334]);
            smtpCommand($socket, base64_encode($username), [334]);
            smtpCommand($socket, base64_encode($password), [235]);
        }

        smtpCommand($socket, 'MAIL FROM:<' . $from . '>', [250]);
        foreach ($to as $recipient) {
            smtpCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        }

        smtpCommand($socket, 'DATA', [354]);
        fwrite($socket, dotStuff(buildSmtpMessage($message, $config, $to, $from)) . "\r\n.\r\n");
        smtpRead($socket, [250]);
        smtpCommand($socket, 'QUIT', [221]);
        fclose($socket);

        return ['success' => true, 'error' => ''];
    } catch (Throwable $exception) {
        fclose($socket);
        return ['success' => false, 'error' => $exception->getMessage()];
    }
}

function getRecipients(array $config): array
{
    $recipients = is_array($config['to']) ? $config['to'] : [$config['to']];
    $validRecipients = [];

    foreach ($recipients as $recipient) {
        $email = trim((string) $recipient);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $validRecipients[] = $email;
        }
    }

    if ($validRecipients === []) {
        throw new InvalidArgumentException('Recipient email address is not configured.');
    }

    return $validRecipients;
}

function getSender(array $config): string
{
    $from = trim((string) ($config['from'] ?? ''));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Sender email address is not configured.');
    }

    return $from;
}

function buildPlainTextBody(array $message): string
{
    return "New portfolio contact form message\r\n\r\n"
        . 'Name: ' . $message['name'] . "\r\n"
        . 'Email: ' . $message['email'] . "\r\n"
        . 'Subject: ' . $message['subject'] . "\r\n\r\n"
        . "Message:\r\n" . $message['body'] . "\r\n";
}

function buildSmtpMessage(array $message, array $config, array $to, string $from): string
{
    $headers = [
        'Date' => date(DATE_RFC2822),
        'To' => implode(', ', $to),
        'From' => formatAddress($from, (string) $config['from_name']),
        'Reply-To' => formatAddress($message['email'], $message['name']),
        'Subject' => encodeHeader($message['subject']),
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Mailer' => 'Portfolio contact form',
    ];

    return buildHeaderString($headers) . "\r\n\r\n" . buildPlainTextBody($message);
}

function buildHeaderString(array $headers): string
{
    $lines = [];
    foreach ($headers as $name => $value) {
        $lines[] = cleanHeader((string) $name) . ': ' . cleanHeader((string) $value);
    }

    return implode("\r\n", $lines);
}

function formatAddress(string $email, string $name = ''): string
{
    $name = cleanHeader($name);
    if ($name === '') {
        return $email;
    }

    if (preg_match('/[^\x20-\x7E]/', $name)) {
        return encodeHeader($name) . ' <' . $email . '>';
    }

    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $name) . '" <' . $email . '>';
}

function encodeHeader(string $value): string
{
    $value = cleanHeader($value);
    if (!preg_match('/[^\x20-\x7E]/', $value)) {
        return $value;
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function cleanHeader(string $value): string
{
    return trim((string) preg_replace('/[\r\n]+/', ' ', $value));
}

function normalizeLineEndings(string $value): string
{
    return preg_replace("/\r\n|\r|\n/", "\r\n", $value) ?? $value;
}

function dotStuff(string $message): string
{
    $message = normalizeLineEndings($message);
    return preg_replace('/^\./m', '..', $message) ?? $message;
}

function smtpClientName(): string
{
    $host = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
    return preg_replace('/[^A-Za-z0-9.-]/', '', $host) ?: 'localhost';
}

function smtpCommand($socket, string $command, array $expectedCodes): string
{
    fwrite($socket, $command . "\r\n");
    return smtpRead($socket, $expectedCodes, $command);
}

function smtpRead($socket, array $expectedCodes, string $command = ''): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    if ($response === '') {
        throw new RuntimeException('SMTP server did not respond.');
    }

    $meta = stream_get_meta_data($socket);
    if (!empty($meta['timed_out'])) {
        throw new RuntimeException('SMTP server connection timed out.');
    }

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        $prefix = $command === '' ? 'SMTP error' : 'SMTP command failed: ' . $command;
        throw new RuntimeException($prefix . '. Response: ' . trim($response));
    }

    return $response;
}

function contactFailureMessage(array $config): string
{
    if (($config['transport'] ?? 'mail') === 'smtp') {
        return 'Message could not be sent. Please check your SMTP settings.';
    }

    return 'Message could not be sent. Localhost needs PHP mail/sendmail or SMTP configuration.';
}

function logMailError(string $error, array $config): void
{
    $logFile = (string) ($config['log_file'] ?? '');
    if ($logFile === '') {
        return;
    }

    @error_log('[' . date('Y-m-d H:i:s') . '] ' . $error . PHP_EOL, 3, $logFile);
}

function respond(bool $success, string $message, int $statusCode = 200, string $debug = ''): void
{
    http_response_code($statusCode);

    $payload = [
        'success' => $success,
        'message' => $message,
    ];

    if ($debug !== '' && ($GLOBALS['config']['debug'] ?? false)) {
        $payload['debug'] = $debug;
    }

    echo json_encode($payload);
    exit;
}