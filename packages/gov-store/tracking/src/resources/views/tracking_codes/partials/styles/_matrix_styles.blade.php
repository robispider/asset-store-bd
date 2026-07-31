<!-- Adaptive Theme-Compliant Spreadsheet & Drag Stylesheet -->
<style>
    .gs-grid-container {
        position: relative;
        max-height: 500px;
        overflow: auto;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background-color: #ffffff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    #matrix-grid-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
        margin-bottom: 0;
        font-size: 13px;
        color: #334155;
    }

    /* --- LOCKED TOP HEADER ROW (Theme Adaptive) --- */
    #matrix-grid-table thead th {
        position: sticky;
        top: 0;
        background-color: #f8fafc; /* Light Mode default */
        color: #334155;
        z-index: 20;
        border-bottom: 2px solid #cbd5e1;
        border-right: 1px solid #e2e8f0;
        padding: 8px 12px;
        font-weight: 600;
        text-align: center;
        height: 40px;
    }

    /* --- LOCKED LEFT COLUMN (OFFICES) (Theme Adaptive) --- */
    #matrix-grid-table tbody td:first-child,
    #matrix-grid-table tfoot td:first-child {
        position: sticky;
        left: 0;
        background-color: #f8fafc; /* Light Mode default */
        z-index: 15;
        border-right: 2px solid #cbd5e1;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 600;
        padding: 10px 15px;
    }

    /* --- THE CORNER INTERSECT CELL (TOP-LEFT) --- */
    #matrix-grid-table thead th:first-child {
        position: sticky;
        left: 0;
        top: 0;
        z-index: 30;
        border-right: 2px solid #cbd5e1;
        border-bottom: 2px solid #cbd5e1;
        background-color: #f1f5f9;
        text-align: left;
    }

    /* --- GENERAL SPREADSHEET CELL STYLING --- */
    #matrix-grid-table td {
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        padding: 0;
        height: 36px;
    }

    /* --- FLAT SPREADSHEET CELL INPUTS --- */
    .gs-cell-input {
        width: 100%;
        height: 36px;
        border: none;
        outline: none;
        text-align: center;
        padding: 0 8px;
        background-color: transparent;
        color: inherit;
        transition: all 0.15s ease-in-out;
    }

    /* Active focused cell outline uses the active Snipe-IT link/accent color dynamically */
    .gs-cell-input:focus {
        background-color: #ffffff;
        box-shadow: inset 0 0 0 2px #3b82f6; /* Fallback */
        box-shadow: inset 0 0 0 2px var(--primary, #3b82f6); /* Dynamic active theme accent */
        z-index: 5;
        position: relative;
    }

    #matrix-grid-table tbody tr:hover td {
        background-color: #f1f5f9;
    }

    /* --- FOOTER TOTALS ROW --- */
    #matrix-grid-table tfoot tr td {
        position: sticky;
        bottom: 0;
        background-color: #f8fafc;
        z-index: 10;
        border-top: 2px solid #cbd5e1;
        font-weight: 700;
        padding: 10px 12px;
        text-align: center;
    }

    #matrix-grid-table tfoot td:first-child {
        z-index: 25;
    }

    /* --- INLINE SPAWNER ELEMENTS --- */
    .gs-inline-spawner {
        background-color: #f8fafc !important;
        cursor: pointer;
        color: #3b82f6;
        color: var(--primary, #3b82f6);
        font-weight: 600 !important;
        text-align: center;
        transition: background-color 0.15s;
    }

    .gs-inline-spawner:hover {
        background-color: #eff6ff !important;
    }

    .gs-inline-select .select2-container--default .select2-selection--single {
        border: none !important;
        background-color: transparent !important;
        height: 34px !important;
    }

    /* --- ABSOLUTE OVERLAY CONTEXT MENUS --- */
    .gs-context-menu {
        position: absolute;
        background-color: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        z-index: 50;
        display: none;
        width: 180px;
    }

    .gs-context-menu ul {
        list-style: none;
        padding: 5px 0;
        margin: 0;
    }

    .gs-context-menu ul li {
        padding: 8px 12px;
        cursor: pointer;
        font-size: 13px;
        color: #334155;
        transition: background-color 0.15s;
    }

    .gs-context-menu ul li:hover {
        background-color: #f1f5f9;
        color: #1e293b;
    }

    .gs-context-menu ul li i {
        margin-right: 8px;
        color: #64748b;
    }

    /* ========================================================================= */
    /* 🌑 DARK MODE OVERRIDES (Seamless Snipe-IT Integration) */
    /* ========================================================================= */
    body.dark-mode .gs-grid-container {
        border-color: #374151;
        background-color: #1f2937;
    }

    body.dark-mode #matrix-grid-table {
        color: #f3f4f6;
    }

    body.dark-mode #matrix-grid-table thead th,
    body.dark-mode #matrix-grid-table tbody td:first-child,
    body.dark-mode #matrix-grid-table tfoot td:first-child,
    body.dark-mode #matrix-grid-table tfoot tr td {
        background-color: #1f2937;
        color: #f3f4f6;
        border-color: #374151;
    }

    body.dark-mode #matrix-grid-table thead th:first-child {
        background-color: #111827;
        border-color: #374151;
    }

    body.dark-mode #matrix-grid-table td {
        border-color: #374151;
    }

    body.dark-mode .gs-cell-input:focus {
        background-color: #111827;
    }

    body.dark-mode #matrix-grid-table tbody tr:hover td {
        background-color: #374151;
    }

    body.dark-mode .gs-inline-spawner {
        background-color: #111827 !important;
    }

    body.dark-mode .gs-inline-spawner:hover {
        background-color: #1f2937 !important;
    }

    body.dark-mode .gs-context-menu {
        background-color: #1f2937;
        border-color: #374151;
    }

    body.dark-mode .gs-context-menu ul li {
        color: #f3f4f6;
    }

    body.dark-mode .gs-context-menu ul li:hover {
        background-color: #374151;
    }

    /* ========================================================================= */
    /* 🎛️ DRAG & DROP VISUAL STATES */
    /* ========================================================================= */
    .gs-dragging {
        opacity: 0.4;
        border: 2px dashed var(--primary, #3b82f6) !important;
    }

    /* Blue vertical insertion line when dragging columns left/right */
    .gs-drag-over-left {
        border-left: 3px solid var(--primary, #3b82f6) !important;
    }

    /* Blue horizontal insertion line when dragging rows up/down */
    .gs-drag-over-top {
        border-top: 3px solid var(--primary, #3b82f6) !important;
    }
</style>