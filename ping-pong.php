<?php

/*
Script CLI (command line interface)

- deux bases de données
- on synchronise **sélectivement** les données de l'ancienne DB-old vers la nouvelle DB-new
- dans un premier se concentrer sur la table "tt_content"
- faire attention que old.tt_content et new.tt_content n'ont pas forcément les mêmes champs
- les données de tt_content_new peuvent être écrasées
*/
include('Classes/Database.php');
include('Classes/Logger.php');
$logger = new Logger();

$dbOld = new Ecodev\Database('localhost', 'root', 'root');
$dbOld->connect('DB-old');
$dbNew = new Ecodev\Database('localhost', 'root', 'root');
$dbNew->connect('DB-new');

$logger->log('DB-new tables list :');

$tables = $dbNew->select('SHOW TABLES');
$tablesNames = array();
foreach ($tables as $table) {
	$tablesNames[] = $table['Tables_in_DB-new'];
}
//print_r($tablesNames);

$logger->log('DB-new table tt_content fields names are :');

$fieldContents = $dbNew->select('SHOW COLUMNS FROM tt_content');
$newFieldsNames = array();
foreach ($fieldContents as $fieldTtContent) {
	$newFieldsNames[] = $fieldTtContent['Field'];
}

// $tables = array('tt_content', 'pages', '...')
// foreach ($tables as $table) {
// ...	$table = 'tt_content';
//}

// SELECT DB-old
$toImportValues = $dbOld->select('SELECT * FROM tt_content');
// INSERT DB-new
$table = 'tt_content';
$freeTable = $dbNew->delete($table);
/*
$dbNew->query('TRUNCATE TABLE tt_content');
*/
$convertFields = array(
	'subheader',
	'header_link'

);

foreach ($toImportValues as $importedValue) {

	foreach ($importedValue as $fieldName => $value) {
		if (!in_array($fieldName, $newFieldsNames)) {
			unset($importedValue[$fieldName]);
		}
	}
	foreach ($convertFields as $convertField) {
		if (is_null($importedValue[$convertField])) {
			$importedValue[$convertField] = '';
		}
	}
	//var_dump($importedValue['subheader']);

	//print_r($importedValue);
	$dbNew->insert($table, $importedValue);

}
