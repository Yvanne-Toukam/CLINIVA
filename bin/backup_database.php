<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use Cliniva\Core\Config;
use Cliniva\Core\Env;

Env::load(BASE_PATH . '/.env');
date_default_timezone_set((string) env('APP_TIMEZONE', 'Africa/Douala'));

function mysqlOptionValue(string $value): string
{
    return '"' . strtr($value, ["\\" => "\\\\", '"' => '\\"', "\r" => '\\r', "\n" => '\\n']) . '"';
}

$backupDirectory = BASE_PATH . '/storage/backups';
$temporaryDump = null;
$credentialsFile = null;
$errorFile = null;
$process = null;
$pipes = [];
$gzip = null;
$exitStatus = 0;

try {
    if (!is_dir($backupDirectory) || !is_writable($backupDirectory)) {
        throw new RuntimeException('Le répertoire storage/backups est absent ou non inscriptible.');
    }

    $database = Config::get('database');
    foreach (['host', 'port', 'database', 'username', 'password'] as $requiredKey) {
        if (!array_key_exists($requiredKey, $database)) {
            throw new RuntimeException('Configuration de base de données incomplète.');
        }
    }

    $credentialsFile = tempnam(sys_get_temp_dir(), 'cliniva-mysqldump-');
    $errorFile = tempnam(sys_get_temp_dir(), 'cliniva-mysqldump-error-');
    $temporaryDump = tempnam($backupDirectory, '.cliniva-backup-');
    if ($credentialsFile === false || $errorFile === false || $temporaryDump === false) {
        throw new RuntimeException('Impossible de préparer les fichiers temporaires de sauvegarde.');
    }
    chmod($credentialsFile, 0600);
    chmod($errorFile, 0600);
    chmod($temporaryDump, 0600);

    $credentials = "[client]\n"
        . 'host=' . mysqlOptionValue((string) $database['host']) . "\n"
        . 'port=' . (int) $database['port'] . "\n"
        . 'user=' . mysqlOptionValue((string) $database['username']) . "\n"
        . 'password=' . mysqlOptionValue((string) $database['password']) . "\n"
        . "default-character-set=utf8mb4\n";
    if (file_put_contents($credentialsFile, $credentials, LOCK_EX) === false) {
        throw new RuntimeException('Impossible de préparer les identifiants mysqldump.');
    }

    $dumpBinary = trim((string) env('MYSQLDUMP_PATH', 'mysqldump')) ?: 'mysqldump';
    $command = [
        $dumpBinary,
        '--defaults-extra-file=' . $credentialsFile,
        '--single-transaction',
        '--quick',
        '--routines',
        '--triggers',
        '--hex-blob',
        (string) $database['database'],
    ];
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['file', $errorFile, 'wb'],
    ];
    $process = proc_open($command, $descriptors, $pipes, BASE_PATH, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Impossible de lancer mysqldump.');
    }
    fclose($pipes[0]);

    $gzip = gzopen($temporaryDump, 'wb9');
    if ($gzip === false) {
        throw new RuntimeException('Impossible de compresser la sauvegarde.');
    }

    while (!feof($pipes[1])) {
        $chunk = fread($pipes[1], 1024 * 1024);
        if ($chunk === false) {
            throw new RuntimeException('Lecture de mysqldump interrompue.');
        }
        if ($chunk !== '' && gzwrite($gzip, $chunk) !== strlen($chunk)) {
            throw new RuntimeException('Écriture de la sauvegarde interrompue.');
        }
    }
    fclose($pipes[1]);
    $pipes = [];
    gzclose($gzip);
    $gzip = null;

    $exitCode = proc_close($process);
    $process = null;
    if ($exitCode !== 0) {
        $detail = is_file($errorFile) ? trim((string) file_get_contents($errorFile)) : '';
        error_log('Cliniva database backup failed: ' . $detail);
        throw new RuntimeException('mysqldump a échoué; consultez le journal PHP.');
    }

    $backupPath = $backupDirectory . '/cliniva_' . date('Ymd_His') . '.sql.gz';
    if (!rename($temporaryDump, $backupPath)) {
        throw new RuntimeException('Impossible de finaliser le fichier de sauvegarde.');
    }
    $temporaryDump = null;
    chmod($backupPath, 0600);

    $cutoff = time() - (14 * 86400);
    foreach (glob($backupDirectory . '/cliniva_*.sql.gz') ?: [] as $oldBackup) {
        if (is_file($oldBackup) && filemtime($oldBackup) !== false && filemtime($oldBackup) < $cutoff) {
            unlink($oldBackup);
        }
    }

    fwrite(STDOUT, 'Sauvegarde créée : ' . basename($backupPath) . PHP_EOL);
} catch (Throwable $exception) {
    error_log('Cliniva database backup failed: ' . $exception->getMessage());
    fwrite(STDERR, "Échec de la sauvegarde; consultez le journal PHP.\n");
    $exitStatus = 1;
} finally {
    if (is_resource($gzip)) {
        gzclose($gzip);
    }
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    foreach ([$credentialsFile, $errorFile, $temporaryDump] as $temporaryFile) {
        if (is_string($temporaryFile) && is_file($temporaryFile)) {
            unlink($temporaryFile);
        }
    }
}

if ($exitStatus !== 0) {
    exit($exitStatus);
}