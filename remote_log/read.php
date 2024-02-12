<?php
$log_path = __DIR__ . '/trace.log';
if ( !file_exists( $log_path ) ) {
    die( 'no log' );
}
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ipAppelant = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $ipAppelant = $_SERVER['REMOTE_ADDR'];
}
?>
<html lang="fr">
<body>
<div>
    <form method="post" action="./clear.php">
        <label style="display: inline-block" for="ip">Effacer les logs de l'IP </label>
        <input style="display: inline-block" type="ip" name="ip" id="ip" placeholder="XXX.XXX.XXX.XXX"/>
        <input style="display: inline-block" type="submit" value="Effacer"/>
        <label>(votre ip : <a href="#" onclick="document.getElementById('ip').value = '<?=$ipAppelant?>';return false;"><?=$ipAppelant?></a>)</label>
    </form>
</div>
<div>
<pre style="max-width: 100%;overflow: auto;white-space: break-spaces;word-break: break-word;"><?php
    echo file_get_contents( $log_path );
    ?></pre>
</div>
</body>
</html>
