<?php namespace x\y_a_m_l;

function to($value, string $dent = '  ', $content = "\t"): ?string {
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
            $value = \strtr($value, [
                "\n" => "\n" . $dent
            ]);
            $value = \substr(\strtr($value . "\n", [
                $dent . "\n" => "\n"
            ]), 0, -1);
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
        return false !== \strpos($value, "\\") || \preg_match('/[\n\r\t]/', $value) ? \json_encode($value, \JSON_UNESCAPED_SLASHES, 1) : $value;
    }
    if (\is_array($value) || \is_object($value)) {
        if (\is_object($value)) {
            if (0 === \q($value)) {
                return '{}';
            }
            $value = (array) $value;
        }
        $out = [];
        if ($content && \array_key_exists($content, $value)) {
            $body = $value[$content];
            unset($value[$content]);
            if (\array_is_list($value)) {
                foreach ($value as $v) {
                    $out[] = to($v, $dent, false);
                }
                $out = \YAML\SOH . "\n" . \implode("\n" . \YAML\ETB . "\n", $out);
                if (null !== $body) {
                    $out .= "\n" . \YAML\EOT . "\n\n" . $body;
                }
                return $out;
            }
        }
        if (\array_is_list($value)) {
            if (!$value) {
                return '[]'; // Empty array
            }
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
                    $out .= to($v, $dent, false) . ', ';
                }
                return \substr($out, 0, -2) . ' ]';
            }
            foreach ($value as $k => $v) {
                if (\is_array($v) || \is_object($v)) {
                    if (\is_object($v)) {
                        if (0 === \q($v)) {
                            $out[] = '- {}'; // Empty object
                            continue;
                        }
                        $v = (array) $v;
                    }
                    $out[] = '- ' . \strtr(to($v, $dent, false), [
                        "\n" => "\n" . $dent
                    ]);
                    continue;
                }
                $out[] = '- ' . to($v, $dent, false);
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
                $out .= $k . ': ' . to($v, $dent, false) . ', ';
            }
            return \substr($out, 0, -2) . ' }';
        }
        foreach ($value as $k => $v) {
            // Test for safe key pattern, otherwise, wrap it with quote!
            if (\is_numeric($k) || \preg_match('/^[:.\w-]+$/', $k)) {} else {
                if (false === \strpos($k, '"') && false !== \strpos($k, "'")) {
                    $k = '"' . $k . '"';
                } else {
                    $k = "'" . \strtr($k, [
                        "'" => "\\'"
                    ]) . "'";
                }
            }
            if (\is_array($v) || \is_object($v)) {
                if (\is_object($v)) {
                    if (0 === \q($v)) {
                        $out[] = $k . ': {}'; // Empty object
                        continue;
                    }
                    $v = (array) $v;
                }
                if (\array_is_list($v)) {
                    if (!$v) {
                        $out[] = $k . ': []'; // Empty array
                        continue;
                    }
                    // Prefer flow-style value?
                    $flow = \count($v) < 7 && \all($v, function ($v) {
                        if (\is_string($v)) {
                            return "" === $v || \strlen($v) < 7 && \preg_match('/^[\w-]+$/', $v);
                        }
                        return \is_float($v) || \is_int($v) || false === $v || null === $v || true === $v;
                    });
                    if ($flow) {
                        $out[] = $k . ': ' . to($v, $dent, false);
                        continue;
                    }
                    $dent = '  '; // Hard-coded
                    $out[] = $k . ":\n" . to($v, $dent, false);
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
                    $out[] = $k . ': ' . to($v, $dent, false);
                    continue;
                }
                $out[] = $k . ":\n" . $dent . \strtr(to($v, $dent, false), [
                    "\n" => "\n" . $dent
                ]);
                continue;
            }
            $out[] = $k . ': ' . to($v, $dent, false);
        }
        return \implode("\n", $out);
    }
    return null; // Error?
}

\To::_('YAML', __NAMESPACE__ . "\\to");
\To::_('yaml', __NAMESPACE__ . "\\to"); // Alias