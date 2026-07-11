<?php

namespace GovStore\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Location;
use App\Models\User;

class LocationRole extends Model
{
    protected $table = 'gov_location_roles';

    protected $fillable = [
        'location_id',
        'primary_approver_id',
        'primary_delegate_id',
        'primary_delegate_until',
        'final_approver_id',
        'final_delegate_id',
        'final_delegate_until',
        'storekeeper_id',
        'storekeeper_delegate_id',
        'storekeeper_delegate_until',
    ];

    protected $casts = [
        'primary_delegate_until' => 'date',
        'final_delegate_until' => 'date',
        'storekeeper_delegate_until' => 'date',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
public function primaryApprover() { return $this->belongsTo(\App\Models\User::class, 'primary_approver_id')->withoutGlobalScopes(); }
    public function finalApprover() { return $this->belongsTo(\App\Models\User::class, 'final_approver_id')->withoutGlobalScopes(); }
    public function storekeeper() { return $this->belongsTo(\App\Models\User::class, 'storekeeper_id')->withoutGlobalScopes(); }


    public function primaryDelegate()
    {
        return $this->belongsTo(User::class, 'primary_delegate_id');
    }


    public function finalDelegate()
    {
        return $this->belongsTo(User::class, 'final_delegate_id');
    }



    public function storekeeperDelegate()
    {
        return $this->belongsTo(User::class, 'storekeeper_delegate_id');
    }
}