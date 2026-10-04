@php($fieldId = 'cm-field-'.\Illuminate\Support\Str::uuid())
<div class="form-group">
    <label for="{{ $fieldId }}">{{ __('committee::committee.'.($label ?? $name)) }}</label>
    @if(isset($options))
        <select id="{{ $fieldId }}" name="{{ $name }}" class="form-control {{ $class ?? '' }}" @if($required ?? false) required @endif>
            @foreach($options as $key => $text)<option value="{{ $key }}" @selected((string)($value ?? '') === (string)$key)>{{ $text }}</option>@endforeach
        </select>
    @else
        <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type ?? 'text' }}" class="form-control {{ $class ?? '' }}" value="{{ $value ?? '' }}" @if($required ?? false) required @endif @if(($type ?? '') === 'file') accept=".pdf,.jpg,.jpeg,.png" @endif>
    @endif
</div>

