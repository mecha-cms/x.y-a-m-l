<?php

// <https://github.com/mecha-cms/mecha/issues/94>
define("YAML\\SOH", '---');
define("YAML\\ETB", '---');
define("YAML\\EOT", '...');

if (defined('TEST') && 'x.y-a-m-l' === TEST && is_file($test = __DIR__ . D . 'test.php')) {
    require $test;
}