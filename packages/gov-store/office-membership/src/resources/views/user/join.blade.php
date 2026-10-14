@extends('layouts/default')

@section('title', __('office_membership::member.join_title'))

{{--
    Join another office (plan board 4). The person says what the office gave them: an office code (checked
    first, so they see which office it is before sending) or nothing (they make a personal code for the
    administrator). Expects $user, $way, $checked, $activeToken.
--}}
@php
    $m = 'office_membership::member.';
    $state = $checked['state'] ?? null;
    $codeError = $errors->first('office_code') ?: (in_array($state, ['unknown', 'expired', 'pending', 'member'], true) ? __($m.'join_check_'.$state) : null);
    $home = auth()->user()->memberships()->where('is_home_office', true)->where('status', 'active')->first();
@endphp

@section('content')
<div class="govmem">
    <x-gs::page-header :title="__($m.'join_title')" :subtitle="__($m.'join_subtitle')" />

    @if ($errors->has('membership'))
        <x-gs::alert tone="danger">{{ $errors->first('membership') }}</x-gs::alert>
    @endif

    <div class="govmem-layout">
        <div class="govmem-layout__main govmem-stack">
            <form method="GET" action="{{ route('gov.membership.join.form') }}">
                <fieldset class="govmem-stack">
                    <legend class="govmem-heading">{{ __($m.'join_question') }}</legend>
                    <div class="gs-choices">
                        <x-gs::choice-card name="way" value="office" icon="fa-building" :checked="$way === 'office'"
                            :label="__($m.'join_way_office')" :description="__($m.'join_way_office_desc')" />
                        <x-gs::choice-card name="way" value="personal" icon="fa-key" :checked="$way === 'personal'"
                            :label="__($m.'join_way_personal')" :description="__($m.'join_way_personal_desc')" />
                    </div>
                    <noscript><div><x-gs::button type="submit" tone="light" outline>{{ __($m.'continue') }}</x-gs::button></div></noscript>
                </fieldset>
            </form>

            <section data-govmem-when="way=office" class="govmem-stack" aria-labelledby="join-office-title">
                <h2 class="govmem-heading" id="join-office-title">{{ __($m.'join_way_office') }}</h2>
                @if ($state === 'ok')
                    @php $profile = $checked['profile']; @endphp
                    <x-gs::box tone="primary">
                        <div class="govmem-stack">
                            <div>
                                <h3 class="govmem-office__name">{{ $profile->location->name }}</h3>
                                <span class="govmem-meta">{{ collect([$profile->location->company->name ?? null, $profile->location->parent?->name])->filter()->implode(' · ') }}</span>
                            </div>
                            <p class="govmem-flush">{{ $checked['admin']
                                ? __($m.'join_reviewer', ['name' => $checked['admin']->present()->fullName])
                                : __($m.'join_reviewer_unknown') }}</p>
                            <div class="govmem-toolbar">
                                <form method="POST" action="{{ route('gov.membership.join') }}" class="govmem-inline-form">
                                    @csrf
                                    <input type="hidden" name="office_code" value="{{ $checked['code'] }}">
                                    <x-gs::button type="submit" icon="fa-paper-plane">{{ __($m.'join_send') }}</x-gs::button>
                                </form>
                                <x-gs::button :href="route('gov.membership.join.form', ['way' => 'office'])" tone="light" outline>{{ __($m.'join_not_this') }}</x-gs::button>
                            </div>
                        </div>
                    </x-gs::box>
                @else
                    <form method="GET" action="{{ route('gov.membership.join.form') }}" class="govmem-stack">
                        <input type="hidden" name="way" value="office">
                        <x-gs::form-row for="code" :label="__($m.'join_code_label')" required :help="__($m.'join_code_help')" :error="$codeError">
                            <input type="text" class="form-control govmem-code-input" id="code" name="code" maxlength="20" required autocomplete="off"
                                autocapitalize="characters" spellcheck="false" value="{{ old('office_code', $checked['code'] ?? '') }}"
                                aria-describedby="code-help @if ($codeError) code-error @endif" @if ($codeError) aria-invalid="true" @endif>
                        </x-gs::form-row>
                        <div><x-gs::button type="submit" icon="fa-magnifying-glass">{{ __($m.'join_check') }}</x-gs::button></div>
                    </form>
                @endif
            </section>

            <section data-govmem-when="way=personal" class="govmem-stack" aria-labelledby="join-personal-title">
                <h2 class="govmem-heading" id="join-personal-title">{{ __($m.'join_way_personal') }}</h2>
                <p class="govmem-flush">{{ __($m.'join_personal_intro') }}</p>
                @include('govmem::partials.personal-code')
            </section>
        </div>

        <aside class="govmem-layout__aside">
            <x-gs::box :title="__($m.'join_transfer_title')">
                <p>{{ __($m.'join_transfer_body') }}</p>
                @if ($home)
                    <x-gs::button :href="route('gov.membership.leave', $home->id)" tone="light" outline size="sm">{{ __($m.'join_transfer_start') }}</x-gs::button>
                @endif
            </x-gs::box>
        </aside>
    </div>
</div>
@endsection

@section('moar_scripts')
@include('govmem::partials.script')
@endsection
