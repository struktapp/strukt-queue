<?php

use Strukt\Queue;

function push(string $queue_name, array $item){

	$mq = new Queue();
	return $mq->push($queue_name, $item);
};

function pop(string $queue_name){

	$mq = new Queue();
	$data = $mq->pop($queue_name, $id);

	return [$id, $data];
};

function reply(string $queue_name, callable $func){

	$mq = new Queue();
	$data = $mq->pop($queue_name, $mid);

	$reply = call_user_func($func, $data);
	
	return $mq->reply($queue_name, $mid, $reply);
}

function wait(string $queue_name, array $data, callable $func){

	$mq = new Queue();
	$mid = $mq->push($queue_name, $data);
	while(!$mq->is_processed($queue_name, $mid))// Wait for execution
		sleep(1);

	$reply = $mq->getReply($queue_name, $mid);

	return call_user_func($func, $reply, $mid);
}
