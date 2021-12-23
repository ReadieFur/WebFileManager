CREATE TABLE IF NOT EXISTS `webfilemanager_paths`(
    `id` char(128) NOT NULL PRIMARY KEY,
    `web_path` varchar(256) NOT NULL,
    `local_path` varchar(256) NOT NULL
);