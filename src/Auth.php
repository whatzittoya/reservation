<?php

declare(strict_types=1);

namespace App;

/**
 * Session-based authentication on top of the POS `tbl_employees` table.
 * A logged-in employee is "owner" when their jobTitle matches a configured
 * keyword; otherwise "employee".
 */
final class Auth
{
    /** @param array<int,string> $ownerTitleKeywords */
    public function __construct(
        private EmployeeRepository $employees,
        private array $ownerTitleKeywords
    ) {
    }

    /**
     * Attempt login. On success stores the user in the session and returns true.
     */
    public function attempt(string $username, string $pin): bool
    {
        $emp = $this->employees->authenticate(trim($username), trim($pin));
        if ($emp === null) {
            return false;
        }

        $name = (string) ($emp['name'] ?? $emp['code'] ?? 'Staff');
        $jobTitle = (string) ($emp['jobTitle'] ?? '');

        $_SESSION['user'] = [
            'id'       => (int) $emp['id'],
            'name'     => $name,
            'jobTitle' => $jobTitle,
            // Generic POS accounts (Manager/Admin) carry the label in `name`,
            // real staff carry it in `jobTitle` — so match against both.
            'role'     => $this->roleFor($name . ' ' . $jobTitle),
        ];

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['user']);
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public function isOwner(): bool
    {
        return ($_SESSION['user']['role'] ?? null) === 'owner';
    }

    public function id(): ?int
    {
        return isset($_SESSION['user']) ? (int) $_SESSION['user']['id'] : null;
    }

    private function roleFor(string $haystack): string
    {
        $title = strtolower($haystack);
        foreach ($this->ownerTitleKeywords as $kw) {
            if ($kw !== '' && str_contains($title, strtolower($kw))) {
                return 'owner';
            }
        }

        return 'employee';
    }
}
