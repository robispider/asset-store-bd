{{-- Error summary at the top of a form; each message links to its field. Field messages come from <x-gs::form-row>. --}}
@if ($errors->any())
    <x-gs::alert tone="danger" :title="__('organization_labels::office.errors_title')">
        <ul class="govorg-flush">
            @foreach ($errors->keys() as $field)
                <li><a href="#{{ $field }}">{{ $errors->first($field) }}</a></li>
            @endforeach
        </ul>
    </x-gs::alert>
@endif
