
libMemcached
===

[libmemcached.org](https://libmemcached.org/)

### Install

```sh
sudo apt install libmemcached-tools # cli tools
```

### Commands

```sh
memcaslap   # is  a load generation and benchmark tool for memcached servers. It generates configurable workload such as threads, concur‐rency, connections, run time, overwrite, miss rate, key size, value size, get/set proportion, expected throughput, and  so  on.  Fur‐thermore, it also tests data verification, expire-time verification, UDP, binary protocol, facebook test, replication test, multi-get and reconnection, etc. Memaslap  manages network connections like memcached with libevent. Each thread of memaslap is bound with a CPU core, all the threads don't communicate with each other, and there are several socket connections in each thread. Each connection keeps key size  distribu‐tion, value size distribution, and command distribution by itself. You can specify servers via the memaslap --servers option or via the environment variable MEMCACHED_SERVERS.
memccapable # connects to the specified memcached server and tries to determine its capabilities by running various commands and verify‐ing the response.
memccat     # reads and outputs the value of a single or a set of keys stored in a memcached(1) server. If any key is not found an error is returned. It is similar to the standard UNIX cat(1) utility.
memccp      # copies one or more files into memcached(1) servers.  It is similar to the standard UNIX cp(1) command. The key names will be the names of the files, without any directory path.
memcdump    # dumps  a list of "keys" from all servers that it is told to fetch from. Because memcached does not guarantee to provide all keys it is not possible to get a complete "dump".
memcerror   # translates an error code from libmemcached into a human readable string.
memcexist   # checks for the existence of a key within a cluster.
memcflush   # resets the contents of memcached(1) servers.WARNING: This means that all data in the specified servers will be deleted.
memcparse   # can be used to validate an option string.
memcping    # can be used to ping a memcached server to see if it is accepting connections.
memcrm      # removes items, specified by key, from memcached(1) servers.
memcslap    # is a load generation and benchmark tool for memcached(1) servers. It generates configurable workload such as threads, concur‐rencies, connections, run time, overwrite, miss rate, key size, value size, get/set proportion, expected throughput, and so on.
memcstat    # dumps the state of memcached(1) servers.  It prints all data to stdout.
memtouch    # does a "touch" on the specified key.
```
