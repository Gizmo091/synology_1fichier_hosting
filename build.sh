#!/bin/bash
CUR_DIR=$(pwd)
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PHP_FILE=$SCRIPT_DIR"/OneFichierCom.php"
INFO_FILE=$SCRIPT_DIR"/INFO"
VERSION=$(cat "$PHP_FILE" | grep "@Version :" | awk '{print $3}')
cd "$SCRIPT_DIR"
tar -czvf "OneFichierCom($VERSION).host" INFO OneFichierCom.php 
cd "$CUR_DIR"