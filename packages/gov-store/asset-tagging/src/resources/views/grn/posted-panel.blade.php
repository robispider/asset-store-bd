{{-- Posted GRN: the "Issued tags" box in the right column (additions only). Reprints reuse the same tags. --}}
@php
    $govtCount = $rows->where('format_version', 'fd18-v1')->count();
    $standardCount = $rows->count() - $govtCount;
@endphp
<div class="box box-solid at-issued">
    <div class="box-header with-border">
        <h3 class="box-title"><i class="fas fa-tags" aria-hidden="true"></i> {{ __('asset-tagging::tagging.posted.title') }}</h3>
    </div>
    <div class="box-body">
        <p class="gs-muted">{{ \GovStore\AssetTagging\Services\TagIssuanceService::line($govtCount, $standardCount) }}</p>
        <ul class="at-issued__list">
            @foreach ($rows as $row)
                <li class="at-issued__item">
                    <a href="{{ route('hardware.show', $row->asset_id) }}" class="gs-mono at-issued__tag">{{ $row->asset_tag }}</a>
                    <x-gs::badge :tone="$row->format_version === 'fd18-v1' ? 'primary' : 'secondary'">{{ __('asset-tagging::tagging.kind.'.($row->format_version === 'fd18-v1' ? 'govt' : 'standard')) }}</x-gs::badge>
                    <span class="at-issued__meta">{{ $row->product }}@if ($row->serial_number) · {{ $row->serial_number }}@endif</span>
                </li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('hardware.bulkedit.show') }}" target="_blank">
            @csrf
            <input type="hidden" name="bulk_actions" value="labels">
            @foreach ($rows as $row)
                <input type="hidden" name="ids[]" value="{{ $row->asset_id }}">
            @endforeach
            <button type="submit" class="btn btn-default btn-block"><i class="fas fa-print" aria-hidden="true"></i> {{ __('asset-tagging::tagging.posted.print') }}</button>
        </form>
        <a class="btn btn-default btn-block" href="{{ route('asset-tags.grn.csv', ['document' => $document->id]) }}">
            <i class="fas fa-file-csv" aria-hidden="true"></i> {{ __('asset-tagging::tagging.posted.csv') }}
        </a>
    </div>
</div>
