<?php

!defined('SYSTEM_ACCESS') && exit('Access deny!');
/**
 * ERP API接口基础程序
 *
 * PHP version 5
 *
 * @category  PHP
 * @author    WanJun <wanjun@rsung.com>
 * @copyright 2016 Rsung
 * @version   <1.0>
 * @date	  2016/03/15
 *
 */

class ApiBase{
    
    protected $DB  = null,        //数据库链接
              $LOG = null,       //日志记录
              $ERP = null;       //ERP合作方名称
    CONST     DHB_ORDER = 'DHB';  //ERP合作方名称
    
    //商品类型翻译
    protected $productCommend = array(//商品类型
                    'default'   => 0,   //默认
                    'recommend' => 1,   //推荐
                    'special'   => 2,   //特价
                    'new'       => 3,   //新款
                    'hot'       => 4,   //热销
                    'gift'      => 8,   //赠品
                    'shortage'  => 9    //缺货
                ),
               $orderStatus = array(//订单类型
                    'pending'     => 0,   //待审核
                    'stocking'    => 1,   //备货中
                    'outLibrary'  => 2,   //已出库
                    'receiving'   => 3,   //已收货
                    'receivables' => 5,   //已收款
                    'complete'    => 7,   //已完成
                ),
                $allowAction = array(//允许的操作
                    'open',               //待审核
                    'close',              //备货中
                    'del',                //已出库
                );
    
    /**
     * 初始化程序参数
     * @param $ERP string ERP合作方名称
     */
    public function __construct($ERP = ''){
        
        $this->ERP = $ERP;
        
        //数据池
        $this->DB  = dbconnect::dataconnect()->getdb();
        $this->DB->cache_dir = CONF_PATH_CACHE;
        
        //日志
        $this->LOG = KLogger::instance(LOG_PATH.$this->ERP, KLogger::INFO);
        
    }
    
    public function getTokenValue($param = array()){
    
    }
    
    /**
     * 验证sKey,获取公司信息
     *@param string skey
     *@return array $rdata(rStatus,message,CompanyID,CompanyDatabase) 状态，提示信息，公司ID,数据库
     *@author seekfor
     */
    protected function getCompanyInfo($tocken = ''){
    
        if(empty($tocken))
        {
            $rdata['rStatus'] = 101;
            $rdata['message'] = '参数错误!';
        }else{
            $tocSql = "select 
                            CompanyID,CompanyDatabase,Status,RunStatus 
                       from 
                            ".DB_DATABASEU.DATATABLE. "_api_serial 
                       where 
                            Token='".$tocken."' 
                       limit 1";
            $cinfo = $this->DB->get_row ($tocSql);
    
            if(empty($cinfo['CompanyID'] )) {
                $rdata['rStatus'] = 101;
                $rdata['message'] = 'Key已过期，请获取最新秘钥';
            } else if($cinfo['Status'] == 'F') {
                $rdata['rStatus'] = 101;
                $rdata['message'] = '接口已停用!';
            } else if($cinfo['RunStatus'] == 'F' && $cinfo['Develop'] == 'DHB') {
                $rdata['rStatus'] = 101;
                $rdata['message'] = '接口已关闭!';
            }else{
                $rdata['rStatus']   = 100;
                $rdata['CompanyID'] = $cinfo['CompanyID'];
                $setSql = "SELECT 
                              SetValue 
                           FROM 
                              ".DB_DATABASEU.DATATABLE."_order_companyset 
                           WHERE 
                              SetCompany=".$cinfo['CompanyID']." 
                              AND SetName='erp' 
                           LIMIT 1";
                $setInfo = $this->DB->get_var($setSql);
                $rdata['setInfo'] = $setInfo ? unserialize($setInfo) : array();
                if(empty($cinfo['CompanyDatabase'])) 
                    $rdata['Database'] = DB_DATABASE.'.'; 
                else 
                    $rdata['Database'] = DB_DATABASE."_".$cinfo['CompanyDatabase'].'.';
    
                $this->LOG->setLogFilePath($cinfo['CompanyID']);
            }
            
            //如果接口状态异常，则记录SQL
            if($rdata['rStatus'] == 101) $this->LOG->logInfo(__METHOD__.' SQL ', $tocSql);
        }
        return $rdata;
    }//END getCompanyInfo
    
    protected function specification($data, $specType, $cid, $sdatabase){
        
        $data = array_filter($data);
        $data = array_unique($data);
        if(empty($data)) {
            return false;
        }
        $apos = array_map(array('ApiBase', 'strQuote'), $data);
        $sql = "SELECT 
                    SpecName 
                FROM 
                    ".$sdatabase.DATATABLE."_order_specification 
                WHERE 
                    SpecType='".$specType."' 
                    AND CompanyID=".$cid." 
                    AND SpecName IN(".implode(',', $apos).")";

        $exists = $this->DB->get_col($sql);
        $exists = $exists ? $exists : array();
        $data = array_diff($data, $exists);
        if($data){
            $header = "INSERT INTO 
                            ".$sdatabase.DATATABLE."_order_specification (
                                 SpecName,
                                 SpecType,
                                 CompanyID
                                ) VALUES";
            $body = array();
            foreach($data as $val){
                $body[] = "('{$val}','{$specType}',{$cid})";
            }
            $this->DB->query($header.implode(",", $body));
        }
    
    }
    
    /**
     * @desc 获取公司账号信息
     * @param $param (CompanyID)
     * @return array
     * @since 2016/03/19
     */
    protected function getCsInfo($param){
        $rData = array();
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['CompanyID'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '参数错误';
            $this->LOG->logInfo('getCsInfo', $param);
        }else{
            $cs_sql = "SELECT 
                            CS_Number,
                            CS_BeginDate,
                            CS_EndDate,
                            CS_SmsNumber,
                            CS_UpDate,
                            CS_UpdateTime 
                       FROM 
                            ".DB_DATABASEU.DATATABLE."_order_cs 
                       WHERE 
                            CS_Company=".$param['CompanyID'];
            $csInfo = $this->DB->get_row($cs_sql);
            if($param['debug']){
                $rData['cs_sql'] = $cs_sql;
            }
            if(empty($csInfo)){
                $this->LOG->logInfo('getCsInfo', $param);
                $rData['rStatus'] = 101;
                $rData['message'] = '数据为空';
                wlog("获取公司账号信息失败" , $cs_sql, $this->ERP);
            }else{
                $this->LOG->setLogFilePath($param['CompanyID']);
                $this->LOG->logInfo('getCsInfo', $param);
                $rData['rStatus'] = 100;
                $rData['rData']   = $csInfo;
            }
        }
        $this->LOG->logInfo('getCsInfo return', $rData);
        return $rData;
    }
    
    /**
     * 以site id为键名重组分类信息并添加当前分类的顶级分类id和名称
     *
     * @author wanjun
     * @param array $siteInfo 分类信息，数组
     * @return array 组合后的数组
     * @since 2015/11/08
     */
    protected function contribulidSite($siteInfo = array(), $needTop = true){
         
        if(empty($siteInfo)) return $siteInfo;
         
        //使用siteid创建数组索引
        $tlen        = count($siteInfo);
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
    }//END contribulidSite
    
    /**
     * @desc 检查发货单数据是否有异常
     * @param $param (sKey,clientNO,consignmentOrder,consignmentNO,...
     * array body (coding,num,color,spec,conType)
     * @return array $rData
     */
    protected function checkConsignment($param = array()) {
        $rData = array(
            'rStatus' => 100,
        );
        
        $param = json_decode(json_encode($param), true);
        $body = $param['body'];
        
        //验证基本信息
        if(empty($param['sKey']) 
           || empty($param['consignmentMan']) 
           || empty($param['consignmentOrder']) 
           ||  empty($param['body'])){
            
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
            if(empty($param['consignmentMan'])){
                $rData['message'] = '发货人不能为空!';
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
        $cid       = $cidarr['CompanyID'];
        $sdatabase = $cidarr['Database'];
        //查询订单信息获取订单ID,订单用户,收货人信息
        $param['consignmentOrder'] = str_replace('DHB', '', $param['consignmentOrder']);
        $order_sql = "SELECT 
                         OrderID,
                         OrderUserID,
                         OrderReceiveCompany,
                         OrderReceiveName,
                         OrderReceivePhone,
                         OrderReceiveAdd 
                      FROM 
                         ".$sdatabase.DATATABLE."_order_orderinfo 
                      WHERE 
                         OrderSN='".$param['consignmentOrder']."' 
                         AND OrderCompany={$cid} 
                      Limit 0,1";
        $orderInfo = $this->DB->get_row($order_sql);
        if(empty($orderInfo)){
            $rData['rStatus'] = 101;
            $rData['message'] = 'DHB'.$param['consignmentOrder'].' 订单不存在!';
            return $rData;
        }
        $orderID = $this->initParam($orderInfo['OrderID'], 'float');
        
        //验证发货单中包含的发货商品是否存在于订货宝中
        $eGoods = $nGoods = array();
        foreach($body as $i=>$val) {
            $cSql  = "SELECT 
                        ID,
                        Coding,
                        GUID,
                        Name 
                    FROM 
                        ".$sdatabase.DATATABLE."_order_content_index
                    WHERE 
                        CompanyID={$cid} 
                        AND GUID='{$val['guid']}' 
                    limit 0,1";
            
            $cInfo = $this->DB->get_row($cSql);
            
            if(empty($cInfo)) {
                wlog("发货单中包含不存在于订货宝中的商品", $this->DB->last_query, $this->ERP);
                $nGoods[] = $val['guid'];
                continue;
            }
            
            $cartInfo = array();
            $cInfo['ID']    = $this->initParam($cInfo['ID']);
            $val['conType'] = empty($val['conType']) ? 'c' : strtolower($val['conType']);
            $val['color']   = htmlentities($val['color'], ENT_COMPAT, "UTF-8");
            $val['spec']    = htmlentities($val['spec'], ENT_COMPAT, "UTF-8");
            if($val['conType'] == 'c'){
                $checkSql = "SELECT 
                                COUNT(*) AS Total 
                             FROM 
                                ".$sdatabase.DATATABLE."_order_cart 
                            WHERE 
                                CompanyID={$cid} 
                                AND OrderID={$orderID} 
                                AND ContentID={$cInfo['ID']} 
                                AND ContentColor='{$val['color']}' 
                                AND ContentSpecification='{$val['spec']}' 
                            LIMIT 0,1";
                $cartInfo = $this->DB->get_var($checkSql);
            } else if($val['conType'] == 'g'){
                $checkSqlG = "SELECT 
                                COUNT(*) AS Total 
                              FROM 
                                ".$sdatabase.DATATABLE."_order_cart_gifts 
                              WHERE 
                                CompanyID={$cid} 
                                AND OrderID={$orderID} 
                                AND ContentID={$cInfo['ID']} 
                                AND ContentColor='{$val['color']}' 
                                AND ContentSpecification='{$val['spec']}' 
                              LIMIT 0,1";
                $cartInfo = $this->DB->get_var($checkSqlG);
            }
            if(empty($cartInfo)) {
                wlog("发货订单中不包含商品" , $this->DB->last_query, $this->ERP);
                $eGoods[] = $cInfo['Name'].'(编码:'.$val['coding'].', 颜色:'.$val['color'].', 规格:'.$val['spec'].')';
            }
        }
    
        if(count($eGoods) > 0 || count($nGoods) > 0) {
            $eStr = "";
            $n    = 1;
            if(count($eGoods) > 0) {
                $eStr .= "{$n}.订单中不包含以下商品:" . implode(', ', $eGoods);
                $n++;
            }
            if(count($nGoods)) {
                $eStr .= "{$n}.系统中不包含以下商品(内码):" . implode(', ', array_unique($nGoods));
            }
            $rData['rStatus'] = 101;
            $rData['message'] = $eStr;
            echo json_encode($rData);
            return $rData;
        }
    
        return $rData;
    }//END checkConsignment
    
    /**
     * @desc 颜色规格加密
     * @param $str
     * @return mixed
     */
    protected static function CSEncode($str){
        $fp  = array('+', '/', '=', '_');
        $rp  = array('-', '|', 'DHB', ' ');
        $str = htmlentities($str , ENT_QUOTES ,"UTF-8");
        
        return str_replace($fp, $rp, base64_encode($str));
    }
    
    /**
     * 为数据添加两侧引号
     * @param string $str
     * @return string
     */
    protected static function strQuote($str = ''){
        return "'".trim($str)."'";
    }
    
    /**
     * 格式化数据类型
     * @param string $data 输入数据
     * @param string $type 要求的数据类型
     * @return 按照type格式化后的数据
     * @author wanjn
     * @since 2016/03/23
     */
    protected function initParam($data = '', $type = 'int'){
        
        $type = strtolower($type);
        switch ($type){
            case "int": 
                $data = intval($data);
            case "string": 
                $data = strval($data);
            case "float": 
                $data = floatval($data);
            case "upper":
                $data = strtoupper($data);
            case "lower":
                $data = strtolower($data);
            default: ;
        }
        return $data;
        
    }//END initParam
    
    /**
     * 过滤价格是否为有效值，大于0
     * @author wanjun
     * @return bool
     * @date 2016/03/25
     * @link http://php.net/manual/zh/function.floatval.php
     */
    protected function validPrice($price = 0){

        return $this->initParam($price, 'float') > 0;
    }//END validPrice
    
}//END ApiBase


?>