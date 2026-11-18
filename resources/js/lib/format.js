const nf = new Intl.NumberFormat('es-HN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const money = (v) => 'L ' + nf.format(Number(v) || 0);

export const qty = (v) => {
    const n = Number(v) || 0;
    return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
};

const parse = (iso) => (iso && iso.length === 10 ? new Date(iso + 'T12:00:00-06:00') : new Date(iso));

export const date = (iso) =>
    iso ? parse(iso).toLocaleDateString('es-HN', { timeZone: 'America/Tegucigalpa', day: 'numeric', month: 'short', year: 'numeric' }) : '';

export const longDate = (iso) =>
    iso ? parse(iso).toLocaleDateString('es-HN', { timeZone: 'America/Tegucigalpa', weekday: 'long', day: 'numeric', month: 'long' }) : '';

export const todayIso = () => {
    const parts = new Intl.DateTimeFormat('en-US', { timeZone: 'America/Tegucigalpa', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
    const get = (type) => parts.find((p) => p.type === type).value;
    return `${get('year')}-${get('month')}-${get('day')}`;
};

export const termsLabel = (t) => (t === 'credit' ? 'Crédito' : 'Contado');

export const statusLabel = (s) => ({ issued: 'Activa', voided: 'Anulada', converted: 'Facturada' })[s] ?? s;
export const statusColor = (s) => ({ issued: 'primary', voided: 'error', converted: 'secondary' })[s] ?? 'default';

export const purposeLabels = {
    business: 'Del negocio',
    family: 'Familia',
    customer: 'Para un cliente',
    moving: 'Mudanza',
    other: 'Otro',
};

export const roleLabels = { admin: 'Administrador', seller: 'Vendedor', accountant: 'Contador (solo ver)' };
