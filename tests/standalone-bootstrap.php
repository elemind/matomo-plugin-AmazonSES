<?php

/**
 * Bootstrap to run the plugin unit tests without a Matomo checkout:
 *   AMAZONSES_VENDOR_AUTOLOAD=/path/to/vendor/autoload.php phpunit -c tests/phpunit.standalone.xml
 * The vendor dir must provide phpunit and phpmailer/phpmailer.
 */

$autoload = getenv('AMAZONSES_VENDOR_AUTOLOAD') ?: __DIR__ . '/../vendor/autoload.php';
require $autoload;

spl_autoload_register(function ($class) {
    if (strpos($class, 'Piwik\\Plugins\\AmazonSES\\tests\\') === 0) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen('Piwik\\Plugins\\AmazonSES\\tests\\'))) . '.php';
        if (is_file($file)) {
            require $file;
        }
        return;
    }
    $prefix = 'Piwik\\Plugins\\AmazonSES\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $file = __DIR__ . '/../' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
