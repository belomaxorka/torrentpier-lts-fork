<?php

define('IN_FORUM', true);
define('NO_GZIP', true);
define('BB_SCRIPT', 'dl');
define('BB_ROOT',  './');
require(BB_ROOT .'common.php');
require(ATTACH_DIR .'attachment_mod.php');

$datastore->enqueue(array(
	'attach_extensions',
));

$download_id = request_var('id', 0);
$thumbnail = request_var('thumb', 0);

// Send file to browser
function send_file_to_browser($attachment, $upload_dir)
{
	global $bb_cfg, $lang, $userdata;

	$filename = ($upload_dir == '') ? $attachment['physical_filename'] : $upload_dir . '/' . $attachment['physical_filename'];

	$gotit = false;

	if (@!file_exists(@amod_realpath($filename)))
	{
		bb_die($lang['ERROR_NO_ATTACHMENT'] . "<br /><br />" . $filename);
	}
	else
	{
		$gotit = true;
	}

	// Correct the mime type - we force application/octet-stream for all files, except images
	// Please do not change this, it is a security precaution
	if (!strstr($attachment['mimetype'], 'image'))
	{
		$attachment['mimetype'] = 'application/octet-stream';
	}

	//bt
	if (!(isset($_GET['original']) && !IS_USER))
	{
		include(INC_DIR .'functions_torrent.php');
		send_torrent_with_passkey($filename);
	}

	// Now the tricky part... let's dance
	header('Pragma: public');
	$real_filename = clean_filename(basename($attachment['real_filename']));
	$mimetype = $attachment['mimetype'].';';
	$encoding = isset($bb_cfg['lang'][$userdata['user_lang']]['encoding']) ? $bb_cfg['lang'][$userdata['user_lang']]['encoding'] : 'utf-8';
	$charset = "charset=$encoding;";

	// Send out the Headers
	header("Content-Type: $mimetype $charset name=\"$real_filename\"");
	header("Content-Disposition: inline; filename=\"$real_filename\"");
	unset($real_filename);

	// Now send the File Contents to the Browser
	if ($gotit)
	{
		$size = @filesize($filename);
		if ($size)
		{
			header("Content-length: $size");
		}
		readfile($filename);
	}
	else
	{
		bb_die($lang['ERROR_NO_ATTACHMENT'] . "<br /><br />" . $filename);
	}

	exit;
}

//
// Start Session Management
//
$user->session_start();

set_die_append_msg();

if (!$download_id)
{
	bb_die($lang['NO_ATTACHMENT_SELECTED']);
}

if ($attach_config['disable_mod'] && !IS_ADMIN)
{
	bb_die($lang['ATTACHMENT_FEATURE_DISABLED']);
}

$sql = 'SELECT * FROM ' . BB_ATTACHMENTS_DESC . ' WHERE attach_id = ' . (int) $download_id;

if (!($result = DB()->sql_query($sql)))
{
	bb_die('Could not query attachment information #1');
}

if (!($attachment = DB()->sql_fetchrow($result)))
{
	bb_die($lang['ERROR_NO_ATTACHMENT']);
}

$attachment['physical_filename'] = basename($attachment['physical_filename']);

// Re-define download mode for thumbnails
if ($thumbnail)
{
	$attachment['physical_filename'] = THUMB_DIR . '/t_' . $attachment['physical_filename'];
}

DB()->sql_freeresult($result);

// get forum_id for attachment authorization or private message authorization
$authorised = false;

$sql = 'SELECT * FROM ' . BB_ATTACHMENTS . ' WHERE attach_id = ' . (int) $attachment['attach_id'];

if (!($result = DB()->sql_query($sql)))
{
	bb_die('Could not query attachment information #2');
}

$auth_pages = DB()->sql_fetchrowset($result);
$num_auth_pages = DB()->num_rows($result);

for ($i = 0; $i < $num_auth_pages && $authorised == false; $i++)
{
	$auth_pages[$i]['post_id'] = intval($auth_pages[$i]['post_id']);

	if ($auth_pages[$i]['post_id'] != 0)
	{
		$sql = 'SELECT forum_id, topic_id FROM ' . BB_POSTS . ' WHERE post_id = ' . (int) $auth_pages[$i]['post_id'];

		if (!($result = DB()->sql_query($sql)))
		{
			bb_die('Could not query post information');
		}

		$row = DB()->sql_fetchrow($result);

		$topic_id = $row['topic_id'];
		$forum_id = $row['forum_id'];

		$is_auth = array();
		$is_auth = auth(AUTH_ALL, $forum_id, $userdata);
		set_die_append_msg($forum_id, $topic_id);

		if ($is_auth['auth_download'])
		{
			$authorised = TRUE;
		}
	}
}

if (!$authorised)
{
	bb_die($lang['SORRY_AUTH_VIEW_ATTACH']);
}

$datastore->rm('cat_forums');

//
// Check tor status
//
if (!IS_AM)
{
	$sql = "SELECT tor_status, poster_id FROM " . BB_BT_TORRENTS . " WHERE attach_id = " . (int) $attachment['attach_id'];

	if (!($result = DB()->sql_query($sql)))
	{
		bb_die('Could not query tor_status information');
	}

	$row = DB()->sql_fetchrow($result);

	if (isset($bb_cfg['tor_frozen'][$row['tor_status']]) && !(isset($bb_cfg['tor_frozen_author_download'][$row['tor_status']]) && $userdata['user_id'] == $row['poster_id']))
	{
		bb_die($lang['TOR_STATUS_FORBIDDEN'] . $lang['TOR_STATUS_NAME'][$row['tor_status']]);
	}

	DB()->sql_freeresult($result);
}

//
// Get Information on currently allowed Extensions
//
$rows = get_extension_informations();
$num_rows = count($rows);

$allowed_extensions = array();
$download_mode = array();
for ($i = 0; $i < $num_rows; $i++)
{
	$extension = strtolower(trim($rows[$i]['extension']));
	$allowed_extensions[] = $extension;
	$download_mode[$extension] = $rows[$i]['download_mode'];
}

// Disallowed
if (!in_array($attachment['extension'], $allowed_extensions))
{
	bb_die(sprintf($lang['EXTENSION_DISABLED_AFTER_POSTING'], $attachment['extension']) . "<br /><br />" . $lang['FILENAME'] . ":&nbsp;" . $attachment['physical_filename']);
}

// Getting download mode by extension
if (!$download_mode = intval($download_mode[$attachment['extension']]))
{
	bb_die('Incorrect download mode');
}

// Update download count
if (!$thumbnail)
{
	$sql = 'UPDATE ' . BB_ATTACHMENTS_DESC . ' SET download_count = download_count + 1 WHERE attach_id = ' . (int) $attachment['attach_id'];

	if (!DB()->sql_query($sql))
	{
		bb_die('Could not update attachment download count');
	}
}

// Determine the 'presenting'-method
if ($download_mode == PHYSICAL_LINK)
{
	$url = make_url($upload_dir . '/' . $attachment['physical_filename']);
	header('Location: ' . $url);
	exit;
}
elseif ($download_mode == INLINE_LINK)
{
	if ((IS_GUEST && !$bb_cfg['captcha']['disabled']) && !bb_captcha('check'))
	{
		global $template;

		$redirect_url = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/');
		$message = '<form action="'. DOWNLOAD_URL . $attachment['attach_id'] .'" method="post">';
		$message .= $lang['CAPTCHA'].':';
		$message .= '<div  class="mrg_10" align="center">'. bb_captcha('get') .'</div>';
		$message .= '<input type="hidden" name="redirect_url" value="'. $redirect_url .'" />';
		$message .= '<input type="submit" class="bold" value="'. $lang['SUBMIT'] .'" /> &nbsp;';
		$message .= '<input type="button" class="bold" value="'. $lang['GO_BACK'] .'" onclick="document.location.href = \''. $redirect_url .'\';" />';
		$message .= '</form>';

		$template->assign_vars(array(
			'ERROR_MESSAGE' => $message,
		));

		require(PAGE_HEADER);
		require(PAGE_FOOTER);
	}

	send_file_to_browser($attachment, $upload_dir);
	exit;
}
else
{
	bb_die('Incorrect download mode: ' . $download_mode);
}
