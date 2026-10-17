{{-- S4 · Finance Code (optional) directly under the product type's single UNSPSC code. --}}
@php
    $defaults = app(\GovStore\Classification\Services\ProductTypeFinanceDefault::class);
    $commodity = app(\GovStore\Classification\Services\ProductTypeCommodity::class);
    $resolved = $defaults->resolve((int) $category->id);
    $severalCodes = $commodity->categoriesWithSeveralCodes()[$category->id] ?? 0;
    $unspsc = $commodity->codeFor((int) $category->id);
    $canEdit = $defaults->canEdit(auth()->user(), (int) $category->id);
    $ownerLabel = $resolved['scope'][0] === 'national'
        ? __('classification::finance.owner_national')
        : __('classification::finance.owner_company', ['company' => \Illuminate\Support\Facades\DB::table('companies')->where('id', $resolved['scope'][1])->value('name') ?? '#'.$resolved['scope'][1]]);
    $options = $canEdit
        ? \GovStore\Classification\Models\FinanceItem::where('status', 'active')
            ->where(fn ($q) => $q->whereNull('unspsc_code')->when($unspsc, fn ($q) => $q->orWhere('unspsc_code', $unspsc)))
            ->orderBy('gov_code')->get()
        : collect();
@endphp
<x-gs::box :title="__('classification::finance.optional')" icon="fas fa-tag" class="gs-finance-default">
    @if ($severalCodes)
        <x-gs::alert tone="warning" :title="__('classification::finance.several_unspsc', ['count' => $severalCodes])">
            {{ __('classification::finance.several_unspsc_help') }}
        </x-gs::alert>
    @endif
    @if ($resolved['status'] === 'needs_review')
        <x-gs::alert tone="warning" :title="__('classification::finance.needs_review')">{{ __('classification::finance.needs_review_help') }}</x-gs::alert>
    @endif

    @if ($resolved['item'])
        <p><code class="gs-mono">{{ $resolved['item']->gov_code }}</code> · {{ $resolved['item']->item_name }}
            <span class="gs-muted">({{ $resolved['item']->path() }})</span></p>
    @else
        <p class="gs-muted">{{ __('classification::finance.none_set') }}</p>
    @endif
    <p class="gs-finance-default__owner"><i class="fas fa-user-shield" aria-hidden="true"></i> {{ $ownerLabel }}</p>
    <p class="gs-muted">{{ __('classification::finance.optional_help') }}</p>

    @if ($canEdit)
        <form method="POST" action="{{ route('gov.catalog.product-types.finance', $category->id) }}">
            @csrf
            @if ($resolved['revision'])<input type="hidden" name="revision" value="{{ $resolved['revision'] }}">@endif
            <x-gs::form-row :label="__('classification::finance.code')" for="finance_gov_code">
                <select id="finance_gov_code" name="gov_code" class="form-control select2">
                    <option value="">{{ __('classification::finance.no_code') }}</option>
                    @foreach ($options as $item)
                        <option value="{{ $item->gov_code }}" @selected($resolved['gov_code'] === $item->gov_code)>
                            {{ $item->label() }} — {{ $item->path() }}@if ($unspsc && $item->unspsc_code === $unspsc) · {{ __('classification::finance.matches_unspsc') }}@endif
                        </option>
                    @endforeach
                </select>
            </x-gs::form-row>
            <x-gs::button type="submit" tone="primary">{{ __('classification::finance.save_default') }}</x-gs::button>
        </form>
    @endif
</x-gs::box>
