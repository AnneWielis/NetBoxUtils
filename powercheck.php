<?php 
/** POWERCHECK
 * Check for missing / unconnected Power Ports
 * AnneWielis 20210707
 */
 
require("credentials.php");

set_error_handler("exception_error_handler");
ini_set('display_errors','true');

$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
$headers = array("Authorization: Token ".$token, "Accept: application/json; indent=4", "Content-Type: application/json");
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); 
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');

echo "*** Machines with no Power Ports\n";
curl_setopt($curl, CURLOPT_URL, $rootUrl."dcim/devices/?limit=0");
$return = curl_exec($curl);
$devices = json_decode($return);
if(isset($devices->count)) { 
	foreach ($devices->results as $device) {
		$total = array();
		// Search for Power Ports
		curl_setopt($curl, CURLOPT_URL, $rootUrl."dcim/power-ports/?q=&device_id=".$device->id);
		$return = curl_exec($curl);
		$outlets = json_decode($return);
		if (!in_array($device->device_role->id,$ignoredTypes)) {
			if ($outlets->count == 0) { //OK, we have no Power Outlets
				if (isset($device->rack->name)) {
					echo $device->rack->name.": ".$device->name."\n";
				}
				else {
					echo $device->name."\n";
				}
			}
		}
	}	
}

echo "*** Power Ports with no connection\n";
curl_setopt($curl, CURLOPT_URL, $rootUrl."dcim/power-ports/?limit=0");
$return = curl_exec($curl);
$ports = json_decode($return);
if(!isset($ports->count)) {
	print_r($ports);die();
}
foreach ($ports->results as $port) { // Loop through all the Ports
	if (!isset ($port->cable->id)) {
		echo $port->device->name." -> ".$port->name."\n";
	}
}

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}


?>
