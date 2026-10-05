@if($reconstitutionDiff)
<section class="cm-subtle"><h3>{{ __('committee::committee.ux.reconstitute') }}</h3>
@foreach($reconstitutionDiff as $change=>$members)<p><strong>{{ __('committee::committee.ux.'.$change) }} ({{ $display::digits($members->count()) }})</strong>: {{ $members->map(fn ($m)=>$display::text($m->nameBn,$m->nameEn))->join(' · ') ?: '—' }}</p>@endforeach
</section>
@endif
