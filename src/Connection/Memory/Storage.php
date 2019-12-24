<?php

namespace Bdf\Queue\Connection\Memory;

/**
 * Storage
 */
class Storage
{
    /**
     * @var \SplObjectStorage[]
     */
    public $queues = [];

    /**
     * The awaiting callback to run
     *
     * @var array
     */
    public $awaitings = [];

    /**
     * The subscribers
     *
     * @var array
     */
    public $listeners = [];
}