<?php
/** 
*
* @package mcp
* @version $Id$
* @copyright (c) 2005 phpBB Group 
* @license http://opensource.org/licenses/gpl-license.php GNU Public License 
*
*/

/**
* @package module_install
*/
class mcp_reports_info
{
	function module()
	{
		return array(
			'filename'	=> 'mcp_reports',
			'title'		=> 'MCP_REPORTS',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'report_details'	=> array('title' => 'MCP_REPORT_DETAILS', 'auth' => 'acl_m_report || aclf_m_report'),
				'reports'			=> array('title' => 'MCP_REPORTS', 'auth' => 'acl_m_report ||aclf_m_report'),
				'reports_closed'	=> array('title' => 'MCP_REPORTS_CLOSED', 'auth' => 'acl_m_report || aclf_m_report'),
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