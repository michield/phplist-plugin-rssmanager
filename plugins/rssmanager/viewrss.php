<?php

if (isset($_GET['start'])) {
  $start = sprintf('%d',$_GET['start']);
} else {
  $start = 0;
}

if ($GLOBALS["require_login"] && !isSuperUser()) {
  $access = accessLevel("viewrss");
  $querytables = $tables["rssitem"] . ',' . $tables["list"];
  switch ($access) {
    case "owner" :
      $subselect = "where " . $tables["rssitem"] . ".list = " . $tables["list"] . ".id and " . $tables["list"] . ".owner = " . $_SESSION["logindetails"]["id"];
      if ($_GET["id"]) {
        $pagingurl = '&id=' . $_GET["id"];
        $subselect .= " and " . $tables["rssitem"] . ".list = " . $_GET["id"];
        print "RSS items for " . ListName($_GET["id"]) . "<br/>";
      }
      break;
    case "all" :
      $subselect = "";
      break;
    case "none" :
    default :
      $subselect = "where " . $tables["rssitem"] . ".list = " . $tables["list"] . ".id and " . $tables["list"] . ".owner = 0";
      break;
  }
} else {
  $querytables = $tables["rssitem"];
  $subselect = "";
  if (isset($_GET["id"]) && $_GET["id"]) {
    $pagingurl = '&id=' . $_GET["id"];
    $subselect = "where " . $tables["rssitem"] . ".list = " . $_GET["id"];
    print "RSS items for " . ListName($_GET["id"]) . "<br/>";
  }
}

$req = Sql_query("select count(*) FROM $querytables $subselect");
$total_req = Sql_Fetch_Row($req);
$total = $total_req[0];
if (isset ($start) && $start > 0) {
  $listing = "Listing item $start to " . ($start +MAX_MSG_PP);
  $limit = "limit $start," . MAX_MSG_PP;
} else {
  $listing = "Listing item 1 to " . MAX_MSG_PP;
  $limit = "limit 0," . MAX_MSG_PP;
  $start = 0;
}
$paging = '';
if ($total > MAX_MSG_PP) {
  $paging = simplePaging("viewrss",$start,$total,MAX_MSG_PP,s("RSS items"));
}
  
$ls = new WebblerListing(s('RSS items'));
$ls->usePanel($paging);

if ($total) {
  $result = Sql_query("SELECT * FROM $querytables $subselect order by added desc $limit");
  while ($rss = Sql_fetch_array($result)) {
    
    $item = '<a href="'.$rss['link'].'">'.$rss['title']. '</a>';
    $ls->addElement($item);
    $ls->addColumn($item,s('Source'),str_replace("&", "& ", $rss["source"]));
    $ls->addColumn($item,s('Date Added'),$rss['added']);

    
    #   $uniqueviews = Sql_Fetch_Row_Query("select count(userid) from {$tables["usermessage"]} where viewed is not null and messageid = ".$msg["id"]);
    //printf('<tr><td valign="top"><table>
          //<tr><td valign="top"><b>Title</b>:</td><td valign="top">%s</td></tr>
          //<tr><td valign="top"><b>Link</b>:</td><td valign="top"><a href="%s" target="_blank">%s</a></td></tr>
          //<tr><td valign="top"><b>Source</b>:</td><td valign="top">%s</td></tr>
          //<tr><td valign="top"><b>Date Added</b>:</td><td valign="top">%s</td></tr>
          //</table>
          //</td>', $rss["title"], $rss["link"], $rss["link"], str_replace("&", "& ", $rss["source"]), $rss["added"]);

    //$status = sprintf('<table border=1>
          //<tr><td>Processed</td><td>%d</td></tr>
          //<tr><td>Text</td><td>%d</td></tr>
          //<tr><td>HTML</td><td>%d</td></tr>
          //</table>', $rss["processed"], $rss["astext"], $rss["ashtml"]);
    //print '<td valign="top">' . $status . '</td>';
    //print '<td valign=top><table>';
    //$data_req = Sql_Query(sprintf('select * from %s where tag != "title" and tag != "link" and itemid = %d', $tables["rssitem_data"], $rss["id"]));
    //while ($data = Sql_Fetch_Array($data_req)) {
      //printf('<tr><td valign=top><b>%s</b></td></td></tr><tr><td valign=top>%s</td></tr>', $data["tag"], $data["data"]);
    //}
    //print '</table></td>';
    //print '</tr>';
  }
  print $ls->display();
  
}
?>

