<?php
require_once __DIR__ . '/config.php';

// No 0, O, 1, I: they look alike on a printed box
const UID_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
const UID_LENGTH   = 12;
const SIG_LENGTH   = 10;

function qr_secret(): string
{
    $secret = env('QR_SECRET_KEY', '');
    if (strlen($secret) < 16) {
        throw new RuntimeException('QR_SECRET_KEY in .env must be at least 16 characters.');
    }
    return $secret;
}

// A random 12-character code, e.g. K7M2Q9XW4PRT
function generate_uid(): string
{
    $uid = '';
    $max = strlen(UID_ALPHABET) - 1;
    for ($i = 0; $i < UID_LENGTH; $i++) {
        $uid .= UID_ALPHABET[random_int(0, $max)];
    }
    return $uid;
}

function sign_uid(string $uid): string
{
    return substr(hash_hmac('sha256', $uid, qr_secret()), 0, SIG_LENGTH);
}

// What is printed on the box: uid + signature
function make_token(string $uid): string
{
    return $uid . '-' . sign_uid($uid);
}

// Checks a scanned/typed token. Returns the uid if genuine, or null if not.
function parse_token(string $token): ?string
{
    $parts = explode('-', trim($token));
    if (count($parts) !== 2) {
        return null;
    }

    $uid = strtoupper($parts[0]);
    $sig = strtolower($parts[1]);

    if (!preg_match('/^[A-HJ-NP-Z2-9]{12}$/', $uid) || !preg_match('/^[0-9a-f]{10}$/', $sig)) {
        return null;
    }

    return hash_equals(sign_uid($uid), $sig) ? $uid : null;
}

// The full link stored in the QR code
function redeem_link(string $uid): string
{
    return rtrim(env('APP_URL', ''), '/') . '/redeem.php?c=' . urlencode(make_token($uid));
}