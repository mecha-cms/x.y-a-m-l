<?php

namespace x\y_a_m_l {
    function from(?string $value, string $dent = '  ', $content = "\t", $eval = true) {
        /*
        if (extension_loaded('yaml')) {}
        */
        // Normalize line-break
        $v = \trim($value = \n($value ?? "", "\t") ?? "");
        if ("" === $v || '~' === $v || 'null' === $v || 'NULL' === $v) {
            return $eval ? null : 'null';
        }
        if ('[]' === $v || '{}' === $v) {
            return [];
        }
        // Document separator
        if (\YAML\SOH === \trim(\strtok($v, "\n#"))) {
            $out = [];
            // Skip any string after `...`
            [$a, $b] = \array_replace(["", null], \explode("\n" . \YAML\EOT . "\n", $v, 2));
            // Remove the first document separator
            $a = \substr($a, (\strpos($a, '#') || \strpos($a, "\n")) + \strlen(\YAML\SOH));
            foreach (\explode("\n" . \YAML\ETB . "\n", $a . "\n") as $vv) {
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
            return $out;
        }
        $str = '"(?:[^"\\\]|\\\.)*"|\'(?:[^\'\\\]|\\\.)*\'';
        // Flow-style value
        if ('[' === $v[0] && ']' === \substr($v, -1) || '{' === $v[0] && '}' === \substr($v, -1)) {
            $out = "";
            // Validate to JSON
            foreach (\preg_split('/\s*(' . $str . '|[\[\]\{\}:,])\s*/', $value, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) as $v) {
                $out .= false !== \strpos('[]{}:,', $v) ? $v : \json_encode($v);
            }
            $v = \json_decode($out, true) ?? $value;
            return $eval ? \e($v, ['~' => null]) : $v;
        }
        if ("'" === $v[0] && "'" === \substr($v, -1)) {
            return \strtr(\substr($v, 1, -1), ["\\'" => "'"]);
        }
        if ('"' === $v[0] && '"' === \substr($v, -1)) {
            try {
                $v = \json_decode($v, true);
            } catch (\Throwable $e) {
                $v = \strtr(\substr($v, 1, -1), ['\\"' => '"']);
            }
            return $v;
        }
        // Normalize list-style value
        if (0 === \strpos($v, "-\n")) {
            $value = \strtr($v, [
                "-\n" . $dent => '- ',
                "\n" . $dent => "\n  "
            ]);
        }
        // List-style value
        if (0 === \strpos($value, '- ')) {
            $out = [];
            foreach (\explode("\n- ", \strtr(\substr($value, 2), [
                "\n" . $dent => "\n"
            ])) as $v) {
                $out[] = from($v, $dent, $content, $eval);
            }
            return $out;
        }
        // Fold-style or literal-style value
        if (false !== \strpos('>|', $value[0])) {
            [$k, $v] = \explode("\n", $value, 2);
            $v = \substr(\strtr("\n" . $v, ["\n" . $dent => "\n"]), 1);
            if ('>' === $k[0]) {
                $v = \preg_replace('/^[ \t]+[^\n]+$/m', ' $0' . "\n", $v);
                $v = \strtr(\preg_replace('/\n(?!\s|$)/', ' ', $v), ["\n " => "\n"]);
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
            if (': [' === $c || ': {' === $c) {
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
                $v = \preg_replace('/((?:' . $str . '|[^"\'\s:]+)\s*:(?:[ \t]+(?:' . $str . '|[>|][+-]?[\s\S]+))?)|((?:^|[ \t])#[^\n]+)/', '$1', $v);
                if ("" === $v) {
                    continue;
                }
            }
            if (false !== \strpos($v, ':')) {
                // Handle key that looks like a string
                if (false !== \strpos('\'"', $v[0]) && \preg_match('/^(' . $str . ')\s*:\s*([\s\S]*)?$/', $v, $m)) {
                    $kk = \strtr(\substr($m[1], 1, -1), [
                        "\\'" => "'",
                        "\\\"" => '"'
                    ]);
                    $vv = $m[2] ?? null;
                } else {
                    [$kk, $n, $vv] = \array_replace(["", "", ""], \preg_split('/\s*:([ \n\t]\s*|$)/', $v, 2, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY));
                    // Fix case for invalid key-value pair(s) `xxx: xxx: xxx` as it should be `xxx:\n  xxx: xxx`
                    if ($n && "\n" !== $n[0] && false !== \strpos($vv, ': ') && '[' !== $vv[0] && ']' !== \substr($vv, -1) && '{' !== $vv[0] && '}' !== \substr($vv, -1)) {
                        $out[$kk] = $vv;
                        continue;
                    }
                }
                if ($vv && 0 !== \strpos($vv, '- ') && 0 !== \strpos($vv, "-\n") && false === \strpos('>|', $vv[0])) {
                    $vv = \substr(\strtr("\n" . $vv, ["\n" . $dent => "\n"]), 1);
                }
                $out[$kk] = from($vv, $dent, $content, $eval);
            } else {}
        }
        return $out;
    }
    function to($value, string $dent = '  ', $content = "\t", $eval = true): ?string {
        /*
        if (extension_loaded('yaml')) {}
        */
        if (null === $value) {
            return '~';
        }
        if (false === $value) {
            return 'false';
        }
        if (true === $value) {
            return 'true';
        }
        if ("" === $value) {
            return '""';
        }
        if (\is_float($value) || \is_int($value)) {
            return (string) $value;
        }
        if (\is_string($value)) {
            // <https://yaml-multiline.info>
            if (false !== \strpos(\trim($value, "\n"), "\n")) {
                $chomp = "\n\n" === \substr($value, -2) ? '+' : ("\n" === \substr($value, -1) ? "" : '-');
                $value = \strtr($value, ["\n" => "\n" . $dent]);
                $value = \substr(\strtr($value . "\n", [$dent . "\n" => "\n"]), 0, -1);
                return '|' . $chomp . "\n" . $dent . ("\n" === \substr($value, -1) ? \substr($value, 0, -1) : $value);
            }
            if (\strlen(\trim($value, "\n")) > 120) {
                $chomp = "\n\n" === \substr($value, -2) ? '+' : ("\n" === \substr($value, -1) ? "" : '-');
                $value = \wordwrap("\n" === \substr($value, -1) ? \substr($value, 0, -1) : $value, 120, "\n" . $dent);
                return '>' . $chomp . "\n" . $dent . $value;
            }
            if (\is_numeric($value) || $value !== \strtr($value, "!#%&*,-:<=>?@[\\]{|}", '-------------------')) {
                return "'" . $value . "'";
            }
            return false !== \strpos($value, "\\") || \preg_match('/[\n\r\t]/', $value) ? \json_encode($value) : $value;
        }
        if (\is_array($value)) {
            $out = [];
            if ($content && \array_key_exists($content, $value)) {
                $body = $value[$content];
                unset($value[$content]);
                if (\array_is_list($value)) {
                    foreach ($value as $v) {
                        $out[] = to($v, $dent, false, $eval);
                    }
                    $out = \YAML\SOH . "\n" . \implode("\n" . \YAML\ETB . "\n", $out);
                    if (null !== $body) {
                        $out .= "\n" . \YAML\EOT . "\n\n" . $body;
                    }
                    return $out;
                }
            }
            if (\array_is_list($value)) {
                // Prefer flow-style value?
                $flow = \count($value) < 7 && \all($value, function ($v) {
                    if (\is_string($v)) {
                        return "" === $v || \strlen($v) < 7 && \preg_match('/^[\w-]+$/', $v);
                    }
                    return \is_float($v) || \is_int($v) || false === $v || null === $v || true === $v;
                });
                if ($flow) {
                    $out = '[ ';
                    foreach ($value as $v) {
                        $out .= to($v, $dent, $content, $eval) . ', ';
                    }
                    return \substr($out, 0, -2) . ' ]';
                }
                foreach ($value as $k => $v) {
                    if (\is_array($v)) {
                        $out[] = '- ' . \strtr(to($v, $dent, $content, $eval), ["\n" => "\n" . $dent]);
                        continue;
                    }
                    $out[] = '- ' . to($v, $dent, $content, $eval);
                }
                return \implode("\n", $out);
            }
            // Prefer flow-style value?
            $flow = \count($value) < 3 && \all($value, function ($v, $k) {
                if (!\preg_match('/^[\w-]+$/', $k)) {
                    return false;
                }
                if (\is_string($v)) {
                    return "" === $v || \strlen($v) < 7 && \preg_match('/^[\w-]+$/', $v);
                }
                return \is_float($v) || \is_int($v) || false === $v || null === $v || true === $v;
            });
            if ($flow) {
                $out = '{ ';
                foreach ($value as $k => $v) {
                    $out .= $k . ': ' . to($v, $dent, $content, $eval) . ', ';
                }
                return \substr($out, 0, -2) . ' }';
            }
            foreach ($value as $k => $v) {
                // Test for safe key pattern, otherwise, wrap it with quote!
                if (\is_numeric($k) || \preg_match('/^[:\w-]+$/', $k)) {} else {
                    if (false === \strpos($k, '"') && false !== \strpos($k, "'")) {
                        $k = '"' . $k . '"';
                    } else {
                        $k = "'" . \strtr($k, ["'" => "\\'"]) . "'";
                    }
                }
                if (\is_array($v)) {
                    if (\array_is_list($v)) {
                        // Prefer flow-style value?
                        $flow = \count($v) < 7 && \all($v, function ($v) {
                            if (\is_string($v)) {
                                return "" === $v || \strlen($v) < 7 && \preg_match('/^[\w-]+$/', $v);
                            }
                            return \is_float($v) || \is_int($v) || false === $v || null === $v || true === $v;
                        });
                        if ($flow) {
                            $out[] = $k . ': ' . to($v, $dent, $content, $eval);
                            continue;
                        }
                        $dent = '  '; // Hard-coded
                        $out[] = $k . ":\n" . to($v, $dent, $content, $eval);
                        continue;
                    }
                    // Prefer flow-style value?
                    $flow = \count($v) < 3 && \all($v, function ($v, $k) {
                        if (!\preg_match('/^[\w-]+$/', $k)) {
                            return false;
                        }
                        if (\is_string($v)) {
                            return "" === $v || \strlen($v) < 7 && \preg_match('/^[\w-]+$/', $v);
                        }
                        return \is_float($v) || \is_int($v) || false === $v || null === $v || true === $v;
                    });
                    if ($flow) {
                        $out[] = $k . ': ' . to($v, $dent, $content, $eval);
                        continue;
                    }
                    $out[] = $k . ":\n" . $dent . \strtr(to($v, $dent, $content, $eval), ["\n" => "\n" . $dent]);
                    continue;
                }
                $out[] = $k . ': ' . to($v, $dent, $content, $eval);
            }
            return \implode("\n", $out);
        }
        return null;
    }
    \From::_('YAML', __NAMESPACE__ . "\\from");
    \From::_('yaml', __NAMESPACE__ . "\\from"); // Alias
    \To::_('YAML', __NAMESPACE__ . "\\to");
    \To::_('yaml', __NAMESPACE__ . "\\to"); // Alias
}

namespace {
    // <https://github.com/mecha-cms/mecha/issues/94>
    \define("YAML\\SOH", '---');
    \define("YAML\\ETB", '---');
    \define("YAML\\EOT", '...');
    if (\defined("\\TEST") && 'x.y-a-m-l' === \TEST && \is_file($test = __DIR__ . \D . 'test.php')) {
        require $test;
    }
}