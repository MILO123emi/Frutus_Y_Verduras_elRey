import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parseDecimalInput, toCents, toQty, lineTotalCents, taxCents, addCents, fromCents, divideCents } from './money.js';

test('decimal text rounds half-up, including negatives and empty input', () => {
    for (const [value, expected] of [['13.7', 1370], [13.7, 1370], ['', 0], [null, 0], ['-5.5', -550], ['1.005', 101], ['-1.005', -101], ['-0.004', 0], ['13,70', 1370], [1e-7, 0]]) {
        assert.equal(toCents(value), expected);
        assert.equal(toQty(value), expected);
    }
});
test('known invoice cent boundaries and large intermediate products', () => {
    for (const [q, p, c] of [['0.15', '13.70', 206], ['0.09', '26.50', 239], ['0.15', '14.50', 218], ['0.15', '17.90', 269], ['999', '99999.99', 9989999001], ['999999.99', '999999.99', 99999998000000]]) {
        assert.equal(lineTotalCents(q, p), c);
    }
    assert.throws(() => lineTotalCents('99999999.99', '9999999999.99'), RangeError);
    assert.throws(() => addCents(Number.MAX_SAFE_INTEGER, 1), RangeError);
    assert.equal(taxCents(1370, '0.15'), 206);
    assert.equal(taxCents(1450, '0.15'), 218);
});
test('sums, balances, average and thresholds use cents', () => {
    assert.equal(fromCents(addCents(10, 20)), '0.30');
    assert.equal(fromCents(addCents(toCents('2.06'), -toCents('2.07'))), '-0.01');
    assert.equal(fromCents(-550), '-5.50');
    assert.equal(divideCents(101, 2), 51);
    assert.equal(divideCents(-101, 2), -51);
    assert.equal(addCents(toCents('9997.94'), lineTotalCents('0.15', '13.70')), 1000000);
});
const cases = JSON.parse(readFileSync(new URL('../../../tests/fixtures/money-cases.json', import.meta.url)));
test('all 206 shared cases match PHP Money line totals and both tax rates', () => {
    assert.equal(cases.length, 206);
    for (const c of cases) {
        const total = lineTotalCents(c.quantity, c.price);
        assert.equal(fromCents(total), c.total, JSON.stringify(c));
        assert.equal(fromCents(taxCents(total, '0.15')), c.tax15);
        assert.equal(fromCents(taxCents(total, '0.18')), c.tax18);
    }
});

test('sale decimal input accepts comma and rejects invalid written values', () => {
    for (const [input, value] of [['2,5', '2.50'], ['2.5', '2.50'], [' 3 ', '3.00']]) {
        assert.deepEqual(parseDecimalInput(input), { empty: false, valid: true, value });
        assert.equal(fromCents(toQty(input)), value);
        assert.equal(fromCents(toCents(input)), value);
    }
    for (const input of ['abc', '-1', '1,2,3', '2.555', '1.000', '1,000', '1.2,3']) {
        assert.deepEqual(parseDecimalInput(input), { empty: false, valid: false, value: null });
    }
    for (const input of ['', null, '  ']) assert.deepEqual(parseDecimalInput(input), { empty: true, valid: false, value: null });
});
