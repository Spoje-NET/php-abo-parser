<?php
/**
 * Debian autoloader for php-spojenet-abo-parser
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'SpojeNet\\AboParser\\';
    $baseDir = '/usr/share/php/SpojeNet/AboParser/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
