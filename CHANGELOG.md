v1.4.0
------

* Add `Bdf\Queue\Consumer\Receiver\ReceiverPipeline` which handle stack of receiver without use delegation chaining
* Allows `Bdf\Queue\Consumer\ReceiverInterface` to take `NextInterface` as parameter (do not change PHP typehint for compatibility)
* Migrate receivers to allow both legacy delegation chaining and next parameter
* Handle new receiver instantiation on `Bdf\Queue\Consumer\Receiver\Builder\ReceiverFactory`. Autodetect by reflection if the factory is legacy
* Add interfaces `Bdf\Queue\Connection\CountableDriverInterface` and `Bdf\Queue\Connection\PeekableDriverInterface` for both queue and topic drivers
* Implements `CountableDriverInterface` and `PeekableDriverInterface` on `MemoryTopic`
* Add `Bdf\Queue\Destination\Topic\ReadableTopicDestination` which implements `ReadableDestinationInterface` to allow use method `count` and `peek` on topics
* Add method `peek` on `Bdf\Queue\Testing\TopicHelper`
* Deprecates `Bdf\Queue\Connection\CountableQueueDriverInterface` and `Bdf\Queue\Connection\PeekableQueueDriverInterface`
* Deprecates `MultiTopicDestination::createByDsn()` and `TopicDestination::createByDsn()`
* BC Breaks: use `Bdf\Queue\Destination\Topic\TopicDestinationFactory::createByDsn()` for create a topic destination. This method will return a `ReadableTopicDestination` is the topic driver supports `peek` or `count` methods
* BC Breaks: default logger of `Bdf\Queue\Consumer\Receiver\Builder\ReceiverBuilder` is resolved on constructor, so changing logger in container will not change logger into built receivers
* BC Breaks: logger instance provided by `Bdf\Queue\Consumer\Receiver\Builder\ReceiverBuilder` is always wrapped into `Bdf\Queue\Consumer\Receiver\Builder\LoggerProxy`
* BC Breaks: `Bdf\Queue\Consumer\Receiver\Builder\ReceiverBuilder::build()` now will use `ReceiverPipeline` instead of legacy delegation chaining
* BC Breaks: registering a factory into `Bdf\Queue\Consumer\Receiver\Builder\ReceiverFactory` with next receiver as parameter, but without typehint is not supported
* BC Breaks: all defaults factories of `Bdf\Queue\Consumer\Receiver\Builder\ReceiverFactory` now use the new receiver system


v1.3.0
------

* BC Breaks: Add method on interface `Bdf\Queue\Consumer\ReceiverInterface::start()`
* BC Breaks: Add parameter `Bdf\Queue\Consumer\ConsumerInterface $consumer` on method `Bdf\Queue\Consumer\ReceiverInterface::receiveStop()`
* BC Breaks: Add parameter `Bdf\Queue\Consumer\ConsumerInterface $consumer` on method `Bdf\Queue\Consumer\ReceiverInterface::terminate()`
* BC Breaks: Add method on interface `Bdf\Queue\Consumer\ConsumerInterface::connection()`
* BC Breaks: Add method on interface `Bdf\Queue\Consumer\Reader\QueueReaderInterface::connection()`


v1.2.0
------

* Adding support of completion in cli commands (#10)
* Adding support of destination in queue helper (#8) (#9)
* Adding compatibillity with doctrine 3 (#7)
* Adding compatibillity to symfony 6 and PHP 8.1
* BC Breaks: Add method on interface DestinationFactoryInterface::destinationNames() and ConnectionDriverFactoryInterface::connectionNames()


v1.1.0
------

* Improve failer system (#5)
* Adding failer attempts and last failed date on failed message (#4)


v1.0.2
------

* Add some documentation
* Add log on receiver and debug method to see the content of the stack
* Add multiqueue send on multi queue destination
* Add support of limit time receiver                                                                                                                              
* Add support of callable resolver for connection factory                                                                                                                          
* Add receiver loader interface                                                                                                                                            
* Add support of symfony 5
* Add PHP 8 compatibility
* Fix reception of error message on processor resolver
* Improve CI and tests


v1.0.1
------

* Adding commands for console
