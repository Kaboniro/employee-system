fetch('php/session_info.php', { credentials: 'same-origin' })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (!data.authenticated) {
            window.location.href = 'index.html';
            return;
        }
        window.location.href = data.role === 'admin' ? 'admin.html' : 'employee-dashboard.html';
    })
    .catch(function () {
        window.location.href = 'index.html';
    });
