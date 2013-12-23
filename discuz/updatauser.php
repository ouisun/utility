<?php

/**
 *      批量激活用户
 */

include_once('source/class/class_core.php');
include_once('source/function/function_core.php');

@set_time_limit(0);

$cachelist = array();
$discuz = C::app();

$discuz->cachelist = $cachelist;
$discuz->init_cron = false;
$discuz->init_setting = true;
$discuz->init_user = false;
$discuz->init_session = false;
$discuz->init_misc = false;

$discuz->init();
$config = array(
	'dbcharset' => $_G['config']['db']['1']['dbcharset'],
	'charset' => $_G['config']['output']['charset'],
	'tablepre' => $_G['config']['db']['1']['tablepre']
);
$theurl = 'updatauser.php';

if(empty($_GET['step'])) $_GET['step'] = 'start';

if($_GET['step'] == 'start') {
        //升级开始
	include_once('config/config_ucenter.php');
	include_once('uc_client/client.php');
	$version = uc_check_version();
	$version = $version['db'];
	if(!$devmode && !C::t('common_setting')->fetch('bbclosed')) {
		C::t('common_setting')->update('bbclosed', 1);
		require_once libfile('function/cache');
		updatecache('setting');
		show_msg('您的站点未关闭，正在关闭，请稍后...', $theurl.'?step=start', 5000);
	}
	show_msg('说明：<br>批量激活邮箱(PJ4695)<br>
                        给等待验证用户组中的用户，拥有“特爱共享奖”勋章的用户加22威望并激活邮箱<br><br>
			<a href="'.$theurl.'?step=data'.($_GET['from'] ? '&from='.rawurlencode($_GET['from']).'&frommd5='.rawurlencode($_GET['frommd5']) : '').'">准备完毕，升级开始</a>');
        
} elseif ($_GET['step'] == 'data') {
        //数据更新
	if(empty($_GET['op']) || $_GET['op'] == 'member') {
		$nextop = 'end';
		$limit = 1000;
                $groupid = 8;   //查出等待验证会员
		$start = !empty($_GET['start']) ? $_GET['start'] : 0;
                
		$needupgrade = DB::query("SELECT COUNT(*) FROM ".DB::table('common_member')." WHERE groupid=$groupid", 'SILENT');
		if($needupgrade) {
			$query = DB::query("SELECT uid,email,credits FROM ".DB::table('common_member')." WHERE groupid=$groupid ORDER BY uid LIMIT $start, $limit");
			if(DB::num_rows($query)) {
				while($member = DB::fetch($query)) {
                                        $uid = intval($member['uid']);
                                        
					$membermf = C::t('common_member_field_forum')->fetch($uid);
                                        $medals = explode("\t", $membermf['medals']);
                                        //特爱共享奖
                                        if(!in_array(53, $medals)){
                                                continue;
                                        }
                                        //设置用户组
                                        $query = C::t('common_usergroup')->fetch_all_not(array(6, 7));
                                        foreach($query as $group) {
                                             if($group['type'] == 'member'){
                                                if(!($member['credits'] >= $group['creditshigher'] && $member['credits'] < $group['creditslower']) && $member['groupid'] != $group['groupid']) {
                                                           continue;
                                                }
                                                $newgroupid = $group['groupid'];
                                             }
                                        }
                                        if(!$newgroupid)
                                                $newgroupid = 10;       //设置默认用户组 新兵入伍
                                        
                                        //更新
                                        DB::update('common_member', array('groupid'=>$newgroupid,'emailstatus'=>1), array('uid'=>$uid));
                                        
                                        //添加22声望
                                        $memberc = DB::fetch_first('SELECT uid,extcredits1 FROM '.DB::table('common_member_count')." WHERE uid=$uid LIMIT 1");
                                        DB::update('common_member_count', array('extcredits1'=>$memberc['extcredits1']+22), array('uid'=>$uid));
                                        
                                        $c = fopen(DISCUZ_ROOT.'data/activatuser.txt', 'a');
                                        fwrite($c, $uid.',');
                                        fclose($c);
                                        

				}
				$start += $limit;
				show_msg("用户批量激活中 ... $start", "$theurl?step=data&op=member&start=$start");
			}
			
		}
		show_msg("用户批量激活完成", "$theurl?step=data&op=$nextop");

	} else {
		show_msg("数据处理完成", "$theurl?step=cache");
	}

} elseif ($_GET['step'] == 'cache') {
	
	show_msg('<span id="finalmsg">缓存更新中，请稍候 ...</span><iframe src="/misc.php?mod=initsys" style="display:none;" onload="document.getElementById(\'finalmsg\').innerHTML = \'恭喜，数据库结构升级完成！为了数据安全，请删除本文件。'.$opensoso.'\'"></iframe>');

}


/**
 * [use]
 * @param type $message
 * @param type $url_forward
 * @param type $time
 * @param type $noexit
 * @param type $notice
 */
function show_msg($message, $url_forward='', $time = 1, $noexit = 0, $notice = '') {

	if($url_forward) {
		$url_forward = $_GET['from'] ? $url_forward.'&from='.rawurlencode($_GET['from']).'&frommd5='.rawurlencode($_GET['frommd5']) : $url_forward;
		$message = "<a href=\"$url_forward\">$message (跳转中...)</a><br>$notice<script>setTimeout(\"window.location.href ='$url_forward';\", $time);</script>";
	}

	show_header();
	print<<<END
	<table>
	<tr><td>$message</td></tr>
	</table>
END;
	show_footer();
	!$noexit && exit();
}


function show_header() {
	global $config;

	$nowarr = array($_GET['step'] => ' class="current"');
	if(in_array($_GET['step'], array('waitingdb','prepare'))) {
		$nowarr = array('sql' => ' class="current"');
	}
	print<<<END
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=$config[charset]" />
	<title> 数据库升级程序 </title>
	<style type="text/css">
	* {font-size:12px; font-family: Verdana, Arial, Helvetica, sans-serif; line-height: 1.5em; word-break: break-all; }
	body { text-align:center; margin: 0; padding: 0; background: #F5FBFF; }
	.bodydiv { margin: 40px auto 0; width:720px; text-align:left; border: solid #86B9D6; border-width: 5px 1px 1px; background: #FFF; }
	h1 { font-size: 18px; margin: 1px 0 0; line-height: 50px; height: 50px; background: #E8F7FC; color: #5086A5; padding-left: 10px; }
	#menu {width: 100%; margin: 10px auto; text-align: center; }
	#menu td { height: 30px; line-height: 30px; color: #999; border-bottom: 3px solid #EEE; }
	.current { font-weight: bold; color: #090 !important; border-bottom-color: #F90 !important; }
	input { border: 1px solid #B2C9D3; padding: 5px; background: #F5FCFF; }
	#footer { font-size: 10px; line-height: 40px; background: #E8F7FC; text-align: center; height: 38px; overflow: hidden; color: #5086A5; margin-top: 20px; }
	</style>
	</head>
	<body>
	<div class="bodydiv">
	<h1>数据库升级工具</h1>
	<div style="width:90%;margin:0 auto;">
	<table id="menu">
	<tr>
	<td{$nowarr[start]}>升级开始</td>
	<td{$nowarr[data]}>数据更新</td>
	<td{$nowarr[cache]}>升级完成</td>
	</tr>
	</table>
	<br>
END;
}

function show_footer() {
	print<<<END
	</div>
	<div id="footer">&copy; Comsenz Inc. 2001-2013 http://www.comsenz.com</div>
	</div>
	<br>
	</body>
	</html>
END;
}

?>
