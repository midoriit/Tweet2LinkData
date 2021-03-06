CREATE TABLE `opendata` (
  `tweet_ID` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `user_name` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
  `screen_name` varchar(255) NOT NULL,
  `tweet_text` varchar(500) CHARACTER SET utf8mb4 NOT NULL,
  `place_full_name` varchar(255) NOT NULL,
  `geo_lat` decimal(20,10) NOT NULL,
  `geo_lon` float(20,10) NOT NULL,
  `tweet_url` varchar(255) NOT NULL,
  `media_url_1` varchar(255) NOT NULL,
  `media_url_2` varchar(255) DEFAULT NULL,
  `media_url_3` varchar(255) DEFAULT NULL,
  `media_url_4` varchar(255) DEFAULT NULL,
  `media_url_5` varchar(255) DEFAULT NULL,
  `invalid` tinyint(1) NOT NULL DEFAULT '0',
  `pname` varchar(255) DEFAULT NULL,
  `mname` varchar(255) DEFAULT NULL,
  `section` varchar(255) DEFAULT NULL,
  `embed_html` text,
  UNIQUE KEY `tweet_ID` (`tweet_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
