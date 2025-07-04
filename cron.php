<?php
/**
 * Cron Job para Automação de Cobrança
 * 
 * Este script deve ser executado diariamente pelo cron do servidor
 * Exemplo de configuração no crontab:
 * 0 9 * * * /usr/bin/php /caminho/para/seu/projeto/cron.php
 * 
 * Isso executará o script todos os dias às 9:00 AM
 */

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Incluir arquivos necessários
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Client.php';
require_once __DIR__ . '/classes/MessageTemplate.php';
require_once __DIR__ . '/classes/MessageHistory.php';
require_once __DIR__ . '/classes/WhatsAppAPI.php';

// Log de início
error_log("=== CRON JOB STARTED ===");
error_log("Date: " . date('Y-m-d H:i:s'));

// Estatísticas do processamento
$stats = [
    'users_processed' => 0,
    'messages_sent' => 0,
    'messages_failed' => 0,
    'clients_due_today' => 0,
    'clients_due_soon' => 0,
    'clients_overdue' => 0,
    'errors' => []
];

try {
    // Conectar ao banco de dados
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Erro na conexão com o banco de dados");
    }
    
    // Inicializar classes
    $user = new User($db);
    $client = new Client($db);
    $template = new MessageTemplate($db);
    $messageHistory = new MessageHistory($db);
    $whatsapp = new WhatsAppAPI();
    
    // Buscar todos os usuários com WhatsApp conectado
    $users_stmt = $user->readAll();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($users) . " users with WhatsApp connected");
    
    foreach ($users as $user_data) {
        $stats['users_processed']++;
        $user_id = $user_data['id'];
        $instance_name = $user_data['whatsapp_instance'];
        
        error_log("Processing user ID: $user_id, Instance: $instance_name");
        
        try {
            // Verificar se a instância está conectada
            if (!$whatsapp->isInstanceConnected($instance_name)) {
                error_log("WhatsApp instance not connected for user $user_id");
                $stats['errors'][] = "Usuário $user_id: WhatsApp não conectado";
                continue;
            }
            
            // 1. Clientes com vencimento hoje
            $due_today_stmt = $client->getClientsWithUpcomingDueDate($user_id, 0);
            $due_today_clients = $due_today_stmt->fetchAll(PDO::FETCH_ASSOC);
            $stats['clients_due_today'] += count($due_today_clients);
            
            foreach ($due_today_clients as $client_data) {
                $message_sent = sendAutomaticMessage(
                    $whatsapp, $template, $messageHistory, 
                    $user_id, $client_data, $instance_name, 
                    'lembrete', 'Lembrete de Vencimento'
                );
                
                if ($message_sent) {
                    $stats['messages_sent']++;
                } else {
                    $stats['messages_failed']++;
                }
            }
            
            // 2. Clientes com vencimento em 3 dias
            $due_soon_stmt = $client->getClientsWithUpcomingDueDate($user_id, 3);
            $due_soon_clients = $due_soon_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Filtrar apenas os que vencem em exatamente 3 dias
            $due_in_3_days = array_filter($due_soon_clients, function($client_data) {
                $due_date = new DateTime($client_data['due_date']);
                $today = new DateTime();
                $diff = $today->diff($due_date);
                return !$diff->invert && $diff->days == 3;
            });
            
            $stats['clients_due_soon'] += count($due_in_3_days);
            
            foreach ($due_in_3_days as $client_data) {
                $message_sent = sendAutomaticMessage(
                    $whatsapp, $template, $messageHistory, 
                    $user_id, $client_data, $instance_name, 
                    'lembrete', 'Lembrete Antecipado'
                );
                
                if ($message_sent) {
                    $stats['messages_sent']++;
                } else {
                    $stats['messages_failed']++;
                }
            }
            
            // 3. Clientes em atraso
            $overdue_stmt = $client->getOverdueClients($user_id);
            $overdue_clients = $overdue_stmt->fetchAll(PDO::FETCH_ASSOC);
            $stats['clients_overdue'] += count($overdue_clients);
            
            foreach ($overdue_clients as $client_data) {
                $message_sent = sendAutomaticMessage(
                    $whatsapp, $template, $messageHistory, 
                    $user_id, $client_data, $instance_name, 
                    'cobranca', 'Cobrança em Atraso'
                );
                
                if ($message_sent) {
                    $stats['messages_sent']++;
                } else {
                    $stats['messages_failed']++;
                }
            }
            
            // Delay entre usuários para evitar sobrecarga
            sleep(1);
            
        } catch (Exception $e) {
            error_log("Error processing user $user_id: " . $e->getMessage());
            $stats['errors'][] = "Usuário $user_id: " . $e->getMessage();
        }
    }
    
} catch (Exception $e) {
    error_log("Critical error in cron job: " . $e->getMessage());
    $stats['errors'][] = "Erro crítico: " . $e->getMessage();
}

// Log de estatísticas finais
error_log("=== CRON JOB COMPLETED ===");
error_log("Users processed: " . $stats['users_processed']);
error_log("Messages sent: " . $stats['messages_sent']);
error_log("Messages failed: " . $stats['messages_failed']);
error_log("Clients due today: " . $stats['clients_due_today']);
error_log("Clients due soon: " . $stats['clients_due_soon']);
error_log("Clients overdue: " . $stats['clients_overdue']);
error_log("Errors: " . count($stats['errors']));

// Enviar email de relatório para o administrador
sendAdminReport($stats);

/**
 * Função para enviar mensagem automática
 */
function sendAutomaticMessage($whatsapp, $template, $messageHistory, $user_id, $client_data, $instance_name, $template_type, $template_name) {
    try {
        // Buscar template por tipo
        $template->user_id = $user_id;
        $message_text = '';
        $template_id = null;
        
        if ($template->readByType($user_id, $template_type)) {
            $message_text = $template->message;
            $template_id = $template->id;
        } else {
            // Template padrão se não encontrar
            switch ($template_type) {
                case 'lembrete':
                    $message_text = "Olá {nome}! Lembrando que sua mensalidade de {valor} vence em {vencimento}. Obrigado!";
                    break;
                case 'cobranca':
                    $message_text = "Olá {nome}! Sua mensalidade de {valor} está em atraso desde {vencimento}. Por favor, regularize o pagamento.";
                    break;
                default:
                    $message_text = "Olá {nome}! Entre em contato conosco sobre sua mensalidade.";
            }
        }
        
        // Personalizar mensagem
        $message_text = str_replace('{nome}', $client_data['name'], $message_text);
        $message_text = str_replace('{valor}', 'R$ ' . number_format($client_data['subscription_amount'], 2, ',', '.'), $message_text);
        $message_text = str_replace('{vencimento}', date('d/m/Y', strtotime($client_data['due_date'])), $message_text);
        
        // Enviar mensagem
        $result = $whatsapp->sendMessage($instance_name, $client_data['phone'], $message_text);
        
        // Extrair ID da mensagem do WhatsApp se disponível
        $whatsapp_message_id = null;
        if (isset($result['data']['key']['id'])) {
            $whatsapp_message_id = $result['data']['key']['id'];
        }
        
        // Registrar no histórico
        $messageHistory->user_id = $user_id;
        $messageHistory->client_id = $client_data['id'];
        $messageHistory->template_id = $template_id;
        $messageHistory->message = $message_text;
        $messageHistory->phone = $client_data['phone'];
        $messageHistory->whatsapp_message_id = $whatsapp_message_id;
        $messageHistory->status = ($result['status_code'] == 200 || $result['status_code'] == 201) ? 'sent' : 'failed';
        
        $messageHistory->create();
        
        error_log("Message sent to client {$client_data['name']} ({$client_data['phone']}): " . $messageHistory->status);
        
        return $messageHistory->status === 'sent';
        
    } catch (Exception $e) {
        error_log("Error sending message to client {$client_data['name']}: " . $e->getMessage());
        return false;
    }
}

/**
 * Função para enviar relatório por email para o administrador
 */
function sendAdminReport($stats) {
    try {
        if (!defined('ADMIN_EMAIL') || empty(ADMIN_EMAIL)) {
            error_log("ADMIN_EMAIL not configured, skipping email report");
            return;
        }
        
        $subject = "Relatório Diário - Automação de Cobrança - " . date('d/m/Y');
        
        $message = "
        <html>
        <head>
            <title>Relatório Diário - ClientManager Pro</title>
            <style>
                body { font-family: Arial, sans-serif; }
                .header { background-color: #3B82F6; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .stats { background-color: #F3F4F6; padding: 15px; border-radius: 5px; margin: 10px 0; }
                .error { background-color: #FEE2E2; color: #DC2626; padding: 10px; border-radius: 5px; margin: 5px 0; }
                .success { color: #059669; }
                .warning { color: #D97706; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>ClientManager Pro</h1>
                <h2>Relatório Diário de Automação</h2>
                <p>" . date('d/m/Y H:i:s') . "</p>
            </div>
            
            <div class='content'>
                <div class='stats'>
                    <h3>Estatísticas do Processamento</h3>
                    <p><strong>Usuários processados:</strong> {$stats['users_processed']}</p>
                    <p><strong class='success'>Mensagens enviadas:</strong> {$stats['messages_sent']}</p>
                    <p><strong class='warning'>Mensagens falharam:</strong> {$stats['messages_failed']}</p>
                </div>
                
                <div class='stats'>
                    <h3>Clientes Identificados</h3>
                    <p><strong>Vencimento hoje:</strong> {$stats['clients_due_today']}</p>
                    <p><strong>Vencimento em 3 dias:</strong> {$stats['clients_due_soon']}</p>
                    <p><strong>Em atraso:</strong> {$stats['clients_overdue']}</p>
                </div>";
        
        if (!empty($stats['errors'])) {
            $message .= "
                <div class='stats'>
                    <h3>Erros Encontrados</h3>";
            foreach ($stats['errors'] as $error) {
                $message .= "<div class='error'>$error</div>";
            }
            $message .= "</div>";
        }
        
        $message .= "
                <div class='stats'>
                    <h3>Próximos Passos</h3>
                    <p>• Verifique se há mensagens que falharam e investigue os motivos</p>
                    <p>• Monitore as confirmações de entrega via webhook</p>
                    <p>• Acompanhe os pagamentos dos clientes contatados</p>
                </div>
            </div>
        </body>
        </html>";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ClientManager Pro <noreply@clientmanager.com>',
            'Reply-To: ' . ADMIN_EMAIL,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        if (mail(ADMIN_EMAIL, $subject, $message, implode("\r\n", $headers))) {
            error_log("Admin report sent successfully to " . ADMIN_EMAIL);
        } else {
            error_log("Failed to send admin report email");
        }
        
    } catch (Exception $e) {
        error_log("Error sending admin report: " . $e->getMessage());
    }
}
?>