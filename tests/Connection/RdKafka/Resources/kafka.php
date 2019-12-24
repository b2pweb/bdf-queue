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

if (!class_exists(KafkaConsumer::class)) {
    class KafkaConsumer
    {
        public function consume()
        {

        }
        public function commit()
        {

        }
        public function commitAsync()
        {

        }
        public function unsubscribe()
        {

        }
    }
}

if (!class_exists(Message::class)) {
    class Message
    {
        public $payload;
        public $partition;
        public $key;
        public $topic_name;
        public $err = RD_KAFKA_RESP_ERR_NO_ERROR;

        public function errstr()
        {
            return '';
        }
    }
}


if (!class_exists(Producer::class)) {
    class Producer
    {
        public function newTopic()
        {

        }
    }
}


if (!class_exists(ProducerTopic::class)) {
    class ProducerTopic
    {
        public function produce()
        {

        }
    }
}