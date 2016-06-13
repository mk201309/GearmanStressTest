<?php
require_once __DIR__ . '/facebook-php-sdk-v4/src/Facebook/autoload.php';

$client = new GearmanClient();
$client->addServer(); // 預設為 localhost

$md5Data = 'hello';

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
    'session' => 'EAAJ4LRqFDDoBAOyp8LQmvWz4aC8hg0v9Krpf0JyQZAofzYZCgmxHTztL3gVANsk6eGwKuPw7k5hYx3HYcFQwmk2A87uBeOKoCXNAer31RiooRh48jp2dXtBReJTZChxemPKJ0NUX2WpfmMu3oPD6SZASvYp5bp9JDg09tc5ZAKgZDZD',
    'workerId' => $workerId
);

$client->doBackground('FB', serialize($fbData));

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

# add two tasks
$task= $client->addTask("md5", $md5Data, NULL);

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
