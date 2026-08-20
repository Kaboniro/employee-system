(function () {
    var EXIT_MS = 220;

    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[href]');
        if (!link) return;
        if (link.target === '_blank' || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        var href = link.getAttribute('href');
        // Only intercept plain same-flow navigations, e.g. "register.html".
        if (!href || !/^[a-zA-Z0-9_-]+\.html$/.test(href)) return;

        var panel = document.querySelector('.auth-panel__inner');
        if (!panel) return;

        e.preventDefault();
        panel.classList.add('panel-exit');
        setTimeout(function () {
            window.location.href = href;
        }, EXIT_MS);
    });
})();
