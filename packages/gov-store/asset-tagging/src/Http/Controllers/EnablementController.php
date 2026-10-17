<?php

namespace GovStore\AssetTagging\Http\Controllers;

use GovStore\AssetTagging\Services\EnablementGate;
use GovStore\AssetTagging\Services\OfficeTagFacts;
use GovStore\Metadata\Services\MetadataValueStore;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * S8 · the deployment operator's console. Enabling writes the office's asset-tagging.enablement row only
 * after every gate passes; the POST runs under a national ability, so tenant-scope's national change
 * review freezes the exact change, asks for a typed confirmation and records the reason.
 */
class EnablementController extends Controller
{
    public function __construct(private EnablementGate $gate, private OfficeTagFacts $facts, private MetadataValueStore $store) {}

    public function index(Request $request)
    {
        $request->validate(['company' => 'nullable|integer']);
        $enablement = $this->facts->available() ? $this->store->all(OfficeTagFacts::ENABLEMENT, 'office') : collect();
        $officeFacts = $this->facts->available() ? $this->store->all(OfficeTagFacts::OFFICE, 'office') : collect();
        $offices = DB::table('locations')->whereNull('deleted_at')
            ->when($request->filled('company'), fn ($q) => $q->where('company_id', $request->integer('company')))
            ->whereIn('id', $officeFacts->keys()->merge($enablement->keys())->all())
            ->orderBy('name')->get(['id', 'name', 'company_id'])
            ->map(fn ($office) => ['id' => $office->id, 'name' => $office->name, 'facts' => $officeFacts->get($office->id)?->payload,
                'enabled' => (bool) ($enablement->get($office->id)?->payload['enabled'] ?? false),
                'reason' => $enablement->get($office->id)?->payload['reason'] ?? null,
                'gates' => $this->gate->office((int) $office->id)]);
        $activity = DB::table('gov_access_events')->where('ability', 'like', 'asset-tags.%')
            ->orderByDesc('created_at')->limit(20)->get(['ability', 'outcome', 'reason', 'user_id', 'location_id', 'created_at']);

        return view('asset-tagging::enablement.index', ['installation' => $this->gate->installation(), 'offices' => $offices,
            'activity' => $activity, 'clashes' => $this->gate->legacyClashes()]);
    }

    public function enable(Request $request, int $office)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        $failing = collect($this->gate->all($office))->reject(fn ($gate) => $gate['passed'])->pluck('key')->all();
        if ($failing) {
            throw ValidationException::withMessages(['reason' => __('asset-tagging::tagging.enable.gates_failing', ['gates' => implode(', ', $failing)])]);
        }
        $this->write($office, true, $data['reason'], now()->toIso8601String());

        return back()->with('success', __('asset-tagging::tagging.enable.enabled'));
    }

    public function disable(Request $request, int $office)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        $this->write($office, false, $data['reason'], null);

        return back()->with('success', __('asset-tagging::tagging.enable.disabled'));
    }

    private function write(int $office, bool $enabled, string $reason, ?string $gatesPassedAt): void
    {
        $row = $this->store->find('office', $office, OfficeTagFacts::ENABLEMENT);
        $this->store->put('office', $office, OfficeTagFacts::ENABLEMENT,
            ['enabled' => $enabled, 'reason' => $reason, 'gates_passed_at' => $gatesPassedAt], $row?->revision, (int) auth()->id(), $reason);
    }
}
