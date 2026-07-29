<?php

namespace GovStore\Metadata\Models;

use Illuminate\Database\Eloquent\Model;

class ModelMetadataState extends Model
{
    protected $table = 'gov_model_metadata_states';
    protected $fillable = ['model_id', 'provider_name', 'version'];
}