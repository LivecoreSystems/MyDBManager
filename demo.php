<?php 
include ('myDBManager.php');

try {

	$myDBManager = new myDBManager(array(
		'host' => '',
		'username' => 'root',
		'password' => '',
		'database'=>'db_name'
	));

	$myDBManager->useExec();
	$myDBManager->exportDatabase("myDBManager_2.sql");


} catch(myDBManager_Exception $e) {
	echo "Couldn't export database: " . $e->getMessage();
}