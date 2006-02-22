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
class acp_permissions
{
	var $u_action;
	var $permission_dropdown;
	
	function main($id, $mode)
	{
		global $db, $user, $auth, $template, $cache;
		global $config, $SID, $phpbb_root_path, $phpbb_admin_path, $phpEx;

		include_once($phpbb_root_path . 'includes/functions_user.' . $phpEx);
		include_once($phpbb_root_path . 'includes/acp/auth.' . $phpEx);

		$auth_admin = new auth_admin();

		$user->add_lang('acp/permissions');
		$user->add_lang('acp/permissions_phpbb');

		$this->tpl_name = 'acp_permissions';

		// Set some vars
		$action = request_var('action', array('' => 0));
		list($action, ) = each($action);

		$action = (isset($_POST['psubmit'])) ? 'apply_permissions' : $action;

		$all_forums = request_var('all_forums', 0);
		$subforum_id = request_var('subforum_id', 0);
		$forum_id = request_var('forum_id', array(0));

		$username = request_var('username', array(''));
		$usernames = request_var('usernames', '');
		$user_id = request_var('user_id', array(0));

		$group_id = request_var('group_id', array(0));

		// Map usernames to ids and vice versa
		if ($usernames)
		{
			$username = explode("\n", $usernames);
		}
		unset($usernames);

		if (sizeof($username) && !sizeof($user_id))
		{
			user_get_id_name($user_id, $username);

			if (!sizeof($user_id))
			{
				trigger_error($user->lang['SELECTED_USER_NOT_EXIST'] . adm_back_link($this->u_action));
			}
		}
		unset($username);
		
		// Build forum ids (of all forums are checked or subforum listing used)
		if ($all_forums)
		{
			$sql = 'SELECT forum_id
				FROM ' . FORUMS_TABLE . '
				ORDER BY left_id';
			$result = $db->sql_query($sql);

			$forum_id = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$forum_id[] = $row['forum_id'];
			}
			$db->sql_freeresult($result);
		}
		else if ($subforum_id)
		{
			$forum_id = array();
			foreach (get_forum_branch($subforum_id, 'children') as $row)
			{
				$forum_id[] = $row['forum_id'];
			}
		}

		// Define some common variables for every mode
		$error = array();
		
		$permission_scope = (strpos($mode, '_global') !== false) ? 'global' : 'local';

		// Showing introductionary page?
		if ($mode == 'intro')
		{
			$template->assign_vars(array(
				'S_INTRO'		=> true)
			);

			return;
		}

		switch ($mode)
		{
			case 'setting_user_global':
			case 'setting_group_global':
				$this->permission_dropdown = array('u_', 'm_', 'a_');
				$permission_victim = ($mode == 'setting_user_global') ? array('user') : array('group');
				$this->page_title = ($mode == 'setting_user_global') ? 'ACP_USERS_PERMISSIONS' : 'ACP_GROUPS_PERMISSIONS';
			break;

			case 'setting_user_local':
			case 'setting_group_local':
				$this->permission_dropdown = array('f_', 'm_');
				$permission_victim = ($mode == 'setting_user_local') ? array('user', 'forums') : array('group', 'forums');
				$this->page_title = ($mode == 'setting_user_local') ? 'ACP_USERS_FORUM_PERMISSIONS' : 'ACP_GROUPS_FORUM_PERMISSIONS';
			break;

			case 'setting_admin_global':
			case 'setting_mod_global':
				$this->permission_dropdown = (strpos($mode, '_admin_') !== false) ? array('a_') : array('m_');
				$permission_victim = array('usergroup');
				$this->page_title = ($mode == 'setting_admin_global') ? 'ACP_ADMINISTRATORS' : 'ACP_GLOBAL_MODERATORS';
			break;

			case 'setting_mod_local':
			case 'setting_forum_local':
				$this->permission_dropdown = ($mode == 'setting_mod_local') ? array('m_') : array('f_');
				$permission_victim = array('forums', 'usergroup');
				$this->page_title = ($mode == 'setting_mod_local') ? 'ACP_FORUM_MODERATORS' : 'ACP_FORUM_PERMISSIONS';
			break;

			case 'view_admin_global':
			case 'view_user_global':
			case 'view_mod_global':
				$this->permission_dropdown = ($mode == 'view_admin_global') ? array('a_') : (($mode == 'view_user_global') ? array('u_') : array('m_'));
				$permission_victim = array('usergroup_view');
				$this->page_title = ($mode == 'view_admin_global') ? 'ACP_VIEW_ADMIN_PERMISSIONS' : (($mode == 'view_user_global') ? 'ACP_VIEW_USER_PERMISSIONS' : 'ACP_VIEW_GLOBAL_MOD_PERMISSIONS');
			break;

			case 'view_mod_local':
			case 'view_forum_local':
				$this->permission_dropdown = ($mode == 'view_mod_local') ? array('m_') : array('f_');
				$permission_victim = array('forums', 'usergroup_view');
				$this->page_title = ($mode == 'view_mod_local') ? 'ACP_VIEW_FORUM_MOD_PERMISSIONS' : 'ACP_VIEW_FORUM_PERMISSIONS';
			break;

			default:
				trigger_error('INVALID_MODE');
		}

		$template->assign_vars(array(
			'L_TITLE'		=> $user->lang[$this->page_title],
			'L_EXPLAIN'		=> $user->lang[$this->page_title . '_EXPLAIN'])
		);

		// Get permission type
		$permission_type = request_var('type', $this->permission_dropdown[0]);

		if (!in_array($permission_type, $this->permission_dropdown))
		{
			trigger_error($user->lang['WRONG_PERMISSION_TYPE'] . adm_back_link($this->u_action));
		}


		// Handle actions
		if (strpos($mode, 'setting_') === 0 && $action)
		{
			switch ($action)
			{
				case 'delete':
					$this->remove_permissions($mode, $permission_type, $auth_admin, $user_id, $group_id, $forum_id);
				break;

				case 'apply_permissions':
					if (!isset($_POST['setting']))
					{
						trigger_error($user->lang['NO_AUTH_SETTING_FOUND'] . adm_back_link($this->u_action));
					}

					$this->set_permissions($mode, $permission_type, $auth_admin, $user_id, $group_id);
				break;

				case 'apply_all_permissions':
					if (!isset($_POST['setting']))
					{
						trigger_error($user->lang['NO_AUTH_SETTING_FOUND'] . adm_back_link($this->u_action));
					}

					$this->set_all_permissions($mode, $permission_type, $auth_admin, $user_id, $group_id);
				break;
			}
		}


		// Setting permissions screen
		$s_hidden_fields = build_hidden_fields(array(
			'user_id'		=> $user_id,
			'group_id'		=> $group_id,
			'forum_id'		=> $forum_id,
			'type'			=> $permission_type)
		);

		// Go through the screens/options needed and present them in correct order
		foreach ($permission_victim as $victim)
		{
			switch ($victim)
			{
				case 'forum_dropdown':

					if (sizeof($forum_id))
					{
						$this->check_existence('forum', $forum_id);
						continue 2;
					}

					$template->assign_vars(array(
						'S_SELECT_FORUM'		=> true,
						'S_FORUM_OPTIONS'		=> make_forum_select(false, false, false))
					);

				break;
					
				case 'forums':

					if (sizeof($forum_id))
					{
						$this->check_existence('forum', $forum_id);
						continue 2;
					}

					$forum_list = make_forum_select(false, false, false, false, true, true);

					// Build forum options
					$s_forum_options = '';
					foreach ($forum_list as $f_id => $f_row)
					{
						$s_forum_options .= '<option value="' . $f_id . '"' . $f_row['selected'] . '>' . $f_row['padding'] . $f_row['forum_name'] . '</option>';
					}

					// Build subforum options
					$s_subforum_options = $this->build_subforum_options($forum_list);

					$template->assign_vars(array(
						'S_SELECT_FORUM'		=> true,
						'S_FORUM_OPTIONS'		=> $s_forum_options,
						'S_SUBFORUM_OPTIONS'	=> $s_subforum_options,
						'S_FORUM_ALL'			=> true,
						'S_FORUM_MULTIPLE'		=> true)
					);

				break;

				case 'user':

					if (sizeof($user_id))
					{
						$this->check_existence('user', $user_id);
						continue 2;
					}

					$template->assign_vars(array(
						'S_SELECT_USER'			=> true,
						'U_FIND_USERNAME'		=> $phpbb_root_path . "memberlist.$phpEx$SID&amp;mode=searchuser&amp;form=select_victim&amp;field=username")
					);

				break;

				case 'group':

					if (sizeof($group_id))
					{
						$this->check_existence('group', $group_id);
						continue 2;
					}

					$template->assign_vars(array(
						'S_SELECT_GROUP'		=> true,
						'S_GROUP_OPTIONS'		=> group_select_options(false))
					);

				break;

				case 'usergroup':
				case 'usergroup_view':

					if (sizeof($user_id) || sizeof($group_id))
					{
						if (sizeof($user_id))
						{
							$this->check_existence('user', $user_id);
						}

						if (sizeof($group_id))
						{
							$this->check_existence('group', $group_id);
						}

						continue 2;
					}

					$sql_forum_id = ($permission_scope == 'global') ? 'AND a.forum_id = 0' : ((sizeof($forum_id)) ? 'AND a.forum_id IN (' . implode(', ', $forum_id) . ')' : 'AND a.forum_id <> 0');
					$sql_permission_option = "AND o.auth_option LIKE '" . $db->sql_escape($permission_type) . "%'";

					$sql = 'SELECT DISTINCT u.user_id, u.username
						FROM (' . USERS_TABLE . ' u, ' . ACL_USERS_TABLE . ' a, ' . ACL_OPTIONS_TABLE . ' o)
						LEFT JOIN ' . ACL_ROLES_DATA_TABLE . " r ON (a.auth_role_id = r.role_id)
						WHERE (a.auth_option_id = o.auth_option_id OR r.auth_option_id = o.auth_option_id)
							$sql_permission_option
							$sql_forum_id
							AND u.user_id = a.user_id
						ORDER BY u.username, u.user_regdate ASC";
					$result = $db->sql_query($sql);

					$s_defined_user_options = '';
					$defined_user_ids = array();
					while ($row = $db->sql_fetchrow($result))
					{
						$s_defined_user_options .= '<option value="' . $row['user_id'] . '">' . $row['username'] . '</option>';
						$defined_user_ids[] = $row['user_id'];
					}
					$db->sql_freeresult($result);

					$sql = 'SELECT DISTINCT g.group_id, g.group_name, g.group_type 
						FROM (' . GROUPS_TABLE . ' g, ' . ACL_GROUPS_TABLE . ' a, ' . ACL_OPTIONS_TABLE . ' o)
						LEFT JOIN ' . ACL_ROLES_DATA_TABLE . " r ON (a.auth_role_id = r.role_id)
						WHERE (a.auth_option_id = o.auth_option_id OR r.auth_option_id = o.auth_option_id)
							$sql_permission_option
							$sql_forum_id
							AND g.group_id = a.group_id
						ORDER BY g.group_type DESC, g.group_name ASC";
					$result = $db->sql_query($sql);

					$s_defined_group_options = '';
					$defined_group_ids = array();
					while ($row = $db->sql_fetchrow($result))
					{
						$s_defined_group_options .= '<option' . (($row['group_type'] == GROUP_SPECIAL) ? ' class="sep"' : '') . ' value="' . $row['group_id'] . '">' . (($row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $row['group_name']] : $row['group_name']) . '</option>';
						$defined_group_ids[] = $row['group_id'];
					}
					$db->sql_freeresult($result);

					// Now we check the users... because the "all"-selection is different here (all defined users/groups)
					$all_users = (isset($_POST['all_users'])) ? true : false;
					$all_groups = (isset($_POST['all_groups'])) ? true : false;

					if ($all_users && sizeof($defined_user_ids))
					{
						$user_id = $defined_user_ids;
						continue 2;
					}

					if ($all_groups && sizeof($defined_group_ids))
					{
						$group_id = $defined_group_ids;
						continue 2;
					}

					$template->assign_vars(array(
						'S_SELECT_USERGROUP'		=> ($victim == 'usergroup') ? true : false,
						'S_SELECT_USERGROUP_VIEW'	=> ($victim == 'usergroup_view') ? true : false,
						'S_DEFINED_USER_OPTIONS'	=> $s_defined_user_options,
						'S_DEFINED_GROUP_OPTIONS'	=> $s_defined_group_options,
						'S_ADD_GROUP_OPTIONS'		=> group_select_options(false, $defined_group_ids),
						'U_FIND_USERNAME'			=> $phpbb_root_path . "memberlist.$phpEx$SID&amp;mode=searchuser&amp;form=add_user&amp;field=username")
					);

				break;
			}

			$template->assign_vars(array(
				'U_ACTION'				=> $this->u_action,
				'ANONYMOUS_USER_ID'		=> ANONYMOUS,

				'S_SELECT_VICTIM'		=> true,
				'S_CAN_SELECT_USER'		=> ($auth->acl_get('a_authusers')) ? true : false,
				'S_CAN_SELECT_GROUP'	=> ($auth->acl_get('a_authgroups')) ? true : false,
				'S_HIDDEN_FIELDS'		=> $s_hidden_fields)
			);

			// Let the forum names being displayed
			if (sizeof($forum_id))
			{
				$sql = 'SELECT forum_name
					FROM ' . FORUMS_TABLE . '
					WHERE forum_id IN (' . implode(', ', $forum_id) . ')
					ORDER BY forum_name ASC';
				$result = $db->sql_query($sql);

				$forum_names = array();
				while ($row = $db->sql_fetchrow($result))
				{
					$forum_names[] = $row['forum_name'];
				}
				$db->sql_freeresult($result);

				$template->assign_vars(array(
					'S_FORUM_NAMES'		=> (sizeof($forum_names)) ? true : false,
					'FORUM_NAMES'		=> implode(', ', $forum_names))
				);
			}

			return;
		}

		// Do not allow forum_ids being set and no other setting defined (will bog down the server too much)
		if (sizeof($forum_id) && !sizeof($user_id) && !sizeof($group_id))
		{
			trigger_error($user->lang['ONLY_FORUM_DEFINED'] . adm_back_link($this->u_action));
		}

		$template->assign_vars(array(
			'S_PERMISSION_DROPDOWN'		=> (sizeof($this->permission_dropdown) > 1) ? $this->build_permission_dropdown($this->permission_dropdown, $permission_type) : false,
			'L_PERMISSION_TYPE'			=> $user->lang['ACL_TYPE_' . strtoupper($permission_type)],

			'U_ACTION'					=> $this->u_action,
			'S_HIDDEN_FIELDS'			=> $s_hidden_fields)
		);

		if (strpos($mode, 'setting_') === 0)
		{
			$template->assign_vars(array(
				'S_SETTING_PERMISSIONS'		=> true)
			);

			$hold_ary = $auth_admin->get_mask('set', (sizeof($user_id)) ? $user_id : false, (sizeof($group_id)) ? $group_id : false, (sizeof($forum_id)) ? $forum_id : false, $permission_type, $permission_scope, ACL_UNSET);
			$auth_admin->display_mask('set', $permission_type, $hold_ary, ((sizeof($user_id)) ? 'user' : 'group'), (($permission_scope == 'local') ? true : false));
		}
		else
		{
			$template->assign_vars(array(
				'S_VIEWING_PERMISSIONS'		=> true)
			);

			$hold_ary = $auth_admin->get_mask('view', (sizeof($user_id)) ? $user_id : false, (sizeof($group_id)) ? $group_id : false, (sizeof($forum_id)) ? $forum_id : false, $permission_type, $permission_scope, ACL_NO);
			$auth_admin->display_mask('view', $permission_type, $hold_ary, ((sizeof($user_id)) ? 'user' : 'group'), (($permission_scope == 'local') ? true : false));
		}
	}

	/**
	* Build +subforum options
	*/
	function build_subforum_options($forum_list)
	{
		global $user;

		$s_options = '';

		$forum_list = array_merge($forum_list);

		foreach ($forum_list as $key => $row)
		{
			$s_options .= '<option value="' . $row['forum_id'] . '"' . $row['selected'] . '>' . $row['padding'] . $row['forum_name'];

			// We check if a branch is there...
			$branch_there = false;

			foreach (array_slice($forum_list, $key + 1) as $temp_row)
			{
				if ($temp_row['left_id'] > $row['left_id'] && $temp_row['left_id'] < $row['right_id'])
				{
					$branch_there = true;
					break;
				}
				continue;
			}
			
			if ($branch_there)
			{
				$s_options .= ' [' . $user->lang['PLUS_SUBFORUMS'] . ']';
			}

			$s_options .= '</option>';
		}

		return $s_options;
	}
	
	/**
	* Build dropdown field for changing permission types
	*/
	function build_permission_dropdown($options, $default_option)
	{
		global $user, $auth;
		
		$s_dropdown_options = '';
		foreach ($options as $setting)
		{
			if (!$auth->acl_get('a_' . str_replace('_', '', $setting) . 'auth'))
			{
				continue;
			}
			$selected = ($setting == $default_option) ? ' selected="selected"' : '';
			$s_dropdown_options .= '<option value="' . $setting . '"' . $selected . '>' . $user->lang['permission_type'][$setting] . '</option>';
		}

		return $s_dropdown_options;
	}

	/**
	* Check if selected items exist. Remove not found ids and if empty return error.
	*/
	function check_existence($mode, &$ids)
	{
		global $db, $user;

		switch ($mode)
		{
			case 'user':
				$table = USERS_TABLE;
				$sql_id = 'user_id';
			break;

			case 'group':
				$table = GROUPS_TABLE;
				$sql_id = 'group_id';
			break;

			case 'forum':
				$table = FORUMS_TABLE;
				$sql_id = 'forum_id';
			break;
		}

		$sql = "SELECT $sql_id
			FROM $table
			WHERE $sql_id IN (" . implode(', ', $ids) . ')';
		$result = $db->sql_query($sql);
							
		$ids = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$ids[] = $row[$sql_id];
		}
		$db->sql_freeresult($result);

		if (!sizeof($ids))
		{
			trigger_error($user->lang['SELECTED_' . strtoupper($mode) . '_NOT_EXIST'] . adm_back_link($this->u_action));
		}
	}

	/** 
	* Apply permissions
	*/
	function set_permissions($mode, $permission_type, &$auth_admin, &$user_id, &$group_id)
	{
		global $user, $auth;

		$psubmit = request_var('psubmit', array(0));

		// User or group to be set?
		$ug_type = (sizeof($user_id)) ? 'user' : 'group';

		// Check the permission setting again
		if (!$auth->acl_get('a_' . str_replace('_', '', $permission_type) . 'auth') || !$auth->acl_get('a_auth' . $ug_type . 's'))
		{
			trigger_error($user->lang['NO_ADMIN'] . adm_back_link($this->u_action));
		}
		
		$ug_id = $forum_id = 0;

		// We loop through the auth settings defined in our submit
		list($ug_id, ) = each($psubmit);
		list($forum_id, ) = each($psubmit[$ug_id]);

		$auth_settings = array_map('intval', $_POST['setting'][$ug_id][$forum_id]);

		// Do we have a role we want to set?
		$assigned_role = (isset($_POST['role'][$ug_id][$forum_id])) ? (int) $_POST['role'][$ug_id][$forum_id] : 0;

		// Do the admin want to set these permissions to other items too?
		$inherit = request_var('inherit', array(0));

		$ug_id = array($ug_id);
		$forum_id = array($forum_id);

		if (sizeof($inherit))
		{
			foreach ($inherit as $_ug_id => $forum_id_ary)
			{
				// Inherit users/groups?
				if (!in_array($_ug_id, $ug_id))
				{
					$ug_id[] = $_ug_id;
				}

				// Inherit forums?
				$forum_id = array_merge($forum_id, array_keys($forum_id_ary));
			}
		}

		$forum_id = array_unique($forum_id);

		// If the auth settings differ from the assigned role, then do not set a role...
		if ($assigned_role)
		{
			if (!$this->check_assigned_role($assigned_role, $auth_settings))
			{
				$assigned_role = 0;
			}
		}

		// Update the permission set...
		$auth_admin->acl_set($ug_type, $forum_id, $ug_id, $auth_settings, $assigned_role);

		// Do we need to recache the moderator lists?
		if ($permission_type == 'm_')
		{
			cache_moderators();
		}

		// Remove users who are now moderators or admins from everyones foes list
		if ($permission_type == 'm_' || $permission_type == 'a_')
		{
			$this->update_foes();
		}

		$this->log_action($mode, 'add', $permission_type, $ug_type, $ug_id, $forum_id);

		trigger_error($user->lang['AUTH_UPDATED'] . adm_back_link($this->u_action));
	}

	/** 
	* Apply all permissions
	*/
	function set_all_permissions($mode, $permission_type, &$auth_admin, &$user_id, &$group_id)
	{
		global $user, $auth;

		// User or group to be set?
		$ug_type = (sizeof($user_id)) ? 'user' : 'group';

		// Check the permission setting again
		if (!$auth->acl_get('a_' . str_replace('_', '', $permission_type) . 'auth') || !$auth->acl_get('a_auth' . $ug_type . 's'))
		{
			trigger_error($user->lang['NO_ADMIN'] . adm_back_link($this->u_action));
		}
		
		$auth_settings = $_POST['setting'];
		$ug_ids = $forum_ids = array();

		// We need to go through the auth settings
		foreach ($auth_settings as $ug_id => $forum_auth_row)
		{
			$ug_id = (int) $ug_id;
			$ug_ids[] = $ug_id;
		
			foreach ($forum_auth_row as $forum_id => $auth_options)
			{
				$forum_id = (int) $forum_id;
				$forum_ids[] = $forum_id;

				// Check role...
				$assigned_role = (isset($_POST['role'][$ug_id][$forum_id])) ? (int) $_POST['role'][$ug_id][$forum_id] : 0;

				// If the auth settings differ from the assigned role, then do not set a role...
				if ($assigned_role)
				{
					if (!$this->check_assigned_role($assigned_role, $auth_options))
					{
						$assigned_role = 0;
					}
				}

				// Update the permission set...
				$auth_admin->acl_set($ug_type, $forum_id, $ug_id, $auth_options, $assigned_role);
			}
		}

		// Do we need to recache the moderator lists?
		if ($permission_type == 'm_')
		{
			cache_moderators();
		}

		// Remove users who are now moderators or admins from everyones foes list
		if ($permission_type == 'm_' || $permission_type == 'a_')
		{
			$this->update_foes();
		}

		$this->log_action($mode, 'add', $permission_type, $ug_type, $ug_ids, $forum_ids);

		trigger_error($user->lang['AUTH_UPDATED'] . adm_back_link($this->u_action));
	}

	/**
	* Compare auth settings with auth settings from role
	* returns false if they differ, true if they are equal
	*/
	function check_assigned_role($role_id, &$auth_settings)
	{
		global $db;

		$sql = 'SELECT o.auth_option, r.auth_setting
			FROM ' . ACL_OPTIONS_TABLE . ' o, ' . ACL_ROLES_DATA_TABLE . ' r
			WHERE o.auth_option_id = r.auth_option_id
				AND r.role_id = ' . $role_id;
		$result = $db->sql_query($sql);

		$test_auth_settings = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$test_auth_settings[$row['auth_option']] = $row['auth_setting'];
		}
		$db->sql_freeresult($result);

		// We need to add any ACL_UNSET setting from auth_settings to compare correctly
		foreach ($auth_settings as $option => $setting)
		{
			if ($setting == ACL_UNSET)
			{
				$test_auth_settings[$option] = $setting;
			}
		}

		if (sizeof(array_diff_assoc($auth_settings, $test_auth_settings)))
		{
			return false;
		}

		return true;
	}

	/**
	* Remove permissions
	*/
	function remove_permissions($mode, $permission_type, &$auth_admin, &$user_id, &$group_id, &$forum_id)
	{
		global $user, $db, $auth;
			
		// User or group to be set?
		$ug_type = (sizeof($user_id)) ? 'user' : 'group';

		// Check the permission setting again
		if (!$auth->acl_get('a_' . str_replace('_', '', $permission_type) . 'auth') || !$auth->acl_get('a_auth' . $ug_type . 's'))
		{
			trigger_error($user->lang['NO_ADMIN'] . adm_back_link($this->u_action));
		}

		// Remove permission type
		$sql = 'SELECT auth_option_id
			FROM ' . ACL_OPTIONS_TABLE . "
			WHERE auth_option LIKE '{$permission_type}%'";
		$result = $db->sql_query($sql);

		$option_id_ary = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$option_id_ary[] = $row['auth_option_id'];
		}
		$db->sql_freeresult($result);

		if (sizeof($option_id_ary))
		{
			$auth_admin->acl_delete($ug_type, (($ug_type == 'user') ? $user_id : $group_id), (sizeof($forum_id) ? $forum_id : false), $option_id_ary);
		}

		// Do we need to recache the moderator lists?
		if ($permission_type == 'm_')
		{
			cache_moderators();
		}

		$this->log_action($mode, 'del', $permission_type, $ug_type, (($ug_type == 'user') ? $user_id : $group_id), (sizeof($forum_id) ? $forum_id : array(0 => 0)));
		
		trigger_error($user->lang['AUTH_UPDATED'] . adm_back_link($this->u_action));
	}

	/**
	* Log permission changes
	*/
	function log_action($mode, $action, $permission_type, $ug_type, $ug_id, $forum_id)
	{
		global $db, $user;

		if (!is_array($ug_id))
		{
			$ug_id = array($ug_id);
		}

		if (!is_array($forum_id))
		{
			$forum_id = array($forum_id);
		}

		// Logging ... first grab user or groupnames ...
		$sql = ($ug_type == 'group') ? 'SELECT group_name as name, group_type FROM ' . GROUPS_TABLE . ' WHERE group_id' : 'SELECT username as name FROM ' . USERS_TABLE . ' WHERE user_id';
		$sql .=  ' IN (' . implode(', ', array_map('intval', $ug_id)) . ')';
		$result = $db->sql_query($sql);

		$l_ug_list = '';
		while ($row = $db->sql_fetchrow($result))
		{
			$l_ug_list .= (($l_ug_list != '') ? ', ' : '') . ((isset($row['group_type']) && $row['group_type'] == GROUP_SPECIAL) ? '<span class="blue">' . $user->lang['G_' . $row['name']] . '</span>' : $row['name']);
		}
		$db->sql_freeresult($result);

		$mode = str_replace('setting_', '', $mode);

		if ($forum_id[0] == 0)
		{
			add_log('admin', 'LOG_ACL_' . strtoupper($action) . '_' . strtoupper($mode) . '_' . strtoupper($permission_type), $l_ug_list);
		}
		else
		{
			// Grab the forum details if non-zero forum_id
			$sql = 'SELECT forum_name  
				FROM ' . FORUMS_TABLE . '
				WHERE forum_id IN (' . implode(', ', $forum_id) . ')';
			$result = $db->sql_query($sql);

			$l_forum_list = '';
			while ($row = $db->sql_fetchrow($result))
			{
				$l_forum_list .= (($l_forum_list != '') ? ', ' : '') . $row['forum_name'];
			}
			$db->sql_freeresult($result);

			add_log('admin', 'LOG_ACL_' . strtoupper($action) . '_' . strtoupper($mode) . '_' . strtoupper($permission_type), $l_forum_list, $l_ug_list);
		}
	}

	/**
	* Update foes
	*/
	function update_foes()
	{
		global $db, $auth;

		$perms = array();
		foreach ($auth->acl_get_list(false, array('a_', 'm_'), false) as $forum_id => $forum_ary)
		{
			foreach ($forum_ary as $auth_option => $user_ary)
			{
				$perms += $user_ary;
			}
		}

		if (sizeof($perms))
		{
			$sql = 'DELETE FROM ' . ZEBRA_TABLE . ' 
				WHERE zebra_id IN (' . implode(', ', $perms) . ')';
			$db->sql_query($sql);
		}
		unset($perms);
	}
}

/**
* @package module_install
*/
class acp_permissions_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_permissions',
			'title'		=> 'ACP_PERMISSIONS',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'intro'					=> array('title' => 'ACP_PERMISSIONS', 'auth' => 'acl_a_authusers || acl_a_authgroups || acl_a_viewauth'),

				'setting_user_global'	=> array('title' => 'ACP_USERS_PERMISSIONS', 'auth' => 'acl_a_authusers && (acl_a_aauth || acl_a_mauth || acl_a_uauth)'),
				'setting_user_local'	=> array('title' => 'ACP_USERS_FORUM_PERMISSIONS', 'auth' => 'acl_a_authusers && (acl_a_mauth || acl_a_fauth)'),
				'setting_group_global'	=> array('title' => 'ACP_GROUPS_PERMISSIONS', 'auth' => 'acl_a_authgroups && (acl_a_aauth || acl_a_mauth || acl_a_uauth)'),
				'setting_group_local'	=> array('title' => 'ACP_GROUPS_FORUM_PERMISSIONS', 'auth' => 'acl_a_authgroups && (acl_a_mauth || acl_a_fauth)'),
				'setting_admin_global'	=> array('title' => 'ACP_ADMINISTRATORS', 'auth' => 'acl_a_aauth && (acl_a_authusers || acl_a_authgroups)'),
				'setting_mod_global'	=> array('title' => 'ACP_GLOBAL_MODERATORS', 'auth' => 'acl_a_mauth && (acl_a_authusers || acl_a_authgroups)'),
				'setting_mod_local'		=> array('title' => 'ACP_FORUM_MODERATORS', 'auth' => 'acl_a_mauth && (acl_a_authusers || acl_a_authgroups)'),
				'setting_forum_local'	=> array('title' => 'ACP_FORUM_PERMISSIONS', 'auth' => 'acl_a_fauth && (acl_a_authusers || acl_a_authgroups)'),

				'view_admin_global'		=> array('title' => 'ACP_VIEW_ADMIN_PERMISSIONS', 'auth' => 'acl_a_viewauth'),
				'view_user_global'		=> array('title' => 'ACP_VIEW_USER_PERMISSIONS', 'auth' => 'acl_a_viewauth'),
				'view_mod_global'		=> array('title' => 'ACP_VIEW_GLOBAL_MOD_PERMISSIONS', 'auth' => 'acl_a_viewauth'),
				'view_mod_local'		=> array('title' => 'ACP_VIEW_FORUM_MOD_PERMISSIONS', 'auth' => 'acl_a_viewauth'),
				'view_forum_local'		=> array('title' => 'ACP_VIEW_FORUM_PERMISSIONS', 'auth' => 'acl_a_viewauth'),
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