<?php

namespace cardbase
{
	function init() {}
	
	function cardlist_decode($str){
		if (eval(__MAGIC__)) return $___RET_VALUE;
		$ret = explode('_',$str);
		if(empty($ret)) $ret[] = '0';
		return $ret;
	}
	
	function cardlist_encode($arr){
		if (eval(__MAGIC__)) return $___RET_VALUE;
		$ret = implode('_',$arr);
		return $ret;
	}

	function get_user_cards($username){
		if (eval(__MAGIC__)) return $___RET_VALUE;
		$udata = fetch_udata_by_username($username);
		$cardlist = get_user_cards_process($udata);
		return $cardlist;
	}	
	
	function get_user_cards_process($udata){
		if (eval(__MAGIC__)) return $___RET_VALUE;
		$cardlist = cardlist_decode($udata['cardlist']);
		return $cardlist;
	}
	
	function get_energy_recover_rate($cardlist, $qiegao)
	{
		if (eval(__MAGIC__)) return $___RET_VALUE;
		eval(import_module('sys','cardbase'));
		/*
		 * 返回 Array ('S'=>..,'A'=>..,'B'=>..,'C'=>0)
		 */
		/*
		 * 新规：S卡CD时间大约在1-3天
		 * A卡CD时间大约在半天-1天
		 * B卡CD时间大约为几小时
		 */
		$ret = Array();
		//$ret['S']=100.0/7/86400;	//S卡固定基准CD 7天
		$ret['C']=0;			//C卡不受能量制影响
		$ret['M']=0;			//M卡更不受能量制影响
		$cnt=Array(); $cnt['S']=0; $cnt['A']=0; $cnt['B']=0;
		//计算S卡、A卡、B卡的数目
		foreach ($cardlist as $key)
		{
			if ($cards[$key]['rare']=='S') $cnt['S']++;
			if ($cards[$key]['rare']=='A') $cnt['A']++;
			if ($cards[$key]['rare']=='B') $cnt['B']++;
		}
		//估算现有切糕对卡片数量的影响，也即还可抽出多少张新卡
//		$bcost = Array('S'=> 90/0.01, 'A' => 90/0.05, 'B'=>90/0.2);
//		foreach (Array('S','A','B') as $ty)
//		{
//			$z=$qiegao;
//			$all=count($cardindex[$ty]);
//			while ($cnt[$ty]<$all && $z>$bcost[$ty]*$all/($all-$cnt[$ty]))
//			{
//				$z-=$bcost[$ty]*$all/($all-$cnt[$ty]);
//				$cnt[$ty]++;
//			}
//		}
		
		$tbase = Array('S' => 86400.0, 'A' => 28800.0, 'B' => 3600.0);
		foreach (Array('S','A','B') as $ty)
		{
			//卡片数目开根号
			$z = round(sqrt($cnt[$ty]));
			if($z<1) $z = 1;
//			$z=$cnt[$ty]/2;
//			if ($cnt[$ty]<=6) $z=$cnt[$ty]*2/3; 
//			if ($cnt[$ty]<=3) $z=2; 
			
			$tbase[$ty]*=$z;
			$ret[$ty]=100.0/$tbase[$ty];
		}
		return $ret;
	}
		
	//获得玩家的卡片数据，会自动更新卡片冷却信息
	function get_user_cardinfo($who)
	{
		if (eval(__MAGIC__)) return $___RET_VALUE;
		eval(import_module('sys','cardbase'));
		if(is_array($who) && isset($who['username'])) {//减少一次数据库操作
			$udata = $who;
			$who = $udata['username'];
		}else{
			$udata = fetch_udata_by_username($who);
		}
		
		$cardlist = get_user_cards_process($udata);		
		$energy_recover_rate = get_energy_recover_rate($cardlist, $udata['gold']);
		
		$cardenergy=Array();
		if ($udata['cardenergy']=="") $t=Array(); else $t=explode('_',$udata['cardenergy']);
		$lastupd = $udata['cardenergylastupd'];
		
		for ($i=0; $i<count($cardlist); $i++)
			if ($i<count($t))
			{
				$cardenergy[$cardlist[$i]]=((double)$t[$i])+($now-$lastupd)*$energy_recover_rate[$cards[$cardlist[$i]]['rare']];
				if (in_array($cards[$cardlist[$i]]['rare'], array('C','M')) || $cardenergy[$cardlist[$i]] > $cards[$cardlist[$i]]['energy']-1e-5)
					$cardenergy[$cardlist[$i]] = $cards[$cardlist[$i]]['energy'];
			}
			else
			{
				$cardenergy[$cardlist[$i]] = $cards[$cardlist[$i]]['energy'];
			}
		
		$ret=Array(
			'cardlist' => $cardlist,
			'cardenergy' => $cardenergy,
			'cardchosen' => $udata['card'],
			'cardenergylastupd' => $now,
		);
		
		if($t != $cardenergy) {
			save_cardenergy($ret, $who);
		}
			
		return $ret;
	}
	
	//更新卡片能量数据库，会自动将能量值转化为浮点数
	function save_cardenergy($data, $who)
	{
		if (eval(__MAGIC__)) return $___RET_VALUE;
		eval(import_module('sys'));
		if(is_array($data['cardenergy'])) {
			$cd_n='';
			for ($i=0; $i<count($data['cardlist']); $i++)
			{
				$x=(double)$data['cardenergy'][$data['cardlist'][$i]];
				if ($i>0) $cd_n.='_';
				$cd_n.=$x;
			}
		}else{
			$cd_n = $data['cardenergy'];
		}
		$upd=Array(
			'cardenergy' => $cd_n,
			'cardenergylastupd' => $data['cardenergylastupd'],
		);
		update_udata_by_username($upd, $who);
	}
	
	//生成一条获得卡片的站内信，返回值为1则表示是新卡
	function get_card_message($ci,$ext='',&$pa=NULL)
	{
		if (eval(__MAGIC__)) return $___RET_VALUE;
		eval(import_module('sys','player','cardbase'));
		if ($pa==NULL){
			$n=$name;
		}else{
			if (isset($pa['username'])) $n=$pa['username'];
			else $n=$pa['name'];
		}
		//判定卡片是不是新卡
		$result = fetch_udata_by_username($n,'cardlist');
		if(empty($result)) return;
		//if(!empty($ext)) $ext.='<br>';
		include_once './include/messages.func.php';
		message_create(
			$n,
			'获得卡片',
			$ext.'查收本消息即可获取此卡片，如果已有此卡片则会转化为切糕。',
			'getcard_'.$ci
		);
		
		$ret = 0;
		$clist = explode('_',$result['cardlist']);
		if (!in_array($ci,$clist)) $ret = 1;
		return $ret;
	}
	
	//获得卡的外壳，主要是数据库读写
	function get_card($ci,&$pa=NULL,$ignore_qiegao=0)
	{
		if (eval(__MAGIC__)) return $___RET_VALUE;
		eval(import_module('sys','player','cardbase'));
		if ($pa==NULL){
			$n=$name;
		}else{
			if (isset($pa['username'])) $n=$pa['username'];
			else $n=$pa['name'];
		}
		$pu = fetch_udata_by_username($n);
		$ret = get_card_process($ci,$pu,$ignore_qiegao);
		
		$upd = array(
			'cardlist' => $pu['cardlist'],
			'gold' => $pu['gold'],
		);
		update_udata_by_username($upd, $n);
		return $ret;
	}
	
	//获得卡片和切糕的核心判定，如果卡重复，则换算成切糕
	//会自动判定输入的cardlist键值是字符串还是数组
	function get_card_process($ci,&$pa,$ignore_qiegao=0){
		if (eval(__MAGIC__)) return $___RET_VALUE;
		if(!is_array($pa['cardlist'])) {
			$cl_changed = 1;
			$pa['cardlist'] = explode('_',$pa['cardlist']);
		}
		eval(import_module('sys','player','cardbase'));
		if (in_array($ci,$pa['cardlist'])){
			if(!$ignore_qiegao) $pa['gold'] += $card_price[$cards[$ci]['rare']];
			$ret = 0;
		}else{
			$pa['cardlist'][] = $ci;
			$ret = 1;
		}
		if(!empty($cl_changed)) $pa['cardlist'] = implode('_',$pa['cardlist']);
		return $ret;
	}
	
	function get_qiegao($num,&$pa=NULL)
	{
		if (eval(__MAGIC__)) return $___RET_VALUE;
		eval(import_module('sys','player'));
		if ($pa==NULL){
			$n=$name;
		}else{
			if (isset($pa['username'])) $n=$pa['username'];
			else $n=$pa['name'];
		}
		$result = fetch_udata_by_username($n,'gold');
		$cg = $result['gold'];
		$cg=$cg+$num;
		if ($cg<0) $cg=0;
		if($pa) $pa['gold'] = $cg;
		update_udata_by_username(array('gold' => $cg), $n);
	}
	
	function calc_qiegao_drop(&$pa,&$pd,&$active){
		if (eval(__MAGIC__)) return $___RET_VALUE;
		eval(import_module('cardbase','sys','logger','map'));
		$qiegaogain=0;
		if (!in_array($gametype,$qiegao_ignore_mode)){		
			if ($pd['type']==90)	//杂兵
			{
				if ($areanum/$areaadd<1)	//0禁
				{
					$dice = rand(0,99);
					if ($dice<5) 
						$qiegaogain=rand(7,15);
					else if ($dice<20)
						$qiegaogain=rand(3,7);
					else if ($dice<50)
						$qiegaogain=rand(1,3);
				}
				else if ($areanum/$areaadd<2)	//1禁
				{
					$dice = rand(0,99);
					if ($dice<5) 
						$qiegaogain=rand(3,5);
					else if ($dice<15)
						$qiegaogain=rand(1,3);
				}
			}
			if ($pd['type']==2)	//幻象
			{
				if ($areanum/$areaadd<1)
				{
					$qiegaogain=rand(9,19);
				}
				else if ($areanum/$areaadd<2)
				{
					$dice=rand(0,99);
					if ($dice<30)
						$qiegaogain=rand(3,7);
					else  $qiegaogain=rand(1,3);
				}
			}
		}
		return $qiegaogain;
	}
	
	function player_kill_enemy(&$pa,&$pd,$active){
		if (eval(__MAGIC__)) return $___RET_VALUE;
		$chprocess($pa, $pd, $active);
		battle_get_qiegao($pa,$pd,$active);
	}	
	
	function battle_get_qiegao(&$pa,&$pd,$active){
		if (eval(__MAGIC__)) return $___RET_VALUE;
		eval(import_module('logger'));
		$qiegaogain=calc_qiegao_drop($pa,$pd,$active);
		if ($qiegaogain>0){
			battle_get_qiegao_update($qiegaogain,$pa);
			$log.="<span class=\"orange\">敌人掉落了{$qiegaogain}单位的切糕！</span><br>";
		}
		return $qiegaogain;
	}
	
	function battle_get_qiegao_update($qiegaogain,&$pa)
	{
		if (eval(__MAGIC__)) return $___RET_VALUE;
		get_qiegao($qiegaogain,$pa);
	}
	
	/*
	function itemmix_success()
	{
		if (eval(__MAGIC__)) return $___RET_VALUE;
		eval(import_module('sys','player','logger','map','cardbase'));
		if (!in_array($gametype,$qiegao_ignore_mode)){
			if (($itm0=="绝冲大剑【神威】")&&(($areanum/$areaadd)<2)){
				if (get_card(42)==1){
					$log.="恭喜您获得了活动奖励卡<span class=\"orange\">Fleur</span>！<br>";
				}else{
					$log.="您已经拥有活动奖励卡了，系统奖励您<span class=\"yellow b\">100</span>切糕！<br>";
					get_qiegao(100);
				}
			}
		}
		$chprocess();	
	}*/
	
	function get_card_pack($card_pack_name) {
		if (eval(__MAGIC__)) return $___RET_VALUE;
		eval(import_module('cardbase'));
		$card_pack = Array();
		foreach ($cards as $ci => $card) {
			if ($card["pack"] == $card_pack_name)
				$card_pack[$ci] = $card;
		}
		//return  json_encode($card_pack, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."test";
		return $card_pack;
	}

	function get_card_pack_list() {
		if (eval(__MAGIC__)) return $___RET_VALUE;
		eval(import_module('cardbase'));
		return $packlist;
	}

	function in_card_pack($packname) {
		if (eval(__MAGIC__)) return $___RET_VALUE;
		eval(import_module('cardbase'));
		return in_array($packname, $packlist);
	}
	
	function kuji($type, &$pa, $is_dryrun = false){
		if (eval(__MAGIC__)) return $___RET_VALUE;
		eval(import_module('cardbase'));
		$ktype=(int)$type;
		if (defined('MOD_KUJIBASE')) {
			$kr=\kujibase\kujidraw($ktype, $pa, $is_dryrun);
			if (!is_array($kr)){
				if ($kr==-1){
					return -1;
				}else{
					$dr=array($kr);
				}
			}else{
				$dr=$kr;
			}
			return $dr;
		}
		return -1;
	}
	
	//卡片按罕贵从罕到平排序
	function card_sort($cards){
		if (eval(__MAGIC__)) return $___RET_VALUE;
		$ret = array();
		$typeweight = array('S'=> 1000000, 'A' => 100000, 'B' => 10000, 'C'=> 1000, 'M'=>0);
		foreach($cards as $ci => $cv){
			$cv['id'] = $ci;
			$weight = $typeweight[$cv['rare']] - $ci;
			$ret[$weight] = $cv;
		}
		krsort($ret);
		return $ret;
	}
	
	//卡包过滤器核心代码，过滤没开放的卡包
	function check_pack_availble($pn){
		if (eval(__MAGIC__)) return $___RET_VALUE;
		eval(import_module('sys','cardbase'));
		$ret = true;
		if(isset($packstart[$pn]) && $packstart[$pn] > $now) $ret = false;
		return $ret;
	}
	
	//卡包过滤器，过滤没开放的卡包。
	function pack_filter($packlist){
		if (eval(__MAGIC__)) return $___RET_VALUE;
		$n_packlist = array();
		foreach($packlist as $pv){
			if(check_pack_availble($pv)) $n_packlist[]=$pv;
		}
		return $n_packlist;
	}
	
	//卡名显示
	function parse_interface_profile()
	{
		if (eval(__MAGIC__)) return $___RET_VALUE;
		$chprocess();
		eval(import_module('sys','player','cardbase'));
		if($cardname == $cards[$card]['name']) {
			if(!empty($cards[$card]['title'])) 
				$uip['cardname_show'] = $cards[$card]['title'];
			else
				$uip['cardname_show'] = $cards[$card]['name'];
		}else{
			$uip['cardname_show'] = $cardname;
		}
	}
	
	//战斗界面显示敌方卡片
	function init_battle($ismeet = 0)
	{
		if (eval(__MAGIC__)) return $___RET_VALUE;
		$chprocess($ismeet);
		eval(import_module('sys','player','metman','cardbase'));
		
		$tdata['cardinfo'] = '';
		if(!empty($w_cardname)){
			if(!empty($cards[$w_card]['title']))
				$tdata['cardinfo'] = $cards[$w_card]['title'];
			else $tdata['cardinfo'] = $w_cardname;
		}
	}
	
	//如果成就或者卡片设定有变，更新卡片获得方式
	function parse_card_gaining_method()
	{
		if (eval(__MAGIC__)) return $___RET_VALUE;
		$dir = GAME_ROOT.'./gamedata/cache';
		//生成文件名
		$filename = 'card_gaining_method';
		$file = $dir.'/'.$filename.'.config.php';
		
		$card_config_file = GAME_ROOT.'/include/modules/extra/card/cardbase/config/card.config.php';
		$card_main_file = GAME_ROOT.'/include/modules/extra/card/cardbase/main.php';
		$ach_config_file = GAME_ROOT.'/include/modules/extra/achievement/achievement_base/config/achievement_base.config.php';
		
		//如果文件存在且最新，就不改变
		if(file_exists($file) && filemtime($card_main_file) < filemtime($file) && filemtime($card_config_file) < filemtime($file) && filemtime($ach_config_file) < filemtime($file)) return;
		
		$cgmethod = array();
		eval(import_module('sys','cardbase'));
		//抽卡
		foreach($cardindex as $ckey => $cval){
			foreach($cval as $ci)
				$cgmethod[$ci] = array('通过抽卡获得');
		}
		//成就
		if(defined('MOD_ACHIEVEMENT_BASE')){
			eval(import_module('achievement_base'));
			//生成未生效成就列表
			$ach_expired = array();
			foreach(array_keys($ach_available_period) as $aap_key){
				if(1 != \achievement_base\check_achtype_available($aap_key)) {
					$ach_expired = array_merge($ach_expired, $achlist[$aap_key]);
				}
			}
			foreach($achlist as $aclass => $aval) {
				foreach($aval as $ai) {
					if(defined('MOD_SKILL'.$ai.'_ACHIEVEMENT_ID') && !defined('MOD_SKILL'.$ai.'_ABANDONED') && !\skillbase\check_skill_info($ai, 'global')){
						eval(import_module('skill'.$ai));
						$astart = ${'ach'.$ai.'_name'};$astart = array_shift($astart);
						//新成就储存格式，直接读数据
						if(!empty(${'ach'.$ai.'_desc'})) {
							if(!empty(${'ach'.$ai.'_card_prize'})) {
								foreach (${'ach'.$ai.'_card_prize'} as $at => $aarr) {
									$cardset_flag = 1;
									if(!is_array($aarr)) {
										$aarr = array($aarr);
										$cardset_flag = 0;
									}
									foreach($aarr as $acard) {
										if(!isset($cgmethod[$acard])) $cgmethod[$acard] = array();
										$seriesname = $achtype[$aclass];
										$cardset_notice = $cardset_flag ? '可能' : '';
										$aname = ${'ach'.$ai.'_name'}[$at];
										if(count(${'ach'.$ai.'_name'})==1) $cgmethod_this = '完成'.$seriesname.'「'.$astart.'」'.$cardset_notice.'获得';
										elseif(preg_match('/LV\d/s', $aname)) $cgmethod_this = '完成'.$seriesname.'成就「'.$aname.'」'.$cardset_notice.'获得';
										else $cgmethod_this = '完成'.$seriesname.'「'.$astart.'」的第'.$at.'阶段「'.$aname.'」'.$cardset_notice.'获得';
										//过期成就判定
										if(in_array($ai, $ach_expired)) $cgmethod_this = '<font color=grey>'.$cgmethod_this.'</font>';
										$cgmethod[$acard][] = $cgmethod_this;
									}
								}
							}
						}else{
							//旧成就储存格式，要暴力读desc.htm
							$desc_cont_file = constant('MOD_SKILL'.$ai.'_DESC').'.htm';
							if(file_exists($desc_cont_file)){
								
								$desc_cont = file_get_contents($desc_cont_file);
								//第一步读取所有奖励显示
								preg_match_all('|if\s*?\(\$c'.$ai.'\s*?==\'(\d)\'.+?\-\-\>(.+?)\<\!\-\-\{/if|s', $desc_cont, $matches);
								$count = count($matches[0])-1;
								for($i=1;$i<=$count;$i++) {
									$at = $matches[1][$i];
									$adesc = $matches[2][$i];
									preg_match('|卡片.+?\<span.+?\>(.+?)\<\/span|s', $adesc, $matches2);
									if(!empty($matches2)) {
										$cn = $matches2[1];
										$acard = 0;
										foreach($cards as $acard => $cv){
											if($cv['name'] == $cn) break;
										}
										if($acard) {
											if(!isset($cgmethod[$acard])) $cgmethod[$acard] = array();
											$seriesname = $achtype[$aclass];
											$aname = ${'ach'.$ai.'_name'}[$at];
											if(count(${'ach'.$ai.'_name'})==1) $cgmethod[$acard][] = '完成'.$seriesname.'「'.$astart.'」获得';
											elseif(preg_match('/LV\d/s', $aname)) $cgmethod[$acard][] = '完成'.$seriesname.'「'.$aname.'」获得';
											else $cgmethod[$acard][] = '完成'.$seriesname.'「'.$astart.'」的第'.$i.'阶段「'.$aname.'」获得';
										}
									}
								}
							}
							
						}
					}
				}
			}
		}
		
		//特判
		$cgmethod[0][] = '注册账号即有';
		//$cgmethod[63][] = '在四禁前使锡安成员技能「破解」达到50层以上获得（只在标准、卡片、荣耀或极速模式有效）';
		$cgmethod[72][] = '完成竞速挑战「不动的大图书馆」获得';
		$cgmethod[78][] = '完成竞速挑战「烈火疾风」获得';
		$cgmethod[88][] = '完成战斗成就「谈笑风生」获得';
		$cgmethod[158][] = '在「伐木模式」从商店购买「博丽神社的参拜券」并在开局20分钟之内使用以获得';
		$cgmethod[159][] = '通过礼品盒开出的★闪熠着光辉的大逃杀卡牌包★获得（15%概率）';
		$cgmethod[160][] = '完成2017万圣节活动「噩梦之夜 LV2」获得';
		$cgmethod[165][] = '<br>当你看到某张小纸条有「奇怪的空白」时，你可以按下F12。<br>这张卡的获得方式，就藏在那段空白对应的页面代码的位置。<br>　　　　　　　　　　　　　　　　　　　　——林苍月';
		for($ci=200;$ci<=204;$ci++) {
			$cgmethod[$ci][] = '<font color=grey>完成2017十一活动「新的战场 LV2」可能获得</font>';
			$cgmethod[$ci][] = '<font color=grey>完成2017十一活动「新的战场 LV3」可能获得</font>';
			$cgmethod[$ci][] = '<font color=grey>完成2017十一活动「血染乐园 LV3」可能获得</font>';
			$cgmethod[$ci][] = '<font color=grey>完成2017十一活动「极光处刑 LV3」可能获得</font>';
			$cgmethod[$ci][] = '<font color=grey>完成2017万圣节活动「不给糖就解禁」可能获得</font>';
		}
		$cgmethod[200][] = '在「荣耀模式」模式击杀「全息实体 幻影斗将神 S.A.S」后，使用缴获的★锋利的卡牌包★获得（15%概率）';
		$cgmethod[201][] = '在「荣耀模式」模式击杀「全息实体 熵魔法传人 Howling」后，使用缴获的★长着兽耳的卡牌包★获得（15%概率）';
		$cgmethod[202][] = '在「荣耀模式」模式击杀「全息实体 通灵冒险家 星海」后，使用缴获的★套了好几层的卡牌包★获得（15%概率）';
		$cgmethod[203][] = '在「荣耀模式」模式击杀「全息实体 银白愿天使 Annabelle」后，使用缴获的★羽翼卡牌包★获得（15%概率）';
		$cgmethod[204][] = '在「荣耀模式」模式击杀「全息实体 麻烦妖精 Sophia」后，使用缴获的★蠢萌的卡牌包★获得（15%概率）';
		$cgmethod[211][] = '击杀场上所有NPC之后，击杀入场的「断罪女神 一一五」，之后使用缴获的★印着「Mind Over Matters」的卡牌包★获得（必定获得）';
		if(empty($cgmethod)) return;
		$contents = str_replace('?>','',$checkstr);//"<?php\r\nif(!defined('IN_GAME')) exit('Access Denied');\r\n";

		$contents .= '$card_gaining_method = '.var_export($cgmethod,1).';';
		
		file_put_contents($file, $contents);
		chmod($file, 0777);
	}
}

?>