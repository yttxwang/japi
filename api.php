<?php


include_once ("common.php");
include_once ("controller.php");
set_time_limit(300);


/*
if(strtoupper($_SERVER['REQUEST_METHOD']) != 'POST') {
    echo json_encode(array(
        'rStatus' => 101,
        'error' => '请将请求方式更新为POST',
    ));
    exit;
}
*/

$db		= dbconnect::dataconnect()->getdb();
$db->cache_dir  = CONF_PATH_CACHE;

$php_input = file_get_contents("php://input");
$php_input = $php_input ? ($php_input) : http_build_query($_GET);

//$php_input = $php_input ? gzuncompress($php_input) : http_build_query($_GET);


//print_r($php_input);
//exit;


$php_array = array();
$php_input = other2utf8($php_input);

parse_str($php_input,$php_array);

$_GET = $_REQUEST = array();
$_POST = $php_array;

$input		=	new Input;
$in			=	$input->parse_incoming();

$param		=   json_decode(urldecode($in['v']),true);

if(empty($param['step']) || (int)$param['step']>1000){
	if($in['f'] == 'getCoding') {
		$param['step'] = $param['step'] > 1000 || empty($param['step']) ? 1000 : (int)$param['step'];
	} else {
		$param['step'] = 1000;
	}
}

if(empty($param['begin'])){
	$param['begin'] = 0;
}

/************************* 金融API开始 *******************************************/
/**
 * 1、固定IP验证方式
 * 2、白名单模式
 * 3、加密方式：固定IP + MD5(固定ip+固定盐)
 * */
//这里使用了统一的 api.php作为入口，独立处理徙木金融的数据读取，10是暂定的长度
if(isset($param['partner']) && strlen($param['partner']) > 8){
	
	$param['step'] = $param['step'] == 1000 ? 300 : $param['step'];
	$salt = '*etong&^%$20170825';	//固定盐值
	
	$ip = RealIp();
	
	include_once './api_map/config_map.php';
	include_once './api_map/DB.class.php';
	
	$route	= $ip_map[$ip];	//获取路由
	$skey	= md5($ip . $salt);
	
	if(!$route || $skey != $param['sKey']){
		$return = array('rStatus' => 101, 'message' => '未经授权的请求');
		
	}else{
		//接口操作开始
		$version	 = $param['version'];	//获取版本号
		$action_name = $in['f'];			//获取操作名称
		$ver_path = SITE_ROOT_PATH . '/api_map/' . $route . '/' . $version . '/ActionController.class.php';
		if(file_exists($ver_path)){
			include_once $ver_path;
			$action_class = new ActionController($param);
			if(method_exists($action_class, $action_name)){
				$return = $action_class->$action_name();
			}else{
				$return['rStatus']	= 102;
				$return['message']	= '请求方法不存在：' . $action_name;
			}
		}else{
			$return['rStatus']	= 103;
			$return['message']	= '版本号错误';
		}
	}
	
	exit(json_encode($return, JSON_UNESCAPED_UNICODE));
}
/************************* 金融API结束 *******************************************/

if(!empty($in['f']))
{
	$func   = trim($in['f']);
	$module = new controller();
	if (method_exists($module, $func))
	{
		$rdata = $module->{$func}($param);
		
		
		if($rdata['rStatus'] == 101){
				
			//unset($param['body']);
			//$module->seaslog('error-101', $func . ' param', ($param+$rdata));	//记录日志，确定是哪个客户
			$module->seaslog('error-101_tah', $func, array('param'=>$param,'rdata'=>$rdata));	//记录日志，确定是哪个客户
		}
		unset($rdata['debug_info']);
		
		$rdatamsg = JSON($rdata);
		$rdatamsg = str_replace("\n","",$rdatamsg);
		$rdatamsg = str_replace("\t","",$rdatamsg);
		$rdatamsg = str_replace('"rData":null','"rData":[]',$rdatamsg);
		echo $rdatamsg = str_replace("\r","",$rdatamsg);
	}else {
		$rdata['rStatus'] = '101';
		$rdata['error'] = '请求方法不存在';
		$rdata['rData'] = '';
		echo json_encode($rdata);
	}  
}

function arrayRecursive(&$array, $function, $apply_to_keys_also = false)
{
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            arrayRecursive($array[$key], $function, $apply_to_keys_also);
        } else {
            $array[$key] = $function($value);
        }

        if ($apply_to_keys_also && is_string($key)) {
            $new_key = $function($key);
            if ($new_key != $key) {
                $array[$new_key] = $array[$key];
                unset($array[$key]);
            }
        }
    }
}

function other2utf8($data){
    if( !empty($data) ){
        $fileType = mb_detect_encoding($data , array('UTF-8','GBK','LATIN1','BIG5')) ;
        if( $fileType != 'UTF-8'){
            $data = mb_convert_encoding($data ,'utf-8' , $fileType);
        }
    }
    return $data;
}


function JSON($array) {
    arrayRecursive($array, 'urlencode', true);
    $json = json_encode($array);
    return urldecode($json);
}

?>
