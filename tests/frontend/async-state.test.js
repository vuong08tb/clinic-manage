import assert from "node:assert/strict";
import { afterEach, beforeEach, test } from "node:test";
import { medicineIndexPage } from "../../resources/js/features/medicines/index.js";
import { prescriptionIndexPage } from "../../resources/js/features/prescriptions/index.js";

function jsonResponse(payload) {
    return new Response(JSON.stringify({ success: true, ...payload }), {
        headers: { "Content-Type": "application/json" },
    });
}

function createStore() {
    return {
        auth: {
            can: () => true,
        },
        ui: {
            notify: () => {},
        },
    };
}

beforeEach(() => {
    globalThis.window = {
        dispatchEvent: () => {},
        localStorage: {
            getItem: () => "test-token",
            removeItem: () => {},
            setItem: () => {},
        },
        location: {
            replace: () => {},
        },
    };
});

afterEach(() => {
    delete globalThis.fetch;
    delete globalThis.window;
});

test("successful stock adjustment closes and resets its modal", async () => {
    const requests = [];

    globalThis.fetch = async (url, options = {}) => {
        requests.push({ url, options });

        if (requests.length === 1) {
            return jsonResponse({ data: { id: 9, stock: 14 } });
        }

        return jsonResponse({
            data: [],
            meta: { current_page: 1, last_page: 1, total: 0 },
        });
    };

    const page = medicineIndexPage();
    page.$store = createStore();
    page.stockOpen = true;
    page.stockTarget = { id: 9, stock: 10 };
    page.stockForm = { direction: "in", amount: "4", note: "Nhập hàng" };

    await page.submitStockAdjust();

    assert.equal(requests.length, 2);
    assert.equal(requests[0].url, "/api/medicines/9/stock");
    assert.deepEqual(JSON.parse(requests[0].options.body), {
        quantity: 4,
        note: "Nhập hàng",
    });
    assert.equal(page.stockOpen, false);
    assert.equal(page.stockTarget, null);
    assert.deepEqual(page.stockForm, {
        direction: "in",
        amount: "",
        note: "",
    });
    assert.equal(page.stockSubmitting, false);
});

test("successful prescription item update exits inline edit mode", async () => {
    const requests = [];

    globalThis.fetch = async (url, options = {}) => {
        requests.push({ url, options });

        if (requests.length === 1) {
            return jsonResponse({ data: { id: 7, quantity: 3 } });
        }

        if (requests.length === 2) {
            return jsonResponse({ data: { id: 4, items: [] } });
        }

        return jsonResponse({
            data: [],
            meta: { current_page: 1, last_page: 1, total: 0 },
        });
    };

    const page = prescriptionIndexPage();
    page.$store = createStore();
    page.detail = { id: 4, items: [] };
    page.editingItemId = 7;
    page.itemEditDraft = {
        quantity: 3,
        dosage: "2 lần/ngày",
        usage_instruction: "Sau ăn",
    };

    await page.saveEditItem({
        id: 7,
        quantity: 2,
        medicine: { stock: 10 },
    });

    assert.equal(requests.length, 3);
    assert.equal(requests[0].url, "/api/prescriptions/4/items/7");
    assert.deepEqual(JSON.parse(requests[0].options.body), {
        quantity: 3,
        dosage: "2 lần/ngày",
        usage_instruction: "Sau ăn",
    });
    assert.equal(page.editingItemId, null);
    assert.equal(page.itemEditDraft, null);
    assert.deepEqual(page.itemEditErrors, {});
    assert.equal(page.itemEditSubmitting, false);
});
