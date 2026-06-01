<?php
require_once 'config.php';

$message = '';
$status_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_upload'])) {
    $webhook_url = getenv('N8N_WEBHOOK_URL');
    $webhook_user = getenv('N8N_WEBHOOK_USER');
    $webhook_pass = getenv('N8N_WEBHOOK_PASS');

    if (!$webhook_url) {
        $message = "Erro: URL do Webhook não configurada no .env";
        $status_type = 'erro';
    } else {
        $file = $_FILES['file_upload'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $file_path = $file['tmp_name'];
            $file_name = $file['name'];
            $file_type = $file['type'];

            // Preparar o cURL para enviar ao n8n
            $ch = curl_init();
            
            // Criar o objeto CURLFile
            $cfile = new CURLFile($file_path, $file_type, $file_name);
            $data = ['file' => $cfile];

            curl_setopt($ch, CURLOPT_URL, $webhook_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            // Autenticação Básica se configurada
            if ($webhook_user && $webhook_pass) {
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_USERPWD, "$webhook_user:$webhook_pass");
            }

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $message = "Erro na conexão com o Webhook: " . $error;
                $status_type = 'erro';
            } elseif ($http_code >= 200 && $http_code < 300) {
                $message = "Sucesso: Arquivo enviado e processado pelo n8n.";
                $status_type = 'sucesso';
            } else {
                $message = "Erro no n8n (Código $http_code): " . $response;
                $status_type = 'erro';
            }
        } else {
            $message = "Erro no upload do arquivo.";
            $status_type = 'erro';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Dados - Carvist</title>
    <link rel="stylesheet" href="includes/app-shell.css">
    <style>
        :root {
            --bg-color: #f4f7f6;
            --text-color: #333;
            --card-bg: #fff;
            --border-color: #ddd;
            --thead-bg: #2c3e50;
            --thead-text: #fff;
            --row-even: #f9f9f9;
            --primary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --font-size: 12px;
            --row-selected: #e3f2fd;
        }

        [data-theme="dark"] {
            --bg-color: #1a1a1a;
            --text-color: #e0e0e0;
            --card-bg: #2d2d2d;
            --border-color: #444;
            --thead-bg: #000;
            --thead-text: #fff;
            --row-even: #333;
            --primary-color: #2980b9;
            --row-selected: #1e3a5f;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            font-size: var(--font-size);
            margin: 0;
            padding: 0;
            transition: background 0.3s, color 0.3s;
        }

        .import-card {
            max-width: 600px;
            margin-bottom: 20px;
            padding: 20px;
            background: var(--card-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .import-header {
            margin-bottom: 20px;
        }

        .upload-area {
            border: 2px dashed var(--border-color);
            padding: 40px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .upload-area:hover {
            border-color: var(--primary-color);
        }

        .file-input {
            display: none;
        }

        .btn-import {
            display: block;
            width: 100%;
            padding: 12px;
            background: var(--success-color);
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
        }

        .btn-import:hover {
            opacity: 0.9;
        }

        .banner {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: bold;
        }

        .error-banner { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .success-banner { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        .tarja-animada {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 10px;
            text-transform: uppercase;
        }
        .tarja-erro-small { background: #e74c3c; color: #fff; }
        .tarja-sucesso-small { background: #27ae60; color: #fff; }

        .file-info {
            margin-top: 15px;
            font-size: 14px;
            color: var(--primary-color);
            font-weight: bold;
        }
    </style>
</head>
<body>
<?php
$carvist_nav_active = 'importar';
$carvist_container_wide = true;
require __DIR__ . '/includes/header.php';
?>

<div class="import-card">
    <div class="import-header">
        <h2>Importar Planilha / CSV</h2>
        <p>Selecione um arquivo Excel ou CSV para enviar ao DB</p>
    </div>

    <?php if ($message): ?>
        <div class="banner <?php echo $status_type === 'sucesso' ? 'success-banner' : 'error-banner'; ?>">
            <span class="tarja-animada <?php echo $status_type === 'sucesso' ? 'tarja-sucesso-small' : 'tarja-erro-small'; ?>">
                <?php echo strtoupper($status_type); ?>
            </span>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="importar.php" method="POST" enctype="multipart/form-data" id="importForm">
        <div class="upload-area" onclick="document.getElementById('file_upload').click()">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/6/60/Microsoft_Office_Excel_%282025%E2%80%93present%29.svg/3840px-Microsoft_Office_Excel_%282025%E2%80%93present%29.svg.png" alt="Excel Icon" style="width: 100px; margin-bottom: 10px; opacity: 0.9;">
            <p id="file-label">Clique para selecionar ou arraste o arquivo aqui</p>
            <div id="file-name" class="file-info" style="display: none;"></div>
            <input type="file" name="file_upload" id="file_upload" class="file-input" accept=".csv, .xlsx, .xls" required onchange="updateFileName()">
        </div>

        <button type="submit" class="btn-import" id="btnSubmit">Enviar para Processamento</button>
    </form>
</div>

<script>
    function updateFileName() {
        const input = document.getElementById('file_upload');
        const label = document.getElementById('file-label');
        const fileNameDiv = document.getElementById('file-name');
        
        if (input.files.length > 0) {
            label.style.display = 'none';
            fileNameDiv.textContent = "Arquivo selecionado: " + input.files[0].name;
            fileNameDiv.style.display = 'block';
        }
    }

    // Tema Dark/Light (reutilizando lógica do index.php)
    const themeToggle = document.getElementById('themeToggle');
    const currentTheme = localStorage.getItem('theme') || 'light';
    if (currentTheme === 'dark') document.body.setAttribute('data-theme', 'dark');

    themeToggle.addEventListener('click', () => {
        let theme = document.body.getAttribute('data-theme');
        if (theme === 'dark') {
            document.body.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
        } else {
            document.body.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
        }
    });

    document.getElementById('importForm').addEventListener('submit', function() {
        const btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.textContent = 'Enviando...';
        document.getElementById('loadingOverlay').style.display = 'flex';
    });
</script>

</div> <!-- Fecha o container aberto no header.php -->
</body>
</html>
