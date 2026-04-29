<?php
// Simple healthcheck — bypasses Laravel bootstrap
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'timestamp' => time()]);
