<?php
// pages/gestion_fichiers.php
require '../config/db.php';
require '../includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

include '../includes/header.php';
?>

<div class="import-export-container">
    <!-- File Manager Section -->
    <div class="file-manager-section">
        <div class="section-header">
            <div class="header-content">
                <div class="section-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <div class="section-info">
                    <h2>Gestion des Fichiers</h2>
                    <p>Stockez et organisez vos documents</p>
                </div>
            </div>

            <div class="view-toggles">
                <button class="view-btn active" onclick="switchView('grid')" title="Vue Grille">
                    <i class="fas fa-th-large"></i>
                </button>
                <button class="view-btn" onclick="switchView('list')" title="Vue Liste">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>

        <div class="fm-toolbar">
            <div class="fm-actions">
                <button class="btn btn-secondary btn-sm" onclick="createNewFolder()">
                    <i class="fas fa-folder-plus"></i> Nouveau dossier
                </button>
                <button class="btn btn-primary btn-sm" onclick="triggerUpload()">
                    <i class="fas fa-cloud-upload-alt"></i> Uploader
                </button>
                <input type="file" id="fileUpload" style="display: none" onchange="uploadFile(this.files[0])">
            </div>
            <div class="fm-breadcrumb" id="fmBreadcrumb">
                <span onclick="loadFiles('')" class="crumb-root"><i class="fas fa-home"></i></span>
            </div>
        </div>

        <div class="fm-dropzone" id="fmDropzone">
            <div class="fm-container">
                <div id="fmLoader" class="fm-loader" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>

                <div class="fm-content grid-view" id="fmContent">
                    <!-- Dynamically populated -->
                </div>

                <div id="fmEmpty" class="fm-empty" style="display: none;">
                    <i class="fas fa-folder-open"></i>
                    <p>Ce dossier est vide</p>
                    <span class="muted">Glissez des fichiers ici pour uploader</span>
                </div>

                <div class="upload-overlay" id="uploadOverlay">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Déposez pour uploader</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rename Modal -->
<div id="renameModal" class="modal">
    <div class="modal-content">
        <h3>Renommer</h3>
        <input type="hidden" id="renameOldName">
        <input type="text" id="renameNewName" class="form-control" placeholder="Nouveau nom">
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeRenameModal()">Annuler</button>
            <button class="btn btn-primary" onclick="confirmRename()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<style>
    .import-export-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1rem 0;
    }

    .file-manager-section {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 2rem;
        border: 1px solid var(--border-color);
        min-height: 700px;
        display: flex;
        flex-direction: column;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .header-content {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    .section-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .section-icon i {
        font-size: 1.25rem;
        color: white;
    }

    .section-info h2 {
        margin: 0 0 0.25rem 0;
        font-size: 1.5rem;
        color: var(--text-primary);
    }

    .section-info p {
        margin: 0;
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    .view-toggles {
        display: flex;
        gap: 0.5rem;
        background: var(--bg-secondary);
        padding: 0.25rem;
        border-radius: 8px;
    }

    .view-btn {
        background: none;
        border: none;
        padding: 0.5rem;
        border-radius: 6px;
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.2s;
    }

    .view-btn:hover {
        color: var(--text-primary);
    }

    .view-btn.active {
        background: var(--bg-card);
        color: var(--primary-color);
        shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    /* File Manager Styles */
    .fm-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-secondary);
        padding: 0.75rem 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        border: 1px solid var(--border-color);
    }

    .fm-actions {
        display: flex;
        gap: 0.5rem;
    }

    .fm-breadcrumb {
        font-size: 0.9rem;
        color: var(--text-secondary);
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .crumb-root,
    .crumb-item {
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 6px;
        transition: background 0.2s;
    }

    .crumb-root:hover,
    .crumb-item:hover {
        background: rgba(0, 0, 0, 0.05);
        color: var(--primary-color);
    }

    .fm-dropzone {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .fm-container {
        flex: 1;
        position: relative;
        border: 2px dashed var(--border-color);
        border-radius: 12px;
        padding: 1.5rem;
        transition: all 0.2s;
        background: var(--bg-card);
    }

    .fm-container.drag-over {
        border-color: var(--primary-color);
        background: rgba(99, 102, 241, 0.05);
    }

    .upload-overlay {
        position: absolute;
        inset: 0;
        background: rgba(255, 255, 255, 0.9);
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 100;
        backdrop-filter: blur(2px);
    }

    .fm-container.drag-over .upload-overlay {
        display: flex;
    }

    .upload-overlay i {
        font-size: 4rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
    }

    .upload-overlay p {
        font-size: 1.25rem;
        color: var(--primary-color);
        font-weight: 500;
    }

    /* Grid View */
    .fm-content.grid-view {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 1.5rem;
    }

    .grid-view .fm-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1rem;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
        text-align: center;
        border: 1px solid transparent;
    }

    .grid-view .fm-item:hover {
        background: var(--bg-secondary);
        border-color: var(--border-color);
        transform: translateY(-2px);
    }

    .grid-view .fm-icon {
        font-size: 3rem;
        margin-bottom: 0.75rem;
        transition: transform 0.2s;
    }

    .grid-view .fm-item:hover .fm-icon {
        transform: scale(1.1);
    }

    .grid-view .fm-name {
        font-size: 0.85rem;
        word-break: break-word;
        line-height: 1.3;
        color: var(--text-primary);
        max-width: 100%;
        margin-bottom: 0.25rem;
        font-weight: 500;
    }

    .grid-view .fm-meta {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    /* List View */
    .fm-content.list-view {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .list-view .fm-item {
        display: grid;
        grid-template-columns: 40px 1fr 100px 150px 80px;
        align-items: center;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        cursor: pointer;
        border: 1px solid transparent;
        transition: all 0.2s;
    }

    .list-view .fm-item:hover {
        background: var(--bg-secondary);
        border-color: var(--border-color);
    }

    .list-view .fm-icon {
        font-size: 1.25rem !important;
        margin: 0;
    }

    .list-view .fm-name {
        font-size: 0.95rem;
        margin: 0;
        text-align: left;
    }

    .list-view .fm-meta {
        font-size: 0.9rem;
        text-align: right;
    }

    .list-view .fm-date {
        font-size: 0.85rem;
        color: var(--text-muted);
        text-align: right;
    }

    /* Icons Colors */
    .fm-icon.folder {
        color: #f59e0b;
    }

    .fm-icon.file-xls,
    .fm-icon.file-xlsx,
    .fm-icon.file-csv {
        color: #10b981;
    }

    .fm-icon.file-pdf {
        color: #ef4444;
    }

    .fm-icon.file-jpg,
    .fm-icon.file-png,
    .fm-icon.file-jpeg {
        color: #8b5cf6;
    }

    .fm-icon.file-doc,
    .fm-icon.file-docx {
        color: #3b82f6;
    }

    .fm-icon.file-zip,
    .fm-icon.file-rar {
        color: #6366f1;
    }

    .fm-item-actions {
        opacity: 0;
        transition: opacity 0.2s;
        display: flex;
        gap: 0.25rem;
        margin-left: auto;
        /* Push to right in list view */
    }

    .fm-item:hover .fm-item-actions {
        opacity: 1;
    }

    .fm-action-btn {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        color: var(--text-secondary);
        transition: all 0.2s;
    }

    .fm-action-btn:hover {
        background: rgba(0, 0, 0, 0.1);
        color: var(--primary-color);
    }

    .fm-action-btn.delete:hover {
        color: #ef4444;
        background: rgba(239, 68, 68, 0.1);
    }

    /* Loader & Empty States */
    .fm-loader,
    .fm-empty {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
    }

    .fm-loader {
        font-size: 2rem;
        color: var(--primary-color);
    }

    .fm-empty i {
        font-size: 3rem;
        opacity: 0.2;
        margin-bottom: 1rem;
    }

    /* Toast Notifications */
    .toast-container {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        z-index: 2000;
    }

    .toast {
        background: var(--bg-card);
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        gap: 1rem;
        border-left: 4px solid var(--primary-color);
        animation: slideIn 0.3s ease-out;
        min-width: 300px;
    }

    .toast.success {
        border-left-color: #10b981;
    }

    .toast.error {
        border-left-color: #ef4444;
    }

    .toast-icon {
        font-size: 1.25rem;
    }

    .toast.success .toast-icon {
        color: #10b981;
    }

    .toast.error .toast-icon {
        color: #ef4444;
    }

    .toast-content h4 {
        margin: 0;
        font-size: 0.95rem;
    }

    .toast-content p {
        margin: 0.25rem 0 0;
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Modal */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        backdrop-filter: blur(2px);
    }

    .modal.active {
        display: flex;
        animation: fadeIn 0.2s;
    }

    .modal-content {
        background: var(--bg-card);
        padding: 2rem;
        border-radius: 12px;
        width: 100%;
        max-width: 400px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 1.5rem;
    }
</style>

<script>
    let currentPath = '';
    let currentView = 'grid';

    document.addEventListener('DOMContentLoaded', () => {
        loadFiles('');
        setupDragAndDrop();
    });

    function switchView(view) {
        currentView = view;
        document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`.view-btn[onclick="switchView('${view}')"]`).classList.add('active');

        // Reload render
        loadFiles(currentPath);
    }

    function showToast(type, title, message) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        let icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

        toast.innerHTML = `
        <div class="toast-icon"><i class="fas ${icon}"></i></div>
        <div class="toast-content">
            <h4>${title}</h4>
            <p>${message}</p>
        </div>
    `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    function loadFiles(path) {
        currentPath = path;
        updateBreadcrumb(path);

        document.getElementById('fmLoader').style.display = 'flex';
        document.getElementById('fmContent').innerHTML = '';
        document.getElementById('fmEmpty').style.display = 'none';

        // Update main class based on view
        const content = document.getElementById('fmContent');
        content.className = `fm-content ${currentView}-view`;

        fetch(`/Taskly/api/file_manager.php?action=list&path=${encodeURIComponent(path)}`)
            .then(r => r.json())
            .then(res => {
                document.getElementById('fmLoader').style.display = 'none';
                if (res.success) {
                    renderFiles(res.data.items);
                } else {
                    showToast('error', 'Erreur', res.message);
                }
            })
            .catch(err => {
                document.getElementById('fmLoader').style.display = 'none';
                console.error(err);
                showToast('error', 'Erreur', 'Impossible de charger les fichiers');
            });
    }

    function renderFiles(items) {
        const container = document.getElementById('fmContent');

        if (items.length === 0) {
            document.getElementById('fmEmpty').style.display = 'flex';
            return;
        }

        items.forEach(item => {
            const el = document.createElement('div');
            el.className = 'fm-item';
            el.onclick = (e) => {
                if (e.target.closest('.fm-action-btn')) return;
                if (item.type === 'folder') {
                    loadFiles(currentPath ? `${currentPath}/${item.name}` : item.name);
                } else {
                    window.open(`/Taskly/uploads/${currentPath ? currentPath + '/' : ''}${item.name}`, '_blank');
                }
            };

            let iconClass = 'fas fa-file';
            if (item.type === 'folder') iconClass = 'fas fa-folder folder';
            else if (['xlsx', 'xls', 'csv'].includes(item.extension)) iconClass = 'fas fa-file-excel file-xls';
            else if (item.extension === 'pdf') iconClass = 'fas fa-file-pdf file-pdf';
            else if (['jpg', 'png', 'jpeg', 'gif'].includes(item.extension)) iconClass = 'fas fa-file-image file-img';
            else if (['doc', 'docx'].includes(item.extension)) iconClass = 'fas fa-file-word file-doc';
            else if (['zip', 'rar', '7z'].includes(item.extension)) iconClass = 'fas fa-file-archive file-zip';

            if (currentView === 'grid') {
                el.innerHTML = `
                <div class="fm-icon ${item.type === 'folder' ? 'folder' : 'file-' + item.extension}">
                    <i class="${iconClass}"></i>
                </div>
                <div class="fm-name" title="${item.name}">${item.name}</div>
                <div class="fm-meta">${item.size}</div>
                
                <div class="fm-item-actions">
                    <div class="fm-action-btn" onclick="openRename('${item.name}')" title="Renommer"><i class="fas fa-edit"></i></div>
                    <div class="fm-action-btn delete" onclick="deleteItem('${item.name}')" title="Supprimer"><i class="fas fa-trash"></i></div>
                </div>
            `;
            } else {
                // List View
                el.innerHTML = `
                <div class="fm-icon">
                    <i class="${iconClass}"></i>
                </div>
                <div class="fm-name" title="${item.name}">${item.name}</div>
                <div class="fm-meta">${item.size}</div>
                <div class="fm-date">${item.date}</div>
                
                <div class="fm-item-actions">
                    <div class="fm-action-btn" onclick="openRename('${item.name}')" title="Renommer"><i class="fas fa-edit"></i></div>
                    <div class="fm-action-btn delete" onclick="deleteItem('${item.name}')" title="Supprimer"><i class="fas fa-trash"></i></div>
                </div>
            `;
            }

            container.appendChild(el);
        });
    }

    function updateBreadcrumb(path) {
        const container = document.getElementById('fmBreadcrumb');
        const parts = path ? path.split('/') : [];

        let html = `<span onclick="loadFiles('')" class="crumb-root"><i class="fas fa-home"></i></span>`;

        let builtPath = '';
        parts.forEach((part, index) => {
            if (!part) return;
            builtPath += (index > 0 ? '/' : '') + part;
            html += ` <i class="fas fa-chevron-right" style="font-size: 0.7rem; color: var(--text-muted)"></i> 
                  <span onclick="loadFiles('${builtPath}')" class="crumb-item">${part}</span>`;
        });

        container.innerHTML = html;
    }

    function setupDragAndDrop() {
        const dropzone = document.getElementById('fmDropzone');
        const container = document.querySelector('.fm-container');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            container.classList.add('drag-over');
        }

        function unhighlight(e) {
            container.classList.remove('drag-over');
        }

        dropzone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;

            if (files.length > 0) {
                uploadFile(files[0]);
            }
        }
    }

    function createNewFolder() {
        const name = prompt("Nom du nouveau dossier :");
        if (name) {
            const formData = new FormData();
            formData.append('action', 'create_folder');
            formData.append('path', currentPath);
            formData.append('name', name);

            fetch('/Taskly/api/file_manager.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast('success', 'Dossier créé', `Le dossier "${name}" a été créé.`);
                        loadFiles(currentPath);
                    } else {
                        showToast('error', 'Erreur', res.message);
                    }
                });
        }
    }

    function triggerUpload() {
        document.getElementById('fileUpload').click();
    }

    function uploadFile(file) {
        if (file) {
            const formData = new FormData();
            formData.append('action', 'upload');
            formData.append('path', currentPath);
            formData.append('file', file);

            // Show uploading toast
            showToast('success', 'Upload en cours', 'Veuillez patienter...');

            fetch('/Taskly/api/file_manager.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast('success', 'Succès', 'Fichier uploadé avec succès.');
                        loadFiles(currentPath);
                    } else {
                        showToast('error', 'Erreur', res.message);
                    }
                })
                .catch(err => {
                    showToast('error', 'Erreur', 'Problème de connexion.');
                });
        }
    }

    function deleteItem(name) {
        if (confirm(`Êtes-vous sûr de vouloir supprimer "${name}" ?`)) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('path', currentPath);
            formData.append('name', name);

            fetch('/Taskly/api/file_manager.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast('success', 'Supprimé', `"${name}" a été supprimé.`);
                        loadFiles(currentPath);
                    } else {
                        showToast('error', 'Erreur', res.message);
                    }
                });
        }
    }

    function openRename(name) {
        document.getElementById('renameOldName').value = name;
        document.getElementById('renameNewName').value = name;
        document.getElementById('renameModal').classList.add('active');
    }

    function closeRenameModal() {
        document.getElementById('renameModal').classList.remove('active');
    }

    function confirmRename() {
        const oldName = document.getElementById('renameOldName').value;
        const newName = document.getElementById('renameNewName').value;

        if (oldName && newName && oldName !== newName) {
            const formData = new FormData();
            formData.append('action', 'rename');
            formData.append('path', currentPath);
            formData.append('old_name', oldName);
            formData.append('new_name', newName);

            fetch('/Taskly/api/file_manager.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        closeRenameModal();
                        showToast('success', 'Renommé', 'Élément renommé avec succès.');
                        loadFiles(currentPath);
                    } else {
                        showToast('error', 'Erreur', res.message);
                    }
                });
        } else {
            closeRenameModal();
        }
    }
</script>

<?php include '../includes/footer.php'; ?>