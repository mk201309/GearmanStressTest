<?php

require_once __DIR__ . '/facebook-php-sdk-v4/src/Facebook/autoload.php';

$id = microtime(true);
$worker = new GearmanWorker();
$worker->addServer(); // 預設為 localhost

// 1.3 Worker1
$worker->addFunction('Md5', 'doMd5');

// 1.3 Worker2 - Worker4
$worker->addFunction('FB', 'doFB');

// 1.4
$worker->addFunction('md5', 'md5_fn');

while ($worker->work()) {
    if ($worker->returnCode() != GEARMAN_SUCCESS) {
        break;
    }
    sleep(1); // 無限迴圈，並讓 CPU 休息一下
}

/* 1.3 Worker1 */
function doMd5($job)
{
    global $id;
    $data = unserialize($job->workload());
    $data = md5($data);
    sleep(1); // 模擬處理時間

    echo "$data\n\n";
    echo "$id: MD5 is done really.\n\n";
}

/* 1.3 Worker2 - Worker4 */
function doFB($job)
{
    global $id;

    $data = unserialize($job->workload());

    updateStatus($data['workerId'], 'I', 'getImg');

    $fb = new Facebook\Facebook([
        'app_id' => '95085067209786',
        'app_secret' => '94adcf9d9f6094ca890ad41303b18112',
        'default_graph_version' => 'v2.0',
    ]);

    // Sets the default fallback access token so we don't have to pass it to each request
    $fb->setDefaultAccessToken($data['session']);

    try {
        $response = $fb->get('/me?fields=cover');
        $userNode = $response->getGraphUser();
    } catch(Facebook\Exceptions\FacebookResponseException $e) {
        // When Graph returns an error
        echo 'Graph returned an error: ' . $e->getMessage()."\n\n";
        return;
    } catch(Facebook\Exceptions\FacebookSDKException $e) {
        // When validation fails or other local issues
        echo 'Facebook SDK returned an error: ' . $e->getMessage()."\n\n";
        return;
    }

    updateStatus($data['workerId'], 'F', 'getImg');

    echo $userNode['cover']['source']."\n\n";

    updateStatus($data['workerId'], 'I', 'filePut');

    $input = $userNode['cover']['source'];
    $output = 'fb.jpg';
    file_put_contents('/tmp/'.$output, file_get_contents($input));

    updateStatus($data['workerId'], 'F', 'filePut');

    sleep(5); // 模擬處理時間
    echo "$id: FB is done really.\n\n";

}

function updateStatus($workerId = 0, $status, $action)
{
    $dsn = "mysql:host=127.0.0.1;dbname=gearman_log";
    $dbh = new PDO($dsn, 'root', 'qwe123');
    $time = date("Y-m-d H:i:s");
    $sql = "UPDATE `fb_action`
            SET `status`= :status
            WHERE `workerId`=:workerId AND `action`=:action";

    $sth = $dbh->prepare($sql);
    $sth->execute(array(
        'status' => $status,
        'workerId' => $workerId,
        'action' => $action,
    ));

    $sth = NULL;
    $dbh = NULL;
}


/* 1.4 */
function md5_fn($job)
{
    echo "Received job: " . $job->handle() . "\n";

    $workload = $job->workload();
    $workload_size = $job->workloadSize();

    echo "Input: $workload ($workload_size)\n";

    $result = md5($workload);

    echo "Result: $result\n";

    # Return what we want to send back to the client.
    return $result;
}

# A much simpler and less verbose version of the above function would be:
function md5_fn_fast($job)
{
    return strrev($job->workload());
}


