{{-- Error summary: what went wrong, in words, with links to the field. Field messages also show under each field. --}}
@if ($errors->any())
    <x-gs::alert tone="danger" :title="__('office_membership::member.errors_title')">
        <ul class="govmem-flush">
            @foreach ($errors->keys() as $field)
                <li>@if ($field === 'membership'){{ $errors->first($field) }}@else<a href="#{{ $field }}">{{ $errors->first($field) }}</a>@endif</li>
            @endforeach
        </ul>
    </x-gs::alert>
@endif
