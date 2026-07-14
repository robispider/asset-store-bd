<?php

namespace GovStore\StoreOperations\Adapters;

use GovStore\StoreOperations\Contracts\StockableInterface;
use App\Models\Component;
use Illuminate\Support\Facades\DB;
use Exception;

class ComponentAdapter implements StockableInterface
{
    protected $model;

    public function __construct(int $id)
    {
        $this->model = Component::findOrFail($id);
    }

    public function getCurrentQuantity(): int
    {
        return (int) $this->model->qty;
    }

    public function incrementQuantity(int $qty): void
    {
        DB::table('components')->where('id', $this->model->id)->increment('qty', $qty);
    }

    public function decrementQuantity(int $qty): void
    {
        if ($this->getCurrentQuantity() < $qty) {
            throw new Exception("Insufficient stock for Component: {$this->getDisplayName()}");
        }
        DB::table('components')->where('id', $this->model->id)->decrement('qty', $qty);
    }

    public function getDisplayName(): string
    {
        return $this->model->name;
    }
}
