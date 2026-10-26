{{--
    Full-text tooltips: any text box, dropdown or shortened label whose content
    is cut off shows the whole text when pointed at. While typing in a field
    that is too narrow, the tooltip stays up and follows the text. Fields that
    fit, passwords and date pickers are left alone. Needs Bootstrap's JS.
--}}
<script>
    (function () {
        const SELECTOR = [
            'input.form-control:not([type=password]):not([type=date]):not([type=datetime-local]):not([type=file]):not([type=number])',
            'textarea.form-control',
            'select.form-select',
            '.ts-wrapper.single .ts-control', // searchable dropdowns
            '.text-truncate',
        ].join(',');
        const measure = document.createElement('canvas').getContext('2d');

        function textOf(el) {
            if (el.matches('input, textarea')) return el.value;
            if (el.matches('select')) return el.selectedOptions[0]?.text || '';
            if (el.matches('.ts-control')) return el.querySelector('.item')?.textContent.trim() || '';
            return el.textContent.trim();
        }

        /** true when the element's text doesn't fit in it */
        function isCut(el) {
            const text = textOf(el);
            if (!text) return false;
            if (el.matches('textarea')) return el.scrollHeight > el.clientHeight + 1;
            if (el.matches('select, .ts-control')) {
                const cs = getComputedStyle(el);
                measure.font = `${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`;
                const room = el.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
                return measure.measureText(text).width > room;
            }
            return el.scrollWidth > el.clientWidth + 1;
        }

        function show(el) {
            if (!isCut(el)) { hide(el); return; }
            // already open (e.g. hovered, then clicked to type): just refresh the text.
            // Calling show() twice on a Bootstrap tooltip makes it hide itself.
            const open = bootstrap.Tooltip.getInstance(el) && document.getElementById(el.getAttribute('aria-describedby') || '');
            if (open) {
                open.querySelector('.tooltip-inner').textContent = textOf(el);
                bootstrap.Tooltip.getInstance(el).update();
                return;
            }
            new bootstrap.Tooltip(el, {
                title: () => textOf(el), trigger: 'manual', placement: 'top', customClass: 'full-text-tip',
                fallbackPlacements: ['bottom', 'top'],
            }).show();
        }
        // dispose rather than hide, so the next show starts from a fresh tooltip
        function hide(el) {
            bootstrap.Tooltip.getInstance(el)?.dispose();
        }

        document.addEventListener('mouseover', e => {
            const el = e.target.closest(SELECTOR);
            if (el && !el.contains(e.relatedTarget)) show(el);
        });
        document.addEventListener('mouseout', e => {
            const el = e.target.closest(SELECTOR);
            if (el && !el.contains(e.relatedTarget) && document.activeElement !== el) hide(el);
        });
        // keep the full text visible while editing a field that is too narrow
        document.addEventListener('focusin', e => { if (e.target.matches?.(SELECTOR)) show(e.target); });
        document.addEventListener('input', e => { if (e.target.matches?.(SELECTOR)) show(e.target); });
        document.addEventListener('change', e => { if (e.target.matches?.('select.form-select')) show(e.target); });
        document.addEventListener('focusout', e => { if (e.target.matches?.(SELECTOR)) hide(e.target); });
        // a dropdown that opens should not sit under its own tooltip
        document.addEventListener('mousedown', e => { const el = e.target.closest('select.form-select, .ts-control'); if (el) hide(el); });
    })();
</script>
