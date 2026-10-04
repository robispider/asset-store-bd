<div class="box"><div class="box-header"><h2 class="box-title">{{ __('committee::committee.transfers') }}</h2></div><div class="box-body">
    <p>{{ __('committee::committee.transfer_help') }}</p>
    <form class="cm-command cm-transfer" method="post" action="{{ route('committee.command.transfer') }}">@csrf
        @include('committee::field',['name'=>'user_id','options'=>[],'class'=>'cm-transfer-person cm-people'])
        <div class="cm-transfer-seats"></div>
        @include('committee::field',['name'=>'reason','required'=>true])
        <x-gov-action ability="committee.manage" type="submit" class="btn btn-warning">{{ __('committee::committee.apply_transfer') }}</x-gov-action>
    </form>
</div></div>
