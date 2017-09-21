#!/bin/sh

ZIP_FILE_NAME=shopgate-veyton-integration.zip

rm -rf src/vendor release/package $ZIP_FILE_NAME
mkdir release/package && mkdir release/package/xt_shopgate
composer install -vvv --no-dev
rsync -av --exclude-from './release/exclude-filelist.txt' ./src/ release/package/xt_shopgate
rsync -av ./modman release/package/xt_shopgate
rsync -av ./README.md release/package/xt_shopgate
rsync -av ./LICENSE.md release/package/xt_shopgate
rsync -av ./CONTRIBUTING.md release/package/xt_shopgate
rsync -av ./CHANGELOG.md release/package/xt_shopgate
cd release/package/
zip -r ../../$ZIP_FILE_NAME .
