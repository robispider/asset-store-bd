<?php

namespace GovStore\Experimentation\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ExperimentAccounts
{
    public function __construct(private BangladeshPeople $names) {}

    public function assignUsername(User $person): void
    {
        // The native ID supplies a numeric suffix across datasets. Preserve unrelated names on collision.
        $number = (int) $person->id;
        do {
            $username = $this->names->username($person->getAttributes(), $number++);
        } while (User::withoutGlobalScopes()->withTrashed()->where('username', $username)->where('id', '!=', $person->id)->exists());

        if ($person->username !== $username) {
            $person->forceFill(['username' => $username])->saveQuietly();
        }
    }

    public function update(User $person): void
    {
        $this->assignUsername($person);
        if (! Hash::check(BangladeshPeople::PASSWORD, $person->password)) {
            $person->forceFill(['password' => Hash::make(BangladeshPeople::PASSWORD)])->saveQuietly();
        }
    }
}
