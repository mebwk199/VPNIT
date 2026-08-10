<?php
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Installer disabled. Database setup is handled automatically by Railway startup.";
