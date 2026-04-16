<?php
header('Content-Type: application/json');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== 'replace-with-a-long-random-secret') {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorised']);
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid id']);
    exit;
}

$mysqli = new mysqli(
    'localhost',
    'cpaneluser_dbuser',
    'db_password_here',
    'cpaneluser_appdb'
);

if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'database connection failed']);
    exit;
}

$stmt = $mysqli->prepare('SELECT id, name, email FROM customers WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode($row ?: ['error' => 'not found']);