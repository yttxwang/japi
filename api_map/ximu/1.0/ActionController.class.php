<?php

!defined('SYSTEM_ACCESS') && exit('Access Deny');

/***
 * 徙木金融的风控模型数据获取程序
 * @author wanjun
 *
 */

class ActionController extends DB {
	
	private $_sql_path = '',	//sql文件存储路径
			$_param = '';		//参数
	
	private $_limit = 300;	//每次最高同步300条数据
	
	/**
	 * 初始化
	 */
	public function __construct($param = array()){
		
		Parent::__construct();
		
		$this->_param = $param;
		
		//设置SQL路径
		$this->_sql_path = str_replace('\\', '/', dirname(dirname(__FILE__)));
	}
	
	/***
	 * 获取买家收货信息列表
	 * @author wanjun
	 */
	public function get_consignee_info(){
		
		$status = $this->_check_limit();
		if(is_array($status)) return $status;
		
		$result = array();
		$sql = file_get_contents($this->_sql_path . '/' . __FUNCTION__ . '.sql');
		$sql = $this->_rebulid_sql($sql, array($this->_param['begin'], $this->_param['step']));
		$result = $this->db->get_results($sql);
		
		//other codes here...
		
		return $this->_bulid_return($result);
	} 
	/**
	 * 获取产品基础信息列表
	 */
	public function get_product_list(){
		
		$status = $this->_check_limit();
		if(is_array($status)) return $status;
		
		$result = array();
		$sql = file_get_contents($this->_sql_path . '/' . __FUNCTION__ . '.sql');
		$sql = $this->_rebulid_sql($sql, array($this->_param['begin'], $this->_param['step']));
		$result = $this->db->get_results($sql);
		
		//获取当前商业的分类并重组
		$site_sql = "select SiteID,SiteName from rsung_order_site";
		$site_data = $this->db->get_results($site_sql);
		
		$site_length = count($site_data);
		$tmp_site = array();
		for($j = 0; $j < $site_length; $j++){
			$tmp_site[$site_data[$j]['SiteID']] = $site_data[$j]['SiteName'];
		}
		////////////////////// END //////////////////////
		
		$length = count($result);
		for($i = 0; $i < $length; $i++){
			$site_ids_str = trim(substr($result[$i]['site_id_multi'], 2), '.');
			$site_ids_arr = explode('.', $site_ids_str);
			$result[$i]['site_id_1'] = $site_ids_arr[0];
			$result[$i]['site_id_name_1'] = $tmp_site[$site_ids_arr[0]];
			$result[$i]['site_id_2'] = $site_ids_arr[1];
			$result[$i]['site_id_name_2'] = $tmp_site[$site_ids_arr[1]];
			$result[$i]['site_id_3'] = $site_ids_arr[2];
			$result[$i]['site_id_name_3'] = $tmp_site[$site_ids_arr[2]];
			$result[$i]['product_name'] = trim($result[$i]['product_name']);
		}
		
		//other codes here...
		
		return $this->_bulid_return($result);
	}
	
	/***
	 * 获取卖家平台账户信息
	 * @author wanjun
	 */
	public function get_merchant_info(){
		$status = $this->_check_limit();
		if(is_array($status)) return $status;
		
		$result = array();
		$sql = file_get_contents($this->_sql_path . '/' . __FUNCTION__ . '.sql');
		$sql = $this->_rebulid_sql($sql, array($this->_param['begin'], $this->_param['step']));
		$result = $this->db->get_results($sql);
		
		//other codes here...
		
		return $this->_bulid_return($result);
	}
	
	
	/***
	 * 获取卖家-真实身份信息（法定代表人）
	 * @author wanjun
	 */
	public function get_merchant_id(){
		$status = $this->_check_limit();
		if(is_array($status)) return $status;
		
		$result = array();
		$sql = file_get_contents($this->_sql_path . '/' . __FUNCTION__ . '.sql');
		$sql = $this->_rebulid_sql($sql, array($this->_param['begin'], $this->_param['step']));
		$result = $this->db->get_results($sql);
		
		//other codes here...
		
		return $this->_bulid_return($result);
	}
	
	/***
	 * 获取物流信息列表
	 */
	public function get_ship_list(){
		$status = $this->_check_limit();
		if(is_array($status)) return $status;
		
		$result = array();
		$sql = file_get_contents($this->_sql_path . '/' . __FUNCTION__ . '.sql');
		$sql = $this->_rebulid_sql($sql, array($this->_param['begin'], $this->_param['step']));
		$result = $this->db->get_results($sql);
		
		//other codes here...
		
		return $this->_bulid_return($result);
	}
	
	/***
	 * 获取支付流水列表
	 */
	public function get_finance_list(){
		$status = $this->_check_limit();
		if(is_array($status)) return $status;
		
		$result = array();
		$sql = file_get_contents($this->_sql_path . '/' . __FUNCTION__ . '.sql');
		$sql = $this->_rebulid_sql($sql, array($this->_param['begin'], $this->_param['step']));
		$result = $this->db->get_results($sql);
		
		//other codes here...
		
		return $this->_bulid_return($result);
	}
	
	/***
	 * 获取卖家的企业资质列表
	 */
	public function get_license_list(){
		$status = $this->_check_limit();
		if(is_array($status)) return $status;
	
		$result = array();
		$sql = file_get_contents($this->_sql_path . '/' . __FUNCTION__ . '.sql');
		$sql = $this->_rebulid_sql($sql, array($this->_param['begin'], $this->_param['step']));
		$result = $this->db->get_results($sql);
	
		//other codes here...
	
		return $this->_bulid_return($result);
	}
	
	/***
	 * 获取订单列表
	 * @author wanjun
	 */
	public function get_order_list(){
		$status = $this->_check_limit();
		if(is_array($status)) return $status;
	
		$result = array();
		$sql = file_get_contents($this->_sql_path . '/' . __FUNCTION__ . '.sql');
		$sql = $this->_rebulid_sql($sql, array($this->_param['begin'], $this->_param['step']));
		$result = $this->db->get_results($sql);
	
		//other codes here...
	
		return $this->_bulid_return($result);
	}
	
	/***
	 * 获取订单详情
	 * @author wanjun
	 */
	public function get_order_detail(){
		$status = $this->_check_limit();
		if(is_array($status)) return $status;
	
		$result = array();
		$sql = file_get_contents($this->_sql_path . '/' . __FUNCTION__ . '.sql');
		$sql = $this->_rebulid_sql($sql, array($this->_param['begin'], $this->_param['step']));
		$result = $this->db->get_results($sql);
	
		//other codes here...
	
		return $this->_bulid_return($result);
	}
	
	/***
	 * 获取买家账户信息
	 * @author wanjun
	 */
	public function get_client_info(){
		$status = $this->_check_limit();
		if(is_array($status)) return $status;
	
		$result = array();
		$sql = file_get_contents($this->_sql_path . '/' . __FUNCTION__ . '.sql');
		$sql = $this->_rebulid_sql($sql, array($this->_param['begin'], $this->_param['step']));
		$result = $this->db->get_results($sql);
	
		//other codes here...
	
		return $this->_bulid_return($result);
	}
	
	/***
	 * 获取买家账户信息
	 * @author wanjun
	 */
	public function get_site_list(){
		$status = $this->_check_limit();
		if(is_array($status)) return $status;
	
		$result = array();
		$sql = file_get_contents($this->_sql_path . '/' . __FUNCTION__ . '.sql');
		$sql = $this->_rebulid_sql($sql, array($this->_param['begin'], $this->_param['step']));
		$result = $this->db->get_results($sql);
	
		//other codes here...
	
		return $this->_bulid_return($result);
	}
	
	
	/********************************* 以下是程序内部使用  *************************************************/
	
	/***
	 * 置换SQL参数
	 * @author wanjun
	 * @param string $sql 待置换的sql语句
	 * @param array $replace limit条件
	 */
	private function _rebulid_sql($sql = '', $replace = array()){

		return str_replace(array('{BEGIN}', '{STEP}'), $replace, $sql);
	}
	
	/***
	 * 检查每次同步的数量限制,300
	 * @author wanjun
	 */
	private function _check_limit(){
		
		if($this->_param['step'] > $this->_limit){
			return array(
				'rStatus'	=> 104,
				'message'	=> '每次允许获取 '. $this->_limit .' 条数据'
			);
		}
		
		return true;
	}
	
	/***
	 * 组合返回数据体
	 * @author wanjun 
	 * @param array $return
	 */
	private function _bulid_return($return = array()){
		
		return array(
				'rStatus'		=> 100,
				'message'	=> '已成功获取 ' . count($return) . ' 条数据',
				'response'	=> $return
		);
	}
	
	public function __destruct(){
		
	}
	
	
	
}