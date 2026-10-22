/** Agranda el gráfico original: conserva sus datos, ejes y opciones de Chart.js. */
export function initFuelChartModal(doc = document, getChart = canvas => window.Chart?.getChart(canvas)) {
    const dialog = doc.getElementById('fuel-chart-dialog');
    if (!dialog) return;

    // El diálogo sale del contenedor desplazable del dashboard y usa la capa modal nativa.
    doc.body.append(dialog);
    const host = dialog.querySelector('[data-fuel-chart-host]');
    const title = dialog.querySelector('#fuel-chart-dialog-title');
    const closeButton = dialog.querySelector('[data-fuel-chart-close]');
    const win = doc.defaultView;
    let expanded = null;

    const restore = () => {
        if (!expanded) return;
        const { canvas, parent, next, chart, trigger, scrollContainers } = expanded;
        expanded = null;
        parent.insertBefore(canvas, next);
        scrollContainers.forEach(({ element, overflow }) => { element.style.overflow = overflow; });
        trigger.focus({ preventScroll: true });
        win.requestAnimationFrame(() => chart.resize());
    };

    doc.querySelectorAll('[data-fuel-chart-expand]').forEach(trigger => {
        trigger.addEventListener('click', () => {
            if (expanded) return;
            const canvas = doc.getElementById(trigger.dataset.fuelChartExpand);
            const chart = canvas && getChart(canvas);
            if (!chart) return;

            const scrollContainers = [doc.documentElement, doc.body, ...doc.querySelectorAll('main')]
                .map(element => ({ element, overflow: element.style.overflow }));
            expanded = { canvas, parent: canvas.parentNode, next: canvas.nextSibling, chart, trigger, scrollContainers };
            title.textContent = trigger.dataset.chartTitle;
            host.append(canvas);
            scrollContainers.forEach(({ element }) => { element.style.overflow = 'hidden'; });
            try {
                // showModal bloquea los clics y el foco en toda la página subyacente.
                dialog.showModal();
                closeButton.focus({ preventScroll: true });
                win.requestAnimationFrame(() => { if (expanded?.canvas === canvas) chart.resize(); });
            } catch (error) {
                restore();
                throw error;
            }
        });
    });

    closeButton.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', restore);
}
