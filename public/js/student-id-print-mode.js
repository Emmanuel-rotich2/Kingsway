/**
 * Student ID print-mode helper.
 *
 * Browser JavaScript cannot reliably inspect the operating system's printer
 * list. The user selects the destination mode and the choice is remembered
 * for the workstation.
 */
window.StudentIdPrintMode = (() => {
    "use strict";

    const STORAGE_KEY = "kingsway_student_id_printer_mode";
    const VALID_MODES = new Set(["a4_pdf", "direct_card"]);
    const VALID_SIDES = new Set(["front", "back", "both"]);

    function getSelectedMode(selectElement = null) {
        const selected = selectElement?.value;

        if (VALID_MODES.has(selected)) {
            localStorage.setItem(STORAGE_KEY, selected);
            return selected;
        }

        const stored = localStorage.getItem(STORAGE_KEY);

        return VALID_MODES.has(stored) ? stored : "a4_pdf";
    }

    function applyStoredMode(selectElement) {
        if (!selectElement) return;

        const mode = getSelectedMode();

        if ([...selectElement.options].some(option => option.value === mode)) {
            selectElement.value = mode;
        }

        selectElement.addEventListener("change", () => {
            getSelectedMode(selectElement);
        });
    }

    function buildRequest({
        studentIds,
        printerMode,
        side = "both",
        chunkSize = 100,
    }) {
        if (!Array.isArray(studentIds) || studentIds.length === 0) {
            throw new Error("Select at least one student.");
        }

        if (!VALID_MODES.has(printerMode)) {
            throw new Error("Invalid printer mode.");
        }

        if (!VALID_SIDES.has(side)) {
            throw new Error("Invalid card side.");
        }

        return {
            student_ids: studentIds,
            printer_mode: printerMode,
            side,
            chunk_size: Math.max(1, Math.min(200, Number(chunkSize) || 100)),
            batch_mode: studentIds.length > 1 ? "bulk" : "single",
        };
    }

    return {
        getSelectedMode,
        applyStoredMode,
        buildRequest,
    };
})();
