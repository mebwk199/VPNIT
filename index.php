<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'status' => 'ok',
  'service' => 'itVPN Telegram Bot',
  'time' => date('c')
], JSON_UNESCAPED_UNICODE);
