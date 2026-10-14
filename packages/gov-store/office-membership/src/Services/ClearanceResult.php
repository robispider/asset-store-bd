<?php

namespace GovStore\OfficeMembership\Services;

class ClearanceResult
{
    public bool $isPassed;

    public string $reason;

    /**
     * What still blocks, one entry per kind of holding, so screens can list it ("2 assets") rather than
     * repeat a sentence of zero counts. Each entry: ['label' => string, 'count' => int]. Optional for rules.
     */
    public array $items;

    public function __construct(bool $isPassed, string $reason = '', array $items = [])
    {
        $this->isPassed = $isPassed;
        $this->reason = $reason;
        $this->items = $items;
    }

    /** What is still open, short enough to sit inside another sentence: "2 assets, 1 role" or the rule's reason. */
    public function summary(): string
    {
        return $this->items ? collect($this->items)->pluck('label')->implode(', ') : rtrim(trim($this->reason), '.।');
    }
}
