<?php
/**
 * file: system.profile.php 
 * desc: 查询数据导出
 * user: liujx
 * date: 2015-11-13
 */
// 设置头信息
header('Content-Type: text/html; charset=UTF-8');
ini_set('display_error', 'on');
set_time_limit(0);

/**
 * post() 获取POST参数
 * @param string $name 		参数名称
 * @param mixed  $default 	不存在或者为空时的默认值
 * @return mixed 返会接收到的数据
 */
function post($name, $default = '')
{
	if (isset($_POST[$name]) && ! empty($_POST[$name])) $default = $_POST[$name];
	return $default;
}

// 接收参数
$host = post('host', '172.24.3.96:27017');
$dbname  = post('dbname', 'test');
$ts   = post('ts');
$op   = post('op');
$ns   = post('ns');
$millis = post('millis');
$sub  = post('sub');
$options = post('options');
$co = post('co');

$html = '';


	$collection = 'system.profile';


$haystack = array("$dbname.$collection","$dbname.\$cmd","$dbname.system.indexes","$dbname.system.namespaces");

	// 判断数据是否存在
	$mongo   = new Mongo('mongodb://'.$host);

	//获取数据库列表
	$select_dbstr = '<select name="dbname" id="dbname"><option value="">';
	$dbases = $mongo->listDBs();
	foreach ($dbases['databases'] as $dbs) {
			$sdbname = $dbs['name'];
			if($sdbname == $dbname)$isselect = " selected ";
			else $isselect = "";
	        $select_dbstr .= '<option value="'.$sdbname.'" '.$isselect.'>'.$sdbname.'</option>';
	     }
	$select_dbstr .= '</select>';



	if($_POST['reset_profile']){
		$profile_size = intval($_POST['profile_size'])>1000000 ? $_POST['profile_size'] : 1000000;
		$mongo->$dbname->setProfilingLevel(0);
		$mongo->$dbname->dropCollection($collection);
		$mongo->$dbname->command(array(
			    "create" => $collection,
			    "capped" => true,
			    "size" => $profile_size
			));
		$profilelevel = empty($_POST['profilelevel']) ? 0:$_POST['profilelevel'];
		$mongo->$dbname->setProfilingLevel($profilelevel);
		
	}


	$collect = $mongo->$dbname->selectCollection($collection);
	

	if($_POST['levelsub']){

		$profilelevel = empty($_POST['profilelevel']) ? 0:$_POST['profilelevel'];
		if($profilelevel==0 || $profilelevel==2){
			$mongo->$dbname->setProfilingLevel($profilelevel);
		}else{
			$mongo->$dbname->command(array('profile' => 1, 'slowms' => $profilelevel));
		}
		print_r($mongo->$dbname->getProfilingLevel());

	}

// 定义错误信息
if (!empty($sub))
{
	// 查询数据
	$where = array();
	// 开始时间
	if ($ts)
	{
		$where['ts'] = array('$gte' => new MongoDate(strtotime($ts)));
	}
	
	// 结束时间
	if ($op)
	{
		$where['ts']['$lte'] = new MongoDate(strtotime($op));
	}

	if($options)
	{
		$where['op'] = $options;
	}
	if($co)
	{
		$where['ns'] = $co;
	}

	// mills
	if ($millis)
	{
		$where['millis'] = array('$gte' => (int)$millis);
	}

	// 执行时间
	if ($ns)
	{
		$ns = new MongoInt64($ns);
		$local = array();
		$gte  =  array('$gte' => $ns);
		$where['$or'] = array(
			array('lockStats.timeLockedMicros.r' => $gte),
			array('lockStats.timeLockedMicros.w' => $gte),
		);
	}

	$cursor = $collect->find($where, array('ts', 'op', 'ns', 'lockStats', 'responseLength', 'query', 'nscanned', 'millis','ntoreturn','updateobj','client'));
	// 设置标题
	$configTH = array(
		'开始时间(ts)' , 
		'执行方式(op)' ,
		'执行表(ns)',
		'执行时间R',
		'执行时间W',
		'数据大小(responseLength)',
		'扫描记录数(nscanned)',
		'millis',
		'返回记录数(nreturned)',
		'检索条件(query)',
		'执行内容(updateobj)',
		'client'
	);

	$html .= '<tr>';
	$scv = '';
	foreach($configTH as $key => $val)
	{
		$html .= '<td>'.$val.'</td>';
		$scv  .= iconv('UTF-8', 'GB2312', $val).',';
	}
	$scv = trim($scv, ',')."\r\n";

	$html .= '</tr>';
	

	//写文件
	$fp = fopen($collection.".csv", "w+");
	fputs($fp,$scv);

	//汇总
	$profile_total = array();
	$total = array();
	$total_record = 0;

	while( $cursor->hasNext() ) {

		$value = $cursor->getNext();
		
		if (isset($value['query']))
		{
			foreach($value['query'] as $k =>  &$val)
			{
				if ($k == 'item_id' || $k == '')
				{
					$val = sprintf('%.0f', $val);
				}
				
			}
		}

		$tt1 = isset($value['ts']) ? date('Y-m-d H:i:s', $value['ts']->sec) : 0;
		$tt2 = isset($value['op']) ? $value['op'] : 0;
		$tt3 = isset($value['ns']) ? $value['ns'] : 0;

		if(in_array($tt3, $haystack))
		{
			continue;
		}


		$tt4 = isset($value['lockStats']['timeLockedMicros']) ? $value['lockStats']['timeLockedMicros']['r'] : '';
		$tt9 = isset($value['lockStats']['timeLockedMicros']) ? $value['lockStats']['timeLockedMicros']['w'] : '';
		$tt5 = isset($value['responseLength']) ? $value['responseLength'] : 0;
		$tt7 = isset($value['nscanned']) ? $value['nscanned'] : 0;
		$tt8 = isset($value['millis']) ? $value['millis'] : 0;
		$tt6 = isset($value['query']) ? str_replace(',', ';', json_encode($value['query'])) : '';
		$tt10 = isset($value['ntoreturn']) ? $value['ntoreturn'] : 0;
		$tt11 = isset($value['updateobj']) ? str_replace(',', ';', json_encode($value['updateobj'])) : '';
		$tt12 = isset($value['client']) ? $value['client'] : 0;

		// csv数据
		//$scv  = sprintf("%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\r\n", $tt1, $tt2, $tt3, $tt4, $tt9, $tt5, $tt7, $tt8, $tt10, $tt6, $tt11, $tt12);
		fputs($fp,"$tt1, $tt2, $tt3, $tt4, $tt9, $tt5, $tt7, $tt8, $tt10, $tt6, $tt11, $tt12\n");
		// 显示数据
		$html .= sprintf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%sms</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>", 
			$tt1,
			$tt2,
			$tt3,
			$tt4,
			$tt9,
			number_format($tt5),
			$tt7,
			$tt8,
			$tt10,
			$tt6,
			$tt11,
			$tt12
		);


		
			$profile_total[$tt3]['opcount']++;
			$profile_total[$tt3]['tmillis']+=$tt8;
			$profile_total[$tt3]['tscanned']+=$tt7;
			$profile_total[$tt3]['tdatalen']+=$tt5;
			$profile_total[$tt3]['tlocalr']+=$tt4;
			if($tt2)$profile_total[$tt3]['toptions'][$tt2]++;

			if($total['lastts']){
				$total['ts'] = $total['ts'] + strtotime($tt1) - strtotime($total['lastts']);
				$total['lastts'] = $tt1;
			}else{
				$total['lastts'] = $tt1;
				$total['begints'] =  $tt1;
				$total['ts'] = 0;
			}

			$total['millis']+=$tt8;
			$total['opcount']++;
			$total['scanned']+=$tt7;
			$total['datalen']+=$tt5;
			$total['tlocalr']+=$tt4;

			$total_record++;

	}

	fclose($fp);
	//file_put_contents('./'.$collection.'.csv', $scv);
}



//获取collection列表
	$select_collstr = '<select name="co" id="collection_name"><option value="">';
	//
	$collections_name = $mongo->$dbname->listCollections();
	foreach ($collections_name as $collection) {
		if($collection == $co)$isselect = " selected ";
		else $isselect = "";
	    $select_collstr .= '<option value="'.$collection.'" '.$isselect.'>'.str_replace("$dbname.","",$collection).'</option>';
	}
	$select_collstr .= '</select>';




?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>查询systm.profile表中数据</title>
	<style type="text/css">
	* {
		margin:auto 5px;
		padding:0;
	}
	table.mtable {border:solid #add9c0; border-width:1px 0px 0px 1px;vertical-align: center;}
	table.mtable tr td {border:solid #add9c0; border-width:0px 1px 1px 0px; padding:5px 0px;}
	div {height:20px; color:red;padding:5px;width:80%; margin:0 auto;}
	</style>
</head>
<body>
<h2>MONGODB性能分析工具</h2>
<p>海贼09ge 192.168.0.82:27017</p>
<p>死神1377 192.168.0.88:27017</p>
<p>海贼内网 172.24.3.96:27017</p>
<form action="?action=query" method="post">
<table class="mtable">
	<tr>
		<td>Mongo连接信息</td>
		<td><input type="text" size="20" value="<?php echo $host; ?>" name="host"></td>
		<td>Mongo库</td>
		<td><?php echo $select_dbstr; ?></td>
		
		<td>重置system.profile大小:</td>
		<td><input type="text" value="100000000" name="profile_size"><input type="submit" name="reset_profile"  value="重置" /></td>
		<td></td>
		<td><input type="text" value="2" name="profilelevel"></td>
		<td>(0,1,2|1填写具体毫秒数)</td>
		<td><input type="submit" name="levelsub"  value="日志等级" /> </td>
		<td></td>
		<td></td>

	</tr>
	<tr>
		
		<td>开始时间</td>
		<td><input type="text" value="<?php echo $ts; ?>" name="ts"></td>
		<td>结束时间</td>
		<td><input type="text" value="<?php echo $op; ?>" name="op"></td>
		<td>表对象</td>
		<td><?php echo $select_collstr; ?></td>
		<td>执行方式</td>
		<td><input type="text" value="<?php echo $options; ?>" name="options"></td>
		<td>执行时间</td>
		<td><input type="text" value="<?php echo $ns; ?>" name="ns"></td>
		<td>millis</td>
		<td><input type="text" value="<?php echo $millis; ?>" name="millis"></td>

	<tr>
	<tr>
		<td colspan="12">
			<input type="submit" name="sub"  value="搜索" />
			<a href="./system.profile.csv">下载文件</a>
		</td>
	<tr>
</table>
</form>



<table class="mtable">
	<tr>
		<td>collection</td>
		<td>totaloption</td>
		<td>totallock/r(μs)</td>
		<td>totaltmillis(ms)</td>
		<td>premillis(ms)</td>
		<td>totaltscanned</td>
		<td>totaltdatalen(Byte)</td>
		<td>predatalen(Byte)</td>
		<td>options</td>
	</tr>
	<?php 
		
			
		foreach((array)$profile_total as $c => $v)
		{
			echo "<tr>";
			echo "<td>$c</td>";
			echo "<td>".$v['opcount']."</td>";
			echo "<td>".number_format($v['tlocalr'])."</td>";
			echo "<td>".$v['tmillis']."</td>";
			$premillis = $v['tmillis']/$v['opcount'];

			echo "<td>".number_format($premillis,3)."ms</td>";
			echo "<td>".$v['tscanned']."</td>";
			echo "<td>".number_format($v['tdatalen'])." </td>";
			$predatalen = $v['tdatalen']/$v['opcount'];
			echo "<td>".number_format($predatalen)."</td>";

			$x="";
			foreach((array)$v['toptions'] as $k =>$c){
				$x.="$k:$c,";
			}
			echo "<td>".$x."</td>";


			echo "</tr>";
		}

		


		echo "<tr>";
		

		echo "<td>".$total['begints']."</br>".$total['lastts']."</br>".$total['ts']."</td>";
		echo "<td>".$total['opcount']."</td>";
		if($total['opcount'])$prelocalr =  $total['tlocalr']/$total['opcount'];
		echo "<td>".number_format($prelocalr)."</td>";
		echo "<td>".$total['millis']."</td>";
		if($total['opcount'])$premillis = $total['millis']/$total['opcount'];
		echo "<td>".number_format($premillis,3)."ms</td>";
		echo "<td>".$total['scanned']."</td>";
		echo "<td>".number_format($total['datalen'])." </td>";
		if($total['opcount'])$predatalen = $total['datalen']/$total['opcount'];
		echo "<td>".number_format($predatalen)."</td>";
		echo "<td></td>";
		echo "</tr>";

	?>
	


</table>


<table class="mtable">
<?php if($co || ($total_record<500))echo $html; ?>
</table>
</body>
</html>
