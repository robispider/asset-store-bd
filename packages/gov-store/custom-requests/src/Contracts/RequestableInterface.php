<?php

namespace GovStore\CustomRequests\Contracts;

use App\Models\User;

interface RequestableInterface
{
    /** Get the underlying Snipe-IT Eloquent Model */
    public function getModel();

    /** Get the friendly name of the item to show on the Dashboard */
    public function getDisplayName(): string;

    /** Identify what category this is (Asset, Consumable, etc.) */
    public function getType(): string;

    /** Check how many are currently left in stock */
    public function getAvailableQuantity(): int;

    /** The universal method to trigger Snipe-IT's native checkout */
    public function checkout(User $targetUser, User $adminUser, int $quantity = 1, string $notes = ''): bool;
}