<?php
namespace Test;

class GearmanTest extends \PHPUnit_Framework_TestCase
{
    public static $client;
    public static $runCount = 0;
    public static $taskCallbacks = array();

    public static function setupBeforeClass()
    {
        // Set up connection to gearmand
        self::$client = new \GearmanClient();
        self::$client->addServer();
        self::$client->setCompleteCallback(array(__CLASS__, 'gearman_task_complete'));

    }

    public function tearDown()
    {
        global $gearman_worker_pid;
        self::$runCount++;

        // Count all the test* methods to see how many tasks we have to run first
        $testMethods = array();
        foreach(get_class_methods($this) as $method) {
            if(strpos($method, 'test') === 0) {
                $testMethods[] = $method;
            }
        }

        // Run all tasks when all tests are done
        if(self::$runCount === count($testMethods)) {
            self::$client->runTasks();

            // Cleanup
            exec('kill ' . $gearman_worker_pid);
        }
    }

    public function testReverseTask()
    {
        $phpunit = $this;
        $taskId = $this->runTask("md5", 'hello', function($result) use($phpunit) {
            $phpunit->assertEquals("321CBA", $result);
        });
    }

    public function testFBTask()
    {
        $phpunit = $this;

        $workerId = microtime(true) * 10000;

        $this->assignWorker($workerId);

        $fbData = array(
            // session 會過期, 需定時更換
            'session' => 'EAAJ4LRqFDDoBAPiTjM63FJlRVbSBZAjvK1a82iNZBNMD101sDyVocqSm0MZA4KG9gy1LFrUoMFUeAtndu5rmbdezOqX8IWotYpXn70kEoEAO690819vq00tcMZCeXdgHmX1ZCwo4CA8hFpK1yar2lkgPUv48k6Qre9vmYK7iQ9QZDZD',
            'workerId' => $workerId
        );
        $taskId = $this->runTask("fb", serialize($fbData), function($result) use($phpunit) {
            $result = json_decode($result, true);

            $cover = 'https://scontent.xx.fbcdn.net/v/t1.0-9/s720x720/1393178_739980722682625_1679047790_n.jpg?oh=816521b7f5fc89ff66b7f61207f459e1&oe=57FFDE62';
            $phpunit->assertEquals($cover, $result['cover']);
            $phpunit->assertFileExists($result['path']);

            $checkWorker = $this->checkWorker($result['logId']);
            $phpunit->assertEquals('Y', $checkWorker);
        });
    }

    /**
     * Add task to Gearman queue to run
     */
    protected function runTask($name, $params, $callback)
    {
        // Assign task id, store callback with task id, addTask to queue
        $taskId = uniqid(php_uname('n'), true);
        self::$taskCallbacks[$taskId] = $callback;
        $task = self::$client->addTask($name, $params, null, $taskId);

        // Return Task ID
        return $taskId;
    }

    protected function assignWorker($workerId = 0)
    {
        $dsn = "mysql:host=127.0.0.1;dbname=gearman_log";
        $dbh = new \PDO($dsn, 'root', 'qwe123');
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

    protected function checkWorker($workerId = 0)
    {
        $dsn = "mysql:host=127.0.0.1;dbname=gearman_log";
        $dbh = new \PDO($dsn, 'root', 'qwe123');

        $sql = "SELECT *  FROM `fb_action` WHERE `workerId` = :workerId";
        $sth = $dbh->prepare($sql);
        $sth->execute(array(
            'workerId' => $workerId,
        ));

        $result = 'Y';

        while ($row = $sth->fetch()) {
            if ($row['status'] != 'F') {
                $result = 'N';
            }
        }

        $sth = NULL;
        $dbh = NULL;

        return $result;
    }

    /**
     * Gearman complete callback
     */
    public static function gearman_task_complete($task)
    {
        call_user_func(self::$taskCallbacks[$task->unique()], $task->data());
        echo "\nCOMPLETE: " . $task->unique() . " = " . $task->data();
    }
}

