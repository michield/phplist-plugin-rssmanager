<?php

$testfeeds = array(
  'BBC World' => 'http://feeds.bbci.co.uk/news/world/rss.xml',
  'BBC Technology' => 'http://feeds.bbci.co.uk/news/technology/rss.xml',
  'phpList news' => 'http://www.phplist.com/rss.php?id=1',
);

foreach ($testfeeds as $name => $url) {
  $exists = Sql_Fetch_Row_Query('select id from '.$tables['list']. ' where name = "'.$name.'"');
  if (empty($exists[0])) {
    Sql_Query('insert into '.$tables['list']. '(name,description,entered,rssfeed,modified,active,category)
      values("'.$name.'","'.$name.'",now(),"'.$url.'",now(),1, "RSS")');
  }
}
