{{--
    The draft panel beside Request items, and its bar on phones. Always current: every add, change and
    removal returns this partial again (BasketController::fragments). Shows how the draft will be sent
    before anything is sent. Expects $draft (DraftView::make) and $presenter.
--}}
@php
    $r = 'requestlabels::requests.';
    $groups = array_values($draft['groups']);
@endphp
<section class="cr-drawer" aria-labelledby="cr-drawer-title">
    <header class="cr-drawer__head">
        <h2 class="cr-drawer__title" id="cr-drawer-title">{{ __($r.'draft_title') }}</h2>
        <span class="cr-meta" data-cr-draft-count-label>{{ trans_choice($r.'draft_items_count_zero', $draft['count'], ['count' => $draft['count']]) }}</span>
    </header>
    @if ($draft['count'] === 0)
        <div class="cr-drawer__empty" data-cr-draft-empty>
            <i class="fas fa-file-pen" aria-hidden="true"></i>
            <strong>{{ __($r.'draft_empty_title') }}</strong>
            <span>{{ __($r.'drawer_empty_body') }}</span>
        </div>
    @else
        <ul class="cr-drawer__lines">
            @foreach ($draft['lines'] as $line)
                <li class="cr-dline" data-cr-dline="{{ $line->key }}">
                    <div class="cr-dline__top">
                        <strong class="cr-dline__name">{{ $line->name }}</strong>
                        <form method="POST" action="{{ route('gov.requests.basket.remove', $line->id) }}" class="cr-inline-form" data-cr-remove data-cr-nojs>
                            @csrf
                            <input type="hidden" name="context" value="catalog">
                            <button type="submit" class="cr-iconbtn" aria-label="{{ __($r.'draft_remove_label', ['item' => $line->name]) }}"><i class="fas fa-trash-can" aria-hidden="true"></i></button>
                        </form>
                    </div>
                    <div class="cr-dline__meta">
                        @if ($line->policy)
                            <span class="cr-pill cr-pill--rule {{ $line->policy === 'AUTO_APPROVE' ? 'cr-pill--auto' : '' }}">{{ $presenter->ruleBadge($line->policy) }}</span>
                        @else
                            <span class="cr-pill cr-pill--none">{{ __($r.'draft_none_free') }}</span>
                        @endif
                        @include('govstore::basket.partials.stepper', ['line' => $line, 'context' => 'catalog'])
                    </div>
                    @if ($line->short && $line->free !== null)
                        <p class="cr-dline__warn"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> {{ __($r.'drawer_short', ['count' => $line->free]) }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($groups)
            <div class="cr-drawer__route">
                <strong><i class="fas fa-route" aria-hidden="true"></i> {{ trans_choice($r.'drawer_sent_as', count($groups), ['count' => count($groups)]) }}</strong>
                @if (count($groups) > 1)
                    <ol class="cr-drawer__groups">
                        @foreach ($groups as $group)
                            <li>
                                <strong>{{ $group['lines']->map(fn ($l) => $presenter->itemName($l->requested_type, $l->requested_id))->implode(', ') }}</strong>
                                <span class="cr-meta">{{ $presenter->routeSentence($group['policy'], $draft['office'], $draft['requester']) }}</span>
                            </li>
                        @endforeach
                    </ol>
                    <span class="cr-meta">{{ __($r.'drawer_split_why') }}</span>
                @else
                    <span class="cr-meta">{{ $presenter->routeSentence($groups[0]['policy'], $draft['office'], $draft['requester']) }}</span>
                @endif
            </div>
        @endif
        <footer class="cr-drawer__foot">
            <x-gs::button :href="route('gov.requests.basket.index')" icon="fa-arrow-right" class="cr-block" data-cr-review>{{ __($r.'draft_review') }}</x-gs::button>
            <span class="cr-meta"><i class="fas fa-floppy-disk" aria-hidden="true"></i> {{ __($r.'drawer_saved', ['date' => $presenter->date($draft['basket']?->expires_at)]) }}</span>
        </footer>
    @endif
</section>
<div class="cr-draftbar" @if ($draft['count'] === 0) hidden @endif>
    <span class="cr-draftbar__text">
        <strong>{{ trans_choice($r.'draft_items_count_zero', $draft['count'], ['count' => $draft['count']]) }}</strong>
        @if (count($groups) > 1)<span class="cr-meta">{{ trans_choice($r.'drawer_sent_as', count($groups), ['count' => count($groups)]) }}</span>@endif
    </span>
    <x-gs::button :href="route('gov.requests.basket.index')" icon="fa-arrow-right">{{ __($r.'draft_review') }}</x-gs::button>
</div>
