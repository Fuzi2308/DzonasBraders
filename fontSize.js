
let initialFontSize = 0;


function setInitialFontSize() {
    const allElements = document.querySelectorAll('*');
    allElements.forEach(element => {
        const fontSize = parseFloat(window.getComputedStyle(element).fontSize);
        
        element.setAttribute('data-initial-font-size', fontSize);
    });
}


function changeFontSize(size) {
    const allElements = document.querySelectorAll('*'); 
    allElements.forEach(element => {
        const initialFontSize = parseFloat(element.getAttribute('data-initial-font-size'));
        let newFontSize;

        
        if (size === 'small') {
            newFontSize = initialFontSize; 
        } else if (size === 'medium') {
            newFontSize = initialFontSize + 2; 
        } else if (size === 'big') {
            newFontSize = initialFontSize + 5;
        }

        
        element.style.fontSize = `${newFontSize}px`;
    });
}


document.querySelectorAll('.textSize p').forEach(p => {
    p.addEventListener('click', function() {
        const size = this.getAttribute('data-size');
        changeFontSize(size);
    });
});


window.onload = function() {
    setInitialFontSize(); 
    changeFontSize('small');
};
