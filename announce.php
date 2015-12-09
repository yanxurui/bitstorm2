<?php
/*
* Bitstorm - A small and fast Bittorrent tracker
* Copyright 2015 Yan XuRui
*/

//Enable debugging?
//This allows anyone to see the entire peer database by opening the announce URL in broswer
define('__DEBUGGING_ON', true);

//How often should clients pull server for new clients? (Seconds)
define('__INTERVAL', 1800);

//What's the minimum interval a client may pull the server? (Seconds)
//Some bittorrent clients does not obey this
define('__INTERVAL_MIN', 300);

//How long should we wait for a client to re-announce after the last
//announce expires? (Seconds)
define('__CLIENT_TIMEOUT', 60);

//loading the predis library
require 'vendor/autoload.php';
$r = new Predis\Client();

//Send response as text
header('Content-type: Text/Plain');

//Bencoding function, returns a bencoded dictionary
function track($list,$complete=0,$incomplete=0) {
	if (is_string($list)) { //Did we get a string? Return an error to the client
		return 'd14:failure reason'.strlen($list).':'.$list.'e';
	}
	$p = ''; //Peer directory
	foreach($list as $peer_id=>$d) { //Runs for each client

		//Do some bencoding
		$pid = '';
		if(!$_GET['no_peer_id']) { //Shall we include the peer id
			$pid = '7:peer id'.strlen($peer_id).':'.$peer_id;
		}
		$p .= 'd2:ip'.strlen($d[0]).':'.$d[0].$pid.'4:porti'.$d[1].'ee';
	}
	//Add some other paramters in the dictionary and merge with peer list
	$r = 'd8:intervali'.__INTERVAL.'e12:min intervali'.__INTERVAL_MIN.'e8:completei'.$complete.'e10:incompletei'.$incomplete.'e5:peersl'.$p.'ee';
	return $r;
}

//Did we get any parameters at all?
//Client is  probably a web browser, do a redirect
if (empty($_GET)) 
{
	header('Location: stats.php');
	exit;
}

//Do some input validation
function valdata($g, $must_be_20_chars=false) {
	if (!isset($_GET[$g])) {
		die(track("Missing key: $g"));
	}
	if (!is_string($_GET[$g])) {
		die(track('Invalid types on one or more arguments'));
	}
	if ($must_be_20_chars && strlen($_GET[$g]) != 20) {
		die(track('Invalid length on '.$g.' argument'));
	}
	if (strlen($_GET[$g]) > 128) { //128 chars should really be enough
		die(track('Argument '.$g.' is too large to handle'));
	}
	return $_GET[$g];
}

//Inputs that are needed, do not continue without these
$peer_id=valdata('peer_id', true);
$info_hash=valdata('info_hash', true);
$port=valdata('port');

//Do we have a valid client port?
if (!ctype_digit($_GET['port']) || $_GET['port'] < 1 || $_GET['port'] > 65535) {
	die(track('Invalid client port'));
}

//Number of peers that the client would like to receive from the tracker.
$numwant=50;
if(isset($_GET['numwant']))
	$numwant=0+$_GET['numwant'];

//Find out if we are seeding or not. Assume not if unknown.
$is_seed = isset($_GET['left'])&&$_GET['left'] == 0? true:false;

//Get IP
$ip=$_SERVER['REMOTE_ADDR'];
if(ip2long($ip))
	$ip4=$ip;
else
	$ip6=$ip;

//Did the client stop the torrent?
//We dont care about other events
if (isset($_GET['event']) && $_GET['event'] === 'stopped') {
	$r->srem($info_hash,$peer_id);
	die(track(array())); //The RFC says its OK to return whatever we want when the client stops downloading,
                             //however, some clients will complain about the tracker not working, hence we return
                             //an empty bencoded peer list
}

//Update information of this peer and get all the other peers downloading the same file.
$map=$info_hash.':'.$peer_id;
$r->sadd('torrents',$info_hash);
$r->sadd($info_hash,$peer_id);
$pid_list=$r->smembers($info_hash);
if(isset($ip4))
	$r->hmset($map,'ip4',$ip4,'port',$port,'seed',$is_seed);
else
	$r->hmset($map,'ip6',$ip6,'port',$port,'seed',$is_seed);
$r->expire($map,__INTERVAL+__CLIENT_TIMEOUT);

$peers=array();
$i=$s=$l=0;
foreach($pid_list as $pid)
{
	if($pid==$peer_id)
		continue;
	$temp=$r->hmget($info_hash.':'.$pid,'ip4','ip6','port','seed');
	if(!$temp[0]&&!$temp[1])//Remove the peer infomation if it's expired
		$r->srem($info_hash,$pid);
	else
	{
		if($temp[3])
			$s++;
		else
			$l++;
		if($i<$numwant)
		{
			if($temp[3]&&$is_seed)//Don't report seeds to other seeders
				continue;
			else
			{
				if(isset($ip4)&&$temp[0])
					$peers[$pid]=array($temp[0],$temp[2]);
				else if(isset($ip6)&&$temp[1])
					$peers[$pid]=array($temp[1],$temp[2]);
				else continue;
				$i++;
			}
		}
	}
}
//Add myself
if($is_seed)
	$s++;
else $l++;
//Bencode the dictionary and send it back
echo(track($peers,$s,$l));
?>