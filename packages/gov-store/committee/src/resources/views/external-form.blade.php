<form class="cm-command" action="{{ route('committee.command.external') }}" method="post">@csrf
    @foreach(['full_name_bn','full_name_en','designation_bn','designation_en','organization_name_bn','organization_name_en'] as $field) @include('committee::field',['name'=>$field,'required'=>true]) @endforeach
    @include('committee::field',['name'=>'organization_kind','options'=>$enumOptions('UNIVERSITY GOVERNMENT AUTONOMOUS PRIVATE_EXPERT DEVELOPMENT_PARTNER OTHER')])
    @include('committee::field',['name'=>'mobile']) @include('committee::field',['name'=>'email','type'=>'email'])
    <x-gov-action ability="committee.manage" type="submit" class="btn btn-primary">{{ __('committee::committee.external') }}</x-gov-action>
</form>
