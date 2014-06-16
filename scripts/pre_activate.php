<?php
/* The script pre_activate.php should contain code that should make the changes in the server
 * environment so that the application is fully functional. For example, this may include
 * changing symbolic links to "data" directories from previous to current versions,
 * upgrading an existing DB schema, or setting up a "Down for Maintenance"
 * message on the live version of the application
 * The following environment variables are accessable to the script:
 * 
 * - ZS_RUN_ONCE_NODE - a Boolean flag stating whether the current node is
 *   flagged to handle "Run Once" actions. In a cluster, this flag will only be set when
 *   the script is executed on once cluster member, which will allow users to write
 *   code that is only executed once per cluster for all different hook scripts. One example
 *   for such code is setting up the database schema or modifying it. In a
 *   single-server setup, this flag will always be set.
 * - ZS_WEBSERVER_TYPE - will contain a code representing the web server type
 *   ("IIS" or "APACHE")
 * - ZS_WEBSERVER_VERSION - will contain the web server version
 * - ZS_WEBSERVER_UID - will contain the web server user id
 * - ZS_WEBSERVER_GID - will contain the web server user group id
 * - ZS_PHP_VERSION - will contain the PHP version Zend Server uses
 * - ZS_APPLICATION_BASE_DIR - will contain the directory to which the deployed
 *   application is staged.
 * - ZS_CURRENT_APP_VERSION - will contain the version number of the application
 *   being installed, as it is specified in the package descriptor file
 * - ZS_PREVIOUS_APP_VERSION - will contain the previous version of the application
 *   being updated, if any. If this is a new installation, this variable will be
 *   empty. This is useful to detect update scenarios and handle upgrades / downgrades
 *   in hook scripts
 * - ZS_<PARAMNAME> - will contain value of parameter defined in deployment.xml, as specified by
 *   user during deployment.
 */  

if (getenv('ZS_RUN_ONCE_NODE')) {
	$appBaseDir = getenv('ZS_APPLICATION_BASE_DIR');
	
	require_once $appBaseDir . '/pimcore/config/startup.php';
	
	try {
		$link = mysql_connect(getenv("ZS_MYSQL_HOST"), getenv("ZS_MYSQL_USERNAME"), getenv("ZS_MYSQL_PASSWORD"));
		if (!$link) throw new Exception('Cannot connect to database! ERROR: ' . mysql_error($link));
	  
		$database = getenv("ZS_MYSQL_DATABASE");
		$sqlDrop = "DROP DATABASE IF EXISTS $database";
		$sqlCreate = "CREATE DATABASE $database DEFAULT CHARACTER SET utf8;";
	  
		if (!mysql_query($sqlDrop, $link)) throw new Exception('Drop Statement failed! ERROR: ' . mysql_error($link));
		if (!mysql_query($sqlCreate, $link)) throw new Exception('Create Statement failed! ERROR: ' . mysql_error($link));

		$db = Zend_Db::factory('PDO_MYSQL',array(
				'host' => getenv("ZS_MYSQL_HOST"),
				'username' => getenv("ZS_MYSQL_USERNAME"),
				'password' => getenv("ZS_MYSQL_PASSWORD"),
				'dbname' => $database,
				"port" => getenv("ZS_MYSQL_PORT")
		));

		$db->getConnection();
	  
		// check utf-8 encoding
		$result = $db->fetchRow('SHOW VARIABLES LIKE "character\_set\_database"');
		if ($result['Value'] != "utf8") {
			$errors[] = "Database charset is not utf-8";
		}
	}
	catch (Exception $e) {
		$errors[] = "Couldn't establish connection to mysql: " . $e->getMessage();
	}


	// check username & password
	if (strlen(getenv("ZS_ADMIN_PASSWORD")) < 4 || strlen(getenv("ZS_ADMIN_USERNAME")) < 4) {
		$errors[] = "Username and password should have at least 4 characters";
	}

	if (!empty($errors)) {
		foreach ($errors as $error) {
			error_log($error);
		}
		error_log('Errors occured while populating the database!');
		exit(1);
	}
}

try {
	$setup = new Tool_Setup();

	$setup->config(array(
			"database" => array(
					"adapter" => 'PDO_MYSQL',
					"params" => array(
							"host" => getenv("ZS_MYSQL_HOST"),
							"username" => getenv("ZS_MYSQL_USERNAME"),
							"password" => getenv("ZS_MYSQL_PASSWORD"),
							"dbname" => getenv("ZS_MYSQL_DATABASE"),
							"port" => getenv("ZS_MYSQL_PORT"),
					)
			),
	));
	
	$dbDataFile = PIMCORE_WEBSITE_PATH . "/dump/data.sql";
	$contentConfig = array(
			"username" => getenv("ZS_ADMIN_USERNAME"),
			"password" => getenv("ZS_ADMIN_PASSWORD")
	);

	if(!file_exists($dbDataFile)) {
		$setup->database();
		Pimcore::initConfiguration();
		$setup->contents($contentConfig);
	} else {
		if (getenv('ZS_RUN_ONCE_NODE')) {
			$setup->insertDump($dbDataFile);
		}
		Pimcore::initConfiguration();
		 
		if (getenv('ZS_RUN_ONCE_NODE')) {
			$i = 0;
			while (1) {
				try {
					$setup->createOrUpdateUser($contentConfig);
					return;
				}
				catch (Exception $e) {
					if ($i++ > 60) throw $e;
					error_log('Waiting for user setup table...');
					sleep(1);
				}
			}
		}
	}
}
catch (Exception $e) {
	error_log('Errors occured while setting up pimcore! ' . $e->getMessage());
	exit(1);
}
