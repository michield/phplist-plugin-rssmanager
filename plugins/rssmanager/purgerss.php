<?php

if (isset($_POST['daysago'])) {
  $daysago = sprintf('%d',$_POST['daysago']);
} else {
  $daysago = 0;
}

if (!$_SESSION['logindetails']['superuser']) {
  print '<p>'.s('Sorry, only super users can purge RSS items from the database').'</p>';
  return;
}
$pl = $GLOBALS['plugins']['rssmanager'];

$count = 0;
if ($daysago) {
  $req = Sql_Query(sprintf('select id from %s where date_add(added,interval %d day) < current_timestamp',$pl->tables['rssitem'],$daysago));
  while ($row = Sql_Fetch_Row($req)) {
    Sql_Query(sprintf('delete from %s where itemid = %d',$pl->tables['rssitem_data'],$row[0]));
    Sql_Query(sprintf('delete from %s where itemid = %d',$pl->tables['rssitem_user'],$row[0]));
    Sql_Query(sprintf('delete from %s where id = %d',$pl->tables['rssitem'],$row[0]));
    $count++;
  }
  printf ('<p>'.$GLOBALS['I18N']->get('%d RSS items purged').'</p>',$count);
}

print '<p>'.$GLOBALS['I18N']->get('Purge RSS items from database').'</p>';
print '<p>'.$GLOBALS['I18N']->get('Enter the number of days to go back purging entries').'</p>';
print '<p>'.$GLOBALS['I18N']->get('All entries that are older than the number of days you enter will be purged.').'</p>';
print '<form method="post" action="">' ;
print '  <input type=text name="daysago" value="30" size=7>';
print '  <input type=submit name="submit" value="Purge">';
print '</form>';

?>
