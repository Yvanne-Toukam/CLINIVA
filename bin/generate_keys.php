<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!function_exists('sodium_crypto_secretbox') || !defined('SODIUM_CRYPTO_SECRETBOX_KEYBYTES')) {
    fwrite(STDERR, "L'extension sodium est requise pour générer les clés Cliniva.\n");
    exit(1);
}

$applicationKey = base64_encode(random_bytes(32));
$sodiumKey = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));

fwrite(STDOUT, "APP_KEY=base64:{$applicationKey}\n");
fwrite(STDOUT, "SODIUM_KEY_BASE64={$sodiumKey}\n");