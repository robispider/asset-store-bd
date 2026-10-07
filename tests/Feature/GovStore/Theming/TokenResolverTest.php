<?php

namespace Tests\Feature\GovStore\Theming;

use GovStore\Theming\Build\ColorMath;
use GovStore\Theming\Build\TokenResolver;
use PHPUnit\Framework\TestCase;

class TokenResolverTest extends TestCase
{
    public function test_color_math_round_trips_through_oklch(): void
    {
        foreach (['#14532D', '#2563EB', '#FFFFFF', '#000000', '#B68A3A'] as $hex) {
            [$l, $c, $h] = ColorMath::toOklch($hex);
            $this->assertSame($hex, ColorMath::fromOklch($l, $c, $h));
        }
        $this->assertEqualsWithDelta(21.0, ColorMath::contrast('#000000', '#FFFFFF'), 0.01);
        $this->assertEqualsWithDelta(1.0, ColorMath::contrast('#777777', '#777777'), 0.01);
        $this->assertSame('rgba(0, 0, 0, 0.5)', ColorMath::normalize('rgba(0,0,0,.5)'));
        $this->assertSame('#AABBCC', ColorMath::normalize('#abc'));
    }

    public function test_references_resolve_including_embedded_and_group_types(): void
    {
        $resolver = new TokenResolver([
            'seed' => ['$type' => 'color', 'primary' => ['$value' => '#14532d']],
            'color' => ['$type' => 'color', 'primary' => ['$value' => '{seed.primary}'], 'link' => ['$value' => '{color.primary}']],
            'border' => ['rule' => ['$value' => '1px solid {color.primary}']],
        ]);
        $tokens = $resolver->resolveAll();
        $this->assertSame('#14532D', $tokens['color.primary']);
        $this->assertSame('#14532D', $tokens['color.link']);
        $this->assertSame('1px solid #14532D', $tokens['border.rule']);
        $this->assertSame([], $resolver->errors());
    }

    public function test_oklch_derivations_lighten_darken_mix_and_alpha(): void
    {
        $tokens = (new TokenResolver([
            'c' => [
                '$type' => 'color',
                'base' => ['$value' => '#2563EB'],
                'darker' => ['$value' => '{c.base}', '$extensions' => ['gs.derive' => ['l' => -0.1]]],
                'lighter' => ['$value' => '{c.base}', '$extensions' => ['gs.derive' => ['l' => 0.1]]],
                'tint' => ['$value' => '{c.base}', '$extensions' => ['gs.derive' => ['mix' => ['with' => '#FFFFFF', 'amount' => 0.9]]]],
                'translucent' => ['$value' => '{c.base}', '$extensions' => ['gs.derive' => ['alpha' => 0.5]]],
            ],
        ]))->resolveAll();
        $base = ColorMath::lightness($tokens['c.base']);
        $this->assertEqualsWithDelta($base - 0.1, ColorMath::lightness($tokens['c.darker']), 0.01);
        $this->assertEqualsWithDelta($base + 0.1, ColorMath::lightness($tokens['c.lighter']), 0.01);
        $this->assertGreaterThan(0.9, ColorMath::lightness($tokens['c.tint']));
        $this->assertStringStartsWith('rgba(', $tokens['c.translucent']);
    }

    public function test_unknown_references_and_cycles_are_reported(): void
    {
        $resolver = new TokenResolver([
            'a' => ['$value' => '{b}'],
            'b' => ['$value' => '{a}'],
            'c' => ['$value' => '{missing.token}'],
        ]);
        $tokens = $resolver->resolveAll();
        $this->assertArrayNotHasKey('c', $tokens);
        $errors = implode("\n", $resolver->errors());
        $this->assertStringContainsString('Reference cycle', $errors);
        $this->assertStringContainsString('Unknown token reference {missing.token}', $errors);
    }
}
