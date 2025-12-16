/**
 * kanban.js
 * Drag and Drop logic for Kanban Board
 */

function allowDrop(ev) {
    ev.preventDefault();
}

function drag(ev) {
    ev.dataTransfer.setData("text", ev.target.id);
    ev.target.classList.add('dragging');
}

function drop(ev) {
    ev.preventDefault();
    var data = ev.dataTransfer.getData("text");
    var card = document.getElementById(data);
    card.classList.remove('dragging');

    // Find the drop target column
    // The event target might be the column itself or a child element
    let column = ev.target.closest('.kanban-column');

    if (column) {
        let taskList = column.querySelector('.kanban-tasks');
        taskList.appendChild(card);

        // Update count
        updateCounts();

        // Trigger Backend Update
        const taskId = card.getAttribute('data-task-id');
        const newStatus = column.getAttribute('data-status');
        updateTaskStatus(taskId, newStatus);
    }
}

function updateCounts() {
    let globalCounts = {
        'todo': 0,
        'in_progress': 0,
        'review': 0,
        'done': 0
    };

    document.querySelectorAll('.kanban-column').forEach(col => {
        const count = col.querySelectorAll('.kanban-card').length;
        col.querySelector('.count').textContent = count;

        // Update Global Stats mapping
        const status = col.getAttribute('data-status');
        if (status) {
            globalCounts[status] = count;
        }
    });

    // Update Global DOM Elements
    if (document.getElementById('stat-todo')) {
        document.getElementById('stat-todo').textContent = globalCounts['todo'];
        document.getElementById('stat-inprogress').textContent = globalCounts['in_progress'];
        document.getElementById('stat-review').textContent = globalCounts['review'];
        document.getElementById('stat-done').textContent = globalCounts['done'];

        // Update Total
        const total = globalCounts['todo'] + globalCounts['in_progress'] + globalCounts['review'] + globalCounts['done'];
        if (document.getElementById('stat-total')) {
            document.getElementById('stat-total').textContent = total;
        }
    }
}

function updateTaskStatus(taskId, status) {
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('task_id', taskId);
    formData.append('status', status);
    formData.append('csrf_token', csrfToken);

    // Re-use tasks.php existing endpoint which handles status updates
    fetch('tasks.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // showToast('Statut mis à jour', 'success'); // Optional: Too noisy for kanban
            } else {
                showToast(data.message || 'Erreur lors de la mise à jour', 'error');
                // Revert move if failed? Complex to implement, maybe refresh page
                setTimeout(() => location.reload(), 2000);
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Erreur réseau', 'error');
        });
}

// Ensure dragging class is removed if drag ends without drop
document.addEventListener('dragend', function (event) {
    if (event.target.classList.contains('kanban-card')) {
        event.target.classList.remove('dragging');
    }
});
