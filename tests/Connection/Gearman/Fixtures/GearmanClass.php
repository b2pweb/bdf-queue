<?php

if (!defined('GEARMAN_SUCCESS')) {
    define('GEARMAN_SUCCESS', 0);
}

if (!defined('GEARMAN_COULD_NOT_CONNECT')) {
    define('GEARMAN_COULD_NOT_CONNECT', 26);
}

if (!defined('GEARMAN_LOST_CONNECTION')) {
    define('GEARMAN_LOST_CONNECTION', 14);
}

if (!defined('GEARMAN_TIMEOUT')) {
    define('GEARMAN_TIMEOUT', 47);
}

if (!class_exists(GearmanClient::class)) {
    class GearmanClient {
        public function __construct() {}
        public function returnCode() {}
        public function error() {}
        public function getErrno() {}
        public function options() {}
        public function setOptions($options) {}
        public function addOptions($options) {}
        public function removeOptions($options) {}
        public function timeout() {}
        public function setTimeout($timeout) {}
        public function context() {}
        public function setContext($context) {}
        public function addServer($host = '127.0.0.1', $port = 4730) {}
        public function addServers($servers = '127.0.0.1:4730') {}
        public function wait() {}
        public function doHigh($function_name, $workload, $unique = null) {}
        public function doNormal($function_name, $workload, $unique = null) {}
        public function doLow($function_name, $workload, $unique = null) {}
        public function doJobHandle() {}
        public function doStatus() {}
        public function doBackground($function_name, $workload, $unique = null) {}
        public function doHighBackground($function_name, $workload, $unique = null) {}
        public function doLowBackground($function_name, $workload, $unique = null) {}
        public function jobStatus($job_handle) {}
        public function addTask($function_name, $workload, $context = null, $unique = null) {}
        public function addTaskHigh($function_name, $workload, $context = null, $unique = null) {}
        public function addTaskLow($function_name, $workload, $context = null, $unique = null) {}
        public function addTaskBackground($function_name, $workload, $context = null, $unique = null) {}
        public function addTaskHighBackground($function_name, $workload, $context = null, $unique = null) {}
        public function addTaskLowBackground($function_name, $workload, $context = null, $unique = null) {}
        public function addTaskStatus($job_handle, $context = null) {}
        public function setWorkloadCallback($callback) {}
        public function setCreatedCallback($callback) {}
        public function setDataCallback($callback) {}
        public function setWarningCallback($callback) {}
        public function setStatusCallback($callback) {}
        public function setCompleteCallback($callback) {}
        public function setExceptionCallback($callback) {}
        public function setFailCallback($callback) {}
        public function clearCallbacks() {}
        public function runTasks() {}
        public function ping($workload) {}
    }
}

if (!class_exists(GearmanWorker::class)) {
    class GearmanWorker {
        public function __construct() {}
        public function returnCode() {}
        public function error() {}
        public function getErrno() {}
        public function options() {}
        public function setOptions($option) {}
        public function addOptions($option) {}
        public function removeOptions($option) {}
        public function timeout() {}
        public function setTimeout($timeout) {}
        public function setId($id) {}
        public function addServer($host = '127.0.0.1', $port = 4730) {}
        public function addServers($servers = '127.0.0.1:4730') {}
        public function wait() {}
        public function register($function_name, $timeout) {}
        public function unregister($function_name) {}
        public function unregisterAll() {}
        public function grabJob() {}
        public function addFunction($function_name, $function, $context = null, $timeout = 0) {}
        public function work() {}
    }
}

if (!class_exists(GearmanJob::class)) {
    class GearmanJob {
        public function returnCode() {}
        public function setReturn($gearman_return_t) {}
        public function sendData($data) {}
        public function sendWarning($warning) {}
        public function sendStatus($numerator, $denominator) {}
        public function sendComplete($result) {}
        public function sendException($exception) {}
        public function sendFail() {}
        public function handle() {}
        public function functionName() {}
        public function unique() {}
        public function workload() {}
        public function workloadSize() {}
    }
}

if (!class_exists(GearmanException::class)) {
    class GearmanException extends Exception {
        public $code;
    }
}
