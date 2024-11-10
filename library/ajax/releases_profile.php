<?php

if (!defined('IN_AJAX')) die(basename(__FILE__));

global $lang, $bb_cfg;

if (!$user_id = intval($this->request['user_id']) or !$profiledata = get_userdata($user_id)) {
	$this->ajax_die($lang['NO_USER_ID_SPECIFIED']);
}

if (!$mode = (string)$this->request['mode']) {
	$this->ajax_die('invalid mode (empty)');
}

if (isset($bb_cfg['show_completed_count'])) {
	$show_completed_count = ($bb_cfg['show_completed_count'] || $bb_cfg['ocelot']['enabled']);
} else {
	$show_completed_count = true;
}

switch ($mode) {
	case 'get_releases_profile':
		$total_releases_size = $total_releases = $total_releases_completed = $total_releases_completed_full = 0;

		$sql = "
			SELECT COUNT(tor.poster_id), SUM(tor.size), SUM(ad.download_count), SUM(tor.complete_count)
			FROM            " . BB_BT_TORRENTS . " tor
				LEFT JOIN    " . BB_USERS . " u ON(u.user_id = tor.poster_id)
				LEFT JOIN    " . BB_ATTACHMENTS_DESC . " ad ON(ad.attach_id = tor.attach_id)
				LEFT JOIN    " . BB_BT_USERS . " ut ON(ut.user_id = tor.poster_id)
			WHERE u.user_id = $user_id
			GROUP BY tor.poster_id
			LIMIT 1
		";

		if ($row = DB()->fetch_row($sql)) {
			$total_releases = $row['COUNT(tor.poster_id)'];
			$total_releases_size = $row['SUM(tor.size)'];
			$total_releases_completed = $row['SUM(ad.download_count)'];
			$total_releases_completed_full = $show_completed_count ? $row['SUM(tor.complete_count)'] : $total_releases_completed;
		}

		$this->response['releases_profile_html'] = '[
			' . $lang['RELEASES'] . ': <span class="seed bold">' . $total_releases . '</span> |
			' . $lang['RELEASER_STAT_SIZE'] . ' <span class="seed bold">' . humn_size($total_releases_size) . '</span> |
			' . $lang['DOWNLOADED'] . ': <span title="' . ($show_completed_count ? $lang['COMPLETED'] : $lang['DOWNLOADED']) . ':&nbsp;' . declension((int)$total_releases_completed_full, 'times') . '" class="seed bold">' . declension((int)$total_releases_completed, 'times') . '</span> ]';
		break;

	default:
		$this->ajax_die("invalid mode: $mode");
}
