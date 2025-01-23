// Zmienna przechowująca początkowy rozmiar czcionki
let initialFontSize = 0;

// Funkcja do ustawienia początkowego rozmiaru czcionki dla * (wszystkich elementów)
function setInitialFontSize() {
    const allElements = document.querySelectorAll('*');
    allElements.forEach(element => {
        const fontSize = parseFloat(window.getComputedStyle(element).fontSize);
        // Przechowujemy początkowy rozmiar czcionki dla każdego elementu
        element.setAttribute('data-initial-font-size', fontSize);
    });
}

// Funkcja do zmiany rozmiaru czcionki
function changeFontSize(size) {
    const allElements = document.querySelectorAll('*'); // Wszystkie elementy na stronie
    allElements.forEach(element => {
        const initialFontSize = parseFloat(element.getAttribute('data-initial-font-size')); // Pobieramy początkowy rozmiar czcionki
        let newFontSize;

        // Zmieniamy rozmiar czcionki w zależności od typu
        if (size === 'small') {
            newFontSize = initialFontSize; // Przywracamy początkowy rozmiar
        } else if (size === 'medium') {
            newFontSize = initialFontSize + 2; // Zwiększamy o 5px
        } else if (size === 'big') {
            newFontSize = initialFontSize + 5; // Zwiększamy o 10px
        }

        // Ustawiamy nowy rozmiar czcionki
        element.style.fontSize = `${newFontSize}px`;
    });
}

// Przypisanie zdarzenia do przycisków
document.querySelectorAll('.textSize p').forEach(p => {
    p.addEventListener('click', function() {
        const size = this.getAttribute('data-size');
        changeFontSize(size);
    });
});

// Ustawiamy początkowy rozmiar czcionki przy załadowaniu strony
window.onload = function() {
    setInitialFontSize(); // Pobierz początkowy rozmiar czcionki
    changeFontSize('small'); // Ustaw domyślny rozmiar czcionki na "small"
};
