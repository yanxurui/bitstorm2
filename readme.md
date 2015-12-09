#A fork of BitStorm, now with Redis support!
##What is BitStorm
BitStorm is a super-thin bittorrent tracker written in PHP which does not require a database.  
BitStorm was originally written by Peter Caprioli as a lightweight bittorrent tracker contained in a single PHP file. As it used only a single flat file as a database, it had difficulty scaling past ~10 announces per second. You can find more detail [here](https://stormhub.org/tracker/ui.php)  
Peter has rewrote the code with MySQL support. Josh Duff forked the beta MySQL code and  published it on [Google Code](https://code.google.com/p/bitstorm/), allowing it to scale a huge numbers of peers.

####This version is rewrited based on the bitstorm using redis as its database.
##What is a Bittorrent Tracker
Tracker is an HTTP/HTTPS service which responds to HTTP GET requests from bittorrent clients such as utorrent. In a word, a tracker maintains information about which peers are downloading a common file.Read [BitTorrent Protocol Specification](http://www.bittorrent.org/beps/bep_0003.html) for a brief introduction about bittorrent and tracker.

####A more detailed Specification is [here](https://wiki.theory.org/BitTorrentSpecification#Tracker_Response).
##Data layout

|#|key				|expire|value-type  |value		 
|-|-----------------|------|------------|------------
|1|torrents			|NA    |set			|info_hash1,hash_info2...
|2|info_hash		|NA    |set			|peer_id1,peer_id2...
|3|info_hash:peer_id|1860s |hash		|ip4,ip6,port,seed
**info_hash**: sha1 hash of the bencoded form of the info value from the metainfo file(.torrent file), can dentify the real file to be transfered.
**peer_id**: identifier of client, generated/changed by the client at startup. Another similay parameter is **key** which is identifier of client and generated/changed by the client when a new session starts.
**seed**: a peer is seeding when he has 0 bytes left to download, otherwise he is leeching.
##Features
1. Redis as storage.
2. Both ipv4 and ipv6 supported. The compact field is ignored to support ipv6.
3. NAT are naturally supported without extra effort.
4. A stats page based on [datatable](http://www.datatables.net/).

##Usage
1. Place the contents in the document root of a php-supported server.
2. Run `composer install` to download Predis.
3. Make sure your redis server can be accessed.
4. Make a torrent and fill tracker as the following URLS(If port absents, 80 will be used)
> http://youripv4:port/announce
> 
> http://[youripv6]:port/announce
5. Visit `http://yourip:port/announce` or `http://yourip:port/stats.php` in a broswer to see the statistics of the running tracker.