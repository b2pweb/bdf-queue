<?php

/**
 * Parse the command line to extract options and arguments
 *
 * ex:
 *  ./script.php foo bar --option=value -f
 *
 * feed arguments:
 * array(2) {
 *   [0]=>
 *   string(3) "foo"
 *   [1]=>
 *   string(3) "bar"
 * }
 * and feed options:
 * array(2) {
 *   ["option"]=>
 *   string(5) "value"
 *   ["f"]=>
 *   bool(true)
 * }
 *
 * @param array $arguments
 * @param array $options
 */
function parseCommandLine(array &$arguments, array &$options = [])
{
    $parsed = $_SERVER['argv'];
    array_shift($parsed);

    $parseOptions = true;
    while (null !== $token = array_shift($parsed)) {
        if ($parseOptions && '' == $token) {
            $arguments[] = $token;
        } elseif ($parseOptions && '--' == $token) {
            $parseOptions = false;
        } elseif ($parseOptions && 0 === strpos($token, '--')) {
            $name = substr($token, 2);

            if (false !== $pos = strpos($name, '=')) {
                if (0 === \strlen($value = substr($name, $pos + 1))) {
                    array_unshift($parsed, $value);
                }
                $options[substr($name, 0, $pos)] = $value;
            } else {
                $options[$name] = true;
            }

        } elseif ($parseOptions && '-' === $token[0] && '-' !== $token) {
            $name = substr($token, 1);

            $options[$name] = true;
        } else {
            $arguments[] = $token;
        }
    }
}

/**
 * Display a table of data
 *
 * @param array $headers
 * @param array $rows
 */
function displayTable(array $headers, array $rows)
{
    foreach ($headers as $head) {
        echo str_pad($head, 20)." |";
    }

    echo PHP_EOL;

    foreach ($rows as $row) {
        foreach ($row as $data) {
            echo str_pad($data, 20)." |";
        }

        echo PHP_EOL;
    }
}

/**
 * Convert the given string value in bytes.
 *
 * @param string $value
 *
 * @return int
 */
function convertToBytes(string $value): int
{
    $value = strtolower(trim($value));
    $unit = substr($value, -1);
    $bytes = (int) $value;

    switch ($unit) {
        case 't': $bytes *= 1024;
        // no break
        case 'g': $bytes *= 1024;
        // no break
        case 'm': $bytes *= 1024;
        // no break
        case 'k': $bytes *= 1024;
    }

    return $bytes;
}