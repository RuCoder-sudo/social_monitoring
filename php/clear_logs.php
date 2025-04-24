<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'true') {
    if (isset($_POST['clear_logs']) && $_POST['clear_logs'] === 'true') {
        $logFile = 'logs.txt'; // Исправленный путь к файлу логов
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
            echo json_encode(['status' => 'logs_cleared']);
        } else {
            echo json_encode(['status' => 'log_file_not_found']);
        }
    } else {
        echo json_encode(['status' => 'invalid_request']);
    }
} else {
    echo json_encode(['status' => 'invalid_request']);
}
?>