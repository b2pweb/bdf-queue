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
