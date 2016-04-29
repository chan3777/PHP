<?php

/*
*	1、数据库中有重复数据没有处理
*	2、图片重复未处理
*	3、只能手动刷新
*	4、内容抓取偶尔出现警告： failed to open stream: HTTP request failed!，网上完美解决方案是使用CURL方法，未测试
*/

	set_time_limit(0);
	//获取网页代码
	$ret = file_get_contents('http://www.wtoutiao.com/');//OK，采集网页源代码,还有两种采集方式

	//----------------------------------------------------------------------------------------------
	
	$con = new mysqli('localhost', 'root', 'chan3777@123', 'webdata');	//连接数据库
	
	//----------------------------------------------------------------------------------------------
	
	mysqli_query($con,"SET NAMES utf8"); //防止中文数据写入数据库乱码

	//----------------------------------------------------------------------------------------------


	//----------------------------------------------------------------------------------------------
	
	//利用正则表达式取出网页源代码中需要的数据
	preg_match_all('#<div class="news-img"><a.*?><img.*?data-original="([^"]*)"[^>]*>#i', $ret, $imgURL);  //图片地址
	preg_match_all('#<div class="news-img"><a.*?title="([^"]*)"[^>]*>#i', $ret, $imgName); //图片title
	preg_match_all('#<div class="news-header"><h3><a.*? title="([^"]*)"[^>]*>#i', $ret, $title);
	preg_match_all('#<div class="news-main"><p>((.|\n|\t|\r)*?)<\/p>#i', $ret, $content);  //获取详情，注意：没有|\n|\t|\r会跳过并且将后面的一个详情插入，导致其后所有详情全部错位
	preg_match_all('#<div class="news-header"><h3><a.*?href="([^"]*)"[^>]*>#i', $ret, $href);   //newsURL = "http://www.wtoutiao.com"+$href，获取文章详细地址
	preg_match_all('#<span class="author"><a.*?>(.*?)<\/a>#i', $ret, $author);  //获取文章作者
	preg_match_all('#<span class="date">(.*?)<\/span>#i', $ret, $date);  //获取文章发表时间

	//采集热门文章和最最新文章内容，注意部分内容只有六张图片，每部分三张
	preg_match_all('#<div class="newscardtext".*?><h3.*?><a.*?href="([^"]*)"[^>]*>#i',$ret, $newscardhref);   //热门文章和最最新文章连接地址
	preg_match_all('#<div class="newscardtext".*?><h3.*?><a.*?title="([^"]*)"[^>]*>#i',$ret, $newscardtitle); //热门文章和最最新文章标题
	preg_match_all('#<div class="newscardtext".*?><a.*?><img.*?src="([^"]*)"[^>]*>#i',$ret, $newscardimgURL);  //热门文章和最最新文章图片地址

	//----------------------------------------------------------------------------------------------
	
	$num = count($imgURL[1]); //记录主内容采集数目，也就是数组元素个数
	$cardnum = count($newscardhref[1]); //记录热门文章及最新文章数目

	//----------------------------------------------------------------------------------------------

	//建立存储图片文件夹，文件命名以时间为准备，如果直接用时间命名图片名称，会出现图片被覆盖
	$file=date("Ymdhis");  //文件夹名
	mkdir('./img/news-view/'.$file);   //建立文件夹，要先建立文件夹，后面的图片才能保存进文件夹
	mkdir('./img/newscard-hot/'.$file);
	mkdir('./img/newscard-new/'.$file);

	//----------------------------------------------------------------------------------------------

	//将热门文章和最新文章写入数据库，并将图片保存
	for($j = 0; $j < $cardnum; $j++)
	{
		//由于数据无法直接将其数据写入数据库，这里是将数组中的数据放到变量中，通过变量将数据写入数据库
		$newscardhrefc = "http://www.wtoutiao.com".$newscardhref[1][$j];  //网页中使用的相对地址，这里将其补全为绝对地址
		$newscardtitlec = $newscardtitle[1][$j];
		//$newscardimgURLc = $newscardimgURL[1][$j];   //此处不能使用这句，因为上面正则获取到图片地址只有六个存在数组中，那么数组的长度为6，当循环超过6此以后，会出现错误

		if($j < 9)  //由于热门文章只有九条，此处判断是否还属于热门文章的条目，超过九条那么就是最新文章的条目
		{
			if($j < 3)  //因为热门文章只有三张图片，这里用来判断图片是否属于热门文章
			{
				$newscardimgURLc = $newscardimgURL[1][$j]; //提取对应的图片地址
				$cardsql = "insert into newscardhot(href, title, imgURL) values ('$newscardhrefc', '$newscardtitlec', '$newscardimgURLc')"; //写入数据库
			}
			else  //超出三张图片，后面的条目数不含有图片的，所以去除图片地址的写入
			{
				$cardsql = "insert into newscardhot(href, title) values ('$newscardhrefc', '$newscardtitlec')";
			}
			
		}
		else   //最新文章条目写入
		{
			if(($j - 9) < 3)   //此处判断最新文章是否含有图片（前三条有图片 (< 3)），又因为最新文章与热门文章是同时采集的，要去除前面九条热门文章条目（$j - 9）
			{
				$newscardimgURLc = $newscardimgURL[1][($j - 6)];  // 最新文章在数组中最小小标是9，前三条有图片的条目中的图片地址在数组中位于后三位，即下标为3、4、5的数组，此时$j最小为9，所以用$j - 6 来得到图片地址下标
				$cardsql = "insert into newscardnew(href, title, imgURL) values ('$newscardhrefc', '$newscardtitlec', '$newscardimgURLc')";//写入数据库
			}
			else
			{
				$cardsql = "insert into newscardnew(href, title) values ('$newscardhrefc', '$newscardtitlec')";   //不含图片的条目写入数据库
			}
		}

		$result = mysqli_query($con, $cardsql);  //执行写入操作
		
		//----------------------------------------------------------------------------------------------
		
		//保存热门文章和最新文章中的图片到不同的文件夹
		if($j < 3)
		{
			$img_name = $j.".jpg";  //文件名直接使用数字1、2、3....来命名，后缀名直接指定为jpg

			file_put_contents('./img/newscard-hot/'.$file.'/'.$img_name, file_get_contents($newscardimgURL[1][$j]));  //写入文件夹，file_get_contents()函数用来下载图片流
		}
		else
			if($j >= 3 && $j < 6) //基本同上
		{
			$img_name = ($j-3).".jpg";
			file_put_contents('./img/newscard-new/'.$file.'/'.$img_name, file_get_contents($newscardimgURL[1][$j]));
		}
	}


	//----------------------------------------------------------------------------------------------
	
	//主内容写入数据库，注意如果页面刷新过快，会导致数据库有大量重复数据
	for ($i = 0; $i < $num; $i++)
	{
		//由于数据无法直接将其数据写入数据库，这里是将数组中的数据放到变量中，通过变量将数据写入数据库
		$imgURLc = $imgURL[1][$i]; 
		$imgNamec = $imgName[1][$i];
		$titlec = $title[1][$i];
		$contentc = isset($content[1][$i])?$content[1][$i]:NULL;   //解决出现数组不存在的情况Notice: Undefined offset 
		$hrefc = "http://www.wtoutiao.com".$href[1][$i];  //href网页中使用的相对地址，这里将其补全为绝对地址
		$authorc = $author[1][$i];
		$datec = $date[1][$i];
		
		
		$sql = "insert into news(imgURL,imgName,title,content,href,author,date) values ('$imgURLc','$imgNamec','$titlec','$contentc','$hrefc','$authorc','$datec')"; //将数据写入对应的表项中
		
		$result = mysqli_query($con, $sql);   //执行mysql写入操作		
		
		//与上面写入图片类似
		$img_name = $i.".jpg";
		file_put_contents('./img/news-view/'.$file.'/'.$img_name, file_get_contents($imgURL[1][$i]));	
	}
?>