{{--
    Quantity of one draft line: − / number / +. Saves as you go (gov-requests.js); without JavaScript the
    number is saved with the Save button. In Request items, − at 1 takes the line out of the draft (with
    Undo); in the checkout it stops at 1 and the row's own Remove button takes the line out.
    Expects $line (DraftView line) and $context (catalog|checkout).
--}}
@php
    $r = 'requestlabels::requests.';
    $removes = $context === 'catalog' && $line->qty <= 1;
@endphp
<form method="POST" action="{{ route('gov.requests.basket.update') }}" class="cr-stepper" data-cr-line-form="{{ $line->id }}">
    @csrf
    <input type="hidden" name="item_id" value="{{ $line->id }}">
    <input type="hidden" name="context" value="{{ $context }}">
    <button type="button" class="cr-stepper__btn" data-cr-step="-1" @disabled($line->qty <= 1 && ! $removes)
        aria-label="{{ $removes ? __($r.'draft_remove_label', ['item' => $line->name]) : __($r.'draft_less', ['item' => $line->name]) }}">
        @if ($removes)<i class="fas fa-trash-can" aria-hidden="true"></i>@else<span aria-hidden="true">−</span>@endif
    </button>
    <input type="number" name="qty" value="{{ $line->qty }}" min="1" max="10000" inputmode="numeric" class="cr-stepper__input"
        id="cr-qty-{{ $context }}-{{ $line->id }}" aria-label="{{ __($r.'draft_qty_label', ['item' => $line->name]) }}" data-cr-line-qty="{{ $line->id }}">
    <button type="button" class="cr-stepper__btn" data-cr-step="1" aria-label="{{ __($r.'draft_more', ['item' => $line->name]) }}"><span aria-hidden="true">+</span></button>
    <noscript><button type="submit" class="gs-btn gs-btn--light gs-btn--outline gs-btn--sm">{{ __($r.'stepper_save') }}</button></noscript>
</form>
