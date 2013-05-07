<?php

$pl = $GLOBALS['plugins']['rssmanager'];

$testfeeds = array(
  'BBC World' => 'http://feeds.bbci.co.uk/news/world/rss.xml',
  'BBC Technology' => 'http://feeds.bbci.co.uk/news/technology/rss.xml',
  'phpList news' => 'http://www.phplist.com/rss.php?id=1',
);

foreach ($testfeeds as $name => $url) {
  print s('Adding').' '.$name.'<br/>';
  $exists = Sql_Fetch_Row_Query('select id from '.$tables['list']. ' where name = "'.$name.'"');
  if (empty($exists[0])) {
    
    Sql_Query('insert into '.$tables['list'].'(name,description,entered,rssfeed,modified,active,category)
      values("'.$name.'","'.$name.'",now(),"",now(),1, "RSS")');
    $listid = Sql_Insert_Id();
    $_POST["rssfeed"] = $url;
    $pl->processEditList($listid);
    
  }
}

