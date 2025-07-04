<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Client.php';
require_once __DIR__ . '/../classes/MessageHistory.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    redirect("../login.php");
}

$database = new Database();
$db = $database->getConnection();
$client = new Client($db);
$messageHistory = new MessageHistory($db);

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

// Buscar estatísticas gerais
$stats = $messageHistory->getStatistics($_SESSION['user_id']);

// Buscar clientes com vencimento próximo (próximos 7 dias)
$upcoming_stmt = $client->getClientsWithUpcomingDueDate($_SESSION['user_id'], 7);
$upcoming_clients = $upcoming_stmt->fetchAll();

// Buscar clientes em atraso
$overdue_stmt = $client->getOverdueClients($_SESSION['user_id']);
$overdue_clients = $overdue_stmt->fetchAll();

// Buscar todos os clientes para estatísticas
$all_clients_stmt = $client->readAll($_SESSION['user_id']);
$all_clients = $all_clients_stmt->fetchAll();

// Calcular estatísticas de clientes
$total_clients = count($all_clients);
$active_clients = 0;
$total_revenue = 0;
$clients_with_subscription = 0;

foreach ($all_clients as $client_row) {
    if ($client_row['status'] == 'active') {
        $active_clients++;
    }
    if ($client_row['subscription_amount']) {
        $total_revenue += $client_row['subscription_amount'];
        $clients_with_subscription++;
    }
}

// Buscar mensagens dos últimos 30 dias por dia
$query = "SELECT DATE(sent_at) as date, COUNT(*) as count, 
                 SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                 SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
          FROM message_history 
          WHERE user_id = :user_id 
          AND sent_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          GROUP BY DATE(sent_at) 
          ORDER BY date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$daily_messages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - ClientManager Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a href="reports.php" class="bg-blue-600 text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md">
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
                        <h1 class="text-3xl font-bold text-gray-900">Relatórios e Análises</h1>
                    </div>
                    
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <!-- Estatísticas Principais -->
                        <div class="mt-8">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="bg-white overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-users text-blue-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 truncate">Total de Clientes</dt>
                                                    <dd class="text-xl font-semibold text-gray-900"><?php echo $total_clients; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-user-check text-green-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 truncate">Clientes Ativos</dt>
                                                    <dd class="text-xl font-semibold text-gray-900"><?php echo $active_clients; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-dollar-sign text-green-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 truncate">Receita Mensal</dt>
                                                    <dd class="text-xl font-semibold text-gray-900">R$ <?php echo number_format($total_revenue, 2, ',', '.'); ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fab fa-whatsapp text-green-400 text-3xl"></i>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-base font-medium text-gray-600 truncate">Mensagens Enviadas</dt>
                                                    <dd class="text-xl font-semibold text-gray-900"><?php echo $stats['total_messages'] ?? 0; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico de Mensagens -->
                        <div class="mt-8 bg-white shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 mb-4">Mensagens dos Últimos 30 Dias</h3>
                                <div class="h-64">
                                    <canvas id="messagesChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Alertas e Notificações -->
                        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Clientes com Vencimento Próximo -->
                            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                                <div class="px-6 py-6 sm:p-8">
                                    <h3 class="text-xl font-semibold text-gray-900 mb-4">
                                        <i class="fas fa-clock text-yellow-500 mr-2"></i>
                                        Vencimentos Próximos (7 dias)
                                    </h3>
                                    
                                    <?php if (empty($upcoming_clients)): ?>
                                        <div class="text-center py-8">
                                            <i class="fas fa-check-circle text-green-300 text-5xl mb-3"></i>
                                            <p class="text-gray-500">Nenhum vencimento próximo</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="space-y-3">
                                            <?php foreach ($upcoming_clients as $client_row): ?>
                                                <?php 
                                                $due_date = new DateTime($client_row['due_date']);
                                                $today = new DateTime();
                                                $diff = $today->diff($due_date);
                                                $days_diff = $diff->invert ? -$diff->days : $diff->days;
                                                ?>
                                                <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                                    <div>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($client_row['name']); ?></p>
                                                        <p class="text-sm text-gray-600">
                                                            R$ <?php echo number_format($client_row['subscription_amount'], 2, ',', '.'); ?> - 
                                                            <?php echo $due_date->format('d/m/Y'); ?>
                                                            <?php if ($days_diff == 0): ?>
                                                                <span class="text-red-600 font-medium">(Vence hoje!)</span>
                                                            <?php else: ?>
                                                                <span class="text-yellow-600">(<?php echo $days_diff; ?> dias)</span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                    <a href="messages.php" class="text-blue-600 hover:text-blue-800">
                                                        <i class="fab fa-whatsapp text-lg"></i>
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Clientes em Atraso -->
                            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                                <div class="px-6 py-6 sm:p-8">
                                    <h3 class="text-xl font-semibold text-gray-900 mb-4">
                                        <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                                        Pagamentos em Atraso
                                    </h3>
                                    
                                    <?php if (empty($overdue_clients)): ?>
                                        <div class="text-center py-8">
                                            <i class="fas fa-check-circle text-green-300 text-5xl mb-3"></i>
                                            <p class="text-gray-500">Nenhum pagamento em atraso</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="space-y-3">
                                            <?php foreach ($overdue_clients as $client_row): ?>
                                                <?php 
                                                $due_date = new DateTime($client_row['due_date']);
                                                $today = new DateTime();
                                                $diff = $today->diff($due_date);
                                                $days_overdue = $diff->days;
                                                ?>
                                                <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg border border-red-200">
                                                    <div>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($client_row['name']); ?></p>
                                                        <p class="text-sm text-gray-600">
                                                            R$ <?php echo number_format($client_row['subscription_amount'], 2, ',', '.'); ?> - 
                                                            <?php echo $due_date->format('d/m/Y'); ?>
                                                            <span class="text-red-600 font-medium">(<?php echo $days_overdue; ?> dias em atraso)</span>
                                                        </p>
                                                    </div>
                                                    <div class="flex space-x-2">
                                                        <a href="messages.php" class="text-blue-600 hover:text-blue-800">
                                                            <i class="fab fa-whatsapp text-lg"></i>
                                                        </a>
                                                        <a href="clients.php" class="text-green-600 hover:text-green-800">
                                                            <i class="fas fa-dollar-sign text-lg"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Estatísticas de Mensagens -->
                        <div class="mt-8 bg-white shadow-md rounded-lg overflow-hidden">
                            <div class="px-6 py-6 sm:p-8">
                                <h3 class="text-xl font-semibold text-gray-900 mb-4">Estatísticas de Mensagens</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                                        <div class="text-2xl font-bold text-blue-600"><?php echo $stats['total_messages'] ?? 0; ?></div>
                                        <div class="text-sm text-gray-600">Total Enviadas</div>
                                    </div>
                                    
                                    <div class="text-center p-4 bg-green-50 rounded-lg">
                                        <div class="text-2xl font-bold text-green-600"><?php echo $stats['sent_count'] ?? 0; ?></div>
                                        <div class="text-sm text-gray-600">Enviadas com Sucesso</div>
                                    </div>
                                    
                                    <div class="text-center p-4 bg-purple-50 rounded-lg">
                                        <div class="text-2xl font-bold text-purple-600"><?php echo $stats['delivered_count'] ?? 0; ?></div>
                                        <div class="text-sm text-gray-600">Entregues</div>
                                    </div>
                                    
                                    <div class="text-center p-4 bg-red-50 rounded-lg">
                                        <div class="text-2xl font-bold text-red-600"><?php echo $stats['failed_count'] ?? 0; ?></div>
                                        <div class="text-sm text-gray-600">Falharam</div>
                                    </div>
                                </div>
                                
                                <?php if ($stats['total_messages'] > 0): ?>
                                <div class="mt-4 text-center">
                                    <p class="text-sm text-gray-600">
                                        Taxa de sucesso: 
                                        <span class="font-semibold text-green-600">
                                            <?php echo round(($stats['sent_count'] / $stats['total_messages']) * 100, 1); ?>%
                                        </span>
                                    </p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Gráfico de mensagens dos últimos 30 dias
        const ctx = document.getElementById('messagesChart').getContext('2d');
        
        const chartData = {
            labels: [
                <?php 
                $labels = [];
                $sent_data = [];
                $failed_data = [];
                
                foreach (array_reverse($daily_messages) as $day) {
                    $labels[] = "'" . date('d/m', strtotime($day['date'])) . "'";
                    $sent_data[] = $day['sent_count'];
                    $failed_data[] = $day['failed_count'];
                }
                
                echo implode(', ', $labels);
                ?>
            ],
            datasets: [{
                label: 'Enviadas',
                data: [<?php echo implode(', ', $sent_data); ?>],
                borderColor: 'rgb(34, 197, 94)',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                tension: 0.1
            }, {
                label: 'Falharam',
                data: [<?php echo implode(', ', $failed_data); ?>],
                borderColor: 'rgb(239, 68, 68)',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.1
            }]
        };

        const config = {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                }
            },
        };

        new Chart(ctx, config);
    </script>
</body>
</html>