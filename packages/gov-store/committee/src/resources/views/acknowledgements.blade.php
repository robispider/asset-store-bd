@php($findings = collect($health->issues ?? [])->unique('code'))
<div class="cm-acknowledgements">
    <div class="cm-preview-acknowledgements">
    @foreach($findings as $finding)
        @if($finding['severity'] === 'WARN')<label><input type="checkbox" name="acknowledged[]" value="{{ $finding['code'] }}"> {{ __('committee::committee.issues.'.$finding['code']) }}</label><br>@endif
    @endforeach
    </div>
    <div class="cm-inoperable-ack {{ $findings->contains('severity','BLOCK') ? '' : 'hidden' }}">
        <label><input type="checkbox" name="acknowledgement" value="INOPERABLE" @if(!$findings->contains('severity','BLOCK')) disabled @endif> {{ __('committee::committee.ux.accept_inoperable') }}</label>
    </div>
    @include('committee::field',['name'=>'reason','type'=>'textarea'])
</div>
