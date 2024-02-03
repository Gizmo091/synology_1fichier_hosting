#!/bin/bash
CUR_DIR=$(pwd)
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PHP_FILE=$SCRIPT_DIR"/OneFichierCom.php"
INFO_FILE=$SCRIPT_DIR"/INFO"
VERSION=$(cat "$INFO_FILE" | grep version | awk -F  ':' '{print $2}' | awk -F '"' '{print $2}')
cd "$SCRIPT_DIR"
tar -czvf "OneFichierCom($VERSION).host" INFO OneFichierCom.php 
cd "$CUR_DIR"