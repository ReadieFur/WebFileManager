#https://askubuntu.com/questions/333640/cp-command-to-exclude-certain-files-from-being-copied
rsync -avr src/ dist/ --exclude '_assets/configuration/config.json' --exclude '_assets/libs/vendor' --exclude '_storage';
zip -r dist.zip dist/;
rm -rf dist;