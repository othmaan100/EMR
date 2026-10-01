import './bootstrap';
import * as bootstrap from 'bootstrap';
import JsBarcode from 'jsbarcode';
import { initWebcam } from './webcam';
import { initPatientPickers } from './patient-picker';
import { initTrendCharts } from './trend-charts';
import { initIcdLookups } from './icd-lookup';
import { initIdleTimeout } from './idle-timeout';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    // Sidebar toggle (mobile).
    document.querySelectorAll('[data-toggle-sidebar]').forEach((btn) => {
        btn.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
    });

    // Live preview for image file inputs: <input data-preview="#imgId">
    document.querySelectorAll('input[type=file][data-preview]').forEach((input) => {
        input.addEventListener('change', () => {
            const target = document.querySelector(input.dataset.preview);
            const file = input.files?.[0];
            if (target && file) {
                target.src = URL.createObjectURL(file);
                target.classList.remove('d-none');
            }
        });
    });

    // Tooltips.
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

    // Code128 barcodes: <svg data-barcode="PT-000001">
    document.querySelectorAll('svg[data-barcode]').forEach((svg) => {
        JsBarcode(svg, svg.dataset.barcode, {
            format: 'CODE128',
            height: Number(svg.dataset.height || 40),
            width: 1.6,
            fontSize: 12,
            margin: 0,
            displayValue: svg.dataset.showValue !== 'false',
        });
    });

    // Show/hide blocks based on a select value: <div data-show-when="payment_type" data-show-values="insurance,corporate">
    document.querySelectorAll('[data-show-when]').forEach((block) => {
        const source = document.getElementById(block.dataset.showWhen);
        const values = block.dataset.showValues.split(',');
        const sync = () => {
            const on = source.type === 'checkbox' ? source.checked : values.includes(source.value);
            block.classList.toggle('d-none', !on);
        };
        source?.addEventListener('change', sync);
        source && sync();
    });

    // Whole-row click: <tr data-href="...">
    document.querySelectorAll('tr[data-href]').forEach((row) => {
        row.style.cursor = 'pointer';
        row.addEventListener('click', (e) => {
            if (!e.target.closest('a, button, form')) window.location = row.dataset.href;
        });
    });

    initWebcam();
    initPatientPickers();
    initTrendCharts();
    initIcdLookups();
    initIdleTimeout();

    // Live Low/High highlighting while entering numeric lab results.
    document.querySelectorAll('input[data-ref-low], input[data-ref-high]').forEach((input) => {
        const low = input.dataset.refLow === '' ? null : parseFloat(input.dataset.refLow);
        const high = input.dataset.refHigh === '' ? null : parseFloat(input.dataset.refHigh);
        const check = () => {
            const v = parseFloat(input.value);
            const out = !Number.isNaN(v) && ((low !== null && v < low) || (high !== null && v > high));
            input.classList.toggle('text-danger', out);
            input.classList.toggle('fw-bold', out);
        };
        input.addEventListener('input', check);
        check();
    });

    // Live BMI preview on vitals forms.
    const weight = document.getElementById('weight');
    const height = document.getElementById('height');
    const bmiOut = document.getElementById('bmi-preview');
    if (weight && height && bmiOut) {
        const update = () => {
            const w = parseFloat(weight.value);
            const h = parseFloat(height.value) / 100;
            bmiOut.textContent = w > 0 && h > 0 ? (w / (h * h)).toFixed(1) : '—';
        };
        weight.addEventListener('input', update);
        height.addEventListener('input', update);
        update();
    }

    // Self-refreshing regions: <div data-autorefresh="30" data-url="...?partial=1">
    document.querySelectorAll('[data-autorefresh]').forEach((region) => {
        const seconds = Number(region.dataset.autorefresh) || 30;
        setInterval(async () => {
            // Don't swap content while the user is typing in or interacting with a form/modal.
            if (document.querySelector('.modal.show') || region.contains(document.activeElement)) return;
            try {
                const res = await fetch(region.dataset.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                // A redirect means the session expired; leave the page as is.
                if (res.ok && !res.redirected) region.innerHTML = await res.text();
            } catch {
                /* offline: keep showing the last board */
            }
        }, seconds * 1000);
    });
});
