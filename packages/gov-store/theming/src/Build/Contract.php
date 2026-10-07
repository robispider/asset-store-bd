<?php

namespace GovStore\Theming\Build;

/**
 * The semantic token contract (§6.4). Every theme must resolve every token in both modes.
 * Token path `color.primary-hover` is emitted as `--gs-color-primary-hover`.
 */
final class Contract
{
    public const GROUPS = [
        'Ground' => ['color.bg', 'color.surface', 'color.surface-raised', 'color.surface-sunken', 'color.border', 'color.border-strong', 'color.overlay'],
        'Text' => ['color.text', 'color.text-muted', 'color.text-subtle', 'color.text-inverse', 'color.link', 'color.link-hover', 'color.focus-ring'],
        'Brand' => ['color.primary', 'color.primary-hover', 'color.primary-active', 'color.primary-subtle', 'color.on-primary', 'color.accent', 'color.on-accent'],
        'Feedback' => [
            'color.success', 'color.success-subtle', 'color.on-success',
            'color.warning', 'color.warning-subtle', 'color.on-warning',
            'color.danger', 'color.danger-subtle', 'color.on-danger',
            'color.info', 'color.info-subtle', 'color.on-info',
        ],
        'Asset status' => ['color.status-deployed', 'color.status-ready', 'color.status-pending', 'color.status-undeployable', 'color.status-archived'],
        'Document status' => ['color.doc-draft', 'color.doc-ready', 'color.doc-posted', 'color.doc-cancelled', 'color.doc-approved', 'color.doc-rejected'],
        'Shell' => [
            'header.bg', 'header.fg', 'header.fg-muted',
            'sidebar.bg', 'sidebar.fg', 'sidebar.fg-muted', 'sidebar.section-fg', 'sidebar.active-bg', 'sidebar.active-fg', 'sidebar.active-marker',
            'footer.bg', 'footer.fg',
        ],
        'Table' => ['table.head-bg', 'table.head-fg', 'table.row-alt', 'table.row-hover', 'table.row-selected', 'table.border', 'table.total-rule'],
        'Charts' => ['chart.1', 'chart.2', 'chart.3', 'chart.4', 'chart.5', 'chart.6', 'chart.7', 'chart.8', 'chart.9', 'chart.10', 'chart.grid', 'chart.label'],
        'Typography' => ['font.sans', 'font.display', 'font.mono', 'font.bengali', 'font.size-base', 'font.size-sm', 'font.line-height-base', 'font.heading-weight'],
        'Shape' => ['radius.sm', 'radius.md', 'radius.lg', 'border.width'],
        'Density' => ['size.row-h', 'size.control-h', 'space.1', 'space.2', 'space.3', 'space.4', 'space.5', 'space.6'],
        'Elevation' => ['shadow.1', 'shadow.2'],
    ];

    /** [foreground, background, minimum ratio] checked in both modes (WCAG 2.2 AA). */
    public const CONTRAST_PAIRS = [
        ['color.text', 'color.surface', 4.5],
        ['color.text', 'color.bg', 4.5],
        ['color.text-muted', 'color.surface', 4.5],
        ['color.on-primary', 'color.primary', 4.5],
        ['color.on-accent', 'color.accent', 4.5],
        ['header.fg', 'header.bg', 4.5],
        ['sidebar.fg', 'sidebar.bg', 4.5],
        ['sidebar.active-fg', 'sidebar.active-bg', 4.5],
        ['color.on-success', 'color.success', 4.5],
        ['color.on-warning', 'color.warning', 4.5],
        ['color.on-danger', 'color.danger', 4.5],
        ['color.on-info', 'color.info', 4.5],
        ['color.link', 'color.surface', 4.5],
        ['color.border-strong', 'color.surface', 3.0],
        ['color.focus-ring', 'color.surface', 3.0],
    ];

    /** Status families whose members must differ by more than hue (ΔL ≥ 0.08 or ΔC large enough). */
    public const DISTINGUISHABLE = [
        ['color.status-deployed', 'color.status-ready', 'color.status-pending', 'color.status-undeployable', 'color.status-archived'],
        ['color.doc-draft', 'color.doc-ready', 'color.doc-posted', 'color.doc-cancelled'],
    ];

    public const SURFACE_TOKENS = ['color.bg', 'color.surface'];

    public static function tokens(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    public static function cssVar(string $path): string
    {
        return '--gs-'.str_replace('.', '-', $path);
    }
}
