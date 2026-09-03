<?php

declare(strict_types=1);

namespace App;

/**
 * HTTP client for the Quinos Cloud Customer API (quinosbackend.com/api/v1).
 *
 * Deliberately built on ext-curl rather than a package: the app ships to XAMPP
 * with `composer install` already done, and this is the only outbound HTTP it
 * makes.
 *
 * Every endpoint needs BOTH auth headers:
 *   API-KEY  application key, must match the backend's configuration
 *   TOKEN    company token — the backend resolves company_id from it, which is
 *            why no company_id is ever sent in the body
 *
 * Missing/invalid API-KEY answers 401 {"message":"Unauthorized"}; missing or
 * invalid TOKEN answers 401 {"message":"Unauthorized token"} — two different
 * failures, and the messages below are what surface them to staff.
 */
final class CloudClient
{
    /** @param array<string,mixed> $config the 'cloud' config array */
    public function __construct(private array $config)
    {
    }

    /**
     * The explicit on/off switch from config (`cloud.enabled`). Defaults to on,
     * so an older config without the key behaves as it always did.
     */
    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    /**
     * False until both credentials are present. Sync is skipped entirely rather
     * than firing requests that can only come back 401.
     */
    public function isConfigured(): bool
    {
        return $this->apiKey() !== '' && $this->token() !== '';
    }

    /** Which credential is missing, for the "why is sync off" message. */
    public function missingCredential(): ?string
    {
        return match (true) {
            $this->apiKey() === '' && $this->token() === '' => 'API-KEY and TOKEN',
            $this->apiKey() === ''                          => 'API-KEY',
            $this->token()  === ''                          => 'TOKEN',
            default                                         => null,
        };
    }

    /**
     * POST /customer — create. `code` must be unique per company.
     *
     * @param array<string,mixed> $payload
     * @return array{ok:bool,status:int,data:?array<string,mixed>,message:string,duplicateCode:bool}
     */
    public function createCustomer(array $payload): array
    {
        return $this->post('/customer', $payload);
    }

    /**
     * POST /customer/update — update. The customer is looked up by `code`
     * first, then `phone`; unknown code answers 422, not 404.
     *
     * @param array<string,mixed> $payload
     * @return array{ok:bool,status:int,data:?array<string,mixed>,message:string,duplicateCode:bool}
     */
    public function updateCustomer(array $payload): array
    {
        return $this->post('/customer/update', $payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,status:int,data:?array<string,mixed>,message:string,duplicateCode:bool}
     */
    private function post(string $path, array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return self::fail(0, 'Could not encode the customer as JSON.');
        }

        $ch = curl_init(rtrim((string) $this->config['base_url'], '/') . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => max(1, (int) ($this->config['timeout'] ?? 8)),
            CURLOPT_CONNECTTIMEOUT => max(1, (int) ($this->config['timeout'] ?? 8)),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'API-KEY: ' . $this->apiKey(),
                'TOKEN: ' . $this->token(),
            ],
        ]);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        // Offline local server, DNS failure, TLS problem, timeout.
        if ($raw === false) {
            return self::fail(0, $err !== '' ? 'Could not reach the cloud: ' . $err : 'Could not reach the cloud.');
        }

        $body = json_decode((string) $raw, true);
        if (!is_array($body)) {
            return self::fail($status, 'Unexpected reply from the cloud (HTTP ' . $status . ').');
        }

        $message = trim((string) ($body['message'] ?? ''));

        if ($status >= 200 && $status < 300 && ($body['success'] ?? false) === true) {
            $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : null;

            return ['ok' => true, 'status' => $status, 'data' => $data, 'message' => $message !== '' ? $message : 'OK', 'duplicateCode' => false];
        }

        // 422 carries per-field errors; the first one is far more useful to
        // staff than the generic top-level message.
        if (isset($body['errors']) && is_array($body['errors'])) {
            foreach ($body['errors'] as $messages) {
                if (is_array($messages) && isset($messages[0]) && is_string($messages[0])) {
                    $message = $messages[0];
                    break;
                }
                if (is_string($messages)) {
                    $message = $messages;
                    break;
                }
            }
        }

        if ($message === '') {
            $message = 'Cloud rejected the request (HTTP ' . $status . ').';
        }

        return [
            'ok'      => false,
            'status'  => $status,
            'data'    => null,
            'message' => $message,
            // "The code has already been taken." means the record is already up
            // there — CustomerSync turns this into an update instead of an error.
            'duplicateCode' => stripos($message, 'code has already been taken') !== false,
        ];
    }

    /** @return array{ok:bool,status:int,data:null,message:string,duplicateCode:bool} */
    private static function fail(int $status, string $message): array
    {
        return ['ok' => false, 'status' => $status, 'data' => null, 'message' => $message, 'duplicateCode' => false];
    }

    private function apiKey(): string
    {
        return trim((string) ($this->config['api_key'] ?? ''));
    }

    private function token(): string
    {
        return trim((string) ($this->config['token'] ?? ''));
    }
}
