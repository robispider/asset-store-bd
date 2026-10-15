{{--
    One item in Request items, as a card or a list row (the list's class decides). Says how many are free
    and who has to approve it before it is added; once in the draft, the Add button becomes a quantity.
    Items with nothing free stay listed but cannot be added (D1).
    Expects $item (CatalogService row), $inDraft (DraftView lines by "type:id"), $rules (explainMany), $presenter.
--}}
@php
    $r = 'requestlabels::requests.';
    $key = $item->type.':'.$item->id;
    $line = $inDraft[$key] ?? null;
    $free = (int) $item->available_qty;
    $policy = $rules[$key]['policy'] ?? null;
    $domId = 'cr-item-'.$item->type.'-'.$item->id;
    $icon = ['asset_model' => 'fa-laptop', 'accessory' => 'fa-keyboard'][$item->type] ?? 'fa-box';
@endphp
<li class="cr-good {{ $line ? 'cr-good--in' : '' }} {{ $free < 1 ? 'cr-good--none' : '' }}" id="{{ $domId }}" data-cr-item="{{ $key }}">
    <div class="cr-good__head">
        <span class="cr-good__media" aria-hidden="true">
            <i class="fas {{ $icon }}"></i>
            @if ($item->image_url)<img src="{{ $item->image_url }}" alt="" loading="lazy" data-cr-img>@endif
        </span>
        <div class="cr-good__title">
            <h3 class="cr-good__name" id="{{ $domId }}-name">{{ $item->name }}</h3>
            <span class="cr-meta">{{ collect([$item->category, __($r.'type_'.$item->type)])->filter()->implode(' · ') }}</span>
        </div>
    </div>
    <div class="cr-good__facts">
        <span class="cr-pills">
            @if ($free < 1)
                <span class="cr-pill cr-pill--none">{{ __($r.'pill_none') }}</span>
            @elseif ($free <= 5)
                <span class="cr-pill cr-pill--few"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> {{ __($r.'catalog_few', ['count' => $free]) }}</span>
            @else
                <span class="cr-pill cr-pill--ok"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ __($r.'catalog_free', ['count' => $free]) }}</span>
            @endif
            @if ($free > 0 && $policy)
                <span class="cr-pill cr-pill--rule {{ $policy === 'AUTO_APPROVE' ? 'cr-pill--auto' : '' }}" data-cr-rule="{{ $policy }}">
                    <i class="fas {{ $policy === 'AUTO_APPROVE' ? 'fa-bolt' : 'fa-user-check' }}" aria-hidden="true"></i> {{ $presenter->ruleBadge($policy) }}
                </span>
            @endif
        </span>
        @if ($item->type === 'asset_model' && $free > 0)
            <span class="cr-good__note"><i class="fas fa-tag" aria-hidden="true"></i> {{ __($r.'catalog_named_issue') }}</span>
        @endif
    </div>
    <div class="cr-good__act">
        @if ($free < 1)
            <span class="cr-meta">{{ __($r.'catalog_none', ['count' => $item->stock]) }}</span>
        @elseif ($line)
            <span class="cr-good__in"><i class="fas fa-check" aria-hidden="true"></i> {{ __($r.'catalog_in_draft_short') }}</span>
            @include('govstore::basket.partials.stepper', ['line' => $line, 'context' => 'catalog'])
        @else
            <form method="POST" action="{{ route('gov.requests.basket.add') }}" class="cr-good__add" data-cr-add>
                @csrf
                <input type="hidden" name="item_type" value="{{ $item->type }}">
                <input type="hidden" name="item_id" value="{{ $item->id }}">
                <input type="hidden" name="qty" value="1">
                <input type="hidden" name="context" value="catalog">
                <x-gs::button type="submit" size="sm" icon="fa-plus" aria-label="{{ __($r.'catalog_add_label', ['item' => $item->name]) }}">{{ __($r.'catalog_add_short') }}</x-gs::button>
            </form>
        @endif
    </div>
</li>
