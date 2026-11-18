// Búsqueda tolerante a errores para clientes y productos (catálogos chicos, en el navegador).
// "tomte" encuentra "Tomate", "pulperia esperansa" encuentra "Pulpería La Esperanza".

export function normalize(text) {
    return String(text ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9ñ ]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

// Distancia de edición (con transposición) acotada.
function distance(a, b) {
    const m = a.length, n = b.length;
    const d = Array.from({ length: m + 1 }, (_, i) => [i, ...Array(n).fill(0)]);
    for (let j = 1; j <= n; j++) d[0][j] = j;
    for (let i = 1; i <= m; i++) {
        for (let j = 1; j <= n; j++) {
            const cost = a[i - 1] === b[j - 1] ? 0 : 1;
            d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + cost);
            if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) {
                d[i][j] = Math.min(d[i][j], d[i - 2][j - 2] + 1);
            }
        }
    }
    return d[m][n];
}

function tokenMatches(token, words) {
    const allowed = token.length >= 6 ? 2 : token.length >= 3 ? 1 : 0;
    return words.some((w) => {
        if (w.startsWith(token) || (token.length >= 3 && w.includes(token))) return true;
        if (!allowed) return false;
        // Compara contra el comienzo de la palabra (para búsquedas a medio escribir) y la palabra completa.
        return distance(token, w.slice(0, token.length)) <= allowed || distance(token, w) <= allowed;
    });
}

export function fuzzyMatch(text, query) {
    const q = normalize(query);
    if (!q) return true;
    const words = normalize(text).split(' ');
    return q.split(' ').every((t) => tokenMatches(t, words));
}

// Para :custom-filter de v-autocomplete.
export const fuzzyFilter = (value, query) => fuzzyMatch(value, query);
