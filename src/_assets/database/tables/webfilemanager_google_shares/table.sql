CREATE TABLE IF NOT EXISTS `webfilemanager_google_shares`(
  `id` char(128) PRIMARY KEY NOT NULL,
  `sid` char(128) NOT NULL,
  `user` varchar(128) NOT NULL
);