<?php
class rssmanager extends phplistPlugin {
  ############################################################
  # Registration & Config
  
  var $name= 'Rss Manager';
  var $version= "2.0";
  var $authors= "phpList ltd";  
  var $coderoot= 'rssmanager/';
  var $enabled= 0;
  var $commandlinePluginPages= array (
    'getrss'
  );
  private $rssMessages = array();
  
  public $topMenuLinks = array(
    'getrss' => array('category' => 'system'),
    'viewrss' => array('category' => 'campaigns'),
    'purgerss' => array('category' => 'system'),
  );
  
  public $pageTitles = array(
    "getrss" => "Fetch RSS entries",
    "viewrss" => "View RSS entries",
    "purgerss" => "Remove outdated RSS entries",
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
    'data'               => array ( 'text', '' ) ),
    'rssitem_user'  => array ( 'itemid' => array ( 'integer not null', 'rss item id' ),
    'userid'            => array ( 'integer not null', 'user id' ),
    'entered'         => array ( 'timestamp', 'Entered' ),
    'primary key'   => array ( '(itemid,userid)', '' ) ),
    'user_rss'         => array ( 'userid' => array ( 'integer not null primary key', 'user id' ),
    'last'                => array ( 'datetime', 'Last time this user was sent something' ) 
  ),
  'listrss'            => array (# rss details for a RSS source of a list
    'listid'               => array ( 'integer not null', 'List ID' ),
    'type'                => array ( 'varchar(255) not null', 'Type of this entry' ),
    'entered'          => array ( 'datetime not null', '' ),
    'info'                => array ( 'text', '' ),
    'index_1'          => array ( 'listididx (listid)', '' ),
    'index_2'          => array ( 'enteredidx (entered)', '' ),
    ) 
);

  //  var $configvars= array (
  //  # config var    array( type, name )
  //  );

  function rssmanager() {
    parent :: phplistplugin();
    $this->coderoot = dirname(__FILE__).'/rssmanager/';
  }

  function activate() {
    parent :: activate();

    $GLOBALS['rssfrequencies']= array (
        #  'hourly' => $strHourly, # to be added at some other point
      'daily' => $GLOBALS['I18N']->get('Daily'),
      'weekly' => $GLOBALS['I18N']->get('Weekly'),
      'monthly' => $GLOBALS['I18N']->get('Monthly'));
      if ( phplistPlugin::isEnabled('rssmanager')  && !function_exists("xml_parse") && WARN_ABOUT_PHP_SETTINGS)
        Warn($GLOBALS['I18N']->get('noxml'));
    return true;
  }

  function initialise() {
    foreach ($this->DBstruct as $table => $structure) {
      print $table . '<br/>';
      Sql_Create_Table($table, $structure);
    }
  }

  function sendFormats() {
    return array('rss' => 'RSS');
  }

  function displayAbout() {
    return "&nbsp;&nbsp;&nbsp;&nbsp;using Onyx-RSS, by Ed Swindelles";  
  }
  ############################################################
  # Main interface hooks

  function adminmenu() {
    if (!$this->enabled)
      return null;
    if (Sql_Table_exists($GLOBALS['tables']['rssitem'])) {
      $menuitems= array ();
      if (MANUALLY_PROCESS_RSS) {
        $menuitems += array (
          'getrss' => $GLOBALS['I18N']->get('Get RSS feeds'
        ));
      }
      $menuitems += array (
        'viewrss' => $GLOBALS['I18N']->get('View RSS items'),
      'purgerss' => $GLOBALS['I18N']->get('Purge RSS items'));
    } else {
      $menuitems= array (
        'initialise' => $GLOBALS['I18N']->get('Initialise rssmanager'
      ));
    }
    return $menuitems;
  }

  ############################################################
  # Frontend

  function displaySubscriptionChoice($pageData, $userID= 0) {
    if (!$this->enabled)
      return;

    global $rssfrequencies, $tables;
    if (!empty($userID)) {
      $current= Sql_Fetch_Row_Query("select rssfrequency from {$GLOBALS["tables"]["user"]} where id = $userID");
      $default= $current[0];
    } else {
      $default= '';
    }
    if (!$default || !in_array($default, array_keys($rssfrequencies))) {
      $default= $pageData['rssdefault'];
    }

    $nippet= "\n<table>";
    $nippet .= '<tr><td>' . $pageData['rssintro'] . '</td></tr>';
    $nippet .= '<tr><td>';
    $options= explode(',', $pageData['rss']);
    if (!in_array($pageData['rssdefault'], $options)) {
      array_push($options, $pageData['rssdefault']);
    }
    if (sizeof($options) == 1) {
      return sprintf('<input type="hidden" name="rssfrequency" value="%s">', $options[0]);
    }

    foreach ($options as $freq) {
      if ($freq) {
        $nippet .= sprintf('<input type=radio name="rssfrequency" value="%s" %s>&nbsp;%s&nbsp;', $freq, $freq == $default ? 'checked' : '', $rssfrequencies[$freq]);
      }
    }
    $nippet .= '</td></tr></table>';

    return $nippet;
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

  function sendMessageTab($messageid= 0, $data= array ()) {
    if (!$this->enabled)
      return null;

    global $rssfrequencies;

    $nippet= $GLOBALS['I18N']->get('rssintro');
    $nippet .= '<br />';
    $nippet .= '<input type=radio name="rsstemplate" value="none">' . $GLOBALS['I18N']->get('No RSS') . ' ';
    foreach ($rssfrequencies as $key => $val) {
      $nippet .= sprintf('<input type=radio name="rsstemplate" value="%s" %s>%s ', $key, $data['rsstemplate'] == $key ? 'checked' : '', $val);
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

    if (!isset($_POST['rsstemplate'])) return ;
    # mark previous RSS templates with this frequency and owner as sent
    # this should actually be much more complex than this:
    # templates should be allowed by list and therefore a subset of lists, but
    # for now we leave it like this
    # the trouble is that this may duplicate RSS messages to users, because
    # it can cause multiple template for lists. The user_rss should handle that, but it is
    # not guaranteed which message will be used.
    #    Sql_Query(sprintf('update %s set status = "sent" where rsstemplate = "%s" and owner = %d',
    #      $tables['message'],$_POST['rsstemplate'],$_SESSION['logindetails']['id']));

    # with RSS message we enforce repeat
    switch ($_POST['rsstemplate']) {
      case 'weekly' :
        $_POST['repeatinterval']= 10080;
        break;
      case 'monthly' :
        $_POST['repeatinterval']= 40320;
        break;
      case 'daily' :
      default :
        $_POST['repeatinterval']= 1440;
        break;
    }
    $_POST['repeatuntil']= date('Y-m-d H:i:00', mktime(0, 0, 0, date('m'),
      date('d'),date('Y') + 1));

    return 'RSS message, repeat set';
  }

  ############################################################
  # Processqueue

  function canSend ($messagedata, $userdata) {
    if ($messagedata['sendformat'] != 'rss') return true;
    ## if it's not an RSS message, we're ok
    if (strpos($messagedata['message'],'[RSS]') === false) {
       return true;
    }
    cl_output('RSS Cansend '.$userdata['rssfrequency'].' '.$messagedata["rsstemplate"]);
    
    $this->rssMessages[] = $messagedata['id'];
    
    if ($userdata['rssfrequency'] == $messagedata["rsstemplate"]) {
      $rssitems = $this->rssUserHasContent($userdata['id'],$messagedata['id'],$userdata['rssfrequency']);
      $threshold = sprintf('%d',getConfig("rssthreshold"));
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
  
  function parseOutgoingTextMessage($messageid, $content, $destination = '', $userdata = array()) {
  #  if (!$this->enabled) {
      return $content;
 #   }
      
    $messagedata = loadMessageData($messageid); 
    if ($messagedata['sendformat'] != 'rss') return 'NOT RSS'.$content;
    if (strpos($content,'[RSS]') === false) {
      cl_output('parseOutgoingTextMessage no placeholder');
      return 'NO PLACEHOLDER'.$content;
    }
#    if (!in_array($messageid,$this->rssMessages)) return $content;
    $rssitems = $this->rssUserHasContent($userdata['id'], $messagedata['id'], $userdata['rssfrequency']);
    cl_output('parseOutgoingTextMessage items returned '.sizeof($rssitems).' '.$rssitems);

    $rssentries= array ();
    $request = join(',', $rssitems);
    $texttemplate = getConfig('rsstexttemplate');
    $textseparatortemplate = getConfig('rsstextseparatortemplate');
    $req= Sql_Query("select * from {$GLOBALS["tables"]["rssitem"]} where id in ($request) order by list,added");
    $curlist = '';
    while ($row= Sql_Fetch_array($req)) {
      if ($curlist != $row['list']) {
        $row['listname'] = ListName($row['list']);
        $curlist = $row['list'];
        $rssentries['text'] .= $this->parseRSSTemplate($textseparatortemplate, $row);
      }

      $data_req = Sql_Query("select * from {$GLOBALS["tables"]["rssitem_data"]} where itemid = {$row["id"]}");
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
    if ($messagedata['sendformat'] != 'rss') return $content;
  #  if (!in_array($messageid,$this->rssMessages)) return $content;
    
    $rssitems = $this->rssUserHasContent($userdata['id'], $messagedata['id'], $userdata['rssfrequency']);
    cl_output('parseOutgoingHTMLMessage items returned '.sizeof($rssitems).' '.$rssitems);

    $rssentries = array ();
    $request = join(',', $rssitems);
    $htmltemplate = getConfig('rsshtmltemplate');
    $htmlseparatortemplate = getConfig('rsshtmlseparatortemplate');
    
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
    
    cl_output('html template '.$htmltemplate.' '.$request);
    $req = Sql_Query("select * from {$GLOBALS["tables"]["rssitem"]} where id in ($request) order by list,added");
    $curlist = '';
    while ($row = Sql_Fetch_array($req)) {
      if ($curlist != $row['list']) {
        $row['listname']= ListName($row['list']);
        $curlist= $row['list'];
        $rssentries['html'] .= $this->parseRSSTemplate($htmlseparatortemplate, $row);
      }

      $data_req= Sql_Query("select * from {$GLOBALS["tables"]["rssitem_data"]} where itemid = {$row["id"]}");
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
    if (!in_array($messageid,$this->rssMessages)) return true;
    $rssitems = $this->rssUserHasContent($userdata['id'], $messageid, $userdata['rssfrequency']);
    if (!is_array($rssitems))
      return true;
    global $tables;
    foreach ($rssitems as $rssitemid) {
      Sql_Query("update {$tables["rssitem"]} set $sendformat = $sendformat + 1 where id = $rssitemid");
      Sql_Query("update {$tables['rssitem']} set processed = processed +1 where id = $rssitemid");
      Sql_Query("replace into {$tables['rssitem_user']} (userid,itemid) values({$userdata['id']},$rssitemid)");
    }
    Sql_Query("replace into {$tables["user_rss"]} (userid,last) values({$userdata['id']},date_sub(current_timestamp,interval 15 minute))");
    return true;
  }

  ############################################################
  # User

  function displayUsers($user, $rowid, $list) {
    if (!$this->enabled)
      return null;

    $rss= Sql_query('SELECT count(*) FROM ' . $GLOBALS['tables']['rssitem_user'] . ' where userid = ' . $user['id']);
    $rsscount= Sql_fetch_row($rss);
    if ($rsscount[0]) {
      $list->addColumn($rowid, $GLOBALS['I18N']->get('rss'),$rsscount[0]);
    }
    if (!empty($user['rssfrequency'])) {
       $list->addColumn($rowid, $GLOBALS['I18N']->get('rss freq'),$user['rssfrequency']);
    }
    $lastsentdate= Sql_Fetch_Row_Query("select last from {$GLOBALS['tables']['user_rss']} where userid = " . $user['id']);
    if ($lastsentdate[0]) {
      $list->addColumn($rowid, $GLOBALS['I18N']->get('last sent'),$lastsentdate[0]);
    }
    return true;
  }

  function deleteUser($id) {
    if (!$this->enabled)
      return null;
    global $tables;
    return Sql_Query(sprintf('delete from %s where userid = %d', $tables['user_rss'], $id));
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
      return sprintf('%s: <a href="%s" target="_blank">%s</a><br /> ', $GLOBALS['I18N']->get('RSS source'),
$list['rssfeed'], $feed) .
      PageLink2("viewrss&pi=rssmanager&id=".$list["id"],$GLOBALS['I18N']->get('(View Items)')) . '<br />';
    }    
  }

  function displayEditList($list) {
    if (!$this->enabled)
      return null;

    if (!empty ($list['rssfeed'])) {
      $validate= sprintf('(<a href="http://feedvalidator.org/check?url=%s" target="_blank">%s</a>)', urlencode($list['rssfeed']),
$GLOBALS['I18N']->get('validate'));
      $viewitems= PageLink2('viewrss&&pi=rssmanager&id=' . $list['id'], $GLOBALS['I18N']->get('View Items'));
    } else {
      $list['rssfeed'] = '';
      $validate= '';
      $viewitems= '';
    }
    return sprintf('<div>%s %s %s</div><div><input type=text name="rssfeed" value="%s" size=50></div>', $GLOBALS['I18N']->get('RSS Source'),$validate, $viewitems, htmlspecialchars($list['rssfeed']));
  }

  function processEditList($id) {
    if (!$this->enabled)
      return null;

    global $tables;
    $query= sprintf('update %s set rssfeed = "%s" where id=%d', $tables["list"], $_POST["rssfeed"], $id);
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
    global $rssfrequencies; //, $rss;

    if (isset($subscribePageData['rss'])) {
      $rssOptions = explode(",",$subscribePageData["rss"]);
    } else { 
      $rssOptions = array();
    }

    $nippet= '<tr><td colspan=2><h1>' . $GLOBALS['I18N']->get('RSS settings') . '</h1></td></tr>';
    $nippet .= sprintf('<tr><td valign=top>' . $GLOBALS['I18N']->get('Intro Text') .
    '</td><td><textarea name=rss_intro rows=3 cols=60>%s</textarea></td></tr>', htmlspecialchars(stripslashes($subscribePageData['rssintro'])));
    foreach ($rssfrequencies as $key => $val) {
      $nippet .= sprintf('<tr><td colspan=2><input type=checkbox name="rss_freqOption[]" value="%s" %s> %s %s (%s <input type=radio name="rss_default" value="%s" %s>)</td></tr>', $key, in_array($key, $rssOptions) ? 'checked' : '', $GLOBALS['I18N']->get('Offer option to receive'),
$GLOBALS['I18N']->get($val),
$GLOBALS['I18N']->get('default'),
$key, $subscribePageData['rssdefault'] == $key ? 'checked' : '');
    }
    $nippet .= '<tr><td colspan=2><hr></td></tr>';
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
        $template= preg_replace('#\[' . preg_quote($key) . '\]#i', $val, $template);
      }
    }
    $template= preg_replace("/[[A-Z\. ]+\]/i", '', $template);
    return $template;
  }

  function updateRSSStats($items, $type) {
  }

  function rssUserHasContent($userid, $messageid, $frequency) {
    global $tables;
    cl_output('rssUserHasContent');
#    if (!in_array($messageid,$this->rssMessages)) return $content;
    # get selection string for mysql data_add function
    switch ($frequency) {
      case 'weekly' : 
        $interval = 'interval 7 day';
        break;
      case 'monthly' :
        $interval = 'interval 1 month';
        break;
      case 'daily' :
      default :
        $interval = 'interval 1 day';
        break;
    }

    $cansend_req = Sql_Query(sprintf('select date_add(last,%s) < current_timestamp FROM %s 
       where userid = %d', $interval, $tables['user_rss'], $userid));
    $exists = Sql_Affected_Rows();
    $cansend = Sql_Fetch_Row($cansend_req);
    if (!$exists || $cansend[0]) {
      # we can send this user as far as the frequency is concerned
      # now check whether there is actually some content

      # check what lists to use. This is the intersection of the lists for the
      # user and the lists for the message
      $lists = array ();
      $listsreq = Sql_Verbose_Query(sprintf('
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
      $max = sprintf('%d', getConfig('rssmax'));
      if (!$max) {
        $max = 30;
      }
      $itemreq = Sql_Query("
        select rssitem.* from {$tables["rssitem"]} as rssitem
        where rssitem.list in ($liststosend) ORDER BY added desc, list, title LIMIT $max");
      while ($item = Sql_Fetch_Array($itemreq)) {
        Sql_Query("SELECT * FROM {$tables["rssitem_user"]} WHERE itemid = {$item["id"]} AND userid = $userid");
        if (!Sql_Affected_Rows()) {
          array_push($itemstosend, $item['id']);
        }
      }
      $items = join(',',$itemstosend);
      cl_output('Num items: '.sizeof($itemstosend).' '.$items);
        return $itemstosend;
      
    
      #  print "<br/>Items to send for user $userid: ".sizeof($itemstosend);
      # if it is less than the threshold return nothing
      $threshold = sprintf('%d',getConfig("rssthreshold"));
      if (1 || sizeof($itemstosend) >= $threshold)
        return $itemstosend;
      else
        return array ();
    }
    return array ();
  } #rssUserHasContent

}
