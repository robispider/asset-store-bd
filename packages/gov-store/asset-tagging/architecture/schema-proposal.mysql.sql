-- Revision 7: DESIGN ONLY. Three new GovStore tables; two existing GovStore tables altered. No Snipe-IT schema/model changes.
-- NOT A MIGRATION. Never execute this file as a script against an existing database.
-- Inspect actual types/collations/duplicates; translate into targeted Laravel migrations.
-- Existing-table changes are deliberately comments pending schema preflight.
-- gov_finance_items.unspsc_code is a soft link: match charset/collation to gov_catalog_nodes.code, no FK.

CREATE TABLE gov_finance_items (
  gov_code CHAR(6) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  group_name VARCHAR(191) NOT NULL,
  subgroup_name VARCHAR(191) NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  unspsc_code VARCHAR(50) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'held',
  origin VARCHAR(30) NOT NULL DEFAULT 'manual_extract',
  source_reference TEXT NULL,
  authority_reference TEXT NULL,
  updated_by INT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  PRIMARY KEY (gov_code),
  KEY finance_unspsc_status (unspsc_code, status),
  KEY finance_status_code (status, gov_code)
) ENGINE=InnoDB;

CREATE TABLE gov_metadata_values (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_type VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  namespace VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  scope_id INT UNSIGNED NOT NULL DEFAULT 0,
  payload JSON NOT NULL,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  updated_by INT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY metadata_subject (subject_type, subject_id, namespace),
  KEY metadata_scope (scope_type, scope_id)
) ENGINE=InnoDB;

CREATE TABLE gov_asset_tag_sequences (
  namespace VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  scope_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  last_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  PRIMARY KEY (namespace, scope_key)
) ENGINE=InnoDB;

-- Proposed ALTER TABLE gov_asset_registrations:
-- ADD COLUMN unit_index INT UNSIGNED NULL;
-- ADD COLUMN format_version VARCHAR(50) NULL;
-- ADD COLUMN gov_code CHAR(6) CHARACTER SET ascii COLLATE ascii_bin NULL;
-- ADD COLUMN tag_snapshot JSON NULL;
-- ADD UNIQUE KEY registration_asset_tag (asset_tag);
-- ADD UNIQUE KEY registration_source_unit (intake_item_id, unit_index);
-- ADD UNIQUE KEY registration_asset (asset_id);
-- ADD KEY registration_finance_code (gov_code);
-- ADD FOREIGN KEY (gov_code) REFERENCES gov_finance_items(gov_code) ON DELETE RESTRICT;
-- Keep intake_item_id NOT NULL; replace its existing gov_document_items ON DELETE CASCADE with RESTRICT using inspected actual constraint name

-- Proposed ALTER TABLE gov_catalog_snipe_mappings:
-- DROP existing non-unique category_id index (inspect actual name);
-- ADD UNIQUE KEY catalog_mapping_category (category_id);

