@auth
    @include('govscope::hooks.menu-items', ['items' => app(\GovStore\TenantScope\Navigation\MenuRegistry::class)->tree()])
@endauth
