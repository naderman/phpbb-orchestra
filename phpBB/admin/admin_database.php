<?php
/***************************************************************************
*                             admin_db_utilities.php
*                              -------------------
*     begin                : Thu May 31, 2001
*     copyright            : (C) 2001 The phpBB Group
*     email                : support@phpbb.com
*
*     $Id$
*
****************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/
//
//	Some functions are adapted phpMyAdmin 2.2.0.
//

if ( !empty($setmodules) )
{
	if ( !$acl->get_acl_admin('general') )
	{
		return;
	}
	
	$filename = basename(__FILE__);
	$module['DB']['DB_Backup'] = $filename . "$SID&amp;mode=backup";

	$file_uploads = @ini_get('file_uploads');
	if( ( $file_uploads != 0 || empty($file_uploads) ) && strtolower($file_uploads) != 'off' && @phpversion() != '4.0.4pl1' )
	{
		$module['DB']['DB_Restore'] = $filename . "$SID&amp;mode=restore";
	}

	return;
}

define('IN_PHPBB', 1);
//
// Load default header
//
$phpbb_root_path = '../';
require($phpbb_root_path . 'extension.inc');
require('pagestart.' . $phpEx);
include($phpbb_root_path . 'includes/functions_admin.'.$phpEx);

//
//
//
if ( !$acl->get_acl_admin('general') )
{
	message_die(MESSAGE, $lang['No_admin']);
}

//
// Increase maximum execution time, but don't complain about it if it isn't
// allowed.
//
@set_time_limit(1200);

//
// Begin program proper
//

if ( isset($HTTP_GET_VARS['mode']) || isset($HTTP_POST_VARS['mode']) )
{
	$mode = ( isset($HTTP_POST_VARS['mode']) ) ? $HTTP_POST_VARS['mode'] : $HTTP_GET_VARS['mode'];

	switch($mode)
	{
		case 'backup':

			if ( SQL_LAYER == 'oracle' || SQL_LAYER == 'odbc' || SQL_LAYER == 'mssql' )
			{
				switch ( SQL_LAYER )
				{
					case 'oracle':
						$db_type = 'Oracle';
						break;
					case 'odbc':
						$db_type = 'ODBC';
						break;
					case 'mssql':
						$db_type = 'MSSQL';
						break;
				}

				message_die(MESSAGE, $lang['Backups_not_supported']);
				break;
			}

			$tables = array('search_wordlist', 'search_wordmatch', 'auth_access', 'banlist', 'categories', 'config', 'disallow', 'forums', 'forum_prune', 'groups', 'posts', 'posts_text', 'privmsgs', 'privmsgs_text', 'ranks', 'search_results', 'search_results', 'sessions', 'smilies', 'themes', 'themes_name', 'topics', 'topics_watch', 'user_group', 'users', 'vote_desc', 'vote_results', 'vote_voters', 'words');

			$additional_tables = ( isset($HTTP_POST_VARS['additional_tables']) ) ? $HTTP_POST_VARS['additional_tables'] : ( ( isset($HTTP_GET_VARS['additional_tables']) ) ? $HTTP_GET_VARS['additional_tables'] : '' );

			$backup_type = ( isset($HTTP_POST_VARS['backup_type']) ) ? $HTTP_POST_VARS['backup_type'] : ( ( isset($HTTP_GET_VARS['backup_type']) ) ? $HTTP_GET_VARS['backup_type'] : '' );

			$ignoresearch = ( !empty($HTTP_POST_VARS['ignoresearch']) ) ? $HTTP_POST_VARS['ignoresearch'] : ( ( !empty($HTTP_GET_VARS['ignoresearch']) ) ? $HTTP_GET_VARS['ignoresearch'] : 0 );

			$gzipcompress = ( !empty($HTTP_POST_VARS['gzipcompress']) ) ? $HTTP_POST_VARS['gzipcompress'] : ( ( !empty($HTTP_GET_VARS['gzipcompress']) ) ? $HTTP_GET_VARS['gzipcompress'] : 0 );

			if ( !empty($additional_tables) )
			{
				$additional_tables = explode(', ', $additional_tables);

				for($i = 0; $i < count($additional_tables); $i++)
				{
					$tables[] = trim($additional_tables[$i]);
				}
				unset($additional_tables);
			}

			if ( !isset($HTTP_POST_VARS['backupstart']) && !isset($HTTP_GET_VARS['backupstart']))
			{
				$s_hidden_fields = '<input type="hidden" name="mode" value="backup" /><input type="hidden" name="drop" value="1" /><input type="hidden" name="mode" value="$mode" />';

				page_header($lang['DB_Backup']);
?>

<h1><?php echo $lang['DB_Backup']; ?></h1>

<p><?php echo $lang['Backup_explain']; ?></p>

<form method="post" action="<?php echo "admin_db_utilities.$phpEx$SID&amp;mode=$mode"; ?>"><table class="bg" width="80%" cellspacing="1" cellpadding="4" border="0" align="center">
	<tr>
		<th colspan="2"><?php echo $lang['Backup_options']; ?></th>
	</tr>
	<tr>
		<td class="row1"><?php echo $lang['Backup_type']; ?></td>
		<td class="row2"><input type="radio" name="backup_type" value="full"  checked="checked" /> <?php echo $lang['Full_backup']; ?>&nbsp;&nbsp;<input type="radio" name="backup_type" value="structure" /> <?php echo $lang['Structure_only']; ?>&nbsp;&nbsp;<input type="radio" name="backup_type" value="data" /> <?php echo $lang['Data_only']; ?></td>
	</tr>
	<tr>
		<td class="row1"><?php echo $lang['Include_search_index']; ?><br /><span class="gensmall"><?php echo $lang['Include_search_index_explain']; ?></span></td>
		<td class="row2"><input type="radio" name="ignoresearch" value="0" /> <?php echo $lang['No']; ?>&nbsp;&nbsp;<input type="radio" name="ignoresearch" value="1" checked="checked" /> <?php echo $lang['Yes']; ?></td>
	</tr>
	<tr>
		<td class="row1"><?php echo $lang['Additional_tables']; ?><br /><span class="gensmall"><?php echo $lang['Additional_tables_explain']; ?></span></td>
		<td class="row2"><input type="text" name="additional_tables" size="40" /></td>
	</tr>
<?php
	
				if ( extension_loaded('zlib') )
				{

?>
	<tr>
		<td class="row1"><?php echo $lang['Gzip_compress']; ?></td>
		<td class="row2"><input type="radio" name="gzipcompress" value="0" checked="checked" /> <?php echo $lang['No']; ?>&nbsp;&nbsp;<input type="radio" name="gzipcompress" value="1" /> <?php echo $lang['Yes']; ?></td>
	</tr>
<?php
	
				}

?>
	<tr>
		<td class="cat" colspan="2" align="center"><?php echo $s_hidden_fields; ?><input type="submit" name="backupstart" value="<?php echo $lang['Start_backup']; ?>" class="mainoption" /></td>
	</tr>
</table></form>

<?php

				break;

			}
			else if ( !isset($HTTP_POST_VARS['startdownload']) && !isset($HTTP_GET_VARS['startdownload']) )
			{
				$template->assign_vars(array(
					"META" => "<meta http-equiv=\"refresh\" content=\"0;url=admin_db_utilities.$phpEx?mode=backup&amp;additional_tables=" . quotemeta($additional_tables) . "&amp;backup_type=$backup_type&amp;drop=1&amp;backupstart=1&amp;gzipcompress=$gzipcompress&amp;startdownload=1\">",

					"MESSAGE_TITLE" => $lang['Database_Utilities'] . " : " . $lang['Backup'],
					"MESSAGE_TEXT" => $lang['Backup_download'])
				);

				include('page_header_admin.php');

				include('page_footer_admin.'.$phpEx);

			}

			header("Pragma: no-cache");
			$do_gzip_compress = FALSE;

			if( $gzipcompress )
			{
				$phpver = phpversion();

				if($phpver >= "4.0")
				{
					if(extension_loaded("zlib"))
					{
						$do_gzip_compress = TRUE;
					}
				}
			}
			if($do_gzip_compress)
			{
				@ob_start();
				@ob_implicit_flush(0);
				header("Content-Type: text/x-delimtext; name=\"phpbb_db_backup.sql.gz\"");
				header("Content-disposition: attachment; filename=phpbb_db_backup.sql.gz");
			}
			else
			{
				header("Content-Type: text/x-delimtext; name=\"phpbb_db_backup.sql\"");
				header("Content-disposition: attachment; filename=phpbb_db_backup.sql");
			}

			//
			// Build the sql script file...
			//
			echo "#\n";
			echo "# phpBB Backup Script\n";
			echo "# Dump of tables for $dbname\n";
			echo "#\n# DATE : " .  gmdate("d-m-Y H:i:s", time()) . " GMT\n";
			echo "#\n";

			if ( SQL_LAYER == 'postgresql' )
			{
				 echo "\n" . pg_get_sequences("\n", $backup_type);
			}

			for($i = 0; $i < count($tables); $i++)
			{
				$table_name = $tables[$i];
				if ( SQL_LAYER != 'mysql4' )
				{
					$table_def_function = "get_table_def_" . SQL_LAYER;
					$table_content_function = "get_table_content_" . SQL_LAYER;
				}
				else
				{
					$table_def_function = "get_table_def_mysql";
					$table_content_function = "get_table_content_mysql";
				}

				if ( $backup_type != 'data' )
				{
					echo "#\n# TABLE: " . $table_prefix . $table_name . "\n#\n";
					echo $table_def_function($table_prefix . $table_name, "\n") . "\n";
				}

				if ( $backup_type != 'structure' )
				{
					$table_content_function($table_prefix . $table_name, "output_table_content");
				}
			}
			
			if ( $do_gzip_compress )
			{
				$Size = ob_get_length();
				$Crc = crc32(ob_get_contents());
				$contents = gzcompress(ob_get_contents());
				ob_end_clean();
				echo "\x1f\x8b\x08\x00\x00\x00\x00\x00".substr($contents, 0, strlen($contents) - 4).gzip_PrintFourChars($Crc).gzip_PrintFourChars($Size);
			}
			exit;

			break;

		case 'restore':

			if ( !isset($restore_start) )
			{
				$s_hidden_fields = '<input type="hidden" name="mode" value="restore" /><input type="hidden" name="mode" value="$mode" />';

				page_header($lang['DB_Restore']);

?>

<h1><?php echo $lang['DB_Restore']; ?></h1>

<p><?php echo $lang['Restore_explain']; ?></p>

<form enctype="multipart/form-data" method="post" action="<?php echo "admin_db_utilities.$phpEx$SID&amp;mode=$mode"; ?>"><table class="bg" width="80%" cellspacing="1" cellpadding="4" border="0" align="center">
		<th colspan="2"><?php echo $lang['Select_file']; ?></th>
	</tr>
	<tr>
		<td class="row1"><?php echo $lang['Upload_file']; ?>: </td>
		<td class="row2"><input type="file" name="backup_file" /></td>
	</tr>
	<tr>
		<td class="row1"><?php echo $lang['Local_backup_file']; ?>: <br /><span class="gensmall"><?php echo $lang['Local_backup_file_explain']; ?></span></td>
		<td class="row2"><input type="text" name="local_file" size="40" /></td>
	</tr>
	<tr>
		<td class="cat" colspan="2" align="center"><input type="submit" name="restore_start" value="<?php echo $lang['Start_Restore']; ?>" class="mainoption" /></td>
	</trs>
</table><?php echo $s_hidden_fields; ?></form>

<?php

				break;

			}
			else
			{
				//
				// Handle the file upload ....
				// If no file was uploaded report an error...
				//
				$backup_file_name = (!empty($HTTP_POST_FILES['backup_file']['name'])) ? $HTTP_POST_FILES['backup_file']['name'] : "";
				$backup_file_tmpname = ($HTTP_POST_FILES['backup_file']['tmp_name'] != "none") ? $HTTP_POST_FILES['backup_file']['tmp_name'] : "";
				$backup_file_type = (!empty($HTTP_POST_FILES['backup_file']['type'])) ? $HTTP_POST_FILES['backup_file']['type'] : "";

				if ( $backup_file_tmpname == "" || $backup_file_name == "" )
				{
					message_die(MESSAGE, $lang['Restore_Error_no_file']);
				}
				//
				// If I file was actually uploaded, check to make sure that we
				// are actually passed the name of an uploaded file, and not
				// a hackers attempt at getting us to process a local system
				// file.
				//
				if( file_exists($backup_file_tmpname) )
				{
					if( preg_match("/^(text\/[a-zA-Z]+)|(application\/(x\-)?gzip(\-compressed)?)|(application\/octet-stream)$/is", $backup_file_type) )
					{
						if( preg_match("/\.gz$/is",$backup_file_name) )
						{
							$do_gzip_compress = FALSE;
							$phpver = phpversion();
							if($phpver >= "4.0")
							{
								if(extension_loaded("zlib"))
								{
									$do_gzip_compress = TRUE;
								}
							}

							if($do_gzip_compress)
							{
								$gz_ptr = gzopen($backup_file_tmpname, 'rb');
								$sql_query = "";
								while( !gzeof($gz_ptr) )
								{
									$sql_query .= gzgets($gz_ptr, 100000);
								}
							}
							else
							{
								include('page_header_admin.'.$phpEx);
								message_die(ERROR, $lang['Restore_Error_decompress']);
							}
						}
						else
						{
							$sql_query = fread(fopen($backup_file_tmpname, 'r'), filesize($backup_file_tmpname));
						}
						//
						// Comment this line out to see if this fixes the stuff...
						//
						//$sql_query = stripslashes($sql_query);
					}
					else
					{
						include('page_header_admin.'.$phpEx);
						message_die(ERROR, $lang['Restore_Error_filename'] ." $backup_file_type $backup_file_name");
					}
				}
				else
				{
					include('page_header_admin.'.$phpEx);
					message_die(ERROR, $lang['Restore_Error_uploading']);
				}

				if($sql_query != "")
				{
					// Strip out sql comments...
					$sql_query = remove_remarks($sql_query);
					$pieces = split_sql_file($sql_query, ";");

					$sql_count = count($pieces);
					for($i = 0; $i < $sql_count; $i++)
					{
						$sql = trim($pieces[$i]);

						if(!empty($sql) and $sql[0] != "#")
						{
							if(DEBUG == 1)
							{
								echo "Executing: $sql\n<br>";
								flush();
							}

							$result = $db->sql_query($sql);

							if(!$result && ( !(SQL_LAYER == 'postgresql' && eregi("drop table", $sql) ) ) )
							{
								//include('page_header_admin.'.$phpEx);
								// echo "~~$sql~~";
								message_die(ERROR, "Error importing backup file", "", __LINE__, __FILE__, $sql);
							}
						}
					}
				}

				include('page_header_admin.'.$phpEx);

				$message = $lang['Restore_success'];

				$template->assign_vars(array(
					"MESSAGE_TITLE" => $lang['Database_Utilities'] . " : " . $lang['Restore'],
					"MESSAGE_TEXT" => $message)
				);

				break;
			}
			break;
	}
}


page_footer();

// -----------------------------------------------
// The following functions are adapted from phpMyAdmin and upgrade_20.php
//
function gzip_PrintFourChars($Val)
{
	for ($i = 0; $i < 4; $i ++)
	{
		$return .= chr($Val % 256);
		$Val = floor($Val / 256);
	}
	return $return;
} 

//
// This function is used for grabbing the sequences for postgres...
//
function pg_get_sequences($crlf, $backup_type)
{
	global $db;

	$get_seq_sql = "SELECT relname FROM pg_class WHERE NOT relname ~ 'pg_.*'
		AND relkind = 'S' ORDER BY relname";

	$seq = $db->sql_query($get_seq_sql);

	if( !$num_seq = $db->sql_numrows($seq) )
	{

		$return_val = "# No Sequences Found $crlf";

	}
	else
	{
		$return_val = "# Sequences $crlf";
		$i_seq = 0;

		while($i_seq < $num_seq)
		{
			$row = $db->sql_fetchrow($seq);
			$sequence = $row['relname'];

			$get_props_sql = "SELECT * FROM $sequence";
			$seq_props = $db->sql_query($get_props_sql);

			if($db->sql_numrows($seq_props) > 0)
			{
				$row1 = $db->sql_fetchrow($seq_props);

				if($backup_type == 'structure')
				{
					$row['last_value'] = 1;
				}

				$return_val .= "CREATE SEQUENCE $sequence start " . $row['last_value'] . ' increment ' . $row['increment_by'] . ' maxvalue ' . $row['max_value'] . ' minvalue ' . $row['min_value'] . ' cache ' . $row['cache_value'] . "; $crlf";

			}  // End if numrows > 0

			if(($row['last_value'] > 1) && ($backup_type != 'structure'))
			{
				$return_val .= "SELECT NEXTVALE('$sequence'); $crlf";
				unset($row['last_value']);
			}

			$i_seq++;

		} // End while..

	} // End else...

	return $returnval;

} // End function...

//
// The following functions will return the "CREATE TABLE syntax for the
// varying DBMS's
//
// This function returns, will return the table def's for postgres...
//
function get_table_def_postgresql($table, $crlf)
{
	global $drop, $db;

	$schema_create = "";
	//
	// Get a listing of the fields, with their associated types, etc.
	//

	$field_query = "SELECT a.attnum, a.attname AS field, t.typname as type, a.attlen AS length, a.atttypmod as lengthvar, a.attnotnull as notnull
		FROM pg_class c, pg_attribute a, pg_type t
		WHERE c.relname = '$table'
			AND a.attnum > 0
			AND a.attrelid = c.oid
			AND a.atttypid = t.oid
		ORDER BY a.attnum";
	$result = $db->sql_query($field_query);

	if(!$result)
	{
		message_die(GENERAL_ERROR, "Failed in get_table_def (show fields)", "", __LINE__, __FILE__, $field_query);
	} // end if..

	if ($drop == 1)
	{
		$schema_create .= "DROP TABLE $table;$crlf";
	} // end if

	//
	// Ok now we actually start building the SQL statements to restore the tables
	//

	$schema_create .= "CREATE TABLE $table($crlf";

	while ($row = $db->sql_fetchrow($result))
	{
		//
		// Get the data from the table
		//
		$sql_get_default = "SELECT d.adsrc AS rowdefault
			FROM pg_attrdef d, pg_class c
			WHERE (c.relname = '$table')
				AND (c.oid = d.adrelid)
				AND d.adnum = " . $row['attnum'];
		$def_res = $db->sql_query($sql_get_default);

		if (!$def_res)
		{
			unset($row['rowdefault']);
		}
		else
		{
			$row['rowdefault'] = @pg_result($def_res, 0, 'rowdefault');
		}

		if ($row['type'] == 'bpchar')
		{
			// Internally stored as bpchar, but isn't accepted in a CREATE TABLE statement.
			$row['type'] = 'char';
		}

		$schema_create .= '	' . $row['field'] . ' ' . $row['type'];

		if (eregi('char', $row['type']))
		{
			if ($row['lengthvar'] > 0)
			{
				$schema_create .= '(' . ($row['lengthvar'] -4) . ')';
			}
		}

		if (eregi('numeric', $row['type']))
		{
			$schema_create .= '(';
			$schema_create .= sprintf("%s,%s", (($row['lengthvar'] >> 16) & 0xffff), (($row['lengthvar'] - 4) & 0xffff));
			$schema_create .= ')';
		}

		if (!empty($row['rowdefault']))
		{
			$schema_create .= ' DEFAULT ' . $row['rowdefault'];
		}

		if ($row['notnull'] == 't')
		{
			$schema_create .= ' NOT NULL';
		}

		$schema_create .= ",$crlf";

	}
	//
	// Get the listing of primary keys.
	//

	$sql_pri_keys = "SELECT ic.relname AS index_name, bc.relname AS tab_name, ta.attname AS column_name, i.indisunique AS unique_key, i.indisprimary AS primary_key
		FROM pg_class bc, pg_class ic, pg_index i, pg_attribute ta, pg_attribute ia
		WHERE (bc.oid = i.indrelid)
			AND (ic.oid = i.indexrelid)
			AND (ia.attrelid = i.indexrelid)
			AND	(ta.attrelid = bc.oid)
			AND (bc.relname = '$table')
			AND (ta.attrelid = i.indrelid)
			AND (ta.attnum = i.indkey[ia.attnum-1])
		ORDER BY index_name, tab_name, column_name ";
	$result = $db->sql_query($sql_pri_keys);

	if(!$result)
	{
		message_die(GENERAL_ERROR, "Failed in get_table_def (show fields)", "", __LINE__, __FILE__, $sql_pri_keys);
	}

	while ( $row = $db->sql_fetchrow($result))
	{
		if ($row['primary_key'] == 't')
		{
			if (!empty($primary_key))
			{
				$primary_key .= ', ';
			}

			$primary_key .= $row['column_name'];
			$primary_key_name = $row['index_name'];

		}
		else
		{
			//
			// We have to store this all this info because it is possible to have a multi-column key...
			// we can loop through it again and build the statement
			//
			$index_rows[$row['index_name']]['table'] = $table;
			$index_rows[$row['index_name']]['unique'] = ($row['unique_key'] == 't') ? ' UNIQUE ' : '';
			$index_rows[$row['index_name']]['column_names'] .= $row['column_name'] . ', ';
		}
	}

	if (!empty($index_rows))
	{
		while(list($idx_name, $props) = each($index_rows))
		{
			$props['column_names'] = ereg_replace(", $", "" , $props['column_names']);
			$index_create .= 'CREATE ' . $props['unique'] . " INDEX $idx_name ON $table (" . $props['column_names'] . ");$crlf";
		}
	}

	if (!empty($primary_key))
	{
		$schema_create .= "	CONSTRAINT $primary_key_name PRIMARY KEY ($primary_key),$crlf";
	}

	//
	// Generate constraint clauses for CHECK constraints
	//
	$sql_checks = "SELECT rcname as index_name, rcsrc
		FROM pg_relcheck, pg_class bc
		WHERE rcrelid = bc.oid
			AND bc.relname = '$table'
			AND NOT EXISTS (
				SELECT *
					FROM pg_relcheck as c, pg_inherits as i
					WHERE i.inhrelid = pg_relcheck.rcrelid
						AND c.rcname = pg_relcheck.rcname
						AND c.rcsrc = pg_relcheck.rcsrc
						AND c.rcrelid = i.inhparent
			)";
	$result = $db->sql_query($sql_checks);

	if (!$result)
	{
		message_die(GENERAL_ERROR, "Failed in get_table_def (show fields)", "", __LINE__, __FILE__, $sql_checks);
	}

	//
	// Add the constraints to the sql file.
	//
	while ($row = $db->sql_fetchrow($result))
	{
		$schema_create .= '	CONSTRAINT ' . $row['index_name'] . ' CHECK ' . $row['rcsrc'] . ",$crlf";
	}

	$schema_create = ereg_replace(',' . $crlf . '$', '', $schema_create);
	$index_create = ereg_replace(',' . $crlf . '$', '', $index_create);

	$schema_create .= "$crlf);$crlf";

	if (!empty($index_create))
	{
		$schema_create .= $index_create;
	}

	//
	// Ok now we've built all the sql return it to the calling function.
	//
	return (stripslashes($schema_create));

}

//
// This function returns the "CREATE TABLE" syntax for mysql dbms...
//
function get_table_def_mysql($table, $crlf)
{
	global $drop, $db;

	$schema_create = "";
	$field_query = "SHOW FIELDS FROM $table";
	$key_query = "SHOW KEYS FROM $table";

	//
	// If the user has selected to drop existing tables when doing a restore.
	// Then we add the statement to drop the tables....
	//
	if ($drop == 1)
	{
		$schema_create .= "DROP TABLE IF EXISTS $table;$crlf";
	}

	$schema_create .= "CREATE TABLE $table($crlf";

	//
	// Ok lets grab the fields...
	//
	$result = $db->sql_query($field_query);
	if(!result)
	{
		message_die(GENERAL_ERROR, "Failed in get_table_def (show fields)", "", __LINE__, __FILE__, $field_query);
	}

	while ($row = $db->sql_fetchrow($result))
	{
		$schema_create .= '	' . $row['Field'] . ' ' . $row['Type'];

		if(!empty($row['Default']))
		{
			$schema_create .= ' DEFAULT \'' . $row['Default'] . '\'';
		}

		if($row['Null'] != "YES")
		{
			$schema_create .= ' NOT NULL';
		}

		if($row['Extra'] != "")
		{
			$schema_create .= ' ' . $row['Extra'];
		}

		$schema_create .= ",$crlf";
	}
	//
	// Drop the last ',$crlf' off ;)
	//
	$schema_create = ereg_replace(',' . $crlf . '$', "", $schema_create);

	//
	// Get any Indexed fields from the database...
	//
	$result = $db->sql_query($key_query);

	while($row = $db->sql_fetchrow($result))
	{
		$kname = $row['Key_name'];

		if(($kname != 'PRIMARY') && ($row['Non_unique'] == 0))
		{
			$kname = "UNIQUE|$kname";
		}

		if(!is_array($index[$kname]))
		{
			$index[$kname] = array();
		}

		$index[$kname][] = $row['Column_name'];
	}

	while(list($x, $columns) = @each($index))
	{
		$schema_create .= ", $crlf";

		if($x == 'PRIMARY')
		{
			$schema_create .= '	PRIMARY KEY (' . implode($columns, ', ') . ')';
		}
		elseif (substr($x,0,6) == 'UNIQUE')
		{
			$schema_create .= '	UNIQUE ' . substr($x,7) . ' (' . implode($columns, ', ') . ')';
		}
		else
		{
			$schema_create .= "	KEY $x (" . implode($columns, ', ') . ')';
		}
	}

	$schema_create .= "$crlf);";

	if(get_magic_quotes_runtime())
	{
		return(stripslashes($schema_create));
	}
	else
	{
		return($schema_create);
	}

} // End get_table_def_mysql


//
// This fuction will return a tables create definition to be used as an sql
// statement.
//
//
// The following functions Get the data from the tables and format it as a
// series of INSERT statements, for each different DBMS...
// After every row a custom callback function $handler gets called.
// $handler must accept one parameter ($sql_insert);
//
//
// Here is the function for postgres...
//
function get_table_content_postgresql($table, $handler)
{
	global $db;

	//
	// Grab all of the data from current table.
	//

	$result = $db->sql_query("SELECT * FROM $table");

	if (!$result)
	{
		message_die(GENERAL_ERROR, "Failed in get_table_content (select *)", "", __LINE__, __FILE__, "SELECT * FROM $table");
	}

	$i_num_fields = $db->sql_numfields($result);

	for ($i = 0; $i < $i_num_fields; $i++)
	{
		$aryType[] = $db->sql_fieldtype($i, $result);
		$aryName[] = $db->sql_fieldname($i, $result);
	}

	$iRec = 0;

	while($row = $db->sql_fetchrow($result))
	{
		unset($schema_vals);
		unset($schema_fields);
		unset($schema_insert);
		//
		// Build the SQL statement to recreate the data.
		//
		for($i = 0; $i < $i_num_fields; $i++)
		{
			$strVal = $row[$aryName[$i]];
			if (eregi("char|text|bool", $aryType[$i]))
			{
				$strQuote = "'";
				$strEmpty = "";
				$strVal = addslashes($strVal);
			}
			elseif (eregi("date|timestamp", $aryType[$i]))
			{
				if ($empty($strVal))
				{
					$strQuote = "";
				}
				else
				{
					$strQuote = "'";
				}
			}
			else
			{
				$strQuote = "";
				$strEmpty = "NULL";
			}

			if (empty($strVal) && $strVal != "0")
			{
				$strVal = $strEmpty;
			}

			$schema_vals .= " $strQuote$strVal$strQuote,";
			$schema_fields .= " $aryName[$i],";

		}

		$schema_vals = ereg_replace(",$", "", $schema_vals);
		$schema_vals = ereg_replace("^ ", "", $schema_vals);
		$schema_fields = ereg_replace(",$", "", $schema_fields);
		$schema_fields = ereg_replace("^ ", "", $schema_fields);

		//
		// Take the ordered fields and their associated data and build it
		// into a valid sql statement to recreate that field in the data.
		//
		$schema_insert = "INSERT INTO $table ($schema_fields) VALUES($schema_vals);";

		$handler(trim($schema_insert));
	}

	return(true);

}// end function get_table_content_postgres...

//
// This function is for getting the data from a mysql table.
//

function get_table_content_mysql($table, $handler)
{
	global $db;
	//
	// Grab the data from the table.
	//
	$result = $db->sql_query("SELECT * FROM $table");

	if (!$result)
	{
		message_die(GENERAL_ERROR, "Failed in get_table_content (select *)", "", __LINE__, __FILE__, "SELECT * FROM $table");
	}

	if($db->sql_numrows($result) > 0)
	{
		$schema_insert = "\n#\n# Table Data for $table\n#\n";
	}
	else
	{
		$schema_insert = "";
	}

	$handler($schema_insert);

	//
	// Loop through the resulting rows and build the sql statement.
	//

	while ($row = $db->sql_fetchrow($result))
	{
		$table_list = '(';
		$num_fields = $db->sql_numfields($result);
		//
		// Grab the list of field names.
		//
		for ($j = 0; $j < $num_fields; $j++)
		{
			$table_list .= $db->sql_fieldname($j, $result) . ', ';
		}
		//
		// Get rid of the last comma
		//
		$table_list = ereg_replace(', $', '', $table_list);
		$table_list .= ')';
		//
		// Start building the SQL statement.
		//
		$schema_insert = "INSERT INTO $table $table_list VALUES(";
		//
		// Loop through the rows and fill in data for each column
		//
		for ($j = 0; $j < $num_fields; $j++)
		{
			if(!isset($row[$j]))
			{
				//
				// If there is no data for the column set it to null.
				// There was a problem here with an extra space causing the
				// sql file not to reimport if the last column was null in
				// any table.  Should be fixed now :) JLH
				//
				$schema_insert .= ' NULL,';
			}
			elseif ($row[$j] != '')
			{
				$schema_insert .= ' \'' . addslashes($row[$j]) . '\',';
			}
			else
			{
				$schema_insert .= '\'\',';
			}
		}
		//
		// Get rid of the the last comma.
		//
		$schema_insert = ereg_replace(',$', '', $schema_insert);
		$schema_insert .= ');';
		//
		// Go ahead and send the insert statement to the handler function.
		//
		$handler(trim($schema_insert));

	}
	return(true);
}

function output_table_content($content)
{
	global $tempfile;

	//fwrite($tempfile, $content . "\n");
	//$backup_sql .= $content . "\n";
	echo $content ."\n";
	return;
}
//
// End Functions
// -----------------------------------------------

?>