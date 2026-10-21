<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Mengirim email lewat Microsoft Graph API (Office 365 / Exchange Online).
 *
 * Memakai app registration di Microsoft Entra ID dengan izin aplikasi
 * `Mail.Send` (client credentials), bukan SMTP basic auth yang sudah
 * dihentikan Microsoft. Pesan dikirim utuh dalam format MIME, jadi lampiran
 * dan HTML tetap seperti yang dibuat Laravel.
 *
 * Endpoint: POST /users/{pengirim}/sendMail (Content-Type: text/plain,
 * body = MIME base64).
 */
class MicrosoftGraphTransport extends AbstractTransport
{
    public const GRAPH_URL = 'https://graph.microsoft.com/v1.0';

    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $sender,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        // Penerima Bcc tidak ikut tertulis di header MIME oleh Symfony, jadi
        // ditambahkan agar Graph tetap mengirimnya.
        $bcc = $this->bccOnly($envelope, $email);
        if ($bcc !== []) {
            $email->getHeaders()->addMailboxListHeader('Bcc', $bcc);
        }

        $response = Http::withToken($this->token())
            ->withHeaders(['Content-Type' => 'text/plain'])
            ->timeout(30)
            ->withBody(base64_encode($email->toString()), 'text/plain')
            ->post(self::GRAPH_URL.'/users/'.rawurlencode($this->sender).'/sendMail');

        if (! $response->successful()) {
            throw new RuntimeException('Microsoft Graph menolak pengiriman: '.self::errorMessage($response->json(), $response->status()));
        }
    }

    /**
     * Access token aplikasi, disimpan di cache sampai sesaat sebelum habis.
     */
    public function token(): string
    {
        $cacheKey = 'msgraph-token:'.sha1($this->tenantId.'|'.$this->clientId.'|'.$this->clientSecret);

        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        $response = Http::asForm()->timeout(20)->post(
            'https://login.microsoftonline.com/'.rawurlencode($this->tenantId).'/oauth2/v2.0/token',
            [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
            ],
        );

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Gagal mendapatkan token Microsoft: '.self::errorMessage($response->json(), $response->status()));
        }

        $ttl = max(60, (int) $response->json('expires_in', 3600) - 120);
        Cache::put($cacheKey, $response->json('access_token'), $ttl);

        return $response->json('access_token');
    }

    /** @return list<Address> */
    private function bccOnly(Envelope $envelope, \Symfony\Component\Mime\Email $email): array
    {
        $visible = collect([...$email->getTo(), ...$email->getCc()])
            ->map(fn (Address $a) => strtolower($a->getAddress()))
            ->all();

        return collect($envelope->getRecipients())
            ->reject(fn (Address $a) => in_array(strtolower($a->getAddress()), $visible, true))
            ->values()
            ->all();
    }

    private static function errorMessage(mixed $json, int $status): string
    {
        if (is_array($json)) {
            $message = $json['error']['message'] ?? $json['error_description'] ?? (is_string($json['error'] ?? null) ? $json['error'] : null);
            if ($message) {
                return trim(strtok((string) $message, "\r\n")).' (HTTP '.$status.')';
            }
        }

        return 'HTTP '.$status;
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}
