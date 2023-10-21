<?php

$host = '127.0.0.1';
$port = 11211;

$m = new \Memcached;
$m->addServer($host, $port);

return $m;
