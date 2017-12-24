<?php
/*********************************
 * 自动载入需要的类文件
 * 
 * 
 */

class Aloader{
	
	static function loader($class_name = '') {
		
		//定义允许载入的目录
		$loader_path = array("class", "controller");
		
		//自动载入
		foreach($loader_path as $ctype){
			$classFile = SITE_ROOT_PATH.'/'.$ctype.'/'.$class_name.'.class.php';
			file_exists($classFile) && include_once($classFile);
			
			$controllerFile = SITE_ROOT_PATH.'/'.$ctype.'/'.$class_name.'Controller.class.php';
			file_exists($controllerFile) && include_once($controllerFile);
		}
		
		return true; 
	}
}

//注册自动载入
spl_autoload_register(array('Aloader', 'loader'));

?>