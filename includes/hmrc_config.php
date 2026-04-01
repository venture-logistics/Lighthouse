<?php
$env = parse_ini_file(__DIR__ . '/../.env');

define('HMRC_CLIENT_ID',     $env['HMRC_CLIENT_ID']);
define('HMRC_CLIENT_SECRET', $env['HMRC_CLIENT_SECRET']);
define('HMRC_REDIRECT_URI',  $env['HMRC_REDIRECT_URI']);
define('HMRC_BASE_URL',      $env['HMRC_BASE_URL']);
define('HMRC_AUTH_URL',      $env['HMRC_AUTH_URL']);