<?php

namespace GovStore\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Company;

class CompanyAdmin extends Model
{
    protected $table = 'gov_company_admins';

    protected $fillable = [
        'user_id',
        'company_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScopes();
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}