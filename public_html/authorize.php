<?php

$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: /pecherie/mcp/index.php/authorize?' . $query, true, 302);
exit;