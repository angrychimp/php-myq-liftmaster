#!/usr/bin/php
<?php
function get_help() {
	global $argv;
	$program = basename($argv[0]);
	print "Send commands to MyQ enabled devices on your network.\n";
	print "usage: {$program} <open|close|on|off|status|help> [name=\"device name\"|id=#]\n";
	print "\tDefaults to showing the status results.\n\n";
	print "\tUse open/close for garage door units.\n";
	print "\tUse on/off for lamp/light modues.\n";
}

function gmt_to_local_time($date) {
	if(is_numeric($date))
		$date = date("Y-m-d H:i:s",$date);

	$dt = new DateTime($date, new DateTimeZone('UTC'));
	$dt->setTimezone(new DateTimeZone('America/Chicago'));
	return  $dt->format('Y-m-d H:i:s');
}


$action = 'status';
$doorname = 'light';

$config_file = "config.ini";

function report($MyQ,$name=false) {

	foreach($MyQ->refresh()->_myDevices as $device_type => $atom) {
		foreach($atom as $id => $thisone) {
			#print_r($thisone);
			if($device_type == 'GarageDoorOpener')
				$device_type = str_pad("Garage",8);
			elseif($device_type == 'LampModule')
				$device_type = str_pad("Light",8);

			if( ( ($name) && ($name == $thisone['desc']) ) || (!$name) ) {
				$time = round(($thisone['deviceState']['timestamp']) / 1000);
				$time = gmt_to_local_time(intval($time));
				$time = date("M d, g:i a",strtotime($time));

				$myDeviceId = "ID:" . str_pad($thisone['MyQDeviceId'],8);
				$desc = str_pad("({$thisone['desc']})",18);

				$deviceState = str_pad(ucfirst($thisone['deviceState']['state']),8);

				print "\t{$device_type}  - {$myDeviceId} - {$desc} - {$deviceState} - {$time}\n";
			}
		}


		#print_r($MyQ->_headers);
	}

};

require_once('MyQ.php');

if(!file_exists($config_file)) {
	die("Error finding: {$config_file}\n");
}

$conf = parse_ini_file($config_file);

if(empty($conf['username'])) {
	die("Error: missing config.ini variable: username\n");
}

if(empty($conf['password'])) {
	die("Error: missing config.ini variable: password\n");
}

$name = false;

foreach($argv as $thisone) {

	if(strstr($thisone,'name=')) {
		$name = str_replace("name=","",$thisone);
	} elseif(strstr($thisone,'id=')) {
		$name = str_replace("id=","",$thisone);
	} else {
		if(substr($thisone,0,2) == '--')
			$thisone = substr($thisone,2);
		elseif(substr($thisone,0,1) == '-')
			$thisone = substr($thisone,1);

		switch($thisone) {
			case 'status':
			case 'open':
			case 'close':
			case 'on':
			case 'off':
			case 'of':
				$action = $thisone;
				break;
			case "h":
			case "help":
			case "help":
			case "?":
				die(get_help());
		}
	}
}

$MyQ = new MyQ();
$MyQ->login($conf['username'], $conf['password']);

try {
	switch($action) {
		case 'on':
		case 'off':
		case 'open':
		case 'close':
			$MyQ->$action($name);
			break;
		default:
			$RC = report($MyQ,$name);
			break;
	}
} catch(Exception $e) {
	die($e->getMessage() . "\n");
}
