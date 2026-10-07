<?php

namespace GovStore\UserOnboarding\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingEvent extends Model
{
    protected $table = 'gov_onboarding_events';

    public $timestamps = false;
}
