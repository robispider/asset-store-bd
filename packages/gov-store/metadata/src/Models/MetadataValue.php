<?php

namespace GovStore\Metadata\Models;

use Illuminate\Database\Eloquent\Model;

class MetadataValue extends Model
{
    protected $table = 'gov_metadata_values';

    protected $fillable = ['subject_type', 'subject_id', 'namespace', 'scope_type', 'scope_id', 'payload', 'revision', 'updated_by'];

    protected $casts = ['payload' => 'array', 'subject_id' => 'integer', 'scope_id' => 'integer', 'revision' => 'integer'];
}
