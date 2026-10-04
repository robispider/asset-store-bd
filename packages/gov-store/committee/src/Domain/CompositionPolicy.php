<?php

namespace GovStore\Committee\Domain;

use Illuminate\Support\Facades\Validator;

final class CompositionPolicy
{
    public static function validate(array $data): array
    {
        Validator::make($data,[
            'strength.min'=>'required|integer|min:1|max:100', 'strength.max'=>'required|integer|gte:strength.min|max:100',
            'strength.odd_only'=>'required|boolean', 'presiding.exactly'=>'required|integer|min:1|max:1',
            'presiding.roles'=>'required|array|min:1', 'presiding.roles.*'=>'in:chairperson,convener',
            'secretary.min'=>'required|integer|min:0|max:1', 'secretary.max'=>'required|integer|gte:secretary.min|max:1',
            'external.min'=>'required|integer|min:0|max:100', 'external.outside'=>'required|in:office,ministry',
            'technical_expert.min'=>'required|integer|min:0|max:100', 'declaration_required'=>'required|boolean',
            'quorum.min_present'=>'nullable|integer|min:1|lte:strength.max', 'nomination'=>'required|in:BY_POST,BY_NAME,BY_POST_OR_NAME',
            'max_term_months'=>'nullable|integer|min:1|max:120', 'incompatible_duties'=>'present|array',
            'incompatible_duties.*.duty'=>'required|string', 'incompatible_duties.*.severity'=>'required|in:WARN,BLOCK',
        ])->validate();
        foreach (['strength'=>['min','max'],'presiding'=>['exactly'],'secretary'=>['min','max'],'external'=>['min'],'technical_expert'=>['min'],'quorum'=>['min_present']] as $group=>$keys) {
            foreach ($keys as $key) { if (isset($data[$group][$key])) { $data[$group][$key] = (int)$data[$group][$key]; } }
        }
        $data['strength']['odd_only'] = (bool)$data['strength']['odd_only'];
        $data['declaration_required'] = (bool)$data['declaration_required'];
        if (isset($data['max_term_months'])) { $data['max_term_months'] = (int)$data['max_term_months']; }
        return $data;
    }
}
