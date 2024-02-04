<?php

/*
	@auteur : warkx
	@version originale Developpé le : 23/11/2013
	@Version : 3.2.9
	@firstversion : 07/07/2019
	@description : Support du compte gratuit, access, premium et CDN

    Module are installed in /var/packages/DownloadStation/etc/download/userhosts on the Synology
	
	Packaging by => tar zcf "OneFichierCom_X.host" INFO OneFichierCom.php
    Update : 
    - 3.2.8 : Ajout de la version anglaise du controle du 2024-02-02
    - 3.2.7 : Ajout d'un test pour verifier que le compte est premium.
 */

class SynoFileHosting
{
    private $Url;
    private $HostInfo;
    private $apikey;
    private $Password;
    private $FILEID;
    private $ORIGINAL_URL;
    private $ACCOUNT_TYPE;
    private $ADZONE_NAME = 'adz';
    private $ADZONE_VALUE = '';
    
    private $ENABLE_DEBUG = TRUE;
    private $ENABLE_DEBUG_HTML = FALSE;
	private $ENABLE_DEBUGCURL_VERBOSE = FALSE;
    private $LOG_DIR = '/tmp/';
    private $LOG_DIR_HTML = '1fichier_log/';
    private $LOG_FILE = '1fichier.log';
    private $LOG_FILE_WINNT = 'C:\intel\1fichier.log';
    
    private $COOKIE_PATH = '/tmp/1fichier.cookie';
    private $COOKIE_PATH_WINNT = 'C:\intel\1fichier.cookie';
    
    private $CHECKLINK_URL_REQ = 'https://1fichier.com/check_links.pl';
    
    private $FILEID_REGEX = '`https?:\/\/1fichier\.com\/\?([a-zA-Z0-9]+)\/?`i';
    private $FILEID_OLD_REGEX = '`https?:\/\/([a-z0-9A-Z]+)\.1fichier\.com\/?`i';
    private $FILE_OFFLINE_REGEX = '`BAD LINK|NOT FOUND`i';
    private $DOWNLOAD_WAIT_REGEX = '`You must wait (\d+) minutes`i';
    private $PREMIUM_REAL_URL_REGEX = '`https?:\/\/[a-z0-9]+-[a-z0-9]+\.1fichier\.com\/[a-z0-9]+`i';
    private $FREE_REAL_URL_REGEX = '`href=\"(https?:\/\/[a-z0-9]+-[a-z0-9]+\.1fichier\.com\/[a-z0-9]+)\"?`i';
    private $DEBUG_REGEX = '/(https?:\/\/1fichier\.com\/.+)\/debug/i';
    // private $ADZONE_REGEX = '`name="adzone" value="(.+?)"`i';
    private $ADZONE_REGEX = '/name="adz" value="([0-9A-z.]*)"/'; /*new REGEX by Babasss*/
    
    private $PREMIUM_TYPE_REGEX = '`(^[0-9]{2}+)`i';
    
    private $WAITING_TIME_DEFAULT = 300;
    private $QUERYAGAIN = 1;
	
	private $MIN_CDN_GB = 5;
	private $CDN_FR_REGEX='`Votre compte a ([0-9.]+) Go`i';
    private $CDN_EN_REGEX='`Your account have ([0-9.]+) GB`i';
	private $CDN_CHECKBOX_REGEX='<input type="checkbox" checked="checked" name="own_credit">';
    
    public function __construct($Url, $Username, $Password, $HostInfo)
    {
        $this->Url = explode('&',$Url)[0];
        $this->apikey = $Username;
        // password never used 
        //$this->Password = $Password;
        $this->HostInfo = $HostInfo;
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
        //var_dump($data);
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