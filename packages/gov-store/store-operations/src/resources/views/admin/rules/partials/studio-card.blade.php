{{-- One family-tree card (view C): a level, its policy and only what that policy changes. --}}
<a href="{{ $href }}" @class(['rst-card', 'rst-gs-plain', 'rst-card--none' => ! $n['policy'], 'rst-card--path' => $path]) @if ($current) aria-current="true" @endif>
    <span class="rst-card__lv">{{ $level }}</span>
    <span class="rst-card__name">{{ $n['name'] }}</span>
    @if ($n['policy'])
        <span class="rst-card__pol">{{ __('storeops::studio.policy_name', ['policy' => $n['policy']['name']]) }}</span>
        @if ($n['diffs'])
            <span class="rst-diffs">
                @foreach ($n['diffs'] as $diff)
                    <span class="rst-diff rst-diff--{{ $diff['kind'] }}">
                        <span class="rst-diff__k" aria-hidden="true">{{ ['add' => '+', 'change' => '~', 'off' => '✕'][$diff['kind']] }}</span>
                        <span>{{ __('storeops::studio.diff_'.$diff['kind'], ['rule' => $diff['name'], 'from' => $diff['from'], 'to' => $diff['to']]) }}</span>
                    </span>
                @endforeach
            </span>
        @else
            <span class="rst-card__same">{{ __('storeops::studio.changes_nothing') }}</span>
        @endif
    @else
        <span class="rst-card__pol">{{ __('storeops::studio.no_own_policy') }}</span>
        <span class="rst-card__same">{{ __('storeops::studio.inherits_everything') }}</span>
    @endif
</a>
