<?php

namespace GovStore\Metadata\Models;

use Illuminate\Database\Eloquent\Model;

class MetadataFieldMapping extends Model
{
    protected $table = 'gov_metadata_field_mappings';
    protected $fillable = ['identifier', 'custom_field_id'];
}
