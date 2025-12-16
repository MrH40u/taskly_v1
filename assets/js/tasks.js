/**
 * tasks.js
 * Logic for the tasks management page
 */

// Helper function to get CSRF token
function getCSRFToken() {
    const tokenInput = document.querySelector('input[name="csrf_token"]');
    return tokenInput ? tokenInput.value : '';
}

// =====================
// ADD TASK
// =====================
function openModal() {
    const modal = document.getElementById('taskModal');
    if (modal) modal.classList.add('active');
}

function closeModal() {
    const modal = document.getElementById('taskModal');
    if (modal) {
        modal.classList.remove('active');
        const form = document.getElementById('addTaskForm');
        if (form) form.reset();
    }
}

// Add Task Form Listener
const addTaskForm = document.getElementById('addTaskForm');
if (addTaskForm) {
    addTaskForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('tasks.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Tâche créée avec succès !', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            });
    });

    // Close on outside click
    document.getElementById('taskModal').addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });
}

// =====================
// STATUS & ACTIONS
// =====================
function updateStatus(taskId, status) {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('task_id', taskId);
    formData.append('status', status);
    formData.append('csrf_token', getCSRFToken());

    fetch('tasks.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                showToast(data.message || 'Erreur', 'error');
                setTimeout(() => location.reload(), 2000);
            } else {
                showToast('Statut mis à jour', 'success');
            }
        });
}

function deleteTask(taskId, taskTitle) {
    if (confirm('Êtes-vous sûr de vouloir supprimer la tâche "' + taskTitle + '" ?')) {
        const formData = new FormData();
        formData.append('action', 'delete_task');
        formData.append('task_id', taskId);
        formData.append('csrf_token', getCSRFToken());

        fetch('tasks.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Tâche supprimée', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Erreur lors de la suppression', 'error');
                }
            });
    }
}

function advanceStatus(taskId) {
    const formData = new FormData();
    formData.append('action', 'advance_status');
    formData.append('task_id', taskId);
    formData.append('csrf_token', getCSRFToken());

    fetch('tasks.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // showToast(data.message, 'success'); 
                location.reload();
            } else {
                showToast(data.message || 'Erreur', 'error');
            }
        });
}

// =====================
// EDIT TASK
// =====================
function openEditModal(taskId) {
    const formData = new FormData();
    formData.append('action', 'get_task');
    formData.append('task_id', taskId);
    formData.append('csrf_token', getCSRFToken());

    fetch('tasks.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const task = data.task;
                const idInput = document.getElementById('edit_task_id');
                const modal = document.getElementById('editTaskModal');

                if (idInput && modal) {
                    idInput.value = task.id;
                    document.getElementById('edit_title').value = task.title;
                    document.getElementById('edit_description').value = task.description || '';
                    document.getElementById('edit_project_id').value = task.project_id;
                    document.getElementById('edit_priority').value = task.priority;
                    document.getElementById('edit_due_date').value = task.due_date || '';
                    document.getElementById('edit_assigned_to').value = task.assigned_to || '';
                    modal.classList.add('active');
                }
            } else {
                showToast(data.message || 'Erreur lors du chargement', 'error');
            }
        });
}

function closeEditModal() {
    const modal = document.getElementById('editTaskModal');
    if (modal) {
        modal.classList.remove('active');
        const form = document.getElementById('editTaskForm');
        if (form) form.reset();
    }
}

// Edit Form Listener
const editTaskForm = document.getElementById('editTaskForm');
if (editTaskForm) {
    editTaskForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('tasks.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Tâche modifiée avec succès', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Erreur', 'error');
                }
            });
    });

    // Close edit modal on outside click
    document.getElementById('editTaskModal').addEventListener('click', function (e) {
        if (e.target === this) closeEditModal();
    });
}

// =====================
// VIEW TASK
// =====================
function openViewModal(taskId) {
    const formData = new FormData();
    formData.append('action', 'get_task');
    formData.append('task_id', taskId);
    formData.append('csrf_token', getCSRFToken());

    fetch('tasks.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const task = data.task;
                const modal = document.getElementById('viewTaskModal');

                if (modal) {
                    document.getElementById('view_title').textContent = task.title;
                    document.getElementById('view_description').textContent = task.description || 'Aucune description';

                    // Status badge
                    const statusLabels = { 'todo': 'À faire', 'in_progress': 'En cours', 'review': 'En revue', 'done': 'Terminé' };
                    const statusEl = document.getElementById('view_status');
                    statusEl.textContent = statusLabels[task.status] || task.status;
                    statusEl.className = 'badge badge-status-' + task.status;

                    // Priority badge
                    const priorityLabels = { 'low': 'Basse', 'medium': 'Moyenne', 'high': 'Haute' };
                    document.getElementById('view_priority_container').innerHTML =
                        '<span class="badge badge-' + task.priority + '">' + priorityLabels[task.priority] + '</span>';

                    document.getElementById('view_project').textContent = task.project_name || 'ASTREE';
                    document.getElementById('view_assigned').textContent = task.assigned_user || 'Non assigné';
                    document.getElementById('view_due_date').textContent = task.due_date || 'Non définie';
                    document.getElementById('view_created_at').textContent = task.created_at ? new Date(task.created_at).toLocaleDateString('fr-FR') : '-';

                    // Duration
                    if (task.duration) {
                        let durationText = '';
                        const mins = parseInt(task.duration);
                        if (mins < 60) durationText = mins + ' min';
                        else if (mins < 1440) durationText = Math.floor(mins / 60) + 'h ' + (mins % 60 > 0 ? (mins % 60) + 'min' : '');
                        else durationText = Math.floor(mins / 1440) + 'j ' + Math.floor((mins % 1440) / 60) + 'h';
                        document.getElementById('view_duration').textContent = durationText;
                    } else {
                        document.getElementById('view_duration').textContent = '-';
                    }

                    modal.classList.add('active');
                }
            } else {
                showToast(data.message || 'Erreur', 'error');
            }
        });
}

function closeViewModal() {
    const modal = document.getElementById('viewTaskModal');
    if (modal) modal.classList.remove('active');
}

// Close view modal on outside click
const viewTaskModal = document.getElementById('viewTaskModal');
if (viewTaskModal) {
    viewTaskModal.addEventListener('click', function (e) {
        if (e.target === this) closeViewModal();
    });
}
