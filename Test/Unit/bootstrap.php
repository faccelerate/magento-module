<?php
declare(strict_types=1);

/**
 * Loads the Magento autoloader (for framework classes) and maps the module's own namespace
 * onto this checkout, so the tests run wherever the module sits: set MAGENTO_ROOT, or put
 * the module somewhere below a Magento root (app/code, vendor).
 */
$moduleRoot = dirname(__DIR__, 2);
$roots = [];
if (getenv('MAGENTO_ROOT')) {
    $roots[] = getenv('MAGENTO_ROOT');
}
for ($dir = dirname($moduleRoot); $dir !== dirname($dir); $dir = dirname($dir)) {
    $roots[] = $dir;
}

foreach ($roots as $root) {
    if (is_file($root . '/vendor/autoload.php')) {
        $autoloader = require $root . '/vendor/autoload.php';
        $autoloader->addPsr4('Faccelerate\\Connect\\', $moduleRoot . '/');
        return;
    }
}

fwrite(STDERR, "Set MAGENTO_ROOT to a Magento installation with vendor/autoload.php.\n");
exit(1);
