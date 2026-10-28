export default (urls) => ({
    report: null, candidates: [], query: '', selected: null, loading: false, busy: false,
    error: '', message: '', searched: false, searchVersion: 0, classificationNote: '', revoking: null, revokeNote: '',
    form: { lineId: '', invoiceQuantity: '', requestQuantity: '', acknowledge: false, note: '', operationKey: '' },
    labels: { unidentified: 'Sin vínculo identificado', partial: 'Vinculación parcial', linked: 'Solicitud vinculada', without_request: 'Compra sin solicitud' },

    async request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin', ...options,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', ...options.headers },
        });
        if (response.status === 401 || response.status === 419) throw new Error('La sesión venció. Recarga la página para continuar.');
        if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('El servidor no devolvió una respuesta válida. Recarga o vuelve a intentar.');
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'No se pudo completar la operación.');
        return data;
    },
    async load() {
        if (this.loading) return;
        this.loading = true;
        this.error = '';
        try {
            this.report = await this.request(urls.report);
            // The report already brings the suggestions; a typed search asks again.
            this.searchVersion++;
            this.candidates = this.report.candidates || [];
            this.searched = true;
            if (this.query.trim() && this.hasRemaining) await this.search();
        } catch (error) { this.error = error.message; }
        finally { this.loading = false; }
    },
    async search() {
        const version = ++this.searchVersion;
        this.error = '';
        try {
            const data = await this.request(`${urls.candidates}?q=${encodeURIComponent(this.query.trim())}`);
            if (version !== this.searchVersion) return;
            this.candidates = data.candidates;
            this.searched = true;
        } catch (error) { if (version === this.searchVersion) this.error = error.message; }
    },
    choose(candidate) {
        this.selected = candidate;
        this.message = '';
        this.form = { lineId: candidate.line_id ? String(candidate.line_id) : '', invoiceQuantity: '', requestQuantity: '',
            acknowledge: false, note: '', operationKey: this.newOperationKey() };
        this.changeLine();
    },
    newOperationKey() {
        // The VPS is also accessed over HTTP by IP, where randomUUID is unavailable.
        if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 15) | 64;
        bytes[8] = (bytes[8] & 63) | 128;
        const hex = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    },
    historyLabels: { linked: 'vinculó', revoked: 'desvinculó', without_request: 'marcó compra directa', unidentified: 'volvió a revisar' },
    get statusClass() {
        return {
            linked: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
            partial: 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
            without_request: 'bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300',
        }[this.report?.classification] || 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300';
    },
    get linkedLines() { return this.report?.lines.filter(line => this.scaled(line.remaining) === 0n).length || 0; },
    get pendingLines() { return this.report?.lines.filter(line => this.scaled(line.remaining) > 0n) || []; },
    get groups() {
        const groups = [];
        for (const reference of this.report?.references || []) {
            let group = groups.find(g => g.folio === reference.folio);
            if (!group) groups.push(group = { folio: reference.folio, url: reference.request_url, rows: [] });
            group.rows.push(reference);
        }
        return groups;
    },
    sameName(a, b) {
        const plain = value => (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
        return plain(a) === plain(b);
    },
    lineDescription(id) { return this.report?.lines.find(line => line.id === id)?.description || ''; },
    sourceLabel(reference) {
        return { auto: 'Automática', odoo: 'Verificada en Odoo' }[reference.source] || 'Manual · ' + reference.actor;
    },
    sourceClass(source) {
        return { auto: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300', odoo: 'bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300' }[source]
            || 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300';
    },
    shortUnit(value) { return this.unit(value) === 'un' ? 'un.' : (value || '').toLowerCase(); },
    get currentLine() { return this.report?.lines.find(line => String(line.id) === String(this.form.lineId)) || null; },
    get hasRemaining() { return this.report?.lines.some(line => this.scaled(line.remaining) > 0n) || false; },
    unit(value) {
        const u = (value || '').trim().toLowerCase();
        if (['u', 'un', 'un.', 'und', 'unds', 'unid', 'unid.', 'unidad', 'unidades', 'unit', 'units', 'units.', 'ud', 'uds', 'pza', 'pzas'].includes(u)) return 'un';
        if (['kg', 'kgs', 'kilo', 'kilos', 'kilogramo', 'kilogramos'].includes(u)) return 'kg';
        if (['l', 'lt', 'lts', 'litro', 'litros'].includes(u)) return 'l';
        if (['m', 'mt', 'mts', 'metro', 'metros'].includes(u)) return 'm';
        return u;
    },
    get needsReview() {
        return this.selected && (this.selected.packaged
            || /\b(cajas?|packs?|paquetes?|docenas?|bidones?|rollos?)\b/i.test(this.currentLine?.description || '')
            || !this.unit(this.currentLine?.unit) || this.unit(this.currentLine?.unit) !== this.unit(this.selected.unit)
            || this.scaled(this.form.invoiceQuantity) === null || this.scaled(this.form.invoiceQuantity) !== this.scaled(this.form.requestQuantity));
    },
    scaled(value) {
        const match = String(value ?? '').match(/^(-?\d+)(?:\.(\d{0,6}))?$/);
        if (!match) return null;
        const negative = match[1].startsWith('-');
        const magnitude = BigInt(match[1].replace('-', '')) * 1000000n + BigInt((match[2] || '').padEnd(6, '0'));
        return negative ? -magnitude : magnitude;
    },
    changeLine() {
        const line = this.currentLine;
        const canCopy = line && !this.selected.packaged && !/\b(cajas?|packs?|paquetes?|docenas?|bidones?|rollos?)\b/i.test(line.description || '')
            && this.unit(line.unit) && this.unit(line.unit) === this.unit(this.selected.unit);
        this.form.invoiceQuantity = line ? (canCopy && this.scaled(this.selected.remaining) < this.scaled(line.remaining) ? this.selected.remaining : line.remaining) : '';
        this.form.requestQuantity = canCopy ? this.form.invoiceQuantity : '';
    },
    number(value) {
        if (value === null || value === undefined) return '—';
        const [integer, decimal = ''] = String(value).split('.');
        const fraction = decimal.replace(/0+$/, '');
        return (integer.startsWith('-') ? '-' : '') + BigInt(integer.replace('-', '') || '0').toLocaleString('es-CL') + (fraction ? ',' + fraction : '');
    },
    date(value) { return new Date(value).toLocaleString('es-CL', { timeZone: 'America/Santiago' }); },
    async mutate(url, method, body) {
        if (this.busy || this.loading) return;
        this.busy = true; this.error = ''; this.message = '';
        try {
            const data = await this.request(url, { method, body: JSON.stringify(body) });
            this.message = data.message;
            this.selected = null; this.revoking = null; this.classificationNote = '';
            await this.load();
        } catch (error) { this.error = error.message; }
        finally { this.busy = false; }
    },
    async save() {
        if (!this.selected || !this.currentLine) { this.error = 'Elige una partida de la factura.'; return; }
        await this.mutate(urls.store, 'POST', {
            document_line_id: this.currentLine.id, purchase_request_item_id: this.selected.item_id,
            invoice_quantity: String(this.form.invoiceQuantity), request_quantity: String(this.form.requestQuantity),
            version: this.report.version, line_version: this.currentLine.version, item_version: this.selected.item_version,
            operation_key: this.form.operationKey, acknowledge: this.form.acknowledge, note: this.form.note,
        });
    },
    async quickLink(candidate) {
        await this.mutate(urls.store, 'POST', {
            document_line_id: candidate.line_id, purchase_request_item_id: candidate.item_id,
            invoice_quantity: candidate.quick_quantity, request_quantity: candidate.quick_quantity,
            version: this.report.version, line_version: candidate.line_version, item_version: candidate.item_version,
            operation_key: this.newOperationKey(),
        });
    },
    async classify(classification) {
        await this.mutate(urls.classify, 'POST', { classification, version: this.report.version, note: this.classificationNote });
    },
    async revoke() { await this.mutate(`${urls.store}/${this.revoking}`, 'DELETE', { note: this.revokeNote }); },
});
