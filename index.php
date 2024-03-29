<?php 

/*
 *	Knoxious Open Pastebin		 v 1.6.0
 * ============================================================================
 *	
 *	Copyright (c) 2009-2010 Xan Manning (http://xan-manning.co.uk/)
 *
 * 	Released under the terms of the MIT License.
 * 	See the MIT for details (http://opensource.org/licenses/mit-license.php).
 *
 *
 *	A quick to set up, rapid install, two-file pastebin! 
 *	(or at least can be)
 *
 *	Supports text, image hosting and url linking.
 *
 *	URL: 		http://xan-manning.co.uk/
 *	EXAMPLE: 	http://pzt.me/
 *
 */

define('ISINCLUDED', 1);

if(!file_exists("config.php"))
	die("Please create config.php");

require("config.php");

if((@$_SERVER['HTTPS']) == "on")
	$CONFIG['pb_protocol'] = "https";
else
	$CONFIG['pb_protocol'] = "http";

/* Start Pastebin */
if(substr(phpversion(), 0, 3) < 5.2)
	die('PHP 5.2 is required to run this pastebin! This version is ' 
		. phpversion() . '. Please contact your host!');

if($CONFIG['pb_encrypt_pastes'] == TRUE && !function_exists('mcrypt_encrypt'))
	$CONFIG['pb_encrypt_pastes'] = FALSE;

if(@$_POST['encryption'])
	$_POST['encryption'] = md5(preg_replace("/[^a-zA-Z0-9\s]/", "i0", 
		$_POST['encryption']));

if(@$_POST['decrypt_phrase']) 
{
	$_temp_decrypt_phrase = $_POST['decrypt_phrase'];
	$_POST['decrypt_phrase'] = md5(preg_replace("/[^a-zA-Z0-9\s]/", "i0", 
		$_POST['decrypt_phrase']));
}

if($CONFIG['pb_gzip'])
	ob_start("ob_gzhandler");

if($CONFIG['pb_infinity'])
	$infinity = array('0');


if($CONFIG['pb_infinity'] && $CONFIG['pb_infinity_default'])
	$CONFIG['pb_lifespan'] = array_merge((array)$infinity, 
		(array)$CONFIG['pb_lifespan']);
elseif($CONFIG['pb_infinity'] && !$CONFIG['pb_infinity_default'])
	$CONFIG['pb_lifespan'] = array_merge((array)$CONFIG['pb_lifespan'], 
		(array)$infinity);


// get_magic_quotes_gpc() Removed in PHP 8.0, deprecated since 7.4
if(version_compare(PHP_VERSION, '8.0.0', '<') === true && function_exists(get_magic_quotes_gpc()) === true)
{
	function callback_stripslashes(&$val, $name) 
	{
		if(get_magic_quotes_gpc() === true) 
 			$val = stripslashes($val);
	}

	if(count($_GET))
		array_walk($_GET, 'callback_stripslashes');

	if(count($_POST))
 		array_walk($_POST, 'callback_stripslashes');

 	if(count($_COOKIE))
 		array_walk($_COOKIE, 'callback_stripslashes');

}


class db
{
	public function __construct($config)
	{
		$this->config = $config;
		$this->dbt = NULL;

		switch($this->config['db_type'])
		{
			case "flatfile":
				$this->dbt = "txt";
			break;
			case "mysql":
				$this->dbt = "mysql";
			break;
			default:
				$this->dbt = "txt";
			break;
		}
	}

	public function serializer($data)
	{
		$serialize = serialize($data);
		$output = $serialize;

		return $output;
	}
			
	public function deserializer($data)
	{
		$unserialize = unserialize($data);
		$output = $unserialize;

		return $output;
	}
			
	public function read($file)
	{
		$open = fopen($file, "r");
		$data = fread($open, filesize($file) + 1024);
		fclose($open);

		return $data;
	}
			
	public function append($data, $file)
	{
		$open = fopen($file, "a");
		$write = fwrite($open, $data);
		fclose($open);
				
		return $write;
	}
			
	public function write($data, $file)
	{
		$open = fopen($file, "w");
		$write = fwrite($open, $data);
		fclose($open);
				
		return $write;
	}

	public function array_remove(array &$a_Input, $m_SearchValue, 
		$b_Strict = False)
	{
    	$a_Keys = array_keys($a_Input, $m_SearchValue, $b_Strict);

    	foreach($a_Keys as $s_Key)
			unset($a_Input[$s_Key]);

		return $a_Input;
	}

	public function setDataPath($filename = FALSE, $justPath = FALSE, 
		$forceImage = FALSE)
	{
		if(!$filename && !$forceImage)
			return $this->config['txt_config']['db_folder'];
				
		if(!$filename && $forceImage)
			return $this->config['txt_config']['db_folder'] . "/" 
				. $this->config['txt_config']['db_images'];

		$filename = str_replace("!", "", $filename);

		$this->config['max_folder_depth'] = (int)$this->config['max_folder_depth'];

		if($this->config['max_folder_depth'] < 1 
			|| !is_numeric($this->config['max_folder_depth']))
			$this->config['max_folder_depth'] = 1;

		$info = pathinfo($filename);

                if(empty($info['extension'])) {
                        $info['extension'] = 0;
                }

		if(!in_array(strtolower($info['extension']), 
			$this->config['pb_image_extensions']))
		{
			$path = $this->config['txt_config']['db_folder'] . "/" 
				. substr($filename, 0, 1);

			if(!file_exists($path) 
				&& is_writable($this->config['txt_config']['db_folder']))
			{
				mkdir($path);
				chmod($path, $this->config['txt_config']['dir_mode']);
				$this->write("FORBIDDEN", $path . "/index.html");
				chmod($path . "/index.html", 
					$this->config['txt_config']['file_mode']);
			}

			for ($i = 1; $i <= $this->config['max_folder_depth'] - 1; $i++) 
			{
				$parent = $path;
						   
				if(strlen($filename) > $i)
					$path .= "/" . substr($filename, $i, 1);

				if(!file_exists($path) && is_writable($parent))
				{
					mkdir($path);
					chmod($path, $this->config['txt_config']['dir_mode']);
					$this->write("FORBIDDEN", $path . "/index.html");
					chmod($path . "/index.html", 
						$this->config['txt_config']['file_mode']);
				}

			}


		} else {
			$path = $this->config['txt_config']['db_folder'] . "/" 
				. $this->config['txt_config']['db_images'] . "/" 
				. substr($info['filename'], 0, 1);
							
			if(!file_exists($path) 
				&& is_writable($this->config['txt_config']['db_folder'] . "/" 
				. $this->config['txt_config']['db_images']))
			{
				mkdir($path);
				chmod($path, $this->config['txt_config']['dir_mode']);
				$this->write("FORBIDDEN", $path . "/index.html");
				chmod($path . "/index.html", 
					$this->config['txt_config']['file_mode']);
			}

			for($i = 1; $i <= $this->config['max_folder_depth'] - 1; $i++) 
			{
				$parent = $path;
							   
				if(strlen($info['filename']) > $i)
					$path .= "/" . substr($info['filename'], $i, 1);

				if(!file_exists($path) && is_writable($parent))
				{
					mkdir($path);
					chmod($path, $this->config['txt_config']['dir_mode']);
					$this->write("FORBIDDEN", $path . "/index.html");
					chmod($path . "/index.html", 
						$this->config['txt_config']['file_mode']);
				}
			}
		}

		if($justPath)
			return $path;
		else
			return $path . "/" . $filename;
	}

	public function connect()
	{
		switch($this->dbt)
		{
			case "mysql":
				$this->link = mysql_connect(
					$this->config['mysql_connection_config']['db_host'], 
					$this->config['mysql_connection_config']['db_uname'], 
					$this->config['mysql_connection_config']['db_pass']);
				$result = mysql_select_db(
					$this->config['mysql_connection_config']['db_name'], 
					$this->link);

				if($this->link == FALSE || $result == FALSE)
					$output = FALSE;
				else
					$output = TRUE;
			break;
			case "txt":
				if(!is_writeable($this->setDataPath() . "/" 
					. $this->config['txt_config']['db_index']) 
					|| !is_writeable($this->setDataPath()))
					$output = FALSE;
				else
					$output = TRUE;
			break;
		}

		return $output;
	}

	public function disconnect()
	{
		switch($this->dbt)
		{
			case "mysql":
				mysql_close();
				$output = TRUE;
			break;
			case "txt":
				$output = TRUE;
			break;
		}

		return $output;
	}

	public function readPaste($id)
	{
		switch($this->dbt)
		{
			case "mysql":
				$this->connect();
				$query = "SELECT * FROM " 
					. $this->config['mysql_connection_config']['db_table'] 
					. " WHERE ID = '" . $id . "'";
				$result = array();
				$result_temp = mysql_query($query);

				if(!$result_temp || mysql_num_rows($result_temp) < 1)
					return false;

				while($row = mysql_fetch_assoc($result_temp))
					$result[] = $row;

				mysql_free_result($result_temp);
			break;
			case "txt":
				$result = array();

				if(!file_exists($this->setDataPath($id)))
				{
					$index = $this->deserializer($this->read(
						$this->setDataPath() . "/" 
						. $this->config['txt_config']['db_index']));

					if(in_array($id, $index))
						$this->dropPaste($id, TRUE);

					return false;
				}

				$result = $this->deserializer(
					$this->read($this->setDataPath($id)));

			break;
		}

		if(count($result) < 1)
			$result = FALSE;

		return $result;
	}

	public function dropPaste($id, $ignoreImage = FALSE)
	{
		$id = (string)$id;

		if(!$ignoreImage)
		{
			$imgTemp = $this->readPaste($id);

			if($this->dbt == "mysql")
				$imgTemp = $imgTemp[0];

			if($imgTemp['Image'] != NULL 
				&& file_exists($this->setDataPath($imgTemp['Image'])))
				unlink($this->setDataPath($imgTemp['Image']));
		}

		switch($this->dbt)
		{
			case "mysql":
				$this->connect();
				$query = "DELETE FROM " 
					. $this->config['mysql_connection_config']['db_table'] 
					. " WHERE ID = '" . $id . "'";
				$result = mysql_query($query);
			break;
			case "txt":
				if(file_exists($this->setDataPath($id)))
					$result = unlink($this->setDataPath($id));

				$index = $this->deserializer($this->read($this->setDataPath() 
					. "/" . $this->config['txt_config']['db_index']));

				if(in_array($id, $index))
					$key = array_keys($index, $id);	
				elseif(in_array("!" . $id, $index))
					$key = array_keys($index, "!" . $id);

				$key = $key[0];

				if(isset($index[$key]))	
					unset($index[$key]);

				$index = array_values($index);
				$result = $this->write($this->serializer($index), 
					$this->setDataPath() . "/" 
					. $this->config['txt_config']['db_index']);
			break;
		}

		return $result;
	}
		
	public function cleanHTML($input)
	{
                if(empty($input)) {
                $input = '';
                }
		if($this->dbt == "mysql")
			$output = addslashes(str_replace('\\', '\\\\', $input));
		else
			$output = addslashes($input);

		return $output;
	}

	public function lessHTML($input)
	{
		$output = htmlspecialchars($input);

		return $output;
	}

	public function dirtyHTML($input)
	{
		$output = htmlspecialchars(stripslashes($input));

		return $output;
	}

	public function rawHTML($input)
	{
		if($this->dbt == "mysql")
			$output = stripslashes($input);
		else 
			$output = stripslashes(stripslashes($input));

		return $output;
	}

	public function uploadFile($file, $rename = FALSE)
	{
		$info = pathinfo($file['name']);

		if(!$this->config['pb_images'])
			return false;

		if($rename)
			$path = $this->setDataPath($rename . "."
				. strtolower($info['extension'] ?? ''));
		else
			$path = $path = $this->setDataPath($file['name']);

		if(!in_array(strtolower($info['extension'] ?? ''),
			$this->config['pb_image_extensions']))
			return false;

		if($file['size'] > $this->config['pb_image_maxsize'])
			return false;

		if(!move_uploaded_file($file['tmp_name'], $path))
			return false;

		chmod($path, $this->config['txt_config']['dir_mode']);

		if(!$rename)
			$filename = $file['name'];
		else
			$filename = $rename . "." . strtolower($info['extension'] ?? '');

		return $filename;
	}

	public function downTheImg($img, $rename)
	{
		$info = pathinfo($img);

		if(!in_array(strtolower($info['extension'] ?? ''),
			$this->config['pb_image_extensions']))
			return false;

		if(!$this->config['pb_images'] || !$this->config['pb_download_images'])
			return false;

		if(substr($img, 0, 4) == 'http')
		{
			$x = array_change_key_case(get_headers($img, 1), CASE_LOWER);

			if(strcasecmp($x[0], 'HTTP/1.1 200 OK') != 0)
				$x = $x['content-length'][1];
			else
				$x = $x['content-length'];
		} else
			$x = @filesize($img);

               	$size = $x;

		if($size > $this->config['pb_image_maxsize'])
			return false;

		$data = file_get_contents($img);

		$path = $this->setDataPath($rename . "." 
			. strtolower($info['extension']));
		
		$fopen = fopen($path, "w+");
		fwrite($fopen, $data);
		fclose($fopen);

		chmod($path, $this->config['txt_config']['dir_mode']);

		$filename = $rename . "." . strtolower($info['extension']);

		return $filename;
	}

	public function insertPaste($id, $data, $arbLifespan = FALSE)
	{
		if($arbLifespan && $data['Lifespan'] > 0)
			$data['Lifespan'] = time() + $data['Lifespan'];
		elseif($arbLifespan && $data['Lifespan'] == 0)
			$data['Lifespan'] = 0;
		else {
			if((($this->config['pb_lifespan'][$data['Lifespan']] == FALSE 
				|| $this->config['pb_lifespan'][$data['Lifespan']] == 0) 
				&& $this->config['pb_infinity']) 
				|| !$this->config['pb_lifespan'])
				$data['Lifespan'] = 0;
			else
				$data['Lifespan'] = time() 
					+ ($this->config['pb_lifespan'][$data['Lifespan']] 
					* 60 * 60 * 24);
		}


		$paste = array('ID'	=> $id,
			'Subdomain' => $data['Subdomain'],
			'Datetime' => time() + ($data['Time_offset'] ?? 0),
			'Author' => $data['Author'],
			'Protection' => $data['Protect'],
			'Encrypted' => $data['Encrypted'],
			'Syntax'  => $data['Syntax'],
			'Parent' => $data['Parent'],
			'Image' => $data['Image'],
			'ImageTxt' => $this->cleanHTML($data['ImageTxt']),
			'URL' => $data['URL'],
			'Lifespan' => $data['Lifespan'],
			'IP' =>	base64_encode($data['IP']),
			'Data' => $this->cleanHTML($data['Content']),
			'GeSHI' => $this->cleanHTML($data['GeSHI']),
			'Style' => $this->cleanHTML($data['Style'])
		);

		if(($paste['Protection'] > 0  && $this->config['pb_private']) 
			|| ($paste['Protection'] > 0 && $arbLifespan))
			$id = "!" . $id;
		else
			$paste['Protection'] = 0;
			
		switch($this->dbt)
		{
			case "mysql":
				$this->connect();
				$query = "INSERT INTO " 
					. $this->config['mysql_connection_config']['db_table'] 
					. " (ID, Subdomain, Datetime, Author, Protection,"
					. " Encrypted, Syntax, Parent, Image, ImageTxt, URL,"
					. " Lifespan, IP, Data, GeSHI, Style) VALUES ('" 
					. $paste['ID'] . "', '" . $paste['Subdomain'] . "', '" 
					. $paste['Datetime'] . "', '" . $paste['Author'] . "', " 
					. (int)$paste['Protection'] . ", '" . $paste['Encrypted'] 
					. "', '" . $paste['Syntax'] . "', '" . $paste['Parent'] 
					. "', '" . $paste['Image'] . "', '" . $paste['ImageTxt'] 
					. "', '" . $paste['URL'] . "', '" 
					. (int)$paste['Lifespan'] . "', '" . $paste['IP'] 
					. "', '" . $paste['Data'] . "', '" . $paste['GeSHI'] 
					. "', '" . $paste['Style'] . "')";
				$result = mysql_query($query);
			break;
			case "txt":
				$index = $this->deserializer($this->read($this->setDataPath() 
					. "/" . $this->config['txt_config']['db_index']));
				$index[] = $id;
				$this->write($this->serializer($index), $this->setDataPath() 
					. "/" . $this->config['txt_config']['db_index']);
				$result = $this->write($this->serializer($paste), 
					$this->setDataPath($paste['ID']));
				chmod($this->setDataPath($paste['ID']), 
					$this->config['txt_config']['file_mode']);
			break;
		}

		return $result;
	}

	public function checkID($id)
	{
		switch($this->dbt)
		{
			case "mysql":
				$this->connect();							
				$query = "SELECT * FROM " 
					. $this->config['mysql_connection_config']['db_table'] 
					. " WHERE ID = '" . $id . "'";
				$result = mysql_query($query);
				$result = mysql_num_rows($result);

				if($result > 0)
					$output = TRUE;
				else
					$output = FALSE;
			break;
			case "txt":
				$index = $this->deserializer($this->read($this->setDataPath() 
					. "/" . $this->config['txt_config']['db_index']));

				if(in_array($id, $index) || in_array("!" . $id, $index))
					$output = TRUE;
				else
					$output = FALSE;
			break;
		}

		return $output;
	}

	public function getLastID()
	{
		if(!is_int($this->config['pb_id_length']))
			$this->config['pb_id_length'] = 1;
		if($this->config['pb_id_length'] > 32)
			$this->config['pb_id_length'] = 32;

		switch($this->dbt)
		{
			case "mysql":
				$this->connect();							
				$query = "SELECT * FROM " 
					. $this->config['mysql_connection_config']['db_table'] 
					. " WHERE ID <> 'subdomain' && ID <> 'forbidden'"
					. " ORDER BY Datetime DESC LIMIT 1";
				$result = mysql_query($query);
				$output = $this->config['pb_id_length'];

				while($assoc = mysql_fetch_assoc($result))
				{
					if(strlen($assoc['ID']) >= 1)
						$output = strlen($assoc['ID']);
					else
						$output = $this->config['pb_id_length'];
				}

				if($output < 1)
					$output = $this->config['pb_id_length'];

				mysql_free_result($result);

			break;
			case "txt":
				$index = $this->deserializer($this->read($this->setDataPath() 
					. "/" . $this->config['txt_config']['db_index']));
				$index = array_reverse($index);
				$output = strlen(str_replace("!",'', $index[0]));

				if($output < 1)
					$output = $this->config['pb_id_length'];
			break;
		}

		return $output;
	}


}

class bin
{
	public function __construct($db)
	{
		$this->db = $db;
	}
		
	public function setTitle($config)
	{
		if(!$config)
			$title = "Pastebin on " . $_SERVER['SERVER_NAME'];
		else
			$title = htmlspecialchars($config, ENT_COMPAT, 'UTF-8', FALSE);

		return $title;
	}

	public function setTagline($config)
	{
		if(!$config)
			$output = "<!-- TAGLINE OMITTED -->";
		else
			$output = "<div id=\"tagline\">" . $config . "</div>";

		return $output;
	}

	public function titleID($requri = FALSE)
	{
		if(!$requri)
			$id = "Welcome!";
		else
			$id = $requri;

		return $id;
	}

	public function robotPrivacy($requri = FALSE)
	{
		if(!$requri)
			return "index,follow";

		$requri = str_replace("!", "", $requri);
		
		if($privacy = $this->db->readPaste($requri))
		{
			if($this->db->dbt == "mysql")
				$privacy = $privacy[0];

			switch((int)$privacy['Protection'])
			{
				case 0:
					if($privacy['URL'] != "")
						$robot = "index,nofollow";
					else
						$robot = "index,follow";

					if($privacy['Encrypted'] != NULL)
							$robot = "noindex,nofollow";

				break;
				case 1:
					if($privacy['URL'] != "")
						$robot = "noindex,nofollow";
					else
						$robot = "noindex,follow";
				break;
				default:
					$robot = "index,follow";
				break;
			}
		}

                if(empty($robot)) {
                        $robot = '';
                }

		return $robot;
	}

	public function thisDir()
	{
		$output = dirname($_SERVER['SCRIPT_FILENAME']);

		return $output;
	}

	public function generateID($id = FALSE, $iterations = 0)
	{
		$checkArray = array('install', 'api', 'defaults', 'recent', 'raw', 
			'moo', 'download', 'pastes', 'subdomain', 'forbidden');

		if($iterations > 0 && $iterations < 4 && $id != FALSE)
			$id = $this->generateRandomString($this->db->getLastID());
		elseif($iterations > 3 && $id != FALSE)
			$id = $this->generateRandomString($this->db->getLastID() + 1);
		else
			$id = $id;

		if(!$id)
			$id = $this->generateRandomString($this->db->getLastID());

		if($id == $this->db->config['txt_config']['db_index'] 
			|| in_array($id, $checkArray))
			$id = $this->generateRandomString($this->db->getLastID());

		if($this->db->config['pb_rewrite'] && (is_dir($id) 
			|| file_exists($id)))
			$id = $this->generateID($id, $iterations + 1);	

		if(!$this->db->checkID($id) && !in_array($id, $checkArray))
			return $id;
		else
			return $this->generateID($id, $iterations + 1);			
	}

	public function checkAuthor($author = FALSE)
	{
		if($author == FALSE)
			return $this->db->config['pb_author'];

		if(preg_match('/^\s/', $author) || preg_match('/\s$/', $author) 
			|| preg_match('/^\s$/', $author))
			return $this->db->config['pb_author'];
		else
			return addslashes($this->db->lessHTML($author));
	}

	public function checkSubdomain($subdomain)
	{
		if($subdomain == FALSE)
			return FALSE;

		if(preg_match('/^\s/', $subdomain) || preg_match('/\s$/', $subdomain) 
			|| preg_match('/^\s$/', $subdomain))
			return FALSE;
		elseif(ctype_alnum($subdomain))
			return $subdomain;
		else
			return preg_replace("/[^A-Za-z0-9]/i", "", $subdomain);
	}


	public function getLastPosts($amount, $user = NULL)
	{
		switch($this->db->dbt)
		{
			case "mysql":
				$this->db->connect();
				$result = array();
				if($this->db->config['subdomain'])
					$whereSubdomain = " AND Subdomain='" 
						. $this->db->config['subdomain']  . "'";
				else
					$whereSubdomain = " AND Subdomain=''";

				if($user)
					$whereUser = " AND Author='" . $user . "'";
				else
					$whereUser = NULL;

				$query = "SELECT * FROM " 
					. $this->db->config['mysql_connection_config']['db_table'] 
					. " WHERE Protection < 1" . $whereSubdomain . $whereUser 
					. " ORDER BY Datetime DESC LIMIT " . $amount;

				$result_temp = mysql_query($query);

				if(!$result_temp || mysql_num_rows($result_temp) < 1)
					return NULL;
							
				while ($row = mysql_fetch_assoc($result_temp))
					 $result[] = $row;

				mysql_free_result($result_temp);
			break;
			case "txt":
				$index = $this->db->deserializer($this->db->read(
					$this->db->setDataPath() . "/" 
					. $this->db->config['txt_config']['db_index']));
				$index = array_reverse($index);
				$int = 0;
				$result = array();

				if(count($index) > 0)
				{ 
					foreach($index as $row)
					{ 
						if($int < $amount && substr($row, 0, 1) != "!") 
						{ 
							$result[$int] = $this->db->readPaste($row); 
							$int++; 
						} elseif($int <= $amount && substr($row, 0, 1) == "!") 
						{ 
							$int = $int; 
						} else { 
							return $result; 
						} 
					} 
				}
			break;
		}
		return $result;
	}

	public function styleSheet()
	{
		if($this->db->config['pb_style'] == FALSE)
			return false;

		if(preg_match("/^(http|https|ftp):\/\/(.*?)/", 
			$this->db->config['pb_style']))
		{
			$headers = @get_headers($this->db->config['pb_style']);

			if (preg_match("|200|", $headers[0]))
				return true;
			else
				return false;
		} else {
			if(file_exists($this->db->config['pb_style']))
				return true;
			else
				return false;
		}
	}

	public function jQuery()
	{
		if($this->db->config['pb_jQuery'] == FALSE)
			return false;

		if(preg_match("/^(http|https|ftp):\/\/(.*?)/", 
			$this->db->config['pb_jQuery']))
		{
			$headers = @get_headers($this->db->config['pb_jQuery']);

			if (preg_match("|200|", $headers[0]))
				return true;
			else
				return false;
		} else {
			if(file_exists($this->db->config['pb_jQuery']))
				return true;
			else
				return false;
		}
	}

	public function highlight()
	{
		if($this->db->config['pb_syntax'] == FALSE)
			return false;

		if(file_exists($this->db->config['pb_syntax']))
			return true;
		else
			return false;
	}

	public function adaptor()
	{
		if($this->db->config['pb_api_adaptor'] == FALSE)
			return false;

		if(file_exists($this->db->config['pb_api_adaptor']))
			return true;
		else
			return false;
	}

	public function highlightPath()
	{
		if($this->highlight())
			return dirname($this->db->config['pb_syntax']) . "/";
		else
			return false;
	}

	public function lineHighlight()
	{
		if($this->db->config['pb_line_highlight'] == FALSE 
			|| strlen($this->db->config['pb_line_highlight']) < 1)
			return false;

		if(strlen($this->db->config['pb_line_highlight']) > 6)
			return substr($this->db->config['pb_line_highlight'], 0, 6);

		if(strlen($this->db->config['pb_line_highlight']) == 1)
			return $this->db->config['pb_line_highlight'] 
				. $this->db->config['pb_line_highlight'];

		return $this->db->config['pb_line_highlight'];
	}

	public function filterHighlight($line)
	{
		if($this->lineHighlight() == FALSE)
			return $line;

		$len = strlen($this->lineHighlight());
				
		if(substr($line, 0, $len) == $this->lineHighlight())
			$line = "<span class=\"lineHighlight\">" . substr($line, $len) 
				. "</span>";

		return $line;
	}

	public function noHighlight($data)
	{
		if($this->lineHighlight() == FALSE)
			return $data;

		$output = array();

		$lines = explode("\n", $data);

		foreach($lines as $line)
		{
			$len = strlen($this->lineHighlight());
			
			if(substr($line, 0, $len) == $this->lineHighlight())
				$output[] = substr($line, $len);
			else
				$output[] = $line;
		}

		$output = implode("\n", $output);

		return $output;
	}

	public function highlightNumbers($data)
	{
		if($this->lineHighlight() == FALSE)
			return false;

		$output = array();

		$n = 0;

		$lines = explode("\n", $data);

		foreach($lines as $line)
		{
			$n++;
			$len = strlen($this->lineHighlight());
				
			if(substr($line, 0, $len) == $this->lineHighlight())
				$output[] = $n;
		}


		return $output;
				
	}

	public function _clipboard()
	{
		if($this->db->config['pb_clipboard'] == FALSE)
			return false;

		$this->db->config['cbdir'] = dirname(
			$this->db->config['pb_clipboard']);
		$cbdir = $this->db->config['cbdir'];

		if(strlen($cbdir) < 2)
			$cbdir = ".";

		if(preg_match("/^(http|https|ftp):\/\/(.*?)/", 
			$this->db->config['pb_clipboard']))
		{
			$headers = @get_headers($this->db->config['pb_clipboard']);
			if (preg_match("|200|", $headers[0])) 
			{
				$jsHeaders = @get_headers($cbdir . "/swfobject.js");
				if(preg_match("|200|", $jsHeaders[0]))
					return true;
				else
					return false; 
			}
			else
				return false;
		} else {
			if(file_exists($this->db->config['pb_clipboard']) 
				&& file_exists($cbdir . "/swfobject.js"))
				return true;
			else
				return false;
		}
				

	}

	public function generateRandomString($length)
	{
		$checkArray = array('install', 'api', 'defaults', 'recent', 'raw', 
			'moo', 'download', 'pastes', 'subdomain', 'forbidden', 0);

		$characters = "0123456789abcdefghijklmnopqrstuvwxyz";  

		if($this->db->config['pb_hexlike_id'])
			$characters = "0123456789abcdefabcdef";

		$output = "";
		for ($p = 0; $p < $length; $p++) 
			$output .= $characters[mt_rand(0, strlen($characters) - 1)];
					
		if(is_bool($output) || $output == NULL || strlen($output) < $length 
			|| in_array($output, $checkArray))
			return $this->generateRandomString($length);
		else
    		return (string)$output;
	}

	public function cleanUp($amount)
	{
		if(!$this->db->config['pb_autoclean'])
			return false;

		if(!file_exists('INSTALL_LOCK'))
			return false;
	
		switch($this->db->dbt)
		{
			case "mysql":
				$this->db->connect();
				$result = array();
				$query = "SELECT * FROM " 
					. $this->db->config['mysql_connection_config']['db_table'] 
					. " WHERE Lifespan <= " . time() 
					. " AND Lifespan > 0 ORDER BY Datetime ASC LIMIT " 
					. $amount;
				$result_temp = mysql_query($query);

				while ($row = mysql_fetch_assoc($result_temp))
					 $result[] = $row;

				mysql_free_result($result_temp);
			break;
			case "txt":
				$index = $this->db->deserializer($this->db->read(
					$this->db->setDataPath() . "/" 
					. $this->db->config['txt_config']['db_index']));

				if(is_array($index) && count($index) > $amount + 1)
					shuffle($index);

				$int = 0;
				$result = array();

				if(count($index) > 0)
				{ 
					foreach($index as $row)
					{ 
						if($int < $amount) 
						{ 
							$result[] = $this->db->readPaste(
								str_replace("!", NULL, $row)); 
						} else { 
							break; 
						} 
						
						$int++;	
					} 
				}
			break;
		}

		foreach($result as $paste)
		{
			if($paste['Lifespan'] == 0)
				$paste['Lifespan'] = time() + time();

			if(gmdate('U') > $paste['Lifespan'])
				$this->db->dropPaste($paste['ID']);
		}

		return $result;
	}

	public function linker($id = FALSE)
	{
		$dir = dirname($_SERVER['SCRIPT_NAME']);

		if(strlen($dir) > 1)
			$now = $this->db->config['pb_protocol'] . "://" 
				. $_SERVER['SERVER_NAME'] . $dir;
		else
			$now = $this->db->config['pb_protocol'] . "://" 
				. $_SERVER['SERVER_NAME'];

		$file = basename($_SERVER['SCRIPT_NAME']);
				
		switch($this->db->config['pb_rewrite'])
		{
			case TRUE:
				if($id == FALSE)
					$output = $now . "/";
				else
					$output = $now . "/" . $id;
			break;
			case FALSE:
				if($id == FALSE)
					$output = $now . "/";
				else
					$output = $now . "/" . $file . "?" . $id;
			break;
		}

		return $output;
	}

	public function setSubdomain($force = FALSE)
	{
		if(!$this->db->config['pb_subdomains'])
			return NULL;

		if($force)
			return $this->db->config['txt_config']['db_folder'] = $this->db->config['txt_config']['db_folder'] 
				. "/subdomain/" . $force;

		if(!file_exists('INSTALL_LOCK'))
			return NULL;

		$domain = strtolower(str_replace("www.", "", $_SERVER['SERVER_NAME']));
		$explode = explode(".", $domain, 2);
		$sub = $explode[0];

		switch($this->db->dbt)
		{
			case "mysql":
				$this->db->connect();
				$subdomain_list = array();
				$query = "SELECT * FROM " 
					. $this->db->config['mysql_connection_config']['db_table'] 
					. " WHERE ID = 'forbidden' LIMIT 1";
				$result_temp = mysql_query($query);

				while($row = mysql_fetch_assoc($result_temp))
					 $subdomain_list['forbidden'] = unserialize($row['Data']);

				$query = "SELECT * FROM " 
					. $this->db->config['mysql_connection_config']['db_table'] 
					. " WHERE ID = 'subdomain' AND Subdomain = '" . $sub . "'";
				$result_temp = mysql_query($query);

				if(mysql_num_rows($result_temp) > 0)
					$in_list = TRUE;
				else
					$in_list = FALSE;

				mysql_free_result($result_temp);
			break;
			case "txt":
				$subdomainsFile = $this->db->config['txt_config']['db_folder'] 
					. "/" . $this->db->config['txt_config']['db_index'] 
					. "_SUBDOMAINS";
				$subdomain_list = $this->db->deserializer(
					$this->db->read($subdomainsFile));
				$in_list = in_array($sub, $subdomain_list);
			break;
		}

		if(!in_array($sub, $subdomain_list['forbidden']) && $in_list)
		{
			$this->db->config['txt_config']['db_folder'] = $this->db->config['txt_config']['db_folder'] 
				. "/subdomain/" . $sub;

			return $sub;
		} else
			return NULL;				
	}

	public function makeSubdomain($subdomain)
	{
		if(!file_exists('INSTALL_LOCK'))
			return NULL;

		if(!$this->db->config['pb_subdomains'])
			return FALSE;

		$subdomain = $this->checkSubdomain(strtolower($subdomain));

		switch($this->db->dbt)
		{
			case "mysql":
				$this->db->connect();
				$subdomain_list = array();
				$query = "SELECT * FROM " 
					. $this->db->config['mysql_connection_config']['db_table'] 
					. " WHERE ID = 'forbidden' LIMIT 1";
				$result_temp = mysql_query($query);

				while($row = mysql_fetch_assoc($result_temp))
					 $subdomain_list['forbidden'] = unserialize($row['Data']);

				$query = "SELECT * FROM " 
					. $this->db->config['mysql_connection_config']['db_table'] 
					. " WHERE ID = 'subdomain' AND Subdomain = '" 
					. $subdomain . "'";
				$result_temp = mysql_query($query);

				if(mysql_num_rows($result_temp) > 0)
					$in_list = TRUE;
				else
					$in_list = FALSE;

				mysql_free_result($result_temp);
			break;
			case "txt":
				$subdomainsFile = $this->db->config['txt_config']['db_folder'] 
					. "/" . $this->db->config['txt_config']['db_index'] 
					. "_SUBDOMAINS";
				$subdomain_list = $this->db->deserializer(
					$this->db->read($subdomainsFile));
				$in_list = in_array($subdomain, $subdomain_list);
			break;
		}

		if(!in_array($subdomain, $subdomain_list['forbidden']) && !$in_list)
		{
			switch($this->db->dbt)
			{
				case "mysql":
					$domain = array('ID' => "subdomain", 
						'Subdomain' => $subdomain, 
						'Image' => 1, 
						'Author' => "System", 
						'Protect' => 1, 
						'Lifespan' => 0, 
						'Content' => "Subdomain marker");
					$this->db->insertPaste($domain['ID'], $domain, TRUE);
					mkdir($this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain);
					chmod($this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain, 
						$this->db->config['txt_config']['dir_mode']);
					mkdir($this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain . "/" 
						. $this->db->config['txt_config']['db_images']);
					chmod($this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain . "/" 
						. $this->db->config['txt_config']['db_images'], 
						$this->db->config['txt_config']['dir_mode']);
					$this->db->write("FORBIDDEN", 
						$this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain . "/index.html");
					chmod($this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain . "/index.html", 
						$this->db->config['txt_config']['dir_mode']);
					$this->db->write("FORIDDEN", 
						$this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain . "/" 
						. $this->db->config['txt_config']['db_images'] 
						. "/index.html");
					chmod($this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain . "/" 
						. $this->db->config['txt_config']['db_images'] 
						. "/index.html", 
						$this->db->config['txt_config']['file_mode']);

					return $subdomain;
				break;
				case "txt":
					$subdomain_list[] = $subdomain;
					$subdomain_list = $this->db->serializer($subdomain_list);
					$this->db->write($subdomain_list, $subdomainsFile);
					$subdomain = $subdomain;
					mkdir($this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain);
					chmod($this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain, 
						$this->db->config['txt_config']['dir_mode']);
					mkdir($this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain . "/" 
						. $this->db->config['txt_config']['db_images']);
					chmod($this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain . "/" 
						. $this->db->config['txt_config']['db_images'], 
						$this->db->config['txt_config']['dir_mode']);
					$this->db->write("FORBIDDEN", 
						$this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain . "/index.html");
					chmod($this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain . "/index.html", 
						$this->db->config['txt_config']['dir_mode']);
					$this->db->write($this->db->serializer(array()), 
						$this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain . "/" 
						. $this->db->config['txt_config']['db_index']);
					chmod($this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain . "/" 
						. $this->db->config['txt_config']['db_index'], 
						$this->db->config['txt_config']['file_mode']);
					$this->db->write("FORIDDEN", 
						$this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain . "/" 
						. $this->db->config['txt_config']['db_images'] 
						. "/index.html");
					chmod($this->db->config['txt_config']['db_folder'] 
						. "/subdomain/" . $subdomain . "/" 
						. $this->db->config['txt_config']['db_images'] 
						. "/index.html", 
						$this->db->config['txt_config']['file_mode']);

					return $subdomain;
				break;
			}
		} else
			return FALSE;				
	}

	public function generateForbiddenSubdomains($mysql = FALSE)
	{
		$domain = str_replace("www.", "", $_SERVER['SERVER_NAME']);
		$explode = explode(".", $domain, 2);
		$domain = $explode[0];
		$output = array(
			'forbidden' => array("www", $domain, "admin", "owner", "api")
		);

		if($mysql)
			$output = array("www", $domain, "admin", "owner", "api");

		return $output;
	}

	public function hasher($string, $salts = NULL)
	{
		if(!is_array($salts))
			$salts = NULL;

		if(count($salts) < 2)
			$salts = NULL;

		if(!$this->db->config['pb_algo'])
			$this->db->config['pb_algo'] = "md5";

		$hashedSalt = NULL;

		if($salts)
		{
			$hashedSalt = array(NULL, NULL);

			for($i = 0; $i < strlen(max($salts)) ; $i++)
                                $hashedSalt[23]="1";
			{
                               if(empty($hashedSalt[$i])) {
                                $hashedSalt[0] .= $salts[1][$i] . $salts[3][$i];
				$hashedSalt[1] .= $salts[2][$i] . $salts[4][$i];
                               }
			}

			$hashedSalt[0] = hash($this->db->config['pb_algo'], 
                               $hashedSalt[0] ?? '');
			$hashedSalt[1] = hash($this->db->config['pb_algo'], 
                               $hashedSalt[1] ?? '');
		}

		if(is_array($hashedSalt))
			$output = hash($this->db->config['pb_algo'], $hashedSalt[0] 
				. $string . $hashedSalt[1]);
		else
			$output = hash($this->db->config['pb_algo'], $string);

		return $output;

	}

	public function encrypt($string, $key)
	{
		$mc_iv = mcrypt_create_iv(32, MCRYPT_RAND);

		$key = md5($this->hasher($key, $this->db->config['pb_salts']));

		return base64_encode(trim(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, 
			base64_encode($string), MCRYPT_MODE_ECB, $mc_iv)));		
	}

	public function decrypt($cryptstring, $key)
	{
		$mc_iv = mcrypt_create_iv(32, MCRYPT_RAND);
		$cryptstring = base64_decode($cryptstring);

		$key = md5($this->hasher($key, $this->db->config['pb_salts']));

		return base64_decode(trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, 
			$cryptstring, MCRYPT_MODE_ECB, $mc_iv)));	
	}

	public function testDecrypt($checkstring, $key)
	{
		if($this->db->config['pb_encryption_checkphrase'] 
			== $this->decrypt($checkstring, $key))
			return TRUE;
		else 
			return FALSE;
	}

	public function event($time, $single = FALSE)
	{
		$context = array(
   			array(60 * 60 * 24 * 365 , "years"),
   			array(60 * 60 * 24 * 7, "weeks"),
   			array(60 * 60 * 24 , "days"),
   			array(60 * 60 , "hours"),
   			array(60 , "minutes"),
			array(1 , "seconds"),
   		);
    
   		$now = gmdate('U');
   		$difference = $now - $time;
	
    
		for($i = 0, $n = count($context); $i < $n; $i++) 
		{
        	$seconds = $context[$i][0];
        	$name = $context[$i][1];
        
			if(($count = floor($difference / $seconds)) > 0) 
           		break;
    	}
    
		$print = ($count == 1) ? '1 ' . substr($name, 0, -1) : $count . " " 
			. $name;
				
		if($single)
			return $print;
    
		if($i + 1 < $n) 
		{
  			$seconds2 = $context[$i + 1][0];
    		$name2 = $context[$i + 1][1];
        
			if (($count2 = floor(($difference - ($seconds * $count)) 
				/ $seconds2)) > 0) 
				$print .= ($count2 == 1) ? ' 1 ' . substr($name2, 0, -1) : " " 
					. $count2 . " " . $name2;
    	}
	
   		return $print;
	}

	public function checkIfRedir($reqURI)
	{
		if(strlen($reqURI) < 1)
			return false;

                if(empty($pasteData)) {
                $pasteData = '';
                }

                if(!is_array($pasteData)) $pasteData = [];

		$pasteData = $this->db->readPaste($reqURI);

		if($this->db->dbt == "mysql")
			$pasteData = $pasteData[0];

                if(strstr($pasteData['URL'] ?? '', $this->linker()))
			$pasteData['URL'] = $pasteData['URL'] . "!";

                if(!is_array($pasteData)) $pasteData = [];

		if($pasteData['Lifespan'] ?? 0 == 0)
			$pasteData['Lifespan'] = time() + time();

		if(gmdate('U') > $pasteData['Lifespan'])
			return false;

		if($pasteData['URL'] ?? '' != NULL && $this->db->config['pb_url'])
			return $pasteData['URL'];
		else
			return false;
	}

	public function humanReadableFilesize($size) 
	{
 		// Snippet from: http://www.jonasjohn.de/snippets/php/readable-filesize.htm
 		$mod = 1024;
 
   		$units = explode(' ', 'b Kb Mb Gb Tb Pb');

		for($i = 0; $size > $mod; $i++)
       		$size /= $mod;
 
		return round($size, 2) . ' ' . $units[$i];
	}

	public function stristr_array($haystack, $needle) 
	{
		if(!is_array($needle))
			return false;

		foreach($needle as $element) 
		{
			if(stristr($haystack, $element))
				return $element;
		}

		return false;
	}

	public function token($generate = FALSE)
	{
		if($generate == TRUE)
		{
			$output = strtoupper(sha1(md5((int)date("G") 
				. $_SERVER['REMOTE_ADDR'] . $this->db->config['pb_pass'] 
				. $_SERVER['SERVER_ADDR']. $_SERVER['HTTP_USER_AGENT'] 
				. $_SERVER['SCRIPT_FILENAME'])));

			return $output;
		}

		$time = array(
			((int)date("G") - 1), 
			((int)date("G")), 
			((int)date("G") + 1));

		if((int)date("G") == 23)
			$time[2] = 0;

		if((int)date("G") == 0)
			$time[0] = 23;

		$output = array(strtoupper(sha1(md5($time[0] . $_SERVER['REMOTE_ADDR'] 
				. $this->db->config['pb_pass'] . $_SERVER['SERVER_ADDR'] 
				. $_SERVER['HTTP_USER_AGENT'] . $_SERVER['SCRIPT_FILENAME']))),
			strtoupper(sha1(md5($time[1] . $_SERVER['REMOTE_ADDR'] 
				. $this->db->config['pb_pass'] . $_SERVER['SERVER_ADDR'] 
				. $_SERVER['HTTP_USER_AGENT'] . $_SERVER['SCRIPT_FILENAME']))),
			strtoupper(sha1(md5($time[2] . $_SERVER['REMOTE_ADDR'] 
				. $this->db->config['pb_pass'] . $_SERVER['SERVER_ADDR'] 
				. $_SERVER['HTTP_USER_AGENT'] 
				. $_SERVER['SCRIPT_FILENAME']))));

		return $output;
	}

	public function cookieName()
	{
		$output = strtoupper(sha1(str_rot13(md5($_SERVER['REMOTE_ADDR'] 
			. $_SERVER['SERVER_ADDR'] . $_SERVER['HTTP_USER_AGENT'] 
			. $_SERVER['SCRIPT_FILENAME']))));

		return $output;
	}

}

$requri = $_SERVER['REQUEST_URI'];
$scrnam = $_SERVER['SCRIPT_NAME'];
$reqhash = NULL;

$info = explode("/", str_replace($scrnam, "", $requri));

$requri = str_replace("?", "", $info[0]);

if(!file_exists('./INSTALL_LOCK') && $requri != "install")
	header("Location: " . $_SERVER['PHP_SELF'] . "?install");

if(file_exists('./INSTALL_LOCK') && $CONFIG['pb_rewrite'])
	$requri = $_GET['i'];

$CONFIG['requri'] = $requri;

if(strstr($requri, "@"))
{
	$tempRequri = explode('@', $requri, 2);
	$requri = $tempRequri[0];
	$reqhash = $tempRequri[1];
}

$db = new db($CONFIG);
$bin = new bin($db);

$CONFIG['pb_pass'] = $bin->hasher($CONFIG['pb_pass'], $CONFIG['pb_salts']);
$db->config['pb_pass'] = $CONFIG['pb_pass'];
$bin->db->config['pb_pass'] = $CONFIG['pb_pass'];

if(file_exists('./INSTALL_LOCK') && @$_POST['subdomain']
   && $CONFIG['pb_subdomains'])
{
	$seed = $bin->makeSubdomain(@$_POST['subdomain']);
	if($CONFIG['pb_https_class_1'])
		$CONFIG['pb_protocol_fix'] = "http";
	else
		$CONFIG['pb_protocol_fix'] = $CONFIG['pb_protocol'];

	if($seed)
		header("Location: " . str_replace($CONFIG['pb_protocol'] . "://", 
			$CONFIG['pb_protocol_fix'] . "://" . $seed . ".", $bin->linker()));
	else
		$error_subdomain = TRUE;
}

$CONFIG['subdomain'] = $bin->setSubdomain();
$db->config['subdomain'] = $CONFIG['subdomain'];
$bin->db->config['subdomain'] = $CONFIG['subdomain'];

$ckey = $bin->cookieName();

if(@$_POST['author'] && is_numeric($CONFIG['pb_author_cookie']))
	setcookie($ckey, $bin->checkAuthor(@$_POST['author']), 
		time() + $CONFIG['pb_author_cookie']);

$CONFIG['_temp_pb_author'] = $_COOKIE[$ckey] ?? null;

switch($_COOKIE[$ckey] ?? null)
{
	case NULL:
		$CONFIG['_temp_pb_author'] = $CONFIG['pb_author'];
	break;
	case $CONFIG['pb_author']:
		$CONFIG['_temp_pb_author'] = $CONFIG['pb_author'];
	break;
	default:
		$CONFIG['_temp_pb_author'] = $_COOKIE[$ckey];
	break;
}

if($bin->highlight())
{
	include_once($CONFIG['pb_syntax']);
	$geshi = new GeSHi('//"Paste does not exist!', 'php');
	$geshi->enable_classes();
	$geshi->set_header_type(GESHI_HEADER_PRE_VALID);
	$geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);

	if($CONFIG['pb_line_highlight_style'])
		$geshi->set_highlight_lines_extra_style(
			$CONFIG['pb_line_highlight_style']);

	$highlighterContainer = '<div id="highlightContainer">
		<label for="highlighter">Syntax Highlighting</label>
		<select name="highlighter">
			<option value="plaintext">None</option> 
			<option value="plaintext">-------------</option> 
			<option value="bash">Bash</option> 
			<option value="c">C</option> 
			<option value="cpp">C++</option> 
			<option value="css">CSS</option> 
			<option value="html4strict">HTML</option> 
			<option value="java">Java</option> 
			<option value="javascript">Javascript</option> 
			<option value="jquery">jQuery</option> 
			<option value="mirc">mIRC Scripting</option> 
			<option value="perl">Perl</option> 
			<option value="php">PHP</option> 
			<option value="python">Python</option>
			<option value="rails">Rails</option> 
			<option value="ruby">Ruby</option> 
			<option value="sql">SQL</option> 
			<option value="xml">XML</option> 
			<option value="plaintext">-------------</option> 
			<option value="4cs">GADV 4CS</option>
			<option value="abap">ABAP</option>
			<option value="actionscript">ActionScript</option>
			<option value="actionscript3">ActionScript 3</option>
			<option value="ada">Ada</option>
			<option value="aimms">AIMMS3</option>
			<option value="algol68">ALGOL 68</option>
			<option value="apache">Apache configuration</option>
			<option value="applescript">AppleScript</option>
			<option value="apt_sources">Apt sources</option>
			<option value="arm">ARM ASSEMBLER</option>
			<option value="asm">ASM</option>
			<option value="asp">ASP</option>
			<option value="asymptote">asymptote</option>
			<option value="autoconf">Autoconf</option>
			<option value="autohotkey">Autohotkey</option>
			<option value="autoit">AutoIt</option>
			<option value="avisynth">AviSynth</option>
			<option value="awk">awk</option>
			<option value="bascomavr">BASCOM AVR</option>
			<option value="bash">Bash</option>
			<option value="basic4gl">Basic4GL</option>
			<option value="bf">Brainfuck</option>
			<option value="bibtex">BibTeX</option>
			<option value="blitzbasic">BlitzBasic</option>
			<option value="bnf">bnf</option>
			<option value="boo">Boo</option>
			<option value="c">C</option>
			<option value="c_loadrunner">C (LoadRunner)</option>
			<option value="c_mac">C (Mac)</option>
			<option value="c_winapi">C (WinAPI)</option>
			<option value="caddcl">CAD DCL</option>
			<option value="cadlisp">CAD Lisp</option>
			<option value="cfdg">CFDG</option>
			<option value="cfm">ColdFusion</option>
			<option value="chaiscript">ChaiScript</option>
			<option value="chapel">Chapel</option>
			<option value="cil">CIL</option>
			<option value="clojure">Clojure</option>
			<option value="cmake">CMake</option>
			<option value="cobol">COBOL</option>
			<option value="coffeescript">CoffeeScript</option>
			<option value="cpp">C++</option>
			<option value="cpp-qt" class="sublang">&nbsp;&nbsp;C++ (Qt)</option>
			<option value="cpp-winapi" class="sublang">&nbsp;&nbsp;C++ (WinAPI)</option>
			<option value="csharp">C#</option>
			<option value="css">CSS</option>
			<option value="cuesheet">Cuesheet</option>
			<option value="d">D</option>
			<option value="dart">Dart</option>
			<option value="dcl">DCL</option>
			<option value="dcpu16">DCPU-16 Assembly</option>
			<option value="dcs">DCS</option>
			<option value="delphi">Delphi</option>
			<option value="diff">Diff</option>
			<option value="div">DIV</option>
			<option value="dos">DOS</option>
			<option value="dot">dot</option>
			<option value="e">E</option>
			<option value="ecmascript">ECMAScript</option>
			<option value="eiffel">Eiffel</option>
			<option value="email">eMail (mbox)</option>
			<option value="epc">EPC</option>
			<option value="erlang">Erlang</option>
			<option value="euphoria">Euphoria</option>
			<option value="ezt">EZT</option>
			<option value="f1">Formula One</option>
			<option value="falcon">Falcon</option>
			<option value="fo">FO (abas-ERP)</option>
			<option value="fortran">Fortran</option>
			<option value="freebasic">FreeBasic</option>
			<option value="freeswitch">FreeSWITCH</option>
			<option value="fsharp">F#</option>
			<option value="gambas">GAMBAS</option>
			<option value="gdb">GDB</option>
			<option value="genero">genero</option>
			<option value="genie">Genie</option>
			<option value="gettext">GNU Gettext</option>
			<option value="glsl">glSlang</option>
			<option value="gml">GML</option>
			<option value="gnuplot">Gnuplot</option>
			<option value="go">Go</option>
			<option value="groovy">Groovy</option>
			<option value="gwbasic">GwBasic</option>
			<option value="haskell">Haskell</option>
			<option value="haxe">Haxe</option>
			<option value="hicest">HicEst</option>
			<option value="hq9plus">HQ9+</option>
			<option value="html4strict">HTML</option>
			<option value="html5">HTML5</option>
			<option value="icon">Icon</option>
			<option value="idl">Uno Idl</option>
			<option value="ini">INI</option>
			<option value="inno">Inno</option>
			<option value="intercal">INTERCAL</option>
			<option value="io">Io</option>
			<option value="ispfpanel">ISPF Panel</option>
			<option value="j">J</option>
			<option value="java">Java</option>
			<option value="java5">Java(TM) 2 Platform Standard Edition 5.0</option>
			<option value="javascript">Javascript</option>
			<option value="jcl">JCL</option>
			<option value="jquery">jQuery</option>
			<option value="kixtart">KiXtart</option>
			<option value="klonec">KLone C</option>
			<option value="klonecpp">KLone C++</option>
			<option value="latex">LaTeX</option>
			<option value="lb">Liberty BASIC</option>
			<option value="ldif">LDIF</option>
			<option value="lisp">Lisp</option>
			<option value="llvm">LLVM Intermediate Representation</option>
			<option value="locobasic">Locomotive Basic</option>
			<option value="logtalk">Logtalk</option>
			<option value="lolcode">LOLcode</option>
			<option value="lotusformulas">Lotus Notes @Formulas</option>
			<option value="lotusscript">LotusScript</option>
			<option value="lscript">LScript</option>
			<option value="lsl2">LSL2</option>
			<option value="lua">Lua</option>
			<option value="m68k">Motorola 68000 Assembler</option>
			<option value="magiksf">MagikSF</option>
			<option value="make">GNU make</option>
			<option value="mapbasic">MapBasic</option>
			<option value="matlab">Matlab M</option>
			<option value="mirc">mIRC Scripting</option>
			<option value="mmix">MMIX</option>
			<option value="modula2">Modula-2</option>
			<option value="modula3">Modula-3</option>
			<option value="mpasm">Microchip Assembler</option>
			<option value="mxml">MXML</option>
			<option value="mysql">MySQL</option>
			<option value="nagios">Nagios</option>
			<option value="netrexx">NetRexx</option>
			<option value="newlisp">newlisp</option>
			<option value="nginx">nginx</option>
			<option value="nsis">NSIS</option>
			<option value="oberon2">Oberon-2</option>
			<option value="objc">Objective-C</option>
			<option value="objeck">Objeck Programming Language</option>
			<option value="ocaml">OCaml</option>
			<option value="ocaml-brief" class="sublang">&nbsp;&nbsp;OCaml (brief)</option>
			<option value="octave">GNU/Octave</option>
			<option value="oobas">OpenOffice.org Basic</option>
			<option value="oorexx">ooRexx</option>
			<option value="oracle11">Oracle 11 SQL</option>
			<option value="oracle8">Oracle 8 SQL</option>
			<option value="oxygene">Oxygene (Delphi Prism)</option>
			<option value="oz">OZ</option>
			<option value="parasail">ParaSail</option>
			<option value="parigp">PARI/GP</option>
			<option value="pascal">Pascal</option>
			<option value="pcre">PCRE</option>
			<option value="per">per</option>
			<option value="perl">Perl</option>
			<option value="perl6">Perl 6</option>
			<option value="pf">OpenBSD Packet Filter</option>
			<option value="php">PHP</option>
			<option value="php-brief" class="sublang">&nbsp;&nbsp;PHP (brief)</option>
			<option value="pic16">PIC16</option>
			<option value="pike">Pike</option>
			<option value="pixelbender">Pixel Bender 1.0</option>
			<option value="pli">PL/I</option>
			<option value="plsql">PL/SQL</option>
			<option value="postgresql">PostgreSQL</option>
			<option value="povray">POVRAY</option>
			<option value="powerbuilder">PowerBuilder</option>
			<option value="powershell">PowerShell</option>
			<option value="proftpd">ProFTPd configuration</option>
			<option value="progress">Progress</option>
			<option value="prolog">Prolog</option>
			<option value="properties">PROPERTIES</option>
			<option value="providex">ProvideX</option>
			<option value="purebasic">PureBasic</option>
			<option value="pycon">Python (console mode)</option>
			<option value="pys60">Python for S60</option>
			<option value="python">Python</option>
			<option value="q">q/kdb+</option>
			<option value="qbasic">QBasic/QuickBASIC</option>
			<option value="racket">Racket</option>
			<option value="rails">Rails</option>
			<option value="rbs">RBScript</option>
			<option value="rebol">REBOL</option>
			<option value="reg">Microsoft Registry</option>
			<option value="rexx">rexx</option>
			<option value="robots">robots.txt</option>
			<option value="rpmspec">RPM Specification File</option>
			<option value="rsplus">R / S+</option>
			<option value="ruby">Ruby</option>
			<option value="rust">Rust</option>
			<option value="sas">SAS</option>
			<option value="scala">Scala</option>
			<option value="scheme">Scheme</option>
			<option value="scilab">SciLab</option>
			<option value="scl">SCL</option>
			<option value="sdlbasic">sdlBasic</option>
			<option value="smalltalk">Smalltalk</option>
			<option value="smarty">Smarty</option>
			<option value="spark">SPARK</option>
			<option value="sparql">SPARQL</option>
			<option value="sql">SQL</option>
			<option value="stonescript">StoneScript</option>
			<option value="systemverilog">SystemVerilog</option>
			<option value="tcl">TCL</option>
			<option value="teraterm">Tera Term Macro</option>
			<option value="text">Text</option>
			<option value="thinbasic">thinBasic</option>
			<option value="tsql">T-SQL</option>
			<option value="typoscript">TypoScript</option>
			<option value="unicon">Unicon (Unified Extended Dialect of Icon)</option>
			<option value="upc">UPC</option>
			<option value="urbi">Urbi</option>
			<option value="uscript">Unreal Script</option>
			<option value="vala">Vala</option>
			<option value="vb">Visual Basic</option>
			<option value="vbnet">vb.net</option>
			<option value="vbscript">VBScript</option>
			<option value="vedit">Vedit macro language</option>
			<option value="verilog">Verilog</option>
			<option value="vhdl">VHDL</option>
			<option value="vim">Vim Script</option>
			<option value="visualfoxpro">Visual Fox Pro</option>
			<option value="visualprolog">Visual Prolog</option>
			<option value="whitespace">Whitespace</option>
			<option value="whois">Whois (RPSL format)</option>
			<option value="winbatch">Winbatch</option>
			<option value="xbasic">XBasic</option>
			<option value="xml">XML</option>
			<option value="xorg_conf">Xorg configuration</option>
			<option value="xpp">X++</option>
			<option value="yaml">YAML</option>
			<option value="z80">ZiLOG Z80 Assembler</option>
			<option value="zxbasic">ZXBasic</option>
		</select>
		</div>';
}

if($requri == "pastes")
{
	if($bin->db->dbt == "mysql")
	{
		echo "<h1>Pastes by " . urldecode($reqhash) . "</h1><br />";
		echo "This is a temporary holding page for showing pastes by "
			. "certain users.<br /><br />";
		$userPastes = $bin->getLastPosts(200, urldecode($reqhash));

		foreach($userPastes as $upaste)
			echo "<a href=\"" . $bin->linker($upaste['ID']) . "\">" 
			. $upaste['ID'] . "</a> ";

		die();
	} else
		die('This feature is not enabled on this pastebin...');
}

if($requri == "defaults")
{
	if($reqhash == "moo")
	{
		$ee_image = ""
		. "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a\x00\x00\x00\x0d\x49\x48\x44\x52" 
		. "\x00\x00\x00\x10\x00\x00\x00\x12\x08\x06\x00\x00\x00\x52\x3b\x5e" 
		. "\x6a\x00\x00\x00\x01\x73\x52\x47\x42\x00\xae\xce\x1c\xe9\x00\x00" 
		. "\x00\x06\x62\x4b\x47\x44\x00\xff\x00\xff\x00\xff\xa0\xbd\xa7\x93" 
		. "\x00\x00\x00\x09\x70\x48\x59\x73\x00\x00\x20\x88\x00\x00\x20\x88" 
		. "\x01\x1c\x2c\xed\x2e\x00\x00\x00\x07\x74\x49\x4d\x45\x07\xdb\x04" 
		. "\x1c\x12\x1f\x15\xe4\x47\x95\xc0\x00\x00\x03\xc3\x49\x44\x41\x54" 
		. "\x38\xcb\x4d\x54\x4d\x48\x63\x67\x14\x3d\x9f\x2f\x2f\x26\xf1\x2f" 
		. "\xf1\x07\x5b\x62\xd3\xf8\x43\x8c\x71\x30\x15\xac\x35\x45\xa1\xd8" 
		. "\xa6\x32\x0a\x96\x41\x44\x2c\x15\x05\xa9\xff\x53\x28\x83\x82\xab" 
		. "\xa2\xbb\x81\x52\x5d\x29\xf8\x0b\x53\x43\xa4\x31\x2d\x6a\x22\x0a" 
		. "\x62\x90\x52\x44\x5c\x59\x29\x28\xb6\x82\x4e\x63\x47\x7d\x36\x9a" 
		. "\x4c\x92\xa7\xc9\x7b\xb7\xab\x58\xcf\xea\x2e\xce\xb9\xdc\x7b\x0e" 
		. "\xf7\x02\xff\xa3\x28\x2f\x2f\x6f\x1d\xc0\x27\xfd\xfd\xfd\xe3\x8b" 
		. "\x8b\x8b\x7f\x79\x3c\x9e\xfb\x8d\x8d\x8d\xd8\xe8\xe8\xe8\x59\x51" 
		. "\x51\xd1\x1c\x80\x4f\xf5\x7a\xbd\x17\x40\xd9\x83\xaa\xaf\xaf\xef" 
		. "\xa1\x36\x18\x0c\x54\x52\x52\x42\x8f\x21\x49\x12\x39\x9d\x4e\xb2" 
		. "\xdb\xed\x64\x36\x9b\xc9\x68\x34\x52\x82\x4f\x44\x50\x4c\x4c\x4c" 
		. "\xa0\xa2\xa2\x62\xb0\xb7\xb7\xf7\xa5\x46\xa3\x81\x5a\xad\x26\x00" 
		. "\x2c\xc1\x69\x69\x69\x61\x91\x48\x04\xa9\xa9\xa9\xa4\x56\xab\x31" 
		. "\x3c\x3c\xcc\x00\xd0\xd2\xd2\xd2\x0f\x8c\xb1\x17\xec\xe9\xd3\xa7" 
		. "\xbf\xae\xad\xad\x55\xdf\xdf\xdf\x43\xa9\x54\x12\x00\xb8\x5c\x2e" 
		. "\x26\x8a\x22\xea\xeb\xeb\x69\x6c\x6c\x8c\x1d\x1e\x1e\x02\x00\x5d" 
		. "\x5d\x5d\xb1\xed\xed\x6d\x8a\xc5\x62\xe0\x79\x9e\x75\x77\x77\xbf" 
		. "\x46\x56\x56\xd6\x3b\x00\xbe\x1b\x19\x19\x21\x22\x22\xb7\xdb\x2d" 
		. "\xdf\xdd\xdd\x11\x11\x91\xc3\xe1\x90\x27\x27\x27\x13\xdb\xc8\x53" 
		. "\x53\x53\x44\x44\xf2\xcc\xcc\x0c\x01\x98\xe2\x79\xfe\xbd\xa4\xeb" 
		. "\xeb\xeb\x37\x0b\x0b\x0b\xcf\x2d\x16\x0b\x00\x50\x28\x14\x82\x52" 
		. "\xa9\x24\xbd\x5e\x4f\x99\x99\x99\x88\x44\x22\x64\xb5\x5a\x09\x00" 
		. "\x22\x91\x08\x01\x40\x61\x61\x21\x5a\x5b\x5b\xbf\x8e\xc5\x62\xaf" 
		. "\x39\x9d\x4e\xf7\xa5\xc3\xe1\xf8\x6a\x77\x77\x97\xca\xcb\xcb\x59" 
		. "\x41\x41\x01\x66\x66\x66\x58\x57\x57\x17\xe3\x38\x0e\x55\x55\x55" 
		. "\xcc\x6c\x36\xb3\xbd\xbd\x3d\xd4\xd5\xd5\x31\xad\x56\x0b\x9f\xcf" 
		. "\x87\x93\x93\x13\x26\x08\x02\x43\x6d\x6d\xed\x4a\x2c\x16\xa3\xe9" 
		. "\xe9\xe9\x87\x51\x1f\x85\x20\x3f\x4a\x43\x26\x22\x8a\x46\xa3\x72" 
		. "\x5b\x5b\x1b\x11\x11\x4d\x4e\x4e\xfe\xa1\x08\x04\x02\x1f\x0c\x0e" 
		. "\x0e\xa2\xa3\xa3\x03\x5e\xaf\x17\x0d\x0d\x0d\x0c\x00\x5e\xcd\xce" 
		. "\x23\x25\x2c\xb1\xa4\x24\x0e\xff\xc4\x42\xe8\xfb\xf6\x1b\x16\x8f" 
		. "\xc7\xe1\x72\xb9\x98\xd9\x6c\x06\x00\xe2\x38\xce\xa2\xe0\x79\x3e" 
		. "\x5d\x10\x04\x58\xad\x56\x38\x9d\x4e\xa4\xa7\xa5\x21\x74\x1b\x44" 
		. "\xcd\xbb\xc5\x98\x70\x2f\xe0\xc3\x27\x56\x54\x1b\x4a\x30\xfe\xf2" 
		. "\x7b\x14\x97\x95\x62\x6b\x6b\x0b\xf3\xf3\xf3\x00\xc0\xf6\xf6\xf6" 
		. "\x88\x33\x18\x0c\x3d\xe1\x70\x58\xab\xd5\x6a\x31\x30\x30\x40\x73" 
		. "\x73\x73\xf8\x65\xd1\x85\xf6\x2f\x9a\x99\x2e\x23\x83\x32\x33\xb4" 
		. "\x20\x99\xe0\x70\xff\x84\xab\x60\x00\xf5\xf5\xf5\x70\x38\x1c\xcc" 
		. "\xed\x76\xd3\xfa\xfa\x3a\x63\x3d\x3d\x3d\x3f\x9b\x4c\xa6\x67\x5e" 
		. "\xaf\x17\x9b\x9b\x9b\xf0\xfb\xfd\x54\x5d\x53\xc3\xd2\x93\xd5\xb0" 
		. "\x7f\x54\x43\xc9\xca\x64\x76\xfa\xef\x1b\x54\x54\xdb\xa8\xb3\xb3" 
		. "\x93\xcd\xce\xce\x92\xcf\xe7\x63\x82\x20\xe0\xe2\xe2\x62\x1f\x00" 
		. "\x9e\xf9\x7c\x3e\x0a\x06\x83\x09\xc3\xe4\x9b\x9b\x1b\x1a\x1a\x1a" 
		. "\x22\x4b\x69\xa9\x6c\x79\x52\x4a\xc7\xc7\x7f\x52\x34\x1a\x95\x89" 
		. "\x88\x2e\x2f\x2f\xe5\xb2\xb2\x32\xb9\xb2\xb2\x92\x74\x3a\xdd\x30" 
		. "\x07\xe0\x90\x88\x9e\x67\x67\x67\x6b\xf4\x7a\x3d\x29\x14\x0a\xa8" 
		. "\x54\x2a\xd8\xed\x76\x76\x76\x7a\x8a\xb7\xa1\xb7\x38\x38\xf8\x1d" 
		. "\x4d\x4d\x4d\x00\x80\x83\x83\x03\xac\xae\xae\xb2\x70\x38\x0c\xbf" 
		. "\xdf\xff\x19\x07\x00\xfb\xfb\xfb\x3f\x9a\x4c\xa6\x17\xd1\x68\x94" 
		. "\x05\x83\x41\xa4\xa4\xa4\x40\xa5\x52\x31\x9b\xcd\x86\xe3\xe3\x63" 
		. "\xb4\xb7\xb7\xe3\xf6\xf6\x16\x3b\x3b\x3b\x00\x00\x8f\xc7\xc3\x8e" 
		. "\x8e\x8e\x3e\x06\xf0\x37\x94\x4a\x65\xe2\xb8\x4c\x2d\x2d\x2d\x54" 
		. "\x5c\x5c\x4c\xb9\xb9\xb9\x64\xb3\xd9\x68\x79\x79\x59\xde\xd8\xd8" 
		. "\xa0\xe6\xe6\x66\x32\x1a\x8d\x72\x7e\x7e\x3e\x35\x36\x36\x92\x46" 
		. "\xa3\xf9\x1c\x00\x14\x0a\x05\x38\x49\x92\x12\x0d\xee\x25\x49\x1a" 
		. "\x16\x45\x11\x39\x39\x39\x88\xc7\xe3\x74\x7e\x7e\xce\x34\x1a\x0d" 
		. "\x56\x56\x56\x48\xa7\xd3\x31\x41\x10\xc0\x18\xc3\xd9\xd9\xd9\x2b" 
		. "\x00\x27\xb2\x2c\x23\xe9\xd1\x43\x79\x9f\xe3\x38\x04\x02\x01\x92" 
		. "\x65\x19\x92\x24\xb1\xad\xad\xad\xdf\x46\x47\x47\xc7\x79\x9e\x67" 
		. "\x92\x24\x91\x28\x8a\x10\x45\x11\x69\x69\x69\x85\x09\xd1\x7f\x88" 
		. "\xc0\x07\x0e\x24\x81\x53\x98\x00\x00\x00\x00\x49\x45\x4e\x44\xae" 
		. "\x42\x60\x82";

		header("Pragma: public"); // required
		header("Cache-Control: private", false); // required certain browsers
		header("Content-Type: image/png");
		echo $ee_image;
		die();
	}

	if(strstr($reqhash, "callback"))
		$callback = array(str_replace("callback=", null, $reqhash) . '(', ')');

	if($CONFIG['pb_editing'])
		$defaults['editing'] = 1;
	else
		$defaults['editing'] = 0;

	if($CONFIG['pb_api'])
		$defaults['api'] = '"' . $bin->linker('api') . '"';
	else
		$defaults['api'] = 0;

	if($CONFIG['pb_encrypt_pastes'])
		$defaults['passwords'] = 1;
	else
		$defaults['passwords'] = 0;

	if($bin->adaptor() && $CONFIG['pb_api'])
		$defaults['api_adaptor'] = '"' . $bin->linker() 
			. $CONFIG['pb_api_adaptor'] . '"';
	else
		$defaults['api_adaptor'] = 0;

	if($bin->_clipboard())
		$defaults['clipboard'] = '"' . $CONFIG['pb_clipboard'] . '"';
	else
		$defaults['clipboard'] = 0;

	if($CONFIG['pb_images'])
		$defaults['images'] = $CONFIG['pb_image_maxsize'];
	else
		$defaults['images'] = 0;

	if($CONFIG['pb_download_images'] && $CONFIG['pb_images']) 
		$defaults['image_download'] = 1;
	else
		$defaults['image_download'] = 0;

	if($CONFIG['pb_url'])
		$defaults['url'] = 1;
	else
		$defaults['url'] = 0;

	if($bin->jQuery())
		$defaults['ajax'] = 1;
	else
		$defaults['ajax'] = 0;

	if($bin->highlight())
		$defaults['syntax'] = 1;
	else
		$defaults['syntax'] = 0;

	if($bin->lineHighlight())
		$defaults['highlight'] = '"' . $bin->lineHighlight() . '"';
	else
		$defaults['highlight'] = 0; 

	if($CONFIG['pb_private'])
		$defaults['privacy'] = 1;
	else
		$defaults['privacy'] = 0;

	if($CONFIG['pb_lifespan'])
	{
		$defaults['lifespan'] = "{ ";

		foreach($CONFIG['pb_lifespan'] as $span)
		{
			$key = array_keys($CONFIG['pb_lifespan'], $span);
			$key = $key[0];
			$defaults['lifespan'] .= ' "' . $key . '": "' 
				. $bin->event(time() - ($span * 24 * 60 * 60), TRUE) 
				. '"' . ",\n";
		}

		$selecter = '/"0 seconds"/';
		$replacer = '"Never"';
		$defaults['lifespan'] = preg_replace($selecter, $replacer, 
			$defaults['lifespan'], 1);

		$defaults['lifespan'] = substr($defaults['lifespan'], 0, -2) . "\n";

		$defaults['lifespan'] .= " }";
	} else
		$defaults['lifespan'] = '{ "0": "Never" }';

	$defaults['ex_ext'] = '"' 
		. implode(", ", $CONFIG['pb_image_extensions']) . '"';

	$defaults['ex_url'] = '"' . $bin->linker('[id]') . '"';

	$defaults['title'] = '"' . $bin->setTitle($CONFIG['pb_name']) . '"';

	$defaults['max_paste_size'] = $CONFIG['pb_max_bytes'];

	$defaults['author'] = '"' . $db->dirtyHTML($CONFIG['pb_author']) . '"';


	$JSON = $callback[0] . '
		{
			"name": ' . $defaults['title'] . ',
			"url": "' . $bin->linker() . '",
			"author": ' . $defaults['author'] . ',
			"text": 1,
			"max_paste_size": ' . $defaults['max_paste_size'] . ',
			"editing":  ' . $defaults['editing'] . ',
			"passwords": ' . $defaults['passwords'] . ',
			"api": ' . $defaults['api'] . ',
			"api_adaptor": ' . $defaults['api_adaptor'] . ',
			"clipboard": ' . $defaults['clipboard'] . ',
			"images": ' . $defaults['images'] . ',
			"image_extensions": ' . $defaults['ex_ext'] . ',
			"image_download": ' . $defaults['image_download'] . ',
			"url_redirection": ' . $defaults['url']. ',
			"jQuery": ' . $defaults['ajax'] . ',
			"syntax": ' . $defaults['syntax'] . ',
			"line_highlight": ' . $defaults['highlight'] . ',
			"url_format": ' . $defaults['ex_url'] . ',
			"lifespan": ' . $defaults['lifespan'] . ',
			"privacy": ' . $defaults['privacy'];

	print_r($JSON);
	die('	}' . $callback[1]);
}

if($requri == "api")
{
	$acceptTokens = $bin->token();

	if(!$CONFIG['pb_api'] && !in_array($_POST['ajax_token'], $acceptTokens))
		die('	{
			"id": 0,
			"url": "' . $bin->linker() . '",
			"error": "E0x",
			"message": "API Disabled!"
			}');


	$bin->cleanUp($CONFIG['pb_recent_posts']);

	if(!isset($reqhash))
	{
		if(@$_POST['email'] != "")
			$result = array('error' => '"E01c"', 
				'message' => "Spam protection activated.");

		$pasteID = $bin->generateID();
		$imageID = $pasteID;

		if($CONFIG['pb_encrypt_pastes'] && @$_POST['encryption'])
		{
			$encryption = $bin->encrypt($CONFIG['pb_encryption_checkphrase'], 
				$_POST['encryption']);
			$imageID = md5($imageID . $bin->generateID()) . "_";
		} else
			$encryption = FALSE;

		if(@$_POST['urlField'])
			$postedURL = htmlspecialchars($_POST['urlField']);
		elseif(preg_match('/^((ht|f)(tp|tps)|mailto|irc|skype|'
			. 'git|svn|cvs|aim|gtalk|feed):/', @$_POST['pasteEnter'] ?? '') 
			&& count(explode("\n", $_POST['pasteEnter'])) < 2)
			$postedURL = htmlspecialchars($_POST['pasteEnter']);
		else
			$postedURL = NULL;

		$requri = @$_POST['parent'];

		$imgHost = @$_POST['imageUrl'];

		$_POST['pasteEnter'] = @$_POST['pasteEnter'];

		$exclam = NULL;

		if(!($_POST['lifespan'] ?? 0))
			$_POST['lifespan'] = 0;

		if($postedURL != NULL)
		{
			$_POST['pasteEnter'] = $postedURL;
			$exclam = "!";
			$postedURLInfo = pathinfo($postedURL);

			if($CONFIG['pb_url'])
				$_FILES['pasteImage'] = NULL;
		}

		$imageUpload = FALSE;
		$uploadAttempt = FALSE;

		if(strlen((@$_FILES['pasteImage']['name'] ?? '')) > 4 && $CONFIG['pb_images'])
	  	{
			$imageUpload = $db->uploadFile($_FILES['pasteImage'], $imageID);

			if($imageUpload != FALSE) 
				$postedURL = NULL;

			$uploadAttempt = TRUE;
		}

                if(empty($postedURLInfo['extension'])) {
                        $postedURLInfo['extension'] = '';
                }

		if(in_array(strtolower($postedURLInfo['extension']), 
			$CONFIG['pb_image_extensions']) && $CONFIG['pb_images'] 
			&& $CONFIG['pb_download_images'] && !$imageUpload) 
		{
			$imageUpload = $db->downTheImg($postedURL, $imageID);

			if($imageUpload != FALSE) 
			{
				$postedURL = NULL;
				$exclam = NULL;
			}

			$uploadAttempt = TRUE;
		}

		if($imgHost)
		{
			$imgHostInfo = pathinfo($imgHost);
			$_POST['pasteEnter'] = $imgHost;

			if(in_array(strtolower($imgHostInfo['extension']), 
				$CONFIG['pb_image_extensions']) && $CONFIG['pb_images'] 
				&& $CONFIG['pb_download_images']) 
			{
				$imageUpload = $db->downTheImg($imgHost, $imageID);

				if($imageUpload != FALSE) 
				{
					$postedURL = NULL;
					$exclam = NULL;
				}

				$uploadAttempt = TRUE;
			}
		}

		if(!$imageUpload && !$uploadAttempt)
			$imageUpload = TRUE;


		if(@$_POST['pasteEnter'] == NULL
			&& strlen(@$_FILES['pasteImage']['name'] ?? '') > 4
			&& $CONFIG['pb_images'])
			$_POST['pasteEnter'] = "Image file ("
				. $_FILES['pasteImage']['name'] . ") uploaded...";

		if(!$CONFIG['pb_url'])
			$exclam = NULL;

		if(!$CONFIG['pb_url'])
			$postedURL = NULL;

		if($bin->highlight() && ($_POST['highlighter'] ?? 0) != "plaintext" 
			&& ($_POST['highlighter'] ?? 0) != NULL)
		{
			$geshi->set_language($_POST['highlighter'] ?? 0);
			$geshi->set_source($bin->noHighlight(@$_POST['pasteEnter']));
			$geshi->highlight_lines_extra($bin->highlightNumbers(
				@$_POST['pasteEnter']));
			$geshiCode = $geshi->parse_code();
			$geshiStyle = $geshi->get_stylesheet();
		} else {
			$geshiCode = NULL;
			$geshiStyle = NULL;
		}
		
		$paste = array(
			'ID' => $pasteID,
			'Subdomain' => $bin->db->config['subdomain'],
			'Author' => $bin->checkAuthor(@$_POST['author']),
			'IP' => $_SERVER['REMOTE_ADDR'],
			'Image' => $imageUpload,
			'ImageTxt' => "Image file (" 
				. @$_FILES['pasteImage']['name'] . ") uploaded...",
			'URL' => $postedURL,
			'Lifespan' => $_POST['lifespan'],
			'Protect' => ($_POST['privacy'] ?? 0),
			'Encrypted' => $encryption,
			'Syntax' => ($_POST['highlighter'] ?? 0),
			'Parent' => $requri,
			'Content' => @$_POST['pasteEnter'],
			'GeSHI' => $geshiCode,
			'Style' => $geshiStyle
		);

		if($encryption)
		{
			$paste['Content'] = $bin->encrypt($paste['Content'], 
				$_POST['encryption']);

			if(strlen($paste['GeSHI']) > 1)
				$paste['GeSHI'] = $bin->encrypt($paste['GeSHI'], 
					$_POST['encryption']);

			if(strlen($paste['Image']) > 1)
				$paste['Image'] = $bin->encrypt($paste['Image'], 
					$_POST['encryption']);
		}
		
		if(@$_POST['pasteEnter'] == @$_POST['originalPaste'] 
			&& strlen($_POST['pasteEnter'] ?? '') > 10)
		{
			$result = array('ID' => 0, 'error' => '"E01c"', 
				'message' => 
				"Please don't just repost what has already been said!");
			$JSON = '{
				"id": ' . $result['ID'] . ',
				"url": "' . $bin->linker($paste['ID']) . $exclam . '",
				"error": ' . $result['error'] . ',
				"message": "' . $result['message'] . '"';

			print_r($JSON);
			die(' }');
		}

		if(strlen(@$_POST['pasteEnter'] ?? '') > 10 && $imageUpload 
			&& mb_strlen($paste['Content']) <= $CONFIG['pb_max_bytes'] 
			&& $db->insertPaste($paste['ID'], $paste))
			$result = array('ID' => '"' . $paste['ID'] . '"', 'error' => '0', 
				'message' => "Success!");
		else {
			if(strlen(@$_FILES['pasteImage']['name'] ?? '') > 4 
				&& $_SERVER['CONTENT_LENGTH'] > $CONFIG['pb_image_maxsize'] 
				&& $CONFIG['pb_images'])
				$result = array('ID' => 0, 'error' => '"E02b"', 
					'message' => "File is too big.");
			elseif(strlen(@$_FILES['pasteImage']['name'] ?? '') > 4 
				&& $CONFIG['pb_images'])
				$result = array('ID' => 0, 'error' => '"E02a"', 
					'message' => "Invalid file format.");
			elseif(strlen(@$_FILES['pasteImage']['name'] ?? '') > 4 
				&& !$CONFIG['pb_images'])
				$result = array('ID' => 0, 'error' => '"E02d"', 
					'message' => "Image hosting disabled.");
			else
				$result = array('ID' => 0, 'error' => '"E01a"', 
					'message' => "Invalid POST request."
					. " Pasted text must be between 10 characters and " 
					. $bin->humanReadableFilesize($CONFIG['pb_max_bytes']));
		}


		$JSON = '{ 
			"id": ' . $result['ID'] . ',
			"url": "' . $bin->linker($paste['ID']) . $exclam . '",
			"error": ' . $result['error'] . ',
			"message": "' . $result['message'] . '"';

		print_r($JSON);
		die(' }');

	} else {
		if($reqhash == "recent")
		{
			$recentPosts = $bin->getLastPosts($CONFIG['pb_recent_posts']);
			$JSON = '{ "recent": [';

			if(count($recentPosts) > 0)
			{
				foreach($recentPosts as $paste)
					$JSON .= '{ "id": "' . $paste['ID'] . '", "author": "' 
						. $paste['Author'] . '", "datetime": ' 
						. $paste['Datetime'] . ' }';
			}

			print_r($JSON);
			die('] }');
		}

		if($pasted = $db->readPaste($reqhash))
		{
			if($db->dbt == "mysql")
				$pasted = $pasted[0];
						
			if($pasted['Encrypted'] != NULL && !@$_POST['decrypt_phrase'])
			{
				$JSON = '{ 
					"id": 0,
					"url": "' . $bin->linker($reqhash) . '",
					"author": 0,
					"datetime": 0,
					"protection": 0,
					"syntax": 0,
					"parent": 0,
					"image": 0,
					"image_text": 0,
					"link": 0,
					"lifespan": 0,
					"data": "Encrypted pastes cannot be sent over API!",
					"data_html": "' . $db->dirtyHTML("<!-- Encrypted pastes"
						. " cannot be sent over API!  -->") . '",
					"geshi": 0,
					"style": 0';
	
				print_r($JSON);
				die(' }');
			} else
				$pasted['Encrypted'] = NULL;

			if(strlen($pasted['Image']) > 3)
				$pasted['Image_path'] = $bin->linker() 
					. $db->setDataPath($pasted['Image']);

			$JSON = '{ 
				"id": "' . $pasted['ID'] . '",
				"url": "' . $bin->linker($pasted['ID']) . '",
				"author": "' . $pasted['Author'] . '",
				"datetime": ' . $pasted['Datetime'] . ',
				"protection": ' . $pasted['Protection'] . ',
				"syntax": "' . $pasted['Syntax'] . '",
				"parent": "' . $pasted['Parent'] . '",
				"image": "' . $pasted['Image_path'] . '",
				"image_text": "' . $pasted['ImageTxt'] . '",
				"link": "' . $pasted['URL'] . '",
				"lifespan": ' . $pasted['Lifespan']. ',
				"data": "' . urlencode($db->dirtyHTML($pasted['Data'])) . '",
				"geshi": "' . urlencode($pasted['GeSHI']) . '",
				"style": "' . urlencode($pasted['Style']) . '"';

			print_r($JSON);
			die(' }');
		} else {
			$JSON = '{ 
				"id": 0,
				"url": "' . $bin->linker($reqhash) . '",
				"author": 0,
				"datetime": 0,
				"protection": 0,
				"syntax": 0,
				"parent": 0,
				"image": 0,
				"image_text": 0,
				"link": 0,
				"lifespan": 0,
				"data": "This paste has either expired or doesn\'t exist!",
				"data_html": "' . $db->dirtyHTML("<!-- This paste has either"
					. " expired or doesn't exist!  -->") . '",
				"geshi": 0,
				"style": 0';

			print_r($JSON);
			die(' }');
		}
	}


}

if($requri != "install" && $requri != NULL 
	&& $bin->checkIfRedir($requri) != false && substr($requri, -1) != "!" 
	&& !($_POST['adminProceed'] ?? ''))
{
	header("Location: " . $bin->checkIfRedir($requri));
	die("This is a URL/Mailto forward holding page!");
}

if($requri != "install" && $requri != NULL && substr($requri, -1) != "!" 
	&& !($_POST['adminProceed'] ?? '') && $reqhash == "raw")
{
	if($pasted = $db->readPaste($requri))
	{
		if($db->dbt == "mysql")
			$pasted = $pasted[0];

		if(strlen($pasted['Image']) > 3)
			header("Location: " . $bin->linker() 
				. $db->setDataPath($pasted['Image']));

		header("Content-Type: text/plain; charset=utf-8");
		die($db->rawHTML($bin->noHighlight($pasted['Data'])));
	} else
		die('There was an error!');
}

if($requri != "install" && $requri != NULL && substr($requri, -1) != "!" 
	&& !($_POST['adminProceed'] ?? '') && $reqhash == "download")
{
	if($pasted = $db->readPaste($requri))
    {
    	if($db->dbt == "mysql")
			$pasted = $pasted[0];

		if(strlen($pasted['Image']) > 3)
		{
			$imageServe = $db->setDataPath($pasted['Image']);
			$imgFileInfo = pathinfo($imageServe);
			if($imgFileInfo['extension'] == "jpg")
				$imgFileInfo['extension'] = "jpeg";

			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0,"
				. " pre-check=0");
			header("Cache-Control: private", false);
			header("Content-Type: image/" . $imgFileInfo['extension']);
			header("Content-Disposition: attachment; filename=" . $requri 
				. "." . str_replace("jpeg", "jpg", $imgFileInfo['extension']));
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: " . filesize($imageServe));

			readfile($imageServe);

			die();	
		}

		header("Content-Type: text/plain; charset=utf-8");
		header("Content-Disposition: attachment; filename=" 
			. $requri . ".txt");
		die($db->rawHTML($bin->noHighlight($pasted['Data'])));
	} else
		die('There was an error!');
}

$pasteinfo = array();
if($requri != "install")
	$bin->cleanUp($CONFIG['pb_recent_posts']);

?>
<!DOCTYPE html> 
<html lang="en">
	<head>
		<meta charset="utf-8" />
		<title><?php echo $bin->setTitle($CONFIG['pb_name']); ?> &raquo; <?php echo $bin->titleID($requri); ?></title>
		<link rel="icon" type="image/vnd.microsoft.icon" href="favicon.ico" />
		<link rel="icon" type="image/png" href="favicon.png" />
		<meta name="generator" content="Knoxious Pastebin">
		<meta name="Description" content="A quick, simple, multi-purpose pastebin." />	
		<meta name="Keywords" content="simple quick pastebin image hosting linking embedding url shortening syntax highlighting" />
		<meta name="Robots" content="<?php echo $bin->robotPrivacy($requri); ?>" /> 
		<meta name="Author" content="Xan Manning, xan-manning.co.uk" />
		<meta name="viewport" content="width=extend-to-zoom, initial-scale=1.0, user-scalable=yes" />
		<?php
			if($bin->styleSheet())
				echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"" . $CONFIG['pb_style'] . "\" media=\"screen, print\" />";
			else {
		?>
		<style type="text/css">
				body { background: #fff; font-family: Arial, Helvetica, sans-serif; font-size: 12px; }
				h2 { font-size: 15px; }
				a { color: #336699; }
				img { border: none; }
				pre { display: inline; font-family: inherit; white-space: pre-wrap; } 
				.success { background-color: #AAFFAA; border: 1px solid #00CC00; font-weight: bolder; text-align: center; padding: 2px; color: #000000; margin-top: 3px; margin-bottom: 3px; }
				.warn { background-color: #FFFFAA; border: 1px solid #CCCC00; font-weight: bolder; text-align: center; padding: 2px; color: #000000; margin-top: 3px; margin-bottom: 3px; }
				.error { background-color: #FFAAAA; border: 1px solid #CC0000; font-weight: bolder; text-align: center; padding: 2px; color: #000000; margin-top: 3px; margin-bottom: 3px; }
				.confirmURL { border-bottom: 1px solid #CCCCCC;  text-align: center; font-size: medium; }
				.alternate { background-color: #F3F3F3; }
				.copyText { color: #336699; text-decoration: underline; cursor: pointer; cursor: hand; }
				._clipboardBar { text-align: right; }
				.plainText { font-family: Arial, Helvetica, sans-serif; border: none; list-style-type: none; margin-bottom: 25px; }
				.monoText { font-family:"Courier New",Courier,mono; list-style-type: decimal; }
				.pastedImage { max-width: 500px; height : auto; }
				.pastedImage { width: auto; max-height : 500px; }
				.infoMessage { padding: 25px; font-size: medium; max-width: 800px; }
				.lineHighlight { background-color: #FFFFAA; font-weight: bolder; color: #000000; }
				.pasteEnterLabel { width: 80%; display: block; }
				.resizehandle {	background: #F0F0F0 scroll 45%; cursor: s-resize; text-align: center; color: #AAAAAA; height: 16px; width: 100%; } 
				#newPaste { text-align: center; border-bottom: 1px dotted #CCCCCC; padding-bottom: 10px; }
				#lineNumbers { width: 100%; background-color: #FFFFFF; overflow: auto; padding: 0; margin: 0; }
				div#siteWrapper { width: 100%; margin: 0 auto; }
				div#siteWrapper > #showAdminFunctions { max-width: 800px; margin: 25px; }
				div#siteWrapper > #hiddenAdmin { max-width: 800px; margin: 25px; }
				div#recentPosts { width: 15%; font-size: xx-small; float: left; position: relative; margin-left: 1%; }
				div#pastebin { width: 82%; float: left; position: relative; padding-left: 1%; border-left: 1px dotted #CCCCCC; }
				#pasteEnter { width: 100%; height: 250px; border: 1px solid #CCCCCC; background-color: #FFFFFF; }
				#authorEnter { background-color: #FFFFFF; border-top: 1px solid #CCCCCC; border-bottom: 1px solid #CCCCCC; border-left: none; border-right: none; width: 68%;  }
				#encryption { background-color: #FFFFFF; border-top: 1px solid #CCCCCC; border-bottom: 1px solid #CCCCCC; border-left: none; border-right: none; width: 33%;  }
				#decrypt_phrase { background-color: #FFFFFF; border-top: 1px solid #CCCCCC; border-bottom: 1px solid #CCCCCC; border-left: none; border-right: none; width: 33%;  }
				#subdomain { background-color: #FFFFFF; border-top: 1px solid #CCCCCC; border-bottom: 1px solid #CCCCCC; border-left: none; border-right: none; }
				#subdomain_form { margin-top: 5px; }
				#passForm { padding: 50px; padding-left: 40%; border: 1px solid #CCCCCC; }
				#passForm > label { display: block; }
				#adminPass { background-color: #FFFFFF; border-top: 1px solid #CCCCCC; border-bottom: 1px solid #CCCCCC; border-left: none; border-right: none; width: 100%;  }
				#copyrightInfo { color: #999999; font-size: xx-small; position: fixed; bottom: 0px; right: 10px; padding-bottom: 10px; text-align: left; }
				ul#postList { padding: 0; margin-left: 0; list-style-type: none; margin-bottom: 50px; }
				#adminAction { width: 100%; }
				#urlField { background-color: #FFFFFF; border-top: 1px solid #CCCCCC; border-bottom: 1px solid #CCCCCC; border-left: none; border-right: none; height: 20px; width: 100%; }
				#emphasizedURL	{ font-size: x-large; width: 100%; overflow: auto; font-style: italic; padding: 5px; }
				#showAdminFunctions { font-size: xx-small; font-weight: bold; text-align: center; }
				#hiddenAdmin { display: none; padding-right: 10px; }
				#instructions { display: none; }
				#tagline { margin-bottom: 10px; }
				#subdomainForm { display: none; }
				#serviceList li { margin-top: 7px; margin-bottom: 7px; list-style: square; }
				#authorContainer { width: 48%; float: left; margin-bottom: 10px;  }
				#authorContainerReply { padding-right: 52%; margin-bottom: 10px;  }
				#submitContainer { width: 100%; display: block; }
				#highlightContainer { margin-bottom: 5px; }
				#lifespanContainer { margin-bottom: 5px; }
				#privacyContainer { margin-bottom: 5px; }
				#encryptContainer { margin-bottom: 5px; }
				#highlightContainer > select { width: 33%; background-color: #FFFFFF; border-top: 1px solid #CCCCCC; border-bottom: 1px solid #CCCCCC; border-left: none; border-right: none; }
				#lifespanContainer > select { width: 33%; background-color: #FFFFFF; border-top: 1px solid #CCCCCC; border-bottom: 1px solid #CCCCCC; border-left: none; border-right: none; }
				#privacyContainer > select { width: 33%; background-color: #FFFFFF; border-top: 1px solid #CCCCCC; border-bottom: 1px solid #CCCCCC; border-left: none; border-right: none; }
				#pasteImage { background-color: #FFFFFF; border-top: 1px solid #CCCCCC; border-bottom: 1px solid #CCCCCC; border-left: none; border-right: none; padding: 2px; }
				#highlightContainer > label { width: 200px; display: block; }
				#lifespanContainer > label { width: 200px; display: block; }
				#privacyContainer > label { width: 200px; display: block; }
				#encryptContainer > label { width: 200px; display: block; }
				#fileUploadContainer { width: 48%; float: left; margin-bottom: 10px; }
				#imageContainer { text-align: center; padding: 10px; }
				#styleBar { text-align: left; position: relative; float: left; width: 48%; }
				#retrievedPaste { width: 100%; position: relative; padding: 0; margin: 0; margin-top: 10px; margin-bottom: 10px; border: 1px solid #CCCCCC; }
				#_clipboard_replace { visibility: hidden; }
				#_clipboardURI_replace { visibility: hidden; }
				#_copyText { visibility: hidden; }
				#_copyURL { visibility: hidden; }
			
			@media print {
				body { background: #fff; font-family: Arial, Helvetica, sans-serif; font-size: 10pt; }
				pre { white-space: pre-wrap; display: inline; }
				li { padding: 0px; margin: 0px; }
				a { color: #336699; }
				img { width: auto; max-width: 100%; }
				#siteWrapper { width: auto; }
				#recentPosts { display: none; }
				#copyrightInfo { position: relative; top: 0px; right: 0px; width: auto; padding: 1%; text-align: right; }
				#retrievedPaste { border: none; }
				#lineNumbers { max-height: none; width: auto; }
				#pasteBin { width: auto; border: none; }
				#formContainer { display: none; }
				#styleBar { display: none; } 
				#_clipboard_replace { display: none; }
				#_clipboardURI_replace { display: none; }
				#_clipboard { display: none; }
				#_clipboardURI {  display: none; }
				#_copyText { display: none; }
				#_copyURL { display: none; }
				._clipboardBar { display: none; width: auto; }
				.copyText { display: none; }
				.spacer { display: none; }
				.alternate { background-color: #F3F3F3; }
				.lineHighlight { background-color: #FFFFAA; font-weight: bolder; color: #000000; }
			}
		</style>
		<?php
			}

			/* begin JS */
			
			$_commonJS = "/* AJAXIAN */
var tab = \"    \";
       
function catchTab(evt) {
    var t = evt.target;
    var ss = t.selectionStart;
    var se = t.selectionEnd;
 
    if (evt.keyCode == 9) {
        evt.preventDefault();
               
        if (ss != se && t.value.slice(ss,se).indexOf(\"\\n\") != -1) {
            var pre = t.value.slice(0,ss);
            var sel = t.value.slice(ss,se).replace(/\\n/g,\"\\n\"+tab);
            var post = t.value.slice(se,t.value.length);
            t.value = pre.concat(tab).concat(sel).concat(post);
                   
            t.selectionStart = ss + tab.length;
            t.selectionEnd = se + tab.length;
        }
               
        else {
            t.value = t.value.slice(0,ss).concat(tab).concat(t.value.slice(ss,t.value.length));
            if (ss == se) {
                t.selectionStart = t.selectionEnd = ss + tab.length;
            }
            else {
                t.selectionStart = ss + tab.length;
                t.selectionEnd = se + tab.length;
            }
        }
    }
           
   else if (evt.keyCode==8 && t.value.slice(ss - 4,ss) == tab) {
        evt.preventDefault();
               
        t.value = t.value.slice(0,ss - 4).concat(t.value.slice(ss,t.value.length));
        t.selectionStart = t.selectionEnd = ss - tab.length;
    }
           
    else if (evt.keyCode==46 && t.value.slice(se,se + 4) == tab) {
        evt.preventDefault();
             
        t.value = t.value.slice(0,ss).concat(t.value.slice(ss + 4,t.value.length));
        t.selectionStart = t.selectionEnd = ss;
    }

    else if (evt.keyCode == 37 && t.value.slice(ss - 4,ss) == tab) {
        evt.preventDefault();
        t.selectionStart = t.selectionEnd = ss - 4;
    }
    else if (evt.keyCode == 39 && t.value.slice(ss,ss + 4) == tab) {
        evt.preventDefault();
        t.selectionStart = t.selectionEnd = ss + 4;
    }
}

function showAdminTools(hideMe){
	document.getElementById('showAdminFunctions').style.display = \"none\";
	document.getElementById('hiddenAdmin').style.display = \"block\";
	return false;
}

function showInstructions(){
	document.getElementById('showInstructions').style.display = \"none\";
	document.getElementById('instructions').style.display = \"block\";
	return false;
}

function showSubdomain(){
	document.getElementById('showSubdomain').style.display = \"none\";
	document.getElementById('subdomainForm').style.display = \"block\";
	return false;
}

function submitPaste(targetButton) {
	var disabledButton = document.createElement('input');
	var parentContainer = document.getElementById('submitContainer');
	disabledButton.setAttribute('value', 'Posting...');
	disabledButton.setAttribute('type', 'button');
	disabledButton.setAttribute('disabled', 'disabled');
	disabledButton.setAttribute('id', 'dummyButton');
	targetButton.style.display = \"none\";
	parentContainer.appendChild(disabledButton);
	return true;
}";

			if($bin->jQuery())
			{ 
				echo "<script type=\"text/javascript\" src=\"" . $CONFIG['pb_jQuery'] . "\"></script>";

		?>
			<script type="text/javascript">
				var pasteEnterH;

				$.fn.resizehandle = function() {
 					return this.each(function() {
    						var me = $(this);
    							me.after(
      							$('<div class="resizehandle"><?php if(!$bin->stylesheet()) echo "==="; ?></div>').bind('mousedown', function(e) {
        							var h = me.height();
        							var y = e.clientY;
        							var moveHandler = function(e) {
          								me.height(Math.max(20, e.clientY + h - y));
       							 	};
        							var upHandler = function(e) {
          								$('html')
          								.unbind('mousemove',moveHandler)
          								.unbind('mouseup',upHandler);
        							};
        							$('html')
        							.bind('mousemove', moveHandler)
        							.bind('mouseup', upHandler);
      								})
    							);
  						});
				}
				$(document).ready(function(){
  					$('#lineNumbers li:nth-child(even)').addClass('alternate');
					$('a[href][rel*=external]').each(function(i){this.target = "_blank";});
					<?php if(!$bin->styleSheet()){ ?>
					$("#foundURL").show().attr('class', 'success').css( { opacity: 0 } );
					<?php } else { ?>
					$("#foundURL").show().css( { opacity: 0 } );
					<?php } ?>

					pasteEnterH = $('#pasteEnter').height();
					if(!$.browser.webkit)
						$("textarea").resizehandle();

				});
				
				<?php if($CONFIG['pb_url']) { ?>
				function checkIfURL(checkMe){
					var checking = checkMe.value;
					var regExpression = new RegExp();
					regExpression.compile('^[A-Za-z]+://[A-Za-z0-9-_]+\\.[A-Za-z0-9-_%&\?\/\!.=]+$');
					if(regExpression.test(checking)){
						$("#pasteEnter").animate({ height: "20px"  }, 500, function() { $("#pasteEnter").attr("id", "urlField"); $("#pasteEnter").attr("name", "urlField"); $("#foundURL").animate({ opacity: 1 }, 250); $("#fileUploadContainer").animate({ opacity: 0 }, 250); $("#highlightContainer").animate({ opacity: 0 }, 250); });
						return false;
					}
					else {
						if(checkMe.id != "pasteEnter")
							$("#urlField").animate({ height: pasteEnterH + "px"  }, 500, function() { $("#urlField").attr("id", "pasteEnter"); $("#urlField").attr("name", "pasteEnter"); $("#foundURL").animate({ opacity: 0 }, 250); $("#fileUploadContainer").animate({ opacity: 1 }, 250); $("#highlightContainer").animate({ opacity: 1 }, 250); });
						return false;
					}
				}
				<?php } else { ?>
				function checkIfURL(checkMe){
					return false;
				}
				<?php } ?>


				function showAdminTools(hideMe){
					$('#showAdminFunctions').hide(500);
					$('#hiddenAdmin').slideDown(500);
					return false;
				}

				function showInstructions(){
					$('#showInstructions').hide(500);
					$('#instructions').slideDown(500);
					return false;
				}

				function showSubdomain(){
					$('#showSubdomain').hide(500);
					$('#subdomainForm').slideDown(500);
					return false;
				}


				/* AJAXIAN */

				var tab = "	";
       
				function catchTab(evt) {
				    var t = evt.target;
				    var ss = t.selectionStart;
				    var se = t.selectionEnd;
				 
				    if (evt.keyCode == 9) {
					evt.preventDefault();
					       
					if (ss != se && t.value.slice(ss,se).indexOf("\n") != -1) {
					    var pre = t.value.slice(0,ss);
					    var sel = t.value.slice(ss,se).replace(/\n/g,"\n"+tab);
					    var post = t.value.slice(se,t.value.length);
					    t.value = pre.concat(tab).concat(sel).concat(post);
						   
					    t.selectionStart = ss + tab.length;
					    t.selectionEnd = se + tab.length;
					}
					       
					else {
					    t.value = t.value.slice(0,ss).concat(tab).concat(t.value.slice(ss,t.value.length));
					    if (ss == se) {
						t.selectionStart = t.selectionEnd = ss + tab.length;
					    }
					    else {
						t.selectionStart = ss + tab.length;
						t.selectionEnd = se + tab.length;
					    }
					}
				    }
					   
				   else if (evt.keyCode==8 && t.value.slice(ss - 4,ss) == tab) {
					evt.preventDefault();
					       
					t.value = t.value.slice(0,ss - 4).concat(t.value.slice(ss,t.value.length));
					t.selectionStart = t.selectionEnd = ss - tab.length;
				    }
					   
				    else if (evt.keyCode==46 && t.value.slice(se,se + 4) == tab) {
					evt.preventDefault();
					     
					t.value = t.value.slice(0,ss).concat(t.value.slice(ss + 4,t.value.length));
					t.selectionStart = t.selectionEnd = ss;
				    }

				    else if (evt.keyCode == 37 && t.value.slice(ss - 4,ss) == tab) {
					evt.preventDefault();
					t.selectionStart = t.selectionEnd = ss - 4;
				    }
				    else if (evt.keyCode == 39 && t.value.slice(ss,ss + 4) == tab) {
					evt.preventDefault();
					t.selectionStart = t.selectionEnd = ss + 4;
				    }
				}

				function submitPaste(targetButton){
					$('.error').remove();
					var buttonElement = $(targetButton);
					var parentForm = $('#pasteForm');
					<?php if($requri) { ?>
					var originalPaste = $('#originalPaste').attr('value');
					var parentThread = $('#parentThread').attr('value');
					<?php } else { ?>
					var originalPaste = "";
					var parentThread = "";
					<?php } 
					if(!$CONFIG['pb_images'] || $requri){
						?>
						var pasteImage = "";
						<?php }
					else {
						?>
						var pasteImage = $('#pasteImage').attr('value');
						<?php 
					}
					?>

					var dataString = $('#pasteForm').serialize();

					if(pasteImage == ""){
						buttonElement.attr('value', 'Posting...').attr('disabled', 'disabled');
						$.ajax({  
      							type: "POST",  
      							url: "<?php echo $bin->linker('api'); ?>",  
      							data: dataString,
							dataType: "json", 
      							success: function(msg) {
								$('#result').attr('class', 'result');
								if(msg.error != 0)
									{
										buttonElement.removeAttr('disabled');
										buttonElement.attr('value', 'Submit your Paste');
										$('#result').prepend('<div class="error" id="' + msg.error + '">' + msg.message + '</div>');
									} else
										{
											$('#result').prepend('<div class="success"><a href="' + msg.url + '">Redirecting</a>...</div>');
											window.location = msg.url;
										}

								window.scrollTo(0,0);
     							 },
							error: function(msg) {
								buttonElement.removeAttr('disabled');
								buttonElement.attr('value', 'Submit your Paste');
								$('#result').prepend('<div class="error">Something went wrong</div><div class="confirmURL">' + msg + '</div>');
								window.scrollTo(0,0);
							} 
    						});
					return false;
					} else
						{
							buttonElement.css({ display: "none" });
							buttonElement.parent().append('<input type="button" name="blank" id="dummyButton" value="Posting..." disabled="disabled" />');
							/* http://www.bennadel.com/blog/1244-ColdFusion-jQuery-And-AJAX-File-Upload-Demo.htm */
							var strName = ("image" + (new Date()).getTime());
							var iFrame = $("<iframe name=\"" + strName + "\" src=\"about:blank\" />");
							iFrame.css("display", "none");
							$("body").append(iFrame);
							parentForm.attr("action", "<?php echo $bin->linker('api'); ?>")
							.attr("method", "post")
							.attr("enctype", "multipart/form-data")
							.attr("encoding", "multipart/form-data")
							.attr("target", strName);
							iFrame.load(
								function(objEvent){
									$('#result').attr('class', 'result');
									var objUploadBody = window.frames[ strName ].document.getElementsByTagName( "body" )[ 0 ];
									var iBody = $( objUploadBody );
									var objData = eval( "(" + iBody.html() + ")" );
									if(objData.error != 0)
										{
											buttonElement.css({ display: "block" });
											$('#dummyButton').remove();
											$('#result').prepend('<div class="error" id="' + objData.error + '">' + objData.message + '</div>');
										} else
											{
												$('#result').prepend('<div class="success"><a href="' + objData.url + '">Redirecting</a>...</div>');
												window.location = objData.url;
											}
									setTimeout(function(){ iFrame.remove(); }, 100);
									window.scrollTo(0,0);
							});
						}
				}

			</script>
		<?php }

			if($bin->_clipboard())
				{
					?>
<script type="text/javascript" src="<?php echo $db->config['cbdir'] . "/swfobject.js"; ?>"></script>
<script type="text/javascript">
function findPosX(obj) {
	var curleft = 0;
	if(obj.offsetParent)
		while(1) {
          		curleft += obj.offsetLeft;
			if(!obj.offsetParent)
            			break;
          		obj = obj.offsetParent;
        	}
    	else if(obj.x)
        	curleft += obj.x;
    	return curleft;
}

function findPosY(obj) {
	var curtop = 0;
    	if(obj.offsetParent)
        	while(1) {
          		curtop += obj.offsetTop;
          		if(!obj.offsetParent)
            			break;
          		obj = obj.offsetParent;
        	}
    	else if(obj.y)
        	curtop += obj.y;
    	return curtop;
}

function findWidth(obj) {
	var w = obj.width;
	if(!w)
		w = obj.offsetWidth;
	return w;
}
function findHeight(obj) {
	var h = obj.height;
	if(!h)
		h = obj.offsetHeight;
	return h;
}
function formSend(id, target) {   
	var originalText = eval(target).value;
	document.getElementById(id).textToCopy(originalText);    
}

function setCopyVars(){
	document.pasteForm.originalPaste.value = document.pasteForm.pasteEnter.value;
}

function flashReady(id, target) {
		setCopyVars();
		formSend(id, target);
	<?php
		if(!@$_POST['submit']) { ?>
		document.getElementById("_copyText").style.visibility = "visible";
		setTimeout("document.getElementById('_copyText').style.display = 'inline'", 500);
	<?php	} ?>
		document.getElementById("_copyURL").style.visibility = "visible";
		setTimeout("document.getElementById('_copyURL').style.display = 'inline'", 500);
}

function sizeFlash() {

	var divWidth = findWidth(document.getElementById("_copyText"));
	var divHeight = findHeight(document.getElementById("_copyText"));
	var divWidthURL = findWidth(document.getElementById("_copyURL"));
	var divHeightURL = findHeight(document.getElementById("_copyURL"));

	var flashvars = {
	  id: "_clipboard",
	  theTarget: "document.pasteForm.originalPaste",
	  width: divWidth,
	  height: divHeight
	};
	var params = {
	  menu: "false",
	  wmode: "transparent",
	  allowScriptAccess: "always"
	};
	var attributes = {
	  id: "_clipboard",
	  name: "_clipboard"
	};

	var flashvarsURI = {
	  id: "_clipboardURI",
	  theTarget: "document.pasteForm.thisURI",
	  width: divWidthURL,
	  height: divHeightURL
	};
	var paramsURI = {
	  menu: "false",
	  wmode: "transparent",
	  allowScriptAccess: "always"
	};
	var attributesURI = {
	  id: "_clipboardURI",
	  name: "_clipboardURI"
	};

	swfobject.embedSWF("<?php echo $CONFIG['pb_clipboard']; ?>", "_clipboard_replace", divWidth, divHeightURL, "10.0.0", "expressInstall.swf", flashvars, params, attributes);
	swfobject.embedSWF("<?php echo $CONFIG['pb_clipboard']; ?>", "_clipboardURI_replace", divWidthURL, divHeightURL, "10.0.0", "expressInstall.swf", flashvarsURI, paramsURI, attributesURI);

	repositionFlash("_clipboard", "_copyText");
	repositionFlash("_clipboardURI", "_copyURL");
}

<?php if(!$bin->jQuery()){ ?>
<?php if($CONFIG['pb_url']) { ?>
function checkIfURL(checkMe){
	var checking = checkMe.value;
	var regExpression = new RegExp();
	regExpression.compile('^[A-Za-z]+://[A-Za-z0-9-_]+\\.[A-Za-z0-9-_%&\?\/\!.=]+$');
	if(regExpression.test(checking)){
		checkMe.setAttribute("id", "urlField");
		document.getElementById('foundURL').style.display = "block";
		document.getElementById('fileUploadContainer').style.visibility = "hidden";
		document.getElementById('highlightContainer').style.visibility = "hidden";
		return false;
	}
	else {
		if(checkMe.id != "pasteEnter")
			checkMe.setAttribute("id", "pasteEnter");

		document.getElementById('foundURL').style.display = "none";
		document.getElementById('fileUploadContainer').style.visibility = "visible";
		document.getElementById('highlightContainer').style.visibility = "visible";
		return false;
	}
}
<?php } else {?>
function checkIfURL(checkMe){
	return false;
}
<?php } 

echo $_commonJS; ?>

<?php } ?>

function repositionFlash(id, zeTarget) {
	var restyle = document.getElementById(id).style;
	restyle.position = 'absolute';
	restyle.zIndex = 99;
	restyle.left = findPosX(document.getElementById(zeTarget)) + "px";
	restyle.top = findPosY(document.getElementById(zeTarget)) + "px";
	restyle.cursor = "pointer";
	restyle.cursor = "hand";
}
function confirmCopy(id){
	alert("Data has been copied to your clipboard!");
}
</script>
<?php

				} else
					{ if(!$bin->jQuery()) { ?>
<script type="text/javascript">
<?php if($CONFIG['pb_url']) { ?>
function checkIfURL(checkMe){
	var checking = checkMe.value;
	var regExpression = new RegExp();
	regExpression.compile('^[A-Za-z]+://[A-Za-z0-9-_]+\\.[A-Za-z0-9-_%&\?\/\!.=]+$');
	if(regExpression.test(checking)){
		checkMe.setAttribute("id", "urlField");
		document.getElementById('foundURL').style.display = "block";
		document.getElementById('fileUploadContainer').style.visibility = "hidden";
		return true;
	}
	else {
		if(checkMe.id != "pasteEnter")
			checkMe.setAttribute("id", "pasteEnter");

		document.getElementById('foundURL').style.display = "none";
		document.getElementById('fileUploadContainer').style.visibility = "visible";
		return false;
	}
}
<?php } else { ?>
function checkIfURL(checkMe){
	return true;
}
<?php } echo $_commonJS; ?>

</script>
<?php
/* end JS */						}
					}

?>
	</head>
	<body<?php if($bin->_clipboard() && ($requri || @$_POST['submit']) && $requri != "install" && substr($requri, -1) != "!") { echo " onload=\"sizeFlash();\""; } ?>><div id="siteWrapper">
<?php

if($requri != "install" && !$db->connect())
	echo "<div class=\"error\">No database connection could be established - check your config.</div>";
elseif($requri != "install" && $db->connect())
	$db->disconnect();
else
	echo "<!-- No Check is required... -->";

if(@$_POST['adminAction'] == "delete" && $bin->hasher(hash($CONFIG['pb_algo'], 
	@$_POST['adminPass']), $CONFIG['pb_salts']) === $CONFIG['pb_pass'])
{ 
	$db->dropPaste($requri); 
	echo "<div class=\"success\">Paste, " . $requri 
		. ", has been deleted!</div>"; 
	$requri = NULL; 
}

if(@$_POST['subdomain'] && $error_subdomain)
	die("<div class=\"result\"><div class=\"error\">Subdomain invalid or already taken!</div></div></div></body></html>");

if($requri != "install" && @$_POST['submit'])
	{
		$acceptTokens = $bin->token();

		if(@$_POST['email'] != "" || !in_array($_POST['ajax_token'], $acceptTokens))
			die("<div class=\"result\"><div class=\"error\">Spambot detected, I don't like that!</div></div></div></body></html>");

		$pasteID = $bin->generateID();
		$imageID = $pasteID;

		if($CONFIG['pb_encrypt_pastes'] && @$_POST['encryption'])
			{
				$encryption = $bin->encrypt($CONFIG['pb_encryption_checkphrase'], $_POST['encryption']);
				$imageID = md5($imageID . $bin->generateID()) . "_";
			}
		else
			$encryption = FALSE;

		if(@$_POST['urlField'])
			$postedURL = htmlspecialchars($_POST['urlField']);
		elseif(preg_match('/^((ht|f)(tp|tps)|mailto|irc|skype|git|svn|cvs|aim|gtalk|feed):/', @$_POST['pasteEnter']) && count(explode("\n", $_POST['pasteEnter'])) < 2)
			$postedURL = htmlspecialchars($_POST['pasteEnter']);
		else
			$postedURL = NULL;

		$exclam = NULL;

		if($postedURL != NULL)
			{
				$_POST['pasteEnter'] = $postedURL;
				$exclam = "!";
				$postedURLInfo = pathinfo($postedURL);

				if($CONFIG['pb_url'])
					$_FILES['pasteImage'] = NULL;
			}

		$imageUpload = FALSE;
		$uploadAttempt = FALSE;

		if(strlen(@$_FILES['pasteImage']['name']) > 4 && $CONFIG['pb_images']) {
			$imageUpload = $db->uploadFile($_FILES['pasteImage'], $imageID);
			if($imageUpload != FALSE) {
				$postedURL = NULL;
			}
			$uploadAttempt = TRUE;
		}

                if(empty($postedURLInfo['extension'])) {
                        $postedURLInfo['extension'] = '';
                }

		if(in_array(strtolower($postedURLInfo['extension']), $CONFIG['pb_image_extensions']) && $CONFIG['pb_images'] && $CONFIG['pb_download_images'] && !$imageUpload) {
			$imageUpload = $db->downTheImg($postedURL, $imageID);
			if($imageUpload != FALSE) {
				$postedURL = NULL;
				$exclam = NULL;
			}
			$uploadAttempt = TRUE;
		}

		if(!$imageUpload && !$uploadAttempt)
			$imageUpload = TRUE;

		if(@$_POST['pasteEnter'] == NULL && strlen(@$_FILES['pasteImage']['name']) > 4 && $CONFIG['pb_images'] && $imageUpload)
			$_POST['pasteEnter'] = "Image file (" . $_FILES['pasteImage']['name'] . ") uploaded...";

		if(!$CONFIG['pb_url'])
			$exclam = NULL;

		if(!$CONFIG['pb_url'])
			$postedURL = NULL;

		if($bin->highlight() && $_POST['highlighter'] != "plaintext")
			{
				$geshi->set_language($_POST['highlighter']);
				$geshi->set_source($bin->noHighlight(@$_POST['pasteEnter']));
				$geshi->highlight_lines_extra($bin->highlightNumbers(@$_POST['pasteEnter']));
				$geshiCode = $geshi->parse_code();
				$geshiStyle = $geshi->get_stylesheet();
			} else
				{
					$geshiCode = NULL;
					$geshiStyle = NULL;
				}


		$paste = array(
			'ID' => $pasteID,
			'Author' => $bin->checkAuthor(@$_POST['author']),
			'Subdomain' => $bin->db->config['subdomain'],
			'IP' => $_SERVER['REMOTE_ADDR'],
			'Image' => $imageUpload,
			'ImageTxt' => "Image file (" . @$_FILES['pasteImage']['name'] . ") uploaded...",
			'URL' => $postedURL,
			'Syntax' => $_POST['highlighter'],
			'Lifespan' => $_POST['lifespan'],
			'Protect' => $_POST['privacy'],
			'Encrypted' => $encryption,
			'Parent' => $requri,
			'Content' => @$_POST['pasteEnter'],
			'GeSHI' => $geshiCode,
			'Style' => $geshiStyle
		);

		if($encryption)
			{
				$paste['Content'] = $bin->encrypt($paste['Content'], $_POST['encryption']);
				if(strlen($paste['GeSHI']) > 1)
					$paste['GeSHI'] = $bin->encrypt($paste['GeSHI'], $_POST['encryption']);
				if(strlen($paste['Image']) > 1)
					$paste['Image'] = $bin->encrypt($paste['Image'], $_POST['encryption']);
			}
		
		if(@$_POST['pasteEnter'] == @$_POST['originalPaste'] && strlen($_POST['pasteEnter']) > 10)
			die("<div class=\"error\">Please don't just repost what has already been said!</div></div></body></html>");
		
		if(strlen(@$_POST['pasteEnter']) > 10 && $imageUpload && mb_strlen($paste['Content']) <= $CONFIG['pb_max_bytes'] && $db->insertPaste($paste['ID'], $paste))
			{ 
				if($bin->_clipboard())
					die("<div class=\"result\"><div class=\"success\">Your paste has been successfully recorded!</div><div class=\"confirmURL\">URL to your paste is <a href=\"" . $bin->linker($paste['ID']) . $exclam . "\">" . $bin->linker($paste['ID']) . "</a> &nbsp; <span class=\"copyText\" id=\"_copyURL\">Copy URL</span><span id=\"_copyText\" style=\"visibility: hidden;\">&nbsp;</span></div></div><form id=\"pasteForm\" name=\"pasteForm\" action=\"" . $bin->linker($pasted['ID']) . "\" method=\"post\"><input type=\"hidden\" name=\"originalPaste\" id=\"originalPaste\" value=\"" . $bin->linker($paste['ID']) . "\" /><input type=\"hidden\" name=\"thisURI\" id=\"thisURI\" value=\"" . $bin->linker($paste['ID']) . "\" /></form><div class=\"spacer\">&nbsp;</div><div class=\"spacer\"><span id=\"_clipboard_replace\">YOU NEED FLASH!</span> &nbsp; <span id=\"_clipboardURI_replace\">YOU NEED FLASH!</span></div></div></body></html>");
				else
					die("<div class=\"result\"><div class=\"success\">Your paste has been successfully recorded!</div><div class=\"confirmURL\">URL to your paste is <a href=\"" . $bin->linker($paste['ID']) . $exclam . "\">" . $bin->linker($paste['ID']) . "</a></div></div></div></body></html>"); }
		else {
			echo "<div class=\"error\">Hmm, something went wrong.</div>";
			if(strlen(@$_FILES['pasteImage']['name']) > 4 && $_SERVER['CONTENT_LENGTH'] > $CONFIG['pb_image_maxsize'] && $CONFIG['pb_images'])
				echo "<div class=\"warn\">Is the file too big?</div>";
			elseif(strlen(@$_FILES['pasteImage']['name']) > 4 && $CONFIG['pb_images'])
				echo "<div class=\"warn\">File is the wrong extension?</div>";
			elseif(!$CONFIG['pb_images'] && strlen(@$_FILES['pasteImage']['name']) > 4)
				echo "<div class=\"warn\">Nope, we don't host images!</div>";
			else
				echo "<div class=\"warn\">Pasted text must be between 10 characters and " . $bin->humanReadableFilesize($CONFIG['pb_max_bytes']) . "</div>";
		}
	}

if(empty($requri)) {
        $requri = '';
}

if($requri != "install" && $CONFIG['pb_recent_posts'] && substr($requri, -1) != "!")
	{
		echo "<div id=\"recentPosts\" class=\"recentPosts\">";
		$recentPosts = $bin->getLastPosts($CONFIG['pb_recent_posts']);
		echo "<h2 id=\"newPaste\"><a href=\"" . $bin->linker() . "\">New Paste</a></h2><div class=\"spacer\">&nbsp;</div>";
		if($requri || count($recentPosts) > 0)
			if(count($recentPosts) > 0)
				{					
					echo "<h2>Recent Pastes</h2>";	
					echo "<ul id=\"postList\" class=\"recentPosts\">";					
					foreach($recentPosts as $paste_) {
						$rel = NULL;
						$exclam = NULL;
						if($paste_['URL'] != NULL && $CONFIG['pb_url']) {
							$exclam = "!";
							$rel = " rel=\"link\"";
						}

						if(!is_bool($paste_['Image']) && !is_numeric($paste_['Image']) && $paste_['Image'] != NULL && $CONFIG['pb_images']) {
							if($CONFIG['pb_media_warn'])
								$exclam = "!";
							else
								$exclam = NULL;

							$rel = " rel=\"image\"";
						}

						if($paste_['Encrypted'] != NULL && $paste_['URL'] == NULL) {
							$rel = " rel=\"locked\"";
						}
						

						echo "<li id=\"" . $paste_['ID'] . "\" class=\"postItem\"><a href=\"" . $bin->linker($paste_['ID']) . $exclam . "\"" . $rel . ">" . stripslashes($paste_['Author']) . "</a><br />" . $bin->event($paste_['Datetime']) . " ago.</li>";
					}
					echo "</ul>";
				} else
					echo "&nbsp;";
			if($requri)
				{
					echo "<div id=\"showAdminFunctions\"><a href=\"#\" onclick=\"return showAdminTools();\">Show Admin tools</a></div><div id=\"hiddenAdmin\"><h2>Administrate</h2>";
					echo "<div id=\"adminFunctions\">
							<form id=\"adminForm\" action=\"" . $bin->linker($requri) . "\" method=\"post\">
								<label for=\"adminPass\">Password</label><br />
								<input id=\"adminPass\" type=\"password\" name=\"adminPass\" value=\"" . @$_POST['adminPass'] . "\" />
								<br /><br />
								<select id=\"adminAction\" name=\"adminAction\">
									<option value=\"ip\">Show Author's IP</option>
									<option value=\"delete\">Delete Paste</option>
								</select>
								<input type=\"submit\" name=\"adminProceed\" value=\"Proceed\" />
							</form>
						</div></div>";
				}
		echo "</div>";
	} else
		{
			if($requri && $requri != "install" && substr($requri, -1) != "!")
				{
					echo "<div id=\"recentPosts\" class=\"recentPosts\">";
					echo "<h2><a href=\"" . $bin->linker() . "\">New Paste</a></h2><div class=\"spacer\">&nbsp;</div>";
					echo "<div id=\"showAdminFunctions\"><a href=\"#\" onclick=\"return showAdminTools();\">Show Admin tools</a></div><div id=\"hiddenAdmin\"><h2>Administrate</h2>";
					echo "<div id=\"adminFunctions\">
							<form id=\"adminForm\" action=\"" . $bin->linker($requri) . "\" method=\"post\">
								<label for=\"adminPass\">Password</label><br />
								<input id=\"adminPass\" type=\"password\" name=\"adminPass\" value=\"" . @$_POST['adminPass'] . "\" />
								<br /><br />
								<select id=\"adminAction\" name=\"adminAction\">
									<option value=\"ip\">Show Author's IP</option>
									<option value=\"delete\">Delete Paste</option>
								</select>
								<input type=\"submit\" name=\"adminProceed\" value=\"Proceed\" />
							</form>
						</div></div>";
					echo "</div>";
				}
		}

if($requri && $requri != "install" && substr($requri, -1) != "!")
	{
		$pasteinfo['Parent'] = $requri;
		echo "<div id=\"pastebin\" class=\"pastebin\">"
			. "<h1>" .  $bin->setTitle($CONFIG['pb_name'])  . "</h1>" .
			$bin->setTagline($CONFIG['pb_tagline'])
			. "<div id=\"result\"></div>";

		if($pasted = $db->readPaste($requri))
			{
				if($db->dbt == "mysql")
					$pasted = $pasted[0];

				if($pasted['Encrypted'] != NULL && !@$_POST['decrypt_phrase'])
					die("<div class=\"result\"><div class=\"warn\">This paste is password protected!</div><div id=\"passFormContaineContainer\"><form id=\"passForm\" name=\"passForm\" action=\"" . $bin->linker($pasted['ID']) . "\" method=\"post\"><label for=\"decrypt_phrase\">Enter password</label><input type=\"password\" id=\"decrypt_phrase\" name=\"decrypt_phrase\" /> <input type=\"submit\" id=\"decrypt\" name=\"decrypt\" value=\"Unlock\" /></form></div></div></div></body></html>");
				elseif($pasted['Encrypted'] != NULL && @$_POST['decrypt_phrase'] && !$bin->testDecrypt($pasted['Encrypted'], $_POST['decrypt_phrase']))
					die("<div class=\"result\"><div class=\"error\">Password incorrect!</div><div><form id=\"passForm\" name=\"passForm\" action=\"" . $bin->linker($pasted['ID']) . "\" method=\"post\"><label for=\"decrypt_phrase\">Enter password</label><input type=\"password\" id=\"decrypt_phrase\" name=\"decrypt_phrase\" /> <input type=\"submit\" id=\"decrypt\" name=\"decrypt\" value=\"Unlock\" /></form></div></div></div></body></html>");	
				elseif($pasted['Encrypted'] != NULL && @$_POST['decrypt_phrase'] && $bin->testDecrypt($pasted['Encrypted'], $_POST['decrypt_phrase']))
					{
						$pasted['Data'] = $bin->decrypt($pasted['Data'], $_POST['decrypt_phrase']);
						if(strlen($pasted['GeSHI']) > 1)
							$pasted['GeSHI'] = $bin->decrypt($pasted['GeSHI'], $_POST['decrypt_phrase']);
						if(strlen($pasted['Image']) > 1)
							$pasted['Image'] = $bin->decrypt($pasted['Image'], $_POST['decrypt_phrase']);
					}
				else
					$pasted['Encrypted'] = NULL;

				$pasted['Data'] = array('Orig' => $pasted['Data'], 'noHighlight' => array());

				$pasted['Data']['Dirty'] = $db->dirtyHTML($pasted['Data']['Orig']);
				$pasted['Data']['noHighlight']['Dirty'] = $bin->noHighlight($pasted['Data']['Dirty']);

				if($pasted['Syntax'] == NULL || is_bool($pasted['Syntax']) || is_numeric($pasted['Syntax']))
					$pasted['Syntax'] = "plaintext";

				if($pasted['Subdomain'] != NULL && !$CONFIG['subdomain'])
					$bin->setSubdomain($pasted['Subdomain']);					
				
				if($bin->highlight() && $pasted['Syntax'] != "plaintext")
					{
						echo "<style type=\"text/css\">";
						echo stripslashes($pasted['Style']);
			 			echo "</style>";
					}

				if(!is_bool($pasted['Image']) && !is_numeric($pasted['Image']))
					$pasteSize = $bin->humanReadableFilesize(filesize($db->setDataPath($pasted['Image'])));
				else
					$pasteSize = $bin->humanReadableFilesize(mb_strlen($pasted['Data']['Orig']));

				if($pasted['Lifespan'] == 0)
					{
						$pasted['Lifespan'] = time() + time();
						$lifeString = "Never";
					} else
						$lifeString = "in " . $bin->event(time() - ($pasted['Lifespan'] - time()));

				if(gmdate('U') > $pasted['Lifespan'])
				{ $db->dropPaste($requri); die("<div class=\"result\"><div class=\"warn\">This paste has either expired or doesn't exist!</div></div></div></body></html>"); }

				if($db->dbt == "mysql")
					$pasted['Author'] = "<a href=\"" . $bin->linker('pastes') . "@" . urlencode(stripslashes($pasted['Author'])) . "\">" . stripslashes($pasted['Author']) . "</a>";

				echo "<div id=\"aboutPaste\"><div id=\"pasteID\"><strong>PasteID</strong>: " . $requri . "</div><strong>Pasted by</strong> " . stripslashes($pasted['Author']) . ", <em title=\"" . $bin->event($pasted['Datetime']) . " ago\">" . gmdate($CONFIG['pb_datetime'], $pasted['Datetime']) . " GMT</em><br />
					<strong>Expires</strong> " . $lifeString . "<br />
					<strong>Paste size</strong> " . $pasteSize . "</div>";

				if(@$_POST['adminAction'] == "ip" && $bin->hasher(hash($CONFIG['pb_algo'], @$_POST['adminPass']), $CONFIG['pb_salts']) === $CONFIG['pb_pass'])
					echo "<div class=\"success\"><strong>Author IP Address</strong> <a href=\"http://whois.domaintools.com/" . base64_decode($pasted['IP']) . "\">" . base64_decode($pasted['IP']) . "</a></div>";

				if(!is_bool($pasted['Image']) && !is_numeric($pasted['Image']))
					echo "<div id=\"imageContainer\"><a href=\"" . $bin->linker() . $db->setDataPath($pasted['Image']) . "\" rel=\"external\"><img src=\"" . $bin->linker() . $db->setDataPath($pasted['Image']) . "\" alt=\"" . $pasted['ImageTxt'] . "\" class=\"pastedImage\" /></a></div>";

                               if(strlen($pasted['Parent'] ?? '') > 0)
					echo "<div class=\"warn\"><strong>This is an edit of</strong> <a href=\"" . $bin->linker($pasted['Parent']) . "\">" . $bin->linker($pasted['Parent']) . "</a></div>";

				echo "<div id=\"styleBar\"><strong>Tools</strong> <a href=\"" . $bin->linker($pasted['ID'] . '@raw') . "\">Raw</a> &nbsp; <a href=\"" . $bin->linker($pasted['ID'] . '@download') . "\">Download</a></div>";

				if($bin->_clipboard())
					echo "<div class=\"_clipboardBar\"><span class=\"copyText\" id=\"_copyText\">Copy Contents</span> &nbsp; <span class=\"copyText\" id=\"_copyURL\">Copy URL</span></div>";
				else 
					echo "<div class=\"spacer\">&nbsp;</div>";

				
				if(!$bin->highlight() || (!is_bool($pasted['Image']) && !is_numeric($pasted['Image'])) || $pasted['Syntax'] == "plaintext")
					{
						echo "<div id=\"retrievedPaste\"><div id=\"lineNumbers\"><ol id=\"orderedList\" class=\"monoText\">";
							$lines = explode("\n", $pasted['Data']['Dirty']);
							foreach($lines as $line)
								echo "<li class=\"line\"><pre>" . str_replace(array("\n", "\r"), "&nbsp;", $bin->filterHighlight($line)) . "&nbsp;</pre></li>";
						echo "</ol></div></div>";
					} else
						{
							echo "<div class=\"spacer\">&nbsp;</div><div id=\"retrievedPaste\" class=\"" . $pasted['Syntax'] . "\"><div id=\"lineNumbers\">";
							echo stripslashes($pasted['GeSHI']);
							echo "</div></div><div class=\"spacer\">&nbsp;</div>";
						}

				if($bin->lineHighlight())
					$lineHighlight = "To highlight lines, prefix them with <em>" . $bin->lineHighlight() . "</em>";
				else
					$lineHighlight = NULL;

				if($bin->jQuery())
					$event = "onblur";
				else
					$event = "onblur=\"return checkIfURL(this);\" onkeyup";

				if(!is_bool($pasted['Image']) && !is_numeric($pasted['Image']))
						$pasted['Data']['noHighlight']['Dirty'] = $bin->linker() . $db->setDataPath($pasted['Image']);	
				
				if($CONFIG['pb_editing']) {
				echo "<div id=\"formContainer\">
					<form id=\"pasteForm\" name=\"pasteForm\" action=\"" . $bin->linker($pasted['ID']) . "\" method=\"post\">
						<div><label for=\"pasteEnter\">Edit this post! " . $lineHighlight . "</label><br />
						<textarea id=\"pasteEnter\" name=\"pasteEnter\" onkeydown=\"return catchTab(event)\" " . $event . "=\"return checkIfURL(this);\">" . $pasted['Data']['noHighlight']['Dirty'] . "</textarea></div>
						<div id=\"foundURL\" style=\"display: none;\">URL has been detected...</div>
						<div class=\"spacer\">&nbsp;</div>";

						$selecter = '/value="' . $pasted['Syntax'] . '"/';
						$replacer = 'value="' . $pasted['Syntax'] . '" selected="selected"';
                                                if(empty($highlighterContainer)) {
                                                        $highlighterContainer = '';
                                                }
						$highlighterContainer = preg_replace($selecter, $replacer, $highlighterContainer, 1);

						if($bin->highlight())
							echo $highlighterContainer;

						if(is_array($CONFIG['pb_lifespan']) && count($CONFIG['pb_lifespan']) > 1)
							{
								echo "<div id=\"lifespanContainer\"><label for=\"lifespan\">Paste Expiration</label> <select name=\"lifespan\" id=\"lifespan\">";

								foreach($CONFIG['pb_lifespan'] as $span)
									{
										$key = array_keys($CONFIG['pb_lifespan'], $span);
										$key = $key[0];
                                                                                if(empty($options)) {
                                                                                       $options = 0;
                                                                                }
										$options .= "<option value=\"" . $key . "\">" . $bin->event(time() - ($span * 24 * 60 * 60), TRUE) . "</option>";
									}

								$selecter = '/\>0 seconds/';
								$replacer = '>Never';
								$options = preg_replace($selecter, $replacer, $options, 1);

								echo $options;

								echo "</select></div>";
							} elseif(is_array($CONFIG['pb_lifespan']) && count($CONFIG['pb_lifespan']) == 1)
								{
									echo "<div id=\"lifespanContainer\"><label for=\"lifespan\">Paste Expiration</label>";

									echo " <div id=\"expireTime\"><input type=\"hidden\" name=\"lifespan\" value=\"0\" />" . $bin->event(time() - ($CONFIG['pb_lifespan'][0] * 24 * 60 * 60), TRUE) . "</div>";

									echo "</div>";
								} else
									echo "<input type=\"hidden\" name=\"lifespan\" value=\"0\" />";

						$enabled = NULL;

						if($pasted['Protection'])
							$enabled = "disabled";
						
						$privacyContainer = "<div id=\"privacyContainer\"><label for=\"privacy\">Paste Visibility</label> <select name=\"privacy\" id=\"privacy\" " . $enabled . "><option value=\"0\">Public</option> <option value=\"1\">Private</option></select></div>";

						$selecter = '/value="' . $pasted['Protection'] . '"/';
						$replacer = 'value="' . $pasted['Protection'] . '" selected="selected"';
						$privacyContainer = preg_replace($selecter, $replacer, $privacyContainer, 1);

						if($pasted['Protection'])
							{
								$selecter = '/\<\/select\>/';
								$replacer = '</select><input type="hidden" name="privacy" value="' . $pasted['Protection'] . '" />';
								$privacyContainer = preg_replace($selecter, $replacer, $privacyContainer, 1);
							}

						if($CONFIG['pb_private'])
							echo $privacyContainer;

						if($CONFIG['pb_encrypt_pastes'])
							echo "<div id=\"encryptContainer\"><label for=\"encryption\">Password Protect</label> <input type=\"password\" value=\"" . $_temp_decrypt_phrase . "\" name=\"encryption\" id=\"encryption\" /></div>";

						echo "<div class=\"spacer\">&nbsp;</div>";

						echo "<div id=\"authorContainerReply\"><label for=\"authorEnter\">Your Name</label><br />
						<input type=\"text\" name=\"author\" id=\"authorEnter\" value=\"" . $CONFIG['_temp_pb_author'] . "\" onfocus=\"if(this.value=='" . $CONFIG['_temp_pb_author'] . "')this.value='';\" onblur=\"if(this.value=='')this.value='" . $CONFIG['_temp_pb_author'] . "';\" maxlength=\"32\" /></div>
						<div class=\"spacer\">&nbsp;</div>
						<input type=\"text\" name=\"email\" id=\"poison\" style=\"display: none;\" />
						<input type=\"hidden\" name=\"ajax_token\" value=\"" . $bin->token(TRUE) . "\" />
						<input type=\"hidden\" name=\"originalPaste\" id=\"originalPaste\" value=\"" . $pasted['Data']['noHighlight']['Dirty'] . "\" />
						<input type=\"hidden\" name=\"parent\" id=\"parentThread\" value=\"" . $requri . "\" />
						<input type=\"hidden\" name=\"thisURI\" id=\"thisURI\" value=\"" . $bin->linker($pasted['ID']) . "\" />
						<div id=\"fileUploadContainer\" style=\"display: none;\">&nbsp;</div>
						<div id=\"submitContainer\" class=\"submitContainer\">
							<input type=\"submit\" name=\"submit\" value=\"Submit your paste\" onclick=\"return submitPaste(this);\" id=\"submitButton\" />
						</div>
					</form>
				</div>
				<div class=\"spacer\">&nbsp;</div><div class=\"spacer\">&nbsp;</div>";
				} else
					{
						echo "<form id=\"pasteForm\" name=\"pasteForm\" action=\"" . $bin->linker($pasted['ID']) . "\" method=\"post\">
							<input type=\"hidden\" name=\"originalPaste\" id=\"originalPaste\" value=\"" . $pasted['Data']['Dirty'] . "\" />
							<input type=\"hidden\" name=\"parent\" id=\"parentThread\" value=\"" . $requri . "\" />
							<input type=\"hidden\" name=\"thisURI\" id=\"thisURI\" value=\"" . $bin->linker($pasted['ID']) . "\" />
						</form><div class=\"spacer\">&nbsp;</div><div class=\"spacer\">&nbsp;</div>";
					}

			}
			else
				{
					echo "<div class=\"result\"><div class=\"warn\">This paste has either expired or doesn't exist!</div></div>";
					$requri = NULL;
				}
		echo "</div>";
	} elseif($requri && $requri != "install" && substr($requri, -1) == "!")
		{
			if(!$bin->checkIfRedir(substr($requri, 0, -1)))
				echo "<div class=\"result\"><h1>Just a sec!</h1><div class=\"warn\">You are about to visit a post that the author has marked as requiring confirmation to view.</div>
				<div class=\"infoMessage\">If you wish to view the content <strong><a href=\"" . $bin->linker(substr($requri, 0, -1)) . "\">click here</a></strong>. Please note that the owner of this pastebin will not be held responsible for the content of this paste.<br /><br /><a href=\"" . $bin->linker() . "\">Take me back...</a></div></div>";
			else
				echo "<div class=\"result\"><h1>Warning!</h1><div class=\"error\">You are about to leave the site!</div>
				<div class=\"infoMessage\">This paste redirects you to<br /><br /><div id=\"emphasizedURL\">" . $bin->checkIfRedir(substr($requri, 0, -1)) . "</div><br /><br />Danger lurks on the world wide web, if you want to visit the site <strong><a href=\"" . $bin->checkIfRedir(substr($requri, 0, -1)) . "\">click here</a></strong>. Please note that the owner of this pastebin will not be held responsible for the content of the site.<br /><br /><a href=\"" . $bin->linker() . "\">Take me back...</a></div></div>";

			echo "<div id=\"showAdminFunctions\"><a href=\"#\" onclick=\"return showAdminTools();\">Show Admin tools</a></div><div id=\"hiddenAdmin\"><div class=\"spacer\">&nbsp;</div><h2>Administrate</h2>";
					echo "<div id=\"adminFunctions\">
							<form id=\"adminForm\" action=\"" . $bin->linker(substr($requri, 0, -1)) . "\" method=\"post\">
								<label for=\"adminPass\">Password</label><br />
								<input id=\"adminPass\" type=\"password\" name=\"adminPass\" value=\"" . @$_POST['adminPass'] . "\" />
								<br /><br />
								<select id=\"adminAction\" name=\"adminAction\">
									<option value=\"ip\">Show Author's IP</option>
									<option value=\"delete\">Delete Paste</option>
								</select>
								<input type=\"submit\" name=\"adminProceed\" value=\"Proceed\" />
							</form>
						</div></div>";

			die("</div></body></html>");
	} elseif(isset($requri) && $requri == "install" && substr($requri, -1) != "!")
		{
			$stage = array();
			echo "<div id=\"installer\" class=\"installer\">"
				 . "<h1>Installing Pastebin</h1>";

			if(file_exists('./INSTALL_LOCK'))
				die("<div class=\"warn\"><strong>Already Installed!</strong></div></div></body></html>");

			echo "<ul id=\"installList\">";
				echo "<li>Checking Directory is writable. ";
					if(!is_writable($bin->thisDir()))
						echo "<span class=\"error\">Directory is not writable!</span> - CHMOD to 0777";
					else
						{ echo "<span class=\"success\">Directory is writable!</span>"; $stage[] = 1; }
				echo "</li>";

				if(count($stage) > 0)
				{ echo "<li>Quick password check. ";
					$passLen = array(8, 9, 10, 11, 12);
					shuffle($passLen);
					if($CONFIG['pb_pass'] === $bin->hasher(hash($CONFIG['pb_algo'], "password"), $CONFIG['pb_salts']) || !isset($CONFIG['pb_pass']))
						echo "<span class=\"error\">Password is still default!</span> &nbsp; &raquo; &nbsp; Suggested password: <em>" . $bin->generateRandomString($passLen[0]) . "</em>";
					else
						{ echo "<span class=\"success\">Password is not default!</span>"; $stage[] = 1; }
				echo "</li>"; }

				if(count($stage) > 1)
				{ echo "<li>Quick Salt Check. ";
					$no_salts = count($CONFIG['pb_salts']);
					$saltLen = array(8, 9, 10, 11, 12, 14, 16, 25, 32);
					shuffle($saltLen);
					if($no_salts < 4 || ($CONFIG['pb_salts'][1] == "str001" || $CONFIG['pb_salts'][2] == "str002" || $CONFIG['pb_salts'][3] == "str003" || $CONFIG['pb_salts'][4] == "str004"))
						echo "<span class=\"error\">Salt strings are inadequate!</span> &nbsp; &raquo; &nbsp; Suggested salts: <ol><li>" . $bin->generateRandomString($saltLen[0]) . "</li><li>" . $bin->generateRandomString($saltLen[1]) . "</li><li>" . $bin->generateRandomString($saltLen[2]) . "</li><li>" . $bin->generateRandomString($saltLen[3]) . "</li></ol>";
					else
						{ echo "<span class=\"success\">Salt strings are adequate!</span>"; $stage[] = 1; }
				echo "</li>"; }

				if(count($stage) > 2)
				{ echo "<li>Checking Database Connection. ";
					if($db->dbt == "txt")
						{ if(!is_dir($CONFIG['txt_config']['db_folder'])) { mkdir($CONFIG['txt_config']['db_folder']); mkdir($CONFIG['txt_config']['db_folder'] . "/" . $CONFIG['txt_config']['db_images']); mkdir($CONFIG['txt_config']['db_folder'] . "/subdomain"); chmod($CONFIG['txt_config']['db_folder'] . "/" . $CONFIG['txt_config']['db_images'], $CONFIG['txt_config']['dir_mode']); chmod($CONFIG['txt_config']['db_folder'], $CONFIG['txt_config']['dir_mode']); chmod($CONFIG['txt_config']['db_folder'] . "/subdomain", $CONFIG['txt_config']['dir_mode']); } $db->write($db->serializer(array()), $CONFIG['txt_config']['db_folder'] . "/" . $CONFIG['txt_config']['db_index']); $db->write($db->serializer($bin->generateForbiddenSubdomains()), $CONFIG['txt_config']['db_folder'] . "/" . $CONFIG['txt_config']['db_index'] . "_SUBDOMAINS"); $db->write("FORBIDDEN", $CONFIG['txt_config']['db_folder'] . "/index.html"); $db->write("FORBIDDEN", $CONFIG['txt_config']['db_folder'] . "/" . $CONFIG['txt_config']['db_images'] . "/index.html"); chmod($CONFIG['txt_config']['db_folder'] . "/" . $CONFIG['txt_config']['db_index'], $CONFIG['txt_config']['file_mode']); chmod($CONFIG['txt_config']['db_folder'] . "/" . $CONFIG['txt_config']['db_index'] . "_SUBDOMAINS", $CONFIG['txt_config']['file_mode']); chmod($CONFIG['txt_config']['db_folder'] . "/index.html", $CONFIG['txt_config']['file_mode']); chmod($CONFIG['txt_config']['db_folder'] . "/" . $CONFIG['txt_config']['db_images'] . "/index.html", $CONFIG['txt_config']['file_mode']);	}
					if(!$db->connect())
						echo "<span class=\"error\">Cannot connect to database!</span> - Check Config in index.php";
					else
						{ echo "<span class=\"success\">Connected to database!</span>"; $stage[] = 1; }
				echo "</li>"; }

				if(count($stage) > 3)
				{ echo "<li>Creating Database Tables. ";
					$structure = "CREATE TABLE IF NOT EXISTS " . $CONFIG['mysql_connection_config']['db_table'] . " (ID varchar(255), Subdomain varchar(100), Datetime bigint, Author varchar(255), Protection int, Encrypted longtext DEFAULT NULL, Syntax varchar(255) DEFAULT 'plaintext', Parent longtext, Image longtext, ImageTxt longtext, URL longtext, Lifespan int, IP varchar(225), Data longtext, GeSHI longtext, Style longtext, INDEX (id)) ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_general_ci";
				if($db->dbt == "mysql")
					{				
						if(!mysql_query($structure, $db->link) && !$CONFIG['mysql_connection_config']['db_existing'])
							{ echo "<span class=\"error\">Structure failed</span> - Check Config in index.php (Does the table already exist?)"; }
						else
							{ echo "<span class=\"success\">Table created!</span>"; 
							  mysql_query("ALTER TABLE `" . $CONFIG['mysql_connection_config']['db_table'] . "` ORDER BY `Datetime` DESC", $db->link);
							  $stage[] = 1;
							  if($CONFIG['mysql_connection_config']['db_existing'])
								echo "<span class=\"warn\">Attempting to use an existing table!</span> If this is not a Pastebin table a fault will occur."; 

								mkdir($CONFIG['txt_config']['db_folder']);
								chmod($CONFIG['txt_config']['db_folder'], $CONFIG['txt_config']['dir_mode']);
								mkdir($CONFIG['txt_config']['db_folder'] . "/subdomain");
								chmod($CONFIG['txt_config']['db_folder'] . "/subdomain", $CONFIG['txt_config']['dir_mode']);
								mkdir($CONFIG['txt_config']['db_folder'] . "/" . $CONFIG['txt_config']['db_images']); 
								chmod($CONFIG['txt_config']['db_folder'] . "/" . $CONFIG['txt_config']['db_images'], $CONFIG['txt_config']['dir_mode']);
								$db->write("FORBIDDEN", $CONFIG['txt_config']['db_folder'] . "/index.html"); 
								chmod($CONFIG['txt_config']['db_folder'] . "/index.html", $CONFIG['txt_config']['file_mode']);
								$db->write("FORBIDDEN", $CONFIG['txt_config']['db_folder'] . "/" . $CONFIG['txt_config']['db_images'] . "/index.html"); 
								chmod($CONFIG['txt_config']['db_folder'] . "/" . $CONFIG['txt_config']['db_images'] . "/index.html", $CONFIG['txt_config']['file_mode']);

								$forbidden_array = array('ID' => 'forbidden', 'Time_offset' => 10, 'Author' => 'System', 'IP' => $_SERVER['REMOTE_ADDR'], 'Lifespan' => 0, 'Image' => TRUE, 'Protect' => 1, 'Content' => serialize($bin->generateForbiddenSubdomains(TRUE)));

								$db->insertPaste($forbidden_array['ID'], $forbidden_array, TRUE);
							}
					} else
						{
							echo "<span class=\"success\">Table created!</span>"; $stage[] = 1;
						}
				echo "</li>"; }
				if(count($stage) > 4)
				{ echo "<li>Locking Installation. ";					
					if(!$db->write(time(), './INSTALL_LOCK'))
						echo "<span class=\"error\">Writing Error</span>";
					else
						{ echo "<span class=\"success\">Complete</span>"; $stage[] = 1; chmod('./INSTALL_LOCK', $CONFIG['txt_config']['file_mode']); }
				echo "</li>"; }
			echo "</ul>";
				if(count($stage) > 5)
				{ $paste_new = array('ID' => $bin->generateRandomString($CONFIG['pb_id_length']), 'Author' => 'System', 'IP' => $_SERVER['REMOTE_ADDR'], 'Lifespan' => 1800, 'Image' => TRUE, 'Protect' => 0, 'Content' => $CONFIG['pb_line_highlight'] . "Congratulations, your pastebin has now been installed!\nThis message will expire in 30 minutes!");
				$db->insertPaste($paste_new['ID'], $paste_new, TRUE);
				echo "<div id=\"confirmInstalled\"><a href=\"" . $bin->linker() . "\">Continue</a> to your new installation!<br /></div>";
				echo "<div id=\"confirmInstalled\" class=\"warn\">It is recommended that you now CHMOD this directory to 755</div>"; }
			echo "</div>";
		} else
			{
				if($CONFIG['pb_subdomains'])
					$subdomainClicker = " [ <a href=\"#\" onclick=\"return showSubdomain();\">make a subdomain</a> ]";
				else
					$subdomainClicker = NULL;

				if($CONFIG['subdomain'])
					{
						$domain_name = str_replace(array($CONFIG['pb_protocol'] . "://", $CONFIG['subdomain'] . ".", "www."), "", $bin->linker());
						$subdomain_action = str_replace($CONFIG['subdomain'] . ".", "", $bin->linker());
					}
				else
					{
						$domain_name = str_replace(array($CONFIG['pb_protocol'] . "://", "www."), "", $bin->linker());
						$subdomain_action = $bin->linker();
					}
					
				$subdomainForm = "<div id=\"subdomainForm\"><strong>Subdomain</strong><br /><form id=\"subdomain_form\" action=\"" . $subdomain_action . "\" method=\"POST\">" . $CONFIG['pb_protocol'] . "://<input type=\"text\" name=\"subdomain\" id=\"subdomain\" maxlength=\"32\" />." . $domain_name . " <input type=\"submit\" id=\"new_subdomain\" name=\"new_subdomain\" value=\"Create Subdomain\" /></form><div class=\"spacer\">&nbsp;</div></div>";

				if(strlen($bin->linker()) < 16)
					$isShortURL = " If your text is a URL, the pastebin will recognize it and will create a Short URL forwarding page! (Like bit.ly, is.gd, etc)";
				else
					$isShortURL = " If your text is a URL, the pastebin will recognize it and will create a URL forwarding page!";


				if($CONFIG['pb_editing'])
					$service['editing'] = array('style' => 'success', 'status' => 'Enabled');
				else
					$service['editing'] = array('style' => 'error', 'status' => 'Disabled');

				if($CONFIG['pb_api'])
					$service['api'] = array('style' => 'success', 'status' => 'Enabled', 'tip' => '<div class="spacer">&nbsp;</div><div><strong>Developer API</strong></div><div>To create a new paste submit using <strong>POST</strong> to <a href="' . $bin->linker('api') . '">' . $bin->linker('api') . '</a> - The response is in JSON format. For server settings visit <a href="' . $bin->linker('defaults') . '">' . $bin->linker('defaults') . '</a>.</div>');
				else
					$service['api'] = array('style' => 'error', 'status' => 'Disabled', 'tip' => NULL);

				if($CONFIG['pb_encrypt_pastes'])
					$service['encrypting'] = array('style' => 'success', 'status' => 'Enabled');
				else
					$service['encrypting'] = array('style' => 'error', 'status' => 'Disabled');	

				if($bin->_clipboard())
					$service['clipboard'] = array('style' => 'success', 'status' => 'Enabled');
				else
					$service['clipboard'] = array('style' => 'error', 'status' => 'Disabled');

				if($CONFIG['pb_images'])
					$service['images'] = array('style' => 'success', 'status' => 'Enabled', 'tip' => ', you can even upload a ' . $bin->humanReadableFilesize($CONFIG['pb_image_maxsize']) . ' image,');
				else
					$service['images'] = array('style' => 'error', 'status' => 'Disabled', 'tip' => NULL);

				if($CONFIG['pb_download_images'] && $CONFIG['pb_images']) {
					$service['image_download'] = array('style' => 'success', 'status' => 'Enabled');
					$service['images']['tip'] = ', you can even upload or copy from another site a ' . $bin->humanReadableFilesize($CONFIG['pb_image_maxsize']) . ' image,';
				}
				else
					$service['image_download'] = array('style' => 'error', 'status' => 'Disabled', 'tip' => NULL);
					

				if($CONFIG['pb_url'])
					$service['url'] = array('style' => 'success', 'status' => 'Enabled', 'tip' => $isShortURL, 'str' => '/url');
				else
					$service['url'] = array('style' => 'error', 'status' => 'Disabled', 'tip' => NULL, 'str' => NULL);

				if($CONFIG['pb_subdomains'])
					$service['subdomains'] = array('style' => 'success', 'status' => 'Enabled', 'tip' => $subdomainForm);
				else
					$service['subdomains'] = array('style' => 'error', 'status' => 'Disabled', 'tip' => NULL);

				if($bin->jQuery())
					$service['jQuery'] = array('style' => 'success', 'status' => 'Enabled');
				else
					$service['jQuery'] = array('style' => 'error', 'status' => 'Disabled');

				if($bin->highlight())
					$service['syntax'] = array('style' => 'success', 'status' => 'Enabled');
				else
					$service['syntax'] = array('style' => 'error', 'status' => 'Disabled');

				if($bin->lineHighlight())
					$service['highlight'] = array('style' => 'success', 'status' => 'Enabled', 'tip' => ' To highlight lines, prefix them with <em>' . $bin->lineHighlight() . '</em>');
				else
					$service['highlight'] = array('style' => 'error', 'status' => 'Disabled', 'tip' => NULL); 

				$uploadForm = NULL;

				if($bin->jQuery())
					$event = "onblur";
				else
					$event = "onblur=\"return checkIfURL(this);\" onkeyup";				


				if($CONFIG['pb_images'])
					$uploadForm = "<div id=\"fileUploadContainer\"><input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"" . $CONFIG['pb_image_maxsize'] . "\" /><label>Attach an Image (" . implode(", ", $CONFIG['pb_image_extensions']) . " &raquo; Max size " . $bin->humanReadableFilesize($CONFIG['pb_image_maxsize']) . ")</label><br /><input type=\"file\" name=\"pasteImage\" id=\"pasteImage\" /><br />(Optional)</div>";
				else
					$uploadForm = "<div id=\"fileUploadContainer\">&nbsp;<br />&nbsp;<br />&nbsp;<br />&nbsp;</div>";
				
				echo "<div id=\"pastebin\" class=\"pastebin\">"
				. "<h1>" .  $bin->setTitle($CONFIG['pb_name'])  . "</h1>" .
				$bin->setTagline($CONFIG['pb_tagline'])
				. "<div id=\"result\"></div>
				<div id=\"formContainer\">
				<div id=\"instructions\" class=\"instructions\"><h2>How to use</h2><div>Fill out the form with data you wish to store online. You will be given an unique address to access your content that can be sent over IM/Chat/(Micro)Blog for online collaboration (eg, " . $bin->linker('z3n') . "). The following services have been made available by the administrator of this server:</div><ul id=\"serviceList\"><li><span class=\"success\">Enabled</span> Text</li><li><span class=\"" . $service['syntax']['style'] . "\">" . $service['syntax']['status'] . "</span> Syntax Highlighting</li><li><span class=\"" . $service['highlight']['style'] . "\">" . $service['highlight']['status'] . "</span> Line Highlighting</li><li><span class=\"" . $service['editing']['style'] . "\">" . $service['editing']['status'] . "</span> Editing</li><li><span class=\"" . $service['encrypting']['style'] . "\">" . $service['encrypting']['status'] . "</span> Password Protection</li><li><span class=\"" . $service['clipboard']['style'] . "\">" . $service['clipboard']['status'] . "</span> Copy to Clipboard</li><li><span class=\"" . $service['images']['style'] . "\">" . $service['images']['status'] . "</span> Image hosting</li><li><span class=\"" . $service['image_download']['style'] . "\">" . $service['image_download']['status'] . "</span> Copy image from URL</li><li><span class=\"" . $service['url']['style'] . "\">" . $service['url']['status'] . "</span> URL Shortening/Redirection</li><li><span class=\"" . $service['jQuery']['style'] . "\">" . $service['jQuery']['status'] . "</span> Visual Effects</li><li><span class=\"" . $service['jQuery']['style'] . "\">" . $service['jQuery']['status'] . "</span> AJAX Posting</li><li><span class=\"" . $service['api']['style'] . "\">" . $service['api']['status'] . "</span> API</li><li><span class=\"" . $service['subdomains']['style'] . "\">" . $service['subdomains']['status'] . "</span> Custom Subdomains</li></ul><div class=\"spacer\">&nbsp;</div><div><strong>What to do</strong></div><div>Just paste your text, sourcecode or conversation into the textbox below, add a name if you wish" . $service['images']['tip'] . " then hit submit!" . $service['url']['tip'] . "" . $service['highlight']['tip'] . "</div><div class=\"spacer\">&nbsp;</div><div><strong>Some tips about usage;</strong> If you want to put a message up asking if the user wants to continue, add an &quot;!&quot; suffix to your URL (eg, " . $bin->linker('z3n') . "!).</div>" . $service['api']['tip'] . "<div class=\"spacer\">&nbsp;</div></div>" . $service['subdomains']['tip'] . "
				<form id=\"pasteForm\" action=\"" . $bin->linker() . "\" method=\"post\" name=\"pasteForm\" enctype=\"multipart/form-data\">	
				<div><label for=\"pasteEnter\" class=\"pasteEnterLabel\">Paste your text" . $service['url']['str'] . " here!" . $service['highlight']['tip'] . " <span id=\"showInstructions\">[ <a href=\"#\" onclick=\"return showInstructions();\">more info</a> ]</span><span id=\"showSubdomain\">" . $subdomainClicker . "</span></label>
						<textarea id=\"pasteEnter\" name=\"pasteEnter\" onkeydown=\"return catchTab(event)\" " . $event . "=\"return checkIfURL(this);\"></textarea></div>
						<div id=\"foundURL\" style=\"display: none;\">URL has been detected...</div>
						<div class=\"spacer\">&nbsp;</div>
						<div id=\"secondaryFormContainer\"><input type=\"hidden\" name=\"ajax_token\" value=\"" . $bin->token(TRUE) . "\" />";

						if($bin->highlight())
							echo $highlighterContainer;

						if(is_array($CONFIG['pb_lifespan']) && count($CONFIG['pb_lifespan']) > 1)
							{
								echo "<div id=\"lifespanContainer\"><label for=\"lifespan\">Paste Expiration</label> <select name=\"lifespan\" id=\"lifespan\">";

								foreach($CONFIG['pb_lifespan'] as $span)
									{
										$key = array_keys($CONFIG['pb_lifespan'], $span);
										$key = $key[0];
                                                                                if(empty($options)) {
                                                                                       $options = 0;
                                                                                }
										$options .= "<option value=\"" . $key . "\">" . $bin->event(time() - ($span * 24 * 60 * 60), TRUE) . "</option>";
									}

								$selecter = '/\>0 seconds/';
								$replacer = '>Never';
								$options = preg_replace($selecter, $replacer, $options, 1);

								echo $options;

								echo "</select></div>";
							}  elseif(is_array($CONFIG['pb_lifespan']) && count($CONFIG['pb_lifespan']) == 1)
								{
									echo "<div id=\"lifespanContainer\"><label for=\"lifespan\">Paste Expiration</label>";

									echo " <div id=\"expireTime\"><input type=\"hidden\" name=\"lifespan\" value=\"0\" />" . $bin->event(time() - ($CONFIG['pb_lifespan'][0] * 24 * 60 * 60), TRUE) . "</div>";

									echo "</div>";
								} else
									echo "<input type=\"hidden\" name=\"lifespan\" value=\"0\" />";

						if($CONFIG['pb_private'])
							echo "<div id=\"privacyContainer\"><label for=\"privacy\">Paste Visibility</label> <select name=\"privacy\" id=\"privacy\"><option value=\"0\">Public</option> <option value=\"1\">Private</option></select></div>";

						if($CONFIG['pb_encrypt_pastes'])
							echo "<div id=\"encryptContainer\"><label for=\"encryption\">Password Protect</label> <input type=\"password\" name=\"encryption\" id=\"encryption\" /></div>";


						echo "<div class=\"spacer\">&nbsp;</div>";

						echo "<div id=\"authorContainer\"><label for=\"authorEnter\">Your Name</label><br />
						<input type=\"text\" name=\"author\" id=\"authorEnter\" value=\"" . $CONFIG['_temp_pb_author'] . "\" onfocus=\"if(this.value=='" . $CONFIG['_temp_pb_author'] . "')this.value='';\" onblur=\"if(this.value=='')this.value='" . $CONFIG['_temp_pb_author'] . "';\" maxlength=\"32\" /></div>
						" . $uploadForm . "
						<div class=\"spacer\">&nbsp;</div>
						<input type=\"text\" name=\"email\" id=\"poison\" style=\"display: none;\" />
						<div id=\"submitContainer\" class=\"submitContainer\">
							<input type=\"submit\" name=\"submit\" value=\"Submit your paste\" onclick=\"return submitPaste(this);\" id=\"submitButton\" />
						</div>
						</div>
					</form>
				</div>";
				echo "</div>";
			}



?>
	<div class="spacer">&nbsp;</div>
	<div class="spacer">&nbsp;</div>
	<div id="copyrightInfo">Written by <a href="http://xan-manning.co.uk/">Xan Manning</a>, 2010.</div>
	</div>
<?php if($bin->_clipboard() && $requri && $requri != "install")
	echo "<div><span id=\"_clipboard_replace\">YOU NEED FLASH!</span> &nbsp; <span id=\"_clipboardURI_replace\">&nbsp;</span></div>";

if(($requri && $requri != "install") && (!is_bool($pasted['Image']) && !is_numeric($pasted['Image'])) && !$bin->jQuery())
	echo "<script type=\"text/javascript\">setTimeout(\"toggleWrap()\", 1000); setTimeout(\"toggleStyle()\", 1000);</script>";
elseif(($requri && $requri != "install") && (!is_bool($pasted['Image']) && !is_numeric($pasted['Image'])) && $bin->jQuery())
	echo "<script type=\"text/javascript\">$(document).ready(function() { setTimeout(\"toggleWrap()\", 1000); setTimeout(\"toggleStyle()\", 1000); });</script>";
else
	echo "<!-- End of Document -->";
?>
</body>
</html>
