<?php

namespace GovStore\CustomRequests\Support;

/** Persisted values are shared by services, queues and validation. */
final class RequestWorkflow
{
    public const PENDING = ['pending_primary', 'pending_final'];

    public const APPROVED = ['approved', 'partially_approved'];

    public const OPEN_FULFILLMENT = ['unstarted', 'partially_issued'];

    public const POLICIES = ['AUTO_APPROVE', 'PRIMARY_ONLY', 'PRIMARY_AND_FINAL'];

    public const TYPES = ['asset_model', 'accessory', 'consumable'];

    public const REQUEST_TYPES = ['new_employee', 'replacement', 'project', 'office_setup', 'repair', 'emergency', 'other'];

    public const MAX_QUANTITY = 10000;
}
