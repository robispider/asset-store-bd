# Requester authority at submission

27 October 2026, Asia/Dhaka. Implements the owner's explicit request to change
the independent-approval policy described in the 4 October refactor record.
This record supersedes that record's requester-role automatic-approval prohibition
for new custom product request submissions only.

## Implemented policy

Resolve the product's item override, category inheritance, default and quantity/value
thresholds first. Then apply the requester's current authority in the working office:

| Effective product rule | Ordinary requester | Primary approver requester | Final approver requester |
| --- | --- | --- | --- |
| AUTO_APPROVE | Automatic approval | Automatic approval | Automatic approval |
| PRIMARY_ONLY | Independent approval | Automatic approval | Automatic approval |
| PRIMARY_AND_FINAL | Independent primary and final decisions | Record primary authority; require independent final decision | Automatic approval |

Qualifying authority requires an activated, nondeleted account in the office's
company, active unexpired membership, and a responsibility or unexpired cover grant
in that office. Admin/superuser flags alone do not qualify. A requester holding both
approval roles uses final authority. Shadow mode cannot manufacture authority.

The shared submission planner drives the draft preview, review snapshot, send-time
decision and approval-path availability. A changed role or expired grant invalidates
the reviewed snapshot before submission. A reason remains required whenever another
person must decide. Missing final cover stays pending and notifies the office head.

The original product rule and threshold explanation remain in `resolved_policy` and
`resolved_reason.lines`. Versioned `requester_authority_v1` evidence is stored in
`resolved_reason.submission` and the submission event. Automatic processing leaves
human decision attribution empty. Primary authority at submission is distinct from
a human primary decision; only requests with the server-written evidence can proceed
to final review without `primary_decided_by`.

Manual self-approval and two human decisions by the same person remain prohibited.
Final reviewers cannot exceed requested quantities. Automatic approval uses the same
transactional stock reservation as product-based automatic approval; stock shortage
rolls back requests, lines, reservations, events and notices and keeps the draft.
English/Bengali previews, request explanations and history describe the authority
exception. Existing pending/approved requests are not reprocessed. No migration or
national product-rule edit is required.

## Verification

Installed PHP 8.4.15, Xdebug disabled, isolated SQLite memory:

```text
php -d memory_limit=1G vendor/bin/phpunit tests/Feature/GovStore/RequesterAuthorityApprovalTest.php tests/Feature/GovStore/CustomRequestsWorkflowTest.php tests/Feature/GovStore/CustomRequestsUxTest.php tests/Feature/GovStore/G1AuthorizationTest.php
```

Result: **141 tests, 7,629 assertions passed**.

Coverage includes the requester-role/product-rule matrix, dual-role requesters,
independent final decisions, self-decision refusal in shadow/enforce, quantities,
forged submission metadata and missing primary evidence, foreign-office/company and
inactive authority, native-admin/superuser non-bypass, active/expired cover and revoked duties,
expiry since draft review, quantity/value/unknown-cost thresholds, mixed baskets,
stock-shortage rollback, missing final cover notices and bilingual rendering.
The earlier self-decision and ordinary independent two-stage tests remain enabled.
The former primary-requester fallback test now explicitly uses a two-stage product
rule and exercises the required final approval instead of expecting final review
for a one-stage rule.

Additional checks: PHP syntax passed for 12 changed source/test files and 23 compiled
custom-request views. English/Bengali translation key parity passed for 699 keys.
`git diff --check` passed.

## Live checks and remaining limits

Read-only browser inspection reached the local request catalog in an existing
ordinary session, whose office had no requestable stock. A separate in-app browser
session was an administrator in All offices view and correctly refused office-bound
catalog access. These are page checks, not completion of the new approval story.
No live request, goods issue, stock change or temporary role/grant was created.
The original working contexts were not changed.

The new role-based submission, independent-final handoff and storekeeper issue must
still be exercised together through the live interface using stocked fixture offices
and the actual primary/final/storekeeper roles. No full live acceptance, MySQL
concurrency run, remote CI result, production rollout or observation-period completion
is claimed by this focused implementation. External email delivery was not exercised.

## Implementation locations

- `packages/gov-store/custom-requests/src/Services/ApprovalRouting.php`: authority planner.
- `src/Services/BasketService.php`, `src/Support/DraftView.php`: preview/review/submission consistency and audit evidence.
- `src/Models/Request.php`, `src/Services/ApprovalService.php`: recorded primary authority and independent final decision.
- `src/Services/RequestInventory.php`: automatic reservation failure explanation.
- `src/Support/RequestPresenter.php`, `src/resources/lang/`: bilingual routing and history.
- `tests/Feature/GovStore/RequesterAuthorityApprovalTest.php`, `CustomRequestsWorkflowTest.php`, `CustomRequestsUxTest.php`: focused regression coverage.
