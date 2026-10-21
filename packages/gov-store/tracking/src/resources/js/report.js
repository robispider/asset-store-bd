/* Measure real rows after fonts load. The server-rendered source is the fallback. */
(function () {
    'use strict';
    async function start() {
        const root = document.querySelector('[data-programme-report]');
        if (!root) return;
        const source = root.querySelector('[data-report-source]');
        const pages = root.querySelector('[data-report-pages]');
        const print = root.querySelector('[data-report-print]');
        const message = root.querySelector('[data-report-message]');
        const digits = value => root.lang.startsWith('bn') ? String(value).replace(/\d/g, d => '০১২৩৪৫৬৭৮৯'[Number(d)]) : String(value);
        let body, sheet;
        print.disabled = true;
        message.textContent = root.dataset.preparing;
        function newPage() {
            // Core's unlayered normalize rule forces article/footer to display:block.
            // A div allows the registered package grid to work without global overrides.
            sheet = document.createElement('div');
            sheet.className = 'gs-document gs-tracking-report__page';
            sheet.dataset.gsTable = 'ledger';
            sheet.append(root.querySelector('[data-report-running]').content.cloneNode(true));
            body = document.createElement('div');
            body.className = 'gs-tracking-report__body';
            sheet.append(body);
            const footer = document.createElement('div');
            footer.className = 'gs-tracking-report__page-footer';
            sheet.append(footer);
            pages.append(sheet);
        }
        function overflows() { return body.scrollHeight > body.clientHeight + 1 || body.scrollWidth > body.clientWidth + 1; }
        function place(node, keepNext) {
            body.append(node);
            if (overflows() || (keepNext && body.getBoundingClientRect().bottom - node.getBoundingClientRect().bottom < 55)) {
                node.remove();
                if (body.children.length) newPage();
                body.append(node);
                if (overflows()) throw new Error('Oversized report block');
            }
        }
        try {
            if (document.fonts) await document.fonts.ready;
            newPage();
            const doc = source.querySelector('.gs-document');
            place(doc.querySelector('.gs-document__head').cloneNode(true));
            place(doc.querySelector('.gs-document__meta').cloneNode(true));
            const sections = {};
            const blocks = Array.from(source.querySelectorAll('[data-report-block]'));
            for (const block of blocks) {
                if (block.hasAttribute('data-report-break') && body.children.length) newPage();
                if (!block.hasAttribute('data-report-table')) {
                    const clone = block.cloneNode(true);
                    place(clone, !!clone.querySelector('h2, h3'));
                    if (clone.dataset.reportSection) {
                        clone.id = 'report-' + clone.dataset.reportSection;
                        sections[clone.dataset.reportSection] = pages.children.length;
                    }
                    continue;
                }
                const table = block.querySelector('table');
                const rows = Array.from(table.tBodies[0].rows);
                // Build the empty shell once; cloning the complete office list on
                // every continued page otherwise creates thousands of unused rows.
                const shell = block.cloneNode(true);
                shell.querySelector('tbody').replaceChildren();
                let target, fragment;
                function tableFragment(path) {
                    fragment = shell.cloneNode(true);
                    const copy = fragment.querySelector('table');
                    copy.tBodies[0].replaceChildren();
                    target = copy.tBodies[0];
                    if (path) {
                        const crumb = document.createElement('p');
                        crumb.className = 'gs-tracking-report__continuation';
                        crumb.textContent = path + ' · ' + root.dataset.continued;
                        fragment.prepend(crumb);
                    }
                    body.append(fragment);
                }
                tableFragment();
                for (let i = 0; i < rows.length;) {
                    // Keep a chain of subtotal bands with its first office, never stranded.
                    const group = [];
                    do { group.push(rows[i++].cloneNode(true)); }
                    while (i < rows.length && group[group.length - 1].hasAttribute('data-report-band'));
                    group.forEach(row => target.append(row));
                    if (overflows()) {
                        group.forEach(row => row.remove());
                        if (!target.rows.length) fragment.remove();
                        const last = body.lastElementChild;
                        const heading = last && last.querySelector('h2, h3') && !last.querySelector('table') ? last : null;
                        if (heading) heading.remove();
                        if (body.children.length) newPage();
                        if (heading) body.append(heading);
                        tableFragment(group[0].dataset.reportPath);
                        group.forEach(row => target.append(row));
                        if (overflows()) throw new Error('Oversized report row');
                    }
                }
            }
            Array.from(pages.children).forEach((page, index) => {
                page.querySelectorAll('[data-report-section]').forEach(section => { sections[section.dataset.reportSection] = index + 1; });
                page.querySelector('.gs-tracking-report__page-footer').textContent = root.dataset.pageLabel.replace('{page}', digits(index + 1)).replace('{total}', digits(pages.children.length));
            });
            pages.querySelectorAll('[data-report-toc-page]').forEach(node => { node.textContent = digits(sections[node.dataset.reportTocPage] || ''); });
            source.hidden = true;
            source.querySelectorAll('[data-report-section]').forEach(node => node.removeAttribute('id'));
            message.textContent = '';
            root.dataset.reportReady = 'true';
        } catch (error) {
            pages.replaceChildren();
            source.querySelectorAll('[data-report-section]').forEach(node => { node.id = 'report-' + node.dataset.reportSection; });
            message.textContent = root.dataset.paginationFailed;
            root.dataset.reportReady = 'fallback';
        } finally {
            print.disabled = false;
        }
        print.addEventListener('click', async () => {
            print.disabled = true;
            root.querySelectorAll('.gs-tracking-report__print-frame').forEach(previous => previous.remove());
            const frame = document.createElement('iframe');
            frame.className = 'gs-tracking-report__print-frame';
            frame.title = print.textContent.trim();
            root.append(frame);
            try {
                // Print only the report: the application's sidebar/content padding must
                // not shift a measured A4 sheet or add a shell header/footer to its pages.
                const output = frame.contentDocument;
                for (const attr of document.documentElement.attributes) output.documentElement.setAttribute(attr.name, attr.value);
                const title = output.createElement('title');
                title.textContent = document.title;
                output.head.append(title);
                const loaded = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(link => new Promise((resolve, reject) => {
                    const copy = link.cloneNode(true);
                    copy.href = link.href;
                    const deadline = setTimeout(() => reject(new Error('Print stylesheet unavailable')), 15000);
                    copy.onload = () => { clearTimeout(deadline); resolve(); };
                    copy.onerror = () => { clearTimeout(deadline); reject(new Error('Print stylesheet unavailable')); };
                    output.head.append(copy);
                }));
                const report = output.createElement('div');
                report.className = 'gs-tracking-report';
                report.lang = root.lang;
                report.append((source.hidden ? pages : source).cloneNode(true));
                output.body.append(report);
                await Promise.all(loaded);
                if (output.fonts) await output.fonts.ready;
                message.textContent = '';
                frame.contentWindow.addEventListener('afterprint', () => frame.remove(), {once:true});
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (error) {
                message.textContent = root.dataset.printFailed;
                frame.remove();
            } finally {
                print.disabled = false;
            }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
