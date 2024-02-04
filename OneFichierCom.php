<?php

/*
    @author : Mathieu Vedie
	@Version : 4.0.0
	@firstversion : 07/07/2019
	@description : Support du compte gratuit, access, premium et CDN

    Module are installed in /var/packages/DownloadStation/etc/download/userhosts on the Synology and could be directly edited here for debug
    Source code of synology php code is here : /volume1/@appstore/DownloadStation/hostscript 
	
	Packaging by :
        tar -czvf "OneFichierCom(<version>).host" INFO OneFichierCom.php
        or directly use bash.sh ou bash_with_docker.sh

    Update : 
    - 4.0.0 : Attention, version utilisant l'API donc reservé au premium/access
    - 3.2.8 : Ajout de la version anglaise du controle du 2024-02-02
    - 3.2.7 : Ajout d'un test pour verifier que le compte est premium.
 */

class SynoFileHosting
{
    private $Url;
    private $apikey;

    public function __construct($Url, $Username, $Password, $HostInfo)
    {
        $this->Url = explode('&',$Url)[0];
        $this->apikey = $Username;
        // password never used 
        //$this->Password = $Password;
        //$this->HostInfo = $HostInfo;
    }
	
    //fonction a executer pour recuperer les informations d'un fichier en fonction d'un lien
    public function GetDownloadInfo()
    {
        sleep(3);
        
        $curl = curl_init();

        $p_data = [
            'url'=>$this->Url,
        ];


        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.1fichier.com/v1/download/get_token.cgi',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>json_encode($p_data),
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->apikey
          ),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
        $data = json_decode($response,true);
        //var_dump($data);
        if (null === $data || false === $data) {
            return [DOWNLOAD_ERROR => ERR_UNKNOWN];
        }
        if ('OK' !== $data['status']) {
            return [DOWNLOAD_ERROR => ERR_UNKNOWN];
        }
        if (!array_key_exists('url',$data)) {
            return [DOWNLOAD_ERROR => ERR_UNKNOWN];
        }
        
        $download_url = $data['url'];

        $curl = curl_init();

        $p_data = [
            'url'=>$this->Url,
        ];


        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.1fichier.com/v1/file/info.cgi',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>json_encode($p_data),
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->apikey
          ),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
        $data = json_decode($response,true);

        if (null === $data || false === $data) {
            return [DOWNLOAD_ERROR => ERR_UNKNOWN];
        }
        if (!array_key_exists('filename',$data)) {
            return [DOWNLOAD_ERROR => ERR_UNKNOWN];
        }

        return [
            INFO_NAME => $data['filename'],
            DOWNLOAD_ISPARALLELDOWNLOAD => true,
            DOWNLOAD_URL => $download_url
        ];

        
    }
    
    //verifie le type de compte entré
    public function Verify($ClearCookie)
    {
        sleep(3);
        return $this->TypeAccount($this->apikey);
    }
    
    
    private function TypeAccount($apikey)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.1fichier.com/v1/user/info.cgi',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>'{}',
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer '.$apikey
          ),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
        $data = json_decode($response,true);
        
        if (null === $data || false === $data) {
            return LOGIN_FAIL;
        }
        if ('OK' !== $data['status']) {
            return LOGIN_FAIL;
        }
        if (!array_key_exists('offer',$data)) {
            return LOGIN_FAIL;
        }
        switch((int)$data['offer']) {
            case 0;
                return USER_IS_FREE;
            case 1;
            case 2;
                return USER_IS_PREMIUM;
        }
        return LOGIN_FAIL;
    }
}
?>