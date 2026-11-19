// dipee/assets/app.js
document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-print]');
    if (trigger) {
        window.print();
    }
});
