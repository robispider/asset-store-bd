<?php

namespace GovStore\Theming\Build;

use InvalidArgumentException;

/**
 * Colour parsing, OKLCH conversion and derivation. Derivations are resolved at
 * build time so the emitted CSS holds literal colours that can be contrast-checked.
 */
final class ColorMath
{
    /** @return array{0: float, 1: float, 2: float, 3: float} sRGB 0..1 + alpha */
    public static function parse(string $color): array
    {
        $color = strtolower(trim($color));
        if (preg_match('/^#([0-9a-f]{3,4})$/', $color, $m)) {
            $hex = '';
            foreach (str_split($m[1]) as $char) {
                $hex .= $char.$char;
            }
            $color = '#'.$hex;
        }
        if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})?$/', $color, $m)) {
            return [hexdec($m[1]) / 255, hexdec($m[2]) / 255, hexdec($m[3]) / 255, isset($m[4]) ? hexdec($m[4]) / 255 : 1.0];
        }
        if (preg_match('/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)(?:[\s,\/]+([\d.]+%?))?\s*\)$/', $color, $m)) {
            $alpha = 1.0;
            if (isset($m[4]) && $m[4] !== '') {
                $alpha = str_ends_with($m[4], '%') ? (float) $m[4] / 100 : (float) $m[4];
            }

            return [(float) $m[1] / 255, (float) $m[2] / 255, (float) $m[3] / 255, $alpha];
        }
        if (in_array($color, ['white', 'black', 'transparent'], true)) {
            return match ($color) {
                'white' => [1.0, 1.0, 1.0, 1.0],
                'black' => [0.0, 0.0, 0.0, 1.0],
                'transparent' => [0.0, 0.0, 0.0, 0.0],
            };
        }

        throw new InvalidArgumentException("Unsupported colour literal [{$color}]");
    }

    public static function isColor(string $value): bool
    {
        try {
            self::parse($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function format(array $rgba): string
    {
        [$r, $g, $b, $a] = $rgba + [3 => 1.0];
        $channels = array_map(fn ($c) => (int) round(max(0, min(1, $c)) * 255), [$r, $g, $b]);
        if ($a < 0.999) {
            return sprintf('rgba(%d, %d, %d, %s)', $channels[0], $channels[1], $channels[2], rtrim(rtrim(number_format(max(0, $a), 3, '.', ''), '0'), '.'));
        }

        return sprintf('#%02X%02X%02X', ...$channels);
    }

    public static function normalize(string $color): string
    {
        return self::format(self::parse($color));
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} L (0..1), C, H (deg), alpha */
    public static function toOklch(string $color): array
    {
        [$r, $g, $b, $a] = self::parse($color);
        [$l, $aa, $bb] = self::linearToOklab(self::toLinear($r), self::toLinear($g), self::toLinear($b));
        $c = sqrt($aa * $aa + $bb * $bb);
        $h = $c < 1e-6 ? 0.0 : fmod(rad2deg(atan2($bb, $aa)) + 360, 360);

        return [$l, $c, $h, $a];
    }

    public static function fromOklch(float $l, float $c, float $h, float $alpha = 1.0): string
    {
        $l = max(0, min(1, $l));
        $c = max(0, $c);
        // Reduce chroma until the colour fits in sRGB (keeps lightness and hue stable).
        $rgb = self::oklchToSrgb($l, $c, $h);
        if (! self::inGamut($rgb)) {
            $lo = 0.0;
            $hi = $c;
            for ($i = 0; $i < 24; $i++) {
                $mid = ($lo + $hi) / 2;
                self::inGamut(self::oklchToSrgb($l, $mid, $h)) ? $lo = $mid : $hi = $mid;
            }
            $rgb = self::oklchToSrgb($l, $lo, $h);
        }

        return self::format([...$rgb, $alpha]);
    }

    /**
     * Apply a `gs.derive` instruction: l / c / h deltas, absolute `lightness` / `chroma`,
     * `alpha`, and `mix: {with, amount}` in OKLab.
     */
    public static function derive(string $color, array $derive): string
    {
        [$l, $c, $h, $a] = self::toOklch($color);
        if (isset($derive['mix'])) {
            $with = self::toOklch((string) $derive['mix']['with']);
            $t = (float) ($derive['mix']['amount'] ?? 0.5);
            [$l1, $a1, $b1] = [$l, $c * cos(deg2rad($h)), $c * sin(deg2rad($h))];
            [$l2, $a2, $b2] = [$with[0], $with[1] * cos(deg2rad($with[2])), $with[1] * sin(deg2rad($with[2]))];
            $l = $l1 + ($l2 - $l1) * $t;
            $ma = $a1 + ($a2 - $a1) * $t;
            $mb = $b1 + ($b2 - $b1) * $t;
            $c = sqrt($ma * $ma + $mb * $mb);
            $h = $c < 1e-6 ? $h : fmod(rad2deg(atan2($mb, $ma)) + 360, 360);
            $a = $a + ($with[3] - $a) * $t;
        }
        if (isset($derive['lightness'])) {
            $l = (float) $derive['lightness'];
        }
        if (isset($derive['chroma'])) {
            $c = (float) $derive['chroma'];
        }
        $l += (float) ($derive['l'] ?? 0);
        $c = $c * (float) ($derive['c_scale'] ?? 1) + (float) ($derive['c'] ?? 0);
        $h = fmod($h + (float) ($derive['h'] ?? 0) + 360, 360);
        if (isset($derive['alpha'])) {
            $a = (float) $derive['alpha'];
        }

        return self::fromOklch($l, $c, $h, $a);
    }

    public static function lightness(string $color): float
    {
        return self::toOklch($color)[0];
    }

    /** WCAG 2.x relative luminance; translucent colours are composited over $background. */
    public static function luminance(string $color, string $background = '#FFFFFF'): float
    {
        [$r, $g, $b, $a] = self::parse($color);
        if ($a < 1) {
            [$br, $bg, $bb] = self::parse($background);
            [$r, $g, $b] = [$r * $a + $br * (1 - $a), $g * $a + $bg * (1 - $a), $b * $a + $bb * (1 - $a)];
        }

        return 0.2126 * self::toLinear($r) + 0.7152 * self::toLinear($g) + 0.0722 * self::toLinear($b);
    }

    public static function contrast(string $foreground, string $background): float
    {
        $bgLum = self::luminance($background);
        $fgLum = self::luminance($foreground, self::format(array_slice(self::parse($background), 0, 3)));
        [$hi, $lo] = $fgLum > $bgLum ? [$fgLum, $bgLum] : [$bgLum, $fgLum];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    private static function toLinear(float $c): float
    {
        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }

    private static function fromLinear(float $c): float
    {
        return $c <= 0.0031308 ? 12.92 * $c : 1.055 * (max(0, $c) ** (1 / 2.4)) - 0.055;
    }

    private static function linearToOklab(float $r, float $g, float $b): array
    {
        $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
        $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
        $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;
        [$l, $m, $s] = [self::cbrt($l), self::cbrt($m), self::cbrt($s)];

        return [
            0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s,
            1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s,
            0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s,
        ];
    }

    private static function oklchToSrgb(float $l, float $c, float $h): array
    {
        $a = $c * cos(deg2rad($h));
        $b = $c * sin(deg2rad($h));
        $l_ = $l + 0.3963377774 * $a + 0.2158037573 * $b;
        $m_ = $l - 0.1055613458 * $a - 0.0638541728 * $b;
        $s_ = $l - 0.0894841775 * $a - 1.2914855480 * $b;
        [$l3, $m3, $s3] = [$l_ ** 3, $m_ ** 3, $s_ ** 3];
        $r = 4.0767416621 * $l3 - 3.3077115913 * $m3 + 0.2309699292 * $s3;
        $g = -1.2684380046 * $l3 + 2.6097574011 * $m3 - 0.3413193965 * $s3;
        $bl = -0.0041960863 * $l3 - 0.7034186147 * $m3 + 1.7076147010 * $s3;

        return [self::fromLinear($r), self::fromLinear($g), self::fromLinear($bl)];
    }

    private static function inGamut(array $rgb): bool
    {
        foreach ($rgb as $channel) {
            if ($channel < -0.0005 || $channel > 1.0005) {
                return false;
            }
        }

        return true;
    }

    private static function cbrt(float $x): float
    {
        return $x < 0 ? -((-$x) ** (1 / 3)) : $x ** (1 / 3);
    }
}
