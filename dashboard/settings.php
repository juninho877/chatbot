<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/AppSettings.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    redirect("../login.php");
}

// Verificar se é administrador (apenas admin@clientmanager.com pode acessar)
if ($_SESSION['user_email'] !== 'admin@clientmanager.com') {
    // Redirecionar para dashboard com mensagem de erro
    $_SESSION['error_message'] = 'Acesso negado. Apenas administradores podem acessar as configurações do sistema.';
    redirect("index.php");
}

$database = new Database();
$db = $database->getConnection();
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
                        'email_notifications' => 'boolean',
                        'whatsapp_delay_seconds' => 'number',
                        'max_retry_attempts' => 'number',
                        'backup_enabled' => 'boolean'
                    ];
                    
                    foreach ($allowed_settings as $key => $type) {
                        if (isset($_POST[$key])) {
                            $value = $_POST[$key];
                            
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
                            
                            // Converter valores booleanos
                            if ($type === 'boolean') {
                                $value = isset($_POST[$key]) && $_POST[$key] === 'on';
                            }
                            
                            if ($appSettings->set($key, $value, null, $type)) {
                                $updated++;
                            }
                        }
                    }
                    
                    if ($updated > 0) {
                        $message = "Configurações atualizadas com sucesso! ($updated alterações)";
                        
                        // Atualizar timezone se foi alterado
                        if (isset($_POST['timezone'])) {
                            date_default_timezone_set($_POST['timezone']);
                        }
                    } else {
                        $error = "Nenhuma configuração foi alterada.";
                    }
                    break;
                    
                case 'test_email':
                    $admin_email = $appSettings->getAdminEmail();
                    
                    if (empty($admin_email)) {
                        throw new Exception("Email do administrador não configurado");
                    }
                    
                    $subject = "Teste de Email - " . $appSettings->getSiteName();
                    $message_body = "
                    <html>
                    <body>
                        <h2>Teste de Email</h2>
                        <p>Este é um email de teste do sistema ClientManager Pro.</p>
                        <p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>
                        <p><strong>Servidor:</strong> " . $_SERVER['SERVER_NAME'] . "</p>
                        <p>Se você recebeu este email, a configuração está funcionando corretamente!</p>
                    </body>
                    </html>";
                    
                    $headers = [
                        'MIME-Version: 1.0',
                        'Content-type: text/html; charset=UTF-8',
                        'From: ' . $appSettings->getSiteName() . ' <noreply@clientmanager.com>',
                        'Reply-To: ' . $admin_email
                    ];
                    
                    if (mail($admin_email, $subject, $message_body, implode("\r\n", $headers))) {
                        $message = "Email de teste enviado com sucesso para: " . $admin_email;
                    } else {
                        $error = "Falha ao enviar email de teste. Verifique a configuração do servidor.";
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
    <title>Configurações - ClientManager Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="flex h-screen bg-gray-100">
        <div class="hidden md:flex md:w-64 md:flex-col">
            <div class="flex flex-col flex-grow pt-5 overflow-y-auto bg-gray-800 text-gray-100 border-r border-gray-700">
                <div class="flex items-center flex-shrink-0 px-4">
                    <h1 class="text-2xl font-extrabold text-white">ClientManager Pro</h1>
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
                                
                                <form method="POST" class="space-y-6">
                                    <input type="hidden" name="action" value="update_settings">
                                    
                                    <!-- Configurações Básicas -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="site_name" class="block text-sm font-medium text-gray-700">Nome do Site</label>
                                            <input type="text" name="site_name" id="site_name" 
                                                   value="<?php echo htmlspecialchars($all_settings['site_name']['value'] ?? ''); ?>"
                                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                                            <p class="mt-1 text-xs text-gray-500">Nome exibido nos emails e relatórios</p>
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
                                            <div class="flex items-center">
                                                <input type="checkbox" name="auto_billing_enabled" id="auto_billing_enabled" 
                                                       <?php echo ($all_settings['auto_billing_enabled']['value'] ?? false) ? 'checked' : ''; ?>
                                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label for="auto_billing_enabled" class="ml-2 block text-sm text-gray-700">
                                                    Cobrança Automática Ativa
                                                </label>
                                            </div>
                                            
                                            <div class="flex items-center">
                                                <input type="checkbox" name="email_notifications" id="email_notifications" 
                                                       <?php echo ($all_settings['email_notifications']['value'] ?? false) ? 'checked' : ''; ?>
                                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label for="email_notifications" class="ml-2 block text-sm text-gray-700">
                                                    Notificações por Email
                                                </label>
                                            </div>
                                            
                                            <div class="flex items-center">
                                                <input type="checkbox" name="backup_enabled" id="backup_enabled" 
                                                       <?php echo ($all_settings['backup_enabled']['value'] ?? false) ? 'checked' : ''; ?>
                                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label for="backup_enabled" class="ml-2 block text-sm text-gray-700">
                                                    Backup Automático
                                                </label>
                                            </div>
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

                        <!-- Teste de Email -->
                        <div class="mt-8 bg-white shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 mb-4">Teste de Email</h3>
                                <p class="text-gray-600 mb-4">
                                    Envie um email de teste para verificar se as configurações estão funcionando corretamente.
                                </p>
                                
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="test_email">
                                    <button type="submit" class="bg-green-600 text-white px-6 py-2.5 rounded-lg hover:bg-green-700 transition duration-150 shadow-md">
                                        <i class="fas fa-paper-plane mr-2"></i>
                                        Enviar Email de Teste
                                    </button>
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
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>