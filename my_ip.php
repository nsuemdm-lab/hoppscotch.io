<?php
header('Content-Type: application/json');
echo json_encode([
    "your_ip" => $_SERVER['REMOTE_ADDR'],
    "user_agent" => $_SERVER['HTTP_USER_AGENT']
]);