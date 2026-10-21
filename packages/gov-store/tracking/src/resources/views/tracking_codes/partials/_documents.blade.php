<x-gs::box :title="__('govtracking::general.documents')">
    @if($trackingCode->documents->isNotEmpty())
        <ul class="gs-tracking-doc-list">
            @foreach($trackingCode->documents as $document)
                <li>
                    <a href="{{ route('gov.tracking.documents.download', [$initiative, $trackingCode, $document]) }}"><i class="fas fa-file-pdf" aria-hidden="true"></i> {{ $document->file_name }} — {{ __('govtracking::general.download') }}</a>
                    @if($trackingCode->status === 'DRAFT')
                        <form method="POST" action="{{ route('gov.tracking.documents.destroy', [$initiative, $trackingCode, $document]) }}">
                            @csrf @method('DELETE')
                            <x-gs::button type="submit" tone="danger" outline size="sm">{{ __('govtracking::general.delete') }}</x-gs::button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    @if($trackingCode->status === 'DRAFT')
        <form method="POST" enctype="multipart/form-data" action="{{ route('gov.tracking.documents.store', [$initiative, $trackingCode]) }}" class="gs-tracking-upload">
            @csrf
            <label for="tracking-document">{{ __('govtracking::general.upload') }}</label>
            <input id="tracking-document" type="file" name="document" class="form-control" required>
            <x-gs::button type="submit">{{ __('govtracking::general.upload') }}</x-gs::button>
        </form>
    @endif
</x-gs::box>
