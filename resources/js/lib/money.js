// All public amounts are safe integer cents; BigInt keeps intermediate products exact.
function safe(value) {
    const result = Number(value);
    if (!Number.isSafeInteger(result)) throw new RangeError('Monto fuera del rango exacto.');
    return result;
}

function roundedDivide(value, divisor) {
    if (divisor <= 0n) throw new RangeError('El divisor debe ser positivo.');
    const sign = value < 0n ? -1n : 1n;
    const magnitude = value * sign;
    return sign * ((magnitude + divisor / 2n) / divisor);
}

// Form inputs are unsigned decimals with at most two fractional digits.
export function parseDecimalInput(value) {
    const text = String(value ?? '').trim();
    if (!text) return { empty: true, valid: false, value: null };
    if (!/^\d+(?:[.,]\d{1,2})?$/.test(text)) return { empty: false, valid: false, value: null };
    const [whole, fraction = ''] = text.replace(',', '.').split('.');
    return { empty: false, valid: true, value: `${whole.replace(/^0+(?=\d)/, '')}.${fraction.padEnd(2, '0')}` };
}

export function toCents(value) {
    const text = String(value ?? '').trim().replace(',', '.');
    const match = /^([+-]?)(\d*)(?:\.(\d*))?(?:e([+-]?\d+))?$/i.exec(text);
    if (!match || !(match[2] || match[3])) return 0;
    const exponent = Number(match[4] || 0) - (match[3] || '').length + 2;
    if (Math.abs(exponent) > 1000) throw new RangeError('Monto fuera del rango exacto.');
    let integer = BigInt((match[2] || '0') + (match[3] || ''));
    if (match[1] === '-') integer = -integer;
    return safe(exponent >= 0 ? integer * 10n ** BigInt(exponent) : roundedDivide(integer, 10n ** BigInt(-exponent)));
}

export const toQty = toCents;
export const lineTotalCents = (quantity, price) => safe(roundedDivide(BigInt(toQty(quantity)) * BigInt(toCents(price)), 100n));
export const taxCents = (baseCents, rate) => safe(roundedDivide(BigInt(baseCents) * BigInt(toCents(rate)), 100n));
export const addCents = (...values) => safe(values.reduce((sum, value) => sum + BigInt(value), 0n));
export const divideCents = (cents, count) => safe(roundedDivide(BigInt(cents), BigInt(count)));
export function fromCents(cents) {
    const integer = BigInt(cents);
    const magnitude = integer < 0n ? -integer : integer;
    return `${integer < 0n ? '-' : ''}${magnitude / 100n}.${String(magnitude % 100n).padStart(2, '0')}`;
}
