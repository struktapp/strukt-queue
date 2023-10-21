<?php

namespace Strukt;	

/**
 * Simple implementation of a Message Queue 
 * that uses Memcached https://memcached.org/
 * as backend
 * 
 * Needs PHP Memcached extensions to be installed to work properly
 * 
 * @author Maurizio Giunti https://www.mauriziogiunti.it / https://codeguru.it
 * @license MIT
 *  
 * @original_name class name Memqueue
 *
 * */	
class Queue{
	
	private $memcached = NULL;
	private $ttl = 3600; // Tempo max permanenza in coda
	
	/**
	 * @param array $options[
	 * 	 host
	 *	 port
	 *	 ttl - optional
	 *	 username - optional
	 *	 password - optional]
	 * */
	function __construct(array $options = []) {

		$defaults = array(

			"host"=>$options["host"]??env("memcached_host"),
			"port"=>$options["port"]??env("memcached_port"),
			"ttl"=>$options["ttl"]??env("memcached_ttl"),
			"username"=>$options["username"]??env("memcached_username"),
			"password"=>$options["password"]??env("memcached_password")
		);

		if(!empty($options))
			$options = array_merge($defaults, $options);

		if(empty($options))//still empty
			$options = $defaults;

		if(!class_exists('Memcached'))
			die("ERROR: class Memcached not found - You must install Memcached PHP extenstion!\n");

		if(array_key_exists("ttl", $options))
			$this->ttl = $options["ttl"];

		$this->memcached = new \Memcached;			

		if(array_key_exists("username", $options) && array_key_exists("password", $options)){
			if(!empty($options["username"]) && !empty($options["password"])){

				$this->memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
				$this->memcached->setSaslAuthData($options["username"], $options["password"]);	
			}
		}

		$this->memcached->addServer($options["host"], $options["port"])  or die ("Could not connect!");
	}
	
	private function __clone() {}
			

	/* * 
	 * push : adds an element at the end of the queue
	 * @param string $queue name of the queue
	 * @param object $item the item to store in the queue
	 * @return int the id of the element in the queue or FALSE in case of error
	 * */
	public function push($queue, $item) {
		
		$id = intval($this->memcached->increment($queue."_tail"));
		if($id==0) { // Empty queue: must init
			$this->memcached->add($queue."_tail", 1, $this->ttl); // Init tail
			$this->memcached->add($queue."_head", 0, $this->ttl); // Init head
			$id=1;
		}
					
		if($this->memcached->add($queue."_".$id, $item, $this->ttl) === FALSE) {
			return FALSE;
		}			
		return $id;
	}

	/**
	 * is_empty: is the queue empty?
	 * @param string $queue name of the queue
	 * @return TRUE: the queue is empty, FALSE: there is something in the queue
	 * */
	public function is_empty($queue) {
	
		$head = intval($this->memcached->get($queue."_head"));
		$tail = intval($this->memcached->get($queue."_tail"));
		
		return ($tail>0 && $head >= $tail);
	}
	
	/**
	 * Tells whether $id message in $queue queue has been processed   
	 *
	 * @param string $queue name of th equeue
	 * @param int $id message id
	 * @return boolean true: has been executed, false: has not been executed or is unknown 
	 */
	public function is_processed($queue, $id) {
		$head = intval($this->memcached->get($queue."_head"));
		$tail = intval($this->memcached->get($queue."_tail"));
		if(empty($head) || empty($tail)) return FALSE;

		return ($id<=$head);
	}

	/**
	 * pop : pops an element from the beginning of the queue
	 * @param string $queue name of the queue
	 * @param int $id in this variable will be stored the id of the message popped 
	 * @return the stored object or FALSE if the queue is empty
	 */
	public function pop($queue, &$id=null) {
		$id=0;
		if($this->is_empty($queue)) return FALSE; // Empty queue
		$tail = $this->memcached->get($queue."_tail");
		$id = $this->memcached->increment($queue."_head");
		return $this->memcached->get($queue."_".($id));
	}
	
  /**
	 * Sends back a reply
	 * @param string $queue name of the queue
	 * @param int $id id of the message we want to reply to
	 * @param mixed $reply reply to post
	 * @return boolean 
	 */
	public function reply($queue, $id, $reply) {		
		if($this->memcached->add($queue."_".$id."_reply", $reply, $this->ttl) === FALSE) {
			return FALSE;
		}			
		return TRUE;
	}

	/**
	 * Get a reply 
	 *
	 * @param string $queue name of the queue
	 * @param int $id id of the message we want the reply for
	 * @return mixed the reply or FALSE if no reply is available
	 */
	public function getReply($queue, $id) {		
		return $this->memcached->get($queue."_".($id)."_reply");
	}
}
