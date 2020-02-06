<?php

namespace Amp\Postgres\Internal;

use function Amp\Postgres\cast;

const STATEMENT_PARAM_REGEX = <<<'REGEX'
~(["'`])(?:\\(?:\\|\1)|(?!\1).)*+\1(*SKIP)(*FAIL)|(\$(\d+)|\?)|(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)~ms
REGEX;

/**
 * @param string $sql SQL statement with named and unnamed placeholders.
 * @param array $names [Output] Array of parameter positions mapped to names and/or indexed locations.
 *
 * @return string SQL statement with Postgres-style placeholders
 */
function parseNamedParams(string $sql, ?array &$names): string
{
    $names = [];
    return \preg_replace_callback(STATEMENT_PARAM_REGEX, function (array $matches) use (&$names): string {
        static $index = 0, $unnamed = 0, $numbered = 1;

        if (isset($matches[4])) {
            $names[$index] = $matches[4];
        } elseif ($matches[2] === '?') {
            $names[$index] = $unnamed++;
        } else {
            $position = (int) $matches[3];
            if ($numbered++ !== $position) {
                throw new \Error("Numbered placeholders must be sequential starting at 1");
            }

            $names[$index] = $unnamed++;
        }

        return '$' . ++$index;
    }, $sql);
}

/**
 * @param mixed[] $params User-provided array of statement parameters.
 * @param mixed[] $names Array generated by parseNamedParams.
 *
 * @return mixed[]
 *
 * @throws \Error If the $param array does not contain a key corresponding to a named parameter.
 */
function replaceNamedParams(array $params, array $names): array
{
    $values = [];
    foreach ($names as $index => $name) {
        if (!\array_key_exists($name, $params)) {
            if (\is_int($name)) {
                $message = \sprintf("Value for unnamed parameter at position %s missing", $name);
            } else {
                $message = \sprintf("Value for named parameter '%s' missing", $name);
            }

            throw new \Error($message);
        }

        $values[$index] = cast($params[$name]);
    }

    return $values;
}
