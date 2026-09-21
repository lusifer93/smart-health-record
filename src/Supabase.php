<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

final class Supabase {
    private string $url;
    private string $key;
    public function __construct() { $this->url = rtrim(env('SUPABASE_URL'), '/'); $this->key = env('SUPABASE_ANON_KEY'); }
    public function request(string $method, string $path, ?array $body = null, ?string $token = null, array $extra = []): array {
        $ch = curl_init($this->url . $path);
        $headers = array_merge(['apikey: ' . $this->key, 'Accept: application/json'], $extra);
        if ($token) $headers[] = 'Authorization: Bearer ' . $token;
        if ($body !== null) { $headers[] = 'Content-Type: application/json'; curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR)); }
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25]);
        $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch);
        $data = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;
        if ($code === 401 && $token && function_exists('sign_out')) {
            sign_out();
            if (function_exists('flash') && function_exists('redirect')) {
                flash('warning', 'Your session expired. Please sign in again.');
                redirect('?page=login');
            }
        }
        if ($raw === false || $code >= 400) throw new RuntimeException(($data['message'] ?? $data['error_description'] ?? $data['msg'] ?? $error ?: 'Supabase request failed') . " (HTTP $code)");
        return is_array($data) ? $data : [];
    }
    public function auth(string $method, string $path, ?array $body = null): array { return $this->request($method, '/auth/v1' . $path, $body, null, ['Content-Type: application/json']); }
    public function authUser(string $method, string $path, ?array $body = null): array { return $this->request($method, '/auth/v1' . $path, $body, auth_token(), ['Content-Type: application/json']); }
    public function db(string $method, string $table, string $query = '', ?array $body = null): array { return $this->request($method, '/rest/v1/' . $table . $query, $body, auth_token(), ['Prefer: return=representation']); }
    public function rpc(string $name, array $body = []): array { return $this->request('POST', '/rest/v1/rpc/'.$name, $body, auth_token(), ['Content-Type: application/json']); }
    public function storageUpload(string $path, string $contents, string $mime): array {
        $url = $this->url . '/storage/v1/object/' . rawurlencode(env('SUPABASE_STORAGE_BUCKET', 'medical-records')) . '/' . str_replace('%2F', '/', rawurlencode($path));
        $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $contents, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45, CURLOPT_HTTPHEADER => ['apikey: ' . $this->key, 'Authorization: Bearer ' . auth_token(), 'Content-Type: ' . $mime, 'x-upsert: false']]);
        $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch);
        if ($raw === false || $code >= 400) throw new RuntimeException('File upload failed: ' . $error . " (HTTP $code)");
        return json_decode($raw ?: '[]', true) ?: [];
    }
    public function signedFileUrl(string $path, bool $download = false): string {
        $payload = ['expiresIn' => 300];
        if ($download) $payload['download'] = true;
        $r = $this->request('POST', '/storage/v1/object/sign/' . rawurlencode(env('SUPABASE_STORAGE_BUCKET', 'medical-records')) . '/' . str_replace('%2F', '/', rawurlencode($path)), $payload, auth_token());
        return $this->url . '/storage/v1' . ($r['signedURL'] ?? throw new RuntimeException('Could not create download link.'));
    }
}
