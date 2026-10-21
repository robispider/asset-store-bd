{{--
    A structured code shown as labelled segments (e.g. a Govt. Tag: group, subgroup, item, month,
    location, division, sequence). segments: [['value' => '09', 'label' => 'Group', 'tone' => 'primary', 'pending' => false], …]
    tone: primary · info · success · warning · muted. A `pending` segment (not allocated yet) has a dashed
    border and reads "allocated when posted". size="sm" hides the labels for tables.
--}}
@props(['segments' => [], 'size' => 'md', 'label' => null])
<span {{ $attributes->class(['gs-segs', 'gs-segs--sm' => $size === 'sm']) }} role="group" @if ($label) aria-label="{{ $label }}" @endif>
    @foreach ($segments as $segment)
        <span @class(['gs-seg', 'gs-seg--pending' => $segment['pending'] ?? false]) data-tone="{{ $segment['tone'] ?? 'muted' }}">
            <span class="gs-seg__v">{{ $segment['value'] }}</span>
            @if (! empty($segment['label']))<span class="gs-seg__l" @if ($size === 'sm') aria-hidden="true" @endif>{{ $segment['label'] }}</span>@endif
            @if ($segment['pending'] ?? false)<span class="gs-sr-only">{{ __('gs-theme::appearance.kit.segment_pending') }}</span>@endif
        </span>
    @endforeach
</span>
