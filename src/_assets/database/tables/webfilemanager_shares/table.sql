-- Tweaked from: https://github.com/kOFReadie/api-readie/blob/main/database/readie/cloud/cloud_files.sql 
CREATE TABLE IF NOT EXISTS `webfilemanager_shares`(
    `id` char(128) NOT NULL PRIMARY KEY,
    `uid` char(128) NOT NULL,
    `file` varchar(256) NOT NULL,
    `shareType` smallInt(6) NOT NULL DEFAULT 0,
    `publicExpiryTime` varchar(32) NOT NULL DEFAULT '-1'
);