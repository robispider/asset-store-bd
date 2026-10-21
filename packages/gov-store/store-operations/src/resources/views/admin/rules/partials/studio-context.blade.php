{{-- Shared by the tree, flow and family views: which office and which item the rules are traced for. --}}
<form class="rst-panel rst-context" method="GET" action="{{ route('storeops.admin.rules.index') }}" data-rst-context>
    <input type="hidden" name="view" value="{{ $view }}">
    @if (! $result['location'] && $result['company'])
        <input type="hidden" name="company" value="{{ $result['company']->id }}">
    @endif
    <div class="rst-field">
        <label for="rst-office">{{ __('storeops::studio.received_at') }}</label>
        <select id="rst-office" name="location">
            <option value="">{{ $result['company'] ? __('storeops::studio.any_office_of', ['name' => $result['company']->name]) : __('storeops::studio.no_office') }}</option>
            @foreach ($locations as $location)
                <option value="{{ $location->id }}" @selected($result['location']?->id === $location->id)>{{ $location->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="rst-field">
        <label for="rst-item">{{ __('storeops::studio.item') }}</label>
        <select id="rst-item" name="category" data-rst-clears="rst-model">
            <option value="">{{ __('storeops::studio.no_item') }}</option>
            @foreach ($categories as $kind => $list)
                <optgroup label="{{ __('storeops::studio.kinds.'.$kind) }}">
                    @foreach ($list as $category)
                        <option value="{{ $category->id }}" @selected($result['category']?->id === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </div>
    @if ($models->isNotEmpty())
        <div class="rst-field">
            <label for="rst-model">{{ __('storeops::studio.model') }}</label>
            <select id="rst-model" name="model">
                <option value="">{{ __('storeops::studio.any_model') }}</option>
                @foreach ($models as $model)
                    <option value="{{ $model->id }}" @selected($result['model']?->id === $model->id)>{{ $model->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <button type="submit" class="gs-btn" data-rst-submit>{{ __('storeops::studio.show') }}</button>
</form>
