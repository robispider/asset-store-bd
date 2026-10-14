@extends('layouts/default')

@section('title', __('organization_labels::office.section_ministry'))

@section('content')
<div class="govorg">
    @include('govorg::partials.admin-header', ['tab' => 'ministry', 'ictCount' => $ictCount ?? 0, 'ministryCount' => $admins->count()])

    <div class="govorg-layout">
        <div class="govorg-layout__main">
            <x-gs::box>
                @if ($admins->isEmpty())
                    <x-gs::empty-state icon="fa-building-columns" :title="__('organization_labels::office.ministry_empty')" />
                @else
                    <x-gs::table :caption="__('organization_labels::office.ministry_caption')">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('organization_labels::office.col_officer') }}</th>
                                <th scope="col">{{ __('organization_labels::office.col_scope_ministry') }}</th>
                                <th scope="col">{{ __('organization_labels::office.col_home') }}</th>
                                <th scope="col"><span class="sr-only">{{ __('organization_labels::office.col_actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($admins as $admin)
                                @php($name = $admin->user?->present()->fullName ?? '—')
                                <tr>
                                    <td>
                                        <span class="govorg-toolbar"><x-gs::avatar :name="$name" size="sm" />
                                        <span><strong>{{ $name }}</strong><span class="govorg-meta govorg-mono">{{ $admin->user->username ?? '' }}</span></span></span>
                                    </td>
                                    <td>{{ $admin->company->name ?? '—' }}</td>
                                    <td>{!! $admin->user?->location ? e($admin->user->location->name) : '<span class="govorg-muted">'.e(__('organization_labels::office.no_home')).'</span>' !!}</td>
                                    <td>
                                        <form method="POST" action="{{ route('gov.org.company_admins.destroy', $admin->id) }}" class="govorg-inline-form">
                                            @csrf
                                            <x-gs::button type="submit" size="sm" tone="light" outline :aria-label="__('organization_labels::office.remove_access_for', ['name' => $name])">{{ __('organization_labels::office.remove_access') }}</x-gs::button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-gs::table>
                @endif
            </x-gs::box>
        </div>

        <aside class="govorg-layout__aside">
            <x-gs::box :title="__('organization_labels::office.give_ministry_title')">
                <form method="POST" action="{{ route('gov.org.company_admins.store') }}" class="govorg-stack">
                    @csrf
                    @include('govorg::partials.errors')
                    <x-gs::form-row for="user_id" :label="__('organization_labels::office.person')" required>
                        <select class="form-control select2" name="user_id" id="user_id" required style="width: 100%">
                            <option value="">{{ __('organization_labels::office.person_placeholder') }}</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>{{ $user->present()->fullName }} ({{ $user->username }})</option>
                            @endforeach
                        </select>
                    </x-gs::form-row>
                    <x-gs::form-row for="company_id" :label="__('organization_labels::office.ministry_pick')" required :help="__('organization_labels::office.ministry_hint')">
                        <select class="form-control select2" name="company_id" id="company_id" required style="width: 100%" aria-describedby="company_id-help">
                            <option value="">{{ __('organization_labels::office.ministry_placeholder') }}</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected((string) old('company_id') === (string) $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </x-gs::form-row>
                    <p class="govorg-muted govorg-small govorg-flush">{{ __('organization_labels::office.national_review_note') }}</p>
                    <div class="govorg-toolbar govorg-toolbar--end"><x-gs::button type="submit" icon="fa-building-columns">{{ __('organization_labels::office.give_access') }}</x-gs::button></div>
                </form>
            </x-gs::box>
        </aside>
    </div>
</div>
@endsection
