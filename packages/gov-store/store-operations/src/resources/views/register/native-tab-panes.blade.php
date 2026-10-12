@foreach($govStoreKardexTabs ?? [] as $tab)
    <x-tabs.pane name="{{ $tab['id'] }}">
        <p><a href="{{ $tab['url'] }}">{{ $tab['title'] }}</a></p>
        @include('storeops::register.kardex-table', ['movements' => $tab['movements']])
    </x-tabs.pane>
@endforeach
