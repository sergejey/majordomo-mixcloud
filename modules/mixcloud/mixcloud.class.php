<?php
/**
* MixCloud 
* @package project
* @author Wizard <sergejey@gmail.com>
* @copyright http://majordomo.smartliving.ru/ (c)
* @version 0.1 (wizard, 14:11:03 [Nov 04, 2016])
*/
//
//
class mixcloud extends module {
/**
* mixcloud
*
* Module class constructor
*
* @access private
*/
function mixcloud() {
  $this->name="mixcloud";
  $this->title="MixCloud";
  $this->module_category="<#LANG_SECTION_APPLICATIONS#>";
  $this->checkInstalled();
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams($data=0) {
 $p=array();
 if (IsSet($this->id)) {
  $p["id"]=$this->id;
 }
 if (IsSet($this->view_mode)) {
  $p["view_mode"]=$this->view_mode;
 }
 if (IsSet($this->edit_mode)) {
  $p["edit_mode"]=$this->edit_mode;
 }
 if (IsSet($this->tab)) {
  $p["tab"]=$this->tab;
 }
 return parent::saveParams($p);
}
/**
* getParams
*
* Getting module parameters from query string
*
* @access public
*/
function getParams() {
  global $id;
  global $mode;
  global $view_mode;
  global $edit_mode;
  global $tab;
  if (isset($id)) {
   $this->id=$id;
  }
  if (isset($mode)) {
   $this->mode=$mode;
  }
  if (isset($view_mode)) {
   $this->view_mode=$view_mode;
  }
  if (isset($edit_mode)) {
   $this->edit_mode=$edit_mode;
  }
  if (isset($tab)) {
   $this->tab=$tab;
  }
}
/**
* Run
*
* Description
*
* @access public
*/
function run() {
 global $session;
  $out=array();
  if ($this->action=='admin') {
   $this->admin($out);
  } else {
   $this->usual($out);
  }
  if (IsSet($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (IsSet($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
  $out['TAB']=$this->tab;
  $this->data=$out;
  $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
  $this->result=$p->result;
}
/**
* BackEnd
*
* Module backend
*
* @access public
*/
function admin(&$out) {
 $this->getConfig();
 $out['API_URL']=$this->config['API_URL'];
 if (!$out['API_URL']) {
  $out['API_URL']='http://';
 }
 $out['API_KEY']=$this->config['API_KEY'];
 $out['API_USERNAME']=$this->config['API_USERNAME'];
 $out['API_PASSWORD']=$this->config['API_PASSWORD'];
 if ($this->view_mode=='update_settings') {
   global $api_url;
   $this->config['API_URL']=$api_url;
   global $api_key;
   $this->config['API_KEY']=$api_key;
   global $api_username;
   $this->config['API_USERNAME']=$api_username;
   global $api_password;
   $this->config['API_PASSWORD']=$api_password;
   $this->saveConfig();
   $this->redirect("?");
 }
 if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
  $out['SET_DATASOURCE']=1;
 }
 if ($this->data_source=='mixcloud_favorites' || $this->data_source=='') {
  if ($this->view_mode=='' || $this->view_mode=='search_mixcloud_favorites') {
   $this->search_mixcloud_favorites($out);
  }
  if ($this->view_mode=='edit_mixcloud_favorites') {
   $this->edit_mixcloud_favorites($out, $this->id);
  }
  if ($this->view_mode=='delete_mixcloud_favorites') {
   $this->delete_mixcloud_favorites($this->id);
   $this->redirect("?");
  }
 }
}
/**
* FrontEnd
*
* Module frontend
*
* @access public
*/
function usual(&$out) {
    global $mode;
    if ($mode) {
        $this->mode=$mode;
    }
    $this->getConfig();

    $this->limit=100;

    if (!$this->config['API_KEY']) {
        $this->api_key='sh1t7hyn3Kh0jhlV';
    } else {
        $this->api_key=$this->config['API_KEY'];
    }

    if ($this->mode=='top') {
        //$this->topStations($out);
    }
    if ($this->mode=='categories') {
        $this->categories($out);
    }
    if ($this->mode=='search') {
        $this->search_items($out);
    }

    if ($this->mode=='') {
        $stations=SQLSelect("SELECT ITEM_ID as `key`, TITLE as `name`, '1' as FAVORITE FROM mixcloud_favorites ORDER BY mixcloud_favorites.ID DESC");
        if ($stations[0]['key']) {
            $total = count($stations);
            for ($i = 0; $i < $total; $i++) {
                $stations[$i]['name_URL']=urlencode($stations[$i]['name']);
                $stations[$i]['name_JS']=addcslashes($stations[$i]['name'],'\'');
            }
            $out['ITEMS']=$stations;
        }
    }

    if ($this->mode=='play') {
        global $item_id;
        global $item_title;

        if ($item_id) {
            $stream_url=$this->getStreamURL($item_id);
        }
        $out['STREAM_URL']=$stream_url;
        $out['ITEM_ID']=$item_id;
        $out['TITLE']=$item_title;

    }

    if ($this->mode=='favorites') {
        global $id;
        global $item_id;
        global $item_title;
        global $remove;

        if ($remove && $id) {
            SQLExec("DELETE FROM mixcloud_favorites WHERE  ITEM_ID = '".DBSafe($item_id)."'");
            $this->redirect("?");
        }
        if ($item_id) {
            $rec=array();
            $rec['ITEM_ID']=$item_id;
            $rec['TITLE']=$item_title;
            SQLExec("DELETE FROM mixcloud_favorites WHERE ITEM_ID = '".DBSafe($item_id)."'");
            SQLInsert('mixcloud_favorites',$rec);
        }
        echo 'OK';exit;

    }

    if ($this->mode=='playnow') {
        $this->play();
    }    
    
}

    function play() {
        global $item_id;
        global $terminal;

        $stream_url=$this->getStreamURL($item_id);

        if ($stream_url!='') {

            if (!$terminal) {
                $terminal='HOME';
            }

            $url=BASE_URL.ROOTHTML.'popup/app_player.html?ajax=1';
            $url.="&command=refresh&play_terminal=".$terminal."&play=".urlencode($stream_url);
            $result=getURL($url, 0);
            echo $result;
        }

        exit;

    }

    function getStreamURL($item_id) {

        $mixcloud_url='https://www.mixcloud.com/';
        $url=$mixcloud_url.preg_replace('/^\//','',$item_id);

        //echo $url."<br/>";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Host: www.mixcloud.com'));
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.27 Safari/537.36');
        curl_setopt($ch, CURLOPT_REFERER,$mixcloud_url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);     // bad style, I know...
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HEADER, 1);

        $tmpfname = ROOT . 'cached/cookie.txt';
        curl_setopt($ch, CURLOPT_COOKIEJAR, $tmpfname);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $tmpfname);

        $result = curl_exec($ch);

        if (preg_match('/m-p-ref="cloudcast_page" m-play-info="(.*)" m-preview=/',$result,$m)) {
            $playInfo=base64_decode($m[1]);
            $magicString=base64_decode('cGxlYXNlZG9udGRvd25sb2Fkb3VybXVzaWN0aGVhcnRpc3Rzd29udGdldHBhaWQ=');
            $magicString.=$magicString;
            $magicString.=$magicString;

            $total=strlen($playInfo);
            $playInfoArray=array();
            for($i=0;$i<$total;$i++) {
                $playInfoArray[]=substr($playInfo,$i,1);
            }

            $total=strlen($magicString);
            $magicStringArray=array();
            for($i=0;$i<$total;$i++) {
                $magicStringArray[]=substr($magicString,$i,1);
            }

            $zipped=$this->zip($magicStringArray,$playInfoArray);
            $total = count($zipped);
            $res='';
            for ($i = 0; $i < $total; $i++) {
                $res.=chr(ord($zipped[$i][0]) ^ ord($zipped[$i][1]));
            }
            $result=json_decode($res,true);
            if (is_array($result)) {
                $stream_id=$result['id'];
                $ping_session_id=$result['html5_ping_session_id']; // TODO: should we ping this periodically?
                $stream_url=$result['stream_url'];
                return $stream_url;
            }
        }
        return '';
    }

    function zip() {
        $params = func_get_args();
        if (count($params) === 1){ // this case could be probably cleaner
            // single iterable passed
            $result = array();
            foreach ($params[0] as $item){
                $result[] = array($item);
            };
            return $result;
        };
        $result = call_user_func_array('array_map',array_merge(array(null),$params));
        $length = min(array_map('count', $params));
        return array_slice($result, 0, $length);
    }

    function categories(&$out) {

        global $key;
        global $title;

        if (!$key) {

        $url='http://api.mixcloud.com/categories/';
        $data=$this->api_call($url,600);
        if (is_array($data)) {
            $total = count($data['data']);
            $categories=array();
            for ($i = 0; $i < $total; $i++) {
                $data['data'][$i]['name_URL']=urlencode($data['data'][$i]['name']);
                $data['data'][$i]['key_URL']=urlencode($data['data'][$i]['key']);
                $categories[]=$data['data'][$i];
            }
            $out['CATEGORIES']=$categories;
        }

        } else {

            global $page;
            $page=(int)$page;

         $out['TITLE']=$title;
         $out['CATEGORY']=$key;

         $url='http://api.mixcloud.com/'.preg_replace('/^\//','',$key).'cloudcasts/';
         $items=$this->get_cloudcasts($url,array('limit'=>$this->limit,'offset'=>($page*$this->limit)));

         if (is_array($items)) {
             $out['ITEMS']=$items;
         }

        }

    }

    function search_items(&$out) {
        global $search;
        if ($search!='') {
            global $page;
            $page=(int)$page;
            $out['SEARCH']=htmlspecialchars($search);
            $url='http://api.mixcloud.com/search/';
            $type='cloudcast';
            $items=$this->get_cloudcasts($url,array('q'=>$search,'type'=>$type,'limit'=>$this->limit,'offset'=>($page*$this->limit)));
            $out['ITEMS']=$items;

        }
    }

    function get_cloudcasts($url,$params=0) {
        $url.='?';
        if (is_array($params)) {
            foreach($params as $k=>$v) {
                $url.='&'.$k.'='.urlencode($v);
            }
        }
        $data=$this->api_call($url,0);

        if (is_array($data)) {
            $items=$data['data'];

            $total = count($items);
            for ($i = 0; $i < $total; $i++) {
                $items[$i]['name_JS']=addcslashes($items[$i]['name'],'\'');
                if ($items[$i]['pictures']['medium']) {
                    $items[$i]['picture']=$items[$i]['pictures']['medium'];
                }
            }

            return $items;
        }

        return 0;
    }

    function api_call($url,$timeout=0) {

        $cached_filename=ROOT.'cached/mixcloud_'.md5($url).'.txt';

        $result=getURL($url,$timeout);

        if ($result!='') {
            SaveFile($cached_filename,$result);
        } elseif (file_exists($cached_filename)) {
            $result=LoadFile($cached_filename);
        }
        if ($result!='') {
            return json_decode($result,true);
        }
    }

/**
* mixcloud_favorites search
*
* @access public
*/
 function search_mixcloud_favorites(&$out) {
  require(DIR_MODULES.$this->name.'/mixcloud_favorites_search.inc.php');
 }
/**
* mixcloud_favorites edit/add
*
* @access public
*/
 function edit_mixcloud_favorites(&$out, $id) {
  require(DIR_MODULES.$this->name.'/mixcloud_favorites_edit.inc.php');
 }
/**
* mixcloud_favorites delete record
*
* @access public
*/
 function delete_mixcloud_favorites($id) {
  $rec=SQLSelectOne("SELECT * FROM mixcloud_favorites WHERE ID='$id'");
  // some action for related tables
  SQLExec("DELETE FROM mixcloud_favorites WHERE ID='".$rec['ID']."'");
 }
/**
* Install
*
* Module installation routine
*
* @access private
*/
 function install($data='') {
  parent::install();
 }
/**
* Uninstall
*
* Module uninstall routine
*
* @access public
*/
 function uninstall() {
  SQLExec('DROP TABLE IF EXISTS mixcloud_favorites');
  parent::uninstall();
 }
/**
* dbInstall
*
* Database installation routine
*
* @access private
*/
 function dbInstall() {
/*
mixcloud_favorites - 
*/
  $data = <<<EOD
 mixcloud_favorites: ID int(10) unsigned NOT NULL auto_increment
 mixcloud_favorites: TITLE varchar(100) NOT NULL DEFAULT ''
 mixcloud_favorites: STREAM varchar(255) NOT NULL DEFAULT ''
 mixcloud_favorites: ITEM_ID varchar(255) NOT NULL DEFAULT '' 
EOD;
  parent::dbInstall($data);
 }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgTm92IDA0LCAyMDE2IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
