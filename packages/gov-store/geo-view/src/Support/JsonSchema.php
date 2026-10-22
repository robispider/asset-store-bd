<?php

namespace GovStore\GeoView\Support;

/**
 * Minimal JSON Schema (2020-12 subset) validator for the gs-geo contracts, so neither PHP nor node needs a
 * dependency. Supports type, const, enum, required, properties, additionalProperties, propertyNames, items,
 * minItems, maxItems, minimum, maximum, pattern, oneOf, anyOf and local $ref. Mirrors tests/js/geo/schema.mjs.
 */
final class JsonSchema
{
    private static array $loaded = [];

    public static function contract(string $name): array
    {
        return self::$loaded[$name] ??= json_decode((string) file_get_contents(dirname(__DIR__, 2).'/contracts/'.$name), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return string[] errors, empty when valid. Values are compared after a JSON round trip (objects as arrays). */
    public static function validate(array $schema, mixed $value): array
    {
        $value = json_decode(json_encode($value, JSON_PRESERVE_ZERO_FRACTION), false);

        return self::check($schema, $value, $schema, '$');
    }

    /** Removes properties the schema does not allow (production: unknown config keys are dropped, plan §5.1). */
    public static function prune(array $schema, mixed $value, ?array $root = null): mixed
    {
        $root ??= $schema;
        if (isset($schema['$ref'])) {
            $schema = self::ref($root, $schema['$ref']) ?? [];
        }
        if (! is_array($value) || array_is_list($value)) {
            return $value;
        }
        $props = $schema['properties'] ?? null;
        foreach ($value as $key => $item) {
            if ($props !== null && array_key_exists($key, $props)) {
                $value[$key] = self::prune($props[$key], $item, $root);
            } elseif (($schema['additionalProperties'] ?? true) === false) {
                unset($value[$key]);
            }
        }

        return $value;
    }

    private static function check(mixed $schema, mixed $value, array $root, string $at): array
    {
        if ($schema === true) {
            return [];
        }
        if ($schema === false) {
            return ["{$at}: not allowed"];
        }
        $errors = [];
        if (isset($schema['$ref'])) {
            $target = self::ref($root, $schema['$ref']);

            return $target === null ? ["{$at}: unresolved {$schema['$ref']}"] : self::check($target, $value, $root, $at);
        }
        if (isset($schema['type'])) {
            $types = (array) $schema['type'];
            $ok = false;
            foreach ($types as $type) {
                $ok = $ok || self::isType($value, $type);
            }
            if (! $ok) {
                return ["{$at}: expected ".implode('|', $types).', got '.self::typeOf($value)];
            }
        }
        if (array_key_exists('const', $schema) && json_encode($schema['const']) !== json_encode($value)) {
            $errors[] = "{$at}: expected ".json_encode($schema['const']);
        }
        if (isset($schema['enum']) && ! in_array(json_encode($value), array_map('json_encode', $schema['enum']), true)) {
            $errors[] = "{$at}: ".json_encode($value).' not in enum';
        }
        if (isset($schema['oneOf'])) {
            $matches = count(array_filter($schema['oneOf'], fn ($s) => self::check($s, $value, $root, $at) === []));
            if ($matches !== 1) {
                $errors[] = "{$at}: matches {$matches} of oneOf";
            }
        }
        if (isset($schema['anyOf']) && ! array_filter($schema['anyOf'], fn ($s) => self::check($s, $value, $root, $at) === [])) {
            $errors[] = "{$at}: matches none of anyOf";
        }
        if (is_int($value) || is_float($value)) {
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                $errors[] = "{$at}: below minimum {$schema['minimum']}";
            }
            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                $errors[] = "{$at}: above maximum {$schema['maximum']}";
            }
        }
        if (is_string($value) && isset($schema['pattern']) && ! preg_match('/'.str_replace('/', '\/', $schema['pattern']).'/u', $value)) {
            $errors[] = "{$at}: does not match {$schema['pattern']}";
        }
        if (is_array($value)) {
            if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
                $errors[] = "{$at}: fewer than {$schema['minItems']} items";
            }
            if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) {
                $errors[] = "{$at}: more than {$schema['maxItems']} items";
            }
            if (isset($schema['items'])) {
                foreach ($value as $i => $item) {
                    array_push($errors, ...self::check($schema['items'], $item, $root, "{$at}[{$i}]"));
                }
            }
        }
        if ($value instanceof \stdClass) {
            $props = get_object_vars($value);
            foreach ($schema['required'] ?? [] as $key) {
                if (! array_key_exists($key, $props)) {
                    $errors[] = "{$at}: missing {$key}";
                }
            }
            foreach ($props as $key => $item) {
                if (isset($schema['propertyNames'])) {
                    array_push($errors, ...self::check($schema['propertyNames'], (string) $key, $root, "{$at}.{$key}(name)"));
                }
                if (isset($schema['properties']) && array_key_exists($key, $schema['properties'])) {
                    array_push($errors, ...self::check($schema['properties'][$key], $item, $root, "{$at}.{$key}"));
                } elseif (array_key_exists('additionalProperties', $schema)) {
                    array_push($errors, ...self::check($schema['additionalProperties'], $item, $root, "{$at}.{$key}"));
                }
            }
        }

        return $errors;
    }

    private static function ref(array $root, string $ref): ?array
    {
        $node = $root;
        foreach (explode('/', ltrim(substr($ref, 1), '/')) as $part) {
            if (! is_array($node) || ! array_key_exists($part, $node)) {
                return null;
            }
            $node = $node[$part];
        }

        return is_array($node) ? $node : null;
    }

    private static function isType(mixed $v, string $type): bool
    {
        return match ($type) {
            'integer' => is_int($v) || (is_float($v) && floor($v) === $v && abs($v) < PHP_INT_MAX),
            'number' => is_int($v) || is_float($v),
            'string' => is_string($v),
            'boolean' => is_bool($v),
            'null' => $v === null,
            'array' => is_array($v),
            'object' => $v instanceof \stdClass || $v === [],   // PHP cannot tell an empty object from an empty list
            default => false,
        };
    }

    private static function typeOf(mixed $v): string
    {
        return match (true) {
            $v === null => 'null', is_bool($v) => 'boolean', is_int($v), is_float($v) => 'number',
            is_string($v) => 'string', is_array($v) => 'array', default => 'object',
        };
    }
}
