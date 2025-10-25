<?php ini_set("display_errors",1);ini_set("display_startup_errors",1);error_reporting(E_ALL);require __DIR__."/helpers.php";require __DIR__."/config.php";echo "OK PHP
"; $pdo=db(); echo "OK DB
"; var_dump(get_columns("amortizations"));