@extends('layouts/default')
@section('title', __('govonboard::onboard.title'))
@section('content')
<div class="govonboard-theme">
    @php
        $statusTabs = collect(['WAITING', 'COMPLETED', 'CANCELLED'])->map(fn (string $tab): array => [
            'key' => $tab,
            'label' => __('govonboard::onboard.'.$tab),
            'href' => route('gov.onboard.index', ['status' => $tab]),
        ])->all();
    @endphp
    <x-gs::box :title="__('govonboard::onboard.title')" icon="fas fa-user-plus" tone="primary">
        <p>{{ __('govonboard::onboard.intro') }}</p>
        <x-gs::status-tabs :tabs="$statusTabs" :active="$status" :label="__('govonboard::onboard.status')" />
        @include('govonboard::queue.notices')
        <div class="table-responsive"><x-gs::table class="table table-striped">
            <thead><tr><th scope="col">{{ __('govonboard::onboard.employee') }}</th><th scope="col">{{ __('govonboard::onboard.authority') }}</th><th scope="col">{{ __('govonboard::onboard.actions') }}</th></tr></thead>
            <tbody>
            @forelse($queue as $item)
                <tr>
                    <td>{{ $item->user?->first_name }} {{ $item->user?->last_name }}<br><small>{{ $item->user?->username }}</small></td>
                    <td>{{ __('govonboard::onboard.owner_'.$item->owner_type) }}<br>{{ __('govonboard::onboard.creator') }}: {{ $item->creator ? trim($item->creator->first_name.' '.$item->creator->last_name) : __('govonboard::onboard.system') }}<br>{{ $item->geoArea?->en_name }}</td>
                    <td>
                        @if($status === 'WAITING')
                        <form method="POST" action="{{ route('gov.onboard.assign') }}">
                            @csrf<input type="hidden" name="onboarding_id" value="{{ $item->id }}">
                            <label for="office-{{ $item->id }}">{{ __('govonboard::onboard.office') }}</label>
                            <select class="form-control" id="office-{{ $item->id }}" name="location_id" required>
                                <option value="">{{ __('govonboard::onboard.choose_office') }}</option>
                                @foreach($locations as $loc)
                                    @if(!$item->user?->company_id || $item->user->company_id == $loc->company_id)
                                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-success">{{ __('govonboard::onboard.assign') }}</button>
                        </form>
                        @endif
                        @if($status !== 'COMPLETED')
                        <form method="POST" action="{{ route('gov.onboard.decide') }}">
                            @csrf<input type="hidden" name="onboarding_id" value="{{ $item->id }}">
                            <label for="reason-{{ $item->id }}">{{ __('govonboard::onboard.reason') }}</label>
                            <textarea class="form-control" id="reason-{{ $item->id }}" name="reason" minlength="5" maxlength="1000" required></textarea>
                            @if($status === 'WAITING')
                                <label for="manager-{{ $item->id }}">{{ __('govonboard::onboard.reassign') }}</label>
                                <select class="form-control" id="manager-{{ $item->id }}" name="owner_choice">
                                    <option value="">{{ __('govonboard::onboard.choose_manager') }}</option>
                                    @foreach($managers[$item->id] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                                <button class="btn btn-default" name="action" value="reassign">{{ __('govonboard::onboard.reassign') }}</button>
                                <button class="btn btn-warning" name="action" value="reject">{{ __('govonboard::onboard.reject') }}</button>
                                <button class="btn btn-danger" name="action" value="cancel">{{ __('govonboard::onboard.cancel') }}</button>
                            @else
                                <button class="btn btn-default" name="action" value="reopen">{{ __('govonboard::onboard.reopen') }}</button>
                            @endif
                        </form>
                        @endif
                        <details><summary>{{ __('govonboard::onboard.history') }}</summary><ul>
                            @foreach($item->events as $event)<li>{{ __('govonboard::onboard.event_'.$event->event_key) }} — {{ $event->reason }} <small>{{ $event->created_at }}</small></li>@endforeach
                        </ul></details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3">{{ __('govonboard::onboard.empty') }}</td></tr>
            @endforelse
            </tbody>
        </x-gs::table></div>
        {{ $queue->links() }}
    </x-gs::box>
</div>
@endsection
