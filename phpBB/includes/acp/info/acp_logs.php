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
* @package module_install
*/
class acp_logs_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_logs',
			'title'		=> 'ACP_LOGGING',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'admin'		=> array('title' => 'ACP_ADMIN_LOGS', 'auth' => 'acl_a_viewlogs'),
				'mod'		=> array('title' => 'ACP_MOD_LOGS', 'auth' => 'acl_a_viewlogs'),
				'critical'	=> array('title' => 'ACP_CRITICAL_LOGS', 'auth' => 'acl_a_viewlogs'),
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