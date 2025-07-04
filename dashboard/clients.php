<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Client.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    redirect("../login.php");
}

$database = new Database();
$db = $database->getConnection();
$client = new Client($db);

$message = '';
$error = '';

// Processar ações
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $client->user_id = $_SESSION['user_id'];
                    $client->name = trim($_POST['name']);
                    $client->email = trim($_POST['email']);
                    $client->phone = trim($_POST['phone']);
                    $client->document = trim($_POST['document']);
                    $client->address = trim($_POST['address']);
                    $client->status = $_POST['status'];
                    $client->notes = trim($_POST['notes']);
                    $client->subscription_amount = !empty($_POST['subscription_amount']) ? floatval($_POST['subscription_amount']) : null;
                    $client->due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
                    $client->last_payment_date = !empty($_POST['last_payment_date']) ? $_POST['last_payment_date'] : null;
                    $client->next_payment_date = !empty($_POST['next_payment_date']) ? $_POST['next_payment_date'] : null;
                    
                    // Validar dados
                    $validation_errors = $client->validate();
                    if (!empty($validation_errors)) {
                        $error = implode(', ', $validation_errors);
                        break;
                    }
                    
                    if ($client->create()) {
                        $message = "Cliente adicionado com sucesso!";
                    } else {
                        $error = "Erro ao adicionar cliente.";
                    }
                    break;
                    
                case 'edit':
                    $client->id = $_POST['id'];
                    $client->user_id = $_SESSION['user_id'];
                    $client->name = trim($_POST['name']);
                    $client->email = trim($_POST['email']);
                    $client->phone = trim($_POST['phone']);
                    $client->document = trim($_POST['document']);
                    $client->address = trim($_POST['address']);
                    $client->status = $_POST['status'];
                    $client->notes = trim($_POST['notes']);
                    $client->subscription_amount = !empty($_POST['subscription_amount']) ? floatval($_POST['subscription_amount']) : null;
                    $client->due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
                    $client->last_payment_date = !empty($_POST['last_payment_date']) ? $_POST['last_payment_date'] : null;
                    $client->next_payment_date = !empty($_POST['next_payment_date']) ? $_POST['next_payment_date'] : null;
                    
                    // Validar dados
                    $validation_errors = $client->validate();
                    if (!empty($validation_errors)) {
                        $error = implode(', ', $validation_errors);
                        break;
                    }
                    
                    if ($client->update()) {
                        $message = "Cliente atualizado com sucesso!";
                    } else {
                        $error = "Erro ao atualizar cliente.";
                    }
                    break;
                    
                case 'delete':
                    $client->id = $_POST['id'];
                    $client->user_id = $_SESSION['user_id'];
                    
                    if ($client->delete()) {
                        $message = "Cliente removido com sucesso!";
                    } else {
                        $error = "Erro ao remover cliente.";
                    }
                    break;
                    
                case 'mark_payment':
                    $client->id = $_POST['id'];
                    $client->user_id = $_SESSION['user_id'];
                    
                    if ($client->readOne()) {
                        if ($client->markPaymentReceived()) {
                            $message = "Pagamento marcado como recebido!";
                        } else {
                            $error = "Erro ao marcar pagamento.";
                        }
                    } else {
                        $error = "Cliente não encontrado.";
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error = "Erro: " . $e->getMessage();
    }
}

// Buscar clientes
$clients_stmt = $client->readAll($_SESSION['user_id']);
$clients = $clients_stmt->fetchAll();

// Se está editando um cliente
$editing_client = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $client->id = $_GET['edit'];
    $client->user_id = $_SESSION['user_id'];
    if ($client->readOne()) {
        $editing_client = $client;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - ClientManager Pro</title>
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
                        <a href="clients.php" class="bg-blue-600 text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
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
                    </nav>
                </div>
                <div class="flex-shrink-0 flex border-t border-gray-700 p-4">
                    <div class="flex-shrink-0 w-full group block">
                        <div class="flex items-center">
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-200"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                                <a href="../logout.php" class="text-xs font-medium text-gray-400 hover:text-white">Sair</a>
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
                            <h1 class="text-3xl font-bold text-gray-900">Clientes</h1>
                            <button onclick="openModal()" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md hover:shadow-lg transition-shadow duration-300">
                                <i class="fas fa-plus mr-2"></i>
                                Adicionar Cliente
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

                        <!-- Lista de Clientes -->
                        <div class="mt-8 bg-white shadow-md rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:p-6">
                                <?php if (empty($clients)): ?>
                                    <div class="text-center py-12">
                                        <i class="fas fa-users text-gray-300 text-7xl mb-4"></i>
                                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Nenhum cliente cadastrado</h3>
                                        <p class="text-lg text-gray-500 mb-4">Comece adicionando seu primeiro cliente</p>
                                        <button onclick="openModal()" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md hover:shadow-lg transition-shadow duration-300">
                                            <i class="fas fa-plus mr-2"></i>
                                            Adicionar Primeiro Cliente
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Cliente</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Contato</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Assinatura</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Vencimento</th>
                                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                                    <th class="px-6 py-4 text-right text-sm font-semibold text-gray-600 uppercase tracking-wider">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($clients as $client_row): ?>
                                                <tr class="hover:bg-gray-50 border-b border-gray-100">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div>
                                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($client_row['name']); ?></div>
                                                            <?php if ($client_row['document']): ?>
                                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($client_row['document']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($client_row['phone']); ?></div>
                                                        <?php if ($client_row['email']): ?>
                                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($client_row['email']); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($client_row['subscription_amount']): ?>
                                                            <div class="text-sm font-medium text-gray-900">R$ <?php echo number_format($client_row['subscription_amount'], 2, ',', '.'); ?></div>
                                                            <?php if ($client_row['last_payment_date']): ?>
                                                                <div class="text-xs text-gray-500">Último: <?php echo date('d/m/Y', strtotime($client_row['last_payment_date'])); ?></div>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-sm text-gray-400">Não definido</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($client_row['due_date']): ?>
                                                            <?php 
                                                            $due_date = new DateTime($client_row['due_date']);
                                                            $today = new DateTime();
                                                            $diff = $today->diff($due_date);
                                                            $days_diff = $diff->invert ? -$diff->days : $diff->days;
                                                            ?>
                                                            <div class="text-sm text-gray-900"><?php echo $due_date->format('d/m/Y'); ?></div>
                                                            <?php if ($days_diff < 0): ?>
                                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                                    <?php echo abs($days_diff); ?> dias em atraso
                                                                </span>
                                                            <?php elseif ($days_diff <= 3): ?>
                                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                                    Vence em <?php echo $days_diff; ?> dias
                                                                </span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-sm text-gray-400">Não definido</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($client_row['status'] == 'active'): ?>
                                                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-200 text-green-800">Ativo</span>
                                                        <?php elseif ($client_row['status'] == 'inactive'): ?>
                                                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-200 text-red-800">Inativo</span>
                                                        <?php else: ?>
                                                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-200 text-yellow-800">Pendente</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <?php if ($client_row['subscription_amount']): ?>
                                                            <button onclick="markPayment(<?php echo $client_row['id']; ?>, '<?php echo htmlspecialchars($client_row['name']); ?>')" 
                                                                    class="text-green-600 hover:text-green-900 mr-3 p-2 rounded-full hover:bg-gray-200 transition duration-150" 
                                                                    title="Marcar pagamento como recebido">
                                                                <i class="fas fa-dollar-sign"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button onclick="editClient(<?php echo htmlspecialchars(json_encode($client_row)); ?>)" class="text-blue-600 hover:text-blue-900 mr-3 p-2 rounded-full hover:bg-gray-200 transition duration-150">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button onclick="deleteClient(<?php echo $client_row['id']; ?>, '<?php echo htmlspecialchars($client_row['name']); ?>')" class="text-red-600 hover:text-red-900 p-2 rounded-full hover:bg-gray-200 transition duration-150">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para adicionar/editar cliente -->
    <div id="clientModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-6 border max-w-2xl shadow-lg rounded-md bg-white border-t-4 border-blue-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 mb-4" id="modalTitle">Adicionar Cliente</h3>
                <form id="clientForm" method="POST">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="clientId">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Informações Básicas -->
                        <div class="space-y-4">
                            <h4 class="text-lg font-medium text-gray-900 border-b pb-2">Informações Básicas</h4>
                            
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Nome *</label>
                                <input type="text" name="name" id="name" required 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" name="email" id="email" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">Telefone/WhatsApp *</label>
                                <input type="tel" name="phone" id="phone" required 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                            </div>
                            
                            <div>
                                <label for="document" class="block text-sm font-medium text-gray-700">CPF/CNPJ</label>
                                <input type="text" name="document" id="document" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                            </div>
                            
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                <select name="status" id="status" 
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                                    <option value="active">Ativo</option>
                                    <option value="inactive">Inativo</option>
                                    <option value="pending">Pendente</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Informações de Assinatura -->
                        <div class="space-y-4">
                            <h4 class="text-lg font-medium text-gray-900 border-b pb-2">Assinatura e Pagamentos</h4>
                            
                            <div>
                                <label for="subscription_amount" class="block text-sm font-medium text-gray-700">Valor da Assinatura (R$)</label>
                                <input type="number" name="subscription_amount" id="subscription_amount" step="0.01" min="0"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5"
                                       placeholder="0,00">
                            </div>
                            
                            <div>
                                <label for="due_date" class="block text-sm font-medium text-gray-700">Data de Vencimento</label>
                                <input type="date" name="due_date" id="due_date" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                            </div>
                            
                            <div>
                                <label for="last_payment_date" class="block text-sm font-medium text-gray-700">Último Pagamento</label>
                                <input type="date" name="last_payment_date" id="last_payment_date" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                            </div>
                            
                            <div>
                                <label for="next_payment_date" class="block text-sm font-medium text-gray-700">Próximo Pagamento</label>
                                <input type="date" name="next_payment_date" id="next_payment_date" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campos que ocupam toda a largura -->
                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700">Endereço</label>
                            <textarea name="address" id="address" rows="2" 
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5"></textarea>
                        </div>
                        
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">Observações</label>
                            <textarea name="notes" id="notes" rows="3" 
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5"></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeModal()" class="bg-gray-200 text-gray-700 px-5 py-2.5 rounded-lg hover:bg-gray-300 transition duration-150">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md">
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('clientModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Adicionar Cliente';
            document.getElementById('formAction').value = 'add';
            document.getElementById('clientForm').reset();
        }

        function closeModal() {
            document.getElementById('clientModal').classList.add('hidden');
        }

        function editClient(client) {
            document.getElementById('clientModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Editar Cliente';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('clientId').value = client.id;
            document.getElementById('name').value = client.name;
            document.getElementById('email').value = client.email || '';
            document.getElementById('phone').value = client.phone;
            document.getElementById('document').value = client.document || '';
            document.getElementById('address').value = client.address || '';
            document.getElementById('status').value = client.status;
            document.getElementById('notes').value = client.notes || '';
            document.getElementById('subscription_amount').value = client.subscription_amount || '';
            document.getElementById('due_date').value = client.due_date || '';
            document.getElementById('last_payment_date').value = client.last_payment_date || '';
            document.getElementById('next_payment_date').value = client.next_payment_date || '';
        }

        function deleteClient(id, name) {
            if (confirm('Tem certeza que deseja remover o cliente "' + name + '"?')) {
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

        function markPayment(id, name) {
            if (confirm('Marcar pagamento como recebido para "' + name + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="mark_payment">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Fechar modal ao clicar fora
        document.getElementById('clientModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Validação do formulário
        document.getElementById('clientForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const email = document.getElementById('email').value.trim();
            const subscriptionAmount = document.getElementById('subscription_amount').value;
            
            if (!name) {
                alert('Nome é obrigatório');
                e.preventDefault();
                return;
            }
            
            if (!phone) {
                alert('Telefone é obrigatório');
                e.preventDefault();
                return;
            }
            
            if (email && !isValidEmail(email)) {
                alert('Email inválido');
                e.preventDefault();
                return;
            }
            
            if (subscriptionAmount && (isNaN(subscriptionAmount) || parseFloat(subscriptionAmount) <= 0)) {
                alert('Valor da assinatura deve ser um número positivo');
                e.preventDefault();
                return;
            }
        });

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Auto-calcular próximo pagamento quando definir último pagamento
        document.getElementById('last_payment_date').addEventListener('change', function() {
            const lastPaymentDate = new Date(this.value);
            if (lastPaymentDate) {
                const nextPaymentDate = new Date(lastPaymentDate);
                nextPaymentDate.setDate(nextPaymentDate.getDate() + 30);
                
                const nextPaymentInput = document.getElementById('next_payment_date');
                const dueDateInput = document.getElementById('due_date');
                
                const formattedDate = nextPaymentDate.toISOString().split('T')[0];
                nextPaymentInput.value = formattedDate;
                
                if (!dueDateInput.value) {
                    dueDateInput.value = formattedDate;
                }
            }
        });
    </script>
</body>
</html>