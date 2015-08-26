<?php

/*
Script CLI (command line interface) for synchronizing two database
*/

$importTables = array(
	'sys_file',
	'tx_datafilter_filters',
	'tx_dataquery_queries',
	'tx_displaycontroller_components_mm',
	'tx_phpdisplay_displays'
);
$synchronizeRuleTables = array(
	'sys_language'=> array(),
	'pages_language_overlay' => array(),
	'tt_content'=> array(),
	'pages' => array(),
	'be_groups' => array(),
	'be_users' => array(),
	'fe_groups' => array(),
	'fe_users' => array(),
	'sys_refindex' => array(),
	'sys_template' => array(),
	'sys_category' => array(),
	'sys_category_record_mm' => array(),
);
$synchronizeTables = array(
	'sys_language',
	'pages_language_overlay',
	'tt_content',
	'pages',
	'be_groups',
	'be_users',
	'fe_groups',
	'fe_users',
	'sys_refindex',
	'sys_template',
	'sys_category',
	'sys_category_record_mm',
);

###########################
# Beginning of the script #
###########################

include('Classes/Database.php');
include('Classes/Logger.php');
include('Credentials.php');

$logger = new Logger();

$dbOld = new Ecodev\Database($oldCredentials['host'], $oldCredentials['username'], $oldCredentials['password'], $oldCredentials['port']);
$dbOld->connect($oldCredentials['database']);

$dbNew = new Ecodev\Database($newCredentials['host'], $newCredentials['username'], $newCredentials['password'], $newCredentials['port']);
$dbNew->connect($newCredentials['database']);

// Synchronize tables
foreach ($synchronizeTables as $synchronizeTable) {
	$logger->log('Synchronizing table "' . $synchronizeTable . '" .....');
	$fieldStructures = $dbNew->select('SHOW COLUMNS FROM ' . $synchronizeTable);
	$newFieldsNames = array();
	foreach ($fieldStructures as $fieldStructure) {
		$newFieldsNames[] = $fieldStructure['Field'];
	}

	// Build clause part of the request
	$clause = '1 = 1';
	$specialFields = array(
		'deleted',
		'disable',
		'hidden'
	);
	foreach ($specialFields as $specialField) {
		if (in_array($specialField, $newFieldsNames)) {
			$clause .= ' AND ' . $specialField . ' = 0';
		}
	}
	//$toImportValues = $dbOld->select('SELECT * FROM ' . $synchronizeTable . ' WHERE ' . $clause);
	$toImportValues = $dbOld->select('SELECT * FROM ' . $synchronizeTable . ' WHERE ' . $clause);
	$dbNew->delete($synchronizeTable);//truncating table
	/*
	$dbNew->query('TRUNCATE TABLE '. $table); => other way to do it
	*/
	foreach ($toImportValues as $toImportValue) {
		foreach ($toImportValue as $fieldName => $value) {
			if (!in_array($fieldName, $newFieldsNames)) {
				unset($toImportValue[$fieldName]);
			}
		}
		foreach ($synchronizeRuleTables[$synchronizeTable] as $synchronizeRuleTable) {
			if (is_null($toImportValue[$synchronizeRuleTable])) {
				$toImportValue[$synchronizeRuleTable] = '';
			}
		}
		$dbNew->insert($synchronizeTable, $toImportValue);
	}
	$logger->log('Synchronized!!!');
}
$logger->log('
All tables synchronized succesfully!!!');
$logger->log('
');

// Import tables
foreach ($importTables as $importTable) {
	$logger->log('Import whole content of table: "' . $importTable . '"');
	$exportCommand = sprintf('mysqldump -u %s -p"%s" %s %s > /tmp/%s.sql',
		$oldCredentials['username'],
		$oldCredentials['password'],
		$oldCredentials['database'],
		$importTable,
		$importTable
	);

	exec($exportCommand);
	$importCommand = sprintf('mysql -u %s -p"%s" %s < /tmp/%s.sql',
		$newCredentials['username'],
		$newCredentials['password'],
		$newCredentials['database'],
		$importTable
	);

	exec($importCommand);
	$logger->log('Done for: ' . $importTable . '!!!');
}
$logger->log('
All tables imported succesfully in the new database!!!');
