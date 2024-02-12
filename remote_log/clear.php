<?php
function redirect() {
    header('Location: read.php');
    die();
}

$log_path = __DIR__.'/trace.log';
if (!file_exists($log_path)) {
    redirect();
}

if (!isset($_POST['ip'])) {
    redirect();
}
if (!preg_match('/^([0-9]{1,3}\.)([0-9x]{1,3}\.){2}[0-9]{1,3}$/',$_POST['ip'])) {
    redirect();
}
$ip_a = explode(".",$_POST['ip']);
$ip_a[1] = str_repeat("x",strlen($ip_a[1]));
$ip_a[2] = str_repeat("x",strlen($ip_a[2]));
$ip_sed = implode('\.',$ip_a);

$cmd = "sed -i '/^\[$ip_sed\]/d' $log_path 2>&1";
exec($cmd,$output,$res);
redirect();
