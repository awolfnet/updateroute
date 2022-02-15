<?php
//设置超时时间
ini_set('max_execution_time', 90);

//国家地区代码
$cc=isset($_GET['cc'])?$_GET['cc']:"CN";

//IP协议类型
$type=isset($_GET['type'])?$_GET['type']:"ipv4";

//脚本平台
$platform=isset($_GET['platform'])?$_GET['platform']:"ros-static-route";

//检查平台
if(checkPlatform($platform)==false)
{
	//Not supported platform.
	echo "-1";
	die();
}
//检查ip协议类型
if(checkIPType($type)==false)
{
	//Not supported ip type.
	echo "-2";
	die();
}

//打开APNIC RIR文档
//文档说明：http://ftp.apnic.net/apnic/stats/apnic/
$file = fopen("http://ftp.apnic.net/apnic/stats/apnic/delegated-apnic-latest","rb");

//每次读取一行
while($line=fgets($file))
{
	//跳过注释行
	if(strncmp($line,"#",1)!=0)
	{
		//拆分行
		$segments=explode("|",$line);
		//    0| 1|   2|           3|   4|       5|    	  6
		//apnic|IN|ipv4|43.227.128.0|1024|20141219|assigned
		
		//判断等于指定国家代码和IP协议类型
		if($segments[1]==$cc && $segments[2]==$type)
		{
			//取出网络号和掩码
			$routeNet=getRouteNet($segments[3],$segments[4]);
			//根据平台生成脚本
			echo selectPlatform($platform,$routeNet);
			echo "\n";
		}
	}
	
}

echo "#done";

/*
 *自定义函数部分
 *
 */

//生成网络号和掩码
function getRouteNet($startIP,$hostCount)
{
	//根据主机数求掩码位数
	$maskBits=32-log($hostCount,2);
	$routeNet=$startIP . "/" . $maskBits;
	return $routeNet;				
}

//生成脚本
function selectPlatform($platform,$routeNet)
{
	$script="";
	
	if($platform=="ros-static-route")	//ROS静态路由
	{
		//脚本注释
		$comment=isset($_GET['comment'])?$_GET['comment']:"";
		
		//静态路由管理距离
		$distance=isset($_GET['distance'])?$_GET['distance']:"10";
		
		//脚本网关
		$gateway=isset($_GET['gateway'])?$_GET['gateway']:"";
		if(strlen($gateway)<=0)
		{
			//Need default gateway.
			echo "-3";
			die();
		}
		
		//生成脚本
		$script=sprintf("/ip route add dst-address=%s gateway=%s distance=%d",$routeNet,$gateway,$distance);
		if(strlen($comment)>0)
		{
			$script.=sprintf(" comment=%s",$comment);
		}
	}else if($platform=="ros-policy-routing-rule")	//ROS策略路由
	{
		//脚本注释
		$comment=isset($_GET['comment'])?$_GET['comment']:"";
		
		//路由标记
		$routingMark=isset($_GET['routingmark'])?$_GET['routingmark']:"";
		
		//路由表
		$routeTable=isset($_GET['routetable'])?$_GET['routetable']:"main";
		
		//动作
		$action=isset($_GET['action'])?$_GET['action']:"lookup";
		if(checkAction($action)==false)
		{
			//Not supported policy-routing rule action
			echo "-4";
			die();
		}
		
		//生成脚本
		$script=sprintf("/ip route rule add dst-address=%s action=%s table=%s",$routeNet,$action,$routeTable);
		if(strlen($routingMark)>0)
		{
			$script.=sprintf(" routing-mark=%s",$routingMark);
		}
		
		if(strlen($comment)>0)
		{
			$script.=sprintf(" comment=%s",$comment);
		}
	}else if($platform=="linux-shell")
	{
		//协议类型
		//要使用自定义协议类型，务必保证/etc/iproute2/rt_protos已经注册好该协议
		$proto=isset($_GET['proto'])?$_GET['proto']:"";
		
		//脚本网关
		$gateway=isset($_GET['gateway'])?$_GET['gateway']:"";
		if(strlen($gateway)<=0)
		{
			//Need default gateway.
			echo "-5";
			die();
		}
		
		$script=sprintf("ip route add %s via %s",$routeNet,$gateway);
		if(strlen($proto)>0)
		{
			$script.=sprintf(" proto %s",$proto);
		}
	}
	return $script;
}

//检查平台支持
function checkPlatform($platform)
{
	$supportPlatforms="ros-static-route|ros-policy-routing-rule|linux-shell";
	if(strpos($supportPlatforms,$platform)!==false)
	{
		return true;
	}else
	{
		return false;
	}
}

//检查ROS 路由动作支持
function checkAction($action)
{
	$supportAction="drop|lookup|lookup-only-in-table|unreachable";
	if(strpos($supportAction,$action)!==false)
	{
		return true;
	}else
	{
		return false;
	}
}

//检查IP协议类型支持
function checkIPType($type)
{
	$supportType="ipv4|ipv6";
	if(strpos($supportType,$type)!==false)
	{
		return true;
	}else
	{
		return false;
	}
}
?>