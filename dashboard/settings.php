<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/AppSettings.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    redirect("../login.php");
}

$database = new Database();
$db = $database->getConnection();

// Verificar se é administrador usando role com fallback
$is_admin = false;
if (isset($_SESSION['user_role'])) {
    $is_admin = ($_SESSION['user_role'] === 'admin');
} else {
    // Fallback: verificar no banco de dados se a role não estiver na sessão
    $query = "SELECT role FROM users WHERE id = :user_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_role = $row['role'] ?? 'user';
        $_SESSION['user_role'] = $user_role; // Atualizar sessão
        $is_admin = ($user_role === 'admin');
    }
}

// Verificar se é administrador
if (!$is_admin) {
    // Redirecionar para dashboard com mensagem de erro
    $_SESSION['error_message'] = 'Acesso negado. Apenas administradores podem acessar as configurações do sistema.';
    redirect("index.php");
}

$appSettings = new AppSettings($db);

$message = '';
$error = '';

// Processar ações
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_settings':
                    $updated = 0;
                    
                    // Lista de configurações que podem ser atualizadas
                    $allowed_settings = [
                        'admin_email' => 'email',
                        'site_name' => 'string',
                        'timezone' => 'string',
                        'auto_billing_enabled' => 'boolean',
                        'whatsapp_delay_seconds' => 'number',
                        'max_retry_attempts' => 'number',
                        // Novas configurações de notificação
                        'notify_5_days_before' => 'boolean',
                        'notify_3_days_before' => 'boolean',
                        'notify_2_days_before' => 'boolean',
                        'notify_1_day_before' => 'boolean',
                        'notify_on_due_date' => 'boolean',
                        'notify_1_day_after_due' => 'boolean'
                    ];
                    
                    foreach ($allowed_settings as $key => $type) {
                        $value = null;
                        
                        // Tratamento especial para campos booleanos (checkboxes)
                        if ($type === 'boolean') {
                            // Para checkboxes, verificar se está presente no POST
                            $value = isset($_POST[$key]) && $_POST[$key] === 'on';
                        } else {
                            // Para outros tipos, verificar se está presente no POST
                            if (isset($_POST[$key])) {
                                $value = $_POST[$key];
                            } else {
                                // Se não está presente, pular esta configuração
                                continue;
                            }
                        }
                        
                        // Validações específicas
                        if ($key === 'admin_email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            throw new Exception("Email do administrador inválido");
                        }
                        
                        if ($key === 'whatsapp_delay_seconds' && ($value < 1 || $value > 60)) {
                            throw new Exception("Delay do WhatsApp deve estar entre 1 e 60 segundos");
                        }
                        
                        if ($key === 'max_retry_attempts' && ($value < 1 || $value > 10)) {
                            throw new Exception("Máximo de tentativas deve estar entre 1 e 10");
                        }
                        
                        // Atualizar a configuração
                        if ($appSettings->set($key, $value, null, $type)) {
                            $updated++;
                        }
                    }
                    
                    // Processar upload do favicon
                    if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                        $favicon_file = $_FILES['favicon'];
                        
                        // Validar tipo de arquivo
                        $allowed_types = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/jpeg', 'image/gif'];
                        $file_type = $favicon_file['type'];
                        $file_info = getimagesize($favicon_file['tmp_name']);
                        
                        if (!in_array($file_type, $allowed_types) && !$file_info) {
                            throw new Exception("Tipo de arquivo não suportado para favicon. Use ICO, PNG, JPG ou GIF.");
                        }
                        
                        // Validar tamanho (máximo 1MB)
                        if ($favicon_file['size'] > 1024 * 1024) {
                            throw new Exception("Arquivo muito grande. Máximo 1MB.");
                        }
                        
                        // Criar diretório de uploads se não existir
                        $upload_dir = __DIR__ . '/../public/uploads/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Gerar nome único para o arquivo
                        $file_extension = pathinfo($favicon_file['name'], PATHINFO_EXTENSION);
                        if (empty($file_extension)) {
                            $file_extension = 'ico';
                        }
                        $new_filename = 'favicon_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        $web_path = '/public/uploads/' . $new_filename;
                        
                        // Mover arquivo para diretório de uploads
                        if (move_uploaded_file($favicon_file['tmp_name'], $upload_path)) {
                            // Remover favicon anterior se existir
                            $old_favicon = $appSettings->get('favicon_path');
                            if ($old_favicon && $old_favicon !== '/favicon.ico' && file_exists(__DIR__ . '/..' . $old_favicon)) {
                                unlink(__DIR__ . '/..' . $old_favicon);
                            }
                            
                            // Salvar novo caminho no banco
                            if ($appSettings->set('favicon_path', $web_path, 'Caminho para o favicon do site', 'string')) {
                                $updated++;
                                $message .= " Favicon atualizado com sucesso!";
                            }
                        } else {
                            throw new Exception("Erro ao fazer upload do favicon.");
                        }
                    }
                    
                    if ($updated > 0) {
                        if (empty($message)) {
                            $message = "Configurações atualizadas com sucesso! ($updated alterações)";
                        } else {
                            $message = "Configurações atualizadas com sucesso! ($updated alterações)" . $message;
                        }
                        
                        // Atualizar timezone se foi alterado
                        if (isset($_POST['timezone'])) {
                            date_default_timezone_set($_POST['timezone']);
                        }
                    } else {
                        $error = "Nenhuma configuração foi alterada.";
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error = "Erro: " . $e->getMessage();
    }
}

// Buscar todas as configurações
$all_settings = $appSettings->getAll();

// Lista de timezones comuns
$timezones = [
    'America/Sao_Paulo' => 'São Paulo (UTC-3)',
    'America/Rio_Branco' => 'Acre (UTC-5)',
    'America/Manaus' => 'Amazonas (UTC-4)',
    'America/Fortaleza' => 'Fortaleza (UTC-3)',
    'America/Recife' => 'Recife (UTC-3)',
    'America/Bahia' => 'Salvador (UTC-3)',
    'UTC' => 'UTC (UTC+0)',
    'America/New_York' => 'New York (UTC-5)',
    'Europe/London' => 'London (UTC+0)',
    'Europe/Paris' => 'Paris (UTC+1)'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Configurações</title>
    <link rel="icon" href="<?php echo FAVICON_PATH; ?>">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="flex h-screen bg-gray-100">
        <div class="hidden md:flex md:w-64 md:flex-col">
            <div class="flex flex-col flex-grow pt-5 overflow-y-auto bg-gray-800 text-gray-100 border-r border-gray-700">
                <div class="flex items-center flex-shrink-0 px-4">
                    <h1 class="text-2xl font-extrabold text-white"><?php echo SITE_NAME; ?></h1>
                </div>
                <div class="mt-5 flex-grow flex flex-col">
                    <nav class="flex-1 px-2 space-y-1">
                        <a href="index.php" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                            <i class="fas fa-home mr-3"></i>
                            Dashboard
                        </a>
                        <a href="clients.php" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                            <i class="fas fa-users mr-3"></i>
                            Clientes
                        </a>
                        <a href="messages.php" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                            <i class="fab fa-whatsapp mr-3"></i>
                            Mensagens
                        </a>
                        <a href="templates.php" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                            <i class="fas fa-template mr-3"></i>
                            Templates
                        </a>
                        <a href="whatsapp.php" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                            <i class="fas fa-qrcode mr-3"></i>
                            WhatsApp
                        </a>
                        <a href="reports.php" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                            <i class="fas fa-chart-bar mr-3"></i>
                            Relatórios
                        </a>
                        <a href="settings.php" class="bg-blue-600 text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                            <i class="fas fa-cog mr-3"></i>
                            Configurações
                            <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">Admin</span>
                        </a>
                    </nav>
                </div>
                <div class="flex-shrink-0 flex border-t border-gray-700 p-4">
                    <div class="flex-shrink-0 w-full group block">
                        <div class="flex items-center">
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-200"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                                <span class="text-xs font-medium text-yellow-400">Administrador</span>
                                <a href="../logout.php" class="text-xs font-medium text-gray-400 hover:text-white block">Sair</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="flex flex-col w-0 flex-1 overflow-hidden">
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <div class="flex items-center justify-between">
                            <h1 class="text-3xl font-bold text-gray-900">Configurações do Sistema</h1>
                            <div class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium">
                                <i class="fas fa-shield-alt mr-1"></i>
                                Acesso Administrativo
                            </div>
                        </div>
                    </div>
                    
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Mensagens de feedback -->
                        <?php if ($message): ?>
                            <div class="mt-4 bg-green-100 border-green-400 text-green-800 p-4 rounded-lg shadow-sm">
                                <div class="flex">
                                    <i class="fas fa-check-circle mr-3 mt-0.5"></i>
                                    <span><?php echo htmlspecialchars($message); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="mt-4 bg-red-100 border-red-400 text-red-800 p-4 rounded-lg shadow-sm">
                                <div class="flex">
                                    <i class="fas fa-exclamation-circle mr-3 mt-0.5"></i>
                                    <span><?php echo htmlspecialchars($error); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Alerta de Segurança -->
                        <div class="mt-8 bg-yellow-100 border-l-4 border-yellow-500 p-4 rounded-lg shadow-sm">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-800">
                                        <strong>Área Restrita:</strong> Esta página contém configurações críticas do sistema. 
                                        Apenas administradores autorizados devem fazer alterações aqui.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Formulário de Configurações -->
                        <div class="mt-8 bg-white shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 mb-6">Configurações Gerais</h3>
                                
                                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                                    <input type="hidden" name="action" value="update_settings">
                                    
                                    <!-- Configurações Básicas -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="site_name" class="block text-sm font-medium text-gray-700">Nome do Site</label>
                                            <input type="text" name="site_name" id="site_name" 
                                                   value="<?php echo htmlspecialchars($all_settings['site_name']['value'] ?? ''); ?>"
                                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                                            <p class="mt-1 text-xs text-gray-500">Nome exibido no sistema e nos emails</p>
                                        </div>
                                        
                                        <div>
                                            <label for="admin_email" class="block text-sm font-medium text-gray-700">Email do Administrador</label>
                                            <input type="email" name="admin_email" id="admin_email" required
                                                   value="<?php echo htmlspecialchars($all_settings['admin_email']['value'] ?? ''); ?>"
                                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                                            <p class="mt-1 text-xs text-gray-500">Email para receber relatórios e notificações</p>
                                        </div>
                                        
                                        <div>
                                            <label for="timezone" class="block text-sm font-medium text-gray-700">Fuso Horário</label>
                                            <select name="timezone" id="timezone" 
                                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                                                <?php foreach ($timezones as $tz => $label): ?>
                                                    <option value="<?php echo $tz; ?>" 
                                                            <?php echo ($all_settings['timezone']['value'] ?? '') === $tz ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label for="favicon" class="block text-sm font-medium text-gray-700">Favicon do Site</label>
                                            <input type="file" name="favicon" id="favicon" accept=".ico,.png,.jpg,.jpeg,.gif"
                                                   class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                            <p class="mt-1 text-xs text-gray-500">Formatos: ICO, PNG, JPG, GIF (máx. 1MB)</p>
                                            <?php if (!empty($all_settings['favicon_path']['value'])): ?>
                                                <div class="mt-2 flex items-center">
                                                    <img src="<?php echo htmlspecialchars($all_settings['favicon_path']['value']); ?>" 
                                                         alt="Favicon atual" class="w-4 h-4 mr-2">
                                                    <span class="text-xs text-gray-600">Favicon atual</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div>
                                            <label for="whatsapp_delay_seconds" class="block text-sm font-medium text-gray-700">Delay entre Mensagens (segundos)</label>
                                            <input type="number" name="whatsapp_delay_seconds" id="whatsapp_delay_seconds" 
                                                   min="1" max="60" 
                                                   value="<?php echo htmlspecialchars($all_settings['whatsapp_delay_seconds']['value'] ?? '2'); ?>"
                                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                                            <p class="mt-1 text-xs text-gray-500">Tempo de espera entre envios para evitar spam</p>
                                        </div>
                                        
                                        <div>
                                            <label for="max_retry_attempts" class="block text-sm font-medium text-gray-700">Máximo de Tentativas</label>
                                            <input type="number" name="max_retry_attempts" id="max_retry_attempts" 
                                                   min="1" max="10" 
                                                   value="<?php echo htmlspecialchars($all_settings['max_retry_attempts']['value'] ?? '3'); ?>"
                                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                                            <p class="mt-1 text-xs text-gray-500">Tentativas de reenvio para mensagens falhadas</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Configurações de Funcionalidades -->
                                    <div class="border-t pt-6">
                                        <h4 class="text-lg font-medium text-gray-900 mb-4">Funcionalidades</h4>
                                        <div class="space-y-4">
                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" name="auto_billing_enabled" id="auto_billing_enabled" 
                                                           <?php echo ($all_settings['auto_billing_enabled']['value'] ?? false) ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="auto_billing_enabled" class="font-medium text-gray-700">
                                                        Cobrança Automática Ativa
                                                    </label>
                                                    <p class="text-gray-500">
                                                        Quando ativada, o sistema enviará mensagens automáticas de cobrança via cron job para clientes com vencimento próximo ou em atraso.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Configurações de Períodos de Notificação -->
                                    <div class="border-t pt-6">
                                        <h4 class="text-lg font-medium text-gray-900 mb-4">Períodos de Notificação</h4>
                                        <p class="text-sm text-gray-600 mb-4">
                                            Configure quando o sistema deve enviar avisos automáticos para seus clientes:
                                        </p>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" name="notify_5_days_before" id="notify_5_days_before" 
                                                           <?php echo ($all_settings['notify_5_days_before']['value'] ?? false) ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="notify_5_days_before" class="font-medium text-gray-700">
                                                        Enviar aviso 5 dias antes
                                                    </label>
                                                    <p class="text-gray-500">Lembrete antecipado para o cliente se organizar</p>
                                                </div>
                                            </div>

                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" name="notify_3_days_before" id="notify_3_days_before" 
                                                           <?php echo ($all_settings['notify_3_days_before']['value'] ?? true) ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="notify_3_days_before" class="font-medium text-gray-700">
                                                        Enviar aviso 3 dias antes
                                                    </label>
                                                    <p class="text-gray-500">Lembrete padrão recomendado</p>
                                                </div>
                                            </div>

                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" name="notify_2_days_before" id="notify_2_days_before" 
                                                           <?php echo ($all_settings['notify_2_days_before']['value'] ?? false) ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="notify_2_days_before" class="font-medium text-gray-700">
                                                        Enviar aviso 2 dias antes
                                                    </label>
                                                    <p class="text-gray-500">Lembrete mais próximo do vencimento</p>
                                                </div>
                                            </div>

                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" name="notify_1_day_before" id="notify_1_day_before" 
                                                           <?php echo ($all_settings['notify_1_day_before']['value'] ?? false) ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="notify_1_day_before" class="font-medium text-gray-700">
                                                        Enviar aviso 1 dia antes
                                                    </label>
                                                    <p class="text-gray-500">Último lembrete antes do vencimento</p>
                                                </div>
                                            </div>

                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" name="notify_on_due_date" id="notify_on_due_date" 
                                                           <?php echo ($all_settings['notify_on_due_date']['value'] ?? true) ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="notify_on_due_date" class="font-medium text-gray-700">
                                                        Enviar aviso no dia do vencimento
                                                    </label>
                                                    <p class="text-gray-500">Lembrete no dia que vence</p>
                                                </div>
                                            </div>

                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" name="notify_1_day_after_due" id="notify_1_day_after_due" 
                                                           <?php echo ($all_settings['notify_1_day_after_due']['value'] ?? false) ? 'checked' : ''; ?>
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="notify_1_day_after_due" class="font-medium text-gray-700">
                                                        Enviar aviso 1 dia após vencimento
                                                    </label>
                                                    <p class="text-gray-500">Cobrança para pagamentos em atraso</p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                                            <p class="text-sm text-blue-800">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                <strong>Dica:</strong> Recomendamos ativar pelo menos "3 dias antes" e "no dia do vencimento" para uma cobrança eficiente. 
                                                Evite ativar muitos períodos para não incomodar os clientes.
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-end space-x-3">
                                        <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                                            <i class="fas fa-save mr-2"></i>
                                            Salvar Configurações
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Informações do Sistema -->
                        <div class="mt-8 bg-white shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 mb-4">Informações do Sistema</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <strong>Última Execução do Cron:</strong><br>
                                        <span class="text-gray-600"><?php echo htmlspecialchars($appSettings->getCronLastRun()); ?></span>
                                    </div>
                                    
                                    <div>
                                        <strong>Timezone Atual:</strong><br>
                                        <span class="text-gray-600"><?php echo date_default_timezone_get(); ?></span>
                                    </div>
                                    
                                    <div>
                                        <strong>Data/Hora Atual:</strong><br>
                                        <span class="text-gray-600"><?php echo date('d/m/Y H:i:s'); ?></span>
                                    </div>
                                    
                                    <div>
                                        <strong>Versão do PHP:</strong><br>
                                        <span class="text-gray-600"><?php echo phpversion(); ?></span>
                                    </div>
                                    
                                    <div>
                                        <strong>Usuário Logado:</strong><br>
                                        <span class="text-gray-600"><?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
                                    </div>
                                    
                                    <div>
                                        <strong>Nível de Acesso:</strong><br>
                                        <span class="text-red-600 font-medium">Administrador</span>
                                    </div>
                                    
                                    <div>
                                        <strong>Cobrança Automática:</strong><br>
                                        <span class="<?php echo $appSettings->isAutoBillingEnabled() ? 'text-green-600' : 'text-red-600'; ?> font-medium">
                                            <?php echo $appSettings->isAutoBillingEnabled() ? 'Ativa' : 'Inativa'; ?>
                                        </span>
                                    </div>
                                    
                                    <div>
                                        <strong>Períodos Ativos:</strong><br>
                                        <span class="text-gray-600">
                                            <?php 
                                            $active_periods = [];
                                            if ($appSettings->isNotify5DaysBeforeEnabled()) $active_periods[] = '5 dias antes';
                                            if ($appSettings->isNotify3DaysBeforeEnabled()) $active_periods[] = '3 dias antes';
                                            if ($appSettings->isNotify2DaysBeforeEnabled()) $active_periods[] = '2 dias antes';
                                            if ($appSettings->isNotify1DayBeforeEnabled()) $active_periods[] = '1 dia antes';
                                            if ($appSettings->isNotifyOnDueDateEnabled()) $active_periods[] = 'No vencimento';
                                            if ($appSettings->isNotify1DayAfterDueEnabled()) $active_periods[] = '1 dia após';
                                            
                                            echo !empty($active_periods) ? implode(', ', $active_periods) : 'Nenhum período ativo';
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Instruções de Automação -->
                        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
                            <h4 class="text-lg font-medium text-blue-900 mb-3">
                                <i class="fas fa-info-circle mr-2"></i>
                                Sobre os Períodos de Notificação
                            </h4>
                            <div class="text-sm text-blue-800 space-y-2">
                                <p>
                                    <strong>Como funciona:</strong> O sistema verificará diariamente (via cron job) quais clientes se enquadram nos períodos configurados e enviará as mensagens automaticamente.
                                </p>
                                <ul class="list-disc list-inside ml-4 space-y-1">
                                    <li><strong>5 dias antes:</strong> Aviso antecipado para o cliente se organizar</li>
                                    <li><strong>3 dias antes:</strong> Lembrete padrão (recomendado)</li>
                                    <li><strong>2 dias antes:</strong> Lembrete mais próximo do vencimento</li>
                                    <li><strong>1 dia antes:</strong> Último aviso antes do vencimento</li>
                                    <li><strong>No vencimento:</strong> Lembrete no dia que vence</li>
                                    <li><strong>1 dia após:</strong> Cobrança para pagamentos em atraso</li>
                                </ul>
                                <p class="mt-3">
                                    <strong>Importante:</strong> Para cada período ativo, você deve criar templates específicos na seção "Templates de Mensagem" com os tipos correspondentes.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Preview do favicon antes do upload
        document.getElementById('favicon').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Criar preview se não existir
                    let preview = document.getElementById('favicon-preview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.id = 'favicon-preview';
                        preview.className = 'mt-2 flex items-center';
                        document.getElementById('favicon').parentNode.appendChild(preview);
                    }
                    
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Preview do favicon" class="w-4 h-4 mr-2">
                        <span class="text-xs text-gray-600">Preview do novo favicon</span>
                    `;
                };
                reader.readAsDataURL(file);
            }
        });

        // Validação do formulário
        document.querySelector('form').addEventListener('submit', function(e) {
            const siteName = document.getElementById('site_name').value.trim();
            const adminEmail = document.getElementById('admin_email').value.trim();
            
            if (!siteName) {
                alert('Nome do site é obrigatório');
                e.preventDefault();
                return;
            }
            
            if (!adminEmail) {
                alert('Email do administrador é obrigatório');
                e.preventDefault();
                return;
            }
            
            // Validar email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(adminEmail)) {
                alert('Email do administrador inválido');
                e.preventDefault();
                return;
            }
        });

        // Feedback visual para os checkboxes
        document.querySelectorAll('input[type="checkbox"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const label = document.querySelector('label[for="' + this.id + '"]');
                if (this.checked) {
                    label.classList.add('text-green-700');
                    label.classList.remove('text-gray-700');
                } else {
                    label.classList.add('text-gray-700');
                    label.classList.remove('text-green-700');
                }
            });
        });
    </script>
</body>
</html>