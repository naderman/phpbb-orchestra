<?
/***************************************************************************
 *                           functions_compress.php
 *                            -------------------
 *   begin                : Saturday, Jul 19, 2003
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id$
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

class compress 
{
	var $fp = 0;

	function add_file($src, $src_prefix = '', $skip_files = '')
	{
		global $phpbb_root_path;

		$skip_files = explode(',', $skip_files);

		if (is_file($phpbb_root_path . $src))
		{
			$src_prefix = ($src_prefix) ? preg_replace('#^(' . preg_quote($src_prefix) . ')#', '', $src) : $src;

			if (!($fp = @fopen($phpbb_root_path . $src, 'rb')))
			{
				return false;
			}

			$data = fread($fp, filesize($phpbb_root_path . $src));
			fclose($fp);

			$this->data($src_prefix, $data, filemtime($phpbb_root_path . $src), false);
		}
		else if (is_dir($phpbb_root_path . $src))
		{
			// Remove prefix from src path 
			$src_prefix = ($src_prefix) ? preg_replace('#^(' . preg_quote($src_prefix) . ')#', '', $src) : $src;

			// Clean up path, remove initial / if present, add ending / if not present
			$src_prefix = (strpos($src_prefix, '/') === 0) ? substr($src_prefix, 1) : $src_prefix;
			$src_prefix = (strrpos($src_prefix, '/') != strlen($src_prefix) - 1) ? (($src_prefix != '') ? $src_prefix . '/' : '') : $src_prefix;

			$filelist = filelist($phpbb_root_path . $src, '', '*');
			ksort($filelist);

			if ($src_prefix)
			{
				$this->data($src_prefix, '', filemtime($src_prefix), true);
			}

			foreach ($filelist as $path => $file_ary)
			{
				if ($path)
				{
					// Same as for src_prefix
					$path = (strpos($path, '/') === 0) ? substr($path, 1) : $path;
					$path = (strrpos($path, '/') != strlen($path) - 1) ? $path . '/' : $path;

					$this->data($src_prefix . $path, '', filemtime($src_prefix . $path), true);
				}

				foreach ($file_ary as $file)
				{
					if (in_array($path . $file, $skip_files))
					{
						continue;
					}

					$this->data($src_prefix . $path . $file, implode('', file($phpbb_root_path . $src . $path . $file)), filemtime($phpbb_root_path . $src . $path . $file), false);
				}
			}

		}
		return true;
	}

	function add_data($src, $name)
	{
		$this->data($name, $src);
		return true;
	}
}

// Zip creation class from phpMyAdmin 2.3.0 � Tobias Ratschiller, Olivier M�ller, Lo�c Chapeaux, Marc Delisle
// http://www.phpmyadmin.net/
//
// Modified extensively by psoTFX, � phpBB Group, 2003
//
// Based on work by Eric Mueller and Denis125
// Official ZIP file format: http://www.pkware.com/appnote.txt
class compress_zip extends compress
{
	var $datasec = array();
	var $ctrl_dir = array();
	var $eof_ctrl_dir = "\x50\x4b\x05\x06\x00\x00\x00\x00";

	var $old_offset = 0;
	var $datasec_len = 0;

	function compress_zip($mode, $file)
	{
		return $this->fp = @fopen($phpbb_root_path . $file, $mode . 'b');
	}

	function unix_to_dos_time($time)
	{
		$timearray = (!$time) ? getdate() : getdate($time);

		if ($timearray['year'] < 1980)
		{
			$timearray['year'] = 1980;
			$timearray['mon'] = $timearray['mday'] = 1;
			$timearray['hours'] = $timearray['minutes'] = $timearray['seconds'] = 0;
		}

		return (($timearray['year'] - 1980) << 25) | ($timearray['mon'] << 21) | ($timearray['mday'] << 16) | ($timearray['hours'] << 11) | ($timearray['minutes'] << 5) | ($timearray['seconds'] >> 1);
	}

	function extract($dst)
	{
		$header = $data = '';

		fseek($this->fp, -14, SEEK_END);
		$tmp = unpack("ventries/vtotentries/Vctsize/Vctpos", fread($this->fp, 12));
		$entries = (int) trim($tmp['entries']);
		$totentries = (int) trim($tmp['totentries']);
		$ctsize = (int) trim($tmp['ctsize']);
		$ctpos = (int) trim($tmp['ctpos']);

		for ($i = 0; $i < $entries; $i++)
		{
			fseek($this->fp, $ctpos);

			$buffer = fread($this->fp, 46);

			$tmp = unpack("Vcrc/Vc_size/Vuc_size/vstrlen", substr($buffer, 16, 14));
			$crc = (int) trim($tmp['crc']);
			$c_size = (int) trim($tmp['c_size']);
			$uc_size = (int) trim($tmp['uc_size']);
			$strlen = (int) trim($tmp['strlen']);

			$tmp = unpack("Vattrib/Voffset", substr($buffer, 38, 8));
			$attrib = (int) trim($tmp['attrib']);
			$offset = (int) trim($tmp['offset']);

			$filename =  fread($this->fp, $strlen);

			$ctpos = ftell($this->fp);

			if ($attrib == 0x41FF0010)
			{
				if (!@mkdir($dst . $filename))
				{
					trigger_error("Could not create directory $filename");
				}
			}
			else
			{
				fseek($this->fp, $offset + 30 + $strlen);

				// We have to fudge here for time being
				if (!($fp = fopen($dst . $filename . '.gz', 'wb')))
				{
					trigger_error("Could not open temporary $filename.gz");
				}

				// .gz header
				fwrite($fp, pack('va1a1Va1a1', 0x8b1f, chr(0x08), chr(0x00), time(), chr(0x00), chr(3)));
				// data ... write it out in 1KB packets to conserve mem
				while ($buffer = fread($this->fp, 1024))
				{
					fwrite($fp, $buffer);
				}
				// .gz footer
				fwrite($fp, pack("VV", $crc, $uc_size));
				fclose($fp);

				if (!($fp = fopen($dst . $filename, 'wb')))
				{
					trigger_error("Could not create $filename");
				}

				if (!($gzfp = gzopen($dst . $filename . '.gz', 'rb')))
				{
					die("Could not open temporary $filename.gz");
				}

				while ($buffer = gzread($gzfp, 1024))
				{
					fwrite($fp, $buffer);
				}
				gzclose($gzfp);
				fclose($fp);
				unlink($dst . $filename . '.gz');
			}
		}
	}

	function close()
	{
		// Write out central file directory and footer
		fwrite($this->fp, $this->file());
		fclose($this->fp);
	}

	function data($name, $data, $mtime = false, $is_dir = false)
	{
		$name = str_replace('\\', '/', $name);

		$dtime = dechex($this->unix_to_dos_time($mtime));
		$hexdtime = '\x' . $dtime[6] . $dtime[7] . '\x' . $dtime[4] . $dtime[5] . '\x' . $dtime[2] . $dtime[3] . '\x' . $dtime[0] . $dtime[1];
		eval('$hexdtime = "' . $hexdtime . '";');

		$unc_len = strlen($data);
		$crc = crc32($data);
		$zdata = gzcompress($data);
		$zdata = substr(substr($zdata, 0, strlen($zdata) - 4), 2); // fix crc bug
		$c_len = strlen($zdata);
		unset($data);

		$fr = "\x50\x4b\x03\x04"; //
		$fr .= "\x14\x00";	// ver needed to extract 4
		$fr .= "\x00\x00";	// gen purpose bit flag 6
		$fr .= "\x08\x00";	// compression method 8
		$fr .= $hexdtime;				// last mod time and date 10
		$fr .= pack('V', $crc);			// crc32 14
		$fr .= pack('V', $c_len);		// compressed filesize 18
		$fr .= pack('V', $unc_len);		// uncompressed filesize 22
		$fr .= pack('v', strlen($name));// length of filename 26
		$fr .= pack('v', 0);			// extra field length 28
		$fr .= $name; // 30
		$fr .= $zdata;
		unset($zdata);
		$fr .= pack('V', $crc);
		$fr .= pack('V', $c_len);	// compressed filesize
		$fr .= pack('V', $unc_len);	// uncompressed filesize

		$this->datasec_len += strlen($fr);

		// Add data to file ... by writing data out incrementally we
		// save some memory
		fwrite($this->fp, $fr);
		unset($fr);

		// Are we a file or a directory? Set archive for file
		$attrib = ($is_dir) ? 0x41FF0010 : 32;

		$cdrec = "\x50\x4b\x01\x02";
		$cdrec .= "\x00\x00";                // version made by
		$cdrec .= "\x14\x00";                // version needed to extract
		$cdrec .= "\x00\x00";                // gen purpose bit flag
		$cdrec .= "\x08\x00";                // compression method
		$cdrec .= $hexdtime;                 // last mod time & date
		$cdrec .= pack('V', $crc);           // crc32
		$cdrec .= pack('V', $c_len);         // compressed filesize
		$cdrec .= pack('V', $unc_len);       // uncompressed filesize
		$cdrec .= pack('v', strlen($name)); // length of filename
		$cdrec .= pack('v', 0);             // extra field length
		$cdrec .= pack('v', 0);             // file comment length
		$cdrec .= pack('v', 0);             // disk number start
		$cdrec .= pack('v', 0);             // internal file attributes
		$cdrec .= pack('V', $attrib);       // external file attributes
		$cdrec .= pack('V', $this->old_offset); // relative offset of local header
		$cdrec .= $name;

		// Save to central directory
		$this->ctrl_dir[] = $cdrec;

		$this->old_offset = $this->datasec_len;
	}

	function file()
	{
		$ctrldir = implode('', $this->ctrl_dir);

		return $ctrldir . $this->eof_ctrl_dir .
			pack('v', sizeof($this->ctrl_dir)) .	// total # of entries "on this disk"
			pack('v', sizeof($this->ctrl_dir)) .	// total # of entries overall
			pack('V', strlen($ctrldir)) .			// size of central dir
			pack('V', $this->datasec_len) .			// offset to start of central dir
			"\x00\x00";								// .zip file comment length
	}
}

// Tar/tar.gz compression routine
// Header/checksum creation derived from tarfile.pl, � Tom Horsley, 1994
class compress_tar extends compress 
{
	var $fzopen = '';
	var $fzclose = '';
	var $fzread = '';
	var $fzwrite = '';
	var $isgz = false;
	var $isbz = false;

	function compress_tar($mode, $file)
	{
		$this->isgz = (strpos($file, '.tar.gz') !== false || strpos($file, '.tgz') !== false) ? true : false;
		$this->isbz = (strpos($file, '.tar.bz2') !== false) ? true : false;

		$fzopen = ($this->isbz && function_exists('bzopen')) ? 'bzopen' : (($this->isgz && extension_loaded('zlib')) ? 'gzopen' : 'fopen');
		return $this->fp = @$fzopen($phpbb_root_path . $file, $mode . 'b');
	}

	function extract($dst)
	{
		$fzread = ($this->isbz && function_exists('bzread')) ? 'bzread' : (($this->isgz && extension_loaded('zlib')) ? 'gzread' : 'fread');

		$header = $data = '';
		$size = 0;
		while ($buffer = $fzread($this->fp, 512))
		{
			$tmp = unpack("A6magic", substr($buffer, 257, 6));

			if (trim($tmp['magic']) == 'ustar')
			{
				$tmp = unpack("A100name", $buffer);
				$filename = trim($tmp['name']);

				$tmp = unpack("Atype", substr($buffer, 156, 1));
				$filetype = (int) trim($tmp['type']);

				if ($filetype == 5)
				{
					if (!@mkdir($dst . $filename))
					{
						trigger_error("Could not create directory $filename");
					}
					continue;
				}
				else
				{
					$tmp = unpack("A12size", substr($buffer, 124, 12));
					$filesize = octdec((int) trim($tmp['size']));
	
					if (!($fp = fopen($dst . $filename, 'wb')))
					{
						trigger_error("Could create file $filename");
					}

					$size = 0;
					continue;
				}
			}

			$size += 512;
			$length = ($size > $filesize) ? 512 - ($size - $filesize) : 512;

			$tmp = unpack("A512data", $buffer);

			fwrite($fp, (string) $tmp['data'], $length);
			unset($buffer);
		}
	}

	function close()
	{
		$fzclose = ($this->isbz && function_exists('bzclose')) ? 'bzclose' : (($this->isgz && extension_loaded('zlib')) ? 'gzclose' : 'fclose');
		$fzclose($this->fp);
	}

	function data($name, $data, $mtime = false, $is_dir = false)
	{
		$fzwrite = 	($this->isbz && function_exists('bzwrite')) ? 'bzwrite' : (($this->isgz && extension_loaded('zlib')) ? 'gzwrite' : 'fwrite');

		$mode = ($is_dir) ? '493' : '436';
		$mtime = (!$mtime) ? time() : $mtime;
		$filesize = ($is_dir) ? 0 : strlen($data);
		$typeflag = ($is_dir) ? '5' : '';

		$header = '';
		$header .= pack("a100", $name);
		$header .= pack("a8", sprintf("%07o", $mode));
		$header .= pack("a8", sprintf("%07o", 0));
		$header .= pack("a8", sprintf("%07o", 0));
		$header .= pack("a12", sprintf("%011o", $filesize));
		$header .= pack("a12", sprintf("%011o", $mtime));
		$header .= '        ';
		$header .= pack("a", $typeflag);
		$header .= pack("a100", '');
		$header .= 'ustar';
		$header .= pack("x");
		$header .= '00';
		$header .= pack("x247");

		// Checksum
		for ($i = 0; $i < 512; $i++)
		{
			$b = unpack("c1char", substr($header, $i, 1));
			$checksum += $b['char'];
		}
		$header = substr_replace($header, pack("a8",sprintf("%07o", $checksum)), 148, 8);

		$fzwrite($this->fp, $header);

		$i = 0;
		// Read the data 512 bytes at a time and write it out
		while ($buffer = substr($data, $i, 512))
		{
			$fzwrite($this->fp, pack("a512", $buffer));
			$i += 512;
		}
		unset($data);
	}
}

?>