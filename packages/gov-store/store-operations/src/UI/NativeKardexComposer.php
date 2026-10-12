<?php

namespace GovStore\StoreOperations\UI;

use GovStore\StoreOperations\Services\InventoryLedgerService;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\View\View;

class NativeKardexComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();
        $item = $data['consumable'] ?? $data['accessory'] ?? $data['component'] ?? null;
        $context = app(TenantContext::class);
        $tabs = [];
        if ($item && auth()->check() && $context->locationId
            && (int) $item->location_id === $context->locationId && (int) $item->company_id === (int) $context->companyId
            && app(GovAccess::class)->decide(auth()->user(), 'storeops.documents.view')->allowed) {
            $type = strtolower(class_basename($item));
            foreach (app(TabRegistry::class)->getTabsFor($type) as $tab) {
                $tabs[] = ['id' => $tab->id, 'title' => __('storeops::storeops.stock_card_title', ['name' => $item->name]),
                    'icon' => $tab->icon, 'url' => route('storeops.register.kardex', ['type' => $type, 'id' => $item->id]),
                    'movements' => app(InventoryLedgerService::class)->getKardexFor(get_class($item), (int) $item->id)];
            }
        }
        $view->with('govStoreKardexTabs', $tabs);
    }
}
