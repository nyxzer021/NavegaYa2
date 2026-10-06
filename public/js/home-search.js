const initializeHomeSearch = () => {
    const transportInput = document.getElementById('transportInput');
    const tabs = [...document.querySelectorAll('.ny-transport-tabs a')];

    if (!transportInput || tabs.length === 0) return;

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            transportInput.value = tab.getAttribute('href') === '#salidas-aereas' ? 'air' : 'river';
        });
    });

    if (transportInput.value === 'air') {
        tabs.find((tab) => tab.getAttribute('href') === '#salidas-aereas')?.click();
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeHomeSearch);
} else {
    initializeHomeSearch();
}
