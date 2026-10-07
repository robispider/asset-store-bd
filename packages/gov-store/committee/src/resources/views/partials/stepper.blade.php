<ol class="cm-stepper" aria-label="{{ __('committee::committee.ux.order_steps') }}">
@foreach(['order','committee','members','check'] as $index=>$label)
    <li class="{{ $step > $index+1 ? 'cm-step-done' : '' }}" @if($step === $index+1) aria-current="step" @endif>
        @if($step > $index+1)<i class="fa fa-check" aria-hidden="true"></i>@endif {{ $display::digits($index+1) }} · {{ __('committee::committee.ux.step_'.$label) }}
    </li>
@endforeach
</ol>
