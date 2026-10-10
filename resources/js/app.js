import '@fontsource/ibm-plex-sans/400.css';
import '@fontsource/ibm-plex-sans/500.css';
import '@fontsource/ibm-plex-sans/600.css';
import '@fontsource/ibm-plex-sans/700.css';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* Summary numbers ([data-count-up]) count up from 0, keeping any ₱ / ha / MT around the number and its decimals. */
function countUp(el) {
    const text = el.textContent.trim();
    const match = text.match(/\d[\d,]*(\.\d+)?/);
    if (!match) return;
    const target = parseFloat(match[0].replace(/,/g, ''));
    if (!target) return;
    const decimals = match[1] ? match[1].length - 1 : 0;
    const grouped = match[0].includes(',');
    const [before, after] = [text.slice(0, match.index), text.slice(match.index + match[0].length)];
    const format = (n) => n.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals, useGrouping: grouped });
    const start = performance.now();
    const step = (now) => {
        const t = Math.min((now - start) / 800, 1);
        el.textContent = before + format(target * (1 - Math.pow(1 - t, 3))) + after;
        if (t < 1) requestAnimationFrame(step); else el.textContent = text;
    };
    requestAnimationFrame(step);
}

/* Busy buttons: filled (gradient) submit buttons and [data-busy] links show a spinner while the server works,
   and a second click on the same form is ignored. Downloads keep the page, so those reset after a few seconds. */
function setBusy(el, resetAfter) {
    if (el.classList.contains('is-busy')) return;
    const spinner = document.createElement('span');
    spinner.className = 'busy-spinner';
    spinner.setAttribute('aria-hidden', 'true');
    el.prepend(spinner);
    el.classList.add('is-busy');
    el.setAttribute('aria-busy', 'true');
    if (resetAfter) setTimeout(() => clearBusy(el), resetAfter);
}

function clearBusy(el) {
    el.querySelector(':scope > .busy-spinner')?.remove();
    el.classList.remove('is-busy');
    el.removeAttribute('aria-busy');
    el.closest('form')?.removeAttribute('data-submitting');
}

window.addEventListener('submit', (event) => {
    const form = event.target;
    if (event.defaultPrevented || !(form instanceof HTMLFormElement)) return;
    if (form.dataset.submitting) { event.preventDefault(); return; }
    const button = event.submitter;
    if (!button || !button.matches('.gradient-button, [data-busy]') || button.matches('[data-no-busy]')) return;
    form.dataset.submitting = '1';
    setBusy(button, form.dataset.busyReset ? Number(form.dataset.busyReset) : 0);
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-busy]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) return;
    setBusy(link, 4000);
});

/* Coming back with the browser's Back button can show the page as it was left; clear any spinners. */
window.addEventListener('pageshow', () => document.querySelectorAll('.is-busy').forEach(clearBusy));

/* Green success messages fade and fold away after a few seconds; errors stay until the page changes. */
function autohide(el) {
    setTimeout(() => {
        el.style.height = `${el.offsetHeight}px`;
        el.getBoundingClientRect();
        el.classList.add('flash-out');
        Object.assign(el.style, { height: '0px', marginTop: '0px', marginBottom: '0px', paddingTop: '0px', paddingBottom: '0px', borderWidth: '0px', overflow: 'hidden' });
        el.addEventListener('transitionend', (e) => { if (e.propertyName === 'height') el.remove(); });
    }, 6000);
}

document.addEventListener('DOMContentLoaded', () => {
    if (!calm) document.querySelectorAll('[data-count-up]').forEach(countUp);
    document.querySelectorAll('.flash[data-autohide]').forEach(autohide);
});
