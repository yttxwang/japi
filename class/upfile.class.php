<?php
if (!defined('FileType_ROOT')) {
	define('FileType_ROOT', dirname(__FILE__) . '/');
}

class upfile{
	//上传文件信息
	var $filename;
	// 保存名
	var $savename;
	// 保存路径
	var $savepath;
	// 文件格式限定，为空时不限制格式
	var $format = "jpg|gif|png";
	// 覆盖模式
	var $overwrite = 1;
	/* $overwrite = 0 时不覆盖同名文件
	* $overwrite = 1 时覆盖同名文件
	*/
	//文件最大字节
	var $maxsize = 80000000;
	//文件扩展名
	var $ext;

	/* 构造函数
	* $path 保存路径
	* $format 文件格式(用"|"分开)
	* $maxsize 文件最大限制,0为默认值
	* $over 复盖参数
	*/
	function upfile($pathend = "./", $format = "jpg|gif|png", $maxsize = 80000000, $over = 1)
	{
		$path = $pathend;
		
		if(!file_exists($path)){
			return "指定的目录[ ".$path." ]不存在。";
		}

		if(!is_writable($path)){
			return "指定的目录[ ".$path." ]不可写。";
		}
		$path = str_replace("\\","/", $path);
		$this->savepath = substr($path, -1) == "/" ? $path : $path."/";//保存路径

		$this->overwrite = $over;//是否复盖相同名字文件
		$this->maxsize	 = !$maxsize ? $this->maxsize : $maxsize;//文件最大字节
		$this->format	 = $format;
	}

	/*
	* 功能:检测并组织文件
	* $form      文件域名称
	* $filename 上传文件保存名称，为空或者上传多个文件时由系统自动生成名称
	* $filename = 1，并上传多个同文件域名称文件时，则文件保存为原上传文件名称。
	*/
	function upload($form, $rename = "")
	{
		$filear = $_FILES[$form];
		$rmsg   = $filear;

			$this->ext = $filear["type"];//取得扩展名
			if($rename=="1"){ $filename="";}else{ $filename = $filear["name"];}
			$newname = $this->set_savename($filename);//设置保存文件名
			$rmsg["newname"] = $newname;
			$bmsg = $this->copyfile($filear);
			return $rmsg;
			if($bmsg == "ok")
			{
				return $rmsg;
			}else{
				return $bmsg;
			}
		return "文件上传失败!";
	}

	/*
	* 功能:检测并复制上传文件
	* $filear 上传文件资料数组
	*/
	function copyfile($filear)
	{
		if(!isset($filear["size"]) || $filear["size"] > $this->maxsize){
			return "上传文件 ".$filear["name"]." 大小超出系统限定值[8M]!";
		}

		if(!$this->overwrite && file_exists($this->savepath.$this->savename)){
			return $this->savename." 文件已经存在,请改名上传!";
		}
		require(FileType_ROOT . 'class_filetypevalidation2.php');
		$x = FileTypeValidation::validation($filear["tmp_name"], $this->ext);
		if(!$x){
			return '文件类型错误!';
			@unlink($filear["tmp_name"]);//删除临时文件
			exit();
		}

		if(!$this->chkext())
		{
			$alertMsg = "格式错误! 当前格式为:[".$this->ext."] 只允许上传以下格式:[".$this->format."]";
			@unlink($filear["tmp_name"]);//删除临时文件
			return $alertMsg;
		}
		
		if(!@move_uploaded_file($filear["tmp_name"], $this->savepath.$this->savename))
		{
			$errors = array(0=>"文件上传成功!",
			1=>"上传的文件超过了,系统中最大限制的值(4M)! ",
			2=>"上传的文件超过了,系统中最大限制的值(4M)! ",
			3=>"文件只有部分被上传! ",
			4=>"没有文件被上传! ");
			return $errors[$filear["error"]];
		}else{
			@unlink($filear["tmp_name"]);//删除临时文件
			return "ok";//返回上传文件名
		}
		return "没有文件被上传!";
	}

	function copytofile($srcfile,$tofile)
	{
		$this->ext = $this->getext($srcfile);
		require(FileType_ROOT . 'class_filetypevalidation2.php');
		$x = FileTypeValidation::validation($srcfile, $this->ext);
		if(!$x)
		{
			return '文件类型错误!';
			@unlink($srcfile);
			exit();
		}

		if(!$this->chkext())
		{
			$alertMsg = "格式错误! 当前格式为:[".$this->ext."] 只允许上传以下格式:[".$this->format."]";
			@unlink($srcfile);
			return $alertMsg;
		}
		
		if(@copy($srcfile, $tofile))
		{
			return "ok";
		}
		return "没有文件被上传!";
	}

	/*
	* 功能: 取得文件扩展名
	* $filename 为文件名称
	*/
	function getext($filename)
	{
		$ext = "";
		if($filename == "") return $ext;
		$ext_a = explode(".", $filename);
		$ext   = array_pop($ext_a);
		$ext   = strtolower($ext);
		return $ext;
	}

	/*
	* 功能:检测文件类型是否允许
	*/
	function chkext()
	{
		if($this->format == "" || in_array($this->ext, explode("|", strtolower($this->format)))) return true;
		else return false;
	}
	/*
	* 功能: 设置文件保存名
	* $savename 保存名，如果为空，则系统自动生成一个随机的文件名
	*/
	function set_savename($savename = "")
	{
		if ($savename == "") 
		{
			list($usec, $sec) = explode(" ",microtime()); //分割unix时间戳 (返回msec sec)
			$name_usec = substr($usec, 2, 3);
			$name = "".date("YmdHis")."_".$name_usec.".jpg";
		} else {
			$name = $savename;
		}
		$this->savename = $name;
		return $this->savename;
	}

	/*
	* 功能:错误提示
	* $msg 为输出信息
	*/
	function halt($msg)
	{
		return $msg;
	}

	//==========================================
	// 函数: makeThumb($sourFile,$width=128,$height=128)
	// 功能: 生成缩略图(输出到浏览器)
	// 参数: $sourFile 图片源文件
	// 参数: $width 生成缩略图的宽度
	// 参数: $height 生成缩略图的高度
	// 返回: 0 失败 成功时返回生成的图片路径
	//==========================================
	function makeThumb($sourFile,$dstFile,$width=100,$height=100)
	{
		$imageInfo  = $this->getInfo($sourFile);

		switch ($imageInfo["type"])
		{
			case 1: //gif
			$img = imagecreatefromgif($sourFile);
			break;
			case 2: //jpg
			$img = imagecreatefromjpeg($sourFile);
			break;
			case 3: //png
			$img = imagecreatefrompng($sourFile);
			break;
			default:
				return 0;
				break;
		}
		if (!$img)	return 0;
		
		if($width >= $imageInfo["width"])
		{
			$width = $imageInfo["width"];
			$height = $imageInfo["height"];
		}else{
			$width  = ($width > $imageInfo["width"]) ? $imageInfo["width"] : $width;
			$height = ($height > $imageInfo["height"]) ? $imageInfo["height"] : $height;
			$srcW	= $imageInfo["width"];
			$srcH	= $imageInfo["height"];		
		
			if ($srcW/$srcH > $width/$height){
				$height = round($srcH * $width / $srcW);
			}else{
				$width  = round($srcW * $height / $srcH);
			}
		}

		//*
		if (function_exists("imagecreatetruecolor")) //GD2.0.1
		{
			$new = imagecreatetruecolor($width, $height);
			ImageCopyResampled($new, $img, 0, 0, 0, 0, $width, $height, $imageInfo["width"], $imageInfo["height"]);
		}
		else
		{
			$new = imagecreate($width, $height);
			ImageCopyResized($new, $img, 0, 0, 0, 0, $width, $height, $imageInfo["width"], $imageInfo["height"]);
		}

		if (file_exists($dstFile))	@unlink($dstFile);
		
		ImageJPEG($new, $dstFile,100);

		ImageDestroy($new);
		ImageDestroy($img);
		return true;
	}

	//==========================================
	// 函数: getInfo($file)
	// 功能: 返回图像信息
	// 参数: $file 文件路径
	// 返回: 图片信息数组
	//==========================================
	function getInfo($file)
	{
		$data = getimagesize($file);
		$imageInfo["width"] = $data[0];
		$imageInfo["height"]= $data[1];
		$imageInfo["type"]  = $data[2];
		$imageInfo["name"]  = basename($file);
		return $imageInfo;
	}

}
?>