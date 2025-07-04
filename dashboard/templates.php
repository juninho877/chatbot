<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/MessageTemplate.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    redirect("../login.php");
}

$database = new Database();
$db = $database->getConnection();
$template = new MessageTemplate($db);

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

$message = '';
$error = '';

// Processar ações
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $template->user_id = $_SESSION['user_id'];
                    $template->name = trim($_POST['name']);
                    $template->type = $_POST['type'];
                    $template->message = trim($_POST['message']);
                    $template->active = isset($_POST['active']) ? 1 : 0;
                    
                    if (empty($template->name)) {
                        $error = "Nome do template é obrigatório.";
                        break;
                    }
                    
                    if (empty($template->message)) {
                        $error = "Mensagem do template é obrigatória.";
                        break;
                    }
                    
                    if ($template->create()) {
                        $message = "Template criado com sucesso!";
                    } else {
                        $error = "Erro ao criar template.";
                    }
                    break;
                    
                case 'edit':
                    $template->id = $_POST['id'];
                    $template->user_id = $_SESSION['user_id'];
                    $template->name = trim($_POST['name']);
                    $template->type = $_POST['type'];
                    $template->message = trim($_POST['message']);
                    $template->active = isset($_POST['active']) ? 1 : 0;
                    
                    if (empty($template->name)) {
                        $error = "Nome do template é obrigatório.";
                        break;
                    }
                    
                    if (empty($template->message)) {
                        $error = "Mensagem do template é obrigatória.";
                        break;
                    }
                    
                    if ($template->update()) {
                        $message = "Template atualizado com sucesso!";
                    } else {
                        $error = "Erro ao atualizar template.";
                    }
                    break;
                    
                case 'delete':
                    $template->id = $_POST['id'];
                    $template->user_id = $_SESSION['user_id'];
                    
                    if ($template->delete()) {
                        $message = "Template removido com sucesso!";
                    } else {
                        $error = "Erro ao remover template.";
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error = "Erro: " . $e->getMessage();
    }
}

// Buscar templates
$templates_stmt = $template->readAll($_SESSION['user_id']);
$templates = $templates_stmt->fetchAll();

// Se está editando um template
$editing_template = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $template->id = $_GET['edit'];
    $template->user_id = $_SESSION['user_id'];
    if ($template->readOne()) {
        $editing_template = $template;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Templates - ClientManager Pro</title>
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
                        <a href="templates.php" class="bg-blue-600 text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
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
                        <?php if ($is_admin): ?>
                        <a href="settings.php" class="text-gray-300 hover:bg-gray-700 hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                            <i class="fas fa-cog mr-3"></i>
                            Configurações
                            <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">Admin</span>
                        </a>
                        <?php endif; ?>
                    </nav>
                </div>
                <div class="flex-shrink-0 flex border-t border-gray-700 p-4">
                    <div class="flex-shrink-0 w-full group block">
                        <div class="flex items-center">
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-200"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                                <?php if ($is_admin): ?>
                                    <span class="text-xs font-medium text-yellow-400">Administrador</span>
                                <?php else: ?>
                                    <span class="text-xs font-medium text-gray-400">Usuário</span>
                                <?php endif; ?>
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
                        <div class="flex justify-between items-center">
                            <h1 class="text-3xl font-bold text-gray-900">Templates de Mensagem</h1>
                            <button onclick="openModal()" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg hover:bg-purple-700 transition duration-150 shadow-md hover:shadow-lg">
                                <i class="fas fa-plus mr-2"></i>
                                Criar Template
                            </button>
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

                        <!-- Templates Predefinidos -->
                        <div class="mt-8 bg-white shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 mb-4">Templates Sugeridos</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-300">
                                        <h4 class="font-semibold text-gray-900 mb-2">Cobrança Amigável</h4>
                                        <p class="text-sm text-gray-600 mb-3">Olá {nome}! Seu pagamento de {valor} vence em {vencimento}. Obrigado!</p>
                                        <button onclick="useTemplate('Cobrança Amigável', 'cobranca', 'Olá {nome}! Seu pagamento de {valor} vence em {vencimento}. Obrigado!')" 
                                                class="text-purple-600 text-sm hover:underline">
                                            Usar este template
                                        </button>
                                    </div>
                                    
                                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-300">
                                        <h4 class="font-semibold text-gray-900 mb-2">Lembrete de Vencimento</h4>
                                        <p class="text-sm text-gray-600 mb-3">Oi {nome}, lembrando que sua mensalidade de {valor} vence hoje ({vencimento}). Pode efetuar o pagamento?</p>
                                        <button onclick="useTemplate('Lembrete de Vencimento', 'lembrete', 'Oi {nome}, lembrando que sua mensalidade de {valor} vence hoje ({vencimento}). Pode efetuar o pagamento?')" 
                                                class="text-purple-600 text-sm hover:underline">
                                            Usar este template
                                        </button>
                                    </div>
                                    
                                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-300">
                                        <h4 class="font-semibold text-gray-900 mb-2">Boas Vindas</h4>
                                        <p class="text-sm text-gray-600 mb-3">Bem-vindo(a) {nome}! Obrigado por escolher nossos serviços. Sua primeira mensalidade é de {valor}.</p>
                                        <button onclick="useTemplate('Boas Vindas', 'boas_vindas', 'Bem-vindo(a) {nome}! Obrigado por escolher nossos serviços. Sua primeira mensalidade é de {valor}.')" 
                                                class="text-purple-600 text-sm hover:underline">
                                            Usar este template
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lista de Templates -->
                        <div class="mt-8 bg-white shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 mb-4">Meus Templates</h3>
                                
                                <?php if (empty($templates)): ?>
                                    <div class="text-center py-12">
                                        <i class="fas fa-file-alt text-gray-300 text-7xl mb-4"></i>
                                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Nenhum template criado</h3>
                                        <p class="text-lg text-gray-500 mb-4">Crie seu primeiro template de mensagem</p>
                                        <button onclick="openModal()" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg hover:bg-purple-700 transition duration-150 shadow-md">
                                            <i class="fas fa-plus mr-2"></i>
                                            Criar Primeiro Template
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                        <?php foreach ($templates as $template_row): ?>
                                        <div class="border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow duration-300">
                                            <div class="flex justify-between items-start mb-3">
                                                <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($template_row['name']); ?></h4>
                                                <div class="flex space-x-2">
                                                    <?php if ($template_row['active']): ?>
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-200 text-green-800">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-200 text-gray-800">Inativo</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <?php
                                                $type_labels = [
                                                    'cobranca' => 'Cobrança',
                                                    'lembrete' => 'Lembrete',
                                                    'boas_vindas' => 'Boas Vindas',
                                                    'custom' => 'Personalizado'
                                                ];
                                                ?>
                                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800">
                                                    <?php echo $type_labels[$template_row['type']] ?? 'Personalizado'; ?>
                                                </span>
                                            </div>
                                            
                                            <p class="text-sm text-gray-600 mb-4 line-clamp-3">
                                                <?php echo htmlspecialchars(substr($template_row['message'], 0, 100)) . (strlen($template_row['message']) > 100 ? '...' : ''); ?>
                                            </p>
                                            
                                            <div class="flex justify-between items-center">
                                                <span class="text-xs text-gray-500">
                                                    <?php echo date('d/m/Y', strtotime($template_row['created_at'])); ?>
                                                </span>
                                                <div class="flex space-x-2">
                                                    <button onclick="editTemplate(<?php echo htmlspecialchars(json_encode($template_row)); ?>)" 
                                                            class="text-blue-600 hover:text-blue-900 p-2 rounded-full hover:bg-gray-200 transition duration-150">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="deleteTemplate(<?php echo $template_row['id']; ?>, '<?php echo htmlspecialchars($template_row['name']); ?>')" 
                                                            class="text-red-600 hover:text-red-900 p-2 rounded-full hover:bg-gray-200 transition duration-150">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para adicionar/editar template -->
    <div id="templateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-6 border max-w-2xl shadow-lg rounded-md bg-white border-t-4 border-purple-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 mb-4" id="modalTitle">Criar Template</h3>
                <form id="templateForm" method="POST">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="templateId">
                    
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Nome do Template *</label>
                            <input type="text" name="name" id="name" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5"
                                   placeholder="Ex: Cobrança Mensal">
                        </div>
                        
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700">Tipo</label>
                            <select name="type" id="type" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5">
                                <option value="cobranca">Cobrança</option>
                                <option value="lembrete">Lembrete</option>
                                <option value="boas_vindas">Boas Vindas</option>
                                <option value="custom">Personalizado</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700">Mensagem *</label>
                            <textarea name="message" id="message" rows="6" required 
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 p-2.5"
                                      placeholder="Digite a mensagem do template..."></textarea>
                            <p class="mt-1 text-xs text-gray-500">
                                Variáveis disponíveis: {nome}, {valor}, {vencimento}
                            </p>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" name="active" id="active" checked 
                                   class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                            <label for="active" class="ml-2 block text-sm text-gray-700">
                                Template ativo
                            </label>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeModal()" class="bg-gray-200 text-gray-700 px-5 py-2.5 rounded-lg hover:bg-gray-300 transition duration-150">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg hover:bg-purple-700 transition duration-150 shadow-md">
                            Salvar Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('templateModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Criar Template';
            document.getElementById('formAction').value = 'add';
            document.getElementById('templateForm').reset();
            document.getElementById('active').checked = true;
        }

        function closeModal() {
            document.getElementById('templateModal').classList.add('hidden');
        }

        function editTemplate(template) {
            document.getElementById('templateModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Editar Template';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('templateId').value = template.id;
            document.getElementById('name').value = template.name;
            document.getElementById('type').value = template.type;
            document.getElementById('message').value = template.message;
            document.getElementById('active').checked = template.active == 1;
        }

        function deleteTemplate(id, name) {
            if (confirm('Tem certeza que deseja remover o template "' + name + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function useTemplate(name, type, message) {
            document.getElementById('templateModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Criar Template';
            document.getElementById('formAction').value = 'add';
            document.getElementById('name').value = name;
            document.getElementById('type').value = type;
            document.getElementById('message').value = message;
            document.getElementById('active').checked = true;
        }

        // Fechar modal ao clicar fora
        document.getElementById('templateModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>