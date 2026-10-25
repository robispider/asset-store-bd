{{-- Theme Lab page header with the three views. Expects $current (workspace · compare · health) and $intro. --}}
<x-gs::page-header class="gs-lab-top" :title="__('gs-theme::appearance.lab_title')" :subtitle="$intro">
    <x-slot:actions>
        <nav class="gs-lab-views" aria-label="{{ __('gs-theme::appearance.lab.views') }}">
            @foreach (['workspace' => ['gs-theme.lab', 'fa-table-columns'], 'compare' => ['gs-theme.lab.compare', 'fa-clone'], 'health' => ['gs-theme.lab.health', 'fa-shield-halved']] as $view => [$route, $icon])
                <a class="gs-lab-views__link" href="{{ route($route) }}" @if ($current === $view) aria-current="page" @endif>
                    <i class="fas {{ $icon }}" aria-hidden="true"></i>
                    {{ __('gs-theme::appearance.lab.'.($view === 'health' ? 'health_report' : $view)) }}
                </a>
            @endforeach
        </nav>
    </x-slot:actions>
</x-gs::page-header>
