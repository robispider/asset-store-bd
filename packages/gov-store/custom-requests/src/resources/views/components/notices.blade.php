@php($notices = app(\GovStore\CustomRequests\Services\RequestNotices::class)->forUser(auth()->id()))
@if($notices->isNotEmpty())
    <div class="box box-info">
        <div class="box-header"><h3 class="box-title">{{ __('requestlabels::requests.notifications') }}</h3></div>
        <ul class="list-group">
            @foreach($notices as $notice)
                <li class="list-group-item">{{ $notice->request_number }} — {{ __('requestlabels::requests.event_'.$notice->event_key) }}</li>
            @endforeach
        </ul>
    </div>
@endif
