<?php

if (file_exists('env'))
    include 'env';

defined('JWT_SALT') OR define('JWT_SALT', isset($_SERVER['JWT_SALT']) ? $_SERVER['JWT_SALT'] : 'bhawanaerp');
defined('COMPANY') OR define('COMPANY', isset($_SERVER['COMPANY']) ? $_SERVER['COMPANY'] : 'BHAWANA');
defined('MODUL') OR define('MODUL', isset($_SERVER['MODUL']) ? $_SERVER['MODUL'] : 'MODUL');
defined('ENVIRONMENT') OR define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'development');
