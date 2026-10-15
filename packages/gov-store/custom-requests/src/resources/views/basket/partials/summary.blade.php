{{--
    Checkout side panel: the draft at a glance, how it will be sent, and who is sending it for which office.
    Expects $draft and $presenter.
--}}
@php
    $r = 'requestlabels::requests.';
    $groups = array_values($draft['groups']);
@endphp
<section class="cr-summary" aria-labelledby="cr-summary-title">
    <h2 class="cr-summary__title" id="cr-summary-title">{{ __($r.'summary_title') }}</h2>
    <ul class="cr-summary__lines">
        @foreach ($draft['lines'] as $line)
            <li><span>{{ $line->name }}</span> <strong class="cr-num">×{{ $line->qty }}</strong></li>
        @endforeach
    </ul>
    @if ($groups)
        <p class="cr-summary__route"><i class="fas fa-route" aria-hidden="true"></i>
            <span>@if (count($groups) > 1){{ trans_choice($r.'drawer_sent_as', count($groups), ['count' => count($groups)]) }}: {{ collect($groups)->map(fn ($g) => $presenter->ruleBadge($g['policy']))->implode(' + ') }}@else{{ $presenter->routeSentence($groups[0]['policy'], $draft['office'], $draft['requester']) }}@endif</span>
        </p>
    @endif
    <ul class="cr-trust">
        <li><i class="fas fa-user" aria-hidden="true"></i><span>{!! __($r.'trust_sender', ['name' => '<strong>'.e($presenter->name($draft['requester'])).'</strong>', 'office' => e($draft['officeName'])]) !!}</span></li>
        <li><i class="fas fa-shield-halved" aria-hidden="true"></i><span>{{ __($r.'trust_visibility') }}</span></li>
        <li><i class="fas fa-check-double" aria-hidden="true"></i><span>{{ __($r.'trust_once') }}</span></li>
    </ul>
</section>
