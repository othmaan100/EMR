{{-- Add / remove rows in a line-item form: #add-line button, #lines container, [data-line] rows. --}}
<script>
    document.getElementById('add-line').addEventListener('click', () => {
        const container = document.getElementById('lines');
        const rows = container.querySelectorAll('[data-line]');
        const clone = rows[rows.length - 1].cloneNode(true);
        const index = rows.length;
        clone.querySelectorAll('[name]').forEach((el) => {
            el.name = el.name.replace(/^(\w+)\[\d+\]/, `$1[${index}]`);
            el.value = '';
            el.classList.remove('is-invalid');
        });
        clone.querySelectorAll('.text-danger.small').forEach((el) => el.remove());
        container.appendChild(clone);
        clone.querySelector('select, input')?.focus();
    });
    document.getElementById('lines').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove-line]');
        if (btn && document.querySelectorAll('[data-line]').length > 1) btn.closest('[data-line]').remove();
    });
</script>
