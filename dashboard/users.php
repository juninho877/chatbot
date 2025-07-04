<?php
require_once __DIR__ . '/auth_check.php'; // Middleware de autenticação
require_once __DIR__ . '/../classes/Plan.php';

// Verificar se é administrador
if ($_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_message'] = 'Acesso negado. Apenas administradores podem gerenciar usuários.';
    redirect("index.php");
}

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$plan = new Plan($db);

$message = '';
$error = '';

// Processar ações
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $user->name = trim($_POST['name']);
                    $user->email = trim($_POST['email']);
                    $user->password = $_POST['password'];
                    $user->phone = trim($_POST['phone']);
                    $user->plan_id = $_POST['plan_id'];
                    $user->role = $_POST['role'];
                    
                    // Validações
                    if (empty($user->name)) {
                        $error = "Nome é obrigatório.";
                        break;
                    }
                    
                    if (empty($user->email) || !filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                        $error = "Email válido é obrigatório.";
                        break;
                    }
                    
                    if (strlen($user->password) < 6) {
                        $error = "Senha deve ter pelo menos 6 caracteres.";
                        break;
                    }
                    
                    // Verificar se email já existe
                    $check_query = "SELECT id FROM users WHERE email = :email";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':email', $user->email);
                    $check_stmt->execute();
                    
                    if ($check_stmt->rowCount() > 0) {
                        $error = "Este email já está em uso.";
                        break;
                    }
                    
                    if ($user->create()) {
                        $message = "Usuário criado com sucesso!";
                    } else {
                        $error = "Erro ao criar usuário.";
                    }
                    break;
                    
                case 'edit':
                    $user->id = $_POST['id'];
                    $user->name = trim($_POST['name']);
                    $user->email = trim($_POST['email']);
                    $user->phone = trim($_POST['phone']);
                    $user->plan_id = $_POST['plan_id'];
                    $user->role = $_POST['role'];
                    
                    // Validações
                    if (empty($user->name)) {
                        $error = "Nome é obrigatório.";
                        break;
                    }
                    
                    if (empty($user->email) || !filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                        $error = "Email válido é obrigatório.";
                        break;
                    }
                    
                    // Verificar se email já existe (exceto para o próprio usuário)
                    $check_query = "SELECT id FROM users WHERE email = :email AND id != :id";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->bindParam(':email', $user->email);
                    $check_stmt->bindParam(':id', $user->id);
                    $check_stmt->execute();
                    
                    if ($check_stmt->rowCount() > 0) {
                        $error = "Este email já está em uso por outro usuário.";
                        break;
                    }
                    
                    // Proteger usuário admin principal
                    if ($user->id == 1 && $user->role !== 'admin') {
                        $error = "Não é possível alterar o papel do administrador principal.";
                        break;
                    }
                    
                    // Atualizar usuário
                    $query = "UPDATE users SET name=:name, email=:email, phone=:phone, plan_id=:plan_id, role=:role WHERE id=:id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':name', $user->name);
                    $stmt->bindParam(':email', $user->email);
                    $stmt->bindParam(':phone', $user->phone);
                    $stmt->bindParam(':plan_id', $user->plan_id);
                    $stmt->bindParam(':role', $user->role);
                    $stmt->bindParam(':id', $user->id);
                    
                    if ($stmt->execute()) {
                        $message = "Usuário atualizado com sucesso!";
                    } else {
                        $error = "Erro ao atualizar usuário.";
                    }
                    break;
                    
                case 'delete':
                    $user_id = $_POST['id'];
                    
                    // Proteger usuário admin principal
                    if ($user_id == 1) {
                        $error = "Não é possível deletar o administrador principal.";
                        break;
                    }
                    
                    // Proteger contra auto-exclusão
                    if ($user_id == $_SESSION['user_id']) {
                        $error = "Você não pode deletar sua própria conta.";
                        break;
                    }
                    
                    $query = "DELETE FROM users WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $user_id);
                    
                    if ($stmt->execute()) {
                        $message = "Usuário removido com sucesso!";
                    } else {
                        $error = "Erro ao remover usuário.";
                    }
                    break;
                    
                case 'reset_password':
                    $user_id = $_POST['id'];
                    $new_password = $_POST['new_password'];
                    
                    if (strlen($new_password) < 6) {
                        $error = "Nova senha deve ter pelo menos 6 caracteres.";
                        break;
                    }
                    
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $query = "UPDATE users SET password = :password WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':id', $user_id);
                    
                    if ($stmt->execute()) {
                        $message = "Senha redefinida com sucesso!";
                    } else {
                        $error = "Erro ao redefinir senha.";
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error = "Erro: " . $e->getMessage();
    }
}

// Buscar todos os usuários
$query = "SELECT u.*, p.name as plan_name, p.price as plan_price 
          FROM users u 
          LEFT JOIN plans p ON u.plan_id = p.id 
          ORDER BY u.id ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll();

// Buscar todos os planos
$plans_stmt = $plan->readAll();
$plans = $plans_stmt->fetchAll();

// Se está editando um usuário
$editing_user = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    foreach ($users as $user_data) {
        if ($user_data['id'] == $_GET['edit']) {
            $editing_user = $user_data;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Gerenciar Usuários</title>
    <link rel="icon" href="<?php echo FAVICON_PATH; ?>">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen bg-gray-100">
        <?php include 'sidebar.php'; ?>

        <!-- Main content -->
        <div class="flex flex-col w-0 flex-1 overflow-hidden">
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <div class="flex justify-between items-center">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900">Gerenciar Usuários</h1>
                                <p class="mt-1 text-sm text-gray-600">Administre todos os usuários do sistema</p>
                            </div>
                            <button onclick="openModal()" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 transition duration-150 shadow-md hover:shadow-lg">
                                <i class="fas fa-plus mr-2"></i>
                                Adicionar Usuário
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

                        <!-- Lista de Usuários -->
                        <div class="mt-8 bg-white shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Usuário</th>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Plano</th>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">WhatsApp</th>
                                                <th class="px-6 py-4 text-right text-sm font-semibold text-gray-600 uppercase tracking-wider">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($users as $user_row): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-10 w-10">
                                                            <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                                <i class="fas fa-user text-gray-600"></i>
                                                            </div>
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($user_row['name']); ?>
                                                                <?php if ($user_row['role'] === 'admin'): ?>
                                                                    <span class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Admin</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user_row['email']); ?></div>
                                                            <?php if ($user_row['phone']): ?>
                                                                <div class="text-xs text-gray-400"><?php echo htmlspecialchars($user_row['phone']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">
                                                        <?php echo htmlspecialchars($user_row['plan_name'] ?? 'Sem plano'); ?>
                                                    </div>
                                                    <?php if ($user_row['plan_price']): ?>
                                                        <div class="text-sm text-gray-500">R$ <?php echo number_format($user_row['plan_price'], 2, ',', '.'); ?>/mês</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php 
                                                    $status = $user_row['subscription_status'] ?? 'unknown';
                                                    switch($status) {
                                                        case 'trial':
                                                            echo '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Teste</span>';
                                                            break;
                                                        case 'active':
                                                            echo '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Ativo</span>';
                                                            break;
                                                        case 'expired':
                                                            echo '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Expirado</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Desconhecido</span>';
                                                    }
                                                    ?>
                                                    <?php if ($user_row['trial_ends_at'] && $status === 'trial'): ?>
                                                        <div class="text-xs text-gray-500 mt-1">
                                                            Expira: <?php echo date('d/m/Y', strtotime($user_row['trial_ends_at'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php if ($user_row['whatsapp_connected']): ?>
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                            <i class="fab fa-whatsapp mr-1"></i>
                                                            Conectado
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                            <i class="fab fa-whatsapp mr-1"></i>
                                                            Desconectado
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user_row)); ?>)" 
                                                            class="text-blue-600 hover:text-blue-900 mr-3 p-2 rounded-full hover:bg-gray-200 transition duration-150">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="resetPassword(<?php echo $user_row['id']; ?>, '<?php echo htmlspecialchars($user_row['name']); ?>')" 
                                                            class="text-yellow-600 hover:text-yellow-900 mr-3 p-2 rounded-full hover:bg-gray-200 transition duration-150">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <?php if ($user_row['id'] != 1 && $user_row['id'] != $_SESSION['user_id']): ?>
                                                        <button onclick="deleteUser(<?php echo $user_row['id']; ?>, '<?php echo htmlspecialchars($user_row['name']); ?>')" 
                                                                class="text-red-600 hover:text-red-900 p-2 rounded-full hover:bg-gray-200 transition duration-150">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para adicionar/editar usuário -->
    <div id="userModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-6 border max-w-2xl shadow-lg rounded-md bg-white border-t-4 border-blue-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 mb-4" id="modalTitle">Adicionar Usuário</h3>
                <form id="userForm" method="POST">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="userId">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Nome *</label>
                            <input type="text" name="name" id="name" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                            <input type="email" name="email" id="email" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                        </div>
                        
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Telefone</label>
                            <input type="tel" name="phone" id="phone" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                        </div>
                        
                        <div>
                            <label for="plan_id" class="block text-sm font-medium text-gray-700">Plano *</label>
                            <select name="plan_id" id="plan_id" required 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                                <option value="">Selecione um plano</option>
                                <?php foreach ($plans as $plan_row): ?>
                                    <option value="<?php echo $plan_row['id']; ?>">
                                        <?php echo htmlspecialchars($plan_row['name']); ?> - R$ <?php echo number_format($plan_row['price'], 2, ',', '.'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700">Papel *</label>
                            <select name="role" id="role" required 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5">
                                <option value="user">Usuário</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        
                        <div id="passwordField">
                            <label for="password" class="block text-sm font-medium text-gray-700">Senha *</label>
                            <input type="password" name="password" id="password" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2.5"
                                   minlength="6">
                            <p class="mt-1 text-xs text-gray-500">Mínimo 6 caracteres</p>
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

    <!-- Modal para redefinir senha -->
    <div id="passwordModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-6 border max-w-md shadow-lg rounded-md bg-white border-t-4 border-yellow-600">
            <div class="mt-3">
                <h3 class="text-xl font-semibold text-gray-900 mb-4">Redefinir Senha</h3>
                <form id="passwordForm" method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="id" id="passwordUserId">
                    
                    <div class="mb-4">
                        <label for="new_password" class="block text-sm font-medium text-gray-700">Nova Senha *</label>
                        <input type="password" name="new_password" id="new_password" required minlength="6"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500 p-2.5">
                        <p class="mt-1 text-xs text-gray-500">Mínimo 6 caracteres</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closePasswordModal()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition duration-150">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition duration-150">
                            Redefinir Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('userModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Adicionar Usuário';
            document.getElementById('formAction').value = 'add';
            document.getElementById('userForm').reset();
            document.getElementById('passwordField').style.display = 'block';
            document.getElementById('password').required = true;
        }

        function closeModal() {
            document.getElementById('userModal').classList.add('hidden');
        }

        function editUser(user) {
            document.getElementById('userModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Editar Usuário';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('userId').value = user.id;
            document.getElementById('name').value = user.name;
            document.getElementById('email').value = user.email;
            document.getElementById('phone').value = user.phone || '';
            document.getElementById('plan_id').value = user.plan_id;
            document.getElementById('role').value = user.role;
            
            // Ocultar campo de senha na edição
            document.getElementById('passwordField').style.display = 'none';
            document.getElementById('password').required = false;
        }

        function deleteUser(id, name) {
            if (confirm('Tem certeza que deseja remover o usuário "' + name + '"? Esta ação não pode ser desfeita.')) {
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

        function resetPassword(id, name) {
            document.getElementById('passwordModal').classList.remove('hidden');
            document.getElementById('passwordUserId').value = id;
        }

        function closePasswordModal() {
            document.getElementById('passwordModal').classList.add('hidden');
            document.getElementById('passwordForm').reset();
        }

        // Fechar modais ao clicar fora
        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.getElementById('passwordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePasswordModal();
            }
        });
    </script>
</body>
</html>