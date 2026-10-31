# Full reset — proposed table inventory

Date: 24 October 2026 (Asia/Dhaka). Companion to the [full reset plan](super-admin-full-reset-plan-2026-10-24.md).

Read-only discovery of the explicitly selected local database `snipeit`: 163 tables, all InnoDB, no views. Counts include soft-deleted rows and are a dated, non-atomic snapshot; they are not an execution preview. No application data is reproduced here. Recompute scope under the writer barrier before execution.

This is a proposed classification, not runnable deletion configuration. The reviewed implementation must declare exact stable baseline identities, relationships, file ownership and supported schema hashes. Evidence/counter exceptions are deliberate. ARCHIVE THEN CLEAR requires a supported protected archive; a database backup alone is insufficient. Until then retain/block rather than silently deleting history. All retained audit references need historical context and protection against ID reuse.

Absent from the installed schema despite historical migration definitions: `gov_capabilities` and `gov_requirement_definitions`. Installed `gov_profile_capabilities` is code-based. Reconcile before baseline approval. Runtime sessions/cache are currently file-backed and therefore absent from this list. Future queue/session/cache tables also require explicit policies.

| Table | Rows | Proposed treatment | Rule |
| --- | ---: | --- | --- |
| `accessories` | 704 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `accessories_checkout` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `action_logs` | 14,439 | ARCHIVE THEN CLEAR | Preserve complete private history/attribution through verified archive before clearing active copy; otherwise retain/block. |
| `asset_logs` | 0 | ARCHIVE THEN CLEAR | Preserve complete private history/attribution through verified archive before clearing active copy; otherwise retain/block. |
| `asset_uploads` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `assets` | 4,853 | WIPE | Include soft-deleted assets and values in all extension columns; physical custom columns need declared policy. |
| `categories` | 179 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `checkout_acceptances` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `checkout_requests` | 3 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `companies` | 527 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `company_user` | 896 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `components` | 352 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `components_assets` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `consumables` | 1,061 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `consumables_users` | 12 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `custom_field_custom_fieldset` | 1,033 | RESTORE BASELINE | Migration MAC link only. |
| `custom_fields` | 7 | RESTORE BASELINE | Migration MAC field only; generated-column policy required. |
| `custom_fieldsets` | 136 | RESTORE BASELINE | Migration MAC fieldset only. |
| `custom_request_notices` | 893 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `custom_request_sequences` | 1 | RETAIN COUNTER | Prevent reuse of request numbers. |
| `custom_service_request_events` | 1,088 | ARCHIVE THEN CLEAR | Preserve complete private history/attribution through verified archive before clearing active copy; otherwise retain/block. |
| `custom_service_request_items` | 611 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `custom_service_requests` | 608 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `departments` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `depreciations` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `draft_basket_items` | 352 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `draft_baskets` | 92 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_access_events` | 640 | RETAIN EVIDENCE | Indefinite security audit; preserve restricted visibility and attribution. |
| `gov_access_grants` | 2 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_access_notices` | 8 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_access_requests` | 4 | ARCHIVE THEN CLEAR | Preserve complete private history/attribution through verified archive before clearing active copy; otherwise retain/block. |
| `gov_access_review_uses` | 56 | RETAIN EVIDENCE | Durable replay consumption; do not permit old review reuse. |
| `gov_approval_policies` | 328 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_asset_registrations` | 4,807 | BLOCK IF ISSUED | 20 managed-tag rows currently block full execution. Nonissued registrations can clear after approved evidence treatment. |
| `gov_asset_tag_sequences` | 2 | RETAIN COUNTER | Permanent tag number authority. |
| `gov_catalog_collection_nodes` | 122 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_catalog_collections` | 13 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_catalog_definitions` | 143,573 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_catalog_enrichments` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_catalog_import_history` | 1 | ARCHIVE THEN CLEAR | Preserve complete private history/attribution through verified archive before clearing active copy; otherwise retain/block. |
| `gov_catalog_nodes` | 158,463 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_catalog_snipe_mappings` | 50 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_catalog_synonyms` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_category_governance` | 173 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_classification_external_mappings` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_classification_metadata` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_classification_nodes` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_classification_synonyms` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_committee_active_slots` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_committee_catalog_mutexes` | 2 | RESTORE BASELINE | Two migration mutex keys. |
| `gov_committee_external_members` | 6 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_committee_health` | 11 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_committee_ledger` | 126 | RETAIN EVIDENCE | Immutable hash chain, original payload and attribution. |
| `gov_committee_order_intakes` | 2 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_committee_orders` | 17 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_committee_purpose_bindings` | 1 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_committee_reminder_dismissals` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_committee_reviews` | 0 | RETAIN EVIDENCE | Review evidence; preserve replay/audit integrity. |
| `gov_committee_scopes` | 11 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_committee_seat_roles` | 7 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_committee_seats` | 33 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_committee_sequences` | 3 | RETAIN COUNTER | Prevent reuse of committee/order numbers. |
| `gov_committee_tenures` | 33 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_committee_types` | 10 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_committees` | 12 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_company_admins` | 13 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_document_attachments` | 364 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_document_item_meta` | 14,342 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_document_items` | 4,679 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_document_notices` | 1 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_document_references` | 435 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_document_timelines` | 855 | ARCHIVE THEN CLEAR | Preserve complete private history/attribution through verified archive before clearing active copy; otherwise retain/block. |
| `gov_documents` | 483 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_employee_verification_tokens` | 8 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_experiment_actions` | 17 | ARCHIVE THEN CLEAR | Preserve complete private history/attribution through verified archive before clearing active copy; otherwise retain/block. |
| `gov_experiment_failed_jobs` | 1 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_experiment_jobs` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_experiment_records` | 76,973 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_experiment_runs` | 6 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_finance_items` | 490 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_funding_types` | 8 | RESTORE BASELINE | Six migration rows. |
| `gov_geo_areas` | 177,685 | RESTORE BASELINE | Bundled geography/BBS content; do not preserve arbitrary current rows. |
| `gov_goods_issue_items` | 144 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_goods_issues` | 99 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_goods_receipt_items` | 3 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_goods_receipts` | 4 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_ict_jurisdictions` | 18 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_initiatives` | 16 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_inventory_movements` | 4,026 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_location_profiles` | 105 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_membership_notices` | 500 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_metadata_field_mappings` | 4 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_metadata_values` | 168 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_ministries_directory` | 526 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_model_metadata_states` | 144 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_office_memberships` | 908 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_office_responsibilities` | 292 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_office_starter_runs` | 48 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_onboarding_events` | 924 | ARCHIVE THEN CLEAR | Preserve complete private history/attribution through verified archive before clearing active copy; otherwise retain/block. |
| `gov_onboarding_notices` | 1,848 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_organization_activity_logs` | 1,339 | ARCHIVE THEN CLEAR | Preserve complete private history/attribution through verified archive before clearing active copy; otherwise retain/block. |
| `gov_override_audit_logs` | 0 | RETAIN EVIDENCE | Override history remains protected. |
| `gov_profile_assignments` | 186 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_profile_capabilities` | 402 | COMPATIBILITY GATE | Historical four links need current schema adapter. |
| `gov_profiles` | 141 | COMPATIBILITY GATE | Historical three-profile baseline needs reconciliation with installed code-based architecture. |
| `gov_role_assignments` | 2 | ARCHIVE THEN CLEAR | Preserve complete private history/attribution through verified archive before clearing active copy; otherwise retain/block. |
| `gov_role_handshakes` | 36 | ARCHIVE THEN CLEAR | Preserve complete private history/attribution through verified archive before clearing active copy; otherwise retain/block. |
| `gov_stock_adjustment_items` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_stock_adjustments` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_store_document_sequences` | 5 | RETAIN COUNTER | Prevent reuse of document numbers. |
| `gov_store_ledger_openings` | 46 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_tenant_scope_mappings` | 4,325 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_tenant_scopes` | 6 | RESTORE BASELINE | Six migration scope strategies; not current custom values. |
| `gov_tracking_allocations` | 585 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_tracking_associations` | 2,623 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_tracking_codes` | 57 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_tracking_deliveries` | 288 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_tracking_documents` | 24 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_tracking_fact_deliveries` | 580 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_tracking_operation_units` | 53 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_tracking_projection_caches` | 16 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_tracking_scopes` | 114 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_tracking_targets` | 588 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_tracking_timeline` | 655 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gov_user_onboardings` | 935 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gs_theme_assignments` | 2 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `gs_theme_preferences` | 487 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `imports` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `kits` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `kits_accessories` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `kits_consumables` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `kits_licenses` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `kits_models` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `license_seats` | 480 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `licenses` | 96 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `locations` | 106 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `login_attempts` | 1,529 | ARCHIVE THEN CLEAR | Preserve complete private history/attribution through verified archive before clearing active copy; otherwise retain/block. |
| `maintenance_types` | 10 | RESTORE BASELINE | Eight migration types. |
| `maintenances` | 72 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `manufacturers` | 16 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `migrations` | 541 | KEEP | Installed migration ledger; 541 rows at inspection. |
| `models` | 133 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `models_custom_fields` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `oauth_access_tokens` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `oauth_auth_codes` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `oauth_clients` | 4 | WIPE / BOOTSTRAP | Remove user clients; recreate only specifically required machine clients using supported setup. |
| `oauth_personal_access_clients` | 2 | WIPE / BOOTSTRAP | Coordinate with retained/rebuilt machine clients. |
| `oauth_refresh_tokens` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `password_resets` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `permission_groups` | 8 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `report_templates` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `requested_assets` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `requests` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `saml_nonces` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `settings` | 1 | REBUILD BOOTSTRAP | One valid runtime settings record with explicit security/authentication exceptions. |
| `status_labels` | 5 | RESTORE BASELINE | Three migration labels; rebind creator under current schema. |
| `suppliers` | 24 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `telescope_entries` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `telescope_entries_tags` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `telescope_monitoring` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `throttle` | 0 | WIPE | Remove all rows, including soft-deleted rows where present. |
| `users` | 983 | KEEP ONE | Initiating active superuser only; delete every other row and clear survivor business links. |
| `users_groups` | 30 | WIPE | Remove all rows, including soft-deleted rows where present. |

## File and schema scope still to inventory

The read-only investigation checked filesystem configuration and file-reference code, not every physical object. Implementation must enumerate public/private/cloud uploads, orphan files, signatures/EULAs, imports/exports, thumbnails, tracking/committee/document evidence and shared references. Do not infer file totals from attachment rows.

Seven current asset extension columns were observed: `_snipeit_mac_address_1`, `_snipeit_ministry_allocation_code_2`, `_snipeit_grn_document_no_3`, `_snipeit_grn_4`, `_snipeit_funding_source_5`, `_snipeit_allocation_6`, `_snipeit_cpu_generation_7`. The baseline MAC definition and each nonbaseline column need explicit handling. Empty extension columns may remain as disclosed schema artifacts in the first data-reset release; exact schema removal requires a separate recoverable DDL stage.

Private backups, required key material, retained audit evidence and the new reset journal are outside business-file deletion. Newly introduced reset journal/review/job tables are retained/control tables, not unclassified business rows. New tables or relationships block execution until the manifest is updated.

