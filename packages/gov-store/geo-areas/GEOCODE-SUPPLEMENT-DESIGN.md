# Geography decision for asset tagging — revision 3

15 October 2026. The earlier external-geocode table/service supplement proposal
is withdrawn from the tagging plan. **No new geography tables, historical
crosswalk import, office-purpose geocode assignment or CGA numbering service
is required.**

Use existing `gov_location_profiles.geo_area_id` and `GeoAreaService` for current
office geography. Preserve `GeoAreaId`, hierarchy paths and existing code values.
The current service already provides ID lookup, district lookup, search and
boundary checks; consumers obtain the existing area's geo_code from that model.
If an external API projection later needs the field exposed, add it through the
existing authorized response contract, without changing source identifiers or
introducing a historical crosswalk resolver.

The extracted [625-row CSV](../../../GeoCodeData/geocodes.csv) is retained with
OCR/provenance evidence as historical reference. Its annex explicitly dates to
March 2013. Do not replace current application codes, infer equivalent IDs from
names, or require offices to reconcile this older source merely to issue tags.
The repository does not independently establish the current dataset's official
recency; this decision preserves the application's adopted data as requested.

The Finance format's LL/DD segments mean registration location and administrative
division, not the CGA guide's geographic district/upazila components. Offices
maintain their own actual LL/DD facts through the metadata package. CGA accounts
and digital document/file-number rules are out of tagging scope.

See the [current tagging architecture](../asset-tagging/ARCHITECTURE.md) and
[dataset compatibility notes](DATASET.md). A future official geography refresh
is a separate reviewed data-maintenance task.
