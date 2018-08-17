# php-deploy-server

Como no tengo maneras de instalar de manera continua una aplicación PHP, es que decidí iniciar este proyecto.

La idea es poder desde un servidor de integración continua enviar los archivos que sufrieron cambios comprimidos en un ZIP.

Para verificar cuáles archivos sufrieron cambios se debe realizar un seguimiento de las modificaciones.

La idea es que funcione de la siguiente manera:

* Cada repositorio tiene al menos un "*commit*" con una marca de tiempo asociada (fecha).
* El servidor tiene una lista de archivos que son los que están en ese momento.

Asumiendo que una marca de tiempo es: `1534362139` podemos comparar la lista de archivos del servidor con dicha marca de tiempo.

La primera vez que se compara, el servidor no conoce esa marca de tiempo, por lo que devolverá la lista completa de archivos y guardará la marca de tiempo para futuras revisiones.

En una segunda vez, la marca de tiempo (de otro "commit") puede ser `1534362140`. 