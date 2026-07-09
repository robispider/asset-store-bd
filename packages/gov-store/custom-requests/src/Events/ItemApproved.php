<?php

namespace GovStore\CustomRequests\Events;

use GovStore\CustomRequests\Models\ItemRequest;
use App\Models\User;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class ItemApproved
{
    use Dispatchable, SerializesModels;

    public $itemRequest;
    public $adminUser;

    public function __construct(ItemRequest $itemRequest, User $adminUser)
    {
        $this->itemRequest = $itemRequest;
        $this->adminUser = $adminUser;
    }
}