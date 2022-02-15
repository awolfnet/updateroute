<?php
//���ó�ʱʱ��
ini_set('max_execution_time', 90);

//���ҵ�������
$cc=isset($_GET['cc'])?$_GET['cc']:"CN";

//IPЭ������
$type=isset($_GET['type'])?$_GET['type']:"ipv4";

//�ű�ƽ̨
$platform=isset($_GET['platform'])?$_GET['platform']:"ros-static-route";

//���ƽ̨
if(checkPlatform($platform)==false)
{
	//Not supported platform.
	echo "-1";
	die();
}
//���ipЭ������
if(checkIPType($type)==false)
{
	//Not supported ip type.
	echo "-2";
	die();
}

//��APNIC RIR�ĵ�
//�ĵ�˵����http://ftp.apnic.net/apnic/stats/apnic/
$file = fopen("http://ftp.apnic.net/apnic/stats/apnic/delegated-apnic-latest","rb");

//ÿ�ζ�ȡһ��
while($line=fgets($file))
{
	//����ע����
	if(strncmp($line,"#",1)!=0)
	{
		//�����
		$segments=explode("|",$line);
		//    0| 1|   2|           3|   4|       5|    	  6
		//apnic|IN|ipv4|43.227.128.0|1024|20141219|assigned
		
		//�жϵ���ָ�����Ҵ����IPЭ������
		if($segments[1]==$cc && $segments[2]==$type)
		{
			//ȡ������ź�����
			$routeNet=getRouteNet($segments[3],$segments[4]);
			//����ƽ̨���ɽű�
			echo selectPlatform($platform,$routeNet);
			echo "\n";
		}
	}
	
}

echo "#done";

/*
 *�Զ��庯������
 *
 */

//��������ź�����
function getRouteNet($startIP,$hostCount)
{
	//����������������λ��
	$maskBits=32-log($hostCount,2);
	$routeNet=$startIP . "/" . $maskBits;
	return $routeNet;				
}

//���ɽű�
function selectPlatform($platform,$routeNet)
{
	$script="";
	
	if($platform=="ros-static-route")	//ROS��̬·��
	{
		//�ű�ע��
		$comment=isset($_GET['comment'])?$_GET['comment']:"";
		
		//��̬·�ɹ������
		$distance=isset($_GET['distance'])?$_GET['distance']:"10";
		
		//�ű�����
		$gateway=isset($_GET['gateway'])?$_GET['gateway']:"";
		if(strlen($gateway)<=0)
		{
			//Need default gateway.
			echo "-3";
			die();
		}
		
		//���ɽű�
		$script=sprintf("/ip route add dst-address=%s gateway=%s distance=%d",$routeNet,$gateway,$distance);
		if(strlen($comment)>0)
		{
			$script.=sprintf(" comment=%s",$comment);
		}
	}else if($platform=="ros-policy-routing-rule")	//ROS����·��
	{
		//�ű�ע��
		$comment=isset($_GET['comment'])?$_GET['comment']:"";
		
		//·�ɱ��
		$routingMark=isset($_GET['routingmark'])?$_GET['routingmark']:"";
		
		//·�ɱ�
		$routeTable=isset($_GET['routetable'])?$_GET['routetable']:"main";
		
		//����
		$action=isset($_GET['action'])?$_GET['action']:"lookup";
		if(checkAction($action)==false)
		{
			//Not supported policy-routing rule action
			echo "-4";
			die();
		}
		
		//���ɽű�
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
		//Э������
		//Ҫʹ���Զ���Э�����ͣ���ر�֤/etc/iproute2/rt_protos�Ѿ�ע��ø�Э��
		$proto=isset($_GET['proto'])?$_GET['proto']:"";
		
		//�ű�����
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

//���ƽ̨֧��
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

//���ROS ·�ɶ���֧��
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

//���IPЭ������֧��
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