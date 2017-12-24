<?
/**
 * Class 缓存
 *
 * @author seekfor seekfor@gmail.com
 * @version CMSfor Website Pro 1.2 Tue Oct 10 18:07:59 CST 2006 
 */
class CacheData
{
	var $DataList = array("Site_Note" => 'Cache_Site_Note.php', "Content_Model" => 'Cache_Content_Model.php',"Site_Array" => 'Cache_Site_Array.php');
	var $CacheFileHeader = '<?php
//CMSfor cache file, DO NOT modify me!
//Created on ';
	var $CacheFileFooter = '

?>';
	var $CacheOutput = null;
	var $returnMsg   = "";
	var $dataArray = null;
	
	function CacheData()
	{
		$this->key  	= "";
		$this->selfid   = "";
	}
	
	function MakeCache($key)
	{
		global $app;
		global $License;
		$app->dataconnect();
		$this->db   = & $app->db;
		switch($key)
		{			
			case "Site_Note":
				if(empty($License['Site_num'])){
					$rsql = "SELECT * FROM " .DATATABLE."_r_site WHERE Disabled=0 order by SiteOrder DESC, SiteID ASC";
				}else{
					$rsql = "SELECT * FROM " .DATATABLE."_r_site WHERE Disabled=0 order by SiteOrder DESC, SiteID ASC LIMIT 0, ".$License['Site_num'];
				}
				$result = $this->db->GetArray($rsql);
				if(empty($result)) return false;				
					$results = var_export($result, true);
					$results = '$Site_Note = ' . $results . ';';
				break;
				
			case "Site_Array":
				if(empty($License['Site_num'])){
					$asql = "SELECT * FROM " .DATATABLE."_r_site WHERE Disabled=0 order by SiteOrder DESC, SiteID ASC";
				}else{
					$asql = "SELECT * FROM " .DATATABLE."_r_site WHERE Disabled=0 order by SiteOrder DESC, SiteID ASC LIMIT 0, ".$License['Site_num'];
				}
				$resulta = $this->db->GetArray($asql);
				if(empty($resulta)) return false;				
				foreach ($resulta as $avar)
				{
					$returna[$avar['SiteID']] = $avar;
					if(!empty($avar['SiteGUID'])) $returna[$avar['SiteGUID']] = $avar;
				}
					$results = var_export($returna, true);
					$results = '$Site_Array = ' . $results . ';';
				break;
							
			case "Content_Model":
				$result = $this->db->GetArray("SELECT * FROM " .DATATABLE."_r_content_table order by TableID ASC");
				$result2 = $this->db->GetArray("SELECT * FROM " .DATATABLE."_r_content_fields order by TableID ASC, FieldOrder DESC, FieldID ASC");
				if(empty($result)) return false;
				
					for($i=0;$i<count($result);$i++)
					{
						for($j=0;$j<count($result2);$j++)
						{
							if($result[$i]['TableID'] == $result2[$j]['TableID'])
							{
								$result[$i]['Model'][] = $result2[$j];
							}
						}
					}				
				
					$results = var_export($result, true);
					$results = '$Content_Model = ' . $results . ';';					
				break;
		}
		$this->WriteCache($key, $results);
		return true;
	}
	
	function WriteCache($key, $cacheData)
	{
		$cacheData = $this->CacheFileHeader . date('Y-m-d, H:i') . "\n" . $cacheData . $this->CacheFileFooter;
		$handle = fopen(DATA_PATH . $this->DataList[$key], "w");
		@flock($handle, 3);
		fwrite($handle, $cacheData);
		fclose($handle);
	}
	
	function CheckCache($key)
	{
		if(!(file_exists (DATA_PATH . $this->DataList[$key])))
		{
			$this->MakeCache($key);	
		}
	  return true;
	}

	function ClearCache($key)
	{
		@unlink (DATA_PATH . $this->DataList[$key]);
		$this->MakeCache($key);
		return true;
	}

	function GetData($key)
	{		
		if(!empty($this->dataArray) && $this->key==$key)
		{
			return 	$this->dataArray;		
		}

		if($this->CheckCache($key))
		{
			include(DATA_PATH . $this->DataList[$key]);	
		}else{
			return null;
		}
		switch($key)
		{
			case "Site_Note":
				$this->dataArray =  $Site_Note;
				$this->key  =  "Site_Note";
				//return $Site_Note;
				unset($Site_Note);
				break;
				
			case "Site_Array":
				$this->dataArray =  $Site_Array;
				$this->key  =  "Site_Array";
				//return $Site_Array;
				unset($Site_Array);
				break;	
							
			case "Content_Model":
				$this->dataArray =  $Content_Model;
				$this->key  =  "Content_Model";
				unset($Content_Model);
				break;
		}
		return $this->dataArray;
	}
	
	function GetKey($keyName, $keyValue, $key)
	{
		if($keyName=='' || $keyValue=='')
		{
			return null;
		}
		if(empty($this->dataArray) || $this->key != $key) $this->GetData($key);
		
		for($i=0;$i<count($this->dataArray);$i++)
		{
			if($this->dataArray[$i][$keyName] == $keyValue)
			{
				return $this->dataArray[$i]; 				
			}
		}
		return null;
	}
	
 	function OutputList($p_id=0,$s_id=0,$level=0)
	{
		$frontMsg = "┠-";
		$repeatMsg = "";
		$selectMsg = "";
				
		if(empty($this->dataArray) || $this->key!="Site_Note") $this->GetData("Site_Note");
				
		foreach($this->dataArray as $key => $var)
		{
			if($var['ParentID'] == $p_id && $var['SiteType'] == "1")
			{				
				if($p_id=="0")
				{
					$level = 0;
					$this->pid = 0;
					$level = 0;
				}
				elseif($this->selfid == $var['ParentID'] )
				{
					$level++;
					$this->pid = $var['ParentID'];
				}
				elseif($this->pid != $var['ParentID'])
				{
					if($level>1) $level--;
					$this->pid = $var['ParentID'];
				}
				$this->selfid = $var['SiteID'];
				$repeatMsg = str_repeat("-+-", $level);
				if(is_array($s_id))
				{
					if(in_array($var['SiteID'],$s_id))
					{
						$selectMsg = " selected ";
					}else{
						$selectMsg = "";
					}					
				}else{	
					if($s_id == $var['SiteID'])
					{
						$selectMsg = " selected ";
					}else{
						$selectMsg = ""; 
					}
				}				
				$this->returnMsg .= "<option value='".$var['SiteID']."' ".$selectMsg." >". $frontMsg .$repeatMsg." ".$var['SiteName']. "</option>\n"; 
				$this->OutputList($var['SiteID'],$s_id,$level);
			}
		}
		
		return $this->returnMsg;
		unset($this->returnMsg);
	}
	
}

?>