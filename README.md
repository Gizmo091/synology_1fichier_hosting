Repo for 1fichier hosting file usable in DL Station Synology Application

# WARNING 

Since release 4.0.0 the package is only usable by premium accounts due to API usage restrictions

[!["Buy Me A Coffee"](https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png)](https://www.buymeacoffee.com/mathieuvedie)


# BUG KNOWN : 

- [Fixed since 4.1.0] Credential validation return error sometimes, ignore this error if you are sure of your key. The 1fichier API can sometimes return over quota response. 

- Conflict with alldebrid host (certainly the 4.3.0 version, maybe other) : The alldebrid host seems to take priority over 1fichier host and cause error durring download. 

# VERSIONS :
- OneFichierCom(3.2.9).host : Free, Premium and Access, (+CDN) (crawling website)
- OneFichierCom(4.1.0).host : Premium and Access only, password must be an apikey (API usage), log disable by default
- OneFichierCom(4.2.0).host : Add support for already tokenized link (link sample : https://a-6.1fichier.com/p1058755667)
- OneFichierCom(4.3.0).host : Real file name is now the destination file name ( without added _ )
- OneFichierCom(4.4.0).host : Disable ssl certificate verification.
- OneFichierCom(4.5.0).host : Fallback on curl HEAD requests to get filename when api refused to return file name (owner locked ...)
- OneFichierCom(4.6.0).host : Url of verify file (hosted on 1fichier) is retrieve from github repo (verify.html)

# CUSTOM CONFIGURATION 

When you configure your username/apikey informations, you can add custom configuration in place of username

## Configuration key available :
- remote_log : enable remote log. So log are sent to an external server using cURL. 
sample : remote_log=https://vedie.fr/remote_log/log.php ( you can host your own remote_log server ( see remote_log/log.php in this repo)) 
- local_log : Log are disabled by default. Enable local_log by setting this value to 1
sample : local_log=1


# HOW TO BUILD 

Directly from CLI : 
```shell
bash build.sh
```

Or using docker environment (from CLI) : 
```shell
bash build_with_docker.sh
```

Use the OneFichierCom(\<version\>).host generated.
