<?php

declare(strict_types=1);

// Prosty "store" zastępczy — wypisuje każdy przychodzący request na konsolę serwera.
// Uruchamianie: php -S 127.0.0.1:9988 router.php
// Uwaga: w SAPI cli-server (php -S) stała STDERR jest niezdefiniowana — logujemy przez error_log().

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'POST' && $path === '/api/lifecycles') {
    $raw = (string) file_get_contents('php://input');
    $payload = json_decode($raw);

    if ($payload === null && $raw !== '') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'invalid-json']);
        error_log("POST /api/lifecycles  < BŁĘDNY JSON");
        exit;
    }

    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'accepted']);

    error_log(sprintf(
        "\n==== %s %s | X-Api-Key: %s ====\n%s",
        $method,
        $path,
        $_SERVER['HTTP_X_API_KEY'] ?? '(brak)',
        json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?? $raw,
    ));
    exit;
}

http_response_code(404);
error_log(sprintf("%s %s — nieobsługiwany", $method, $path));
