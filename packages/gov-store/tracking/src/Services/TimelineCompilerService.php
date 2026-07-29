<?php

namespace GovStore\Tracking\Services;

use App\Models\Actionlog;
use App\Models\Asset;
use GovStore\Tracking\Models\TrackingReference;
use GovStore\Tracking\Models\TrackingTimeline;
use GovStore\Tracking\Models\TrackingAssociation;
use Illuminate\Support\Collection;

class TimelineCompilerService
{
    /**
     * Compile a unified chronological log containing both explicit reference events
     * and transactional ledger events from linked inventory assets.
     */
    public function compileUnifiedTimeline(TrackingReference $reference): Collection
    {
        $timelineEvents = collect();

        // 1. Fetch Explicit Administrative Timeline Events
        $adminEvents = TrackingTimeline::with('actor')
            ->where('tracking_reference_id', $reference->id)
            ->get();

        foreach ($adminEvents as $event) {
            $timelineEvents->push([
                'type' => 'admin',
                'event_type' => $event->event_type,
                'description' => $event->description,
                'actor_name' => $event->actor ? "{$event->actor->first_name} {$event->actor->last_name}" : 'System',
                'meta' => $event->metadata,
                'timestamp' => $event->occurred_at,
            ]);
        }

        // 2. Fetch Transactional History of Associated Core Assets
        $associatedAssetIds = TrackingAssociation::where('tracking_reference_id', $reference->id)
            ->where('associatable_type', Asset::class)
            ->where('status', 'ACTIVE')
            ->pluck('associatable_id')
            ->toArray();

        if (!empty($associatedAssetIds)) {
            $ledgerLogs = Actionlog::with('user', 'item')
                ->whereIn('item_id', $associatedAssetIds)
                ->where('item_type', Asset::class)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($ledgerLogs as $log) {
                $timelineEvents->push([
                    'type' => 'ledger',
                    'event_type' => $log->action_type,
                    'description' => "Asset #{$log->item->asset_tag} ({$log->item->name}) processed for transaction type: {$log->action_type}.",
                    'actor_name' => $log->user ? "{$log->user->first_name} {$log->user->last_name}" : 'System',
                    'meta' => [
                        'asset_id' => $log->item_id,
                        'notes' => $log->note,
                        'target' => $log->target_id ? "Target #{$log->target_id}" : null,
                    ],
                    'timestamp' => $log->created_at,
                ]);
            }
        }

        // Sort dynamically in reverse chronological order
        return $timelineEvents->sortByDesc('timestamp')->values();
    }
}
