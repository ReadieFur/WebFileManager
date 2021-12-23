CREATE TABLE IF NOT EXISTS `webfilemanager_users`(
    `id` char(22) NOT NULL PRIMARY KEY,
    `username` varchar(32) NOT NULL,
    `password` char(60) NOT NULL,
    `sessionToken` varchar(64) DEFAULT NULL
);