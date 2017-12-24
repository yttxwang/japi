<?php
/*++++++++++++++++++++++++++++++++++ 
ip 读取程序
+++++++++++++++++++++++++++++++++++++*/
define('__QQWRY__' , str_replace('\\', '/',dirname(__FILE__)."/ip/qqwry.dat"));
class IPAddress{
    var $StartIP=0;
    var $EndIP=0;
    var $Country='';
    var $Local='';
    var $CountryFlag=0; // 标识 Country位置
             // 0x01,随后3字节为Country偏移,没有Local
             // 0x02,随后3字节为Country偏移，接着是Local
             // 其他,Country,Local,Local有类似的压缩。可能多重引用。
    var $fp;
 
    var $FirstStartIp=0;
    var $LastStartIp=0;
    var $EndIpOff=0 ;
 
    function getStartIp($RecNo){
     $offset=$this->FirstStartIp+$RecNo * 7 ;
     @fseek($this->fp,$offset,SEEK_SET) ;
     $buf=fread($this->fp ,7) ;
     $this->EndIpOff=ord($buf[4]) + (ord($buf[5])*256) + (ord($buf[6])* 256*256);
     $this->StartIp=ord($buf[0]) + (ord($buf[1])*256) + (ord($buf[2])*256*256) + (ord($buf[3])*256*256*256);
     return $this->StartIp;
    }
 
    function getEndIp(){
     @fseek ( $this->fp , $this->EndIpOff , SEEK_SET ) ;
     $buf=fread ( $this->fp , 5 ) ;
     $this->EndIp=ord($buf[0]) + (ord($buf[1])*256) + (ord($buf[2])*256*256) + (ord($buf[3])*256*256*256);
     $this->CountryFlag=ord ( $buf[4] ) ;
     return $this->EndIp ;
    }
 
    function getCountry(){
	     switch ( $this->CountryFlag ) {
	        case 1:
	        case 2:
	         $this->Country=$this->getFlagStr ( $this->EndIpOff+4) ;
	         //echo sprintf('EndIpOffset=(%x)',$this->EndIpOff );
	         $this->Local=( 1 == $this->CountryFlag )? '' : $this->getFlagStr ( $this->EndIpOff+8);
	         break ;
	        default :
	         $this->Country=$this->getFlagStr ($this->EndIpOff+4) ;
	         $this->Local=$this->getFlagStr ( ftell ( $this->fp )) ;
	     }
    }
 
    function getFlagStr ($offset){
     $flag=0 ;
     while(1){
        @fseek($this->fp ,$offset,SEEK_SET) ;
        $flag=ord(fgetc($this->fp ) ) ;
        if ( $flag == 1 || $flag == 2 ) {
         $buf=fread ($this->fp , 3 ) ;
         if ($flag==2){
            $this->CountryFlag=2;
            $this->EndIpOff=$offset - 4 ;
         }
         $offset=ord($buf[0]) + (ord($buf[1])*256) + (ord($buf[2])* 256*256);
        }
        else{
         break ;
        }
 
     }
     if($offset<12)
        return '';
     @fseek($this->fp , $offset , SEEK_SET ) ;
 
     return $this->getStr();
    }
 
    function getStr ( )
    {
     $str='' ;
     while ( 1 ) {
        $c=fgetc ( $this->fp ) ;
        //echo "$cn" ;
 
        if(ord($c[0])== 0 )
         break ;
        $str.= $c ;
     }
     //echo "$str n";
     return $str ;
    }
 
 
    function qqwry ($dotip='') {
        if(!$dotip)return false;
            if(ereg("^(127)",$dotip)){$this->Country='本地网络';return false;}
            elseif(ereg("^(192)",$dotip)) {$this->Country='局域网';return false;}
            
     $ip=$this->IpToInt ( $dotip );
     $this->fp= fopen(__QQWRY__, "rb");     
     if ($this->fp == NULL) {
         $szLocal= "打开文件失败";
        return 1;
 
     }
     @fseek ( $this->fp , 0 , SEEK_SET ) ;
     $buf=fread ( $this->fp , 8 ) ;
     $this->FirstStartIp=ord($buf[0]) + (ord($buf[1])*256) + (ord($buf[2])*256*256) + (ord($buf[3])*256*256*256);
     $this->LastStartIp=ord($buf[4]) + (ord($buf[5])*256) + (ord($buf[6])*256*256) + (ord($buf[7])*256*256*256);
 
     $RecordCount= floor( ( $this->LastStartIp - $this->FirstStartIp ) / 7);
     if ($RecordCount <= 1){
        $this->Country="数据文件错误";
        fclose($this->fp) ;
        return 2 ;
     }
 
     $RangB= 0;
     $RangE= $RecordCount;
     // Match ...
     while ($RangB < $RangE-1)
     {
     $RecNo= floor(($RangB + $RangE) / 2);
     $this->getStartIp ( $RecNo ) ;
 
        if ( $ip == $this->StartIp )
        {
         $RangB=$RecNo ;
         break ;
        }
     if ($ip>$this->StartIp)
        $RangB= $RecNo;
     else
        $RangE= $RecNo;
     }
     $this->getStartIp ( $RangB ) ;
     $this->getEndIp ( ) ;
 
     if ( ( $this->StartIp <= $ip ) && ( $this->EndIp >= $ip ) ){
        $nRet=0 ;
        $this->getCountry ( ) ;
        //这样不太好..............所以..........
        $this->Local=str_replace("（我们一定要解放台湾！！！）", "", $this->Local);
     }
     else{
        $nRet=3 ;
        $this->Country = '' ;
        $this->Local = '' ;
     }
     fclose ( $this->fp );
	$this->Country=preg_replace("/(CZ88.NET)|(纯真网络)/","",$this->Country);
	$this->Local=preg_replace("/(CZ88.NET)|(纯真网络)/","",$this->Local);
//////////////看看 $nRet在上面的值是什么0和3，于是将下面的行注释掉

        //return $nRet ;
 		#如此直接返回位置和国家便可以了
     return array($this->Country, $this->Local);
    }
 
    function IpToInt($Ip) {
     $array=explode('.',$Ip);
     $Int=($array[0] * 256*256*256) + ($array[1]*256*256) + ($array[2]*256) + $array[3];
     return $Int;
    }
    function replaceArea(){
    	if(!$this->is_utf8($this->Country)){
    		$return = @iconv('gb2312', 'utf-8', "$this->Country");
    	}
    	else{
    		$return = $this->Country;
    	}
		$dataLoc = array('广西','北京市','上海市','西藏','内蒙古','宁夏','新疆','天津市','重庆市');
		$dataLocN = array('广西省','北京省','上海省','西藏省','内蒙古省','宁夏省','新疆省','天津省','重庆省');
		$location = @str_replace($dataLoc, $dataLocN, $return);	
		return @str_replace('省', '', $location);		
	}     
	function is_utf8($word)
	{
		if (
		 preg_match("/^([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){1}/",$word) == true ||
		 preg_match("/([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){1}$/",$word) == true ||
		 preg_match("/([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){2,}/",$word) == true
		 )
		{
			return true;
		}
		else
		{
			return false;
		}
	} // function is_utf8
	
 }	//IPAddress end 
 
 function GetIP(){//获取IP
    	return $_SERVER[REMOTE_ADDR]?$_SERVER[REMOTE_ADDR]:$GLOBALS[HTTP_SERVER_VARS][REMOTE_ADDR];
}
?>