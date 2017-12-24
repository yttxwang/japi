<?php

!defined('SYSTEM_ACCESS') && exit('Access Deny');

/***
 * 仅作为数据库链接使用，数据库对象来自于主入口文件 api.php
 * @author wanjun
 *
 */
class DB {
	protected $db = OBJECT;
	
	public function __construct(){
		global $db;
		$this->db = $db;
	}
	
}