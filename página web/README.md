Los archivos dentro de esta carpeta van tal cual en la carpeta /var/www/html/ de nuestro servidor Ubuntu de aplicaciones
Las configuraciones de los Ubuntu server son las siguientes (red interna):
Web: 192.168.10.2/24
Aplicaciones: 192.168.10.2/24
BBDD: 192.168.10.4/24
---------------------------------------------------------Adaptadores extra en los Ubuntu Server-----------------------------------------------------------------------
Web: Adaptador puente con dirección IP fija (192.168.5.135)
Aplicaciones: NAT
BBDD: NAT
--------------------------------------------------------Monitoreo-----------------------------------------------------------------------------------------------------
Usamos Prometheus para monitorizar los tres servidores al mismo tiempo y de manera centralizada y Grafana para tener estos datos en unas gráficas constantes (las de prometheus se borran cada vez que salimos de la página)
Todos los derechos están reservados por los miembros de Technostore© y creadores de dicho trabajo
