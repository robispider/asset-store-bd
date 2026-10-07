<?php

namespace GovStore\TenantScope\Services;

use Illuminate\Database\Eloquent\Model;

/** Process-local schema knowledge, never a cache of memberships or authorization. */
class SchemaKnowledge
{
    private array $columns = [];

    public function hasColumn(Model $model, string $column): bool
    {
        $connection = $model->getConnection();
        $key = implode('|', [$connection->getName(), $connection->getDatabaseName(), $model->getTable()]);
        $this->columns[$key] ??= $connection->getSchemaBuilder()->getColumnListing($model->getTable());

        return in_array($column, $this->columns[$key], true);
    }

    public function clear(): void
    {
        $this->columns = [];
    }
}
