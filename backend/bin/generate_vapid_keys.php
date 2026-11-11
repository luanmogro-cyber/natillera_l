<?php
declare(strict_types=1);

$opensslConfig = dirname((string) php_ini_loaded_file())
    . DIRECTORY_SEPARATOR . 'extras'
    . DIRECTORY_SEPARATOR . 'ssl'
    . DIRECTORY_SEPARATOR . 'openssl.cnf';
$opensslConfigured = in_array('--openssl-configured', $argv, true);
if (
    PHP_OS_FAMILY === 'Windows'
    && (getenv('OPENSSL_CONF') === false || getenv('OPENSSL_CONF') === '')
    && is_file($opensslConfig)
    && !$opensslConfigured
) {
    $environment = getenv();
    if (!is_array($environment)) {
        fwrite(STDERR, "No fue posible leer el entorno para configurar OpenSSL.\n");
        exit(1);
    }
    $environment['OPENSSL_CONF'] = $opensslConfig;
    $process = proc_open(
        [PHP_BINARY, __FILE__, '--openssl-configured'],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        dirname(__DIR__),
        $environment
    );
    if (!is_resource($process)) {
        fwrite(STDERR, "No fue posible iniciar PHP con la configuración OpenSSL de Laragon.\n");
        exit(1);
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($stdout !== false) {
        fwrite(STDOUT, $stdout);
    }
    if ($stderr !== false) {
        fwrite(STDERR, $stderr);
    }
    exit($status);
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Faltan las dependencias PHP. Ejecuta composer install primero.\n");
    exit(1);
}
require $autoload;
require dirname(__DIR__) . '/config/vapid_keys.php';

$keys = Minishlink\WebPush\VAPID::createVapidKeys();
Minishlink\WebPush\VAPID::validate([
    'subject' => 'mailto:admin@localhost',
    'publicKey' => $keys['publicKey'],
    'privateKey' => $keys['privateKey'],
]);
echo "NATILLERA_VAPID_PUBLIC_KEY={$keys['publicKey']}\n";
echo "NATILLERA_VAPID_PRIVATE_KEY={$keys['privateKey']}\n";
