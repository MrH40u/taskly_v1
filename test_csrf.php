<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Protection CSRF - Taskly</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #0f172a;
            color: #f1f5f9;
        }
        .test-section {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .test-section h2 {
            color: #6366f1;
            margin-top: 0;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .status.success {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
        }
        .status.error {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
        }
        .status.pending {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
        }
        button {
            background: #6366f1;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            margin-right: 10px;
        }
        button:hover {
            background: #4f46e5;
        }
        .result {
            margin-top: 15px;
            padding: 15px;
            background: #0f172a;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
        }
        .info {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <h1>🔒 Test de Protection CSRF - Taskly v1</h1>
    
    <div class="info">
        <strong>ℹ️ Information :</strong> Cette page permet de tester la protection CSRF implémentée dans Taskly.
        Les tests simulent des requêtes avec et sans token CSRF valide.
    </div>

    <!-- Test 1: Vérification du token dans la session -->
    <div class="test-section">
        <h2>Test 1: Génération du Token CSRF</h2>
        <p>Vérifie si un token CSRF est généré et stocké en session.</p>
        <button onclick="testTokenGeneration()">Exécuter le test</button>
        <div id="test1-result"></div>
    </div>

    <!-- Test 2: Validation avec token valide -->
    <div class="test-section">
        <h2>Test 2: Requête avec Token Valide</h2>
        <p>Simule une requête AJAX avec un token CSRF valide.</p>
        <button onclick="testValidToken()">Exécuter le test</button>
        <div id="test2-result"></div>
    </div>

    <!-- Test 3: Validation avec token invalide -->
    <div class="test-section">
        <h2>Test 3: Requête avec Token Invalide</h2>
        <p>Simule une requête AJAX avec un token CSRF invalide (devrait être rejetée).</p>
        <button onclick="testInvalidToken()">Exécuter le test</button>
        <div id="test3-result"></div>
    </div>

    <!-- Test 4: Requête sans token -->
    <div class="test-section">
        <h2>Test 4: Requête sans Token</h2>
        <p>Simule une requête AJAX sans token CSRF (devrait être rejetée).</p>
        <button onclick="testNoToken()">Exécuter le test</button>
        <div id="test4-result"></div>
    </div>

    <script>
        function showResult(elementId, status, message, details = '') {
            const element = document.getElementById(elementId);
            const statusClass = status === 'success' ? 'success' : 'error';
            element.innerHTML = `
                <div style="margin-top: 15px;">
                    <span class="status ${statusClass}">${status === 'success' ? '✅ SUCCÈS' : '❌ ÉCHEC'}</span>
                    <p style="margin-top: 10px;">${message}</p>
                    ${details ? `<div class="result">${details}</div>` : ''}
                </div>
            `;
        }

        function showPending(elementId) {
            const element = document.getElementById(elementId);
            element.innerHTML = `
                <div style="margin-top: 15px;">
                    <span class="status pending">⏳ EN COURS...</span>
                </div>
            `;
        }

        function testTokenGeneration() {
            showPending('test1-result');
            
            // Vérifier si un token existe dans le DOM
            const tokenInput = document.querySelector('input[name="csrf_token"]');
            
            if (tokenInput && tokenInput.value) {
                showResult(
                    'test1-result',
                    'success',
                    'Token CSRF trouvé et généré avec succès !',
                    `Token: ${tokenInput.value.substring(0, 20)}... (${tokenInput.value.length} caractères)`
                );
            } else {
                showResult(
                    'test1-result',
                    'error',
                    'Aucun token CSRF trouvé dans le DOM.',
                    'Assurez-vous que csrfField() est appelé dans un formulaire.'
                );
            }
        }

        function testValidToken() {
            showPending('test2-result');
            
            const tokenInput = document.querySelector('input[name="csrf_token"]');
            if (!tokenInput) {
                showResult('test2-result', 'error', 'Impossible de récupérer le token CSRF.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'get_task');
            formData.append('task_id', '1');
            formData.append('csrf_token', tokenInput.value);

            fetch('../pages/tasks.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success || data.message !== 'Token de sécurité invalide') {
                    showResult(
                        'test2-result',
                        'success',
                        'Requête acceptée avec token valide !',
                        JSON.stringify(data, null, 2)
                    );
                } else {
                    showResult(
                        'test2-result',
                        'error',
                        'Requête rejetée malgré un token valide.',
                        JSON.stringify(data, null, 2)
                    );
                }
            })
            .catch(err => {
                showResult('test2-result', 'error', 'Erreur réseau', err.message);
            });
        }

        function testInvalidToken() {
            showPending('test3-result');
            
            const formData = new FormData();
            formData.append('action', 'get_task');
            formData.append('task_id', '1');
            formData.append('csrf_token', 'INVALID_TOKEN_12345');

            fetch('../pages/tasks.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.message === 'Token de sécurité invalide') {
                    showResult(
                        'test3-result',
                        'success',
                        'Requête correctement rejetée avec token invalide !',
                        JSON.stringify(data, null, 2)
                    );
                } else {
                    showResult(
                        'test3-result',
                        'error',
                        'ALERTE : Requête acceptée avec token invalide !',
                        JSON.stringify(data, null, 2)
                    );
                }
            })
            .catch(err => {
                showResult('test3-result', 'error', 'Erreur réseau', err.message);
            });
        }

        function testNoToken() {
            showPending('test4-result');
            
            const formData = new FormData();
            formData.append('action', 'get_task');
            formData.append('task_id', '1');
            // Pas de token CSRF

            fetch('../pages/tasks.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.message === 'Token de sécurité invalide') {
                    showResult(
                        'test4-result',
                        'success',
                        'Requête correctement rejetée sans token !',
                        JSON.stringify(data, null, 2)
                    );
                } else {
                    showResult(
                        'test4-result',
                        'error',
                        'ALERTE : Requête acceptée sans token !',
                        JSON.stringify(data, null, 2)
                    );
                }
            })
            .catch(err => {
                showResult('test4-result', 'error', 'Erreur réseau', err.message);
            });
        }
    </script>

    <?php
    // Inclure les fonctions CSRF pour générer un token de test
    require_once '../includes/functions.php';
    require_once '../includes/csrf.php';
    
    // Générer un token pour les tests
    echo csrfField();
    ?>
</body>
</html>
