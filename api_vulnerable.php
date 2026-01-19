<?php
// Список доверенных IP-адресов
$whitelist = [
    '127.0.0.1',      // Локальный адрес
    'хх.хх.хх.хх',  // Ваш домашний IP (измените на свой!)
];

// Получаем IP посетителя
$user_ip = $_SERVER['REMOTE_ADDR'];

// Проверяем наличие IP в белом списке
if (!in_array($user_ip, $whitelist)) {
    header('HTTP/1.0 403 Forbidden');
    die("Access Denied: Your IP ($user_ip) is not whitelisted.");
}

// Принудительно отключаем защитные заголовки, если Beget их ставит сам
header_remove("X-Powered-By"); 

// УСТАНОВКА ЦЕЛЕВОГО ТИПА (Но с риском MIME-Sniffing, если не настроено верно)
header('Content-Type: application/json; charset=utf-8');

// Имитация базы данных
$users = [
    ["id" => 1, "name" => "Admin", "secret" => "BGT_ADMIN_PASS_2026"],
    ["id" => 2, "name" => "Student", "secret" => "SPO_USER_KEY"]
];

$method = $_SERVER['REQUEST_METHOD'];

// ЛОГИКА GET: Уязвимость Reflected XSS
if ($method === 'GET') {
    $search = $_GET['search'] ?? '';
    echo json_encode([
        "status" => "active",
        "search_result" => $search // УЯЗВИМОСТЬ: Вывод без htmlspecialchars
    ], JSON_UNESCAPED_UNICODE);
}

// ЛОГИКА POST: Уязвимость Insecure API (утечка данных)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    // Отдаем данные админа по любому запросу - ошибка контроля доступа
    echo json_encode($users[0], JSON_UNESCAPED_UNICODE);
}
