Repo for 1fichier hosting file usable in DL Station Synology Application

# WARNING 

Since release 4.0.0 the package is only usable by premium accounts due to API usage restrictions

# VERSIONS : 
- OneFichierCom(3.2.9).host : Free, Premium and Access, (+CDN) (crawling website)
- OneFichierCom(4.0.7).host : Premium and Access only, password must be an apikey (API usage), log disable by default

# CUSTOM CONFIGURATION 

When you configure your username/apikey informations, you can add custom configuration in place of username

## Configuration key available :
- remote_log : enable remote log. So log are sent to an external server using cURL. If remote_log is enabled, no local log are write
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
