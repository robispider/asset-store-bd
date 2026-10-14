{{-- Staff of the office (active memberships). Expects $localStaff. --}}
<x-gs::box :title="__('organization_labels::office.section_staff')" id="govorg-staff">
    @if (\Illuminate\Support\Facades\Route::has('gov.membership.admin.index'))
        <x-slot:tools><x-gs::button size="sm" tone="light" outline :href="route('gov.membership.admin.index')">{{ __('organization_labels::office.manage_staff') }}</x-gs::button></x-slot:tools>
    @endif
    @if ($localStaff->isEmpty())
        <p class="govorg-muted govorg-flush">{{ __('organization_labels::office.no_staff_body') }}</p>
    @else
        <x-gs::table :caption="trans_choice('organization_labels::office.staff_count', $localStaff->count(), ['count' => $localStaff->count()])">
            <thead>
                <tr>
                    <th scope="col">{{ __('organization_labels::office.col_name') }}</th>
                    <th scope="col">{{ __('organization_labels::office.col_job') }}</th>
                    <th scope="col">{{ __('organization_labels::office.col_username') }}</th>
                    <th scope="col">{{ __('organization_labels::office.col_email') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($localStaff as $user)
                    <tr>
                        <td><strong>{{ $user->present()->fullName }}</strong></td>
                        <td>{{ $user->jobtitle ?: '—' }}</td>
                        <td class="govorg-mono">{{ $user->username }}</td>
                        <td>{{ $user->email ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-gs::table>
    @endif
</x-gs::box>
