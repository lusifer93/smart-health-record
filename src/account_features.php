<?php
/** Account/profile helpers used by the patient account page. */
function update_user_profile(Supabase $sb, string $uid, array $input): void {
    $name = trim((string)($input['full_name'] ?? ''));
    if ($name === '') throw new RuntimeException('Please enter your full name.');
    $sb->db('PATCH', 'profiles', '?id=eq.' . rawurlencode($uid), [
        'full_name' => $name,
        'phone' => trim((string)($input['phone'] ?? '')),
        'address' => trim((string)($input['address'] ?? '')),
        'nearest_hospital' => trim((string)($input['nearest_hospital'] ?? '')),
    ]);
    profile(true);
}

function register_doctor(Supabase $sb, array $input, array $file): void {
    $name = trim((string)($input['name'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $required = ['specialization', 'qualification', 'clinic_name', 'license_number'];
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        throw new RuntimeException('Enter a valid name, email, and password of at least 8 characters.');
    }
    foreach ($required as $field) {
        if (trim((string)($input[$field] ?? '')) === '') throw new RuntimeException('Complete all doctor credential fields.');
    }
    $upload = validated_medical_upload($file);
    $result = sign_up($email, $password, $name);
    if (empty($result['user']['id'])) throw new RuntimeException('Account created, but the doctor profile could not be prepared.');
    $uid = (string)$result['user']['id'];
    $path = $uid . '/credentials/' . bin2hex(random_bytes(16)) . '.' . $upload['extension'];
    $sb->storageUpload($path, (string)file_get_contents($upload['tmp_name']), $upload['mime']);
    $sb->db('PATCH', 'profiles', '?id=eq.' . rawurlencode($uid), [
        'professional_title' => 'Doctor',
        'specialization' => trim((string)$input['specialization']),
        'qualification' => trim((string)$input['qualification']),
        'clinic_name' => trim((string)$input['clinic_name']),
        'license_number' => trim((string)$input['license_number']),
        'credential_path' => $path,
        'credential_document_type' => $upload['mime'],
        'verification_status' => 'pending',
    ]);
}
