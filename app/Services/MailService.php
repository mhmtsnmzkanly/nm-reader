<?php

declare(strict_types=1);

namespace App\Services;

final class MailService
{
    private ?string $logPath;

    public function __construct(
        private readonly SiteConfigService $siteConfig,
        ?string $logPath = null
    ) {
        $this->logPath = $logPath ?: (dirname(__DIR__, 2) . '/storage/logs/mail.log');
    }

    public function isMailEnabled(): bool
    {
        return (bool) $this->siteConfig->get('mail_enabled', true);
    }

    public function isSendOnRegisterEnabled(): bool
    {
        return (bool) $this->siteConfig->get('mail_send_on_register', true);
    }

    public function isEmailVerificationRequired(): bool
    {
        // If mail is disabled or Resend is not configured, don't block users with email verification requirement
        if (!$this->isMailEnabled() || !$this->hasApiKey()) {
            return false;
        }

        return (bool) $this->siteConfig->get('email_verification_required', false);
    }

    public function hasApiKey(): bool
    {
        $key = $this->getApiKey();
        return $key !== '';
    }

    public function getApiKey(): string
    {
        $envKey = trim((string) ($_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY') ?: ''));
        if ($envKey !== '') {
            return $envKey;
        }
        return trim((string) ($this->siteConfig->get('integrations')['resend_api_key'] ?? ''));
    }

    public function getFromAddress(): string
    {
        $envFrom = trim((string) ($_ENV['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS') ?: ''));
        if ($envFrom !== '') {
            return $envFrom;
        }
        return (string) $this->siteConfig->get('mail_from_address', 'noreply@nmreader.com');
    }

    public function getFromName(): string
    {
        $envName = trim((string) ($_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: ''));
        if ($envName !== '') {
            return $envName;
        }
        return (string) $this->siteConfig->get('mail_from_name', $this->siteConfig->siteName());
    }

    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        if (!$this->isMailEnabled()) {
            $this->logEmail($to, $subject, $htmlBody, 'DISABLED');
            return true;
        }

        $apiKey = $this->getApiKey();
        if ($apiKey === '') {
            // Local dev / test simulation mode
            $this->logEmail($to, $subject, $htmlBody, 'SIMULATED (NO_API_KEY)');
            return true;
        }

        $from = sprintf('%s <%s>', $this->getFromName(), $this->getFromAddress());
        $payload = [
            'from' => $from,
            'to' => [$to],
            'subject' => $subject,
            'html' => $htmlBody,
        ];
        if ($textBody !== null && $textBody !== '') {
            $payload['text'] = $textBody;
        }

        $ch = curl_init('https://api.resend.com/emails');
        if ($ch === false) {
            $this->logEmail($to, $subject, $htmlBody, 'FAILED (CURL_INIT)');
            return false;
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'User-Agent: NM-Reader/1.0',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '' || $httpCode < 200 || $httpCode >= 300) {
            $this->logEmail($to, $subject, $htmlBody, sprintf('FAILED (HTTP %d: %s, Error: %s)', $httpCode, is_string($response) ? substr($response, 0, 200) : '', $curlError));
            return false;
        }

        $this->logEmail($to, $subject, $htmlBody, 'SENT (RESEND)');
        return true;
    }

    public function sendPasswordReset(string $email, string $username, string $resetToken, string $appUrl): bool
    {
        $siteName = $this->siteConfig->siteName();
        $actionUrl = rtrim($appUrl, '/') . '/reset-password?token=' . urlencode($resetToken) . '&email=' . urlencode($email);

        $subjectTpl = (string) $this->siteConfig->get('password_reset_subject', 'Şifre Sıfırlama Talebi - {{site_name}}');
        $bodyTpl = (string) $this->siteConfig->get('password_reset_body', '');

        if ($bodyTpl === '') {
            $bodyTpl = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; background-color: #18181b; color: #f4f4f5; border-radius: 12px;"><h2 style="color: #ffffff; margin-bottom: 16px;">Şifre Sıfırlama</h2><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">Merhaba <strong>{{username}}</strong>,</p><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">{{site_name}} hesabınız için bir şifre sıfırlama talebi aldık. Şifrenizi yenilemek için aşağıdaki butona tıklayabilirsiniz:</p><div style="text-align: center; margin: 28px 0;"><a href="{{action_url}}" style="background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block;">Şifremi Sıfırla</a></div><p style="color: #71717a; font-size: 12px; line-height: 1.5;">Bu bağlantı <strong>{{expires_in}}</strong> boyunca geçerlidir. Talebi siz yapmadıysanız bu e-postayı güvenle silebilirsiniz.</p></div>';
        }

        $replacements = [
            '{{username}}' => htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{email}}' => htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{site_name}}' => htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{action_url}}' => $actionUrl,
            '{{token}}' => $resetToken,
            '{{expires_in}}' => '1 saat',
            '{{current_year}}' => date('Y'),
        ];

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subjectTpl);
        $htmlBody = str_replace(array_keys($replacements), array_values($replacements), $bodyTpl);

        return $this->send($email, $subject, $htmlBody);
    }

    public function sendEmailVerification(string $email, string $username, string $verificationToken, string $appUrl): bool
    {
        $siteName = $this->siteConfig->siteName();
        $actionUrl = rtrim($appUrl, '/') . '/verify-email?token=' . urlencode($verificationToken);

        $subjectTpl = (string) $this->siteConfig->get('email_verification_subject', 'E-posta Adresinizi Doğrulayın - {{site_name}}');
        $bodyTpl = (string) $this->siteConfig->get('email_verification_body', '');

        if ($bodyTpl === '') {
            $bodyTpl = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; background-color: #18181b; color: #f4f4f5; border-radius: 12px;"><h2 style="color: #ffffff; margin-bottom: 16px;">E-posta Doğrulama</h2><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">Merhaba <strong>{{username}}</strong>,</p><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">{{site_name}} ailesine hoş geldiniz! Hesabınızı doğrulamak ve güvenliğinizi sağlamak için lütfen aşağıdaki butona tıklayın:</p><div style="text-align: center; margin: 28px 0;"><a href="{{action_url}}" style="background-color: #e11d48; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block;">E-postamı Doğrula</a></div><p style="color: #71717a; font-size: 12px; line-height: 1.5;">Bu bağlantı <strong>{{expires_in}}</strong> boyunca geçerlidir.</p></div>';
        }

        $replacements = [
            '{{username}}' => htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{email}}' => htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{site_name}}' => htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{action_url}}' => $actionUrl,
            '{{token}}' => $verificationToken,
            '{{expires_in}}' => '24 saat',
            '{{current_year}}' => date('Y'),
        ];

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subjectTpl);
        $htmlBody = str_replace(array_keys($replacements), array_values($replacements), $bodyTpl);

        return $this->send($email, $subject, $htmlBody);
    }

    private function logEmail(string $to, string $subject, string $htmlBody, string $status): void
    {
        if ($this->logPath === null) {
            return;
        }

        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $entry = sprintf(
            "[%s] STATUS=%s TO=%s SUBJECT=%s BODY_PREVIEW=%s\n",
            date('Y-m-d H:i:s'),
            $status,
            $to,
            $subject,
            substr(strip_tags($htmlBody), 0, 100)
        );

        @file_put_contents($this->logPath, $entry, FILE_APPEND);
    }
}
