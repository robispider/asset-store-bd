<?php

namespace GovStore\Classification\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Category;
use App\Models\Company;
use App\Models\User;

class CategoryGovernance extends Model
{
    protected $table = 'gov_category_governance';

    protected $fillable = [
        'category_id', 'governance_type', 'created_by_company_id', 'created_by_user_id'
    ];

    public function category() { return $this->belongsTo(Category::class); }
    public function originatingCompany() { return $this->belongsTo(Company::class, 'created_by_company_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by_user_id'); }
}