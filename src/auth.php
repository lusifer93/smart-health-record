<?php
declare(strict_types=1);
require_once __DIR__ . '/Supabase.php';
require_once __DIR__ . '/account_features.php';
require_once __DIR__ . '/account_routes.php';

function auth_token(): ?string { return $_SESSION['auth']['access_token'] ?? null; }
function user(): ?array { return $_SESSION['auth']['user'] ?? null; }
function require_auth(): void { if (!auth_token() || !user()) { flash('warning', 'Please sign in first.'); redirect('?page=login'); } }
function sign_out(): void { unset($_SESSION['auth'], $_SESSION['profile']); }
function sign_in(string $email, string $password): void { $_SESSION['auth'] = (new Supabase())->auth('POST', '/token?grant_type=password', compact('email', 'password')); unset($_SESSION['profile']); }
function change_password(string $currentPassword, string $newPassword, string $confirmPassword): void {
    if ($newPassword !== $confirmPassword) throw new RuntimeException('New password and confirmation do not match.');
    if (strlen($newPassword) < 10 || !preg_match('/[A-Z]/',$newPassword) || !preg_match('/[a-z]/',$newPassword) || !preg_match('/\d/',$newPassword)) throw new RuntimeException('Use at least 10 characters with uppercase, lowercase, and a number.');
    $email=(string)(user()['email'] ?? '');
    if ($email === '' || $currentPassword === '') throw new RuntimeException('Enter your current password.');
    (new Supabase())->auth('POST', '/token?grant_type=password', ['email'=>$email,'password'=>$currentPassword]);
    (new Supabase())->authUser('PUT', '/user', ['password'=>$newPassword]);
    sign_out();
}
function sign_up(string $email, string $password, string $name): array {
    $result = (new Supabase())->auth('POST', '/signup', ['email'=>$email,'password'=>$password,'data'=>['full_name'=>$name]]);
    if (!empty($result['access_token']) && !empty($result['user'])) $_SESSION['auth']=$result;
    return $result;
}
function profile(bool $refresh=false): ?array {
    if (!$refresh && isset($_SESSION['profile'])) return $_SESSION['profile'];
    if (!auth_token() || !user()) return null;
    $rows=(new Supabase())->db('GET','profiles','?select=*&id=eq.'.rawurlencode((string)user()['id']).'&limit=1');
    $_SESSION['profile']=$rows[0] ?? null; return $_SESSION['profile'];
}
function current_role(): ?string { return profile()['role'] ?? null; }
function require_role(string $role): void { require_auth(); if (current_role() !== $role) { flash('warning','This area is only available to '.$role.' accounts.'); $home=current_role()==='admin'?'admin_dashboard':(current_role()==='doctor'?'doctor_dashboard':'dashboard'); redirect('?page='.$home); } }

account_route_dispatch();
