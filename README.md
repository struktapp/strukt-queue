Strukt Queue
===

Simple implementation of a Message Queue 
that uses Memcached https://memcached.org/
as backend
 
Needs PHP Memcached extensions installed to work properly

### Requirements

```sh
sudo apt install memcached
sudo apt install php-memcached
sudo apt install libmemcached-tools # cli tools
```

## Usage

### Wait

```php
//wait.php
$data = ["username"=>"pitsolu", "password"=>"p@55w0rd"];
wait("queue_test", $data, function($reply){

	print_r($reply);
});
```
### Reply

```php
//reply.php
reply("queue_test", function($data){

	$data["processed"] = true;

	return $data;
});
```

## Other Usages

### Pop

```php
$data = array(
	"username"=>"pitsolu", 
	"password"=>"p@55w0rd"
);

$uid = push("queue_test", $data);
```

### Push

```php
list($oid, $udata) = pop("queue_test");

print_r(array(

	"uid"=>$uid,
	"oid"=>$oid,
	"data"=>$data,
	"udata"=>$udata
));
```

## Credits

~~~
@author Maurizio Giunti https://www.mauriziogiunti.it / https://codeguru.it
@license MIT
~~~