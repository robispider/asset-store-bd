# Tracking matrix capabilities

Reviewed 8 October 2026 (Asia/Dhaka).

The browser worksheet supports office/category selection, quantity editing,
clipboard paste, keyboard navigation and saving the resulting delivery matrix.
These controls do not constitute an Excel file import/export workflow.

The following are proposals, not delivered features:

- Download an Excel template and upload a completed workbook.
- Generate rows/columns automatically from geography and category groups.
- Save reusable matrix templates or distribute quantities automatically.
- Round-trip an Excel workbook with change detection.

No spreadsheet parsing library, workbook upload route or export contract is
implemented by this package. Any future implementation must define file limits,
scoped object validation, change review and rollback before accepting workbooks.
