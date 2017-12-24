<?
/**
 * Class 文件操作
 *
 * @author seekfor seekfor@gmail.com
 * @version CMSfor Website Pro 1.2 Tue Oct 10 18:08:42 CST 2006 
 */
class ifile
{
	var $Fp = null;
	var $File = null;
	var $OpenMode = null;
	function openfile($r_path, $File, $Mode = "r")
	{
		$this->OpenMode = $Mode;
		$this->File =  str_replace('//', '/', $r_path . '' . $File);;

		if(!((!($this->OpenMode == "r") AND !($this->OpenMode == "r+"))))
		{
			if($this->CheckFile ())
			{
				$this->Fp = fopen($this->File, $this->OpenMode);
			}
			else
			{
				echo "Error: " . $File . " 不存在！ ";
				return false;
			}
		}
		else
		{
			$this->Fp = fopen($this->File, $this->OpenMode);
		}
		return true;
	}

	function closefile()
	{
			@fclose($this->Fp);
			return null;
	}

	function getfiledata()
	{
		@flock($this->Fp, 1);
		$Content = fread($this->Fp, filesize ($this->File));
		$this->CloseFile();
		return $Content;
	}

	function checkfile()
	{
		if(file_exists ($this->File))
		{
			return true;
		}
		return false;
	}

	function writefile($Data, $Mode = 3)
	{
		@flock($this->Fp, $Mode);
		$fl = fwrite($this->Fp, $Data);
		$this->CloseFile();
		if($fl){
			return true;
		}else{
			return false;
		}
	}

	//遍历目录
	function _list ($r_path, $path='')
    {
      $dirlist = array ();
      $filelist = array ();
      $psn_path = $r_path . '' . $path;
      $dir = dir ($psn_path);
      $dir->rewind ();
      
      while ($file = $dir->read ())
      {
        if (!($file == '.') && !($file == '..'))
        {
            $p_path = str_replace('//', '/', $psn_path . '/' . $file);
            $b_path = str_replace('//', '/', $path . '/' . $file);
        	if (is_dir ($p_path))
            {
              $dirlist['dir'][] = array ('path' => $b_path, 'type' => 'dir', 'name' => $file, 'size' => ceil (filesize ($psn_path . '/' . $file) / 1024), 'modifiedDate' => date ('Y-m-d H:i:s', filemtime ($psn_path . '/' . $file)), 'mode' => fileperms ($psn_path . '/' . $file));
              continue;
            }
            else
            {
              $arr = explode ('.', $file);
              $filelist['file'][] = array ('path' => $b_path, 'type' => array_pop ($arr), 'name' => $file, 'size' => ceil (filesize ($psn_path . '/' . $file) / 1024), 'modifiedDate' => date ('Y-m-d H:i:s', filemtime ($psn_path . '/' . $file)), 'mode' => fileperms ($psn_path . '/' . $file));
              continue;
            }
            continue;
          }
      }

      $filelist = array_merge ($dirlist, $filelist);
      $dir->close ();
      return $filelist;
    }
	
    
    function _mkdir ($r_path, $dirname, $mode=0777)
    {
      $b_path = str_replace('//', '/', $r_path . '' . $dirname);
      if (@mkdir ($b_path, $mode))
      {
		//@chmod ($b_path, $mode);
      	return true;
      }
      return false;
    }
   
    function _rename ($r_path, $oldName, $newName)
    {
      $o_path = str_replace('//', '/', $r_path . '' . $oldName);
      $n_path = str_replace('//', '/', $r_path . '' . $newName);
      if (@rename ($o_path, $n_path))
      {
        return true;
      }
      return false;
    }   
    
    function _rmdir ($r_path, $dirname)
    {
      $b_path = $r_path . '' . $dirname;
      $b_path = str_replace('//', '/', $b_path);

      if (@rmdir ($b_path))
      {
        return true;
      }
      return false;
    }

    function _delfile ($r_path, $filename)
    {
      $deler = $r_path . '' . $filename;
      $deler = str_replace('//', '/', $deler);
      if (@file_exists ($deler))
      {
        if (@unlink ($deler))
        {
          return true;
        }
        return false;
      }
      return true;
    }
     
}

?>