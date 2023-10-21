<?php

require "bootstrap.php";

$data = ["username"=>"pitsolu", "password"=>"p@55w0rd"];
wait("queue_test", $data, function($reply){

	print_r($reply);
});