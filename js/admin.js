(function () {
    var csrfToken = '';

    function formatDateTime(dateStr) {
        if (!dateStr) return 'Never';
        return new Date(dateStr).toLocaleString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
        });
    }

    function showMessage(type, text) {
        var errorEl = document.querySelector('[data-role="error"]');
        var successEl = document.querySelector('[data-role="success"]');
        if (type === 'error') {
            errorEl.textContent = text;
            errorEl.hidden = false;
            successEl.hidden = true;
        } else {
            successEl.textContent = text;
            successEl.hidden = false;
            errorEl.hidden = true;
        }
    }

    function postAction(action, params) {
        var body = new URLSearchParams(Object.assign({ action: action, csrf_token: csrfToken }, params));
        return fetch('php/admin_actions.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        }).then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        });
    }

    function renderRow(employee) {
        var template = document.getElementById('employee-row-template');
        var node = template.content.cloneNode(true);
        var tr = node.querySelector('tr');

        var viewName = node.querySelector('.cell-name');
        var viewDept = node.querySelector('.cell-department');
        var viewPhone = node.querySelector('.cell-phone');
        var viewEmail = node.querySelector('.cell-email');
        var viewRole = node.querySelector('.cell-role');

        var editName = node.querySelector('.cell-edit-name');
        var editDept = node.querySelector('.cell-edit-department');
        var editPhone = node.querySelector('.cell-edit-phone');
        var editEmail = node.querySelector('.cell-edit-email');
        var editRole = node.querySelector('.cell-edit-role');

        function fillView(emp) {
            viewName.textContent = emp.name;
            viewDept.textContent = emp.department;
            viewPhone.textContent = emp.phone;
            viewEmail.textContent = emp.email;
            viewRole.textContent = emp.role;
            viewRole.className = 'badge cell-view cell-role badge--' + emp.role;
        }

        function fillEdit(emp) {
            editName.value = emp.name;
            editDept.value = emp.department;
            editPhone.value = emp.phone;
            editEmail.value = emp.email;
            editRole.value = emp.role;
        }

        fillView(employee);
        fillEdit(employee);
        node.querySelector('.cell-last-login').textContent = formatDateTime(employee.logged_in_at);

        var editBtn = node.querySelector('.cell-edit-btn');
        var saveBtn = node.querySelector('.cell-save-btn');
        var cancelBtn = node.querySelector('.cell-cancel-btn');
        var sendLinkBtn = node.querySelector('.cell-send-link-btn');
        var deleteBtn = node.querySelector('.cell-delete-btn');

        var viewEls = [viewName, viewDept, viewPhone, viewEmail, viewRole];
        var editEls = [editName, editDept, editPhone, editEmail, editRole];

        function enterEditMode() {
            tr.classList.add('row--editing');
            viewEls.forEach(function (el) { el.hidden = true; });
            editEls.forEach(function (el) { el.hidden = false; });
            editBtn.hidden = true;
            sendLinkBtn.hidden = true;
            deleteBtn.hidden = true;
            saveBtn.hidden = false;
            cancelBtn.hidden = false;
        }

        function exitEditMode() {
            tr.classList.remove('row--editing');
            viewEls.forEach(function (el) { el.hidden = false; });
            editEls.forEach(function (el) { el.hidden = true; });
            editBtn.hidden = false;
            sendLinkBtn.hidden = false;
            deleteBtn.hidden = false;
            saveBtn.hidden = true;
            cancelBtn.hidden = true;
        }

        editBtn.addEventListener('click', enterEditMode);

        cancelBtn.addEventListener('click', function () {
            fillEdit(employee);
            exitEditMode();
        });

        saveBtn.addEventListener('click', function () {
            var updated = {
                id: employee.id,
                name: editName.value.trim(),
                department: editDept.value.trim(),
                phone: editPhone.value.trim(),
                email: editEmail.value.trim(),
                role: editRole.value,
            };
            if (!updated.name || !updated.department || !updated.phone || !updated.email) {
                showMessage('error', 'Please fill in all fields.');
                return;
            }
            postAction('edit-employee', updated).then(function (result) {
                if (!result.ok) {
                    showMessage('error', result.data.error || 'Could not update employee.');
                    return;
                }
                employee = result.data.employee;
                fillView(employee);
                fillEdit(employee);
                exitEditMode();
                showMessage('success', 'Employee updated.');
            });
        });

        sendLinkBtn.addEventListener('click', function () {
            postAction('send-login-link', { id: employee.id }).then(function (result) {
                if (!result.ok) {
                    showMessage('error', result.data.error || 'Could not send login link.');
                    return;
                }
                showMessage('success', 'Login link emailed to ' + result.data.email + '.');
            });
        });

        deleteBtn.addEventListener('click', function () {
            if (!window.confirm('Delete ' + employee.name + '? This cannot be undone.')) return;
            postAction('delete-employee', { id: employee.id }).then(function (result) {
                if (!result.ok) {
                    showMessage('error', result.data.error || 'Could not delete employee.');
                    return;
                }
                showMessage('success', 'Employee deleted.');
                loadAndRender();
            });
        });

        return node;
    }

    function loadAndRender() {
        return fetch('php/admin_data.php', { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var employees = data.employees;
                document.getElementById('employee-count').textContent = '(' + employees.length + ')';
                var tbody = document.getElementById('employee-rows');
                tbody.innerHTML = '';
                employees.forEach(function (employee) {
                    tbody.appendChild(renderRow(employee));
                });
            });
    }

    Promise.all([
        fetch('php/session_info.php', { credentials: 'same-origin' }).then(function (res) { return res.json(); }),
        fetch('php/csrf.php', { credentials: 'same-origin' }).then(function (res) { return res.text(); }),
    ]).then(function (results) {
        var session = results[0];
        if (!session.authenticated) {
            window.location.href = 'index.html';
            return;
        }
        if (session.role !== 'admin') {
            window.location.href = 'employee-dashboard.html';
            return;
        }
        csrfToken = results[1];
        document.getElementById('welcome-message').textContent = 'Welcome, ' + session.name;

        document.getElementById('add-employee-toggle-btn').addEventListener('click', function () {
            var panel = document.getElementById('add-employee-panel');
            panel.hidden = !panel.hidden;
        });

        document.getElementById('add-employee-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var form = e.target;
            var params = {
                name: form.name.value.trim(),
                department: form.department.value.trim(),
                phone: form.phone.value.trim(),
                email: form.email.value.trim(),
                role: form.role.value,
            };
            postAction('add-employee', params).then(function (result) {
                if (!result.ok) {
                    showMessage('error', result.data.error || 'Could not add employee.');
                    return;
                }
                form.reset();
                document.getElementById('add-employee-panel').hidden = true;
                showMessage('success', 'Employee added and login link emailed.');
                loadAndRender();
            });
        });

        loadAndRender();
    });
})();
