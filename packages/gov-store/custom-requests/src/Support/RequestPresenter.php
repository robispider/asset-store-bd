<?php

namespace GovStore\CustomRequests\Support;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\User;
use Carbon\CarbonInterface;
use GovStore\CustomRequests\Models\Request;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Services\ApprovalRouting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns a request into words people can act on (plan P0): item and people names, one next-step sentence
 * per derived status naming who is responsible, short line labels, approver notes and a dated history.
 * One instance per HTTP request (scoped singleton), so names are looked up once.
 */
class RequestPresenter
{
    public const TYPE_CLASSES = [
        'asset_model' => AssetModel::class, 'assetmodel' => AssetModel::class, 'asset' => Asset::class,
        'accessory' => Accessory::class, 'consumable' => Consumable::class,
        'component' => Component::class, 'license' => License::class,
    ];

    private array $people = [];

    private array $items = [];

    private array $roles = [];

    public function t(string $key, array $replace = []): string
    {
        return __('requestlabels::requests.'.$key, $replace);
    }

    public function choice(string $key, int $count, array $replace = []): string
    {
        return trans_choice('requestlabels::requests.'.$key, $count, $replace + ['count' => $count]);
    }

    // ---------------------------------------------------------------- names

    /** Full display name for a user id, or "Someone no longer listed". */
    public function name(?int $userId): string
    {
        if (! $userId) {
            return $this->t('unknown_person');
        }
        if (! array_key_exists($userId, $this->people)) {
            $this->preloadPeople([$userId]);
        }

        return $this->people[$userId] ?? $this->t('unknown_person');
    }

    public function preloadPeople(iterable $ids): void
    {
        $missing = collect($ids)->filter()->map(fn ($id) => (int) $id)->unique()->reject(fn ($id) => array_key_exists($id, $this->people));
        if ($missing->isEmpty()) {
            return;
        }
        foreach (User::withTrashed()->whereIn('id', $missing->all())->get() as $user) {
            // display_name falls back to the full name (User::displayName()), never just the first name (F9).
            $this->people[(int) $user->id] = trim((string) $user->display_name) ?: trim($user->first_name.' '.$user->last_name);
        }
        foreach ($missing as $id) {
            $this->people[$id] ??= null;
        }
    }

    /** Name of a catalogue item, also for deleted or legacy rows. */
    public function itemName(?string $type, ?int $id): string
    {
        $type = strtolower((string) $type);
        $key = $type.':'.$id;
        if (! array_key_exists($key, $this->items)) {
            $this->preloadItems([[$type, $id]]);
        }

        return $this->items[$key]['name'] ?? $this->t('unknown_item');
    }

    /** Category id and purchase cost of an item (for alternatives and rule sentences). */
    public function itemFacts(?string $type, ?int $id): array
    {
        $this->itemName($type, $id);

        return $this->items[strtolower((string) $type).':'.$id] ?? ['name' => null, 'category_id' => null, 'cost' => null];
    }

    /** @param iterable<array{0:string,1:int}> $pairs */
    public function preloadItems(iterable $pairs): void
    {
        $byType = [];
        foreach ($pairs as [$type, $id]) {
            $type = strtolower((string) $type);
            if ($id && ! array_key_exists($type.':'.$id, $this->items)) {
                $byType[$type][] = (int) $id;
            }
        }
        foreach ($byType as $type => $ids) {
            $class = self::TYPE_CLASSES[$type] ?? null;
            if ($class) {
                $query = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($class)) ? $class::withTrashed() : $class::query();
                foreach ($query->whereIn('id', array_unique($ids))->get() as $model) {
                    $name = $model->name ?: ($model->asset_tag ?? null);
                    $this->items[$type.':'.$model->id] = ['name' => $name, 'category_id' => $model->category_id ?? null,
                        'cost' => $model->purchase_cost ?? null];
                }
            }
            foreach ($ids as $id) {
                $this->items[$type.':'.$id] ??= null;
            }
        }
    }

    public function preloadLines(Collection $requests): void
    {
        $pairs = [];
        $people = [];
        foreach ($requests as $request) {
            $people[] = $request->requested_by;
            $people[] = $request->assigned_approver_id;
            $people[] = $request->primary_decided_by;
            $people[] = $request->decided_by;
            foreach ($request->items as $line) {
                $pairs[] = [$line->requested_type, $line->requested_id];
                if ($line->fulfilled_id) {
                    $pairs[] = [$line->fulfilled_type, $line->fulfilled_id];
                }
            }
        }
        $this->preloadItems($pairs);
        $this->preloadPeople($people);
    }

    /** The item the line is (or will be) issued as: the alternative if one was given. */
    public function lineName(RequestItem $line): string
    {
        return $line->fulfilled_id ? $this->itemName($line->fulfilled_type, $line->fulfilled_id)
            : $this->itemName($line->requested_type, $line->requested_id);
    }

    public function typeLabel(?string $type): string
    {
        $type = strtolower((string) $type);
        $type = $type === 'assetmodel' ? 'asset_model' : $type;
        $key = 'requestlabels::requests.type_'.$type;

        return trans()->has($key) ? __($key) : $this->t('type_asset_model');
    }

    /** Kind of request; legacy stored values (e.g. "equipment") get a label instead of a leaked key. */
    public function requestType(?string $type): string
    {
        return in_array($type, RequestWorkflow::REQUEST_TYPES, true) ? $this->t('request_type_'.$type) : $this->t('request_type_legacy');
    }

    /** "Toner ×1, A4 paper ×2 and 2 more". */
    public function itemsSummary(Request $request, int $limit = 3, string $qty = 'requested_qty'): string
    {
        $lines = $request->items;
        $parts = $lines->take($limit)->map(fn ($line) => $this->t('item_qty', ['item' => $this->lineName($line), 'qty' => (int) $line->{$qty}]));
        $text = $parts->implode(', ');
        if ($lines->count() > $limit) {
            $text .= ' '.$this->choice('and_more', $lines->count() - $limit);
        }

        return $text;
    }

    // ---------------------------------------------------------------- people responsible

    public function storeName(?int $office): string
    {
        $id = $office ? ($this->roleHolders($office, 'storekeeper')[0] ?? null) : null;

        return $id ? $this->name($id) : $this->t('store_team');
    }

    public function officeHeadId(?int $office): ?int
    {
        return $office ? (int) DB::table('gov_location_profiles')->where('location_id', $office)->value('office_admin_id') ?: null : null;
    }

    public function roleHolders(int $office, string $role, array $exclude = []): array
    {
        $key = $office.':'.$role;
        $this->roles[$key] ??= app(ApprovalRouting::class)->candidates($office, $role);

        return array_values(array_diff($this->roles[$key], array_filter($exclude)));
    }

    /** Who gives the final approval after the first approver (excluding the requester and the first approver). */
    public function finalApproverId(Request $request): ?int
    {
        return $request->office_id ? ($this->roleHolders($request->office_id, 'final_approver',
            [$request->requested_by, $request->assigned_approver_id, $request->primary_decided_by])[0] ?? null) : null;
    }

    public function escalationDate(Request $request): CarbonInterface
    {
        return ($request->updated_at ?? now())->copy()->addWeekdays(max(1, (int) config('govstore-requests.escalation_weekdays', 3)));
    }

    // ---------------------------------------------------------------- dates

    public function date(?CarbonInterface $at): string
    {
        return $at ? $at->translatedFormat('j M Y') : '';
    }

    public function dateTime(?CarbonInterface $at): string
    {
        return $at ? $at->translatedFormat('j M Y, H:i') : '';
    }

    public function timesNote(): string
    {
        return $this->t('times_note', ['zone' => config('app.timezone').' (UTC'.now()->format('P').')']);
    }

    // ---------------------------------------------------------------- statuses and next steps

    /** One whole sentence: where the request is and who acts next (board 2). */
    public function nextStep(Request $request): string
    {
        $status = $request->statusKey();

        return match ($status) {
            'request_pending_primary' => $this->nextPendingPrimary($request),
            // The requester is the office's first approver, so routing went straight to an independent final approver.
            'request_pending_final' => ! $request->primary_decided_by && $request->assigned_approver_id
                ? $this->t('next_pending_final_direct', ['approver' => $this->name($request->assigned_approver_id)])
                : ($request->assigned_approver_id
                ? $this->t('next_pending_final',['first' => $this->name($request->primary_decided_by), 'approver' => $this->name($request->assigned_approver_id)])
                : $this->t('next_pending_final_unassigned', ['first' => $this->name($request->primary_decided_by)])),
            'request_approved' => $this->t('next_approved', ['store' => $this->storeName($request->office_id)]),
            'request_partly_issued' => $this->t('next_partly_issued', [
                'issued' => (int) $request->items->sum('issued_qty'),
                'approved' => (int) $request->items->where('line_approval_status', 'approved')->sum('approved_qty')]),
            'request_issued' => $this->t('next_issued', ['store' => $this->storeName($request->office_id),
                'date' => $this->date($this->lastEventAt($request, ['issued', 'item_issued']) ?? $request->closed_at)]),
            'request_received' => $this->t($this->returnableLines($request)->isNotEmpty() ? 'next_received_return' : 'next_received',
                ['date' => $this->date($request->received_at)]),
            'request_closed_short' => ($reason = $this->closeReason($request))
                ? $this->t('next_closed_short', ['date' => $this->date($request->closed_at), 'reason' => $reason])
                : $this->t('next_closed_short_plain', ['date' => $this->date($request->closed_at)]),
            'request_rejected' => ($note = $this->decisionNotes($request)->first())
                ? $this->t('next_rejected', ['approver' => $this->name($request->decided_by), 'date' => $this->date($request->closed_at ?? $request->updated_at), 'note' => $note['note']])
                : $this->t('next_rejected_plain', ['approver' => $this->name($request->decided_by), 'date' => $this->date($request->closed_at ?? $request->updated_at)]),
            'request_withdrawn' => $this->t('next_withdrawn', ['date' => $this->date($request->closed_at ?? $request->updated_at)]),
            'request_return_requested' => $this->t('next_return_requested', ['store' => $this->storeName($request->office_id)]),
            'request_return_drafted' => $this->t('next_return_drafted'),
            default => $this->t('next_needs_review'),
        };
    }

    private function nextPendingPrimary(Request $request): string
    {
        if (! $request->assigned_approver_id) {
            return $this->t('next_pending_unassigned');
        }
        $approver = $this->name($request->assigned_approver_id);
        if ($this->escalationDate($request)->isPast()) {
            return $this->t('next_pending_overdue', ['approver' => $approver, 'days' => (int) config('govstore-requests.escalation_weekdays', 3)]);
        }
        if ($request->resolved_policy === 'PRIMARY_AND_FINAL' && ($final = $this->finalApproverId($request))) {
            return $this->t('next_pending_two', ['approver' => $approver, 'final' => $this->name($final)]);
        }

        return $this->t('next_pending_primary', ['approver' => $approver, 'date' => $this->date($this->escalationDate($request))]);
    }

    /** Requested approvers by group, for a group in the draft split preview. */
    public function routeSentence(string $policy, int $office, int $requester): string
    {
        $submission = app(ApprovalRouting::class)->submission($office, $policy, $requester);
        if ($submission['authority']) {
            if ($submission['status'] === 'approved') {
                return $this->t('group_authority_auto');
            }
            $final = $this->roleHolders($office, 'final_approver', [$requester])[0] ?? null;

            return $final ? $this->t('group_authority_final', ['name' => $this->name($final)])
                : $this->t('group_authority_final_unassigned');
        }
        if ($policy === 'AUTO_APPROVE') {
            return $this->t('group_auto');
        }
        $primary = $this->roleHolders($office, 'primary_approver', [$requester])[0] ?? null;
        if ($policy === 'PRIMARY_AND_FINAL') {
            $final = $this->roleHolders($office, 'final_approver', [$requester, $primary])[0] ?? null;

            return $primary && $final ? $this->t('group_two', ['first' => $this->name($primary), 'final' => $this->name($final)]) : $this->t('group_two_unassigned');
        }
        // One approver falls back to a final approver when the office has no other first approver (ApprovalRouting::assign).
        $primary ??= $this->roleHolders($office, 'final_approver', [$requester])[0] ?? null;

        return $primary ? $this->t('group_one', ['name' => $this->name($primary)]) : $this->t('group_one_unassigned');
    }

    /** The short rule label on catalogue cards and draft lines: "No approval", "1 approver", "2 approvers". */
    public function ruleBadge(string $policy): string
    {
        return $this->t(match ($policy) { 'AUTO_APPROVE' => 'badge_rule_auto', 'PRIMARY_AND_FINAL' => 'badge_rule_two', default => 'badge_rule_one' });
    }

    /** Who reads "Why is it needed?": the first approver of the first group that needs one, if the office has one. */
    public function firstReader(array $policies, int $office, int $requester): ?string
    {
        $pending = collect($policies)->map(fn ($policy) => app(ApprovalRouting::class)->submission($office, $policy, $requester))
            ->filter(fn ($plan) => $plan['status'] !== 'approved');
        if ($pending->isEmpty()) {
            return null;
        }
        if ($pending->every(fn ($plan) => $plan['status'] === 'pending_final')) {
            $final = $this->roleHolders($office, 'final_approver', [$requester])[0] ?? null;

            return $final ? $this->name($final) : null;
        }
        $first = $this->roleHolders($office, 'primary_approver', [$requester])[0]
            ?? $this->roleHolders($office, 'final_approver', [$requester])[0] ?? null;

        return $first ? $this->name($first) : null;
    }

    /** One sentence for a line that changed after the person reviewed their draft (BasketService::changes). */
    public function changeSentence(array $change): string
    {
        [$type, $id] = array_pad(explode(':', $change['item'], 2), 2, 0);
        $item = $this->itemName($type, (int) $id);

        return match ($change['change']) {
            'added' => $this->t('changed_added', ['item' => $item, 'qty' => $change['qty']]),
            'removed' => $this->t('changed_removed', ['item' => $item]),
            'qty' => $this->t('changed_qty', ['item' => $item, 'qty' => $change['qty'], 'before' => $change['before']]),
            'rule' => $this->t('changed_rule', ['item' => $item, 'rule' => $this->ruleWords((string) $change['policy'])]),
            'authority' => $this->t('changed_authority', ['item' => $item]),
            'gone' => $this->t('changed_gone', ['item' => $item]),
            default => $this->t('changed_short', ['item' => $item, 'qty' => $change['qty']]),
        };
    }

    public function ruleWords(string $policy): string
    {
        return $this->t(match ($policy) { 'AUTO_APPROVE' => 'rule_auto', 'PRIMARY_AND_FINAL' => 'rule_two', default => 'rule_one' });
    }

    /** Sentences explaining why the request needed its approvers, from resolved_reason (D3). */
    public function ruleReasons(Request $request): array
    {
        $reasons = collect($request->resolved_reason['lines'] ?? []);
        if ($reasons->isEmpty()) {
            return $request->resolved_policy === 'PRIMARY_AND_FINAL' ? [$this->t('reason_unknown')] : [];
        }

        $sentences = $reasons->map(fn ($reason) => $this->reasonSentence($reason));
        if ($request->resolved_reason['submission']['authority'] ?? null) {
            $sentences->push($this->t($request->primarySatisfiedOnSubmission() ? 'reason_authority_final' : 'reason_authority_auto'));
        }

        return $sentences->filter()->unique()->values()->all();
    }

    public function reasonSentence(array $reason): ?string
    {
        $item = $this->itemName($reason['type'] ?? null, (int) ($reason['id'] ?? 0));

        return match ($reason['threshold'] ?? null) {
            'qty' => $this->t('reason_qty', ['item' => $item, 'qty' => $reason['threshold_qty'] ?? '']),
            'value' => $this->t('reason_value', ['item' => $item, 'value' => number_format((float) ($reason['threshold_value'] ?? 0))]),
            'cost' => $this->t('reason_cost', ['item' => $item]),
            default => match ($reason['source'] ?? 'default') {
                'item' => $this->t('reason_item_rule', ['item' => $item, 'rule' => $this->ruleWords($reason['policy'])]),
                'category' => $this->t('reason_category_rule', ['category' => Category::withTrashed()->find($reason['category_id'] ?? 0)?->name ?? $item,
                    'rule' => $this->ruleWords($reason['policy'])]),
                default => $this->t('reason_default', ['item' => $item]),
            },
        };
    }

    // ---------------------------------------------------------------- lines

    public function lineLabel(RequestItem $line, Request $request): string
    {
        $approved = (int) $line->approved_qty;

        return match (true) {
            $request->approval_status === 'cancelled' || $line->line_approval_status === 'cancelled' => $this->t('line_cancelled'),
            $line->line_approval_status === 'rejected' => $this->t('line_rejected'),
            $line->line_approval_status === 'pending' => $this->t($request->approval_status === 'pending_final' ? 'line_waiting_final' : 'line_waiting'),
            $approved > 0 && $line->issued_qty >= $approved => $this->t('line_issued'),
            $line->issued_qty > 0 && $line->line_fulfillment_status === 'cancelled' => $this->t('line_issued_of', ['issued' => $line->issued_qty, 'approved' => $approved]),
            $line->line_fulfillment_status === 'cancelled' => $this->t('line_closed'),
            $line->issued_qty > 0 => $this->t('line_issued_of', ['issued' => $line->issued_qty, 'approved' => $approved]),
            $approved < $line->requested_qty => $this->t('line_approved_of', ['approved' => $approved, 'requested' => $line->requested_qty]),
            default => $this->t('line_as_asked'),
        };
    }

    /** Status tone for a line label (badge colour is never the only signal: the label is text). */
    public function lineTone(RequestItem $line): string
    {
        return match (true) {
            in_array($line->line_approval_status, ['rejected', 'cancelled'], true) => 'secondary',
            $line->line_approval_status === 'pending' => 'warning',
            $line->approved_qty > 0 && $line->issued_qty >= $line->approved_qty => 'success',
            $line->line_fulfillment_status === 'cancelled' => 'secondary',
            default => 'info',
        };
    }

    /** Approver notes per line id, newest stage last: [line_id => ['by' => id, 'note' => text]]. */
    public function decisionNotes(Request $request): Collection
    {
        $notes = [];
        foreach ($request->events->sortBy('id') as $event) {
            if (! in_array($event->event_type, ['pending_final', 'approved', 'partially_approved', 'rejected'], true)) {
                continue;
            }
            foreach ($event->details['decisions'] ?? [] as $lineId => $decision) {
                if (filled($decision['notes'] ?? null)) {
                    $notes[(int) $lineId] = ['by' => $event->user_id, 'note' => trim($decision['notes'])];
                }
            }
        }

        return collect($notes);
    }

    public function lineNote(RequestItem $line, Request $request): ?string
    {
        $note = $this->decisionNotes($request)->get($line->id);

        return $note ? $this->t('line_note', ['name' => $this->name($note['by']), 'note' => $note['note']]) : null;
    }

    /** Bulk lines that can be returned through the store (serialised equipment uses native check-in). */
    public function returnableLines(Request $request): Collection
    {
        return $request->items->filter(fn ($line) => $line->issued_qty > 0
            && in_array($line->fulfilled_type ?: $line->requested_type, ['accessory', 'consumable'], true));
    }

    public function closeReason(Request $request): ?string
    {
        $event = $request->events->where('event_type', 'closed')->sortByDesc('id')->first();

        return $event ? ($event->details['reason'] ?? null) : null;
    }

    public function lastEventAt(Request $request, array $types): ?CarbonInterface
    {
        return $request->events->whereIn('event_type', $types)->sortByDesc('id')->first()?->created_at;
    }

    /** Sent → Approved → Issued → Received for <x-gs::stepper>. */
    public function steps(Request $request): array
    {
        $status = $request->statusKey();
        $approvedAt = $request->approved_at;
        $issuedAt = $request->fulfillment_status === 'issued' || in_array($status, ['request_received', 'request_return_requested', 'request_return_drafted'], true)
            ? ($this->lastEventAt($request, ['issued', 'item_issued']) ?? $request->closed_at) : null;
        $label = fn ($step, $at) => $at ? $this->t('step_on', ['step' => $this->t($step), 'date' => $this->date($at)]) : $this->t($step);
        $stopped = in_array($status, ['request_rejected', 'request_withdrawn'], true);
        $short = $status === 'request_closed_short';

        return [
            ['label' => $label('step_sent', $request->submitted_at ?? $request->created_at), 'state' => 'done'],
            ['label' => $label('step_approved', $approvedAt), 'state' => $approvedAt ? 'done' : ($stopped ? 'blocked' : 'current')],
            ['label' => $label('step_issued', $issuedAt), 'state' => $issuedAt ? 'done' : ($short ? 'blocked' : ($approvedAt && ! $stopped ? 'current' : 'upcoming'))],
            ['label' => $label('step_received', $request->received_at), 'state' => $request->received_at ? 'done' : ($issuedAt && ! $request->received_at ? 'current' : 'upcoming')],
        ];
    }

    // ---------------------------------------------------------------- history

    /** Newest first, ready for <x-gs::timeline>: full names, a sentence and the dated local time. */
    public function timeline(Request $request): array
    {
        $events = $request->events->sortByDesc('id');
        $this->preloadPeople($events->pluck('user_id'));

        return $events->map(function (RequestEvent $event) use ($request) {
            [$action, $target] = $this->eventSentence($event, $request);

            return ['by' => $event->event_type === 'escalated' ? $this->t('system') : $this->name($event->user_id),
                'action' => $action, 'target' => $target, 'at' => $this->dateTime($event->created_at)];
        })->values()->all();
    }

    /** @return array{0:string,1:?string} the action phrase and an optional detail line */
    public function eventSentence(RequestEvent $event, Request $request): array
    {
        $d = $event->details ?? [];
        $decisions = collect($d['decisions'] ?? []);
        $extra = collect([
            filled($d['handed_to'] ?? null) ? $this->t('history_handed_to', ['name' => $d['handed_to']]) : null,
            filled($d['goods_issue'] ?? null) ? $this->t('history_document', ['number' => $d['goods_issue']]) : null,
            filled($d['notes'] ?? null) ? $this->t('history_note', ['note' => $d['notes']]) : null,
        ])->merge($decisions->pluck('notes')->filter()->map(fn ($note) => $this->t('history_note', ['note' => $note])))->filter()->implode(' ') ?: null;

        $action = match ($event->event_type) {
            'submitted' => $request->approval_status === 'pending_primary' && $request->assigned_approver_id
                ? $this->t('history_submitted_to', ['approver' => $this->name($request->assigned_approver_id)]) : $this->t('history_submitted'),
            'auto_approved' => $this->t(($d['submission']['authority'] ?? null) ? 'history_authority_auto' : 'history_auto_approved'),
            'pending_final' => $this->t('history_pending_final'),
            'approved' => $this->decisionReduced($decisions, $request) ? $this->t('history_approved_fewer') : $this->t('history_approved'),
            'partially_approved' => $this->t('history_partially_approved', [
                'approved' => $decisions->where('status', 'approved')->count(), 'total' => max($decisions->count(), $request->items->count())]),
            'rejected' => $this->t('history_rejected'),
            'cancelled' => $this->t('history_cancelled'),
            'item_substituted' => $this->t('history_item_substituted', ['new' => $d['substituted_with'] ?? '', 'old' => $d['original'] ?? '']),
            'item_issued' => $this->t('history_item_issued', ['item' => $d['item'] ?? $this->t('unknown_item'), 'qty' => $d['issued_qty'] ?? 0]),
            'issued' => $this->t('history_issued'),
            'closed' => $this->t('history_closed', ['reason' => $d['reason'] ?? '']),
            'received' => $this->t('history_received'),
            'escalated' => $this->t('history_escalated', ['days' => (int) config('govstore-requests.escalation_weekdays', 3)]),
            'return_requested' => $this->t('history_return_requested', ['reason' => $d['reason'] ?? '']),
            'return_drafted' => $this->t('history_return_drafted', ['number' => $d['document_number'] ?? '']),
            'asset_metadata_repaired' => $this->t('history_asset_metadata_repaired'),
            default => $this->t('history_other'),
        };

        return [$action, in_array($event->event_type, ['closed', 'return_requested'], true) ? null : $extra];
    }

    private function decisionReduced(Collection $decisions, Request $request): bool
    {
        $lines = $request->items->keyBy('id');

        return $decisions->contains(fn ($decision, $id) => ($decision['status'] ?? '') === 'approved'
            && isset($lines[$id]) && (int) ($decision['qty'] ?? 0) < (int) $lines[$id]->requested_qty);
    }

    // ---------------------------------------------------------------- updates

    /** A notice row as a sentence with its link and date. */
    public function notice(object $notice): array
    {
        $key = 'requestlabels::requests.notice_'.$notice->event_key;
        $at = $notice->created_at ? \Illuminate\Support\Carbon::parse($notice->created_at) : null;

        return [
            'text' => trans()->has($key) ? __($key, ['number' => $notice->request_number]) : $this->t('notice_other', ['number' => $notice->request_number]),
            'href' => route('gov.requests.user.show', $notice->request_id),
            'number' => $notice->request_number,
            'at' => $at?->isToday() ? $at->translatedFormat('H:i') : $this->date($at),
            'when' => $this->dateTime($at),
            'unread' => $notice->read_at === null,
        ];
    }
}
