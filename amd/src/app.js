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
 * Progressive enhancement for every Schedule Course Copy page. Every feature here has a
 * plain link or form underneath it, so the pages still work without JavaScript.
 *
 * Written as a plain AMD module (no build step needed); amd/build/app.min.js is a copy.
 *
 * @module     local_schedulecoursecopy/app
 * @copyright  2026 German Bobadilla, MA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str', 'core/toast', 'core_form/modalform'], function(Str, Toast, ModalFormModule) {
    'use strict';

    var ModalForm = ModalFormModule.default || ModalFormModule;
    var COMPONENT = 'local_schedulecoursecopy';
    var FIELDS = ['templateshortname', 'fullname', 'shortname', 'scheduledate', 'startdate', 'enddate'];
    var DATES = ['scheduledate', 'startdate', 'enddate'];

    /**
     * Turn DD/MM/YYYY into YYYY-MM-DD; anything else is trimmed and returned as is.
     *
     * @param {string} value
     * @returns {string}
     */
    var toIso = function(value) {
        var m = String(value).trim().match(/^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$/);
        if (!m) {
            return String(value).trim();
        }
        return m[3] + '-' + m[2].padStart(2, '0') + '-' + m[1].padStart(2, '0');
    };

    /**
     * Rows in the whole table open the job drawer, except when clicking a control inside them.
     */
    var initClickRows = function() {
        document.addEventListener('click', function(e) {
            var row = e.target.closest('tr[data-href]');
            if (!row || e.target.closest('a, button, input, label, select, textarea')) {
                return;
            }
            window.location.href = row.dataset.href;
        });
    };

    /**
     * Escape closes the drawer; focus starts on its close button.
     */
    var initDrawer = function() {
        var drawer = document.querySelector('[data-scc-drawer]');
        if (!drawer) {
            return;
        }
        document.body.classList.add('scc-drawer-open');
        var close = drawer.querySelector('[data-scc-drawer-close]');
        if (close) {
            close.focus();
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && close) {
                window.location.href = close.href;
            }
        });
    };

    /**
     * Collapse and expand tree nodes, and filter the tree by name.
     */
    var initTree = function() {
        var tree = document.querySelector('.scc-tree');
        if (!tree) {
            return;
        }
        var setOpen = function(li, open) {
            var toggle = li.querySelector(':scope > .scc-tree-row [data-scc-toggle]');
            var group = li.querySelector(':scope > ul');
            var icon = li.querySelector(':scope > .scc-tree-row .scc-tree-icon');
            if (!toggle || !group) {
                return;
            }
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            group.hidden = !open;
            if (icon) {
                icon.classList.toggle('fa-folder-open', open);
                icon.classList.toggle('fa-folder', !open);
            }
        };
        tree.addEventListener('click', function(e) {
            var toggle = e.target.closest('[data-scc-toggle]');
            if (toggle) {
                var li = toggle.closest('li');
                setOpen(li, toggle.getAttribute('aria-expanded') !== 'true');
            }
        });

        var filter = document.querySelector('[data-scc-treefilter]');
        if (!filter) {
            return;
        }
        var nodes = Array.prototype.slice.call(tree.querySelectorAll('li[data-scc-node]'));
        filter.addEventListener('input', function() {
            var q = filter.value.trim().toLowerCase();
            // Deepest first, so a parent knows whether any child matched.
            nodes.slice().reverse().forEach(function(li) {
                var childMatch = Array.prototype.some.call(li.querySelectorAll(':scope > ul > li'), function(c) {
                    return !c.hidden;
                });
                li.hidden = q !== '' && li.dataset.name.indexOf(q) === -1 && !childMatch;
                if (q !== '') {
                    setOpen(li, true);
                }
            });
        });
    };

    /**
     * Add and edit categories in a modal instead of on a separate page.
     */
    var initCategoryModal = function() {
        document.addEventListener('click', function(e) {
            var link = e.target.closest('[data-scc-catmodal]');
            if (!link) {
                return;
            }
            e.preventDefault();
            var editing = link.dataset.sccCatmodal === 'edit';
            var form = new ModalForm({
                formClass: 'local_schedulecoursecopy\\form\\category_modal',
                args: editing ? {id: link.dataset.id} : {parentid: link.dataset.parentid || 0},
                modalConfig: {title: Str.get_string(editing ? 'categoryedit' : 'categoryadd', COMPONENT)},
                returnFocus: link,
            });
            form.addEventListener(form.events.FORM_SUBMITTED, function(event) {
                window.location.href = M.cfg.wwwroot + '/local/schedulecoursecopy/categories.php?id=' + event.detail.id;
            });
            form.show();
        });
    };

    /**
     * The "Schedule copies" panel: open/close, Table | CSV, rows, inherited defaults, paste,
     * CSV import and live validation.
     */
    var initBulk = function() {
        var panel = document.querySelector('[data-scc-bulk]');
        if (!panel) {
            return;
        }
        var form = panel.querySelector('form');
        var tbody = panel.querySelector('[data-scc-rows]');
        var template = panel.querySelector('[data-scc-rowtemplate]');
        var summary = panel.querySelector('[data-scc-summary]');
        var submit = panel.querySelector('[data-scc-submit]');
        var openButton = document.querySelector('[data-scc-bulkopen]');
        var strings = {};

        var keys = ['bulksummaryempty', 'bulksummaryerrors', 'bulksummaryerrors1', 'bulksummaryready', 'bulksummaryready1',
            'schedulecopies', 'schedulecopy1', 'schedulecopiesn',
            'errorfullnamerequired', 'errorshortnamerequired', 'errorshortnameduplicate', 'errortemplaterequired',
            'errorcopydaterequired', 'bulkpasted', 'bulkcsvimported', 'bulkcsverror'];

        var defaultInput = function(field) {
            return panel.querySelector('[data-scc-default="' + field + '"]');
        };
        var rows = function() {
            return Array.prototype.slice.call(tbody.querySelectorAll('tr.scc-bulkrow'));
        };
        var cell = function(row, field) {
            return row.querySelector('[data-scc-field="' + field + '"]');
        };

        // Blank date cells show the default, muted, until the user types their own.
        var applyDefaults = function() {
            var tpl = defaultInput('templateshortname').value.trim();
            rows().forEach(function(row) {
                cell(row, 'templateshortname').placeholder = tpl || '';
                DATES.forEach(function(field) {
                    var input = cell(row, field);
                    if (input.value === '' || input.dataset.inherited === '1') {
                        input.value = defaultInput(field).value;
                        input.dataset.inherited = '1';
                        input.classList.add('is-inherited');
                    }
                });
            });
        };

        var renumber = function() {
            rows().forEach(function(row, i) {
                row.querySelector('[data-scc-rownum]').textContent = i + 1;
            });
        };

        var addRows = function(n) {
            var added = [];
            for (var i = 0; i < n; i++) {
                tbody.appendChild(template.content.cloneNode(true));
                added.push(tbody.lastElementChild);
            }
            renumber();
            applyDefaults();
            return added;
        };

        var isBlank = function(row) {
            return ['templateshortname', 'fullname', 'shortname'].every(function(f) {
                return cell(row, f).value.trim() === '';
            });
        };

        var setError = function(row, field, message) {
            row.classList.toggle('has-error', !!message);
            FIELDS.forEach(function(f) {
                cell(row, f).classList.toggle('is-invalid', !!message && f === field);
            });
            row.querySelector('[data-scc-rowerror]').textContent = message || '';
        };

        var validate = function() {
            if (!strings.schedulecopies) {
                return;
            }
            var counts = {};
            rows().forEach(function(row) {
                var k = cell(row, 'shortname').value.trim().toLowerCase();
                if (k) {
                    counts[k] = (counts[k] || 0) + 1;
                }
            });
            var valid = 0;
            var errors = 0;
            rows().forEach(function(row) {
                if (isBlank(row)) {
                    setError(row, '', '');
                    return;
                }
                // An error from the server (e.g. short name in use) stays until the row is edited.
                if (row.dataset.serverError === '1') {
                    errors++;
                    return;
                }
                var short = cell(row, 'shortname').value.trim();
                var error = null;
                if (!cell(row, 'fullname').value.trim()) {
                    error = ['fullname', strings.errorfullnamerequired];
                } else if (!short) {
                    error = ['shortname', strings.errorshortnamerequired];
                } else if (counts[short.toLowerCase()] > 1) {
                    error = ['shortname', strings.errorshortnameduplicate];
                } else if (!cell(row, 'templateshortname').value.trim() && !defaultInput('templateshortname').value.trim()) {
                    error = ['templateshortname', strings.errortemplaterequired];
                } else if (!cell(row, 'scheduledate').value) {
                    error = ['scheduledate', strings.errorcopydaterequired];
                }
                setError(row, error ? error[0] : '', error ? error[1] : '');
                if (error) {
                    errors++;
                } else {
                    valid++;
                }
            });

            var text = strings.bulksummaryempty;
            if (errors) {
                text = strings[errors === 1 ? 'bulksummaryerrors1' : 'bulksummaryerrors']
                    .replace('{errors}', errors).replace('{valid}', valid);
            } else if (valid) {
                text = valid === 1 ? strings.bulksummaryready1 : strings.bulksummaryready.replace('{n}', valid);
            }
            summary.textContent = text;
            summary.classList.toggle('is-error', errors > 0);
            submit.disabled = !valid || errors > 0;
            if (valid === 1) {
                submit.textContent = strings.schedulecopy1;
            } else {
                submit.textContent = valid ? strings.schedulecopiesn.replace('{n}', valid) : strings.schedulecopies;
            }
        };

        // Fill rows from parsed records, starting at a given row and column.
        var fill = function(records, startRow, startCol) {
            var all = rows();
            while (all.length < startRow + records.length) {
                addRows(1);
                all = rows();
            }
            records.forEach(function(values, r) {
                var row = all[startRow + r];
                values.forEach(function(value, c) {
                    var field = FIELDS[startCol + c];
                    if (!field) {
                        return;
                    }
                    var input = cell(row, field);
                    input.value = DATES.indexOf(field) !== -1 ? toIso(value) : String(value).trim();
                    input.dataset.inherited = '';
                    input.classList.remove('is-inherited');
                });
                delete row.dataset.serverError;
            });
            applyDefaults();
            validate();
        };

        // Rows that came back from the server with an error keep it until edited.
        rows().forEach(function(row) {
            if (row.classList.contains('has-error')) {
                row.dataset.serverError = '1';
            }
        });

        panel.addEventListener('input', function(e) {
            var input = e.target;
            if (input.dataset.sccField) {
                input.dataset.inherited = '';
                input.classList.remove('is-inherited');
                delete input.closest('tr').dataset.serverError;
            }
            if (input.dataset.sccDefault) {
                applyDefaults();
            }
            validate();
        });
        panel.addEventListener('change', function(e) {
            if (e.target.dataset.sccDefault) {
                applyDefaults();
                validate();
            }
        });

        panel.addEventListener('paste', function(e) {
            var input = e.target.closest('[data-scc-field]');
            if (!input) {
                return;
            }
            var text = (e.clipboardData || window.clipboardData).getData('text');
            if (!/[\t\n]/.test(text.trim())) {
                return;
            }
            e.preventDefault();
            var lines = text.replace(/\r/g, '').split('\n').filter(function(l) {
                return l.trim() !== '';
            });
            fill(lines.map(function(l) {
                return l.split('\t');
            }), rows().indexOf(input.closest('tr')), FIELDS.indexOf(input.dataset.sccField));
            Toast.add(strings.bulkpasted.replace('{n}', lines.length));
        });

        panel.addEventListener('click', function(e) {
            var add = e.target.closest('[data-scc-addrows]');
            if (add) {
                addRows(parseInt(add.dataset.sccAddrows, 10));
                validate();
                return;
            }
            var remove = e.target.closest('[data-scc-removerow]');
            if (remove) {
                remove.closest('tr').remove();
                if (!rows().length) {
                    addRows(1);
                }
                renumber();
                validate();
                return;
            }
            var mode = e.target.closest('[data-scc-mode]');
            if (mode) {
                setMode(mode.dataset.sccMode);
                return;
            }
            var close = e.target.closest('[data-scc-bulkclose]');
            if (close) {
                e.preventDefault();
                panel.hidden = true;
                if (openButton) {
                    openButton.hidden = false;
                    openButton.focus();
                }
            }
        });

        var setMode = function(mode) {
            panel.querySelectorAll('[data-scc-mode]').forEach(function(b) {
                var on = b.dataset.sccMode === mode;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            panel.querySelectorAll('[data-scc-pane]').forEach(function(p) {
                p.hidden = p.dataset.sccPane !== mode;
            });
        };

        if (openButton) {
            openButton.addEventListener('click', function(e) {
                e.preventDefault();
                panel.hidden = false;
                openButton.hidden = true;
                var first = panel.querySelector('[data-scc-field="fullname"]');
                if (first) {
                    first.focus();
                }
            });
        }

        // CSV import: parse in the browser, then review in the table.
        var parseCsv = function(text) {
            var out = [];
            var row = [];
            var field = '';
            var quoted = false;
            for (var i = 0; i < text.length; i++) {
                var ch = text[i];
                if (quoted) {
                    if (ch === '"' && text[i + 1] === '"') {
                        field += '"';
                        i++;
                    } else if (ch === '"') {
                        quoted = false;
                    } else {
                        field += ch;
                    }
                } else if (ch === '"') {
                    quoted = true;
                } else if (ch === ',' || ch === ';') {
                    row.push(field);
                    field = '';
                } else if (ch === '\n' || ch === '\r') {
                    if (ch === '\r' && text[i + 1] === '\n') {
                        i++;
                    }
                    row.push(field);
                    out.push(row);
                    row = [];
                    field = '';
                } else {
                    field += ch;
                }
            }
            if (field !== '' || row.length) {
                row.push(field);
                out.push(row);
            }
            return out.filter(function(r) {
                return r.some(function(v) {
                    return v.trim() !== '';
                });
            });
        };

        var importCsv = function(file) {
            var reader = new FileReader();
            reader.onload = function() {
                var records = parseCsv(String(reader.result).replace(/^﻿/, ''));
                var names = {template: 0, templateshortname: 0, fullname: 1, shortname: 2, copyon: 3, scheduledate: 3,
                    startdate: 4, enddate: 5};
                var order = [0, 1, 2, 3, 4, 5];
                if (records.length && records[0].some(function(h) {
                    return names[h.trim().toLowerCase()] !== undefined;
                })) {
                    var header = records.shift().map(function(h) {
                        return names[h.trim().toLowerCase()];
                    });
                    order = header;
                }
                var mapped = records.map(function(r) {
                    var values = ['', '', '', '', '', ''];
                    r.forEach(function(v, i) {
                        if (order[i] !== undefined) {
                            values[order[i]] = v;
                        }
                    });
                    return values;
                });
                if (!mapped.length) {
                    Toast.add(strings.bulkcsverror, {type: 'danger'});
                    return;
                }
                // Replace empty rows rather than appending after them.
                rows().forEach(function(r) {
                    if (isBlank(r)) {
                        r.remove();
                    }
                });
                var start = rows().length;
                fill(mapped, start, 0);
                renumber();
                setMode('grid');
                Toast.add(strings.bulkcsvimported.replace('{n}', mapped.length).replace('{file}', file.name));
            };
            reader.readAsText(file);
        };

        var fileInput = panel.querySelector('[data-scc-csvfile]');
        fileInput.addEventListener('change', function() {
            if (fileInput.files[0]) {
                importCsv(fileInput.files[0]);
                fileInput.value = '';
            }
        });
        var drop = panel.querySelector('[data-scc-dropzone]');
        ['dragenter', 'dragover'].forEach(function(type) {
            drop.addEventListener(type, function(e) {
                e.preventDefault();
                drop.classList.add('is-over');
            });
        });
        ['dragleave', 'drop'].forEach(function(type) {
            drop.addEventListener(type, function() {
                drop.classList.remove('is-over');
            });
        });
        drop.addEventListener('drop', function(e) {
            e.preventDefault();
            if (e.dataTransfer.files[0]) {
                importCsv(e.dataTransfer.files[0]);
            }
        });

        // Inherited values are the defaults anyway; clear them so the server applies the defaults itself.
        form.addEventListener('submit', function() {
            rows().forEach(function(row) {
                DATES.forEach(function(field) {
                    var input = cell(row, field);
                    if (input.dataset.inherited === '1') {
                        input.value = '';
                    }
                });
            });
        });

        applyDefaults();
        Str.get_strings(keys.map(function(key) {
            // Placeholders are filled in here, not by the string API.
            var a = {
                bulksummaryerrors: {errors: '{errors}', valid: '{valid}'},
                bulksummaryerrors1: {valid: '{valid}'},
                bulksummaryready: '{n}', schedulecopiesn: '{n}', bulkpasted: '{n}',
                bulkcsvimported: {n: '{n}', file: '{file}'},
            }[key];
            return {key: key, component: COMPONENT, param: a};
        })).then(function(values) {
            keys.forEach(function(key, i) {
                strings[key] = values[i];
            });
            validate();
            return values;
        }).catch(function() {
            return null;
        });
    };

    /**
     * Status report: row selection and the bulk action bar.
     */
    var initReport = function() {
        document.querySelectorAll('[data-scc-autosubmit]').forEach(function(select) {
            select.addEventListener('change', function() {
                select.form.submit();
            });
        });

        var form = document.querySelector('[data-scc-report]');
        if (!form) {
            return;
        }
        var bar = form.querySelector('[data-scc-bulkbar]');
        var count = form.querySelector('[data-scc-selcount]');
        var all = form.querySelector('[data-scc-selectall]');
        var boxes = Array.prototype.slice.call(form.querySelectorAll('[data-scc-select]'));
        var label = '';

        var update = function() {
            var n = boxes.filter(function(b) {
                return b.checked;
            }).length;
            bar.hidden = n === 0;
            count.textContent = label.replace('{n}', n);
            all.checked = n > 0 && n === boxes.length;
            all.indeterminate = n > 0 && n < boxes.length;
            boxes.forEach(function(b) {
                b.closest('tr').classList.toggle('is-selected', b.checked);
            });
        };

        form.addEventListener('change', function(e) {
            if (e.target === all) {
                boxes.forEach(function(b) {
                    b.checked = all.checked;
                });
            }
            update();
        });
        form.querySelector('[data-scc-clearsel]').addEventListener('click', function() {
            boxes.forEach(function(b) {
                b.checked = false;
            });
            update();
        });
        Str.get_string('reportselected', COMPONENT, '{n}').then(function(s) {
            label = s;
            update();
            return s;
        }).catch(function() {
            return null;
        });
    };

    /**
     * Settings: "Reset to default" links, shown only while a value differs from its default.
     */
    var initSettings = function() {
        var settings = document.querySelector('.scc-settings');
        if (!settings) {
            return;
        }
        var valueOf = function(name) {
            var control = settings.querySelector('[data-scc-setting="' + name + '"]');
            if (!control) {
                return '';
            }
            if (name === 'notifyroles') {
                return Array.prototype.map.call(control.querySelectorAll('input:checked'), function(i) {
                    return i.value;
                }).join(',');
            }
            if (control.type === 'checkbox') {
                return control.checked ? '1' : '0';
            }
            return control.value.replace(/\r\n/g, '\n');
        };
        var refresh = function() {
            settings.querySelectorAll('[data-scc-reset]').forEach(function(button) {
                button.hidden = valueOf(button.dataset.sccReset) === button.dataset.default;
            });
        };
        settings.addEventListener('input', refresh);
        settings.addEventListener('change', refresh);
        settings.addEventListener('click', function(e) {
            var button = e.target.closest('[data-scc-reset]');
            if (!button) {
                return;
            }
            var name = button.dataset.sccReset;
            var value = button.dataset.default;
            var control = settings.querySelector('[data-scc-setting="' + name + '"]');
            if (name === 'notifyroles') {
                var ids = value.split(',');
                control.querySelectorAll('input').forEach(function(i) {
                    i.checked = ids.indexOf(i.value) !== -1;
                });
            } else if (control.type === 'checkbox') {
                control.checked = value === '1';
            } else {
                control.value = value;
            }
            refresh();
            control.focus();
        });
        refresh();
    };

    return {
        init: function() {
            initClickRows();
            initDrawer();
            initTree();
            initCategoryModal();
            initBulk();
            initReport();
            initSettings();
        },
    };
});
