<?php

class rssmanager extends phplistPlugin {
  ############################################################
  # Registration & Config
  
  public $name= 'Rss Manager';
  public $version= "3.0";
  public $authors= "phpList ltd";  
  public $coderoot= 'rssmanager/';
  public $enabled= 0;
  public $commandlinePluginPages= array (
    'getrss'
  );
  private $rssMessages = array();
  
  private $rssFrequencies = array(
    'daily' => array('caption' => 'Daily', 'interval' => '1 day', 'order' => 1),
    'weekly' => array('caption' => 'Weekly', 'interval' => '1 week', 'order' => 2),
    'monthly' => array('caption' => 'Monthly', 'interval' => '1 month', 'order' => 3),
    'hourly' => array('caption' => 'Hourly', 'interval' => '1 hour', 'order' => 4)
  );
  
  private $frequency_attribute = 0;
  
  public $topMenuLinks = array(
    'getrss' => array('category' => 'system'),
    'viewrss' => array('category' => 'campaigns'),
    'purgerss' => array('category' => 'system'),
    'delrss' => array('category' => 'develop'),
    'testrss' => array('category' => 'develop'),
    'init' => array('category' => 'develop'),
  );
  
  public $pageTitles = array(
    "getrss" => "Fetch RSS entries",
    "viewrss" => "View RSS entries",
    "purgerss" => "Remove outdated RSS entries",
    'delrss' => 'Delete RSS entries from DB',
    'testrss' => 'Add some test RSS feeds',
    'init' => 'Initialise DB',
  );

  var $DBstruct= array ( 
  'rssitem'    => array ( 
    'id'              => array ( 'integer not null primary key auto_increment', 'ID' ),
    'title'           => array ( 'varchar(100) not null', 'Title' ),
    'link'            => array ( 'varchar(100) not null', 'Link' ),
    'source'       => array ( 'varchar(255)', '' ),
    'list'             => array ( 'integer not null', '' ),
    'index_1'     => array ( 'titlelinkidx (title,link)', '' ),
    'index_2'     => array ( 'titleidx (title)', '' ),
    'index_3'     => array ( 'listidx (list)', '' ),
    'added'       => array ( 'datetime', '' ),
    'processed' => array ( 'mediumint unsigned default 0', 'Number Processed' ),
    'astext'        => array ( 'integer default 0', 'Sent as text' ),
    'ashtml'       => array ( 'integer default 0', 'Sent as HTML' ),
  ),
  'rssitem_data' => array ( 
    'itemid'            => array ( 'integer not null', 'rss item id' ),
    'tag'                 => array ( 'varchar(100) not null', '' ),
    'primary key'   => array ( '(itemid,tag)', '' ),
    'data'               => array ( 'text', '' ) 
  ),
  'rssitem_user'  => array ( 'itemid' => array ( 'integer not null', 'rss item id' ),
    'userid'            => array ( 'integer not null', 'user id' ),
    'entered'         => array ( 'timestamp', 'Entered' ),
    'primary key'   => array ( '(itemid,userid)', '' ) ),
    'user_rss'         => array ( 'userid' => array ( 'integer not null primary key', 'user id' ),
    'last'                => array ( 'datetime', 'Last time this user was sent something' ) 
  ),
  'listrss'            => array (# rss details for a RSS source of a list
    'listid'               => array ( 'integer not null', 'List ID' ),
    'name'                => array ( 'varchar(255) not null', 'Name of this entry' ),
    'lastmodified'          => array ( 'datetime not null', '' ),
    'data'                => array ( 'text', '' ),
    'primary key'          => array ( '(listid,name)', '' ),
    'index_1'          => array ( 'listididx (listid)', '' ),
    'index_2'          => array ( 'enteredidx (lastmodified)', '' ),
    ) 
  );
 
  public $settings = array(
    "rssmanager_threshold" => array (
      'value' => 4,
      'description' => 'Minimum amount of items to send in an RSS feed',
      'type' => "integer",
      'allowempty' => 0,
      'min' => 1,
      'max' => 50,
      'category'=> 'composition',
    ),
    "rssmanager_max" => array (
      'value' => 30,
      'description' => 'Maximum amount of items to send in an RSS feed',
      'type' => "integer",
      'allowempty' => 0,
      'min' => 1,
      'max' => 50,
      'category'=> 'composition',
    ),
    "rssmanager_texttemplate" => array (
      'value' => '
      [title]
      [description]
      URL: [link]
      ',
      'description' => 'Template for text item in RSS feeds',
      'type' => "textarea",
      'category'=> 'composition',
    ),
    "rssmanager_htmltemplate" => array (
      'value' => '<br/>
      <a href="[link]"><b>[title]</b></a><br/>
      <p class="information">[description]</p>
      <hr/>',
      'description' => 'Template for HTML item in RSS feeds',
      'type' => "textarea",
      'allowempty' => 1,
      'category'=> 'composition',
    ),
    "rssmanager_textseparatortemplate" => array (
      'value' => '
      **** [listname] ******
    
      ',
      'description' => 'Template for separator between feeds in RSS feeds (text)',
      'type' => "text",
      'allowempty' => 1,
      'category'=> 'composition',
    ),
    "rssmanager_htmlseparatortemplate" => array (
      'value' => '<br/>
      <h3>[listname]</h3>
       ',
      'description' => 'Template for separator between feeds in RSS feeds (HTML)',
      'type' => "text",
      'allowempty' => 1,
      'category'=> 'composition',
    ),
    'rssmanager_frequency_attribute' => array (
      'value' => 0,
      'description' => 'RSS Frequency attribute',
      'type' => "integer",
      'allowempty' => 1,
      'min' => 0,
      'max' => 999,
      'category'=> 'subscription',
    ),
  );

  //  var $configvars= array (
  //  # config var    array( type, name )
  //  );

  function __construct() {
    global $table_prefix;
    parent :: phplistplugin();
    $this->coderoot = dirname(__FILE__).'/rssmanager/';
    if (!defined('MANUALLY_PROCESS_RSS')) define('MANUALLY_PROCESS_RSS',1);
  }

  function activate() {
    global $table_prefix;
    parent :: activate();
    $GLOBALS['rssfrequencies'] = array (
        #  'hourly' => $strHourly, # to be added at some other point
      'daily' => s('Daily'),
      'weekly' => s('Weekly'),
      'monthly' => s('Monthly'));
    if ( phplistPlugin::isEnabled('rssmanager') && !function_exists("xml_parse") && WARN_ABOUT_PHP_SETTINGS)
        Warn(s('noxml'));
    $this->frequency_attribute = getConfig('rssmanager_frequency_attribute');

    if (empty($this->frequency_attribute)) {
      ## just make sure, there isn't one already
      $existing = Sql_Fetch_Row_Query('select id from '.$GLOBALS['tables']['attribute'].' where name = "rssfrequency"');
      SaveConfig('rssmanager_frequency_attribute',$att_id);
      $this->frequency_attribute = sprintf('%d',$existing[0]);
    }

    return true;
  }

  function initialise() {
    parent::initialise(); // will create table structure
    
    if (empty($this->frequency_attribute)) {
      $req = Sql_Query(sprintf('insert into %s (name,type,tablename) values("rssfrequency","radio","rssfrequency")',
        $GLOBALS['tables']['attribute']));
      $att_id = Sql_Insert_Id();
      SaveConfig('rssmanager_frequency_attribute',$att_id);
      $query = "create table if not exists ".$GLOBALS['table_prefix']."listattr_rssfrequency (id integer not null primary key auto_increment, name varchar(255) unique,listorder integer default 0)";
      Sql_Query($query);

      foreach ($this->rssFrequencies as $key => $value) {
        Sql_Query('insert ignore into '.$GLOBALS['table_prefix'].'listattr_rssfrequency (name,listorder) values("'. $key . '",'. $value['order'] . ')');
      }
    }
    return $this->name. ' '.s('initialised'); 
  }
  
  function upgrade($previous) {
    parent::upgrade($previous);
    
  }

  function sendFormats() {
   # return array('rss' => 'RSS');
  }

  function displayAbout() {
    return "&nbsp;&nbsp;&nbsp;&nbsp;using Onyx-RSS, by Ed Swindelles";  
  }

  ############################################################
  # Frontend

  function displaySubscriptionChoice($pageData, $userID= 0) {
    if (!$this->enabled)
      return;

    return ;
  }

  ############################################################
  # Messages

  function displayMessages($msg, &$status) {
    if (!$this->enabled)
      return null;
    if ( isset($msg['rsstemplate']) && $msg['status'] != 'sent' ){
      $status .= '<br/>'.$msg['rsstemplate'];
    }
    return null;
  }
  
  ############################################################
  # Message

  function sendMessageTab($messageid = 0, $data = array ()) {
    if (!$this->enabled)
      return null;

    $nippet = s('If you want to use this message as the template for sending RSS feeds
    select the frequency it should be used for and use [RSS] in your message to indicate where the list of items needs to go.');
    $nippet .= '<br />';
    $noRssSelected = isset($data['rsstemplate']) && $data['rsstemplate'] != 'none' ? '' : 'checked';
    $nippet .= sprintf('<input type=radio name="rsstemplate" value="none" %s>', $noRssSelected) . s('No RSS') . ' ';
    foreach ($this->rssFrequencies as $key => $value) {
      $nippet .= sprintf('<input type=radio name="rsstemplate" value="%s" %s>%s ', $key, $data['rsstemplate'] == $key ? 'checked' : '', $value['caption']);
    }
    return $nippet;
  }

  function sendMessageTabTitle($messageid = 0) {
    if (!$this->enabled)
      return null;

    return 'RSS';
  }

  function sendMessageTabSave($messageid = 0, $data = array ()) {
    if (!$this->enabled)
      return null;

    return '';
  }

  ############################################################
  # Processqueue

  function canSend ($messagedata, $userdata) {
    ## if it's not an RSS message, we're ok
    if (strpos($messagedata['message'],'[RSS]') === false) {
       return true;
    }
    
    if (empty($this->frequency_attribute)) {
      cl_output('No attribute');exit;
      return false;
    }
    
    $userFrequency = UserAttributeValue($userdata['id'],$this->frequency_attribute);

    cl_output('RSS Can send freq '.$userFrequency.' '.$messagedata["rsstemplate"]);
    
    $this->rssMessages[$messagedata['id']] = 1;
    
    if ($userFrequency == $messagedata["rsstemplate"]) {
      $rssitems = $this->rssUserHasContent($userdata['id'],$messagedata['id'],$userFrequency);
      $threshold = sprintf('%d',getConfig("rssmanager_threshold"));
      $cansend = sizeof($rssitems) && (sizeof($rssitems) >= $threshold);
    } else {
      $cansend = false;
    }
    if ($cansend) {
      cl_output('RSS 2 Cansend '.$threshold.' '.sizeof($rssitems));
    } else {
      cl_output('RSS Can NOT send '.$threshold.' '.sizeof($rssitems));
    }
    return $cansend;
  }
  
  function deleteSent() {
    Sql_Verbose_Query('delete from '.$this->tables['rssitem_user']);
    Sql_Verbose_Query('delete from '.$this->tables['user_rss']);
  }
  
  function parseOutgoingTextMessage($messageid, $content, $destination = '', $userdata = array()) {
  #  if (!$this->enabled) {
      return $content;
 #   }
      
    $messagedata = loadMessageData($messageid); 
    if (strpos($content,'[RSS]') === false) {
      cl_output('parseOutgoingTextMessage no placeholder');
      return $content;
    }
    $userFrequency = UserAttributeValue($userdata['id'],$this->frequency_attribute);
    $rssitems = $this->rssUserHasContent($userdata['id'], $messagedata['id'], $userFrequency);
  #  cl_output('parseOutgoingTextMessage items returned '.sizeof($rssitems).' '.$rssitems);

    $rssentries = array ('text' => '');
    $request = join(',', $rssitems);
    $texttemplate = getConfig('rssmanager_texttemplate');
    $textseparatortemplate = getConfig('rssmanager_textseparatortemplate');
    $req= Sql_Query("select * from ".$this->tables["rssitem"]. " where id in ($request) order by list,added");
    $curlist = '';
    while ($row= Sql_Fetch_array($req)) {
      if ($curlist != $row['list']) {
        $row['listname'] = ListName($row['list']);
        $curlist = $row['list'];
        $rssentries['text'] .= $this->parseRSSTemplate($textseparatortemplate, $row);
      }

      $data_req = Sql_Query("select * from ".$this->tables["rssitem_data"] . " where itemid = {$row["id"]}");
      while ($data = Sql_Fetch_Array($data_req))
        $row[$data['tag']]= $data['data'];

      $rssentries['text'] .= stripHTML($this->parseRSSTemplate($texttemplate, $row));
    }
    $content= str_ireplace('[RSS]', $rssentries['text'], $content);
    return $content;
  }

  function parseOutgoingHTMLMessage($messageid, $content, $destination = '', $userdata = array()) {
    if (!$this->enabled) {
      return $content;
    }
    if (strpos($content,'[RSS]') === false) {
      return $content;
    }
    $messagedata = loadMessageData($messageid); 
    $userFrequency = UserAttributeValue($userdata['id'],$this->frequency_attribute);
    
    $rssitems = $this->rssUserHasContent($userdata['id'], $messagedata['id'], $userFrequency);
  #  cl_output('parseOutgoingHTMLMessage items returned '.sizeof($rssitems).' '.$rssitems);
    if (empty($rssitems)) return $content;

    $rssentries = array ('html' => '');
    $request = join(',', $rssitems);
    $htmltemplate = getConfig('rssmanager_htmltemplate');
    $htmlseparatortemplate = getConfig('rssmanager_htmlseparatortemplate');
    
    if (empty($htmltemplate)) {
      $htmltemplate = '<br/>
      <a href="[link]"><b>[title]</b></a><br/>
      <p>[description]</p>
      <hr/>
      ';
    }
      
    $htmlseparatortemplate = '<br/>
      <h3>[listname]</h3>
      ';
    
#    cl_output('html template '.$htmltemplate.' '.$request);
    $req = Sql_Query("select * from ".$this->tables["rssitem"] . " where id in ($request) order by list, id DESC");
    $curlist = '';
    while ($row = Sql_Fetch_array($req)) {
      if ($curlist != $row['list']) {
        $row['listname']= ListName($row['list']);
        $curlist= $row['list'];
        $rssentries['html'] .= $this->parseRSSTemplate($htmlseparatortemplate, $row);
      }

      $data_req= Sql_Query("select * from ".$this->tables["rssitem_data"] .  " where itemid = {$row["id"]}");
      while ($data= Sql_Fetch_Array($data_req))
        $row[$data['tag']]= $data['data'];

      $rssentries['html'] .= $this->parseRSSTemplate($htmltemplate, $row);
    }
    $content = str_ireplace('[RSS]', $rssentries['html'], $content);
    return $content;
  }

  function processSuccesFailure($messageid, $sendformat, $userdata, $success = true) {
    if (!$this->enabled)
      return null;
    if (!$success)
      return true;

    if (!isset($this->rssMessages[$messageid]))
        return true;
    $userFrequency = UserAttributeValue($userdata['id'],$this->frequency_attribute);
    
    $rssitems = $this->rssUserHasContent($userdata['id'], $messageid, $userFrequency);
    if (!is_array($rssitems))
      return true;
    foreach ($rssitems as $rssitemid) {
      Sql_Query("update ".$this->tables["rssitem"]. " set $sendformat = $sendformat + 1 where id = $rssitemid");
      Sql_Query("update ".$this->tables["rssitem"]. " set processed = processed +1 where id = $rssitemid");
      Sql_Query("replace into ".$this->tables['rssitem_user']." (userid,itemid) values({$userdata['id']},$rssitemid)");
    }
    Sql_Query("replace into ".$this->tables['user_rss']." (userid,last) values({$userdata['id']},date_sub(current_timestamp,interval 15 minute))");
    return true;
  }

  ############################################################
  # User

  function displayUsers($user, $rowid, $list) {
    if (!$this->enabled)
      return null;

    $rss= Sql_query('SELECT count(*) FROM ' . $this->tables['rssitem_user'] . ' where userid = ' . $user['id']);
    $rsscount= Sql_fetch_row($rss);
    if ($rsscount[0]) {
      $list->addColumn($rowid, s('rss'),$rsscount[0]);
    }
    $userFrequency = UserAttributeValue($user['id'],$this->frequency_attribute);
    
    if (!empty($userFrequency)) {
       $list->addColumn($rowid, s('rss freq'),$this->rssFrequencies[$userFrequency]['caption']);
    }
    $lastsentdate= Sql_Fetch_Row_Query("select last from ".$this->tables['user_rss']." where userid = " . $user['id']);
    if ($lastsentdate[0]) {
      $list->addColumn($rowid, s('last sent'),$lastsentdate[0]);
    }
    return true;
  }

  function deleteUser($id) {
    if (!$this->enabled)
      return null;
    return Sql_Query(sprintf('delete from %s where userid = %d', $this->tables['user_rss'], $id));
  }
  
  ############################################################
  # List

  function displayLists($list) {
    if (!$this->enabled)
      return null;

    if (!empty($list['rssfeed'])) {
      $feed = $list['rssfeed'];
      # reformat string, so it wraps if it's very long
      $feed = str_replace("/","/ ",$feed);
      $feed = str_replace("&","& ",$feed);
      return sprintf('%s: <a href="%s" target="_blank">%s</a><br /> ', s('RSS source'),
        $list['rssfeed'], $feed) .
      PageLink2("viewrss&pi=rssmanager&id=".$list["id"],s('(View Items)')) . '<br />';
    }    
  }

  function displayEditList($list) {
    if (!$this->enabled)
      return null;

    $source = Sql_Fetch_Assoc_Query(sprintf('select data from %s where listid = %d and name="source"',
      $this->tables["listrss"], $list['id']));
    $rssSource = $source['data'];

    if (!empty ($rssSource)) {
      $validate = sprintf(
        '(<a href="http://feedvalidator.org/check?url=%s" target="_blank">%s</a>)', urlencode($rssSource),
        s('validate')
      );
      $viewitems= PageLink2('viewrss&&pi=rssmanager&id=' . $list['id'], s('View Items'));
    } else {
      $validate= '';
      $viewitems= '';
    }
    return sprintf(
        '<div><label for="rssfeed">%s</label> %s %s</div><div><input type=text name="rssfeed" value="%s" /></div>',
        s('RSS Source'),$validate, $viewitems, htmlspecialchars($rssSource)
    );
  }

    function processEditList($id) {

        $query = sprintf(
            'replace into %s set listid = %d, name="source",data = "%s",lastmodified = now()',
            $this->tables["listrss"], $id,sql_escape($_POST["rssfeed"])
        );
        return Sql_Query($query);
    }

  ############################################################
  # Subscribe page

  function displaySubscribePageEdit($subscribePageData) {
    if (!$this->enabled)
      return null;
    # purpose: return tablerows with subscribepage options for this list
    # Currently used in spageedit.php
    # 200710 Bas

    if (isset($subscribePageData['rss'])) {
      $rssOptions = explode(",",$subscribePageData["rss"]);
    } else { 
      $rssOptions = array();
    }

    $nippet= '<h1>' . s('RSS settings') . '</h1><table><tbody>';
    $nippet .= sprintf('<tr><td valign=top>' . s('Intro Text') .
    '</td><td><textarea name=rss_intro rows=3 cols=60>%s</textarea></td></tr>', htmlspecialchars(stripslashes($subscribePageData['rssintro'])));
    foreach ($this->rssFrequencies as $key => $value) {
      $nippet .= sprintf(
        '<tr><td colspan=2><input type=checkbox name="rss_freqOption[]" value="%s" %s> %s %s (%s <input type=radio name="rss_default" value="%s" %s>)<br></td></tr>',
        $key, in_array($key, $rssOptions) ? 'checked' : '', s('Offer option to receive'),
        s($value['caption']),
        s('default'),
        $key, $subscribePageData['rssdefault'] == $key ? 'checked' : ''
      );
    }
    $nippet .= '<tr><td colspan=2><hr></td></tr></tbody></table>';
    return $nippet;
  }

  function processSubscribePageEdit($subscribePageID) {
    if (!$this->enabled)
      return null;
    # purpose: process selected subscribepage options for this list
    # return false if failed
    # Currently used in spageedit.php
    # 200710 Bas
    global $tables;
    Sql_Query(sprintf('replace into %s (id,name,data) values(%d,"rssintro","%s")', $tables['subscribepage_data'], $subscribePageID, $_REQUEST['rss_intro']));
    Sql_Query(sprintf('replace into %s (id,name,data) values(%d,"rss","%s")', $tables['subscribepage_data'], $subscribePageID, join(',', $_REQUEST['rss_freqOption'])));
    Sql_Query(sprintf('replace into %s (id,name,data) values(%d,"rssdefault","%s")', $tables['subscribepage_data'], $subscribePageID, $_REQUEST['rss_default']));
    return true;
  }


  ######################################
  # library functions moved in here
  function parseRSSTemplate($template, $data) {
    foreach ($data as $key => $val) {
      if (!preg_match("#^\d+$#", $key)) {
        #      print "$key => $val<br/>";
        $template= str_ireplace('[' . $key . ']', trim($val), $template);
      }
    }
    $template = preg_replace("/[[A-Z\. ]+\]/i", '', $template);
    return $template;
  }

  function updateRSSStats($items, $type) {
  }

  function rssUserHasContent($userid, $messageid, $frequency) {
    global $tables;
#    cl_output('rssUserHasContent');
#    if (!in_array($messageid,$this->rssMessages)) return $content;
    # get selection string for mysql data_add function
    $interval = 'INTERVAL ' . $this->rssFrequencies[$frequency]['interval'];

    $cansend_req = Sql_Query(sprintf('select date_add(last,%s) < current_timestamp FROM %s 
       where userid = %d', $interval, $this->tables['user_rss'], $userid));
    $exists = Sql_Affected_Rows();
    $cansend = Sql_Fetch_Row($cansend_req);
    if (!$exists || $cansend[0]) {
      # we can send this user as far as the frequency is concerned
      # now check whether there is actually some content

      # check what lists to use. This is the intersection of the lists for the
      # user and the lists for the message
      $lists = array ();
      $listsreq = Sql_Query(sprintf('
        select listuser.listid from %s listuser, %s listmessage
        where listuser.listid = listmessage.listid and listuser.userid = %d and listmessage.messageid = %d',
        $tables['listuser'], $tables['listmessage'], $userid, $messageid));
      while ($row = Sql_Fetch_Row($listsreq)) {
        array_push($lists, $row[0]);
      }
      if (!sizeof($lists)) {
        cl_output('No lists');
        return 0;
      }
      $liststosend = join(',', $lists);

      # request the rss items that match these lists and that have not been sent to this user
      $itemstosend = array ();
      $max = sprintf('%d', getConfig('rssmanager_max'));
      if (!$max) {
        $max = 30;
      }
      $itemreq = Sql_Query("
        select rssitem.* from ".$this->tables["rssitem"]. " as rssitem
        where rssitem.list in ($liststosend) ORDER BY id DESC, list, title LIMIT $max");
      while ($item = Sql_Fetch_Array($itemreq)) {
        Sql_Query("SELECT * FROM ".$this->tables["rssitem_user"]. " WHERE itemid = {$item["id"]} AND userid = $userid");
        if (!Sql_Affected_Rows()) {
          array_push($itemstosend, $item['id']);
        }
      }
      $items = join(',',$itemstosend);
      #  return $itemstosend;
      
    
      #  print "<br/>Items to send for user $userid: ".sizeof($itemstosend);
      # if it is less than the threshold return nothing
      $threshold = sprintf('%d',getConfig("rssmanager_threshold"));
      if (sizeof($itemstosend) >= $threshold)
        return $itemstosend;
      else
        return array ();
    }
    return array ();
  } #rssUserHasContent

}
