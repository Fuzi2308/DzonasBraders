document.addEventListener('DOMContentLoaded', () => {
    const dayViewBtn = document.getElementById('dayView');
    const weekViewBtn = document.getElementById('weekView');
    const confirmDateBtn = document.getElementById('confirmDate');
    const leftArrowBtn = document.querySelector('.nav-button.left');
    const rightArrowBtn = document.querySelector('.nav-button.right');
    const dateInput = document.getElementById('dateInput');
    const slideContainer = document.querySelector('.slide');

    let currentView = 'day'; 

    const fetchLessons = (viewType) => {
        currentView = viewType; 
        let selectedDate = dateInput.value;

        if (!selectedDate) {
            const today = new Date();
            const formattedDate = today.toISOString().split('T')[0];
            dateInput.value = formattedDate;
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
                console.error('Помилка:', error);
            });
    };

    const adjustDate = (direction) => {
        let currentDate = new Date(dateInput.value || new Date());

        if (currentView === 'week') {
            currentDate.setDate(currentDate.getDate() + direction * 7); 
        } else {
            currentDate.setDate(currentDate.getDate() + direction); 
        }

        const formattedDate = currentDate.toISOString().split('T')[0];
        dateInput.value = formattedDate;
        fetchLessons(currentView); 
    };

    dayViewBtn.addEventListener('click', () => fetchLessons('day'));
    weekViewBtn.addEventListener('click', () => fetchLessons('week'));
    confirmDateBtn.addEventListener('click', () => fetchLessons('day'));

    leftArrowBtn.addEventListener('click', () => adjustDate(-1)); 
    rightArrowBtn.addEventListener('click', () => adjustDate(1)); 
});
