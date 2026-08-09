<?php
namespace PHPMailer\PHPMailer;

class PHPMailer
{
    public const ENCRYPTION_STARTTLS = 'tls';
    public const ENCRYPTION_SMTPS = 'ssl';

    public string $Host = '';
    public bool $SMTPAuth = false;
    public string $Username = '';
    public string $Password = '';
    public string $SMTPSecure = '';
    public int $Port = 25;
    public string $CharSet = 'UTF-8';
    public string $Subject = '';
    public string $Body = '';
    public string $AltBody = '';
    public int $Timeout = 30;
    public string $Hostname = 'localhost';
    public string $ErrorInfo = '';

    private bool $useSmtp = false;
    private bool $isHtml = false;
    private array $from = ['', ''];
    private array $replyTo = [];
    private array $recipients = [];
    private $socket = null;

    public function __construct(bool $exceptions = false)
    {
    }

    public function isSMTP(): void
    {
        $this->useSmtp = true;
    }

    public function isHTML(bool $isHtml = true): void
    {
        $this->isHtml = $isHtml;
    }

    public function setFrom(string $address, string $name = ''): void
    {
        $this->validateEmail($address, 'Invalid sender email.');
        $this->from = [$address, $name];
    }

    public function addReplyTo(string $address, string $name = ''): void
    {
        $this->validateEmail($address, 'Invalid reply-to email.');
        $this->replyTo[] = [$address, $name];
    }

    public function addAddress(string $address, string $name = ''): void
    {
        $this->validateEmail($address, 'Invalid recipient email.');
        $this->recipients[] = [$address, $name];
    }

    public function send(): bool
    {
        if (!$this->useSmtp) {
            throw new Exception('Only SMTP sending is enabled in this PMTS PHPMailer bundle.');
        }
        if ($this->Host === '') {
            throw new Exception('SMTP host is empty.');
        }
        if ($this->from[0] === '') {
            throw new Exception('Sender email is empty.');
        }
        if (count($this->recipients) === 0) {
            throw new Exception('No recipient email address was added.');
        }

        $this->smtpConnect();
        try {
            $this->smtpCommand('EHLO ' . $this->Hostname, [250]);

            if ($this->SMTPSecure === self::ENCRYPTION_STARTTLS) {
                $this->smtpCommand('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('Could not start TLS encryption for SMTP.');
                }
                $this->smtpCommand('EHLO ' . $this->Hostname, [250]);
            }

            if ($this->SMTPAuth) {
                $this->smtpCommand('AUTH LOGIN', [334]);
                $this->smtpCommand(base64_encode($this->Username), [334]);
                $this->smtpCommand(base64_encode($this->Password), [235]);
            }

            $this->smtpCommand('MAIL FROM:<' . $this->from[0] . '>', [250]);
            foreach ($this->recipients as $recipient) {
                $this->smtpCommand('RCPT TO:<' . $recipient[0] . '>', [250, 251]);
            }
            $this->smtpCommand('DATA', [354]);
            $this->smtpWrite($this->buildMessage() . "\r\n.");
            $this->smtpRead([250]);
            $this->smtpCommand('QUIT', [221, 250]);
            $this->smtpClose();
            return true;
        } catch (\Throwable $e) {
            $this->smtpClose();
            $this->ErrorInfo = $e->getMessage();
            if ($e instanceof Exception) {
                throw $e;
            }
            throw new Exception($e->getMessage());
        }
    }

    private function smtpConnect(): void
    {
        $scheme = $this->SMTPSecure === self::ENCRYPTION_SMTPS ? 'ssl://' : 'tcp://';
        $address = $scheme . $this->Host . ':' . $this->Port;
        $errno = 0;
        $errstr = '';
        $this->socket = @stream_socket_client($address, $errno, $errstr, $this->Timeout, STREAM_CLIENT_CONNECT);
        if (!$this->socket) {
            throw new Exception('SMTP connection failed: ' . ($errstr ?: 'Unknown error') . ($errno ? " ({$errno})" : ''));
        }
        stream_set_timeout($this->socket, $this->Timeout);
        $this->smtpRead([220]);
    }

    private function smtpCommand(string $command, array $expectedCodes): string
    {
        $this->smtpWrite($command);
        return $this->smtpRead($expectedCodes);
    }

    private function smtpWrite(string $line): void
    {
        if (!$this->socket) {
            throw new Exception('SMTP socket is not connected.');
        }
        $line = str_replace(["\r\n", "\r", "\n"], "\r\n", $line);
        fwrite($this->socket, $line . "\r\n");
    }

    private function smtpRead(array $expectedCodes): string
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        if ($response === '') {
            throw new Exception('No response from SMTP server.');
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new Exception('SMTP error: ' . trim($response));
        }
        return $response;
    }

    private function buildMessage(): string
    {
        $headers = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: ' . $this->formatAddress($this->from);
        $headers[] = 'To: ' . implode(', ', array_map([$this, 'formatAddress'], $this->recipients));
        if ($this->replyTo) {
            $headers[] = 'Reply-To: ' . implode(', ', array_map([$this, 'formatAddress'], $this->replyTo));
        }
        $headers[] = 'Subject: ' . $this->encodeHeader($this->Subject);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: ' . ($this->isHtml ? 'text/html' : 'text/plain') . '; charset=' . $this->CharSet;
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'X-Mailer: PMTS PHPMailer SMTP';

        return implode("\r\n", $headers) . "\r\n\r\n" . $this->normalizeBody($this->Body);
    }

    private function formatAddress(array $address): string
    {
        [$email, $name] = $address;
        if (trim($name) === '') {
            return '<' . $email . '>';
        }
        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, $this->CharSet, 'B', "\r\n");
        }
        return $value;
    }

    private function normalizeBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r", "\n"], "\r\n", $body);
        return preg_replace('/^\./m', '..', $body) ?? $body;
    }

    private function validateEmail(string $address, string $message): void
    {
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new Exception($message . ' ' . $address);
        }
    }

    private function smtpClose(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }
}
