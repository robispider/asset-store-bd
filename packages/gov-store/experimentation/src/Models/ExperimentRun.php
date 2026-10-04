<?php

namespace GovStore\Experimentation\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExperimentRun extends Model
{
    use HasUuids;

    protected $table = 'gov_experiment_runs';

    protected $guarded = [];

    protected $hidden = ['password'];

    protected $casts = ['password' => 'encrypted', 'report' => 'array', 'anchor_date' => 'datetime', 'wiped_at' => 'datetime'];
}
