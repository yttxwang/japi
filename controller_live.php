<?php
// +----------------------------------------------------------------------
// | Describe: 接口控制器
// +----------------------------------------------------------------------
// | Author: seekfor <seekfor@gmail.com>
// +----------------------------------------------------------------------
// | Date: 2014-11-13
// +----------------------------------------------------------------------
class controller{

    //TODO:: 错误信息修改准确一点

	/**
    * 验证，获取Key
	*@param array $param(SerialNumber,Password) 用户名,密码
	*@return array $rdata(rStatus,error,sKey) 状态，提示信息，key
    *@author seekfor
    */
	public function  getTokenValue($param){
		global $db,$log;
		
		if(is_object($param)) $param = (array)$param;
		$log->logInfo('getTokenValue', $param);
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
			$rdata['message']   = '授权失败';
		}elseif($ruinfo['Password'] != $param['PassWord']){
			$rdata['rStatus'] = 101;
			$rdata['message']   = '密码不正确';
		}elseif($ruinfo['Status'] == 'F'){
			$rdata['rStatus'] = 101;
			$rdata['message']   = '该接口已停用';
		}elseif($ruinfo['RunStatus'] == 'F' && $ruinfo['Develop'] == 'DHB'){
            $rdata['rStatus'] = 101;
            $rdata['message'] = '该接口已关闭';
        }else{
			$rdata['rStatus'] = 100;
			$rdata['message'] = 'Tocken授权码获取成功';
			$token = md5 ( $ruinfo['ID'].$param['SerialNumber'] . $ruinfo['PassWord'].time());
			$db->query ( "update ".DB_DATABASEU.DATATABLE."_api_serial set Token='".$token."' where ID=" . $ruinfo ['ID'] . " limit 1" );
			$rdata['sKey']   = $token;
		}

        if(!empty($ruinfo['ID'])){
            $log->setLogFilePath($ruinfo['CompanyID']);
        }

		$log->logInfo('getTokenValue return', $rdata);
		return $rdata;
	}

	/**
    * 验证sKey,获取公司信息
	*@param string skey
	*@return array $rdata(rStatus,error,CompanyID,CompanyDatabase) 状态，提示信息，公司ID,数据库
    *@author seekfor
    */
	protected function getCompanyInfo($param){
		global $db,$log;

		if (empty($param))
		{
			$rdata['rStatus'] = 101;
			$rdata['error']   = '参数错误!';
		}else{
			//$db->use_disk_cache = true;
			//$db->cache_queries = true;
			$cinfo = $db->get_row ( "select CompanyID,CompanyDatabase,Status,RunStatus from " .DB_DATABASEU.DATATABLE. "_api_serial where Token='".$param."' limit 0,1" );
			//$db->cache_queries = false;

			if(empty($cinfo['CompanyID'] )) {
				$rdata['rStatus'] = 101;
				$rdata['error']   = '验证key过期';
			} else if($cinfo['Status'] == 'F') {
                $rdata['rStatus'] = 101;
                $rdata['error'] = '接口已停用!';
            } else if($cinfo['RunStatus'] == 'F' && $cinfo['Develop'] == 'DHB') {
                $rdata['rStatus'] = 101;
                $rdata['error'] = '接口已关闭!';
            }else{
				$rdata['rStatus'] = 100;
				$rdata['CompanyID']   = $cinfo['CompanyID'];
                $setInfo = $db->get_var("SELECT SetValue FROM ".DB_DATABASEU.DATATABLE."_order_companyset where SetCompany = ".$cinfo['CompanyID']." and SetName='erp' limit 0,1");
                $rdata['setInfo'] = $setInfo ? unserialize($setInfo) : array();
				if(empty($cinfo['CompanyDatabase'])) $rdata['Database'] = DB_DATABASE.'.'; else $rdata['Database'] = DB_DATABASE."_".$cinfo['CompanyDatabase'].'.';

                $log->setLogFilePath($cinfo['CompanyID']);
			}
		}
		return $rdata;
	}

    /**
     * @desc 获取公司账号信息
     * @param $param (CompanyID)
     * @return array
     */
    protected function getCsInfo($param){
        global $db,$log;
        $rData = array();
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['CompanyID'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '参数错误';
            $log->logInfo('getCsInfo',$param);
        }else{
            $cs_sql = "SELECT CS_Number,CS_BeginDate,CS_EndDate,CS_SmsNumber,CS_UpDate,CS_UpdateTime FROM ".DB_DATABASEU.DATATABLE."_order_cs WHERE CS_Company=".$param['CompanyID'];
            $csInfo = $db->get_row($cs_sql);
            if($param['debug']){
                $rData['cs_sql'] = $cs_sql;
            }
            if(empty($csInfo)){
                $log->logInfo('getCsInfo',$param);
                $rData['rStatus'] = 101;
                $rData['error'] = '数据为空';
                wlog("获取公司账号信息失败" , $cs_sql);
            }else{
                $log->setLogFilePath($param['CompanyID']);
                $log->logInfo('getCsInfo',$param);
                $rData['rStatus'] = 100;
                $rData['rData'] = $csInfo;
            }
        }
        $log->logInfo('getCsInfo return',$rData);
        return $rData;
    }

    /**
     * @desc 获取商品编码等信息
     * @param $param (sKey,flag,begin,step)
     * @return array
     * @author hxtgirq
     * @since 2015-08-17
     */
    public function getCoding($param) {
        global $db,$log;
        $rData = array('rStatus' => 100);
        $param = json_decode(json_encode($param),true);
        if(empty($param['sKey'])) {
            $rData['rStatus'] = 101;
            $rData['error'] = '验证key必需';
            $log->logInfo(__METHOD__ . ' param ' , $param);
        } else {
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus'] == 101) {
                $log->logInfo(__METHOD__ . ' param ' , $param);
                return $cidarr;
            }
            $log->logInfo(__METHOD__ . ' param ' , $param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
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
            if($param['debug']) {
                $rData['rDebug']['list_sql'] = $db->last_query;
            }
        }

        return $rData;
    }

    /**
     * @desc 商品外码转内码 Coding唯一,GUID唯一
     * @param $param (sKey,body)
     * @body[0] (guid,coding)
     * @return array $rData
     * @author hxtgirq
     * @since 2015-06-29
     */
    public function productOuterToInner($param) {
        global $db,$log;
        $rData = array('rStatus'=>100);
        //$param = is_object($param) ? (array)$param : $param;
        $param = json_decode(json_encode($param),true);
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '参数错误!';
            $log->logInfo(__METHOD__.' param ',$param);
        } else if($param['count'] != count($param['body'])) {
            $rData['rStatus'] = 101;
            $rData['error'] = '数据传输错误!';
            $log->logInfo(__METHOD__.' param ',$param);
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo(__METHOD__.' param ',$param);
                return $cidarr;
            }
            $log->logInfo(__METHOD__.' param ',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $errItems = array();
            foreach($param['body'] as $val) {
                $sql = "UPDATE  " . $sdatabase . DATATABLE."_order_content_index SET GUID='".$val['guid']."',ERP='T' WHERE CompanyID={$cid} AND Coding='".$val['coding']."'";
                $rData['debug']['sql'][] = $sql;
                $rst = $db->query($sql);
                if($rst === false) {
                    $errItems[] = $val;
                    wlog("商品外码转内码失败" , $sql);
                }
            }
            //coding
            if($errItems) {
                $rData['rStatus'] = 101;
                $rData['error'] = "商品外码转内码失败!";
                $rData['rData'] = $errItems;
            }
        }
        if(!$param['debug']){
            unset($rData['debug']);
        }
        $log->logInfo(__METHOD__.' return '.$rData);
        return $rData;
    }

    /**
     * @desc 药店外码转内码
     * @param $param (sKey,type,body) type => name/coding  default coding
     * @body[0] (clientGUID,clientNO,clientName) , clientName => ClientCompanyName
     * @return array $rData;
     * @author hxtgirq
     * @since 2015-06-29
     */
    public function clientOuterToInner($param) {
        global $db,$log;
        
        $rData = array('rStatus'=>100);
        //$param = is_object($param) ? (array)$param : $param;
        $param = json_decode(json_encode($param),true);
        $param['type'] = $param['type'] ? $param['type'] : 'guid';
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error']   = '参数错误!';
            $log->logInfo(__METHOD__.' param ',$param);
        } else if($param['count'] != count($param['body'])) {
            $rData['rStatus'] = 101;
            $rData['error']   = '数据传输错误!';
            $log->logInfo(__METHOD__.' param ',$param);
        } else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo(__METHOD__.' param ',$param);
                return $cidarr;
            }
            $log->logInfo(__METHOD__.' param ',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $errItems   = array();  //存储失败的药店
            $emptyItems = array();  //存储GUID为空的药店
            foreach($param['body'] as $val) {
                
//                if($val['clientName'] && $param['type'] == 'name') {
//                    $sql = "UPDATE  " . $sdatabase . DATATABLE."_order_client SET ClientGUID='".$val['clientGUID']."',ERP='T' WHERE ClientCompany={$cid} AND ClientCompanyName='".$val['clientName']."' AND ERP='F'";
//                } else {
//                    $sql = "UPDATE  " . $sdatabase . DATATABLE."_order_client SET ClientGUID='".$val['clientGUID']."',ERP='T' WHERE ClientCompany={$cid} AND ClientNO='".$val['clientNO']."'";
//                }
            
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
                
                $db->query($outSql);
                $rData['debug']['upguid'][] = $outSql;
                $rst = $db->query($outSql);
                if($rst === false) {
                    $errItems[] = $val;
                    wlog("药店外码转内码失败" , $sql);
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
        
        if(!$param['debug']){
            unset($rData['debug']);
        }
        $log->logInfo(__METHOD__.' return ',$rData);
        
        return $rData;
    }

    /**
     * @desc ERP接品通信发生错误,推送到DHB处理
     * @param $param (date,message)
     * @return array $rData
     * @author hxtgirq
     * @since 2015-06-17
     */
    public function popError($param) {
        global $db,$log;
        $rData = array('rStatus'=>100);
        if(empty($param['sKey'])) {
            $rData['rStatus'] = 101;
            $rData['error'] = "验证key必需";
            $log->logInfo("popError param ",$param);
        } else {
            $cidarr = $this->getCompanyInfo($param['sKey']); //取公司ID,Database
            $sdatabase = $cidarr['Database'];
            if($cidarr['rStatus'] == "101"){
                $log->logInfo("popError param ",$param);
                $rData = $cidarr;
            } else {
                $log->logInfo("popError param ",$param);
                $sql = "INSERT INTO ".$sdatabase.DATATABLE."_api_error (CompanyID,err_message,err_date) VALUES (".$cidarr['CompanyID'].",'".$param['error']."','".$param['date']."')";
                //$log->logInfo("popError - SQL " , $sql);
                $result = $db->query($sql);
                if($result === false) {
                    $rData['rStatus'] = 101;
                    $rData['error'] = "错误信息保存失败!";
                    wlog("错误消息处理失败" , $sql);
                }
            }

        }
        $log->logInfo("popError return " , $rData);
        return $rData;
    }

	/**
    * 获取订单列表
	*@param array $param(sKey,flag,begin,step) key,起始值，步长
	*@return array $rdata(rStatus,error,rData) 状态，提示信息，数据
    *@author seekfor
    */
	public function getOrderList($param){
		global $db,$log;
		if(is_object($param)) $param = (array)$param;

		if (empty ( $param['sKey'] ))
		{
			$rdata['rStatus'] = 101;
			$rdata['message']   = '验证key必需';
            $log->logInfo('getOrderList', $param);
		}else{
			$cidarr = $this->getCompanyInfo($param['sKey']); //取公司ID,Database
			if($cidarr['rStatus'] == "101"){
                $log->logInfo('getOrderList', $param);
				return $cidarr;
			}else{
                $log->logInfo('getOrderList', $param);
                $flags = array(
                    'pending' => 0,     //待审核
                    'stocking' => 1,    //备货中
                    'outLibrary' => 2,  //已出库
                    'receiving' => 3,   //已收货
                    'receivables' => 5, //已收款
                    'complete' => 7,    //已完成
                );

                $flag_where = "";
                //统一使用 OrderPayStatus 作为付款状态             
                if(isset($param['flag']) && $param['flag'] == 'receivables'){
                    $flag_where = " AND o.OrderPayStatus=2";
                }
                else if(isset($flags[$param['flag']])) {
                    $flag_where = " AND o.OrderStatus=" . $flags[$param['flag']];
                }

				$cid    = $cidarr['CompanyID'];
				$sdatabase = $cidarr['Database'];
                $setInfo = $cidarr['setInfo'];
                $log->logInfo('setInfo',$setInfo);
//                 $check = $setInfo['erp_order_check'] == 'Y' ? '1' : '0,1';
                //无需审核时，取消状态复核限制
                if($setInfo['erp_order_check'] == 'Y'){
                    $check = '1';
                }else{
                    $check = '0,1';
                    $flag_where = "";
                }
//                 $check = '0,1';
                
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
				
                if($param['debug']) {
                    $rdata['rDebug']['SQL'] = $sql;
                }
				$oinfo  = $db->get_results ( $sql );
				$log->logInfo('getOrderList sql', $sql);

				if(empty($oinfo)) {
					$rdata['rStatus'] = 101;
					$rdata['message']   = '无符合条件的数据';
                    wlog("获取订单列表数据为空或异常" , $sql );
				}else{
					
					$senttypearr = array (
							'1' => '送货上门',
							'2' => '快递',
							'3' => '货运',
							'4' => '上门自取'
					);
					
                    foreach($oinfo as $key=>$val){
                        $oinfo[$key]['OrderSN'] 		   = 'ETONG'.$val['OrderSN'];
                        $oinfo[$key]['OrderSendTypeTrans'] = $senttypearr[$oinfo[$key]['OrderSendType']];
                    }
					$rdata['rStatus'] = 100;
					$rdata['message'] = '获取订单列表完成';
                    $rdata['count']   = count($oinfo);
					$rdata['rData']   = $oinfo;
				}
			}
		}
		$log->logInfo('getOrderList return', $rdata);
		return $rdata;
	}

	/**
    * 获取订单明细
	*@param array $param(sKey,orderSn) key,订单号
	*@return array $rdata(rStatus,error,rData) 状态，提示信息，数据
    *@author seekfor
    */
	public function getOrderContent($param){
		global $db,$log;
		
		if(is_object($param)) $param = (array)$param;
        $param['orderSn'] = str_replace('ETONG','',$param['orderSn']);
		if (empty ( $param['sKey'] ))
		{
			$rdata['rStatus'] = 101;
			$rdata['message']   = '验证key必需';
            $log->logInfo('getOrderContent', $param);
		} else if( empty($param['orderSn'])){
            $rdata['rStatus'] = 101;
            $rdata['message']   = '订单号不能为空!';
            $log->logInfo('getOrderContent', $param);
        }else{
			$cidarr = $this->getCompanyInfo($param['sKey']); //取公司ID,Database
			$cid    = $cidarr['CompanyID'];
            $log->logInfo('getOrderContent', $param);
            
			if($cidarr['rStatus'] == "101"){
				return $cidarr;
			}else{
				$sdatabase = $cidarr['Database'];
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
				
				//商品明细
				$sqlc   = "select SiteID,GUID as guid,Name,Coding,Units,ContentColor,ContentSpecification,ContentPrice,ContentNumber,ContentPercent,'c' as conType from ".$sdatabase.DATATABLE."_view_index_cart where OrderID=".$oinfo['OrderID']." and CompanyID=".$cid." ".$shieldCondition;
                //$log->logInfo("取订单明细-SQL:",$sqlc);
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
					$rdata['message']   = "数据为空，获取订单[{$param['orderSn']}]表头失败";
                    wlog("获取订单[{$param['orderSn']}]表头失败",$sql);
				}else{
                    $oinfo['OrderSN'] = 'ETONG'.$oinfo['OrderSN'];
                    //添加取过标记 通知时才更改
                    $db->query("UPDATE ".$sdatabase.DATATABLE."_order_orderinfo SET OrderApi='T' WHERE OrderID=".$oinfo['OrderID']);
					unset($oinfo['OrderID'],$oinfo['InvoiceTax']);
					$rdata['rStatus'] = 100;
                    $rdata['count'] = count($infoall);
                    $rdata['message'] = '获取单据详情完毕';
					$rdata['rData']['header']   = $oinfo;
					$rdata['rData']['body']   	= $infoall;// $cinfo;
					//$rdata['rData']['sql'] 	= $sql;
                    if(count($infoall) == 0) {
                        wlog("获取订单[{$param['orderSn']}]明细数据失败" , array(
                            '买品' => $sqlc,
                            '赠品' => $sqlg,
                        ));
                    }
				}
			}
		}
		$log->logInfo('getOrderContent return', $rdata);
		return $rdata;
	}


    /**
     * 订单获取成功后通知DHB将OrderApi设置为T
     * @param array $param (sKey,body)
     * @return array $rData
     * @author hxtgirq
     * @since 2015-06-15
     */
    public function orderNotify($param = array()){
        global $db,$log;
        $rData = array('rStatus' => 100);
        $param  = json_decode(json_encode($param),true);
        $data = $param['body'];
        if(empty($param['sKey'])) {
            $rData['rStatus'] = 101;
            $rData['error'] = '验证key必需!';
            $log->logInfo("orderNotify Param:",$param);
            $log->logInfo("orderNotify Return ",$rData);
            return $rData;
        }

        $cidarr = $this->getCompanyInfo($param['sKey']);
        $sdatabase = $cidarr['Database'];
        if($cidarr['rStatus'] == 101){
            $log->logInfo("orderNotify Param:",$param);
            return $cidarr;
        }

        $log->logInfo("orderNotify Param:",$param);
        foreach($data as  $key => $sn) {
            $data[$key] = str_replace(array('DHB','RC'),'',$sn);
        }
        $isSuccess = true;
        foreach(array_chunk($data,50) as $sns) {
            $result = $db->query("UPDATE ".$sdatabase.DATATABLE."_order_orderinfo SET OrderApi='T' WHERE OrderCompany={$cidarr['CompanyID']} AND OrderSN IN(".implode(",",array_map("add_quotes",$sns)).")");
            $isSuccess = $result === false ? false : $isSuccess;
            if($result === false) {
                wlog("处理订单通知失败" , $db->last_query);
            }
        }
        if(!$isSuccess) {
            $rData['rStatus'] = 101;
            $rData['error'] = '通知处理失败!';
        }
        $log->logInfo($rData);
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
        global $db,$log;
        $rData = array('rStatus'=>100 , 'message' => '接口更新订单状态执行完毕');
        $eOrderSN = array();
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
            $log->logInfo(__METHOD__.' param ',$param);
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo(__METHOD__.' param ',$param);
                return $cidarr;
            }
            $log->logInfo(__METHOD__.' param ',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $setInfo = $cidarr['setInfo'];
            $body = $param['body'] = json_decode(json_encode($param['body']),true);
            if(count($body)>1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '单次同步数据只能在1000条以内';
                $log->logInfo(__METHOD__. ' return',$rData);
                return $rData;
            }
            if($param['count']!=count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '传输数据错误!';
                $log->logInfo(__METHOD__.' return ',$rData);
                return $rData;
            }
            $allowAct = array('open','close','del');
            $rData['debug']['setInfo'] = $setInfo;
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
                $val['orderSN'] = str_replace('ETONG','',$val['orderSN']);
                $order = $db->get_row("SELECT * FROM ".$sdatabase.DATATABLE."_order_orderinfo WHERE OrderSN='{$val['orderSN']}' AND OrderCompany={$cid} LIMIT 0,1");
                if(empty($order)){
                    $eOrderSN[] = array(
                        'orderSN'=>'ETONG'.$val['orderSN'],
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

                $rData['debug']['sql'][] = $sql;
                if($rst===false){
                    $eOrderSN[] = array(
                        'orderSN'=>$init_sn,
                        'message'=>'第'.($key+1).'条订单操作失败!',
                    );
                    wlog("订单状态更新失败" , $sql );
                }
            }
        }
        if(!$param['debug']){
            unset($rData['debug']);
        }
        
        if(count($eOrderSN)>0){
            $rData['rStatus'] = 101;
            $rData['message'] = '部分订单操作失败!';
            $rData['rData']   = $eOrderSN;
        }
        $log->logInfo(__METHOD__.' return '.$rData);
        return $rData;
    }

    /**
     * @desc 获取退货单列表
     * @param array $param(sKey,begin,step)
     * @return array $rdata(rStatus,error,rData) 状态,提示,数据
     */
    public function getReturnList($param){
        global $db,$log;
        $rData = array();
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '验证key必须!';
            $log->logInfo('getReturnList',$param);
        }else{
            $cidarr = $this->getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo('getReturnList',$param);
                return $cidarr;
            }
            $log->logInfo('getReturnList',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];

            $sql = "SELECT ReturnSN,ReturnSendAbout,ReturnAbout,ReturnTotal,ReturnDate,ReturnStatus,ReturnType FROM ".$sdatabase.DATATABLE."_order_returninfo WHERE ReturnCompany=".$cid." and (ReturnStatus=3 OR ReturnStatus=5) AND ReturnApi='F'";
            $sql .= " Limit ".$param['begin'].",".$param['step'];
            $log->logInfo("getReturnList",$sql);
            $rinfo = $db->get_results($sql);
            if(empty($rinfo)){
                $rData['rStatus'] = 101;
                $rData['error'] = '数据为空';
                wlog("获取退货单列表数据为空或异常" , $sql);
            }else{
                foreach($rinfo as $key=>$val){
                    $rinfo[$key]['ReturnSN'] = ($cid == 577 ? 'RC' : 'DHB').$val['ReturnSN'];
                }
                $rData['rStatus'] = 100;
                $rData['rTotal'] = count($rinfo);
                $rData['rData'] = $rinfo;
            }
        }
        $log->logInfo('getReturnList return',$rData);
        return $rData;
    }

    /**
     * @desc 获取退货单详细
     * @param array $param(sKey,returnSN)
     * @return array $rData(rStatus,error,rData)
     */
    public function getReturnContent($param){
        global $db,$log;
        $rData = array();
        $param = is_object($param) ? (array)$param : $param;

        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '验证key必须';
            $log->logInfo("getReturnContent",$param);
        }else{
            //$param['returnSN'] = substr($param['returnSN'],3);
            $param['returnSN'] = str_replace(array('DHB','RC'),'',$param['returnSN']);
            $cidarr = $this->getCompanyInfo($param['sKey']);
            $sdatabase = $cidarr['Database'];
            if($cidarr['rStatus']==101){
                $log->logInfo("getReturnContent",$param);
                return $cidarr;
            }
            $log->logInfo("getReturnContent",$param);
            $cid = $cidarr['CompanyID'];
            //获取退货单基本信息
            $hsql = "SELECT r.ReturnID,r.ReturnOrder,r.ReturnSN,r.ReturnSendAbout,ReturnProductW,ReturnProductB,ReturnAbout,ReturnDate,ReturnType,ReturnTotal,c.ClientNO,c.ClientGUID,r.ReturnApi
                      FROM ".$sdatabase.DATATABLE."_order_returninfo AS r
                      LEFT JOIN ".$sdatabase.DATATABLE."_order_client AS c
                      ON c.ClientID = r.ReturnClient
                      WHERE ReturnCompany=".$cid." AND ReturnSN='".$param['returnSN']."'
                      Limit 0,1";
            $hdata = $db->get_row($hsql);
            $submitLog = $db->get_row("select Name from ".$sdatabase.DATATABLE."_order_returnsubmit where CompanyID=".$cid." and OrderID=".$hdata['ReturnID']." and Status='审核通过' limit 0,1");
            if(empty($hdata)){
                $rData['rStatus'] = 101;
                $rData['error'] = '数据为空';
                wlog("获取退货单[{$param['returnSN']}]表头失败", $hsql);
            }else{
                $hdata['ReturnSN'] = ($cid == 577 ? 'RC' : 'DHB').$hdata['ReturnSN'];
                $hdata['ReturnOrder'] = ($cid == 577 ? 'RC' : 'DHB').$hdata['ReturnOrder'];
                $hdata['AdminUser'] = $submitLog['Name'];
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
                wlog("未找到退货单[{$param['returnSN']}]详细信息" , $isql);
                //更改已取状态 通知的时候才更新状态
                $db->query("UPDATE ".$sdatabase.DATATABLE."_order_returninfo SET ReturnApi='T' WHERE ReturnID=".$hdata['ReturnID']);
            }

        }
        $log->logInfo("getReturnContent return",$rData);
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
        global $db,$log;
        $rData = array('rStatus' => 100);
        $param  = json_decode(json_encode($param),true);
        $data = $param['body'];
        if(empty($param['sKey'])) {
            $rData['rStatus'] = 101;
            $rData['error'] = '验证key必须!';
            $log->logInfo("returnNotify Param:",$param);
            $log->logInfo("returnNotify Return ",$rData);
            return $rData;
        }

        $cidarr = $this->getCompanyInfo($param['sKey']);
        $sdatabase = $cidarr['Database'];
        if($cidarr['rStatus'] == 101){
            $log->logInfo("returnNotify Param:",$param);
            return $cidarr;
        }

        $log->logInfo("returnNotify Param:",$param);
        foreach($data as  $key => $sn) {
            $data[$key] = str_replace(array('DHB','RC'),'',$sn);
        }
        $isSuccess = true;
        foreach(array_chunk($data,50) as $sns) {
            $result = $db->query("UPDATE ".$sdatabase.DATATABLE."_order_returninfo SET ReturnApi='T' WHERE ReturnCompany={$cidarr['CompanyID']} AND ReturnSN IN(".implode(",",array_map("add_quotes",$sns)).")");
            $isSuccess = $result === false ? false : $isSuccess;
            if($result === false) {
                wlog("退货单获取通知",$db->last_query);
            }
        }
        if(!$isSuccess) {
            $rData['rStatus'] = 101;
            $rData['error'] = '通知处理失败!';
        }
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
        global $db,$log;
        $rData = array('rStatus'=>100);
        $eClient = array();		//错误的药店资料
        $hasClient = array();	//进行更新内码操作的资料
        $param = is_object($param) ? (array)$param : $param;

        if(empty($param['sKey'])){

            $rData['rStatus'] = 101;
            $rData['message'] = '该 Tocken 未经授权!';
            $log->logInfo('addDealers param ',$param);
        }else{
            $cidarr 	= $this->getCompanyInfo($param['sKey']);
            $sdatabase  = $cidarr['Database'];
            $cid		= $cidarr['CompanyID'];
            $log->logInfo('addDealers param ',$param);
            
            if($cidarr['rStatus']==101){
                return $cidarr;
            }
            
            $csInfo = self::getCsInfo(array('CompanyID'=>$cid,'debug'=>$param['debug']));
            
            if($csInfo['rStatus']==101){
                return $csInfo;
            }
            $csInfo = $csInfo['rData'];
            $body = $param['body'] = json_decode(json_encode($param['body']),true);
            
            if(empty($body)){
            	$rData['rStatus'] = 101;
            	$rData['message'] = '没有传递终端数据...';
            	$log->logInfo(__METHOD__. ' return', $rData);
            	return $rData;
            }

            if(count($body)>1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '单次同步数据只能在1000条以内';
                $log->logInfo(__METHOD__. ' return', $rData);
                return $rData;
            }
            if($param['count']!=count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
                $log->logInfo(__METHOD__.' return ', $rData);
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
                        $rData['debug'][$key]['upguid'] = $outSql;
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
                if(mb_strlen($val['bankname'],"utf-8") > 50) {
                    $eClient[] = array_merge($val,array('message' => '开户行名称长度超过50个汉字,请重试!'));
                    wlog("新增药店,开户行名称超长" , $val);
                    $isok = false;
                }
                
                if(!$isok){
                    continue;
                }
                
                if(in_array($val['guid'], $guidExists, true)) {
                	$eClient[] = array_merge($val,array('message' => 'ERP内码已使用['.$val['guid'].']'));
                	wlog("ERP内码已使用[".$val['guid']."]" , $val);
                	continue;
                }
                $guidExists[] = $val['guid'];
               
                if(in_array($val['clientno'], $noExists, true)) {
                    $eClient[] = array_merge($val,array('message' => '药店编号已使用['.$val['clientno'].']'));
                    wlog("药店编号已使用[".$val['clientno']."]" , $val);
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
                $rData['debug']['dealers'][$key]['dealers'] = $dealers_sql;
                
                if(false!==$db->query($dealers_sql)){
                    $inid = $db->insert_id;
                    $client_sql = "INSERT INTO ".$sdatabase.DATATABLE."_order_client(ClientID,ClientCompany,ClientLevel,ClientName,ClientCompanyName,ClientCompanyPinyi,ClientNO,ClientTrueName,ClientEmail,
                ClientPhone,ClientFax,ClientMobile,ClientAdd,ClientAbout,ClientDate,ClientShield,ClientSetPrice,ClientPercent,
                ClientBrandPercent,ClientPay,ClientConsignment,AccountName,BankName,BankAccount,InvoiceHeader,TaxpayerNumber,ClientGUID,ERP,ClientArea)
                               VALUES(".$inid.",".$cid.",'".$val['clientLevel']."','".$val['clientName']."','".$val['companyname']."','".$val['clientCompanyPinyi']."','".$val['clientno']."','".$val['clientTrueName']."','".$val['clientEmail']."','".$val['clientPhone']."',
                               '".$val['clientFax']."','".$val['clientPhone']."','".$val['clientAdd']."','".$val['clientAbout']."',".time().",'".$val['clientShield']."','".$val['clientSetPrice']."','".$val['clientPercent']."',
                               '".$val['clientBrandPercent']."','".$val['clientPay']."','".$val['clientConsignment']."','".$val['accountName']."','".$val['bankName']."','".$val['bankAccount']."','".$val['invoiceHeader']."','".$val['taxpayerNumber']."','{$val['guid']}','T',".$val['clientArea'].")";
                    
                    $booRst = $db->query($client_sql);
                    if(!$booRst){
                        $log->logInfo("addDealers 错误 client-SQL:" , $client_sql);
                        wlog("添加药店失败-client" , $client_sql);
                        $db->query("DELETE FROM " .$sdatabase.DATATABLE."_order_dealers WHERE ClientCompany= ".$cid." AND ClientID=" . $inid);
                        $eClient[] = array_merge($val,array('message' => '药店client保存失败!'));
                    }
                    $rData['debug']['dealers'][$key]['dealers_son'] = $client_sql;
                }else{
                    $log->logInfo("addDealers 错误 dealers-SQL:" , $dealers_sql);
                    $eClient[] = array_merge($val,array('message' => '药店dealers保存失败!'));
                    wlog("添加药店失败-dealers" , $dealers_sql);
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
        
        $log->logInfo('addDealers return ',$rData);
        
        if(!$param['debug']){
        	unset($rData['debug']);
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
        global $db,$log;
        $rData = array('rStatus'=>100);
        $eClient = array();
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '该 Tocken 未经授权!';
            $log->logInfo('updateDealers param ',$param);
        }else{
            $cidarr = $this->getCompanyInfo($param['sKey']);
            $sdatabase = $cidarr['Database'];
            $cid = $cidarr['CompanyID'];
            $log->logInfo('updateDealers param ',$param);
            if($cidarr['rStatus']==101){
                return $cidarr;
            }

            $body = $param['body'] = json_decode(json_encode($param['body']),true);
            if(count($body)>1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '单次同步数据只能在1000条以内';
                $log->logInfo(__METHOD__. ' return',$rData);
                return $rData;
            }
            if($param['count']!=count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
                $log->logInfo(__METHOD__.' return ',$rData);
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
                
                if(!empty($val['status'])){
                    $setInfo[] = $dInfo[] = "ClientFlag=".($val['status']=='T' ? 0 : 9);
                }

                if(in_array($val['clientno'], $noExists) && $val['guid'] && $val['guid'] != array_search($val['clientno'],$noExists)) {
                    $eClient[] = array_merge($val,array('message' => '药店编号已使用!'));
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
                    $rData['debug']['client'][] = $update_client_sql = $db->last_query;
                    if(count($dInfo) > 0) {
                        $drst = $db->query("UPDATE ".DB_DATABASEU.DATATABLE."_order_dealers SET ".implode(',',$dInfo)." WHERE ClientID IN(SELECT ClientID FROM ".$sdatabase.DATATABLE."_order_client WHERE ClientCompany={$cid} AND ClientGUID='{$val['guid']}')");
                        $rData['debug']['dealers'][] = $db->last_query;
                        if($drst === false) {
                            wlog("更新药店失败-dealers" , $db->last_query);
                        }
                    }

                    if($rst===false){
                        $eClient[] = array_merge($val,array('error'=> '修改失败!'));
                        wlog("更新药店失败-client" , $update_client_sql);
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
//                 $log->logInfo("del-dealers-sql", $dealers_del_sql);
//                 $log->logInfo("del-client-sql", $client_del_sql);
                $log->logInfo("update-client-sql", $client_del_sql);
                $rData['debug']['del'] = $client_del_sql;
                //删除Dealers
//                 $ddrst = $db->query($dealers_del_sql);
                //删除Client
                $dcrst = $db->query($client_del_sql);

//                 if($ddrst === false) {
//                     wlog("删除药店失败-dealers" , $dealers_del_sql);
//                 }
                if($dcrst === false) {
                    wlog("删除药店失败-client" , $client_del_sql);
                }

            }
        }
        
        $rData['message'] = '修改药店档案执行完毕，错误：'.count($eClient).'个';
        if($eClient){
            $rData['rStatus'] = 101;
            $rData['rData'] = $eClient;
        }
        $log->logInfo('updateDealers return ',$rData);
        
        if(!$param['debug']){
            unset($rData['debug']);
        }
        
        return $rData;
    }
    
    /**
     * @desc 批量修改药店，API独立客户接口
     * @param array $param (sKey,count,body)
     * body (clientCompanyName,clientNO,clientTrueName,clientArea,bankName,status)
     * @return array $rData
     * //修改药店未验证药店数量
     */
    public function updateDealers_static($param){
    	global $db,$log;
    	$rData = array('rStatus'=>100);
    	$eClient = array();
    	$param = is_object($param) ? (array)$param : $param;
    	if(empty($param['sKey'])){
    		$rData['rStatus'] = 101;
    		$rData['error'] = '该 Tocken 未经授权!';
    		$log->logInfo('updateDealers param ',$param);
    	}else{
    		$cidarr = $this->getCompanyInfo($param['sKey']);
    		$sdatabase = $cidarr['Database'];
    		if($cidarr['rStatus']==101){
    			$log->logInfo('updateDealers param ',$param);
    			return $cidarr;
    		}
    		$log->logInfo('updateDealers param ',$param);
    		$cid = $cidarr['CompanyID'];
    
    		$body = $param['body'] = json_decode(json_encode($param['body']),true);
    		if(count($body)>1000){
    			$rData['rStatus'] = 101;
    			$rData['error'] = '数据只能在1000条以内!';
    			$log->logInfo(__METHOD__. ' return',$rData);
    			return $rData;
    		}
    		if($param['count']!=count($body)){
    			$rData['rStatus'] = 101;
    			$rData['error'] = '数据传输错误!';
    			$log->logInfo(__METHOD__.' return ',$rData);
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
    			$setInfo = array();
    			$dInfo = array();
    			if($val['status'] == 'F') {
    				$del_client[] = $val['clientGUID'];
    				continue;
    			}
    
    			if(!empty($val['clientCompanyName'])){
    				$setInfo[] = "ClientCompanyName='".$val['clientCompanyName']."'";
    				$setInfo[] = "ClientCompanyPinyi='".$letter->C($val['clientCompanyName'])."'";
    			}
    			if(!empty($val['clientTrueName'])){
    				$setInfo[] = "ClientTrueName='".$val['clientTrueName']."'";
    			}
    			if(!empty($val['clientArea'])){
    				//获取对应地区
    
    				//2015年7月28日16时 接口传递的地区ID直接为DHB中地区的ID
    				//$areaID = $db->get_var("SELECT TrueAreaID FROM ".$sdatabase.DATATABLE."_api_area WHERE AreaID='".$val['clientArea']."'");
    				//$val['clientArea'] = $areaID ? $areaID : 0; //药店所在地区
    				$setInfo[] = "ClientArea='".((int)$val['clientArea'])."'";
    			}
                if(!empty($val['bankName'])){
                    $setInfo[] = "BankName='".$val['bankName']."'";
                }
    			if(!empty($val['clientNO'])){
    				$setInfo[] = "ClientNO='".$val['clientNO']."'";
    			}
    			if(!empty($val['status'])){
    				$setInfo[] = $dInfo[] = "ClientFlag=".($val['status']=='T' ? 0 : 9);
    			}
    
    			if(in_array($val['clientNO'],$noExists) && $val['clientGUID'] != array_search($val['clientNO'],$noExists)) {
    				$eClient[] = array_merge($val,array('error' => '药店编号已使用!'));
    				continue;
    			}
    
                if(in_array($val['clientCompanyName'],$nameExists) && $val['clientGUID'] != array_search($val['clientCompanyName'],$nameExists)) {
                    $eClient[] = array_merge($val,array('error' => '药店名称已使用!'));
                    continue;
                }
    
    			//$log->logInfo("update field : " , count($setInfo));
    			if(count($setInfo) == 0) {
    				//可以修改的字段一个都没有修改
    				continue;
    			}
    
    			if($setInfo && $val['clientGUID'] != ""){
    				$rst = $db->query("UPDATE ".$sdatabase.DATATABLE."_order_client SET ".implode(',',$setInfo)." WHERE ClientGUID='{$val['clientGUID']}' AND ClientCompany={$cid}");
    				$rData['debug']['client'][] = $update_client_sql = $db->last_query;
    				if(count($dInfo) > 0) {
    					$drst = $db->query("UPDATE ".DB_DATABASEU.DATATABLE."_order_dealers SET ".implode(',',$dInfo)." WHERE ClientID IN(SELECT ClientID FROM ".$sdatabase.DATATABLE."_order_client WHERE ClientGUID='{$val['clientGUID']}' AND ClientCompany={$cid})");
    					$rData['debug']['dealers'][] = $db->last_query;
    					if($drst === false) {
    						wlog("更新药店失败-dealers" , $db->last_query);
    					}
    				}
    
    				if($rst===false){
    					$eClient[] = array_merge($val,array('error'=> '修改失败!'));
    					wlog("更新药店失败-client" , $update_client_sql);
    				}
    				$rData['debug']['sql'][] = $db->last_query;
    			}elseif($val['clientGUID'] == ""){
    				$eClient[] = array_merge($val,array('error'=> '缺少clientGUID或没有要修改的数据!'));
    			}
    
    		}
    		if(!empty($del_client)) {
    			$client_guid_str = "'" . implode("','",$del_client) . "'";
    			$client_id = $db->get_col("SELECT ClientID FROM ".$sdatabase.DATATABLE."_order_client WHERE ClientGUID IN(".$client_guid_str.")");
    			$client_id = $client_id ? $client_id : array();
    			$client_id[] = 0;
    
    			$client_id_str = implode(",",$client_id);
    			$dealers_del_sql = "DELETE FROM ".DB_DATABASEU.DATATABLE."_order_dealers WHERE ClientID IN({$client_id_str})";
    			$client_del_sql = "DELETE FROM ".$sdatabase.DATATABLE."_order_client WHERE ClientID IN({$client_id_str})";
    			$log->logInfo("del-dealers-sql",$dealers_del_sql);
    			$log->logInfo("del-client-sql",$client_del_sql);
    			//删除Dealers
    			$ddrst = $db->query($dealers_del_sql);
    			//删除Client
    			$dcrst = $db->query($client_del_sql);
    
    			if($ddrst === false) {
    				wlog("删除药店失败-dealers" , $dealers_del_sql);
    			}
    			if($dcrst === false) {
    				wlog("删除药店失败-client" , $client_del_sql);
    			}
    
    		}
    	}
    
        if($eClient){
            $rData['rStatus'] = 101;
            $rData['error'] = '部分药店修改失败!';
            $rData['rData'] = $eClient;
        }

    	$log->logInfo('updateDealers return ',$rData);
    	if(!$param['debug']){
    		unset($rData['debug']);
    	}
    	return $rData;
    }
    
    /**
     *处理药厂名称[先简单粗暴的处理下]
     *@author wanjun
     *@todo 现在处理的情况是ERP中没有特定维护药厂，直接与商品资料传过来的。需要处理ERP中已维护好的资料 
     */
    private function _toBulidBrand($brandName = ''){
    	global $db,$log, $param;
    	
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
    	$csql = "select BrandName,BrandPinYin,Logo from ".$sdatabase.DATATABLE."_order_brand where and BrandName='".$brandName."' limit 1";
    	$binfo = $db->get_row($csql);
    	
    	
    	include_once (SITE_ROOT_PATH."/class/letter.class.php");
    	$letter = new letter();
    	$pinyima = $letter->C($brandName);
    	
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
        global $db,$log;
        $rData = array(
            'rStatus'=>100,
        	'message' => '添加商品档案执行完毕'
        );
        
        //添加失败的商品编码
        $eCoding = array();
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
            $log->logInfo('addProduct param ',$param);
        }else{
            $cidarr = self::getCompanyInfo($param['sKey'],$param['debug']);
            if($cidarr['rStatus']==101){
                $log->logInfo('addProduct param ',$param);
                return $cidarr;
            }
            $log->logInfo('addProduct param ',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $body = $param['body'] = json_decode(json_encode($param['body']),true);
            if(count($body)>1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '单次同步数据只能在1000条以内';
                $log->logInfo(__METHOD__. ' return',$rData);
                return $rData;
            }
            if($param['count']!=count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
                $log->logInfo(__METHOD__.' return ',$rData);
                return $rData;
            }
            $rData['debug']['body'] = $body;
            include_once (SITE_ROOT_PATH."/class/letter.class.php");
            $letter = new letter();
            $editBody = array();
            $ctype = array(
            		'default' => 0,		//默认
            		'presell' => 1,		//预售
            		'special' => 2,		//特价
            		'contsell' => 3,	//控销
            		'hot' => 4,			//热销
            		'gift' => 8,		//赠品
            		'shortage' => 9		//缺货
            );
            foreach($body as $key=>$val){
                
            	$val['name'] = trim($val['name']);
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
                	$rData['debug'][$key]['upguid'] = $outSql;
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

                $rData['debug'][$key]['idx'] = $idx_sql;
                if(false!==$db->query($idx_sql)){
                    $inid = $db->insert_id;
                    $now = time();
                    $userInfo = $db->get_row("SELECT UserName FROM ".DB_DATABASEU.DATATABLE."_order_user WHERE UserCompany=".$cid);
                    $val['contentCreateUser'] = $val['contentEditUser'] = $userInfo['UserName'];
                    
                    //这里自定义字段的修改，只为CID：515 的客户服务
                    //增加商品自定义字段[FieldContent] by wanjun @20151029
//                     if($cid == 515){
//                         $_1_sql = "INSERT INTO ".$sdatabase.DATATABLE."_order_content_1(ContentIndexID,CompanyID,ContentCreateDate,ContentEditDate,
//                             ContentCreateUser,ContentEditUser,ContentLink,ContentKeywords,Content,ContentPoint,Package,FieldContent)VALUES(
//                               {$inid},{$cid},{$now},{$now},'".$val['contentCreateUser']."','".$val['contentEditUser']."','','{$val['contentKeywords']}','{$val['content']}',{$val['contentPoint']},{$val['package']},'".($val['userdefined'] ? serialize($val['userdefined']) : '')."')";
//                     }else{
                        $_1_sql = "INSERT INTO ".$sdatabase.DATATABLE."_order_content_1(ContentIndexID,CompanyID,ContentCreateDate,ContentEditDate,
                            ContentCreateUser,ContentEditUser,ContentLink,ContentKeywords,Content,ContentPoint,Package)VALUES(
                              {$inid},{$cid},{$now},{$now},'".$val['contentCreateUser']."','".$val['contentEditUser']."','','{$val['contentKeywords']}','{$val['content']}',{$val['contentPoint']},{$val['package']})";
//                     }
                    
                    if(false === $db->query($_1_sql) ) {
                        wlog("新增商品-content_1",$_1_sql);
                    }
                    $rData['debug'][$key]['content'] = $_1_sql;

                    //插入空主库存
                    $db->query("INSERT INTO ".$sdatabase.DATATABLE."_order_number (CompanyID,ContentID,OrderNumber,ContentNumber)VALUES({$cid},{$inid},0,0)");
                    $rData['debug'][$key]['lib']['man'] = $db->last_query;

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
                        $rData['debug'][$key]['lib']['son'] = $son;
                        if($son){
                            $db->query($sql_header.implode(',',$son));
                        }
                    }

                }else{
                    $eCoding[] = $val;
                    wlog("新增商品失败-index", $idx_sql);
                }
            }
            
        }
        
       if($eCoding){
           $rData['rStatus'] = 101;
           $rData['message'] = '部分商品添加未成功!';
           $rData['rData'] = $eCoding;
       }
//         //统一返回添加成功的状态
//         if($eCoding){
//             $rData['rStatus'] = 100;
//             $rData['message'] = '商品已添加，核对部分商品档案';
//             $rData['rData'] = $eCoding;
//         }

        $log->logInfo('addProduct return ',$rData);
        
        if(!$param['debug']){
        	unset($rData['debug']);
        }
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
        global $db,$log;
        $rData = array('rStatus'=>100, 'message' => '维护商品档案执行完毕');
        $eCoding = array();
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
            $log->logInfo('updateProduct param ',$param);
        }else{
            $cidarr = self::getCompanyInfo($param['sKey'],$param['debug']);
            if($cidarr['rStatus']==101){
                $log->logInfo('updateProduct param ',$param);
                return $cidarr;
            }
            $log->logInfo('updateProduct param ',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $body = $param['body'] = json_decode(json_encode($param['body']),true);
            if(count($body)>1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '单次同步数据只能在1000条以内';
                $log->logInfo(__METHOD__. ' return',$rData);
                return $rData;
            }
            if($param['count']!=count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
                $log->logInfo(__METHOD__.' return ',$rData);
                return $rData;
            }
            $rData['debug']['body'] = $body;
            include_once (SITE_ROOT_PATH."/class/letter.class.php");
            $letter = new letter();
            //del_product
            $del_product = array();//待删除的商品GUID数组
            $not_Exi = array();//GUID不存在的数据
            foreach($body as $key=>$val){
            	
            	$val['status'] = strtoupper($val['status']);
            	$val['status'] = $val['status'] == 'D' ? 'D' : '';
                if($val['status'] == 'D') {
                    $del_product[] = $val['guid'];
                    continue;
                }
                
                $cSql      = "SELECT ID FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} AND GUID='{$val['guid']}'";
                $contentID = $db->get_var($cSql);
                $rData['debug'][$key]['cGuid'] = $cSql;
                if(!$contentID) {
                	$val['message'] = 'GUID不存在，请核查已同步的商品';
                	$not_Exi[] = $val;
                	continue;
                }
                
                $setInfo = array();
                $color   = array();
                $spec    = array();

                if(isset($val['name'])){
                    $setInfo[] = "Pinyi='".$letter->C($val['name'])."'";
                    $val['name'] = htmlspecialchars($val['name']);
                    $setInfo[] = "Name='".$val['name']."'";
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
               if(isset($val['price1'])){
                   $setInfo[] = "Price1=".floatval($val['price1']);
               }
               if(isset($val['price2'])){
                   $setInfo[] = "Price2=".floatval($val['price2']);
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
//                 if(isset($val['brandID'])){
//                     $setInfo[] = "BrandID='".intval($val['brandID'])."'";
//                 }
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
                $rData['debug'][$key]['index'] = $u_idx_sql;
                $rst = $db->query($u_idx_sql);
                if($rst===false){
                    $eCoding[] = $val;
                    wlog("更新商品失败-index",$u_idx_sql);
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
                        $specAll = $spec ? $spec : array_map('CSEncode',array('统一'));
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

                      if(false === $db->query($up_sql)) {
                        wlog("物理删除商品数据失败 - content_1" , $up_sql);
                      }
                    //$log->logInfo("del_index - sql : " , $del_sql);
                    //$log->logInfo("del_content - sql : " , $del_sql_1);
                }
            }
        }
        $log->logInfo('updateProduct return ',$rData);
        if(!$param['debug']){
            unset($rData['debug']);
        }
       if($eCoding || $not_Exi){
           $rData['rStatus'] = 101;
           $rData['message'] = '部分商品未修改成功!';
           $rData['rData'] = array_filter(array_merge(array('eCoding' => $eCoding), array('emptyguid' => $not_Exi)));
       }

        return $rData;
    }
    
    /**
     * @desc 批量修改商品，API独立客户接口
     * @param array $param (sKey,count,body)
     * body (siteID,name,coding,units,price1,price2,barcode,model,color,specification)
     * @return array $rData
     */
    public function updateProduct_static($param){
    	global $db,$log;
    	$rData = array('rStatus'=>100);
    	$eCoding = array();
    	$param = is_object($param) ? (array)$param : $param;
    	
    	if(empty($param['sKey'])){
    		$rData['rStatus'] = 101;
    		$rData['error'] = '验证key必须!';
    		$log->logInfo('updateProduct param ',$param);
    	}else{
    		$cidarr = self::getCompanyInfo($param['sKey'],$param['debug']);
    		if($cidarr['rStatus']==101){
    			$log->logInfo('updateProduct param ',$param);
    			return $cidarr;
    		}
    		$log->logInfo('updateProduct param ',$param);
    		$cid = $cidarr['CompanyID'];
    		$sdatabase = $cidarr['Database'];
    		$body = $param['body'] = json_decode(json_encode($param['body']),true);
    		if(count($body)>1000){
    			$rData['rStatus'] = 101;
    			$rData['error'] = '数据只能在1000条以内!';
    			$log->logInfo(__METHOD__. ' return',$rData);
    			return $rData;
    		}
    		if($param['count']!=count($body)){
    			$rData['rStatus'] = 101;
    			$rData['error'] = '数据传输错误!';
    			$log->logInfo(__METHOD__.' return ',$rData);
    			return $rData;
    		}
    		$rData['debug']['body'] = $body;
    		include_once (SITE_ROOT_PATH."/class/letter.class.php");
    		$letter = new letter();
    		//del_product
    		$del_product = array();//待删除的商品GUID数组
    		foreach($body as $key=>$val){
    			if($val['status'] == 'F') {
    				$del_product[] = $val['guid'];
    				continue;
    			}
    			//$contentID = $db->get_var("SELECT ID FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} AND Coding='{$val['coding']}'");
    			$contentID = $db->get_var("SELECT ID FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} AND GUID='{$val['guid']}'");
    
    			$setInfo = array();
    			$color = array();
    			$spec = array();
                if(isset($val['siteID'])){
                    //获取SiteID
                    //2015年7月28日16时 接口中传递的分类ID直接为DHB需要的分类ID(不再转换)
                    //$siteID = $db->get_var("SELECT TrueSiteID FROM ".$sdatabase.DATATABLE."_api_site WHERE SiteID='".$val['siteID']."'");
                    //$val['siteID'] = $siteID ? $siteID : 0;
                    $val['siteID'] = (int)$val['siteID'];
                    $setInfo[] = "SiteID='".((int)$val['siteID'])."'";
                }
                if(isset($val['name'])){
                    $setInfo[] = "Name='".$val['name']."'";
                    $setInfo[] = "Pinyi='".$letter->C($val['name'])."'";
                }
                if(isset($val['units'])){
                    $setInfo[] = "Units='".$val['units']."'";
                }
                if( $val['price1'] !== null){
                    $setInfo[] = "Price1=". (empty($val['price1']) ? 0.00 : floatval($val['price1']));
                }
                if($val['price2'] !== null){
                    $setInfo[] = "Price2=". (empty($val['price2']) ? 0.00 : floatval($val['price2']));
                }
                if(isset($val['barcode'])){
                    $setInfo[] = "Barcode='".$val['barcode']."'";
                }
                if(isset($val['model'])){
                    $setInfo[] = "Model='".$val['model']."'";
                }
                if(isset($val['color']) && !empty($val['color'])){
                    $setInfo[] = "Color='".$val['Color']."'";
                    $color = array('CSEncode',explode(',',$val['Color']));
                    //将颜色添加到规格颜色库
                    $this->specification(explode(',',$val['Color']),'Color',$cid,$sdatabase);
                }
                if(isset($val['specification']) && !empty($val['specification'])){
                    $setInfo[] = "Specification='".$val['specification']."'";
                    $spec = array('CSEncode',explode(',',$val['specification']));
                    //将规格添加到规格颜色库
                    $this->specification(explode(',',$val['specification']),'Specification',$cid,$sdatabase);
                }
                if(isset($val['coding'])){
                    $setInfo[] = "Coding='".$val['coding']."'";
                }
    
    			$u_idx_sql = "UPDATE ".$sdatabase.DATATABLE."_order_content_index SET ".implode(",",$setInfo)." WHERE CompanyID={$cid} AND GUID='{$val['guid']}' limit 1";
    			$rData['debug'][$key]['index'] = $u_idx_sql;
    			$rst = $db->query($u_idx_sql);
    			if($rst===false){
    				$eCoding[] = $val;
    				wlog("更新商品失败-index",$u_idx_sql);
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
    					$specAll = $spec ? $spec : array_map('CSEncode',array('统一'));
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
    				$del_sql = "DELETE FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} AND GUID IN(".$guid_str.")";
    				$del_sql_1 = "DELETE FROM ".$sdatabase.DATATABLE."_order_content_1 WHERE CompanyID={$cid} AND ContentID IN(".implode(",",$id_arr).")";
    				//删除
    				if(false === $db->query($del_sql) ) {
    					wlog("删除商品数据失败 - index",$del_sql);
    				}
    				if(false === $db->query($del_sql_1)) {
    					wlog("删除商品数据失败 - content_1" , $del_sql_1);
    				}
    				//$log->logInfo("del_index - sql : " , $del_sql);
    				//$log->logInfo("del_content - sql : " , $del_sql_1);
    			}
    		}
    	}
    	$log->logInfo('updateProduct return ',$rData);
    	if(!$param['debug']){
    		unset($rData['debug']);
    	}
        if($eCoding){
           $rData['rStatus'] = 101;
           $rData['error'] = '部分商品未修改成功!';
           $rData['rData'] = $eCoding;
        }
    	return $rData;
    }


    /**
     * @desc 检查发货单数据是否有异常
     * @param $param (sKey,clientNO,consignmentOrder,consignmentNO,...
     * array body (coding,num,color,spec,conType)
     * @return array $rData
     */
    private function checkConsignment($param) {
        global $db,$log;
        $rData = array(
            'rStatus' => 100,
        );
        $param = json_decode(json_encode($param),true);
        $body = $param['body'];
        //验证基本信息
        if(empty($param['sKey']) || empty($param['consignmentMan']) ||  empty($param['body'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
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
        $param['consignmentOrder'] = str_replace('ETONG','',$param['consignmentOrder']);
        $order_sql = "SELECT OrderID,OrderUserID,OrderReceiveCompany,OrderReceiveName,OrderReceivePhone,OrderReceiveAdd FROM ".$sdatabase.DATATABLE."_order_orderinfo WHERE OrderSN='".$param['consignmentOrder']."' AND OrderCompany={$cid} Limit 0,1";
        $orderInfo = $db->get_row($order_sql);
        if(empty($orderInfo)){
            $rData['message'] = 'ETONG'.$param['consignmentOrder'].'订单不存在!';
            $rData['rStatus'] = 101;
            return $rData;
        }

        $orderID = $orderInfo['OrderID'];
        //验证发货单中包含的发货商品是否存在于平台中
        $eGoods = $nGoods = array();
        foreach($body as $i=>$val) {
            $cInfo = $db->get_row("SELECT ID,Coding,GUID,Name FROM ".$sdatabase.DATATABLE."_order_content_index WHERE CompanyID={$cid} AND GUID='{$val['guid']}' limit 0,1" );
            if(empty($cInfo)) {
                wlog("发货单中包含不存在于医统中的商品" , $db->last_query);
                $nGoods[] = $val['guid'];
                continue;
            }
            $cartInfo = array();
            $val['conType'] = empty($val['conType']) ? 'c' : strtolower($val['conType']);
            $val['color']   = htmlentities($val['color'],ENT_COMPAT,"UTF-8");
            $val['spec']    = htmlentities($val['spec'], ENT_COMPAT, "UTF-8");
            if($val['conType']=='c'){
                $cartInfo = $db->get_var("SELECT COUNT(*) AS Total FROM ".$sdatabase.DATATABLE."_order_cart WHERE CompanyID={$cid} AND OrderID={$orderID} AND ContentID={$cInfo['ID']} AND ContentColor='{$val['color']}' AND ContentSpecification='{$val['spec']}' LIMIT 0,1");
                $rData['debug']['c'][$i] = $db->last_query;
            } else if($val['conType']=='g'){
                $cartInfo = $db->get_var("SELECT COUNT(*) AS Total FROM ".$sdatabase.DATATABLE."_order_cart_gifts WHERE CompanyID = {$cid} AND OrderID={$orderID} AND ContentID={$cInfo['ID']} AND ContentColor='{$val['color']}' AND ContentSpecification='{$val['spec']}' LIMIT 0,1");
                $rData['debug']['g'][$i] = $db->last_query;
            }
            $rData['debug']['cart'][$i] = $cartInfo;
            
            if(empty($cartInfo)) {
                wlog("发货订单中不包含商品" , $db->last_query);
                $eGoods[] = trim($cInfo['Name']).'(内码:'.$val['guid'].',颜色:'.$val['color'].',规格:'.$val['spec'].')';
            }
        }

        
        
        if(empty($param['debug'])) unset($rData['debug']);
        
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
        global $db,$log;
        $rData = array(
            'rStatus'=>100,
        	'message'=> '发货单同步完成'
        );
        $param = is_object($param) ? (array)$param : $param;
        $param['conType'] = strtolower($param['conType']);
        
        $check_result = $this->checkConsignment($param);//验证发货数据
        $log->logInfo("验证发货数据结果" , $check_result);//记录验证结果

        if($check_result['rStatus'] == 101) {
            $log->logInfo(__METHOD__.' Param ', $param);
            //验证发货数据未通过
            return $check_result;
        }
        if(true){
            $cidarr = self::getCompanyInfo($param['sKey']);
            $log->logInfo(__METHOD__.' Param ',$param);
            if($cidarr['rStatus']==101){
                return $cidarr;
            }
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            
            //查询订单信息获取订单ID,订单用户,收货人信息
            $param['consignmentOrder'] = str_replace('ETONG','',$param['consignmentOrder']);
            $order_sql = "SELECT OrderID,OrderUserID,OrderReceiveCompany,OrderReceiveName,OrderReceivePhone,OrderReceiveAdd,OrderSN,OrderSendStatus FROM ".$sdatabase.DATATABLE."_order_orderinfo WHERE OrderSN='".$param['consignmentOrder']."' AND OrderCompany={$cid} Limit 0,1";
            $orderInfo = $db->get_row($order_sql);
            
            //当前是一个订单发一次货
            if(!(in_array($orderInfo['OrderSendStatus'], array(0,1)))){
            	
            	$rData['rStatus'] = 101;
            	$rData['message'] = '该采购单已发货，本次配送单平台未接收';
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
                    $rData['debug']['content'][] = $db->last_query;
                    $cartInfo = array();
                    $val['conType'] = empty($val['conType']) ? 'c' : strtolower($val['conType']);
                    $val['color']   = htmlentities($val['color'],ENT_COMPAT,"UTF-8");
                    $val['spec']    = htmlentities($val['spec'], ENT_COMPAT, "UTF-8");
                    if($val['conType']=='c'){
                        $cartInfo = $db->get_row("SELECT ID,ContentID,ContentNumber,ContentSend,ContentColor,ContentSpecification,ContentPrice,ContentPercent FROM ".$sdatabase.DATATABLE."_order_cart WHERE CompanyID={$cid} AND OrderID={$orderID} AND ContentID={$cInfo['ID']} AND ContentColor='{$val['color']}' AND ContentSpecification='{$val['spec']}' LIMIT 0,1");
                    }else if($val['conType']=='g'){
                        $cartInfo = $db->get_row("SELECT ID,ContentID,ContentNumber,ContentSend,ContentColor,ContentSpecification FROM ".$sdatabase.DATATABLE."_order_cart_gifts WHERE CompanyID = {$cid} AND OrderID={$orderID} AND ContentID={$cInfo['ID']} AND ContentColor='{$val['color']}' AND ContentSpecification='{$val['spec']}' LIMIT 0,1");
                    }
                    
                    //校验发货数量，不能大于ETONG中的订购数
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
                    $rData['debug']['out_sql'][] = $outSql;
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
                            if(!$param['debug']){
                                unset($rData['debug']);
                            }
                            $rData['rStatus'] = 101;
                            $rData['message'] = '更新发货数不成功!';
                            $log->logInfo(__METHOD__.' return',$rData);
                            return $rData;
                        }
                    }else{
                        $log->logInfo(__METHOD__.' return',$rData);
                        if(!$param['debug']){
                            unset($rData['debug']);
                        }
                        $out_cnt = $db->get_var("SELECT COUNT(*) as Total FROM ".$sdatabase.DATATABLE."_order_out_library WHERE CompanyID={$cid} AND ConsignmentID={$consignmentID} LIMIT 1");
                        if((int)$out_cnt == 0){
                            $db->query("DELETE FROM ".$sdatabase.DATATABLE."_order_consignment WHERE ConsignmentID=".$consignmentID);
                        }

                        $rData['rStatus'] = 101;
                        $rData['message'] = '保存不成功!';
                        return $rData;
                    }
                }

                //处理订单状态&发货状态
                $sendline = $db->get_var("select count(*) as allrow from ".$sdatabase.DATATABLE."_order_cart where ContentSend < ContentNumber and CompanyID = ".$cid." and OrderID=".$orderID."");
                $sendlineg = $db->get_var("select count(*) as allrow from ".$sdatabase.DATATABLE."_order_cart_gifts where ContentSend < ContentNumber and CompanyID = ".$cid." and OrderID=".$orderID."");
                $orderSendStatus = ( $sendline + $sendlineg ) > 0 ? 3 : 2; //3=>未发完,2=>已发完
                $db->query("UPDATE ".$sdatabase.DATATABLE."_order_orderinfo set OrderSendStatus={$orderSendStatus} WHERE OrderID={$orderID} AND OrderCompany={$cid}");
                $rData['debug']['orderSendStatus'] = $db->last_query;
                $log->logInfo('addConsignment orderSendStatus',$db->last_query);
                //将当前订单由备货中改为已发货状态
                $db->query("UPDATE ".$sdatabase.DATATABLE."_order_orderinfo SET OrderStatus=2 WHERE OrderID={$orderID} AND OrderCompany={$cid} AND OrderStatus=1");
                $rData['debug']['orderStatus'] = $db->last_query;
                $log->logInfo('addConsignment orderStatus',$db->last_query);
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
                wlog("发货单保存失败" , $consignment_sql);
            }

            $rData['debug']['order_sql'] = $order_sql;
            $rData['debug']['consignment'] = $consignment_sql;
        }

        
        if($moreSend) $rData['rData']['moreSend'] = $moreSend;
        
        $log->logInfo('addConsignment return',$rData);
        
        if(!$param['debug']){
        	SYSTEM_DEBUG && $log->logInfo(__METHOD__.' system_debug ', $rData['debug']);
        	unset($rData['debug']);
        }
        
        return $rData;
    }

    /**
     * 处理其他款项(退款处理)
     * @author wanjun
     * @param $cid int 商业ID
     */
    private function _bulidBill($cid = 0){
    	global $db,$log;
    	
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
        global $db,$log;
        $rData = array(
            'rStatus'=>100,
        	'message' => '库存同步执行完毕'
        );
        $eProduct = array();
		$nullProduct = 0; // 记录一下传递过来的空值数量 by 小牛New 2015-11-17
		$emptyGuid = array();
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '参数错误!';
            $log->logInfo(__METHOD__.' param ', $param);
        }else{
            $fp = array('+','/','=','_');
            $rp = array('-','|','DHB',' ');
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo(__METHOD__.' param ', $param);
                return $cidarr;
            }
            $log->logInfo(__METHOD__.' param ', $param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $body = $param['body'] = json_decode(json_encode($param['body']),true);
            if(count($body)>1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据只能在1000条以内';
                $log->logInfo(__METHOD__. ' return',$rData);
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
				$rData['debug']['ckproduct'][$val['guid']] = $db->last_query;
				
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
                    $rData['debug']['freeze_cart'][$val['guid']] = $freeze_sql;

                    $freeze_gift_sql = "SELECT SUM(ContentNumber - ContentSend) AS freeze FROM ".$sdatabase.DATATABLE."_order_cart_gifts AS c
                                    LEFT JOIN ".$sdatabase.DATATABLE."_order_orderinfo AS o ON o.OrderID = c.OrderID
                                    WHERE c.CompanyID = {$cid} AND o.OrderCompany = {$cid} AND c.ContentID = {$cInfo['ID']} AND o.OrderSendStatus IN(0,1,3) AND c.ContentColor = '{$val['color']}' AND c.ContentSpecification='{$val['spec']}'";
                    //赠品未发数
                    $freeze_gift = $db->get_var($freeze_gift_sql);
                    $rData['debug']['freeze_gift'][$val['guid']] = $freeze_gift_sql;
                    
                    //当前商品可用库存
                    $allow = max(0,$val['num']-$freeze-$freeze_gift);
                    $lsql = "UPDATE ".$sdatabase.DATATABLE."_order_inventory_number SET OrderNumber={$allow},ContentNumber={$val['num']} WHERE CompanyID={$cid} AND ContentID={$cInfo['ID']} AND ContentColor='{$color}' AND ContentSpec='{$spec}'";
                    $db->query($lsql);
                    $rData['debug']['update_inventory'][$val['guid']] = $lsql;
                    //操作主库存
                    $lib = $db->get_row("SELECT SUM(OrderNumber) as OrderNumber,SUM(ContentNumber) as ContentNumber FROM ".$sdatabase.DATATABLE."_order_inventory_number WHERE ContentID={$cInfo['ID']} AND CompanyID={$cid}");
                    
                    $db->query("UPDATE ".$sdatabase.DATATABLE."_order_number SET OrderNumber={$lib['OrderNumber']},ContentNumber={$lib['ContentNumber']} WHERE CompanyID={$cid} AND ContentID={$cInfo['ID']}");
                    
                    $rData['debug']['update_stock'][$val['guid']] = $db->last_query;
                }else{
                    //操作主库存
                	//买品未发数量
                    $freeze_sql = "SELECT SUM(ContentNumber - ContentSend) AS freeze FROM ".$sdatabase.DATATABLE."_order_cart AS c
                                    LEFT JOIN ".$sdatabase.DATATABLE."_order_orderinfo AS o ON o.OrderID = c.OrderID
                                    WHERE c.CompanyID = {$cid} AND o.OrderCompany = {$cid} AND c.ContentID = {$cInfo['ID']} AND o.OrderSendStatus IN(0,1,3)";
                    
                    $freeze = $db->get_var($freeze_sql);
                    $rData['debug']['freeze_cart'][$val['guid']] = $freeze_sql;
                    
                    //赠品未发数量
                    $freeze_gift_sql = "SELECT SUM(ContentNumber - ContentSend) AS freeze FROM ".$sdatabase.DATATABLE."_order_cart_gifts AS c
                                    LEFT JOIN ".$sdatabase.DATATABLE."_order_orderinfo AS o ON o.OrderID = c.OrderID
                                    WHERE c.CompanyID = {$cid} AND o.OrderCompany = {$cid} AND c.ContentID = {$cInfo['ID']} AND o.OrderSendStatus IN(0,1,3)";
                    
                    $freeze_gift = $db->get_var($freeze_gift_sql);
                    $rData['debug']['freeze_gift'][$val['guid']] = $freeze_gift_sql;
                    //可用库存
                    $allow = max(0,$val['num']-$freeze-$freeze_gift); //实际库存减去商品未发数再减去赠品未发数
                    $sql = "UPDATE ".$sdatabase.DATATABLE."_order_number SET OrderNumber={$allow},ContentNumber={$val['num']} WHERE ContentID={$cInfo['ID']} AND CompanyID={$cid}";
                    $rData['debug']['update_stock'][$val['guid']] = $sql;
                    $db->query($sql);
                }

            }
        }

        if(!$param['debug']){
        	SYSTEM_DEBUG && $log->logInfo(__METHOD__.' system_debug ', $rData['debug']);
            unset($rData['debug']);
        }
        if(count($eProduct)>0 || $nullProduct){
            $rData['rStatus'] = 101;
            $rData['message'] = '部分商品库存同步失败!';
            $rData['rData'] = $eProduct;
            $rData['rData'] = array_filter(array_merge(array('eCoding' => $eProduct), array('emptyguid' => $emptyGuid)));
        }

        $log->logInfo('stock return ',$rData);
        return $rData;
    }
    
    /**
     * @desc 库存同步，只有丰达凯莱的需求
     * @param array $param(sKey,body)
     * body (coding,num,spec,color)
     * @return mixed
     */
    public function stock_inca($param){
    	global $db,$log;
    	$rData = array(
    			'rStatus'=>100,
    			'message' => '库存同步执行完毕'
    	);
    	$eProduct = array();
    	$nullProduct = 0; // 记录一下传递过来的空值数量 by 小牛New 2015-11-17
    	$emptyGuid = array();
    	$param = is_object($param) ? (array)$param : $param;
    	if(empty($param['sKey'])){
    		$rData['rStatus'] = 101;
    		$rData['message'] = '参数错误!';
    		$log->logInfo(__METHOD__.'param ', $param);
    	}else{
    		$fp = array('+','/','=','_');
    		$rp = array('-','|','DHB',' ');
    		$cidarr = self::getCompanyInfo($param['sKey']);
    		if($cidarr['rStatus']==101){
    			$log->logInfo(__METHOD__.' param ', $param);
    			return $cidarr;
    		}
    		$log->logInfo(__METHOD__.' param ', $param);
    		$cid = $cidarr['CompanyID'];
    		$sdatabase = $cidarr['Database'];
    		$body = $param['body'] = json_decode(json_encode($param['body']),true);
    		if(count($body)>1000){
    			$rData['rStatus'] = 101;
    			$rData['message'] = '数据只能在1000条以内';
    			$log->logInfo(__METHOD__. ' return',$rData);
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
    			$rData['debug']['ckproduct'][$val['guid']] = $db->last_query;
    
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
//     			$rData['debug'][] = $freeze_sql;
    			
//     			//赠品未发数量
//     			$freeze_gift_sql = "SELECT SUM(ContentNumber - ContentSend) AS freeze FROM ".$sdatabase.DATATABLE."_order_cart_gifts AS c
//                                       LEFT JOIN ".$sdatabase.DATATABLE."_order_orderinfo AS o ON o.OrderID = c.OrderID
//                                     WHERE c.CompanyID = {$cid} AND o.OrderCompany = {$cid} AND c.ContentID = {$cInfo['ID']} AND o.OrderSendStatus IN(0,1,3)";
//     			$freeze_gift = $db->get_var($freeze_gift_sql);
//     			$rData['debug'][] = $freeze_gift_sql;
    			
    			//可用库存
//     			$allow = $freeze+$freeze_gift; //实际库存减去商品未发数再减去赠品未发数
//这里会增大库存误差
    			$sql = "UPDATE ".$sdatabase.DATATABLE."_order_number SET OrderNumber=(CASE WHEN (OrderNumber-".$val['num'].") < 0 THEN 0 ELSE (OrderNumber-".$val['num'].") END),
				ContentNumber=(CASE WHEN (ContentNumber-".$val['num'].") < 0 THEN 0 ELSE (ContentNumber-".$val['num'].") END) WHERE ContentID={$cInfo['ID']} AND CompanyID={$cid}";
    			$rData['debug']['update_stock'][$val['guid']] = $sql;
    			$db->query($sql);
    
    		}
    	}
    
    	if(!$param['debug']){
    		SYSTEM_DEBUG && $log->logInfo(__METHOD__.' system_debug ', $rData['debug']);
    		unset($rData['debug']);
    	}
    	if(count($eProduct)>0 || $nullProduct){
    		$rData['rStatus'] = 101;
    		$rData['message'] = '部分商品线下发货单同步失败!';
    		$rData['rData'] = $eProduct;
    		$rData['rData'] = array_filter(array_merge(array('eCoding' => $eProduct), array('emptyguid' => $emptyGuid)));
    	}
    
    	$log->logInfo(__METHOD__.' return ', $rData);
    	return $rData;
    }

    /**
     * @desc 同步分类维护
     * @param array $param (sKey,count,body)
     * body array(
            array('ParentID'=>0,'SiteName'=>'中性笔','SiteID'=>1),
     * )
     * body数据需排序被依赖(父ID)的数据排在前面
     * @return array $rData
     */
    public function site($param){
        global $db,$log;
        
        $rData = array('rStatus'=>100, 'message' => '添加商品分类执行完毕');
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
            $log->logInfo("site param ",$param);
        }elseif(false && empty($param['body'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '未传入分类信息';
            $log->logInfo("site param ",$param);
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo("site param ",$param);
                return $cidarr;
            }
            $log->logInfo("site param ",$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $body = $param['body'] = json_decode(json_encode($param['body']),true);
            if($param['count']!=count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
                $log->logInfo(__METHOD__.' return ',$rData);
                return $rData;
            }
            include_once (SITE_ROOT_PATH."/class/letter.class.php");
            $letter = new letter();
            $SiteIDS = array();
            $list = array();
            
            foreach($body as $val){
                $val = array_map('trim', $val);
                $list[$val['siteID']] = $val;
            }
            include_once(SITE_ROOT_PATH."/class/tree.class.php");
            $tree = new Tree($list,'parentID','Name','siteID');

            $body = $tree->getArray();
            $body = $body ? $body : array();
            foreach($body as $key=>$val){
                $SiteIDS[] = $val['siteID'];
                $sign = $db->get_row("SELECT * FROM ".$sdatabase.DATATABLE."_api_site WHERE SiteID='{$val['siteID']}' AND CompanyID={$cid}");
                if($sign) {
                    $tsite = $db->get_row("SELECT * FROM ".$sdatabase.DATATABLE."_order_site WHERE CompanyID={$cid} AND SiteID={$sign['TrueSiteID']}");
                    if(empty($tsite)) {
                        $db->query("DELETE FROM ".$sdatabase.DATATABLE."_api_site WHERE SiteID='{$val['siteID']}' AND CompanyID={$cid} LIMIT 1");
                        $sign = false;
                    }
                }
                if($sign){
                    if($sign['ParentID']==$val['parentID'] && $sign['Name'] == $val['siteName']){
                        //分类信息无改动
                        continue;
                    }
                    //更改接口数据
                    $db->query("UPDATE ".$sdatabase.DATATABLE."_api_site SET ParentID='{$val['parentID']}',Name='{$val['siteName']}' WHERE CompanyID={$cid} AND SiteID='{$val['siteID']}'");
                    $log->logInfo("更新api-site" , $db->last_query);
                    $ParentID = "ParentID";
                    $SiteNO = "SiteNO";
                    if($sign['ParentID']!=$val['parentID']){
                        $ParentID = $db->get_var("SELECT TrueSiteID FROM ".$sdatabase.DATATABLE."_api_site WHERE CompanyID={$cid} AND SiteID='{$val['parentID']}'");
                        $SiteNO = $db->get_var("SELECT SiteNO FROM ".$sdatabase.DATATABLE."_order_site WHERE CompanyID={$cid} AND SiteID={$ParentID}");
                        $SiteNO .= $sign['TrueSiteID'].".";
                    }
                    //更改订货宝数据
                    $db->query("UPDATE ".$sdatabase.DATATABLE."_order_site SET ParentID={$ParentID},SiteNO=".($SiteNO=='SiteNO' ? 'SiteNO' : "'{$SiteNO}'").",SiteName='{$val['siteName']}',SitePinyi='".$letter->C($val['siteName'])."' WHERE CompanyID={$cid} AND SiteID={$sign['TrueSiteID']}");

                }else{
                    $sql_api = "INSERT INTO ".$sdatabase.DATATABLE."_api_site (SiteID,ParentID,Name,CompanyID,TrueSiteID) VALUES ('{$val['siteID']}','{$val['parentID']}','{$val['siteName']}',{$cid},0)";
                    $rData['debug']['_api_site']['insert'][] = $sql_api;
                    $db->query($sql_api); 

                    if(strlen($val['parentID'])>1 || $val['parentID']!=0){
                        $parentID = $db->get_var("SELECT TrueSiteID FROM ".$sdatabase.DATATABLE."_api_site WHERE SiteID='{$val['parentID']}' AND CompanyID={$cid}");
                        $rData['debug']['ps'][] = $db->last_query;
                    }else{
                        $parentID = 0;
                    }
                    $val['Pinyi'] = $letter->C($val['SiteName']);
                    $sql = "INSERT INTO ".$sdatabase.DATATABLE."_order_site (CompanyID,ParentID,SiteName,SitePinyi,Content,Disabled) VALUES ({$cid},{$parentID},'{$val['siteName']}','{$val['Pinyi']}','',0)";
                    $rData['debug']['_site'][] = $sql;
                    $db->query($sql);
                    $siteID = $db->insert_id;
                    $db->query("UPDATE ".$sdatabase.DATATABLE."_api_site SET TrueSiteID={$siteID} WHERE SiteID='{$val['siteID']}' AND CompanyID={$cid}");
                    $siteNO = "0.".$siteID.'.';
                    if(strlen($val['parentID'])>1 || $val['parentID']!=0){
                        $siteNO = $db->get_var("SELECT SiteNO FROM ".$sdatabase.DATATABLE."_order_site WHERE SiteID={$parentID} AND CompanyID={$cid}");
                        $siteNO .= $siteID.'.';
                    }
                    $db->query("UPDATE ".$sdatabase.DATATABLE."_order_site SET SiteNO='{$siteNO}' WHERE CompanyID={$cid} AND SiteID={$siteID}");
                    $rData['debug']['_api_site']['update'][] = $db->last_query;
                }
            }

            $All = $db->get_col("SELECT SiteID FROM ".$sdatabase.DATATABLE."_api_site WHERE CompanyID={$cid}");
            $delIDS = array_diff($All,$SiteIDS);
            $rData['debug']['all'] = $All;
            $rData['debug']['del'] = $delIDS;
            if(count($delIDS)>0){
                $delIDS = array_map("strQuote",$delIDS);
                $trueSiteIDS = $db->get_col("SELECT TrueSiteID FROM ".$sdatabase.DATATABLE."_api_site WHERE CompanyID={$cid} AND SiteID IN(".implode(',',$delIDS).")");
                $db->query("DELETE FROM ".$sdatabase.DATATABLE."_order_site WHERE CompanyID={$cid} AND SiteID IN(".implode(',',$trueSiteIDS).")");
                $rData['debug']['del_site'] = $db->last_query;
                $db->query("DELETE FROM ".$sdatabase.DATATABLE."_api_site WHERE CompanyID={$cid} AND SiteID IN(".implode(',',$delIDS).")");
                $rData['debug']['del_api_site'] = $db->last_query;
            }

        }
        $log->logInfo("site return ",$rData);
        if(!$param['debug']){
            unset($rData['debug']);
        }
        return $rData;
    }


    /**
     * @desc ERP获取DHB分类信息
     * @param $param (sKey)
     * @return array $rData
     * @author hxtgirq
     * @since 2015-07-22
     */
    public function getSite($param) {
        global $db,$log;
        
        $rData = array('rStatus'=>100, 'message' => '地区信息获取成功');
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须';
            $log->logInfo(__METHOD__.' param ',$param);
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo(__METHOD__.' param ',$param);
                return $cidarr;
            }
            $log->logInfo(__METHOD__.' param ',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            //注释SQL部分 释放全部商品分类 by wanjun @20151217
            $site_sql = "SELECT
                          s.SiteID,
                          s.SiteName,
                          IF(a.SiteID IS NULL, '', a.SiteID) AS ERPID,
                          IF(a.Name IS NULL, '', a.Name) AS ERPName
                        FROM
                          ".$sdatabase . DATATABLE."_order_site AS s
                          LEFT JOIN ".$sdatabase.DATATABLE."_api_site AS a
                            ON a.TrueSiteID = s.SiteID
                            AND a.CompanyID = {$cid}
                        WHERE s.CompanyID = {$cid} 
                         -- AND
                         -- (SELECT
                         --   COUNT(*) AS CNT
                         -- FROM
                         --   ".$sdatabase.DATATABLE."_order_site AS sc
                         -- WHERE sc.CompanyID = {$cid}
                         --   AND sc.SiteNO LIKE CONCAT(s.SiteNO, '%')) = 1 ";
            $list = $db->get_results($site_sql);
            if(!$list) {
                $rData['rStatus'] = 100;
                $rData['count'] = count($list);
                $rData['rData'] = $list;
            } else {
                $rData['rStatus'] = 101;
                $rData['error'] = '数据为空';
                $rData['rData'] = array();
            }
            
        }
        if(!$param['debug']){
            unset($rData['debug']);
        }
        $log->logInfo(__METHOD__.' return '.$rData);
        return $rData;
    }
    
    
    /**
     * 品牌维护，只有品牌名称
     */
    public function brand($param){
        global $db,$log;
        
        $rData = array('rStatus'=>100, 'error' => '品牌上传成功');
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '验证key必须!';
            $log->logInfo("brand param ",$param);
        }elseif(false && empty($param['body'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '未传入品牌信息';
            $log->logInfo("brand param ",$param);
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo("brand param ",$param);
                return $cidarr;
            }
            $log->logInfo("site param ",$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $body = $param['body'] = json_decode(json_encode($param['body']),true);
            if($param['count']!=count($body)){
                $rData['rStatus'] = 101;
                $rData['error'] = '数据传输错误!';
                $log->logInfo(__METHOD__.' return ',$rData);
                return $rData;
            }
            
            include_once (SITE_ROOT_PATH."/class/letter.class.php");
            $letter = new letter();
            
            //查询系统中品牌编号
            $var = $db->get_var('select BrandNO from '.$sdatabase.DATATABLE.'_order_brand where CompanyID='.$cid.' order by BrandID desc');
            
            if(substr($var, 0, 3) == 'ERP'){
                $st = substr($var, 3);
            }

            $startNum = $st ? $st : 100001;
            $brandNames = array();
            foreach ($body as $val){
                $val['brandName'] = trim($val['brandName']);
                $chk = "select BrandID from ".$sdatabase.DATATABLE."_order_brand where CompanyID=".$cid." and BrandName='".$val['brandName']."'";
                $bid = $db->get_var($chk);
                
                if($bid){//更新名称
                    $bup= "update ".$sdatabase.DATATABLE."_order_brand set BrandName='".$val['brandName']."' where CompanyID=".$cid." and BrandID=".$bid;
                    $db->query($bup);
                    continue;
                }
                
                $brandNames[] = "({$cid},'{$val['brandName']}','{$letter->C($val['brandName'])}','".($startNum++)."')";
            }
           
            $sql = "INSERT INTO 
                      ".$sdatabase.DATATABLE."_order_brand 
                     (CompanyID,BrandName,BrandPinYin,BrandNO) 
                        VALUES ".implode(', ', $brandNames);
            $rData['debug'] = $sql;
            
            $db->query($sql);
        }
        
        $log->logInfo("brand return ",$rData);
        if(!$param['debug']){
            unset($rData['debug']);
        }

        return $rData;
        
    }
    
    /**
     * 获取品牌
     */
    public function getBrand($param) {
        global $db,$log;
        
        $rData = array('rStatus'=>100);
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '验证sKey必须!';
            $log->logInfo(__METHOD__.' param ',$param);
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo(__METHOD__.' param ',$param);
                return $cidarr;
            }
            
            $log->logInfo(__METHOD__.' param ',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            
            $brand_sql = "SELECT 
                            BrandID as brandID, BrandNO as brandNO, BrandName as brandName
                          FROM
                            ".$sdatabase.DATATABLE."_order_brand
                          WHERE CompanyID={$cid}";

            $list = $db->get_results($brand_sql);
            $rData['debug'] = $brand_sql;
            
            if($list) {
                $rData['rStatus'] = 100;
                $rData['rData'] = $list;
            } else {
                $rData['rStatus'] = 101;
                $rData['error'] = "数据为空!";
            }
        }
        if(!$param['debug']){
            unset($rData['debug']);
        }
        $log->logInfo(__METHOD__.' return ', $rData);
        return $rData;
    }
    
    /**
     * @desc 同步品牌维护
     * @param array $param (sKey,count,body)
     * body array(
     array('brandID'=>0,'brandName'=>'中性笔','brandCode'=>1),
     * )
     * @return array $rData
     */
//     public function brand($param){
//         global $db,$log;
        
//         $rData = array('rStatus'=>100);
//         $param = is_object($param) ? (array)$param : $param;
//         if(empty($param['sKey'])){
//             $rData['rStatus'] = 101;
//             $rData['error'] = '验证key必须!';
//             $log->logInfo("site param ",$param);
//         }elseif(false && empty($param['body'])){
//             $rData['rStatus'] = 101;
//             $rData['error'] = '未传入品牌信息';
//             $log->logInfo("site param ",$param);
//         }else{
//             $cidarr = self::getCompanyInfo($param['sKey']);
//             if($cidarr['rStatus']==101){
//                 $log->logInfo("site param ",$param);
//                 return $cidarr;
//             }
//             $log->logInfo("site param ",$param);
//             $cid = $cidarr['CompanyID'];
//             $sdatabase = $cidarr['Database'];
//             $body = $param['body'] = json_decode(json_encode($param['body']),true);
//             if($param['count']!=count($body)){
//                 $rData['rStatus'] = 101;
//                 $rData['error'] = '数据传输错误!';
//                 $log->logInfo(__METHOD__.' return ',$rData);
//                 return $rData;
//             }
            
//             $BrandIDS = array();
//             $list = array();
//             foreach($body as $val){
//                 $val = array_map('trim', $val);
//                 $list[$val['brandID']] = $val;
//             }
            
//             include_once (SITE_ROOT_PATH."/class/letter.class.php");
//             $letter = new letter();
            
//             $body = $list;
//             $unChange = array();
//             foreach($body as $key=>$val){
                
//                 $BrandIDS[] = $val['brandID'];
//                 $sign = $db->get_row("SELECT * FROM ".$sdatabase.DATATABLE."_api_brand WHERE BrandID='{$val['brandID']}' AND CompanyID={$cid}");
                
//                 if($sign){//更新
//                     if($sign['Name'] == $val['brandName'] && $sign['BrandCode'] == $val['brandCode']){
//                         //品牌名称/编号无改动
//                         $unChange[] = $val;
//                         continue;
//                     }

//                     //更改接口数据
//                     $db->query("UPDATE ".$sdatabase.DATATABLE."_api_brand SET Name='{$val['brandName']}',BrandCode='{$val['brandCode']}' WHERE CompanyID={$cid} AND BrandID='{$val['brandID']}'");
//                     $log->logInfo("更新api-brand" , $db->last_query);

//                     //更改订货宝数据
//                     $db->query("UPDATE ".$sdatabase.DATATABLE."_order_brand SET BrandNO=".$val['brandCode'].",BrandName='{$val['brandName']}',BrandPinYin='".$letter->C($val['brandName'])."' WHERE CompanyID={$cid} AND BrandID={$sign['TrueBrandID']}");
    
//                 }else{
//                     $sql_api = "INSERT INTO ".$sdatabase.DATATABLE."_api_brand (BrandID,Name,CompanyID,TrueBrandID,BrandCode) VALUES ('{$val['brandID']}','{$val['brandName']}',{$cid},0, '".$val['brandCode']."')";
//                     $rData['debug']['_api_brand']['insert'][] = $sql_api;
//                     $db->query($sql_api);
                    
//                     $val['Pinyi'] = $letter->C($val['brandName']);
//                     $sql = "INSERT INTO ".$sdatabase.DATATABLE."_order_brand (CompanyID,BrandName,BrandPinYin,BrandNO) VALUES ({$cid},'{$val['brandName']}','{$val['Pinyi']}','".$val['brandCode']."')";
//                     $rData['debug']['_brand'][] = $sql;
//                     $db->query($sql);
//                     $brandID = $db->insert_id;
//                     $db->query("UPDATE ".$sdatabase.DATATABLE."_api_brand SET TrueBrandID={$brandID} WHERE BrandID='{$val['brandID']}' AND CompanyID={$cid}");
//                     $rData['debug']['_api_brand']['update'][] = $db->last_query;
//                 }
//             }

//             $All = $db->get_col("SELECT BrandID FROM ".$sdatabase.DATATABLE."_api_brand WHERE CompanyID={$cid}");
//             $delIDS = array_diff($All, $BrandIDS);
//             $rData['debug']['all'] = $All;
//             $rData['debug']['del'] = $delIDS;
//             if(count($delIDS)>0){
//                 $delIDS = array_map("strQuote", $delIDS);
//                 $trueBrandIDS = $db->get_col("SELECT TrueBrandID FROM ".$sdatabase.DATATABLE."_api_brand WHERE CompanyID={$cid} AND BrandID IN(".implode(',',$delIDS).")");
//                 $db->query("DELETE FROM ".$sdatabase.DATATABLE."_order_brand WHERE CompanyID={$cid} AND BrandID IN(".implode(',',$trueBrandIDS).")");
//                 $rData['debug']['del_brand'] = $db->last_query;
//                 $db->query("DELETE FROM ".$sdatabase.DATATABLE."_api_brand WHERE CompanyID={$cid} AND BrandID IN(".implode(',',$delIDS).")");
//                 $rData['debug']['del_api_brand'] = $db->last_query;
//             }
//         }
//         $log->logInfo("brand return ",$rData);
//         if(!$param['debug']){
//             unset($rData['debug']);
//         }
        
//         if(count($unChange)) $rData['unchange'] = $unChange;
        
//         return $rData;
//     }
    
    
    /**
     * @desc ERP获取DHB品牌信息
     * @param $param
     * @return array $rData
     * @author hxtgirq
     * @since 2015-07-22
     */
//     public function getBrand($param) {
//         global $db,$log;
        
//         $rData = array('rStatus'=>100);
//         $param = is_object($param) ? (array)$param : $param;
//         if(empty($param['sKey'])){
//             $rData['rStatus'] = 101;
//             $rData['error'] = '验证sKey必须!';
//             $log->logInfo(__METHOD__.' param ',$param);
//         }else{
//             $cidarr = self::getCompanyInfo($param['sKey']);
//             if($cidarr['rStatus']==101){
//                 $log->logInfo(__METHOD__.' param ',$param);
//                 return $cidarr;
//             }
            
//             $log->logInfo(__METHOD__.' param ',$param);
//             $cid = $cidarr['CompanyID'];
//             $sdatabase = $cidarr['Database'];
            
//             $brand_sql = "SELECT 
//                               ob.BrandID,
//                               ob.BrandName,
//                               IF(ab.BrandID IS NULL, '', ab.BrandID) AS ERPID,
//                               IF(
//                                 ab.Name IS NULL,
//                                 '',
//                                 ab.Name
//                               ) AS ERPName 
//                             FROM
//                               ".$sdatabase.DATATABLE."_order_brand AS ob
//                               LEFT JOIN ".$sdatabase.DATATABLE."_api_brand AS ab
//                                 ON ab.TrueBrandID = ob.BrandID 
//                                 AND ob.CompanyID = {$cid} 
//                             WHERE ab.CompanyID = {$cid}";

//             $list = $db->get_results($brand_sql);
//             $rData['debug'] = $brand_sql;
            
//             if($list) {
//                 $rData['rStatus'] = 100;
//                 $rData['rData'] = $list;
//             } else {
//                 $rData['rStatus'] = 101;
//                 $rData['error'] = "数据为空!";
//             }
//         }
//         if(!$param['debug']){
//             unset($rData['debug']);
//         }
//         $log->logInfo(__METHOD__.' return ', $rData);
//         return $rData;
//     }
    
    
    

    /**
     * @desc 同步地区
     * @param array $param (sKey,count,body)
     * body array(
        array(areaID,parentID,Name),
     * )
     * @return array $rData
     */
    public function area($param){
        global $db,$log;
        $rData = array('rStatus'=>100, 'message' => '添加地区信息执行完毕');
        $param = is_object($param) ? (array)$param : $param;
     
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
            $log->logInfo(__METHOD__.' param ',$param);
            $log->logInfo(__METHOD__.' return ',$rData);
        }else{   
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo(__METHOD__.' param ',$param);
                return $cidarr;
            }

            $log->logInfo(__METHOD__.' param ',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $body = $param['body'] = json_decode(json_encode($param['body']),true);
            if($param['count']!=count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
                $log->logInfo(__METHOD__.' return ',$rData);
                return $rData;
            }
            include_once (SITE_ROOT_PATH."/class/letter.class.php");
            $letter = new letter();
            $AreaIDS = array();
            
            foreach($body as $key=>$val){
                $AreaIDS[] = $val['areaid'];
                $sign = $db->get_row("SELECT * FROM ".$sdatabase.DATATABLE."_api_area WHERE CompanyID={$cid} AND AreaID='{$val['areaid']}'");
                if($sign) {
                    $tsite = $db->get_row("SELECT * FROM ".$sdatabase.DATATABLE."_order_area WHERE AreaCompany={$cid} AND AreaID={$sign['TrueAreaID']}");
                    if(empty($tsite)) {
                        $db->query("DELETE FROM ".$sdatabase.DATATABLE."_api_area WHERE AreaID='{$val['areaid']}' AND CompanyID={$cid} LIMIT 1");
                        $sign = false;
                    }
                }
                if($sign){
                    if($sign['AreaParentID']==$val['parentid'] && $sign['AreaName']==$val['name']){
                        //地区信息无改动
                        continue;
                    }
                    $db->query("UPDATE ".$sdatabase.DATATABLE."_api_area SET AreaParentID='{$val['parentid']}',AreaName='{$val['name']}' WHERE CompanyID={$cid} AND AreaID='{$val['areaid']}' ");
                    $areaParentID = "AreaParentID";
                    if($sign['AreaParentID']!=$val['parentid']){
                        $areaParentID = $db->get_var("SELECT TrueAreaID FROM ".$sdatabase.DATATABLE."_api_area WHERE CompanyID={$cid} AND AreaID='{$val['parentid']}'");
                    }
                    $db->query("UPDATE ".$sdatabase.DATATABLE."_order_area SET AreaName='{$val['name']}',AreaPinyi='".$letter->C($val['name'])."',AreaParentID={$areaParentID} WHERE AreaCompany={$cid} AND AreaID={$sign['TrueAreaID']}");

                }else{
                    $db->query("INSERT INTO ".$sdatabase.DATATABLE."_api_area (AreaID,AreaParentID,AreaName,TrueAreaID,CompanyID) VALUES ('{$val['areaid']}','{$val['parentid']}','{$val['name']}',0,{$cid})");
                    $rData['debug']['insert_api_area'][] = $db->last_query;
                    $parentID = 0;
                    if((int)$val['parentid']!=0){
                        $parentID = $db->get_var("SELECT TrueAreaID FROM ".$sdatabase.DATATABLE."_api_area WHERE CompanyID={$cid} AND AreaID='{$val['parentid']}'");
                        $rData['debug']['parent'][] = $db->last_query;
                    }

                    $db->query("INSERT INTO ".$sdatabase.DATATABLE."_order_area (AreaCompany,AreaParentID,AreaName,AreaPinyi,AreaAbout) VALUES ('{$cid}','{$parentID}','{$val['name']}','".$letter->C($val['name'])."','')");

                    $areaID = $db->insert_id;
                    $rData['debug']['insert_area'][] = $db->last_query;
                    $db->query("UPDATE ".$sdatabase.DATATABLE."_api_area SET TrueAreaID={$areaID} WHERE AreaID='{$val['areaid']}' AND CompanyID={$cid}");
                    $rData['debug']['update_trueAreaID'][] = $db->last_query;
                }
            }

            $CurAreaIDS = $db->get_col("SELECT AreaID FROM ".$sdatabase.DATATABLE."_api_area WHERE CompanyID={$cid}");
            $CurAreaIDS = empty($CurAreaIDS) ? array() : $CurAreaIDS;
            $delIDS = array_diff($CurAreaIDS,$AreaIDS);
            $rData['debug']['all'] = $AreaIDS;
            $rData['debug']['cur'] = $CurAreaIDS;
            $rData['debug']['del'] = $delIDS;
            if(count($delIDS)>0){
                $delIDS = array_map("strQuote",$delIDS);
                $delTrueAreaIDS = $db->get_col("SELECT TrueAreaID FROM ".$sdatabase.DATATABLE."_api_area WHERE CompanyID={$cid} AND AreaID IN(".implode(',',$delIDS).")");
                $db->query("DELETE FROM ".$sdatabase.DATATABLE."_api_area WHERE CompanyID={$cid} AND AreaID IN(".implode(',',$delIDS).")");
                $rData['debug']['del_api_area'] = $db->last_query;
                $db->query("DELETE FROM ".$sdatabase.DATATABLE."_order_area WHERE AreaCompany={$cid} AND AreaID IN(".implode(',',$delTrueAreaIDS).")");
                $rData['debug']['del_area'] = $db->last_query;
            }
        }

        $log->logInfo(__METHOD__.' return ',$rData);
        if(!$param['debug']){
            unset($rData['debug']);
        }
        
        return $rData;
    }

    /**
     * @desc ERP获取DHB地区信息(药店分类)
     * @param $param
     * @return array $rData
     * @author hxtgirq
     * @since 2015-07-22
     */
    public function getArea($param) {
        global $db,$log;
        $rData = array('rStatus'=>100, 'message' => '地区信息获取成功');
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证sKey必须!';
            $log->logInfo(__METHOD__.' param ',$param);
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo(__METHOD__.' param ',$param);
                return $cidarr;
            }
            $log->logInfo(__METHOD__.' param ',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            //注释SQL部分 释放全部商品分类 by wanjun @20151218
            $area_sql = "SELECT
                          a.AreaID,
                          a.AreaName,
                          IF(aa.AreaID IS NULL ,'',aa.AreaID) AS ERPID,
                          IF(aa.AreaName IS NULL , '',aa.AreaName) AS ERPName
                        FROM
                          ".$sdatabase.DATATABLE."_order_area AS a
                          LEFT JOIN ".$sdatabase.DATATABLE."_api_area AS aa
                          ON aa.TrueAreaID = a.AreaID AND aa.CompanyID ={$cid}
                        WHERE a.AreaCompany = {$cid}
                          -- AND
                          -- (SELECT
                          --   COUNT(*) AS cnt
                          -- FROM
                          --   ".$sdatabase.DATATABLE."_order_area AS ac
                          -- WHERE ac.AreaCompany = {$cid}
                          --  AND ac.AreaParentID = a.AreaID) = 0 ";
            $list = $db->get_results($area_sql);
            if($list) {
                $rData['rStatus'] = 100;
                $rData['count'] = count($list);
                $rData['rData'] = $list;
            } else {
                $rData['rStatus'] = 101;
                $rData['message'] = "数据为空!";
                $rData['rData'] = array();
            }
        }
        if(!$param['debug']){
            unset($rData['debug']);
        }
        $log->logInfo(__METHOD__.' return ', $rData);
        return $rData;
    }
    /**
     * @desc 款项 (订货宝中已确认到账的付款单传递给ERP接口
     * @param array $param (sKey,body)
     * @return array $rData
     */
    public function getFinanceList($param){
        global $db,$log;
        $rData = array('rStatus'=>100);
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '验证key必须!';
            $log->logInfo(__METHOD__.' param ',$param);
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo(__METHOD__.' param ',$param);
                return $cidarr;
            }
            $log->logInfo(__METHOD__.' param ',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $sql = "SELECT f.FinanceID,f.FinanceTotal,f.FinanceAbout,IF(f.FinanceOrder<>'0' && f.FinanceOrder<>'','YIN','YU') as FinanceCategory,f.FinanceType,f.FinanceUser,f.FinanceAdmin,f.FinanceUpDate
                    FROM ".$sdatabase.DATATABLE."_order_finance AS f
                    WHERE f.FinanceFlag=2 AND f.FinanceCompany={$cid} AND f.FinanceApi='F' AND f.FinanceFlag<>'Y' ";
            $sql .= " limit ".$param['begin'].",".intval($param['step']);
            $list = $db->get_results($sql);
            $rData['debug']['sql'] = $sql;
            if(empty($list)){
                $rData['rStatus'] = 101;
                $rData['error'] = '没有相关收款单!';
            }else{
                $rData['rTotal'] = count($list);
                $rData['rData'] = $list;
            }

        }
        if(!$param['debug']){
            unset($rData['debug']);
        }
        $log->logInfo(__METHOD__.' return '.$rData);
        return $rData;
    }

    /**
     * @desc 获取款项详细
     * @param array $param (sKey,financeID)
     * @return array $rData
     */
    public function getFinanceContent($param){
        global $db,$log;
        $rData = array('rStatus'=>100);
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '参数错误!';
            $log->logInfo(__METHOD__.' param ',$param);
        }elseif(empty($param['financeID'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '收款单号不能为空!';
            $log->logInfo(__METHOD__.' param ',$param);
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo(__METHOD__.' param ',$param);
                return $cidarr;
            }
            $log->logInfo(__METHOD__.' param ',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $sql = "SELECT f.FinanceID,f.FinanceClient,f.FinanceOrder,IF(f.FinanceOrder<>'0' && f.FinanceOrder<>'','YIN','YU') as FinanceCategory,f.FinanceTotal,f.FinanceAbout,f.FinanceType,f.FinanceUser,f.FinanceAdmin,f.FinanceUpDate,f.FinanceApi
                    ,a.AccountsBank,a.AccountsNO,c.ClientNO
                    FROM ".$sdatabase.DATATABLE."_order_finance AS f LEFT JOIN ".$sdatabase.DATATABLE."_order_accounts AS a ON a.AccountsID = f.FinanceAccounts LEFT JOIN ".$sdatabase.DATATABLE."_order_client AS c ON c.ClientID = f.FinanceClient
                    WHERE f.FinanceFlag=2 AND f.FinanceCompany={$cid} AND f.FinanceID={$param['financeID']} AND f.FinanceFlag<>'Y' LIMIT 0,1 ";
            $single = $db->get_row($sql);
            $rData['debug']['sql'] = $sql;
            if($single){
                //更改付款单　接口取数据状态
                $db->query("UPDATE ".$sdatabase.DATATABLE."_order_finance SET FinanceApi='T' WHERE FinanceID={$param['financeID']}");
                $rData['rData'] = $single;
            }else{
                $rData['rStatus'] = 101;
                $rData['error'] = '付款单不存在!';
            }
        }
        if(!$param['debug']){
            unset($rData['debug']);
        }
        $log->logInfo(__METHOD__.' return '.$rData);
        return $rData;
    }

    /**
     * @desc 接口基础代码
     * @param $param
     * @return array
     */
    public function base($param){
        global $db,$log;
        $rData = array('rStatus'=>100);
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '参数错误!';
            $log->logInfo(__METHOD__.' param ',$param);
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo(__METHOD__.' param ',$param);
                return $cidarr;
            }
            $log->logInfo(__METHOD__.' param ',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];

            //coding
        }
        if(!$param['debug']){
            unset($rData['debug']);
        }
        $log->logInfo(__METHOD__.' return '.$rData);
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
     * 根据商品编号(或批量)获取商品基础资料
     *
     * @author wanjun
     * @param array $param 接口传入参数
     * @return array 格式化后的商品资料
     * @since 2015/11/08
     * @todo return优化，判断编号为空的逻辑
     */
    public function getProductContent($param){
    	global $db,$log;
    	
        $rData = array('rStatus'=>100);
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '参数错误!';
            $log->logInfo(__METHOD__.' param ',$param);
            return $rData;
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo(__METHOD__.' param ',$param);
                return $cidarr;
            }
            $log->logInfo(__METHOD__.' param ',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
           
           //目前按照两种逻辑处理
           //1、最多支持100个编号，无分页(当前系统默认分页步长为100)
           //2、未传递编号，批量获取
           if(strlen($param['productNO']) == 0){//没有传递编号
           		$where = " LIMIT " . intval($param['begin']) . ", " . intval($param['step']);
           }else{
				//验证时按需获取还是批量获取
				$productsNO = $this->_bulidCat($param['productNO']);
				 if(count($productsNO) > 100){
					$rData['rStatus'] = 101;
            		$rData['error'] = '传递了' . count($productsNO) . '个编号，请传递数量小于100';
            		return $rData;
				}else{
					$where = " AND ci.Coding in(" . implode(",", $productsNO) . ")";
				}
           }

           //查询结果
           $pSql = "SELECT 
						ci.SiteID,ci.Name,ci.Coding,ci.FlagID,ci.LibraryDown,ci.LibraryUp,ci.Color,ci.Specification,
						cc.ContentCreateDate,cc.ContentEditDate,cc.FieldContent 
           			FROM 
           				".$sdatabase.DATATABLE."_order_content_index AS ci LEFT JOIN ".$sdatabase.DATATABLE."_order_content_1 AS cc 
           				ON ci.CompanyID=cc.CompanyID AND ci.ID = cc.ContentIndexID 
  					WHERE ci.CompanyID=" . $cid . $where;
           $pinfo = $db->get_results($pSql);
           
           //商品分类
           $sqlSites = "SELECT site.SiteID,site.SiteNO,site.SiteName,apisite.SiteID AS ApiSiteID FROM ".$sdatabase.DATATABLE."_order_site AS site LEFT JOIN ".$sdatabase.DATATABLE."_api_site AS apisite ON site.CompanyID=apisite.CompanyID AND site.SiteID=apisite.TrueSiteID WHERE site.CompanyID=" . $cid;
                
			//组合商品份额分类
			$relationSite = $this->_contribulidSite($db->get_results($sqlSites));
			
			//格式化商品资料
			$plen = count($pinfo);
			for($i = 0; $i < $plen; $i++){
//				$pinfo[$i]['ContentCreateDate'] = date('Y-m-d H:i:s', empty($pinfo[$i]['ContentCreateDate']) ? $pinfo[$i]['ContentEditDate'] : $pinfo[$i]['ContentCreateDate']);
//				$pinfo[$i]['ContentEditDate']	= date('Y-m-d H:i:s');
				$pinfo[$i]['SiteName']			= @$relationSite[$pinfo[$i]['SiteID']]['SiteName'];		//当前分类名称
				$pinfo[$i]['TopSiteID']			= @$relationSite[$pinfo[$i]['SiteID']]['TopSite'];		//顶级分类ID
				$pinfo[$i]['TopSiteName']		= @$relationSite[$pinfo[$i]['SiteID']]['TopSiteName'];	//顶级分类名称
				$pinfo[$i]['TopSiteNO']			= @$relationSite[$pinfo[$i]['SiteID']]['ApiSiteID'];	//ERP ID
				$pinfo[$i]['OnSell']			= $pinfo[$i]['FlagID'] ? 'N' : 'Y';	//是否在售：Y，是；N，下架
       		}
           
       		unset($param, $relationSite);
       		return array(
				'header' => array('count' => count($pinfo)),		
				'body' => $pinfo	
			);
        }
    }//END getProductContent
    
    /**
     * 根据药店编号(或批量)获取药店基础资料
     *
     * @author wanjun
     * @param array $param 接口传入参数
     * @return array 格式化后的商品资料
     * @since 2015/11/08
     * @todo return优化，判断编号为空的逻辑
     */
    public function getClientContent($param){
    	global $db,$log;
    	
        $rData = array('rStatus'=>100);
        $param = is_object($param) ? (array)$param : $param;
        
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '参数错误!';
            $log->logInfo(__METHOD__.' param ',$param);
            return $rData;
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo(__METHOD__.' param ',$param);
                return $cidarr;
            }
            $log->logInfo(__METHOD__.' param ',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
           
           //目前按照两种逻辑处理
           //1、最多支持100个编号，无分页(当前系统默认分页步长为100)
           //2、未传递编号，批量获取
           if(strlen($param['clientNO']) == 0){//没有传递编号
           		$where = " LIMIT " . intval($param['begin']) . ", " . intval($param['step']);
           }else{
				//验证时按需获取还是批量获取
				 $clientsNO = $this->_bulidCat($param['clientNO']);
				 if(count($clientsNO) > 100){
					$rData['rStatus'] = 101;
            		$rData['error'] = '传递了' . count($clientsNO) . '个编号，请传递数量小于100';
            		return $rData;
				}else{
					$where = " AND c.ClientNO in(" . implode(",", $clientsNO) . ")";
				}
           }
           
           //查询结果
           $cSql = "SELECT c.ClientArea,c.ClientCompanyName,c.ClientName,c.ClientNO,c.ClientTrueName,c.ClientEmail,c.ClientPhone,c.ClientFax,c.ClientMobile,c.ClientAdd,c.ClientAbout,c.AccountName,c.BankName,c.InvoiceHeader,c.TaxpayerNumber,a.AreaName FROM ".$sdatabase.DATATABLE."_order_client AS c LEFT JOIN ".$sdatabase.DATATABLE."_order_area AS a ON c.ClientCompany=a.AreaCompany AND c.ClientArea=a.AreaID WHERE c.ClientCompany=" . $cid . $where;
           $clientInfo = $db->get_results($cSql);
        }
		return array(
				'header' => array('count' => count($clientInfo)),		
				'body' => $clientInfo	
		);
    }//END getClientContent
    
    
    /**
     * 根据运单号获取发货单信息
     * @author wanjun
     * @param array $param 接口传入参数
     * @return array 发货单资料
     * @since 2016/03/01
     * @todo 增加每个状态值和描述
     */
    public function getConsignment($param){
        global $db,$log;
         
        $rData = array('rStatus'=>100);
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['error'] = '参数错误!';
            $log->logInfo(__METHOD__.' param ',$param);
            return $rData;
        }else{
            $cidarr = self::getCompanyInfo($param['sKey']);
            if($cidarr['rStatus']==101){
                $log->logInfo(__METHOD__.' param ',$param);
                return $cidarr;
            }
            $log->logInfo(__METHOD__.' param ',$param);
            $cid = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            
            //运单 编号
            $cno = strval($param['cno']);
            $sms = empty($cno) ? "" : " AND con.ConsignmentNO='".$cno."'";
            //发货单
            $consignment_sql = "SELECT
                            con.ConsignmentID,
                        	con.ConsignmentNO,
                        	con.InceptMan,
                        	con.InceptCompany,
                        	con.InceptPhone,
                        	con.InceptAddress,
                        	c.ClientNO,
                        	c.ClientCompanyName,
                        	con.ConsignmentOrder,
                        	con.ConsignmentClient
                        	FROM ".$sdatabase.DATATABLE."_order_consignment AS con
                        	LEFT JOIN ".$sdatabase.DATATABLE."_order_client AS c
                        	ON con.ConsignmentClient = c.ClientID
                        	where con.ConsignmentCompany= ".$cid.$sms." limit 0,1";
            $coninfo = $db->get_row($consignment_sql);

            //发货单->一、订单表[根据订单号获取订单明细信息]
            $consignment_date['c_consignment']['BillNo']          = $coninfo['ConsignmentNO'];     //运货订单号（唯一）
            $consignment_date['c_consignment']['DealerNo']        = $coninfo['ClientNO'];          //客户编号
            $consignment_date['c_consignment']['DealerName']      = $coninfo['ClientCompanyName']; //客户名称
            
            //收货信息
            $consignment_date['c_consignment']['InceptMan']      = $coninfo['InceptMan'];          //收货人
            $consignment_date['c_consignment']['InceptCompany']  = $coninfo['InceptCompany'];      //收货公司
            $consignment_date['c_consignment']['InceptPhone']    = $coninfo['InceptPhone'];        //联系电话
            $consignment_date['c_consignment']['InceptAddress']  = $coninfo['InceptAddress'];      //收货地址
            
            //正常商品
            $psql = "select c.ID,c.ContentID,c.ContentName,c.ContentColor,c.ContentSpecification,l.ContentNumber,i.Coding,i.Casing,i.Units from ".$sdatabase.DATATABLE."_order_cart c inner join ".$sdatabase.DATATABLE."_order_out_library l on c.ID=l.CartID left join ".$sdatabase.DATATABLE."_order_content_index i on c.ContentID=i.ID where c.CompanyID=".$cid." and l.ConsignmentID=".$coninfo['ConsignmentID']." and l.ConType='c' order by i.SiteID asc,c.ID asc";
            $cart_product = $db->get_results($psql);
            //发货单->二、商品表信息[获取商品信息]
            foreach ($cart_product as $ckey=>$cval){
                $consignment_product[$ckey]['ProductNo']         = $cval['Coding'];          //产品代码
                $consignment_product[$ckey]['ProductName']       = $cval['ContentName'];     //产品名称
                $consignment_product[$ckey]['ProductUnitName']   = $cval['Units'];           //产单位名称
                $consignment_product[$ckey]['ProductCount']      = $cval['ContentNumber'];   //产品数量
                $consignment_product[$ckey]['BoxCount']          = 0;                        //装箱数
                $consignment_product[$ckey]['RowNo']             = $cval['ID'];             //行编号（同一订单唯一）
            }
            $consignment_date['c_product'] = $consignment_product;
            //赠品
            $gsql = "select c.ID,c.ContentID,c.ContentName,c.ContentColor,c.ContentSpecification,l.ContentNumber,i.Coding,i.Casing,i.Units from ".$sdatabase.DATATABLE."_order_cart_gifts c inner join ".$sdatabase.DATATABLE."_order_out_library l on c.ID=l.CartID left join ".$sdatabase.DATATABLE."_order_content_index i on c.ContentID=i.ID where c.CompanyID=".$cid." and l.ConsignmentID=".$coninfo['ConsignmentID']." and l.ConType='g' order by i.SiteID asc,c.ID asc";
            $cart_product = $db->get_results($gsql);
            //发货单->二、商品表信息[获取商品信息]
            $consignment_product_gifts = array();
            foreach ($cart_product as $ckey=>$cval){
                $consignment_product_gifts[$ckey]['ProductNo']         = $cval['Coding'];          //产品代码
                $consignment_product_gifts[$ckey]['ProductName']       = $cval['ContentName'];     //产品名称
                $consignment_product_gifts[$ckey]['ProductUnitName']   = $cval['Units'];           //产单位名称
                $consignment_product_gifts[$ckey]['ProductCount']      = $cval['ContentNumber'];   //产品数量
                $consignment_product_gifts[$ckey]['BoxCount']          = 0;                        //装箱数
                $consignment_product_gifts[$ckey]['RowNo']             = $cval['ID'];             //行编号（同一订单唯一）
            }
            
            $consignment_date['c_product_gifts'] = $consignment_product_gifts;
            
            //发货单->三、药店信息 [获取药店信息]
//             $consignment_date['c_user']['DealerNo']        = $coninfo['ClientNO'];             //药店代码
//             $consignment_date['c_user']['DealerName']      = $coninfo['ClientCompanyName'];    //药店名称
            $return['consignment_data']                    = $consignment_date;

            //获取 发货单 相关联的订单信息
            //订单 表头
            $orderClass_sql   = "SELECT
                                  o.OrderID,
                                  c.ClientNO,
                                  c.ClientName,
                                  c.ClientArea,
                                  o.OrderUserID,
                                  c.ClientTrueName,
                                  c.ClientPhone,
                                  o.OrderReceiveCompany,
                                  o.OrderReceiveName,
                                  o.OrderReceivePhone,
                                  o.OrderReceiveAdd,
                                  o.OrderSendType,
                                  o.OrderSendStatus,
                                  o.OrderPayType,
                                  o.OrderPayStatus,
                                  o.OrderSN,
                                  o.OrderStatus,
                                  o.OrderType,
                                  o.OrderDate
                               FROM
                                  ".$sdatabase.DATATABLE."_order_orderinfo AS o
                                 LEFT JOIN ".$sdatabase.DATATABLE."_order_client AS c
                                 ON o.OrderUserID = c.ClientID
                               WHERE o.OrderCompany = ".$cid."
                                and o.OrderSN='".$coninfo['ConsignmentOrder']."'
                               Order By o.OrderDate DESC";
            $oinfo= $db->get_row($orderClass_sql);
            
            $order_status_arr = array(
                '0'         =>  '待审核',
                '1'         =>  '备货中',
                '2'         =>  '已出库',
                '3'         =>  '已收货',
                '5'         =>  '已收款',
                '7'         =>  '已完成',
                '8'         =>  '客户端取消',
                '9'         =>  '管理端取消'
            );
            $paytypearr = array (
                '1' => '现金(先付)',
                '2' => '转帐(先付)',
                '3' => '在线支付(先付)',
                '4' => '转帐(后付)',
                '5' => '代收款',
                '6' => '月结',
                '7' => '预存款(先付)',
                '8' => '货到付款'
            );
            
            //一、订单表[根据订单号获取订单明细信息]
            $order_data['o_order']['OrderSN']            = $oinfo['OrderSN'];                           //订单单号
            $order_data['o_order']['OrderDate']          = date("Y-m-d H:i:s",$oinfo['OrderDate']);     //下单时间
            $order_data['o_order']['OrderStatus']        = $order_status_arr[$oinfo['OrderStatus']];    //订单状态
            $order_data['o_order']['OrderType']          = $ordertypearr[$oinfo['OrderType']];          //订单类型
            
            //客户信息
            $order_data['o_order']['UserNO']             = $oinfo['ClientNO'];          //用户ID
            $order_data['o_order']['UserTrueName']       = $oinfo['ClientTrueName'];    //联系人
            $order_data['o_order']['Userphone']          = $oinfo['ClientPhone'];       //联系电话
            //收货地址
            $order_data['o_order']['ReceivedCompanyName']= $oinfo['OrderReceiveCompany']; //收货公司名称
            $order_data['o_order']['Receiver']           = $oinfo['OrderReceiveName'];    //联系人
            $order_data['o_order']['ReceiverPhone']      = $oinfo['OrderReceivePhone'];   //联系电话
            $order_data['o_order']['ReceiverAddress']    = $oinfo['OrderReceiveAdd'];     //收货地址
            
            //订单->详情
            $ocart_sql = "select ID,OrderID,CompanyID,ClientID,ContentID,ContentName,Coding,ContentColor,ContentSpecification,SUM(ContentNumber) AS ContentNumber,Units,ContentPrice,ContentPercent,(ContentPrice*ContentPercent)/10 AS percent_end,(SUM(ContentNumber)*ContentPrice*ContentPercent)/10 AS notetotal from ".$sdatabase.DATATABLE."_view_index_cart where CompanyID=".$cid." and OrderID=".$oinfo['OrderID']." group by ContentID asc";
            $ocart = $db->get_results($ocart_sql);
            $ocartgifts_sql = "select ID,OrderID,CompanyID,ClientID,ContentID,ContentName,Coding,ContentColor,ContentSpecification,SUM(ContentNumber) AS ContentNumber,Units,ContentPrice,10 as ContentPercent,ContentPrice AS percent_end,(SUM(ContentNumber)*ContentPrice) AS notetotal from ".$sdatabase.DATATABLE."_view_index_gifts where CompanyID=".$cid." and OrderID=".$oinfo['OrderID']." group by ContentID asc";
            $ocartgifts = $db->get_results($ocartgifts_sql);
            
            //订单->订购的产品
            foreach($ocart as $ckey=>$cvar){
                $order_product[$ckey]['Coding']          = $cvar['Coding'];  //编号
                $order_product[$ckey]['Name']            = $cvar['ContentName'];    //商品名称
                $order_product[$ckey]['Specification']   = $cvar['ContentColor']."|".$cvar['ContentSpecification'];// 颜色/规格
            
                $order_product[$ckey]['Number']         = $cvar['ContentNumber'];       //数量
                $order_product[$ckey]['Units']          = $cvar['Units'];               //单位
                $order_product[$ckey]['Price']          = $cvar['ContentPrice'];        //价格
                $order_product[$ckey]['Discount']       = $cvar['ContentPercent'];      //折扣
                $order_product[$ckey]['DiscountPrice']  = number_format($cvar['percent_end'], 2, '.', '');  //折后价
                $order_product[$ckey]['Sums']           = number_format($cvar['notetotal'], 2, '.', '');    //金额(数量*折扣*单价)
            }
            //订单->赠品
            foreach($ocartgifts as $ckey=>$cvar){
                $order_product_gifts[$ckey]['Coding']         = $cvar['Coding'];  //编号
                $order_product_gifts[$ckey]['Name']           = $cvar['ContentName'];    //商品名称
                $order_product_gifts[$ckey]['Specification']  = $cvar['ContentColor']."|".$cvar['ContentSpecification'];// 颜色/规格
            
                $order_product_gifts[$ckey]['Number']         = $cvar['ContentNumber'];       //数量
                $order_product_gifts[$ckey]['Units']          = $cvar['Units'];               //单位
                $order_product_gifts[$ckey]['Price']          = $cvar['ContentPrice'];        //价格
                $order_product_gifts[$ckey]['Discount']       = $cvar['ContentPercent'];      //折扣
                $order_product_gifts[$ckey]['DiscountPrice']  = number_format($cvar['percent_end'], 2, '.', '');  //折后价
                $order_product_gifts[$ckey]['Sums']           = number_format($cvar['notetotal'], 2, '.', '');    //金额(数量*折扣*单价)
            }
            $order_data['o_product']        = $order_product;
            $order_data['o_product_gifts']  = $order_product_gifts;
            $return['order_data']           = $order_data;

            $rData['message']   = '对接成功';
            $rData['body']      = $return;
            
            $log->logInfo(__METHOD__.' return '.$rData);
            unset($order_product_gifts, $order_product, $ocartgifts, $ocart, $order_data, $oinfo, $consignment_product);
            return $rData;
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
