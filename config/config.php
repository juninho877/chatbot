<?php
// Habilitar exibição de erros para depuração
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Configurações da API Evolution V2
define('EVOLUTION_API_URL', 'https://evov2.duckdns.org');
define('EVOLUTION_API_KEY', '79Bb4lpu2TzxrSMu3SDfSGvB3MIhkur7');

// Configurações do Mercado Pago
define('MERCADO_PAGO_ACCESS_TOKEN', 'SEU_ACCESS_TOKEN_AQUI');
define('MERCADO_PAGO_PUBLIC_KEY', 'SEU_PUBLIC_KEY_AQUI');

// Configurações gerais
define('SITE_URL', 'http://localhost');

// Configurações de sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Timezone padrão (pode ser sobrescrito pelas configurações do banco)
date_default_timezone_set('America/Sao_Paulo');

// Função para obter configurações do banco de dados
function getAppSetting($key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        try {
            require_once __DIR__ . '/database.php';
            require_once __DIR__ . '/../classes/AppSettings.php';
            
            $database = new Database();
            $db = $database->getConnection();
            
            if ($db) {
                $settings = new AppSettings($db);
            }
        } catch (Exception $e) {
            error_log("Error loading app settings: " . $e->getMessage());
            return $default;
        }
    }
    
    if ($settings) {
        return $settings->get($key, $default);
    }
    
    return $default;
}

// Definir constantes baseadas nas configurações do banco
define('ADMIN_EMAIL', getAppSetting('admin_email', 'admin@clientmanager.com'));
define('SITE_NAME', getAppSetting('site_name', 'ClientManager Pro'));

// Atualizar timezone se configurado no banco
$db_timezone = getAppSetting('timezone', 'America/Sao_Paulo');
if ($db_timezone) {
    date_default_timezone_set($db_timezone);
}

// Função para debug
function debug($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

// Função para redirecionar
function redirect($url) {
    header("Location: $url");
    exit();
}
?>