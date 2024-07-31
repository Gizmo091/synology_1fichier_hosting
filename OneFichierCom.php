<?php
/*
    @author : Mathieu Vedie
	@Version : 4.4.0
	@firstversion : 07/07/2019
	@description : Support du compte gratuit, access, premium et CDN

    Module are installed in /var/packages/DownloadStation/etc/download/userhosts on the Synology and could be directly edited here for debug
    Source code of synology php code is here : /volume1/@appstore/DownloadStation/hostscript 
	
	Packaging by :
        tar -czvf "OneFichierCom(<version>).host" INFO OneFichierCom.php
        or directly use bash.sh ou bash_with_docker.sh

    Update :
    - 4.4.0 : Désactivation du controle du certifficat SSL.
    - 4.3.0 : Définition du nom du fichier de destination dans les informations retournées au DL Station ( evite par exemple les _ indésirables )
    - 4.2.0 : Prise en compte des liens avec un token de téléchargement : exemple : https://a-6.1fichier.com/p1058755667
    - 4.1.0 : Le endpoint Account : Show n'est plus utilisé pour valider que la clé d'API peut être utilisé , on test plutot sur un fichier dont on connait l'existance.
    - 4.0.7 : Code rendu compatible à partir de php 5.6 pour être pleinement rétrocompatible.
    - 4.0.6 : Correction d'un problème si pas de paramètre passé à la place de l'username et correction d'un problème avec les logs
    - 4.0.5 : Le code est maintenant compatible php7 (des fonctionnements de php8 avait été inclus auparavant)
    - 4.0.4 : Ajout de la possibilité d'envoyer les logs sur un serveur externe (pour aider au debug)
    - 4.0.2 : Ajout de logs pour debugger
    - 4.0.1 : Utilisation du password pour l'apikey et non l'username
    - 4.0.0 : Attention, version utilisant l'API donc reservé au premium/access
    - 3.2.8 : Ajout de la version anglaise du contrôle du 2024-02-02
    - 3.2.7 : Ajout d'un test pour verifier que le compte est premium.
 */

class DownloadError extends Exception
{
}

class SynoFileHosting
{
    const LOG_DIR = '/tmp/1fichier_dot_com';
    // fichier pour lequelle on récupere les informations afin de verifier que la clé d'api est correcte
    // fichier heberger sur le compte 1fichier du créateur du fichier host
    const FILE_TO_CHECK = 'https://1fichier.com/?0x7zq8jobl8snu5qcngw';
    private $Url;
    //private $Username;
    private $apikey;

    private $log_dir;
    private $log_id;

    private $conf_remote_log = null;
    private $conf_cli_log    = null;
    private $conf_local_log  = null;


    /**
     * @param string $endpoint
     * @param mixed  $data
     *
     * @return bool|mixed|string
     */
    public function callApi($endpoint, $data)
    {
        // pause entre les appels curl pour éviter le blockage
        sleep(2);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL            => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apikey
            ),
            CURLOPT_SSL_VERIFYPEER => false, // Ignorer la vérification du certificat
            CURLOPT_SSL_VERIFYHOST => false, // Ne pas vérifier le nom de l'hôte
        ));
        $response = curl_exec($curl);
        if (false === $response) {
            $this->writeLog(__FUNCTION__, 'Erreur de curl', [
                'parameters' => [
                    'endpoint' => $endpoint,
                    'data'     => $data,
                ],
                'curl_error' => curl_error($curl)
            ]);
        }
        curl_close($curl);
        return $response;
    }

    /**
     * Constructeur de la class de gestion des téléchargements sur 1fichier.com
     * Le mot de passe est utilisé pour stocker l'apikey, c'est pour cela que le 3ème arguments
     * est nommé apikey et non password
     */
    public function __construct($Url, $Username, $apikey, $HostInfo)
    {
        // parsing des conf que l'on peut passer dans l'username
        $configs = array_map(function ($param_couple) {
            if (strpos($param_couple, '=') === false) {
                return null;
            }
            return explode('=', $param_couple, 2);
        }, explode(';', $Username));
        $configs = array_filter($configs);

        if (!empty($configs)) {
            $configs = array_combine(array_column($configs, 0), array_column($configs, 1));
            foreach ($configs as $key => $value) {
                $this->{"conf_$key"} = $value;
            }
        }


        // Retire tout ce qui vient en plus du lien de téléchargement strict.
        // exemple : $Url       = "https://1fichier.com/?fzrlqa5ogmx4dzbcpga6&af=3601079"
        //           $this->Url = "https://1fichier.com/?fzrlqa5ogmx4dzbcpga6"
        $this->Url     = explode('&', $Url)[0];
        $this->apikey  = $apikey;
        $this->log_id  = null;
        $this->log_dir = static::LOG_DIR;
        // on définit un identifiant pour enregistrer les logs (identifiant du téléchargement ou null si non récupérable)
        // exemple1 : $this->Url = "https://1fichier.com/?fzrlqa5ogmx4dzbcpga6"
        //            $this->log_id = "fzrlqa5ogmx4dzbcpga6"
        // exemple2 : $this->Url = "https://1fichier.com/fzrlqa5ogmx4dzbcpga6"
        //            $this->log_id = "null"
        $log_id = explode('?', $this->Url, 2);
        if (isset($log_id[1])) {
            $this->log_id = $log_id[1];
        }
        $this->writeLog(__FUNCTION__, 'Appel du constructeur de ' . __CLASS__, ['parameters' => [
            'Url'      => $Url,
            'Username' => $Username,
            'apikey'   => str_pad(substr($apikey, 0, (int)(strlen($apikey) / 2)), strlen($apikey), '?', STR_PAD_RIGHT),
            'HostInfo' => $HostInfo
        ]]);
    }

    /**
     * Fonction qui est appelée pour récupérer les informations d'un fichier
     * en fonction du lien passé au constructeur
     */
    public function GetDownloadInfo()
    {
        try {
            // Si c'est un lien déjà obtenu avec un token de téléchargement.
            if (preg_match("/^https:\/\/[a-zA-Z0-9]+(-[0-9]+)?\.1fichier\.com\/[a-zA-Z0-9]+$/", $this->Url)) {
                $filename = $this->getFilenameFromUrl($this->Url);
                if (null === $filename) {
                    $this->writeLog(__FUNCTION__, 'No filename returned', ['return' => [DOWNLOAD_ERROR => ERR_UNKNOWN]]);
                    return [DOWNLOAD_ERROR => ERR_UNKNOWN];
                }
                $download_url = $this->Url;
            } else {
                $download_url = $this->getDownloadLink($this->Url);
                $this->writeLog(__FUNCTION__, 'download_url : ', $download_url);
                $filename = $this->getFileName($this->Url, $download_url);
                $this->writeLog(__FUNCTION__, 'filename : ', $filename);
            }
        } catch (DownloadError $e) {
            $this->writeLog(__FUNCTION__, 'Catch DownloadError', ['return' => [DOWNLOAD_ERROR => ERR_UNKNOWN]]);
            return [DOWNLOAD_ERROR => ERR_UNKNOWN];
        }

        $return = [
            INFO_NAME                   => $filename,
            DOWNLOAD_FILENAME           => $filename,
            DOWNLOAD_ISPARALLELDOWNLOAD => true,
            DOWNLOAD_URL                => $download_url
        ];
        $this->writeLog(__FUNCTION__, 'Fin de la methode sans erreur : ', ['return' => $return]);
        return $return;
    }

    protected function getFilenameFromUrl($url)
    {
        $this->writeLog(__FUNCTION__, 'Debut de la methode : ', ['parameters' => [
            'url' => $url,
        ]]);

        // Initialiser cURL
        $ch = curl_init();

        // Configurer les options de cURL pour envoyer une requête HEAD
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, true); // Ne récupère que les en-têtes
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // Inclure les en-têtes dans la sortie
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Suivre les redirections
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');

        // Exécuter la requête cURL
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $this->writeLog(__FUNCTION__, 'Curl Error', ['error' => curl_error($ch), 'return' => null]);
            curl_close($ch);
            return null;
        }

        // Fermer la session cURL
        curl_close($ch);

        // Utiliser une expression régulière pour extraire le nom du fichier
        if (preg_match('/filename="(.*?)"/', $response, $matches)) {
            $this->writeLog(__FUNCTION__, 'Filename found', ['return' => $matches[1]]);
            return $matches[1];
        }
        $this->writeLog(__FUNCTION__, 'No filename', ['return' => null]);
        return null;
    }

    /**
     * Methode appelé par l'interface DL Station pour la verification du compte.
     *
     * @param $ClearCookie
     *
     * @return int
     * @noinspection PhpUnused
     */
    public function Verify($ClearCookie)
    {
        $this->writeLog(__FUNCTION__, 'Debut de la methode : ', ['parameters' => [
            'ClearCookie' => $ClearCookie,
        ]]);
        //        $typeaccount_return = $this->TypeAccount( $this->apikey );
        $typeaccount_return = LOGIN_FAIL;
        try {
            $filename = $this->getFileName('https://1fichier.com/?0x7zq8jobl8snu5qcngw');
            if ('verify' == $filename) {
                $typeaccount_return = USER_IS_PREMIUM;
            }
        } catch (DownloadError $e) {
        }

        $this->writeLog(__FUNCTION__, 'Fin de la methode : ', ['return' => $typeaccount_return]);
        return $typeaccount_return;
    }


    /**
     * @param $apikey
     *
     * @return int
     * @deprecated Cette methode engendrant souvent des blocage de l'APIkey ou de l'IP car mal généré au niveau de
     *             1fichier n'est plus utilisé
     */
    private function TypeAccount($apikey)
    {
        $this->writeLog(__FUNCTION__, 'Debut de la methode : ', ['parameters' => [
            'apikey' => str_pad(substr($apikey, 0, (int)(strlen($apikey) / 2)), strlen($apikey), '?', STR_PAD_RIGHT),
        ]]);
        $end_point = 'https://api.1fichier.com/v1/user/info.cgi';
        $response  = $this->callApi($end_point, new stdClass());
        $this->writeLog(__FUNCTION__, 'Réponse brute de l\'api à ' . $end_point . ' ', $response);
        $data = json_decode($response, true);
        $this->writeLog(__FUNCTION__, 'Réponse json de l\'api à ' . $end_point . ' ', $data);
        if (null === $data || false === $data) {
            $this->writeLog(__FUNCTION__, 'Data non valide !', ['return' => LOGIN_FAIL]);
            return LOGIN_FAIL;
        }
        if ('OK' !== $data['status']) {
            $this->writeLog(__FUNCTION__, 'Status non OK !', ['return' => LOGIN_FAIL]);
            return LOGIN_FAIL;
        }
        if (!array_key_exists('offer', $data)) {
            $this->writeLog(__FUNCTION__, 'Pas d\'offer dans la réponse !', ['return' => LOGIN_FAIL]);
            return LOGIN_FAIL;
        }
        $return = LOGIN_FAIL;
        switch ((int)$data['offer']) {
            case 0;
                $return = USER_IS_FREE;
                break;
            case 1;
            case 2;
                $return = USER_IS_PREMIUM;
                break;
        }
        $this->writeLog(__FUNCTION__, 'Fin de la methode sans erreur : ', ['return' => $return]);
        return $return;
    }


    /**
     * @return string Retourne le lien de téléchargement
     * @throws \DownloadError
     */
    private function getDownloadLink($url)
    {
        $p_data    = [
            'url' => $url,
        ];
        $end_point = 'https://api.1fichier.com/v1/download/get_token.cgi';
        $response  = $this->callApi($end_point, $p_data);
        $this->writeLog(__FUNCTION__, 'Réponse brute de l\'api à ' . $end_point . ' ', $response);
        $data = json_decode($response, true);
        $this->writeLog(__FUNCTION__, 'Réponse json de l\'api à ' . $end_point . ' ', $data);
        if (null === $data || false === $data) {
            $this->writeLog(__FUNCTION__, 'Data non valide ! throw DownloadError', ['param' => ['message' => ERR_UNKNOWN]]);
            throw new DownloadError(ERR_UNKNOWN);
        }

        if ('OK' !== $data['status']) {
            $this->writeLog(__FUNCTION__, 'Status non OK ! throw DownloadError', ['param' => ['message' => ERR_UNKNOWN]]);
            throw new DownloadError(ERR_UNKNOWN);
        }
        if (!array_key_exists('url', $data)) {
            $this->writeLog(__FUNCTION__, 'Pas d\'url dans la réponse ! throw DownloadError', ['param' => ['message' => ERR_UNKNOWN]]);
            throw new DownloadError(ERR_UNKNOWN);
        }

        return $data['url'];
    }

    /**
     * @throws \DownloadError
     */
    private function getFileName($url_original, $url_download = null)
    {
        $p_data    = [
            'url' => $url_original,
        ];
        $end_point = 'https://api.1fichier.com/v1/file/info.cgi';
        $response  = $this->callApi($end_point, $p_data);
        $this->writeLog(__FUNCTION__, 'Réponse brute de l\'api à ' . $end_point . ' ', $response);
        $data = json_decode($response, true);
        $this->writeLog(__FUNCTION__, 'Réponse json de l\'api à ' . $end_point . ' ', $data);
        if (null === $data || false === $data) {
            $this->writeLog(__FUNCTION__, 'Data non valide ! throw DownloadError', ['param' => ['message' => ERR_UNKNOWN]]);
            // on fallback sur l'autre methode
            if ($url_download) {
                return $this->getFilenameFromUrl($url_download);
            }
            throw new DownloadError(ERR_UNKNOWN);
        }
        if (!array_key_exists('filename', $data)) {
            $this->writeLog(__FUNCTION__, 'Pas de filename dans la réponse ! throw DownloadError', ['param' => ['message' => ERR_UNKNOWN]]);
            // on fallback sur l'autre methode
            if ($url_download) {
                return $this->getFilenameFromUrl($url_download);
            }
            throw new DownloadError(ERR_UNKNOWN);
        }
        return $data['filename'];
    }


    /**
     * @param string $function
     * @param string $message
     * @param mixed  $data
     *
     * @return void
     */
    private
    function writeLog($function, $message, $data = null)
    {
        $date = (new DateTime())->format(DATE_RFC3339_EXTENDED);
        $row1 = "$date : $function : Message :  $message" . PHP_EOL;
        $row2 = "$date : $function : Data : " . serialize($data) . PHP_EOL;
        $this->writeCLILog($row1, $row2);
        $this->writeRemoteLog($row1, $row2);
        $this->writeLocalLog($row1, $row2);
    }

    private
    function writeCLILog($row1, $row2)
    {
        if ("1" == $this->conf_cli_log) {
            fwrite(STDERR, $row1);
            fwrite(STDERR, $row2);
        }
    }

    /**
     * @param string $row1
     * @param string $row2
     *
     * @return void
     */
    private
    function writeRemoteLog($row1, $row2)
    {
        if ($this->conf_remote_log === null) {
            return;
        }
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL            => $this->conf_remote_log,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode([
                'row1' => $row1,
                'row2' => $row2
            ]),
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'User-Agent: OneFichierCom/Synology'
            ),
        ));
        curl_exec($curl);
        curl_close($curl);
    }

    /**
     * @param string $row1
     * @param string $row2
     *
     * @return void
     */
    private
    function writeLocalLog($row1, $row2)
    {
        // si local log désactivé, on sort de la fonction
        if ($this->conf_local_log != "1") {
            return;
        }
        if (!file_exists($this->log_dir)) {
            if (!mkdir($this->log_dir, 0755, true)) {
                // on sort si on ne peut pas créer le repertoire de log
                return;
            }
        }
        $this->cleanLog();
        // définition du fichier de log
        if (null === $this->log_id) {
            $log_path = $this->log_dir . DIRECTORY_SEPARATOR . 'default.log' . $this->log_id;
        } else {
            $log_path = $this->log_dir . DIRECTORY_SEPARATOR . $this->log_id . '.log';
        }

        // écriture de deux lignes de log, une avec le message et une avec les datas
        file_put_contents($log_path, $row1, FILE_APPEND);
        file_put_contents($log_path, $row2, FILE_APPEND);
    }

    /**
     * Fonction pour supprimer les fichiers logs qui sont trop anciens.
     * Est appelé à chaque fois que le constructeur de cette classe est appelé.
     */
    public
    function cleanLog()
    {
        $log_file_a = scandir($this->log_dir);
        // On définit le timestamp au dela duquel on supprime les logs.
        // Ici : tout ce qui a plus de 1 jour (24 x 3600 secondes)
        $timestamp_max = time() - 3600 * 24;
        foreach ($log_file_a as $log_file) {
            if ($log_file == '.' || $log_file == '..') {
                continue;
            }
            $log_file  = $this->log_dir . DIRECTORY_SEPARATOR . $log_file;
            $filemtime = filemtime($log_file);
            if ($filemtime < $timestamp_max) {
                unlink($log_file);
            }
        }
    }
}
