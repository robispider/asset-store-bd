{{-- One tree node (view A): where a policy can be attached, its policy and how much it changes. --}}
<a class="rst-node rst-gs-plain" href="{{ $href }}" @if ($current) aria-current="true" @endif data-rst-filter-text="{{ $n['name'] }} {{ $n['policy']['name'] ?? '' }}">
    <i class="fas {{ $icon }}" aria-hidden="true"></i>
    <span class="rst-node__text">
        <span class="rst-node__name">{{ $n['name'] }}</span>
        @if ($n['policy'])
            <span class="rst-node__pol">{{ $n['policy']['name'] }}</span>
        @else
            <span class="rst-node__pol rst-node__pol--none">{{ __('storeops::studio.no_own_policy') }}</span>
        @endif
    </span>
    @if ($n['diffs'])
        <span class="rst-badge">{{ trans_choice('storeops::studio.changes_n', count($n['diffs']), ['count' => count($n['diffs'])]) }}</span>
    @elseif ($n['policy'])
        <span class="rst-badge">{{ __('storeops::studio.repeats_only') }}</span>
    @endif
</a>
