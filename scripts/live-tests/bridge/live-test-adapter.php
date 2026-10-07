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
$app = require_once __DIR__.'/../../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

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

$action = $argv[1] ?? 'help';
$rawInput = '{}';
if (isset($argv[2])) {
    $decoded = base64_decode($argv[2], true);
    $rawInput = ($decoded !== false && json_validate($decoded)) ? $decoded : $argv[2];
} elseif (in_array('--stdin', $argv, true)) {
    $rawInput = stream_get_contents(STDIN) ?: '{}';
}
$input = json_decode($rawInput, true) ?: [];

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
            ]);

        case 'preflight':
            $dbName = DB::connection()->getDatabaseName();
            $runs = DB::table('gov_experiment_runs')->where('status', 'ready')->whereNull('wiped_at')->count();
            jsonResponse([
                'status' => 'success',
                'environment' => app()->environment(),
                'production' => app()->isProduction(),
                'appUrl' => config('app.url'),
                'database' => $dbName,
                'accessMode' => config('govstore-access.mode'),
                'mailer' => config('mail.default'),
                'queue' => config('queue.default'),
                'readyDatasets' => $runs,
            ]);

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

            $offices = $officeQuery->get();
            $resolvedOffice = null;
            $resolvedActors = [];
            $resolvedRecords = [];
            $search = ['offices' => 0, 'users' => 0, 'rejected' => []];
            $reject = function (string $reason) use (&$search) {
                $search['rejected'][$reason] = ($search['rejected'][$reason] ?? 0) + 1;
            };

            $actorDefs = $input['actors'] ?? [];
            $recordDefs = $input['records'] ?? [];

            foreach ($offices as $candOffice) {
                $officeId = (int) $candOffice->id;
                $search['offices']++;
                $usedUserIds = [];
                $officeActors = [];

                // Try resolving each requested actor in this office
                $allActorsMatched = true;
                foreach ($actorDefs as $actorAlias => $actorDef) {
                    $reqRole = $actorDef['role'] ?? null;
                    $reqCaps = $actorDef['capabilities']['allOf'] ?? [];

                    // Find eligible users in this office
                    $candUserQuery = DB::table('users as u')
                        ->join('gov_office_memberships as m', 'm.user_id', '=', 'u.id')
                        ->where('m.location_id', $officeId)
                        ->where('m.status', 'active')
                        ->whereIn('u.id', $ownedUserIds)
                        ->whereNull('u.deleted_at')
                        ->select('u.id', 'u.username', 'u.first_name', 'u.last_name', 'u.display_name', 'u.password', 'u.permissions');

                    if (! empty($usedUserIds)) {
                        $candUserQuery->whereNotIn('u.id', $usedUserIds);
                    }

                    $candidateUsers = $candUserQuery->get();
                    $selectedUser = null;

                    // Least privilege first: fewest effective roles, then lowest id.
                    $scored = [];
                    foreach ($candidateUsers as $candUser) {
                        $isSuper = (bool) ($candUser->permissions && str_contains($candUser->permissions, '"superuser":"1"'));
                        $scored[] = [$candUser, officeRoles((int) $candUser->id, $officeId, (int) $candOffice->company_id, $isSuper), $isSuper];
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
                        if (Hash::check(BangladeshPeople::PASSWORD, $candUser->password)) {
                            $verifiedPassword = BangladeshPeople::PASSWORD;
                        }

                        if (! $verifiedPassword && isset($run->password)) {
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
                        }
                        if ($verifiedPassword) {
                            $selectedUser = [
                                'id' => (int) $candUser->id,
                                'username' => $candUser->username,
                                'display_name' => $candUser->display_name ?: ($candUser->first_name.' '.$candUser->last_name),
                                'password' => $verifiedPassword,
                            ];
                            $usedUserIds[] = (int) $candUser->id;
                            break;
                        }
                    }

                    if (! $selectedUser) {
                        $allActorsMatched = false;
                        break;
                    }

                    $officeActors[$actorAlias] = $selectedUser;
                }

                if (! $allActorsMatched) {
                    continue;
                }

                // Check records in this office
                $allRecordsMatched = true;
                $officeRecords = [];
                foreach ($recordDefs as $recAlias => $recDef) {
                    $kind = $recDef['kind'] ?? 'consumable';
                    $minAvail = (int) ($recDef['minimumAvailable'] ?? 0);

                    if ($kind === 'consumable') {
                        $item = DB::table('consumables as c')
                            ->join('gov_experiment_records as own', function ($join) use ($run) {
                                $join->on(DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(own.record_key, '$.id')) AS UNSIGNED)"), '=', 'c.id')
                                    ->where('own.table_name', '=', 'consumables')
                                    ->where('own.run_id', '=', $run->id);
                            })
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
                    }
                }

                if (! $allRecordsMatched) {
                    continue;
                }

                // Found matching office!
                $resolvedOffice = [
                    'id' => $officeId,
                    'name' => $candOffice->name,
                    'company_id' => (int) $candOffice->company_id,
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

                case 'basket.line':
                    $uid = (int) ($args['userId'] ?? 0);
                    $qty = DB::table('draft_basket_items as i')
                        ->join('draft_baskets as b', 'b.id', '=', 'i.basket_id')
                        ->where('b.user_id', $uid)->where('b.status', 'draft')
                        ->where('i.requested_type', (string) ($args['itemType'] ?? ''))
                        ->where('i.requested_id', (int) ($args['itemId'] ?? 0))
                        ->sum('i.requested_qty');
                    jsonResponse(['user_id' => $uid, 'quantity' => (int) $qty]);

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

                default:
                    jsonError("Unsupported observation check: '{$check}'", 'observation.unsupported');
            }

        default:
            jsonError("Unknown bridge action: '{$action}'", 'bridge.invalid-action');
    }
} catch (\Throwable $e) {
    jsonError($e->getMessage(), 'bridge.exception');
}
