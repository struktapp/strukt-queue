<?php

require "bootstrap.php";

reply("queue_test", function($data){

	$data["processed"] = true;

	return $data;
});