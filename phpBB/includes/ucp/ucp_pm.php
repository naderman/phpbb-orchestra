<?php
/***************************************************************************
 *                            usercp_profile.php
 *                            -------------------
 *   begin                : Saturday, Feb 21, 2003
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id$
 *
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *
 ***************************************************************************/

// TODO for 2.2:
//
// * Integrate as part of UCP
// * Utilise more code from posting, modularise as appropriate
// * Introduce (admin limited) folders to replace saved (inbox, outbox and sentbox remain)
// * Introduce (admin limited) numbers of messages within each folder
// * Introduce (admin definable) differing limits for certain users/groups
// * Introduce (admin/user switchable) different methods of dealing with full inboxes
// * Give option of recieving a receipt upon reading (sender)
// * Give option of not sending a receipt upon reading (recipient)
// * Topic based approach? i.e. single topic threading
// * Archive inbox to text file? to email?
// * Implement white (buddy) and black (ignore) list marking (see UCP)
// * Mark rows of white list users different colour?
// * Review of post when replying/quoting
// * Introduce post/post thread forwarding
// * Introduce (admin definable) mass mailing
// * Introduce (admin definable) group mailing
// * Introduce (option of) emailing entire PM when notifying user of new message

class ucp_pm extends ucp
{
	function main($module_id)
	{
		global $config, $db, $user, $auth, $SID, $template, $phpEx;

		// Is PM disabled?
		if (!empty($config['privmsg_disable']))
		{
			trigger_error($user->lang['PM_disabled']);
		}

		$html_entities_match = array('#&#', '#<#', '#>#');
		$html_entities_replace = array('&amp;', '&lt;', '&gt;');

		// Parameters
		$submit = (isset($_POST['post'])) ? TRUE : 0;
		$submit_search = (isset($_POST['usersubmit'])) ? TRUE : 0;
		$submit_msgdays = (isset($_POST['submit_msgdays'])) ? TRUE : 0;
		$cancel = (isset($_POST['cancel'])) ? TRUE : 0;
		$preview = (isset($_POST['preview'])) ? TRUE : 0;
		$confirm = (isset($_POST['confirm'])) ? TRUE : 0;
		$delete = (isset($_POST['delete'])) ? TRUE : 0;
		$delete_all = (isset($_POST['deleteall'])) ? TRUE : 0;

		$refresh = $preview || $submit_search;

		$mark_list = (!empty($_POST['mark'])) ? $_POST['mark'] : 0;

		if (isset($_POST['folder']) || isset($_GET['folder']))
		{
			$folder = (isset($_POST['folder'])) ? $_POST['folder'] : $_GET['folder'];

			if ($folder != 'inbox' && $folder != 'outbox' && $folder != 'sentbox' && $folder != 'savebox')
			{
				$folder = 'inbox';
			}
		}
		else
		{
			$folder = 'inbox';
		}

		// Cancel
		if ($cancel)
		{
			redirect("privmsg.$phpEx$SIDfolder=$folder");
		}


		// Var definitions
		if (!empty($_POST['mode']) || !empty($_GET['mode']))
		{
			$mode = (!empty($_POST['mode'])) ? $_POST['mode'] : $_GET['mode'];
		}
		else
		{
			$mode = '';
		}

		$start = (!empty($_GET['start'])) ? intval($_GET['start']) : 0;

		if (isset($_POST['p']) || isset($_GET['p']))
		{
			$privmsg_id = (isset($_POST['p'])) ? intval($_POST['p']) : intval($_GET['p']);
		}
		else
		{
			$privmsg_id = '';
		}

		$error = FALSE;

		//
		// Define the box image links
		//
		$inbox_img = ($folder != 'inbox' || $mode != '') ? '<a href="' . append_sid("privmsg.$phpEx?folder=inbox") . '"><img src="' . $images['pm_inbox'] . '" border="0" alt="' . $lang['Inbox'] . '" /></a>' : '<img src="' . $images['pm_inbox'] . '" border="0" alt="' . $lang['Inbox'] . '" />';
		$inbox_url = ($folder != 'inbox' || $mode != '') ? '<a href="' . append_sid("privmsg.$phpEx?folder=inbox") . '">' . $lang['Inbox'] . '</a>' : $lang['Inbox'];

		$outbox_img = ($folder != 'outbox' || $mode != '') ? '<a href="' . append_sid("privmsg.$phpEx?folder=outbox") . '"><img src="' . $images['pm_outbox'] . '" border="0" alt="' . $lang['Outbox'] . '" /></a>' : '<img src="' . $images['pm_outbox'] . '" border="0" alt="' . $lang['Outbox'] . '" />';
		$outbox_url = ($folder != 'outbox' || $mode != '') ? '<a href="' . append_sid("privmsg.$phpEx?folder=outbox") . '">' . $lang['Outbox'] . '</a>' : $lang['Outbox'];

		$sentbox_img = ($folder != 'sentbox' || $mode != '') ? '<a href="' . append_sid("privmsg.$phpEx?folder=sentbox") . '"><img src="' . $images['pm_sentbox'] . '" border="0" alt="' . $lang['Sentbox'] . '" /></a>' : '<img src="' . $images['pm_sentbox'] . '" border="0" alt="' . $lang['Sentbox'] . '" />';
		$sentbox_url = ($folder != 'sentbox' || $mode != '') ? '<a href="' . append_sid("privmsg.$phpEx?folder=sentbox") . '">' . $lang['Sentbox'] . '</a>' : $lang['Sentbox'];

		$savebox_img = ($folder != 'savebox' || $mode != '') ? '<a href="' . append_sid("privmsg.$phpEx?folder=savebox") . '"><img src="' . $images['pm_savebox'] . '" border="0" alt="' . $lang['Savebox'] . '" /></a>' : '<img src="' . $images['pm_savebox'] . '" border="0" alt="' . $lang['Savebox'] . '" />';
		$savebox_url = ($folder != 'savebox' || $mode != '') ? '<a href="' . append_sid("privmsg.$phpEx?folder=savebox") . '">' . $lang['Savebox'] . '</a>' : $lang['Savebox'];

		// ----------
		// Start main
		//
		if ($mode == 'newpm')
		{
			$gen_simple_header = TRUE;

			$page_title = $lang['Private_Messaging'];
			include($phpbb_root_path . 'includes/page_header.'.$phpEx);

			$template->set_filenames(array(
				'body' => 'privmsgs_popup.tpl')
			);

			if ($userdata['user_id'])
			{
				if ($userdata['user_new_privmsg'])
				{
					$l_new_message = ($userdata['user_new_privmsg'] == 1) ? $lang['You_new_pm'] : $lang['You_new_pms'];
				}
				else
				{
					$l_new_message = $lang['You_no_new_pm'];
				}

				$l_new_message .= '<br /><br />' . sprintf($lang['Click_view_privmsg'], '<a href="' . append_sid("privmsg.".$phpEx."?folder=inbox") . '" onClick="jump_to_inbox();return false;" target="_new">', '</a>');
			}
			else
			{
				$l_new_message = $lang['Login_check_pm'];
			}

			$template->assign_vars(array(
				'L_CLOSE_WINDOW' => $lang['Close_window'],
				'L_MESSAGE' => $l_new_message)
			);

			$template->pparse('body');

			include($phpbb_root_path . 'includes/page_tail.'.$phpEx);

		}
		else if ($mode == 'read')
		{
			if (!empty($_GET['p']))
			{
				$privmsgs_id = intval($_GET['p']);
			}
			else
			{
				message_die(MESSAGE, $lang['No_post_id']);
			}

			if (!$userdata['user_id'])
			{
				$header_location = (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE'))) ? 'Refresh: 0; URL=' : 'Location: ';
				header($header_location . append_sid("login.$phpEx?redirect=privmsg.$phpEx&folder=$folder&mode=$mode&" . POST_POST_URL . "=$privmsgs_id", true));
			}

			//
			// SQL to pull appropriate message, prevents nosey people
			// reading other peoples messages ... hopefully!
			//
			switch($folder)
			{
				case 'inbox':
					$l_box_name = $lang['Inbox'];
					$pm_sql_user = "AND pm.privmsgs_to_userid = " . $userdata['user_id'] . "
						AND (pm.privmsgs_type = " . PRIVMSGS_READ_MAIL . "
							OR pm.privmsgs_type = " . PRIVMSGS_NEW_MAIL . "
							OR pm.privmsgs_type = " . PRIVMSGS_UNREAD_MAIL . ")";
					break;
				case 'outbox':
					$l_box_name = $lang['Outbox'];
					$pm_sql_user = "AND pm.privmsgs_from_userid =  " . $userdata['user_id'] . "
						AND (pm.privmsgs_type = " . PRIVMSGS_NEW_MAIL . "
							OR pm.privmsgs_type = " . PRIVMSGS_UNREAD_MAIL . ") ";
					break;
				case 'sentbox':
					$l_box_name = $lang['Sentbox'];
					$pm_sql_user = "AND pm.privmsgs_from_userid =  " . $userdata['user_id'] . "
						AND pm.privmsgs_type = " . PRIVMSGS_SENT_MAIL;
					break;
				case 'savebox':
					$l_box_name = $lang['Savebox'];
					$pm_sql_user .= "AND ((pm.privmsgs_to_userid = " . $userdata['user_id'] . "
							AND pm.privmsgs_type = " . PRIVMSGS_SAVED_IN_MAIL . ")
						OR (pm.privmsgs_from_userid = " . $userdata['user_id'] . "
							AND pm.privmsgs_type = " . PRIVMSGS_SAVED_OUT_MAIL . ")
						)";
					break;
				default:
					message_die(MESSAGE, $lang['No_such_folder']);
					break;
			}

			//
			// Major query obtains the message ...
			//
			$sql = "SELECT u.username AS username_1, u.user_id AS user_id_1, u2.username AS username_2, u2.user_id AS user_id_2, u.user_sig_bbcode_uid, u.user_posts, u.user_from, u.user_website, u.user_email, u.user_icq, u.user_aim, u.user_yim, u.user_regdate, u.user_msnm, u.user_viewemail, u.user_rank, u.user_sig, u.user_avatar, pm.*, pmt.privmsgs_bbcode_uid, pmt.privmsgs_text
				FROM " . PRIVMSGS_TABLE . " pm, " . PRIVMSGS_TEXT_TABLE . " pmt, " . USERS_TABLE . " u, " . USERS_TABLE . " u2
				WHERE pm.privmsgs_id = $privmsgs_id
					AND pmt.privmsgs_text_id = pm.privmsgs_id
					$pm_sql_user
					AND u.user_id = pm.privmsgs_from_userid
					AND u2.user_id = pm.privmsgs_to_userid";
			$result = $db->sql_query($sql);

			//
			// Did the query return any data?
			//
			if (!($privmsg = $db->sql_fetchrow($result)))
			{
				$header_location = (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE'))) ? 'Refresh: 0; URL=' : 'Location: ';
				header($header_location . append_sid("privmsg.$phpEx?folder=$folder", true));
			}

			$privmsg_id = $privmsg['privmsgs_id'];

			//
			// Is this a new message in the inbox? If it is then save
			// a copy in the posters sent box
			//
			if (($privmsg['privmsgs_type'] == PRIVMSGS_NEW_MAIL || $privmsg['privmsgs_type'] == PRIVMSGS_UNREAD_MAIL) && $folder == 'inbox')
			{
				$sql = "UPDATE " . PRIVMSGS_TABLE . "
					SET privmsgs_type = " . PRIVMSGS_READ_MAIL . "
					WHERE privmsgs_id = " . $privmsg['privmsgs_id'];
				$db->sql_query($sql);

				$sql = "UPDATE " . USERS_TABLE . "
					SET user_unread_privmsg = user_unread_privmsg - 1
					WHERE user_id = " . $userdata['user_id'];
				$db->sql_query($sql);

				//
				// Check to see if the poster has a 'full' sent box
				//
				$sql = "SELECT COUNT(privmsgs_id) AS sent_items, MIN(privmsgs_date) AS oldest_post_time
					FROM " . PRIVMSGS_TABLE . "
					WHERE privmsgs_type = " . PRIVMSGS_SENT_MAIL . "
						AND privmsgs_from_userid = " . $privmsg['privmsgs_from_userid'];
				$result = $db->sql_query($sql);

				$sql_priority = (SQL_LAYER == 'mysql') ? 'LOW_PRIORITY' : '';

				if ($sent_info = $db->sql_fetchrow($result))
				{
					if ($sent_info['sent_items'] >= $config['max_sentbox_privmsgs'])
					{
						$sql = "DELETE $sql_priority FROM " . PRIVMSGS_TABLE . "
							WHERE privmsgs_type = " . PRIVMSGS_SENT_MAIL . "
								AND privmsgs_date = " . $sent_info['oldest_post_time'] . "
								AND privmsgs_from_userid = " . $privmsg['privmsgs_from_userid'];
						$db->sql_query($sql);
					}
				}

				//
				// This makes a copy of the post and stores it as a SENT message from the sendee. Perhaps
				// not the most DB friendly way but a lot easier to manage, besides the admin will be able to
				// set limits on numbers of storable posts for users ... hopefully!
				//
				$sql = "INSERT $sql_priority INTO " . PRIVMSGS_TABLE . " (privmsgs_type, privmsgs_subject, privmsgs_from_userid, privmsgs_to_userid, privmsgs_date, privmsgs_ip, privmsgs_enable_html, privmsgs_enable_bbcode, privmsgs_enable_smilies, privmsgs_attach_sig)
					VALUES (" . PRIVMSGS_SENT_MAIL . ", '" . str_replace("\'", "''", addslashes($privmsg['privmsgs_subject'])) . "', " . $privmsg['privmsgs_from_userid'] . ", " . $privmsg['privmsgs_to_userid'] . ", " . $privmsg['privmsgs_date'] . ", '" . $privmsg['privmsgs_ip'] . "', " . $privmsg['privmsgs_enable_html'] . ", " . $privmsg['privmsgs_enable_bbcode'] . ", " . $privmsg['privmsgs_enable_smilies'] . ", " .  $privmsg['privmsgs_attach_sig'] . ")";
				$db->sql_query($sql);

				$privmsg_sent_id = $db->sql_nextid();

				$sql = "INSERT $sql_priority INTO " . PRIVMSGS_TEXT_TABLE . " (privmsgs_text_id, privmsgs_bbcode_uid, privmsgs_text)
					VALUES ($privmsg_sent_id, '" . $privmsg['privmsgs_bbcode_uid'] . "', '" . str_replace("\'", "''", addslashes($privmsg['privmsgs_text'])) . "')";
				$db->sql_query($sql);
			}

			//
			// Pick a folder, any folder, so long as it's one below ...
			//
			$post_urls = array(
				'post' => append_sid("privmsg.$phpEx?mode=post"),
				'reply' => append_sid("privmsg.$phpEx?mode=reply&amp;" . POST_POST_URL . "=$privmsg_id"),
				'quote' => append_sid("privmsg.$phpEx?mode=quote&amp;" . POST_POST_URL . "=$privmsg_id"),
				'edit' => append_sid("privmsg.$phpEx?mode=edit&amp;" . POST_POST_URL . "=$privmsg_id")
			);
			$post_icons = array(
				'post_img' => '<a href="' . $post_urls['post'] . '"><img src="' . $images['pm_postmsg'] . '" alt="' . $lang['Post_new_pm'] . '" border="0"></a>',
				'post' => '<a href="' . $post_urls['post'] . '">' . $lang['Post_new_pm'] . '</a>',
				'reply_img' => '<a href="' . $post_urls['reply'] . '"><img src="' . $images['pm_replymsg'] . '" alt="' . $lang['Post_reply_pm'] . '" border="0"></a>',
				'reply' => '<a href="' . $post_urls['reply'] . '">' . $lang['Post_reply_pm'] . '</a>',
				'quote_img' => '<a href="' . $post_urls['quote'] . '"><img src="' . $images['pm_quotemsg'] . '" alt="' . $lang['Post_quote_pm'] . '" border="0"></a>',
				'quote' => '<a href="' . $post_urls['quote'] . '">' . $lang['Post_quote_pm'] . '</a>',
				'edit_img' => '<a href="' . $post_urls['edit'] . '"><img src="' . $images['pm_editmsg'] . '" alt="' . $lang['Edit_pm'] . '" border="0"></a>',
				'edit' => '<a href="' . $post_urls['edit'] . '">' . $lang['Edit_pm'] . '</a>'
			);

			if ($folder == 'inbox')
			{
				$post_img = $post_icons['post_img'];
				$reply_img = $post_icons['reply_img'];
				$quote_img = $post_icons['quote_img'];
				$edit_img = '';
				$post = $post_icons['post'];
				$reply = $post_icons['reply'];
				$quote = $post_icons['quote'];
				$edit = '';
				$l_box_name = $lang['Inbox'];
			}
			else if ($folder == 'outbox')
			{
				$post_img = $post_icons['post_img'];
				$reply_img = '';
				$quote_img = '';
				$edit_img = $post_icons['edit_img'];
				$post = $post_icons['post'];
				$reply = '';
				$quote = '';
				$edit = $post_icons['edit'];
				$l_box_name = $lang['Outbox'];
			}
			else if ($folder == 'savebox')
			{
				if ($privmsg['privmsgs_type'] == PRIVMSGS_SAVED_IN_MAIL)
				{
					$post_img = $post_icons['post_img'];
					$reply_img = $post_icons['reply_img'];
					$quote_img = $post_icons['quote_img'];
					$edit_img = '';
					$post = $post_icons['post'];
					$reply = $post_icons['reply'];
					$quote = $post_icons['quote'];
					$edit = '';
				}
				else
				{
					$post_img = $post_icons['post_img'];
					$reply_img = '';
					$quote_img = '';
					$edit_img = '';
					$post = $post_icons['post'];
					$reply = '';
					$quote = '';
					$edit = '';
				}
				$l_box_name = $lang['Saved'];
			}
			else if ($folder == 'sentbox')
			{
				$post_img = $post_icons['post_img'];
				$reply_img = '';
				$quote_img = '';
				$edit_img = '';
				$post = $post_icons['post'];
				$reply = '';
				$quote = '';
				$edit = '';
				$l_box_name = $lang['Sent'];
			}

			$s_hidden_fields = '<input type="hidden" name="mark[]" value="' . $privmsgs_id . '" />';

			$page_title = $lang['Read_private_message'];
			include($phpbb_root_path . 'includes/page_header.'.$phpEx);

			//
			// Load templates
			//
			$template->set_filenames(array(
				'body' => 'privmsgs_read_body.tpl')
			);
			make_jumpbox('viewforum.'.$phpEx);

			$template->assign_vars(array(
				'INBOX_IMG' => $inbox_img,
				'SENTBOX_IMG' => $sentbox_img,
				'OUTBOX_IMG' => $outbox_img,
				'SAVEBOX_IMG' => $savebox_img,
				'INBOX' => $inbox_url,

				'POST_PM_IMG' => $post_img,
				'REPLY_PM_IMG' => $reply_img,
				'EDIT_PM_IMG' => $edit_img,
				'QUOTE_PM_IMG' => $quote_img,
				'POST_PM' => $post,
				'REPLY_PM' => $reply,
				'EDIT_PM' => $edit,
				'QUOTE_PM' => $quote,

				'SENTBOX' => $sentbox_url,
				'OUTBOX' => $outbox_url,
				'SAVEBOX' => $savebox_url,

				'BOX_NAME' => $l_box_name,

				'L_INBOX' => $lang['Inbox'],
				'L_OUTBOX' => $lang['Outbox'],
				'L_SENTBOX' => $lang['Sent'],
				'L_SAVEBOX' => $lang['Saved'],
				'L_FLAG' => $lang['Flag'],
				'L_SUBJECT' => $lang['Subject'],
				'L_POSTED' => $lang['Posted'],
				'L_DATE' => $lang['Date'],
				'L_FROM' => $lang['From'],
				'L_TO' => $lang['To'],
				'L_SAVE_MSG' => $lang['Save_message'],
				'L_DELETE_MSG' => $lang['Delete_message'],

				'S_PRIVMSGS_ACTION' => append_sid("privmsg.$phpEx?folder=$folder"),
				'S_HIDDEN_FIELDS' => $s_hidden_fields)
			);

			$username_from = $privmsg['username_1'];
			$user_id_from = $privmsg['user_id_1'];
			$username_to = $privmsg['username_2'];
			$user_id_to = $privmsg['user_id_2'];

			$post_date = $user->format_date($privmsg['privmsgs_date']);

			$temp_url = append_sid("ucp.$phpEx?mode=viewprofile&amp;u=$user_id_from");
			$profile_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_profile'] . '" alt="' . $lang['Read_profile'] . '" title="' . $lang['Read_profile'] . '" border="0" /></a>';
			$profile = '<a href="' . $temp_url . '">' . $lang['Read_profile'] . '</a>';

			$temp_url = append_sid("privmsg.$phpEx?mode=post&amp;u=$poster_id");
			$pm_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_pm'] . '" alt="' . $lang['Send_private_message'] . '" title="' . $lang['Send_private_message'] . '" border="0" /></a>';
			$pm = '<a href="' . $temp_url . '">' . $lang['Send_private_message'] . '</a>';

			if (!empty($privmsg['user_viewemail']) || $auth->acl_get('a_'))
			{
				$email_uri = ($config['board_email_form']) ? append_sid("ucp.$phpEx?mode=email&amp;u$user_id_from") : 'mailto:' . $privmsg['user_email'];

				$email_img = '<a href="' . $email_uri . '"><img src="' . $images['icon_email'] . '" alt="' . $lang['Send_email'] . '" title="' . $lang['Send_email'] . '" border="0" /></a>';
				$email = '<a href="' . $email_uri . '">' . $lang['Send_email'] . '</a>';
			}
			else
			{
				$email_img = '';
				$email = '';
			}

			$www_img = ($privmsg['user_website']) ? '<a href="' . $privmsg['user_website'] . '" target="_userwww"><img src="' . $images['icon_www'] . '" alt="' . $lang['Visit_website'] . '" title="' . $lang['Visit_website'] . '" border="0" /></a>' : '';
			$www = ($privmsg['user_website']) ? '<a href="' . $privmsg['user_website'] . '" target="_userwww">' . $lang['Visit_website'] . '</a>' : '';

			if (!empty($privmsg['user_icq']))
			{
				$icq_status_img = '<a href="http://wwp.icq.com/' . $privmsg['user_icq'] . '#pager"><img src="http://web.icq.com/whitepages/online?icq=' . $privmsg['user_icq'] . '&img=5" width="18" height="18" border="0" /></a>';
				$icq_img = '<a href="http://wwp.icq.com/scripts/search.dll?to=' . $privmsg['user_icq'] . '"><img src="' . $images['icon_icq'] . '" alt="' . $lang['ICQ'] . '" title="' . $lang['ICQ'] . '" border="0" /></a>';
				$icq =  '<a href="http://wwp.icq.com/scripts/search.dll?to=' . $privmsg['user_icq'] . '">' . $lang['ICQ'] . '</a>';
			}
			else
			{
				$icq_status_img = '';
				$icq_img = '';
				$icq = '';
			}

			$aim_img = ($privmsg['user_aim']) ? '<a href="aim:goim?screenname=' . $privmsg['user_aim'] . '&amp;message=Hello+Are+you+there?"><img src="' . $images['icon_aim'] . '" alt="' . $lang['AIM'] . '" title="' . $lang['AIM'] . '" border="0" /></a>' : '';
			$aim = ($privmsg['user_aim']) ? '<a href="aim:goim?screenname=' . $privmsg['user_aim'] . '&amp;message=Hello+Are+you+there?">' . $lang['AIM'] . '</a>' : '';

			$temp_url = append_sid("ucp.$phpEx?mode=viewprofile&amp;u=$poster_id");
			$msn_img = ($privmsg['user_msnm']) ? '<a href="' . $temp_url . '"><img src="' . $images['icon_msnm'] . '" alt="' . $lang['MSNM'] . '" title="' . $lang['MSNM'] . '" border="0" /></a>' : '';
			$msn = ($privmsg['user_msnm']) ? '<a href="' . $temp_url . '">' . $lang['MSNM'] . '</a>' : '';

			$yim_img = ($privmsg['user_yim']) ? '<a href="http://edit.yahoo.com/config/send_webmesg?.target=' . $privmsg['user_yim'] . '&amp;.src=pg"><img src="' . $images['icon_yim'] . '" alt="' . $lang['YIM'] . '" title="' . $lang['YIM'] . '" border="0" /></a>' : '';
			$yim = ($privmsg['user_yim']) ? '<a href="http://edit.yahoo.com/config/send_webmesg?.target=' . $privmsg['user_yim'] . '&amp;.src=pg">' . $lang['YIM'] . '</a>' : '';

			$temp_url = append_sid("search.$phpEx?search_author=" . urlencode($username_from) . "&amp;showresults=posts");
			$search_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_search'] . '" alt="' . $lang['Search_user_posts'] . '" title="' . $lang['Search_user_posts'] . '" border="0" /></a>';
			$search = '<a href="' . $temp_url . '">' . $lang['Search_user_posts'] . '</a>';

			//
			// Processing of post
			//
			$post_subject = $privmsg['privmsgs_subject'];

			$private_message = $privmsg['privmsgs_text'];
			$bbcode_uid = $privmsg['privmsgs_bbcode_uid'];

			if ($config['allow_sig'])
			{
				$user_sig = ($privmsg['privmsgs_from_userid'] == $userdata['user_id']) ? $userdata['user_sig'] : $privmsg['user_sig'];
			}
			else
			{
				$user_sig = '';
			}

			$user_sig_bbcode_uid = ($privmsg['privmsgs_from_userid'] == $userdata['user_id']) ? $userdata['user_sig_bbcode_uid'] : $privmsg['user_sig_bbcode_uid'];

			//
			// If the board has HTML off but the post has HTML
			// on then we process it, else leave it alone
			//
			if (!$config['allow_html'])
			{
				if ($user_sig != '' && $privmsg['privmsgs_enable_sig'] && $userdata['user_allowhtml'])
				{
					$user_sig = preg_replace('#(<)([\/]?.*?)(>)#is', "&lt;\\2&gt;", $user_sig);
				}

				if ($privmsg['privmsgs_enable_html'])
				{
					$private_message = preg_replace('#(<)([\/]?.*?)(>)#is', "&lt;\\2&gt;", $private_message);
				}
			}

			if ($user_sig != '' && $privmsg['privmsgs_attach_sig'] && $user_sig_bbcode_uid != '')
			{
				$user_sig = ($config['allow_bbcode']) ? bbencode_second_pass($user_sig, $user_sig_bbcode_uid) : preg_replace('/\:[0-9a-z\:]+\]/si', ']', $user_sig);
			}

			if ($bbcode_uid != '')
			{
				$private_message = ($config['allow_bbcode']) ? bbencode_second_pass($private_message, $bbcode_uid) : preg_replace('/\:[0-9a-z\:]+\]/si', ']', $private_message);
			}

			$private_message = make_clickable($private_message);

			if ($privmsg['privmsgs_attach_sig'] && $user_sig != '')
			{
				$private_message .= '<br /><br />_________________<br />' . make_clickable($user_sig);
			}

			if (count($censors['match']))
			{
				$post_subject = preg_replace($censors['match'], $censors['replace'], $post_subject);
				$private_message = preg_replace($censors['match'], $censors['replace'], $private_message);
			}

			if ($config['allow_smilies'] && $privmsg['privmsgs_enable_smilies'])
			{
				$private_message = smilies_pass($private_message);
			}

			$private_message = nl2br($private_message);

			//
			// Dump it to the templating engine
			//
			$template->assign_vars(array(
				'MESSAGE_TO' => $username_to,
				'MESSAGE_FROM' => $username_from,
				'RANK_IMAGE' => $rank_image,
				'POSTER_JOINED' => $poster_joined,
				'POSTER_POSTS' => $poster_posts,
				'POSTER_FROM' => $poster_from,
				'POSTER_AVATAR' => $poster_avatar,
				'POST_SUBJECT' => $post_subject,
				'POST_DATE' => $post_date,
				'MESSAGE' => $private_message,

				'PROFILE_IMG' => $profile_img,
				'PROFILE' => $profile,
				'SEARCH_IMG' => $search_img,
				'SEARCH' => $search,
				'EMAIL_IMG' => $email_img,
				'EMAIL' => $email,
				'WWW_IMG' => $www_img,
				'WWW' => $www,
				'ICQ_STATUS_IMG' => $icq_status_img,
				'ICQ_IMG' => $icq_img,
				'ICQ' => $icq,
				'AIM_IMG' => $aim_img,
				'AIM' => $aim,
				'MSN_IMG' => $msn_img,
				'MSN' => $msn,
				'YIM_IMG' => $yim_img,
				'YIM' => $yim)
			);

			$template->pparse('body');

			include($phpbb_root_path . 'includes/page_tail.'.$phpEx);

		}
		else if (($delete && $mark_list) || $delete_all)
		{
			if (!$userdata['user_id'])
			{
				$header_location = (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE'))) ? 'Refresh: 0; URL=' : 'Location: ';
				header($header_location . append_sid("login.$phpEx?redirect=privmsg.$phpEx&folder=inbox", true));
			}
			if (isset($mark_list) && !is_array($mark_list))
			{
				// Set to empty array instead of '0' if nothing is selected.
				$mark_list = array();
			}

			if (!$confirm)
			{
				$s_hidden_fields = '<input type="hidden" name="mode" value="' . $mode . '" />';
				$s_hidden_fields .= (isset($_POST['delete'])) ? '<input type="hidden" name="delete" value="true" />' : '<input type="hidden" name="deleteall" value="true" />';

				for($i = 0; $i < count($mark_list); $i++)
				{
					$s_hidden_fields .= '<input type="hidden" name="mark[]" value="' . $mark_list[$i] . '" />';
				}

				//
				// Output confirmation page
				//
				include($phpbb_root_path . 'includes/page_header.'.$phpEx);

				$template->set_filenames(array(
					'confirm_body' => 'confirm_body.tpl')
				);
				$template->assign_vars(array(
					'MESSAGE_TITLE' => $lang['Information'],
					'MESSAGE_TEXT' => (count($mark_list) == 1) ? $lang['Confirm_delete_pm'] : $lang['Confirm_delete_pms'],

					'L_YES' => $lang['Yes'],
					'L_NO' => $lang['No'],

					'S_CONFIRM_ACTION' => append_sid("privmsg.$phpEx?folder=$folder"),
					'S_HIDDEN_FIELDS' => $s_hidden_fields)
				);

				$template->pparse('confirm_body');

				include($phpbb_root_path . 'includes/page_tail.'.$phpEx);

			}
			else if ($confirm)
			{
				if ($delete_all)
				{
					switch($folder)
					{
						case 'inbox':
							$delete_type = "privmsgs_to_userid = " . $userdata['user_id'] . " AND (
							privmsgs_type = " . PRIVMSGS_READ_MAIL . " OR privmsgs_type = " . PRIVMSGS_NEW_MAIL . " OR privmsgs_type = " . PRIVMSGS_UNREAD_MAIL . ")";
							break;

						case 'outbox':
							$delete_type = "privmsgs_from_userid = " . $userdata['user_id'] . " AND (privmsgs_type = " . PRIVMSGS_NEW_MAIL . " OR privmsgs_type = " . PRIVMSGS_UNREAD_MAIL . ")";
							break;

						case 'sentbox':
							$delete_type = "privmsgs_from_userid = " . $userdata['user_id'] . " AND privmsgs_type = " . PRIVMSGS_SENT_MAIL;
							break;

						case 'savebox':
							$delete_type = "((privmsgs_from_userid = " . $userdata['user_id'] . "
								AND privmsgs_type = " . PRIVMSGS_SAVED_OUT_MAIL . ")
							OR (privmsgs_to_userid = " . $userdata['user_id'] . "
								AND privmsgs_type = " . PRIVMSGS_SAVED_IN_MAIL . "))";
							break;
					}

					$sql = "SELECT privmsgs_id
						FROM " . PRIVMSGS_TABLE . "
						WHERE $delete_type";
					$result = $db->sql_query($sql);

					while ($row = $db->sql_fetchrow($result))
					{
						$mark_list[] = $row['privmsgs_id'];
					}

					unset($delete_type);
				}

				if (count($mark_list))
				{
					$delete_sql_id = implode(', ', $mark_list);

					// Need to decrement the new message counter of recipient
					// problem is this doesn't affect the unread counter even
					// though it may be the one that needs changing ... hhmmm
					if ($folder == 'outbox')
					{
						$sql = "SELECT privmsgs_to_userid
							FROM " . PRIVMSGS_TABLE . "
							WHERE privmsgs_id IN ($delete_sql_id)
								AND privmsgs_from_userid = " . $userdata['user_id'] . "
								AND privmsgs_type = " . PRIVMSGS_NEW_MAIL;
						$result = $db->sql_query($sql);

						$update_pm_sql = '';
						while($row = $db->sql_fetchrow($result))
						{
							$update_pm_sql .= (($update_pm_sql != '') ? ', ' : '') . $row['privmsgs_to_userid'];
						}

						if ($update_pm_sql != '')
						{
							$sql = "UPDATE " . USERS_TABLE . "
								SET user_new_privmsg = user_new_privmsg - 1
								WHERE user_id IN ($update_pm_sql)";
							$db->sql_query($sql);
						}

						$sql = "SELECT privmsgs_to_userid
							FROM " . PRIVMSGS_TABLE . "
							WHERE privmsgs_id IN ($delete_sql_id)
								AND privmsgs_from_userid = " . $userdata['user_id'] . "
								AND privmsgs_type = " . PRIVMSGS_UNREAD_MAIL;
						$result = $db->sql_query($sql);

						$update_pm_sql = '';
						while($row = $db->sql_fetchrow($result))
						{
							$update_pm_sql .= (($update_pm_sql != '') ? ', ' : '') . $row['privmsgs_to_userid'];
						}

						if ($update_pm_sql != '')
						{
							$sql = "UPDATE " . USERS_TABLE . "
								SET user_unread_privmsg = user_unread_privmsg - 1
								WHERE user_id IN ($update_pm_sql)";
							$db->sql_query($sql);
						}
					}

					$delete_text_sql = "DELETE FROM " . PRIVMSGS_TEXT_TABLE . "
						WHERE privmsgs_text_id IN ($delete_sql_id)";
					$delete_sql = "DELETE FROM " . PRIVMSGS_TABLE . "
						WHERE privmsgs_id IN ($delete_sql_id)
							AND ";

					switch($folder)
					{
						case 'inbox':
							$delete_sql .= "privmsgs_to_userid = " . $userdata['user_id'] . " AND (
								privmsgs_type = " . PRIVMSGS_READ_MAIL . " OR privmsgs_type = " . PRIVMSGS_NEW_MAIL . " OR privmsgs_type = " . PRIVMSGS_UNREAD_MAIL . ")";
							break;

						case 'outbox':
							$delete_sql .= "privmsgs_from_userid = " . $userdata['user_id'] . " AND (
								privmsgs_type = " . PRIVMSGS_NEW_MAIL . " OR privmsgs_type = " . PRIVMSGS_UNREAD_MAIL . ")";
							break;

						case 'sentbox':
							$delete_sql .= "privmsgs_from_userid = " . $userdata['user_id'] . " AND privmsgs_type = " . PRIVMSGS_SENT_MAIL;
							break;

						case 'savebox':
							$delete_sql .= "((privmsgs_from_userid = " . $userdata['user_id'] . "
								AND privmsgs_type = " . PRIVMSGS_SAVED_OUT_MAIL . ")
							OR (privmsgs_to_userid = " . $userdata['user_id'] . "
								AND privmsgs_type = " . PRIVMSGS_SAVED_IN_MAIL . "))";
							break;
					}

					$db->sql_query($delete_sql);
					$db->sql_query($delete_text_sql);
				}
			}
		}
		else if ($save && $mark_list && $folder != 'savebox' && $folder != 'outbox')
		{
			if (!$userdata['user_id'])
			{
				$header_location = (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE'))) ? 'Refresh: 0; URL=' : 'Location: ';
				header($header_location . append_sid("login.$phpEx?redirect=privmsg.$phpEx&folder=inbox", true));
			}

			//
			// See if recipient is at their savebox limit
			//
			$sql = "SELECT COUNT(privmsgs_id) AS savebox_items, MIN(privmsgs_date) AS oldest_post_time
				FROM " . PRIVMSGS_TABLE . "
				WHERE ((privmsgs_to_userid = " . $userdata['user_id'] . "
						AND privmsgs_type = " . PRIVMSGS_SAVED_IN_MAIL . ")
					OR (privmsgs_from_userid = " . $userdata['user_id'] . "
						AND privmsgs_type = " . PRIVMSGS_SAVED_OUT_MAIL . "))";
			$result = $db->sql_query($sql);

			$sql_priority = (SQL_LAYER == 'mysql') ? 'LOW_PRIORITY' : '';

			if ($saved_info = $db->sql_fetchrow($result))
			{
				if ($saved_info['savebox_items'] >= $config['max_savebox_privmsgs'])
				{
					$sql = "DELETE $sql_priority FROM " . PRIVMSGS_TABLE . "
						WHERE ((privmsgs_to_userid = " . $userdata['user_id'] . "
									AND privmsgs_type = " . PRIVMSGS_SAVED_IN_MAIL . ")
								OR (privmsgs_from_userid = " . $userdata['user_id'] . "
									AND privmsgs_type = " . PRIVMSGS_SAVED_OUT_MAIL . "))
							AND privmsgs_date = " . $saved_info['oldest_post_time'];
					$db->sql_query($sql);
				}
			}

			//
			// Process request
			//
			$saved_sql = "UPDATE " . PRIVMSGS_TABLE;

			switch($folder)
			{
				case 'inbox':
					$saved_sql .= " SET privmsgs_type = " . PRIVMSGS_SAVED_IN_MAIL . "
						WHERE privmsgs_to_userid = " . $userdata['user_id'] . "
							AND (privmsgs_type = " . PRIVMSGS_READ_MAIL . "
								OR privmsgs_type = " . PRIVMSGS_NEW_MAIL . "
								OR privmsgs_type = " . PRIVMSGS_UNREAD_MAIL . ")";
					break;

				case 'outbox':
					$saved_sql .= " SET privmsgs_type = " . PRIVMSGS_SAVED_OUT_MAIL . "
						WHERE privmsgs_from_userid = " . $userdata['user_id'] . "
							AND (privmsgs_type = " . PRIVMSGS_NEW_MAIL . "
								OR privmsgs_type = " . PRIVMSGS_UNERAD_MAIL . ") ";
					break;

				case 'sentbox':
					$saved_sql .= " SET privmsgs_type = " . PRIVMSGS_SAVED_OUT_MAIL . "
						WHERE privmsgs_from_userid = " . $userdata['user_id'] . "
							AND privmsgs_type = " . PRIVMSGS_SENT_MAIL;
					break;
			}

			if (count($mark_list))
			{
				$saved_sql_id = '';
				for($i = 0; $i < count($mark_list); $i++)
				{
					$saved_sql_id .= (($saved_sql_id != '') ? ', ' : '') . $mark_list[$i];
				}

				$saved_sql .= " AND privmsgs_id IN ($saved_sql_id)";

				$db->sql_query($saved_sql);
			}

		}
		else if ($submit || $refresh || $mode != '')
		{

			if (!$userdata['user_id'])
			{
				$user_id = (isset($_GET['u'])) ? '&u=' . intval($_GET['u']) : '';
				$header_location = (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE'))) ? 'Refresh: 0; URL=' : 'Location: ';
				header($header_location . append_sid("login.$phpEx?redirect=privmsg.$phpEx&folder=$folder&mode=$mode" . $user_id, true));
			}

			//
			// Toggles
			//
			if (!$config['allow_html'])
			{
				$html_on = 0;
			}
			else
			{
				$html_on = ($submit || $refresh) ? ((!empty($_POST['disable_html'])) ? 0 : TRUE) : $userdata['user_allowhtml'];
			}

			if (!$config['allow_bbcode'])
			{
				$bbcode_on = 0;
			}
			else
			{
				$bbcode_on = ($submit || $refresh) ? ((!empty($_POST['disable_bbcode'])) ? 0 : TRUE) : $userdata['user_allowbbcode'];
			}

			if (!$config['allow_smilies'])
			{
				$smilies_on = 0;
			}
			else
			{
				$smilies_on = ($submit || $refresh) ? ((!empty($_POST['disable_smilies'])) ? 0 : TRUE) : $userdata['user_allowsmile'];
			}

			$attach_sig = ($submit || $refresh) ? ((!empty($_POST['attach_sig'])) ? TRUE : 0) : $userdata['user_attachsig'];
			$user_sig = ($userdata['user_sig'] != '' && $config['allow_sig']) ? $userdata['user_sig'] : "";

			if ($submit && $mode != 'edit')
			{
				// Flood control
				$sql = "SELECT MAX(privmsgs_date) AS last_post_time
					FROM " . PRIVMSGS_TABLE . "
					WHERE privmsgs_from_userid = " . $userdata['user_id'];
				$result = $db->sql_query($sql);

				$db_row = $db->sql_fetchrow($result);

				$last_post_time = $db_row['last_post_time'];
				$current_time = time();

				if (($current_time - $last_post_time) < $config['flood_interval'])
				{
					message_die(MESSAGE, $lang['Flood_Error']);
				}
				// End Flood control
			}

			if ($submit)
			{
				if (!empty($_POST['username']))
				{
					$to_username = $_POST['username'];

					$sql = "SELECT user_id, user_notify_pm, user_email, user_lang, user_active
						FROM " . USERS_TABLE . "
						WHERE username = '" . str_replace("\'", "''", $to_username) . "'
							AND user_id <> " . ANONYMOUS;
					if (!($result = $db->sql_query($sql)))
					{
						$error = TRUE;
						$error_msg = $lang['No_such_user'];
					}

					$to_userdata = $db->sql_fetchrow($result);
				}
				else
				{
					$error = TRUE;
					$error_msg .= ((!empty($error_msg)) ? '<br />' : '') . $lang['No_to_user'];
				}

				$privmsg_subject = trim(strip_tags($_POST['subject']));
				if (empty($privmsg_subject))
				{
					$error = TRUE;
					$error_msg .= ((!empty($error_msg)) ? '<br />' : '') . $lang['Empty_subject'];
				}

				if (!empty($_POST['message']))
				{
					if (!$error)
					{
						if ($bbcode_on)
						{
							$bbcode_uid = make_bbcode_uid();
						}

						$privmsg_message = prepare_message($_POST['message'], $html_on, $bbcode_on, $smilies_on, $bbcode_uid);

					}
				}
				else
				{
					$error = TRUE;
					$error_msg .= ((!empty($error_msg)) ? '<br />' : '') . $lang['Empty_message'];
				}
			}

			if ($submit && !$error)
			{
				//
				// Has admin prevented user from sending PM's?
				//
				if (!$userdata['user_allow_pm'])
				{
					$message = $lang['Cannot_send_privmsg'];
					message_die(MESSAGE, $message);
				}

				$msg_time = time();

				if ($mode != 'edit')
				{
					//
					// See if recipient is at their inbox limit
					//
					$sql = "SELECT COUNT(privmsgs_id) AS inbox_items, MIN(privmsgs_date) AS oldest_post_time
						FROM " . PRIVMSGS_TABLE . "
						WHERE (privmsgs_type = " . PRIVMSGS_NEW_MAIL . "
								OR privmsgs_type = " . PRIVMSGS_READ_MAIL . "
								OR privmsgs_type = " . PRIVMSGS_UNREAD_MAIL . ")
							AND privmsgs_to_userid = " . $to_userdata['user_id'];
					$result = $db->sql_query($sql);

					$sql_priority = (SQL_LAYER == 'mysql') ? 'LOW_PRIORITY' : '';

					if ($inbox_info = $db->sql_fetchrow($result))
					{
						if ($inbox_info['inbox_items'] >= $config['max_inbox_privmsgs'])
						{
							$sql = "DELETE $sql_priority FROM " . PRIVMSGS_TABLE . "
								WHERE (privmsgs_type = " . PRIVMSGS_NEW_MAIL . "
										OR privmsgs_type = " . PRIVMSGS_READ_MAIL . "
										OR privmsgs_type = " . PRIVMSGS_UNREAD_MAIL . " )
									AND privmsgs_date = " . $inbox_info['oldest_post_time'] . "
									AND privmsgs_to_userid = " . $to_userdata['user_id'];
							$db->sql_query($sql);
						}
					}

					$sql_info = "INSERT INTO " . PRIVMSGS_TABLE . " (privmsgs_type, privmsgs_subject, privmsgs_from_userid, privmsgs_to_userid, privmsgs_date, privmsgs_ip, privmsgs_enable_html, privmsgs_enable_bbcode, privmsgs_enable_smilies, privmsgs_attach_sig)
						VALUES (" . PRIVMSGS_NEW_MAIL . ", '" . str_replace("\'", "''", $privmsg_subject) . "', " . $userdata['user_id'] . ", " . $to_userdata['user_id'] . ", $msg_time, '$user_ip', $html_on, $bbcode_on, $smilies_on, $attach_sig)";
				}
				else
				{
					$sql_info = "UPDATE " . PRIVMSGS_TABLE . "
						SET privmsgs_type = " . PRIVMSGS_NEW_MAIL . ", privmsgs_subject = '" . str_replace("\'", "''", $privmsg_subject) . "', privmsgs_from_userid = " . $userdata['user_id'] . ", privmsgs_to_userid = " . $to_userdata['user_id'] . ", privmsgs_date = $msg_time, privmsgs_ip = '$user_ip', privmsgs_enable_html = $html_on, privmsgs_enable_bbcode = $bbcode_on, privmsgs_enable_smilies = $smilies_on, privmsgs_attach_sig = $attach_sig
						WHERE privmsgs_id = $privmsg_id";
				}

				$db->sql_query($sql_info);

				if ($mode != 'edit')
				{
					$privmsg_sent_id = $db->sql_nextid();

					$sql = "INSERT INTO " . PRIVMSGS_TEXT_TABLE . " (privmsgs_text_id, privmsgs_bbcode_uid, privmsgs_text)
						VALUES ($privmsg_sent_id, '" . $bbcode_uid . "', '" . str_replace("\'", "''", $privmsg_message) . "')";
				}
				else
				{
					$sql = "UPDATE " . PRIVMSGS_TEXT_TABLE . "
						SET privmsgs_text = '" . str_replace("\'", "''", $privmsg_message) . "', privmsgs_bbcode_uid = '$bbcode_uid'
						WHERE privmsgs_text_id = $privmsg_id";
				}

				$db->sql_query($sql);

				if ($mode != 'edit')
				{
					//
					// Add to the users new pm counter
					//
					$sql = "UPDATE " . USERS_TABLE . "
						SET user_new_privmsg = user_new_privmsg + 1, user_last_privmsg = " . time() . "
						WHERE user_id = " . $to_userdata['user_id'];
					if (!$status = $db->sql_query($sql))
					{
						message_die(GENERAL_ERROR, 'Could not update private message new/read status for user', '', __LINE__, __FILE__, $sql);
					}

					if ($to_userdata['user_notify_pm'] && !empty($to_userdata['user_email']) && $to_userdata['user_active'])
					{
						$email_headers = 'From: ' . $config['board_email'] . "\nReturn-Path: " . $config['board_email'] . "\r\n";

						$script_name = preg_replace('/^\/?(.*?)\/?$/', "\\1", trim($config['script_path']));
						$script_name = ($script_name != '') ? $script_name . '/privmsg.'.$phpEx : 'privmsg.'.$phpEx;
						$server_name = trim($config['server_name']);
						$server_protocol = ($config['cookie_secure']) ? 'https://' : 'http://';
						$server_port = ($config['server_port'] <> 80) ? ':' . trim($config['server_port']) . '/' : '/';

						include($phpbb_root_path . 'includes/emailer.'.$phpEx);
						$emailer = new emailer($config['smtp_delivery']);

						$emailer->use_template('privmsg_notify', $to_userdata['user_lang']);
						$emailer->extra_headers($email_headers);
						$emailer->email_address($to_userdata['user_email']);
						$emailer->set_subject(); //$lang['Notification_subject']

						$emailer->assign_vars(array(
							'USERNAME' => $to_username,
							'SITENAME' => $config['sitename'],
							'EMAIL_SIG' => str_replace('<br />', "\n", "-- \n" . $config['board_email_sig']),

							'U_INBOX' => $server_protocol . $server_name . $server_port . $script_name . '?folder=inbox')
						);

						$emailer->send();
						$emailer->reset();
					}
				}

				$template->assign_vars(array(
					'META' => '<meta http-equiv="refresh" content="3;url=' . append_sid("privmsg.$phpEx?folder=inbox") . '">')
				);

				$msg = $lang['Message_sent'] . '<br /><br />' . sprintf($lang['Click_return_inbox'], '<a href="' . append_sid("privmsg.$phpEx?folder=inbox") . '">', '</a> ') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . append_sid("index.$phpEx") . '">', '</a>');

				message_die(GMESSAGE, $msg);
			}
			else if ($preview || $refresh || $error)
			{

				//
				// If we're previewing or refreshing then obtain the data
				// passed to the script, process it a little, do some checks
				// where neccessary, etc.
				//
				$to_username = (isset($_POST['username'])) ? trim(strip_tags(stripslashes($_POST['username']))) : '';
				$privmsg_subject = (isset($_POST['subject'])) ? trim(strip_tags(stripslashes($_POST['subject']))) : '';
				$privmsg_message = (isset($_POST['message'])) ? trim($_POST['message']) : '';
				$privmsg_message = preg_replace('#<textarea>#si', '&lt;textarea&gt;', $privmsg_message);
				if (!$preview)
				{
					$privmsg_message = stripslashes($privmsg_message);
				}

				//
				// Do mode specific things
				//
				if ($mode == 'post')
				{
					$page_title = $lang['Send_new_privmsg'];

					$user_sig = ($userdata['user_sig'] != '' && $config['allow_sig']) ? $userdata['user_sig'] : '';

				}
				else if ($mode == 'reply')
				{
					$page_title = $lang['Reply_privmsg'];

					$user_sig = ($userdata['user_sig'] != '' && $config['allow_sig']) ? $userdata['user_sig'] : '';

				}
				else if ($mode == 'edit')
				{
					$page_title = $lang['Edit_privmsg'];

					$sql = "SELECT u.user_id, u.user_sig
						FROM " . PRIVMSGS_TABLE . " pm, " . USERS_TABLE . " u
						WHERE pm.privmsgs_id = $privmsg_id
							AND u.user_id = pm.privmsgs_from_userid";
					$result = $db->sql_query($sql);

					if ($postrow = $db->sql_fetchrow($result))
					{
						if ($userdata['user_id'] != $postrow['user_id'])
						{
							message_die(MESSAGE, $lang['Sorry_edit_own_posts']);
						}

						$user_sig = ($postrow['user_sig'] != '' && $config['allow_sig']) ? $postrow['user_sig'] : '';
					}
				}
			}
			else
			{
				if (!$privmsg_id && ($mode == 'reply' || $mode == 'edit' || $mode == 'quote'))
				{
					message_die(GENERAL_ERROR, $lang['No_post_id']);
				}

				if (!empty($_GET['u']))
				{
					$user_id = intval($_GET['u']);

					$sql = "SELECT username
						FROM " . USERS_TABLE . "
						WHERE user_id = $user_id
							AND user_id <> " . ANONYMOUS;
					if (!($result = $db->sql_query($sql)))
					{
						$error = TRUE;
						$error_msg = $lang['No_such_user'];
					}

					if ($row = $db->sql_fetchrow($result))
					{
						$to_username = $row['username'];
					}
				}

				if ($mode == 'edit')
				{
					$sql = "SELECT pm.*, pmt.privmsgs_bbcode_uid, pmt.privmsgs_text, u.username, u.user_id, u.user_sig
						FROM " . PRIVMSGS_TABLE . " pm, " . PRIVMSGS_TEXT_TABLE . " pmt, " . USERS_TABLE . " u
						WHERE pm.privmsgs_id = $privmsg_id
							AND pmt.privmsgs_text_id = pm.privmsgs_id
							AND pm.privmsgs_from_userid = " . $userdata['user_id'] . "
							AND (pm.privmsgs_type = " . PRIVMSGS_NEW_MAIL . "
								OR pm.privmsgs_type = " . PRIVMSGS_UNREAD_MAIL . ")
							AND u.user_id = pm.privmsgs_to_userid";
					$result = $db->sql_query($sql);

					if (!($privmsg = $db->sql_fetchrow($result)))
					{
						$header_location = (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE'))) ? 'Refresh: 0; URL=' : 'Location: ';
						header($header_location . append_sid("privmsg.$phpEx?folder=$folder", true));
					}

					$privmsg_subject = $privmsg['privmsgs_subject'];
					$privmsg_message = $privmsg['privmsgs_text'];
					$privmsg_bbcode_uid = $privmsg['privmsgs_bbcode_uid'];
					$privmsg_bbcode_enabled = ($privmsg['privmsgs_enable_bbcode'] == 1);

					if ($privmsg_bbcode_enabled)
					{
						$privmsg_message = preg_replace("/\:(([a-z0-9]:)?)$privmsg_bbcode_uid/si", '', $privmsg_message);
					}

					$privmsg_message = str_replace('<br />', "\n", $privmsg_message);
					$privmsg_message = preg_replace('#</textarea>#si', '&lt;/textarea&gt;', $privmsg_message);

					$user_sig = ( $config['allow_sig']) ? $privmsg['user_sig'] : '';

					$to_username = $privmsg['username'];
					$to_userid = $privmsg['user_id'];

				}
				else if ($mode == 'reply' || $mode == 'quote')
				{

					$sql = "SELECT pm.privmsgs_subject, pm.privmsgs_date, pmt.privmsgs_bbcode_uid, pmt.privmsgs_text, u.username, u.user_id
						FROM " . PRIVMSGS_TABLE . " pm, " . PRIVMSGS_TEXT_TABLE . " pmt, " . USERS_TABLE . " u
						WHERE pm.privmsgs_id = $privmsg_id
							AND pmt.privmsgs_text_id = pm.privmsgs_id
							AND pm.privmsgs_to_userid = " . $userdata['user_id'] . "
							AND u.user_id = pm.privmsgs_from_userid";
					$result = $db->sql_query($sql);

					if (!($privmsg = $db->sql_fetchrow($result)))
					{
						$header_location = (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE'))) ? 'Refresh: 0; URL=' : 'Location: ';
						header($header_location . append_sid("privmsg.$phpEx?folder=$folder", true));
					}

					$privmsg_subject = ((!preg_match('/^Re:/', $privmsg['privmsgs_subject'])) ? 'Re: ' : '') . $privmsg['privmsgs_subject'];

					$to_username = $privmsg['username'];
					$to_userid = $privmsg['user_id'];

					if ($mode == 'quote')
					{
						$privmsg_message = $privmsg['privmsgs_text'];
						$privmsg_bbcode_uid = $privmsg['privmsgs_bbcode_uid'];

						$privmsg_message = preg_replace("/\:(([a-z0-9]:)?)$privmsg_bbcode_uid/si", '', $privmsg_message);
						$privmsg_message = str_replace('<br />', "\n", $privmsg_message);
						$privmsg_message = preg_replace('#</textarea>#si', '&lt;/textarea&gt;', $privmsg_message);

						$msg_date =  $user->format_date($privmsg['privmsgs_date']);

						$privmsg_message = '[quote="' . $to_username . '"]' . $privmsg_message . '[/quote]';

						$mode = 'reply';
					}
				}
			}

			//
			// Has admin prevented user from sending PM's?
			//
			if (!$userdata['user_allow_pm'] && $mode != 'edit')
			{
				$message = $lang['Cannot_send_privmsg'];
				message_die(MESSAGE, $message);
			}

			//
			// Start output, first preview, then errors then post form
			//
			$page_title = $lang['Send_private_message'];
			include($phpbb_root_path . 'includes/page_header.'.$phpEx);

			if ($preview && !$error)
			{
				$censors = array();
				obtain_word_list($censors);

				if ($bbcode_on)
				{
					$bbcode_uid = make_bbcode_uid();
				}

				$preview_message = stripslashes(prepare_message($privmsg_message, $html_on, $bbcode_on, $smilies_on, $bbcode_uid));
				$privmsg_message = stripslashes(preg_replace($html_entities_match, $html_entities_replace, $privmsg_message));

				//
				// Finalise processing as per viewtopic
				//
				if (!$html_on)
				{
					if ($user_sig != '' || !$userdata['user_allowhtml'])
					{
						$user_sig = preg_replace('#(<)([\/]?.*?)(>)#is', "&lt;\\2&gt;", $user_sig);
					}
				}

				if ($attach_sig && $user_sig != '' && $userdata['user_sig_bbcode_uid'])
				{
					$user_sig = bbencode_second_pass($user_sig, $userdata['user_sig_bbcode_uid']);
				}

				if ($bbcode_on)
				{
					$preview_message = bbencode_second_pass($preview_message, $bbcode_uid);
				}

				if ($attach_sig && $user_sig != '')
				{
					$preview_message = $preview_message . '<br /><br />_________________<br />' . $user_sig;
				}

				if (count($censors['match']))
				{
					$preview_subject = preg_replace($censors['match'], $censors['replace'], $privmsg_subject);
					$preview_message = preg_replace($censors['match'], $censors['replace'], $preview_message);
				}
				else
				{
					$preview_subject = $privmsg_subject;
				}

				if ($smilies_on)
				{
					$preview_message = smilies_pass($preview_message);
				}

				$preview_message = make_clickable($preview_message);
				$preview_message = nl2br($preview_message);

				$s_hidden_fields = '<input type="hidden" name="folder" value="' . $folder . '" />';
				$s_hidden_fields .= '<input type="hidden" name="mode" value="' . $mode . '" />';

				if (isset($privmsg_id))
				{
					$s_hidden_fields .= '<input type="hidden" name="p" value="' . $privmsg_id . '" />';
				}

				$template->set_filenames(array(
					"preview" => 'privmsgs_preview.tpl')
				);

				$template->assign_vars(array(
					'TOPIC_TITLE' => $preview_subject,
					'POST_SUBJECT' => $preview_subject,
					'MESSAGE_TO' => $to_username,
					'MESSAGE_FROM' => $userdata['username'],
					'POST_DATE' => $user->date_format(time()),
					'MESSAGE' => $preview_message,

					'S_HIDDEN_FIELDS' => $s_hidden_fields,

					'L_SUBJECT' => $lang['Subject'],
					'L_DATE' => $lang['Date'],
					'L_FROM' => $lang['From'],
					'L_TO' => $lang['To'],
					'L_PREVIEW' => $lang['Preview'],
					'L_POSTED' => $lang['Posted'])
				);

				$template->assign_var_from_handle('POST_PREVIEW_BOX', 'preview');
			}

			//
			// Start error handling
			//
			if ($error)
			{
				$template->set_filenames(array(
					'reg_header' => 'error_body.tpl')
				);
				$template->assign_vars(array(
					'ERROR_MESSAGE' => $error_msg)
				);
				$template->assign_var_from_handle('ERROR_BOX', 'reg_header');
			}

			//
			// Load templates
			//
			$template->set_filenames(array(
				'body' => 'posting_body.tpl')
			);
			make_jumpbox('viewforum.'.$phpEx);

			//
			// Enable extensions in posting_body
			//
			$template->assign_block_vars('switch_privmsg', array());

			//
			// HTML toggle selection
			//
			if ($config['allow_html'])
			{
				$html_status = $lang['HTML_is_ON'];
				$template->assign_block_vars('switch_html_checkbox', array());
			}
			else
			{
				$html_status = $lang['HTML_is_OFF'];
			}

			//
			// BBCode toggle selection
			//
			if ($config['allow_bbcode'])
			{
				$bbcode_status = $lang['BBCode_is_ON'];
				$template->assign_block_vars('switch_bbcode_checkbox', array());
			}
			else
			{
				$bbcode_status = $lang['BBCode_is_OFF'];
			}

			//
			// Smilies toggle selection
			//
			if ($config['allow_smilies'])
			{
				$smilies_status = $lang['Smilies_are_ON'];
				$template->assign_block_vars('switch_smilies_checkbox', array());
			}
			else
			{
				$smilies_status = $lang['Smilies_are_OFF'];
			}

			//
			// Signature toggle selection - only show if
			// the user has a signature
			//
			if ($user_sig != '')
			{
				$template->assign_block_vars('switch_signature_checkbox', array());
			}

			if ($mode == 'post')
			{
				$post_a = $lang['Send_a_new_message'];
			}
			else if ($mode == 'reply')
			{
				$post_a = $lang['Send_a_reply'];
				$mode = 'post';
			}
			else if ($mode == 'edit')
			{
				$post_a = $lang['Edit_message'];
			}

			$s_hidden_fields = '<input type="hidden" name="folder" value="' . $folder . '" />';
			$s_hidden_fields .= '<input type="hidden" name="mode" value="' . $mode . '" />';
			if ($mode == 'edit')
			{
				$s_hidden_fields .= '<input type="hidden" name="' . POST_POST_URL . '" value="' . $privmsg_id . '" />';
			}

			//
			// Send smilies to template
			//
			generate_smilies('inline', PAGE_PRIVMSGS);

			$template->assign_vars(array(
				'SUBJECT' => preg_replace($html_entities_match, $html_entities_replace, $privmsg_subject),
				'USERNAME' => preg_replace($html_entities_match, $html_entities_replace, $to_username),
				'MESSAGE' => $privmsg_message,
				'HTML_STATUS' => $html_status,
				'SMILIES_STATUS' => $smilies_status,
				'BBCODE_STATUS' => sprintf($bbcode_status, '<a href="' . append_sid("faq.$phpEx?mode=bbcode") . '" target="_phpbbcode">', '</a>'),
				'FORUM_NAME' => $lang['Private_message'],

				'BOX_NAME' => $l_box_name,
				'INBOX_IMG' => $inbox_img,
				'SENTBOX_IMG' => $sentbox_img,
				'OUTBOX_IMG' => $outbox_img,
				'SAVEBOX_IMG' => $savebox_img,
				'INBOX' => $inbox_url,
				'SENTBOX' => $sentbox_url,
				'OUTBOX' => $outbox_url,
				'SAVEBOX' => $savebox_url,

				'L_SUBJECT' => $lang['Subject'],
				'L_MESSAGE_BODY' => $lang['Message_body'],
				'L_OPTIONS' => $lang['Options'],
				'L_SPELLCHECK' => $lang['Spellcheck'],
				'L_PREVIEW' => $lang['Preview'],
				'L_SUBMIT' => $lang['Submit'],
				'L_CANCEL' => $lang['Cancel'],
				'L_POST_A' => $post_a,
				'L_FIND_USERNAME' => $lang['Find_username'],
				'L_FIND' => $lang['Find'],
				'L_DISABLE_HTML' => $lang['Disable_HTML_pm'],
				'L_DISABLE_BBCODE' => $lang['Disable_BBCode_pm'],
				'L_DISABLE_SMILIES' => $lang['Disable_Smilies_pm'],
				'L_ATTACH_SIGNATURE' => $lang['Attach_signature'],

				'L_BBCODE_B_HELP' => $lang['bbcode_b_help'],
				'L_BBCODE_I_HELP' => $lang['bbcode_i_help'],
				'L_BBCODE_U_HELP' => $lang['bbcode_u_help'],
				'L_BBCODE_Q_HELP' => $lang['bbcode_q_help'],
				'L_BBCODE_C_HELP' => $lang['bbcode_c_help'],
				'L_BBCODE_L_HELP' => $lang['bbcode_l_help'],
				'L_BBCODE_O_HELP' => $lang['bbcode_o_help'],
				'L_BBCODE_P_HELP' => $lang['bbcode_p_help'],
				'L_BBCODE_W_HELP' => $lang['bbcode_w_help'],
				'L_BBCODE_A_HELP' => $lang['bbcode_a_help'],
				'L_BBCODE_S_HELP' => $lang['bbcode_s_help'],
				'L_BBCODE_F_HELP' => $lang['bbcode_f_help'],
				'L_EMPTY_MESSAGE' => $lang['Empty_message'],

				'L_FONT_SIZE' => $lang['Font_size'],
				'L_FONT_TINY' => $lang['font_tiny'],
				'L_FONT_SMALL' => $lang['font_small'],
				'L_FONT_NORMAL' => $lang['font_normal'],
				'L_FONT_LARGE' => $lang['font_large'],
				'L_FONT_HUGE' => $lang['font_huge'],

				'L_BBCODE_CLOSE_TAGS' => $lang['Close_Tags'],
				'L_STYLES_TIP' => $lang['Styles_tip'],

				'S_HTML_CHECKED' => (!$html_on) ? ' checked="checked"' : '',
				'S_BBCODE_CHECKED' => (!$bbcode_on) ? ' checked="checked"' : '',
				'S_SMILIES_CHECKED' => (!$smilies_on) ? ' checked="checked"' : '',
				'S_SIGNATURE_CHECKED' => ($attach_sig) ? ' checked="checked"' : '',
				'S_NAMES_SELECT' => $user_names_select,
				'S_HIDDEN_FORM_FIELDS' => $s_hidden_fields,
				'S_POST_ACTION' => append_sid("privmsg.$phpEx"),

				'U_SEARCH_USER' => append_sid("search.$phpEx?mode=searchuser"),
				'U_VIEW_FORUM' => append_sid("privmsg.$phpEx"))
			);

			$template->display('body');

			include($phpbb_root_path . 'includes/page_tail.'.$phpEx);
		}

		//
		// Default page
		//
		if (!$userdata['user_id'])
		{
			$header_location = (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE'))) ? 'Refresh: 0; URL=' : 'Location: ';
			header($header_location . append_sid("login.$phpEx?redirect=privmsg.$phpEx&folder=inbox", true));
		}

		// Update unread status
		$sql = "UPDATE " . USERS_TABLE . "
			SET user_unread_privmsg = user_unread_privmsg + user_new_privmsg, user_new_privmsg = 0, user_last_privmsg = " . $userdata['session_start'] . "
			WHERE user_id = " . $userdata['user_id'];
		$db->sql_query($sql);

		$sql = "UPDATE " . PRIVMSGS_TABLE . "
			SET privmsgs_type = " . PRIVMSGS_UNREAD_MAIL . "
			WHERE privmsgs_type = " . PRIVMSGS_NEW_MAIL . "
				AND privmsgs_to_userid = " . $userdata['user_id'];
		$db->sql_query($sql);

		// Reset PM counters
		$userdata['user_new_privmsg'] = 0;
		$userdata['user_unread_privmsg'] = ($userdata['user_new_privmsg'] + $userdata['user_unread_privmsg']);

		// Generate page
		$page_title = $lang['Private_Messaging'];
		include($phpbb_root_path . 'includes/page_header.'.$phpEx);

		// Load templates
		$template->set_filenames(array(
			'body' => 'privmsgs_body.tpl')
		);
		make_jumpbox('viewforum.'.$phpEx);

		//
		// New message
		//
		$post_new_mesg_url = '<a href="' . append_sid("privmsg.$phpEx?mode=post") . '"><img src="' . $images['post_new'] . '" alt="' . $lang['Post_new_message'] . '" border="0" /></a>';

		//
		// General SQL to obtain messages
		//
		$sql_tot = "SELECT COUNT(privmsgs_id) AS total
			FROM " . PRIVMSGS_TABLE . " ";
		$sql = "SELECT pm.privmsgs_type, pm.privmsgs_id, pm.privmsgs_date, pm.privmsgs_subject, u.user_id, u.username
			FROM " . PRIVMSGS_TABLE . " pm, " . USERS_TABLE . " u ";
		switch($folder)
		{
			case 'inbox':
				$sql_tot .= "WHERE privmsgs_to_userid = " . $userdata['user_id'] . "
					AND (privmsgs_type =  " . PRIVMSGS_NEW_MAIL . "
						OR privmsgs_type = " . PRIVMSGS_READ_MAIL . "
						OR privmsgs_type = " . PRIVMSGS_UNREAD_MAIL . ")";

				$sql .= "WHERE pm.privmsgs_to_userid = " . $userdata['user_id'] . "
					AND u.user_id = pm.privmsgs_from_userid
					AND (pm.privmsgs_type =  " . PRIVMSGS_NEW_MAIL . "
						OR pm.privmsgs_type = " . PRIVMSGS_READ_MAIL . "
						OR privmsgs_type = " . PRIVMSGS_UNREAD_MAIL . ")";
				break;

			case 'outbox':
				$sql_tot .= "WHERE privmsgs_from_userid = " . $userdata['user_id'] . "
					AND (privmsgs_type =  " . PRIVMSGS_NEW_MAIL . "
						OR privmsgs_type = " . PRIVMSGS_UNREAD_MAIL . ")";

				$sql .= "WHERE pm.privmsgs_from_userid = " . $userdata['user_id'] . "
					AND u.user_id = pm.privmsgs_to_userid
					AND (pm.privmsgs_type =  " . PRIVMSGS_NEW_MAIL . "
						OR privmsgs_type = " . PRIVMSGS_UNREAD_MAIL . ")";
				break;

			case 'sentbox':
				$sql_tot .= "WHERE privmsgs_from_userid = " . $userdata['user_id'] . "
					AND privmsgs_type =  " . PRIVMSGS_SENT_MAIL;

				$sql .= "WHERE pm.privmsgs_from_userid = " . $userdata['user_id'] . "
					AND u.user_id = pm.privmsgs_to_userid
					AND pm.privmsgs_type =  " . PRIVMSGS_SENT_MAIL;
				break;

			case 'savebox':
				$sql_tot .= "WHERE ((privmsgs_to_userid = " . $userdata['user_id'] . "
						AND privmsgs_type = " . PRIVMSGS_SAVED_IN_MAIL . ")
					OR (privmsgs_from_userid = " . $userdata['user_id'] . "
						AND privmsgs_type = " . PRIVMSGS_SAVED_OUT_MAIL . "))";

				$sql .= "WHERE ((pm.privmsgs_to_userid = " . $userdata['user_id'] . "
						AND pm.privmsgs_type = " . PRIVMSGS_SAVED_IN_MAIL . "
						AND u.user_id = pm.privmsgs_from_userid)
					OR (pm.privmsgs_from_userid = " . $userdata['user_id'] . "
						AND pm.privmsgs_type = " . PRIVMSGS_SAVED_OUT_MAIL . "
						AND u.user_id = pm.privmsgs_from_userid))";
				break;

			default:
				message_die(MESSAGE, $lang['No_such_folder']);
				break;
		}

		//
		// Show messages over previous x days/months
		//
		if ($submit_msgdays && (!empty($_POST['msgdays']) || !empty($_GET['msgdays'])))
		{
			$msg_days = (!empty($_POST['msgdays'])) ? intval($_POST['msgdays']) : intval($_GET['msgdays']);
			$min_msg_time = time() - ($msg_days * 86400);

			$limit_msg_time_total = " AND privmsgs_date > $min_msg_time";
			$limit_msg_time = " AND pm.privmsgs_date > $min_msg_time ";

			if (!empty($_POST['msgdays']))
			{
				$start = 0;
			}
		}
		else
		{
			$limit_msg_time = '';
			$post_days = 0;
		}

		$sql .= $limit_msg_time . " ORDER BY pm.privmsgs_date DESC LIMIT $start, " . $config['topics_per_page'];
		$sql_all_tot = $sql_tot;
		$sql_tot .= $limit_msg_time_total;

		//
		// Get messages
		//
		$result = $db->sql_query($sql_tot);
		$pm_total = ($row = $db->sql_fetchrow($result)) ? $row['total'] : 0;

		$result = $db->sql_query($sql_all_tot);
		$pm_all_total = ($row = $db->sql_fetchrow($result)) ? $row['total'] : 0;

		//
		// Build select box
		//
		$previous_days = array(0, 1, 7, 14, 30, 90, 180, 364);
		$previous_days_text = array($lang['All_Posts'], $lang['1_Day'], $lang['7_Days'], $lang['2_Weeks'], $lang['1_Month'], $lang['3_Months'], $lang['6_Months'], $lang['1_Year']);

		$select_msg_days = '';
		for($i = 0; $i < count($previous_days); $i++)
		{
			$selected = ($msg_days == $previous_days[$i]) ? ' selected="selected"' : '';
			$select_msg_days .= '<option value="' . $previous_days[$i] . '"' . $selected . '>' . $previous_days_text[$i] . '</option>';
		}

		//
		// Define correct icons
		//
		if ($folder == 'inbox')
		{
			$post_pm_img = '<a href="' . append_sid("privmsg.$phpEx?mode=post") . '"><img src="' . $images['pm_postmsg'] . '" alt="' . $lang['Post_new_pm'] . '" border="0"></a>';
			$reply_pm_img = '<a href="' . append_sid("privmsg.$phpEx?mode=reply&amp;p=$privmsg_id") . '"><img src="' . $images['pm_replymsg'] . '" alt="' . $lang['Post_reply_pm'] . '" border="0"></a>';
			$quote_pm_img = '<a href="' . append_sid("privmsg.$phpEx?mode=quote&amp;p=$privmsg_id") . '"><img src="' . $images['pm_quotemsg'] . '" alt="' . $lang['Post_quote_pm'] . '" border="0"></a>';
			$edit_pm_img = '';

			$l_box_name = $lang['Inbox'];
		}
		else if ($folder == 'outbox')
		{
			$post_pm_img = '<a href="' . append_sid("privmsg.$phpEx?mode=post") . '"><img src="' . $images['pm_postmsg'] . '" alt="' . $lang['Post_new_pm'] . '" border="0"></a>';
			$reply_pm_img = '';
			$quote_pm_img = '';
			$edit_pm_img = '<a href="' . append_sid("privmsg.$phpEx?mode=edit&amp;p=$privmsg_id") . '"><img src="' . $images['pm_editmsg'] . '" alt="' . $lang['Edit_pm'] . '" border="0"></a>';

			$l_box_name = $lang['Outbox'];
		}
		else if ($folder == 'savebox')
		{
			$post_pm_img = '<a href="' . append_sid("privmsg.$phpEx?mode=post") . '"><img src="' . $images['pm_postmsg'] . '" alt="' . $lang['Post_new_pm'] . '" border="0"></a>';
			$reply_pm_img = '<a href="' . append_sid("privmsg.$phpEx?mode=reply&amp;p=$privmsg_id") . '"><img src="' . $images['pm_replymsg'] . '" alt="' . $lang['Post_reply_pm'] . '" border="0"></a>';
			$quote_pm_img = '<a href="' . append_sid("privmsg.$phpEx?mode=quote&amp;p=$privmsg_id") . '"><img src="' . $images['pm_quotemsg'] . '" alt="' . $lang['Post_quote_pm'] . '" border="0"></a>';
			$edit_pm_img = '';

			$l_box_name = $lang['Savedbox'];
		}
		else if ($folder == 'sentbox')
		{
			$post_pm_img = '<a href="' . append_sid("privmsg.$phpEx?mode=post") . '"><img src="' . $images['pm_postmsg'] . '" alt="' . $lang['Post_new_pm'] . '" border="0"></a>';
			$reply_pm_img = '';
			$quote_pm_img = '<a href="' . append_sid("privmsg.$phpEx?mode=quote&amp;p=$privmsg_id") . '"><img src="' . $images['pm_quotemsg'] . '" alt="' . $lang['Post_quote_pm'] . '" border="0"></a>';
			$edit_pm_img = '';

			$l_box_name = $lang['Sentbox'];
		}

		//
		// Output data for inbox status
		//
		if ($folder != 'outbox')
		{
			if ($config['max_' . $folder . '_privmsgs'] > 0)
			{
				$inbox_limit_pct = round(($pm_all_total / $config['max_' . $folder . '_privmsgs']) * 100);
			}
			else
			{
				$inbox_limit_pct = 100;
			}
			if ($config['max_' . $folder . '_privmsgs'] > 0)
			{
				$inbox_limit_img_length = round(($pm_all_total / $config['max_' . $folder . '_privmsgs']) * $config['privmsg_graphic_length']);
			}
			else
			{
				$inbox_limit_img_length = $config['privmsg_graphic_length'];
			}
			if ($config['max_' . $folder . '_privmsgs'] > 0)
			{
				$inbox_limit_remain = $config['max_' . $folder . '_privmsgs'] - $pm_all_total;
			}
			else
			{
				$inbox_limit_remain = 0;
			}

			$template->assign_block_vars('switch_box_size_notice', array());

			switch($folder)
			{
				case 'inbox':
					$l_box_size_status = sprintf($lang['Inbox_size'], $inbox_limit_pct);
					break;
				case 'sentbox':
					$l_box_size_status = sprintf($lang['Sentbox_size'], $inbox_limit_pct);
					break;
				case 'savebox':
					$l_box_size_status = sprintf($lang['Savebox_size'], $inbox_limit_pct);
					break;
				default:
					$l_box_size_status = '';
					break;
			}
		}

		//
		// Dump vars to template
		//
		$template->assign_vars(array(
			'BOX_NAME' => $l_box_name,
			'INBOX_IMG' => $inbox_img,
			'SENTBOX_IMG' => $sentbox_img,
			'OUTBOX_IMG' => $outbox_img,
			'SAVEBOX_IMG' => $savebox_img,
			'INBOX' => $inbox_url,
			'SENTBOX' => $sentbox_url,
			'OUTBOX' => $outbox_url,
			'SAVEBOX' => $savebox_url,

			'POST_PM_IMG' => $post_pm_img,

			'INBOX_LIMIT_IMG_WIDTH' => $inbox_limit_img_length,
			'INBOX_LIMIT_PERCENT' => $inbox_limit_pct,

			'BOX_SIZE_STATUS' => $l_box_size_status,

			'L_INBOX' => $lang['Inbox'],
			'L_OUTBOX' => $lang['Outbox'],
			'L_SENTBOX' => $lang['Sent'],
			'L_SAVEBOX' => $lang['Saved'],
			'L_MARK' => $lang['Mark'],
			'L_FLAG' => $lang['Flag'],
			'L_SUBJECT' => $lang['Subject'],
			'L_DATE' => $lang['Date'],
			'L_DISPLAY_MESSAGES' => $lang['Display_messages'],
			'L_FROM_OR_TO' => ($folder == 'inbox' || $folder == 'savebox') ? $lang['From'] : $lang['To'],
			'L_MARK_ALL' => $lang['Mark_all'],
			'L_UNMARK_ALL' => $lang['Unmark_all'],
			'L_DELETE_MARKED' => $lang['Delete_marked'],
			'L_DELETE_ALL' => $lang['Delete_all'],
			'L_SAVE_MARKED' => $lang['Save_marked'],

			'S_PRIVMSGS_ACTION' => append_sid("privmsg.$phpEx?folder=$folder"),
			'S_HIDDEN_FIELDS' => '',
			'S_POST_NEW_MSG' => $post_new_mesg_url,
			'S_SELECT_MSG_DAYS' => $select_msg_days,

			'U_POST_NEW_TOPIC' => $post_new_topic_url)
		);

		// Okay, let's build the correct folder
		$result = $db->sql_query($sql);

		if ($row = $db->sql_fetchrow($result))
		{
			do
			{
				$privmsg_id = $row['privmsgs_id'];

				$flag = $row['privmsgs_type'];

				$icon_flag = ($flag == PRIVMSGS_NEW_MAIL || $flag == PRIVMSGS_UNREAD_MAIL) ? $images['pm_unreadmsg'] : $images['pm_readmsg'];
				$icon_flag_alt = ($flag == PRIVMSGS_NEW_MAIL || $flag == PRIVMSGS_UNREAD_MAIL) ? $lang['Unread_message'] : $lang['Read_message'];

				$msg_userid = $row['user_id'];
				$msg_username = $row['username'];

				$u_from_user_profile = append_sid("ucp.$phpEx?mode=viewprofile&amp;u=$msg_userid");

				$msg_subject = $row['privmsgs_subject'];

				if (count($censors['match']))
				{
					$msg_subject = preg_replace($censors['match'], $censors['replace'], $msg_subject);
				}

				$u_subject = append_sid("privmsg.$phpEx?folder=$folder&amp;mode=read&amp;p=$privmsg_id");

				$msg_date = $user_format_date($row['privmsgs_date']);

				if ($flag == PRIVMSGS_NEW_MAIL && $folder == 'inbox')
				{
					$msg_subject = '<b>' . $msg_subject . '</b>';
					$msg_date = '<b>' . $msg_date . '</b>';
					$msg_username = '<b>' . $msg_username . '</b>';
				}

				$row_color = (!($i % 2)) ? $theme['td_color1'] : $theme['td_color2'];
				$row_class = (!($i % 2)) ? $theme['td_class1'] : $theme['td_class2'];

				$template->assign_block_vars('listrow', array(
					'ROW_COLOR' => '#' . $row_color,
					'ROW_CLASS' => $row_class,
					'FROM' => $msg_username,
					'SUBJECT' => $msg_subject,
					'DATE' => $msg_date,
					'PRIVMSG_FOLDER_IMG' => $icon_flag,

					'L_PRIVMSG_FOLDER_ALT' => $icon_flag_alt,

					'S_MARK_ID' => $privmsg_id,

					'U_READ' => $u_subject,
					'U_FROM_USER_PROFILE' => $u_from_user_profile)
				);
			}
			while($row = $db->sql_fetchrow($result));

			$template->assign_vars(array(
				'PAGINATION' => generate_pagination("privmsg.$phpEx?folder=$folder", $pm_total, $config['topics_per_page'], $start),
				'PAGE_NUMBER' => sprintf($lang['Page_of'], (floor($start / $config['topics_per_page']) + 1), ceil($pm_total / $config['topics_per_page'])),

				'L_GOTO_PAGE' => $lang['Goto_page'])
			);

		}
		else
		{
			$template->assign_vars(array(
				'L_NO_MESSAGES' => $lang['No_messages_folder'])
			);

			$template->assign_block_vars("switch_no_messages", array());
		}

		$template->pparse('body');

		include($phpbb_root_path . 'includes/page_tail.'.$phpEx);


		
	}
}

?>