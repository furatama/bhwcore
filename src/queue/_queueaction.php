<?php

date_default_timezone_set('Asia/Singapore');
echo "RESTARTING WORKER at " . date("Y-m-d H:i:s") . PHP_EOL;

$dbhost = $DATABASE_ENV['hostname'];
$dbname = $DATABASE_ENV['database'];
$dbuser = $DATABASE_ENV['username'];
$dbpass = $DATABASE_ENV['password'];

$dbconn = pg_connect("host=$dbhost dbname=$dbname user=$dbuser password=$dbpass")
or die('Could not connect: ' . pg_last_error());

$result = pg_query("CREATE table if not exists utility.materialized_view_refresh_queue (
	mv_name varchar NULL,
	mv_query text NULL,
	created_at timestamptz NULL DEFAULT CURRENT_TIMESTAMP,
	refreshes_in int4 NULL DEFAULT 60,
	finished_at timestamptz NULL
);
CREATE INDEX if not exists materialized_view_refresh_queue_created_at_idx ON utility.materialized_view_refresh_queue USING btree (created_at DESC);
CREATE INDEX materialized_view_refresh_queue_finished_at_idx ON utility.materialized_view_refresh_queue (finished_at,mv_name);
");

if ($result) {
	echo "Initiate refresh queue table". PHP_EOL;
}

while (true) {
	refresh($dbconn);
	sleep(1);
}

pg_close($dbconn);

function refresh($dbconn) {
	date_default_timezone_set('Asia/Singapore');
	$tbname = "utility.materialized_view_refresh_queue";

	$query = "SELECT * FROM $tbname WHERE 
finished_at is NULL and age(clock_timestamp(), created_at) > (refreshes_in || ' seconds')::interval 
ORDER BY created_at DESC 
LIMIT 1";
	$result = pg_query($dbconn, $query);

	if (!$result) {
		echo "SELECT FAILED " . var_dump($result) . " AT " . date('Y-m-d H:i:s');
		echo PHP_EOL;
		sleep(60);
		echo "..wait done" . PHP_EOL;
		shell_exec("sh ./exec.sh");
		return false;
	}

	if (pg_num_rows($result) <= 0) {
		return;
	}
	
	$row = pg_fetch_array($result);
	if (isset($REFRESH_BY_SWAPPING) && $REFRESH_BY_SWAPPING) {
		$result = refresh_by_swapping($dbconn, $row);
	} else {
		$result = refresh_by_refreshing($dbconn, $row);
	}

	if(is_bool($result) && $result == false) {
		return false;
	}

	echo "REFRESH DONE ON " . $row['mv_name'] . " AT " . date('Y-m-d H:i:s');
	echo PHP_EOL;
	$result = pg_query($dbconn, "UPDATE $tbname SET finished_at=CURRENT_TIMESTAMP WHERE 
finished_at IS NULL AND mv_name='". $row['mv_name'] ."'");
	if (!$result) {
		echo "but update status failed :(";
		echo PHP_EOL;
	}

	pg_free_result($result);
}

function refresh_by_swapping($dbconn, $row) {
	$mv_old = $row['mv_name'];
	$mv_new = $mv_old . "_new";
	$query = $row['mv_query'];
	$result = pg_query($dbconn, "CREATE MATERIALIZED VIEW ". $mv_new ." AS " . $query);
	if (!$result) {
		echo "REFRESH -- CREATE NEW FAILED" . $mv_new;
		echo PHP_EOL;
		echo var_dump($result);
		echo PHP_EOL;
		echo PHP_EOL;
		return false;
	}
	$result = pg_query($dbconn, "DROP MATERIALIZED VIEW ". $mv_old);
	if (!$result) {
		echo "REFRESH -- DROP OLD FAILED" . $mv_new;
		echo PHP_EOL;
		echo var_dump($result);
		echo PHP_EOL;
		echo PHP_EOL;
		return false;
	}
	$result = pg_query($dbconn, "ALTER MATERIALIZED VIEW ". $mv_new ." RENAME TO " . $mv_old);
	if (!$result) {
		echo "REFRESH -- ALTER NEW TO OLD FAILED" . $mv_new;
		echo PHP_EOL;
		echo var_dump($result);
		echo PHP_EOL;
		echo PHP_EOL;
		return false;
	}
}

function refresh_by_refreshing($dbconn, $row) {
	$result = pg_query($dbconn, "REFRESH MATERIALIZED VIEW " . $row['mv_name']);
	if (!$result) {
		echo "CREATING MATERIALIZED VIEW " . $row['mv_name'];
		echo PHP_EOL;
		$result = pg_query($dbconn, "CREATE MATERIALIZED VIEW ". $row['mv_name'] ." 
AS " . $row['mv_query']);
	}
	if (!$result) {
		echo "REFRESH FAILED " . var_dump($result);
		echo PHP_EOL;
		return false;
	}
}

?>
