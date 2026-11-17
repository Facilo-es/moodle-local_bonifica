// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Bonifica screens: saves each grid cell as it's typed, the paste dialog, inline fixes and the Pro toggle.
 * Every form still works without this script; it only saves the round trip.
 *
 * @module     local_bonifica/grid
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const CELL = /^cells\[(\d+)\]\[([a-z_]+)\]$/;

/**
 * Shows the new state after a save: red cells, row ticks, the summary and the export button.
 *
 * @param {HTMLElement} grid
 * @param {Object} state result of local_bonifica_save_cell
 */
const paint = (grid, state) => {
    const bad = {};
    state.cells.forEach(c => {
        bad[c.partid] = bad[c.partid] || {};
        bad[c.partid][c.field] = c.message;
    });
    grid.querySelectorAll('tr[data-partid]').forEach((row, i) => {
        const mine = bad[row.dataset.partid] || {};
        row.querySelectorAll('td[data-field]').forEach(td => {
            const why = mine[td.dataset.field];
            td.classList.toggle('bad', why !== undefined);
            const box = td.querySelector('.why');
            if (box) {
                box.textContent = why || '';
            }
        });
        const ok = Object.keys(mine).length === 0;
        row.classList.toggle('okrow', ok);
        row.classList.toggle('badrow', !ok);
        const rn = row.querySelector('.rn');
        if (ok) {
            rn.textContent = '✓';
        } else if (!rn.querySelector('[data-bonifica-goto]')) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'bC-rn';
            btn.dataset.bonificaGoto = '';
            btn.textContent = String(i + 1);
            rn.replaceChildren(btn);
        }
    });
    const root = grid.closest('.local-bonifica');
    root.querySelector('.bC-foot')?.classList.toggle('bad', state.cells.length > 0);
    ['ready', 'sub', 'foot', 'footshort'].forEach(k => {
        const el = root.querySelector(`[data-region="${k}"]`);
        if (el) {
            el.textContent = state[k];
        }
    });
    const ring = root.querySelector('.bC-ring');
    if (ring) {
        ring.style.setProperty('--p', state.pct);
        ring.querySelector('span').textContent = state.ok;
    }
    const gen = root.querySelector('[data-region="gen"]');
    if (gen) {
        gen.disabled = !state.canexport;
    }
};

/**
 * Saves one cell.
 *
 * @param {HTMLElement} grid
 * @param {HTMLInputElement|HTMLSelectElement} input
 */
const save = (grid, input) => {
    const m = CELL.exec(input.name);
    if (!m) {
        return;
    }
    let value = input.value;
    if (input.type === 'checkbox') {
        value = input.checked ? '1' : '0';
    }
    input.closest('td')?.classList.add('saving');
    Ajax.call([{methodname: 'local_bonifica_save_cell', args: {partid: Number(m[1]), field: m[2], value}}])[0]
        .then(state => paint(grid, state))
        .catch(Notification.exception)
        .finally(() => input.closest('td')?.classList.remove('saving'));
};

/**
 * Brings a red cell into view, even in a column off-screen, and puts the cursor in it.
 *
 * @param {HTMLElement} td
 */
const goTo = td => {
    const row = td.closest('tr');
    row.closest('tbody')?.querySelectorAll('tr.target').forEach(r => r.classList.remove('target'));
    row.classList.add('target');
    td.scrollIntoView({block: 'center', inline: 'center', behavior: 'smooth'});
    td.querySelector('.bC-cell')?.focus({preventScroll: true});
    td.classList.remove('flash');
    td.getBoundingClientRect();
    td.classList.add('flash');
};

/**
 * The red cell after the one being edited, starting again at the top.
 *
 * @param {HTMLElement} grid
 * @returns {HTMLElement|undefined}
 */
const nextBad = grid => {
    const cells = [...grid.querySelectorAll('td.bad')];
    const here = document.activeElement?.closest('td.bad');
    const i = here ? cells.indexOf(here) : -1;
    return cells[(i + 1) % cells.length];
};

/**
 * Opens or closes a dialog.
 *
 * @param {string} id
 * @param {boolean} open
 */
const dialog = (id, open) => {
    const dlg = document.getElementById(id);
    if (!dlg) {
        return;
    }
    dlg.classList.toggle('open', open);
    if (open) {
        dlg.querySelector('textarea, input:not([type=hidden])')?.focus();
    }
};

export const init = () => {
    const root = document.querySelector('.local-bonifica');
    if (!root || root.dataset.bonificaReady) {
        return;
    }
    root.dataset.bonificaReady = '1';

    const grid = root.querySelector('[data-bonifica-grid]');
    if (grid) {
        grid.addEventListener('change', e => {
            if (e.target.matches('.bC-cell, input[type=checkbox]')) {
                save(grid, e.target);
            }
        });
        // Enter in a cell saves it instead of sending the whole form.
        grid.addEventListener('keydown', e => {
            if (e.key === 'Enter' && e.target.matches('input.bC-cell')) {
                e.preventDefault();
                e.target.blur();
            }
        });
    }

    root.addEventListener('change', e => {
        if (e.target.matches('[data-bonifica-autosubmit]')) {
            e.target.form.submit();
        }
    });

    root.addEventListener('click', e => {
        const t = e.target.closest('[data-bonifica-open], [data-bonifica-close], [data-bonifica-fixopen], ' +
            '[data-bonifica-seg] button, [data-confirm], [data-bonifica-goto], [data-bonifica-next]');
        if (!t) {
            if (e.target.classList.contains('bC-dlg')) {
                e.target.classList.remove('open');
            }
            return;
        }
        if (t.hasAttribute('data-bonifica-goto')) {
            const td = t.closest('tr').querySelector('td.bad');
            if (td) {
                goTo(td);
            }
        } else if (t.hasAttribute('data-bonifica-next')) {
            const td = grid && nextBad(grid);
            if (td) {
                goTo(td);
            }
        } else if (t.dataset.bonificaOpen) {
            dialog(t.dataset.bonificaOpen, true);
        } else if (t.dataset.bonificaClose) {
            dialog(t.dataset.bonificaClose, false);
        } else if (t.hasAttribute('data-bonifica-fixopen')) {
            const form = t.parentElement.querySelector('[data-bonifica-fix]');
            form.hidden = !form.hidden;
            if (!form.hidden) {
                form.querySelector('input:not([type=hidden]), select')?.focus();
            }
        } else if (t.dataset.v) {
            t.parentElement.querySelectorAll('button').forEach(b => b.classList.toggle('on', b === t));
            root.classList.toggle('show-fin', t.dataset.v === 'fin');
        } else if (t.dataset.confirm) {
            e.preventDefault();
            Notification.saveCancelPromise(t.title || t.textContent, t.dataset.confirm, t.title || t.textContent)
                .then(() => t.form.requestSubmit(t))
                .catch(() => null);
        }
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            root.querySelectorAll('.bC-dlg.open').forEach(d => d.classList.remove('open'));
        }
    });
};
