<?php

$DATABASE_ENV = array(
        'dsn'   => '',                                          'hostname' => 'localhost',
        'username' => 'ajbuserdb',                     'password' => 'examplepas',
        'database' => 'exampledb',                     'dbdriver' => 'postgre',
        'dbprefix' => '',                                       'pconnect' => FALSE,
        'db_debug' => FALSE,                            'cache_on' => FALSE,
        'cachedir' => '',                                       'char_set' => 'utf8',
        'dbcollat' => 'utf8_general_ci',        'swap_pre' => '',
        'encrypt' => FALSE,                                     'compress' => FALSE,
        'stricton' => FALSE,                            'failover' => array(),
        'save_queries' => TRUE,                         'port' => 5432,
);
$REFRESH_BY_SWAPPING = false;
include("_queueaction.php");

// find . -name "*.php"| while read fname; do
//   if [[ "$fname" != "./_queueaction.php" ]]
//   then
//    nohup php "$fname" >> "${fname/php/log}" &
//    sleep .3
//   fi
// done

// # nohup php livemgp.php