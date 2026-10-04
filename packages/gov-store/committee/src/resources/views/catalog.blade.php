<div class="box box-warning"><div class="box-header"><h2 class="box-title">{{ __('committee::committee.'.$mode) }}</h2></div><div class="box-body">
@if($mode === 'types')
    <p class="alert alert-info">{{ __('committee::committee.policy_notice') }}</p>
    @foreach($types->push(null) as $t)
        @php($p = $t?->composition_policy ?? ['strength'=>['min'=>3,'max'=>5],'secretary'=>['min'=>0],'external'=>['min'=>0,'outside'=>'office'],'technical_expert'=>['min'=>0],'quorum'=>['min_present'=>2],'max_term_months'=>12,'declaration_required'=>false])
        <details><summary>{{ $t?->name_bn ?? __('committee::committee.new') }} · {{ $t?->code }}</summary>
        <form class="cm-command cm-catalog" action="{{ $t ? route('committee.command.type.update',$t->id) : route('committee.command.type') }}" method="post" @if($t) data-method="PUT" @endif>@csrf
            @include('committee::field',['name'=>'scope','options'=>$enumOptions('ministry national'),'value'=>$t && !$t->owner_company_id ? 'national' : 'ministry'])
            @include('committee::field',['name'=>'code','label'=>'code_label','value'=>$t?->code,'required'=>true])
            @include('committee::field',['name'=>'name_en','value'=>$t?->name_en,'required'=>true]) @include('committee::field',['name'=>'name_bn','value'=>$t?->name_bn,'required'=>true])
            @include('committee::field',['name'=>'category','options'=>$enumOptions('inventory disposal audit other'),'value'=>$t?->category])
            @include('committee::field',['name'=>'default_term_basis','label'=>'term_basis','options'=>$enumOptions('FISCAL_YEAR FIXED SINGLE_MATTER UNTIL_FURTHER_ORDER'),'value'=>$t?->default_term_basis])
            <fieldset><legend>{{ __('committee::committee.allowed_scope_types') }}</legend>
                @foreach(app(\GovStore\Committee\Contracts\ScopeTypeRegistry::class)->keys() as $scopeType)
                    <label><input type="checkbox" name="allowed_scope_types[]" value="{{ $scopeType }}" @checked(in_array($scopeType,$t?->allowed_scope_types ?? ['office','store']))> {{ __('committee::committee.options.'.$scopeType) }}</label>
                @endforeach
            </fieldset>
            <input type="hidden" name="allow_concurrent" value="0"><label><input type="checkbox" name="allow_concurrent" value="1" @checked($t?->allow_concurrent)> {{ __('committee::committee.allow_concurrent') }}</label>
            @include('committee::field',['name'=>'composition_policy[strength][min]','label'=>'min_strength','type'=>'number','value'=>$p['strength']['min'],'required'=>true])
            @include('committee::field',['name'=>'composition_policy[strength][max]','label'=>'max_strength','type'=>'number','value'=>$p['strength']['max'],'required'=>true])
            <input type="hidden" name="composition_policy[strength][odd_only]" value="0"><label><input type="checkbox" name="composition_policy[strength][odd_only]" value="1" @checked($p['strength']['odd_only'] ?? false)> {{ __('committee::committee.odd_only') }}</label>
            <input type="hidden" name="composition_policy[presiding][exactly]" value="1">
            <fieldset><legend>{{ __('committee::committee.presiding_roles') }}</legend>
                @foreach(['chairperson','convener'] as $roleCode)<label><input type="checkbox" name="composition_policy[presiding][roles][]" value="{{ $roleCode }}" @checked(in_array($roleCode,$p['presiding']['roles'] ?? ['chairperson','convener']))> {{ $roles->firstWhere('code',$roleCode)?->name_bn }}</label>@endforeach
            </fieldset>
            @include('committee::field',['name'=>'composition_policy[secretary][min]','label'=>'secretary_min','type'=>'number','value'=>$p['secretary']['min'],'required'=>true])
            <input type="hidden" name="composition_policy[secretary][max]" value="{{ $p['secretary']['max'] ?? 1 }}">
            @include('committee::field',['name'=>'composition_policy[external][min]','label'=>'external_min','type'=>'number','value'=>$p['external']['min'],'required'=>true])
            @include('committee::field',['name'=>'composition_policy[external][outside]','label'=>'outside','options'=>$enumOptions('office ministry'),'value'=>$p['external']['outside']])
            @include('committee::field',['name'=>'composition_policy[technical_expert][min]','label'=>'technical_min','type'=>'number','value'=>$p['technical_expert']['min'],'required'=>true])
            @include('committee::field',['name'=>'composition_policy[quorum][min_present]','label'=>'quorum','type'=>'number','value'=>$p['quorum']['min_present'] ?? null])
            @include('committee::field',['name'=>'composition_policy[max_term_months]','label'=>'max_term','type'=>'number','value'=>$p['max_term_months'] ?? null])
            @include('committee::field',['name'=>'composition_policy[nomination]','label'=>'nomination','options'=>$enumOptions('BY_POST BY_NAME BY_POST_OR_NAME'),'value'=>$p['nomination'] ?? 'BY_POST_OR_NAME'])
            <input type="hidden" name="composition_policy[declaration_required]" value="0"><label><input type="checkbox" name="composition_policy[declaration_required]" value="1" @checked($p['declaration_required'])> {{ __('committee::committee.declaration_required') }}</label>
            @foreach($p['incompatible_duties'] ?? [] as $dutyIndex=>$duty)
                <input type="hidden" name="composition_policy[incompatible_duties][{{ $dutyIndex }}][duty]" value="{{ $duty['duty'] }}">
                @include('committee::field',['name'=>'composition_policy[incompatible_duties]['.$dutyIndex.'][severity]','label'=>'incompatible_duties','options'=>$enumOptions('WARN BLOCK'),'value'=>$duty['severity']])
            @endforeach
            <input type="hidden" name="is_active" value="0"><label><input type="checkbox" name="is_active" value="1" @checked($t?->is_active)> {{ __('committee::committee.is_active') }}</label>
            @include('committee::field',['name'=>'change_reason','required'=>true])
            <x-gov-action ability="committee.types.manage" type="submit" class="btn btn-primary">{{ __('committee::committee.save') }}</x-gov-action>
        </form></details>
    @endforeach
@else
    @foreach($bindings->push(null) as $binding)
    <details><summary>{{ $binding?->purpose_code ?? __('committee::committee.binding') }} · {{ $types->firstWhere('id',$binding?->committee_type_id)?->name_bn }}</summary>
    <form class="cm-command cm-catalog" action="{{ $binding ? route('committee.command.binding.update',$binding->id) : route('committee.command.binding') }}" method="post" @if($binding) data-method="PUT" @endif>@csrf
        @include('committee::field',['name'=>'scope','options'=>$enumOptions('ministry national'),'value'=>$binding && !$binding->owner_company_id ? 'national' : 'ministry'])
        @include('committee::field',['name'=>'purpose_code','options'=>collect($purposes)->mapWithKeys(fn ($p) => [$p->code=>$p->labelBn.' / '.$p->labelEn])->all(),'value'=>$binding?->purpose_code])
        @include('committee::field',['name'=>'committee_type_id','label'=>'type','options'=>$types->mapWithKeys(fn ($t) => [$t->id=>$t->name_bn])->all(),'value'=>$binding?->committee_type_id])
        @include('committee::field',['name'=>'priority','type'=>'number','value'=>$binding?->priority ?? 0,'required'=>true])
        <input type="hidden" name="allow_ancestor_fallback" value="0"><label><input type="checkbox" name="allow_ancestor_fallback" value="1" @checked($binding?->allow_ancestor_fallback)> {{ __('committee::committee.allow_ancestor_fallback') }}</label>
        <input type="hidden" name="is_active" value="0"><label><input type="checkbox" name="is_active" value="1" @checked(!$binding || $binding->is_active)> {{ __('committee::committee.is_active') }}</label>
        @include('committee::field',['name'=>'change_reason','required'=>true])
        <x-gov-action ability="committee.purposes.manage" type="submit" class="btn btn-primary">{{ __('committee::committee.binding') }}</x-gov-action>
    </form></details>
    @endforeach
@endif
</div></div>
