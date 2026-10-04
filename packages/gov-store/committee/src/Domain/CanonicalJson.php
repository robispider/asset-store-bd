<?php

namespace GovStore\Committee\Domain;

final class CanonicalJson
{
    public static function encode(mixed $value): string
    {
        return json_encode(self::normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        } elseif (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            return array_map(self::normalize(...), $value);
        }
        if (is_string($value) && class_exists(\Normalizer::class)) {
            return \Normalizer::normalize($value, \Normalizer::FORM_C);
        }
        return $value;
    }
}

