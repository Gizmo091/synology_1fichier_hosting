#!/bin/bash
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
docker run --volume "$SCRIPT_DIR:/home/code" "ubuntu:18.04" bash /home/code/build.sh