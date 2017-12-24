<?php
// +----------------------------------------------------------------------
// | Describe: 接口控制器
// +----------------------------------------------------------------------
// | Date: 2014-11-13
// +----------------------------------------------------------------------
class controller{

    //TODO:: 错误信息修改准确一点

	/**
    * 验证，获取Key
	*@param array $param(SerialNumber,Password) 用户名,密码
	*@return array $rdata(rStatus,error,sKey) 状态，提示信息，key
    */
	public function  getTokenValue($param){
		global $db;
		
		if(is_object($param)) $param = (array)$param;
		
		//记录日志
		self::seaslog('getToken', __METHOD__, $param);
		
        $param['SerialNumber'] = trim($param['SerialNumber']);
        $param['PassWord'] = trim($param['PassWord']);

		if(empty($param['SerialNumber']) || empty($param['PassWord'])){
			$rdata['rStatus'] = 101;
			$rdata['message']   = '授权信息不能为空';
			return $rdata;
		}
		if(!is_filename($param['SerialNumber']) || strlen($param['SerialNumber']) < 18 || strlen($param['SerialNumber']) > 40){
			$rdata['rStatus'] = 101;
			$rdata['message']   = '请输入合法的序列号';
			return $rdata;
		}
		if(!is_filename($param['PassWord']) || strlen($param['PassWord']) < 3 || strlen($param['PassWord']) > 32){
			$rdata['rStatus'] = 101;
			$rdata['message']   = '请输入合法的密码！(6-32位数字、字母和下划线)';
			return $rdata;
		}
		$param['SerialNumber'] = strtolower($param['SerialNumber']);
		$param['PassWord']     = strtolower($param['PassWord']);

		$ruinfo = $db->get_row ( "select ID,Password,Status,RunStatus,CompanyID from ".DB_DATABASEU.DATATABLE."_api_serial where SerialNumber='" . $param['SerialNumber'] . "' limit 0,1" );
		
		if(empty($ruinfo['ID'])){
			$rdata['rStatus'] = 101;
			$rdata['message'] = '授权失败';
		}elseif($ruinfo['Password'] != $param['PassWord']){
			$rdata['rStatus'] = 101;
			$rdata['message'] = '密码不正确';
		}elseif($ruinfo['Status'] == 'F'){
			$rdata['rStatus'] = 101;
			$rdata['message'] = '该接口已停用';
		}else{
			$rdata['rStatus'] = 100;
			$rdata['message'] = 'Tocken授权码获取成功';
			$token = md5 ( $ruinfo['ID'].$param['SerialNumber'] . $ruinfo['Password'].time());
			$db->query ( "update ".DB_DATABASEU.DATATABLE."_api_serial set Token='".$token."' where ID=" . $ruinfo ['ID'] . " limit 1" );
			$rdata['sKey']    = $token;
		}
        
		//记录日志
		self::seaslog('getToken', __METHOD__, $rdata);
		
		return $rdata;
	}

	/**
    * 验证sKey,获取公司信息
	*@param string skey
	*@return array $rdata(rStatus,error,CompanyID,CompanyDatabase) 状态，提示信息，公司ID,数据库
    */
	protected function getCompanyInfo($param){
		global $db;

		if (empty($param))
		{
			$rdata['rStatus'] = 101;
			$rdata['error']   = '参数错误!';
		}else{
			$cinfo = array();
			$cinfo = $db->get_row ( "select CompanyID,CompanyDatabase,Status,RunStatus from " .DB_DATABASEU.DATATABLE. "_api_serial where Token='".$param."' limit 0,1" );

			if(empty($cinfo['CompanyID'])) {
				$rdata['rStatus'] = 101;
				$rdata['error']   = '验证key过期';
			} else if($cinfo['Status'] == 'F') {
                $rdata['rStatus'] = 101;                
                $rdata['error'] = '接口已停用!';
            }else{
				$rdata['rStatus'] = 100;
				$rdata['CompanyID']   = $cinfo['CompanyID'];
                $setInfo = $db->get_var("SELECT SetValue FROM ".DB_DATABASEU.DATATABLE."_order_companyset where SetCompany = ".$cinfo['CompanyID']." and SetName='erp' limit 0,1");
                $rdata['setInfo'] = $setInfo ? unserialize($setInfo) : array();
				if(empty($cinfo['CompanyDatabase'])) $rdata['Database'] = DB_DATABASE.'.'; else $rdata['Database'] = DB_DATABASE."_".$cinfo['CompanyDatabase'].'.';

			}
		}
		$rdata['debug_info']['Company_id']   = $cinfo['CompanyID'];
		return $rdata;
	}

    /**
     * @desc 获取公司账号信息
     * @param $param (CompanyID)
     * @return array
     */
    protected function getCsInfo($param){
        global $db;
        $rData = array();
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['CompanyID'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '参数错误';
        }else{
            $cs_sql = "SELECT CS_Number,CS_BeginDate,CS_EndDate,CS_SmsNumber,CS_UpDate,CS_UpdateTime FROM ".DB_DATABASEU.DATATABLE."_order_cs WHERE CS_Company=".$param['CompanyID'];
            $csInfo = $db->get_row($cs_sql);
            $rData['cs_sql'] = $cs_sql;
            
            if(empty($csInfo)){
            	
                $rData['rStatus'] = 101;
                $rData['error'] = '数据为空';
            }else{

                $rData['rStatus'] = 100;
                $rData['rData']   = $csInfo;
            }
        }
        
        self::seaslog($param['CompanyID'], __METHOD__ . ' return', $rData);
        return $rData;
    }

    /**
     * @desc 获取商品编码等信息
     * @param $param (sKey,flag,begin,step)
     * @return array
     * @since 2015-08-17
     */
    public function getCoding($param) {
        global $db;
        $rData = array('rStatus' => 100);
        $param = json_decode(json_encode($param),true);
        if(empty($param['sKey'])) {
            $rData['rStatus'] = 101;
            $rData['error'] = '验证key必需';
            
            return $rData;
        } else {
            $cidarr = self::getCompanyInfo($param['sKey']);
            $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
            if($cidarr['rStatus'] == 101) return $cidarr;

            $cid 		= $cidarr['CompanyID'];
            $sdatabase  = $cidarr['Database'];
            
            self::seaslog($cid, __METHOD__ . ' param', $param);
            
            $whereStr = "";
            if(isset($param['flag']) && $param['flag'] == 0) {
                //上架的商品
                $whereStr .= " AND FlagID=0";
            } else if($param['flag'] == 1) {
                //下架的商品
                $whereStr .= " AND FlagID=1";
            }
            $goods_sql = "SELECT Coding,GUID,Barcode FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} " . $whereStr;
            $list = $db->get_results($goods_sql . " LIMIT " . $param['begin'] . " , " . $param['step']);
            $rData['rData'] = $list;
            $rData['debug_info']['list_sql'] = $db->last_query;
        }

        self::seaslog($cid, __METHOD__ . ' return', $rData);
        //if(!isset($param['debug'])) unset($rData['rDebug']);

        return $rData;
    }

    /**
     * @desc 商品外码转内码 Coding唯一,GUID唯一
     * @param $param (sKey,body)
     * @body[0] (guid,coding)
     * @return array $rData
     * @since 2015-06-29
     */
    public function productOuterToInner($param) {
        global $db;
        $rData = array('rStatus'=>100);
        $param = json_decode(json_encode($param),true);
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '验证key必须';
            
            return $rData;
        } else if($param['count'] != count($param['body'])) {
            $rData['rStatus'] = 101;
            $rData['error'] = '数据传输错误!';
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
            if($cidarr['rStatus']==101)  return $cidarr;
            $cid		= $cidarr['CompanyID'];
            $sdatabase	= $cidarr['Database'];
            
            //记录日志
            self::seaslog($cid, __METHOD__ . ' param', $param);
            
            $errItems = array();
            foreach($param['body'] as $val) {
                $sql = "UPDATE  " . $sdatabase . DATATABLE."_order_content_index SET GUID='".$val['guid']."',ERP='T' WHERE CompanyID={$cid} AND Coding='".$val['coding']."'";
                $rData['debug_info']['sql'][] = $sql;
                $rst = $db->query($sql);
                if($rst === false) {
                    $errItems[] = $val;
                }
            }
            //coding
            if($errItems) {
                $rData['rStatus'] = 101;
                $rData['error'] = "商品外码转内码失败!";
                $rData['rData'] = $errItems;
            }
        }
        //记录日志
        self::seaslog($cid, __METHOD__ . ' return', $rData);
        //if(!$param['debug'])  unset($rData['debug']);
        
        return $rData;
    }

    /**
     * @desc 药店外码转内码
     * @param $param (sKey,type,body) type => name/coding  default coding
     * @body[0] (clientGUID,clientNO,clientName) , clientName => ClientCompanyName
     * @return array $rData;
     * @since 2015-06-29
     */
    public function clientOuterToInner($param) {
        global $db;
        
        $rData = array('rStatus'=>100);
        $param = json_decode(json_encode($param),true);
        $param['type'] = $param['type'] ? $param['type'] : 'guid';
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error']   = '验证key必须';
            
            return $rData;
        } else if($param['count'] != count($param['body'])) {
            $rData['rStatus'] = 101;
            $rData['error']   = '数据传输错误!';
            
            return $rData;
        } else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
            if($cidarr['rStatus']==101) return $cidarr;
            $cid 		= $cidarr['CompanyID'];
            $sdatabase  = $cidarr['Database'];
            
            //记录日志
            self::seaslog($cid, __METHOD__ . ' param', $param);
            
            $errItems   = array();  //存储失败的药店
            $emptyItems = array();  //存储GUID为空的药店
            foreach($param['body'] as $val) {
            
               //根据外码更新内码。若外码不存在则跳过 by wanjun @20151218
               $val['clientGUID'] = trim($val['clientGUID']);
               if(!strlen($val['clientGUID'])){
                   $emptyItems[] = $val;
                   continue;
               }
                
               $val['clientNO']   = mysql_real_escape_string($val['clientNO']);
               $val['clientGUID'] = mysql_real_escape_string($val['clientGUID']);
               //开始更新内码
                $outSql = "UPDATE ".$sdatabase.DATATABLE."_order_client SET ClientGUID='{$val['clientGUID']}',ERP='T' WHERE ClientCompany={$cid} AND ClientNO='{$val['clientNO']}'";
                
                $rData['debug_info']['upguid'][] = $outSql;
                $rst = $db->query($outSql);
                if($rst === false) {
                    $errItems[] = $val;
                }
            }
            //coding
            if($errItems) {
                $rData['rStatus'] = 100;
                $rData['error']   = "部分药店外码转内码失败，请核对档案!";
                $rData['rData']['error'] = $errItems;
            }
            if($emptyItems) $rData['rData']['unexist'] = $emptyItems;
        }
        
        //记录日志
        self::seaslog($cid, __METHOD__ . ' return', $rData);
        //if(!isset($param['debug']))  unset($rData['debug']);
        
        return $rData;
    }

	/**
    * 获取订单列表
	*@param array $param(sKey,flag,begin,step) key,起始值，步长
	*@return array $rdata(rStatus,error,rData) 状态，提示信息，数据
    */
	public function getOrderList($param){
		global $db;
		if(is_object($param)) $param = (array)$param;

		if (empty ( $param['sKey'] ))
		{
			$rdata['rStatus'] = 101;
			$rdata['message']   = '验证key必需';
			
			return $rdata;
		}else{
			$cidarr = $this->getCompanyInfo($param['sKey']); //取公司ID,Database
			$rdata['debug_info']['Company_id'] = $cidarr['CompanyID'];
			if($cidarr['rStatus'] == "101"){
				return $cidarr;
			}else{
				$cid		= $cidarr['CompanyID'];
				$sdatabase	= $cidarr['Database'];
				$setInfo	= $cidarr['setInfo'];
                //记录日志
                self::seaslog($cid, __METHOD__ . ' param', $param);
                
                $flags = array(
                    'pending'		=> 0,    //待审核
                    'stocking'		=> 1,    //备货中
                    'outLibrary'	=> 2,    //已出库
                    'receiving'		=> 3,    //已收货
                    'receivables'	=> 5, 	 //已收款
                    'complete'		=> 7,    //已完成
                );

                $flag_where = "";
                //统一使用 OrderPayStatus 作为付款状态             
                if(isset($param['flag']) && $param['flag'] == 'receivables'){
                    $flag_where = " AND o.OrderPayStatus=2";
                }
                else if(isset($flags[$param['flag']])) {
                    $flag_where = " AND o.OrderStatus=" . $flags[$param['flag']];
                }
				
                //记录日志
                self::seaslog($cid, __METHOD__ . ' setInfo', $setInfo);
                
//                 $check = $setInfo['erp_order_check'] == 'Y' ? '1' : '0,1';
                //无需审核时，取消状态复核限制
                if($setInfo['erp_order_check'] == 'Y'){
                    $check = '1';
                }else{
                    $check = '0,1';
                    $flag_where = "";
                }
//                 $check = '0,1';

                //是否指定了时间
                if (!empty($param['timestart'])){
                	$flag_where .= " and o.OrderDate>=".strtotime($param['timestart']);
                }
                if (!empty($param['timeend'])){
                	$flag_where .= " and o.OrderDate<=".strtotime($param['timeend']." 23:59:59");
                }
                
                //是否获取全部列表
                $isAll = $param['type'] == 'all' ? '' : " AND OrderApi='F' ";
                
                $check .= ",2,3,5,7";
				$sql    = "select 
								o.OrderSN,o.DeliveryDate,o.OrderRemark,o.OrderTotal,o.OrderStatus,
								o.OrderDate,o.OrderType,o.OrderSendType,c.lastOrderAt,c.ClientNO,c.ClientGUID 
							from 
								".$sdatabase.DATATABLE."_order_orderinfo as o 
							LEFT JOIN ".$sdatabase.DATATABLE."_order_client as c 
								ON c.ClientID=o.OrderUserID 
							where 
								o.OrderCompany=".$cid." 
								and OrderStatus IN ({$check}) {$flag_where} 
								".$isAll;
				$sql .= " limit ".$param['begin'].",".intval($param['step']);
				
				//记录SQL
                $rdata['debug_info']['SQL'] = $sql;
                
				$oinfo  = $db->get_results ( $sql );

				if(empty($oinfo)) {
					$rdata['rStatus'] = 101;
					$rdata['message']   = '无符合条件的数据';
                    //记录日志
                    self::seaslog($cid, __METHOD__. ' error', array('message' => "获取订单列表数据为空或异常", 'sql' => $sql));
				}else{
					
					$senttypearr = array (
							'1' => '送货上门',
							'2' => '快递',
							'3' => '货运',
							'4' => '上门自取'
					);
					
                    foreach($oinfo as $key=>$val){
                        $oinfo[$key]['OrderSN'] 		   = 'ET'.$val['OrderSN'];
                        $oinfo[$key]['OrderSendTypeTrans'] = $senttypearr[$oinfo[$key]['OrderSendType']];
                    }
					$rdata['rStatus'] = 100;
					$rdata['message'] = '获取订单列表完成';
                    $rdata['count']   = count($oinfo);
					$rdata['rData']   = $oinfo;
				}
			}
		}
		//记录日志
		self::seaslog($cid, __METHOD__ . ' return', $rdata);
		//if(!isset($param['debug'])) unset($rdata['rDebug']);
		
		return $rdata;
	}

	/**
    * 获取订单明细
	*@param array $param(sKey,orderSn) key,订单号
	*@return array $rdata(rStatus,error,rData) 状态，提示信息，数据
    */
	public function getOrderContent($param){
		global $db;
		
		if(is_object($param)) $param = (array)$param;
        $param['orderSn'] = str_replace('ET', '', $param['orderSn']);
        $param['orderSn'] = str_replace('ONG', '201', $param['orderSn']);
        
		if (empty ( $param['sKey'] )){
			$rdata['rStatus'] = 101;
			$rdata['message'] = '验证key必需';
			
			return $rdata;
		} else if( empty($param['orderSn'])){
            $rdata['rStatus'] = 101;
            $rdata['message']   = '订单号不能为空!';
            
            return $rdata;
        }else{
			$cidarr = $this->getCompanyInfo($param['sKey']); //取公司ID,Database
			$rdata['debug_info']['Company_id'] = $cidarr['CompanyID'];
			$cid    = @$cidarr['CompanyID'];
			$sdatabase = @$cidarr['Database'];
			
			if($cidarr['rStatus'] == "101"){
				return $cidarr;
			}else{
				//记录日志
				self::seaslog($cid, __METHOD__ . ' param', $param);
				
				//取单头
				$sql    = "select
								o.OrderID,o.OrderSN,o.OrderSendType,o.InvoiceTax,o.DeliveryDate,
								o.OrderRemark,o.OrderTotal,o.OrderStatus,o.OrderDate,c.ClientNO,
								c.ClientGUID AS guid,o.OrderApi,o.OrderReceiveName,o.OrderReceiveCompany,
								o.OrderReceivePhone,o.OrderReceiveAdd,s.SalerID 
							from 
								".$sdatabase.DATATABLE."_order_orderinfo o 
							left join 
								".$sdatabase.DATATABLE."_order_client c 
								ON o.OrderUserID=c.ClientID 
							left join 
								".$sdatabase.DATATABLE."_order_salerclient s 
								ON c.ClientID=s.ClientID 
							where 
								o.OrderCompany=".$cid." 
								and o.OrderSN='".$param['orderSn']."' 
								and c.ClientCompany=".$cid." 
							limit 0,1";
				$oinfo  = $db->get_row ( $sql );

				if(!empty($oinfo)){
					
					
					//取业务员电话
					if(!empty($oinfo['SalerID'])){
						$userinfo = $db->get_row ( "select UserMobile from " .DB_DATABASEU.DATATABLE. "_order_user where UserCompany=".$cid." and UserID =".$oinfo['SalerID']." limit 0,1" );
						if(!empty($userinfo['UserMobile']))$oinfo['UserMobile'] = $userinfo['UserMobile'];
						else $oinfo['UserMobile'] = '';
					}else{
						$oinfo['UserMobile'] = '';
					}
					unset($oinfo['SalerID']);
					if(empty($oinfo['UserMobile'])) unset($oinfo['UserMobile']);
	
	               //标准接口中，ERP识别@符号
	//                 $oinfo['OrderRemark'] .= "@@@{$oinfo['OrderReceiveCompany']}@@@{$oinfo['OrderReceiveName']}@@@{$oinfo['OrderReceivePhone']}@@@{$oinfo['OrderReceiveAdd']}@@@{$oinfo['UserMobile']}";
	
					$sqls   = "select Name from ".$sdatabase.DATATABLE."_order_ordersubmit where CompanyID=".$cid." and OrderID=".$oinfo['OrderID']." and Status='审核订单' limit 0,1";
					$sinfo  = $db->get_row ( $sqls );
					$oinfo['AdminUser'] = $sinfo['Name'];
					//if(empty($oinfo['AdminUser'])) unset($oinfo['AdminUser']);
					
					$shieldCondition = '';
					//商品明细
					$sqlc   = "select SiteID,GUID as guid,Name,Coding,Units,ContentColor,ContentSpecification,ContentPrice,ContentNumber,ContentPercent,'c' as conType from ".$sdatabase.DATATABLE."_view_index_cart where OrderID=".$oinfo['OrderID']." and CompanyID=".$cid." ".$shieldCondition;
					$cinfo  = $db->get_results ( $sqlc );
					//赠品明细
	                $sqlg   = "select SiteID,GUID,Name,Coding,Units,ContentColor,ContentSpecification,'0' as ContentPrice,ContentNumber,'g' as conType from ".$sdatabase.DATATABLE."_view_index_gifts where OrderID=".$oinfo['OrderID']." and CompanyID=".$cid." ".$shieldCondition;
	                $ginfo = $db->get_results($sqlg);
	                
	                //商品分类
	                $sqlSites = "SELECT site.SiteID,site.SiteNO,site.SiteName,apisite.SiteID AS ApiSiteID FROM ".$sdatabase.DATATABLE."_order_site AS site LEFT JOIN ".$sdatabase.DATATABLE."_api_site AS apisite ON site.CompanyID=apisite.CompanyID AND site.SiteID=apisite.TrueSiteID WHERE site.CompanyID=".$cid;
	                $sitesInfo =  $db->get_results($sqlSites);
	                
	                //组合商品份额分类
					$relationSite = $this->_contribulidSite($sitesInfo);
	                $infoall = array();
					for($i=0;$i<count($cinfo);$i++){
						$cinfo[$i]['InvoiceTax']		= $oinfo['InvoiceTax'] / 100;
						$cinfo[$i]['ContentPercent']	= $cinfo[$i]['ContentPercent'] / 10;
						$cinfo[$i]['SiteName']			= @$relationSite[$cinfo[$i]['SiteID']]['SiteName'];		//当前分类名称
						$cinfo[$i]['TopSiteID']			= @$relationSite[$cinfo[$i]['SiteID']]['TopSite'];		//顶级分类ID
						$cinfo[$i]['TopSiteName']		= @$relationSite[$cinfo[$i]['SiteID']]['TopSiteName'];	//顶级分类名称
						$cinfo[$i]['TopSiteNO']			= @$relationSite[$cinfo[$i]['SiteID']]['ApiSiteID'];	//ERP ID
	                    $infoall[] = $cinfo[$i];
					}
	                for($i=0;$i<count($ginfo);$i++) {
	                    $ginfo[$i]['InvoiceTax']		= 0;
	                    $ginfo[$i]['ContentPercent']	= 1;
	                    $ginfo[$i]['SiteName']			= @$relationSite[$ginfo[$i]['SiteID']]['SiteName'];		//当前分类名称
						$ginfo[$i]['TopSiteID']			= @$relationSite[$ginfo[$i]['SiteID']]['TopSite'];		//顶级分类ID
						$ginfo[$i]['TopSiteName']		= @$relationSite[$ginfo[$i]['SiteID']]['TopSiteName'];	//顶级分类名称
						$ginfo[$i]['TopSiteNO']			= @$relationSite[$ginfo[$i]['SiteID']]['ApiSiteID'];	//ERP ID
	                    $infoall[] = $ginfo[$i];
	                }
	                
					if(empty($oinfo)) {
						$rdata['rStatus'] = 101;
						$rdata['message'] = "数据为空，获取订单[{$param['orderSn']}]表头失败";
					}else{
	                    $oinfo['OrderSN'] = 'ET'.$oinfo['OrderSN'];
	                    //添加取过标记 通知时才更改
	                    $db->query("UPDATE ".$sdatabase.DATATABLE."_order_orderinfo SET OrderApi='T' WHERE OrderID=".$oinfo['OrderID']);
						unset($oinfo['OrderID'],$oinfo['InvoiceTax']);
						$rdata['rStatus'] = 100;
	                    $rdata['count']   = count($infoall);
	                    $rdata['message'] = '获取单据详情完毕';
						$rdata['rData']['header']   = $oinfo;
						$rdata['rData']['body']   	= $infoall;// $cinfo;
						$rdata['rData']['sql'] 		= $sql;
	                    if(count($infoall) == 0) {
	                    	//记录日志
	                    	self::seaslog($cid, __METHOD__. ' error', array('message' => "获取订单[{$param['orderSn']}]明细数据失败", 'sql' => array('买品' => $sqlc, '赠品' => $sqlg)));
	                        
	                    }
					}
				}else{
					$rdata['rStatus'] = 101;
					$rdata['message'] = "数据为空，获取订单[{$param['orderSn']}]表头失败";
				}//empty($oinfo)
			}
		}
		
		//记录日志
		self::seaslog($cid, __METHOD__ . ' return', $rdata);
		//if(!isset($param['debug'])) unset($rdata['rDebug']);
		
		return $rdata;
	}


    /**
     * 订单获取成功后通知DHB将OrderApi设置为T
     * @param array $param (sKey,body)
     * @return array $rData
     * @since 2015-06-15
     */
    public function orderNotify($param = array()){
        global $db;
        $rData = array('rStatus' => 100);
        $param  = json_decode(json_encode($param),true);
        $data = $param['body'];
        if(empty($param['sKey'])) {
            $rData['rStatus'] = 101;
            $rData['error'] = '验证key必需';
            
            return $rData;
        }

        $cidarr = $this->getCompanyInfo($param['sKey']);
        $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
        $sdatabase = $cidarr['Database'];
        if($cidarr['rStatus'] == 101) return $cidarr;
        
        //记录日志
        self::seaslog($cidarr['CompanyID'], __METHOD__ . ' param', $param);

        foreach($data as  $key => $sn) {
            $data[$key] = str_replace(array('ET','RC'),'',$sn);
        }
        $isSuccess = true;
        foreach(array_chunk($data,50) as $sns) {
            $result = $db->query("UPDATE ".$sdatabase.DATATABLE."_order_orderinfo SET OrderApi='T' WHERE OrderCompany={$cidarr['CompanyID']} AND OrderSN IN(".implode(",",array_map("add_quotes",$sns)).")");
            $isSuccess = $result === false ? false : $isSuccess;
            if($result === false) {
                //记录日志
                self::seaslog($cid, __METHOD__. ' error', array('message' => "处理订单通知失败", 'sql' => $db->last_query));
            }
        }
        if(!$isSuccess) {
            $rData['rStatus'] = 101;
            $rData['error'] = '通知处理失败!';
        }
        //记录日志
        self::seaslog($cidarr['CompanyID'], __METHOD__ . ' return', $rData);

        return $rData;
    }

    /**
     * @desc 更新订单状态
     * @param array $param (sKey,count,body)
     * body array(
            array('orderSN'=>'',status=>'close|open|del'),
     * )
     * @return array $rData
     */
    public function orderStatus($param){
        global $db;
        $rData = array('rStatus'=>100 , 'message' => '接口更新订单状态执行完毕');
        $eOrderSN = array();
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须';
            
            return $rData;
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
            if($cidarr['rStatus']==101)   return $cidarr;
            
            $cid 		= $cidarr['CompanyID'];
            $sdatabase	= $cidarr['Database'];
            $setInfo	= $cidarr['setInfo'];
            //记录日志
            self::seaslog($cid, __METHOD__ . ' param', $param);
            
            $body = $param['body'] = json_decode(json_encode($param['body']),true);
            if(count($body)>1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '单次同步数据只能在1000条以内';
                return $rData;
            }
            if($param['count']!=count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '传输数据错误!';
                return $rData;
            }
            $allowAct = array('open', 'close', 'del');
            $rData['debug_info']['setInfo'] = $setInfo;
            foreach($body as $key=>$val){
                if(empty($val['orderSN'])){
                    $eOrderSN[] = array(
                        'orderSN'=>'',
                        'message'=>'第'.($key+1).'条订单数据缺少医统平台订单编号!',
                    );
                    continue;
                }
                if(!in_array(strtolower($val['status']),$allowAct)){
                    $eOrderSN[] = array(
                        'orderSN'=>$val['orderSN'],
                        'message'=>'未知的操作码：'.$val['status'],
                    );
                    continue;
                }
                //$val['orderSN'] = substr($val['orderSN'],3);
                $init_sn = $val['orderSN'];
                $val['orderSN'] = str_replace('ET','',$val['orderSN']);
                $order = $db->get_row("SELECT * FROM ".$sdatabase.DATATABLE."_order_orderinfo WHERE OrderSN='{$val['orderSN']}' AND OrderCompany={$cid} LIMIT 0,1");
                if(empty($order)){
                    $eOrderSN[] = array(
                        'orderSN'=>'ET'.$val['orderSN'],
                        'message'=>'订单'.$init_sn.'不存在',
                    );
                    continue;
                }
                $orderStatus = null;
                $sql = array();
                switch(strtolower($val['status'])){
                    case 'open':
                        $check = $setInfo['erp_order_check'] == 'Y' ? 1 : 0;
                        $sql[] = "UPDATE ".$sdatabase.DATATABLE."_order_orderinfo SET OrderStatus={$check},OrderSendStatus={$check} WHERE OrderSN='{$val['orderSN']}' AND OrderCompany={$cid}";
                        $sql[] = "INSERT INTO ".$sdatabase.DATATABLE."_order_ordersubmit(CompanyID,OrderID,AdminUser,`Date`,`Status`,Content) VALUES ({$cid},'{$order['OrderID']}','接口','".time()."','打开订单','ERP通过接口打开订单')";
                        break;
                    case 'close'://管理员取消
                    	$querys = "select count(*) totle from ".$sdatabase.DATATABLE."_order_consignment WHERE ConsignmentOrder='{$val['orderSN']}' AND ConsignmentCompany={$cid}";
                    	$order_consignment_num = $db->get_var($querys);
                    	if(!$order_consignment_num){
                    		$sql[] = "UPDATE ".$sdatabase.DATATABLE."_order_orderinfo SET OrderStatus=9 WHERE OrderSN='{$val['orderSN']}' AND OrderCompany={$cid} and OrderApi='T'";
                    		$sql[] = "INSERT INTO ".$sdatabase.DATATABLE."_order_ordersubmit(CompanyID,OrderID,AdminUser,`Date`,`Status`,Content) VALUES ({$cid},'{$order['OrderID']}','接口','".time()."','关闭/取消订单','ERP通过接口关闭/取消订单')";
                    	}
                        break;
                        
                    case 'del':
                    	$querys = "select count(*) totle from ".$sdatabase.DATATABLE."_order_consignment WHERE ConsignmentOrder='{$val['orderSN']}' AND ConsignmentCompany={$cid}";
                    	$order_consignment_num = $db->get_var($querys);
                    	if(!$order_consignment_num){
							$sql[] = "UPDATE ".$sdatabase.DATATABLE."_order_orderinfo SET OrderStatus=9 WHERE OrderSN='{$val['orderSN']}' AND OrderCompany={$cid} and OrderApi='T'";
							$sql[] = "INSERT INTO ".$sdatabase.DATATABLE."_order_ordersubmit(CompanyID,OrderID,AdminUser,`Date`,`Status`,Content) VALUES ({$cid},'{$order['OrderID']}','接口','".time()."','删除订单','ERP通过接口删除订单')";
                    	}
						break;
						
                    default:
                        break;
                }
                
                $result = array();
                foreach($sql as $item){
                    $result[] = $db->query($item);
                }
                $rst = !in_array(false,$result,true);

                $rData['debug_info']['sql'][] = $sql;
                if($rst===false){
                    $eOrderSN[] = array(
                        'orderSN'=>$init_sn,
                        'message'=>'第'.($key+1).'条订单操作失败!',
                    );
                    //记录日志
                    self::seaslog($cid, __METHOD__. ' error', array('message' => "订单状态更新失败", 'sql' => $sql));
                }
            }
        }
        
        if(count($eOrderSN)>0){
            $rData['rStatus'] = 101;
            $rData['message'] = '部分订单操作失败!';
            $rData['rData']   = $eOrderSN;
        }
        //记录日志
        self::seaslog($cid, __METHOD__ . ' return', $rData);
        //if(!$param['debug']) unset($rData['debug']);

        return $rData;
    }

    /**
     * @desc 获取退货单列表
     * @param array $param(sKey,begin,step)
     * @return array $rdata(rStatus,error,rData) 状态,提示,数据
     */
    public function getReturnList($param){
        global $db;
        $rData = array();
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '验证key必须';
            
            return $rData;
        }else{
            $cidarr = $this->getCompanyInfo($param['sKey']);
            $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
            if($cidarr['rStatus']==101) return $cidarr;
            $cid		= $cidarr['CompanyID'];
            $sdatabase	= $cidarr['Database'];
            
            //记录日志
            self::seaslog($cid, __METHOD__ . ' param', $param);

            $sql = "SELECT ReturnSN,ReturnSendAbout,ReturnAbout,ReturnTotal,ReturnDate,ReturnStatus,ReturnType FROM ".$sdatabase.DATATABLE."_order_returninfo WHERE ReturnCompany=".$cid." and (ReturnStatus=3 OR ReturnStatus=5) AND ReturnApi='F'";
            $sql .= " Limit ".$param['begin'].",".$param['step'];
            $rData['selectsql'] = $sql;
            $rinfo = $db->get_results($sql);
            if(empty($rinfo)){
                $rData['rStatus'] = 101;
                $rData['error'] = '数据为空';
                //记录日志
                self::seaslog($cid, __METHOD__. ' error', array('message' => "获取退货单列表数据为空或异常", 'sql' => $sql));
            }else{
                foreach($rinfo as $key=>$val){
                    $rinfo[$key]['ReturnSN'] = ($cid == 577 ? 'RC' : 'DHB').$val['ReturnSN'];
                }
                $rData['rStatus'] = 100;
                $rData['rTotal'] = count($rinfo);
                $rData['rData'] = $rinfo;
            }
        }
        //记录日志
        self::seaslog($cid, __METHOD__ . ' return', $rData);
        
        return $rData;
    }

    /**
     * @desc 获取退货单详细
     * @param array $param(sKey,returnSN)
     * @return array $rData(rStatus,error,rData)
     */
    public function getReturnContent($param){
        global $db;
        $rData = array();
        $param = is_object($param) ? (array)$param : $param;

        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '验证key必须';
            
            return $rData;
        }else{
            //$param['returnSN'] = substr($param['returnSN'],3);
            $param['returnSN'] = str_replace(array('ET','RC'), '', $param['returnSN']);
            $cidarr = $this->getCompanyInfo($param['sKey']);
            $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            if($cidarr['rStatus']==101) return $cidarr;
            $cid = $cidarr['CompanyID'];
            
            //记录日志
            self::seaslog($cid, __METHOD__ . ' param', $param);
            
            //获取退货单基本信息
            $hsql = "SELECT r.ReturnID,r.ReturnOrder,r.ReturnSN,r.ReturnSendAbout,ReturnProductW,ReturnProductB,ReturnAbout,ReturnDate,ReturnType,ReturnTotal,c.ClientNO,c.ClientGUID,r.ReturnApi
                      FROM ".$sdatabase.DATATABLE."_order_returninfo AS r
                      LEFT JOIN ".$sdatabase.DATATABLE."_order_client AS c
                      ON c.ClientID = r.ReturnClient
                      WHERE ReturnCompany=".$cid." AND ReturnSN='".$param['returnSN']."'
                      Limit 0,1";
            $hdata = $db->get_row($hsql);
            $submitLog = $db->get_row("select Name from ".$sdatabase.DATATABLE."_order_returnsubmit where CompanyID=".$cid." and OrderID=".$hdata['ReturnID']." and Status='审核通过' limit 0,1");
            $rData['sql'] = $hsql;
            
            if(empty($hdata)){
                $rData['rStatus'] = 101;
                $rData['error'] = '数据为空';
                //记录日志
                self::seaslog($cid, __METHOD__. ' error', array('message' => "获取退货单[{$param['returnSN']}]表头失败", 'sql' => $hsql));
            }else{
                $hdata['ReturnSN']		= 'ET'.$hdata['ReturnSN'];
                $hdata['ReturnOrder']	= 'ET'.$hdata['ReturnOrder'];
                $hdata['AdminUser']		= $submitLog['Name'];
                $rData['rData']['header'] = $hdata;

                //获取退货单详细信息 退货数据转换为负数
                $isql = "SELECT i.GUID,i.Coding,ContentID,i.Name,ContentColor,ContentSpecification,ContentPrice,-ContentNumber as ContentNumber,'0' as InvoiceTax
                     FROM ".$sdatabase.DATATABLE."_order_cart_return as r
                     LEFT JOIN ".$sdatabase.DATATABLE."_order_content_index as i
                     ON r.ContentID = i.ID
                     WHERE r.ReturnID=".$hdata['ReturnID']."
                     AND r.CompanyID=".$cid;
                $idata = $db->get_results($isql);
                $rData['rTotal'] = count($idata);
                $rData['rData']['body'] = $idata;
                $rData['rStatus'] = 100;
                
                //记录日志
                self::seaslog($cid, __METHOD__. ' error', array('message' => "未找到退货单[{$param['returnSN']}]详细信息", 'sql' => $isql));
                //更改已取状态 通知的时候才更新状态
                $db->query("UPDATE ".$sdatabase.DATATABLE."_order_returninfo SET ReturnApi='T' WHERE ReturnID=".$hdata['ReturnID']);
            }

        }
        
        //记录日志
        self::seaslog($cid, __METHOD__ . ' return', $rData);
        return $rData;
    }

    /**
     * 订单获取成功后通知DHB将ReturnApi设置为T
     * @param array $param (sKey,body)
     * @return array $rData
     * @author hxtgirq
     * @since 2015-06-15
     */
    public function returnNotify($param = array()){
        global $db;
        $rData = array('rStatus' => 100);
        $param  = json_decode(json_encode($param),true);
        $data = $param['body'];
        if(empty($param['sKey'])) {
            $rData['rStatus'] = 101;
            $rData['error'] = '验证key必须';
            
            return $rData;
        }

        $cidarr = $this->getCompanyInfo($param['sKey']);
        $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
        $sdatabase = $cidarr['Database'];
        if($cidarr['rStatus'] == 101)  return $cidarr;

        //记录日志
        self::seaslog($cidarr['CompanyID'], __METHOD__ . ' param', $param);

        foreach($data as  $key => $sn) {
            $data[$key] = str_replace(array('DHB','RC'),'',$sn);
        }
        $isSuccess = true;
        foreach(array_chunk($data,50) as $sns) {
            $result = $db->query("UPDATE ".$sdatabase.DATATABLE."_order_returninfo SET ReturnApi='T' WHERE ReturnCompany={$cidarr['CompanyID']} AND ReturnSN IN(".implode(",",array_map("add_quotes",$sns)).")");
            $isSuccess = $result === false ? false : $isSuccess;
            if($result === false) {
                //记录日志
                self::seaslog($cid, __METHOD__. ' error', array('message' => "退货单获取通知", 'sql' => $db->last_query));
            }
        }
        if(!$isSuccess) {
            $rData['rStatus'] = 101;
            $rData['error'] = '通知处理失败!';
        }
        
        self::seaslog($cidarr['CompanyID'], __METHOD__ . ' return', $rData);
        return $rData;
    }


    /**
     * @desc 批量添加药店
     * @param array $param (sKey,count,body)
     * body (clientCompanyName,clientNO,clientTrueName,clientArea,bankName)
     * @return array $rData
     * 
     * @history
     * 1、外码不存在，则新增药店；存在则根据外码更新内码 @20151217
     * 2、取消内码存在时，更新档案的操作 @20151217
     */
    public function addDealers($param){
        global $db;
        $rData = array('rStatus'=>100);
        $eClient = array();		//错误的药店资料
        $hasClient = array();	//进行更新内码操作的资料
        $param = is_object($param) ? (array)$param : $param;
        
        if(empty($param['sKey'])){

            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须';
            
            return $rData;
        }else{
        	//验证Skey
            $cidarr 	= $this->getCompanyInfo($param['sKey']);
            $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
            if($cidarr['rStatus']==101)  return $cidarr;
            
            //指定数据库和供应商
            $sdatabase  = $cidarr['Database'];
            $cid		= $cidarr['CompanyID'];
            
            //记录日志
            self::seaslog($cid, __METHOD__ . ' param', $param);
            
            $csInfo = self::getCsInfo(array('CompanyID'=>$cid,'debug'=>$param['debug']));
            if($csInfo['rStatus']==101) return $csInfo;
            
            
            $csInfo = $csInfo['rData'];
            $body = $param['body'] = json_decode(json_encode($param['body']), true);
            
            if(empty($body)){
            	$rData['rStatus'] = 101;
            	$rData['message'] = '没有传递终端数据...';
            	//记录日志
            	self::seaslog($cid, __METHOD__. ' return', $rData);
            	return $rData;
            }

            if(count($body)>1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '单次同步数据只能在1000条以内';
                
                //记录日志
                self::seaslog($cid, __METHOD__. ' return', $rData);
                return $rData;
            }
            if($param['count']!=count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
                self::seaslog($cid, __METHOD__. ' return', $rData);
                return $rData;
            }
            $company_sql = "SELECT CompanyPrefix FROM ".DB_DATABASEU.DATATABLE."_order_company WHERE CompanyID=".$cid;
            $companyInfo = $db->get_row($company_sql);
            $prefix = $companyInfo['CompanyPrefix'];

            include_once (SITE_ROOT_PATH."/class/letter.class.php");
            $letter = new letter();

            //将程序中的药店编码/药店名称读出来
            $list = $db->get_results("SELECT ClientNO,ClientCompanyName,ClientGUID FROM ".$sdatabase.DATATABLE."_order_client WHERE ClientCompany = " . $cid);
            $noExists = array_column($list ? $list : array(),'ClientNO',null);
            $noExists = array_unique(array_filter($noExists));

            $nameExists = array_column($list ? $list : array() , 'ClientCompanyName',null);
            $nameExists = array_unique(array_filter($nameExists));
            
            $guidExists = array_column($list ? $list : array() , 'ClientGUID',null);
            $guidExists = array_unique(array_filter($guidExists));
            
            $editBody = array(); //同步的数据中包含的已存在的药店直接执行对应的更新操作
            foreach($body as $key=>$val){
            	
            	//去除空格
            	$val['companyname'] 	= trim($val['companyname']);
            	$val['clientno'] 		= trim($val['clientno']);
            	$val['password'] 		= trim($val['password']);
            	$val['truename'] 		= trim($val['truename']);
            	$val['email']			= trim($val['email']);
            	$val['phone']			= trim($val['phone']);
            	$val['fax']				= trim($val['fax']);
            	$val['mobile']			= trim($val['mobile']);
            	$val['address']			= trim($val['address']);
            	$val['about']			= trim($val['about']);
            	$val['accountname']		= trim($val['accountname']);
            	$val['bankname']		= trim($val['bankname']);
            	$val['bankaccount']		= trim($val['bankaccount']);
            	$val['invoiceheader']	= trim($val['invoiceheader']);
            	$val['taxpayernumber']	= trim($val['taxpayernumber']);
            	
                $isok = true;
                if(!isset($val['clientno']) || $val['clientno'] == ""){
                    $eClient[] = array_merge($val,array('message' => '药店编号不能为空'));
                    $isok = false;
                }
                else if(!isset($val['guid']) || $val['guid'] == ""){
                    $eClient[] = array_merge($val,array('message' => '药店GUID不能为空'));
                    $isok = false;
                }
                else if(empty($val['companyname'])){
                    $eClient[] = array_merge($val,array('message' => '药店名称不能为空!'));
                    $isok = false;
                }else{
                    
                    //↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
                    $val['clientno'] = mysql_real_escape_string($val['clientno']);
                    
                    //验证外码是否存在，存在则更新内码;不存在则新增档案
                    $ckSql = "SELECT COUNT(*) codeRow FROM ".$sdatabase.DATATABLE."_order_client WHERE ClientCompany={$cid} AND ClientNO='{$val['clientno']}'";
                    $codeIsExist = $db->get_var($ckSql);
                    if($codeIsExist){//存在则更新内码
                    	$hasClient[] = $val;
                        $outSql = "UPDATE ".$sdatabase.DATATABLE."_order_client 
                                    SET 
                                        ClientGUID='{$val['guid']}' 
                                    WHERE 
                                        ClientCompany={$cid} AND ClientNO='{$val['clientno']}'";
                        $db->query($outSql);
                        $rData['debug_info'][$key]['upguid'] = $outSql;
                        continue;
                    }
                    
                    //↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑
                    
                }
                
                //生成随机账号
                list($sec, $mic) = explode('.', microtime(true));
                $acc = substr($val['clientno'], -4).$mic;
                
                //$val['clientName']     = $prefix.'-'.$val['clientno'];//药店账号要通过clientNO生成
                $val['clientName']     = $prefix.'-'.$acc;//药店账号要通过clientNO生成
                $val['clientPassword'] = $val['password'] ? $val['password'] : '123456';
                //$val['clientLevel']    = '';//药店等级
                //获取对应地区
                
                $val['clientArea'] = (int)$val['areaid'];

                $val['clientCompanyPinyi'] = $letter->C($val['companyname']);
                $val['clientTrueName']     = $val['truename'] ? $val['truename'] : '';
                $val['clientEmail']        = $val['email'] ? $val['email'] : '';
                $val['clientPhone']        = $val['phone'] ? $val['phone'] : '';
                $val['clientFax']          = $val['fax'] ?  $val['fax'] : '';
                $val['clientMobile']       = $val['mobile'] ?  $val['mobile'] : '';
                $val['clientAdd']          = $val['address'] ? $val['address'] : '';
                $val['clientAbout']        = $val['about'] ? $val['about'] : '';
                $val['clientShield']       = ''; //屏壁分类 本地程序操作
//                 $val['clientSetPrice']     = $val['setprice'] ? $val['setprice'] : 'Price1';//默认执行价格一
                $val['clientSetPrice']     = 'Price1';//默认执行价格一
                $val['clientSetPrice']	   = ucfirst($val['clientSetPrice']);
                $val['clientPercent']      = $val['percent'] ? $val['percent'] : '10.00';
                $val['clientBrandPercent'] = '';//品牌折扣
                $val['clientPay']          = '';//支付类型
                $val['clientConsignment']  = '';
                $val['bankName']           = $val['bankname'] ? $val['bankname'] : '';
                $val['bankAccount']        = $val['bankaccount'] ? $val['bankaccount'] : '';
                $val['accountName']        = $val['accountname'];
                $val['invoiceHeader'] 	   = $val['invoiceheader'];
                $val['taxpayerNumber']	   = $val['taxpayernumber'];
                
                //营业执照有效期
                $val['businessvalidity'] = str_replace('.', '-', $val['businessvalidity']);
                $val['businessvalidity'] = date('Y-m-d', strtotime($val['businessvalidity']));
                if($val['businessvalidity'] == '1970-01-01'){
                	$val['businessvalidity'] = '';
                }
                //GSP/GMP有效期
                $val['gsmpvalidity'] = str_replace('.', '-', $val['gsmpvalidity']);
                $val['gsmpvalidity'] = date('Y-m-d', strtotime($val['gsmpvalidity']));
                if($val['gsmpvalidity'] == '1970-01-01'){
                	$val['gsmpvalidity'] = '';
                }
                //许可证有效期
                $val['licencevalidity'] = str_replace('.', '-', $val['licencevalidity']);
                $val['licencevalidity'] = date('Y-m-d', strtotime($val['licencevalidity']));
                if($val['licencevalidity'] == '1970-01-01'){
                	$val['licencevalidity'] = '';
                }
                
                
                if(mb_strlen($val['bankname'],"utf-8") > 50) {
                    $eClient[] = array_merge($val,array('message' => '开户行名称长度超过50个汉字,请重试!'));
                    self::seaslog($cid, __METHOD__. ' error', array('message' => '新增药店,开户行名称超长', 'data' => $val));
                    $isok = false;
                }
                
                if(!$isok){
                    continue;
                }
                
                if(in_array($val['guid'], $guidExists, true)) {
                	$eClient[] = array_merge($val,array('message' => 'ERP内码已使用['.$val['guid'].']'));
                	self::seaslog($cid, __METHOD__. ' error', array('message' => "ERP内码已使用[".$val['guid']."]", 'data' => $val));
                	continue;
                }
                $guidExists[] = $val['guid'];
               
                if(in_array($val['clientno'], $noExists, true)) {
                    $eClient[] = array_merge($val,array('message' => '药店编号已使用['.$val['clientno'].']'));
                    self::seaslog($cid, __METHOD__. ' error', array('message' => "药店编号已使用[".$val['clientno']."]", 'data' => $val));
                    continue;
                }

                if(is_phone($val['clientPhone'])) {
                    //查看当前手机号是否已做为登录账号
                    $cnt = $db->get_var("SELECT COUNT(*) as cnt FROM ".DB_DATABASEU.DATATABLE."_order_dealers WHERE ClientCompany={$cid} LIMIT 1");
                    if($cnt == 0) {
                        $val['clientMobile'] = $val['clientPhone'];
                    }
                }
                $dealers_sql = "INSERT INTO ".DB_DATABASEU.DATATABLE."_order_dealers (ClientCompany,ClientName,ClientPassword,ClientMobile) VALUES(".$cid.",'".$val['clientName']."','".$val['clientPassword']."','".$val['clientMobile']."')";
                $rData['debug_info']['dealers'][$key]['dealers'] = $dealers_sql;
                
                if(false!==$db->query($dealers_sql)){
                    $inid = $db->insert_id;
                    $client_sql = "INSERT INTO ".$sdatabase.DATATABLE."_order_client(ClientID,ClientCompany,ClientLevel,ClientName,ClientCompanyName,ClientCompanyPinyi,ClientNO,ClientTrueName,ClientEmail,
                ClientPhone,ClientFax,ClientMobile,ClientAdd,ClientAbout,ClientDate,ClientShield,ClientSetPrice,ClientPercent,
                ClientBrandPercent,ClientPay,ClientConsignment,AccountName,BankName,BankAccount,InvoiceHeader,TaxpayerNumber,ClientGUID,ERP,ClientArea,BusinessValidity,GsmpValidity,LicenceValidity)
                               VALUES(".$inid.",".$cid.",'".$val['clientLevel']."','".$val['clientName']."','".$val['companyname']."','".$val['clientCompanyPinyi']."','".$val['clientno']."','".$val['clientTrueName']."','".$val['clientEmail']."','".$val['clientPhone']."',
                               '".$val['clientFax']."','".$val['clientPhone']."','".$val['clientAdd']."','".$val['clientAbout']."',".time().",'".$val['clientShield']."','".$val['clientSetPrice']."','".$val['clientPercent']."',
                               '".$val['clientBrandPercent']."','".$val['clientPay']."','".$val['clientConsignment']."','".$val['accountName']."','".$val['bankName']."','".$val['bankAccount']."','".$val['invoiceHeader']."','".$val['taxpayerNumber']."','{$val['guid']}','T',".$val['clientArea'].",'".$val['businessvalidity']."','".$val['gsmpvalidity']."','".$val['licencevalidity']."')";
                    
                    $booRst = $db->query($client_sql);
                    if(!$booRst){
                        
                        self::seaslog($cid, __METHOD__. ' error', array('message' => "添加药店失败-client", 'sql' => $client_sql));
                        $db->query("DELETE FROM " .$sdatabase.DATATABLE."_order_dealers WHERE ClientCompany= ".$cid." AND ClientID=" . $inid);
                        $eClient[] = array_merge($val,array('message' => '药店client保存失败!'));
                    }
                    $rData['debug_info']['dealers'][$key]['dealers_son'] = $client_sql;
                }else{
                    $eClient[] = array_merge($val,array('message' => '药店dealers保存失败!'));
                    self::seaslog($cid, __METHOD__. ' error', array('message' => "添加药店失败-dealers", 'sql' => $dealers_sql));
                }
            }
            
        }

        //药店名称已使用时，虽然返回成功的状态，但还是要把错误信息返回给他们
        if(!empty($param['sKey'])){
        	$rData['rStatus'] = 100;
        }
        if($eClient || $hasClient){
        	$rData['rStatus'] = 101;
            $rData['rData'] = array_merge(array('error' => $eClient), array('exist' => $hasClient));
        }
        $rData['message'] = '添加药店档案执行完毕，错误：'.count($eClient).' 个，重复传递：'.count($hasClient).'个，详情请见日志记录';
        
        self::seaslog($cid, __METHOD__. ' return', $rData);
        
        if(!$param['debug']){
        	//unset($rData['debug']);
        }
        
        unset($eClient, $hasClient, $param, $guidExists, $body);
        return $rData;
    }

    /**
     * @desc 批量修改药店
     * @param array $param (sKey,count,body)
     * body (clientCompanyName,clientNO,clientTrueName,clientArea,bankName,status)
     * @return array $rData
     * //修改药店未验证药店数量
     * @deprecated 
     *  1、修改药店资料时，未修改账号
     *  2、根据内码检查是否已存在于DHB，存在则更新
     *  3、更新药店档案时：外码、名称、状态、联系人发生更新 by wanjun @20151215
     */
    public function updateDealers($param){
        global $db;
        $rData = array('rStatus'=>100);
        $eClient = array();
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须';
            
            return $rData;
        }else{
            $cidarr = $this->getCompanyInfo($param['sKey']);
            $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
            if($cidarr['rStatus']==101) return $cidarr;
            
            $sdatabase  = $cidarr['Database'];
            $cid 		= $cidarr['CompanyID'];
            self::seaslog($cid, __METHOD__ . ' param', $param);

            $body = $param['body'] = json_decode(json_encode($param['body']),true);
            if(count($body)>1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '单次同步数据只能在1000条以内';
                self::seaslog($cid, __METHOD__ . ' return', $rData);
                return $rData;
            }
            if($param['count']!=count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
                self::seaslog($cid, __METHOD__ . ' return', $rData);
                return $rData;
            }
            include_once (SITE_ROOT_PATH."/class/letter.class.php");
            $letter = new letter();
            $del_client = array();

            $list = $db->get_results("SELECT ClientNO,ClientCompanyName,ClientGUID FROM ".$sdatabase.DATATABLE."_order_client WHERE ClientCompany = " . $cid);
            $noExists = array_column($list ? $list : array(),'ClientNO','ClientGUID');
            $noExists = array_unique(array_filter($noExists));

            $nameExists = array_column($list ? $list : array() , 'ClientCompanyName','ClientGUID');
            $nameExists = array_unique(array_filter($nameExists));

            foreach($body as $key=>$val){
            	
            	$val['clientno']		= trim($val['clientno']);
            	$val['truename']		= trim($val['truename']);
            	$val['email']			= trim($val['email']);
            	$val['phone']			= trim($val['phone']);
            	$val['fax']				= trim($val['fax']);
            	$val['address']			= trim($val['address']);
            	$val['about']			= trim($val['about']);
            	$val['accountname']		= trim($val['accountname']);
            	$val['bankname']		= trim($val['bankname']);
            	$val['bankaccount']		= trim($val['bankaccount']);
            	$val['taxpayernumber']	= trim($val['taxpayernumber']);
            	$val['businessvalidity']	= trim($val['businessvalidity']);
            	$val['gsmpvalidity']		= trim($val['gsmpvalidity']);
            	$val['licencevalidity']		= trim($val['licencevalidity']);
            	
                $setInfo = array();
                $dInfo = array();
                
                if($val['guid'] == ""){
                	$eClient[] = array_merge($val, array('message'=> '缺少平台和ERP对码：guid'));
                	continue;
                }
                
                $val['status'] = strtoupper($val['status']);
                $val['status'] = $val['status'] == 'D' ? 'D' : 'T';
                //逻辑删除
                if($val['status'] == 'D') {
                    $del_client[] = $val['guid'];
                    continue;
                }
                //根据内码检查，若存在则更新药店档案 ，不存在则跳过处理
                $ckSql = "select count(*) as total from ".$sdatabase.DATATABLE."_order_client WHERE ClientCompany=".$cid." and ClientGUID='".$val['guid']."'";
				$ckGUID = $db->get_var($ckSql);
                if(!$ckGUID) continue;
                
                //更新药店名称
//                 if(!empty($val['companyname'])){
//                     $setInfo[] = "ClientCompanyName='".$val['companyname']."'";
//                     $setInfo[] = "ClientCompanyPinyi='".$letter->C($val['companyname'])."'";
//                 }
                //更新编号
                if(!empty($val['clientno'])){
                	$setInfo[] = "ClientNO='".$val['clientno']."'";
                }
                //更新所在地区
//                 if(!empty($val['areaid'])){
//                 	$setInfo[] = "ClientArea='".((int)$val['areaid'])."'";
//                 }
                //更新联系人信息
                if(!empty($val['truename'])){
                    $setInfo[] = "ClientTrueName='".$val['truename']."'";
                }
                //更新Email
                if(!empty($val['email'])){
                	$setInfo[] = "ClientEmail='".$val['email']."'";
                }
                //更新电话
                if(!empty($val['phone'])){
                	$setInfo[] = "ClientPhone='".$val['phone']."'";
                }
                //更新传真
                if(!empty($val['fax'])){
                	$setInfo[] = "ClientFax='".$val['fax']."'";
                }
                //更新地址
                if(!empty($val['address'])){
                	$setInfo[] = "ClientAdd='".$val['address']."'";
                }
                //更新备注
                if(!empty($val['about'])){
                	$setInfo[] = "ClientAbout='".mysql_escape_string($val['about'])."'";
                }
                //更新开户名称
               if(!empty($val['accountname'])){
                   $setInfo[] = "AccountName='".$val['accountname']."'";
               }
               //更新开户银行
               if(!empty($val['bankname'])){
               	$setInfo[] = "BankName='".$val['bankname']."'";
               }
               //更新银行账号
               if(!empty($val['bankaccount'])){
               	$setInfo[] = "BankAccount='".$val['bankaccount']."'";
               }
               //更新开票抬头
               if(!empty($val['invoiceheader'])){
               	$setInfo[] = "InvoiceHeader='".$val['invoiceheader']."'";
               }
               //更新纳税人识别号
               if(!empty($val['taxpayernumber'])){
               	$setInfo[] = "TaxpayerNumber='".$val['taxpayernumber']."'";
               }
               //更新营业执照有效期
               if(!empty($val['businessvalidity'])){
               	$val['businessvalidity'] = str_replace('.', '-', $val['businessvalidity']);
               	$setInfo[] = "BusinessValidity='".date('Y-m-d', strtotime($val['businessvalidity']))."'";
               }
               //更新GSP/GMP有效期
               if(!empty($val['gsmpvalidity'])){
               	$val['gsmpvalidity'] = str_replace('.', '-', $val['gsmpvalidity']);
               	$setInfo[] = "GsmpValidity='".date('Y-m-d', strtotime($val['gsmpvalidity']))."'";
               }
               //更新许可证有效期
               if(!empty($val['licencevalidity'])){
               	$val['licencevalidity'] = str_replace('.', '-', $val['licencevalidity']);
               	$setInfo[] = "LicenceValidity='".date('Y-m-d', strtotime($val['licencevalidity']))."'";
               }
                
                if(!empty($val['status'])){
                    $setInfo[] = $dInfo[] = "ClientFlag=".($val['status']=='T' ? 0 : 9);
                }

                if(in_array($val['clientno'], $noExists) && $val['guid'] && $val['guid'] != array_search($val['clientno'],$noExists)) {
                    $eClient[] = array_merge($val,array('message' => '药店编号已使用'));
                    continue;
                }
                $noExists[] = $val['clientno'];
                
                if(count($setInfo) == 0) {
                    //可以修改的字段一个都没有修改
                    continue;
                }

                if($setInfo && $val['guid'] != ""){
                	//现在根据外码更新了内码
                	$setInfo[] = "ERP='T'";
					$where_ClientFlag=($val['status']=='T') ? "" : " ClientFlag=0 and ";
                    $rst = $db->query("UPDATE ".$sdatabase.DATATABLE."_order_client SET ".implode(',',$setInfo)." WHERE ".$where_ClientFlag." ClientCompany={$cid} and ClientGUID='{$val['guid']}'");
                    $rData['debug_info']['client'][] = $update_client_sql = $db->last_query;
                    if(count($dInfo) > 0) {
                        $drst = $db->query("UPDATE ".DB_DATABASEU.DATATABLE."_order_dealers SET ".implode(',',$dInfo)." WHERE ClientID IN(SELECT ClientID FROM ".$sdatabase.DATATABLE."_order_client WHERE ClientCompany={$cid} AND ClientGUID='{$val['guid']}')");
                        $rData['debug_info']['dealers'][] = $db->last_query;
                        if($drst === false) {
                           self::seaslog($cid, __METHOD__. ' error', array('message' => "更新药店失败-dealers", 'sql' => $db->last_query));
                        }
                    }

                    if($rst===false){
                        $eClient[] = array_merge($val,array('error'=> '修改失败'));
                        self::seaslog($cid, __METHOD__. ' error', array('message' => "更新药店失败-client", 'sql' => $update_client_sql));
                    }
                }

            }
            if(!empty($del_client)) {
                $client_guid_str = "'" . implode("','",$del_client) . "'";
                $client_id = $db->get_col("SELECT ClientID FROM ".$sdatabase.DATATABLE."_order_client WHERE ClientGUID IN(".$client_guid_str.")");
                $client_id = $client_id ? $client_id : array();
                $client_id[] = 0;

                $client_id_str = implode(",",$client_id);
//                 $dealers_del_sql = "DELETE FROM ".DB_DATABASEU.DATATABLE."_order_dealers WHERE ClientID IN({$client_id_str})";
//                 $client_del_sql = "DELETE FROM ".$sdatabase.DATATABLE."_order_client WHERE ClientID IN({$client_id_str})";
//                 $dealers_del_sql = "update".DB_DATABASEU.DATATABLE."_order_dealers WHERE ClientID IN({$client_id_str})";
                $client_del_sql = "update ".$sdatabase.DATATABLE."_order_client set ClientFlag=1 WHERE ClientID IN({$client_id_str})";
                //记录日志
                self::seaslog($cid, __METHOD__. ' error', array('message' => "update-client-sql", 'sql' => $client_del_sql));
                
                $rData['debug_info']['del'] = $client_del_sql;
                //删除Dealers
//                 $ddrst = $db->query($dealers_del_sql);
                //删除Client
                $dcrst = $db->query($client_del_sql);

//                 if($ddrst === false) {
//                     wlog("删除药店失败-dealers" , $dealers_del_sql);
//                 }
                if($dcrst === false) {
                    self::seaslog($cid, __METHOD__. ' error', array('message' => "删除药店失败-client", 'sql' => $client_del_sql));
                }

            }
        }
        
        $rData['message'] = '修改药店档案执行完毕，错误：'.count($eClient).'个';
        if($eClient){
            $rData['rStatus'] = 101;
            $rData['rData'] = $eClient;
        }

        self::seaslog($cid, __METHOD__. ' return', $rData);
        
        //if(!$param['debug'])   unset($rData['debug']);
        
        return $rData;
    }
    
    /**
     *处理药厂名称[先简单粗暴的处理下]
     *@author wanjun
     *@todo 现在处理的情况是ERP中没有特定维护药厂，直接与商品资料传过来的。需要处理ERP中已维护好的资料 
     */
    private function _toBulidBrand($brandName = ''){
    	global $db, $param;
    	
    	if(empty($brandName)) return 0;
    	
    	//获取数据库链接
    	$cidarr = $this->getCompanyInfo($param['sKey']);
    	$sdatabase	= $cidarr['Database'];
    	$cid 		= $cidarr['CompanyID'];
    	
    	//查询当前药批中是否已存在
    	$csql = "select BrandID,BrandName,BrandPinYin,Logo from ".$sdatabase.DATATABLE."_order_brand where CompanyID=".$cid." and BrandName='".$brandName."' limit 1";
    	$binfo = $db->get_row($csql);
    	if($binfo) return $binfo['BrandID'];
    	
    	//查询系统中是否已存在[系统库中查询]
    	$csql = "select BrandName,BrandPinYin,Logo from ".$sdatabase.DATATABLE."_order_brand where BrandName='".$brandName."' limit 1";
    	$binfo = $db->get_row($csql);

    	include_once (SITE_ROOT_PATH."/class/pinyin.php");
    	$pinyima = pinyin($brandName, 'first');
    	$pinyima = strtoupper($pinyima);
    	
    	$isql = "INSERT INTO
                      	".$sdatabase.DATATABLE."_order_brand
                     	(CompanyID,BrandName,BrandPinYin,Logo)
                      VALUES (".$cid.", '".$brandName."', '".($binfo['BrandPinYin'] ? $binfo['BrandPinYin'] : $pinyima)."', '".$binfo['Logo']."')";
    	$db->query($isql);
    	return $db->insert_id;
    	
    }

    /**
     * @desc 批量添加商品
     * @param array $param (sKey,count,body)
     * body (siteID,brandID,name,coding,units,price1,price2,barcode,model,color,specification)
     * @return array $rData
     * 
     * @history
     * 1、外码不存在，则新增商品；存在则根据外码更新内码 @20151217
     * 2、取消内码存在时，更新档案的操作 @20151217
     */
    public function addProduct($param){
        global $db;
        $rData = array(
            'rStatus'=>100,
        	'message' => '添加商品档案执行完毕'
        );
        
        //添加失败的商品编码
        $eCoding = array();
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须';
            
            return $rData;
        }else{
            $cidarr = self::getCompanyInfo($param['sKey'],$param['debug']);
            $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
            if($cidarr['rStatus']==101) return $cidarr;
            
            $cid 		= $cidarr['CompanyID'];
            $sdatabase  = $cidarr['Database'];
            //记录日志
            self::seaslog($cid, __METHOD__ . ' param', $param);
            
            $body = $param['body'] = json_decode(json_encode($param['body']),true);
            if(count($body)>1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '单次同步数据只能在1000条以内';
                return $rData;
            }
            if($param['count']!=count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
                return $rData;
            }

            include_once (SITE_ROOT_PATH."/class/letter.class.php");
            $letter = new letter();
            $editBody = array();
            $ctype = array(
            		'default'	=> 0,		//默认
            		'presell'	=> 1,		//预售
            		'special'	=> 2,		//特价
            		'contsell' 	=> 3,		//控销
            		'hot'		=> 4,		//热销
            		'gift'		=> 8,		//赠品
            		'shortage'	=> 9		//缺货
            );
            foreach($body as $key=>$val){
                
            	$val['name'] = trim($val['name']);
            	$val['coding'] = trim($val['coding']);
            	$val['guid'] = trim($val['guid']);
            	$val['model'] = trim($val['model']);
            	$val['brandName'] = trim($val['brandName']);
            	$val['units'] = trim($val['units']);
            	$val['appnumber'] = trim($val['appnumber']);
            	
                if(empty($val['guid'])){
                    $val['message'] = 'GUID不能空';
                    $eCoding[] = $val;
                    continue;
                }
                
                if(empty($val['coding'])){
                	$val['message'] = '商品编号不能空';
                	$eCoding[] = $val;
                	continue;
                }
                
                //↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
                $val['coding'] = mysql_real_escape_string($val['coding']);
                //验证外码是否存在;不存在则新增档案
                $ckSql = "SELECT COUNT(*) codeRow FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} AND Coding='{$val['coding']}'";
                $codeIsExist = $db->get_var($ckSql);
                if($codeIsExist){//存在则更新内码
                	$outSql = "UPDATE ".$sdatabase.DATATABLE."_order_content_index
			                	SET
			                		GUID='{$val['guid']}',ERP='T'
			                	WHERE
			                		CompanyID={$cid} AND Coding='{$val['coding']}'";
                	
                	$db->query($outSql);
                	$rData['debug_info'][$key]['upguid'] = $outSql;
                	continue;   //内码已存在，直接下一个
                }
                //↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑
                
                $val['price1']		= !isset($val['price1']) ? 0.00 : floatval($val['price1']);
                $val['price2']		= !isset($val['price2']) ? 0.00 : floatval($val['price2']);
                $val['package']		= $val['package'] ? $val['package'] : 1;
                $val['count']		= $val['count'] ? $val['count'] : 0;
                $val['orderID']		= $val['orderID'] ? $val['orderID'] : 500;
                $val['barcode']		= $val['barcode'] ? $val['barcode'] : '';
                $val['content']		= mysql_real_escape_string($val['content']);
                $val['commendID']	= $val['commendID'] ? $val['commendID'] : 'default';
                $val['commendID']	= $ctype[$val['commendID']];//----默认=default；预售=presell；特价=special；控销=contsell；热销=hot；赠品=gift；缺货=shortage
                $val['model']		= $val['model'] ? $val['model'] : '';
                $val['color']		= $val['color'] ? $val['color'] : '';
                $val['contentLink'] = $val['contentKeywords'] = '';
                $val['brandID']		= $val['brandName'] ? $this->_toBulidBrand(trim($val['brandName'])) : 0;
                $val['pinyi']		= $letter->C($val['name']);
                $val['units']		= empty($val['units']) ? '无' : $val['units'];
                $val['name']		= htmlspecialchars($val['name']);
                $val['nearvalid']	= trim($val['nearvalid']);	//近效期
                $val['farvalid']	= trim($val['farvalid']);	//远效期
                $val['appnumber']	= trim($val['appnumber']);	//批准文号
                $val['contentPoint'] = 0;	//积分
//                 $val['siteID'] 		= (int)$val['siteID'];

                $idx_sql = "INSERT INTO ".$sdatabase.DATATABLE."_order_content_index (CompanyID,BrandID,OrderID,CommendID,
                        Count,FlagID,Name,Pinyi,Coding,Barcode,Price1,Price2,Price3,Units,Casing,Picture,
                        Color,Specification,Model,LibraryDown,LibraryUp,GUID,ERP,Nearvalid,Farvalid,Appnumber,Conversion) VALUES(
                        {$cid},{$val['brandID']},{$val['orderID']},{$val['commendID']},0,0,'{$val['name']}','{$val['pinyi']}',
                        '{$val['coding']}','{$val['barcode']}','{$val['price1']}','{$val['price2']}','','{$val['units']}','{$val['casing']}','{$val['picture']}',
                        '{$val['color']}','{$val['specification']}','{$val['model']}',0,0,'{$val['guid']}','T','{$val['nearvalid']}','{$val['farvalid']}','{$val['appnumber']}','{$val['conversion']}'
                        )";

                $rData['debug_info'][$key]['idx'] = $idx_sql;
                if(false!==$db->query($idx_sql)){
                    $inid = $db->insert_id;
                    $now = time();
                    $userInfo = $db->get_row("SELECT UserName FROM ".DB_DATABASEU.DATATABLE."_order_user WHERE UserCompany=".$cid);
                    $val['contentCreateUser'] = $val['contentEditUser'] = $userInfo['UserName'];

                    $_1_sql = "INSERT INTO ".$sdatabase.DATATABLE."_order_content_1(ContentIndexID,CompanyID,ContentCreateDate,ContentEditDate,
                            ContentCreateUser,ContentEditUser,ContentLink,ContentKeywords,Content,ContentPoint,Package)VALUES(
                              {$inid},{$cid},{$now},{$now},'".$val['contentCreateUser']."','".$val['contentEditUser']."','','{$val['contentKeywords']}','{$val['content']}',{$val['contentPoint']},{$val['package']})";
                    
                    if(false === $db->query($_1_sql) ) {
                        //记录日志
                        self::seaslog($cid, __METHOD__. ' error', array('message' => "新增商品-content_1", 'sql' => $_1_sql));
                    }
                    $rData['debug_info'][$key]['content'] = $_1_sql;

                    //插入空主库存
                    $db->query("INSERT INTO ".$sdatabase.DATATABLE."_order_number (CompanyID,ContentID,OrderNumber,ContentNumber)VALUES({$cid},{$inid},0,0)");
                    $rData['debug_info'][$key]['lib']['man'] = $db->last_query;

                    //插入空子库存
                    if(!empty($val['color']) || !empty($val['specification'])){
                        //保存颜色
                        $this->specification(explode(',',$val['color']),'Color',$cid,$sdatabase);
                        //保存规格
                        $this->specification(explode(',',$val['specification']),'Specification',$cid,$sdatabase);
                        $son = array();
                        $color = $val['color'] ? explode(',',$val['color']) : array('统一');
                        $spec = $val['specification'] ? explode(',',$val['specification']) : array('统一');
                        $color = array_map('CSEncode',$color);
                        $spec = array_map('CSEncode',$spec);
                        $sql_header = "INSERT INTO ".$sdatabase.DATATABLE."_order_inventory_number (CompanyID,ContentID,ContentColor,ContentSpec,OrderNumber,ContentNumber) VALUES ";
                        if(!empty($color) && !empty($spec)){
                            foreach($color as $v){
                                foreach($spec as $sv){
                                    $son[] = "({$cid},{$inid},'{$v}','{$sv}',0,0)";
                                }
                            }
                        }
                        $rData['debug_info'][$key]['lib']['son'] = $son;
                        if($son){
                            $db->query($sql_header.implode(',',$son));
                        }
                    }

                }else{
                    $eCoding[] = $val;
                    //记录日志
                    self::seaslog($cid, __METHOD__. ' error', array('message' => "新增商品失败-index", 'sql' => $idx_sql));
                }
            }
            
        }
        
       if($eCoding){
           $rData['rStatus'] = 101;
           $rData['message'] = '部分商品添加未成功!';
           $rData['rData']	 = $eCoding;
       }

        //记录日志
        self::seaslog($cid, __METHOD__ . ' return', $rData);
        //if(!$param['debug']) unset($rData['debug']);

        return $rData;
    }

    /**
     * @desc 批量修改商品
     * @param array $param (sKey,count,body)
     * body (siteID,brandID,name,coding,units,price1,price2,barcode,model,color,specification)
     * @return array $rData
     * //删除颜色规格时不验证库存(ERP中有库存的颜色规格不允许删除)
     * 2、根据内码进行检查，存在则更新，不存在则跳过当前  by wanjun @20151215
     * 3、更新商品档案时：外码、名称、规格型号、条码、状态发生更新 by wanjun @20151215
     */
    public function updateProduct($param){
        global $db;
        $rData = array('rStatus'=>100, 'message' => '维护商品档案执行完毕');
        $eCoding = array();
        $param = is_object($param) ? (array)$param : $param;
        
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须';
            
            return $rData;
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
            if($cidarr['rStatus']==101) return $cidarr;
            
            $cid		= $cidarr['CompanyID'];
            $sdatabase  = $cidarr['Database'];
            
            //记录日志
            self::seaslog($cid, __METHOD__ . ' param', $param);
            
            $body = $param['body'] = json_decode(json_encode($param['body']),true);
            if(count($body)>1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '单次同步数据只能在1000条以内';
                return $rData;
            }
            if($param['count']!=count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
                return $rData;
            }
            
            //读取ERP价格的独立配置
          	$sql = "select isPrice from  etong_db_live_user.".DATATABLE."_api_serial where CompanyID=".$cidarr['CompanyID'];
          	$isPriceStatus = $db->get_var($sql);
            
            include_once (SITE_ROOT_PATH."/class/letter.class.php");
            $letter = new letter();
            //del_product
            $del_product	= array();	//待删除的商品GUID数组
            $not_Exi		= array();	//GUID不存在的数据
            foreach($body as $key=>$val){
            	
            	$val['barcode'] = trim($val['barcode']);
            	$val['name'] = trim($val['name']);
            	$val['units'] = trim($val['units']);
            	$val['model'] = trim($val['model']);
            	$val['coding'] = trim($val['coding']);
            	
            	$val['status'] = strtoupper($val['status']);
            	$val['status'] = $val['status'] == 'D' ? 'D' : '';
                if($val['status'] == 'D') {
                    $del_product[] = $val['guid'];
                    continue;
                }
                
                $cSql      = "SELECT ID FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} AND GUID='{$val['guid']}'";
                $contentID = $db->get_var($cSql);
                $rData['debug_info'][$key]['cGuid'] = $cSql;
                if(!$contentID) {
                	$val['message'] = 'GUID不存在，请核查已同步的商品';
                	$not_Exi[] = $val;
                	continue;
                }
                
                $setInfo = array();
                $color   = array();
                $spec    = array();

                if(isset($val['name'])){
                    $setInfo[]	 = "Pinyi='".$letter->C($val['name'])."'";
                    $val['name'] = htmlspecialchars($val['name']);
                    $setInfo[]	 = "Name='".$val['name']."'";
                }
                //包含计量单位 by wanjun @20160524 李宗建
               if(isset($val['units'])){
                   $setInfo[] = "Units='".$val['units']."'";
               }
               if(isset($val['casing'])){
               		$setInfo[] = "Casing='".$val['casing']."'";
               }
               if(isset($val['conversion'])){
               	$setInfo[] = "Conversion='".$val['conversion']."'";
               }
               
               //现含价格,存在字段则更新 by wanjun @20160308
               
               
               //如果指定了不能更新价格，那么就跳过
               if($isPriceStatus != 'F'){
	               if(isset($val['price1'])){
	                   $setInfo[] = "Price1=".floatval($val['price1']);
	               }
	               if(isset($val['price2'])){
	                   $setInfo[] = "Price2=".floatval($val['price2']);
	               }
               }
               
               if(isset($val['barcode'])){
                    $setInfo[] = "Barcode='".$val['barcode']."'";
                }
               if(isset($val['model'])){
                   $setInfo[] = "Model='".$val['model']."'";
               }
               if(isset($val['color']) && !empty($val['color'])){
                    $setInfo[] = "Color='".$val['color']."'";
                    $color = array_map('CSEncode',explode(',',$val['color']));
                    
                    //将颜色添加到规格颜色库
                    $this->specification(explode(',',$val['color']),'Color',$cid,$sdatabase);
                }
                if(isset($val['specification']) && !empty($val['specification'])){
                    $setInfo[] = "Specification='".$val['specification']."'";
                    $spec = array_map('CSEncode',explode(',',$val['specification']));
                    
                    //将规格添加到规格颜色库
                    $this->specification(explode(',',$val['specification']),'Specification',$cid,$sdatabase);
                }
                if(isset($val['coding'])){
                    $setInfo[] = "Coding='".$val['coding']."'";
                }
                if(isset($val['nearvalid'])){//近效期
                	$setInfo[] = "Nearvalid='".trim($val['nearvalid'])."'";
                }
                if(isset($val['farvalid'])){//远效期
                	$setInfo[] = "Farvalid='".trim($val['farvalid'])."'";
                }
                if(isset($val['appnumber'])){//批准文号
                	$setInfo[] = "Appnumber='".trim($val['appnumber'])."'";
                }
                
                //现在根据外码更新了内码
                $setInfo[] = "ERP='T'";

                $u_idx_sql = "UPDATE ".$sdatabase.DATATABLE."_order_content_index SET ".implode(",",$setInfo)." WHERE CompanyID={$cid} AND GUID='{$val['guid']}' limit 1";
                $rData['debug_info'][$key]['index'] = $u_idx_sql;

                $rst = $db->query($u_idx_sql);
                if($rst===false){
                    $eCoding[] = $val;
                    //记录日志
                    self::seaslog($cid, __METHOD__. ' error', array('message' => "更新商品失败-index", 'sql' => $u_idx_sql));
                }else{
                	
                    $numarr = $db->get_results("SELECT ContentColor,ContentSpec FROM ".$sdatabase.DATATABLE."_order_inventory_number where CompanyID=".$cid." and ContentID=".$contentID." ");
                    $colory = array();
                    $specy = array();
                    if(!empty($numarr)){
                        foreach($numarr as $nv){
                            $colory[] = $nv['ContentColor'];
                            $specy[] = $nv['ContentSpec'];
                            if(!in_array($nv['ContentColor'],$color)){
                                $db->query("DELETE FROM ".$sdatabase.DATATABLE."_order_inventory_number WHERE ContentColor='{$nv['ContentColor']}' AND ContentID={$contentID} AND CompanyID={$cid}");
                            }

                            if(!in_array($nv['ContentSpec'],$spec)){
                                $db->query("DELETE FROM ".$sdatabase.DATATABLE."_order_inventory_number WHERE ContentSpec='{$nv['ContentSpec']}' AND ContentID={$contentID} AND CompanyID={$cid}");
                            }
                        }
                    }


                    $addEd = array();
                    foreach($color as $cv){
                        if(in_array($cv,$colory)){
                            continue;
                        }
                        $specAll = $spec ? $spec : array_map('CSEncode', array('统一'));
                        foreach($specAll as $sv){
                            $db->query("INSERT INTO ".$sdatabase.DATATABLE."_order_inventory_number (CompanyID,ContentID,ContentColor,ContentSpec,OrderNumber,ContentNumber) VALUES ({$cid},{$contentID},'{$sv}','{$cv}',0,0)");
                            $addEd[] = $contentID.'-'.$cv.'-'.$sv;
                        }
                    }

                    foreach($spec as $sv){
                        if(in_array($sv,$specy)){
                            continue;
                        }
                        $colorAll = $color ? $color : array_map('CSEncode',array('统一'));
                        foreach($colorAll as $cv){
                            if(in_array($contentID.'-'.$cv.'-'.$sv,$addEd)){
                                continue;
                            }
                            $db->query("INSERT INTO ".$sdatabase.DATATABLE."_order_inventory_number (CompanyID,ContentID,ContentColor,ContentSpec,OrderNumber,ContentNumber) VALUES ({$cid},{$contentID},'{$sv}','{$cv}',0,0)");
                        }
                    }
                    unset($addEd);

                }
            }
            //执行删除商品操作
            if(!empty($del_product)) {
                $del_product = array_filter(array_unique($del_product));
                foreach(array_chunk($del_product,200) as $guids) {
                    $guid_str = "'" . implode("','",$guids) . "'";
                    $id_arr = $db->get_col("SELECT ID FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} AND GUID IN(".$guid_str.")");
                    $id_arr = $id_arr ? $id_arr : array();
                    $id_arr[] = 0;
//                     $del_sql = "DELETE FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} AND GUID IN(".$guid_str.")";
//                     $del_sql_1 = "DELETE FROM ".$sdatabase.DATATABLE."_order_content_1 WHERE CompanyID={$cid} AND ContentID IN(".implode(",",$id_arr).")";
                    $up_sql = "update ".$sdatabase.DATATABLE."_order_content_index set FlagID=1 WHERE CompanyID={$cid} AND GUID IN(".$guid_str.")";
                    $rData['debug_info'][$key]['del'] = $up_sql;
                    
                      if(false === $db->query($up_sql)) {
                        //记录日志
                        self::seaslog($cid, __METHOD__. ' error', array('message' => "物理删除商品数据失败 - content_1", 'sql' => $up_sql));
                      }
                }
            }
        }
        
        if($eCoding || $not_Exi){
           $rData['rStatus'] = 101;
           $rData['message'] = '部分商品未修改成功!';
           $rData['rData']	 = array_filter(array_merge(array('eCoding' => $eCoding), array('emptyguid' => $not_Exi)));
        }

        //记录日志
        self::seaslog($cid, __METHOD__ . ' return', $rData);
        //if(!isset($param['debug']))  unset($rData['debug']);
        
        return $rData;
    }

    /**
     * @desc 检查发货单数据是否有异常
     * @param $param (sKey,clientNO,consignmentOrder,consignmentNO,...
     * array body (coding,num,color,spec,conType)
     * @return array $rData
     */
    private function checkConsignment($param) {
        global $db;
        $rData = array(
            'rStatus' => 100,
        );
        $param = json_decode(json_encode($param), true);
        $body = $param['body'];
        //验证基本信息
        if(empty($param['sKey']) || empty($param['consignmentMan']) ||  empty($param['body'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须';
            if(empty($param['consignmentMan'])){
                $rData['message'] = '发货经办人不能为空!';
            }elseif(empty($param['consignmentOrder'])){
                $rData['message'] = '未指定发货订单!';
            }elseif(empty($param['body']) || count($param['body']) == 0){
                $rData['message'] = '未指定发货商品信息!';
            }
            return $rData;
        }

        //验证订单是否存在
        $cidarr = self::getCompanyInfo($param['sKey']);
        $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
        if($cidarr['rStatus']==101){
            return $cidarr;
        }
        $cid = $cidarr['CompanyID'];
        $sdatabase = $cidarr['Database'];
        
        //对丰达凯莱做特殊处理，如果是丰达的并且特别授权码是:inca20170424inj
        //出现该授权码时，说明是线下单据，直接通过同步库存接口更新库存，更新完毕后，结速程序运行
        if($param['consignmentOrder'] == 'inca20170424inj'){//线下订单，只更新库存，不做其他修改
        	
        	$toStock['sKey']  = $param['sKey'];
        	$toStock['count'] = count($param['body']);
        	$toStock['body']  = $param['body'];
        	
        	self::stock_inca($toStock);
        	return array(
        			'rStatus' => 101,
        			'message' => '线下商品库存同步完毕'
        	);
        	exit;
        }
        
        //查询订单信息获取订单ID,订单用户,收货人信息
        $param['consignmentOrder'] = str_replace('ET','',$param['consignmentOrder']);
        $order_sql = "SELECT OrderID,OrderUserID,OrderReceiveCompany,OrderReceiveName,OrderReceivePhone,OrderReceiveAdd FROM ".$sdatabase.DATATABLE."_order_orderinfo WHERE OrderSN='".$param['consignmentOrder']."' AND OrderCompany={$cid} Limit 0,1";
        $orderInfo = $db->get_row($order_sql);
        if(empty($orderInfo)){
            $rData['message'] = 'ET'.$param['consignmentOrder'].'订单不存在!';
            $rData['rStatus'] = 101;
            return $rData;
        }

        $orderID = $orderInfo['OrderID'];
        //验证发货单中包含的发货商品是否存在于平台中
        $eGoods = $nGoods = array();
        foreach($body as $i=>$val) {
            $cInfo = $db->get_row("SELECT ID,Coding,GUID,Name FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} AND GUID='{$val['guid']}' limit 0,1" );
            if(empty($cInfo)) {
                //记录日志
                self::seaslog($cid, __METHOD__. ' error', array('message' => "发货单中包含不存在于医统中的商品", 'sql' => $db->last_query));
                $nGoods[] = $val['guid'];
                continue;
            }
            $cartInfo = array();
            $val['conType'] = empty($val['conType']) ? 'c' : strtolower($val['conType']);
            $val['color']   = htmlentities($val['color'],ENT_COMPAT,"UTF-8");
            $val['spec']    = htmlentities($val['spec'], ENT_COMPAT, "UTF-8");
            if($val['conType']=='c'){
                $cartInfo = $db->get_var("SELECT COUNT(*) AS Total FROM ".$sdatabase.DATATABLE."_order_cart WHERE CompanyID={$cid} AND OrderID={$orderID} AND ContentID={$cInfo['ID']} AND ContentColor='{$val['color']}' AND ContentSpecification='{$val['spec']}' LIMIT 0,1");
                $rData['debug_info']['c'][$i] = $db->last_query;
            } else if($val['conType']=='g'){
                $cartInfo = $db->get_var("SELECT COUNT(*) AS Total FROM ".$sdatabase.DATATABLE."_order_cart_gifts WHERE CompanyID = {$cid} AND OrderID={$orderID} AND ContentID={$cInfo['ID']} AND ContentColor='{$val['color']}' AND ContentSpecification='{$val['spec']}' LIMIT 0,1");
                $rData['debug_info']['g'][$i] = $db->last_query;
            }
            $rData['debug_info']['cart'][$i] = $cartInfo;
            
            if(empty($cartInfo)) {
                //记录日志
                self::seaslog($cid, __METHOD__. ' error', array('message' => "发货订单中不包含商品", 'sql' => $db->last_query));
                $eGoods[] = trim($cInfo['Name']).'(内码:'.$val['guid'].')';
            }
        }

        //if(empty($param['debug'])) unset($rData['debug']);
        
        if(count($eGoods)>0 || count($nGoods) > 0) {
            $eStr = "";
            $n = 1;
            if(count($eGoods) > 0) {
                $eStr .= "{$n}.订单中不包含以下商品:" . implode(',',$eGoods);
                $n++;
            }
            if(count($nGoods)) {
                $eStr .= "  {$n}.系统中不包含以下商品(GUID):" . implode(',',array_unique($nGoods));
            }
            $rData['rStatus'] = 101;
            $rData['message'] = $eStr;
            return $rData;
        }
        
        return $rData;
    }

    /**
     * @desc 添加发货单
     * @param array $param(sKey,clientNO,consignmentOrder,consignmentNO,consignmentMan,consignmentRemark,body,consignmentDate)
     * array body (coding,num,color,spec,conType),..
     * @return array $rData
     */
    public function addConsignment($param){
        global $db;
        $rData = array(
            'rStatus'=>100,
        	'message'=> '发货单同步完成'
        );
        $param = is_object($param) ? (array)$param : $param;
        $param['conType'] = @strtolower($param['conType']);
        
        $check_result = $this->checkConsignment($param);//验证发货数据

        if($check_result['rStatus'] == 101) {
            //验证发货数据未通过
            return $check_result;
        }
        if(true){
            $cidarr = self::getCompanyInfo($param['sKey']);
            $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
            if($cidarr['rStatus']==101)  return $cidarr;
            
            $cid		= $cidarr['CompanyID'];
            $sdatabase	= $cidarr['Database'];
            
            //记录日志
            self::seaslog($cid, __METHOD__ . ' param', $param);
            
            //查询订单信息获取订单ID,订单用户,收货人信息
            $param['consignmentOrder'] = str_replace('ET', '', $param['consignmentOrder']);
            
            //临时处理523 ID的出库信息，需要去除
            if(strpos($param['consignmentOrder'], '-') === false && $cid == 523){
            	$param['consignmentOrder'] = substr($param['consignmentOrder'], 0, 8).'-'.intval(substr($param['consignmentOrder'],strpos($param['consignmentOrder'], '-')+1));
            }
            
            $order_sql = "SELECT OrderID,OrderUserID,OrderReceiveCompany,OrderReceiveName,OrderReceivePhone,OrderReceiveAdd,OrderSN,OrderSendStatus FROM ".$sdatabase.DATATABLE."_order_orderinfo WHERE OrderSN='".$param['consignmentOrder']."' AND OrderCompany={$cid} Limit 0,1";
            $orderInfo = $db->get_row($order_sql);
            
            //当前是一个订单发一次货
            if(!(in_array($orderInfo['OrderSendStatus'], array(0,1)))){
            	
            	$rData['rStatus'] = 101;
            	$rData['message'] = '该采购单已发货，本次配送单平台未接收';
            	
            	self::seaslog($cid, __METHOD__ . ' error', array('param' => $param, 'return' => $rData));
            	return $rData;
            	exit;
            }
            
            $orderID = $orderInfo['OrderID'];
            $param['orderID'] = $orderID;
            $param['consignmentLogistics'] = 0;//物流
            $param['inceptMan'] = $orderInfo['OrderReceiveName'];
            $param['inceptArea'] = '';
            $param['consignmentNO'] = $param['consignmentNO'] ? $param['consignmentNO'] : '-';
            $param['inceptAddress'] = $orderInfo['OrderReceiveAdd'];
            $param['inceptCompany'] = $orderInfo['OrderReceiveCompany'];
            $param['inceptPhone'] = $orderInfo['OrderReceivePhone'];
            $param['consignmentClient'] = $orderInfo['OrderUserID'];//根据ClientNO获取当前是获取订单中的药店ID

            $param['consignmentUser'] = '';
            $param['consignmentFlag'] = 0;
            $param['consignmentDate'] = $param['consignmentDate'] ? $param['consignmentDate'] : date('Y-m-d H:i');//未传发货时间默认当前时间
            $param['consignmentMoneyType'] = '1'; //默认已付
            $param['consignmentMoney'] = 0;//运费

            //发货单SQL
            $consignment_sql = "INSERT INTO ".$sdatabase.DATATABLE."_order_consignment(
                        ConsignmentCompany,ConsignmentClient,ConsignmentOrder,
                        ConsignmentLogistics,ConsignmentNO,ConsignmentMan,
                        ConsignmentDate,ConsignmentRemark,ConsignmentMoneyType,
                        ConsignmentMoney,InceptMan,InceptArea,
                        InceptAddress,InceptCompany,InceptPhone,
                        InputDate,ConsignmentUser,ConsignmentFlag
                        )VALUES(
                        {$cid},{$param['consignmentClient']},'{$param['consignmentOrder']}',
                        {$param['consignmentLogistics']},'{$param['consignmentNO']}','{$param['consignmentMan']}',
                        '{$param['consignmentDate']}','{$param['consignmentRemark']}','{$param['consignmentMoneyType']}',
                        {$param['consignmentMoney']},'{$param['inceptMan']}','{$param['inceptArea']}',
                        '{$param['inceptAddress']}','{$param['inceptCompany']}','{$param['inceptPhone']}',
                        ".time().",'{$param['consignmentUser']}',{$param['consignmentFlag']}
                        )";

                        
            $moreSend = array();    //存储超发的数据
            if(false!==$db->query($consignment_sql)){
                $consignmentID = $db->insert_id;
                $body = $param['body'] = json_decode(json_encode($param['body']),true);

                $less_money = 0;	//发货的金额
                foreach($body as $i=>$val){
                    $val['num'] = (int)$val['num'];
                    //$cInfo = $db->get_row("SELECT ID,Coding FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} AND Coding='{$val['guid']}' limit 0,1" );
                    $cInfo = $db->get_row("SELECT ID,Coding FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} AND GUID='{$val['guid']}' limit 0,1" );
                    $rData['debug_info']['content'][] = $db->last_query;
                    $cartInfo = array();
                    $val['conType'] = empty($val['conType']) ? 'c' : strtolower($val['conType']);
                    $val['color']   = htmlentities($val['color'],ENT_COMPAT,"UTF-8");
                    $val['spec']    = htmlentities($val['spec'], ENT_COMPAT, "UTF-8");
                    if($val['conType']=='c'){
                        $cartInfo = $db->get_row("SELECT ID,ContentID,ContentNumber,ContentSend,ContentColor,ContentSpecification,ContentPrice,ContentPercent FROM ".$sdatabase.DATATABLE."_order_cart WHERE CompanyID={$cid} AND OrderID={$orderID} AND ContentID={$cInfo['ID']} AND ContentColor='{$val['color']}' AND ContentSpecification='{$val['spec']}' LIMIT 0,1");
                    }else if($val['conType']=='g'){
                        $cartInfo = $db->get_row("SELECT ID,ContentID,ContentNumber,ContentSend,ContentColor,ContentSpecification FROM ".$sdatabase.DATATABLE."_order_cart_gifts WHERE CompanyID = {$cid} AND OrderID={$orderID} AND ContentID={$cInfo['ID']} AND ContentColor='{$val['color']}' AND ContentSpecification='{$val['spec']}' LIMIT 0,1");
                    }
                    
                    //校验发货数量，不能大于ET中的订购数
                    if(($cartInfo['ContentSend']+$val['num']) > $cartInfo['ContentNumber']){//已超过订购数量
                        $needNum = $cartInfo['ContentNumber'] - $cartInfo['ContentSend'];
                        $more = '订购数量：'.$cartInfo['ContentNumber'];
                        $more .= '，已发数量：'.$cartInfo['ContentSend'];
                        $more .= '，本次同步数量：'.$val['num'];
                        $more .= '， 超发数量：'.($val['num']-$needNum);
                        $val['more'] = $more;
                    
                        $moreSend[]  = $val;
                        $val['num']  = $needNum;
                    }elseif($val['num'] < $cartInfo['ContentNumber'] && $val['conType']=='c'){//只有常规商品才退款
                    	$less_money   = $less_money + ($cartInfo['ContentNumber'] - intval($val['num'])) * $cartInfo['ContentPrice'] * $cartInfo['ContentPercent'] / 10;
                    }
                    
                    $cartID = $cartInfo['ID'];
                    $contentID = $cInfo['ID'];
                    $outSql ="INSERT INTO ".$sdatabase.DATATABLE."_order_out_library (CompanyID,ConsignmentID,OrderID,CartID,ContentID,ContentNumber,ConType) VALUES(
                        {$cid},{$consignmentID},{$orderID},{$cartID},{$contentID},{$val['num']},'{$val['conType']}'
                    )";
                    $rData['debug_info']['out_sql'][] = $outSql;
                    
                    if(false!==$db->query($outSql)){
                        //更新商品发货数量
                        if($val['conType']=='c'){
                            $cartUSql = "UPDATE ".$sdatabase.DATATABLE."_order_cart SET ContentSend = ContentSend + {$val['num']} WHERE CompanyID={$cid} AND OrderID={$orderID} AND ContentID={$contentID} AND ContentColor='{$val['color']}' AND ContentSpecification='{$val['spec']}'";
                        }else if($val['conType']=='g'){
                            $cartUSql = "UPDATE ".$sdatabase.DATATABLE."_order_cart_gifts SET ContentSend = ContentSend + {$val['num']} WHERE CompanyID={$cid} AND OrderID={$orderID} AND ContentID={$contentID} AND ContentColor='{$val['color']}' AND ContentSpecification='{$val['spec']}'";
                        }

                        if(false!==$db->query($cartUSql)){
                               //更新库存代码[扣减可用库存，实际库存由数据同步扣减]
//                         	$db->query("update ".$sdatabase.DATATABLE."_order_number set OrderNumber=(CASE WHEN (OrderNumber-".$val['num'].") < 0 THEN 0 ELSE (OrderNumber-".$val['num'].") END) where CompanyID=".$cid." and ContentID=".$contentID." limit 1");
                        }else{
                            $rData['rStatus'] = 101;
                            $rData['message'] = '更新发货数不成功!';
                            //记录日志
                            self::seaslog($cid, __METHOD__ . ' return', $rData);
                            //if(!isset($param['debug'])) unset($rData['debug']);
                            
                            return $rData;
                        }
                    }else{
                        $out_cnt = $db->get_var("SELECT COUNT(*) as Total FROM ".$sdatabase.DATATABLE."_order_out_library WHERE CompanyID={$cid} AND ConsignmentID={$consignmentID} LIMIT 1");
                        if((int)$out_cnt == 0){
                            $db->query("DELETE FROM ".$sdatabase.DATATABLE."_order_consignment WHERE ConsignmentID=".$consignmentID);
                        }
                        $rData['rStatus'] = 101;
                        $rData['message'] = '保存不成功!';
                        //记录日志
                        self::seaslog($cid, __METHOD__ . ' return', $rData);
                        //if(!isset($param['debug'])) unset($rData['debug']);
                        
                        return $rData;
                    }
                }

                //处理订单状态&发货状态
                $sendline = $db->get_var("select count(*) as allrow from ".$sdatabase.DATATABLE."_order_cart where ContentSend < ContentNumber and CompanyID = ".$cid." and OrderID=".$orderID."");
                $sendlineg = $db->get_var("select count(*) as allrow from ".$sdatabase.DATATABLE."_order_cart_gifts where ContentSend < ContentNumber and CompanyID = ".$cid." and OrderID=".$orderID."");
                $orderSendStatus = ( $sendline + $sendlineg ) > 0 ? 3 : 2; //3=>未发完,2=>已发完
                $db->query("UPDATE ".$sdatabase.DATATABLE."_order_orderinfo set OrderSendStatus={$orderSendStatus} WHERE OrderID={$orderID} AND OrderCompany={$cid}");
                $rData['debug_info']['orderSendStatus'] = $db->last_query;
                //记录日志
                self::seaslog($cid, __METHOD__ . ' orderStatus', array($db->last_query));
                
                //将当前订单由备货中改为已发货状态
                $db->query("UPDATE ".$sdatabase.DATATABLE."_order_orderinfo SET OrderStatus=2 WHERE OrderID={$orderID} AND OrderCompany={$cid} AND OrderStatus=1");
                $rData['debug_info']['orderStatus'] = $db->last_query;
                //记录日志
                self::seaslog($cid, __METHOD__ . ' orderStatus', array($db->last_query));
                
                $db->query("INSERT INTO ".$sdatabase.DATATABLE."_order_ordersubmit(CompanyID,OrderID,AdminUser,Name,Date,Status,Content) VALUES ({$cid},{$orderID},'接口','接口',".time().",'已发货','已添加发货单') ");
				//处理退款
				if($less_money){
					//作为其他款项的增加项
					//处理其他款项(退款处理)
					$bill_id = $this->_bulidBill($cid);
					
					$inSql= "insert into rsung_order_expense(CompanyID,ClientID,BillID,ExpenseTotal,ExpenseDate,ExpenseRemark,ExpenseTime,ExpenseUser,FlagID)  values(".$cid.",".$orderInfo['OrderUserID'].",".$bill_id.",".$less_money.",'".date('Y-m-d')."', '订单 ".$orderInfo['OrderSN']." 少发货退款￥ ".$less_money."', ".time().", 'ERP接口', 2)";			
					$db->query($inSql);
					//写入订单跟踪
	                $db->query("INSERT INTO ".$sdatabase.DATATABLE."_order_ordersubmit(CompanyID,OrderID,AdminUser,Name,Date,Status,Content) VALUES ({$cid},{$orderID},'接口','接口',".time().",'少发退款','已退款 ￥".$less_money." 到余额') ");
				}
            }else{
                $rData['error'] = '同步发货数据失败!';
                $rData['rStatus'] = 101;
                //记录日志
                self::seaslog($cid, __METHOD__. ' error', array('message' => "发货单保存失败", 'sql' => $consignment_sql));
            }

            $rData['debug_info']['order_sql']	= $order_sql;
            $rData['debug_info']['consignment']	= $consignment_sql;
        }
        
        if($moreSend) $rData['rData']['moreSend'] = $moreSend;
        
        //记录日志
        self::seaslog($cid, __METHOD__ . ' return', $rData);
        //if(!isset($param['debug']))	unset($rData['debug']);
        
        return $rData;
    }

    /**
     * 处理其他款项(退款处理)
     * @author wanjun
     * @param $cid int 商业ID
     */
    private function _bulidBill($cid = 0){
    	global $db;
    	
    	if(!$cid) return false;
    	
    	$select = "select * from ".$sdatabase.DATATABLE."_order_expense_bill where CompanyID=".$cid." and BillNO='otherbill'";
    	$info = $db->get_row($select);
    	if(empty($info)){
	    	$insert = "INSERT INTO ".$sdatabase.DATATABLE."_order_expense_bill SET BillNO='otherbill', BillName='少发退款', CompanyID=".$cid;
	    	$db->query($insert);
	    	
	    	return $db->insert_id;
    	}
    	
//     	$insert = "REPLACE INTO ".$sdatabase.DATATABLE."_order_expense_bill SET BillNO='otherbill', BillName='少发退款', CompanyID=".$cid;
    	return $info['BillID'];
    }
    

    /**
     * @desc 库存同步
     * @param array $param(sKey,body)
     * body (coding,num,spec,color)
     * @return mixed
     */
    public function stock($param){
        global $db;
        $rData = array(
            'rStatus' =>100,
        	'message' => '库存同步执行完毕'
        );
        $eProduct	 = array();
		$nullProduct = 0; // 记录一下传递过来的空值数量 by 小牛New 2015-11-17
		$emptyGuid	 = array();
        $param		 = is_object($param) ? (array)$param : $param;
        
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须';
            
            return $rData;
        }else{
            $fp = array('+','/','=','_');
            $rp = array('-','|','DHB',' ');
            $cidarr = self::getCompanyInfo($param['sKey']);
            $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
            $cid 		= $cidarr['CompanyID'];
            $sdatabase  = $cidarr['Database'];
            if($cidarr['rStatus']==101)  return $cidarr;
            //记录日志
            self::seaslog($cid, __METHOD__ . ' param', $param);
            
            $body = $param['body'] = json_decode(json_encode($param['body']), true);
            if(count($body)>1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据只能在1000条以内';
                return $rData;
            }
            foreach($body as $k=>$val){
            	$val['guid'] = trim($val['guid']);
                if(empty($val['guid'])){
					$nullProduct++;
					$val['message'] = 'GUID为空';
					$emptyGuid[] = $val;
					continue;
				}
				
				//instr 存在BUG
// 				$cInfo = $db->get_row("SELECT ID,Coding FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} AND FIND_IN_SET('{$val['guid']}',CONCAT(',',GUID,','))>0");
				
				$cInfo = $db->get_row("SELECT ID,Coding FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} AND GUID='".$val['guid']."'");
				$rData['debug_info']['ckproduct'][$val['guid']] = $db->last_query;
				
                if(empty($cInfo)){
                    $eProduct[] = array(
                        'guid'=>$val['guid'],
                        'message'=>'商品不存在或还未同步',
                    );
                    continue;
                }
                $val['spec'] = $val['spec'] ? $val['spec'] : '';
                $val['color'] = $val['color'] ? $val['color'] : '';

                //有颜色|规格
                if(!empty($val['spec']) || !empty($val['color'])){
                    $color = empty($val['color']) ? '' : str_replace($fp,$rp,base64_encode($val['color']));
                    $spec = empty($val['spec']) ? '' : str_replace($fp,$rp,base64_encode($val['spec']));
                    //当前商品 待发货/待审核/未发完的库存
                    $freeze_sql = "SELECT SUM(ContentNumber - ContentSend) AS freeze FROM ".$sdatabase.DATATABLE."_order_cart AS c
                                    LEFT JOIN ".$sdatabase.DATATABLE."_order_orderinfo AS o ON o.OrderID = c.OrderID
                                    WHERE c.CompanyID = {$cid} AND o.OrderCompany = {$cid} AND c.ContentID = {$cInfo['ID']} AND o.OrderSendStatus IN(0,1,3) AND c.ContentColor = '{$val['color']}' AND c.ContentSpecification='{$val['spec']}'";

                    //买品未发数量
                    $freeze = $db->get_var($freeze_sql);
                    $rData['debug_info']['freeze_cart'][$val['guid']] = $freeze_sql;

                    $freeze_gift_sql = "SELECT SUM(ContentNumber - ContentSend) AS freeze FROM ".$sdatabase.DATATABLE."_order_cart_gifts AS c
                                    LEFT JOIN ".$sdatabase.DATATABLE."_order_orderinfo AS o ON o.OrderID = c.OrderID
                                    WHERE c.CompanyID = {$cid} AND o.OrderCompany = {$cid} AND c.ContentID = {$cInfo['ID']} AND o.OrderSendStatus IN(0,1,3) AND c.ContentColor = '{$val['color']}' AND c.ContentSpecification='{$val['spec']}'";
                    //赠品未发数
                    $freeze_gift = $db->get_var($freeze_gift_sql);
                    $rData['debug_info']['freeze_gift'][$val['guid']] = $freeze_gift_sql;
                    
                    //当前商品可用库存
                    $allow = max(0,$val['num']-$freeze-$freeze_gift);
                    $lsql = "UPDATE ".$sdatabase.DATATABLE."_order_inventory_number SET OrderNumber={$allow},ContentNumber={$val['num']} WHERE CompanyID={$cid} AND ContentID={$cInfo['ID']} AND ContentColor='{$color}' AND ContentSpec='{$spec}'";
                    $db->query($lsql);
                    $rData['debug_info']['update_inventory'][$val['guid']] = $lsql;
                    //操作主库存
                    $lib = $db->get_row("SELECT SUM(OrderNumber) as OrderNumber,SUM(ContentNumber) as ContentNumber FROM ".$sdatabase.DATATABLE."_order_inventory_number WHERE ContentID={$cInfo['ID']} AND CompanyID={$cid}");
                    
                    $db->query("UPDATE ".$sdatabase.DATATABLE."_order_number SET OrderNumber={$lib['OrderNumber']},ContentNumber={$lib['ContentNumber']} WHERE CompanyID={$cid} AND ContentID={$cInfo['ID']}");
                    
                    $rData['debug_info']['update_stock'][$val['guid']] = $db->last_query;
                }else{
                    //操作主库存
                	//买品未发数量
                    $freeze_sql = "SELECT SUM(ContentNumber - ContentSend) AS freeze FROM ".$sdatabase.DATATABLE."_order_cart AS c
                                    LEFT JOIN ".$sdatabase.DATATABLE."_order_orderinfo AS o ON o.OrderID = c.OrderID
                                    WHERE c.CompanyID = {$cid} AND o.OrderCompany = {$cid} AND c.ContentID = {$cInfo['ID']} AND o.OrderSendStatus IN(0,1,3)";
                    
                    $freeze = $db->get_var($freeze_sql);
//                     $rData['debug_info']['freeze_cart'][$val['guid']] = $freeze_sql;
                    
                    //赠品未发数量
                    $freeze_gift_sql = "SELECT SUM(ContentNumber - ContentSend) AS freeze FROM ".$sdatabase.DATATABLE."_order_cart_gifts AS c
                                    LEFT JOIN ".$sdatabase.DATATABLE."_order_orderinfo AS o ON o.OrderID = c.OrderID
                                    WHERE c.CompanyID = {$cid} AND o.OrderCompany = {$cid} AND c.ContentID = {$cInfo['ID']} AND o.OrderSendStatus IN(0,1,3)";
                    
                    $freeze_gift = $db->get_var($freeze_gift_sql);
//                     $rData['debug_info']['freeze_gift'][$val['guid']] = $freeze_gift_sql;
                    //可用库存
                    $allow = max(0,$val['num']-$freeze-$freeze_gift); //实际库存减去商品未发数再减去赠品未发数
                    $sql = "UPDATE ".$sdatabase.DATATABLE."_order_number SET OrderNumber={$allow},ContentNumber={$val['num']} WHERE ContentID={$cInfo['ID']} AND CompanyID={$cid}";
                    $rData['debug_info']['update_stock'][$val['guid']] = $sql;
                    $db->query($sql);
                }

            }
        }

        if(count($eProduct)>0 || $nullProduct){
            $rData['rStatus'] = 101;
            $rData['message'] = '部分商品库存同步失败!';
            $rData['rData']   = $eProduct;
            $rData['rData']   = array_filter(array_merge(array('eCoding' => $eProduct), array('emptyguid' => $emptyGuid)));
        }

        //记录日志
        self::seaslog($cid, __METHOD__ . ' return', $rData);
        //if(!isset($param['debug'])) unset($rData['debug']);
        
        return $rData;
    }
    
    /**
     * @desc 库存同步，只有丰达凯莱的需求
     * @param array $param(sKey,body)
     * body (coding,num,spec,color)
     * @return mixed
     */
    public function stock_inca($param){
    	global $db;
    	$rData = array(
    			'rStatus'=>100,
    			'message' => '库存同步执行完毕'
    	);
    	$eProduct	 = array();
    	$nullProduct = 0; // 记录一下传递过来的空值数量 by 小牛New 2015-11-17
    	$emptyGuid   = array();
    	$param 		 = is_object($param) ? (array)$param : $param;
    	if(empty($param['sKey'])){
    		$rData['rStatus'] = 101;
    		$rData['message'] = '验证key必须';
    		
    		return $rData;
    	}else{
    		$fp = array('+','/','=','_');
    		$rp = array('-','|','DHB',' ');
    		$cidarr = self::getCompanyInfo($param['sKey']);
    		$rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
    		if($cidarr['rStatus']==101)	return $cidarr;
    		
    		$cid		= $cidarr['CompanyID'];
    		$sdatabase	= $cidarr['Database'];
    		//记录日志
    		self::seaslog($cid, __METHOD__ . ' param', $param);
    		
    		$body = $param['body'] = json_decode(json_encode($param['body']),true);
    		if(count($body)>1000){
    			$rData['rStatus'] = 101;
    			$rData['message'] = '数据只能在1000条以内';
    			return $rData;
    		}
    		foreach($body as $k=>$val){
    			$val['guid'] = trim($val['guid']);
    			if(empty($val['guid'])){
    				$nullProduct++;
    				$val['message'] = 'GUID为空';
    				$emptyGuid[] = $val;
    				continue;
    			}
    
    			$cInfo = $db->get_row("SELECT ID,Coding FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} AND GUID='".$val['guid']."'");
    			$rData['debug_info']['ckproduct'][$val['guid']] = $db->last_query;
    
    			if(empty($cInfo)){
    				$eProduct[] = array(
    						'guid'=>$val['guid'],
    						'message'=>'商品不存在或还未同步',
    				);
    				continue;
    			}
    			$val['spec'] = $val['spec'] ? $val['spec'] : '';
    			$val['color'] = $val['color'] ? $val['color'] : '';
    			
    			//操作主库存
    			
//     			//买品未发数量
//     			$freeze_sql = "SELECT SUM(ContentNumber - ContentSend) AS freeze FROM ".$sdatabase.DATATABLE."_order_cart AS c
//                                    LEFT JOIN ".$sdatabase.DATATABLE."_order_orderinfo AS o ON o.OrderID = c.OrderID
//                                WHERE c.CompanyID = {$cid} AND o.OrderCompany = {$cid} AND c.ContentID = {$cInfo['ID']} AND o.OrderSendStatus IN(0,1,3)";
//     			$freeze = $db->get_var($freeze_sql);
//     			$rData['debug_info'][] = $freeze_sql;
    			
//     			//赠品未发数量
//     			$freeze_gift_sql = "SELECT SUM(ContentNumber - ContentSend) AS freeze FROM ".$sdatabase.DATATABLE."_order_cart_gifts AS c
//                                       LEFT JOIN ".$sdatabase.DATATABLE."_order_orderinfo AS o ON o.OrderID = c.OrderID
//                                     WHERE c.CompanyID = {$cid} AND o.OrderCompany = {$cid} AND c.ContentID = {$cInfo['ID']} AND o.OrderSendStatus IN(0,1,3)";
//     			$freeze_gift = $db->get_var($freeze_gift_sql);
//     			$rData['debug_info'][] = $freeze_gift_sql;
    			
    			//可用库存
//     			$allow = $freeze+$freeze_gift; //实际库存减去商品未发数再减去赠品未发数
//这里会增大库存误差
    			$sql = "UPDATE ".$sdatabase.DATATABLE."_order_number SET OrderNumber=(CASE WHEN (OrderNumber-".$val['num'].") < 0 THEN 0 ELSE (OrderNumber-".$val['num'].") END),
				ContentNumber=(CASE WHEN (ContentNumber-".$val['num'].") < 0 THEN 0 ELSE (ContentNumber-".$val['num'].") END) WHERE ContentID={$cInfo['ID']} AND CompanyID={$cid}";
    			$rData['debug_info']['update_stock'][$val['guid']] = $sql;
    			$db->query($sql);
    
    		}
    	}
    
    	if(count($eProduct)>0 || $nullProduct){
    		$rData['rStatus'] = 101;
    		$rData['message'] = '部分商品线下发货单同步失败!';
    		$rData['rData']	  = $eProduct;
    		$rData['rData']	  = array_filter(array_merge(array('eCoding' => $eProduct), array('emptyguid' => $emptyGuid)));
    	}
    
    	//记录日志
    	self::seaslog($cid, __METHOD__ . ' return', $rData);
    	//if(!isset($param['debug'])) unset($rData['debug']);

    	return $rData;
    }

    /**
     * @desc 款项 (平台中已确认到账的付款单传递给ERP接口
     * @param array $param (sKey,body)
     * @return array $rData
     */
    public function getFinanceList($param){
        global $db;
        $rData = array('rStatus'=>100);
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '验证key必须';
            
            return $rData;
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
            if($cidarr['rStatus']==101)  return $cidarr;
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            
            //记录日志
            self::seaslog($cid, __METHOD__ . ' param', $param);
            
            $sql = "SELECT f.FinanceID,f.FinanceTotal,f.FinanceAbout,IF(f.FinanceOrder<>'0' && f.FinanceOrder<>'','YIN','YU') as FinanceCategory,f.FinanceType,f.FinanceUser,f.FinanceAdmin,f.FinanceUpDate
                    FROM ".$sdatabase.DATATABLE."_order_finance AS f
                    WHERE f.FinanceFlag=2 AND f.FinanceCompany={$cid} AND f.FinanceApi='F' AND f.FinanceFlag<>'Y' ";
            $sql .= " limit ".$param['begin'].",".intval($param['step']);
            $list = $db->get_results($sql);
            $rData['debug_info']['sql'] = $sql;
            if(empty($list)){
                $rData['rStatus'] = 101;
                $rData['error'] = '没有相关收款单!';
            }else{
                $rData['rTotal'] = count($list);
                $rData['rData'] = $list;
            }

        }
        
        self::seaslog($cid, __METHOD__ . ' return', $rData);
        //if(!isset($param['debug']))  unset($rData['debug']);

        return $rData;
    }

    /**
     * @desc 获取款项详细
     * @param array $param (sKey,financeID)
     * @return array $rData
     */
    public function getFinanceContent($param){
        global $db;
        $rData = array('rStatus'=>100);
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '验证key必须';
            
            return $rData;
        }elseif(empty($param['financeID'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '收款单号不能为空!';
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            $rData['debug_info']['Company_id'] = $cidarr['CompanyID'];
            if($cidarr['rStatus']==101)  return $cidarr;
            $cid		= $cidarr['CompanyID'];
            $sdatabase	= $cidarr['Database'];
            
            //记录日志
            self::seaslog($cid, __METHOD__ . ' param', $param);
            
            $sql = "SELECT f.FinanceID,f.FinanceClient,f.FinanceOrder,IF(f.FinanceOrder<>'0' && f.FinanceOrder<>'','YIN','YU') as FinanceCategory,f.FinanceTotal,f.FinanceAbout,f.FinanceType,f.FinanceUser,f.FinanceAdmin,f.FinanceUpDate,f.FinanceApi
                    ,a.AccountsBank,a.AccountsNO,c.ClientNO
                    FROM ".$sdatabase.DATATABLE."_order_finance AS f LEFT JOIN ".$sdatabase.DATATABLE."_order_accounts AS a ON a.AccountsID = f.FinanceAccounts LEFT JOIN ".$sdatabase.DATATABLE."_order_client AS c ON c.ClientID = f.FinanceClient
                    WHERE f.FinanceFlag=2 AND f.FinanceCompany={$cid} AND f.FinanceID={$param['financeID']} AND f.FinanceFlag<>'Y' LIMIT 0,1 ";
            $single = $db->get_row($sql);
            $rData['debug_info']['sql'] = $sql;
            if($single){
                //更改付款单　接口取数据状态
                $db->query("UPDATE ".$sdatabase.DATATABLE."_order_finance SET FinanceApi='T' WHERE FinanceID={$param['financeID']}");
                $rData['rData'] = $single;
            }else{
                $rData['rStatus'] = 101;
                $rData['error'] = '付款单不存在!';
            }
        }
        
        //记录日志
        self::seaslog($cid, __METHOD__ . ' return', $rData);
        //if(!isset($param['debug'])) unset($rData['debug']);
        
        return $rData;
    }

    //1、地区档案，以及客户档案中的地区档案，做为非必填项，有数据就传，没数据就不传。
    //2、销售订单，不支持用户在用友中删除、修改（涉及两边数据的双向匹配，目前不考虑），如果订单取消，只能在用友中关闭订单，接口会把用友中订单的关闭状态传递给订货宝。

    // TODO:: 颜色规格组合控制 必须先维护 暂时先放放 FIXME
    // 金蝶中地区在订货宝中维护
    // 获取详细 允许重复取并将是否已取过状态返回给ERP
    // 数据校验
    // 接口数据量限定
    // TODO:: 付款单待定
    // 管理员添加启用序列号密码

    private function specification($data,$specType,$cid,$sdatabase){
        global $db;
        $data = array_filter($data);
        $data = array_unique($data);
        if(empty($data)) {
            return false;
        }
        $apos = array_map('strQuote',$data);
        $sql = "SELECT SpecName FROM ".$sdatabase.DATATABLE."_order_specification WHERE SpecType='".$specType."' AND CompanyID=".$cid." AND SpecName IN(".implode(',',$apos).")";
        $exists = $db->get_col($sql);
        $exists = $exists ? $exists : array();
        $data = array_diff($data,$exists);
        if($data){
            $header = "INSERT INTO ".$sdatabase.DATATABLE."_order_specification (`SpecName`,`SpecType`,`CompanyID`) VALUES";
            $body = array();
            foreach($data as $val){
                $body[] = "('{$val}','{$specType}',{$cid})";
            }
            $db->query($header.implode(",",$body));
        }

    }
    
    
    /**
     * 将商品编号添加上单引号，利于SQL查询
     *
     * @author wanjun
     * @param sring $productsno 以英文逗号分隔商品编号
     * @return array 添加单引号后的商品编号
     * @since 2015/11/08
     */
    private function _bulidCat($productsno = ''){
    	
    	if(!isset($productsno)) return $productsno;
    	
    	$productsno = str_replace(array(',', '，', '"', "'"), array(',', ',', '', ''), $productsno);
    	$arrProductNO = explode(',', $productsno);
    	$arrProductNO = array_filter($arrProductNO);
    	sort($arrProductNO);
    	//添加单引号，利于查询
    	$slen = count($arrProductNO);
    	for($i = 0; $i < $slen; $i++){
    		$arrProductNO[$i] = "'" . $arrProductNO[$i] . "'";
    	}
    	unset($productsno);
    	return $arrProductNO;
    	
    }//END _bulidCat
    
    /**
     * 以site id为键名重组分类信息并添加当前分类的顶级分类id和名称
     *
     * @author wanjun
     * @param array $siteInfo 分类信息，数组
     * @return array 组合后的数组
     * @since 2015/11/08
     */
    private function _contribulidSite($siteInfo = array(), $needTop = true){
    	
    	if(empty($siteInfo)) return $siteInfo;
    	
    	//使用siteid创建数组索引
    	$tlen = count($siteInfo);
    	$rebulidSite = array();
    	for($ti = 0; $ti < $tlen; $ti++) $rebulidSite[$siteInfo[$ti]['SiteID']] = $siteInfo[$ti];
    	
    	//加入当前和顶级分类
    	if($needTop){
    		$contribulid = array();
	    	foreach ($rebulidSite as $skey => $svalue){
	    		$siteSon = explode('.', substr(rtrim($svalue['SiteNO'], '.'), 2));
	    		//顶级分类
	    		$svalue['TopSite']		= $siteSon[0];
	    		$svalue['TopSiteName']	= $rebulidSite[$svalue['TopSite']]['SiteName'];
				$contribulid[$svalue['SiteID']] = $svalue;
	    	}
	    	$rebulidSite = $contribulid;
    	}
    	
    	unset($contribulid, $siteInfo);
    	return $rebulidSite;
    }//END _contribulidSite
    
    //use sealog plugin
    public static function seaslog($companyid = 0, $message,array $content = array(),$module = ''){
    	$maxsize = 1024000; //最大1m
    	//$filename='/app/livesystem/japi/data/log/erp'.$companyid.'.log';
        $filename=LOG_PATH.'erp'.$companyid.'.log';
    	$res['debug_info']['msg'] = $message;
    	$res['debug_info']['logtime'] = date("Y-m-d H:i:s",time());
    	if (array_key_exists('debug_info', $content))
    	{
    		$res['debug_info'] = $res['debug_info'] + $content['debug_info'];
    		unset($content['debug_info']);
    	}
    	if (array_key_exists('rdata', $content))
    	{
    		if (array_key_exists('debug_info', $content['rdata']))
    		{
    			$res['debug_info'] = $res['debug_info'] + $content['rdata']['debug_info'];
    			unset($content['rdata']['debug_info']);
    		}
    	}
    	$res['debug_data'] = $content;
    	//如果日志文件超过了指定大小则备份日志文件
    	if(file_exists($filename) && (abs(filesize($filename)) > $maxsize)){
    		$newfilename = dirname($filename).'/'.time().'-'.basename($filename);
    		rename($filename, $newfilename);
    	}
    	//如果是新建的日志文件，去掉内容中的第一个字符逗号
    	if(file_exists($filename) && abs(filesize($filename))>0){
    		$log = ",".json_encode($res) ."\r\n";
    	}else{
    		$log = json_encode($res). "\r\n";
    	}
    	//往日志文件内容后面追加日志内容
    	file_put_contents($filename, $log, FILE_APPEND);    
    	// 	SeasLog::log($companyid, $message . ' {notice}', array('{notice}' => print_r($content, 1)));
    }
    
    //use sealog plugin
    public static function seaslog_org($companyid = 0, $message,array $content = array(),$module = ''){
    	$maxsize = 1024000; //最大1m
        $filename='/app/livesystem/japi/data/log/erp'.$companyid.'.log';
        $res = array();
        $res['msg'] = $message;
        $res['logtime'] = date("Y-m-d H:i:s",time());
        //如果日志文件超过了指定大小则备份日志文件
        if(file_exists($filename) && (abs(filesize($filename)) > $maxsize)){
            $newfilename = dirname($filename).'/'.time().'-'.basename($filename);
            rename($filename, $newfilename);
        }
        //如果是新建的日志文件，去掉内容中的第一个字符逗号
        if(file_exists($filename) && abs(filesize($filename))>0){
            $log = ",".json_encode($res)."---".json_encode($content) ."\r\n";
        }else{
            $log = json_encode($res)."---".json_encode($content) . "\r\n";
        }
        //往日志文件内容后面追加日志内容
        file_put_contents($filename, $log, FILE_APPEND);

   // 	SeasLog::log($companyid, $message . ' {notice}', array('{notice}' => print_r($content, 1)));
    }

}

function make_kid($product_id, $product_color='', $product_spec='')
{
    $kid = $product_id;
    $fp = array('+','/','=','_');
    $rp = array('-','|','DHB',' ');
    if(empty($product_color) && empty($product_spec)) return $product_id;

    if(empty($product_color)) $product_color  = '统一';
    if(empty($product_spec)) $product_spec    = '统一';

    if(!empty($product_color))
    {
        $kid .= "_p_".str_replace($fp,$rp,base64_encode($product_color));
    }
    if(!empty($product_spec))
    {
        $kid .= "_s_".str_replace($fp,$rp,base64_encode($product_spec));
    }
    return $kid;
}

function make_kid2($product_id, $product_color='', $product_spec='')
{
    $kid = $product_id;
    if(!empty($product_color))
    {
        $kid .= "_p_".$product_color;
    }
    if(!empty($product_spec))
    {
        $kid .= "_s_".$product_spec;
    }
    return $kid;
}

/**
 * @desc 颜色规格加密
 * @param $str
 * @return mixed
 */
function CSEncode($str){
    $fp = array('+','/','=','_');
    $rp = array('-','|','DHB',' ');
    $str = htmlentities($str , ENT_QUOTES ,"UTF-8");
    return str_replace($fp,$rp,base64_encode($str));
}

function strQuote($str){
    return "'".trim($str)."'";
}

?>
