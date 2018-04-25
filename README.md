# MyDBManager

MyDBManager is a PHP Library to easily manage your MySQL databases, hassle free

## Getting Started

To get started just download the library to your working project folder or server and start coding

### Prerequisites

The library works with >=PHP 5.4. You may get lucky with previous versions of PHP

```
>=PHP 5.4
```

### Features

* Import and export MySQL Databases through both PHP and exec() command
* Handle multiple database transactions- import into/export from different databases
* Import/export selected tables from a Database

```
//jus include the MyDBManager.php file
include ('src/myDBManager.php');
```

then create a new MyDBManager object

```
$myDBManager = new myDBManager(array(
	'host' => '',
	'username' => 'root',
	'password' => '',
	'database'=>'db_name'
));
```

With the MyDBManager object successfully created, you can call any of the following

```
//to use the exec() command rather than PHP functions
$myDBManager->useExec();
//export database as .sql file
$myDBManager->exportDatabase("myDBManager_export.sql");
//drop a database
$myDBManager->dropDatabase("myDBManager");
//create a database
$myDBManager->createDatabase("myDBManager");
//import a database
$myDBManager->importDatabase("myDBManager_2.sql");
```

## Importing databases

The import function accepts a second parameter, where to import and what action to take

```
//creates and inserts into the db myDBmanager_old
$myDBManager->importDatabase("myDBManager_2.sql",array('create'=>'myDBManager_old'));
//inserts into the db myDBmanager_old
$myDBManager->importDatabase("myDBManager_2.sql",array('target'=>'myDBManager_old'));
//replaces the db myDBmanager_old
$myDBManager->importDatabase("myDBManager_2.sql",array('replace'=>'myDBManager_old'));
```

## Authors

* **Gichimu Muhoro** - *Creative* - [for Livecore Systems](https://twitter.com/justSisto)

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details
