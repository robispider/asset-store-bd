<?php

namespace GovStore\TenantScope\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ReferenceRequestTarget
{
    public function resolve(Request $request): Model
    {
        foreach (ReferenceTypes::MODELS as $type => $class) {
            $value = $request->route($type) ?? $request->route($type.'_id');
            if ($value !== null) {
                $id = $value instanceof Model ? $value->getKey() : $value;

                return $class::query()->whereNull('deleted_at')->findOrFail($id);
            }
        }
        abort(404);
    }
}
