<?php
require_once __DIR__ . '/facebook-php-sdk-v4/src/Facebook/autoload.php';

$client = new GearmanClient();
$client->addServer(); // 預設為 localhost

/* 1.3 Worker1 */

//$client->doBackground('Md5', serialize($md5Data));
//
//if ($client->returnCode() != GEARMAN_SUCCESS)
//{
//    echo "bad return code\n";
//    exit;
//}
//
//echo "MD5 is done.\n";


/* 1.3 Worker2 - Worker4 */

$workerId = microtime(true) * 10000;

assignWorker($workerId);

$fbData = array(
    // session 會過期, 需定時更換
    'session' => 'EAAJ4LRqFDDoBABSIdLKjx7p3yN5WqCu0wENbrHTaVwXgRF4bI04rrNZBWJZCZChNxYXhM7YT4jb4bL2hlp4FuhyQEKncKtBR02jmeZBxsH9zRZBte58ZCOZBJuvm2CXUnac8AvTZCi4KvIpqhxiBpt6fhkDA7uPk8wjFHRuCCXmYBwZDZD',
    'workerId' => $workerId
);

//$client->doBackground('FB', serialize($fbData));

$client->addTask('FB', serialize($fbData));

echo "FB is done.\n";


function assignWorker($workerId = 0)
{

    $dsn = "mysql:host=127.0.0.1;dbname=gearman_log";
    $dbh = new PDO($dsn, 'root', 'qwe123');
    $time = date("Y-m-d H:i:s");
    $sql = "INSERT INTO `gearman_log`.`fb_action` (`workerId`, `action`, `status`, `ceateTime`) VALUES (:workerId, 'getImg', 'W', :ceateTime), (:workerId, 'filePut', 'W', :ceateTime)";

    $sth = $dbh->prepare($sql);
    $sth->execute(array(
        'workerId' => $workerId,
        'ceateTime' => $time,
    ));

    $sth = NULL;
    $dbh = NULL;
}

/* 1.4 */

# register some callbacks
$client->setCreatedCallback("reverse_created");
$client->setDataCallback("reverse_data");
$client->setStatusCallback("reverse_status");
$client->setCompleteCallback("reverse_complete");
$client->setFailCallback("reverse_fail");

# run the tasks in parallel (assuming multiple workers)
if (! $client->runTasks())
{
    echo "ERROR " . $client->error() . "\n";
    exit;
}

echo "DONE\n";

function reverse_created($task)
{
    echo "CREATED: " . $task->jobHandle() . "\n";
}

function reverse_status($task)
{
    echo "STATUS: " . $task->jobHandle() . " - " . $task->taskNumerator() .
        "/" . $task->taskDenominator() . "\n";
}

function reverse_complete($task)
{
    echo "COMPLETE: " . $task->jobHandle() . ", " . $task->data() . "\n";
}

function reverse_fail($task)
{
    echo "FAILED: " . $task->jobHandle() . "\n";
}

function reverse_data($task)
{
    echo "DATA: " . $task->data() . "\n";
}
