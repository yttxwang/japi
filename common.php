<?php
	 header('Content-Type: text/html; charset=utf-8'."\n"); 
	 define("SITE_ROOT_PATH",str_replace("\\","/",dirname(__FILE__)));
	 date_default_timezone_set('PRC'); 
	 ini_set('memory_limit', '1024M');
	 ini_set('display_errors', 0);
	 error_reporting(E_ALL);
	 //Site Infomation
	 define("RESOURCE_PATH", 'http://resource.dhb.net.cn/');
	 define("DATA_PATH", 'data/');
	 define("CACHE_LIFETIME", '12');
	 define("CONF_PATH_CACHE", 'data/cache');
	 define("LOG_PATH", 'data/log/');
	 
	 //系统文件访问授权
	 define("SYSTEM_ACCESS", 'ALLOW');
	 
	 //设定系统默认药批
	 define("DEFAULT_AGENCY", 501);
	 
	 //系统调试日志配置
	 define("SYSTEM_DEBUG", true);

     //Database
     define("DB_HOST", "172.19.224.205:3306");
     define("DB_USER", "ftp-etong");
     define("DB_PASSWORD", "yttx123456");
     define("DB_DATABASE", "etong_db_live");
     define("DB_DATABASEU", "etong_db_live_user.");
     define("DATATABLE", "rsung");
	 
	 include_once (SITE_ROOT_PATH."/class/ezsql/shared/ez_sql_core.php");
	 include_once (SITE_ROOT_PATH."/class/ezsql/mysql/ez_sql_mysql.php");
	 include_once (SITE_ROOT_PATH."/class/db.class.php");
	 include_once (SITE_ROOT_PATH."/class/input.class.php");
	 include_once (SITE_ROOT_PATH."/class/functions.php");
	 include_once (SITE_ROOT_PATH."/class/KLogger.php");
	 include_once (SITE_ROOT_PATH."/class/autoload.class.php");
?>
