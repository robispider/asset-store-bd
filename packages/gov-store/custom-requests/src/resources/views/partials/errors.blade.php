{{-- Error summary: what went wrong in words, linked to the field. Field messages also show where the problem is. --}}
@if ($errors->any())
    <x-gs::alert tone="danger" :title="__('requestlabels::requests.errors_title')" data-cr-errors>
        <ul class="cr-flush cr-plain">
            @foreach ($errors->keys() as $field)
                @php $anchor = 'cr-field-'.\Illuminate\Support\Str::slug(str_replace('.', '-', $field)); @endphp
                <li>@if (in_array($field, ['request', 'decision', 'workflow', 'item', 'items', 'issue'], true)){{ $errors->first($field) }}@else<a href="#{{ $anchor }}">{{ $errors->first($field) }}</a>@endif</li>
            @endforeach
        </ul>
    </x-gs::alert>
@endif
