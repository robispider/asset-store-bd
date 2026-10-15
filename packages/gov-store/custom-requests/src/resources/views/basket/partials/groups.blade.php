{{--
    The review step's list of requests this draft becomes, each with its items and who approves it.
    Expects $draft and $presenter.
--}}
@php
    $r = 'requestlabels::requests.';
    $groups = array_values($draft['groups']);
    $lines = $draft['lines']->keyBy('id');
@endphp
<div class="cr-doc__groups">
    @foreach ($groups as $i => $group)
        <div class="cr-doc__group">
            <div class="cr-doc__group-head">
                <strong>{{ count($groups) > 1 ? __($r.'review_request_of', ['n' => $i + 1, 'count' => count($groups)]) : __($r.'review_items') }}</strong>
                <span class="cr-pill cr-pill--rule {{ $group['policy'] === 'AUTO_APPROVE' ? 'cr-pill--auto' : '' }}">{{ $presenter->ruleBadge($group['policy']) }}</span>
            </div>
            <ul class="cr-doc__items">
                @foreach ($group['lines'] as $item)
                    @php
                        $line = $lines->get($item->id);
                    @endphp
                    <li><span class="cr-num">{{ $item->requested_qty }}</span> × {{ $line?->name ?? $presenter->itemName($item->requested_type, $item->requested_id) }}
                        @if ($line && $line->short && $line->free !== null)<span class="cr-warn">· {{ __($r.'review_only_free', ['count' => $line->free]) }}</span>@endif
                    </li>
                @endforeach
            </ul>
            <span class="cr-meta"><i class="fas fa-route" aria-hidden="true"></i> {{ $presenter->routeSentence($group['policy'], $draft['office'], $draft['requester']) }}</span>
        </div>
    @endforeach
</div>
