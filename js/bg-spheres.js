(function () {
    var container = document.createElement('div');
    container.className = 'bg-spheres';
    container.setAttribute('aria-hidden', 'true');
    container.innerHTML =
        '<div class="bg-sphere bg-sphere--1"></div>' +
        '<div class="bg-sphere bg-sphere--2"></div>';
    document.body.prepend(container);
})();
