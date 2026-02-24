<?php
/**
 * Encryption/Decryption Functions
 * Para sa secure database backup
 */

// Encryption method
define('ENCRYPTION_METHOD', 'AES-256-CBC');

/**
 * Encrypt data
 */
function encrypt_data($data) {
    $key = hash('sha256', SYSTEM_SECRET_KEY);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    
    $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, $key, 0, $iv);
    
    // Combine IV and encrypted data
    return base64_encode($iv . '::' . $encrypted);
}

/**
 * Decrypt data
 */
function decrypt_data($data) {
    try {
        $key = hash('sha256', SYSTEM_SECRET_KEY);
        $data = base64_decode($data);
        
        list($iv, $encrypted) = explode('::', $data, 2);
        
        return openssl_decrypt($encrypted, ENCRYPTION_METHOD, $key, 0, $iv);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Verify if data is encrypted
 */
function is_encrypted($data) {
    $decoded = base64_decode($data, true);
    if ($decoded === false) {
        return false;
    }
    
    return strpos($decoded, '::') !== false;
}
?>