<?php
/**
 * GovStore Live Testing - Read-Only PHP Bridge Adapter
 * Provides read-only fixture discovery, context resolution, and domain observations.
 * NEVER mutates application state, accounts, or manifest records.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

require __DIR__.'/../../../vendor/autoload.php';
$action = $argv[1] ?? 'help';
$rawInput = '{}';
if (in_array('--stdin', $argv, true)) {
    $rawInput = stream_get_contents(STDIN) ?: '{}';
} elseif (isset($argv[2])) {
    $decoded = base64_decode($argv[2], true);
    $rawInput = ($decoded !== false && json_validate($decoded)) ? $decoded : $argv[2];
}
$input = json_decode($rawInput, true) ?: [];
$target = $input['_target'] ?? null;
if ($target && (! in_array($target['environment'] ?? '', ['local', 'staging', 'testing'], true)
    || ! isset($target['database']['host'], $target['database']['database'], $target['database']['username'])
    || ($target['database']['driver'] ?? 'mysql') !== 'mysql')) {
    echo json_encode(['status' => 'error', 'code' => 'preflight.target-config', 'message' => 'Invalid nonproduction target database configuration.']);
    exit(1);
}
$app = require_once __DIR__.'/../../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
if ($target) {
    $db = $target['database'];
    $options = [];
    if (! empty($db['sslCa'])) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $db['sslCa'];
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    config(['database.default' => 'live_target', 'database.connections.live_target' => [
        'driver' => 'mysql', 'url' => null, 'host' => $db['host'], 'port' => $db['port'] ?? 3306,
        'database' => $db['database'], 'username' => $db['username'], 'password' => $db['password'] ?? '',
        'unix_socket' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '', 'strict' => true, 'engine' => null, 'options' => $options,
    ]]);
}

use App\Models\Consumable;
use App\Models\Location;
use App\Models\User;
use GovStore\Experimentation\Models\ExperimentRun;
use GovStore\Experimentation\Services\BangladeshPeople;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Semantic capability aliases (alias => {ability|native}) from tests/live/capabilities.json. */
function capabilityAliases(): array {
    $file = __DIR__.'/../../../tests/live/capabilities.json';
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

    return $data['capabilities'] ?? [];
}

/** Effective office-scoped roles, mirroring GovAccess::roles() without TenantContext or writes. */
function officeRoles(int $userId, int $officeId, int $companyId, bool $isSuper): array {
    $roles = ['authenticated'];
    if ($isSuper) {
        $roles[] = 'superuser';
    }
    if (DB::table('gov_company_admins')->where('user_id', $userId)->where('company_id', $companyId)->exists()) {
        $roles[] = 'company_admin';
    }
    if (DB::table('gov_ict_jurisdictions')->where('user_id', $userId)->exists()) {
        $roles[] = 'ict_officer';
    }
    if (DB::table('gov_location_profiles')->where('location_id', $officeId)->where('office_admin_id', $userId)->exists()) {
        $roles[] = 'office_admin';
    }
    $roles = array_merge($roles, DB::table('gov_office_responsibilities')
        ->where('location_id', $officeId)->where('user_id', $userId)->pluck('role_slug')->all());
    $roles = array_merge($roles, DB::table('gov_access_grants')
        ->where('location_id', $officeId)->where('user_id', $userId)
        ->where('expires_at', '>', now())->pluck('role_slug')->all());

    return array_values(array_unique($roles));
}

/** Evaluate a semantic capability; null when the alias is unmapped. */
function hasCapability(string $alias, array $roles, int $userId): ?bool {
    $abilities = config('govstore-abilities', []);
    $map = capabilityAliases()[$alias] ?? (isset($abilities[$alias]) ? ['ability' => $alias] : null);
    if ($map === null) {
        return null;
    }
    if (isset($map['ability'])) {
        $definition = $abilities[$map['ability']] ?? null;

        return $definition ? (bool) array_intersect($roles, $definition['roles']) : null;
    }
    if (isset($map['native'])) {
        $user = User::find($userId);

        return $user ? (bool) $user->hasAccess($map['native']) : false;
    }

    return null;
}

function jsonResponseError(string $message, string $code, array $extra): void {
    echo json_encode(['status' => 'error', 'code' => $code, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(1);
}

function jsonResponse(array $data, int $status = 0): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit($status);
}

function jsonError(string $message, string $code = 'error', int $status = 1): void {
    echo json_encode([
        'status' => 'error',
        'code' => $code,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit($status);
}

try {
    switch ($action) {
        case 'datasets':
            $runs = DB::table('gov_experiment_runs')
                ->select(['id', 'label', 'profile', 'status', 'phase', 'created_at'])
                ->where('status', 'ready')
                ->whereNull('wiped_at')
                ->orderBy('created_at', 'desc')
                ->get();
            jsonResponse(['status' => 'success', 'datasets' => $runs]);

        case 'fingerprint':
            // Diagnostic hash of the tables discovery must never change (used by the bridge read-only test).
            $hash = fn (string $table, array $cols) => md5(json_encode(DB::table($table)->orderBy($cols[0])->get($cols)));
            jsonResponse([
                'status' => 'success',
                'users' => $hash('users', ['id', 'password', 'permissions', 'updated_at', 'deleted_at']),
                'manifest' => $hash('gov_experiment_records', ['id', 'run_id', 'table_name', 'record_key']),
                'runs' => $hash('gov_experiment_runs', ['id', 'status', 'password', 'updated_at', 'wiped_at']),
                'memberships' => $hash('gov_office_memberships', ['id', 'user_id', 'location_id', 'status']),
                'responsibilities' => $hash('gov_office_responsibilities', ['id', 'user_id', 'location_id', 'role_slug']),
                'committees' => $hash('gov_committees', ['id', 'lineage_id', 'status', 'lock_version', 'updated_at', 'deleted_at']),
                'committeeOrders' => $hash('gov_committee_orders', ['id', 'committee_id', 'kind', 'memo_no', 'recorded_at']),
                'committeeLedger' => $hash('gov_committee_ledger', ['id', 'lineage_id', 'committee_id', 'event_type', 'hash']),
                'locations' => $hash('locations', ['id', 'name', 'company_id', 'parent_id', 'updated_at', 'deleted_at']),
                'officeProfiles' => $hash('gov_location_profiles', ['id', 'location_id', 'lifecycle_status', 'office_admin_id', 'geo_area_id', 'geo_area_verified_at', 'updated_at']),
                'ictJurisdictions' => $hash('gov_ict_jurisdictions', ['id', 'user_id', 'geo_area_id']),
                'companyAdmins' => $hash('gov_company_admins', ['id', 'user_id', 'company_id']),
                'metadata' => $hash('gov_metadata_values', ['id', 'subject_type', 'subject_id', 'namespace', 'revision', 'updated_at']),
                'registrations' => $hash('gov_asset_registrations', ['id', 'asset_id', 'asset_tag', 'format_version']),
                'financeItems' => $hash('gov_finance_items', ['gov_code', 'status', 'unspsc_code', 'updated_at']),
                'profileAssignments' => $hash('gov_profile_assignments', ['id', 'profile_id', 'target_type', 'target_id', 'effective_to']),
                'documents' => $hash('gov_documents', ['id', 'status', 'updated_at']),
                'settings' => $hash('settings', ['id', 'auto_increment_assets', 'auto_increment_prefix', 'next_auto_tag_base']),
            ]);

        case 'preflight':
            $dbName = DB::connection()->getDatabaseName();
            $runs = DB::table('gov_experiment_runs')->where('status', 'ready')->whereNull('wiped_at')->count();
            jsonResponse([
                'status' => 'success',
                'environment' => $target['environment'] ?? app()->environment(),
                'production' => app()->isProduction(),
                'appUrl' => $target['baseUrl'] ?? config('app.url'),
                'database' => $dbName,
                'accessMode' => $target ? ($target['accessMode'] ?? 'unknown') : config('govstore-access.mode'),
                'mailer' => $target ? 'unknown' : config('mail.default'),
                'queue' => $target ? 'unknown' : config('queue.default'),
                'configurationSource' => $target ? 'selected-target-file' : 'local-application',
                'readyDatasets' => $runs,
            ]);

        case 'verifyAccount':
            $userQuery = User::withoutGlobalScopes()->where('activated', true)->whereNull('deleted_at');
            if (isset($input['userId'])) {
                $userId = filter_var($input['userId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (! $userId) jsonError('A valid supplied user ID is required.', 'credentials.invalid-user-id');
                $userQuery->whereKey($userId);
                if (! empty($input['username'])) $userQuery->where('username', $input['username']);
            } else {
                $userQuery->where('username', $input['username'] ?? '');
            }
            $user = $userQuery->first();
            if (! $user || ! is_string($input['password'] ?? null) || ! Hash::check($input['password'], $user->password)) {
                jsonError('The supplied account is not active or its credentials do not match this target database.', 'credentials.target-database-mismatch');
            }
            jsonResponse(['status' => 'success', 'id' => (int) $user->id,
                'username' => $user->username, 'isSuperuser' => $user->isSuperUser()]);

        case 'resolveContext':
            $datasetId = $input['datasetId'] ?? null;
            $query = DB::table('gov_experiment_runs')->where('status', 'ready')->whereNull('wiped_at');
            if ($datasetId) {
                $query->where('id', $datasetId);
            }
            $run = $query->orderBy('created_at', 'desc')->first();

            if (! $run) {
                jsonError('No ready experiment dataset found', 'fixtures.unsatisfied-requirements');
            }

            // Find manifest-owned users for this run
            $ownedUserRecords = DB::table('gov_experiment_records')
                ->where('run_id', $run->id)
                ->where('table_name', 'users')
                ->get();

            $ownedUserIds = [];
            foreach ($ownedUserRecords as $rec) {
                $key = json_decode($rec->record_key, true);
                if (isset($key['id'])) {
                    $ownedUserIds[] = (int) $key['id'];
                }
            }

            if (empty($ownedUserIds)) {
                jsonError('No owned users found in ready dataset', 'fixtures.unsatisfied-requirements');
            }

            // Find candidate offices with members and consumables
            $officeReqs = $input['office']['requirements'] ?? [];
            $requestedOfficeId = $input['office']['id'] ?? null;

            $officeQuery = DB::table('locations as l')
                ->join('gov_experiment_records as own', function ($join) use ($run) {
                    $join->on(DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(own.record_key, '$.id')) AS UNSIGNED)"), '=', 'l.id')
                        ->where('own.table_name', '=', 'locations')
                        ->where('own.run_id', '=', $run->id);
                })
                ->select('l.id', 'l.name', 'l.company_id');

            if ($requestedOfficeId) {
                $officeQuery->where('l.id', $requestedOfficeId);
            }
            // ledgerOpen: the office has a stock-ledger opening record (GRN posting needs one).
            if (! empty($officeReqs['ledgerOpen'])) {
                $officeQuery->whereIn('l.id', DB::table('gov_store_ledger_openings')->select('location_id'));
            }

            $offices = $officeQuery->orderBy('l.id')->get();
            $resolvedOffice = null;
            $resolvedActors = [];
            $resolvedRecords = [];
            $search = ['offices' => 0, 'users' => 0, 'rejected' => []];
            $reject = function (string $reason) use (&$search) {
                $search['rejected'][$reason] = ($search['rejected'][$reason] ?? 0) + 1;
            };

            $actorDefs = $input['actors'] ?? [];
            $recordDefs = $input['records'] ?? [];
            $ownedIds = fn (string $table) => DB::table('gov_experiment_records')->where('run_id', $run->id)->where('table_name', $table)
                ->pluck('record_key')->map(fn ($key) => (int) (json_decode($key, true)['id'] ?? 0))->filter()->values()->all();

            // One actor in one office: least privilege first (fewest effective roles, then lowest id).
            $resolveActor = function (array $actorDef, int $officeId, int $companyId, array $usedUserIds) use ($ownedUserIds, $reject, &$search, $input, $target, $run): ?array {
                $reqRole = $actorDef['role'] ?? null;
                $reqCaps = $actorDef['capabilities']['allOf'] ?? [];
                // activeMembership false declares an area-, company- or nation-scoped actor who need not be a member.
                $candidateUsers = DB::table('users as u')
                    ->when(($actorDef['activeMembership'] ?? true) !== false, fn ($q) => $q
                        ->join('gov_office_memberships as m', 'm.user_id', '=', 'u.id')
                        ->where('m.location_id', $officeId)
                        ->where('m.status', 'active')
                        ->where(fn ($w) => $w->whereNull('m.valid_until')->orWhere('m.valid_until', '>=', today()->toDateString())))
                    ->whereIn('u.id', $ownedUserIds)
                    ->when($reqRole === 'ict_officer', fn ($q) => $q->whereIn('u.id', DB::table('gov_ict_jurisdictions')->select('user_id')))
                    ->when($reqRole === 'company_admin', fn ($q) => $q->whereIn('u.id', DB::table('gov_company_admins')->where('company_id', $companyId)->select('user_id')))
                    ->where('u.activated', true)
                    ->whereNull('u.deleted_at')
                    ->when($usedUserIds, fn ($q) => $q->whereNotIn('u.id', $usedUserIds))
                    ->select('u.id', 'u.username', 'u.first_name', 'u.last_name', 'u.display_name', 'u.password', 'u.permissions')
                    ->get();

                $scored = [];
                foreach ($candidateUsers as $candUser) {
                    $isSuper = (bool) ($candUser->permissions && preg_match('/"superuser":"?1"?/', $candUser->permissions));
                    $scored[] = [$candUser, officeRoles((int) $candUser->id, $officeId, $companyId, $isSuper), $isSuper];
                }
                usort($scored, fn ($a, $b) => [count($a[1]), $a[0]->id] <=> [count($b[1]), $b[0]->id]);

                foreach ($scored as [$candUser, $roles, $isSuper]) {
                    $search['users']++;
                    if ($isSuper && empty($actorDef['allowSuperuser'])) {
                        $reject('superuser-not-requested');
                        continue;
                    }
                    if (! empty($actorDef['emptyBasket']) && DB::table('draft_basket_items as i')
                        ->join('draft_baskets as b', 'b.id', '=', 'i.basket_id')
                        ->where('b.user_id', $candUser->id)->where('b.status', 'draft')->exists()) {
                        $reject('basket-not-empty');
                        continue;
                    }
                    if ($reqRole && ! in_array($reqRole, $roles, true)) {
                        $reject('missing-role:'.$reqRole);
                        continue;
                    }
                    $capOk = true;
                    foreach ($reqCaps as $cap) {
                        $has = hasCapability((string) $cap, $roles, (int) $candUser->id);
                        if ($has === null) {
                            jsonError("Capability alias '{$cap}' is not mapped in tests/live/capabilities.json", 'fixtures.unknown-capability');
                        }
                        if (! $has) {
                            $capOk = false;
                            $reject('missing-capability:'.$cap);
                            break;
                        }
                    }
                    foreach (($actorDef['capabilities']['noneOf'] ?? []) as $cap) {
                        if (hasCapability((string) $cap, $roles, (int) $candUser->id)) {
                            $capOk = false;
                            $reject('denied-capability-present:'.$cap);
                            break;
                        }
                    }
                    if (! $capOk) {
                        continue;
                    }

                    // Verify credential privately
                    $verifiedPassword = null;
                    $targetPassword = $input['fixtureCredentials'][$candUser->username] ?? null;
                    if (is_string($targetPassword) && Hash::check($targetPassword, $candUser->password)) {
                        $verifiedPassword = $targetPassword;
                    }
                    if (! $target && ! $verifiedPassword && Hash::check(BangladeshPeople::PASSWORD, $candUser->password)) {
                        $verifiedPassword = BangladeshPeople::PASSWORD;
                    }
                    if (! $target && ! $verifiedPassword && isset($run->password)) {
                        try {
                            $decrypted = decrypt($run->password);
                            if (Hash::check($decrypted, $candUser->password)) {
                                $verifiedPassword = $decrypted;
                            }
                        } catch (\Throwable $t) {
                            // ignore decryption error
                        }
                    }
                    if (! $verifiedPassword) {
                        $reject('credential-mismatch');
                        continue;
                    }

                    return [
                        'id' => (int) $candUser->id,
                        'username' => $candUser->username,
                        'display_name' => $candUser->display_name ?: ($candUser->first_name.' '.$candUser->last_name),
                        'password' => $verifiedPassword,
                        'office_id' => $officeId,
                    ];
                }

                return null;
            };

            foreach ($offices as $candOffice) {
                $officeId = (int) $candOffice->id;
                $companyId = (int) $candOffice->company_id;
                $search['offices']++;
                $usedUserIds = [];
                $officeActors = [];
                $officeRecords = [];

                // Office records first (another office for cross-office scenarios), so actors can be bound to them.
                $allRecordsMatched = true;
                foreach ($recordDefs as $recAlias => $recDef) {
                    if (($recDef['kind'] ?? null) !== 'office') {
                        continue;
                    }
                    $taken = array_merge([$officeId], array_column($officeRecords, 'id'));
                    $other = DB::table('locations as l')->whereIn('l.id', $ownedIds('locations'))->whereNotIn('l.id', $taken)->whereNull('l.deleted_at')
                        ->when(array_key_exists('sameCompany', $recDef), fn ($q) => $recDef['sameCompany']
                            ? $q->where('l.company_id', $companyId) : $q->where('l.company_id', '!=', $companyId))
                        ->when(! empty($recDef['ledgerOpen']), fn ($q) => $q->whereIn('l.id', DB::table('gov_store_ledger_openings')->select('location_id')))
                        ->orderBy('l.id')->first(['l.id', 'l.name', 'l.company_id']);
                    if (! $other) {
                        $reject('missing-office-record:'.$recAlias);
                        $allRecordsMatched = false;
                        break;
                    }
                    $officeRecords[$recAlias] = ['id' => (int) $other->id, 'name' => $other->name, 'company_id' => (int) $other->company_id];
                }
                if (! $allRecordsMatched) {
                    continue;
                }

                // Actors: in this office, or in an office record named by the actor's "office" field.
                $allActorsMatched = true;
                foreach ($actorDefs as $actorAlias => $actorDef) {
                    $boundOffice = isset($actorDef['office'], $officeRecords[$actorDef['office']]) ? $officeRecords[$actorDef['office']] : null;
                    $selectedUser = $resolveActor($actorDef, $boundOffice['id'] ?? $officeId, $boundOffice['company_id'] ?? $companyId, $usedUserIds);
                    if (! $selectedUser) {
                        $allActorsMatched = false;
                        break;
                    }
                    $usedUserIds[] = $selectedUser['id'];
                    $officeActors[$actorAlias] = $selectedUser;
                }
                if (! $allActorsMatched) {
                    continue;
                }

                // Other records in this office.
                foreach ($recordDefs as $recAlias => $recDef) {
                    $kind = $recDef['kind'] ?? 'consumable';
                    $minAvail = (int) ($recDef['minimumAvailable'] ?? 0);

                    if ($kind === 'consumable') {
                        $item = DB::table('consumables as c')
                            ->whereIn('c.id', $ownedIds('consumables'))
                            ->where('c.location_id', $officeId)
                            ->where('c.qty', '>=', $minAvail)
                            ->whereNull('c.deleted_at')
                            ->select('c.id', 'c.name', 'c.qty', 'c.category_id', 'c.company_id', 'c.location_id')
                            ->orderBy('c.id', 'asc')
                            ->first();

                        if (! $item) {
                            $allRecordsMatched = false;
                            break;
                        }

                        $officeRecords[$recAlias] = [
                            'id' => (int) $item->id,
                            'name' => $item->name,
                            'quantity' => (int) $item->qty,
                            'category_id' => (int) $item->category_id,
                            'location_id' => (int) $item->location_id,
                            'company_id' => (int) $item->company_id,
                        ];
                    } elseif ($kind === 'assetModel') {
                        // A dataset asset model whose product type (category) name contains categoryContains.
                        $model = DB::table('models as m')->join('categories as c', 'c.id', '=', 'm.category_id')
                            ->whereIn('m.id', $ownedIds('models'))->whereNull('m.deleted_at')
                            ->when(! empty($recDef['categoryContains']), fn ($q) => $q->where('c.name', 'like', '%'.$recDef['categoryContains'].'%'))
                            ->orderBy('m.id')->first(['m.id', 'm.name', 'm.model_number', 'm.category_id', 'c.name as category_name']);
                        if (! $model) {
                            $reject('missing-asset-model:'.$recAlias);
                            $allRecordsMatched = false;
                            break;
                        }
                        $officeRecords[$recAlias] = ['id' => (int) $model->id, 'name' => $model->name, 'model_number' => $model->model_number,
                            'category_id' => (int) $model->category_id, 'category_name' => $model->category_name];
                    } elseif ($kind === 'financeItem') {
                        // National Finance master data (not dataset-owned): the item with exactly this English name.
                        $item = DB::table('gov_finance_items')->where('item_name', $recDef['itemName'] ?? '')->orderBy('gov_code')->first();
                        if (! $item) {
                            $reject('missing-finance-item:'.$recAlias);
                            $allRecordsMatched = false;
                            break;
                        }
                        $officeRecords[$recAlias] = ['id' => $item->gov_code, 'gov_code' => $item->gov_code, 'item_name' => $item->item_name,
                            'status' => $item->status, 'group_name' => $item->group_name];
                    } elseif ($kind !== 'office') {
                        jsonError("Unknown record kind '{$kind}'", 'fixtures.unknown-record-kind');
                    }
                }

                if (! $allRecordsMatched) {
                    continue;
                }

                // Found matching office!
                $resolvedOffice = [
                    'id' => $officeId,
                    'name' => $candOffice->name,
                    'company_id' => $companyId,
                ];
                $resolvedActors = $officeActors;
                $resolvedRecords = $officeRecords;
                break;
            }

            if (! $resolvedOffice) {
                jsonResponseError('Could not find complete matching office, actors, and records', 'fixtures.unsatisfied-requirements', ['search' => $search]);
            }

            jsonResponse([
                'status' => 'success',
                'run' => [
                    'id' => $run->id,
                    'label' => $run->label,
                ],
                'office' => $resolvedOffice,
                'search' => $search,
                'actors' => $resolvedActors,
                'records' => $resolvedRecords,
            ]);

        case 'observe':
            $check = $input['check'] ?? '';
            $args = $input['args'] ?? [];

            switch ($check) {
                case 'inventory.consumable':
                    $id = (int) ($args['id'] ?? 0);
                    $c = DB::table('consumables')->where('id', $id)->first();
                    if (! $c) {
                        jsonError("Consumable with ID {$id} not found", 'observation.not-found');
                    }
                    $fingerprint = md5($c->name.'|'.$c->category_id.'|'.$c->qty.'|'.$c->location_id);
                    jsonResponse([
                        'id' => (int) $c->id,
                        'name' => $c->name,
                        'category_id' => (int) $c->category_id,
                        'company_id' => (int) $c->company_id,
                        'location_id' => (int) $c->location_id,
                        'quantity' => (int) $c->qty,
                        'available' => (int) $c->qty,
                        'businessFingerprint' => $fingerprint,
                    ]);

                case 'request.progress':
                    $id = (int) ($args['id'] ?? 0);
                    $rows = DB::table('custom_service_requests')->where('requested_by', $id)
                        ->where('approval_status', '!=', 'draft')->whereNull('deleted_at')
                        ->select('approval_status', DB::raw('count(*) as c'))->groupBy('approval_status')->pluck('c', 'approval_status')->all();
                    $get = fn (array $keys) => (int) array_sum(array_map(fn ($k) => $rows[$k] ?? 0, $keys));
                    $draftItems = DB::table('draft_basket_items as i')
                        ->join('draft_baskets as b', 'b.id', '=', 'i.basket_id')
                        ->where('b.user_id', $id)->where('b.status', 'draft')->count();
                    jsonResponse([
                        'user_id' => $id,
                        'pending' => $get(['pending_primary', 'pending_final']),
                        'approved' => $get(['approved', 'partially_approved']),
                        'rejected' => $get(['rejected']),
                        'total' => (int) array_sum($rows),
                        'draftItems' => $draftItems,
                    ]);

                case 'request.queues':
                    // Independent restatement of the queue rules behind the navbar inbox and sidebar badges.
                    $uid = (int) ($args['userId'] ?? 0);
                    $officeId = (int) ($args['officeId'] ?? 0);
                    $office = fn () => DB::table('custom_service_requests')->where('office_id', $officeId)->whereNull('deleted_at');
                    jsonResponse([
                        'user_id' => $uid,
                        'office_id' => $officeId,
                        'pendingPrimary' => $office()->where('approval_status', 'pending_primary')->where('requested_by', '!=', $uid)->count(),
                        'pendingFinal' => $office()->where('approval_status', 'pending_final')->where('requested_by', '!=', $uid)
                            ->where(fn ($q) => $q->whereNull('primary_decided_by')->orWhere('primary_decided_by', '!=', $uid))->count(),
                        'awaitingFulfilment' => $office()->whereIn('approval_status', ['approved', 'partially_approved'])
                            ->whereNotIn('fulfillment_status', ['closed', 'issued'])->count(),
                    ]);

                case 'basket.line':
                    $uid = (int) ($args['userId'] ?? 0);
                    $qty = DB::table('draft_basket_items as i')
                        ->join('draft_baskets as b', 'b.id', '=', 'i.basket_id')
                        ->where('b.user_id', $uid)->where('b.status', 'draft')
                        ->where('i.requested_type', (string) ($args['itemType'] ?? ''))
                        ->where('i.requested_id', (int) ($args['itemId'] ?? 0))
                        ->sum('i.requested_qty');
                    jsonResponse(['user_id' => $uid, 'quantity' => (int) $qty]);

                case 'request.list':
                    // Independent restatement of Request::statusKey() and the My requests tabs (plan P0/P2).
                    $uid = (int) ($args['userId'] ?? 0);
                    $officeId = (int) ($args['officeId'] ?? 0);
                    $rows = DB::table('custom_service_requests')->where('requested_by', $uid)->where('office_id', $officeId)
                        ->where('approval_status', '!=', 'draft')->whereNull('deleted_at')
                        ->get(['request_number', 'approval_status', 'fulfillment_status', 'received_at', 'return_requested_at', 'return_document_id']);
                    $statuses = [];
                    $tabs = ['active' => 0, 'needs_you' => 0, 'finished' => 0];
                    foreach ($rows as $r) {
                        $key = $r->return_document_id ? 'request_return_drafted'
                            : ($r->return_requested_at ? 'request_return_requested'
                            : ($r->approval_status === 'rejected' ? 'request_rejected'
                            : ($r->approval_status === 'cancelled' ? 'request_withdrawn'
                            : ($r->received_at ? 'request_received'
                            : ($r->fulfillment_status === 'issued' ? 'request_issued'
                            : (in_array($r->fulfillment_status, ['closed', 'cannot_fulfill'], true) ? 'request_closed_short'
                            : ($r->approval_status === 'pending_final' ? 'request_pending_final'
                            : (in_array($r->approval_status, ['pending_primary', 'submitted', 'under_review'], true) ? 'request_pending_primary'
                            : ($r->fulfillment_status === 'partially_issued' ? 'request_partly_issued' : 'request_approved')))))))));
                        $statuses[$r->request_number] = $key;
                        $tabs[$key === 'request_issued' ? 'needs_you' : (in_array($key, ['request_received', 'request_closed_short', 'request_rejected', 'request_withdrawn'], true) ? 'finished' : 'active')]++;
                    }
                    jsonResponse(['user_id' => $uid, 'office_id' => $officeId, 'total' => count($statuses), 'tabs' => $tabs,
                        'byStatus' => array_count_values($statuses), 'statuses' => $statuses]);

                case 'request.state':
                    $number = (string) ($args['number'] ?? '');
                    $r = DB::table('custom_service_requests')->where('request_number', $number)->first();
                    if (! $r) {
                        jsonError("Request '{$number}' not found", 'observation.not-found');
                    }
                    $items = DB::table('custom_service_request_items')->where('request_id', $r->id)->orderBy('id')
                        ->get(['requested_type', 'requested_id', 'requested_qty', 'approved_qty', 'issued_qty', 'line_approval_status']);
                    jsonResponse([
                        'id' => (int) $r->id,
                        'request_number' => $r->request_number,
                        'requested_by' => (int) $r->requested_by,
                        'office_id' => isset($r->office_id) ? (int) $r->office_id : null,
                        'purpose' => $r->purpose,
                        'approval_status' => $r->approval_status,
                        'fulfillment_status' => $r->fulfillment_status,
                        'primary_decided_by' => isset($r->primary_decided_by) ? (int) $r->primary_decided_by : null,
                        'decided_by' => isset($r->decided_by) ? (int) $r->decided_by : null,
                        'lines_count' => $items->count(),
                        'first_line' => $items->first() ? [
                            'type' => $items->first()->requested_type,
                            'id' => (int) $items->first()->requested_id,
                            'requested_qty' => (int) $items->first()->requested_qty,
                            'approved_qty' => (int) $items->first()->approved_qty,
                            'line_status' => $items->first()->line_approval_status,
                        ] : null,
                    ]);

                case 'office.context':
                    $id = (int) ($args['id'] ?? 0);
                    $loc = DB::table('locations')->where('id', $id)->first();
                    if (! $loc) {
                        jsonError("Location with ID {$id} not found", 'observation.not-found');
                    }
                    $profile = DB::table('gov_location_profiles')->where('location_id', $id)->first();
                    jsonResponse([
                        'id' => (int) $loc->id,
                        'name' => $loc->name,
                        'company_id' => (int) $loc->company_id,
                        'operational' => true,
                        'office_admin_id' => $profile ? (int) $profile->office_admin_id : null,
                    ]);

                case 'actor.assignments':
                    $id = (int) ($args['id'] ?? 0);
                    $u = DB::table('users')->where('id', $id)->first();
                    if (! $u) {
                        jsonError("User with ID {$id} not found", 'observation.not-found');
                    }
                    $memberships = DB::table('gov_office_memberships')->where('user_id', $id)->pluck('location_id')->all();
                    $roles = DB::table('gov_office_responsibilities')->where('user_id', $id)->pluck('role_slug')->all();
                    jsonResponse([
                        'id' => (int) $u->id,
                        'username' => $u->username,
                        'display_name' => $u->display_name,
                        'office_ids' => $memberships,
                        'roles' => $roles,
                        'is_superadmin' => (bool) ($u->permissions && str_contains($u->permissions, '"superuser":"1"')),
                    ]);

                case 'committee.visibility':
                    // Mirrors CommitteeBoundaryScope for a non-company-admin office user (read-only).
                    $officeId = (int) ($args['officeId'] ?? 0);
                    $companyId = (int) ($args['companyId'] ?? 0);
                    $visible = fn ($q) => $q->where('owner_company_id', $companyId)->where(function ($w) use ($officeId) {
                        $w->where('owner_location_id', $officeId)->orWhereExists(function ($s) use ($officeId) {
                            $s->select(DB::raw(1))->from('gov_committee_scopes')
                                ->whereColumn('gov_committee_scopes.committee_id', 'gov_committees.id')
                                ->where('scope_type', 'office')->where('scope_id', (string) $officeId);
                        });
                    });
                    $mine = $visible(DB::table('gov_committees')->whereNull('deleted_at'));
                    $latest = (clone $mine)->orderByDesc('created_at')->orderByDesc('id')->first(['committee_number']);
                    $foreign = DB::table('gov_committees')->whereNull('deleted_at')
                        ->whereNotIn('id', $visible(DB::table('gov_committees')->whereNull('deleted_at'))->select('id'))
                        ->orderByDesc('created_at')->first(['id', 'committee_number']);
                    if (! $foreign) {
                        jsonError('No committee outside this office and company exists to test the boundary', 'observation.not-found');
                    }
                    $count = (clone $mine)->count();
                    jsonResponse([
                        'count' => $count,
                        'firstPage' => min($count, 20),
                        'searchTerm' => $latest->committee_number ?? 'LIVE-NO-SUCH-COMMITTEE',
                        'searchMatches' => $latest ? 1 : 0,
                        'foreignId' => $foreign->id,
                        'foreignNumber' => $foreign->committee_number,
                    ]);

                case 'committee.record':
                    // Pick a real committee visible in the selected office so browser journeys
                    // can verify its detail, history and print views without fixed IDs.
                    $officeId = (int) ($args['officeId'] ?? 0);
                    $companyId = (int) ($args['companyId'] ?? 0);
                    $visible = fn ($q) => $q->where('c.owner_company_id', $companyId)->where(function ($w) use ($officeId) {
                        $w->where('c.owner_location_id', $officeId)->orWhereExists(function ($s) use ($officeId) {
                            $s->select(DB::raw(1))->from('gov_committee_scopes')
                                ->whereColumn('gov_committee_scopes.committee_id', 'c.id')
                                ->where('scope_type', 'office')->where('scope_id', (string) $officeId);
                        });
                    });
                    $record = $visible(DB::table('gov_committees as c')->whereNull('c.deleted_at'))
                        ->select('c.id', 'c.committee_number', 'c.name_en', 'c.status', 'c.lineage_id')
                        ->selectSub(DB::table('gov_committee_seats as s')->selectRaw('count(*)')->whereColumn('s.committee_id', 'c.id'), 'seat_count')
                        ->selectSub(DB::table('gov_committee_orders as o')->selectRaw('count(*)')->whereColumn('o.committee_id', 'c.id'), 'order_count')
                        ->selectSub(DB::table('gov_committee_ledger as l')->selectRaw('count(*)')->whereColumn('l.lineage_id', 'c.lineage_id'), 'history_count')
                        ->orderByDesc('order_count')->orderByDesc('history_count')->orderByDesc('c.created_at')->first();
                    if (! $record) {
                        jsonError('No committee is visible in this office for a detail journey', 'observation.not-found');
                    }
                    jsonResponse([
                        'id' => $record->id,
                        'number' => $record->committee_number,
                        'name' => $record->name_en,
                        'status' => $record->status,
                        'seatCount' => (int) $record->seat_count,
                        'orderCount' => (int) $record->order_count,
                        'historyCount' => (int) $record->history_count,
                    ]);

                case 'committee.catalog':
                    $companyId = (int) ($args['companyId'] ?? 0);
                    $types = DB::table('gov_committee_types')
                        ->where(fn ($q) => $q->whereNull('owner_company_id')->orWhere('owner_company_id', $companyId));
                    jsonResponse([
                        'visibleTypes' => (clone $types)->count(),
                        'activeTypes' => (clone $types)->where('is_active', 1)->count(),
                        'firstCode' => (clone $types)->orderBy('name_en')->value('code'),
                    ]);

                case 'committee.mine':
                    $id = (int) ($args['id'] ?? 0);
                    $on = now('Asia/Dhaka')->toDateString();
                    $current = DB::table('gov_committee_tenures as t')
                        ->join('gov_committees as c', 'c.id', '=', 't.committee_id')
                        ->where('t.user_id', $id)->whereNull('c.deleted_at')
                        ->whereIn('c.status', ['ACTIVE', 'SUSPENDED'])
                        ->where('c.effective_from', '<=', $on)
                        ->where(fn ($q) => $q->whereNull('c.effective_to')->orWhere('c.effective_to', '>=', $on))
                        ->where('t.from_date', '<=', $on)
                        ->where(fn ($q) => $q->whereNull('t.to_date')->orWhere('t.to_date', '>=', $on))
                        ->count();
                    jsonResponse([
                        'user_id' => $id,
                        'tenures' => DB::table('gov_committee_tenures')->where('user_id', $id)->count(),
                        'current' => $current,
                    ]);

                case 'organization.registry':
                    // Independent restatement of the office registry scope and status tabs for one user:
                    // super users see every location, area ICT officers only profiled offices inside their area.
                    $uid = (int) ($args['userId'] ?? 0);
                    $user = DB::table('users')->where('id', $uid)->first();
                    if (! $user) {
                        jsonError("User with ID {$uid} not found", 'observation.not-found');
                    }
                    $isSuper = (bool) ($user->permissions && str_contains($user->permissions, '"superuser":"1"'));
                    $hid = $isSuper ? null : DB::table('gov_ict_jurisdictions as j')->join('gov_geo_areas as g', 'g.GeoAreaId', '=', 'j.geo_area_id')
                        ->where('j.user_id', $uid)->orderBy('j.id')->value('g.hid');
                    if (! $isSuper && ! $hid) {
                        jsonError("User {$uid} has no area ICT jurisdiction", 'observation.not-found');
                    }
                    $scope = fn () => DB::table('locations as l')->whereNull('l.deleted_at')
                        ->leftJoin('gov_location_profiles as p', 'p.location_id', '=', 'l.id')
                        ->leftJoin('gov_geo_areas as g', 'g.GeoAreaId', '=', 'p.geo_area_id')
                        ->when(! $isSuper, fn ($q) => $q->whereNotNull('p.id')->where('g.hid', 'like', $hid.'%'));
                    $byStatus = $scope()->selectRaw("COALESCE(p.lifecycle_status, 'unconfigured') as s, count(*) as c")
                        ->groupBy('s')->pluck('c', 's')->map(fn ($n) => (int) $n)->all();
                    $sum = fn (array $keys) => (int) array_sum(array_map(fn ($k) => $byStatus[$k] ?? 0, $keys));
                    $all = (int) array_sum($byStatus);
                    $action = $sum(['unconfigured', 'provisioned', 'configured']);
                    $live = $sum(['operational']);
                    // A live office in scope with staff, so opening its hub cannot move it to another status.
                    $sample = $scope()->where('p.lifecycle_status', 'operational')
                        ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('gov_office_memberships as m')
                            ->whereColumn('m.location_id', 'l.id')->where('m.status', 'active'))
                        ->orderBy('l.name')->orderBy('l.id')->first(['l.id', 'l.name']);
                    $sameName = $sample ? DB::table('locations')->whereNull('deleted_at')->where('name', 'like', '%'.$sample->name.'%')->count() : 0;
                    $foreign = $isSuper ? null : DB::table('gov_location_profiles as p')
                        ->join('locations as l', 'l.id', '=', 'p.location_id')->whereNull('l.deleted_at')
                        ->leftJoin('gov_geo_areas as g', 'g.GeoAreaId', '=', 'p.geo_area_id')
                        ->where(fn ($q) => $q->whereNull('g.hid')->orWhere('g.hid', 'not like', $hid.'%'))
                        ->orderBy('l.id')->value('l.id');
                    jsonResponse([
                        'user_id' => $uid,
                        'all' => $all,
                        'action' => $action,
                        'setup' => $sum(['provisioned', 'configured']),
                        'live' => $live,
                        'paused' => $sum(['suspended']),
                        'none' => $sum(['unconfigured']),
                        'attentionBanner' => $action > 0 ? 1 : 0,
                        'firstPageRows' => min($all, 25),
                        'liveRows' => min($live, 25),
                        'sampleOfficeId' => $sample ? (int) $sample->id : null,
                        'sampleOfficeName' => $sample->name ?? null,
                        'sampleSearchRows' => min($sameName, 25),
                        'foreignOfficeId' => $foreign ? (int) $foreign : null,
                    ]);

                case 'organization.office':
                    // Office readiness restated from the business rule: eligible staff are activated, undeleted users
                    // of the office's company with an unexpired active membership; each role must sit with one of them.
                    $id = (int) ($args['id'] ?? 0);
                    $loc = DB::table('locations')->where('id', $id)->whereNull('deleted_at')->first();
                    $profile = DB::table('gov_location_profiles')->where('location_id', $id)->first();
                    if (! $loc || ! $profile) {
                        jsonError("Office {$id} with a profile not found", 'observation.not-found');
                    }
                    $today = now()->toDateString();
                    $eligible = DB::table('users as u')->join('gov_office_memberships as m', 'm.user_id', '=', 'u.id')
                        ->where('m.location_id', $id)->where('m.status', 'active')
                        ->where(fn ($q) => $q->whereNull('m.valid_until')->orWhereDate('m.valid_until', '>=', $today))
                        ->where('u.activated', true)->whereNull('u.deleted_at')->where('u.company_id', $loc->company_id)
                        ->distinct()->pluck('u.id')->map(fn ($v) => (int) $v)->all();
                    $holds = fn (string $slug) => DB::table('gov_office_responsibilities')->where('location_id', $id)
                        ->where('role_slug', $slug)->whereIn('user_id', $eligible ?: [0])->exists();
                    $hasStaff = count($eligible) > 0;
                    $hasAdmin = $profile->office_admin_id && in_array((int) $profile->office_admin_id, $eligible, true);
                    $hasApprover = $holds('primary_approver');
                    $hasStorekeeper = $holds('storekeeper');
                    $done = (int) $hasStaff + (int) $hasAdmin + (int) $hasApprover + (int) $hasStorekeeper;
                    $status = $profile->lifecycle_status;
                    $nextStep = match (true) {
                        $status === 'suspended' => 'next_paused',
                        $status === 'operational' => 'next_ready',
                        ! $hasStaff => 'next_staff',
                        ! $hasAdmin => 'next_admin',
                        ! $hasApprover => 'next_approver',
                        ! $hasStorekeeper => 'next_storekeeper',
                        default => 'next_ready',
                    };
                    $admin = $profile->office_admin_id ? DB::table('users')->where('id', $profile->office_admin_id)->first() : null;
                    $foreign = DB::table('gov_location_profiles as p')->join('locations as l', 'l.id', '=', 'p.location_id')
                        ->whereNull('l.deleted_at')->where('p.location_id', '!=', $id)
                        ->where(fn ($q) => $q->whereNull('p.office_admin_id')->orWhere('p.office_admin_id', '!=', $profile->office_admin_id ?? 0))
                        ->orderBy('l.id')->value('l.id');
                    jsonResponse([
                        'id' => (int) $loc->id,
                        'name' => $loc->name,
                        'company_id' => $loc->company_id ? (int) $loc->company_id : null,
                        'status' => $status,
                        'badgeStatus' => 'office_'.$status,
                        'active' => in_array($status, ['provisioned', 'configured', 'operational'], true),
                        'staff' => count($eligible),
                        'hasAdmin' => $hasAdmin,
                        'hasApprover' => $hasApprover,
                        'hasStorekeeper' => $hasStorekeeper,
                        'done' => $done,
                        'readinessCount' => $done.' of 4 done',
                        'nextStepKey' => $nextStep,
                        'adminId' => $admin ? (int) $admin->id : null,
                        'adminName' => $admin ? User::withoutGlobalScopes()->find($admin->id)?->getFullNameAttribute() : null,
                        'activityRows' => min(20, DB::table('gov_organization_activity_logs')->where('location_id', $id)->count()),
                        'geoVerified' => (bool) $profile->geo_area_verified_at,
                        'foreignOfficeId' => $foreign ? (int) $foreign : null,
                    ]);

                case 'organization.national':
                    // Counts behind the Administrators tabs and the government directory page.
                    jsonResponse([
                        'ictOfficers' => DB::table('gov_ict_jurisdictions')->count(),
                        'ministryAdmins' => DB::table('gov_company_admins')->count(),
                        'directoryRecords' => DB::table('gov_ministries_directory')->count(),
                        'directoryPreviewRows' => min(10, DB::table('gov_ministries_directory')->count()),
                    ]);

                case 'organization.location':
                    // How many locations carry exactly this name (proves a rejected form created nothing).
                    $name = (string) ($args['name'] ?? '');
                    jsonResponse([
                        'name' => $name,
                        'count' => DB::table('locations')->where('name', $name)->count(),
                    ]);

                case 'membership.person':
                    // My offices restated from the membership rules: current = waiting, active, leaving, paused or a
                    // signed-off home office; roles count where the person is live or leaving; a role with a pending
                    // outgoing handover shows Cancel instead of Hand over.
                    $uid = (int) ($args['userId'] ?? 0);
                    $today = now()->toDateString();
                    $rows = DB::table('gov_office_memberships')->where('user_id', $uid)->get();
                    if ($rows->isEmpty()) {
                        jsonError("User {$uid} has no office memberships", 'observation.not-found');
                    }
                    $isLive = fn ($m) => $m->status === 'active' && (! $m->valid_until || $m->valid_until >= $today);
                    $current = $rows->filter(fn ($m) => in_array($m->status, ['pending', 'active', 'release_requested', 'suspended'], true)
                        || ($m->status === 'released' && (bool) $m->is_home_office));
                    $home = $rows->first(fn ($m) => (bool) $m->is_home_office && $isLive($m));
                    $roleOffices = $current->filter(fn ($m) => $isLive($m) || $m->status === 'release_requested')->pluck('location_id')->map(fn ($v) => (int) $v)->all();
                    $roles = DB::table('gov_office_responsibilities')->where('user_id', $uid)->whereIn('location_id', $roleOffices ?: [0])
                        ->get(['location_id', 'role_slug'])->map(fn ($r) => [(int) $r->location_id, $r->role_slug])->all();
                    foreach (DB::table('gov_location_profiles')->where('office_admin_id', $uid)->whereIn('location_id', $roleOffices ?: [0])->pluck('location_id') as $office) {
                        $roles[] = [(int) $office, 'office_admin'];
                    }
                    $outgoing = DB::table('gov_role_handshakes')->where('outgoing_user_id', $uid)->where('status', 'pending')->get(['location_id', 'role_type']);
                    $waiting = array_filter($roles, fn ($r) => $outgoing->contains(fn ($h) => (int) $h->location_id === $r[0] && $h->role_type === $r[1]));
                    $homeOffice = $home ? (int) $home->location_id : null;
                    $colleagues = $homeOffice ? DB::table('gov_office_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
                        ->where('m.location_id', $homeOffice)->where('m.user_id', '!=', $uid)->whereIn('m.status', ['active', 'release_requested'])
                        ->where(fn ($q) => $q->whereNull('m.valid_until')->orWhere('m.valid_until', '>=', $today))
                        ->where('u.activated', true)->whereNull('u.deleted_at')->pluck('m.status') : collect();
                    jsonResponse([
                        'user_id' => $uid,
                        'current' => $current->count(),
                        'past' => $rows->count() - $current->count(),
                        'active' => $current->where('status', 'active')->count(),
                        'pending' => $rows->where('status', 'pending')->count(),
                        'homeTags' => $current->filter(fn ($m) => (bool) $m->is_home_office)->count(),
                        'homeMembershipId' => $home ? (int) $home->id : null,
                        'homeOfficeId' => $homeOffice,
                        'homeOfficeName' => $homeOffice ? DB::table('locations')->where('id', $homeOffice)->value('name') : null,
                        'roles' => count($roles),
                        'homeRoles' => count(array_filter($roles, fn ($r) => $r[0] === $homeOffice)),
                        'handoverButtons' => count($roles) - count($waiting),
                        'pendingOutgoing' => count($waiting),
                        'incoming' => DB::table('gov_role_handshakes')->where('incoming_user_id', $uid)->where('status', 'pending')->count(),
                        'incomingBox' => DB::table('gov_role_handshakes')->where('incoming_user_id', $uid)->where('status', 'pending')->exists() ? 1 : 0,
                        'openOffices' => $rows->whereIn('status', ['active', 'pending', 'release_requested', 'suspended'])->count(),
                        'notices' => min(20, DB::table('gov_membership_notices')->where('user_id', $uid)->count()),
                        'personalCode' => DB::table('gov_employee_verification_tokens')->where('user_id', $uid)->whereNull('used_at')->where('expires_at', '>', now())->exists(),
                        'colleagues' => $colleagues->count(),
                        'leavingColleagues' => $colleagues->filter(fn ($s) => $s === 'release_requested')->count(),
                        'foreignMembershipId' => (int) DB::table('gov_office_memberships')->where('user_id', '!=', $uid)->orderBy('id')->value('id'),
                    ]);

                case 'membership.staff':
                    // The Staff page's five tab counts restated for one office (a signed-off home office elsewhere in
                    // the same ministry is "arriving"; past staff = left in the last 12 months).
                    $id = (int) ($args['officeId'] ?? 0);
                    $loc = DB::table('locations')->where('id', $id)->first();
                    if (! $loc) {
                        jsonError("Office {$id} not found", 'observation.not-found');
                    }
                    $here = fn () => DB::table('gov_office_memberships')->where('location_id', $id);
                    $arriving = fn () => DB::table('gov_office_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
                        ->where('m.is_home_office', true)->where('m.status', 'released')->where('m.location_id', '!=', $id)
                        ->where('u.company_id', $loc->company_id)->where('u.activated', true)->whereNull('u.deleted_at');
                    $staff = $here()->whereIn('status', ['active', 'suspended'])->count();
                    $waiting = $here()->where('status', 'pending')->count();
                    $leaving = $here()->where('status', 'release_requested')->count();
                    $expected = $arriving()->where('m.destination_location_id', $id)->count();
                    $profile = DB::table('gov_location_profiles')->where('location_id', $id)->first();
                    jsonResponse([
                        'officeId' => $id,
                        'name' => $loc->name,
                        'status' => $profile->lifecycle_status ?? null,
                        'adminId' => $profile && $profile->office_admin_id ? (int) $profile->office_admin_id : null,
                        'staff' => $staff,
                        'waiting' => $waiting,
                        'leaving' => $leaving,
                        'arriving' => $arriving()->count(),
                        'past' => $here()->whereIn('status', ['released', 'inactive', 'rejected'])
                            ->where(fn ($q) => $q->where('is_home_office', false)->orWhere('status', '!=', 'released'))
                            ->where('updated_at', '>=', now()->subYear())->count(),
                        'expected' => $expected,
                        'waitTotal' => $waiting + $leaving + $expected,
                        'staffRows' => min(25, $staff),
                        'waitingRows' => min(25, $waiting),
                        'waitBanner' => $waiting + $leaving + $expected > 0 ? 1 : 0,
                        'additionalStaff' => $here()->where('status', 'active')->where('is_home_office', false)->count(),
                        'codeLive' => (bool) ($profile && $profile->invitation_code && $profile->invitation_code_expires_at > now()),
                    ]);

                case 'membership.handover':
                    // The latest handover of one role at one office from one person, and who holds the role now.
                    $office = (int) ($args['officeId'] ?? 0);
                    $role = (string) ($args['role'] ?? '');
                    $from = (int) ($args['fromUserId'] ?? 0);
                    $latest = DB::table('gov_role_handshakes')->where('location_id', $office)->where('role_type', $role)
                        ->where('outgoing_user_id', $from)->orderByDesc('id')->first();
                    $holders = DB::table('gov_office_responsibilities')->where('location_id', $office)->where('role_slug', $role)
                        ->pluck('user_id')->map(fn ($v) => (int) $v)->all();
                    $notice = $latest ? DB::table('gov_membership_notices')->where('user_id', $from)->where('location_id', $office)
                        ->where('event_key', 'transfer_'.$latest->status)->where('created_at', '>=', $latest->created_at)->orderByDesc('id')->first() : null;
                    jsonResponse([
                        'pendingForRole' => DB::table('gov_role_handshakes')->where('location_id', $office)->where('role_type', $role)->where('status', 'pending')->count(),
                        'id' => $latest ? (int) $latest->id : null,
                        'status' => $latest->status ?? null,
                        'incomingUserId' => $latest ? (int) $latest->incoming_user_id : null,
                        'fromHolds' => in_array($from, $holders, true),
                        'holders' => count($holders),
                        'noticeReason' => $notice ? (json_decode((string) $notice->details, true)['reason'] ?? null) : null,
                    ]);

                case 'membership.code':
                    // How many offices use this office code (normalised the way people may type it).
                    $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) ($args['code'] ?? '')));
                    jsonResponse(['code' => $code, 'offices' => $code === '' ? 0 : DB::table('gov_location_profiles')->where('invitation_code', $code)->count()]);

                case 'membership.overrides':
                    // Emergency change history size (the Change history tab count).
                    jsonResponse(['count' => DB::table('gov_override_audit_logs')->count()]);

                case 'asset-tags.readiness':
                    // Gates G1-G6 restated from the business rules (ARCHITECTURE.md §6/§15), plus the office's facts,
                    // the company code lists and enablement, each with its stored revision.
                    $officeId = (int) ($args['officeId'] ?? 0);
                    $office = DB::table('locations')->where('id', $officeId)->first(['id', 'name', 'company_id']);
                    $meta = fn (string $type, int $id, string $ns) => DB::table('gov_metadata_values')->where('subject_type', $type)
                        ->where('subject_id', $id)->where('namespace', $ns)->first(['payload', 'revision', 'updated_by']);
                    $facts = $meta('office', $officeId, 'asset-tagging.office');
                    $enable = $meta('office', $officeId, 'asset-tagging.enablement');
                    $lists = $office?->company_id ? $meta('company', (int) $office->company_id, 'asset-tagging.codelists') : null;
                    $factsPayload = $facts ? json_decode($facts->payload, true) : null;
                    $listsPayload = $lists ? json_decode($lists->payload, true) : ['locations' => [], 'divisions' => []];
                    $settings = DB::table('settings')->first(['auto_increment_assets', 'auto_increment_prefix', 'zerofill_count', 'next_auto_tag_base']);
                    $prefix = (string) ($settings->auto_increment_prefix ?? '');
                    $locationCodes = array_column($listsPayload['locations'] ?? [], 'code');
                    $divisionCodes = array_column($listsPayload['divisions'] ?? [], 'code');
                    $experiment = DB::table('gov_experiment_records')->where('table_name', 'locations')->where('record_key', json_encode(['id' => (string) $officeId]))->exists();
                    $fd18 = DB::table('gov_asset_registrations')->where('format_version', 'fd18-v1')->pluck('asset_tag')->flip();
                    $legacy = DB::table('assets')->whereRaw('LENGTH(asset_tag) = 18')->pluck('asset_tag')
                        ->filter(fn ($tag) => ctype_digit((string) $tag) && ! $fd18->has($tag))->count();
                    $gates = [
                        'G1' => (string) ($settings->auto_increment_assets ?? '0') === '1' && $prefix !== '' && ! ctype_digit($prefix[0]),
                        'G2' => ! DB::table('gov_asset_registrations')->select('asset_tag')->groupBy('asset_tag')->havingRaw('count(*) > 1')->exists(),
                        'G3' => $legacy === 0 || filled(config('asset-tagging.legacy_reviewed_at')),
                        'G4' => $factsPayload && in_array($factsPayload['location_code'] ?? null, $locationCodes, true)
                            && ! empty($factsPayload['division_codes']) && ! array_diff($factsPayload['division_codes'], $divisionCodes)
                            && in_array($factsPayload['default_division_code'] ?? null, $factsPayload['division_codes'], true),
                        'G5' => (bool) config('asset-tagging.single_issuer'),
                        'G6' => ! $experiment || ((bool) config('asset-tagging.rehearsal') && ! app()->isProduction()),
                    ];
                    jsonResponse([
                        'office_id' => $officeId, 'company_id' => (int) ($office->company_id ?? 0), 'office_name' => $office->name ?? null,
                        'native' => ['auto_increment' => (string) ($settings->auto_increment_assets ?? '0') === '1', 'prefix' => $prefix,
                            'zerofill' => (int) ($settings->zerofill_count ?? 0), 'next' => (int) ($settings->next_auto_tag_base ?? 0)],
                        'gates' => $gates, 'failing' => array_keys(array_filter($gates, fn ($passed) => ! $passed)),
                        'allPass' => ! in_array(false, $gates, true), 'passCount' => count(array_filter($gates)), 'experimentOffice' => $experiment,
                        'officePassCount' => (int) $gates['G4'] + (int) $gates['G6'], 'installationPassCount' => (int) $gates['G1'] + (int) $gates['G2'] + (int) $gates['G3'] + (int) $gates['G5'],
                        'facts' => $factsPayload, 'factsRevision' => $facts->revision ?? null,
                        'locationCode' => $factsPayload['location_code'] ?? null, 'defaultDivision' => $factsPayload['default_division_code'] ?? null,
                        'divisionCount' => count($factsPayload['division_codes'] ?? []),
                        'codeLists' => $listsPayload, 'codeListsRevision' => $lists->revision ?? null,
                        'locationListCount' => count($locationCodes), 'divisionListCount' => count($divisionCodes),
                        'enabled' => (bool) ($enable ? (json_decode($enable->payload, true)['enabled'] ?? false) : false),
                        'enablement' => $enable ? json_decode($enable->payload, true) : null, 'enablementRevision' => $enable->revision ?? null,
                        'takenLocations' => DB::table('gov_metadata_values as v')->join('locations as l', 'l.id', '=', 'v.subject_id')
                            ->where('v.subject_type', 'office')->where('v.namespace', 'asset-tagging.office')
                            ->where('l.company_id', (int) ($office->company_id ?? 0))->where('v.subject_id', '!=', $officeId)
                            ->pluck('v.payload')->map(fn ($p) => json_decode($p, true)['location_code'] ?? null)->filter()->values()->all(),
                        'accessEvents' => DB::table('gov_access_events')->where('ability', 'like', 'asset-tags.%')->count(),
                    ]);

                case 'asset-tags.document':
                    // One store document by id: header facts the tag rules read, its lines and every tag issued for it.
                    $doc = DB::table('gov_documents')->where('id', (string) ($args['id'] ?? ''))->first();
                    if (! $doc) {
                        jsonResponse(['exists' => false]);
                    }
                    $refs = DB::table('gov_document_references')->where('document_id', $doc->id)->get(['reference_type', 'reference_number', 'reference_date']);
                    $date = fn (string $type) => ($r = $refs->firstWhere('reference_type', $type)) && $r->reference_date ? substr((string) $r->reference_date, 0, 10) : null;
                    $items = DB::table('gov_document_items')->where('document_id', $doc->id)->orderBy('id')->get(['id', 'product_type', 'product_id', 'quantity']);
                    $regs = DB::table('gov_asset_registrations as r')->join('gov_document_items as i', 'i.id', '=', 'r.intake_item_id')
                        ->leftJoin('assets as a', 'a.id', '=', 'r.asset_id')
                        ->where('i.document_id', $doc->id)->orderBy('i.id')->orderBy('r.unit_index')
                        ->get(['r.asset_tag', 'r.format_version', 'r.gov_code', 'r.unit_index', 'r.tag_snapshot', 'i.product_id',
                            'a.asset_tag as asset_asset_tag', 'a.purchase_date', 'a.location_id', 'a.company_id', 'a.model_id'])
                        ->map(function ($r) {
                            $snapshot = json_decode((string) $r->tag_snapshot, true) ?: [];

                            return ['tag' => $r->asset_tag, 'format' => $r->format_version, 'gov_code' => $r->gov_code, 'unit' => $r->unit_index,
                                'model_id' => (int) $r->product_id, 'asset_tag_matches' => $r->asset_asset_tag === $r->asset_tag,
                                'purchase_date' => $r->purchase_date ? substr((string) $r->purchase_date, 0, 10) : null,
                                'location_id' => (int) $r->location_id, 'company_id' => (int) $r->company_id,
                                'finance_source' => $snapshot['finance_source'] ?? null, 'standard_reason' => $snapshot['standard_reason'] ?? null,
                                'division_code' => $snapshot['division_code'] ?? null, 'location_code' => $snapshot['location_code'] ?? null,
                                'acquired_on' => $snapshot['acquired_on'] ?? null, 'date_source' => $snapshot['date_source'] ?? null];
                        });
                    $byModel = fn (string $format) => $regs->where('format', $format)->groupBy('model_id')->map->count()->all();
                    jsonResponse([
                        'exists' => true, 'id' => $doc->id, 'number' => $doc->document_number, 'type' => $doc->type, 'status' => $doc->status,
                        'purchase_type' => $doc->purchase_type ?? null, 'location_id' => (int) $doc->location_id, 'company_id' => (int) $doc->company_id,
                        'posted_by' => $doc->posted_by ? (int) $doc->posted_by : null, 'created_by' => $doc->created_by ? (int) $doc->created_by : null,
                        'challanDate' => $date('Supplier Challan'), 'poDate' => $date('Purchase Order'),
                        'lines' => $items->count(), 'quantity' => (int) $items->sum('quantity'),
                        'lineQuantities' => $items->mapWithKeys(fn ($i) => [$i->product_id => (int) $i->quantity])->all(),
                        'tags' => $regs->pluck('tag')->all(), 'registrations' => $regs->values()->all(),
                        'govt' => $regs->where('format', 'fd18-v1')->count(), 'standard' => $regs->where('format', 'native-v1')->count(),
                        'entered' => $regs->whereNotIn('format', ['fd18-v1', 'native-v1'])->count(),
                        'govtByModel' => $byModel('fd18-v1'), 'standardByModel' => $byModel('native-v1'),
                        'govtTags' => $regs->where('format', 'fd18-v1')->pluck('tag')->values()->all(),
                        'standardTags' => $regs->where('format', 'native-v1')->pluck('tag')->values()->all(),
                        'allAssetTagsMatch' => $regs->every(fn ($r) => $r['asset_tag_matches']),
                        // Optional modelId: the same facts for one line's product.
                        'modelTags' => $regs->where('model_id', (int) ($args['modelId'] ?? 0))->pluck('tag')->values()->all(),
                        'modelFormats' => $regs->where('model_id', (int) ($args['modelId'] ?? 0))->pluck('format')->unique()->values()->all(),
                        'modelFinanceSources' => $regs->where('model_id', (int) ($args['modelId'] ?? 0))->pluck('finance_source')->unique()->values()->all(),
                        'modelStandardReasons' => $regs->where('model_id', (int) ($args['modelId'] ?? 0))->pluck('standard_reason')->unique()->values()->all(),
                        'modelPurchaseDates' => $regs->where('model_id', (int) ($args['modelId'] ?? 0))->pluck('purchase_date')->unique()->values()->all(),
                        'modelOffices' => $regs->where('model_id', (int) ($args['modelId'] ?? 0))->pluck('location_id')->unique()->values()->all(),
                    ]);

                case 'asset-tags.tag':
                    // Where one tag lives: its asset, office and registration (lookup must hide it from other offices).
                    $tag = (string) ($args['tag'] ?? '');
                    $reg = DB::table('gov_asset_registrations as r')->leftJoin('assets as a', 'a.id', '=', 'r.asset_id')
                        ->where('r.asset_tag', $tag)->first(['r.asset_id', 'r.format_version', 'r.gov_code', 'a.location_id', 'a.company_id', 'a.model_id', 'a.deleted_at']);
                    jsonResponse(['tag' => $tag, 'exists' => (bool) $reg, 'asset_id' => $reg ? (int) $reg->asset_id : null,
                        'format' => $reg->format_version ?? null, 'gov_code' => $reg->gov_code ?? null,
                        'location_id' => $reg ? (int) $reg->location_id : null, 'company_id' => $reg ? (int) $reg->company_id : null,
                        'assetsWithTag' => DB::table('assets')->where('asset_tag', $tag)->count()]);

                case 'asset-tags.finance':
                    // One Finance item and its product-type default usage.
                    $code = (string) ($args['code'] ?? '');
                    $item = DB::table('gov_finance_items')->where('gov_code', $code)->first(['gov_code', 'item_name', 'status', 'unspsc_code', 'updated_by']);
                    jsonResponse(['code' => $code, 'exists' => (bool) $item, 'status' => $item->status ?? null, 'item_name' => $item->item_name ?? null,
                        'unspsc_code' => $item->unspsc_code ?? null, 'updated_by' => $item?->updated_by ? (int) $item->updated_by : null,
                        'activeCount' => DB::table('gov_finance_items')->where('status', 'active')->count()]);

                case 'asset-tags.default':
                    // A product type's (category's) Finance Code default as stored.
                    $categoryId = (int) ($args['categoryId'] ?? 0);
                    $row = DB::table('gov_metadata_values')->where('subject_type', 'category')->where('subject_id', $categoryId)
                        ->where('namespace', 'asset-tagging.default')->first(['payload', 'revision', 'scope_type', 'scope_id']);
                    $governance = DB::table('gov_category_governance')->where('category_id', $categoryId)->first(['governance_type', 'created_by_company_id']);
                    jsonResponse(['category_id' => $categoryId, 'gov_code' => $row ? (json_decode($row->payload, true)['gov_code'] ?? null) : null,
                        'revision' => $row->revision ?? null, 'scope' => $row ? $row->scope_type : null,
                        'national' => ! ($governance && $governance->governance_type === 'company' && $governance->created_by_company_id)]);

                case 'rules.effective':
                    // Who decides one capability for one office and asset model, restated from the published assignments:
                    // layers GLOBAL > COMPANY > LOCATION > CATEGORY > MODEL; the deepest layer that says ENFORCE or DISABLE wins;
                    // when none does, issue_govt_tags falls to the built-in DEFAULT layer (on for asset models).
                    $code = (string) ($args['code'] ?? 'issue_govt_tags');
                    $officeId = (int) ($args['officeId'] ?? 0);
                    $modelId = (int) ($args['modelId'] ?? 0);
                    $companyId = (int) DB::table('locations')->where('id', $officeId)->value('company_id');
                    $categoryId = (int) DB::table('models')->where('id', $modelId)->value('category_id');
                    $layers = ['GLOBAL' => [['System'], 1], 'COMPANY' => [['Tenant'], $companyId], 'LOCATION' => [['Location', 'App\Models\Location'], $officeId],
                        'CATEGORY' => [['App\Models\Category'], $categoryId], 'MODEL' => [['App\Models\AssetModel'], $modelId]];
                    $decider = null;
                    $trail = [];
                    foreach ($layers as $layer => [$types, $targetId]) {
                        $assignment = DB::table('gov_profile_assignments as a')->join('gov_profiles as p', 'p.id', '=', 'a.profile_id')
                            ->whereIn('a.target_type', $types)->where('a.target_id', $targetId)
                            ->where(fn ($q) => $q->whereNull('a.effective_to')->orWhere('a.effective_to', '>', now()))
                            ->orderByDesc('a.id')->first(['p.id', 'p.name', 'p.status']);
                        $behavior = $assignment ? DB::table('gov_profile_capabilities')->where('profile_id', $assignment->id)
                            ->where('capability_code', $code)->value('behavior') : null;
                        $trail[$layer] = ['policy' => $assignment->name ?? null, 'policy_id' => $assignment ? (int) $assignment->id : null, 'behavior' => $behavior];
                        if (in_array($behavior, ['ENFORCE', 'DISABLE'], true)) {
                            $decider = ['layer' => $layer, 'behavior' => $behavior, 'policy' => $assignment->name];
                        }
                    }
                    $defaultOn = $code === 'issue_govt_tags';
                    jsonResponse(['code' => $code, 'office_id' => $officeId, 'model_id' => $modelId, 'category_id' => $categoryId, 'trail' => $trail,
                        'decider' => $decider['layer'] ?? ($defaultOn ? 'DEFAULT' : null), 'policy' => $decider['policy'] ?? null,
                        'on' => $decider ? $decider['behavior'] === 'ENFORCE' : $defaultOn]);

                case 'rules.policy':
                    // One policy by exact name: status, version, behaviors and its active assignments.
                    $profile = DB::table('gov_profiles')->where('name', (string) ($args['name'] ?? ''))->orderByDesc('id')->first(['id', 'name', 'status', 'version', 'lineage_id']);
                    jsonResponse(['exists' => (bool) $profile, 'id' => $profile ? (int) $profile->id : null, 'status' => $profile->status ?? null,
                        'version' => $profile->version ?? null, 'lineage' => $profile->lineage_id ?? null,
                        'capabilities' => $profile ? DB::table('gov_profile_capabilities')->where('profile_id', $profile->id)->pluck('behavior', 'capability_code')->all() : [],
                        'assignments' => $profile ? DB::table('gov_profile_assignments')->where('profile_id', $profile->id)
                            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', now()))
                            ->get(['id', 'target_type', 'target_id'])->map(fn ($a) => ['id' => (int) $a->id, 'target_type' => $a->target_type, 'target_id' => (int) $a->target_id])->all() : [],
                        'activeAssignments' => $profile ? DB::table('gov_profile_assignments')->where('profile_id', $profile->id)->whereNull('effective_to')->count() : 0]);

                case 'storeops.documents':
                    // Store documents of one type created in one office by one user (proves a refused action created none).
                    jsonResponse(['count' => DB::table('gov_documents')->where('location_id', (int) ($args['officeId'] ?? 0))
                        ->where('type', (string) ($args['type'] ?? 'receipt'))->where('created_by', (int) ($args['userId'] ?? 0))->count(),
                        'posted' => DB::table('gov_documents')->where('location_id', (int) ($args['officeId'] ?? 0))
                            ->where('type', (string) ($args['type'] ?? 'receipt'))->where('status', 'POSTED')->count(),
                        'registrations' => DB::table('gov_asset_registrations')->count()]);

                default:
                    jsonError("Unsupported observation check: '{$check}'", 'observation.unsupported');
            }

        default:
            jsonError("Unknown bridge action: '{$action}'", 'bridge.invalid-action');
    }
} catch (\Throwable $e) {
    jsonError($e->getMessage(), 'bridge.exception');
}
