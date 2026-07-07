@props([
    'name' => null,
    'item' => null,
    'label' => null,
    'options' => null,
    'selected' => null,
    'value' => '1',
    'required' => null,
    'disabled' => false,
    'help_text' => null,
    'info_tooltip_text' => null,
    // Default input column: only skip the offset when a left-hand label
    // column is being rendered (i.e. multi mode with a label). Single mode
    // never has a left label; multi mode without a label lays out the same
    // way. Keeping the grid classes centralized here means a future Bootstrap /
    // AdminLTE upgrade only has to touch this file, not every callsite.
    // Note: Blade evaluates @props defaults twice (once via extractPropNames
    // before caller attrs are bound, once when applying defaults after). The
    // `?? null` guards against "undefined variable" on the first pass; isset()
    // is inherently safe against undefined vars, is_array() is not.
    'input_div_class' => (is_array($options ?? null) && isset($label)) ? 'col-md-8' : 'col-md-8 col-md-offset-3',
])

@php
    // Multi-checkbox mode kicks in when the caller supplies an $options map;
    // otherwise this renders as a single boolean checkbox.
    $is_multi = is_array($options);

    // Old-input aware check-state. On a fresh render, session()->hasOldInput()
    // is false, so we fall back to the model (or supplied :selected). On a
    // validation-failure redisplay, hasOldInput() is true and we trust the
    // (possibly missing) old value — an unchecked box comes back correctly
    // unchecked instead of falling through to the stale $item->{$name}.
    $is_redisplay = session()->hasOldInput();

    if (! $is_multi) {
        $single_checked = $is_redisplay
            ? (bool) old($name)
            : (bool) ($item?->{$name} ?? false);

        // Helper::checkIfRequired dereferences $item statically via $item::rules(),
        // so it needs a real class/object. Fall back to false when no model was
        // supplied (transient forms have no persistent model).
        $really_required = $required ?? ($item ? Helper::checkIfRequired($item, $name) : false);
    } else {
        // For multi mode, callers can pass :selected as an array of currently-
        // selected values, a comma-joined string (common when the model stores
        // it that way, e.g. Setting's modellist_displays), or a callable
        // (value): bool for per-value predicates. When :selected is omitted
        // the same fallback is applied to $item->{$name}.
        if ($selected === null) {
            $selected = $item?->{$name};
        }

        if (is_string($selected)) {
            $selected = $selected === '' ? [] : array_map('trim', explode(',', $selected));
        }

        $old_values = is_array(old($name)) ? old($name) : [];

        $is_checked = function ($value) use ($is_redisplay, $old_values, $selected) {
            if ($is_redisplay) {
                return in_array($value, $old_values);
            }
            if (is_callable($selected)) {
                return (bool) $selected($value);
            }
            return in_array($value, is_array($selected) ? $selected : []);
        };
    }

    $errors_class = $errors->has($name) ? ' has-error' : '';
@endphp

<div {{ $attributes->merge(['class' => 'form-group'.$errors_class]) }}>

    @if (! $is_multi)

        {{-- Single checkbox: no left-hand label column; label wraps the input. --}}
        <div class="{{ $input_div_class }}">
            <label class="form-control">
                <x-input.checkbox
                    :name="$name"
                    :id="$name"
                    :value="$value"
                    :checked="$single_checked"
                    :required="$really_required"
                    :disabled="$disabled"
                    :aria-label="$name"
                />
                {{ $label }}
            </label>
        </div>

    @else

        {{-- Multi: standard left-hand label + a stack of wrapped checkboxes on the right. --}}
        @if (isset($label))
            <x-form.label :for="$name" class="col-md-3">{{ $label }}</x-form.label>
        @endif

        <div class="{{ $input_div_class }}">
            @foreach ($options as $option_value => $option_label)
                <label class="form-control">
                    <x-input.checkbox
                        :name="$name.'[]'"
                        :value="$option_value"
                        :checked="$is_checked($option_value)"
                        :disabled="$disabled"
                        :aria-label="$name"
                    />
                    {{ $option_label }}
                </label>
            @endforeach
        </div>

    @endif

    @if ($info_tooltip_text)
        <div class="col-md-1 text-left" style="padding-left:0; margin-top: 5px;">
            <x-form.tooltip>
                {{ $info_tooltip_text }}
            </x-form.tooltip>
        </div>
    @endif

    @error($name)
        <div class="col-md-8 col-md-offset-3">
            <span class="alert-msg" aria-hidden="true">
                <x-icon type="x" />
                {{ $message }}
            </span>
        </div>
    @enderror

    @if ($help_text)
        <div class="col-md-8 col-md-offset-3">
            <p class="help-block">
                {!! $help_text !!}
            </p>
        </div>
    @endif

</div>
