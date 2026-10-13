<?php

namespace GovStore\StoreOperations\Services;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * GRN-only stock intake (config `govstore-intake`). Decides which Snipe-IT create paths are
 * closed and watches stock imports row by row while EnforceGrnIntake runs one.
 */
class IntakePolicy
{
    private bool $watchingImport = false;

    private ?string $importViolation = null;

    public function active(): bool
    {
        return config('govstore-intake.mode', 'grn_only') === 'grn_only';
    }

    /** @return string[] Route names closed to everyone, super users included. */
    public function blockedRoutes(): array
    {
        $routes = config('govstore-intake.routes.assets', []);
        if (config('govstore-intake.block_licenses', false)) {
            $routes = array_merge($routes, config('govstore-intake.routes.licenses', []));
        }

        return $routes;
    }

    public function blocks(string $routeName): bool
    {
        return $this->active() && in_array($routeName, $this->blockedRoutes(), true);
    }

    public function requiresZeroQuantity(string $routeName): bool
    {
        return $this->active() && in_array($routeName, config('govstore-intake.zero_quantity_routes', []), true);
    }

    public function isStockImport(string $routeName, ?string $importType): bool
    {
        return $this->active() && $routeName === config('govstore-intake.import_route')
            && in_array($importType, config('govstore-intake.stock_import_types', []), true);
    }

    public function ledgerOpened(int $locationId): bool
    {
        return Schema::hasTable('gov_store_ledger_openings')
            && DB::table('gov_store_ledger_openings')->where('location_id', $locationId)->exists();
    }

    public function watchImport(bool $watching): void
    {
        $this->watchingImport = $watching;
        if ($watching) {
            $this->importViolation = null;
        }
    }

    public function importViolation(): ?string
    {
        return $this->importViolation;
    }

    /**
     * Model `saving` hook for stock rows written by an import: an office whose ledger is open
     * takes stock through store documents only. Throwing rolls the whole import back.
     */
    public function checkImportedRecord(Model $record): void
    {
        if (! $this->watchingImport) {
            return;
        }
        $locationId = (int) ($record instanceof Asset ? ($record->rtd_location_id ?: $record->location_id) : $record->getAttribute('location_id'));
        if ($locationId && $this->ledgerOpened($locationId)) {
            $this->importViolation = __('storeops::intake.import_ledger_open', [
                'item' => (string) ($record->getAttribute('name') ?: $record->getAttribute('asset_tag') ?: $record->getKey() ?: '?'),
            ]);

            throw ValidationException::withMessages(['import' => $this->importViolation]);
        }
    }
}
