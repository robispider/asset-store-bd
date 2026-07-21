<?php

namespace GovStore\StoreOperations\Traits;

use GovStore\StoreOperations\Enums\DocumentState;
use GovStore\StoreOperations\Models\DocumentTimeline;
use Exception;

trait HasDocumentState
{
    public function getState(): DocumentState
    {
        // 1. Handle legacy records: Map old 'SUBMITTED' string to 'POSTED'
        $rawStatus = $this->status === 'SUBMITTED' ? 'POSTED' : $this->status;

        // 2. Use tryFrom() for safety, falling back to DRAFT if an unknown string is found
        return DocumentState::tryFrom($rawStatus) ?? DocumentState::DRAFT;
    }

    public function transitionTo(DocumentState $newState, int $userId, string $notes = ''): void
    {
        $currentState = $this->getState();

        if ($currentState === DocumentState::POSTED) {
            throw new Exception("Cannot alter a document that has already been posted to the ledger.");
        }

        if ($currentState === DocumentState::CANCELLED) {
            throw new Exception("Cannot alter a cancelled document.");
        }

        // Update Model
        $this->update(['status' => $newState->value]);

        // Record Timeline Audit Trail
        $this->timelines()->create([
            'state' => $newState->value,
            'user_id' => $userId,
            'notes' => $notes,
        ]);
    }

    public function timelines()
    {
        // Requires models to import GovStore\StoreOperations\Models\DocumentTimeline 
        // or reference it fully like this:
        return $this->morphMany(\GovStore\StoreOperations\Models\DocumentTimeline::class, 'document');
    }
}