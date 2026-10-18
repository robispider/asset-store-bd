@props(['reasons' => [], 'note' => null])
<details {{ $attributes->class('gs-scope-why') }} open><summary><i class="fas fa-shield-halved" aria-hidden="true"></i> {{ __('gs-theme::kit.scope') }}</summary>
    <ul>@foreach($reasons as $reason)<li>{{ $reason }}</li>@endforeach</ul>@if($note)<p>{{ $note }}</p>@endif
</details>
