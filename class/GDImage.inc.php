<?php
//====================================================
// FileName:GDImage.inc.php
//====================================================

class GDImage
{
var $sourcePath; //图片存储路径
var $galleryPath; //图片缩略图存储路径
var $toFile = false; //是否生成文件
var $fontName; //使用的TTF字体名称
var $maxWidth  = 500; //图片最大宽度
var $maxHeight = 600; //图片最大高度



//==========================================
// 函数: GDImage($sourcePath ,$galleryPath, $fontPath)
// 功能: constructor
// 参数: $sourcePath 图片源路径(包括最后一个"/")
// 参数: $galleryPath 生成图片的路径
// 参数: $fontPath 字体路径
//==========================================
function GDImage($sourcePath, $galleryPath, $galleryPath1, $fontPath)
{
$this->sourcePath = $sourcePath;
$this->galleryPath = $galleryPath;
$this->galleryPath1 = $galleryPath1;
$this->fontName = $fontPath . "04B_08__.TTF";
}

//==========================================
// 函数: UPfile($sourcePath ,$galleryPath, $fontPath)
// 功能: 上传图片
// 参数: $tempFile 图片源路径(包括最后一个"/")
// 参数: $sourcefile 生成图片的路径
//==========================================
function UpFile($tempfile,$sourcefile,$filetype,$rename)
{
  $extension = array(
                                //"asf"        =>        "video/x-ms-asf",
                                //"avi"        =>        "video/x-msvideo",
                                //"bmp"        =>        "image/bmp",
                                //"cer"        =>        "application/x-x509-ca-cert",
                                //"css"        =>        "text/css",
                                //"doc"        =>        "application/msword",
                                //"exe"        =>        "application/octet-stream",
                                "gif"        =>        "image/gif",
                                //"gz"        =>        "application/x-gzip",
                                //"hlp"        =>        "application/winhlp",
                                //"hta"        =>        "application/hta",
                                //"htc"        =>        "text/x-component",
                                //"htm"        =>        "text/html",
                                //"htt"        =>        "text/webviewhtml",
                                //"ico"        =>        "image/x-icon",
                                "jpg"        =>        "image/jpeg",
                                "jpg"        =>        "image/pjpeg",
                                //"js"        =>        "application/x-javascript",
                                //"mdb"        =>        "application/x-msaccess",
                                //"mid"        =>        "audio/mid",
                                //"mov"        =>        "video/quicktime",
                               //"mp3"        =>        "audio/mpeg",
                                //"mpg"        =>        "video/mpeg",
                                //"pdf"        =>        "application/pdf",
                                //"ppt"        =>        "application/vnd.ms-powerpoint",
                                "png"        =>        "image/png",
                                //"ra"        =>        "audio/x-pn-realaudio",
                                //"rtf"        =>        "application/rtf",
                                "swf"        =>        "application/x-shockwave-flash",
                                //"tar"        =>        "application/x-tar",
                                //"tgz"        =>        "application/x-compressed",
                                //"tif"        =>        "image/tiff",
                                //"tiff"=>        "image/tiff",
                                //"txt"        =>        "text/plain",
                                //"wav"        =>        "audio/x-wav",
                                //"wps"        =>        "application/vnd.ms-works",
                                //"xls"        =>        "application/vnd.ms-excel",
                                //"zip"        =>        "application/zip"
                  );
          if(!in_array($filetype,$extension))
          {
          echo "<script language=\"javascript\">\n";
          echo "alert('对不起，您上传图片格式不支持(支持格式为gif,jpg,png文件!)')\n";
          echo "window.close();\n";
          echo "</script>";
          exit();
          }else{
          	if($rename=="1"){
          	$ImgExtension = ".".array_search($filetype,$extension);
            $SourceFileName = date("YmdHis")."_".$this->currentTimeMillis().$ImgExtension;
          	}else{
          	$SourceFileName = $sourcefile;	
          	}
            move_uploaded_file($tempfile, $this->sourcePath.$SourceFileName);
            return $SourceFileName;
         }

}

 function currentTimeMillis()
 {
  list($usec, $sec) = explode(" ",microtime()); //分割unix时间戳 (返回msec sec)
  return substr($usec, 2, 3);  //返回时间戳+微妙数3个
 }


//==========================================
// 函数: makeThumb($sourFile,$width=128,$height=128)
// 功能: 生成缩略图(输出到浏览器)
// 参数: $sourFile 图片源文件
// 参数: $width 生成缩略图的宽度
// 参数: $height 生成缩略图的高度
// 返回: 0 失败 成功时返回生成的图片路径
//==========================================
function makeThumb($sourFile,$width=188,$height=188)
{
$imageInfo = $this->getInfo($sourFile);
$sourFile = $this->sourcePath . $sourFile;
$newName = substr($imageInfo["name"], 0, strrpos($imageInfo["name"], ".")) . ".jpg";
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
if (!$img)
return 0;

$width = ($width > $imageInfo["width"]) ? $imageInfo["width"] : $width;
$height = ($height > $imageInfo["height"]) ? $imageInfo["height"] : $height;
$srcW = $imageInfo["width"];
$srcH = $imageInfo["height"];
if ($srcW * $width > $srcH * $height)
$height = round($srcH * $width / $srcW);
else
$width = round($srcW * $height / $srcH);
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
//*/
if ($this->toFile)
{
if (file_exists($this->galleryPath1 . $newName))
unlink($this->galleryPath1 . $newName);
ImageJPEG($new, $this->galleryPath1 . $newName);
return $newName;
}
else
{
ImageJPEG($new);
}
ImageDestroy($new);
ImageDestroy($img);
return $newName;
}
//==========================================
// 函数: waterMark($sourFile, $text)
// 功能: 给图片加水印
// 参数: $sourFile 图片文件名
// 参数: $text 文本数组(包含二个字符串)
// 返回: 1 成功 成功时返回生成的图片路径
//==========================================
function waterMark($sourFile, $text)
{
$imageInfo = $this->getInfo($sourFile);
$sourFile = $this->sourcePath . $sourFile;
$newName = substr($imageInfo["name"], 0, strrpos($imageInfo["name"], ".")) . ".jpg";
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
if (!$img)
return 0;

$width = ($this->maxWidth > $imageInfo["width"]) ? $imageInfo["width"] : $this->maxWidth;
$height = ($this->maxHeight > $imageInfo["height"]) ? $imageInfo["height"] : $this->maxHeight;
$srcW = $imageInfo["width"];
$srcH = $imageInfo["height"];
if ($srcW * $width > $srcH * $height)
$height = round($srcH * $width / $srcW);
else
$width = round($srcW * $height / $srcH);
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
$white = imageColorAllocate($new, 255, 255, 255);
$black = imageColorAllocate($new, 0, 0, 0);
$alpha = imageColorAllocateAlpha($new, 230, 230, 230, 40);
//$rectW = max(strlen($text[0]),strlen($text[1]))*7;
ImageFilledRectangle($new, 0, $height-26, $width, $height, $alpha);
ImageFilledRectangle($new, 13, $height-20, 15, $height-7, $black);
ImageTTFText($new, 4.9, 0, 20, $height-14, $black, $this->fontName, $text[0]);
ImageTTFText($new, 4.9, 0, 20, $height-6, $black, $this->fontName, $text[1]);
//*/
if ($this->toFile)
{
//删除原图	
if (file_exists($sourFile)) unlink($sourFile);
	
if (file_exists($this->galleryPath . $newName)) unlink($this->galleryPath . $newName);
ImageJPEG($new, $this->galleryPath . $newName);
return $newName;
}
else
{
ImageJPEG($new);
}
ImageDestroy($new);
ImageDestroy($img);

return $newName;
}

//==========================================
// 函数: displayThumb($file)
// 功能: 显示指定图片的缩略图
// 参数: $file 文件名
// 返回: 0 图片不存在
//==========================================
function displayThumb($file)
{
$thumbName = substr($file, 0, strrpos($file, ".")) . "_thumb.jpg";
$file = $this->galleryPath1 . $thumbName;
if (!file_exists($file))
return 0;
$html = "<img src='$file' style='border:1px solid #000'/>";
echo $html;
}
//==========================================
// 函数: displayMark($file)
// 功能: 显示指定图片的水印图
// 参数: $file 文件名
// 返回: 0 图片不存在
//==========================================
function displayMark($file)
{
$markName = substr($file, 0, strrpos($file, ".")) . "_mark.jpg";
$file = $this->galleryPath . $markName;
if (!file_exists($file))
return 0;
$html = "<img src='$file' style='border:1px solid #000'/>";
echo $html;
}
//==========================================
// 函数: getInfo($file)
// 功能: 返回图像信息
// 参数: $file 文件路径
// 返回: 图片信息数组
//==========================================
function getInfo($file)
{
$file = $this->sourcePath . $file;
$data = getimagesize($file);
$imageInfo["width"] = $data[0];
$imageInfo["height"]= $data[1];
$imageInfo["type"] = $data[2];
$imageInfo["name"] = basename($file);
return $imageInfo;
}


  /**
    * 删除图片
    * @params $Image:String;
    * @return Boolean;
    */
    function delete_big($sourFile)
    {
        if(@file_exists($this->sourcePath.$sourFile))
        {
            echo  @unlink($this->sourcePath.$sourFile);
            exit();
        }
        return false;
    }

}
?>