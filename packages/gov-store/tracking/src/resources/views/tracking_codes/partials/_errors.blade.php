@if (isset($errors) && $errors->any())
    <x-gs::alert tone="danger" :title="__('govtracking::general.task.check_parameters')" class="gs-tracking-task-errors">
        <ul class="gs-tracking-list">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-gs::alert>
@endif
