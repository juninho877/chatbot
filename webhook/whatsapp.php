<?php
/**
 * Webhook para receber atualizações de status do WhatsApp
 * 
 * Este arquivo recebe notificações da API Evolution sobre:
 * - Status de entrega das mensagens
 * - Confirmações de leitura
 * - Falhas no envio
 */

// Configurar headers para resposta
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responder a requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log de início
error_log("=== WEBHOOK RECEIVED ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Time: " . date('Y-m-d H:i:s'));

try {
    // Incluir arquivos necessários
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../classes/MessageHistory.php';
    
    // Verificar se é uma requisição POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit();
    }
    
    // Ler o payload JSON
    $input = file_get_contents('php://input');
    error_log("Raw payload: " . $input);
    
    if (empty($input)) {
        error_log("Empty payload received");
        http_response_code(400);
        echo json_encode(['error' => 'Empty payload']);
        exit();
    }
    
    // Decodificar JSON
    $payload = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit();
    }
    
    error_log("Decoded payload: " . json_encode($payload, JSON_PRETTY_PRINT));
    
    // Conectar ao banco de dados
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    $messageHistory = new MessageHistory($db);
    
    // Processar diferentes tipos de eventos
    $event_type = $payload['event'] ?? null;
    
    switch ($event_type) {
        case 'messages.update':
            handleMessageUpdate($payload, $messageHistory);
            break;
            
        case 'messages.upsert':
            handleMessageUpsert($payload, $messageHistory);
            break;
            
        case 'connection.update':
            handleConnectionUpdate($payload);
            break;
            
        default:
            error_log("Unknown event type: " . $event_type);
            // Tentar processar como atualização de mensagem genérica
            if (isset($payload['data']) && is_array($payload['data'])) {
                foreach ($payload['data'] as $message_data) {
                    processMessageStatus($message_data, $messageHistory);
                }
            }
    }
    
    // Resposta de sucesso
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Webhook processed']);
    
} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Função para limpar ID da mensagem do WhatsApp removendo sufixos
 */
function cleanWhatsAppMessageId($message_id) {
    if (empty($message_id)) {
        return null;
    }
    
    // Remover sufixos como _0, _1, etc.
    $cleaned_id = preg_replace('/_\d+$/', '', $message_id);
    
    error_log("Webhook - Cleaned WhatsApp message ID: '$message_id' -> '$cleaned_id'");
    
    return $cleaned_id;
}

/**
 * Processar atualizações de status de mensagem
 */
function handleMessageUpdate($payload, $messageHistory) {
    error_log("Processing message update");
    
    if (!isset($payload['data']) || !is_array($payload['data'])) {
        error_log("No data array in message update payload");
        return;
    }
    
    foreach ($payload['data'] as $message_data) {
        processMessageStatus($message_data, $messageHistory);
    }
}

/**
 * Processar novas mensagens ou atualizações
 */
function handleMessageUpsert($payload, $messageHistory) {
    error_log("Processing message upsert");
    
    if (!isset($payload['data']) || !is_array($payload['data'])) {
        error_log("No data array in message upsert payload");
        return;
    }
    
    foreach ($payload['data'] as $message_data) {
        processMessageStatus($message_data, $messageHistory);
    }
}

/**
 * Processar atualizações de conexão
 */
function handleConnectionUpdate($payload) {
    error_log("Processing connection update");
    error_log("Connection data: " . json_encode($payload['data'] ?? []));
    
    // Aqui você pode implementar lógica para atualizar o status de conexão
    // do usuário no banco de dados se necessário
}

/**
 * Processar status individual de mensagem
 */
function processMessageStatus($message_data, $messageHistory) {
    try {
        // Extrair informações da mensagem
        $raw_message_id = $message_data['key']['id'] ?? null;
        $status = null;
        
        // Limpar o ID da mensagem removendo sufixos
        $message_id = cleanWhatsAppMessageId($raw_message_id);
        
        // Determinar o status baseado nos dados recebidos
        if (isset($message_data['status'])) {
            $status = mapWhatsAppStatus($message_data['status']);
        } elseif (isset($message_data['messageTimestamp'])) {
            $status = 'delivered';
        }
        
        if (!$message_id) {
            error_log("No message ID found in webhook data");
            return;
        }
        
        if (!$status) {
            error_log("No valid status found in webhook data");
            return;
        }
        
        error_log("Processing message ID: $message_id (raw: $raw_message_id), Status: $status");
        
        // Buscar mensagem no histórico usando o ID limpo
        $message_record = $messageHistory->getByWhatsAppMessageId($message_id);
        
        if (!$message_record) {
            error_log("Message not found in database: $message_id");
            
            // Tentar buscar com o ID original (fallback)
            if ($raw_message_id !== $message_id) {
                error_log("Trying fallback search with raw ID: $raw_message_id");
                $message_record = $messageHistory->getByWhatsAppMessageId($raw_message_id);
                
                if (!$message_record) {
                    error_log("Message not found even with raw ID: $raw_message_id");
                    return;
                } else {
                    error_log("Found message with raw ID, will update using raw ID");
                    $message_id = $raw_message_id; // Use o ID original para a atualização
                }
            } else {
                return;
            }
        }
        
        // Atualizar status apenas se for uma progressão válida
        $current_status = $message_record['status'];
        if (shouldUpdateStatus($current_status, $status)) {
            $updated = $messageHistory->updateStatus($message_id, $status);
            
            if ($updated) {
                error_log("Status updated successfully: $message_id -> $status");
                
                // Se a mensagem falhou, enviar notificação para o admin
                if ($status === 'failed') {
                    sendFailureNotification($message_record, $message_data);
                }
            } else {
                error_log("Failed to update status in database");
            }
        } else {
            error_log("Status update skipped: $current_status -> $status (not a valid progression)");
        }
        
    } catch (Exception $e) {
        error_log("Error processing message status: " . $e->getMessage());
    }
}

/**
 * Mapear status do WhatsApp para nossos status internos
 */
function mapWhatsAppStatus($whatsapp_status) {
    $status_map = [
        'PENDING' => 'sent',
        'SENT' => 'sent',
        'DELIVERED' => 'delivered',
        'READ' => 'read',
        'FAILED' => 'failed',
        'ERROR' => 'failed'
    ];
    
    return $status_map[strtoupper($whatsapp_status)] ?? null;
}

/**
 * Verificar se devemos atualizar o status (progressão válida)
 */
function shouldUpdateStatus($current_status, $new_status) {
    $status_hierarchy = [
        'sent' => 1,
        'delivered' => 2,
        'read' => 3,
        'failed' => 0 // Failed pode acontecer a qualquer momento
    ];
    
    $current_level = $status_hierarchy[$current_status] ?? 0;
    $new_level = $status_hierarchy[$new_status] ?? 0;
    
    // Permitir atualização se:
    // 1. O novo status é "failed" (pode acontecer a qualquer momento)
    // 2. O novo status tem nível maior que o atual
    return $new_status === 'failed' || $new_level > $current_level;
}

/**
 * Enviar notificação de falha para o administrador
 */
function sendFailureNotification($message_record, $webhook_data) {
    try {
        if (!defined('ADMIN_EMAIL') || empty(ADMIN_EMAIL)) {
            error_log("ADMIN_EMAIL not configured, skipping failure notification");
            return;
        }
        
        $client_name = $message_record['client_name'] ?? 'Cliente desconhecido';
        $phone = $message_record['phone'] ?? 'Número desconhecido';
        $message_text = substr($message_record['message'], 0, 100) . '...';
        
        $subject = "ALERTA: Falha no Envio de Mensagem - ClientManager Pro";
        
        $email_body = "
        <html>
        <head>
            <title>Alerta de Falha - ClientManager Pro</title>
            <style>
                body { font-family: Arial, sans-serif; }
                .header { background-color: #DC2626; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .alert { background-color: #FEE2E2; color: #DC2626; padding: 15px; border-radius: 5px; margin: 10px 0; }
                .details { background-color: #F3F4F6; padding: 15px; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>⚠️ ALERTA DE FALHA</h1>
                <p>ClientManager Pro</p>
            </div>
            
            <div class='content'>
                <div class='alert'>
                    <h3>Falha no Envio de Mensagem</h3>
                    <p>Uma mensagem automática falhou ao ser entregue.</p>
                </div>
                
                <div class='details'>
                    <h3>Detalhes da Mensagem</h3>
                    <p><strong>Cliente:</strong> $client_name</p>
                    <p><strong>Telefone:</strong> $phone</p>
                    <p><strong>Mensagem:</strong> $message_text</p>
                    <p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>
                </div>
                
                <div class='details'>
                    <h3>Ações Recomendadas</h3>
                    <ul>
                        <li>Verificar se o número do cliente está correto</li>
                        <li>Confirmar se o WhatsApp está conectado</li>
                        <li>Tentar reenviar a mensagem manualmente</li>
                        <li>Entrar em contato com o cliente por outros meios</li>
                    </ul>
                </div>
            </div>
        </body>
        </html>";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ClientManager Pro <noreply@clientmanager.com>',
            'Reply-To: ' . ADMIN_EMAIL,
            'X-Mailer: PHP/' . phpversion(),
            'X-Priority: 1' // Alta prioridade
        ];
        
        if (mail(ADMIN_EMAIL, $subject, $email_body, implode("\r\n", $headers))) {
            error_log("Failure notification sent to admin: " . ADMIN_EMAIL);
        } else {
            error_log("Failed to send failure notification email");
        }
        
    } catch (Exception $e) {
        error_log("Error sending failure notification: " . $e->getMessage());
    }
}

error_log("=== WEBHOOK COMPLETED ===");
?>