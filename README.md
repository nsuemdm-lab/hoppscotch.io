# МЕТОДИЧЕСКОЕ ПОСОБИЕ
**Дисциплина:** МДК.09.03 Обеспечение безопасности веб-приложений  
**Тема:** Полный цикл аудита безопасности серверной части (Backend Audit)  
**Инструментарий:** Beget Hosting, Hoppscotch.io, PHP 8.x  

---

## Этап 1. Подготовка серверной среды (Beget)

Перед развертыванием кода необходимо убедиться, что сервер настроен согласно современным стандартам (или намеренно ослабить их для теста).

1.  **Настройка версии PHP:**
    *   Зайдите в панель Beget -> раздел **«Сайты»**.
    *   Выберите ваш сайт -> кнопка **«Настройка сайта»** (шестеренка).
    *   Установите версию **PHP 8.2** или **8.3**. Это критично для корректной работы современных функций обработки данных.
2.  **Работа с логами (Диагностика):**
    *   В случае ошибки 500 (Internal Server Error) перейдите в раздел **«Логи» -> «Журнал ошибок»**.
    *   Изучите последние записи. Ошибка `Parse error` означает опечатку в коде, `Fatal error` — сбой при исполнении.
3.  **Права доступа:**
    *   Через Файловый менеджер убедитесь, что на файлы установлены права **644**, на папки — **755**.

---

## Этап 2. Создание тестового полигона

Создайте в корневой папке (`public_html`) файл `api_vulnerable.php`. Этот код специально написан с типичными уязвимостями для учебного аудита.

**Код файла `api_vulnerable.php`:**
```php
<?php
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
```

***Код файла `my_ip.php`:**

```php
<?php
header('Content-Type: application/json');
echo json_encode([
    "your_ip" => $_SERVER['REMOTE_ADDR'],
    "user_agent" => $_SERVER['HTTP_USER_AGENT']
]);
```

Включение нашего IP в “белый список”:

```php
// Список доверенных IP-адресов
$whitelist = [
    '127.0.0.1',      // Локальный адрес
    'ХХ.ХХ.ХХ.ХХ',  // Ваш домашний IP (измените на свой!)
];
// Получаем IP посетителя
$user_ip = $_SERVER['REMOTE_ADDR'];
// Проверяем наличие IP в белом списке
if (!in_array($user_ip, $whitelist)) {
    header('HTTP/1.0 403 Forbidden');
    die("Access Denied: Your IP ($user_ip) is not whitelisted.");
}
```
---

## Этап 3. Настройка Hoppscotch.io

Поскольку ваш сайт работает по **HTTP**, а Hoppscotch по **HTTPS**, браузер будет блокировать запросы (Mixed Content).

1.  **Установка Interceptor:** Установите расширение **Hoppscotch Browser Extension**.
2.  **Активация:** В Hoppscotch перейдите в `Settings` -> `Interceptor` -> выберите `Extension`. 
3.  **Альтернатива:** Если расширение недоступно, включите `Proxy` в тех же настройках.

---

## Этап 4. Проведение запросов и расшифровка

### Запрос №1: Базовый аудит заголовков (Header Analysis)
*   **Метод:** `GET`
*   **URL:** `http://ваш-логин.beget.tech/api_vulnerable.php`
*   **Действие:** Нажать **Send**.

**Расшифровка полученных заголовков:**
*   `server: nginx-reuseport/1.21.1` — **Уязвимость (Information Disclosure)**. Раскрытие версии сервера позволяет хакеру искать готовые эксплойты.
*   `content-type: text/html` (вместо JSON) — **Ошибка конфигурации**. Признак того, что сервер выдает ошибку или PHP не смог отправить заголовок.
*   `X-Frame-Options` (отсутствует) — **Уязвимость (Clickjacking)**. Сайт можно встроить в чужой фрейм.
*   `Content-Security-Policy` (отсутствует) — **Критическая уязвимость**. Нет защиты от внедрения скриптов.

### Запрос №2: Тест на XSS (Межсайтовый скриптинг)
*   **URL:** `.../api_vulnerable.php?search=<script>alert('XSS')</script>`
*   **Анализ Body:** Если в ответе вы видите тег `<script>` без изменений — приложение уязвимо. Скрипт исполнится в браузере жертвы.

### Запрос №3: Тест POST (Утечка конфиденциальных данных)
*   **Метод:** `POST`
*   **Body (JSON):** `{"user_id": 2}`
*   **Анализ:** Если сервер вернул данные `Admin` (id: 1), хотя мы просили студента (id: 2) — это уязвимость **BOLA (Broken Object Level Authorization)**.

---

## Этап 5. Исправление (Remediation)

Чтобы закрыть уязвимости, замените код в `api_vulnerable.php` на защищенный:

1.  **Защита вывода:** `htmlspecialchars($search, ENT_QUOTES, 'UTF-8')`.
2.  **Защита заголовков:**
    ```php
    header("Content-Security-Policy: default-src 'self'");
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header_remove("X-Powered-By"); 
    ```

---

## Чек-лист для отчета (студенту)
1. [ ] Скриншот из Beget с версией PHP 8.x.
2. [ ] Скриншот Hoppscotch с вкладкой **Headers** (версия Nginx).
3. [ ] Скриншот Hoppscotch с вкладкой **Body** (подтверждение XSS).
4. [ ] Описание риска: «Раскрытие версии ПО упрощает подготовку атаки».

**Методический вывод:** Использование Hoppscotch в паре с Beget позволяет имитировать полный цикл работы ИБ-специалиста: от обнаружения ошибки конфигурации до верификации исправленной уязвимости.
