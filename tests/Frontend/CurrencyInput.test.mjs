import test from "node:test";
import assert from "node:assert/strict";
import {
    formatMoneyInput,
    parseMoneyInput,
    isMoneyField,
} from "../../resources/js/currency.ts";

test("money is readable at 1,000 through 50,000,000 VND without changing its amount", () => {
    for (const [input, shown, amount] of [
        ["1000", "1.000", 1000],
        ["100000", "100.000", 100000],
        ["50000000", "50.000.000", 50000000],
        ["001", "1", 1],
        ["0", "0", 0],
    ]) {
        assert.equal(formatMoneyInput(input), shown);
        assert.equal(parseMoneyInput(shown), amount);
    }
});
test("pasted Vietnamese and English thousands separators preserve the amount", () => {
    assert.equal(parseMoneyInput("1.250.000"), 1250000);
    assert.equal(parseMoneyInput("1,250,000"), 1250000);
    assert.equal(parseMoneyInput(" 250000 "), 250000);
    assert.equal(formatMoneyInput(""), "");
});
test("negative, fractional, scientific and unsafe money values are rejected", () => {
    for (const value of [
        "-1000",
        "1.5",
        "1,50",
        "100.00",
        "1e6",
        "Infinity",
        "abc",
        "",
        "1.000,000",
        "9007199254740993",
    ])
        assert.equal(parseMoneyInput(value), null, value);
});
test("prices, wallet deposits and payroll are formatted while quantities and percentages remain numbers", () => {
    for (const name of [
        "amount",
        "price",
        "unit_cost",
        "hourly_rate",
        "base_amount",
        "allowances",
        "deductions",
        "opening_cash",
    ])
        assert.equal(isMoneyField({ name, type: "number" }, {}), true, name);
    for (const name of [
        "quantity",
        "discount_percent",
        "stock",
        "capacity",
        "user_id",
        "duration_days",
    ])
        assert.equal(isMoneyField({ name, type: "number" }, {}), false, name);
    assert.equal(
        isMoneyField({ name: "value", type: "number" }, { kind: "percent" }),
        false,
    );
    assert.equal(
        isMoneyField({ name: "value", type: "number" }, { kind: "fixed" }),
        true,
    );
});
