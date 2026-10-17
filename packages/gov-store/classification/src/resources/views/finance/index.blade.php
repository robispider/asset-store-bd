@extends('layouts/default')

@section('title')
    {{ __('classification::finance.title') }}
    @parent
@stop

@section('content')
@php
    $statusTabs = [];
    foreach (['held', 'active', 'retired', 'all'] as $key) {
        $statusTabs[] = ['key' => $key, 'label' => __('classification::finance.status_'.$key),
            'href' => route('gov.catalog.finance.index', array_filter(['status' => $key, 'group' => $filters['group'] ?? null])),
            'count' => $key === 'all' ? array_sum([$counts['held'], $counts['active'], $counts['retired']]) : $counts[$key]];
    }
    $groupOptions = $capacity->pluck('name', 'group')->all();
    $isHeld = $status === 'held';
@endphp
<div class="gs-finance-catalog">
    <x-gs::page-header :title="__('classification::finance.title')" :subtitle="__('classification::finance.intro')" />

    @if (session('success'))
        <x-gs::alert tone="success" dismissible>{{ session('success') }}</x-gs::alert>
    @endif
    @if ($errors->any())
        <x-gs::alert tone="danger">{{ $errors->first() }}</x-gs::alert>
    @endif

    <div class="gs-finance-catalog__kpis">
        <x-gs::kpi :label="__('classification::finance.status_held')" :value="number_format($counts['held'])" icon="fa-pause" tone="warning" />
        <x-gs::kpi :label="__('classification::finance.status_active')" :value="number_format($counts['active'])" icon="fa-circle-check" tone="success" />
        <x-gs::kpi :label="__('classification::finance.status_retired')" :value="number_format($counts['retired'])" icon="fa-box-archive" tone="muted" />
        <x-gs::kpi :label="__('classification::finance.linked_unspsc')" :value="number_format($counts['linked'])" icon="fa-link" />
    </div>

    <x-gs::box :title="__('classification::finance.capacity_title')" icon="fas fa-gauge">
        <p class="gs-muted">{{ __('classification::finance.capacity_help') }}</p>
        <ul class="gs-finance-capacity">
            @foreach ($capacity as $group)
                <li class="gs-finance-capacity__item">
                    <x-gs::progress :value="$group['used']" :max="$group['capacity']" :tone="$group['tone']" size="sm" :show-value="false"
                        :label="$group['group'].' '.$group['name'].' · '.__('classification::finance.capacity_used', ['used' => $group['used'], 'capacity' => $group['capacity']])" />
                    @if ($group['tone'] === 'danger')
                        <p class="gs-finance-capacity__note"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ __('classification::finance.capacity_full', ['group' => $group['group']]) }}</p>
                    @elseif ($group['tone'] === 'warning')
                        <p class="gs-finance-capacity__note"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> {{ __('classification::finance.capacity_warning', ['group' => $group['group'], 'used' => $group['used']]) }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-gs::box>

    <x-gs::status-tabs :tabs="$statusTabs" :active="$status" :label="__('classification::finance.title')" />

    <x-gs::filter-bar :action="route('gov.catalog.finance.index')" :reset-href="route('gov.catalog.finance.index', ['status' => $status])"
        :active="count(array_filter([$filters['q'] ?? null, $filters['group'] ?? null]))">
        <input type="hidden" name="status" value="{{ $status }}">
        <div>
            <label for="finance-q" class="control-label">{{ __('classification::finance.search') }}</label>
            <input id="finance-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" maxlength="100">
        </div>
        <div>
            <label for="finance-group" class="control-label">{{ __('classification::finance.group') }}</label>
            <select id="finance-group" name="group" class="form-control">
                <option value="">—</option>
                @foreach ($groupOptions as $code => $name)
                    <option value="{{ $code }}" @selected(($filters['group'] ?? null) === $code)>{{ $code }} · {{ $name }}</option>
                @endforeach
            </select>
        </div>
    </x-gs::filter-bar>

    <form method="POST" action="{{ route($isHeld ? 'gov.catalog.finance.activate' : 'gov.catalog.finance.retire') }}" id="finance-bulk">
        @csrf
        <x-gs::table :caption="__('classification::finance.table_caption')">
            <thead>
                <tr>
                    @if (in_array($status, ['held', 'active'], true))<th scope="col"><span class="gs-sr-only">{{ __('classification::finance.select') }}</span></th>@endif
                    <th scope="col">{{ __('classification::finance.code') }}</th>
                    <th scope="col">{{ __('classification::finance.item') }}</th>
                    <th scope="col">{{ __('classification::finance.path') }}</th>
                    <th scope="col">{{ __('classification::finance.unspsc') }}</th>
                    <th scope="col">{{ __('classification::finance.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    @php
                        $blocked = $catalog->hasOpenIssues($item);
                    @endphp
                    <tr>
                        @if (in_array($status, ['held', 'active'], true))
                            <td><input type="checkbox" name="codes[]" value="{{ $item->gov_code }}" aria-label="{{ $item->label() }}" @disabled($isHeld && $blocked)></td>
                        @endif
                        <td><code class="gs-mono">{{ $item->gov_code }}</code></td>
                        <td>{{ $item->item_name }}</td>
                        <td class="gs-muted">{{ $item->path() }}</td>
                        <td>
                            @if ($item->unspsc_code)<code class="gs-mono">{{ $item->unspsc_code }}</code>@else<span class="gs-muted">—</span>@endif
                        </td>
                        <td>
                            <x-gs::badge :tone="['held' => 'warning', 'active' => 'success', 'retired' => 'secondary'][$item->status] ?? 'secondary'">{{ __('classification::finance.status_'.$item->status) }}</x-gs::badge>
                            @if ($isHeld && $blocked)<x-gs::badge tone="danger" outline>{{ __('classification::finance.source_conflict') }}</x-gs::badge>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-gs::empty-state icon="fa-list" :title="__('classification::finance.empty')" /></td></tr>
                @endforelse
            </tbody>
        </x-gs::table>
        {{ $items->links() }}

        @if ($isHeld)
            <x-gs::box :title="__('classification::finance.activate_title')" icon="fas fa-circle-check">
                <p class="gs-muted">{{ __('classification::finance.activate_help') }}</p>
                <x-gs::form-row :label="__('classification::finance.authority_reference')" for="authority_reference" required>
                    <input id="authority_reference" name="authority_reference" class="form-control" required minlength="3" maxlength="500">
                </x-gs::form-row>
                <x-gs::button type="submit" tone="primary" icon="fa-circle-check">{{ __('classification::finance.activate') }}</x-gs::button>
            </x-gs::box>
        @elseif ($status === 'active')
            <x-gs::button type="submit" tone="danger" outline icon="fa-box-archive">{{ __('classification::finance.retire') }}</x-gs::button>
        @endif
    </form>

    <x-gs::box :title="__('classification::finance.new_title')" icon="fas fa-plus">
        <form method="GET" action="{{ route('gov.catalog.finance.index') }}" class="gs-finance-new__pick">
            <input type="hidden" name="status" value="{{ $status }}">
            <x-gs::form-row :label="__('classification::finance.group')" for="new_group">
                <select id="new_group" name="new_group" class="form-control">
                    <option value="">—</option>
                    @foreach ($groupOptions as $code => $name)
                        <option value="{{ $code }}" @selected(($preview['group'] ?? null) === $code)>{{ $code }} · {{ $name }}</option>
                    @endforeach
                </select>
            </x-gs::form-row>
            <x-gs::button type="submit" tone="secondary" outline>{{ __('classification::finance.preview_number') }}</x-gs::button>
        </form>

        @if ($preview)
            @if ($preview['next'] === null)
                <x-gs::alert tone="danger" :title="__('classification::finance.capacity_full', ['group' => $preview['group']])">
                    {{ __('classification::finance.capacity_full_help') }}
                </x-gs::alert>
            @else
                <p>{{ __('classification::finance.next_number', ['group' => $preview['group'], 'next' => $preview['next']]) }}</p>
                <form method="POST" action="{{ route('gov.catalog.finance.store') }}">
                    @csrf
                    <input type="hidden" name="group_code" value="{{ $preview['group'] }}">
                    <x-gs::form-row :label="__('classification::finance.subgroup')" for="subgroup_code" required>
                        <select id="subgroup_code" name="subgroup_code" class="form-control" required>
                            @foreach ($preview['subgroups'] as $code => $name)
                                <option value="{{ $code }}">{{ $code }} · {{ $name }}</option>
                            @endforeach
                        </select>
                    </x-gs::form-row>
                    <x-gs::form-row :label="__('classification::finance.item')" for="item_name" required>
                        <input id="item_name" name="item_name" class="form-control" required minlength="2" maxlength="255">
                    </x-gs::form-row>
                    <x-gs::form-row :label="__('classification::finance.authority_reference')" for="new_authority_reference">
                        <input id="new_authority_reference" name="authority_reference" class="form-control" maxlength="500">
                    </x-gs::form-row>
                    <x-gs::button type="submit" tone="primary" icon="fa-plus">{{ __('classification::finance.create') }}</x-gs::button>
                    <p class="gs-muted">{{ __('classification::finance.create_help') }}</p>
                </form>
            @endif
        @endif
    </x-gs::box>
</div>
@endsection
