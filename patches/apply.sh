#!/bin/bash

set -e

CURRENT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )

git apply --verbose --no-index --ignore-whitespace $CURRENT_DIR/php73.diff
