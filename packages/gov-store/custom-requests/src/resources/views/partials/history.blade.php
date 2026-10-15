{{--
    The one request history (replaces three timelines, F9): full names, a sentence per event and the date
    with the time in the app timezone, newest first. Expects $serviceRequest and $presenter.
--}}
@php $items = $presenter->timeline($serviceRequest); @endphp
<x-gs::box :title="__('requestlabels::requests.history_title')" icon="fas fa-clock-rotate-left" id="history">
    @if ($items)
        <x-gs::timeline :items="$items" />
        <p class="cr-meta cr-flush">{{ $presenter->timesNote() }}</p>
    @else
        <p class="cr-muted cr-flush">{{ __('requestlabels::requests.history_none') }}</p>
    @endif
</x-gs::box>
