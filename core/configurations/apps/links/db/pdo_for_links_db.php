<?php

require __DIR__."/db_host_for_links_db.php";
require __DIR__."/db_port_for_links_db.php";
require __DIR__."/password_for_links_db.php";
require __DIR__."/pdo_drv_name_for_links_db.php";
require __DIR__."/pdo_options_for_links_db.php";
require __DIR__."/user_name_for_links_db.php";
require __DIR__."/../../../dbms/mysql/databases/name_of_the_links_db_in_mysql.php";
require_once __DIR__."/../../../../functions/create_new_pdo.php";

$pdo_for_links_db = create_new_pdo(
    $pdo_drv_name_for_links_db,
    $db_host_for_links_db,
    $db_port_for_links_db,
    $name_of_the_links_db_in_mysql,
    $user_name_for_links_db, $password_for_links_db,
    $pdo_options_for_links_db
);

?>
