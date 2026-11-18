<?php
$pass = getenv('DB_PASS');
if ($pass === false) {
    $pass = '';
}

return [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => $pass,
    'db'   => getenv('DB_NAME') ?: 'db_printta_pos',
];
