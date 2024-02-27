#!/bin/bash

branch=$(git symbolic-ref HEAD | sed -e 's,.*/\(.*\),\1,')

# Make store version
echo "Make store version"
rm -rf endereco_jtl5_client
rsync -ar --exclude 'endereco_jtl5_client' --exclude 'node_modules' ./* ./endereco_jtl5_client
cp .htaccess ./endereco_jtl5_client/.htaccess
rm endereco_jtl5_client/webpack.config.js
rm endereco_jtl5_client/ruleset.xml
rm endereco_jtl5_client/package-lock.json
rm endereco_jtl5_client/package.json
rm endereco_jtl5_client/endereco.scss
rm endereco_jtl5_client/endereco.js
rm endereco_jtl5_client/composer.json
rm endereco_jtl5_client/build.sh

zip -r endereco-jtl5-client-store-$branch.zip endereco_jtl5_client

echo "Make github version"
sed -i '/<ExsID>f9cb7819-9dad-4a4f-8aea-fcca10d6b59c<\/ExsID>/d' ./endereco_jtl5_client/info.xml

zip -r endereco-jtl5-client-$branch.zip endereco_jtl5_client

echo "Clean up"
rm -rf endereco_jtl5_client