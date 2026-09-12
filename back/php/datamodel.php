<?php
    session_start();
	
	header("Access-Control-Allow-Origin: *");
	header("Access-Control-Allow-Credentials: true");
	header("Access-Control-Max-Age: 1000");
	header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Cache-Control, Pragma, Authorization, Accept, Accept-Encoding");
	header("Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS, DELETE");
	header('Content-Type: text/html; charset=utf-8');
	
	error_reporting(0);

	require_once "DB.class.php";
	
	global $db;
	
	$db = new DB();

    function echoResult($result) {
        echo json_encode($result);
    }
?>