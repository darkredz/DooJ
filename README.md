DooJ
====

DooJ - doophp on steroids. Runs on JVM, event-driven, non-blocking I/O realtime web framework, modular and distributed app/web server, built in proxy load balancer, and session clustering (with optional Redis failover)

##Requirements
- JDK 7 http://www.oracle.com/technetwork/java/javase/downloads/jdk7-downloads-1880260.html
- Vert.x https://github.com/eclipse/vert.x
- Vert.x Mod PHP fork https://github.com/darkredz/mod-lang-php

##Installation
1. Install and setup JDK
2. Download and setup environment path for vert.x
3. Clone the forked mod-lang-php and copy the content to `VERTX_HOME/sys-mods`
4. Update `langs.properties` in your `VERTX_HOME/conf` directory with:
`php=io.vertx~lang-php~2.0.0:io.vertx.lang.php.PhpVerticleFactory2`
5. Clone DooJ, cd to the folder
6. Run commandline: chmod u+x *.sh
7. Run server: ./server.sh -conf server.json

Test it in browser! http://localhost:8888/
` Hello! It works!`

See [Routing](https://github.com/darkredz/DooJ/wiki/Routing)

See [Auto Routing](https://github.com/darkredz/DooJ/wiki/Auto-Routing)

More to write...



