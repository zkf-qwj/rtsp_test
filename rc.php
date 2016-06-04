<?php
ob_implicit_flush();
//socket_set_option($socket,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>1, "usec"=>100));

$url = empty($_GET['u']) ? (empty($argv[1]) ? false: $argv[1]) : $_GET['u'] ;

function getLine($n=1) {
    $fp = new SplFileObject(dirname(__FILE__).DIRECTORY_SEPARATOR.'rtsplist.txt', 'rb');
    $fp -> seek($n - 1);

    return trim(($fp -> current()), "\n");
}
if(empty($url))
{
$url = getLine(rand(1,1));
}
echo $url." ";


$waitTimeout = 2; // data wait timeout

if (preg_match('/endTime\=(\d+)/i', $url, $m)) {
	$timeout = (int)$m[1]-5;
} else {
	$timeout = 10;	// data download timeout
}

//$timeout = 60;


function trace($s, $n = "\n") {
	echo $s.$n;
	return 0;
}

function error($s) {
	echo 'ERROR: '.$s."\n";
	exit;
}

function output($s) {
	echo $s;
}

function connect(){
	$host = '172.16.188.88';
	$port = 800;
	$timeout = 10; // connection timeout


	$s = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($s < 0) {
		error("socket error: " . socket_strerror($s) );
	}
    

	//stream_set_timeout($s, 10);
	//$r = socket_connect($s, $host, $port);

	// $info = stream_get_meta_data($s);
	// if ($info['timed_out']) {
	// 	error('connection timed out!');
	// }
	
	$time = time();
	while (!@socket_connect($s, $host, $port)) {
		$err = socket_last_error($s);
		if ($err == 115 || $err == 114) {
			if ((time() - $time) >= $timeout){
				socket_close($s);
				error("Connection timed out.");
			}
			sleep(1);
			continue;
		}
		error( socket_strerror($err) );
	}

	// if ($r < 0) {
	// 	error("connect error: ($r) " . socket_strerror($r) );
	// }
	return $s;
}


function close($s) {
	socket_close($s);
}


function teardown($s, $c) {
	send($s, $c, false);
	close($s);
	output("SUCCESS\n");
}

function send($s, $c, $display = true) {
	//$c = str_replace("\n", "\r\n", $c);
	static $sess = '';

	global $c5;
	$check_http_code = true;
	$c = str_replace('{sess}', $sess, $c);
	//socket_read($s, 2048);
	socket_write($s, $c, strlen($c));



	trace("SEND:\n".$c);
	if (!$display)
		return;


	trace("RECIVED:");
	$r = '';

	$sTime = time();
	//socket_set_option($s1,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>6, "usec"=>100));

	while ($out = socket_read($s, 2048)) {
		$sTime = time();

		$r .= $out;
		
		trace($out, '');
		if ($check_http_code && preg_match('/RTSP\/1\.0\s*200/i', $r)) {
			$check_http_code = false;
		} else {
			if (preg_match('/RTSP\/1\.0\s*([^\r\n]*)/i', $r, $m)) {
				error($m[1]);
			} else {
				error($r);
			}
			
		}

		if (preg_match('/keystring\=([^\r\n]+)/i', $r, $ks)) {
                        $sess = $ks[1];
                }
		
		if ( preg_match('/Session\:\s*(\d+)/i',$r,$m) ) {
			$sess = $m[1];
		}

		if ( preg_match('/Content-length\:\s*(\d+)/i',$r,$m) ){
			//echo 'cl: '.$m[1];
			if ( preg_match('/\r\n\r\n([\w\W]*)/i', $r, $n) ) {
			//	echo ' --- con: '.strlen($n[1]);
				if ((int)$m[1] == strlen($n[1])) {
					return $r;
				}
			}
		} else if (preg_match('/\r\n\r\n/i', $r)){
			return $r;
		}

	}

	if (time()-$sTime > 5) {
		teardown($s, $c5);
		return error('wait data timeout.');
	}

	return $r;
};


//$url = "rtsp://192.168.5.102:554/ARTS/PT/vod-0ba32e46069de280888149975b08d6b29.ts";



$c1 = "DESCRIBE $url RTSP/1.0\r\nCSeq: 1\r\nAccept: application/sdp\r\nUser-Agent: Coship TS Client\r\nDate: 2014-02-25 04:42:07\r\nX-version: V3.0\r\n\r\n";

//$c2 = "SETUP $url RTSP/1.0\r\nCSeq: 2\r\nUser-Agent: Coship TS Client\r\nRange: npt=0-\r\nTransport: MP2T/H2221/TCP;unicast;areaCode=100\r\nDate: 2014-02-25 04:42:08\r\nX-version: V3.0\r\n\r\n";

$c2 = "SETUP $url RTSP/1.0\r\nCSeq: 2\r\nUser-Agent: Coship TS Client\r\nRange: npt=0-\r\nTransport: MP2T/UDP;unicast;client_port=51898-51899\r\n\r\n";

$c3 = "VALIDATE $url RTSP/1.0\r\nKeys: {sess}\r\nUser-Agent: Coship TS Client\r\nDate: 2014-02-25 04:42:08\r\nX-version: V3.0\r\n\r\n";

$c4 = "PLAY $url RTSP/1.0\r\nCSeq: 3\r\nSession: {sess}\r\nUser-Agent: Coship TS Client\r\nRange: npt=0-\r\nScale: 1.000000\r\nDate: 2014-02-25 04:42:30\r\n\r\n";
$c5 = "TEARDOWN $url RTSP/1.0\r\nCSeq:4\r\nSession: {sess}\r\nUser-Agent: Coship TS Client\r\nX-version: V3.0\r\n\r\n";


// ACTIONS

$s1 = connect();
send($s1, $c1);
send($s1, $c2);

//$s2 = connect();
//$r = send($s2, $c3);


send($s1, $c4);

$ts = time();
$tl = 0;


$waitTime = time();

//socket_set_option($s1,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>0, "usec"=>100));

//trace("RECIVE DATA\n");
while(true){
	$te = time();
	if ($ts+$timeout < $te) {
		trace('');
		teardown($s1, $c5);
		break;
	}

	usleep(200000);
	$o = socket_read($s1,2048);

	if($o)
       {
	trace('recv_len:'.strlen($o));
	}
	if (empty($waitTime)) {
		if (empty($o)) {
			$waitTime = time();
		}
	} else {
		if (empty($o)) {
			if ( time()-$waitTime>$waitTimeout ) 
				error('data wait timeout');
		} else {
			$waitTime = 0;
		}
	}


	$tl += strlen($o);
	if ($o) {
		trace(date('Y-m-d H:i:s').' '.strlen($o) );
		//trace( "\033[1A\033[50D".($te - $ts).'/'.$timeout.' '. $tl);
	}
}

//close($s1);
//close($s2);
