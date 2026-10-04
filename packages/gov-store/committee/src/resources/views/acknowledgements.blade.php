<div class="cm-acknowledgements">
    <p class="help-block">{{ __('committee::committee.backdated') }}</p>
    @foreach(($health?->issues ?? []) as $finding)
        @if(($finding['severity'] ?? '') === 'WARN')
            <label><input type="checkbox" name="acknowledged[]" value="{{ $finding['code'] }}"> {{ __('committee::committee.issues.'.$finding['code']) }}</label><br>
        @endif
    @endforeach
    @include('committee::field',['name'=>'reason'])
    @include('committee::field',['name'=>'acknowledgement'])
</div>

