<?php
// api/file_manager.php
require '../config/db.php';
require '../includes/functions.php';

// Ensure user is logged in
requireLogin();

// Base upload directory
$base_dir = __DIR__ . '/../uploads/';

// Ensure base folder exists
if (!file_exists($base_dir)) {
    mkdir($base_dir, 0777, true);
}

// Get action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Current path handling (prevent directory traversal)
$current_path = $_POST['path'] ?? $_GET['path'] ?? '';
// Remove any .. or attempts to go above root
$current_path = str_replace('..', '', $current_path);
$current_path = trim($current_path, '/\\');

$full_path = $base_dir . $current_path;
if (!is_dir($full_path)) {
    // Fallback to root if invalid path
    $full_path = $base_dir;
    $current_path = '';
}

// JSON Response helper
function jsonResponse($success, $message, $data = [])
{
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// Actions
switch ($action) {
    case 'list':
        // List files and directories
        $items = scandir($full_path);
        $result = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..')
                continue;

            $item_path = $full_path . '/' . $item;
            $is_dir = is_dir($item_path);

            $result[] = [
                'name' => $item,
                'type' => $is_dir ? 'folder' : 'file',
                'size' => $is_dir ? '-' : formatSize(filesize($item_path)),
                'date' => date('Y-m-d H:i', filemtime($item_path)),
                'extension' => $is_dir ? '' : strtolower(pathinfo($item, PATHINFO_EXTENSION))
            ];
        }

        jsonResponse(true, 'List fetched', ['items' => $result, 'current_path' => $current_path]);
        break;

    case 'create_folder':
        $folder_name = trim($_POST['name'] ?? '');
        // Sanitize
        $folder_name = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $folder_name);

        if (empty($folder_name)) {
            jsonResponse(false, 'Nom de dossier invalide');
        }

        $new_folder_path = $full_path . '/' . $folder_name;

        if (file_exists($new_folder_path)) {
            jsonResponse(false, 'Ce dossier existe déjà');
        }

        if (mkdir($new_folder_path, 0777)) {
            jsonResponse(true, 'Dossier créé');
        } else {
            jsonResponse(false, 'Erreur lors de la création du dossier');
        }
        break;

    case 'upload':
        if (!isset($_FILES['file'])) {
            jsonResponse(false, 'Aucun fichier reçu');
        }

        $file = $_FILES['file'];
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.\s]/', '', $file['name']);
        $target_path = $full_path . '/' . $filename;

        if (file_exists($target_path)) {
            // Append timestamp if exists
            $filename = pathinfo($filename, PATHINFO_FILENAME) . '_' . time() . '.' . pathinfo($filename, PATHINFO_EXTENSION);
            $target_path = $full_path . '/' . $filename;
        }

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            jsonResponse(true, 'Fichier uploadé');
        } else {
            jsonResponse(false, 'Erreur lors de l\'upload');
        }
        break;

    case 'delete':
        $name = $_POST['name'] ?? '';
        // Sanitize path component prevents traversal
        $name = basename($name);

        if (empty($name))
            jsonResponse(false, 'Nom invalide');

        $target = $full_path . '/' . $name;

        if (!file_exists($target))
            jsonResponse(false, 'Fichier introuvable');

        if (is_dir($target)) {
            // Simple recursive delete
            if (deleteDirectory($target)) {
                jsonResponse(true, 'Dossier supprimé');
            } else {
                jsonResponse(false, 'Impossible de supprimer le dossier (peut-être non vide ou permissions)');
            }
        } else {
            if (unlink($target)) {
                jsonResponse(true, 'Fichier supprimé');
            } else {
                jsonResponse(false, 'Erreur suppression fichier');
            }
        }
        break;

    case 'rename':
        $old_name = basename($_POST['old_name'] ?? '');
        $new_name = preg_replace('/[^a-zA-Z0-9_\-\.\s]/', '', $_POST['new_name'] ?? '');

        if (empty($old_name) || empty($new_name))
            jsonResponse(false, 'Noms invalides');

        $old_path = $full_path . '/' . $old_name;
        $new_path = $full_path . '/' . $new_name;

        if (!file_exists($old_path))
            jsonResponse(false, 'Source introuvable');
        if (file_exists($new_path))
            jsonResponse(false, 'Un élément porte déjà ce nom');

        if (rename($old_path, $new_path)) {
            jsonResponse(true, 'Renommé avec succès');
        } else {
            jsonResponse(false, 'Erreur renommage');
        }
        break;

    default:
        jsonResponse(false, 'Action invalide');
}

// Helpers
function formatSize($bytes)
{
    if ($bytes >= 1073741824)
        return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)
        return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)
        return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function deleteDirectory($dir)
{
    if (!file_exists($dir))
        return true;
    if (!is_dir($dir))
        return unlink($dir);

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..')
            continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item))
            return false;
    }
    return rmdir($dir);
}
