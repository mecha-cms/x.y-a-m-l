<?php

foreach (glob(__DIR__ . D . 'test' . D . '*.yaml') as $v) {
    echo '<h1 id="' . ($n = basename($v)) . '"><a aria-hidden="true" href="#' . $n . '">&sect;</a> ' . strtr($v, [PATH . D => '.' . D]) . '</h1>';
    echo '<pre style="background:#ccc;border:1px solid rgba(0,0,0,.25);color:#000;font:normal normal 100%/1.25 monospace;padding:.5em .75em;white-space:pre-wrap;word-wrap:break-word;">' . htmlspecialchars($v = file_get_contents($v)) . '</pre>';
    echo '<pre style="background:#cfc;border:1px solid rgba(0,0,0,.25);color:#000;font:normal normal 100%/1.25 monospace;padding:.5em .75em;white-space:pre-wrap;word-wrap:break-word;">' . json_encode(From::YAML($v), JSON_PRETTY_PRINT) . '</pre>';
}

foreach (glob(__DIR__ . D . 'test' . D . '*.json') as $v) {
    echo '<h1 id="' . ($n = basename($v)) . '"><a aria-hidden="true" href="#' . $n . '">&sect;</a> ' . strtr($v, [PATH . D => '.' . D]) . '</h1>';
    echo '<pre style="background:#ccc;border:1px solid rgba(0,0,0,.25);color:#000;font:normal normal 100%/1.25 monospace;padding:.5em .75em;white-space:pre-wrap;word-wrap:break-word;">' . htmlspecialchars($v = file_get_contents($v)) . '</pre>';
    echo '<pre style="background:#cfc;border:1px solid rgba(0,0,0,.25);color:#000;font:normal normal 100%/1.25 monospace;padding:.5em .75em;white-space:pre-wrap;word-wrap:break-word;">' . To::YAML(json_decode($v)) . '</pre>';
}

exit;