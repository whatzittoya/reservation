<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Pushes a local customer to Quinos Cloud and records what happened.
 *
 * When it runs
 *   - a customer is created locally (Customers form, or the inline "new
 *     customer" box on a booking)
 *   - a customer is edited locally
 * Picking an existing customer for a booking changes nothing, so nothing is
 * sent: the customer base that was already here stays untouched until someone
 * presses Sync.
 *
 * Create vs update is decided by what we already know: a customer with a
 * recorded cloud id is updated, anything else is created. A create that comes
 * back "code has already been taken" is retried as an update — that is what
 * makes the Sync button safe to press twice, and what heals a push that timed
 * out after the backend had in fact saved it.
 *
 * push() never throws. A local save must always succeed even with the network
 * down; the customer is just left "Not synced" with the reason recorded.
 */
final class CustomerSync
{
    public function __construct(
        private CloudClient $client,
        private CustomerRepository $customers,
        private CustomerSyncRepository $state,
        private string $codePrefix = 'RSV',
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->client->isEnabled()
            && $this->client->isConfigured()
            && $this->state->isInstalled();
    }

    /**
     * Why sync is off, for the banner on the Customers page. Null when on.
     */
    public function disabledReason(): ?string
    {
        // The deliberate switch comes first: if someone turned sync off, that is
        // the reason, not whatever else might also be unset.
        if (!$this->client->isEnabled()) {
            return 'it is switched off in config/local.php (cloud.enabled)';
        }
        if (!$this->state->isInstalled()) {
            return 'the tbl_customer_cloud_sync table has not been created yet (see sql/schema.sql)';
        }
        $missing = $this->client->missingCredential();
        if ($missing === null) {
            return null;
        }

        return ($missing === 'API-KEY and TOKEN' ? 'neither API-KEY nor TOKEN is' : 'no ' . $missing . ' is')
            . ' configured (see config/local.php)';
    }

    /**
     * The customer's cloud `code`: prefix + zero-padded local id, e.g.
     * RSV000042. Deterministic, so one local customer can only ever map to one
     * cloud record. Capped at the API's 16 characters.
     */
    public function codeFor(int $customerId): string
    {
        $prefix = preg_replace('/[^A-Za-z0-9_-]/', '', $this->codePrefix) ?? '';
        $prefix = substr($prefix, 0, 10);

        return $prefix . str_pad((string) $customerId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Send one customer to the cloud.
     *
     * @return array{ok:bool,skipped:bool,message:string}
     */
    public function push(int $customerId): array
    {
        try {
            return $this->run($customerId);
        } catch (Throwable $e) {
            // Belt and braces: nothing in here may take down the page that
            // just saved a customer successfully.
            return ['ok' => false, 'skipped' => false, 'message' => 'Cloud sync failed: ' . $e->getMessage()];
        }
    }

    /** @return array{ok:bool,skipped:bool,message:string} */
    private function run(int $customerId): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'skipped' => true, 'message' => 'Cloud sync is off — ' . $this->disabledReason() . '.'];
        }

        $customer = $this->customers->find($customerId);
        if ($customer === null) {
            return ['ok' => false, 'skipped' => true, 'message' => 'Customer not found.'];
        }

        $code    = $this->codeFor($customerId);
        $payload = $this->payload($customer, $code);

        // Required by the API and not fixable here — say which field, so staff
        // can correct the customer and press Sync again.
        if ($payload === null) {
            $message = 'Name must be at least 3 characters before it can sync to the cloud.';
            $this->state->record($customerId, $code, CustomerSyncRepository::FAILED, null, $message);

            return ['ok' => false, 'skipped' => false, 'message' => $message];
        }

        $known   = $this->state->find($customerId);
        $cloudId = isset($known['cloud_id']) ? (int) $known['cloud_id'] : 0;

        $result = $cloudId > 0
            ? $this->client->updateCustomer($payload)
            : $this->client->createCustomer($payload);

        // Already in the cloud under this code (an earlier push that we never
        // saw succeed). Switch to an update instead of reporting a conflict.
        if (!$result['ok'] && $result['duplicateCode']) {
            $result = $this->client->updateCustomer($payload);
        }

        if ($result['ok']) {
            $newCloudId = isset($result['data']['id']) ? (int) $result['data']['id'] : ($cloudId ?: null);
            $this->state->record($customerId, $code, CustomerSyncRepository::SYNCED, $newCloudId, null);

            return ['ok' => true, 'skipped' => false, 'message' => 'Synced to the cloud as ' . $code . '.'];
        }

        $this->state->record($customerId, $code, CustomerSyncRepository::FAILED, $cloudId ?: null, $result['message']);

        return ['ok' => false, 'skipped' => false, 'message' => 'Cloud sync failed: ' . $result['message']];
    }

    /**
     * Map a local `tbl_customers` row onto the API's fields.
     *
     * The rule for optional fields is: send it only if it passes the documented
     * validation, otherwise leave it out. Local POS data predates these limits,
     * and one over-long address should not stop a customer syncing. `name` and
     * `code` are required, so a bad name returns null instead.
     *
     * `notes` has no counterpart in the Customer API and stays local only.
     *
     * @param array<string,mixed> $c
     * @return array<string,mixed>|null
     */
    private function payload(array $c, string $code): ?array
    {
        $name = trim((string) ($c['name'] ?? ''));
        if (mb_strlen($name) < 3) {
            return null;
        }

        $payload = ['name' => $name, 'code' => $code];

        // Digits, + and nothing else — local numbers are stored with spaces and
        // dashes that would eat into the API's 16-character limit.
        $phone = preg_replace('/[^\d+]/', '', (string) ($c['phone1'] ?? '')) ?? '';
        if ($phone !== '' && mb_strlen($phone) <= 16) {
            $payload['phone'] = $phone;
        }

        $email = trim((string) ($c['email'] ?? ''));
        if ($email !== '' && mb_strlen($email) <= 64 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $payload['email'] = $email;
        }

        $gender = trim((string) ($c['gender'] ?? ''));
        if ($gender !== '' && mb_strlen($gender) <= 8) {
            $payload['gender'] = $gender;
        }

        $address = trim((string) ($c['address'] ?? ''));
        if ($address !== '' && mb_strlen($address) <= 128) {
            $payload['address'] = $address;
        }

        // tbl_customers.birthday is the API's `dob`; both are plain dates.
        foreach (['birthday' => 'dob', 'expired' => 'expired'] as $column => $field) {
            $date = trim((string) ($c[$column] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && $date !== '0000-00-00') {
                $payload[$field] = $date;
            }
        }

        return $payload;
    }
}
