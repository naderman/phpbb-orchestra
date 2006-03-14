<?php
/** 
*
* @package acp
* @version $Id$
* @copyright (c) 2005 phpBB Group 
* @license http://opensource.org/licenses/gpl-license.php GNU Public License 
*
*/

/**
* @package acp
*/
class acp_reasons
{
	var $u_action;

	function main($id, $mode)
	{
		global $db, $user, $auth, $template, $cache;
		global $config, $SID, $phpbb_root_path, $phpbb_admin_path, $phpEx;

		$user->add_lang(array('mcp', 'acp/posting'));

		// Set up general vars
		$action = request_var('action', '');
		$submit = (isset($_POST['submit'])) ? true : false;
		$reason_id = request_var('id', 0);

		$this->tpl_name = 'acp_reasons';
		$this->page_title = 'ACP_REASONS';

		// dumdidum... do i really need to do something mom?
		$error = array();

		switch ($action)
		{
			case 'add':
			case 'edit':

				$reason_row = array(
					'reason_title'			=> request_var('reason_title', ''),
					'reason_description'	=> request_var('reason_description', '')
				);

				if ($submit)
				{
					// Reason specified?
					if (!$reason_row['reason_title'] || !$reason_row['reason_description'])
					{
						$error[] = $user->lang['NO_REASON_INFO'];
					}

					$check_double = ($action == 'add') ? true : false;

					if ($action == 'edit')
					{
						$sql = 'SELECT reason_title
							FROM ' . REASONS_TABLE . "
							WHERE reason_id = $reason_id";
						$result = $db->sql_query($sql);
						$row = $db->sql_fetchrow($result);
						$db->sql_freeresult($result);

						if ($row['reason_title'] == 'other')
						{
							$reason_row['reason_title'] = 'other';
						}
						else if (strtolower($row['reason_title']) != strtolower($reason_row['reason_title']))
						{
							$check_double = true;
						}
					}

					// Check for same reason if adding it...
					if ($check_double)
					{
						$sql = 'SELECT reason_id
							FROM ' . REASONS_TABLE . "
							WHERE LOWER(reason_title) = '" . strtolower($reason_row['reason_title']) . "'";
						$result = $db->sql_query($sql);
						$row = $db->sql_fetchrow($result);
						$db->sql_freeresult($result);

						if ($row)
						{
							$error[] = $user->lang['REASON_ALREADY_EXIST'];
						}
					}

					if (!sizeof($error))
					{
						// New reason?
						if ($action == 'add')
						{
							// Get new order...
							$sql = 'SELECT MAX(reason_order) as max_reason_order
								FROM ' . REASONS_TABLE;
							$result = $db->sql_query($sql);
							$max_order = (int) $db->sql_fetchfield('max_reason_order', 0, $result);
							$db->sql_freeresult($result);
							
							$sql_ary = array(
								'reason_title'			=> (string) $reason_row['reason_title'],
								'reason_description'	=> (string) $reason_row['reason_description'],
								'reason_order'			=> $max_order + 1
							);

							$db->sql_query('INSERT INTO ' . REASONS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary));

							$log = 'ADDED';
						}
						else if ($reason_id)
						{
							$sql_ary = array(
								'reason_title'			=> (string) $reason_row['reason_title'],
								'reason_description'	=> (string) $reason_row['reason_description'],
							);

							$db->sql_query('UPDATE ' . REASONS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
								WHERE reason_id = ' . $reason_id);

							$log = 'UPDATED';
						}

						add_log('admin', 'LOG_REASON_' . $log, $reason_row['reason_title']);
						trigger_error($user->lang['REASON_' . $log] . adm_back_link($this->u_action));
					}
				}
				else if ($reason_id)
				{
					$sql = 'SELECT *
						FROM ' . REASONS_TABLE . '
						WHERE reason_id = ' . $reason_id;
					$result = $db->sql_query($sql);
					$reason_row = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);

					if (!$reason_row)
					{
						trigger_error($user->lang['NO_REASON'] . adm_back_link($this->u_action));
					}
				}

				$l_title = ($action == 'edit') ? 'EDIT' : 'ADD';

				$translated = false;

				// If the reason is defined within the language file, we will use the localized version, else just use the database entry...
				if (isset($user->lang['report_reasons']['TITLE'][strtoupper($reason_row['reason_title'])]) && isset($user->lang['report_reasons']['DESCRIPTION'][strtoupper($reason_row['reason_title'])]))
				{
					$translated = true;
				}

				$template->assign_vars(array(
					'L_TITLE'		=> $user->lang['REASON_' . $l_title],
					'U_ACTION'		=> $this->u_action . "&amp;id=$reason_id&amp;action=$action",
					'U_BACK'		=> $this->u_action,
					'ERROR_MSG'		=> (sizeof($error)) ? implode('<br />', $error) : '',
					
					'REASON_TITLE'			=> $reason_row['reason_title'],
					'REASON_DESCRIPTION'	=> $reason_row['reason_description'],
					
					'S_EDIT_REASON'		=> true,
					'S_TRANSLATED'		=> $translated,
					'S_ERROR'			=> (sizeof($error)) ? true : false,
					)
				);

				return;
			break;

			case 'delete':

				$sql = 'SELECT *
					FROM ' . REASONS_TABLE . '
					WHERE reason_id = ' . $reason_id;
				$result = $db->sql_query($sql);
				$reason_row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				if (!$reason_row)
				{
					trigger_error($user->lang['NO_REASON'] . adm_back_link($this->u_action));
				}

				// Let the deletion be confirmed...
				if (confirm_box(true))
				{
					$sql = 'SELECT reason_id
						FROM ' . REASONS_TABLE . "
						WHERE reason_title = 'other'";
					$result = $db->sql_query($sql);
					$other_reason_id = (int) $db->sql_fetchfield('reason_id', 0, $result);
					$db->sql_freeresult($result);

					// Change the reports using this reason to 'other'
					$sql = 'UPDATE ' . REPORTS_TABLE . '
						SET reason_id = ' . $other_reason_id . ", report_text = CONCAT('" . $db->sql_escape($reason_row['reason_description']) . "\n\n', report_text)
						WHERE reason_id = $reason_id";
					$db->sql_query($sql);

					$db->sql_query('DELETE FROM ' . REASONS_TABLE . ' WHERE reason_id = ' . $reason_id);

					add_log('admin', 'LOG_REASON_REMOVED', $reason_row['reason_title']);
					trigger_error($user->lang['REASON_REMOVED'] . adm_back_link($this->u_action));
				}
				else
				{
					confirm_box(false, $user->lang['CONFIRM_OPERATION'], build_hidden_fields(array(
						'i'			=> $id,
						'mode'		=> $mode,
						'action'	=> $action,
						'id'		=> $reason_id))
					);
				}

			break;

			case 'move_up':
			case 'move_down':

				$order = request_var('order', 0);
				$order_total = $order * 2 + (($action == 'move_up') ? -1 : 1);

				$sql = 'UPDATE ' . REASONS_TABLE . '
					SET reason_order = ' . $order_total  . ' - reason_order
					WHERE reason_order IN (' . $order . ', ' . (($action == 'move_up') ? $order - 1 : $order + 1) . ')';
				$db->sql_query($sql);

			break;
		}

		// By default, check that order is valid and fix it if necessary
		$sql = 'SELECT reason_id, reason_order
			FROM ' . REASONS_TABLE . '
			ORDER BY reason_order';
		$result = $db->sql_query($sql);

		if ($row = $db->sql_fetchrow($result))
		{
			$order = 0;
			do
			{
				++$order;
				
				if ($row['reason_order'] != $order)
				{
					$sql = 'UPDATE ' . REASONS_TABLE . "
						SET reason_order = $order
						WHERE reason_id = {$row['reason_id']}";
					$db->sql_query($sql);
				}
			}
			while ($row = $db->sql_fetchrow($result));
		}
		$db->sql_freeresult($result);

		$template->assign_vars(array(
			'U_ACTION'			=> $this->u_action,
			)
		);

		// Reason count
		$sql = 'SELECT reason_id, COUNT(reason_id) AS reason_count
			FROM ' . REPORTS_TABLE . ' 
			GROUP BY reason_id';
		$result = $db->sql_query($sql);

		$reason_count = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$reason_count[$row['reason_id']] = $row['reason_count'];
		}
		$db->sql_freeresult($result);

		$sql = 'SELECT *
			FROM ' . REASONS_TABLE . '
			ORDER BY reason_order ASC';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$translated = false;
			$other_reason = ($row['reason_title'] == 'other') ? true : false;

			// If the reason is defined within the language file, we will use the localized version, else just use the database entry...
			if (isset($user->lang['report_reasons']['TITLE'][strtoupper($row['reason_title'])]) && isset($user->lang['report_reasons']['DESCRIPTION'][strtoupper($row['reason_title'])]))
			{
				$row['reson_description'] = $user->lang['report_reasons']['DESCRIPTION'][strtoupper($row['reason_title'])];
				$row['reason_title'] = $user->lang['report_reasons']['TITLE'][strtoupper($row['reason_title'])];

				$translated = true;
			}

			$template->assign_block_vars('reasons', array(
				'REASON_TITLE'			=> $row['reason_title'],
				'REASON_DESCRIPTION'	=> $row['reason_description'],
				'REASON_COUNT'			=> (isset($reason_count[$row['reason_id']])) ? $reason_count[$row['reason_id']] : 0,

				'S_TRANSLATED'		=> $translated,
				'S_OTHER_REASON'	=> $other_reason,

				'U_EDIT'		=> $this->u_action . '&amp;action=edit&amp;id=' . $row['reason_id'],
				'U_DELETE'		=> (!$other_reason) ? $this->u_action . '&amp;action=delete&amp;id=' . $row['reason_id'] : '',
				'U_MOVE_UP'		=> $this->u_action . '&amp;action=move_up&amp;order=' . $row['reason_order'],
				'U_MOVE_DOWN'	=> $this->u_action . '&amp;action=move_down&amp;order=' . $row['reason_order'])
			);
		}
		$db->sql_freeresult($result);
	}
}

/**
* @package module_install
*/
class acp_reasons_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_reasons',
			'title'		=> 'ACP_REASONS',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'main'		=> array('title' => 'ACP_MANAGE_REASONS', 'auth' => 'acl_a_reasons'),
			),
		);
	}

	function install()
	{
	}

	function uninstall()
	{
	}
}

?>