<?php
// -------------------------------------------------------------
//
// $Id$
//
// FILENAME  : compose.php
// STARTED   : Sat Mar 27, 2004
// COPYRIGHT : � 2004 phpBB Group
// WWW       : http://www.phpbb.com/
// LICENCE   : GPL vs2.0 [ see /docs/COPYING ] 
// 
// -------------------------------------------------------------

// * Called from ucp_pm with mode == 'compose'

function compose_pm($id, $mode, $action)
{
	global $template, $db, $auth, $user;
	global $phpbb_root_path, $phpEx, $config, $SID;
	
	include($phpbb_root_path . 'includes/functions_admin.'.$phpEx);
	include($phpbb_root_path . 'includes/functions_posting.'.$phpEx);
	include($phpbb_root_path . 'includes/message_parser.'.$phpEx);

	if (!$action)
	{
		$action = 'post';
	}

	// Grab only parameters needed here
	$msg_id			= request_var('p', 0);
	$quote_post		= request_var('q', 0);
	$draft_id		= request_var('d', 0);
	$lastclick		= request_var('lastclick', 0);

	// Do NOT use request_var or specialchars here
	$address_list	= isset($_REQUEST['address_list']) ? $_REQUEST['address_list'] : array();

	$submit		= (isset($_POST['post']));
	$preview	= (isset($_POST['preview']));
	$save		= (isset($_POST['save']));
	$load		= (isset($_POST['load']));
	$cancel		= (isset($_POST['cancel']));
	$confirm	= (isset($_POST['confirm']));
	$delete		= (isset($_POST['delete']));

	$remove_u	= (isset($_REQUEST['remove_u']));
	$remove_g	= (isset($_REQUEST['remove_g']));
	$add_to		= (isset($_REQUEST['add_to']));
	$add_bcc	= (isset($_REQUEST['add_bcc']));

	$refresh	= isset($_POST['add_file']) || isset($_POST['delete_file']) || isset($_POST['edit_comment']) || $save || $load
		|| $remove_u || $remove_g || $add_to || $add_bcc;

	$action		= ($delete && !$preview && !$refresh && $submit) ? 'delete' : $action;

	$error = array();
	$current_time = time();

	// Was cancel pressed? If so then redirect to the appropriate page
	if ($cancel || ($current_time - $lastclick < 2 && $submit))
	{
		$redirect = "{$phpbb_root_path}ucp.$phpEx$SID&amp;i=$id&amp;mode=view_messages&amp;action=view_message" . (($msg_id) ? "&amp;p=$msg_id" : '');
		redirect($redirect);
	}

	$sql = '';

	// What is all this following SQL for? Well, we need to know
	// some basic information in all cases before we do anything.
	switch ($action)
	{
		case 'post':
			if (!$auth->acl_get('u_sendpm'))
			{
				trigger_error('NOT_AUTHORIZED_POST_PM');
			}
		
			break;

		case 'reply':
		case 'quote':
		case 'forward':
			if (!$msg_id)
			{
				trigger_error('NO_PM');
			}
					
			if ($quote_post)
			{
				$sql = 'SELECT p.post_text as message_text, p.poster_id as author_id, p.post_time as message_time, p.bbcode_bitfield, p.bbcode_uid, p.enable_sig, p.enable_html, p.enable_smilies, p.enable_magic_url, t.topic_title as message_subject, u.username as quote_username
					FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t, ' . USERS_TABLE . " u
					WHERE p.post_id = $msg_id
						AND t.topic_id = p.topic_id
						AND u.user_id = p.poster_id";
			}
			else
			{
				$sql = 'SELECT t.*, p.*, u.username as quote_username
					FROM ' . PRIVMSGS_TO_TABLE . ' t, ' . PRIVMSGS_TABLE . ' p, ' . USERS_TABLE . ' u
					WHERE t.user_id = ' . $user->data['user_id'] . "
						AND p.author_id = u.user_id
						AND t.msg_id = p.msg_id
						AND p.msg_id = $msg_id";
			}
			break;

		case 'edit':
			if (!$msg_id)
			{
				trigger_error('NO_PM');
			}

			// check for outbox (not read) status, we do not allow editing if one user already having the message
			$sql = 'SELECT p.*, t.*
				FROM ' . PRIVMSGS_TO_TABLE . ' t, ' . PRIVMSGS_TABLE . ' p
				WHERE t.user_id = ' . $user->data['user_id'] . '
					AND t.folder_id = ' . PRIVMSGS_OUTBOX . "
					AND t.msg_id = $msg_id
					AND t.msg_id = p.msg_id";
			break;

		case 'delete':
			if (!$auth->acl_get('u_pm_delete'))
			{
				trigger_error('NOT_AUTHORIZED_DELETE_PM');
			}
		
			if (!$msg_id)
			{
				trigger_error('NO_PM');
			}

			$sql = 'SELECT msg_id, unread, new, author_id
				FROM ' . PRIVMSGS_TO_TABLE . '
				WHERE user_id = ' . $user->data['user_id'] . "
					AND msg_id = $msg_id";
			break;

		case 'smilies':
			generate_smilies('window', 0);
			break;

		default:
			trigger_error('NO_POST_MODE');
	}

	if ($action == 'reply' && !$auth->acl_get('u_sendpm'))
	{
		trigger_error('NOT_AUTHORIZED_REPLY_PM');
	}

	if ($action == 'quote' && (!$config['auth_quote_pm'] || !$auth->acl_get('u_sendpm')))
	{
		trigger_error('NOT_AUTHORIZED_QUOTE_PM');
	}

	if ($action == 'forward' && (!$config['forward_pm'] || !$auth->acl_get('u_pm_forward')))
	{
		trigger_error('NOT_AUTHORIZED_FORWARD_PM');
	}

	if ($action == 'edit' && !$auth->acl_get('u_pm_edit'))
	{
		trigger_error('NOT_AUTHORIZED_EDIT_PM');
	}

	if ($sql)
	{
		$result = $db->sql_query_limit($sql, 1);

		if (!($row = $db->sql_fetchrow($result)))
		{
			trigger_error('NOT_AUTHORIZED');
		}

		extract($row);
		$db->sql_freeresult($result);
		
		$msg_id = (int) $msg_id;
		$enable_urls = $enable_magic_url;

		if (!$author_id && $msg_id)
		{
			trigger_error('NO_USER');
		}

		if (($action == 'reply' || $action == 'quote') && !sizeof($address_list) && !$refresh && !$submit && !$preview)
		{
			$address_list = array('u' => array($author_id => 'to'));
		}
		else if ($action == 'edit' && !sizeof($address_list) && !$refresh && !$submit && !$preview)
		{
			// Rebuild TO and BCC Header
			$address_list = rebuild_header(array('to' => $to_address, 'bcc' => $bcc_address));
		}
	}
	else
	{
		$message_attachment = 0;
		$message_text = $subject = '';
	}

	if ($action == 'edit' && !$refresh && !$preview && !$submit)
	{
		if (!($message_time > time() - $config['pm_edit_time'] || !$config['pm_edit_time']))
		{
			trigger_error('NOT_AUTHORIZED_EDIT_TIME');
		}
	}

	$message_parser = new parse_message();

	$message_subject = (isset($message_subject)) ? $message_subject : '';
	$message_text = ($action == 'reply') ? '' : ((isset($message_text)) ? $message_text : '');
	$icon_id = 0;

	$s_action = "{$phpbb_root_path}ucp.$phpEx?sid={$user->session_id}&amp;i=$id&amp;mode=$mode&amp;action=$action";
	$s_action .= ($msg_id) ? "&amp;p=$msg_id" : '';
	$s_action .= ($quote_post) ? "&amp;q=1" : '';

	// Handle User/Group adding/removing
	handle_message_list_actions($address_list, $remove_u, $remove_g, $add_to, $add_bcc);

	// Check for too many recipients
	if (!$config['allow_mass_pm'] && num_recipients($address_list) > 1)
	{
		$address_list = get_recipient_pos($address_list, 1);
		$error[] = $user->lang['TOO_MANY_RECIPIENTS'];
	}

	$message_parser->get_submitted_attachment_data();

	if ($message_attachment && !$submit && !$refresh && !$preview && $action == 'edit')
	{
		$sql = 'SELECT attach_id, physical_filename, comment, real_filename, extension, mimetype, filesize, filetime, thumbnail
			FROM ' . ATTACHMENTS_TABLE . "
			WHERE post_msg_id = $msg_id
				AND in_message = 1
				ORDER BY filetime " . ((!$config['display_order']) ? 'DESC' : 'ASC');
		$result = $db->sql_query($sql);

		$message_parser->attachment_data = array_merge($message_parser->attachment_data, $db->sql_fetchrowset($result));
		
		$db->sql_freeresult($result);
	}
	
	if (!in_array($action, array('quote', 'edit', 'delete', 'forward')))
	{
		$enable_sig		= ($config['allow_sig_pm'] && $auth->acl_get('u_pm_sig') && $user->optionget('attachsig'));
		$enable_smilies	= ($config['allow_smilies'] && $auth->acl_get('u_pm_smilies') && $user->optionget('smile'));
		$enable_bbcode	= ($config['allow_bbcode'] && $auth->acl_get('u_pm_bbcode') && $user->optionget('bbcode'));
		$enable_urls	= true;
	}

	$enable_magic_url = $drafts = false;

	// User own some drafts?
	if ($auth->acl_get('u_savedrafts') && $action != 'delete')
	{
		$sql = 'SELECT draft_id
			FROM ' . DRAFTS_TABLE . '
			WHERE (forum_id = 0 AND topic_id = 0)
				AND user_id = ' . $user->data['user_id'] . 
				(($draft_id) ? " AND draft_id <> $draft_id" : '');
		$result = $db->sql_query_limit($sql, 1);

		if ($db->sql_fetchrow($result))
		{
			$drafts = true;
		}
		$db->sql_freeresult($result);
	}

	if ($action == 'edit' || $action == 'forward')
	{
		$message_parser->bbcode_uid = $bbcode_uid;
	}

	// Delete triggered ?
	if ($action == 'delete')
	{
		// Get Folder ID
		$folder_id = request_var('f', PRIVMSGS_NO_BOX);

		$s_hidden_fields = '<input type="hidden" name="p" value="' . $msg_id . '" /><input type="hidden" name="f" value="' . $folder_id . '" /><input type="hidden" name="action" value="delete" />';

		// Do we need to confirm ?
		if (confirm_box(true))
		{
			delete_pm($user->data['user_id'], $msg_id, $folder_id);
						
			// TODO - jump to next message in "history"?
			$meta_info = "{$phpbb_root_path}ucp.$phpEx$SID&amp;i=pm&amp;folder=$folder_id";
			$message = $user->lang['PM_DELETED'];

			meta_refresh(3, $meta_info);
			$message .= '<br /><br />' . sprintf($user->lang['RETURN_FOLDER'], '<a href="' . $meta_info . '">', '</a>');
			trigger_error($message);
		}
		else
		{
			// "{$phpbb_root_path}ucp.$phpEx$SID&amp;i=pm&amp;mode=compose"
			confirm_box(false, 'DELETE_PM', $s_hidden_fields);
		}
	}

	$html_status	= ($config['allow_html'] && $config['auth_html_pm'] && $auth->acl_get('u_pm_html'));
	$bbcode_status	= ($config['allow_bbcode'] && $config['auth_bbcode_pm'] && $auth->acl_get('u_pm_bbcode'));
	$smilies_status	= ($config['allow_smilies'] && $config['auth_smilies_pm'] && $auth->acl_get('u_pm_smilies'));
	$img_status		= ($config['auth_img_pm'] && $auth->acl_get('u_pm_img'));
	$flash_status	= ($config['auth_flash_pm'] && $auth->acl_get('u_pm_flash'));
	$quote_status	= ($config['auth_quote_pm']);

	// Save Draft
	if ($save && $auth->acl_get('u_savedrafts'))
	{
		$subject = preg_replace('#&amp;(\#[0-9]+;)#', '&\1', request_var('subject', ''));
		$subject = (!$subject && $action != 'post') ? $user->lang['NEW_MESSAGE'] : $subject;
		$message = (isset($_POST['message'])) ? htmlspecialchars(trim(str_replace(array('\\\'', '\\"', '\\0', '\\\\'), array('\'', '"', '\0', '\\'), $_POST['message']))) : '';
		$message = preg_replace('#&amp;(\#[0-9]+;)#', '&\1', $message);

		if ($subject && $message)
		{
			$sql = 'INSERT INTO ' . DRAFTS_TABLE . ' ' . $db->sql_build_array('INSERT', array(
				'user_id'	=> $user->data['user_id'],
				'topic_id'	=> 0,
				'forum_id'	=> 0,
				'save_time'	=> $current_time,
				'draft_subject' => $subject,
				'draft_message' => $message));
			$db->sql_query($sql);
	
			meta_refresh(3, "ucp.$phpEx$SID&i=pm&mode=$mode");

			$message = $user->lang['DRAFT_SAVED'] . '<br /><br />' . sprintf($user->lang['RETURN_UCP'], "<a href=\"ucp.$phpEx$SID&amp;i=pm&amp;mode=$mode\">", '</a>');

			trigger_error($message);
		}

		unset($subject);
		unset($message);
	}

	// Load Draft
	if ($draft_id && $auth->acl_get('u_savedrafts'))
	{
		$sql = 'SELECT draft_subject, draft_message 
			FROM ' . DRAFTS_TABLE . " 
			WHERE draft_id = $draft_id
				AND topic_id = 0
				AND forum_id = 0
				AND user_id = " . $user->data['user_id'];
		$result = $db->sql_query_limit($sql, 1);
	
		if ($row = $db->sql_fetchrow($result))
		{
			$_REQUEST['subject'] = $row['draft_subject'];
			$_POST['message'] = $row['draft_message'];
			$refresh = true;
			$template->assign_var('S_DRAFT_LOADED', true);
		}
		else
		{
			$draft_id = 0;
		}
	}

	// Load Drafts
	if ($load && $drafts)
	{
		load_drafts(0, 0, $id);
	}

	if ($submit || $preview || $refresh)
	{
		$subject = request_var('subject', '');

		if (strcmp($subject, strtoupper($subject)) == 0 && $subject)
		{
			$subject = phpbb_strtolower($subject);
		}
		$subject = preg_replace('#&amp;(\#[0-9]+;)#', '&\1', $subject);

	
		$message_parser->message = (isset($_POST['message'])) ? htmlspecialchars(str_replace(array('\\\'', '\\"', '\\0', '\\\\'), array('\'', '"', '\0', '\\'), $_POST['message'])) : '';
		$message_parser->message = preg_replace('#&amp;(\#[0-9]+;)#', '&\1', $message_parser->message);

		$icon_id			= request_var('icon', 0);

		$enable_html 		= (!$html_status || isset($_POST['disable_html'])) ? false : true;
		$enable_bbcode 		= (!$bbcode_status || isset($_POST['disable_bbcode'])) ? false : true;
		$enable_smilies		= (!$smilies_status || isset($_POST['disable_smilies'])) ? false : true;
		$enable_urls 		= (isset($_POST['disable_magic_url'])) ? 0 : 1;
		$enable_sig			= (!$config['allow_sig']) ? false : ((isset($_POST['attach_sig'])) ? true : false);

		// Faster than crc32
		$check_value	= (($preview || $refresh) && isset($_POST['status_switch'])) ? (int) $_POST['status_switch'] : (($enable_html+1) << 16) + (($enable_bbcode+1) << 8) + (($enable_smilies+1) << 4) + (($enable_urls+1) << 2) + (($enable_sig+1) << 1);
		$status_switch	= (isset($_POST['status_switch']) && (int) $_POST['status_switch'] != $check_value);


		// Parse Attachments - before checksum is calculated
		$message_parser->parse_attachments($action, $msg_id, $submit, $preview, $refresh, true);

		// Grab md5 'checksum' of new message
		$message_md5 = md5($message_parser->message);

		// Check checksum ... don't re-parse message if the same
		if ($action != 'edit' || $message_md5 != $post_checksum || $status_switch || $preview)
		{
			// Parse message
			$message_parser->parse($enable_html, $enable_bbcode, $enable_urls, $enable_smilies, $img_status, $flash_status, $quote_status);
		}

		if ($action != 'edit' && !$preview && !$refresh && $config['flood_interval'] && !$auth->acl_get('u_ignoreflood'))
		{
			// Flood check
			$last_post_time = $user->data['user_lastpost_time'];

			if ($last_post_time)
			{
				if ($last_post_time && ($current_time - $last_post_time) < intval($config['flood_interval']))
				{
					$error[] = $user->lang['FLOOD_ERROR'];
				}
			}
		}

		// Subject defined
		if (!$subject)
		{
			$error[] = $user->lang['EMPTY_SUBJECT'];
		}

		if (!sizeof($address_list))
		{
			$error[] = $user->lang['NO_RECIPIENT'];
		}

		if (sizeof($message_parser->warn_msg))
		{
			$error[] = implode('<br />', $message_parser->warn_msg);
		}

		// Store message, sync counters
		if (!sizeof($error) && $submit)
		{
			$pm_data = array(
				'subject'				=> (!$message_subject) ? $subject : $message_subject,
				'msg_id'				=> (int) $msg_id,
				'reply_from_root_level'	=> (int) $root_level,
				'reply_from_msg_id'		=> (int) $msg_id,
				'icon_id'				=> (int) $icon_id,
				'author_id'				=> (int) $author_id,
				'enable_sig'			=> (bool) $enable_sig,
				'enable_bbcode'			=> (bool) $enable_bbcode,
				'enable_html' 			=> (bool) $enable_html,
				'enable_smilies'		=> (bool) $enable_smilies,
				'enable_urls'			=> (bool) $enable_urls,
				'message_md5'			=> (int) $message_md5,
				'post_checksum'			=> (int) $post_checksum,
				'post_edit_reason'		=> $post_edit_reason,
				'post_edit_user'		=> ($action == 'edit') ? $user->data['user_id'] : (int) $post_edit_user,
				'author_ip'				=> (int) $author_ip,
				'bbcode_bitfield'		=> (int) $message_parser->bbcode_bitfield,
				'address_list'			=> $address_list
			);
			
			submit_pm($action, $message_parser->message, $subject, $message_parser->bbcode_uid, $message_parser->attachment_data, $message_parser->filename_data, $pm_data);
		}	

		$message_text = $message_parser->message;
		$message_subject = stripslashes($subject);
	}

	// Preview
	if (!sizeof($error) && $preview)
	{
		$post_time = ($action == 'edit') ? $post_time : $current_time;

		$preview_subject = censor_text($subject);

		$preview_signature = $user->data['user_sig'];
		$preview_signature_uid = $user->data['user_sig_bbcode_uid'];
		$preview_signature_bitfield = $user->data['user_sig_bbcode_bitfield'];

		include($phpbb_root_path . 'includes/bbcode.' . $phpEx);
		$bbcode = new bbcode($message_parser->bbcode_bitfield | $preview_signature_bitfield);

		$preview_message = $message_parser->message;
		format_display($preview_message, $preview_signature, $message_parser->bbcode_uid, $preview_signature_uid, $enable_html, $enable_bbcode, $enable_urls, $enable_smilies, $enable_sig, $bbcode);

		// Attachment Preview
		if (sizeof($message_parser->attachment_data))
		{
			include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
			$extensions = $update_count = array();
					
			$template->assign_var('S_HAS_ATTACHMENTS', true);
			display_attachments(0, 'attachment', $message_parser->attachment_data, $update_count, true);
		}
	}


	// Decode text for message display
	$bbcode_uid = (($action == 'quote' || $action == 'forward')&& !$preview && !$refresh && !sizeof($error)) ? $bbcode_uid : $message_parser->bbcode_uid;

	decode_text($message_text, $bbcode_uid);

	if ($subject)
	{
		decode_text($subject, $bbcode_uid);
	}


	if ($action == 'quote' && !$preview && !$refresh)
	{
		$message_text = '[quote="' . $quote_username . '"]' . censor_text(trim($message_text)) . "[/quote]\n";
	}
	
	if (($action == 'reply' || $action == 'quote') && !$preview && !$refresh)
	{
		$message_subject = ((!preg_match('/^Re:/', $message_subject)) ? 'Re: ' : '') . censor_text($message_subject);
	}

	if ($action == 'forward' && !$preview && !$refresh)
	{
		$user->lang['FWD_ORIGINAL_MESSAGE'] = '-------- Original Message --------';
		$user->lang['FWD_SUBJECT'] = 'Subject: %s';
		$user->lang['FWD_DATE'] = 'Date: %s';
		$user->lang['FWD_FROM'] = 'From: %s';
		$user->lang['FWD_TO'] = 'To: %s';
		
		$fwd_to_field = write_pm_addresses(array('to' => $to_address), 0, true);

		$forward_text = array();
		$forward_text[] = $user->lang['FWD_ORIGINAL_MESSAGE'];
		$forward_text[] = sprintf($user->lang['FWD_SUBJECT'], censor_text($message_subject));
		$forward_text[] = sprintf($user->lang['FWD_DATE'], $user->format_date($message_time));
		$forward_text[] = sprintf($user->lang['FWD_FROM'], $quote_username);
		$forward_text[] = sprintf($user->lang['FWD_TO'], implode(', ', $fwd_to_field['to']));

		$message_text = implode("\n", $forward_text) . "\n\n[quote=\"[url=" . generate_board_url() . "/memberlist.$phpEx$SID&mode=viewprofile&u={$author_id}]{$quote_username}[/url]\"]\n" . censor_text(trim($message_text)) . "\n[/quote]";
		$message_subject = ((!preg_match('/^Fwd:/', $message_subject)) ? 'Fwd: ' : '') . censor_text($message_subject);
	}


	// MAIN PM PAGE BEGINS HERE

	// Generate smilie listing
	generate_smilies('inline', 0);

	// Generate PM Icons
	$s_pm_icons = false;
	if ($config['enable_pm_icons'])
	{
		$s_pm_icons = posting_gen_topic_icons($action, $icon_id);
	}
	
	// Generate inline attachment select box
	posting_gen_inline_attachments($message_parser);

	// Build address list for display
	// array('u' => array($author_id => 'to'));
	if (sizeof($address_list))
	{
		// Get Usernames and Group Names
		$result = array();
		if (isset($address_list['u']) && sizeof($address_list['u']))
		{
			$result['u'] = $db->sql_query('SELECT user_id as id, username as name, user_colour as colour 
				FROM ' . USERS_TABLE . ' 
				WHERE user_id IN (' . implode(', ', array_map('intval', array_keys($address_list['u']))) . ')');
		}
		
		if (isset($address_list['g']) && sizeof($address_list['g']))
		{
			$result['g'] = $db->sql_query('SELECT group_id as id, group_name as name, group_colour as colour 
				FROM ' . GROUPS_TABLE . ' 
				WHERE group_id IN (' . implode(', ', array_map('intval', array_keys($address_list['g']))) . ')');
		}

		$u = $g = array();
		foreach (array('u', 'g') as $type)
		{
			if (isset($result[$type]) && $result[$type])
			{
				while ($row = $db->sql_fetchrow($result[$type]))
				{
					${$type}[$row['id']] = array('name' => $row['name'], 'colour' => $row['colour']);
				}
				$db->sql_freeresult($result[$type]);
			}
		}

		// Now Build the address list
		$plain_address_field = '';
		foreach ($address_list as $type => $adr_ary)
		{
			foreach ($adr_ary as $id => $field)
			{
				$field = ($field == 'to') ? 'to' : 'bcc';
				$type = ($type == 'u') ? 'u' : 'g';
				$id = (int) $id;
				
				$template->assign_block_vars($field . '_recipient', array(
					'NAME'		=> ${$type}[$id]['name'],
					'IS_GROUP'	=> ($type == 'g'),
					'IS_USER'	=> ($type == 'u'),
					'COLOUR'	=> (${$type}[$id]['colour']) ? ${$type}[$id]['colour'] : '',
					'UG_ID'		=> $id,
					'U_VIEW'	=> ($type == 'u') ? "{$phpbb_root_path}memberlist.$phpEx$SID&amp;mode=viewprofile&amp;u=" . $id : "{$phpbb_root_path}groupcp.$phpEx$SID&amp;g=" . $id,
					'TYPE'		=> $type)
				);
			}
		}
	}

	// Build hidden address list
	$s_hidden_address_field = '';
	foreach ($address_list as $type => $adr_ary)
	{
		foreach ($adr_ary as $id => $field)
		{
			$s_hidden_address_field .= '<input type="hidden" name="address_list[' . (($type == 'u') ? 'u' : 'g') . '][' . (int) $id . ']" value="' . (($field == 'to') ? 'to' : 'bcc') . '" />';
		}
	}

	$html_checked		= (isset($enable_html)) ? !$enable_html : (($config['allow_html'] && $auth->acl_get('u_pm_html')) ? !$user->optionget('html') : 1);
	$bbcode_checked		= (isset($enable_bbcode)) ? !$enable_bbcode : (($config['allow_bbcode'] && $auth->acl_get('u_pm_bbcode')) ? !$user->optionget('bbcode') : 1);
	$smilies_checked	= (isset($enable_smilies)) ? !$enable_smilies : (($config['allow_smilies'] && $auth->acl_get('u_pm_smilies')) ? !$user->optionget('smile') : 1);
	$urls_checked		= (isset($enable_urls)) ? !$enable_urls : 0;
	$sig_checked		= $enable_sig;

	switch ($action)
	{
		case 'post':
			$page_title = $user->lang['POST_NEW_PM'];
			break;

		case 'quote':
			$page_title = $user->lang['POST_QUOTE_PM'];
			break;

		case 'reply':
			$page_title = $user->lang['POST_REPLY_PM'];
			break;

		case 'edit':
			$page_title = $user->lang['POST_EDIT_PM'];
			break;

		case 'forward':
			$page_title = $user->lang['POST_FORWARD_PM'];
			break;

		default:
			trigger_error('NOT_AUTHORIZED');
	}

	$s_hidden_fields = '<input type="hidden" name="lastclick" value="' . $current_time . '" />';
	$s_hidden_fields .= (isset($check_value)) ? '<input type="hidden" name="status_switch" value="' . $check_value . '" />' : '';
	$s_hidden_fields .= ($draft_id || isset($_REQUEST['draft_loaded'])) ? '<input type="hidden" name="draft_loaded" value="' . ((isset($_REQUEST['draft_loaded'])) ? intval($_REQUEST['draft_loaded']) : $draft_id) . '" />' : '';

	$form_enctype = (@ini_get('file_uploads') == '0' || strtolower(@ini_get('file_uploads')) == 'off' || @ini_get('file_uploads') == '0' || !$config['allow_pm_attach'] || !$auth->acl_get('u_pm_attach')) ? '' : ' enctype="multipart/form-data"';

	// Start assigning vars for main posting page ...
	$template->assign_vars(array(
		'L_POST_A'				=> $page_title,
		'L_ICON'				=> $user->lang['PM_ICON'], 
		'L_MESSAGE_BODY_EXPLAIN'=> (intval($config['max_post_chars'])) ? sprintf($user->lang['MESSAGE_BODY_EXPLAIN'], intval($config['max_post_chars'])) : '',

		'SUBJECT'				=> (isset($message_subject)) ? $message_subject : '',
		'MESSAGE'				=> trim($message_text),
		'PREVIEW_SUBJECT'		=> ($preview && !sizeof($error)) ? $preview_subject : '',
		'PREVIEW_MESSAGE'		=> ($preview && !sizeof($error)) ? $preview_message : '', 
		'PREVIEW_SIGNATURE'		=> ($preview && !sizeof($error)) ? $preview_signature : '', 
		'HTML_STATUS'			=> ($html_status) ? $user->lang['HTML_IS_ON'] : $user->lang['HTML_IS_OFF'],
		'BBCODE_STATUS'			=> ($bbcode_status) ? sprintf($user->lang['BBCODE_IS_ON'], '<a href="' . "faq.$phpEx$SID&amp;mode=bbcode" . '" target="_phpbbcode">', '</a>') : sprintf($user->lang['BBCODE_IS_OFF'], '<a href="' . "faq.$phpEx$SID&amp;mode=bbcode" . '" target="_phpbbcode">', '</a>'),
		'IMG_STATUS'			=> ($img_status) ? $user->lang['IMAGES_ARE_ON'] : $user->lang['IMAGES_ARE_OFF'],
		'FLASH_STATUS'			=> ($flash_status) ? $user->lang['FLASH_IS_ON'] : $user->lang['FLASH_IS_OFF'],
		'SMILIES_STATUS'		=> ($smilies_status) ? $user->lang['SMILIES_ARE_ON'] : $user->lang['SMILIES_ARE_OFF'],
		'MINI_POST_IMG'			=> $user->img('icon_post', $user->lang['POST']),
		'ERROR'					=> (sizeof($error)) ? implode('<br />', $error) : '', 

		'S_DISPLAY_PREVIEW'		=> ($preview && !sizeof($error)),
		'S_EDIT_POST'			=> ($action == 'edit'),
		'S_SHOW_PM_ICONS'		=> $s_pm_icons,
		'S_HTML_ALLOWED'		=> $html_status,
		'S_HTML_CHECKED' 		=> ($html_checked) ? ' checked="checked"' : '',
		'S_BBCODE_ALLOWED'		=> $bbcode_status,
		'S_BBCODE_CHECKED' 		=> ($bbcode_checked) ? ' checked="checked"' : '',
		'S_SMILIES_ALLOWED'		=> $smilies_status,
		'S_SMILIES_CHECKED' 	=> ($smilies_checked) ? ' checked="checked"' : '',
		'S_SIG_ALLOWED'			=> ($config['allow_sig_pm'] && $auth->acl_get('u_pm_sig')),
		'S_SIGNATURE_CHECKED' 	=> ($sig_checked) ? ' checked="checked"' : '',
		'S_MAGIC_URL_CHECKED' 	=> ($urls_checked) ? ' checked="checked"' : '',
		'S_SAVE_ALLOWED'		=> $auth->acl_get('u_savedrafts'),
		'S_HAS_DRAFTS'			=> ($auth->acl_get('u_savedrafts') && $drafts),
		'S_FORM_ENCTYPE'		=> $form_enctype,

		'S_POST_ACTION' 		=> $s_action,
		'S_HIDDEN_ADDRESS_FIELD'=> $s_hidden_address_field,
		'S_HIDDEN_FIELDS'		=> $s_hidden_fields)
	);

	// Attachment entry
	if ($auth->acl_get('u_pm_attach') && $config['allow_pm_attach'] && $form_enctype)
	{
		posting_gen_attachment_entry($message_parser);
	}
}

// Submit PM
function submit_pm($mode, $message, $subject, $bbcode_uid, $attach_data, $filename_data, $data)
{
	global $db, $auth, $user, $config, $phpEx, $SID, $template;

	// We do not handle erasing posts here
	if ($mode == 'delete')
	{
		return;
	}
	
	$current_time = time();

	// Collect some basic informations about which tables and which rows to update/insert
	$sql_data = array();
	$root_level = 0;

	// Recipient Informations
	$recipients = $to = $bcc = array();

	if ($mode != 'edit')
	{
		// Build Recipient List
		foreach (array('u', 'g') as $ug_type)
		{
			if (sizeof($data['address_list'][$ug_type]))
			{
				foreach ($data['address_list'][$ug_type] as $id => $field)
				{
					$field = ($field == 'to') ? 'to' : 'bcc';
					if ($ug_type == 'u')
					{
						$recipients[$id] = $field;
					}
					${$field}[] = $ug_type . '_' . (int) $id;
				}
			}
		}

		if (sizeof($data['address_list']['g']))
		{
			$sql = 'SELECT group_id, user_id
				FROM ' . USER_GROUP_TABLE . '
				WHERE group_id IN (' . implode(', ', array_keys($data['address_list']['g'])) . ')
					AND user_pending = 0';
			$result = $db->sql_query($sql);
	
			while ($row = $db->sql_fetchrow($result))
			{
				$field = ($data['address_list']['g'][$row['group_id']] == 'to') ? 'to' : 'bcc';
				$recipients[$row['user_id']] = $field;
			}
			$db->sql_freeresult($result);
		}

		if (!sizeof($recipients))
		{
			trigger_error('NO_RECIPIENT');
		}
	}

	$sql = '';
	switch ($mode)
	{
		case 'reply':
		case 'quote':
			$root_level = ($data['reply_from_root_level']) ? $data['reply_from_root_level'] : $data['reply_from_msg_id']; 

			// Set message_replied switch for this user
			$sql = 'UPDATE ' . PRIVMSGS_TO_TABLE . '
				SET replied = 1
				WHERE user_id = ' . $user->data['user_id'] . '
					AND msg_id = ' . $data['reply_from_msg_id'];

		case 'forward':
		case 'post':
			$sql_data = array(
				'root_level'		=> $root_level,
				'author_id'			=> (int) $user->data['user_id'],
				'icon_id'			=> $data['icon_id'], 
				'author_ip' 		=> $user->ip,
				'message_time'		=> $current_time,
				'enable_bbcode' 	=> $data['enable_bbcode'],
				'enable_html' 		=> $data['enable_html'],
				'enable_smilies' 	=> $data['enable_smilies'],
				'enable_magic_url' 	=> $data['enable_urls'],
				'enable_sig' 		=> $data['enable_sig'],
				'message_subject'	=> $subject,
				'message_text' 		=> $message,
				'message_checksum'	=> $data['message_md5'],
				'message_encoding'	=> $user->lang['ENCODING'],
				'message_attachment'=> (sizeof($filename_data['physical_filename'])) ? 1 : 0,
				'bbcode_bitfield'	=> $data['bbcode_bitfield'],
				'bbcode_uid'		=> $bbcode_uid,
				'to_address'		=> implode(':', $to),
				'bcc_address'		=> implode(':', $bcc)
			);
			break;

		case 'edit':
			$sql_data = array(
				'icon_id'			=> $data['icon_id'],
				'message_edit_time'	=> $current_time,
				'enable_bbcode' 	=> $data['enable_bbcode'],
				'enable_html' 		=> $data['enable_html'],
				'enable_smilies' 	=> $data['enable_smilies'],
				'enable_magic_url' 	=> $data['enable_urls'],
				'enable_sig' 		=> $data['enable_sig'],
				'message_subject'	=> $subject,
				'message_text' 		=> $message,
				'message_checksum'	=> $data['message_md5'],
				'message_encoding'	=> $user->lang['ENCODING'],
				'message_attachment'=> (sizeof($filename_data['physical_filename'])) ? 1 : 0,
				'bbcode_bitfield'	=> $data['bbcode_bitfield'],
				'bbcode_uid'		=> $bbcode_uid
			);
			break;
	}

	if (sizeof($sql_data))
	{
		if ($mode == 'post' || $mode == 'reply' || $mode == 'quote' || $mode == 'forward')
		{
			$db->sql_query('INSERT INTO ' . PRIVMSGS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_data));
			$data['msg_id'] = $db->sql_nextid();
		}
		else if ($mode == 'edit')
		{
			$sql = 'UPDATE ' . PRIVMSGS_TABLE . ' 
				SET message_edit_count = message_edit_count + 1, ' . $db->sql_build_array('UPDATE', $sql_data) . ' 
				WHERE msg_id = ' . $data['msg_id'];
			$db->sql_query($sql);
		}
	}
	
	if ($mode != 'edit')
	{
		$db->sql_transaction();
	
		if ($sql)
		{
			$db->sql_query($sql);
		}
		unset($sql);

		foreach ($recipients as $user_id => $type)
		{
			$db->sql_query('INSERT INTO ' . PRIVMSGS_TO_TABLE . ' ' . $db->sql_build_array('INSERT', array(
				'msg_id'	=> $data['msg_id'],
				'user_id'	=> $user_id,
				'author_id'	=> $user->data['user_id'],
				'folder_id'	=> PRIVMSGS_NO_BOX,
				'new'		=> 1,
				'unread'	=> 1,
				'forwarded'	=> ($mode == 'forward') ? 1 : 0))
			);
		}

		$sql = 'UPDATE ' . USERS_TABLE . ' 
			SET user_new_privmsg = user_new_privmsg + 1, user_unread_privmsg = user_unread_privmsg + 1
			WHERE user_id IN (' . implode(', ', array_keys($recipients)) . ')';
		$db->sql_query($sql);

		// Put PM into outbox
		$db->sql_query('INSERT INTO ' . PRIVMSGS_TO_TABLE . ' ' . $db->sql_build_array('INSERT', array(
			'msg_id'	=> (int) $data['msg_id'],
			'user_id'	=> (int) $user->data['user_id'],
			'author_id'	=> (int) $user->data['user_id'],
			'folder_id'	=> PRIVMSGS_OUTBOX,
			'new'		=> 0,
			'unread'	=> 0,
			'forwarded'	=> ($mode == 'forward') ? 1 : 0))
		);

		$db->sql_transaction('commit');
	}

	// Set user last post time
	if ($mode == 'reply' || $mode == 'quote' || $mode == 'forward' || $mode == 'post')
	{
		$sql = 'UPDATE ' . USERS_TABLE . "
			SET user_lastpost_time = $current_time
			WHERE user_id = " . $user->data['user_id'];
		$db->sql_query($sql);
	}

	$db->sql_transaction();

	// Submit Attachments
	if (count($attach_data) && $data['msg_id'] && in_array($mode, array('post', 'reply', 'quote', 'edit', 'forward')))
	{
		$space_taken = $files_added = 0;

		foreach ($attach_data as $pos => $attach_row)
		{
			if ($attach_row['attach_id'])
			{
				// update entry in db if attachment already stored in db and filespace
				$sql = 'UPDATE ' . ATTACHMENTS_TABLE . " 
					SET comment = '" . $db->sql_escape($attach_row['comment']) . "' 
					WHERE attach_id = " . (int) $attach_row['attach_id'];
				$db->sql_query($sql);
			}
			else
			{
				// insert attachment into db 
				$attach_sql = array(
					'post_msg_id'		=> $data['msg_id'],
					'topic_id'			=> 0,
					'in_message'		=> 1,
					'poster_id'			=> $user->data['user_id'],
					'physical_filename'	=> $attach_row['physical_filename'],
					'real_filename'		=> $attach_row['real_filename'],
					'comment'			=> $attach_row['comment'],
					'extension'			=> $attach_row['extension'],
					'mimetype'			=> $attach_row['mimetype'],
					'filesize'			=> $attach_row['filesize'],
					'filetime'			=> $attach_row['filetime'],
					'thumbnail'			=> $attach_row['thumbnail']
				);

				$sql = 'INSERT INTO ' . ATTACHMENTS_TABLE . ' ' . 
					$db->sql_build_array('INSERT', $attach_sql);
				$db->sql_query($sql);

				$space_taken += $attach_row['filesize'];
				$files_added++;
			}
		}
		
		if (count($attach_data))
		{
			$sql = 'UPDATE ' . PRIVMSGS_TABLE . '
				SET message_attachment = 1
				WHERE msg_id = ' . $data['msg_id'];
			$db->sql_query($sql);
		}

		set_config('upload_dir_size', $config['upload_dir_size'] + $space_taken, true);
		set_config('num_files', $config['num_files'] + $files_added, true);
	}

	$db->sql_transaction('commit');

	// Delete draft if post was loaded...
	$draft_id = request_var('draft_loaded', 0);
	if ($draft_id)
	{
		$sql = 'DELETE FROM ' . DRAFTS_TABLE . " 
			WHERE draft_id = $draft_id 
				AND user_id = " . $user->data['user_id'];
		$db->sql_query($sql);
	}

	// Send Notifications
	if ($mode != 'edit')
	{
		pm_notification($mode, stripslashes($user->data['username']), $recipients, stripslashes($subject), stripslashes($message));
	}

	$return_message_url = "{$phpbb_root_path}ucp.$phpEx$SID&amp;i=pm&amp;mode=view_messages&amp;action=view_message&amp;p=" . $data['msg_id'];
	$return_folder_url = "{$phpbb_root_path}ucp.$phpEx$SID&amp;i=pm&amp;folder=outbox";
	meta_refresh(3, $return_message_url);

	$message = $user->lang['MESSAGE_STORED'] . '<br /><br />' . sprintf($user->lang['VIEW_MESSAGE'], '<a href="' . $return_message_url . '">', '</a>') . '<br /><br />' . sprintf($user->lang['RETURN_FOLDER'], '<a href="' . $return_folder_url . '">', '</a>');
	trigger_error($message);
}

// For composing messages, handle list actions
function handle_message_list_actions(&$address_list, $remove_u, $remove_g, $add_to, $add_bcc)
{
	global $_REQUEST;

	// Delete User [TO/BCC]
	if ($remove_u)
	{
		$remove_user_id = array_keys($_REQUEST['remove_u']);
		unset($address_list['u'][(int) $remove_user_id[0]]);
	}

	// Delete Group [TO/BCC]
	if ($remove_g)
	{
		$remove_group_id = array_keys($_REQUEST['remove_g']);
		unset($address_list['g'][(int) $remove_group_id[0]]);
	}

	// Add User/Group [TO]
	if ($add_to || $add_bcc)
	{
		$type = ($add_to) ? 'to' : 'bcc';

		// Add Selected Groups
		$group_list = isset($_REQUEST['group_list']) ? array_map('intval', $_REQUEST['group_list']) : array();

		if (sizeof($group_list))
		{
			foreach ($group_list as $group_id)
			{
				$address_list['g'][$group_id] = $type;
			}
		}
		
		// Build usernames to add
		$usernames = (isset($_REQUEST['username'])) ? array(request_var('username', '')) : array();
		$username_list = request_var('username_list', '');
		if ($username_list)
		{
			$usernames = array_merge($usernames, explode("\n", $username_list));
		}

		// Reveal the correct user_ids
		if (sizeof($usernames))
		{
			$user_id_ary = array();
			user_get_id_name($user_id_ary, $usernames);
			
			if (sizeof($user_id_ary))
			{
				foreach ($user_id_ary as $user_id)
				{
					$address_list['u'][$user_id] = $type;
				}
			}
		}

		// Add Friends if specified
		$friend_list = (is_array($_REQUEST['add_' . $type])) ? array_map('intval', array_keys($_REQUEST['add_' . $type])) : array();

		foreach ($friend_list as $user_id)
		{
			$address_list['u'][$user_id] = $type;
		}
	}

}

// PM Notification
function pm_notification($mode, $author, $recipients, $subject, $message)
{
	global $db, $user, $config, $phpbb_root_path, $phpEx, $auth;

	decode_text($subject);
	$subject = censor_text($subject);
	
	// Get banned User ID's
	$sql = 'SELECT ban_userid 
		FROM ' . BANLIST_TABLE;
	$result = $db->sql_query($sql);

	unset($recipients[ANONYMOUS], $recipients[$user->data['user_id']]);
	
	while ($row = $db->sql_fetchrow($result))
	{
		if (isset($row['ban_userid']))
		{
			unset($recipients[$row['ban_userid']]);
		}
	}
	$db->sql_freeresult($result);

	if (!sizeof($recipients))
	{
		return;
	}

	$recipient_list = implode(', ', array_keys($recipients));

	$sql = 'SELECT user_id, username, user_email, user_lang, user_notify_type, user_jabber 
		FROM ' . USERS_TABLE . "
		WHERE user_id IN ($recipient_list)";
	$result = $db->sql_query($sql);

	$msg_list_ary = array();
	while ($row = $db->sql_fetchrow($result))
	{
		if (trim($row['user_email']))
		{
			$msg_list_ary[] = array(
				'method'	=> $row['method'],
				'email'		=> $row['user_email'],
				'jabber'	=> $row['user_jabber'],
				'name'		=> $row['username'],
				'lang'		=> $row['user_lang']
			);
		}
	}
	$db->sql_freeresult($result);
	
	if (!sizeof($msg_list_ary))
	{
		return;
	}

	include_once($phpbb_root_path . 'includes/functions_messenger.'.$phpEx);
	$messenger = new messenger();

	$email_sig = str_replace('<br />', "\n", "-- \n" . $config['board_email_sig']);

	foreach ($msg_list_ary as $pos => $addr)
	{
		$messenger->template('privmsg_notify', $addr['lang']);

		$messenger->replyto($config['board_email']);
		$messenger->to($addr['email'], $addr['name']);
		$messenger->im($addr['jabber'], $addr['name']);

		$messenger->assign_vars(array(
			'EMAIL_SIG'		=> $email_sig,
			'SITENAME'		=> $config['sitename'],
			'SUBJECT'		=> $subject,
			'AUTHOR_NAME'	=> $author,

			'U_INBOX'		=> generate_board_url() . "/ucp.$phpEx?i=pm&mode=unread")
		);

		$messenger->send($addr['method']);
		$messenger->reset();
	}
	unset($msg_list_ary);

	if ($messenger->queue)
	{
		$messenger->queue->save();
	}
}

// Return number of recipients
function num_recipients($address_list)
{
	$num_recipients = 0;

	foreach ($address_list as $field => $adr_ary)
	{
		$num_recipients += sizeof($adr_ary);
	}

	return $num_recipients;
}

// Get recipient at position 'pos'
function get_recipient_pos($address_list, $position = 1)
{
	$recipient = array();

	$count = 1;
	foreach ($address_list as $field => $adr_ary)
	{
		foreach ($adr_ary as $id => $type)
		{
			if ($count == $position)
			{
				$recipient[$field][$id] = $type;
				break 2;
			}
			$count++;
		}
	}

	return $recipient;
}

?>