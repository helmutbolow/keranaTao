<?php
// Config via env
return [
  'db' => [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'name' => getenv('DB_NAME') ?: 'kerana',
    'user' => getenv('DB_USER') ?: 'kerana',
    'pass' => getenv('DB_PASS') ?: 'kerana',
  ],
  'app' => [
    'title' => 'Kerana Admin',
  ]
];
