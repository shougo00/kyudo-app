<script>
document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('recipientGrid');
    const count = document.getElementById('recipientCount');
    const selectAll = document.getElementById('selectAllRecipients');
    const clear = document.getElementById('clearRecipients');

    if (!grid || !count) {
        return;
    }

    const boxes = Array.from(grid.querySelectorAll('input[type="checkbox"]'));
    const updateCount = () => {
        count.textContent = boxes.filter((box) => box.checked).length;
    };

    selectAll?.addEventListener('click', () => {
        boxes.forEach((box) => {
            box.checked = true;
        });
        updateCount();
    });

    clear?.addEventListener('click', () => {
        boxes.forEach((box) => {
            box.checked = false;
        });
        updateCount();
    });

    boxes.forEach((box) => box.addEventListener('change', updateCount));
    updateCount();
});
</script>
