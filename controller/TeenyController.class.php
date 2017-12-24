<?php

!defined('SYSTEM_ACCESS') && exit('Access deny!');
/**
 * 天力精算 API 接口程序
 *
 * PHP version 5
 *
 * @category  PHP
 * @author    WanJun <wanjun@rsung.com>
 * @copyright 2016 Rsung
 * @version   <1.0>
 * @date	  2016/03/15
 * @todo 数据查询独立到模型里
 *
 */

class Teeny extends ApiBase{
    
    /**
     * 继承父级初始化数据
     */
    public function __construct(){
        parent::__construct(__CLASS__);
    }

    /**
     * 
     * 获取接口授权码
     * @author wanjun
     * @version 1.0
     * @see ApiBase::getTokenValue()
     * @return array 接口授权或验证信息
     */
    public function getTokenValue($param = array()){
        if(is_object($param)) $param = (array)$param;
        $this->LOG->logInfo('getTokenValue', $param);
        $param['SerialNumber']  = trim($param['SerialNumber']);
        $param['Password']      = trim($param['Password']);
        
        if(empty($param['SerialNumber']) || empty($param['Password'])){
            $rdata['rStatus'] = 101;
            $rdata['message'] = '帐号密码不能为空';
            return $rdata;
        }
        
        if(!is_filename($param['SerialNumber']) || strlen($param['SerialNumber']) < 18 || strlen($param['SerialNumber']) > 40){
            $rdata['rStatus'] = 101;
            $rdata['message'] = '请输入合法的帐号！(3-40位数字、字母和下划线)';
            return $rdata;
        }
        if(!is_filename($param['Password']) || strlen($param['Password']) < 3 || strlen($param['Password']) > 32){
            $rdata['rStatus'] = 101;
            $rdata['message'] = '请输入合法的密码！(3-32位数字、字母和下划线)';
            return $rdata;
        }
        $param['SerialNumber'] = strtolower($param['SerialNumber']);
        $param['Password']     = strtolower($param['Password']);
        
        $ruinfo = $this->DB->get_row ( "select ID,Password,Status,RunStatus,CompanyID from ".DB_DATABASEU.DATATABLE."_api_serial where SerialNumber='" . $param['SerialNumber'] . "' limit 0,1" );
        if(empty($ruinfo['ID'])){
            $rdata['rStatus'] = 101;
            $rdata['message'] = '数据不存在';
        }elseif($ruinfo['Password'] != $param['Password']){
            $rdata['rStatus'] = 101;
            $rdata['message'] = '密码不正确';
        }elseif($ruinfo['Status'] == 'F'){
            $rdata['rStatus'] = 101;
            $rdata['message'] = '该接口已停用';
        }elseif($ruinfo['RunStatus'] == 'F' && $ruinfo['Develop'] == 'DHB'){
            $rdata['rStatus'] = 101;
            $rdata['message'] = '该接口已关闭';
        }else{
            $rdata['rStatus'] = 100;
            $rdata['message'] = '';
            $token = md5 ( $ruinfo['ID'].$param['SerialNumber'] . $ruinfo['Password'].time());
            $this->DB->query ( "update ".DB_DATABASEU.DATATABLE."_api_serial set Token='".$token."' where ID=".$ruinfo['ID']." limit 1");
            
            $rdata['sKey']   = $token;
        }
        
        if(!empty($ruinfo['ID'])){
            $this->LOG->setLogFilePath($ruinfo['CompanyID']);
        }
        
        $this->LOG->logInfo('getTokenValue return', $rdata);
        return $rdata;
        
    }// END getTokenValue
    
    /**
     * @desc 上传商品分类
     * @param array $param (sKey,count,body)
     * body array(
            array('ParentID'=>0,'SiteName'=>'中性笔','SiteID'=>1),
     * )
     * body数据需排序被依赖(父ID)的数据排在前面
     * @return array $rData
     */
    public function site($param = array()){

        $rData = array('rStatus'=>100, 'message' => '商品分类上传成功', 'rData' => array());
        
        $this->LOG->logInfo("site param ", $param);
        $param = is_object($param) ? (array)$param : $param;
        
        if(empty($param['sKey'])){
            $rData['rStatus']   = 101;
            $rData['message']   = '验证key必须!';
            
        }elseif(false && empty($param['body'])){
            $rData['rStatus']   = 101;
            $rData['message']   = '未传入分类信息';
        }else{
            $cidarr = parent::getCompanyInfo($param['sKey']);
            $this->LOG->logInfo("site param ",$param);
            
            if($cidarr['rStatus']==101) return $cidarr;
            
            $cid        = $cidarr['CompanyID'];
            $sdatabase  = $cidarr['Database'];
            $body       = $param['body'] = json_decode(json_encode($param['body']), true);
            if($param['count']!=count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
                $this->LOG->logInfo(__METHOD__.' return ',($param+$rData));
                return $rData;
            }
            
            //初始化拼音程序
            include_once (SITE_ROOT_PATH."/class/letter.class.php");
            $letter  = new letter();
            $SiteIDS = array();
            $list    = array();
            foreach($body as $val){
                $val = array_map('trim', $val);
                $list[$val['siteID']] = $val;
            }
            
            //初始化层级程序
            include_once(SITE_ROOT_PATH."/class/tree.class.php");
            $tree = new Tree($list,'parentID','Name','siteID');
    
            $body = $tree->getArray();
            $body = $body ? $body : array();
            foreach($body as $key=>$val){
                $SiteIDS[] = $val['siteID'];
                $sign      = $this->DB->get_row("SELECT * FROM ".$sdatabase.DATATABLE."_api_site WHERE SiteID='{$val['siteID']}' AND CompanyID={$cid}");
                if($sign) {
                    $tsite = $this->DB->get_row("SELECT * FROM ".$sdatabase.DATATABLE."_order_site WHERE CompanyID={$cid} AND SiteID={$sign['TrueSiteID']}");
                    if(empty($tsite)) {
                        $this->DB->query("DELETE FROM ".$sdatabase.DATATABLE."_api_site WHERE SiteID='{$val['siteID']}' AND CompanyID={$cid} LIMIT 1");
                        $sign = false;
                    }
                }
                if($sign){
                    if($sign['ParentID']==$val['parentID'] && $sign['Name'] == $val['siteName']){
                        //分类信息无改动
                        continue;
                    }
                    //更改接口数据
                    $this->DB->query("UPDATE ".$sdatabase.DATATABLE."_api_site SET ParentID='{$val['parentID']}',Name='{$val['siteName']}' WHERE CompanyID={$cid} AND SiteID='{$val['siteID']}'");
                    $this->LOG->logInfo("更新api-site" , $this->DB->last_query);
                    $ParentID = "ParentID";
                    $SiteNO   = "SiteNO";
                    if($sign['ParentID']!=$val['parentID']){
                        $ParentID = $this->DB->get_var("SELECT TrueSiteID FROM ".$sdatabase.DATATABLE."_api_site WHERE CompanyID={$cid} AND SiteID='{$val['parentID']}'");
                        $SiteNO   = $this->DB->get_var("SELECT SiteNO FROM ".$sdatabase.DATATABLE."_order_site WHERE CompanyID={$cid} AND SiteID={$ParentID}");
                        $SiteNO  .= $sign['TrueSiteID'].".";
                    }
                    //更改订货宝数据
                    $this->DB->query("UPDATE ".$sdatabase.DATATABLE."_order_site SET ParentID={$ParentID},SiteNO=".($SiteNO=='SiteNO' ? 'SiteNO' : "'{$SiteNO}'").",SiteName='{$val['siteName']}',SitePinyi='".$letter->C($val['siteName'])."' WHERE CompanyID={$cid} AND SiteID={$sign['TrueSiteID']}");
    
                }else{
                    $sql_api = "INSERT INTO ".$sdatabase.DATATABLE."_api_site (SiteID,ParentID,Name,CompanyID,TrueSiteID) VALUES ('{$val['siteID']}','{$val['parentID']}','{$val['siteName']}',{$cid},0)";
                    $rData['debug']['_api_site']['insert'][] = $sql_api;
                    $this->DB->query($sql_api);
    
                    if(strlen($val['parentID'])>1 || $val['parentID']!=0){
                        $parentID = $this->DB->get_var("SELECT TrueSiteID FROM ".$sdatabase.DATATABLE."_api_site WHERE SiteID='{$val['parentID']}' AND CompanyID={$cid}");
                        $rData['debug']['ps'][] = $this->DB->last_query;
                    }else{
                        $parentID = 0;
                    }
                    $val['Pinyi'] = $letter->C($val['SiteName']);
                    $sql = "INSERT INTO ".$sdatabase.DATATABLE."_order_site (CompanyID,ParentID,SiteName,SitePinyi,Content,Disabled) VALUES ({$cid},{$parentID},'{$val['siteName']}','{$val['Pinyi']}','',0)";
                    $rData['debug']['_site'][] = $sql;
                    $this->DB->query($sql);
                    $siteID = $this->DB->insert_id;
                    $this->DB->query("UPDATE ".$sdatabase.DATATABLE."_api_site SET TrueSiteID={$siteID} WHERE SiteID='{$val['siteID']}' AND CompanyID={$cid}");
                    $siteNO = "0.".$siteID.'.';
                    if(strlen($val['parentID'])>1 || $val['parentID']!=0){
                        $siteNO = $this->DB->get_var("SELECT SiteNO FROM ".$sdatabase.DATATABLE."_order_site WHERE SiteID={$parentID} AND CompanyID={$cid}");
                        $siteNO .= $siteID.'.';
                    }
                    $this->DB->query("UPDATE ".$sdatabase.DATATABLE."_order_site SET SiteNO='{$siteNO}' WHERE CompanyID={$cid} AND SiteID={$siteID}");
                    $rData['debug']['_api_site']['update'][] = $this->DB->last_query;
                }
            }
    
            $All = $this->DB->get_col("SELECT SiteID FROM ".$sdatabase.DATATABLE."_api_site WHERE CompanyID={$cid}");
            $delIDS = array_diff($All,$SiteIDS);
            $rData['debug']['all'] = $All;
            $rData['debug']['del'] = $delIDS;
            if(count($delIDS)>0){
                $delIDS = array_map(array('ApiBase', 'strQuote'), $delIDS);
                $trueSiteIDS = $this->DB->get_col("SELECT TrueSiteID FROM ".$sdatabase.DATATABLE."_api_site WHERE CompanyID={$cid} AND SiteID IN(".implode(',',$delIDS).")");
                $this->DB->query("DELETE FROM ".$sdatabase.DATATABLE."_order_site WHERE CompanyID={$cid} AND SiteID IN(".implode(',',$trueSiteIDS).")");
                $rData['debug']['del_site'] = $this->DB->last_query;
                $this->DB->query("DELETE FROM ".$sdatabase.DATATABLE."_api_site WHERE CompanyID={$cid} AND SiteID IN(".implode(',',$delIDS).")");
                $rData['debug']['del_api_site'] = $this->DB->last_query;
            }
    
        }
        $this->LOG->logInfo("site return ", $rData);
        if(!$param['debug']){
            unset($rData['debug']);
        }
        return $rData;
    }// END site
    

    /**
     * @desc ERP获取DHB分类信息
     * @param $param (sKey)
     * @return array $rData
     * @author hxtgirq
     * @since 2015-07-22
     */
    public function getSite($param = array()) {

        $rData = array('rStatus'=>100, 'message' => '商品分类获取成功', 'rData' => array());
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
            $this->LOG->logInfo(__METHOD__.' param ', ($param+$rData));
            return $rData;
        }else{
            $cidarr = parent::getCompanyInfo($param['sKey']);
            $this->LOG->logInfo(__METHOD__.' param ', $param);
            
            if($cidarr['rStatus']==101) return $cidarr;
            
            $cid       = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
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
            $list = $this->DB->get_results($site_sql);
            $rData['debug'][] = $site_sql;
            
            if($list) {
                $rData['rStatus'] = 100;
                $rData['rData']   = $list;
            } else {
                $rData['rStatus'] = 101;
                $rData['message'] = '数据为空!';
            }
        }
        if(!$param['debug']){
            unset($rData['debug']);
        }
        $this->LOG->logInfo(__METHOD__.' return ', $rData);
        return $rData;
    }// END getSite
    
    /**
     * @desc 批量添加商品
     * @param array $param (sKey,count,body)
     * body (siteID,BrandID,name,coding,units,price1,price2,barcode,model,color,specification)
     * @return array $rData
     *
     * @history
     * 1、外码不存在，则新增商品；存在则根据外码更新内码 @20151217
     * 2、取消内码存在时，更新档案的操作 @20151217
     * 3、新增的时候，如果提供的GUID，在订货宝已经存在，只需要跳过，无需返回数据 @20160614
     */
    public function addProduct($param = array()){
        
        $param = is_object($param) ? (array)$param : $param;
        
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
            $this->LOG->logInfo('addProduct param ', ($param+$rData));
            return $rData;
        }else{
            $cidarr = parent::getCompanyInfo($param['sKey']);
            $this->LOG->logInfo('addProduct param ', $param);
            
            if($cidarr['rStatus'] == 101) return $cidarr;
                
            $cid        = $cidarr['CompanyID'];
            $sdatabase  = $cidarr['Database'];
            $body       = $param['body'] = json_decode(json_encode($param['body']),true);
            if(count($body) > 1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据只能在1000条以内!';
                return $rData;
            }
            if($param['count'] != count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
                return $rData;
            }
            
            $rData['debug']['body'] = $body;

            include_once (SITE_ROOT_PATH."/class/letter.class.php");
            $letter     = new letter();      //初始化拼音程序
            $editBody   = array();
            $readyCodig = array();    //存储已处理的编号
            $repetCodig = array();    //存储重复的编号
            $emptyCodig = array();    //存储为空的编号
            $eCoding    = array();     //添加失败的商品编码
            $emptyGuid    = array();     //添加失败的商品编码
            foreach($body as $key=>$val){
                
                //记录编号为空的商品
                if($val['coding'] == ''){
                    $emptyCodig[] = array_merge(array('message' => '商品编号为空', 'type' => 'empty'), $val);
                    continue;
                }
                //记录GUID为空的商品
                if($val['guid'] == '' || empty($val['guid'])){
                    $emptyGuid[] = array_merge(array('message' => '商品GUID为空', 'type' => 'guidempty'), $val);
                    continue;
                }
                
                //记录编号，验证本次是否重复
                if(in_array($val['coding'], $readyCodig)){
                    $repetCodig[] = array_merge(array('message' => '本次同步商品编号重复', 'type' => 'repeat'), $val);
                    continue;
                }
                $readyCodig[] = $val['coding'];
                
                
                //↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
                //验证内码是否存在，不允许内码重复
                $gckSql = "SELECT
                            COUNT(*) codeRow
                          FROM
                            ".$sdatabase.DATATABLE."_order_content_index
                          WHERE
                            CompanyID={$cid}
                            AND GUID='{$val['guid']}'";
                $guidIsExist = $this->DB->get_var($gckSql);
                if($guidIsExist){//存在则更新同步状态
                    $outSql = "UPDATE ".$sdatabase.DATATABLE."_order_content_index
                                SET
                                    ERP='T'
                                WHERE
                                    CompanyID={$cid} AND GUID='{$val['guid']}'";
                    $this->DB->query($outSql);
                    //$rData['debug'][$key]['upguid'] = $outSql;  新增的时候，如果提供的GUID，在订货宝已经存在，只需要跳过无需返回数据 tubo 2016-06-14
                    
                    continue;   //内码已存在，直接下一个
                }
                
                //验证外码是否存在，不允许编号重复
                $ckSql = "SELECT 
                            COUNT(*) codeRow 
                          FROM 
                            ".$sdatabase.DATATABLE."_order_content_index 
                          WHERE 
                            CompanyID={$cid} 
                            AND Coding='{$val['coding']}'";
                $codeIsExist = $this->DB->get_var($ckSql);
                if($codeIsExist){
                    $outSql = "UPDATE ".$sdatabase.DATATABLE."_order_content_index
                                SET
                                    ERP='T',
                                    GUID='{$val['guid']}'
                                WHERE
                                    CompanyID={$cid} AND Coding='{$val['coding']}'";
                    $this->DB->query($outSql);
                    
                    $repetCodig[] = array_merge(array('message' => '与系统商品编号重复', 'type' => 'repeat'), $val); 
                    continue;
                }
                if(isset($val['BrandID']) && !empty($val['BrandID'])) //商品品牌名称
                $BrandID   = $this->DB->get_var("SELECT BrandID FROM ".$sdatabase.DATATABLE."_order_brand WHERE CompanyID={$cid} AND BrandName='{$val['BrandID']}'");
                //↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑

                $val['name']            = mysql_real_escape_string($val['name']);      //商品名称
                $val['pinyi']           = $letter->C($val['name']);   //商品名称拼音码
                $val['siteID']          = intval($val['siteID']);    //DHB中分类ID
                $val['brandID']         = $BrandID['BrandID'] ? $BrandID['BrandID'] : 0;    //品牌在订货宝中维护
                $val['model']           = $val['model'] ? $val['model'] : '';            //型号
                $val['coding']          = mysql_real_escape_string($val['coding']);      //商品编号
                $val['barcode']         = $val['barcode'] ? $val['barcode'] : '';        //条码
                $val['price1']          = !isset($val['price1']) ? 0.00 : floatval($val['price1']);    //价格1
                $val['price2']          = !isset($val['price2']) ? 0.00 : floatval($val['price2']);    //价格2
                $val['units']           = $val['units'];     //单位
                $val['specification']   = $val['specification'] ? $val['specification'] : '';     //规格
                $val['color']           = $val['color'] ? $val['color'] : '';     //颜色
                $val['userdefined']     = $val['userdefined'] ? $val['userdefined'] : array();   //自定义字段
                $val['content']         = mysql_real_escape_string($val['content']);       //商品详情
                $val['orderID']         = $val['orderID'] ? $val['orderID'] : 500;          //排序权重
                $val['package']         = $val['package'] ? intval($val['package']) : 1;    //整包装出货量
                $val['librarydown']     = intval(@$val['librarydown']);     //库存下限
                $val['libraryup']       = intval(@$val['libraryup']);       //库存上限
                $commendID              = isset($val['commendid']) && strlen($val['commendid']) ? trim($val['commendid']) : 'default';
                $val['commendid']       = $this->productCommend[$commendID];      //商品类型
                $val['count']           = intval($val['count']);                   //热度，点击量
//              $val['contentPoint'] = $val['contentPoint'] ? $val['contentPoint'] : 0;     //积分，本期先屏蔽
                $val['casing']          = $val['contentLink'] = $val['contentKeywords'] = '';
    
                $idx_sql = "INSERT INTO 
                                ".$sdatabase.DATATABLE."_order_content_index(
                                    CompanyID,
                                    Name,
                                    Pinyi,
                                    Price1,
                                    Price2,
                                    Price3,
                                    BrandID,
                                    SiteID,
                                    Model,
                                    OrderID,
                                    Coding,
                                    Units,
                                    Barcode,
                                    CommendID,
                                    Count,
                                    FlagID,
                                    Casing,
                                    Picture,
                                    Color,
                                    Specification,
                                    LibraryDown,
                                    LibraryUp,
                                    GUID,
                                    ERP) 
                            VALUES(
                                    {$cid},
                                    '{$val['name']}',
                                    '{$val['pinyi']}',
                                    '{$val['price1']}',
                                    '{$val['price2']}',
                                    '',
                                    {$val['brandID']},
                                    {$val['siteID']},
                                    '{$val['model']}',
                                    {$val['orderID']},
                                    '{$val['coding']}',
                                    '{$val['units']}',
                                    '{$val['barcode']}',
                                    {$val['commendid']},
                                    {$val['count']},
                                    0,
                                    '{$val['casing']}',
                                    '',
                                    '{$val['color']}',
                                    '{$val['specification']}',
                                    {$val['librarydown']},
                                    {$val['libraryup']},
                                    '{$val['guid']}',
                                    'T'
                                    )";

                //若商品主档案写入成功，则继续写入详情、自动入库等
                $rData['debug'][$key]['idx'] = $idx_sql;
                if(false!==$this->DB->query($idx_sql)){
                    $inid     = $this->DB->insert_id;
                    $now      = time();
                    
                    //获取系统默认账户信息
                    $defaultUser = "SELECT UserName FROM ".DB_DATABASEU.DATATABLE."_order_user WHERE UserCompany=".$cid;
                    $userInfo    = $this->DB->get_row($defaultUser);
                    //定义系统默认操作者
                    $val['contentCreateUser'] = $val['contentEditUser'] = $userInfo['UserName'];
    
                    //增加商品自定义字段[FieldContent]
                    $_1_sql = "INSERT INTO ".$sdatabase.DATATABLE."_order_content_1(
                                    ContentIndexID,
                                    CompanyID,
                                    ContentCreateDate,
                                    ContentEditDate,
                                    ContentCreateUser,
                                    ContentEditUser,
                                    ContentLink,
                                    ContentKeywords,
                                    Content,
                                    Package,
                                    FieldContent)
                                VALUES(
                                    {$inid},
                                    {$cid},
                                    {$now},
                                    {$now},
                                    '".$val['contentCreateUser']."',
                                    '".$val['contentEditUser']."',
                                    '',
                                    '{$val['contentKeywords']}',
                                    '{$val['content']}',
                                    {$val['package']},
                                    '".($val['userdefined'] ? serialize($val['userdefined']) : '')."'
                                        )";
    
                    if(false === $this->DB->query($_1_sql) ) {
                        wlog("新增商品-content_1", $_1_sql, $this->ERP);
                    }
                    $rData['debug'][$key]['content'] = $_1_sql;
    
                    //插入空主库存
                    $manStore = "INSERT INTO 
                                    ".$sdatabase.DATATABLE."_order_number (
                                    CompanyID,
                                    ContentID,
                                    OrderNumber,
                                    ContentNumber)
                                 VALUES(
                                    {$cid},
                                    {$inid},
                                    0,
                                    0
                                    )";
                    $this->DB->query($manStore);
                    $rData['debug'][$key]['lib']['man'] = $this->DB->last_query;

                    //插入空子库存
                    if(!empty($val['color']) || !empty($val['specification'])){
                        //保存颜色
                        $this->specification(explode(',', $val['color']), 'Color', $cid, $sdatabase);
                        //保存规格
                        $this->specification(explode(',', $val['specification']), 'Specification' ,$cid, $sdatabase);
                        
                        $son    = array();
                        $color  = $val['color'] ? explode(',', $val['color']) : array('统一');
                        $spec   = $val['specification'] ? explode(',', $val['specification']) : array('统一');
                        $color  = array_map(array('ApiBase', 'CSEncode'), $color);
                        $spec   = array_map(array('ApiBase', 'CSEncode'), $spec);
                        $sql_header = "INSERT INTO 
                                            ".$sdatabase.DATATABLE."_order_inventory_number (
                                                CompanyID,
                                                ContentID,
                                                ContentColor,
                                                ContentSpec,
                                                OrderNumber,
                                                ContentNumber)
                                       VALUES ";
                        if(!empty($color) && !empty($spec)){
                            foreach($color as $v){
                                foreach($spec as $sv){
                                    $son[] = "({$cid},{$inid},'{$v}','{$sv}',0,0)";
                                }
                            }
                        }
                        $rData['debug'][$key]['lib']['son'] = $son;
                        if($son){
                            $this->DB->query($sql_header.implode(',', $son));
                            $rData['debug']['invinsert'] = $this->DB->last_query;
                        }
                    }
    
                }else{
                    $eCoding[] = array_merge(array('msg' => '新增商品失败', 'type' => 'failed'), $val);
                    wlog("新增商品失败-index", $idx_sql, $this->ERP);
                }
            }

        }
        if(!@$param['debug']){
            unset($rData['debug']);
        }
        
       if($eCoding || $repetCodig || $emptyCodig){
           $rData['rStatus'] = 101;
           $rData['message'] = '存在部分商品未添加成功，可能原因：关键属性无有效值或编号为空或编号重复或GUID为空!';
           $rData['rData']   = array_merge(
                                 array('failedcnt' => count($eCoding), 'failed' => $eCoding), 
                                 array('emptyguidcnt' => count($emptyGuid), 'emptyguid' => $emptyGuid), 
                                 array('emptycnt' => count($emptyCodig), 'emptyno' => $emptyCodig), 
                                 array('repetcodecnt' => count($repetCodig),'repetcode' => $repetCodig)
                                );
       }else{
           $rData['rStatus'] = 100;
           $rData['message'] = '商品档案上传成功';
       }
    
       $this->LOG->logInfo('addProduct return ', $rData);
       return $rData;
    }// END addProduct
    
    /**
     * @desc 批量修改商品
     * @param array $param (sKey,count,body)
     * body (siteID,BrandID,name,coding,units,price1,price2,barcode,model,color,specification)
     * @return array $rData
     * //删除颜色规格时不验证库存(ERP中有库存的颜色规格不允许删除)
     * 2、根据内码进行检查，存在则更新，不存在则跳过当前  by wanjun @20151215
     * 3、更新商品档案时：外码、名称、规格型号、条码、状态发生更新 by wanjun @20151215
     */
    public function updateProduct($param = array()){
        
        $eCoding = array();
        $param = is_object($param) ? (array)$param : $param;
        
        $rData = array();  //存储返回报文
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
            $this->LOG->logInfo('updateProduct param ' , ($param+$rData));
            return $rData;
        }else{
            $cidarr = parent::getCompanyInfo($param['sKey']);
            $this->LOG->logInfo('updateProduct param ', $param);
            
            if($cidarr['rStatus'] == 101) return $cidarr;
            
            $cid       = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $body      = $param['body'] = json_decode(json_encode($param['body']), true);
            if(count($body) > 1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据只能在1000条以内!';
                $this->LOG->logInfo(__METHOD__. ' return', $rData);
                return $rData;
            }
            if($param['count'] != count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
                $this->LOG->logInfo(__METHOD__.' return ', $rData);
                return $rData;
            }
            
            $rData['debug']['body'] = $body;
            include_once (SITE_ROOT_PATH."/class/letter.class.php");
            $letter = new letter();

            $del_product = array();//待删除的商品GUID数组
            foreach($body as $key=>$val){
                if(isset($val['status']) && $val['status'] == 'F') {
                    $del_product[] = $val['guid'];
                    continue;
                }

                $cSql = "SELECT 
                            ID 
                         FROM 
                            ".$sdatabase.DATATABLE."_order_content_index
                         WHERE 
                            CompanyID={$cid} 
                            AND GUID='{$val['guid']}'";
                $contentID = $this->DB->get_var($cSql);
                if(!$contentID) continue;
    
                $setInfo = array();
                $color   = array();
                $spec    = array();

                if(isset($val['siteID']) && !empty($val['siteID'])){//商品分类
                    $setInfo[] = "SiteID='".$this->initParam($val['siteID'])."'";
                }
                if(isset($val['BrandID']) && !empty($val['BrandID'])){//商品品牌
                    $BrandID   = $this->DB->get_var("SELECT BrandID FROM ".$sdatabase.DATATABLE."_order_brand WHERE CompanyID={$cid} AND BrandName='{$val['BrandID']}'");
                    $setInfo[] = "BrandID='".$BrandID['BrandID']."'";
                }
                
                if(isset($val['name']) && !empty($val['name'])){//商品名称
                    $setInfo[] = "Name='".$val['name']."'";
                    $setInfo[] = "Pinyi='".$letter->C($val['name'])."'";
                }
                if(isset($val['units']) && !empty($val['units'])){//单位
                   $setInfo[] = "Units='".$val['units']."'";
                }
                if(isset($val['price1']) && !empty($val['price1'])){//价格1
                    $setInfo[] = "Price1=".floatval($val['price1']);
                }
                if(isset($val['price2']) && !empty($val['price2'])){//价格2
                    $setInfo[] = "Price2=".floatval($val['price2']);
                }
                if(isset($val['barcode']) && !empty($val['barcode'])){//条码
                    $setInfo[] = "Barcode='".$val['barcode']."'";
                }
                if(isset($val['model']) && !empty($val['model'])){//型号
                    $setInfo[] = "Model='".$val['model']."'";
                }
                if(isset($val['color'])){//颜色
                    $val['color'] = trim($val['color'], ',');
                    $setInfo[]    = "Color='".$val['color']."'";
                    //将颜色添加到规格颜色库
                    strlen($val['color']) && $this->specification(explode(',', $val['color']), 'Color', $cid, $sdatabase);
                }
                if(isset($val['specification'])){//尺码
                    $val['specification'] = trim($val['specification'], ',');
                    $setInfo[] = "Specification='".$val['specification']."'";
                    //将规格添加到规格颜色库
                    strlen($val['specification']) && $this->specification(explode(',', $val['specification']), 'Specification', $cid, $sdatabase);
                }
                if(isset($val['coding']) && !empty($val['coding'])){//商品编码
                    $setInfo[] = "Coding='".$val['coding']."'";
                }
                if(isset($val['librarydown'])){//库存下限
                    $setInfo[] = "LibraryDown=".intval($val['librarydown']);
                }
                if(isset($val['libraryup'])){//库存上限
                    $setInfo[] = "LibraryUp=".intval($val['libraryup']);
                }
                if(isset($val['commendid']) && !empty($val['commendid'])){//商品类型
                    $commendID        = strlen($val['commendid']) ? trim($val['commendid']) : 'default';
                    $val['commendid'] = $this->productCommend[$commendID];      //商品类型
                    $setInfo[]        = "CommendID=".intval($val['commendid']);
                }
    
                //启用ERP接口后，只有ERP可以更新，状态为T
                $setInfo[] = "ERP='T'";
    
                $u_idx_sql = "UPDATE 
                                ".$sdatabase.DATATABLE."_order_content_index 
                              SET 
                                    ".implode(",", $setInfo)." 
                              WHERE 
                                    CompanyID={$cid} 
                                    AND GUID='{$val['guid']}' 
                              limit 1";
                
                $rData['debug'][$key]['index'] = $u_idx_sql;
                $rst = $this->DB->query($u_idx_sql);
                
                if($rst === false){
                    $eCoding[] = $val;
                    wlog("更新商品失败-index", $u_idx_sql, $this->ERP);
                }else{
                    
                    if(!empty($val['color']) || !empty($val['specification']))
                    {
                        $val['color'] = $val['color'] ? $val['color'] : '统一';
                        $color = array_map(array('ApiBase', 'CSEncode'), explode(',', $val['color']));
                         
                        $val['specification'] = $val['specification'] ? $val['specification'] : '统一';
                        $spec = array_map(array('ApiBase', 'CSEncode'), explode(',', $val['specification']));
                        
                        $storeSql = "SELECT
                                        ContentColor,ContentSpec
                                    FROM
                                        ".$sdatabase.DATATABLE."_order_inventory_number
                                    where
                                        CompanyID=".$cid."
                                        and ContentID=".$contentID." ";
                        $numarr = $this->DB->get_results($storeSql);

                        $colory = array();
                        $specy  = array();
                        if(!empty($numarr)){
                            foreach($numarr as $nv){
                                $colory[] = $nv['ContentColor'];
                                $specy[]  = $nv['ContentSpec'];
                                if(!in_array($nv['ContentColor'], $color)){
                                    $delColorSql = "DELETE FROM
                                                        ".$sdatabase.DATATABLE."_order_inventory_number
                                               WHERE
                                                    ContentColor='{$nv['ContentColor']}'
                                                    AND ContentID={$contentID}
                                                    AND CompanyID={$cid}";
                                    $this->DB->query($delColorSql);
                                }
    
                                if(!in_array($nv['ContentSpec'],$spec)){
                                    $delSpeSql = "DELETE FROM
                                                        ".$sdatabase.DATATABLE."_order_inventory_number
                                                  WHERE
                                                        ContentSpec='{$nv['ContentSpec']}'
                                                        AND ContentID={$contentID}
                                                        AND CompanyID={$cid}";
                                    $this->DB->query($delSpeSql);
                                }
                            }
                        }

                        foreach($color as $cv){
                            
                            if (!in_array($cv, $colory)){
                                foreach($spec as $sv){
                                    $inSql = "INSERT INTO
                                                ".$sdatabase.DATATABLE."_order_inventory_number
                                                (
                                                     CompanyID,
                                                     ContentID,
                                                     ContentColor,
                                                     ContentSpec,
                                                     OrderNumber,
                                                     ContentNumber
                                                 )
                                              VALUES 
                                                (
                                                     {$cid},
                                                     {$contentID},
                                                     '{$cv}',
                                                     '{$sv}',
                                                     0,
                                                     0
                                                 )";
                                     $this->DB->query($inSql);
                                     $rData['debug']['color'][] = $inSql;
                                }
                            }
                        }

                        foreach($spec as $sv){
                            
                            if (!in_array($sv, $specy))
                            {
                                foreach($color as $cv){
                                    $inSpeSql = "INSERT INTO
                                                    ".$sdatabase.DATATABLE."_order_inventory_number 
                                                  (
                                                        CompanyID,
                                                        ContentID,
                                                        ContentColor,
                                                        ContentSpec,
                                                        OrderNumber,
                                                        ContentNumber
                                                   )
                                                 VALUES 
                                                  (
                                                        {$cid},
                                                        {$contentID},
                                                        '{$cv}',
                                                        '{$sv}',
                                                        0,
                                                        0
                                                   )";
                                    $this->DB->query($inSpeSql);
                                    $rData['debug']['spec'][] = $inSpeSql;
                                }
                            }
                        }

                        //操作库存
                        $lib = $this->DB->get_row("SELECT
                                                    SUM(OrderNumber) as OrderNumber,
                                                    SUM(ContentNumber) as ContentNumber
                                                   FROM
                                                     ".$sdatabase.DATATABLE."_order_inventory_number
                                                   WHERE
                                                     ContentID={$contentID}
                                                     AND CompanyID={$cid}");
                        
                        $this->DB->query("UPDATE
                                             ".$sdatabase.DATATABLE."_order_number
                                          SET
                                             OrderNumber=".intval($lib['OrderNumber']).",
                                             ContentNumber=".intval($lib['ContentNumber'])."
                                        WHERE
                                        CompanyID={$cid}
                                        AND ContentID={$contentID}");
                    }else{
                        $this->DB->query("delete from ".$sdatabase.DATATABLE."_order_inventory_number where CompanyID=".$cid." and ContentID=".$contentID." ");
                    }
    
                    //修改商品自定义字段 
                    $filedSql = "update 
                                    ".$sdatabase.DATATABLE."_order_content_1 
                                 set 
                                     FieldContent='".($val['userdefined'] ? serialize($val['userdefined']) : '')."'  
                                 where 
                                     CompanyID=".$cid." 
                                     and ContentIndexID=".$contentID." 
                                 limit 1";
                    $this->DB->query($filedSql);
                    $rData['debug']['filedSql'][$contentID] = $filedSql;
                }
            }
            //执行删除商品操作
            if(!empty($del_product)) {
                $del_product = array_filter(array_unique($del_product));
                
                foreach(array_chunk($del_product, 200) as $guids) {
                    $guid_str = "'" . implode("','", $guids) . "'";
                    $dSql     = "SELECT 
                                    ID 
                                FROM 
                                    ".$sdatabase.DATATABLE."_order_content_index
                                WHERE 
                                        CompanyID={$cid} 
                                        AND GUID IN(".$guid_str.")";
                    $id_arr   = $this->DB->get_col($dSql);
                    $id_arr   = $id_arr ? $id_arr : array();
                    $id_arr[] = 0;
                    $del_sql  = "DELETE FROM 
                                    ".$sdatabase.DATATABLE."_order_content_index 
                                WHERE 
                                    CompanyID={$cid} 
                                    AND GUID IN(".$guid_str.")";
                    $del_sql_1 = "DELETE FROM 
                                    ".$sdatabase.DATATABLE."_order_content_1 
                                  WHERE 
                                     CompanyID={$cid} 
                                     AND ContentID IN(".implode(",", $id_arr).")";
                    //删除
                    if(false === $this->DB->query($del_sql)) {
                        wlog("删除商品数据失败 - index", $del_sql, $this->ERP);
                    }
                    if(false === $this->DB->query($del_sql_1)) {
                        wlog("删除商品数据失败 - content_1" , $del_sql_1, $this->ERP);
                    }
                    $this->LOG->logInfo("del_index - sql : " , $del_sql);
                    $this->LOG->logInfo("del_content - sql : " , $del_sql_1);
                }
            }
        }
        
        $this->LOG->logInfo('updateProduct return ', $rData);
        if(!$param['debug']){
            unset($rData['debug']);  //临时关闭
        }
       if($eCoding){
           $rData['rStatus'] = 101;
           $rData['message'] = '部分商品未修改成功';
           $rData['rData']   = $eCoding;
       }else{
           $rData['rStatus'] = 100;
           $rData['message'] = '商品档案更新成功';
       }

        return $rData;
    }//END 
    
    
    /**
     * @desc 同步地区
     * @param array $param (sKey,count,body)
     * body array(
     array(areaID,parentID,Name),
     * )
     * @return array $rData
     */
    public function area($param = array()){
        
        $param = is_object($param) ? (array)$param : $param;
        
        $rData = array();   //存储返回报文
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
            $this->LOG->logInfo(__METHOD__.' param ', ($param+$rData));
            return $rData;
        }else{
            $cidarr = parent::getCompanyInfo($param['sKey']);
            $this->LOG->logInfo(__METHOD__.' param ', $param);
            
            if($cidarr['rStatus'] == 101) return $cidarr;
    
            $cid       = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $body      = $param['body'] = json_decode(json_encode($param['body']), true);
            
            if($param['count'] != count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
//                 $this->LOG->logInfo(__METHOD__.' return ', $rData);
                return $rData;
            }
            
            include_once (SITE_ROOT_PATH."/class/letter.class.php");
            $letter  = new letter();
            $AreaIDS = array();
            foreach($body as $key=>$val){
                $AreaIDS[] = $val['areaID'];
                $sign = $this->DB->get_row("SELECT * FROM ".$sdatabase.DATATABLE."_api_area WHERE CompanyID={$cid} AND AreaID='{$val['areaID']}'");

                if($sign) {
                    $tsite = $this->DB->get_row("SELECT * FROM ".$sdatabase.DATATABLE."_order_area WHERE AreaCompany={$cid} AND AreaID={$sign['TrueAreaID']}");
                    if(empty($tsite)) {
                        $this->DB->query("DELETE FROM ".$sdatabase.DATATABLE."_api_area WHERE AreaID='{$val['areaID']}' AND CompanyID={$cid} LIMIT 1");
                        $sign = false;
                    }
                }
                if($sign){
                    if($sign['AreaParentID'] == $val['parentID'] && $sign['AreaName'] == $val['name']){
                        //地区信息无改动
                        continue;
                    }
                    
                    $updateApiArea = "UPDATE 
                                            ".$sdatabase.DATATABLE."_api_area 
                                      SET 
                                            AreaParentID='{$val['parentID']}',
                                            AreaName='{$val['name']}'
                                      WHERE 
                                            CompanyID={$cid} 
                                            AND AreaID='{$val['areaID']}' ";
                    $this->DB->query($updateApiArea);
                    
//                     $areaParentID = "AreaParentID";
                    $areaParentID = "0";
                    if($sign['AreaParentID'] != $val['parentID']){
                        $areaPidSql   = "SELECT 
                                            TrueAreaID 
                                        FROM 
                                            ".$sdatabase.DATATABLE."_api_area 
                                        WHERE 
                                            CompanyID={$cid} 
                                            AND AreaID='{$val['parentID']}'";
                        $areaParentID = $this->DB->get_var($areaPidSql);
                    }
                    
                    //更新DHB中地区档案
                    $updateArea = "UPDATE 
                                        ".$sdatabase.DATATABLE."_order_area 
                                   SET 
                                        AreaName='{$val['name']}',
                                        AreaPinyi='".$letter->C($val['name'])."',
                                        AreaParentID={$areaParentID} 
                                   WHERE 
                                        AreaCompany={$cid} 
                                        AND AreaID={$sign['TrueAreaID']}";
                    $this->DB->query($updateArea);
    
                }else{
                    $inserApiArea = "INSERT INTO 
                                        ".$sdatabase.DATATABLE."_api_area (
                                        AreaID,
                                        AreaParentID,
                                        AreaName,
                                        TrueAreaID,
                                        CompanyID) 
                                    VALUES (
                                        '{$val['areaID']}',
                                        '{$val['parentID']}',
                                        '{$val['name']}',
                                        0,
                                        {$cid})";
                    $this->DB->query($inserApiArea);
                    $rData['debug']['insert_api_area'][] = $this->DB->last_query;
                    
                    $parentID = 0;
                    if($val['parentID'] !== 0){
                        $parentID = $this->DB->get_var(
                                                "SELECT 
                                                        TrueAreaID 
                                                  FROM 
                                                        ".$sdatabase.DATATABLE."_api_area 
                                                  WHERE 
                                                        CompanyID={$cid} 
                                                        AND AreaID='{$val['parentID']}'"
                                                   );
                        $rData['debug']['parent'][] = $this->DB->last_query;
                    }
    
                    //写入地区档案
                    $insertArea = "INSERT INTO 
                                        ".$sdatabase.DATATABLE."_order_area (
                                            AreaCompany,
                                            AreaParentID,
                                            AreaName,
                                            AreaPinyi,
                                            AreaAbout) 
                                    VALUES (
                                            '{$cid}',
                                            '{$parentID}',
                                            '{$val['name']}',
                                            '".$letter->C($val['name'])."',
                                            '')";
                    $this->DB->query($insertArea);
    
                    $areaID = $this->DB->insert_id;
                    $rData['debug']['insert_area'][] = $this->DB->last_query;
                    $this->DB->query("UPDATE ".$sdatabase.DATATABLE."_api_area SET TrueAreaID={$areaID} WHERE AreaID='{$val['areaID']}' AND CompanyID={$cid}");
                    $rData['debug']['update_trueAreaID'][] = $this->DB->last_query;
                }
            }
    
            $CurAreaIDS = $this->DB->get_col("SELECT AreaID FROM ".$sdatabase.DATATABLE."_api_area WHERE CompanyID={$cid}");
            $CurAreaIDS = empty($CurAreaIDS) ? array() : $CurAreaIDS;
            $delIDS     = array_diff($CurAreaIDS,$AreaIDS);
            $rData['debug']['all'] = $AreaIDS;
            $rData['debug']['cur'] = $CurAreaIDS;
            $rData['debug']['del'] = $delIDS;
            
            if(count($delIDS) > 0){
                $delIDS = array_map(array_map(array('ApiBase', 'strQuote')), $delIDS);
                $trueIDSql = "SELECT 
                                TrueAreaID 
                              FROM 
                                ".$sdatabase.DATATABLE."_api_area 
                              WHERE 
                                CompanyID={$cid} 
                                AND AreaID IN(".implode(',',$delIDS).")";
                $delTrueAreaIDS = $this->DB->get_col($trueIDSql);
                
                $this->DB->query("DELETE FROM ".$sdatabase.DATATABLE."_api_area WHERE CompanyID={$cid} AND AreaID IN(".implode(',',$delIDS).")");
                $rData['debug']['del_api_area'] = $this->DB->last_query;
                
                $this->DB->query("DELETE FROM ".$sdatabase.DATATABLE."_order_area WHERE AreaCompany={$cid} AND AreaID IN(".implode(',',$delTrueAreaIDS).")");
                $rData['debug']['del_area'] = $this->DB->last_query;
            }
        }
    
        $this->LOG->logInfo(__METHOD__.' return ', $rData);
        if(!$param['debug']){
            unset($rData['debug']);
        }
        
        $rData = array('rStatus'=> 100, 'message' => '地区档案更新成功');
        return $rData;
    }// END area
    
    /**
     * @desc ERP获取DHB地区信息(经销商分类)
     * @param $param
     * @return array $rData
     * @author hxtgirq
     * @since 2015-07-22
     */
    public function getArea($param = array()) {
        
        $rData = array('rStatus'=>100, 'message' => '地区档案获取成功', 'rData' => array());
        $param = is_object($param) ? (array)$param : $param;
        
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证sKey必须!';
//             $this->LOG->logInfo(__METHOD__.' param ', $param);
            return $rData;
        }else{
            $cidarr = parent::getCompanyInfo($param['sKey']);
            $this->LOG->logInfo(__METHOD__.' param ', $param);
            
            if($cidarr['rStatus'] == 101) $cidarr;
            
            $cid       = $cidarr['CompanyID'];
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
            $list = $this->DB->get_results($area_sql);
            $rData['debug'] = $this->DB->last_query;
            
            if($list) {
                $rData['rStatus'] = 100;
                $rData['message'] = "地区档案获取成功";
                $rData['rData']   = $list;
            } else {
                $rData['rStatus'] = 101;
                $rData['message'] = "数据为空!";
            }
        }
        if(!$param['debug']){
            unset($rData['debug']);
        }
        $this->LOG->logInfo(__METHOD__.' return ', $rData);
 
        return $rData;
    }// END getArea
    
    /**
     * @desc 批量添加经销商
     * @param array $param (sKey,count,body)
     * @return array $rData
     *
     * @history
     * 1、外码不存在，则新增经销商；存在则根据外码更新内码 @20151217
     * 2、取消内码存在时，更新档案的操作 @20151217
     * 3、新增的时候，如果提供的GUID，在订货宝已经存在，只需要跳过，无需返回数据 @20160614
     */
    public function addDealers($param = array()){

        $rData   = array('rStatus'=>100);
        $param   = is_object($param) ? (array)$param : $param;
        
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
            $this->LOG->logInfo('addDealers param ', ($param+$rData));
            return $rData;
        }else{
            $cidarr    = $this->getCompanyInfo($param['sKey']);
            if($cidarr['rStatus'] == 101) return $cidarr;
            
            $sdatabase = $cidarr['Database'];   //指定数据库
            $cid       = $cidarr['CompanyID'];  //当前供应商ID
            $this->LOG->logInfo('addDealers param ', $param);
            
            $csInfo = parent::getCsInfo(array('CompanyID' => $cid, 'debug' => @$param['debug']));
            if($csInfo['rStatus'] == 101) return $csInfo;
            
            $csInfo = $csInfo['rData'];
            $body   = $param['body'] = json_decode(json_encode($param['body']), true);
            if(count($body) > 1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据只能在1000条以内!';
                return $rData;
            }
            if($param['count'] != count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
                return $rData;
            }
            $company_sql = "SELECT CompanyPrefix FROM ".DB_DATABASEU.DATATABLE."_order_company WHERE CompanyID=".$cid;
            $companyInfo = $this->DB->get_row($company_sql);
            $prefix      = $companyInfo['CompanyPrefix'];
    
            $allowSql = "SELECT 
                            count(*) AS clientrow 
                         FROM 
                            ".DB_DATABASEU.DATATABLE."_order_dealers 
                         WHERE 
                            ClientCompany = ".$cid." 
                            and ClientFlag = 0 ";
            $dataNum = $this->DB->get_var($allowSql);
            
            //验证授权经销商数是否已用完
//             if($dataNum >= $csInfo['CS_Number']){
//                 $rData['rStatus'] = 101;
//                 $rData['message'] = '您只有 '.$csInfo['CS_Number'].' 个授权经销商， 已全部用完，请联系开发商增加授权用户';
//                 $this->LOG->logInfo('addDealers return', $rData);
//                 return $rData;
//             }
            
//             if($dataNum+count($body) > $csInfo['CS_Number']){
//                 $rData['rStatus'] = 101;
//                 $rData['message'] = '您当前剩余可用经销商数 '.($csInfo['CS_Number'] - $dataNum). ' ,不满足您当前批量添加所需经销商数量,请联系开发商增加授权用户';
//                 $this->LOG->logInfo('addDealers return', $rData);
//                 return $rData;
//             }
    
            include_once (SITE_ROOT_PATH."/class/letter.class.php");
            $letter = new letter();
    
            //将系统中的经销商编码/经销商名称读出来
            $list = $this->DB->get_results("SELECT 
                                         ClientNO,ClientCompanyName 
                                      FROM 
                                         ".$sdatabase.DATATABLE."_order_client 
                                      WHERE 
                                         ClientCompany = " . $cid);
            $noExists   = array_column($list ? $list : array(), 'ClientNO', null);
            $noExists   = array_unique(array_filter($noExists));
    
            $nameExists = array_column($list ? $list : array(), 'ClientCompanyName', null);
            $nameExists = array_unique(array_filter($nameExists));
            
            $editBody      = array();    //同步的数据中包含的已存在的经销商直接执行对应的更新操作
            $eClient       = array();    //存储验证失败的经销商档案
            $readyClientNO = array();    //存储已处理的编号
            $repetClientNO = array();    //存储重复的编号
            $emptyClientNO = array();    //编号为空
            $emptyGuid = array();        //编号为空
            foreach($body as $key=>$val){
                $isok = true;
                //记录编号，验证本次是否重复
                if(in_array($val['clientNO'], $readyClientNO)){
                    $repetClientNO[] = array_merge(array('message' => '本次同步经销商编号重复', 'type' => 'repeat'), $val);
                    $isok = false;
                }
                
                $readyClientNO[] = $val['clientNO'];    //这个位置放的不得已呀
                if(!isset($val['clientNO']) || $val['clientNO'] == ""){
                    $emptyClientNO[] = array_merge(array('message' => '经销商编号不能为空', 'type' => 'empty'), $val);
                    $isok = false;
                }
                else if(!isset($val['guid']) || $val['guid'] == "" || empty($val['guid'])){
                    $emptyGuid[] = array_merge(array('message' => '经销商guid不能为空', 'type' => 'guidempty'), $val);
                    $isok = false;
                }
                else if(!isset($val['companyname']) || empty($val['companyname'])){
                    $eClient[] = array_merge($val, array('message' => '经销商名称不能为空!'));
                    $isok = false;
                }else{
                    
                	//验证内码是否存在，不允许内码重复
                    $gckSql = "SELECT
                                 COUNT(*) codeRow
                               FROM
                                 ".$sdatabase.DATATABLE."_order_client
                               WHERE
                                 ClientCompany={$cid}
                                 AND ClientGUID='{$val['guid']}'";
                    $guidIsExist = $this->DB->get_var($gckSql);
                    if($guidIsExist){//存在则更新内码
                        $outSql = "UPDATE ".$sdatabase.DATATABLE."_order_client
                                    SET
                                        ERP='T'
                                    WHERE
                                        ClientCompany={$cid} 
                                        AND ClientGUID='{$val['guid']}'";
                        $this->DB->query($outSql);
                        //$rData['debug']['upguid'][] = $outSql; 新增的时候，如果提供的GUID，在订货宝已经存在，只需要跳过，无需返回数据 tubo 2016-06-14
                        $isok = false;
                        continue;
                    }
//                     $val['clientNO'] = mysql_real_escape_string($val['clientNO']);
                    //验证外码是否存在,不允许编号重复
                    $ckSql = "SELECT 
                                    COUNT(*) codeRow 
                              FROM 
                                    ".$sdatabase.DATATABLE."_order_client 
                              WHERE 
                                    ClientCompany={$cid} 
                                    AND ClientNO='{$val['clientNO']}'";
                    $codeIsExist = $this->DB->get_var($ckSql);
                    $rData['debug']['ckclientNO'][] = $ckSql;
                    if($codeIsExist){
                        $outSql = "UPDATE ".$sdatabase.DATATABLE."_order_client
                                    SET
                                        ERP='T',
                                        ClientGUID='{$val['guid']}'
                                    WHERE
                                        ClientCompany={$cid}
                                        AND ClientNO='{$val['clientNO']}'";
                        $this->DB->query($outSql);
                        
                        $repetClientNO[] = array_merge(array('message' => '与系统经销商编号重复', 'type' => 'repeat'), $val);
                        $isok = false;
                        continue;
                    }
                }
    
                $val['clientName']         = $prefix.'-'.$val['clientNO'];      //经销商账号要通过clientNO生成
                $val['password']           = $val['password'] ? $val['password'] : '123456';
                $val['clientLevel']        = '';//经销商等级
                $val['areaID']             = (int)$val['areaID'];    //DHB中的地区ID
                $val['clientCompanyPinyi'] = $letter->C($val['companyname']);
                $val['truename']           = $val['truename'] ? $val['truename'] : '';
                $val['email']              = $val['email'] ? $val['email'] : '';
                $val['phone']              = $val['phone'] ? $val['phone'] : '';
                $val['fax']                = $val['fax'] ?  $val['fax'] : '';
//                 $val['clientMobile']       = '';
                $val['address']            = $val['address'] ? $val['address'] : '';
                $val['about']              = $val['about'] ? $val['about'] : '';
                $val['clientShield']       = ''; //屏壁分类 本地程序操作
                $val['setprice']           = $val['setprice'] ? ucfirst($val['setprice']) : 'Price1';//默认执行价格一
                $val['percent']            = $val['percent'] ? floatval($val['percent']) : '10.00';
                $val['clientBrandPercent'] = '';//品牌折扣
                $val['clientPay']          = '';//支付类型
                $val['clientConsignment']  = '';
                $val['bankname']           = $val['bankname'] ? $val['bankname'] : '';
                $val['bankaccount']        = $val['bankaccount'] ? $val['bankaccount'] : '';
                $val['accountname']        = $val['accountname'];
                $val['invoiceheader']      = $val['invoiceheader'];
                $val['taxpayernumber']     = $val['taxpayernumber'];
                
                if(mb_strlen($val['bankname'], "utf-8") > 50) {
                    $eClient[] = array_merge($val, array('message' => '开户行名称长度超过50个汉字,请重试!'));
                    wlog("新增经销商,开户行名称已超过50个汉字" , $val, $this->ERP);
                    $isok = false;
                }
    
                //存在不满足当前要求的，继续处理下一个
                if(!$isok) continue;
                 
                if(in_array($val['clientNO'], $noExists, true)) {
                    $eClient[] = array_merge($val, array('message' => '经销商编号已使用!'));
                    wlog("经销商编号已使用" , $val, $this->ERP);
                    continue;
                }
    
                if(is_phone($val['mobile'])) {
                    //查看当前手机号是否已做为登录账号
                    $cnt = $this->DB->get_var("SELECT 
                                            COUNT(*) as cnt 
                                        FROM 
                                            ".DB_DATABASEU.DATATABLE."_order_dealers 
                                        WHERE 
                                            ClientCompany={$cid} 
                                        LIMIT 1");
                    if($cnt == 0) {
                        $val['mobile'] = $val['mobile'];
                    }
                }
                
                //写入账户数据
                $dealers_sql = "INSERT INTO 
                                    ".DB_DATABASEU.DATATABLE."_order_dealers (
                                    ClientCompany,
                                    ClientName,
                                    ClientPassword,
                                    ClientMobile) 
                                VALUES(
                                    ".$cid.",
                                    '".$val['clientName']."',
                                    '".$val['password']."',
                                    '".$val['mobile']."')";
                $rData['debug']['dealers'][$key]['main'] = $dealers_sql;
                
                if(false !== $this->DB->query($dealers_sql)){
                    $inid       = $this->DB->insert_id;
                    $client_sql = "INSERT INTO 
                                        ".$sdatabase.DATATABLE."_order_client(
                                    ClientID,
                                    ClientCompany,
                                    ClientLevel,
                                    ClientArea,
                                    ClientName,
                                    ClientCompanyName,
                                    ClientCompanyPinyi,
                                    ClientNO,
                                    ClientTrueName,
                                    ClientEmail,
                                    ClientPhone,
                                    ClientFax,
                                    ClientMobile,
                                    ClientAdd,
                                    ClientAbout,
                                    ClientDate,
                                    ClientShield,
                                    ClientSetPrice,
                                    ClientPercent,
                                    ClientBrandPercent,
                                    ClientPay,
                                    ClientConsignment,
                                    AccountName,
                                    BankName,
                                    BankAccount,
                                    InvoiceHeader,
                                    TaxpayerNumber,
                                    ClientGUID,
                                    ERP)
                                 VALUES(
                                    ".$inid.",
                                    ".$cid.",
                                    '".$val['clientLevel']."',
                                    ".$val['areaID'].",
                                    '".$val['clientName']."',
                                    '".$val['companyname']."',
                                    '".$val['clientCompanyPinyi']."',
                                    '".$val['clientNO']."',
                                    '".$val['truename']."',
                                    '".$val['email']."',
                                    '".$val['phone']."',
                                    '".$val['fax']."',
                                    '".$val['mobile']."',
                                    '".$val['address']."',
                                    '".$val['about']."',
                                    ".time().",
                                    '".$val['clientShield']."',
                                    '".$val['setprice']."',
                                    '".$val['percent']."',
                                    '".$val['clientBrandPercent']."',
                                    '".$val['clientPay']."',
                                    '".$val['clientConsignment']."',
                                    '".$val['accountname']."',
                                    '".$val['bankname']."',
                                    '".$val['bankaccount']."',
                                    '".$val['invoiceheader']."',
                                    '".$val['taxpayernumber']."',
                                    '{$val['guid']}',
                                    'T')";
                    
                    $rData['debug']['dealers'][$key]['dealers_son'] = $client_sql;
                    $booRst = $this->DB->query($client_sql);
                    if(!$booRst){
                        $this->LOG->logInfo("addDealers 错误 client-SQL:" , $client_sql);
                        wlog("添加经销商失败-client" , $client_sql, $this->ERP);
                        $this->DB->query("DELETE FROM 
                                        ".$sdatabase.DATATABLE."_order_dealers 
                                    WHERE 
                                        ClientCompany=".$cid." 
                                        AND ClientID=".$inid);
                        $eClient[] = array_merge($val, array('message' => '经销商client保存失败!'));
                    }
                    
                }else{
                    $this->LOG->logInfo("addDealers 错误 dealers-SQL:" , $dealers_sql);
                    $eClient[] = array_merge($val, array('message' => '经销商dealers保存失败!'));
                    wlog("添加经销商失败-dealers" , $dealers_sql, $this->ERP);
                }
            }
    
        }
        if(!@$param['debug']){
            unset($rData['debug']); //临时关闭
        }
        
        if($eClient || $repetClientNO || $emptyGuid || $emptyClientNO){
            $rData['rStatus'] = 101;
            $rData['message'] = '存在部分经销商未添加成功，可能原因：关键属性无有效值或编号重复!';
            $rData['rData']   = array_merge(
                                    array('failedcnt' => count($eClient), 'failed' => $eClient),
                                    array('emptyguidcnt' => count($emptyGuid), 'emptyguid' => $emptyGuid),
                                    array('emptycnt' => count($emptyClientNO), 'emptyno' => $emptyClientNO),
                                    array('repetnoecnt' => count($repetClientNO),'repetno' => $repetClientNO)
                                 );
       }else{
           $rData['message'] = '经销商档案上传成功';
       }

        $this->LOG->logInfo('addDealers return ', $rData);

        return $rData;
    }//END addDealers
    
    
    /**
     * @desc 批量修改经销商
     * @param array $param (sKey,count,body)
     * body (clientCompanyName,clientNO,clientTrueName,clientArea,bankName,status)
     * @return array $rData
     * //修改经销商未验证经销商数量
     * @deprecated
     *  1、修改经销商资料时，未修改账号
     *  2、根据内码检查是否已存在于DHB，存在则更新
     *  3、更新经销商档案时：外码、名称、状态、联系人发生更新 by wanjun @20151215
     */
    public function updateDealers($param = array()){

        $rData = array('rStatus'=>100);
        $param = is_object($param) ? (array)$param : $param;
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
            $this->LOG->logInfo('updateDealers param ', ($param+$rData));
            return $rData;
        }else{
            $cidarr    = $this->getCompanyInfo($param['sKey']);
            
            $cid       = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $this->LOG->logInfo('updateDealers param ', $param);
            if($cidarr['rStatus'] == 101) return $cidarr;
    
            $body = $param['body'] = json_decode(json_encode($param['body']),true);
            if(count($body) > 1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据只能在1000条以内!';
//                 $this->LOG->logInfo(__METHOD__. ' return', $rData);
                return $rData;
            }
            if($param['count'] != count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据传输错误!';
//                 $this->LOG->logInfo(__METHOD__.' return ', $rData);
                return $rData;
            }
            include_once (SITE_ROOT_PATH."/class/letter.class.php");
            $letter = new letter();
    
            $listSql = "SELECT 
                            ClientNO,ClientCompanyName,ClientGUID 
                        FROM 
                            ".$sdatabase.DATATABLE."_order_client 
                        WHERE 
                            ClientCompany = ".$cid;
            $list = $this->DB->get_results($listSql);
            
            $noExists = array_column($list ? $list : array(), 'ClientNO', 'ClientGUID');
            $noExists = array_unique(array_filter($noExists));
    
            $nameExists = array_column($list ? $list : array() , 'ClientCompanyName', 'ClientGUID');
            $nameExists = array_unique(array_filter($nameExists));
    
            $del_client     = array();  //存储删除的经销商
            $eClient        = array();  //存储验证失败的经销商
            $notExistGuid   = array();  //存储没有guid的档案
            foreach($body as $key=>$val){
                $setInfo = array(); //存储要更新的字段
                $dInfo   = array();
                if(isset($val['status']) && $val['status'] == 'F') {
                    $del_client[] = $val['guid'];
                    continue;
                }
                
                if(empty($val['guid'])){//更新接口中，guid必传
                    $notExistGuid[] = array_merge($val, array('message' => '缺少guid字段或该字段未传递数据'));
                    continue;
                }
                //根据内码检查，若存在则更新，不存在则跳过处理
                $ckSql = "select 
                                count(*) as total 
                          from 
                                ".$sdatabase.DATATABLE."_order_client 
                          where 
                                ClientCompany=".$cid." 
                                and ClientGUID='".$val['guid']."'";
                $ckGUID = $this->DB->get_var($ckSql);
                if(!$ckGUID) {
                    $notExistGuid[] = array_merge($val, array('message' => '系统中不存在该guid'));
                    continue;
                }
    
                /****************** 可以更新的字段验证开始 *********************/
                //基础资料
                if(!empty($val['companyname'])){//经销商名称
                    $setInfo[] = "ClientCompanyName='".$val['companyname']."'";
                    $setInfo[] = "ClientCompanyPinyi='".$letter->C($val['companyname'])."'";
                }
                if(!empty($val['clientNO'])){//经销商编号，唯一
                    $setInfo[] = "ClientNO='".$val['clientNO']."'";
                }
                if(!empty($val['areaID'])){//DHB中的经销商地区ID
                    $setInfo[] = "ClientArea=".((int)$val['areaID']);
                }
                if(!empty($val['truename'])){//联系人
                    $setInfo[] = "ClientTrueName='".$val['truename']."'";
                }
                if(!empty($val['email'])){//邮箱地址
                    $setInfo[] = "ClientEmail='".$val['email']."'";
                }
                if(!empty($val['phone'])){//电话
                    $setInfo[] = "ClientPhone='".$val['phone']."'";
                }
                if(!empty($val['fax'])){//传真
                    $setInfo[] = "ClientFax='".$val['fax']."'";
                }
                if(!empty($val['address'])){//地址
                    $setInfo[] = "ClientAdd='".$val['address']."'";
                }
                if(!empty($val['about'])){//经销商备注
                    $setInfo[] = "ClientAbout='".mysql_real_escape_string($val['about'])."'";
                }
                
                //开票信息
                if(!empty($val['accountname'])){//开户名称
                    $setInfo[] = "AccountName='".$val['accountname']."'";
                }
               if(!empty($val['bankname'])){//开户银行
                   $setInfo[] = "BankName='".$val['bankname']."'";
               }
               if(!empty($val['bankaccount'])){//银行帐号
                   $setInfo[] = "BankAccount='".$val['bankaccount']."'";
               }
               if(!empty($val['invoiceheader'])){//开票抬头
                   $setInfo[] = "InvoiceHeader='".$val['invoiceheader']."'";
               }
               if(!empty($val['taxpayernumber'])){//纳税人识别号
                   $setInfo[] = "TaxpayerNumber='".$val['invoiceheader']."'";
               }
                
//             if(!empty($val['status'])){//是否删除，删除后进入到回收站
//                 $setInfo[] = $dInfo[] = "ClientFlag=".($val['status']=='T' ? 0 : 9);
//             }
               /****************** 可以更新的字段验证结束 *********************/
               
                if(in_array($val['clientNO'], $noExists) && $val['guid'] != array_search($val['clientNO'], $noExists)) {
                    $eClient[] = array_merge($val, array('message' => '经销商编号已使用!'));
                    continue;
                }

                if(count($setInfo) == 0) {
                    //可以修改的字段一个都没有修改
                    continue;
                }
    
                $setInfo[] = "ERP='T'";     //根据内码更新档案，则更新状态为已同步
                if($setInfo && $val['guid'] != ""){
                    //根据内码更新经销商档案
                    $rst = $this->DB->query("UPDATE 
                                            ".$sdatabase.DATATABLE."_order_client 
                                       SET 
                                            ".implode(',',$setInfo)." 
                                       WHERE 
                                            ClientCompany={$cid} 
                                            AND ClientGUID='{$val['guid']}'");
                    $rData['debug']['guid']['update'][] = $update_client_sql = $this->DB->last_query;
                    
                    if(count($dInfo) > 0) {
                        $drst = $this->DB->query("UPDATE 
                                                ".DB_DATABASEU.DATATABLE."_order_dealers 
                                            SET 
                                                ".implode(',',$dInfo)." 
                                            WHERE 
                                                ClientID IN(
                                                    SELECT 
                                                        ClientID 
                                                    FROM 
                                                        ".$sdatabase.DATATABLE."_order_client
                                                    WHERE 
                                                        ClientCompany={$cid} 
                                                        AND ClientGUID='{$val['guid']}')");
                        $rData['debug']['dealers'][] = $this->DB->last_query;
                        if($drst === false) {
                            wlog("更新经销商失败-dealers" , $this->DB->last_query, $this->ERP);
                        }
                    }
    
                    if($rst===false){
                        $eClient[] = array_merge($val, array('message'=> '修改失败!'));
                        wlog("更新经销商失败-client", $update_client_sql, $this->ERP);
                    }
                    $rData['debug']['sql'][] = $this->DB->last_query;
                }
    
            }
            
            if(!empty($del_client)) {
                $client_guid_str = "'" . implode("','", $del_client) . "'";
                $client_id = $this->DB->get_col("SELECT 
                                                    ClientID 
                                                  FROM 
                                                    ".$sdatabase.DATATABLE."_order_client 
                                                  WHERE 
                                                        ClientCompany={$cid} 
                                                        AND ClientGUID IN(".$client_guid_str.")");
                $client_id   = $client_id ? $client_id : array();
                $client_id[] = 0;
    
                $client_id_str   = implode(",", $client_id);
                $dealers_del_sql = "DELETE FROM 
                                        ".DB_DATABASEU.DATATABLE."_order_dealers 
                                    WHERE 
                                        ClientID IN({$client_id_str})";
                $client_del_sql = "DELETE FROM 
                                        ".$sdatabase.DATATABLE."_order_client 
                                   WHERE 
                                        ClientCompany={$cid} 
                                        AND ClientID IN({$client_id_str})";
                
                $this->LOG->logInfo("del-dealers-sql", $dealers_del_sql);
                $this->LOG->logInfo("del-client-sql", $client_del_sql);
                //删除Dealers
                $ddrst = $this->DB->query($dealers_del_sql);
                //删除Client
                $dcrst = $this->DB->query($client_del_sql);
    
                if($ddrst === false) {
                    wlog("删除经销商失败-dealers" , $dealers_del_sql, $this->ERP);
                }
                if($dcrst === false) {
                    wlog("删除经销商失败-client" , $client_del_sql, $this->ERP);
                }
    
            }
        }
    
        //合并SQL操作失败或GUID不存在的档案
        $eClient = array_merge($eClient, $notExistGuid);
       if($eClient){
           $rData['rStatus'] = 101;
           $rData['message'] = '部分经销商档案修改失败!';
           $rData['rData']   = $eClient;
       }else{
            $rData['rStatus'] = 100;
            $rData['message'] = '经销商档案修改成功';
        }
        $this->LOG->logInfo('updateDealers return ', $rData);
        
        if(!$param['debug']){
            unset($rData['debug']);  //临时关闭
        }
        return $rData;
    }//END updateDealers
    
    /**
     * 获取订单列表
     *@param array $param(sKey,flag,begin,step) key,起始值，步长
     *@return array $rdata(rStatus,message,rData) 状态，提示信息，数据
     *@author seekfor
     */
    public function getOrderList($param = array()){
        if(is_object($param)) $param = (array)$param;
        
        if (empty ( $param['sKey'] )){
            $rdata['rStatus'] = 101;
            $rdata['message'] = '验证key必需';
//             $this->LOG->logInfo('getOrderList', $param);
            return $rData;
        }else{
            $cidarr    = $this->getCompanyInfo($param['sKey']); //取公司ID,Database
            $cid       = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            
            $this->LOG->logInfo('getOrderList', $param);
            if($cidarr['rStatus'] == "101"){
                return $cidarr;
                
            }else{
    
                $flag_where = "";
                $param['flag'] = trim($param['flag']);
                //统一使用 OrderPayStatus 作为付款状态
                if(isset($param['flag']) && $param['flag'] == 'receivables'){
                    $flag_where .= " AND o.OrderPayStatus=2";
                }
                else if(isset($this->orderStatus[$param['flag']])) {
                    $flag_where .= " AND o.OrderStatus=" . $this->orderStatus[$param['flag']];
                }
                //获取全部订单还是未同步的订单
                if(empty($param['type']) || strtolower($param['type']) == 'unsync'){
                    $flag_where .= " AND o.OrderApi='F' ";
                }
                
                //时间搜索
                $timeSearch = $param['starttime'] ? " AND o.OrderDate>=".strtotime($param['starttime']) : '';
                $timeSearch .= $param['endtime'] ? " AND o.OrderDate<=".strtotime($param['endtime']) : '';
                
                $setInfo = $cidarr['setInfo'];
                $this->LOG->logInfo('setInfo', $setInfo);
                $check = $setInfo['erp_order_check'] == 'Y' ? '1' : '0,1';
                $check .= ",2,3,5,7";
                $sql    = "SELECT 
                                o.OrderSN,
                                o.DeliveryDate,
                                o.OrderRemark,
                                o.OrderTotal,
                                o.OrderStatus,
                                o.OrderDate,
                                o.OrderType,
                                o.OrderSendType,
                                o.OrderApi,
                                c.lastOrderAt,
                                c.ClientGUID AS guid,
                                c.ClientNO
                            FROM 
                                ".$sdatabase.DATATABLE."_order_orderinfo as o 
                            LEFT JOIN 
                                ".$sdatabase.DATATABLE."_order_client as c 
                            ON c.ClientID=o.OrderUserID 
                            WHERE 
                                o.OrderCompany=".$cid." 
                                AND o.OrderStatus IN ({$check}) 
                                {$flag_where} {$timeSearch} ";
                $sql .= " limit ".$param['begin'].",".intval($param['step']);
                
                if($param['debug']) {
                    $rdata['rDebug']['SQL'] = $sql;
                }
                $oinfo  = $this->DB->get_results( $sql );
                $this->LOG->logInfo('getOrderList sql', $sql);
    
                if(empty($oinfo)) {
                    $rdata['rStatus'] = 101;
                    $rdata['message'] = '未查询出相关订单';
                    wlog("获取订单列表数据为空或异常" , $sql, $this->ERP);
                }else{
                    
                    $flipOrderStatus = array_flip($this->orderStatus);  //交换订单状态键/值
                    foreach($oinfo as $key=>$val){
                        $oinfo[$key]['OrderSN'] = parent::DHB_ORDER.$val['OrderSN'];
                        $oinfo[$key]['OrderStatus'] = $flipOrderStatus[$val['OrderStatus']];
                    }

                    $rdata['rStatus'] = 100;
                    $rdata['rTotal']  = count($oinfo);
                    $rdata['message'] = '获取订单列表成功';
                    $rdata['rData']   = $oinfo;
                }
            }
        }

        $this->LOG->logInfo('getOrderList return', $rdata);
        return $rdata;
    }//END getOrderList
    
    /**
     * 获取订单明细
     *@param array $param(sKey,orderSn) key,订单号
     *@return array $rdata(rStatus,message,rData) 状态，提示信息，数据
     *@author seekfor
     */
    public function getOrderContent($param = array()){
        
        $param = is_object($param) ? (array)$param : $param;
        $param['orderSn'] = str_replace(array(parent::DHB_ORDER),'',$param['orderSn']);
        
        if (empty( $param['sKey'] )){
            $rdata['rStatus'] = 101;
            $rdata['message'] = '验证key必需';
//             $this->LOG->logInfo('getOrderContent', $param);
            return $rData;
        } else if( empty($param['orderSn'])){
            $rdata['rStatus'] = 101;
            $rdata['message'] = '订单号不能为空!';
//             $this->LOG->logInfo('getOrderContent', $param);
            return $rData;
        }else{
            $cidarr    = $this->getCompanyInfo($param['sKey']); //取公司ID,Database
            $cid       = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $this->LOG->logInfo('getOrderContent', $param);
            
            if($cidarr['rStatus'] == "101"){
                return $cidarr;
            }else{
                
                //取单头
                $sql    = "select 
                                o.OrderID,
                                o.OrderSN,
                                o.OrderSendType,
                                o.InvoiceTax,
                                o.DeliveryDate,
                                o.OrderRemark,
                                o.OrderTotal,
                                o.OrderStatus,
                                o.OrderDate,
                                c.ClientNO,
                                c.ClientGUID as guid,
                                o.OrderApi,
                                o.OrderReceiveName,
                                o.OrderReceiveCompany,
                                o.OrderReceivePhone,
                                o.OrderReceiveAdd 
                           from 
                                ".$sdatabase.DATATABLE."_order_orderinfo o 
                           left join 
                                ".$sdatabase.DATATABLE."_order_client c 
                           ON o.OrderUserID=c.ClientID 
                           where 
                              o.OrderCompany=".$cid." 
                              and o.OrderSN='".$param['orderSn']."' 
                              and c.ClientCompany=".$cid." 
                           limit 0,1";
                $ckOrder = $oinfo = $this->DB->get_row ( $sql );
                $oinfo['OrderID'] = intval($oinfo['OrderID']);
    
                $sqls   = "select 
                                Name 
                           from 
                                ".$sdatabase.DATATABLE."_order_ordersubmit 
                           where 
                                CompanyID=".$cid." 
                                and OrderID=".$oinfo['OrderID']." 
                                and Status='审核订单' 
                           limit 1";
                $sinfo  = $this->DB->get_row ( $sqls );
                $oinfo['AdminUser'] = $sinfo['Name'];
                
                //商品明细
                $sqlc   = "select 
                                SiteID,
                                GUID as guid,
                                Name,
                                Coding,
                                Units,
                                ContentColor,
                                ContentSpecification,
                                ContentPrice,
                                ContentNumber,
                                ContentPercent,
                                'c' as conType 
                           from 
                                ".$sdatabase.DATATABLE."_view_index_cart 
                           where 
                                CompanyID=".$cid."
                                and OrderID=".$oinfo['OrderID'];
                $cinfo  = $this->DB->get_results ( $sqlc );
                
                //赠品明细
                $sqlg   = "select 
                                SiteID,
                                GUID as guid,
                                Name,
                                Coding,
                                Units,
                                ContentColor,
                                ContentSpecification,
                                '0' as ContentPrice,
                                ContentNumber,
                                'g' as conType 
                           from 
                                ".$sdatabase.DATATABLE."_view_index_gifts 
                           where 
                                CompanyID=".$cid." 
                                and OrderID=".$oinfo['OrderID']." ";
                $ginfo = $this->DB->get_results($sqlg);
    
                //商品分类
                $sqlSites = "SELECT 
                                site.SiteID,
                                site.SiteNO,
                                site.SiteName,
                                apisite.SiteID AS ApiSiteID 
                            FROM 
                                ".$sdatabase.DATATABLE."_order_site AS site 
                            LEFT JOIN 
                                ".$sdatabase.DATATABLE."_api_site AS apisite 
                            ON site.CompanyID=apisite.CompanyID 
                               AND site.SiteID=apisite.TrueSiteID 
                            WHERE 
                               site.CompanyID=".$cid;
                $sitesInfo = $this->DB->get_results($sqlSites);
    
                //组合商品份额分类
                $relationSite = $this->contribulidSite($sitesInfo);
                $infoall = array();
                for($i=0;$i<count($cinfo);$i++){
                    $cinfo[$i]['InvoiceTax']        = $oinfo['InvoiceTax'] / 100;
                    $cinfo[$i]['ContentPercent']    = $cinfo[$i]['ContentPercent'] / 10;
                    $cinfo[$i]['SiteName']          = @$relationSite[$cinfo[$i]['SiteID']]['SiteName'];		//当前分类名称
                    $cinfo[$i]['TopSiteID']         = @$relationSite[$cinfo[$i]['SiteID']]['TopSite'];		//顶级分类ID
                    $cinfo[$i]['TopSiteName']       = @$relationSite[$cinfo[$i]['SiteID']]['TopSiteName'];	//顶级分类名称
                    $cinfo[$i]['TopSiteNO']         = @$relationSite[$cinfo[$i]['SiteID']]['ApiSiteID'];	//ERP ID
                    $infoall[] = $cinfo[$i];
                }
                for($i=0;$i<count($ginfo);$i++) {
                    $ginfo[$i]['InvoiceTax']        = 0;
                    $ginfo[$i]['ContentPercent']    = 1;
                    $ginfo[$i]['SiteName']          = @$relationSite[$ginfo[$i]['SiteID']]['SiteName'];		//当前分类名称
                    $ginfo[$i]['TopSiteID']         = @$relationSite[$ginfo[$i]['SiteID']]['TopSite'];		//顶级分类ID
                    $ginfo[$i]['TopSiteName']       = @$relationSite[$ginfo[$i]['SiteID']]['TopSiteName'];	//顶级分类名称
                    $ginfo[$i]['TopSiteNO']         = @$relationSite[$ginfo[$i]['SiteID']]['ApiSiteID'];	//ERP ID
                    $infoall[] = $ginfo[$i];
                }
    
                if(empty($ckOrder)) {
                    $rdata['rStatus'] = 101;
                    $rdata['message'] = "数据为空，获取订单[{$param['orderSn']}]表头失败";
                    wlog("获取订单[{$param['orderSn']}]表头失败", $sql, $this->ERP);
                }else{
                    $oinfo['OrderSN']     = parent::DHB_ORDER.$oinfo['OrderSN'];
                    //翻译订单状态
                    $flipOrderStatus      = array_flip($this->orderStatus);  //交换订单状态键/值
                    $oinfo['OrderStatus'] = $flipOrderStatus[$oinfo['OrderStatus']];
                    
                    //添加取过标记 通知时才更改
                    $this->DB->query("UPDATE ".$sdatabase.DATATABLE."_order_orderinfo SET OrderApi='T' WHERE OrderID=".$oinfo['OrderID']);
                    unset($oinfo['OrderID'], $oinfo['InvoiceTax']);
                    $rdata['rStatus']           = 100;
                    $rdata['rTotal']            = count($infoall);
                    $rdata['rData']['header']   = $oinfo;
                    $rdata['rData']['body']   	= $infoall;
                    if(count($infoall) == 0) {
                        wlog("获取订单[{$param['orderSn']}]明细数据失败" , array(
                            '买品' => $sqlc,
                            '赠品' => $sqlg,
                        ), $this->ERP);
                    }
                }
            }
        }
        
        $infoall && $rdata['message'] = '获取订单详情成功';

        $this->LOG->logInfo('getOrderContent return', $rdata);
        
        return $rdata;
    }//END getOrderContent
    
    /**
     * @desc 更新订单状态
     * @param array $param (sKey,count,body)
     * body array(
     array('orderSN'=>'',status=>'close|open|del'),
     * )
     * @return array $rData
     */
    public function orderStatus($param = array()){
        
        $eOrderSN = array();
        $param    = is_object($param) ? (array)$param : $param;
        
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
            $this->LOG->logInfo(__METHOD__.' param ', ($param+$rData));
            return $rData;
        }else{
            $cidarr    = parent::getCompanyInfo($param['sKey']);
            $cid       = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
//             $this->LOG->logInfo(__METHOD__.' param ',$param);
            
            if($cidarr['rStatus'] == 101) return $cidarr;
            
            $setInfo = $cidarr['setInfo'];
            $body    = $param['body'] = json_decode(json_encode($param['body']), true);
            if(count($body) > 1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据只能在1000条以内!';
//                 $this->LOG->logInfo(__METHOD__. ' return', $rData);
                return $rData;
            }
            if($param['count'] != count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '接收数据总量与原始总量不一致';
//                 $this->LOG->logInfo(__METHOD__.' return ', $rData);
                return $rData;
            }

            $rData['debug']['setInfo'] = $setInfo;
            foreach($body as $key=>$val){
                if(empty($val['orderSN'])){
                    $eOrderSN[] = array(
                        'orderSN' => '',
                        'message' => '第'.($key+1).'条订单数据缺少订单编号!',
                    );
                    continue;
                }
                if(!in_array(strtolower($val['status']), $this->allowAction)){
                    $eOrderSN[] = array(
                        'orderSN' => $val['orderSN'],
                        'message' => '未知的操作码'.$val['status'],
                    );
                    continue;
                }
                $init_sn        = $val['orderSN'];
                $val['orderSN'] = str_replace(array(parent::DHB_ORDER), '', $val['orderSN']);
                $order = $this->DB->get_row("SELECT 
                                              * 
                                        FROM 
                                              ".$sdatabase.DATATABLE."_order_orderinfo 
                                        WHERE 
                                              OrderSN='{$val['orderSN']}' 
                                              AND OrderCompany={$cid} 
                                        LIMIT 0,1");
                if(empty($order)){
                    $eOrderSN[] = array(
                        'orderSN' => $val['orderSN'],
                        'message' => '订单 '.$init_sn.' 不存在',
                    );
                    continue;
                }
                $orderStatus = null;
                $sql         = array();
                switch(strtolower($val['status'])){
                    case 'open':
                        $check = $setInfo['erp_order_check'] == 'Y' ? 1 : 0;
                        $sql[] = "UPDATE 
                                      ".$sdatabase.DATATABLE."_order_orderinfo 
                                  SET 
                                      OrderStatus={$check},
                                      OrderSendStatus={$check} 
                                  WHERE 
                                        OrderSN='{$val['orderSN']}' 
                                        AND OrderCompany={$cid}";
                        $sql[] = "INSERT INTO 
                                        ".$sdatabase.DATATABLE."_order_ordersubmit(
                                            CompanyID,
                                            OrderID,
                                            AdminUser,
                                            Date,
                                            Status,
                                            Content) 
                                   VALUES (
                                            {$cid},
                                            '{$order['OrderID']}',
                                            '接口',
                                            '".time()."',
                                            '打开订单',
                                            'ERP通过接口打开订单')";
                        break;
                    case 'close'://管理员取消
                        $sql[] = "UPDATE 
                                        ".$sdatabase.DATATABLE."_order_orderinfo 
                                  SET 
                                        OrderStatus=9 
                                  WHERE 
                                        OrderSN='{$val['orderSN']}' 
                                        AND OrderCompany={$cid}";
                        $sql[] = "INSERT 
                                        INTO ".$sdatabase.DATATABLE."_order_ordersubmit(
                                            CompanyID,
                                            OrderID,
                                            AdminUser,
                                            Date,
                                            Status,
                                            Content) 
                                 VALUES (
                                            {$cid},
                                            '{$order['OrderID']}',
                                            '接口',
                                            '".time()."',
                                            '关闭/取消订单',
                                            'ERP通过接口关闭/取消订单')";
                        break;
                    case 'del':
                        $sql[] = "UPDATE 
                                        ".$sdatabase.DATATABLE."_order_orderinfo 
                                  SET 
                                        OrderStatus=9 
                                  WHERE 
                                        OrderSN='{$val['orderSN']}' 
                                        AND OrderCompany={$cid}";
                        $sql[] = "INSERT 
                                        INTO ".$sdatabase.DATATABLE."_order_ordersubmit(
                                            CompanyID,
                                            OrderID,
                                            AdminUser,
                                            Date,
                                            Status,
                                            Content) 
                                  VALUES (
                                            {$cid},
                                            '{$order['OrderID']}',
                                            '接口',
                                            '".time()."',
                                            '删除订单',
                                            'ERP通过接口删除订单')";
                        break;
                    default:
                        break;
                }
    
                $result = array();
                foreach($sql as $item){
                    $result[] = $this->DB->query($item);
                }
                $rst = !in_array(false, $result, true);
    
                $rData['debug']['sql'][] = $sql;
                if($rst===false){
                    $eOrderSN[] = array(
                        'orderSN' => $init_sn,
                        'message' => '第'.($key+1).'条订单操作失败!',
                    );
                    wlog("订单状态更新失败" , $sql, $this->ERP);
                }
            }
        }
        if(!$param['debug']){
            unset($rData['debug']);
        }
        if(count($eOrderSN) > 0){
            $rData['rStatus'] = 101;
            $rData['message'] = '部分订单操作失败!';
            $rData['rData']   = $eOrderSN;
        }else{
            $rData['rStatus'] = 100;
            $rData['message'] = '订单状态更新成功';
        }
        $this->LOG->logInfo(__METHOD__.' return ', $rData);
        
        return $rData;
    }//END orderStatus
    
    /**
     * @desc 添加发货单
     * @param array $param(sKey,clientNO,consignmentOrder,consignmentNO,consignmentMan,consignmentRemark,body,consignmentDate)
     * array body (coding,num,color,spec,conType),..
     * @return array $rData
     */
    public function addConsignment($param = array()){
        $rData = array(
            'rStatus' =>100,
            'message' => '添加发货单成功'
        );
        
        $param = is_object($param) ? (array)$param : $param;
        
        if($param['count'] != count($param['body'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '接收数据总量与原始总量不一致';
//             $this->LOG->logInfo(__METHOD__.' return ', $rData);
            return $rData;
        }
        
        $check_result = $this->checkConsignment($param);//验证发货数据
        
        if($check_result['rStatus'] == 101) {
//             $this->LOG->logInfo('addConsignment Param ',$param);
//             $this->LOG->logInfo("验证发货数据结果" , $check_result);//记录验证结果
            //验证发货数据未通过
            return $check_result;
        }
        
        if(true){
            $cidarr    = self::getCompanyInfo($param['sKey']);
            $cid       = $cidarr['CompanyID'];  //供应商ID
            $sdatabase = $cidarr['Database'];   //所在数据库
            
            $this->LOG->logInfo('addConsignment Param ',$param);
            $this->LOG->logInfo("验证发货数据结果" , $check_result);//记录验证结果
            
            if($cidarr['rStatus']==101) return $cidarr;
            
            //查询订单信息获取订单ID,订单用户,收货人信息
            $param['consignmentOrder'] = str_replace(parent::DHB_ORDER, '', $param['consignmentOrder']);
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
            
            $orderID = $this->initParam($orderInfo['OrderID']);
            $param['orderID']               = $orderID;
            $param['consignmentLogistics']  = 0;//物流
            $param['inceptMan']             = $orderInfo['OrderReceiveName'];   //收货公司/收货人
            $param['inceptArea']            = '';
            $param['consignmentNO']         = $param['consignmentNO'] ? $param['consignmentNO'] : '-';  //运单号
            $param['inceptAddress']         = $orderInfo['OrderReceiveAdd'];    //收货人地址
            $param['inceptCompany']         = $orderInfo['OrderReceiveCompany'];    //收货人公司
            $param['inceptPhone']           = $orderInfo['OrderReceivePhone'];  //联系电话
            $param['consignmentClient']     = $orderInfo['OrderUserID'];//根据ClientNO获取当前是获取订单中的经销商ID
    
            $param['consignmentUser']       = '';
            $param['consignmentFlag']       = 0;
            $param['consignmentDate']       = $param['consignmentDate'] ? $param['consignmentDate'] : date('Y-m-d H:i');//未传发货时间默认当前时间
            $param['consignmentMoneyType']  = '1'; //默认已付
            $param['consignmentMoney']      = 0;//运费
            
            //发货单SQL
            $consignment_sql = "INSERT INTO 
                                    ".$sdatabase.DATATABLE."_order_consignment(
                                        ConsignmentCompany,
                                        ConsignmentClient,
                                        ConsignmentOrder,
                                        ConsignmentLogistics,
                                        ConsignmentNO,
                                        ConsignmentMan,
                                        ConsignmentDate,
                                        ConsignmentRemark,
                                        ConsignmentMoneyType,
                                        ConsignmentMoney,
                                        InceptMan,
                                        InceptArea,
                                        InceptAddress,
                                        InceptCompany,
                                        InceptPhone,
                                        InputDate,
                                        ConsignmentUser,
                                        ConsignmentFlag)
                                 VALUES(
                                        {$cid},
                                        {$param['consignmentClient']},
                                        '{$param['consignmentOrder']}',
                                        {$param['consignmentLogistics']},
                                        '{$param['consignmentNO']}',
                                        '{$param['consignmentMan']}',
                                        '{$param['consignmentDate']}',
                                        '{$param['consignmentRemark']}',
                                        '{$param['consignmentMoneyType']}',
                                        {$param['consignmentMoney']},
                                        '{$param['inceptMan']}',
                                        '{$param['inceptArea']}',
                                        '{$param['inceptAddress']}',
                                        '{$param['inceptCompany']}',
                                        '{$param['inceptPhone']}',
                                        ".time().",
                                        '{$param['consignmentUser']}',
                                        {$param['consignmentFlag']})";
    
            if(false !== $this->DB->query($consignment_sql)){
                $consignmentID = $this->DB->insert_id;
                $body = $param['body'] = json_decode(json_encode($param['body']), true);
    
                $moreSend = array();    //存储超发的数据
                foreach($body as $i=>$val){
                    $val['num'] = $this->initParam($val['num']);
                    $cInfo = $this->DB->get_row("SELECT 
                                                    ID,
                                                    Coding 
                                               FROM 
                                                    ".$sdatabase.DATATABLE."_order_content_index 
                                               WHERE 
                                                    CompanyID={$cid} 
                                                    AND GUID='{$val['guid']}' 
                                               LIMIT 0,1" );
                    $rData['debug']['content'][] = $this->DB->last_query;
                    
                    $cartInfo = array();
                    $val['conType'] = empty($val['conType']) ? 'c' : strtolower($val['conType']);
                    $val['color']   = htmlentities($val['color'], ENT_COMPAT, "UTF-8");
                    $val['spec']    = htmlentities($val['spec'], ENT_COMPAT, "UTF-8");
                    $val['conType'] = $this->initParam($val['conType'], 'lower');
                    if($val['conType'] == 'c'){
                        $sqlC = "SELECT 
                                    ID,
                                    ContentID,
                                    ContentNumber,
                                    ContentSend,
                                    ContentColor,
                                    ContentSpecification 
                                FROM 
                                    ".$sdatabase.DATATABLE."_order_cart 
                                WHERE 
                                    CompanyID={$cid} 
                                    AND OrderID={$orderID} 
                                    AND ContentID={$cInfo['ID']} 
                                    AND ContentColor='{$val['color']}' 
                                    AND ContentSpecification='{$val['spec']}' 
                                LIMIT 0,1";
                        $cartInfo = $this->DB->get_row($sqlC);
                    }else if($val['conType'] == 'g'){
                        $sqlG = "SELECT 
                                     ID,
                                     ContentID,
                                     ContentNumber,
                                     ContentSend,
                                     ContentColor,
                                     ContentSpecification 
                                FROM 
                                     ".$sdatabase.DATATABLE."_order_cart_gifts 
                                WHERE 
                                     CompanyID={$cid} 
                                     AND OrderID={$orderID} 
                                     AND ContentID={$cInfo['ID']} 
                                     AND ContentColor='{$val['color']}' 
                                     AND ContentSpecification='{$val['spec']}' 
                                LIMIT 0,1";
                        $cartInfo = $this->DB->get_row($sqlG);
                    }
                    
                    //校验发货数量，不能大于DHB中的订购数
                    if(($cartInfo['ContentSend']+$val['num']) > $cartInfo['ContentNumber']){//已超过订购数量
                        $needNum = $cartInfo['ContentNumber'] - $cartInfo['ContentSend'];
                        $more = '已发数量：'.$cartInfo['ContentSend'];
                        $more .= '，本次同步数量：'.$val['num'];
                        $more .= '， 超发数量：'.($val['num']-$needNum);
                        $val['more'] = $more;
                        
                        $moreSend[]  = $val;
                        $val['num']  = $needNum;
                    }
    
                    $cartID    = $this->initParam($cartInfo['ID']);
                    $contentID = $this->initParam($cInfo['ID']);
                    $outSql ="INSERT INTO 
                                ".$sdatabase.DATATABLE."_order_out_library (
                                    CompanyID,
                                    ConsignmentID,
                                    OrderID,
                                    CartID,
                                    ContentID,
                                    ContentNumber,
                                    ConType) 
                              VALUES(
                                    {$cid},
                                    {$consignmentID},
                                    {$orderID},
                                    {$cartID},
                                    {$contentID},
                                    {$val['num']},
                                    '{$val['conType']}')";
                                    
                    $rData['debug']['out_sql'][] = $outSql;
                    if(false !== $this->DB->query($outSql)){
                        //更新商品发货数量
                        if($val['conType'] == 'c'){
                            $cartUSql = "UPDATE 
                                            ".$sdatabase.DATATABLE."_order_cart 
                                        SET 
                                            ContentSend=ContentSend+{$val['num']} 
                                        WHERE 
                                            CompanyID={$cid} 
                                            AND OrderID={$orderID} 
                                            AND ContentID={$contentID} 
                                            AND ContentColor='{$val['color']}' 
                                            AND ContentSpecification='{$val['spec']}'";
                        }else if($val['conType'] == 'g'){
                            $cartUSql = "UPDATE 
                                            ".$sdatabase.DATATABLE."_order_cart_gifts 
                                         SET 
                                            ContentSend=ContentSend+{$val['num']} 
                                         WHERE 
                                            CompanyID={$cid} 
                                            AND OrderID={$orderID} 
                                            AND ContentID={$contentID} 
                                            AND ContentColor='{$val['color']}' 
                                            AND ContentSpecification='{$val['spec']}'";
                        }
    
                        if(false !== $this->DB->query($cartUSql)){
                            //更新库存代码 暂不处理库存
                        }else{
                            if(!$param['debug']){
                                unset($rData['debug']);
                            }
                            $rData['rStatus'] = 101;
                            $rData['message'] = '更新发货数不成功!';
                            $this->LOG->logInfo('addConsignment return', $rData);
                            return $rData;
                        }
                    }else{
                        $this->LOG->logInfo('addConsignment return', $rData);
                        if(!$param['debug']){
                            unset($rData['debug']);
                        }
                        $out_cnt = $this->DB->get_var("SELECT 
                                                         COUNT(*) as Total 
                                                       FROM 
                                                         ".$sdatabase.DATATABLE."_order_out_library 
                                                       WHERE 
                                                          CompanyID={$cid} 
                                                          AND ConsignmentID={$consignmentID} 
                                                       LIMIT 1");
                        if((int)$out_cnt == 0){
                            $this->DB->query("DELETE FROM ".$sdatabase.DATATABLE."_order_consignment WHERE ConsignmentID=".$consignmentID);
                        }
    
                        $rData['rStatus'] = 101;
                        $rData['message'] = '保存不成功!';
                        return $rData;
                    }
                }
    
                //处理订单状态&发货状态
                $sendline = $this->DB->get_var("select 
                                                    count(*) as allrow 
                                                from 
                                                    ".$sdatabase.DATATABLE."_order_cart 
                                                where 
                                                    ContentSend < ContentNumber 
                                                    and CompanyID = ".$cid." 
                                                    and OrderID=".$orderID."");
                $sendlineg = $this->DB->get_var("select 
                                                    count(*) as allrow 
                                                 from 
                                                    ".$sdatabase.DATATABLE."_order_cart_gifts 
                                                 where 
                                                    ContentSend < ContentNumber 
                                                    and CompanyID = ".$cid." 
                                                    and OrderID=".$orderID."");
                $orderSendStatus = ( $sendline + $sendlineg ) > 0 ? 3 : 2; //3=>未发完,2=>已发完
                $this->DB->query("UPDATE 
                                    ".$sdatabase.DATATABLE."_order_orderinfo 
                                  SET 
                                    OrderSendStatus={$orderSendStatus} 
                                  WHERE 
                                    OrderID={$orderID} 
                                    AND OrderCompany={$cid}");
                $rData['debug']['orderSendStatus'] = $this->DB->last_query;
                
                $this->LOG->logInfo('addConsignment orderSendStatus', $this->DB->last_query);
                //将当前订单由备货中改为已发货状态
                $this->DB->query("UPDATE 
                                    ".$sdatabase.DATATABLE."_order_orderinfo 
                                  SET 
                                    OrderStatus=2 
                                  WHERE 
                                    OrderID={$orderID} 
                                    AND OrderCompany={$cid} 
                                    AND OrderStatus=1");
                $rData['debug']['orderStatus'] = $this->DB->last_query;
                
                $this->LOG->logInfo('addConsignment orderStatus', $this->DB->last_query);
                $this->DB->query("INSERT INTO 
                                    ".$sdatabase.DATATABLE."_order_ordersubmit(
                                        CompanyID,
                                        OrderID,
                                        AdminUser,
                                        Name,
                                        Date,
                                        Status,
                                        Content) 
                                   VALUES (
                                        {$cid},
                                        {$orderID},
                                        '接口',
                                        '接口',
                                        ".time().",
                                        '已发货',
                                        '已添加发货单')");
    
            }else{
                $rData['message'] = '发货单保存不存功!';
                $rData['rStatus'] = 101;
                wlog("发货单保存失败" , $consignment_sql, $this->ERP);
            }
    
            $rData['debug']['order_sql']   = $order_sql;
            $rData['debug']['consignment'] = $consignment_sql;
        }
    
        $moreSend ? $rData['moreSend'] = $moreSend : '';
        if(!$param['debug']){
            unset($rData['debug']);
        }
        $this->LOG->logInfo('addConsignment return', $rData);
        
        return $rData;
    }//END addConsignment
    
    /**
     * @desc 库存同步
     * @param array $param(sKey,body)
     * body (coding,num,spec,color)
     * @return mixed
     */
    public function stock($param = array()){
        $rData = array(
            'rStatus' => 100,
            'message' => '库存数据同步成功！'
        );
        $eProduct    = array();
        $nullProduct = 0; // 记录一下传递过来的空值数量
        $param       = is_object($param) ? (array)$param : $param;
        
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '参数错误!';
//             $this->LOG->logInfo('stock param ', $param);
        }else{
            $fp = array('+', '/', '=', '_');
            $rp = array('-', '|', 'DHB', ' ');
            $cidarr    = parent::getCompanyInfo($param['sKey']);
            $cid       = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            
            $this->LOG->logInfo('stock param ', $param);
            if($cidarr['rStatus']==101) return $cidarr;
            
            $body = $param['body'] = json_decode(json_encode($param['body']), true);
            if(count($body) > 1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据只能在1000条以内!';
//                 $this->LOG->logInfo(__METHOD__.' return', $rData);
                return $rData;
            }
            if($param['count'] != count($param['body'])){
                $rData['rStatus'] = 101;
                $rData['message'] = '接收数据总量与原始总量不一致';
//                 $this->LOG->logInfo(__METHOD__.' return ', $rData);
                return $rData;
            }
            
            foreach($body as $k=>$val){
                if($val['guid'] === null || $val['guid'] == '' || strtolower($val['guid']) == 'null'){
                    $nullProduct++;
                    continue;
                }

                $cInfo = $this->DB->get_row("SELECT 
                                                  ID,
                                                  Coding 
                                               FROM 
                                                  ".$sdatabase.DATATABLE."_order_content_index 
                                               WHERE 
                                                  CompanyID={$cid} 
                                                  AND FIND_IN_SET('{$val['guid']}',CONCAT(',',GUID,','))>0");
    
                if(empty($cInfo)){
                    $eProduct[] = array(
                        'guid'     => $val['guid'],
                        'message'  => '商品不存在!',
                    );
                    continue;
                }
                $val['spec']  = $val['spec'] ? trim($val['spec'], ',') : '';
                $val['color'] = $val['color'] ? trim($val['color'], ',') : '';
    
                $cInfo['ID']  = $this->initParam($cInfo['ID']);
                
                //有颜色|规格
                if(!empty($val['spec']) || !empty($val['color'])){
                    $val['color'] = $val['color'] ? $val['color'] : '统一';
                    $val['spec']  = $val['spec'] ? $val['spec'] : '统一';

                    $color = $this->CSEncode($val['color']);
                    $spec  = $this->CSEncode($val['spec']);
                    
                    //当前商品 待发货/待审核/未发完的库存
                    $freeze_sql = "SELECT 
                                        SUM(ContentNumber - ContentSend) AS freeze 
                                   FROM 
                                        ".$sdatabase.DATATABLE."_order_cart AS c
                                   LEFT JOIN 
                                        ".$sdatabase.DATATABLE."_order_orderinfo AS o 
                                   ON o.OrderID=c.OrderID
                                   WHERE 
                                        c.CompanyID={$cid} 
                                        AND o.OrderCompany={$cid} 
                                        AND c.ContentID={$cInfo['ID']} 
                                        AND o.OrderSendStatus IN(0,1,3) 
                                        AND c.ContentColor='{$val['color']}' 
                                        AND c.ContentSpecification='{$val['spec']}'";
                    //买品未发数量
                    $freeze = $this->DB->get_var($freeze_sql);
                    $rData['debug'][] = $freeze_sql;
    
                    $freeze_gift_sql = "SELECT 
                                            SUM(ContentNumber - ContentSend) AS freeze 
                                        FROM 
                                            ".$sdatabase.DATATABLE."_order_cart_gifts AS c
                                        LEFT JOIN 
                                            ".$sdatabase.DATATABLE."_order_orderinfo AS o 
                                        ON o.OrderID=c.OrderID
                                        WHERE 
                                            c.CompanyID={$cid} 
                                            AND o.OrderCompany={$cid} 
                                            AND c.ContentID={$cInfo['ID']} 
                                            AND o.OrderSendStatus IN(0,1,3) 
                                            AND c.ContentColor='{$val['color']}' 
                                            AND c.ContentSpecification='{$val['spec']}'";
                    //赠品未发数
                    $freeze_gift = $this->DB->get_var($freeze_gift_sql);
                    $rData['debug'][] = $freeze_gift_sql;
                    
                    //当前商品可用库存
                    $allow = max(0, $val['num']-$freeze-$freeze_gift);
                    $lsql  = "UPDATE 
                                  ".$sdatabase.DATATABLE."_order_inventory_number 
                              SET 
                                  OrderNumber={$allow},
                                  ContentNumber={$val['num']} 
                              WHERE 
                                  CompanyID={$cid} 
                                  AND ContentID={$cInfo['ID']} 
                                  AND ContentColor='{$color}' 
                                  AND ContentSpec='{$spec}'";
                    
                    $this->DB->query($lsql);
                    $rData['debug'][] = $lsql;

                    //若有多规格，但是更新库存接口中没有多规格数据时不做数据更新
                    $lib = $this->DB->get_row("SELECT
                                                count(*) as total
                                               FROM
                                                 ".$sdatabase.DATATABLE."_order_inventory_number
                                               WHERE
                                                ContentID={$cInfo['ID']}
                                                AND CompanyID={$cid}");
                    
                    if($lib['total'] == 0){
                        $val['error'] = 'GUID：'.$val['guid'].'，该商品不存在多规格';
                        $eProduct['emptysc'][] = $val;
                        continue;
                    }
                    
                    //操作主库存
                    $lib = $this->DB->get_row("SELECT 
                                                SUM(OrderNumber) as OrderNumber,SUM(ContentNumber) as ContentNumber 
                                               FROM 
                                                 ".$sdatabase.DATATABLE."_order_inventory_number 
                                               WHERE 
                                                  ContentID={$cInfo['ID']} 
                                                  AND CompanyID={$cid}");

                    $this->DB->query("UPDATE 
                                         ".$sdatabase.DATATABLE."_order_number 
                                      SET 
                                         OrderNumber=".intval($lib['OrderNumber']).",
                                         ContentNumber=".intval($lib['ContentNumber'])."
                                      WHERE 
                                         CompanyID={$cid} 
                                         AND ContentID={$cInfo['ID']}");
                    $rData['debug'][] = $this->DB->last_query;

                }else{

                    //操作主库存
                    $freeze_sql = "SELECT 
                                        SUM(ContentNumber - ContentSend) AS freeze 
                                   FROM 
                                        ".$sdatabase.DATATABLE."_order_cart AS c
                                   LEFT JOIN 
                                        ".$sdatabase.DATATABLE."_order_orderinfo AS o 
                                        ON o.OrderID=c.OrderID
                                   WHERE 
                                        c.CompanyID={$cid} 
                                        AND o.OrderCompany={$cid} 
                                        AND c.ContentID={$cInfo['ID']} 
                                        AND o.OrderSendStatus IN(0,1,3)";
                    
                    //买品未发数量
                    $freeze = $this->DB->get_var($freeze_sql);
                    $rData['debug'][] = $freeze_sql;
                    $freeze_gift_sql = "SELECT 
                                            SUM(ContentNumber - ContentSend) AS freeze 
                                        FROM 
                                            ".$sdatabase.DATATABLE."_order_cart_gifts AS c
                                        LEFT JOIN 
                                            ".$sdatabase.DATATABLE."_order_orderinfo AS o 
                                            ON o.OrderID=c.OrderID
                                        WHERE 
                                            c.CompanyID={$cid} 
                                            AND o.OrderCompany={$cid} 
                                            AND c.ContentID={$cInfo['ID']} 
                                            AND o.OrderSendStatus IN(0,1,3)";
                    //赠品未发数量
                    $freeze_gift = $this->DB->get_var($freeze_gift_sql);
                    $rData['debug'][] = $freeze_gift_sql;
                    //可用库存
                    $allow = max(0, $val['num']-$freeze-$freeze_gift); //实际库存减去商品未发数再减去赠品未发数
                    $sql = "UPDATE 
                                ".$sdatabase.DATATABLE."_order_number 
                            SET 
                                OrderNumber={$allow},
                                ContentNumber={$val['num']} 
                            WHERE 
                                ContentID={$cInfo['ID']} 
                                AND CompanyID={$cid}";
                    $this->DB->query($sql);
                    $rData['debug'][] = $sql;
                }
    
            }
        }
    
        if(!$param['debug']){
            unset($rData['debug']);
        }
        if(count($eProduct)){
            $rData['rStatus'] = 101;
            $rData['message'] = '部分商品库存同步失败!';
            $rData['rData']   = $eProduct;
        }
        if($nullProduct > 0){
            $rData['null_data'] = $nullProduct;
        }
        $this->LOG->logInfo('stock return ', $rData);
        
        return $rData;
    }//END stock
    
    /**
     * 同步商品指定价。覆盖模式。接收数据中，价格不大于0的为无效数据
     * @param array $param 
     * @return $rData 接口运行数据
     * @author wanjun
     * @date 2016/03/24
     */
    public function assignPrice($param = array()){
        
        $param = is_object($param) ? (array)$param : $param;
        
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
            $this->LOG->logInfo(__METHOD__.' param ', ($param+$rData));
        }else{
            $cidarr    = parent::getCompanyInfo($param['sKey']);
            $cid       = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $this->LOG->logInfo(__METHOD__.' param ', $param);
            
            if($cidarr['rStatus']==101) return $cidarr;
            
            $body = $param['body'] = json_decode(json_encode($param['body']), true);
            if(count($body) > 1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据只能在1000条以内!';
//                 $this->LOG->logInfo(__METHOD__. ' return', $rData);
                return $rData;
            }
            if($param['count'] != count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '接收数据总量与原始总量不一致';
//                 $this->LOG->logInfo(__METHOD__.' return ', $rData);
                return $rData;
            }
            
            //开始处理数据
            $existGuid          = array(); //存储根据 商品GUID 存在的数据
            $notExistGuid       = array(); //存储根据 商品GUID 不存在的数据
            $productGuidIsNull  = array(); //存储根据 商品GUID 为空或未null的数据
            $eClient            = array(); //存储根据 经销商GUID 不存在的数据
            
            foreach($body as $k=>$val){//数据验证
                if($val['productguid'] === null 
                    || $val['productguid'] == '' 
                    || strtolower($val['productguid']) == 'null'){
                    
                    $productGuidIsNull[] = array_merge(array('error' => '商品guid不能为空'), $val);
                    continue;
                }
                
                //根据商品GUID查询是否存在于DHB中
                $productSql = "SELECT 
                                  Price3 
                               FROM 
                                  ".$sdatabase.DATATABLE."_order_content_index
                               WHERE
                                  CompanyID={$cid}
                                  AND ERP='T'
                                  AND GUID='{$val['productguid']}'
                               LIMIT 1";
                $rData['debug']['pguidc'][] = $productSql;
                
                $guidExt = $this->DB->get_row($productSql);
                if(!count($guidExt)){//不存在或未同步
                    $notExistGuid[] = array(
                            'productguid' => $val['productguid'],
                            'error'       => '该商品未同步或guid不存在于DHB中'
                        );
                    continue;
                }
                
                 //根据经销商GUID查询是否存在于DHB中
                 $priceClient = array(); //存储根据 经销商的指定价
                 $priceDel = array(); //存储清理的指定价经销商
                 foreach($val['priceset'] as $kp=>$kv){
                    $kv['price'] = $this->initParam($kv['price'], 'float');
                    $kv['price'] = sprintf("%01.2f", round($kv['price'], 2));
                    $pSql = "SELECT
                                  ClientID,
                                  '{$kv['price']}' price
                             FROM
                                  ".$sdatabase.DATATABLE."_order_client
                             WHERE
                                  ClientCompany={$cid}
                                  AND ERP='T'
                                  AND ClientGUID='{$kv['clientguid']}'";
                    $cInfo = $this->DB->get_row($pSql);
                    $rData['debug']['pcuidc'][] = $pSql;

                    if(empty($cInfo)){
                        $eClient[] = array(
                            'clientguid' => $val['priceset'][$i]['clientguid'],
                            'message'    => '经销商未同步或不存在于DHB中',
                        );
                        continue;
                    }
                    
                    if(!$this->initParam($cInfo['price'], 'float')){
                        $priceDel[$cInfo['ClientID']] = $cInfo['price'];
                    }
                    $priceClient[$cInfo['ClientID']] = $cInfo['price'];
                    
                 }//for $val['priceset']
                 
                 //处理当前商品价格
                 $assignPrice = unserialize(urldecode($guidExt['Price3']));

                 //1、已有指定价 2、没有指定价
                 //ERP数据中，价格不大于0的认为是无效数据，删除DHB中,覆盖模式
                 //天力指定价不存在为0的
//               $priceClient                = array_filter($priceClient, array('ApiBase', 'validPrice'));
//               $assignPrice['clientprice']    = array_filter($priceClient, array('ApiBase', 'validPrice'));
              
                 $assignPrice['clientprice'] = empty($assignPrice['clientprice']) ? $priceClient : ($priceClient+$assignPrice['clientprice']);
                 $assignPrice['clientprice'] = array_diff_key($assignPrice['clientprice'], $priceDel);

                 $nowPrice = urlencode(serialize($assignPrice));
                 
                 $updateSql = "UPDATE 
                                  ".$sdatabase.DATATABLE."_order_content_index 
                               SET
                                    Price3='{$nowPrice}'
                               WHERE
                                  CompanyID={$cid}
                                  AND GUID='{$val['productguid']}'
                               LIMIT 1";
                 
                 $this->DB->query($updateSql);
                 $rData['debug']['pcuidc'][] = $updateSql;
//                  $this->LOG->logInfo('assignPrice Sql', $this->DB->last_query);
                
            }//foreach $body
            
            
            if(!empty($productGuidIsNull) || !empty($notExistGuid) || !empty($eClient)){
                $rData['rStatus'] = 101;
                $rData['message'] = '存在异常数据';
                
                if($productGuidIsNull) $rData['rData']['productguidisnull'] = $productGuidIsNull;
                if($notExistGuid) $rData['rData']['productguidnotexist'] = $notExistGuid;
                if($eClient) $rData['rData']['clientguidnotexist'] = $eClient;
                
            }else{
                $rData['rStatus'] = 100;
                $rData['message'] = '经销商指定价同步成功';
            }
           
            $this->LOG->logInfo('assignPrice return ', $rData);
            
            if(!$param['debug']){
                unset($rData['debug']);
            }
            
            return $rData;
        }
        
    }//END assignPrice
    
    /**
     * 该接口本期作废 by wanjun 2016/03/25
     */
    public function synBalance($param = array()){
        
        $param = is_object($param) ? (array)$param : $param;
        
        if(empty($param['sKey'])){
            $rData['rStatus'] = 101;
            $rData['message'] = '验证key必须!';
            $this->LOG->logInfo(__METHOD__.' param ', ($param+$rData));
        }else{
            $cidarr    = parent::getCompanyInfo($param['sKey']);
            $cid       = $cidarr['CompanyID'];
            $sdatabase = $cidarr['Database'];
            $this->LOG->logInfo(__METHOD__.' param ', $param);
        
            if($cidarr['rStatus']==101) return $cidarr;
        
            $body = $param['body'] = json_decode(json_encode($param['body']), true);
            if(count($body) > 1000){
                $rData['rStatus'] = 101;
                $rData['message'] = '数据只能在1000条以内!';
//                 $this->LOG->logInfo(__METHOD__. ' return', $rData);
                return $rData;
            }
            if($param['count'] != count($body)){
                $rData['rStatus'] = 101;
                $rData['message'] = '接收数据总量与原始总量不一致';
//                 $this->LOG->logInfo(__METHOD__.' return ', $rData);
                return $rData;
            }
            
            //开始处理数据
            $clientGuidIsNull = array();    //存储guid为空的数据
            $notExistGuid     = array();    //存储guid不存在于DHB的数据
            foreach($body as $k=>$val){
                if($val['guid'] === null
                    || $val['guid'] == ''
                    || strtolower($val['guid']) == 'null'){
                
                        $clientGuidIsNull[] = array_merge(array('error' => '经销商 guid 不能为空'), $val);
                        continue;
                }
                
                //根据经销商GUID查询是否存在于DHB中
                $clientSql = "SELECT
                                  count(*) as 
                               FROM
                                  ".$sdatabase.DATATABLE."_order_client
                               WHERE
                                  CompanyID={$cid}
                                  AND ERP='T'
                                  AND GUID='{$val['guid']}'
                               LIMIT 1";
                $guidExt = $this->DB->get_row($clientSql);
                if(!count($guidExt)){//不存在或未同步
                    $notExistGuid[] = array(
                        'guid'  => $val['guid'],
                        'error' => '该经销商未同步或guid不存在于DHB中'
                    );
                    continue;
                }
            }
        }
    }
    
    
    
    
    
    
    
    /************************ 以下是私有方法 ******************************/
    
    
    
    

}//EOF Teeny
