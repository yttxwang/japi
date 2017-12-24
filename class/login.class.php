<?
     isLogin($menu_flag,$pope);
	 
	 //isLogin
    function isLogin($mo='',$tf='')
	{
		if(empty($_SESSION['uinfo']['userid']) || empty($_SESSION['uc']['CompanyID']))
		{
      	 	session_unset();
			session_destroy();
			$_SESSION['eMsg'] = "请先登陆！";
		 	Error::outAdmin('登陆超时或您的帐号在别的地方登陆了，请重新登陆！','/index.html');
        }		

		if($_SESSION['uinfo']['userflag']!="9")
		{
			if($mo=="system")
			{
				Error::Back('对不起，您没有此项操作权限！');
			}else{
				if(!empty($mo) && !empty($tf))
				{
					if($_SESSION['up'][$mo][$tf] != 'Y') Error::Back('对不起，您没有此项操作权限！');
				}
			}
		}
	}
?>