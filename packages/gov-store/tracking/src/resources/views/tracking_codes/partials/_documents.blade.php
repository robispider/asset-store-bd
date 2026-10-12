<div class="box box-default">
    <div class="box-header"><h3 class="box-title">{{ __('govtracking::general.documents') }}</h3></div>
    <div class="box-body">
        @foreach($trackingCode->documents as $document)
            <div class="clearfix">
                <a href="{{ route('gov.tracking.documents.download', [$initiative, $trackingCode, $document]) }}">{{ $document->file_name }} — {{ __('govtracking::general.download') }}</a>
                @if($trackingCode->status === 'DRAFT')
                <form method="POST" action="{{ route('gov.tracking.documents.destroy', [$initiative, $trackingCode, $document]) }}" class="pull-right">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">{{ __('govtracking::general.delete') }}</button>
                </form>
                @endif
            </div>
        @endforeach
        @if($trackingCode->status === 'DRAFT')
        <form method="POST" enctype="multipart/form-data" action="{{ route('gov.tracking.documents.store', [$initiative, $trackingCode]) }}">
            @csrf
            <label for="tracking-document">{{ __('govtracking::general.upload') }}</label>
            <input id="tracking-document" type="file" name="document" required>
            <button type="submit" class="btn btn-primary">{{ __('govtracking::general.upload') }}</button>
        </form>
        @endif
    </div>
</div>
