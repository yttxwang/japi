<?
/**
 * Class 数据分页
 *
 * @author seekfor seekfor@gmail.com
 * @version CMSfor Website Pro 1.2 Tue Oct 10 18:09:18 CST 2006 
 */
 class ShowPage {

        var $PageSize;     //每页显示的记录数

        var $Total;        //记录总数

        var $LinkAry;      //Url参数数组

//取得总页数
        function PageCount() 
        {
                $TotalPage = ($this->Total % $this->PageSize == 0) ? floor($this->Total / $this->PageSize) :  floor($this->Total / $this->PageSize)+1;
                return $TotalPage;
         }
//取得当前页
        function PageNum()
        {
                $page =  (isset( $_GET['page'])!="") ? $_GET['page'] :  $page = 1;
                return $page;
        }
//查询语句定位指针
        function OffSet() 
        {
             if ($this->PageNum() > $this->PageCount()) 
             {
                $pagemin = max(0,$this->Total - $this->PageSize - 1);
             }
             else if ($this->PageNum() == 1)
             {
                 $pagemin = 0;
             }else {
                 $pagemin = min($this->Total - 1,$this->PageSize * ($this->PageNum() - 1));
            }
            if($pagemin<0) $pagemin=0;
            if($pagemin > $this->Total) $pagemin = $this->Total;
            return " limit ". $pagemin . "," . $this->PageSize;
         }
//定位首页
        function FristPage() {
                $Frist = ($this->PageNum() <= 1) ? "<font color=#666666>首页</font>  " : "<a href=\"?page=1".$this->Url($this->LinkAry)."\">首页</a> ";
                return $Frist;
        }
//定位上一页
        function PrePage() {
                $prepage=$this->PageNum() - 1;
                $Previous = ($this->PageNum() >= 2) ? " <a href=\"?page=".$prepage.$this->Url($this->LinkAry)."\">上页</a> " : "<font color=#666666>上页</font> ";
                return $Previous;
        }
//定位下一页
        function NextPage() {
                $nextpage = $this->PageNum() + 1;
                $Next = ($this->PageNum() <= $this->PageCount()-1) ? " <a href=\"?page=".$nextpage.$this->Url($this->LinkAry)."\">下页</a> " : "<font color=#666666>下页</font> ";
                return $Next;
        }
//定位最后一页
        function LastPage() {
                $Last = ($this->PageNum() >= $this->PageCount()) ? "<font color=#666666>尾页</font>  " : " <a href=\"?page=".$this->PageCount().$this->Url($this->LinkAry)."\">尾页</a> ";
                return $Last;
        }
//下拉跳转页面
        function JumpPage() {
                $Jump = " 当前第 ".$this->PageNum()." 页 跳到 <select name=page onchange=\"javascript:location=this.options[this.selectedIndex].value;\">";
                for ($i=1; $i<=$this->PageCount(); $i++) {
                if ($i==$this->PageNum())
                        $Jump .= "<option value=\"?page=".$i.$this->Url($this->LinkAry)."\" selected>$i</option>";
                else
                        $Jump .="<option value=\"?page=".$i.$this->Url($this->LinkAry)."\">$i</option> ";
                }
            $Jump .= "</select> 页";
		    //$Jump .= "</select> 页 [".$this->PageSize."条/页]";
                return $Jump;
        }
//URL参数处理
        function Url($ary) {
                $Linkstr = "";
                if (count($ary) > 0) {
                        foreach ($ary as $key => $val) {
                        $Linkstr .= "&".$key."=".$val;
                        }
                }
                return $Linkstr;
        }
//总条数
        function Totalnum() {
                $tnum = "共 ".$this->Total." 条 ".$this->PageCount()." 页 ";
                return $tnum;
        }


	function multi($num, $perpage, $curpage, $mpurl, $maxpages = 0) 
	{
		$multipage = '';
		$mpurl .="&";
		if($num > $perpage) 
		{
			$page = 5;
			$offset = 2;
			$realpages = ceil($num / $perpage);
			$pages = $maxpages && $maxpages < $realpages ? $maxpages : $realpages;
			$from = $curpage - $offset;
			$to = $curpage + $page - $offset - 1;			
			if($page > $pages)
			{
				$from = 1;
				$to = $pages;
			} else {
				if($from < 1)
				 {
					$to = $curpage + 1 - $from;
					$from = 1;
					if(($to - $from) < $page && ($to - $from) < $pages)
					{
						$to = $page;
					}
				} elseif($to > $pages) {
				$from = $curpage - $pages + $to;
				$to = $pages;
				if(($to - $from) < $page && ($to - $from) < $pages) 
				{
					$from = $pages - $page + 1;
				}
			}
		}

		$multipage = ($curpage - $offset > 1 && $pages > $page ? '<a href="'.$mpurl.'page=1">&#8249;&#8249; 首页' : '').($curpage > 1 ? '<a href="'.$mpurl.'page='.($curpage - 1).'">&#8249; 上一页</a>' : '');
		for($i = $from; $i <= $to; $i++) {
			$multipage .= $i == $curpage ? '<span class="page_bar_current">'.$i.'</span>' : '<a href="'.$mpurl.'page='.$i.'">'.$i.'</a>';
		}

		$multipage .= ($curpage < $pages ? '<a href="'.$mpurl.'page='.($curpage + 1).'">下一页 &#8250;</a>' : '').($curpage + $page - $offset <= $pages ? '<a href="'.$mpurl.'page='.$pages.'">尾页 &#8250;&#8250;</a>' : '').($curpage == $maxpages ? '<a href="'.$mpurl.'pages='.$maxpages.'">&gt;</a>' : '').($pages > $page ? '<input type="text" name="custompage" onKeypress="if ((event.keyCode < 48 || event.keyCode > 57) && window.event.keyCode != 13) event.returnValue = false;"  onKeyDown="javascript: if(window.event.keyCode == 13) window.location=\''.$mpurl.'page=\'+this.value;">' : '');

		//$multipage = $multipage ? '<div class="page_bar">'.'<span class="page_bar_current">共&nbsp;'.$num.'&nbsp;件</span>'.'<span class="page_bar_current">第&nbsp;'.$curpage.'&nbsp;页&nbsp;/&nbsp;共&nbsp;'.$realpages.'&nbsp;页&nbsp;</span>'.$multipage.''.'</div>' : '';

		$multipage = $multipage ? '<div class="page_bar">'.''.'<span class="page_bar_current">共&nbsp;'.$num.'&nbsp;条&nbsp;/&nbsp;'.$realpages.'&nbsp;页&nbsp;</span>'.$multipage.''.'</div>' : '';
		}
	return $multipage;
	}

	function multi2($num, $perpage, $curpage, $mpurl, $maxpages = 0) 
	{
		$multipage = '';
		$mpurl .="&";
		if($num > $perpage) 
		{
			$page = 4;
			$offset = 1;
			$realpages = ceil($num / $perpage);
			$pages = $maxpages && $maxpages < $realpages ? $maxpages : $realpages;
			$from = $curpage - $offset;
			$to = $curpage + $page - $offset - 1;			
			if($page > $pages)
			{
				$from = 1;
				$to = $pages;
			} else {
				if($from < 1)
				 {
					$to = $curpage + 1 - $from;
					$from = 1;
					if(($to - $from) < $page && ($to - $from) < $pages)
					{
						$to = $page;
					}
				} elseif($to > $pages) {
				$from = $curpage - $pages + $to;
				$to = $pages;
				if(($to - $from) < $page && ($to - $from) < $pages) 
				{
					$from = $pages - $page + 1;
				}
			}
		}

		$multipage = ($curpage - $offset > 1 && $pages > $page ? '<a href="'.$mpurl.'page=1">&#8249;&#8249; 首页' : '').($curpage > 1 ? '<a href="'.$mpurl.'page='.($curpage - 1).'">&#8249; 上一页</a>' : '');
		for($i = $from; $i <= $to; $i++) {
			$multipage .= $i == $curpage ? '<span class="page_bar_current">'.$i.'</span>' : '<a href="'.$mpurl.'page='.$i.'">'.$i.'</a>';
		}

		$multipage .= ($curpage < $pages ? '<a href="'.$mpurl.'page='.($curpage + 1).'">下一页 &#8250;</a>' : '').($curpage + $page - $offset <= $pages ? '<a href="'.$mpurl.'page='.$pages.'">尾页 &#8250;&#8250;</a>' : '').($curpage == $maxpages ? '<a href="'.$mpurl.'pages='.$maxpages.'">&gt;</a>' : '');

		$multipage = $multipage ? '<div class="page_bar">'.''.'<span class="page_bar_current">共&nbsp;'.$num.'&nbsp;条&nbsp;/&nbsp;'.$realpages.'&nbsp;页&nbsp;</span>'.$multipage.''.'</div>' : '';
		}
	return $multipage;
	}

//生成导航条
        function ShowLink1() 
        {
		 	if($this->Total < 1){
				return " ";
		 	}else{
            	return "| ".$this->FristPage()." | ".$this->PrePage()." | ".$this->NextPage()." | ".$this->LastPage()." | ".$this->Totalnum().$this->JumpPage();
		 	}
        }

//生成导航条 1
        function ShowLink($pageurl='') 
        {
        	return $this->multi($this->Total, $this->PageSize, $this->PageNum(), $pageurl.'?dhb=hk'.$this->Url($this->LinkAry), $maxpages = 0);
        }
        function ShowLink2($pageurl='') 
        {
        	return $this->multi2($this->Total, $this->PageSize, $this->PageNum(), $pageurl.'?dhb=hk'.$this->Url($this->LinkAry), $maxpages = 0);
        }
 }
?>