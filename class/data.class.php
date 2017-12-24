<?
/**
 * Class idata
 * 数据操作
 * 
 * @author seekfor seekfor@gmail.com
 * @version 1.2 Mon Sep 25 20:43:17 CST 2006 
 */

class idata
{
	var $insData = null;
	var $checkpass = true;
	var $errinfo = null;
	var $db_insert_id = null;
	var $db_debug = false;
	
	function idata()
	{
		global $db;
		$this->db   = & $db;
		$this->retrunSite = null;
	}
	
	function getForm($tmpArray)
	{
		foreach($tmpArray as $key => $val)
		{
			$this->insData[$key] = $val;
		}
	}
	
	function filterData($IN)
	{
		if(!(is_array ($IN)))
		{
			return false;
		}
		foreach($IN as $key => $var)
		{
			$header = substr($key, 0, 5);
			if($header == "data_")
			{
				$field = substr($key, 5);
				if($var == "undefined") $var="";
				$this->addData($field, $var);
				continue;
			}
		}
	}
	
	function debugData()
	{
		foreach($this->insData as $key => $val)
		{
			echo $key . " -- " . $val . ' 
<br>';
		}
		exit();
	}
	
	function getData($key = NULL)
	{
		if(!(empty ($key)))
		{
			return $this->insData[$key];
		}
		return $this->insData;
	}
	
	function addData($data, $val = NULL)
	{
		if(is_array ($val))
		{
			$linkallmsg = "";
			$fi=0;
			foreach($val as $key2 => $var2)
			{
				$var2 = str_replace(",", "，", $var2);
				if($fi==0){
					$linkallmsg = $var2;
				}else{
					$linkallmsg .= ",".$var2;
				}
				$fi++;
			}
			 $val = $linkallmsg;
		}

		$this->insData[$data] = $this->db->escape($val);
	}
	
	function delData($key)
	{
		unset($this->insData[$key]);
	}
	
	function flushData()
	{
		unset($this->insData);
	}
	
	function chgData($key, $val)
	{
		$this->insData[$key] = $val;
	}
	
	function dataInsert($Table)
	{
		$Table = DATATABLE.$Table;
		$insData_Num = count($this->insData);
		$Foreach_I = 0;
		$query = "Insert into " . $Table . '(
';
		$query_key = '';
		$query_val = '';

		foreach ($this->insData as $key => $val)
		{
			if(0 < strlen ($val))
			{
				if($Foreach_I == 0)
				{
					$query_key .= "`" . $key . "`";
					$query_val .=  $this->ensql($val);
				}
				else
				{
					$query_key .= ',`' . $key . "`";
					$query_val .= ', ' . $this->ensql($val) . '';
				}
				$Foreach_I += 1;
				continue;
			}
		}
		$query .= $query_key . ') Values(' . $query_val . ')';

		if($result = $this->db->query ($query))
		{
			$db_insert_id = mysql_insert_id();
			return $db_insert_id;
		}

		//$this->db->debug();
		return false;
	}
	
	function dataUpdate($Table, $where)
	{
		$Table = DATATABLE.$Table;
		$Foreach_I = 0;
		$query = "update " . $Table . " set ";
		$query_key = '';
		$query_val = '';
		foreach($this->insData as $key => $val)
		{
			if(0 <= strlen ($val))
			{
				if($Foreach_I == 0)
				{
					$query_key = "`" . $key . "`";
					$query_val = "=" . $this->ensql($val) . "";
					$query .= $query_key . $query_val;
				}
				else
				{
					$query_key = ",`" . $key . "`";
					$query_val = "=" . $this->ensql($val) . "";
					$query .= $query_key . $query_val;
				}
				$Foreach_I += 1;
				continue;
			}
		}
		 $query .= " " . $where;
		if($this->db->query ($query))
		{
			return true;
		}
		
		//$this->db->outp("<P>ERROR:</P>", $newline = true);
		return false;
	}
	
	function dataReplace($Table)
	{

		$Table = DATATABLE.$Table;
		$insData_Num = count($this->insData);
		$Foreach_I = 0;
		$query = "Replace into " . $Table . '(
';
		$query_key = '';
		$query_val = '';
		foreach ($this->insData as $key => $val)
		{
			if(0 < strlen ($val))
			{
				if($Foreach_I == 0)
				{
					$query_key .= "`" . $key . "`";
					$query_val .= "" . $this->ensql($val) . "";
				}
				else
				{
					$query_key .= ',`' . $key . "`";
					$query_val .= ',' . $this->ensql($val) . "";
				}
				$Foreach_I += 1;
				continue;
			}
		}
		$query .= $query_key . '
) 
Values(
' . $query_val . '
)';
		if($result = $this->db->query ($query))
		{
			$db_insert_id = mysql_insert_id();
			return true;
		}
		//$this->db->outp("<P>ERROR:</P>", $newline = true);
		return false;
	}
	
	function dataDel($Table, $which, $id, $method = "=")
	{
		$Table = DATATABLE.$Table;
		$query = "Delete From " . $Table . " where " . $which . $method . $id;
		if($this->db->query ($query))
		{
			return true;
		}
		//$this->db->outp("<P>ERROR:</P>", $newline = true);
		return false;
	}

	
	function dataExists($Table, $method, $field, $var)
	{
		$Table = DATATABLE.$Table;
		$query = "select COUNT(*) as nr From " . $Table . " where " . $field . $method . $var;
		$result = $this->db->query($query);
		if($result)
		{
			return true;
		}
		return false;
	}
	
	function ensql($string)
	{
		$string = str_replace("'", "‘", $string);
		return "'".$string."'";
	}
	
//END	
}

?>