<?php namespace x\y_a_m_l;

function from(?string $value, string $dent = '  ', $content = "\t", $eval = true) {
    // Normalize line-break
    $v = \trim($value = \n($value ?? "", "\t") ?? "");
    if ("" === $v || '~' === $v || 'null' === $v || 'NULL' === $v) {
        return $eval ? null : 'null';
    }
    if ('[]' === $v) {
        return [];
    }
    if ('{}' === $v) {
        return (object) [];
    }
    if (\YAML\SOH === \trim(\strtok($v, " \n\t"))) {
        $out = [];
        // Skip any string after `...`
        [$a, $b] = \array_replace(["", null], \explode("\n" . \YAML\EOT . "\n", $v, 2));
        // Normalize document separator
        $a = \substr(\strtr("\n" . $a, [
            "\n" . \YAML\ETB . "\n" => "\n" . \YAML\ETB . ' ',
            "\n" . \YAML\ETB . "\t" => "\n" . \YAML\ETB . ' ',
            "\n" . \YAML\SOH . "\n" => "\n" . \YAML\SOH . ' ',
            "\n" . \YAML\SOH . "\t" => "\n" . \YAML\SOH . ' '
        ]), 1);
        // Remove the first document separator
        $a = \substr($a, \strpos($a, ' ') + 1);
        foreach (\explode("\n" . \YAML\ETB . ' ', $a . ' ') as $vv) {
            $out[] = from($vv, $dent, false, $eval);
        }
        // Take the rest of the YAML stream just in case you will need it!
        if ($content && \is_string($b)) {
            // We use tab character as array key placeholder by default because based on the specification, this
            // character should not be written in a YAML document, so it will be impossible that, there will be a
            // YAML key denoted by a human using a tab character.
            //
            // <https://yaml.org/spec/1.2/spec.html#id2777534>
            $out[$content] = \ltrim($b, "\n");
        }
        return \array_is_list($out) ? $out : (object) $out;
    }
    $str = '"(?:[^"\\\]|\\\.)*"|\'(?:[^\'\\\]|\\\.)*\'';
    // Flow-style value
    if ('[' === $v[0] && ']' === \substr($v, -1) || '{' === $v[0] && '}' === \substr($v, -1)) {
        $out = "";
        // Validate to JSON
        foreach (\preg_split('/\s*(' . $str . '|[\[\]\{\}:,])\s*/', $value, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) as $v) {
            if ($eval) {
                if ('false' === $v || 'true' === $v || \is_numeric($v)) {
                    $out .= $v;
                    continue;
                }
                if ('~' === $v) {
                    $out .= 'null';
                    continue;
                }
            }
            $out .= false !== \strpos('[]{}:,', $v) ? $v : \json_encode($v, false, 1);
        }
        return \json_decode($out) ?? $value;
    }
    if ("'" === $v[0] && "'" === \substr($v, -1)) {
        return \strtr(\substr($v, 1, -1), [
            "\\'" => "'"
        ]);
    }
    if ('"' === $v[0] && '"' === \substr($v, -1)) {
        try {
            $v = \json_decode($v, false, 1, \JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $v = \strtr(\substr($v, 1, -1), [
                '\"' => '"'
            ]);
        }
        return $v;
    }
    // Normalize list-style value
    if (0 === \strpos($v, "-\n")) {
        $value = \strtr($v, [
            "-\n" . $dent => '- ',
            "\n" . $dent => "\n  " // Hard-coded
        ]);
    }
    // List-style value
    if (0 === \strpos($value, '- ')) {
        $out = [];
        foreach (\explode("\n- ", \strtr(\substr($value, 2), [
            "\n" . $dent => "\n"
        ])) as $v) {
            $out[] = from($v, $dent, false, $eval);
        }
        return $out;
    }
    // Fold-style or literal-style value
    if (false !== \strpos('>|', $value[0])) {
        [$k, $v] = \explode("\n", $value, 2);
        $v = \substr(\strtr("\n" . $v, [
            "\n" . $dent => "\n"
        ]), 1);
        // <https://yaml-multiline.info>
        if ('>' === $k[0]) {
            $v = \preg_replace('/^[ \t]+[^\n]+$/m', ' $0' . "\n", $v);
            $v = \strtr(\preg_replace('/\n(?!\s|$)/', ' ', $v), [
                "\n " => "\n"
            ]);
        }
        if ("" === ($chomp = $k[1] ?? "")) {
            return \rtrim($v) . "\n";
        }
        if ('+' === $chomp) {
            return $v . "\n";
        }
        if ('-' === $chomp) {
            return \rtrim($v);
        }
        return $v;
    }
    // Scalar
    if (false === \strpos($value, ': ') && false === \strpos($value, ":\n")) {
        return $eval ? \e(\trim($value), ['~' => null]) : $value;
    }
    $chops = [];
    $k = 0;
    foreach (\explode("\n", $value) as $v) {
        $chops[$k] = "";
        if ("" === $v) {
            $chops[$k - 1] .= "\n";
            continue;
        }
        if (0 === \strpos($v, '#')) {
            continue; // Remove comment(s)
        }
        if (' ' === $v[0]) {
            $chops[$k - 1] .= "\n" . $v;
            continue;
        }
        if (0 === \strpos($v, '- ')) {
            $chops[$k - 1] .= "\n" . $v;
            continue;
        }
        if ('-' === $v) {
            $chops[$k - 1] .= "\n" . $v;
            continue;
        }
        $c = \substr(\trim(\strtok($v, '#')), -3);
        if (': [' === $c || ': {' === $c || \preg_match('/^:[ \t]+[\[{]$/', $c)) {
            $chops[$k++] .= $v;
            continue;
        }
        if (']' === $c || '}' === $c) {
            $chops[$k - 1] .= "\n" . $v;
            continue;
        }
        $chops[$k++] = $v;
    }
    $out = [];
    foreach ($chops as $v) {
        if ("" === $v) {
            continue;
        }
        if (false !== \strpos($v, '#')) {
            // Remove comment(s) except those in the string
            $v = \preg_replace('/((?:' . $str . '|[^"\'\s:]+)\s*:(?:\s+(?:' . $str . '|[>|][+-]?[\s\S]+))?)|((?:^|\s+)#[^\n]+)/', '$1', $v);
            if ("" === $v) {
                continue;
            }
        }
        if (false !== \strpos($v, ':')) {
            // Handle key that looks like a string
            if (false !== \strpos('\'"', $v[0]) && \preg_match('/^(' . $str . ')\s*:(\s+[\s\S]*)?$/', $v, $m)) {
                $kk = \strtr(\substr($m[1], 1, -1), [
                    "\\'" => "'",
                    '\"' => '"'
                ]);
                $vv = $m[2] ?? null;
            } else {
                [$kk, $n, $vv] = \array_replace(["", "", ""], \preg_split('/[ \t]*:([ \n\t]\s*|$)/', $v, 2, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY));
                // Fix case for invalid key-value pair(s) such as `xxx: xxx: xxx` as it should be `xxx:\n  xxx: xxx`
                if ($n && "\n" !== $n[0] && false !== \strpos($vv, ': ') && '[' !== $vv[0] && ']' !== \substr($vv, -1) && '{' !== $vv[0] && '}' !== \substr($vv, -1)) {
                    $out[$kk] = $vv;
                    continue;
                }
            }
            if ($vv && 0 !== \strpos($vv, '- ') && 0 !== \strpos($vv, "-\n") && false === \strpos('>|', $vv[0])) {
                $vv = \substr(\strtr("\n" . $vv, [
                    "\n" . $dent => "\n"
                ]), 1);
            }
            $out[$kk] = from($vv, $dent, false, $eval);
        } else {}
    }
    return $out;
}

\From::_('YAML', __NAMESPACE__ . "\\from");
\From::_('yaml', __NAMESPACE__ . "\\from"); // Alias