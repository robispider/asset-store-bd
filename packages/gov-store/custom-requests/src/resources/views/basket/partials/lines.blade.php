{{--
    Checkout step 1: the draft's lines with free stock, approval rule and quantity, and why the draft is
    split. Returned again after every change (BasketController::fragments). Expects $draft and $presenter.
--}}
@php
    $r = 'requestlabels::requests.';
    $count = count($draft['groups']);
@endphp
<div class="cr-tablewrap">
    <table class="cr-lines-table cr-cards-sm">
        <caption class="cr-meta">{{ __($r.'draft_lines_caption') }}</caption>
        <thead>
            <tr>
                <th scope="col">{{ __($r.'lines_item') }}</th>
                <th scope="col">{{ __($r.'lines_free') }}</th>
                <th scope="col">{{ __($r.'lines_rule') }}</th>
                <th scope="col" class="cr-end">{{ __($r.'lines_qty') }}</th>
                <th scope="col"><span class="gs-sr-only">{{ __($r.'draft_remove') }}</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($draft['lines'] as $line)
                <tr data-cr-line="{{ $line->id }}">
                    <td data-label="{{ __($r.'lines_item') }}">
                        <strong>{{ $line->name }}</strong>
                        <span class="cr-meta">{{ $line->type_label }}</span>
                        @if ($line->free === null)
                            <span class="cr-warn"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> {{ __($r.'lines_gone') }}</span>
                        @elseif ($line->short)
                            <span class="cr-warn"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> {{ __($r.'draft_more_than_free') }}</span>
                        @endif
                        @if ($line->reason && $line->reason['threshold'])
                            <span class="cr-meta">{{ $presenter->reasonSentence($line->reason) }}</span>
                        @endif
                    </td>
                    <td data-label="{{ __($r.'lines_free') }}" class="cr-num">
                        @if ($line->free === null || $line->free < 1)
                            <span class="cr-pill cr-pill--none">{{ __($r.'pill_none') }}</span>
                        @elseif ($line->free <= 5)
                            <span class="cr-pill cr-pill--few">{{ __($r.'catalog_few', ['count' => $line->free]) }}</span>
                        @else
                            <span class="cr-pill cr-pill--ok">{{ __($r.'catalog_free', ['count' => $line->free]) }}</span>
                        @endif
                    </td>
                    <td data-label="{{ __($r.'lines_rule') }}">
                        @if ($line->policy)
                            <span class="cr-pill cr-pill--rule {{ $line->policy === 'AUTO_APPROVE' ? 'cr-pill--auto' : '' }}">{{ $presenter->ruleBadge($line->policy) }}</span>
                        @endif
                    </td>
                    <td data-label="{{ __($r.'lines_qty') }}" class="cr-end">@include('govstore::basket.partials.stepper', ['line' => $line, 'context' => 'checkout'])</td>
                    <td class="cr-end">
                        <form method="POST" action="{{ route('gov.requests.basket.remove', $line->id) }}" class="cr-inline-form" data-cr-remove>
                            @csrf
                            <input type="hidden" name="context" value="checkout">
                            <button type="submit" class="cr-iconbtn" aria-label="{{ __($r.'draft_remove_label', ['item' => $line->name]) }}"><i class="fas fa-trash-can" aria-hidden="true"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@if ($count > 1)
    <x-gs::alert tone="info" :title="__($r.'split_title', ['count' => $count])" id="cr-split">
        <p class="cr-flush">{{ __($r.'split_body') }}</p>
    </x-gs::alert>
@endif
