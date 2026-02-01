<?php

namespace x\page\from\x {
    function yaml($content) {
        $r = \From::YAML($content, true);
        // Check for “2-document” style
        if (\is_array($r) && \array_is_list($r) && \is_array($r[0] ?? 0) && \is_string($r[1] ?? 0)) {
            return \array_replace(['content' => $r[1]], $r[0]);
        }
        return $r;
    }
    function yml($content) {
        return yaml($content);
    }
}

namespace x\page\to\x {
    function yaml($lot) {
        return \To::YAML($lot, 2);
    }
    function yml($lot) {
        return yaml($lot);
    }
}