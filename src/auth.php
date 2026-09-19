<?php
declare(strict_types=1);
require_once __DIR__ . '/Supabase.php';

function auth_token(): ?string { return $_SESSION['auth']['access_token'] ?? null; }
function user(): ?array { return $_SESSION['auth']['user'] ?? null; }
function require_auth(): void { if (!auth_token() || !user()) { flash('warning', 'Please sign in first.'); redirect('?page=login'); } }
function sign_out(): void { unset($_SESSION['auth'], $_SESSION['profile']); }
function sign_in(string $email, string $password): void { $_SESSION['auth'] = (new Supabase())->auth('POST', '/token?grant_type=password', compact('email', 'password')); unset($_SESSION['profile']); }
function sign_up(string $email, string $password, string $name): array {
    $result = (new Supabase())->auth('POST', '/signup', ['email' => $email, 'password' => $password, 'data' => ['full_name' => $name]]);
    if (!empty($result['access_token']) && !empty($result['user'])) $_SESSION['auth'] = $result;
    return $result;
}

function profile(bool $refresh = false): ?array {
    if (!$refresh && isset($_SESSION['profile'])) return $_SESSION['profile'];
    if (!auth_token() || !user()) return null;
    $rows = (new Supabase())->db('GET', 'profiles', '?select=*&id=eq.' . rawurlencode((string)user()['id']) . '&limit=1');
    $_SESSION['profile'] = $rows[0] ?? null;
    return $_SESSION['profile'];
}

function current_role(): ?string { return profile()['role'] ?? null; }

function require_role(string $role): void {
    require_auth();
    if (current_role() !== $role) {
        flash('warning', 'This area is only available to ' . $role . ' accounts.');
        redirect('?page=' . (current_role() === 'doctor' ? 'doctor_dashboard' : 'patient_dashboard'));
    }
}
