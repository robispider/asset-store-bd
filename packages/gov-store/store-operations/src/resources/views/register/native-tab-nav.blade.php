@foreach($govStoreKardexTabs ?? [] as $tab)
    <x-tabs.nav-item name="{{ $tab['id'] }}" icon="{{ $tab['icon'] }}" label="{{ $tab['title'] }}" />
@endforeach
