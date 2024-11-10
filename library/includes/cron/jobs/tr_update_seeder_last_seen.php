<?php

if (!defined('BB_ROOT')) die(basename(__FILE__));

DB()->query("
	UPDATE
		". BUF_LAST_SEEDER ." b,
		". BB_BT_TORRENTS     ." tor
	SET
		tor.seeder_last_seen = b.seeder_last_seen,
		tor.last_seeder_id = b.user_id
	WHERE
		tor.topic_id = b.topic_id
");

DB()->query("TRUNCATE TABLE ".  BUF_LAST_SEEDER);
