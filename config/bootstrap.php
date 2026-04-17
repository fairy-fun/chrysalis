<?php
declare(strict_types=1);

$externalConfig = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'pecherie_config.php';
$localConfig    = __DIR__ . DIRECTORY_SEPARATOR . 'local.php';

if (is_file($externalConfig)) {
    return require $externalConfig;
}

if (is_file($localConfig)) {
    return require $localConfig;
}

http_response_code(500);
echo 'Configuration error: no config file found.';
exit;