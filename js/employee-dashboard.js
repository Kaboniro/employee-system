(function () {
    var csrfToken = '';

    function formatDate(dateStr) {
        if (!dateStr) return 'No due date';
        return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
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
        return fetch('php/task_actions.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) {
                    showMessage('error', data.error || 'Something went wrong.');
                    return Promise.reject(data);
                }
                return data;
            });
        });
    }

    function renderTask(task) {
        var template = document.getElementById('task-card-template');
        var node = template.content.cloneNode(true);
        var li = node.querySelector('.task-card');

        li.classList.add('task-card--' + task.status);
        li.draggable = true;
        li.addEventListener('dragstart', function (e) {
            e.dataTransfer.setData('text/plain', String(task.id));
            e.dataTransfer.effectAllowed = 'move';
        });

        node.querySelector('.task-card__status').textContent = task.status === 'completed' ? 'Completed' : 'Pending';
        node.querySelector('.task-card__title').textContent = task.title;
        node.querySelector('.task-card__due').textContent = 'Due: ' + formatDate(task.due_date);
        node.querySelector('.task-card__creator').textContent = 'By ' + task.created_by_name;

        var completedByEl = node.querySelector('.task-card__completed-by');
        if (task.status === 'completed' && task.completed_by_name) {
            completedByEl.textContent = 'Completed by ' + task.completed_by_name;
        } else {
            completedByEl.remove();
        }

        // Anyone may complete or reopen a task on the shared board.
        var completeBtn = node.querySelector('.task-card__btn--complete');
        if (task.status === 'completed') {
            completeBtn.remove();
        } else {
            completeBtn.addEventListener('click', function () {
                postAction('update-status', { id: task.id, status: 'completed' }).then(loadAndRender);
            });
        }

        // Only the creator may edit or delete their own task.
        var deleteBtn = node.querySelector('.task-card__btn--delete');
        if (task.is_owner) {
            deleteBtn.addEventListener('click', function () {
                if (!window.confirm('Delete "' + task.title + '"?')) return;
                postAction('delete-task', { id: task.id }).then(loadAndRender);
            });
        } else {
            deleteBtn.remove();
        }

        var editBtn = node.querySelector('.task-card__btn--edit');
        var editEl = node.querySelector('.task-card__edit');

        if (task.is_owner && task.status === 'pending') {
            var viewEl = node.querySelector('.task-card__view');
            var titleInput = node.querySelector('.task-card__edit-title');
            var dueInput = node.querySelector('.task-card__edit-due');
            titleInput.value = task.title;
            dueInput.value = task.due_date || '';

            editBtn.addEventListener('click', function () {
                viewEl.hidden = true;
                editEl.hidden = false;
            });
            node.querySelector('.task-card__btn--cancel').addEventListener('click', function () {
                viewEl.hidden = false;
                editEl.hidden = true;
            });
            node.querySelector('.task-card__btn--save').addEventListener('click', function () {
                var newTitle = titleInput.value.trim();
                if (!newTitle) return;
                postAction('edit-task', { id: task.id, title: newTitle, due_date: dueInput.value }).then(loadAndRender);
            });
        } else {
            editBtn.remove();
            editEl.remove();
        }

        return node;
    }

    function renderEmptyState(text) {
        var li = document.createElement('li');
        li.className = 'task-card task-card--empty';
        li.textContent = text;
        return li;
    }

    function renderGroup(listEl, countEl, label, tasks, emptyText) {
        countEl.textContent = label + ' (' + tasks.length + ')';
        listEl.innerHTML = '';
        if (tasks.length === 0) {
            listEl.appendChild(renderEmptyState(emptyText));
            return;
        }
        tasks.forEach(function (task) {
            listEl.appendChild(renderTask(task));
        });
    }

    function loadAndRender() {
        return fetch('php/employee_data.php', { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                document.getElementById('welcome-message').textContent = 'Welcome, ' + data.user.name;

                renderGroup(
                    document.getElementById('pending-tasks'),
                    document.getElementById('pending-count'),
                    'Pending',
                    data.tasks.pending,
                    'No pending tasks.'
                );
                renderGroup(
                    document.getElementById('completed-tasks'),
                    document.getElementById('completed-count'),
                    'Completed',
                    data.tasks.completed,
                    'No completed tasks.'
                );
            });
    }

    function loadOnlineList() {
        return fetch('php/online_employees.php', { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var listEl = document.getElementById('online-list');
                listEl.innerHTML = '';
                if (data.online.length === 0) {
                    var li = document.createElement('li');
                    li.className = 'online-list__empty';
                    li.textContent = 'No one else is online.';
                    listEl.appendChild(li);
                    return;
                }
                data.online.forEach(function (name) {
                    var li = document.createElement('li');
                    li.className = 'online-list__item';
                    li.innerHTML = '<span class="online-dot"></span>';
                    li.appendChild(document.createTextNode(name));
                    listEl.appendChild(li);
                });
            });
    }

    function setupDropzone(groupEl, targetStatus) {
        groupEl.addEventListener('dragover', function (e) {
            e.preventDefault();
            groupEl.classList.add('task-group--dragover');
        });
        groupEl.addEventListener('dragleave', function () {
            groupEl.classList.remove('task-group--dragover');
        });
        groupEl.addEventListener('drop', function (e) {
            e.preventDefault();
            groupEl.classList.remove('task-group--dragover');
            var id = e.dataTransfer.getData('text/plain');
            if (!id) return;
            postAction('update-status', { id: id, status: targetStatus }).then(loadAndRender);
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
        csrfToken = results[1];

        setupDropzone(document.getElementById('pending-group'), 'pending');
        setupDropzone(document.getElementById('completed-group'), 'completed');

        document.getElementById('add-task-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var titleInput = document.getElementById('new-task-title');
            var dueInput = document.getElementById('new-task-due');
            var title = titleInput.value.trim();
            if (!title) return;
            postAction('add-task', { title: title, due_date: dueInput.value }).then(function () {
                titleInput.value = '';
                dueInput.value = '';
                loadAndRender();
            });
        });

        loadAndRender();
        loadOnlineList();
        setInterval(loadOnlineList, 30000);
    });
})();
