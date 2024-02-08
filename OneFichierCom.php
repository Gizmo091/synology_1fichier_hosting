<?php

/*
    @author : Mathieu Vedie
	@Version : 4.0.5
	@firstversion : 07/07/2019
	@description : Support du compte gratuit, access, premium et CDN

    Module are installed in /var/packages/DownloadStation/etc/download/userhosts on the Synology and could be directly edited here for debug
    Source code of synology php code is here : /volume1/@appstore/DownloadStation/hostscript 
	
	Packaging by :
        tar -czvf "OneFichierCom(<version>).host" INFO OneFichierCom.php
        or directly use bash.sh ou bash_with_docker.sh

    Update : 
    - 4.0.5 : Le code est maintenant compatible php7 ( des fonctionnements de php8 avait été inclus auparavant )
    - 4.0.4 : Ajout de la possibilité d'envoyer les logs sur un serveur externe ( pour aider au debug )
    - 4.0.2 : Ajout de logs pour debuger
    - 4.0.1 : Utilisation du password pour l'apikey et non le username
    - 4.0.0 : Attention, version utilisant l'API donc reservé au premium/access
    - 3.2.8 : Ajout de la version anglaise du controle du 2024-02-02
    - 3.2.7 : Ajout d'un test pour verifier que le compte est premium.
 */

class SynoFileHosting
{
    const LOG_DIR = '/tmp/1fichier_dot_com';
    private $Url;
    private $Username;
    private $apikey;

    private $log_dir;
    private $log_id; 

    private $conf_remote_log = null;
    private $conf_cli_log = null;
    

    public function callApi(string $endpoint, $data) {
        // pause entre les appels curl pour éviter le blockage
        sleep(2);   
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $endpoint,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>json_encode($data),
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->apikey
          ),
        ));
        $response = curl_exec($curl);
        if (false === $response) {
            $this->writeLog(__FUNCTION__,'Erreur de curl',['parameters'=>[
                'endpoint'=>$endpoint,
                'data'=>$data,
            ],
            'curl_error' => curl_error($curl)]);
        } 
        curl_close($curl);
        return $response;
    }

    /**
     * Constructeur de la class de gestion des téléchargement sur 1fichier.com
     * Le mot de passe est utilisé pour stocker l'apikey, c'est pour cela que le 3eme arguments 
     * est nommé apikey et non password
     */
    public function __construct($Url, $Username, $apikey, $HostInfo)
    {
        // parsing des conf que l'on peut passer dans le username
        $configs = array_map(function($param_couple) {
            if (strpos($param_couple,'=') === false) {
                return null;
            }
            return explode('=',$param_couple,2);
        },explode(';',$Username));
        $configs = array_filter($configs);
        
        if (!empty($configs)) {
            $configs = array_combine(array_column($configs,0),array_column($configs,1));
            foreach($configs as $key => $value) {
                $this->{"conf_$key"} = $value;
            }
        }


        // retire tout ce qui vient en plus du lien de téléchargement strict. 
        // exemple : $Url       = "https://1fichier.com/?fzrlqa5ogmx4dzbcpga6&af=3601079"
        //           $this->Url = "https://1fichier.com/?fzrlqa5ogmx4dzbcpga6"
        $this->Url = explode('&',$Url)[0];
        $this->apikey = $apikey;
        $this->log_id = null;
        $this->log_dir = static::LOG_DIR;
        // on défini un identifiant pour enregister les logs ( identifiant du téléchargement ou null si non récupérable )
        // exemple1 : $this->Url = "https://1fichier.com/?fzrlqa5ogmx4dzbcpga6"
        //            $this->log_id = "fzrlqa5ogmx4dzbcpga6"
        // exemple2 : $this->Url = "https://1fichier.com/fzrlqa5ogmx4dzbcpga6"
        //            $this->log_id = "null"
        $log_id =  explode('?',$this->Url,2);
        if (isset($log_id[1])) {
            $this->log_id = $log_id[1];
        }
        $this->writeLog(__FUNCTION__,'Appel du constructeur de '.__CLASS__,['parameters'=>[
            'Url'=>$Url,
            'Username'=>$Username,
            'apikey'=>str_pad(substr($apikey,0,(int)(strlen($apikey)/2)),strlen($apikey),'?',STR_PAD_RIGHT),
            'HostInfo'=>$HostInfo
        ]]);
    }
	
    /**
     * Fonction qui est appelée pour recuperer les informations d'un fichier 
     * en fonction du lien passé au constructeur
     */
    public function GetDownloadInfo()
    {
        $p_data = [
            'url'=>$this->Url,
        ];
        $end_point = 'https://api.1fichier.com/v1/download/get_token.cgi';
        $response = $this->callApi($end_point,$p_data);
        $this->writeLog(__FUNCTION__,'Reponse brute de l\'api à '.$end_point.' ',$response);
        $data = json_decode($response,true);
        $this->writeLog(__FUNCTION__,'Reponse json de l\'api à '.$end_point.' ',$data);
        if (null === $data || false === $data) {
            $this->writeLog(__FUNCTION__,'Data non valide !',['return'=>[DOWNLOAD_ERROR => ERR_UNKNOWN]]);
            return [DOWNLOAD_ERROR => ERR_UNKNOWN];
        }
        if ('OK' !== $data['status']) {
            $this->writeLog(__FUNCTION__,'Status non OK !',['return'=>[DOWNLOAD_ERROR => ERR_UNKNOWN]]);
            return [DOWNLOAD_ERROR => ERR_UNKNOWN];
        }
        if (!array_key_exists('url',$data)) {
            $this->writeLog(__FUNCTION__,'Pas d\'url dans la réponse !',['return'=>[DOWNLOAD_ERROR => ERR_UNKNOWN]]);
            return [DOWNLOAD_ERROR => ERR_UNKNOWN];
        }
        
        $download_url = $data['url'];

        $this->writeLog(__FUNCTION__,'download_url : ',$download_url);

        $p_data = [
            'url'=>$this->Url,
        ];
        $end_point = 'https://api.1fichier.com/v1/file/info.cgi';
        $response = $this->callApi($end_point,$p_data);
        $this->writeLog(__FUNCTION__,'Reponse brute de l\'api à '.$end_point.' ',$response);
        $data = json_decode($response,true);
        $this->writeLog(__FUNCTION__,'Reponse json de l\'api à '.$end_point.' ',$data);
        if (null === $data || false === $data) {
            $this->writeLog(__FUNCTION__,'Data non valide !',['return'=>[DOWNLOAD_ERROR => ERR_UNKNOWN]]);
            return [DOWNLOAD_ERROR => ERR_UNKNOWN];
        }
        if (!array_key_exists('filename',$data)) {
            $this->writeLog(__FUNCTION__,'Pas de filename dans la réponse !',['return'=>[DOWNLOAD_ERROR => ERR_UNKNOWN]]);
            return [DOWNLOAD_ERROR => ERR_UNKNOWN];
        }

        
        $return = [
            INFO_NAME => $data['filename'],
            DOWNLOAD_ISPARALLELDOWNLOAD => true,
            DOWNLOAD_URL => $download_url
        ]; 
        $this->writeLog(__FUNCTION__,'Fin de la methode sans erreur : ',['return'=>$return]);
        return $return;

        
    }
    
    //verifie le type de compte entré
    public function Verify($ClearCookie)
    {
        $this->writeLog(__FUNCTION__,'Debut de la methode : ',['parameters'=>[
            'ClearCookie'=>$ClearCookie,
        ]]);
        $typeaccount_return = $this->TypeAccount($this->apikey);
        $this->writeLog(__FUNCTION__,'Fin de la methode : ',['return'=>$typeaccount_return]);
        return $typeaccount_return;
    }
    
    
    private function TypeAccount($apikey)
    {
        $this->writeLog(__FUNCTION__,'Debut de la methode : ',['parameters'=>[
            'apikey'=>str_pad(substr($apikey,0,(int)(strlen($apikey)/2)),strlen($apikey),'?',STR_PAD_RIGHT),
        ]]);
        $end_point = 'https://api.1fichier.com/v1/user/info.cgi';
        $response = $this->callApi($end_point,new stdClass());
        $this->writeLog(__FUNCTION__,'Reponse brute de l\'api à '.$end_point.' ',$response);
        $data = json_decode($response,true);
        $this->writeLog(__FUNCTION__,'Reponse json de l\'api à '.$end_point.' ',$data);
        if (null === $data || false === $data) {
            $this->writeLog(__FUNCTION__,'Data non valide !',['return'=>LOGIN_FAIL]);
            return LOGIN_FAIL;
        }
        if ('OK' !== $data['status']) {
            $this->writeLog(__FUNCTION__,'Status non OK !',['return'=>LOGIN_FAIL]);
            return LOGIN_FAIL;
        }
        if (!array_key_exists('offer',$data)) {
            $this->writeLog(__FUNCTION__,'Pas d\'offer dans la réponse !',['return'=>LOGIN_FAIL]);
            return LOGIN_FAIL;
        }
        $return = LOGIN_FAIL;
        switch((int)$data['offer']) {
            case 0;
                $return=  USER_IS_FREE;
                break;
            case 1;
            case 2;
                $return = USER_IS_PREMIUM;
                break;
        }
        $this->writeLog(__FUNCTION__,'Fin de la methode sans erreur : ',['return'=>$return]);
        return $return;
    }

    

    private function writeLog(string $function,string $message,$data = null) {
        $date = (new DateTime())->format(DATE_RFC3339_EXTENDED);
        $row1 = "$date : $function : Message :  $message".PHP_EOL;
        $row2 = "$date : $function : Data : ".serialize($data).PHP_EOL;
        if (null !== $this->conf_cli_log) {
            echo $row1;
            echo $row2;
        }
        if ($this->conf_remote_log !== null) {
            $this->writeRemoteLog($row1,$row2);
            return;
        }   
        $this->writeLocalLog($row1,$row2);
    }

    private function writeRemoteLog(string $row1, string $row2) {
        // pause entre les appels curl pour eviter le blockage
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $this->conf_remote_log,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 2,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>json_encode(['row1'=>$row1,'row2'=>$row2]),
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'User-Agent: OneFichierCom/Synology'
          ),
        ));
        curl_exec($curl);
        curl_close($curl);
    }

    private function writeLocalLog(string $row1, string $row2) {
        if (!file_exists($this->log_dir)) {
            if (!mkdir($this->log_dir, 0755, true)) {
                // on sort si on ne peut pas créer le repertoire de log
                return;
            }
        }
        $this->cleanLog();
        // définition du fichier de log
        $log_path = $this->log_dir.DIRECTORY_SEPARATOR.($this->log_id ?? 'default').'.log';
            
        // écritue de deux ligne de log, une avec le message et une avec les datas
        file_put_contents($log_path,$row1,FILE_APPEND);
        file_put_contents($log_path,$row2,FILE_APPEND);
    }

    /**
     * Fonction pour supprimer les fichiers logs qui sont trop anciens. 
     * Est appelé à chaque fois que le constructeur de cette classe est appelé. 
     */
    public function cleanLog() {
        $log_file_a = scandir($this->log_dir);
        // on defini le timestamp au dela du quel on supprime les logs. 
        // ici : tout ce qui à plus de 1 jours ( 24 x 3600 secondes )
        $timestamp_max = time() - 3600*24;
        foreach($log_file_a as $log_file) {
            if ($log_file == '.'|| $log_file == '..') {
                continue;
            }
            $log_file = $this->log_dir.DIRECTORY_SEPARATOR.$log_file;
            $filemtime = filemtime($log_file);
            if ( $filemtime < $timestamp_max) {
                unlink($log_file);
            }
        }
    }
}
