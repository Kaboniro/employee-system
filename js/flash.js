(function () {
    var params = new URLSearchParams(window.location.search);

    ['error', 'success'].forEach(function (key) {
        var value = params.get(key);
        var el = document.querySelector('[data-role="' + key + '"]');
        if (value && el) {
            el.textContent = value;
            el.hidden = false;
        }
    });

    var csrfFields = document.querySelectorAll('.csrf-token');
    if (csrfFields.length) {
        fetch('php/csrf.php', { credentials: 'same-origin' })
            .then(function (res) { return res.text(); })
            .then(function (token) {
                csrfFields.forEach(function (field) { field.value = token; });
            });
    }
})();
