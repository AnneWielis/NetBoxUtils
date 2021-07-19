<?php 
/** POWERCALC
 * Recursively calculate Power usage for Netbox device chains 
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

curl_setopt($curl, CURLOPT_URL, $rootUrl."dcim/racks/");
$return = curl_exec($curl);
$racks = json_decode($return);
if(!isset($racks->count)) {
	print_r($racks);die();
}
foreach ($racks->results as $rack) { // Let's see what's in the Racks
	echo "***".$rack->name."\n";
	curl_setopt($curl, CURLOPT_URL, $rootUrl."dcim/devices/?q=&rack_id=".$rack->id);
	$return = curl_exec($curl);
	$devices = json_decode($return);
	if(isset($devices->count)) { // OK, so we got devices?
		foreach ($devices->results as $device) {
			$total = array();
			// Search for Power Outlets 
			curl_setopt($curl, CURLOPT_URL, $rootUrl."dcim/power-outlets/?q=&device_id=".$device->id);
			$return = curl_exec($curl);
			$outlets = json_decode($return);
			if ($outlets->count > 0) { //OK, we do have Power Outlets
				foreach ($outlets->results as $outlet) {
					if(!is_null($outlet->cable_peer)) { // Is there a cable plugged in?
						$powerData = checkRecursivePower($outlet->cable_peer,1); // Actually we don't need the return as we don't save anything on top level devices
					}					
				}
			}
		}	
	}
}

function checkRecursivePower($peer, $level=0) {
	global $curl, $rootUrl;
	$total = array('max'=>0,'alloc'=>0);
	
	// Do we have Power Outlets on this Device's peer?
	curl_setopt($curl, CURLOPT_URL, $rootUrl."dcim/power-outlets/?q=&device_id=".$peer->device->id);
	$return = curl_exec($curl);
	$outlets = json_decode($return);
	if ($outlets->count > 0) { //OK, we do have Power Outlers
		echo $peer->device->name."\n";
		foreach ($outlets->results as $outlet) {
			if (!isset($outlet->power_port->id) || ($outlet->power_port->id == $peer->id)) { // Either the right power port is assigned or none at all (fallback)
				if(!is_null($outlet->cable_peer)) { // Is there a cable plugged in?
					$powerData = checkRecursivePower($outlet->cable_peer, $level+1);
					if (!is_null($powerData)) { // Received power data? Then sum up now
						$total['max'] += $powerData['max'];
						$total['alloc'] += $powerData['alloc'];
					}
				}
			}			
		}
		if ($total['max'] > 0) { // There is power data, save now
			curl_setopt($curl, CURLOPT_URL, $peer->url); // Load port to get data to save
			$return = curl_exec($curl);
			$peerPort = json_decode($return);
			if (($total['max'] != $peerPort->maximum_draw) || ($total['alloc'] != $peerPort->allocated_draw)) { // Save only if data has changed
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
				curl_setopt($curl, CURLOPT_URL, $peer->url);
				$newdata = array();
				$newdata['device'] = $peer->device->id;
				$newdata ['name'] = $peer->name;
				$newdata['maximum_draw'] = $total['max'];
				$newdata['allocated_draw'] = $total['alloc'];
				
				curl_setopt($curl, CURLOPT_POSTFIELDS,json_encode($newdata));
				$response = curl_exec($curl);
				
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET'); // Clear up
			}
		}
				
				
	}
	else { // No Power Outlets, just return stored Power Info
		if($level >0) {
			curl_setopt($curl, CURLOPT_URL, $peer->url); // Load matching port
			$return = curl_exec($curl);
			$peerPort = json_decode($return);
			$total['max'] = $peerPort->maximum_draw;
			$total['alloc'] = $peerPort->allocated_draw;
		}
	}	
	return $total;
}
		
function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}


?>
