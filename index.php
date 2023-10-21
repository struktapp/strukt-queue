<?php

require "bootstrap.php";

$data = array(
	"username"=>"pitsolu", 
	"password"=>"p@55w0rd"
);

$uid = push("queue_test", $data);
list($oid, $udata) = pop("queue_test");

print_r(array(

	"uid"=>$uid,
	"oid"=>$oid,
	"data"=>$data,
	"udata"=>$udata
));



