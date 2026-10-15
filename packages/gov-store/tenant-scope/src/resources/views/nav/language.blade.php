{{-- Navbar language toggle (EN | বাং): one click saves the language to the user's profile
     (LocaleController) and reloads the page in it. Languages come from govstore-navigation.languages. --}}
@php
    $langOptions = config('govstore-navigation.languages', []);
    $langCurrent = app()->getLocale();
    if (! array_key_exists($langCurrent, $langOptions)) {
        // A regional variant (en-GB) lights up its language's entry (en-US).
        $langCurrent = collect(array_keys($langOptions))
            ->first(fn ($langCode) => \Illuminate\Support\Str::before($langCode, '-') === \Illuminate\Support\Str::before($langCurrent, '-'));
    }
@endphp
@if(count($langOptions) > 1)
<li class="gs-nav-lang" id="gov-nav-language">
    <form action="{{ route('gov.locale.switch') }}" method="POST" class="gs-nav-lang-form" role="group" aria-label="{{ __('tenantops::nav.language') }}">
        @csrf
        @foreach($langOptions as $langCode => $langLabel)
            <button type="submit" name="locale" value="{{ $langCode }}" lang="{{ $langCode }}" class="gs-nav-lang-option"
                    aria-pressed="{{ $langCode === $langCurrent ? 'true' : 'false' }}">{{ $langLabel }}</button>
        @endforeach
    </form>
</li>
@endif
