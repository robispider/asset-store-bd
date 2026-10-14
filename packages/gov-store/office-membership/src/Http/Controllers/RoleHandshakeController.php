<?php

namespace GovStore\OfficeMembership\Http\Controllers;

use GovStore\OfficeMembership\Http\Controllers\Concerns\RespondsWithRefusals;
use GovStore\OfficeMembership\Models\RoleHandshake;
use GovStore\OfficeMembership\Services\RoleHandshakeService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RoleHandshakeController extends Controller
{
    use RespondsWithRefusals;

    public function propose(Request $request, RoleHandshakeService $service)
    {
        $request->validate([
            'location_id' => 'required|integer',
            'role_type' => 'required|string|max:50',
            'assigned_user_id' => 'required|integer',
            'note' => 'nullable|string|max:500',
        ], [], ['assigned_user_id' => __('office_membership::member.handover_who')]);

        return $this->attempt(
            fn () => $service->proposeHandshake((int) $request->location_id, $request->role_type, (int) auth()->id(), (int) $request->assigned_user_id, $request->input('note')),
            fn () => __('office_membership::member.handshake_proposed', $this->names(RoleHandshake::where('outgoing_user_id', auth()->id())->latest('id')->first())));
    }

    public function accept($id, RoleHandshakeService $service)
    {
        return $this->attempt(fn () => $service->acceptHandshake((int) $id, (int) auth()->id()),
            fn () => __('office_membership::member.handshake_accepted', $this->names(RoleHandshake::find((int) $id))));
    }

    public function reject(Request $request, $id, RoleHandshakeService $service)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        return $this->attempt(fn () => $service->rejectHandshake((int) $id, (int) auth()->id(), $request->input('reason')),
            fn () => __('office_membership::member.handshake_rejected', $this->names(RoleHandshake::find((int) $id))));
    }

    public function cancel($id, RoleHandshakeService $service)
    {
        return $this->attempt(fn () => $service->cancelHandshake((int) $id, (int) auth()->id()),
            fn () => __('office_membership::member.handshake_cancelled', $this->names(RoleHandshake::find((int) $id))));
    }

    /** Names for whole-sentence success messages. */
    private function names(?RoleHandshake $handshake): array
    {
        $handshake?->load(['outgoingUser', 'incomingUser', 'location' => fn ($q) => $q->withoutGlobalScopes()]);

        return [
            'role' => $handshake ? __('office_membership::member.role_'.$handshake->role_type) : '',
            'office' => $handshake?->location?->name ?? '',
            'from' => $handshake?->outgoingUser?->first_name ?? '',
            'to' => $handshake?->incomingUser?->first_name ?? '',
        ];
    }
}
