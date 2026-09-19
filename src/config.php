<?php
declare(strict_types=1);

function env(string $name, string $default = ''): string {
    static $values = null;
    if ($values === null) {
        $values = [];
        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        if (is_file($file)) foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value, " \t\"'");
        }
    }
    return $_ENV[$name] ?? getenv($name) ?: ($values[$name] ?? $default);
}

function configured(): bool { return str_starts_with(env('SUPABASE_URL'), 'https://') && env('SUPABASE_ANON_KEY') !== ''; }
function flash(string $type, string $message): void { $_SESSION['flash'][] = compact('type', 'message'); }
function redirect(string $path = '?'): never { header('Location: ' . $path); exit; }
function safe_error_message(Throwable $error): string {
    error_log('[CareNest] '.$error->getMessage());
    $message=$error->getMessage();
    if (str_contains(strtolower($message), 'invalid login')) return 'Email or password is incorrect.';
    if (preg_match('/^(Choose|Enter|Please|For prescription|The prescription|No authorised|This patient|Medicine|Files|File upload|AI prescription)/i',$message)) return $message;
    return 'We could not complete that request. Please try again.';
}
