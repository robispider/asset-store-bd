@extends('layouts/default')

@section('title', __('organization_labels::office.directory_title'))

@php($bangla = str_starts_with(strtolower(app()->getLocale()), 'bn'))

@section('content')
<div class="govorg">
    <x-gs::page-header :title="__('organization_labels::office.directory_title')" :bn="$bangla ? null : __('organization_labels::office.directory_title', [], 'bn-BD')"
        :subtitle="__('organization_labels::office.directory_subtitle')" />

    @include('govorg::partials.section-nav', ['active' => 'directory'])

    <div class="govorg-layout">
        <div class="govorg-layout__main">
            <x-gs::box :title="__('organization_labels::office.directory_preview')">
                <x-slot:tools><span class="govorg-muted">{{ trans_choice('organization_labels::office.directory_records', $totalRecords, ['count' => number_format($totalRecords)]) }}</span></x-slot:tools>
                @if ($latestRecords->isEmpty())
                    <x-gs::empty-state icon="fa-database" :title="__('organization_labels::office.directory_empty')" />
                @else
                    <x-gs::table>
                        <thead>
                            <tr>
                                <th scope="col" class="gs-num">{{ __('organization_labels::office.col_id') }}</th>
                                <th scope="col">{{ __('organization_labels::office.col_en_name') }}</th>
                                <th scope="col">{{ __('organization_labels::office.col_bn_name') }}</th>
                                <th scope="col">{{ __('organization_labels::office.col_type') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($latestRecords as $row)
                                <tr>
                                    <td class="gs-num">{{ $row->id }}</td>
                                    <td><strong>{{ $row->en_name }}</strong></td>
                                    <td><span class="gs-bn" lang="bn">{{ $row->bn_name }}</span></td>
                                    <td><x-gs::badge tone="light">{{ $row->org_type }}</x-gs::badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-gs::table>
                @endif
            </x-gs::box>
        </div>

        <aside class="govorg-layout__aside">
            <x-gs::box :title="__('organization_labels::office.directory_import_title')">
                <form method="POST" action="{{ route('gov.org.directory.import') }}" class="govorg-stack">
                    @csrf
                    <p class="govorg-muted govorg-flush">{{ __('organization_labels::office.directory_import_desc') }}</p>
                    <p class="govorg-muted govorg-small govorg-flush">{{ __('organization_labels::office.national_review_note') }}</p>
                    <div><x-gs::button type="submit" icon="fa-file-import">{{ __('organization_labels::office.directory_import_button') }}</x-gs::button></div>
                </form>
            </x-gs::box>
        </aside>
    </div>
</div>
@endsection
