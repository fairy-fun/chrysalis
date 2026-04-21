<?php
declare(strict_types=1);

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');

echo "Not Found\n";
echo "No public site is available at this address.\n";