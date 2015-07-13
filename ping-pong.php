<?php

/*
Script CLI (command line interface) for synchronizing two database
*/
include('Classes/Database.php');
include('Classes/Logger.php');
$logger = new Logger();

$dbOld = new Ecodev\Database('localhost', 'root', 'root');
$dbOld->connect('DB-old');
$dbNew = new Ecodev\Database('localhost', 'root', 'root');
$dbNew->connect('DB-new');

$convertFields = array(
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
);

$tables = array(
	'tt_content',
	'pages',
	'be_groups',
	'be_users',
	'fe_groups',
	'fe_users',
	'sys_domain',
	'sys_refindex',
	'sys_template',
);
foreach ($tables as $table) {
	$logger->log('Synchronizing table " ' . $table . ' " .....');
	$fieldStructures = $dbNew->select('SHOW COLUMNS FROM ' . $table);
	$newFieldsNames = array();
	foreach ($fieldStructures as $fieldStructure) {
		$newFieldsNames[] = $fieldStructure['Field'];
	}
	$toImportValues = $dbOld->select('SELECT * FROM ' . $table);
	$dbNew->delete($table);//truncating table
	/*
	$dbNew->query('TRUNCATE TABLE '. $table); => other way to do it
	*/
	foreach ($toImportValues as $toImportValue) {
		foreach ($toImportValue as $fieldName => $value) {
			if (!in_array($fieldName, $newFieldsNames)) {
				unset($toImportValue[$fieldName]);
			}
		}
		foreach ($convertFields[$table] as $convertField) {
			if (is_null($toImportValue[$convertField])) {
				$toImportValue[$convertField] = '';
			}
		}
		$dbNew->insert($table, $toImportValue);
	}
	$logger->log('Synchronized!!!');
}
$logger->log('
All tables synchronized succesfully!!!');
$tables = array(
	'tx_dam',
	'tx_dam_cat',
	'tx_dam_mm_cat',
	'tx_datafilter_filters',
	'tx_dataquery_queries',
	'tx_displaycontroller_components_mm',
	'tx_phpdisplay_displays',
	'tx_speciality_domain_model_domain',
	'tx_speciality_domain_model_group',
	'tx_speciality_domain_model_master',
	'tx_templatedisplay_displays'
);
foreach ($tables as $table) {
	$logger->log('Import whole content of table: "' . $table.'"');
	$exportCommand = 'mysqldump -u root -p"root" DB-old ' . $table . ' > /tmp/' . $table . '.sql';
	exec($exportCommand);
	$importCommand = 'mysql -u root -p"root" DB-new < /tmp/' . $table . '.sql';
	exec($importCommand);
	$logger->log('Done for:' . $table . '!!!');
}
$logger->log('
All tables imported succesfully in the new database!!!');