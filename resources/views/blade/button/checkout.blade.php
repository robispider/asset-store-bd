@props([
    'item' => null,
    'permission' => null,
    'route',
    'wide' => false,
])

@can('checkout', $item)
    @if(app(\GovStore\StoreOperations\Services\LedgerStockGuard::class)->manages($item))
        <span class="text-muted">{{ __('storeops::storeops.ledger_managed_stock') }}</span>
    @elseif ($item->showCheckoutButton($item) == 'show-active')
        <a href="{{ $route  }}" class="btn btn-sm bg-maroon hidden-print" data-tooltip="true"  data-placement="top" data-title="{{ trans('general.checkout') }}">
            <x-icon type="checkout" class="fa-fw" />
            @if ($wide=='true')
                {{ trans('general.checkout') }}
            @endif
        </a>
    @elseif ($item->showCheckoutButton($item) == 'show-disabled')
        <button href="#" class="btn btn-sm bg-maroon hidden-print disabled" data-tooltip="true" data-placement="top" data-title="{{ ($item::class =='App\Models\Asset') ? trans('admin/hardware/general.undeployable_tooltip') : trans('general.undeployable_tooltip') }}">
            <x-icon type="checkout" class="fa-fw"/>
            @if ($wide=='true')
                {{ trans('general.checkout') }}
            @endif
        </button>
    @endif
@endcan
