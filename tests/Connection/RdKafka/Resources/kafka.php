<?php

namespace RdKafka;

if (!defined('RD_KAFKA_PARTITION_UA')) {
    define('RD_KAFKA_PARTITION_UA', 1);
}
if (!defined('RD_KAFKA_RESP_ERR_NO_ERROR')) {
    define('RD_KAFKA_RESP_ERR_NO_ERROR', 1);
}
if (!defined('RD_KAFKA_RESP_ERR__PARTITION_EOF')) {
    define('RD_KAFKA_RESP_ERR__PARTITION_EOF', -1);
}
if (!defined('RD_KAFKA_RESP_ERR__TIMED_OUT')) {
    define('RD_KAFKA_RESP_ERR__TIMED_OUT', -2);
}
if (!defined('RD_KAFKA_RESP_ERR__TRANSPORT')) {
    define('RD_KAFKA_RESP_ERR__TRANSPORT', -195);
}

if (!class_exists(Conf::class)) {
    class TopicConf
    {
        public function dump()
        {
        }
        public function set($name, $value)
        {
        }
        public function setPartitioner($partitioner)
        {
        }
    }
    class Conf
    {
        /**
         * @return array<string, string>
         */
        public function dump()
        {
        }
        public function set($name, $value)
        {
        }
        public function setDefaultTopicConf(TopicConf $topic_conf)
        {
        }
        public function setDrMsgCb(callable $callback)
        {
        }
        public function setErrorCb(callable $callback)
        {
        }
        public function setRebalanceCb(callable $callback)
        {
        }
        public function setStatsCb(callable $callback)
        {
        }
        public function setConsumeCb(callable $callback)
        {
        }
        public function setOffsetCommitCb(callable $callback)
        {
        }
        public function setLogCb(callable $callback)
        {
        }
    }
}

if (!class_exists(KafkaConsumer::class)) {
    class KafkaConsumer
    {
        public function __construct(Conf $conf)
        {
        }
        public function assign($topic_partitions = null)
        {
        }
        public function commit($message_or_offsets = null)
        {
        }
        public function commitAsync($message_or_offsets = null)
        {
        }
        public function consume($timeout_ms)
        {
        }
        public function getAssignment()
        {
        }
        public function getMetadata($all_topics, $only_topic, $timeout_ms)
        {
        }
        public function getSubscription()
        {
        }
        public function newTopic($topic_name, TopicConf $topic_conf = null)
        {
        }
        public function subscribe($topics)
        {
        }
        public function unsubscribe()
        {
        }
        public function getCommittedOffsets($topicPartitions, $timeout_ms)
        {
        }
        public function offsetsForTimes($topicPartitions, $timeout_ms)
        {
        }
        public function queryWatermarkOffsets($topic, $partition, &$low, &$high, $timeout_ms)
        {
        }
        public function getOffsetPositions($topics)
        {
        }
        public function close()
        {
        }
        public function pausePartitions($topic_partitions)
        {
        }
        public function resumePartitions($topic_partitions)
        {
        }
    }
}

if (!class_exists(Message::class)) {
    class Message
    {
        public $err;
        public $topic_name;
        public $partition;
        public $payload;
        public $len;
        public $key;
        public $offset;
        public $timestamp;
        public $headers;
        public $opaque;
        public function errstr()
        {
            return 'error';
        }
    }
}


if (!class_exists(Producer::class)) {
    class Producer
    {
        public function __construct(Conf $conf = null)
        {
        }
        public function newTopic($topic_name, TopicConf $topic_conf = null)
        {
        }
        public function initTransactions(int $timeoutMs)
        {
        }
        public function beginTransaction()
        {
        }
        public function commitTransaction(int $timeoutMs)
        {
        }
        public function abortTransaction(int $timeoutMs)
        {
        }
        public function poll(int $timeoutMs)
        {
        }
    }
}


if (!class_exists(ProducerTopic::class)) {
    class ProducerTopic
    {
        private function __construct()
        {
        }
        public function getName()
        {
        }
        public function produce($partition, $msgflags, $payload, $key = null)
        {
        }
        public function producev($partition, $msgflags, $payload, $key = null, $headers = null, $timestamp_ms = null)
        {
        }
    }
}

if (!class_exists(TopicPartition::class)) {
    class TopicPartition
    {
        public function __construct($topic, $partition, $offset = null)
        {
        }
        public function getOffset()
        {
        }
        public function getPartition()
        {
        }

        /**
         * @return string
         */
        public function getTopic()
        {
        }
        public function setOffset($offset)
        {
        }
        public function setPartition($partition)
        {
        }
        public function setTopic($topic_name)
        {
        }
    }
}