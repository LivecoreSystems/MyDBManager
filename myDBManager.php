<?php 
/**
 * myDBManager Package.
 * PHP Version 4+
 * @package myDBManager
 * @link https://github.com/livecore/myDBManager/ The myDBManager GitHub project
 * @author Gichimu Muhoro (Sisto) <sisto@livecore.co.ke>
 * @author Livecore Systems (Livecore) <info@livecore.co.ke>
 * @copyright 2018
 * @version 1.1.0
 * @license  Copyright (c) 2018, Gichimu Sisto Muhoro for Livecore Systems  
	livecore.co.ke
	sisto@livecore.co.ke
	All rights reserved.  

	Redistribution and use in source and binary forms, with or without modification,
	are permitted provided that the following conditions are met:

	* Redistributions of source code must retain the above copyright notice, this
	  list of conditions and the following disclaimer.

	* Redistributions in binary form must reproduce the above copyright notice, this
	  list of conditions and the following disclaimer in the documentation and/or
	  other materials provided with the distribution.

	* Neither the names of Gichimu Sisto or Livecore Systems nor the names of its
	  contributors may be used to endorse or promote products derived from
	  this software without specific prior written permission.

	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
	ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
	WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
	DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
	ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
	(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
	ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
	(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
	SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

 class myDBManager {
	/**
	 * Maximum length of single insert statement
	 */
	const INSERT_THRESHOLD = 838860;
	
	/**
	 * @var myDBManagerHandler_DBConn
	 */	
	public $db;

	/**
	 * @var myDBManagerHandler
	 */
	public $export_file;

	/**
	 * End of line style used in the export
	 */
	public $eol = "\r\n";

	/**
	 * Specificed tables to include
	 */
	public $include_tables;

	/**
	 * Specified tables to exclude
	 */
	public $exclude_tables = array();

	/**
	 * Factory method for exporter on current hosts's configuration. 
	 */
	public $DBOptions=array();
	/**
	 * Current connected Database. 
	 */
	public static $useExec=false;
	public static $database;

	/**
	 * Checks if a new database has been created. 
	 */
	public static $isNewDatabase;

	function __construct($db_options) {
		$this->DBOptions=$db_options;
		$database='';
		if(isset($db_options['database'])){$this->database=$database=$db_options['database'];}
		return $this->connect($database);
	}

	function connect($database='') {
		$this->db = myDBManagerHandler_DBConn::create($this->DBOptions);
		$this->db->connect();
		if(!empty($database)){$this->db->selectDatabase($database);}
		return true;
	}

	function useExec(){
		self::$useExec=true;
	}

	function createDatabase($db){
		self::$isNewDatabase=true;
		self::$database=$db;
		return $this->db->createDatabase($db);
	}

	function dropDatabase($db=''){
		if(self::dbExists($db)){
			return $this->db->dropDatabase($db);
		}
		return false;
	}

	function exportDatabase($exportPath, $tablePrefix='') {
		if(self::$useExec){
			$exporter = new execHandler($this->DBOptions);
			return $exporter->createExport($exportPath);
		}else{
			if (self::has_shell_access() 
				&& self::is_shell_command_available('mysqldump')
				&& self::is_shell_command_available('gzip')
			) {
			$exporter = new myDBManager_ShellCommand($this->DBOptions);
			} else {
				//die();
				$exporter = new myDBManager_Native($this->DBOptions);
			}

			if (isset($this->DBOptions['include_tables'])) {
				$exporter->include_tables = $this->DBOptions['include_tables'];
			}
			if (isset($this->DBOptions['exclude_tables'])) {
				$exporter->exclude_tables = $this->DBOptions['exclude_tables'];
			}
			return $exporter->createExport($exportPath, $tablePrefix);
		}
	}

	function importDatabase($importPath, $options=array()) {
		if(self::$useExec){
			$importer = new execHandler($this->DBOptions);
			return $importer->createImport($importPath);
		}else{
			$importer = new myDBManagerImporter($this->DBOptions);
			return $importer->createImport($importPath, $options);
		}
	}

	public function has_shell_access() {
		if (!is_callable('shell_exec')) {
			return false;
		}
		$disabled_functions = ini_get('disable_functions');
		return stripos($disabled_functions, 'shell_exec') === false;
	}

	public function is_shell_command_available($command) {
		if (preg_match('~win~i', PHP_OS)) {
			/*
			On Windows, the `where` command checks for availabilty in PATH. According
			to the manual(`where /?`), there is quiet mode: 
			....
			    /Q       Returns only the exit code, without displaying the list
			             of matched files. (Quiet mode)
			....
			*/
			$output = array();
			exec('where /Q ' . $command, $output, $return_val);

			if (intval($return_val) === 1) {
				return false;
			} else {
				return true;
			}

		} else {
			$last_line = exec('which ' . $command);
			$last_line = trim($last_line);

			// Whenever there is at least one line in the output, 
			// it should be the path to the executable
			if (empty($last_line)) {
				return false;
			} else {
				return true;
			}
		}
		
	}

	protected function get_tables($table_prefix) {
		if (!empty($this->include_tables)) {
			return $this->include_tables;
		}
		
		// $tables will only include the tables and not views.
		// TODO - Handle views also, edits to be made in function 'get_create_table_sql' line 336
		$tables = $this->db->fetch_numeric('
			SHOW FULL TABLES WHERE Table_Type = "BASE TABLE" AND Tables_in_'.$this->db->database.' LIKE "' . $this->db->escape_like($table_prefix) . '%"
		');

		$tables_list = array();
		foreach ($tables as $table_row) {
			$table_name = $table_row[0];
			if (!in_array($table_name, $this->exclude_tables)) {
				$tables_list[] = $table_name;
			}
		}
		return $tables_list;
	}
	function dbExists($db){
		try {
			$this->db->selectDatabase($db);
			return true;
		} catch (myDBManager_Exception $e) {
			return false;
		}
	}
}

/**
 * Abstract export file: provides common interface for writing
 * data to export files. 
 */
abstract class myDBManagerHandler {
	/**
	 * File Handle
	 */
	protected $fh;

	/**
	 * Location of the export file on the disk
	 */
	protected $file_location;

	abstract function write($string);
	abstract function end();

	static function create($filename) {
		if (self::isArchive($filename)) {
			return new myDBManagerHandler_Archive($filename);
		}
		return new myDBManagerHandler_Plaintext($filename);
	}
	function __construct($file) {
		$this->file_location = $file;
		$this->fh = $this->open();

		if (!$this->fh) {
			throw new myDBManager_Exception("Couldn't create gz file");
		}
	}

	public static function isArchive($filename) {
		return preg_match('~gz$~i', $filename);
	}	
}

/**
 * Plain text implementation. Uses standard file functions in PHP. 
 */
class myDBManagerHandler_Plaintext extends myDBManagerHandler {
	function open() {
		return fopen($this->file_location, 'w');
	}
	function write($string) {
		return fwrite($this->fh, $string);
	}
	function end() {
		return fclose($this->fh);
	}
}

/**
 * Gzip implementation. Uses gz* functions. 
 */
class myDBManagerHandler_Archive extends myDBManagerHandler {
	function open() {
		return gzopen($this->file_location, 'wb9');
	}
	function write($string) {
		return gzwrite($this->fh, $string);
	}
	function end() {
		return gzclose($this->fh);
	}
}

/**
 * MySQL insert statement builder. 
 */
class myDBManagerHandler_Insert_Statement {
	private $rows = array();
	private $length = 0;
	private $table;

	function __construct($table) {
		$this->table = $table;
	}

	function reset() {
		$this->rows = array();
		$this->length = 0;
	}

	function add_row($row) {
		$row = '(' . implode(",", $row) . ')';
		$this->rows[] = $row;
		$this->length += strlen($row);
	}

	function get_sql() {
		if (empty($this->rows)) {
			return false;
		}

		return 'INSERT INTO `' . $this->table . '` VALUES ' . 
			implode(",\n", $this->rows) . '; ';
	}

	function get_length() {
		return $this->length;
	}
}

class myDBManager_ShellCommand extends myDBManager {
	function createExport($export_file_location, $table_prefix='') {
		$command = 'mysqldump -h ' . escapeshellarg($this->db->host) .
			' -u ' . escapeshellarg($this->db->username) . 
			' --password=' . escapeshellarg($this->db->password) . 
			' ' . escapeshellarg($this->db->name);

		$include_all_tables = empty($table_prefix) &&
			empty($this->include_tables) &&
			empty($this->exclude_tables);

		if (!$include_all_tables) {
			$tables = $this->get_tables($table_prefix);
			$command .= ' ' . implode(' ', array_map('escapeshellarg', $tables));
		}

		$error_file = tempnam(sys_get_temp_dir(), 'err');

		$command .= ' 2> ' . escapeshellarg($error_file);

		if (myDBManagerHandler::isArchive($export_file_location)) {
			$command .= ' | gzip';
		}

		$command .= ' > ' . escapeshellarg($export_file_location);

		exec($command, $output, $return_val);

		if ($return_val !== 0) {
			$error_text = file_get_contents($error_file);
			unlink($error_file);
			throw new myDBManager_Exception('Couldn\'t export database: ' . $error_text);
		}

		unlink($error_file);
	}
}

class myDBManager_Native extends myDBManager {
	public function createExport($export_file_location, $table_prefix) {
		$eol = $this->eol;

		$this->export_file = myDBManagerHandler::create($export_file_location);

		$this->export_file->write("-- Generation time: " . date('r') . $eol);
		$this->export_file->write("-- Host: " . $this->db->host . $eol);
		$this->export_file->write("-- DB name: " . $this->db->database . $eol);
		$this->export_file->write("/*!40030 SET NAMES UTF8 */;$eol");
		
		$this->export_file->write("/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;$eol");
		$this->export_file->write("/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;$eol");
		$this->export_file->write("/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;$eol");
		$this->export_file->write("/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;$eol");
		$this->export_file->write("/*!40103 SET TIME_ZONE='+00:00' */;$eol");
		$this->export_file->write("/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;$eol");
		$this->export_file->write("/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;$eol");
		$this->export_file->write("/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;$eol");
		$this->export_file->write("/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;$eol$eol");


		$tables = $this->get_tables($table_prefix);
		foreach ($tables as $table) {
			$this->export_table($table);
		}
		
		$this->export_file->write("$eol$eol");
		$this->export_file->write("/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;$eol");
		$this->export_file->write("/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;$eol");
		$this->export_file->write("/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;$eol");
		$this->export_file->write("/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;$eol");
		$this->export_file->write("/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;$eol");
		$this->export_file->write("/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;$eol");
		$this->export_file->write("/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;$eol$eol");

		unset($this->export_file);
		return true;
	}

	protected function export_table($table) {
		$eol = $this->eol;

		$this->export_file->write("DROP TABLE IF EXISTS `$table`;$eol");

		$create_table_sql = $this->get_create_table_sql($table);
		$this->export_file->write($create_table_sql . $eol . $eol);

		$data = $this->db->query("SELECT * FROM `$table`");

		$insert = new myDBManagerHandler_Insert_Statement($table);

		while ($row = $this->db->fetch_row($data)) {
			$row_values = array();
			foreach ($row as $value) {
				$row_values[] = $this->db->escape($value);
			}
			$insert->add_row( $row_values );

			if ($insert->get_length() > self::INSERT_THRESHOLD) {
				// The insert got too big: write the SQL and create
				// new insert statement
				$this->export_file->write($insert->get_sql() . $eol);
				$insert->reset();
			}
		}

		$sql = $insert->get_sql();
		if ($sql) {
			$this->export_file->write($insert->get_sql() . $eol);
		}
		$this->export_file->write($eol . $eol);
	}
	
	public function get_create_table_sql($table) {
		$create_table_sql = $this->db->fetch('SHOW CREATE TABLE `' . $table . '`');
		return $create_table_sql[0]['Create Table'] . ';';
	}
}

class myDBManagerImporter extends myDBManager{
	function createImport($path,$options){
		if(parent::$isNewDatabase){
			$this->db->selectDatabase(parent::$database);
			$this->db->database=parent::$database;
		}

		$tempLine='';
		$newDatabase=false;
		$database='';
		$lines=file($path);
		if(isset($options["target"])){
			$this->db->selectDatabase($options["target"]);
		}
		if(isset($options["drop"])){
			$this->db->dropDatabase($options["drop"]);
		}

		if(isset($options["create"])){
			self::$isNewDatabase=true;
			self::$database=$options["create"];
			$this->db->createDatabase($options["create"]);
			$this->db->selectDatabase($options["create"]);
			$newDatabase=true;
			$database=$options["create"];
		}

		if(isset($options["replace"])){
			if(self::dbExists($options["replace"])){
				$this->db->dropDatabase($options["replace"]);
			}
			$this->db->createDatabase($options["replace"]);
			$this->db->selectDatabase($options["replace"]);
			$newDatabase=true;
			$database=$options["replace"];
		}
    	
		foreach ($lines as $line) {
			if(substr($line, 0,2)=="--"||substr($line, 0,2)=="/*"||$line=='')
				continue;
			$tempLine.=$line;
			if(substr(trim($line), -1,1)==';'){
				try {
					$this->db->query($tempLine);
					$tempLine="";
				} catch(myDBManager_Exception $e) {
					if($newDatabase){$this->db->dropDatabase($database);}
					throw new myDBManager_Exception($e->getMessage());
				}
			}
		}
		return true;
	}
}

class myDBManagerHandler_DBConn {
	public $host;
	public $username;
	public $password;
	public static $database;
	protected $connection;

	function __construct($options) {
		$this->host = $options['host'];
		if (empty($this->host)) {
			$this->host = '127.0.0.1';
		}
		$this->username = $options['username'];
		$this->password = $options['password'];
		if (!empty($options['database'])) {
			$this->database=$options['database'];
		}
	}

	static function create($options) {
		if (class_exists('mysqli')) {
			$class_name = "myDBManagerHandler_DBConn_Mysqli";
		} else {
			$class_name = "myDBManagerHandler_DBConn_Mysql";
		}
		return new $class_name($options);
	}

}

class myDBManagerHandler_DBConn_Mysql extends myDBManagerHandler_DBConn {
	function connect() {
		$this->connection = @mysql_connect($this->host, $this->username, $this->password);
		if (!$this->connection) {
			throw new myDBManager_Exception("Couldn't connect to the server: " . mysql_error());
		}
		return true;
	}

	function createDatabase($db) {
		if (!$this->connection) {
			$this->connect();
		}
		if(empty($db)){
			throw new myDBManager_Exception("No database name to create!");
		}
		$sql="CREATE DATABASE $db";
		$this->query($sql);
		return true;
	}

	function selectDatabase($db){
		if (!$this->connection) {
			$this->connect();
		}
		self::$database=$db;
		$res=mysql_select_db($db,$this->connection);
		if (!$res) {
			throw new myDBManager_Exception("SQL error: " . mysql_error($this->connection));
		}
		return true;
	}

	function dropDatabase($db){
		if (!$this->connection) {
			$this->connect();
		}
		$database='';
		if(!empty($db)){$database=$db;}else{$database=$this->database;}
		if(empty($db)&&empty($this->database)){
			throw new myDBManager_Exception("No database name to drop!");
		}
		$sql="DROP DATABASE ".$database;
		$this->query($sql);
		return true;
	}

	function query($q) {
		if (!$this->connection) {
			$this->connect();
		}
		$res = mysql_query($q);
		if (!$res) {
			throw new myDBManager_Exception("SQL error: " . mysql_error($this->connection));
		}
		return $res;
	}

	function fetch_numeric($query) {
		return $this->fetch($query, MYSQL_NUM);
	}

	function fetch($query, $result_type=MYSQL_ASSOC) {
		$result = $this->query($query, $this->connection);
		$return = array();
		while ( $row = mysql_fetch_array($result, $result_type) ) {
			$return[] = $row;
		}
		return $return;
	}

	function escape($value) {
		if (is_null($value)) {
			return "NULL";
		}
		return "'" . mysql_real_escape_string($value) . "'";
	}

	function escape_like($search) {
		return str_replace(array('_', '%'), array('\_', '\%'), $search);
	}

	function get_var($sql) {
		$result = $this->query($sql);
		$row = mysql_fetch_array($result);
		return $row[0];
	}

	function fetch_row($data) {
		return mysql_fetch_assoc($data);
	}
}


class myDBManagerHandler_DBConn_Mysqli extends myDBManagerHandler_DBConn {
	function connect() {
		$this->connection = @new MySQLi($this->host, $this->username, $this->password);
		if ($this->connection->connect_error) {
			throw new myDBManager_Exception("Couldn't connect to the server: " . $this->connection->connect_error);
		}
		return true;
	}

	function createDatabase($db) {
		if (!$this->connection) {
			$this->connect();
		}
		if(empty($db)){
			throw new myDBManager_Exception("No database name to create!");
		}
		$sql="CREATE DATABASE $db";
		$this->query($sql);
		return true;
	}

	function selectDatabase($db){
		if (!$this->connection) {
			$this->connect();
		}
		self::$database=$db;
		$res=mysqli_select_db($this->connection,$db);
		if (!$res) {
			throw new myDBManager_Exception("SQL error: " . $this->connection->error);
		}
		return true;
	}

	function dropDatabase($db){
		if (!$this->connection) {
			$this->connect();
		}
		$database='';
		if(!empty($db)){$database=$db;}else{$database=$this->database;}
		if(empty($db)&&empty($this->database)){
			throw new myDBManager_Exception("No database name to drop!");
		}
		$sql="DROP DATABASE ".$database;
		$this->query($sql);
		return true;
	}

	function query($q) {
		if (!$this->connection) {
			$this->connect();
		}
		$res = $this->connection->query($q);
		
		if (!$res) {
			throw new myDBManager_Exception("SQL error: " . $this->connection->error);
		}
		
		return $res;
	}

	function fetch_numeric($query) {
		return $this->fetch($query, MYSQLI_NUM);
	}

	function fetch($query, $result_type=MYSQLI_ASSOC) {
		$result = $this->query($query, $this->connection);
		$return = array();
		while ( $row = $result->fetch_array($result_type) ) {
			$return[] = $row;
		}
		return $return;
	}

	function escape($value) {
		if (is_null($value)) {
			return "NULL";
		}
		return "'" . $this->connection->real_escape_string($value) . "'";
	}

	function escape_like($search) {
		return str_replace(array('_', '%'), array('\_', '\%'), $search);
	}

	function get_var($sql) {
		$result = $this->query($sql);
		$row = $result->fetch_array($result, MYSQLI_NUM);
		return $row[0];
	}

	function fetch_row($data) {
		return $data->fetch_array(MYSQLI_ASSOC);
	}
}

class execHandler extends myDBManagerHandler_DBConn{
	function createImport($path){
		//DO NOT EDIT BELOW THIS LINE
		$command='mysql -h' .$this->host .' -u' .$this->username .' -p' .$this->password .' ' .$this->database .' < ' .$path;
		exec($command,$output=array(),$worked);
		switch($worked){
		    case 0:
		        return true;
		        break;
		    case 1:
		    throw new myDBManager_Exception('There was an error during import. Please check your values:<br/><br/><table><tr><td>MySQL Database Name:</td><td><b>' .$this->database .'</b></td></tr><tr><td>MySQL User Name:</td><td><b>' .$this->username .'</b></td></tr><tr><td>MySQL Password:</td><td><b>NOTSHOWN</b></td></tr><tr><td>MySQL Host Name:</td><td><b>' .$this->host .'</b></td></tr><tr><td>MySQL Import Filename:</td><td><b>' .$path .'</b></td></tr></table>');
		        break;
		}
	}
	function createExport($path){
		//DO NOT EDIT BELOW THIS LINE
		$command='mysqldump --opt -h' .$this->host .' -u' .$this->username .' -p' .$this->password .' ' .$this->database .' > ~/' .$path;
		exec($command,$output=array(),$worked);
		switch($worked){
		case 0:
			return true;
		break;
		case 1||2:
			throw new myDBManager_Exception('There was an error during export. Please check your values:<br/><br/><table><tr><td>MySQL Database Name:</td><td><b>' .$this->database .'</b></td></tr><tr><td>MySQL User Name:</td><td><b>' .$this->username .'</b></td></tr><tr><td>MySQL Password:</td><td><b>NOTSHOWN</b></td></tr><tr><td>MySQL Host Name:</td><td><b>' .$this->host .'</b></td></tr></table>');
		break;
		}
	}
}

class myDBManager_Exception extends Exception {};
