<?php

/*
Script CLI (command line interface) for synchronizing two database
*/

$importTables = array(
	'tx_dam',
	'tx_dam_cat',
	'tx_dam_mm_cat',
	'tx_speciality_domain_model_domain',
	'tx_speciality_domain_model_group',
	'tx_speciality_domain_model_master',
	'tx_templatedisplay_displays'
);
$synchronizeRuleTables = array(
	'tt_content' => array(

		'subheader',
		'header_link'
	),
	'pages' => array(

		'url',
		'subtitle',
		'author',
		'nav_title',
	),
	'be_groups' => array(),
	'be_users' => array(
		'password'
	),
	'fe_groups' => array(),
	'fe_users' => array(),
	'sys_domain' => array(),
	'sys_refindex' => array(),
	'sys_template' => array(),
	'tx_datafilter_filters'=> array(),
	'tx_dataquery_queries'=> array(),
	'tx_displaycontroller_components_mm'=> array(),
	'tx_phpdisplay_displays'=> array(),
);
$synchronizeTables = array(
	'tt_content',
	'pages',
	'be_groups',
	'be_users',
	'fe_groups',
	'fe_users',
	'sys_refindex',
	'sys_template',
	'tx_datafilter_filters',
	'tx_dataquery_queries',
	'tx_displaycontroller_components_mm',
	'tx_phpdisplay_displays',
);
############################################
include('Classes/Database.php');
include('Classes/Logger.php');
$logger = new Logger();

$dbOld = new Ecodev\Database('localhost', 'root', 'root');
$dbOld->connect('DB-old');
$dbNew = new Ecodev\Database('localhost', 'root', 'root');
$dbNew->connect('DB-new');
foreach ($synchronizeTables as $synchronizeTable) {
	$logger->log('Synchronizing table " ' . $synchronizeTable . ' " .....');
	$fieldStructures = $dbNew->select('SHOW COLUMNS FROM ' . $synchronizeTable);
	$newFieldsNames = array();
	foreach ($fieldStructures as $fieldStructure) {
		$newFieldsNames[] = $fieldStructure['Field'];
	}
	$clause = '1 = 1';
	$specialFields = array('deleted', 'disable', 'hidden');
	foreach ($specialFields as $specialField) {
		if (isset($newFieldsNames['deleted'])) {
			$clause .= ' deleted = 1';
		}
		if (isset($newFieldsNames['disable'])) {
			$clause .= ' disable = 1';
		}
		if (isset($newFieldsNames['hidden'])) {
			$clause .= ' hidden = 1';
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

foreach ($importTables as $importTable) {
	$logger->log('Import whole content of table: "' . $importTable . '"');
	$exportCommand = 'mysqldump -u root -p"root" DB-old ' . $importTable . ' > /tmp/' . $importTable . '.sql';
	exec($exportCommand);
	$importCommand = 'mysql -u root -p"root" DB-new < /tmp/' . $importTable . '.sql';
	exec($importCommand);
	$logger->log('Done for:' . $importTable . '!!!');
}
$logger->log('
All tables imported succesfully in the new database!!!');