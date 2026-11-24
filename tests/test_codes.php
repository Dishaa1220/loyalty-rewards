<?php
// Run with:  C:\xampp\php\php.exe tests\test_codes.php
require_once __DIR__ . '/../app/codes.php';

$passed = 0;
$failed = 0;

function check(string $name, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS  $name\n";
    } else {
        $failed++;
        echo "FAIL  $name\n";
    }
}

// 1. Format of a generated uid
$uid = generate_uid();
check('uid has 12 characters', strlen($uid) === UID_LENGTH);
check('uid uses only the allowed alphabet', preg_match('/^[A-HJ-NP-Z2-9]{12}$/', $uid) === 1);

// 2. Uniqueness over many codes
$seen = [];
for ($i = 0; $i < 10000; $i++) {
    $seen[generate_uid()] = true;
}
check('10,000 generated uids are all unique', count($seen) === 10000);

// 3. A genuine token is accepted
$token = make_token($uid);
check('genuine token is accepted', parse_token($token) === $uid);
check('lowercase typing is accepted', parse_token(strtolower($token)) === $uid);

// 4. Tampering is rejected
$otherChar = $uid[0] === 'A' ? 'B' : 'A';
$tamperedUid = $otherChar . substr($uid, 1);
check('changed uid is rejected', parse_token($tamperedUid . '-' . sign_uid($uid)) === null);
check('wrong signature is rejected', parse_token($uid . '-0000000000') === null);

// 5. Garbage input is rejected
check('empty string is rejected', parse_token('') === null);
check('random text is rejected', parse_token('hello') === null);
check('SQL injection text is rejected', parse_token("' OR 1=1 --") === null);

// 6. A token made with a different secret is rejected
$realSecret = $_ENV['QR_SECRET_KEY'];
$_ENV['QR_SECRET_KEY'] = 'a_completely_different_secret_key';
$forged = make_token($uid);
$_ENV['QR_SECRET_KEY'] = $realSecret;
check('token signed with another secret is rejected', parse_token($forged) === null);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);