<?php

declare(strict_types=1);

use App\Helpers\Crypto;

test('Crypto encrypts and decrypts text without changing the original payload', function (): void {
    $plainText = 'lacadev-secret-payload';
    $encrypted = Crypto::encrypt($plainText);

    assert_true($encrypted !== '', 'Encrypted value should not be empty.');
    assert_true($encrypted !== $plainText, 'Encrypted value should differ from the original payload.');
    assert_true(Crypto::isEncrypted($encrypted), 'Encrypted value should be detected as encrypted.');
    assert_same($plainText, Crypto::decrypt($encrypted));
});

test('Crypto keeps empty and invalid values stable', function (): void {
    assert_same('', Crypto::encrypt(''));
    assert_same('', Crypto::decrypt(''));
    assert_same('not-base64-data', Crypto::decrypt('not-base64-data'));
    assert_true(!Crypto::isEncrypted('short'));
});
