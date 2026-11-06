<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => ['code' => 'not_found', 'message' => 'No such endpoint.']]), "\n";
