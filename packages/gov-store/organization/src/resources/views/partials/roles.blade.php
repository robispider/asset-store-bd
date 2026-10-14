{{--
    People and roles. Expects $localStaff, $roles, $profile, $location, $rolesAction (POST url), $adminForm (bool:
    show the administrator slot as an editable form — hub only), $locked (bool: office paused or not active).
--}}
@php
    $staffOptions = $localStaff->mapWithKeys(fn ($user) => [$user->id => $user->present()->fullName.' ('.$user->username.')']);
    $byId = $localStaff->keyBy('id');
    $holder = fn ($id) => $id && $byId->has($id) ? $byId->get($id) : null;
    $admin = $profile->officeAdmin;
@endphp
<x-gs::box :title="__('organization_labels::office.section_roles')" id="govorg-roles">
    @if ($localStaff->isEmpty())
        <x-gs::empty-state icon="fa-user-plus" :title="__('organization_labels::office.no_staff_title')">
            {{ __('organization_labels::office.no_staff_body') }}
            @if (\Illuminate\Support\Facades\Route::has('gov.membership.admin.index'))
                <x-slot:actions><x-gs::button :href="route('gov.membership.admin.index')" icon="fa-users">{{ __('organization_labels::office.manage_staff') }}</x-gs::button></x-slot:actions>
            @endif
        </x-gs::empty-state>
    @else
        <p class="govorg-muted">{{ __('organization_labels::office.roles_hint') }}</p>
        @if ($locked)
            <x-gs::alert tone="info">{{ __('organization_labels::office.locked_paused') }}</x-gs::alert>
        @endif
        <div class="gs-roles">
            @if ($adminForm)
                <form method="POST" action="{{ route('gov.org.hub.update', $location->id) }}" class="govorg-flush">
                    @csrf
                    <input type="hidden" name="name" value="{{ $location->name }}">
                    <input type="hidden" name="company_id" value="{{ $location->company_id }}">
                    <input type="hidden" name="parent_id" value="{{ $location->parent_id }}">
                    <input type="hidden" name="geo_area_id" value="{{ $profile->geo_area_id }}">
                    <fieldset class="govorg-flush" @disabled($locked)>
                        <x-gs::role-slot :role="__('organization_labels::office.role_admin')" :description="__('organization_labels::office.role_admin_desc')"
                            :person="$admin?->present()->fullName" :meta="$admin?->jobtitle" required>
                            <x-slot:actions>
                                <label class="sr-only" for="office_admin_id">{{ __('organization_labels::office.role_admin') }}</label>
                                <select class="form-control select2" name="office_admin_id" id="office_admin_id" style="width: 100%">
                                    <option value="">{{ __('organization_labels::office.nobody') }}</option>
                                    @foreach ($staffOptions as $id => $label)
                                        <option value="{{ $id }}" @selected((string) old('office_admin_id', $profile->office_admin_id) === (string) $id)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-gs::button type="submit" size="sm" tone="light" outline>{{ __('organization_labels::office.save_admin') }}</x-gs::button>
                            </x-slot:actions>
                        </x-gs::role-slot>
                    </fieldset>
                </form>
            @else
                <x-gs::role-slot :role="__('organization_labels::office.role_admin')" :description="__('organization_labels::office.role_admin_desc')"
                    :person="$admin?->present()->fullName" :meta="$admin?->jobtitle" required />
            @endif
        </div>

        <form method="POST" action="{{ $rolesAction }}" class="govorg-stack">
            @csrf
            <fieldset class="govorg-flush" @disabled($locked)>
                <div class="gs-roles">
                    @foreach ([
                        ['primary_approver_id', 'role_approver', true, false],
                        ['storekeeper_id', 'role_storekeeper', true, false],
                        ['final_approver_id', 'role_final', false, true],
                    ] as [$field, $key, $required, $optional])
                        @php($person = $holder($roles->{$field} ?? null))
                        <x-gs::role-slot :role="__('organization_labels::office.'.$key)" :description="__('organization_labels::office.'.$key.'_desc')"
                            :person="$person?->present()->fullName" :meta="$person?->jobtitle" :required="$required" :optional="$optional">
                            <x-slot:actions>
                                <div class="govorg-stack">
                                    <label class="sr-only" for="{{ $field }}">{{ __('organization_labels::office.'.$key) }}</label>
                                    <select class="form-control select2" name="{{ $field }}" id="{{ $field }}" style="width: 100%" @if ($required) required @endif>
                                        <option value="">{{ $optional ? __('organization_labels::office.no_second') : __('organization_labels::office.person_placeholder') }}</option>
                                        @foreach ($staffOptions as $id => $label)
                                            <option value="{{ $id }}" @selected((string) old($field, $roles->{$field} ?? '') === (string) $id)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error($field)<p class="gs-form-row__error" role="alert">{{ $message }}</p>@enderror
                                </div>
                            </x-slot:actions>
                        </x-gs::role-slot>
                    @endforeach
                </div>
                <div class="govorg-toolbar govorg-toolbar--end">
                    <x-gs::button type="submit" icon="fa-floppy-disk">{{ __('organization_labels::office.save_roles') }}</x-gs::button>
                </div>
            </fieldset>
        </form>
    @endif
</x-gs::box>
