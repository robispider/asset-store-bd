<?php

namespace GovStore\Experimentation\Services;

use App\Models\User;
use GovStore\AssetTagging\Services\OfficeTagFacts;
use GovStore\Committee\Models\Committee;
use GovStore\Committee\Services\CommitteeLedger;
use GovStore\Experimentation\Models\ExperimentRun;
use GovStore\GeoView\Data\GeoRequest;
use GovStore\GeoView\Registry\GeoSourceRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WorkflowVerifier
{
    public function verify(ExperimentRun $run, User $actor): array
    {
        $checks = ['workflow_actions' => true, 'reservations' => true, 'ledger_chains' => true,
            'committee' => true, 'typed_metadata' => true, 'tracking_provenance' => true, 'appearance' => true, 'geo_sources' => true, 'document_actions' => true, 'organization_actions' => true];
        $units = $run->report['units'] ?? [];
        foreach (['asset_model', 'consumable', 'accessory'] as $type) {
            $recipes = ScenarioManifest::REQUEST_RECIPES;
            if ($type !== 'asset_model') {
                $recipes = [...$recipes, 'return', 'substitution'];
            }
            foreach ($recipes as $recipe) {
                $result = $units['v2-request-'.$type.'-'.$recipe] ?? null;
                $request = $result ? DB::table('custom_service_requests')->where('id', $result['request_id'])->first() : null;
                if (! $request) {
                    $checks['workflow_actions'] = false;

                    continue;
                }
                $line = DB::table('custom_service_request_items')->where('request_id', $request->id)->first();
                $events = DB::table('custom_service_request_events')->where('request_id', $request->id)->get();
                $expected = match ($recipe) {
                    'partial', 'closed' => 1, 'complete', 'acknowledged', 'return', 'substitution' => 2, default => 0,
                };
                $approval = match ($recipe) {
                    'pending' => 'pending_primary', 'withdrawn' => 'cancelled', 'rejected' => 'rejected', default => 'approved'
                };
                $fulfillment = match ($recipe) {
                    'partial' => 'partially_issued', 'closed', 'withdrawn', 'rejected' => 'closed',
                    'complete', 'acknowledged', 'return', 'substitution' => 'issued', default => 'unstarted',
                };
                $valid = $line && $line->requested_type === $type && (int) $line->issued_qty === $expected
                    && $request->approval_status === $approval && $request->fulfillment_status === $fulfillment
                    && $events->contains('event_type', $recipe === 'auto' ? 'auto_approved' : 'submitted');
                if ($recipe === 'two_stage' || $recipe === 'threshold') {
                    $valid = $valid && $request->resolved_policy === 'PRIMARY_AND_FINAL' && $request->primary_decided_by
                        && $request->decided_by && (int) $request->decided_by !== (int) $request->primary_decided_by
                        && $events->contains('event_type', 'pending_final');
                }
                if ($recipe === 'reduced') {
                    $valid = $valid && (int) $line->approved_qty === 1;
                }
                if ($recipe === 'acknowledged') {
                    $valid = $valid && $request->received_at && $events->contains('event_type', 'received');
                }
                if ($recipe === 'closed') {
                    $valid = $valid && $events->contains('event_type', 'closed');
                }
                if ($recipe === 'withdrawn') {
                    $valid = $valid && $events->contains('event_type', 'cancelled');
                }
                if ($recipe === 'substitution') {
                    $valid = $valid && (int) $line->fulfilled_id === (int) $result['alternative_id'] && $events->contains('event_type', 'item_substituted');
                }
                if ($recipe === 'return') {
                    $valid = $valid && $request->return_requested_at && $request->return_document_id === $result['document_id']
                        && DB::table('gov_documents')->where('id', $result['document_id'])->where('type', 'receipt')->where('status', 'POSTED')->exists()
                        && $events->contains('event_type', 'return_requested') && $events->contains('event_type', 'return_drafted');
                }
                if ($expected > 0) {
                    $issueEvents = $events->where('event_type', 'item_issued');
                    $valid = $valid && $issueEvents->count() === ($recipe === 'complete' ? 2 : 1);
                    foreach ($issueEvents as $event) {
                        $valid = $valid && DB::table('gov_office_responsibilities')->where('location_id', $request->office_id)
                            ->where('role_slug', 'storekeeper')->where('user_id', $event->user_id)->exists();
                    }
                    if ($type === 'asset_model') {
                        $serials = $issueEvents->flatMap(fn ($event) => json_decode($event->details, true)['asset_ids'] ?? [])->all();
                        $valid = $valid && count(array_unique($serials)) === $expected
                            && DB::table('assets')->whereIn('id', $serials)->where('assigned_to', $request->requested_by)->count() === $expected;
                    } else {
                        $numbers = $issueEvents->map(fn ($event) => json_decode($event->details, true)['goods_issue'] ?? null)->filter();
                        $valid = $valid && $numbers->count() === $issueEvents->count()
                            && DB::table('gov_documents')->whereIn('document_number', $numbers)->where('location_id', $request->office_id)
                                ->where('type', 'issue')->where('status', 'POSTED')->count() === $numbers->count();
                    }
                }
                $checks['workflow_actions'] = $checks['workflow_actions'] && (bool) $valid;
            }
        }
        $mixed = $units['v2-mixed-policy']['request_ids'] ?? [];
        $checks['workflow_actions'] = $checks['workflow_actions'] && count($mixed) === 2
            && DB::table('custom_service_requests')->whereIn('id', $mixed)->distinct()->count('submission_batch') === 1;
        $ids = app(RecordRegistry::class)->ids($run, 'custom_service_requests');
        foreach (DB::table('custom_service_request_items as i')->join('custom_service_requests as r', 'r.id', '=', 'i.request_id')
            ->whereIn('r.id', $ids)->get(['i.*', 'r.fulfillment_status']) as $line) {
            $remaining = max(0, (int) $line->approved_qty - (int) $line->issued_qty);
            $expected = in_array($line->fulfillment_status, ['issued', 'closed'], true) || $line->line_approval_status !== 'approved' ? 0 : $remaining;
            $checks['reservations'] = $checks['reservations'] && (int) $line->reserved_qty === $expected;
        }
        $offices = app(RecordRegistry::class)->ids($run, 'locations');
        $chains = DB::table('gov_inventory_movements')->whereIn('location_id', $offices)->orderBy('created_at')->orderBy('id')->get()
            ->groupBy(fn ($m) => $m->location_id.':'.$m->stockable_type.':'.$m->stockable_id);
        foreach ($chains as $movements) {
            $balance = 0;
            foreach ($movements as $movement) {
                $balance += ($movement->movement_type === 'IN' ? 1 : -1) * (int) $movement->quantity;
                $checks['ledger_chains'] = $checks['ledger_chains'] && $balance >= 0 && $balance === (int) $movement->balance_after;
            }
        }
        foreach (['draft', 'active', 'suspended', 'dissolved'] as $state) {
            $result = $units['v2-committee-'.$state] ?? [];
            $committee = Committee::withoutGlobalScopes()->find($result['committee_id'] ?? null);
            $checks['committee'] = $checks['committee'] && $committee && $committee->status === strtoupper($state)
                && app(CommitteeLedger::class)->verifyChain($committee->lineage_id) === [];
            if ($committee && $state !== 'draft') {
                $declaration = DB::table('gov_committee_tenures')->where('committee_id', $committee->id)->where('declaration_status', 'FILED')->first();
                $checks['committee'] = $checks['committee'] && $declaration
                    && Storage::disk('committee_private')->exists($declaration->declaration_attachment_path)
                    && hash('sha256', Storage::disk('committee_private')->get($declaration->declaration_attachment_path)) === $declaration->declaration_attachment_sha256;
            }
        }
        $intake = $units['v2-committee-order-intake'] ?? [];
        $draft = DB::table('gov_committee_order_intakes')->where('user_id', $intake['user_id'] ?? 0)
            ->where('location_id', $intake['location_id'] ?? 0)->first();
        $attachment = $draft ? json_decode($draft->attachment, true) : null;
        $checks['committee'] = $checks['committee'] && $draft && $draft->revision === ($intake['revision'] ?? 0)
            && $attachment && Storage::disk('committee_private')->exists($attachment['attachment_path']);
        $handshakes = DB::table('gov_role_handshakes')->whereIn('id', $units['v2-handshake-decisions'] ?? [])->pluck('status')->sort()->values()->all();
        $office = $intake['location_id'] ?? 0;
        $checks['organization_actions'] = $handshakes === ['accepted', 'accepted', 'cancelled', 'rejected']
            && DB::table('gov_role_assignments')->where('location_id', $office)->where('status', 'completed')->count() === 2
            && DB::table('gov_access_requests')->whereIn('id', $units['v2-access-decisions']['request_ids'] ?? [])->pluck('status')->sort()->values()->all() === ['approved', 'declined']
            && DB::table('gov_user_onboardings')->whereIn('id', $units['v2-onboarding-decisions'] ?? [])->where('status', 'CANCELLED')->count() === 2;
        $checks['typed_metadata'] = isset($units['v2-typed-metadata'])
            && DB::table('gov_metadata_values')->where('subject_type', 'office')->whereIn('subject_id', $offices)
                ->where('namespace', OfficeTagFacts::OFFICE)->count() === count($offices);
        $codes = app(RecordRegistry::class)->ids($run, 'gov_tracking_codes');
        $checks['tracking_provenance'] = ! DB::table('gov_tracking_codes')->whereIn('id', $codes)
            ->where(fn ($q) => $q->whereNull('activated_at')->orWhere('activation_is_approximate', true))->exists();
        $deliveries = DB::table('gov_tracking_deliveries')->whereIn('tracking_code_id', $codes)->get();
        $checks['tracking_provenance'] = $checks['tracking_provenance'] && $deliveries->isNotEmpty();
        foreach ($deliveries as $delivery) {
            $checks['tracking_provenance'] = $checks['tracking_provenance'] && $delivery->movement_id && $delivery->document_item_id
                && $delivery->receipt_snapshot && DB::table('gov_inventory_movements')->where('id', $delivery->movement_id)->exists();
        }
        $checks['appearance'] = DB::table('gs_theme_preferences')->whereIn('user_id', app(RecordRegistry::class)->ids($run, 'users'))->count()
            === count(app(RecordRegistry::class)->ids($run, 'users'));
        $takeover = $units['v2-takeover'] ?? [];
        $checks['document_actions'] = DB::table('gov_documents')->where('id', $takeover['document_id'] ?? '')
            ->where('status', 'POSTED')->where('created_by', $takeover['drafter_id'] ?? 0)->where('drafted_by', $takeover['drafter_id'] ?? 0)
            ->where('posted_by', $takeover['poster_id'] ?? 0)->where('managed_by', $takeover['poster_id'] ?? 0)->exists()
            && DB::table('gov_document_notices')->where('document_id', $takeover['document_id'] ?? '')->where('event_key', 'takeover')->exists();
        $adjustments = $units['v2-stock-adjustments']['document_ids'] ?? [];
        $checks['document_actions'] = $checks['document_actions'] && count($adjustments) === 2
            && DB::table('gov_documents')->whereIn('id', $adjustments)->where('type', 'adjustment')->where('status', 'POSTED')->count() === 2;
        $transfer = $units['v2-transfer']['document_id'] ?? '';
        $checks['document_actions'] = $checks['document_actions'] && DB::table('gov_documents')->where('id', $transfer)->where('type', 'transfer')->where('status', 'POSTED')->exists()
            && DB::table('gov_documents')->where('source_document_id', $transfer)->where('type', 'receipt')->where('status', 'POSTED')->count() === 1;
        $checks['geo_sources'] = app(ActorContext::class)->run($actor, null, function () use ($offices) {
            $source = app(GeoSourceRegistry::class)->get('organization.offices');
            if (! $source) {
                return false;
            }
            $request = new GeoRequest(auth()->user());
            $seen = [];
            foreach ($source->facts($request, ['offices']) as $fact) {
                if (in_array((string) $fact->locationId, array_map('strval', $offices), true)) {
                    if ($fact->value('offices') !== 1 || ! $fact->areaId) {
                        return false;
                    }
                    $seen[] = $fact->locationId;
                }
            }

            return count(array_unique($seen)) === count($offices);
        });
        $checks['tracking_provenance'] = $checks['tracking_provenance'] && DB::table('gov_tracking_documents')->whereIn('tracking_code_id', $codes)->count() === count($codes);
        if (isset($run->report['tag_rehearsal'])) {
            $itemIds = DB::table('gov_document_items')->whereIn('document_id', $run->report['tag_rehearsal']['document_ids'])->pluck('id');
            $formats = DB::table('gov_asset_registrations')->whereIn('intake_item_id', $itemIds)->pluck('format_version')->sort()->values()->all();
            $checks['managed_tags'] = $formats === ['fd18-v1', 'native-v1'];
        }

        return $checks;
    }
}
