<?php
/**
 * Class functions
 * 常用函数
 * 
 * @author 
 */
function is_filename($str)
{
      return preg_match("/^[A-Za-z0-9_.@\-]+$/", $str);
} 

function is_safe($str)
{
	return preg_match("/^([\x81-\xfea-z0-9])+$/i", $str);
}

function is_email($str){
	return preg_match("/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/", $str);
}

function is_mobile($str){
    return preg_match("/^((\(\d{3}\))|(\d{3}\-))?13\d{9}$/", $str);
}


/************** 递归处理函数 **************/
	function set_object_array($obj) {
		$ret = array();
		if ( is_object($obj) ) {
			settype($obj,"array");
		}
		if (is_array($obj)){
			foreach ($obj as $k => $v) {
				$ret[$k] = set_object_array($v);
			}
		}else{
			return trim($obj);
		}
		return $ret;
	}//end function set_object_array


function is_phone($var)
{
	$var = trim($var);
	if(preg_match ("/^[-]?[0-9]+([\.][0-9]+)?$/", $var))
	{
		if(strlen($var) == 11) return true; else return false;
	}else{
		return false;
	}
}

function is_chinese($str){
    return preg_match("/^[".chr(0xa1)."-".chr(0xff)."]+$/",$str);
}

function is_english($str){
    return preg_match("/^[A-Za-z]+$/", $str);
}

function is_zip($str){
	return preg_match("/^[1-9]\d{5}$/", $str);
}

function is_url($str){
	return preg_match("/^http:\/\/[A-Za-z0-9]+\.[A-Za-z0-9]+[\/=\?%\-&_~`@[\]\':+!]*([^<>\"])*$/", $str);
}

function RealIp()
{
	if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]))
	{
		$ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	}
	elseif(isset($_SERVER["HTTP_CLIENT_IP"]))
	{
		$ip = $_SERVER["HTTP_CLIENT_IP"];
	}else{
		$ip = $_SERVER["REMOTE_ADDR"];
	}
	return $ip;
}

/*
* @todo 中文截取，支持gb2312,gbk,utf-8,big5
*
* @param string $str 要截取的字串
* @param int $start 截取起始位置
* @param int $length 截取长度
* @param int $param  是否去除HTML代码
* @param string $charset utf-8|gb2312|gbk|big5 编码
* @param $suffix 是否加尾缀
*/
function cutmsg($str, $length, $suffix="", $param=0, $start=0, $charset="utf-8")
{
	if($param==1)
	{
		$str = preg_replace("#<.+?>#is", "", $str);
	}

	if(function_exists("mb_substr"))
	return mb_substr($str, $start, $length, $charset);

	$re['utf-8']   = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
	$re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
	$re['gbk']  = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
	$re['big5']  = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
	preg_match_all($re[$charset], $str, $match);
	$slice = join("",array_slice($match[0], $start, $length));

	return $slice.$suffix;
}

function countStrLength($string)
{
   $string = preg_replace ( '/[\x80-\xff]{3}/', 'x', $string );
   return strlen ($string);
}


//删除HTML
function filterHtml($str){
	$str = preg_replace("/<sty(.*)\\/style>|<scr(.*)\\/script>|<!--(.*)-->/isU","",$str);
	$alltext = "";
	$start = 1;
	for($i=0;$i<strlen($str);$i++)
	{
		if($start==0 && $str[$i]==">")
		{
			$start = 1;
		}
		else if($start==1)
		{
			if($str[$i]=="<")
			{
				$start = 0;
				$alltext .= " ";
			}
			else if(ord($str[$i])>31)
			{
				$alltext .= $str[$i];
			}
		}
	}
	$alltext = str_replace("　"," ",$alltext);
	$alltext = str_replace("&nbsp;", '', $alltext);
	$alltext = preg_replace("/&([^;&]*)(;|&)/","",$alltext);
	$alltext = preg_replace("/[ ]+/s"," ",$alltext);
	return $alltext;
}

/**
	 * @todo GetExt 取得文件的扩展名
	 * 
	 * @param string $filename 文件名
	 * @return string $ext 扩展名
	 */
function GetExt($filename)
{
	$ext = "";
	if($filename == "") return $ext;
	$ext_a = explode(".", $filename);
	$ext = array_pop($ext_a);
	$ext = strtolower($ext);
	return $ext;
}

function ShowImg($filename)
{
	$ext = "";
	if(empty($filename)) $ext = "/template/img/default.jpg"; else $ext = "/".RESOURCE_PATH.$filename;
	return $ext;
}

function GoodsType($ftype)
{
	if(empty($ftype)) return "";
	$ext = "";
	 switch($ftype)
	 {
		case 0:
		{
			$ext = "";
			break;
		}
		case 1:
		{
			$ext = "<span class=regbg>[荐]</span>";
			break;
		}
		case 2:
		{
			$ext = "<span class=greenbg>[特]</span>";
			break;
		}
		case 3:
		{
			$ext = "<span class=yellowbg>[新]</span>";
			break;
		}
		case 4:
		{
			$ext = "<span class=bluebg>[热]</span>";
			break;
		}
		case 9:
		{
			$ext = "<span class=darkbg>[缺]</span>";
			break;
		}
		default: 
			$ext = "";
			break;
	}


	return $ext;
}

/**
     * @todo 取得当前页的完整地址
     * 
     * @param  
     * @return string $url 地址
     */     
function get_url()
{
	if (isset($_SERVER['REQUEST_URI']))
	{
		$url = $_SERVER['REQUEST_URI'];
	}else{
		$url = $_SERVER['SCRIPT_NAME'];
		$url .= (!empty($_SERVER['QUERY_STRING'])) ? '?' . $_SERVER['QUERY_STRING'] : '';
	}
	return $url;
}


/**
     * @todo 取得时间戳
     * 
     * @param  
     * @return float 时间
     */  
function microtime_float()
{
	list($usec, $sec) = explode(" ", microtime());
	return ((float)$usec + (float)$sec);
}

/**************/
//@description:防止站外提交
//@Attention:
/**************/
function checkpost(){
	$action = $_SERVER['PHP_SELF'];
	if ($_SERVER['REQUEST_METHOD'] == 'POST'){
		$ref = parse_url($_SERVER['HTTP_REFERER']);
		$srv = "http://{$_SERVER['SERVER_NAME']}";
		if (strcmp($srv,$ref['scheme']."://".$ref['host']) == 0){
			echo "ok";
			exit;
		}else{
			echo "error";
			exit;
		}
	}
}



function passport_encrypt($txt, $key) {
	srand((double)microtime() * 1000000);
	$encrypt_key = md5(rand(0, 32000));
	$ctr = 0;
	$tmp = '';
	for($i = 0;$i < strlen($txt); $i++) {
		$ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
		$tmp .= $encrypt_key[$ctr].($txt[$i] ^ $encrypt_key[$ctr++]);
	}
	return base64_encode(passport_key($tmp, $key));
}

function passport_decrypt($txt, $key) {
	$txt = passport_key(base64_decode($txt), $key);
	$tmp = '';
	for($i = 0;$i < strlen($txt); $i++) {
		$md5 = $txt[$i];
		$tmp .= $txt[++$i] ^ $md5;
	}
	return $tmp;
}

function passport_key($txt, $encrypt_key) {
	$encrypt_key = md5($encrypt_key);
	$ctr = 0;
	$tmp = '';
	for($i = 0; $i < strlen($txt); $i++) {
		$ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
		$tmp .= $txt[$i] ^ $encrypt_key[$ctr++];
	}
	return $tmp;
}

/**
 * 给字符串添加单引号
 * @param $str
 * @return string
 */
function add_quotes($str) {
    return "'" . $str . "'";
}

/**
 * 兼容 array_column
 * @since 2015-06-15
 */
if(!function_exists('array_column')){
    function array_column(array $array, $column_key, $index_key = null){
        $result = array();
        foreach($array as $arr){
            if(!is_array($arr)) continue;

            if(is_null($column_key)){
                $value = $arr;
            }else{
                $value = $arr[$column_key];
            }

            if(!is_null($index_key)){
                $key = $arr[$index_key];
                $result[$key] = $value;
            }else{
                $result[] = $value;
            }

        }
        return $result;
    }
}

/**
 * 记录接口中的错误信息
 * @param $msg
 * @param null $data
 */
function wlog($msg, $data = null, $typePath = '') {
    
    $typePath = $typePath ? rtrim($typePath, '/') . '/' : '';
    $file     = 'log_' . date("Y-m-d") . '_error.txt';
    $content  = date('Y-m-d H:i:s') . " - WLOG --> ";
    $content .= $msg . ' ; ' . (is_null($data) ? '' : print_r($data,true)) . "\r\n";
    error_log($content, 3, LOG_PATH . $typePath . $file);
}


?>