<?php
/**
 * Class 输入
 *
 * @author seekfor seekfor@gmail.com
 * @version CMSfor Website Pro 1.2 Tue Oct 10 17:58:39 CST 2006 
 */

class Input {
 
function parse_incoming()
{
	global $_GET;
	global $_POST;
	global $REQUEST_METHOD;

	//防护XSS,SQL,代码执行，文件包含等多种高危漏洞
	$url_arr  = array(
		'xss'=>"\\=\\+\\/v(?:8|9|\\+|\\/)|\\%0acontent\\-(?:id|location|type|transfer\\-encoding)",
	);

	$args_arr = array(
		'xss'=>"[\\'\\\"\\;\\*\\<\\>].*\\bon[a-zA-Z]{3,15}[\\s\\r\\n\\v\\f]*\\=|\\b(?:expression)\\(|\\<script[\\s\\\\\\/]|\\<\\!\\[cdata\\[|\\b(?:eval|alert|prompt|msgbox)\\s*\\(|url\\((?:\\#|data|javascript)",
		'sql'=>"[^\\{\\s]{1}(\\s|\\b)+(?:select\\b|update\\b|insert(?:(\\/\\*.*?\\*\\/)|(\\s)|(\\+))+into\\b).+?(?:from\\b|set\\b)|[^\\{\\s]{1}(\\s|\\b)+(?:create|delete|drop|truncate|rename|desc)(?:(\\/\\*.*?\\*\\/)|(\\s)|(\\+))+(?:table\\b|from\\b|database\\b)|into(?:(\\/\\*.*?\\*\\/)|\\s|\\+)+(?:dump|out)file\\b|\\bsleep\\([\\s]*[\\d]+[\\s]*\\)|benchmark\\(([^\\,]*)\\,([^\\,]*)\\)|(?:declare|set|select)\\b.*@|union\\b.*(?:select|all)\\b|(?:select|update|insert|create|delete|drop|grant|truncate|rename|exec|desc|from|table|database|set|where)\\b.*(charset|ascii|bin|char|uncompress|concat|concat_ws|conv|export_set|hex|instr|left|load_file|locate|mid|sub|substring|oct|reverse|right|unhex)\\(|(?:master\\.\\.sysdatabases|msysaccessobjects|msysqueries|sysmodules|mysql\\.db|sys\\.database_name|information_schema\\.|sysobjects|sp_makewebtask|xp_cmdshell|sp_oamethod|sp_addextendedproc|sp_oacreate|xp_regread|sys\\.dbms_export_extension)",
		'other'=>"\\.\\.[\\\\\\/].*\\%00([^0-9a-fA-F]|$)|%00[\\'\\\"\\.]"
	);

	$referer = empty($_SERVER['HTTP_REFERER']) ? array() : array($_SERVER['HTTP_REFERER']);
	$query_string = empty($_SERVER["QUERY_STRING"]) ? array() : array($_SERVER["QUERY_STRING"]);

	$this->check_data($query_string,$url_arr);
	$this->check_data($_GET,$args_arr);
	$this->check_data($_POST,$args_arr);
	$this->check_data($_COOKIE,$args_arr);
	$this->check_data($referer,$args_arr);


	$return = array();
	if(is_array ($_GET))
	{
		while(list ($k, $v) = each($_GET))
		{
			if(is_array ($_GET[$k]))
			{
				while(list ($k2, $v2) = each($_GET[$k]))
				{
					$return[$k][$this->clean_key($k2)] = $this->clean_value($v2);
				}
				continue;
			}
			else
			{
				$return[$k] = $this->clean_value($v);
				continue;
			}
		}
	}
	if(is_array ($_POST))
	{
		while(list ($k, $v) = each($_POST))
		{
			if(is_array ($_POST[$k]))
			{
				while(list ($k2, $v2) = each($_POST[$k]))
				{
					$return[$k][$this->clean_key($k2)] = $this->clean_value($v2);
				}
				continue;
			}
			else
			{
				$return[$k] = $this->clean_value($v);
				continue;
			}
		}
	}

	$return["request_method"] =($_SERVER["REQUEST_METHOD"] != '' ? strtolower ($_SERVER["REQUEST_METHOD"]) : strtolower($REQUEST_METHOD));
	
	if(!empty($return['do']))
	{
	   $data = explode(';', $return['do']);
	   foreach($data as $key => $var)
	   {
		 $data1 = explode("^^", $var); 
		 $return[$data1[0] . ''] = $data1[1];
	   }
	}
	return $return;
}

function clean_key($key)
{
	if($key == '')
	{
		return 0;
	}
	$key = str_replace("'", "", $key);
	$key = preg_replace('/\\.\\./', '', $key);
	$key = preg_replace('/\\_\\_(.+?)\\_\\_/', '', $key);
	$key = preg_replace('/^([\\w\\.\\-\\_]+)$/', '$1', $key);
	return $key;
}

function clean_value($val)
{
	if($val == '')
	{
		return '';
	}
	$val = str_replace("'", "‘", $val);
	$val = str_replace("\u0027", "‘", $val);
	if(get_magic_quotes_gpc())
	{
		$val = stripslashes($val);
	}
	return trim($val);
}


function _addslashes($string)
{

		if(is_array ($string))
		{
			foreach($string as $key => $val)
			{
				$string[$key] = $this->_addslashes($val);
			}
			return $string;
		}
		$string = addslashes($string);

	return $string;
}

function _htmlentities($string)
{
		if(is_array ($string))
		{
			foreach($string as $key => $val)
			{
				$string[$key] = $this->_htmlentities($val);
			}
			return $string;
		}
		$string = str_replace("'", "’", $string);
		$string = htmlentities($string, ENT_QUOTES,'UTF-8');		

	return $string;
}

function _replace_blank($string)
{
	$replmsg = array("'", "\"");	
	if(is_array ($string))
	{
		foreach($string as $key => $val)
		{
			$string[$key] = $this->_replace_blank($val);
		}
		return $string;
	}
		
	$string = str_replace($replmsg, "", $string);		

	return $string;
}

function W_log($log)
{
	$logpath = $_SERVER["DOCUMENT_ROOT"]."/data/input_log.txt";
	$log_f   = fopen($logpath,"a+");
	fputs($log_f,$log."\r\n");
	fclose($log_f);
}

function check_data($arr,$v)
{
	foreach($arr as $key=>$value)
	{
		if(!is_array($key))
		{ 
			$this->check($key,$v);
		}else{ 
			$this->check_data($key,$v);
		}

		if(!is_array($value))
		{ 
			$this->check($value,$v);
		}else{ 
			$this->check_data($value,$v);
		}
	}
}

function check($str,$v)
{
	foreach($v as $key=>$value)
	{
		if (preg_match("/".$value."/is",$str)==1||preg_match("/".$value."/is",urlencode($str))==1)
		{
			$this->W_log("<br>IP: ".$_SERVER["REMOTE_ADDR"]."<br>时间: ".strftime("%Y-%m-%d %H:%M:%S")."<br>页面:".$_SERVER["PHP_SELF"]."<br>提交方式: ".$_SERVER["REQUEST_METHOD"]."<br>提交数据: ".$str);
			print "Input Error!";
			exit();
		}
	}
}

    /**
     * Add slashes before "'" and "\" characters so a value containing them can
     * be used in a sql comparison.
     *
     * @param   string   the string to slash
     * @param   boolean  whether the string will be used in a 'LIKE' clause
     *                   (it then requires two more escaped sequences) or not
     * @param   boolean  whether to treat cr/lfs as escape-worthy entities
     *                   (converts \n to \\n, \r to \\r)
     *
     * @param   boolean  whether this function is used as part of the
     *                   "Create PHP code" dialog
     *
     * @return  string   the slashed string
     *
     * @access  public
     */
    function PMA_sqlAddslashes($a_string = '', $is_like = false, $crlf = false, $php_code = false)
    {
        if ($is_like) {
            $a_string = str_replace('\\', '\\\\\\\\', $a_string);
        } else {
            $a_string = str_replace('\\', '\\\\', $a_string);
        }

        if ($crlf) {
            $a_string = str_replace("\n", '\n', $a_string);
            $a_string = str_replace("\r", '\r', $a_string);
            $a_string = str_replace("\t", '\t', $a_string);
        }

        if ($php_code) {
            $a_string = str_replace('\'', '\\\'', $a_string);
        } else {
            $a_string = str_replace('\'', '\'\'', $a_string);
        }

        return $a_string;
    }

//END

}
?>