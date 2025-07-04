<?php
// Verificar se é administrador
$is_admin = ($_SESSION['user_role'] === 'admin');
?>
<!-- Sidebar -->
<div class="hidden md:flex md:w-64 md:flex-col">
    <div class="flex flex-col flex-grow pt-5 overflow-y-auto bg-gray-800 text-gray-100 border-r border-gray-700">
        <div class="flex items-center flex-shrink-0 px-4">
            <h1 class="text-2xl font-extrabold text-white"><?php echo SITE_NAME; ?></h1>
        </div>
        <div class="mt-5 flex-grow flex flex-col">
            <nav class="flex-1 px-2 space-y-1">
                <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-home mr-3"></i>
                    Dashboard
                </a>
                <a href="clients.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-users mr-3"></i>
                    Clientes
                </a>
                <a href="messages.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fab fa-whatsapp mr-3"></i>
                    Mensagens
                </a>
                <a href="templates.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'templates.php' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-template mr-3"></i>
                    Templates
                </a>
                <a href="whatsapp.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'whatsapp.php' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-qrcode mr-3"></i>
                    WhatsApp
                </a>
                <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Relatórios
                </a>
                <a href="user_settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'user_settings.php' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-user-cog mr-3"></i>
                    Minhas Configurações
                </a>
                
                <?php if ($is_admin): ?>
                <!-- Separador para seção administrativa -->
                <div class="border-t border-gray-700 my-2"></div>
                <div class="px-2 py-1">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Administração</span>
                </div>
                
                <a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'bg-red-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-users-cog mr-3"></i>
                    Gerenciar Usuários
                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">Admin</span>
                </a>
                
                <a href="plans.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'plans.php' ? 'bg-red-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-tags mr-3"></i>
                    Gerenciar Planos
                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">Admin</span>
                </a>
                
                <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-red-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-cog mr-3"></i>
                    Configurações Sistema
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