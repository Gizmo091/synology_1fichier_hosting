<?php 
$log_path = __DIR__.'/trace.log';

// Ouvrir le fichier en mode ajout
$log_file = fopen($log_path, "a");

if (!$log_file) {
    die("Erreur lors de l'ouverture du fichier");
}

if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ipAppelant = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $ipAppelant = $_SERVER['REMOTE_ADDR'];
}
// Lire le corps de la requête POST
$donnees = file_get_contents("php://input");
$datas = json_decode($donnees,true);
if (!is_array($datas)) {
	die("not logged");
}
if (!isset($datas['row1']) && !isset($datas['row2'])) {
	die("not logged");
}
$ipAppelant_a = explode(".",$ipAppelant);
$ipAppelant_a[1] = str_repeat("x",strlen($ipAppelant_a[1]));
$ipAppelant_a[2] = str_repeat("x",strlen($ipAppelant_a[2]));
$ipAppelant = implode('.',$ipAppelant_a);

// Écrire les données dans le fichier
fwrite($log_file, '['.$ipAppelant.'] '.$datas['row1']);
fwrite($log_file, '['.$ipAppelant.'] '.$datas['row2']);
// Fermer le fichier
fclose($log_file);
die('logged');