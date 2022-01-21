#https://askubuntu.com/questions/333640/cp-command-to-exclude-certain-files-from-being-copied
rm dist.zip;
rsync -avr src/ dist/ --exclude '_assets/configuration/config.json' --exclude '_assets/libs/vendor' --exclude '_assets/libs/composer.lock' --exclude '_storage' --exclude 'node_modules' --exclude 'test.php';
zip -r dist.zip dist/;
rm -rf dist;