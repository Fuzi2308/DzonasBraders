document.addEventListener('DOMContentLoaded', () => {
    const dayViewBtn = document.getElementById('dayView');
    const weekViewBtn = document.getElementById('weekView');
    const confirmDateBtn = document.getElementById('confirmDate'); // Dodajemy obsługę przycisku
    const dateInput = document.getElementById('dateInput');
    const slideContainer = document.querySelector('.slide');

    const fetchLessons = (viewType) => {
        let selectedDate = dateInput.value;

        // Ustaw bieżącą datę, jeśli pole daty jest puste
        if (!selectedDate) {
            const today = new Date();
            const formattedDate = today.toISOString().split('T')[0]; // Format YYYY-MM-DD
            dateInput.value = formattedDate; // Ustaw datę w polu
            selectedDate = formattedDate;
        }

        fetch('fetchLessons.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ view: viewType, date: selectedDate }),
        })
            .then((response) => response.text())
            .then((data) => {
                slideContainer.innerHTML = data;
            })
            .catch((error) => {
                console.error('Błąd:', error);
            });
    };

    dayViewBtn.addEventListener('click', () => fetchLessons('day'));
    weekViewBtn.addEventListener('click', () => fetchLessons('week'));

    // Obsługa kliknięcia przycisku akceptacji daty
    confirmDateBtn.addEventListener('click', () => {
        fetchLessons('day'); // Domyślnie wywołujemy widok dzienny
    });
});
