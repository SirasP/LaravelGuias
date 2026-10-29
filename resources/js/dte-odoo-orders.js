// La orden de Odoo que pagó la factura: vínculos, sugerencias y búsqueda.
export default (urls) => ({
    report: null, candidates: [], query: '', loading: false, busy: false, error: '', message: '',
    revoking: null, revokeNote: '', searched: false,

    async request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin', ...options,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', ...options.headers },
        });
        if (response.status === 401 || response.status === 419) throw new Error('La sesión venció. Recarga la página para continuar.');
        if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('El servidor no devolvió una respuesta válida.');
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'No se pudo completar la operación.');
        return data;
    },
    async load() {
        if (this.loading) return;
        this.loading = true; this.error = '';
        try {
            this.report = await this.request(urls.report);
            this.candidates = this.report.candidates;
            this.searched = false;
        } catch (error) { this.error = error.message; }
        finally { this.loading = false; }
    },
    async search() {
        this.error = '';
        try {
            this.candidates = (await this.request(`${urls.candidates}?q=${encodeURIComponent(this.query.trim())}`)).candidates;
            this.searched = true;
        } catch (error) { this.error = error.message; }
    },
    async mutate(url, method, body) {
        if (this.busy) return;
        this.busy = true; this.error = ''; this.message = '';
        try {
            this.message = (await this.request(url, { method, body: JSON.stringify(body) })).message;
            this.revoking = null; this.query = '';
            await this.load();
        } catch (error) { this.error = error.message; }
        finally { this.busy = false; }
    },
    link(candidate) { return this.mutate(urls.store, 'POST', { odoo_order_id: candidate.odoo_id, amount: candidate.quick_amount }); },
    revoke() { return this.mutate(`${urls.store}/${this.revoking}`, 'DELETE', { note: this.revokeNote }); },
    money(value, currency) {
        const amount = Number(value || 0);
        return currency && currency !== 'CLP'
            ? currency + ' ' + amount.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : (amount < 0 ? '−$' : '$') + Math.abs(Math.round(amount)).toLocaleString('es-CL');
    },
    percent(part, whole) { return whole > 0 ? Math.round(part * 100 / whole) + ' %' : ''; },
    reason(candidate) {
        if (candidate.referenced) return 'La factura nombra esta orden';
        const parts = [];
        if (candidate.coverage >= 0.999) parts.push('mismos productos');
        else if (candidate.coverage > 0) parts.push(Math.round(candidate.coverage * 100) + ' % del monto en productos de la orden');
        if (candidate.same_amount) parts.push('mismo monto pendiente');
        if (!candidate.same_supplier) parts.push('otro proveedor');
        return parts.join(' · ') || 'Mismo proveedor, fechas cercanas';
    },
});
