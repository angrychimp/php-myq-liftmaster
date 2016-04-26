<?php

require_once('MyQ.php');
$conf = parse_ini_file('config.ini');

$door = new MyQ();
$door->login($conf['username'], $conf['password']);
$door->getState();