<?php
/** 
*
* @package install
* @version $Id$
* @copyright (c) 2006 phpBB Group 
* @license http://opensource.org/licenses/gpl-license.php GNU Public License 
*
*/

/**
*/
if (!defined('IN_INSTALL'))
{
	// Someone has tried to access the file directly. This is not a good idea, so exit
	exit;
}

if (!empty($setmodules))
{
	// If phpBB is not installed we do not include this module
	if (@file_exists($phpbb_root_path . 'config.' . $phpEx) && !@file_exists($phpbb_root_path . 'cache/install_lock'))
	{
		include_once($phpbb_root_path . 'config.' . $phpEx);

		if (!defined('PHPBB_INSTALLED'))
		{
			return;
		}
	}
	else
	{
		return;
	}

	$module[] = array(
		'module_type'		=> 'update',
		'module_title'		=> 'UPDATE',
		'module_filename'	=> substr(basename(__FILE__), 0, -strlen($phpEx)-1),
		'module_order'		=> 30,
		'module_subs'		=> '',
		'module_stages'		=> array('INTRO', 'VERSION_CHECK', 'FILE_CHECK', 'UPDATE_FILES', 'UPDATE_DB'),
		'module_reqs'		=> ''
	);
}

/**
* Update Installation
* @package install
*/
class install_update extends module
{
	var $p_master;
	var $update_info;
	
	var $old_location;
	var $new_location;
	var $latest_version;
	var $current_version;

	// Set to false
	var $test_update = false;

	function install_update(&$p_master)
	{
		$this->p_master = &$p_master;
	}

	function main($mode, $sub)
	{
		global $template, $phpEx, $phpbb_root_path, $user, $db, $config, $cache, $auth;

		$this->tpl_name = 'install_update';
		$this->old_location = $phpbb_root_path . 'install/update/old/';
		$this->new_location = $phpbb_root_path . 'install/update/new/';

		// Init DB
		require($phpbb_root_path . 'config.' . $phpEx);
		require($phpbb_root_path . 'includes/db/' . $dbms . '.' . $phpEx);
		require($phpbb_root_path . 'includes/constants.' . $phpEx);

		$db = new $sql_db();

		// Connect to DB
		$db->sql_connect($dbhost, $dbuser, $dbpasswd, $dbname, $dbport, false);

		// We do not need this any longer, unset for safety purposes
		unset($dbpasswd);

		$config = $cache->obtain_config();

		// First of all, init the user session
		$user->session_begin();
		$auth->acl($user->data);
		$user->setup('install');

		include_once($phpbb_root_path . 'includes/diff/diff.' . $phpEx);

		/**
		* Check for user session
		* - commented out for beta3 with "broken" install check routine
		if (!$user->data['is_registered'])
		{
			login_box('', $user->lang['LOGIN_UPDATE_EXPLAIN']);
		}

		if (!$auth->acl_get('a_'))
		{
			trigger_error($user->lang['NO_AUTH_UPDATE']);
		}
		*/

		// If we are within the intro page we need to make sure we get up-to-date version info
		if ($sub == 'intro')
		{
			$cache->destroy('_version_info');
		}

		// Set custom template again. ;)
		$template->set_custom_template('../adm/style', 'admin');

		// Get current and latest version
		$this->current_version = $config['version'];

		if (($latest_version = $cache->get('_version_info')) === false)
		{
			$this->latest_version = $this->get_file('version_info');
			$cache->put('_version_info', $this->latest_version);
		}
		else
		{
			$this->latest_version = $latest_version;
		}

		$up_to_date = (version_compare(strtolower($this->current_version), strtolower($this->latest_version), '<')) ? false : true;

		// Check for a valid update directory, else point the user to the phpbb.com website
		if (!file_exists($phpbb_root_path . 'install/update') || !file_exists($phpbb_root_path . 'install/update/index.' . $phpEx) || !file_exists($this->old_location) || !file_exists($this->new_location))
		{
			$template->assign_vars(array(
				'S_ERROR'		=> true,
				'ERROR_MSG'		=> ($up_to_date) ? $user->lang['NO_UPDATE_FILES_UP_TO_DATE'] : sprintf($user->lang['NO_UPDATE_FILES_OUTDATED'], $config['version'], $this->current_version, $this->latest_version))
			);

			return;
		}

		$this->update_info = $this->get_file('update_info');

		// Make sure the update directory holds the correct information
		// Since admins are able to run the update/checks more than once we only check if the current version is lower or equal than the version to which we update to.
		if (version_compare(strtolower($this->current_version), strtolower($this->update_info['version']['to']), '>'))
		{
			$template->assign_vars(array(
				'S_ERROR'		=> true,
				'ERROR_MSG'		=> sprintf($user->lang['INCOMPATIBLE_UPDATE_FILES'], $config['version'], $this->update_info['version']['from'], $this->update_info['version']['to']))
			);

			return;
		}

		// Check if the update files stored are for the latest version...
		if ($this->latest_version != $this->update_info['version']['to'])
		{
			$template->assign_vars(array(
				'S_ERROR'		=> true,
				'ERROR_MSG'		=> sprintf($user->lang['OLD_UPDATE_FILES'], $this->update_info['version']['from'], $this->update_info['version']['to'], $this->latest_version))
			);

			return;
		}

		// Got the updater template itself updated? If so, we are able to directly use it - but only if all three files are present
		if (in_array('adm/style/install_update.html', $this->update_info['files']))
		{
			$this->tpl_name = '../../install/update/new/adm/style/install_update';
		}

		// What about the language file? Got it updated?
		if (in_array('language/en/install.php', $this->update_info['files']))
		{
			$lang = array();
			include('./update/new/language/en/install.php');
			$user->lang = array_merge($user->lang, $lang);
		}

		// Make sure we stay at the file check if checking the files again
		if (!empty($_POST['check_again']))
		{
			$sub = $this->p_master->sub = 'file_check';
		}

		switch ($sub)
		{
			case 'intro':
				$this->page_title = 'UPDATE_INSTALLATION';

				$template->assign_vars(array(
					'S_INTRO'		=> true,
					'U_ACTION'		=> append_sid($this->p_master->module_url, "mode=$mode&amp;sub=version_check"),
				));

				// Make sure the update list is destroyed.
				$cache->destroy('_update_list');
			break;

			case 'version_check':
				$this->page_title = 'STAGE_VERSION_CHECK';

				$template->assign_vars(array(
					'S_UP_TO_DATE'		=> $up_to_date,
					'S_VERSION_CHECK'	=> true,
					'U_ACTION'			=> append_sid($this->p_master->module_url, "mode=$mode&amp;sub=file_check"),

					'LATEST_VERSION'	=> $this->latest_version,
					'CURRENT_VERSION'	=> $config['version'])
				);

			break;

			case 'file_check':

				$this->page_title = 'STAGE_FILE_CHECK';

				// Now make sure our update list is correct if the admin refreshes
				$action = request_var('action', '');

				// We are directly within an update. To make sure our update list is correct we check its status.
				$update_list = (!empty($_POST['check_again'])) ? false : $cache->get('_update_list');
				$modified = ($update_list !== false) ? @filemtime($cache->cache_dir . 'data_update_list.' . $phpEx) : 0;

				// Make sure the list is up-to-date
				if ($update_list !== false)
				{
					$get_new_list = false;
					foreach ($this->update_info['files'] as $file)
					{
						if (file_exists($phpbb_root_path . $file) && filemtime($phpbb_root_path . $file) > $modified)
						{
							$get_new_list = true;
							break;
						}
					}
				}
				else
				{
					$get_new_list = true;
				}

				if ($get_new_list)
				{
					$update_list = $this->get_update_structure();
					$cache->put('_update_list', $update_list);
				}

				if ($action == 'diff')
				{
					$this->show_diff($update_list);
					return;
				}

				if (sizeof($update_list['no_update']))
				{
					$template->assign_vars(array(
						'S_NO_UPDATE_FILES'		=> true,
						'NO_UPDATE_FILES'		=> implode(', ', array_map('htmlspecialchars', $update_list['no_update'])))
					);
				}

				// Now assign the list to the template
				foreach ($update_list as $status => $filelist)
				{
					if ($status == 'no_update' || !sizeof($filelist))
					{
						continue;
					}

					$template->assign_block_vars('files', array(
						'S_STATUS'		=> true,
						'STATUS'		=> $status,
						'L_STATUS'		=> $user->lang['STATUS_' . strtoupper($status)],
						'TITLE'			=> $user->lang['FILES_' . strtoupper($status)],
						'EXPLAIN'		=> $user->lang['FILES_' . strtoupper($status) . '_EXPLAIN'],
						)
					);

					foreach ($filelist as $file_struct)
					{
						$template->assign_block_vars('files', array(
							'STATUS'			=> $status,

							'FILENAME'			=> htmlspecialchars($file_struct['filename']),
							'NUM_CONFLICTS'		=> (isset($file_struct['conflicts'])) ? $file_struct['conflicts'] : 0,

							'S_CUSTOM'			=> ($file_struct['custom']) ? true : false,
							'CUSTOM_ORIGINAL'	=> ($file_struct['custom']) ? $file_struct['original'] : '',

							'U_SHOW_DIFF'		=> append_sid($this->p_master->module_url, "mode=$mode&amp;sub=file_check&amp;action=diff&amp;status=$status&amp;file=" . urlencode($file_struct['filename'])),
							'UA_SHOW_DIFF'		=> append_sid($this->p_master->module_url, "mode=$mode&sub=file_check&action=diff&status=$status&file=" . urlencode($file_struct['filename']), false),
							'L_SHOW_DIFF'		=> ($status != 'up_to_date') ? $user->lang['SHOW_DIFF_' . strtoupper($status)] : '',
						));
					}
				}

				$all_up_to_date = true;
				foreach ($update_list as $status => $filelist)
				{
					if ($status != 'up_to_date' && $status != 'custom' && sizeof($filelist))
					{
						$all_up_to_date = false;
						break;
					}
				}

				$template->assign_vars(array(
					'S_FILE_CHECK'			=> true,
					'S_ALL_UP_TO_DATE'		=> $all_up_to_date,
					'S_VERSION_UP_TO_DATE'	=> $up_to_date,
					'U_ACTION'				=> append_sid($this->p_master->module_url, "mode=$mode&amp;sub=file_check"),
					'U_UPDATE_ACTION'		=> append_sid($this->p_master->module_url, "mode=$mode&amp;sub=update_files"),
					'U_DB_UPDATE_ACTION'	=> append_sid($this->p_master->module_url, "mode=$mode&amp;sub=update_db"),
				));

			break;

			case 'update_files':

				$this->page_title = 'STAGE_UPDATE_FILES';

				$s_hidden_fields = '';
				foreach (request_var('conflict', array('' => 0)) as $filename => $merge_option)
				{
					$s_hidden_fields .= '<input type="hidden" name="conflict[' . htmlspecialchars($filename) . ']" value="' . $merge_option . '" />';
				}

				$no_update = request_var('no_update', array(0 => ''));

				foreach ($no_update as $index => $filename)
				{
					$s_hidden_fields .= '<input type="hidden" name="no_update[]" value="' . htmlspecialchars($filename) . '" />';
				}

				if (!empty($_POST['download']))
				{
					include_once($phpbb_root_path . 'includes/functions_compress.' . $phpEx);

					$use_method = request_var('use_method', '');
					$methods = array('.tar');

					$available_methods = array('.tar.gz' => 'zlib', '.tar.bz2' => 'bz2', '.zip' => 'zlib');
					foreach ($available_methods as $type => $module)
					{
						if (!@extension_loaded($module))
						{
							continue;
						}
		
						$methods[] = $type;
					}

					// Let the user decide in which format he wants to have the pack
					if (!$use_method)
					{
						$this->page_title = 'SELECT_DOWNLOAD_FORMAT';

						$radio_buttons = '';
						foreach ($methods as $method)
						{
							$radio_buttons .= '<input type="radio"' . ((!$radio_buttons) ? ' id="use_method"' : '') . ' class="radio" value="' . $method . '" name="use_method" />&nbsp;' . $method . '&nbsp;';
						}

						$template->assign_vars(array(
							'S_DOWNLOAD_FILES'		=> true,
							'U_ACTION'				=> append_sid($this->p_master->module_url, "mode=$mode&amp;sub=update_files"),
							'RADIO_BUTTONS'			=> $radio_buttons,
							'S_HIDDEN_FIELDS'		=> $s_hidden_fields)
						);

						// To ease the update process create a file location map
						$update_list = $cache->get('_update_list');

						foreach ($update_list as $status => $files)
						{
							if ($status == 'up_to_date' || $status == 'no_update')
							{
								continue;
							}

							foreach ($files as $file_struct)
							{
								if (in_array($file_struct['filename'], $no_update))
								{
									continue;
								}

								$template->assign_block_vars('location', array(
									'SOURCE'		=> htmlspecialchars($file_struct['filename']),
									'DESTINATION'	=> $user->page['root_script_path'] . htmlspecialchars($file_struct['filename']),
								));
							}
						}

						return;
					}

					if (!in_array($use_method, $methods))
					{
						$use_method = '.tar';
					}

					$update_mode = 'download';
				}
				else
				{
					include_once($phpbb_root_path . 'includes/functions_transfer.' . $phpEx);

					// Choose FTP, if not available use fsock...
					$method = request_var('method', '');
					$submit = (isset($_POST['submit'])) ? true : false;
					$test_ftp_connection = request_var('test_connection', '');

					if (!$method)
					{
						$method = 'ftp';
						$methods = transfer::methods();

						if (!in_array('ftp', $methods))
						{
							$method = $methods[0];
						}
					}

					$test_connection = false;
					if ($test_ftp_connection || $submit)
					{
						$transfer = new $method(request_var('host', ''), request_var('username', ''), request_var('password', ''), request_var('root_path', ''), request_var('port', ''), request_var('timeout', ''));
						$test_connection = $transfer->open_session();

						// Make sure that the directory is correct by checking for the existence of common.php
						if ($test_connection === true)
						{
							// Check for common.php file
							if (!$transfer->file_exists($phpbb_root_path, 'common.' . $phpEx))
							{
								$test_connection = 'ERR_WRONG_PATH_TO_PHPBB';
							}
						}

						$transfer->close_session();

						// Make sure the login details are correct before continuing
						if ($submit && $test_connection !== true)
						{
							$submit = false;
							$test_ftp_connection = true;
						}
					}

					if (!$submit)
					{
						$this->page_title = 'SELECT_FTP_SETTINGS';

						$requested_data = call_user_func(array($method, 'data'));
						foreach ($requested_data as $data => $default)
						{
							$template->assign_block_vars('data', array(
								'DATA'		=> $data,
								'NAME'		=> $user->lang[strtoupper($method . '_' . $data)],
								'EXPLAIN'	=> $user->lang[strtoupper($method . '_' . $data) . '_EXPLAIN'],
								'DEFAULT'	=> (!empty($_REQUEST[$data])) ? request_var($data, '') : $default
							));
						}

						$s_hidden_fields .= build_hidden_fields(array('method' => $method));

						$template->assign_vars(array(
							'S_CONNECTION_SUCCESS'		=> ($test_ftp_connection && $test_connection === true) ? true : false,
							'S_CONNECTION_FAILED'		=> ($test_ftp_connection && $test_connection !== true) ? true : false,
							'ERROR_MSG'					=> ($test_ftp_connection && $test_connection !== true) ? $user->lang[$test_connection] : '',

							'S_FTP_UPLOAD'		=> true,
							'UPLOAD_METHOD'		=> $method,
							'U_ACTION'			=> append_sid($this->p_master->module_url, "mode=$mode&amp;sub=update_files"),
							'S_HIDDEN_FIELDS'	=> $s_hidden_fields)
						);

						return;
					}

					$update_mode = 'upload';
				}

				// Now update the installation or download the archive...
				$archive_filename = 'update_' . $this->update_info['version']['from'] . '_to_' . $this->update_info['version']['to'];
				$update_list = $cache->get('_update_list');
				$conflicts = request_var('conflict', array('' => 0));

				if ($update_list === false)
				{
					trigger_error($user->lang['NO_UPDATE_INFO'], E_USER_ERROR);
				}

				// Check if the conflicts data is valid
				if (sizeof($conflicts))
				{
					$conflict_filenames = array();
					foreach ($update_list['conflict'] as $files)
					{
						$conflict_filenames[] = $files['filename'];
					}

					$new_conflicts = array();
					foreach ($conflicts as $filename => $diff_method)
					{
						if (in_array($filename, $conflict_filenames))
						{
							$new_conflicts[$filename] = $diff_method;
						}
					}

					$conflicts = $new_conflicts;
				}

				if (sizeof($update_list['conflict']) != sizeof($conflicts))
				{
					trigger_error($user->lang['MERGE_SELECT_ERROR'], E_USER_ERROR);
				}

				// Now init the connection
				if ($update_mode == 'download')
				{
					if ($use_method == '.zip')
					{
						$compress = new compress_zip('w', $phpbb_root_path . 'store/' . $archive_filename . $use_method);
					}
					else
					{
						$compress = new compress_tar('w', $phpbb_root_path . 'store/' . $archive_filename . $use_method, $use_method);
					}
				}
				else
				{
					$transfer = new $method(request_var('host', ''), request_var('username', ''), request_var('password', ''), request_var('root_path', ''), request_var('port', ''), request_var('timeout', ''));
					$transfer->open_session();
				}

				// Ok, go through the update list and do the operations based on their status
				foreach ($update_list as $status => $files)
				{
					foreach ($files as $file_struct)
					{
						// Skip this file if the user selected to not update it
						if (in_array($file_struct['filename'], $no_update))
						{
							continue;
						}

						$original_filename = ($file_struct['custom']) ? $file_struct['original'] : $file_struct['filename'];

						switch ($status)
						{
							case 'new':
							case 'new_conflict':
							case 'not_modified':
								if ($update_mode == 'download')
								{
									$compress->add_custom_file($this->new_location . $original_filename, $file_struct['filename']);
								}
								else
								{
									if ($status != 'new')
									{
										$transfer->rename($file_struct['filename'], $file_struct['filename'] . '.bak');
									}
									$transfer->copy_file($this->new_location . $original_filename, $file_struct['filename']);
								}
							break;

							case 'modified':
								$diff = &new diff3(file($this->old_location . $original_filename), file($phpbb_root_path . $file_struct['filename']), file($this->new_location . $original_filename));
								$contents = implode("\n", $diff->merged_output());

								if ($update_mode == 'download')
								{
									$compress->add_data($contents, $file_struct['filename']);
								}
								else
								{
									$transfer->rename($file_struct['filename'], $file_struct['filename'] . '.bak');
									$transfer->write_file($file_struct['filename'], $contents);
								}
							break;

							case 'conflict':
								$diff = &new diff3(file($this->old_location . $original_filename), file($phpbb_root_path . $file_struct['filename']), file($this->new_location . $original_filename));

								if ($conflicts[$file_struct['filename']] == 1)
								{
									$contents = implode("\n", $diff->merged_new_output());
								}
								else if ($conflicts[$file_struct['filename']] == 2)
								{
									$contents = implode("\n", $diff->merged_orig_output());
								}
								else
								{
									break;
								}

								if ($update_mode == 'download')
								{
									$compress->add_data($contents, $file_struct['filename']);
								}
								else
								{
									$transfer->rename($file_struct['filename'], $file_struct['filename'] . '.bak');
									$transfer->write_file($file_struct['filename'], $contents);
								}
							break;
						}
					}
				}

				if ($update_mode == 'download')
				{
					$compress->close();

					$compress->download($archive_filename);
					@unlink($phpbb_root_path . 'store/' . $archive_filename . $use_method);

					exit;
				}
				else
				{
					$transfer->close_session();

					$template->assign_vars(array(
						'S_UPLOAD_SUCCESS'	=> true,
						'U_ACTION'			=> append_sid($this->p_master->module_url, "mode=$mode&amp;sub=file_check"))
					);
					return;
				}

			break;

			case 'update_db':

				// Make sure the database update is valid for the latest version
				$valid = false;
				$updates_to_version = '';

				if (file_exists($phpbb_root_path . 'install/database_update.' . $phpEx))
				{
					include_once($phpbb_root_path . 'install/database_update.' . $phpEx);

					if ($updates_to_version === $this->latest_version)
					{
						$valid = true;
					}
				}

				// Should not happen at all
				if (!$valid)
				{
					trigger_error($user->lang['DATABASE_UPDATE_INFO_OLD'], E_USER_ERROR);
				}

				// Because we are done with the file update we purge the cache directory
				$cache->purge();

				// Redirect the user to the database update script with some explanations...
				$template->assign_vars(array(
					'S_DB_UPDATE'		=> true,
					'U_DB_UPDATE'		=> $phpbb_root_path . 'install/database_update.' . $phpEx)
				);

			break;
		}
	}

	/**
	* Show file diff
	*/
	function show_diff(&$update_list)
	{
		global $phpbb_root_path, $template, $user;

		$this->tpl_name = 'install_update_diff';
		$this->page_title = 'VIEWING_FILE_DIFF';

		$status = request_var('status', '');
		$file = request_var('file', '');
		$diff_mode = request_var('diff_mode', 'inline');

		// First of all make sure the file is within our file update list with the correct status
		$found_entry = array();
		foreach ($update_list[$status] as $index => $file_struct)
		{
			if ($file_struct['filename'] === $file)
			{
				$found_entry = $update_list[$status][$index];
			}
		}

		if (empty($found_entry))
		{
			trigger_error($user->lang['FILE_DIFF_NOT_ALLOWED'], E_USER_ERROR);
		}

		// If the status is 'up_to_date' then we do not need to show a diff
		if ($status == 'up_to_date')
		{
			trigger_error($user->lang['FILE_ALREADY_UP_TO_DATE'], E_USER_ERROR);
		}

		$original_file = ($found_entry['custom']) ? $found_entry['original'] : $file;

		// Get the correct diff
		switch ($status)
		{
			case 'conflict':
				$diff = &new diff3(file($this->old_location . $original_file), file($phpbb_root_path . $file), file($this->new_location . $original_file));

				$template->assign_vars(array(
					'S_DIFF_CONFLICT_FILE'	=> true,
					'NUM_CONFLICTS'			=> $diff->merged_output(false, false, false, true))
				);
			break;

			case 'modified':
				$diff = &new diff3(file($this->old_location . $original_file), file($phpbb_root_path . $original_file), file($this->new_location . $file));
			break;

			case 'not_modified':
			case 'new_conflict':
				$diff = &new diff(file($phpbb_root_path . $file), file($this->new_location . $original_file));
			break;

			case 'new':
				$diff = &new diff(array(), file($this->new_location . $original_file));
				$template->assign_var('S_DIFF_NEW_FILE', true);
				$diff_mode = 'inline';
				$this->page_title = 'VIEWING_FILE_CONTENTS';
			break;
		}

		$diff_mode_options = '';
		foreach (array('side_by_side', 'inline', 'unified', 'raw') as $option)
		{
			$diff_mode_options .= '<option value="' . $option . '"' . (($diff_mode == $option) ? ' selected="selected"' : '') . '>' . $user->lang['DIFF_' . strtoupper($option)] . '</option>';
		}

		// Now the correct renderer
		$render_class = 'diff_renderer_' . $diff_mode;

		if (!class_exists($render_class))
		{
			trigger_error('Chosen diff mode is not supported', E_USER_ERROR);
		}

		$renderer = &new $render_class();

		$template->assign_vars(array(
			'DIFF_CONTENT'			=> $renderer->get_diff_content($diff),
			'S_DIFF_MODE_OPTIONS'	=> $diff_mode_options,
			'S_SHOW_DIFF'			=> true,
		));
	}

	/**
	* Collect all file status infos we need for the update by diffing all files
	*/
	function get_update_structure()
	{
		global $phpbb_root_path, $phpEx, $user;

		$update_list = array(
			'up_to_date'	=> array(),
			'new'			=> array(),
			'not_modified'	=> array(),
			'modified'		=> array(),
			'new_conflict'	=> array(),
			'conflict'		=> array(),
			'no_update'		=> array(),
		);

		// Get a list of those files which are completely new by checking with file_exists...
		foreach ($this->update_info['files'] as $index => $file)
		{
			if (!file_exists($phpbb_root_path . $file))
			{
				// Make sure the update files are consistent by checking if the file is in new_files...
				if (!file_exists($this->new_location . $file))
				{
					trigger_error($user->lang['INCOMPLETE_UPDATE_FILES'], E_USER_ERROR);
				}

				// If the file exists within the old directory the file got removed and we will write it back
				// not a biggie, but we might want to state this circumstance seperatly later.
				//	if (file_exists($this->old_location . $file))
				//	{
				//		$update_list['removed'][] = $file;
				//	}

				// Only include a new file as new if the underlying path exist
				// The path normally do not exist if the original style or language has been removed
				if (file_exists($phpbb_root_path . dirname($file)))
				{
					$this->get_custom_info($update_list['new'], $file);
					$update_list['new'][] = array('filename' => $file, 'custom' => false);
				}
				else
				{
					$update_list['no_update'][] = $file;
				}
				unset($this->update_info['files'][$index]);
			}
		}

		if (!sizeof($this->update_info['files']))
		{
			return $update_list;
		}

		// Now diff the remaining files to get information about their status (not modified/modified/up-to-date)

		// not modified?
		foreach ($this->update_info['files'] as $index => $file)
		{
			$this->make_update_diff($update_list, $file, $file);
		}

		// Now to the styles...
		if (empty($this->update_info['custom']))
		{
			return $update_list;
		}

		foreach ($this->update_info['custom'] as $original_file => $file_ary)
		{
			foreach ($file_ary as $index => $file)
			{
				$this->make_update_diff($update_list, $original_file, $file, true);
			}
		}

		return $update_list;
	}

	/**
	* Compare files for storage in update_list
	*/
	function make_update_diff(&$update_list, $original_file, $file, $custom = false)
	{
		global $phpbb_root_path, $user;

		$update_ary = array('filename' => $file, 'custom' => $custom);

		if ($custom)
		{
			$update_ary['original'] = $original_file;
		}

		// On a successfull update the new location file exists but the old one does not exist.
		// Check for this circumstance, the new file need to be up-to-date with the current file then...
		if (!file_exists($this->old_location . $original_file) && file_exists($this->new_location . $original_file) && file_exists($phpbb_root_path . $file))
		{
			// We need to diff the contents here to make sure the file is really the one we expect
			$diff = &new diff(file($this->new_location . $original_file), file($phpbb_root_path . $file));

			// if there are no differences we have an up-to-date file...
			if ($diff->is_empty())
			{
				$update_list['up_to_date'][] = $update_ary;
				return;
			}

			// If no other status matches we have another file in the way...
			$update_list['new_conflict'][] = $update_ary;
			return;
		}

		// Check for existance, else abort immediatly
		if (!file_exists($this->old_location . $original_file) || !file_exists($this->new_location . $original_file))
		{
			trigger_error($user->lang['INCOMPLETE_UPDATE_FILES'], E_USER_ERROR);
		}

		$diff = &new diff(file($this->old_location . $original_file), file($phpbb_root_path . $file));

		// If the file is not modified we are finished here...
		if ($diff->is_empty())
		{
			// Further check if it is already up to date - it could happen that non-modified files
			// slip through
			$diff = &new diff(file($this->new_location . $original_file), file($phpbb_root_path . $file));

			if ($diff->is_empty())
			{
				$update_list['up_to_date'][] = $update_ary;
				return;
			}

			$update_list['not_modified'][] = $update_ary;
			return;
		}

		// If the file had been modified then we need to check if it is already up to date
		$diff = &new diff(file($this->new_location . $original_file), file($phpbb_root_path . $file));

		// if there are no differences we have an up-to-date file...
		if ($diff->is_empty())
		{
			$update_list['up_to_date'][] = $update_ary;
			return;
		}

		// if the file is modified we try to make sure a merge succeed
		$diff = &new diff3(file($this->old_location . $original_file), file($phpbb_root_path . $file), file($this->new_location . $original_file));

		if ($diff->merged_output(false, false, false, true))
		{
			$update_ary['conflicts'] = $diff->_conflicting_blocks;
			$update_list['conflict'][] = $update_ary;
			return;
		}

		// now compare the merged output with the original file to see if the modified file is up to date
		$diff = &new diff(file($phpbb_root_path . $file), $diff->merged_output());

		if ($diff->is_empty())
		{
			$update_list['up_to_date'][] = $update_ary;
			return;
		}

		// If no other status matches we have a modified file...
		$update_list['modified'][] = $update_ary;
	}

	/**
	* Update update_list with custom new files
	*/
	function get_custom_info(&$update_list, $file)
	{
		if (empty($this->update_info['custom']))
		{
			return;
		}

		if (in_array($file, array_keys($this->update_info['custom'])))
		{
			foreach ($this->update_info['custom'][$file] as $_file)
			{
				$update_list[] = array('filename' => $_file, 'custom' => true, 'original' => $file);
			}
		}
	}

	/**
	* Get remote file
	*/
	function get_file($mode)
	{
		global $user, $db;

		$errstr = '';
		$errno = 0;

		switch ($mode)
		{
			case 'version_info':
				$info = get_remote_file('www.phpbb.com', '/updatecheck', '30x.txt', $errstr, $errno);

				if ($info !== false)
				{
					$info = explode("\n", $info);
					$info = trim($info[0]);
				}

				if ($this->test_update !== false)
				{
					$info = $this->test_update;
				}
			break;

			case 'update_info':
				global $phpbb_root_path, $phpEx;

				$update_info = array();
				include($phpbb_root_path . 'install/update/index.php');

				$info = (empty($update_info) || !is_array($update_info)) ? false : $update_info;
				$errstr = ($info === false) ? $user->lang['WRONG_INFO_FILE_FORMAT'] : '';

				if ($info !== false)
				{
					// Adjust the update info file to hold some specific style-related information
					$info['custom'] = array();

					/* Get custom installed styles...
					$sql = 'SELECT template_name, template_path
						FROM ' . STYLES_TEMPLATE_TABLE . "
						WHERE template_name NOT IN ('subSilver', 'BLABLA')";
					$result = $db->sql_query($sql);

					$templates = array();
					while ($row = $db->sql_fetchrow($result))
					{
						$templates[] = $row;
					}
					$db->sql_freeresult($result);

					if (sizeof($templates))
					{
						foreach ($info['files'] as $filename)
						{
							// Template update?
							if (strpos($filename, 'styles/subSilver/template/') === 0)
							{
								foreach ($templates as $row)
								{
									$info['custom'][$filename][] = str_replace('/subSilver/', '/' . $row['template_path'] . '/', $filename);
								}
							}
						}
					}
					*/
				}
			break;

			default:
				trigger_error('Mode for getting remote file not specified', E_USER_ERROR);
			break;
		}

		if ($info === false)
		{
			trigger_error($errstr, E_USER_ERROR);
		}

		return $info;
	}
}

?>