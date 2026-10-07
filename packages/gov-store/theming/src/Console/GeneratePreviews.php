<?php

namespace GovStore\Theming\Console;

use GovStore\Theming\Build\ColorMath;
use GovStore\Theming\Build\FontRegistry;
use GovStore\Theming\Themes\Theme;
use GovStore\Theming\Themes\ThemeRepository;
use Illuminate\Console\Command;
use Throwable;

/**
 * Draws 1200×750 schematic picker previews (preview.light.png / preview.dark.png) from a
 * theme's resolved tokens and variants: shell, KPI strip, status tabs and a register table.
 */
class GeneratePreviews extends Command
{
    protected $signature = 'gs-theme:previews {key? : One theme only} {--force : Overwrite existing previews}';

    protected $description = 'Generate schematic preview images for the theme picker from resolved tokens';

    private const W = 1200;

    private const H = 750;

    public function handle(ThemeRepository $themes, FontRegistry $fonts): int
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->error('The GD extension is required.');

            return self::FAILURE;
        }
        $targets = $this->argument('key') ? array_filter([$themes->find($this->argument('key'))]) : $themes->all();
        if ($targets === []) {
            $this->error('No such theme.');

            return self::FAILURE;
        }
        foreach ($targets as $theme) {
            foreach (['light', 'dark'] as $mode) {
                $file = $theme->path.DIRECTORY_SEPARATOR."preview.{$mode}.png";
                if (is_file($file) && ! $this->option('force')) {
                    $this->line("  keep {$theme->key}/preview.{$mode}.png");

                    continue;
                }
                try {
                    $this->draw($theme, $theme->tokens($mode, $fonts), $file, $mode);
                    $this->info("  wrote {$theme->key}/preview.{$mode}.png");
                } catch (Throwable $e) {
                    $this->error("  {$theme->key}/{$mode}: ".$e->getMessage());
                }
            }
        }

        return self::SUCCESS;
    }

    private function draw(Theme $theme, array $t, string $file, string $mode): void
    {
        $img = imagecreatetruecolor(self::W, self::H);
        $c = function (string $path, string $fallback = '#888888') use ($img, $t) {
            $value = $t[$path] ?? $fallback;
            try {
                [$r, $g, $b, $a] = ColorMath::parse($value);
                if ($a < 1) {
                    [$sr, $sg, $sb] = ColorMath::parse($t['color.surface'] ?? '#FFFFFF');
                    [$r, $g, $b] = [$r * $a + $sr * (1 - $a), $g * $a + $sg * (1 - $a), $b * $a + $sb * (1 - $a)];
                }
            } catch (Throwable) {
                [$r, $g, $b] = [0.5, 0.5, 0.5];
            }

            return imagecolorallocate($img, (int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
        };
        $v = $theme->variants;
        $radius = ['none' => 0, 'sm' => 2, 'md' => 6, 'lg' => 10][$v['radius'] ?? 'sm'] ?? 3;
        $row = ['compact' => 30, 'regular' => 36, 'comfortable' => 44][$v['density'] ?? 'regular'] ?? 36;

        imagefill($img, 0, 0, $c('color.bg'));

        // Header
        [$hbg, $hfg] = match ($v['header'] ?? 'brand') {
            'neutral' => ['color.surface', 'color.text'],
            default => ['header.bg', 'header.fg'],
        };
        imagefilledrectangle($img, 0, 0, self::W, 56, $c($hbg));
        if (($v['header'] ?? '') === 'neutral') {
            imageline($img, 0, 56, self::W, 56, $c('color.border'));
        }
        imagestring($img, 5, 24, 20, 'National Asset Register', $c($hfg));
        imagestring($img, 3, self::W - 210, 22, $theme->label('en-US').' - '.$mode, $c($hfg));

        // Sidebar
        [$sbg, $sfg] = match ($v['sidebar'] ?? 'dark') {
            'light' => ['color.surface', 'color.text-muted'],
            'brand' => ['color.primary', 'color.on-primary'],
            default => ['sidebar.bg', 'sidebar.fg'],
        };
        imagefilledrectangle($img, 0, 57, 230, self::H, $c($sbg));
        if (($v['sidebar'] ?? '') === 'light') {
            imageline($img, 230, 57, 230, self::H, $c('color.border'));
        }
        $items = ['Dashboard', 'Assets', 'Store Documents', 'Goods Receipt', 'Stock Register', 'Requests', 'Reports', 'Settings'];
        foreach ($items as $i => $label) {
            $y = 80 + $i * 40;
            if ($i === 2) {
                imagefilledrectangle($img, 0, $y - 8, 229, $y + 24, $c(($v['sidebar'] ?? '') === 'light' ? 'sidebar.active-bg' : 'sidebar.active-bg'));
                imagefilledrectangle($img, 0, $y - 8, 4, $y + 24, $c('sidebar.active-marker'));
            }
            $x = ($v['nav_icons'] ?? 'shown') === 'shown' ? 50 : 24;
            if ($x === 50) {
                $this->box($img, 22, $y + 1, 36, $y + 15, 3, $c($i === 2 ? 'sidebar.active-fg' : $sfg));
            }
            imagestring($img, 4, $x, $y, $label, $c($i === 2 ? 'sidebar.active-fg' : $sfg));
        }

        // Page title
        $x0 = 260;
        imagestring($img, 5, $x0, 78, 'Store Documents Hub', $c('color.text'));
        imagestring($img, 3, $x0, 98, 'Goods receipts, issues and transfers for Dhaka District Store', $c('color.text-muted'));
        $this->box($img, self::W - 170, 76, self::W - 30, 108, $radius, $c('color.primary'));
        imagestring($img, 4, self::W - 152, 84, '+ New receipt', $c('color.on-primary'));

        // KPI strip
        $flat = ($v['surfaces'] ?? 'card') === 'flat';
        $kpis = [['Assets in service', '12,486'], ['Pending receipts', '37'], ['Stock value', '4.82 Cr'], ['Overdue audits', '5']];
        foreach ($kpis as $i => [$label, $value]) {
            $x = $x0 + $i * 228;
            if ($flat) {
                imageline($img, $x, 130, $x, 210, $c('color.border'));
            } else {
                $this->box($img, $x, 130, $x + 212, 210, $radius, $c('color.border'));
                $this->box($img, $x + 1, 131, $x + 211, 209, $radius, $c('color.surface'));
            }
            imagestring($img, 3, $x + 16, 146, $label, $c('color.text-muted'));
            imagestring($img, 5, $x + 16, 172, $value, $c('color.text'));
        }

        // Table surface
        $top = 236;
        if (! $flat) {
            $this->box($img, $x0, $top, self::W - 30, self::H - 30, $radius, $c('color.border'));
            $this->box($img, $x0 + 1, $top + 1, self::W - 31, self::H - 31, $radius, $c('color.surface'));
        }
        // Status tabs
        foreach (['All 128', 'Draft 12', 'Ready 7', 'Posted 109'] as $i => $tab) {
            $tx = $x0 + 16 + $i * 110;
            imagestring($img, 4, $tx, $top + 16, $tab, $c($i === 0 ? 'color.primary' : 'color.text-muted'));
            if ($i === 0) {
                imagefilledrectangle($img, $tx, $top + 38, $tx + 70, $top + 40, $c('color.primary'));
            }
        }
        imageline($img, $x0 + 1, $top + 41, self::W - 31, $top + 41, $c('color.border'));

        // Table head + rows
        $table = $v['table'] ?? 'grid';
        $headY = $top + 52;
        imagefilledrectangle($img, $x0 + 1, $headY, self::W - 31, $headY + $row, $c('table.head-bg'));
        $cols = [['Document', 16], ['Type', 190], ['Office', 340], ['Date', 540], ['Value (BDT)', 650], ['Status', 790]];
        foreach ($cols as [$label, $offset]) {
            imagestring($img, 3, $x0 + $offset, $headY + (int) ($row / 2) - 6, $table === 'ledger' ? strtoupper($label) : $label, $c('table.head-fg'));
        }
        $statuses = ['posted', 'ready', 'draft', 'approved', 'cancelled', 'posted', 'ready', 'draft', 'posted', 'rejected', 'posted'];
        $y = $headY + $row;
        foreach ($statuses as $i => $status) {
            if ($y + $row > self::H - 40) {
                break;
            }
            if ($table === 'grid' && $i % 2 === 1) {
                imagefilledrectangle($img, $x0 + 1, $y, self::W - 31, $y + $row, $c('table.row-alt'));
            }
            imageline($img, $x0 + 1, $y, self::W - 31, $y, $c('table.border'));
            if ($table === 'grid') {
                foreach ($cols as [, $offset]) {
                    if ($offset > 16) {
                        imageline($img, $x0 + $offset - 10, $y, $x0 + $offset - 10, $y + $row, $c('table.border'));
                    }
                }
            }
            $mid = $y + (int) ($row / 2) - 6;
            imagestring($img, 3, $x0 + 16, $mid, sprintf('GRN-2026-%05d', 412 + $i), $c('color.link'));
            imagestring($img, 3, $x0 + 190, $mid, 'Goods Receipt', $c('color.text'));
            imagestring($img, 3, $x0 + 340, $mid, 'Dhaka District Store', $c('color.text'));
            imagestring($img, 3, $x0 + 540, $mid, '2026-10-0'.(1 + $i % 9), $c('color.text'));
            imagestring($img, 3, $x0 + 650, $mid, number_format(18250 + $i * 7310), $c('color.text'));
            $this->badge($img, $x0 + 790, $y + (int) ($row / 2) - 10, $status, 'color.doc-'.$status, $v['status_style'] ?? 'tinted', $radius, $c, $t);
            $y += $row;
        }
        if ($table === 'ledger') {
            imageline($img, $x0 + 1, $y + 2, self::W - 31, $y + 2, $c('table.total-rule'));
            imageline($img, $x0 + 1, $y + 5, self::W - 31, $y + 5, $c('table.total-rule'));
        }

        imagepng($img, $file, 9);
        imagedestroy($img);
    }

    private function badge($img, int $x, int $y, string $label, string $tone, string $style, int $radius, callable $c, array $t): void
    {
        $w = 96;
        $h = 20;
        if ($style === 'chip') {
            $this->box($img, $x, $y, $x + $w, $y + $h, min($radius, 3), $c($tone));
            imagestring($img, 2, $x + 8, $y + 4, strtoupper($label), $c('color.on-primary', '#FFFFFF'));
        } elseif ($style === 'text') {
            imagefilledellipse($img, $x + 6, $y + 10, 8, 8, $c($tone));
            imagestring($img, 3, $x + 16, $y + 3, ucfirst($label), $c($tone));
        } else {
            $this->box($img, $x, $y, $x + $w, $y + $h, 10, $c($tone));
            $tint = ColorMath::derive($t[$tone] ?? '#888888', ['mix' => ['with' => $t['color.surface'] ?? '#FFFFFF', 'amount' => 0.86]]);
            [$r, $g, $b] = ColorMath::parse($tint);
            $this->box($img, $x + 1, $y + 1, $x + $w - 1, $y + $h - 1, 9, imagecolorallocate($img, (int) ($r * 255), (int) ($g * 255), (int) ($b * 255)));
            imagestring($img, 3, $x + 12, $y + 3, ucfirst($label), $c($tone));
        }
    }

    private function box($img, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        if ($r <= 0) {
            imagefilledrectangle($img, $x1, $y1, $x2, $y2, $color);

            return;
        }
        $r = min($r, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2));
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $color);
        }
    }
}
