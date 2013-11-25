DooJ
====

DooJ - doophp on steroids. Runs on JVM, event-driven, non-blocking I/O realtime web framework

##Requirements
- JDK 7 http://www.oracle.com/technetwork/java/javase/downloads/jdk7-downloads-1880260.html
- Vert.x https://github.com/eclipse/vert.x
- Vert.x Mod PHP fork https://github.com/darkredz/mod-lang-php

##Installation
- Install and setup JDK
- Download and setup environment path for vert.x
- Set vert.x langs.properties to php=io.vertx~lang-php~2.0.0:io.vertx.lang.php.PhpVerticleFactory2
- Clone DooJ
- Run commandline: chmod u+x *.sh
- Run server: ./server.sh -conf server.json

Test it in browser! http://localhost:8888/
> Hello! It works!






