<?php

function createTablesInDB($pdo) {
    $commands = ['CREATE TABLE IF NOT EXISTS `m1095_links`.`links` (
        `link_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `slug` VARCHAR(255) NOT NULL,
        `target_url` VARCHAR(2048) NOT NULL,
        `creator_ip` VARCHAR(15) NOT NULL,
        `created_at` TIMESTAMP NOT NULL,
        PRIMARY KEY (`link_id`),
        UNIQUE INDEX `links_id_UNIQUE` (`link_id` ASC),
        UNIQUE INDEX `slug_UNIQUE` (`slug` ASC))
      ENGINE = InnoDB;',
    'CREATE TABLE IF NOT EXISTS `m1095_links`.`visits` (
        `visit_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `visited_at` TIMESTAMP NOT NULL,
        `visitor_ip` VARCHAR(15) NOT NULL,
        `link_id` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`visit_id`),
        UNIQUE INDEX `visit_id_UNIQUE` (`visit_id` ASC),
        INDEX `fk_visits_links_idx` (`link_id` ASC),
        CONSTRAINT `fk_visits_links`
          FOREIGN KEY (`link_id`)
          REFERENCES `m1095_links`.`links` (`link_id`)
          ON DELETE CASCADE
          ON UPDATE CASCADE)
      ENGINE = InnoDB;'];

    // execute the sql commands to create new tables
    foreach ($commands as $command)
        $pdo->exec($command);
}

function createRedirectInDB($pdo, $obj) {
    $sql = 'INSERT INTO links(slug,target_url,creator_ip,created_at) '
            . 'VALUES(:slug,:target_url,:creator_ip,now())';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($obj);

    return $pdo->lastInsertId();
}

header("Access-Control-Allow-Origin: *");

require __DIR__."/core/configurations/apps/links/admin_password.php";
if ((in_array($_SERVER['REQUEST_METHOD'], array("POST", "PATCH", "DELETE")) || ($_SERVER['REQUEST_METHOD'] == "GET" && !isset($_GET["s"]) && !isset($_GET["i"]) && isset($_GET["stats"])) && (!isset($_SERVER['HTTP_API_KEY_PASSWORD']) || $_SERVER['HTTP_API_KEY_PASSWORD'] != $admin_password)))
    die(json_encode("Valid password required in API-Key-Password header!"));

try {
	require __DIR__."/core/configurations/apps/links/db/pdo_for_links_db.php";
	$pdo_for_links_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //$date_time_now = new DateTime();
    //$timestamp_now = $date_time_now->getTimestamp();
    $remote_addr = $_SERVER['REMOTE_ADDR'];
    switch($_SERVER['REQUEST_METHOD']) {
        case "POST": // The request is using the POST method
            $data_in = file_get_contents("php://input");
            $request = json_decode($data_in, true);
            if(isset($request["slug"]) && isset($request["target"])){
                $new_redirect = array(
                    //"created_at" => $timestamp_now,
                    "creator_ip" => $remote_addr,
                    "slug" => $request["slug"],
                    "target_url" => $request["target"]
                );
                echo createRedirectInDB($pdo_for_links_db, $new_redirect);
            } else
                echo(json_encode(array("error" => "No slug or target to save in db")));
            break;
        case "GET":
            if (isset($_GET["s"]) || isset($_GET["i"])) {
                $slug = $_GET[(isset($_GET["s"]) ? "s": "i")];
                $sql = "SELECT link_id, target_url FROM links WHERE slug = ?;";
                $stmt = $pdo_for_links_db->prepare($sql);
                $stmt->execute(array($slug));
                $results = $stmt->fetchAll(PDO::FETCH_CLASS);
                if(count($results) == 0)
                    echo("There no redirect with slug name '$slug'!");
                else {
                    $result = $results[0];
                    $sql = 'INSERT INTO visits(visited_at,visitor_ip,link_id) '
                    . 'VALUES(now(),:visitor_ip,:link_id)';
                    $obj = array(
                        //"visited_at" => $timestamp_now,
                        "visitor_ip" => $remote_addr,
                        "link_id" => $result->link_id
                    );
                    $stmt = $pdo_for_links_db->prepare($sql);
                    $stmt->execute($obj);
                    $target_url = $result->target_url;
                    if(isset($_GET["s"]))
                        header("Location: $target_url", true, 302);
                    else
                        echo($target_url);
                }
            } else if (isset($_GET["stats"])) {
                $sql = "SELECT s.link_id, slug, target_url, COUNT(visit_id) AS counter, MAX(visited_at) AS last_visit_at FROM links s LEFT JOIN visits v ON s.link_id = v.link_id GROUP BY s.link_id, slug, target_url, creator_ip, created_at;";
                $stmt = $pdo_for_links_db->query($sql);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo(json_encode($data));
            }
            break;
        case "PATCH":
            if (isset($_GET["s"])) {
                $data_in = file_get_contents("php://input");
                $request = json_decode($data_in, true);
                if (isset($request["link"])) {
                    $sql = 'UPDATE links SET target_url = :link '
                        . "WHERE slug = :slug";
                    $stmt = $pdo_for_links_db->prepare($sql);
                    $stmt->execute(["slug" => $_GET["s"], "link" => $request["link"]]);
                    echo $stmt->rowCount();
                } else echo(json_encode(array("error" => "No link to update in db")));
            } else echo(json_encode(array("error" => "No slug to update in db")));
            break;
        case "DELETE":
            if (isset($_GET["s"])) {
                $sql = 'DELETE FROM links '
                    . "WHERE slug = ?";
                $stmt = $pdo_for_links_db->prepare($sql);
                $stmt->execute([$_GET["s"]]);
                echo $stmt->rowCount();
            } else echo(json_encode(array("error" => "No slug or target to delete from db")));
            break;
        default:
            echo(
                json_encode(
                    array(
                        "errors" => array(
                            "Unsupported method"
                        )
                    )
                )
            );
    }
	$pdo_for_links_db = null; // Close db connection
}
catch(PDOException $e) {
    createTablesInDB($pdo_for_links_db);
    echo(json_encode(array("errors" => array('PDO driver error: '.mb_convert_encoding($e->getMessage().' (#'.$e->getCode().') in line: '.$e->getLine(), 'UTF-8', 'ISO-8859-2'))))); // Print PDOException message
}
?>
