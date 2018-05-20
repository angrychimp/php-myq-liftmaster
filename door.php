<?php

require_once('MyQ.php');
$conf = parse_ini_file('config.ini');

$door = new MyQ();
$door->login($conf['username'], $conf['password']);

$report = function() {
    global $door;
    echo "{$door->refresh()->state} as of {$door->state->delta} ago\n";
};

// Current state
echo $report();

// Open the door
$door->open();
sleep(2);
echo $report();

// Wait for a few seconds and close the door again
// (my door takes about 15-20 sec to open)
sleep(15);
$door->close = true;
sleep(10);
echo $report();
