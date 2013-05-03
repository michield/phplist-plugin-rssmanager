<?php

$pl = $GLOBALS['plugins']['rssmanager'];

foreach ($pl->DBstruct as $table => $structure) {
  Sql_Verbose_Query(sprintf('delete from '.$pl->tables[$table]));
}
