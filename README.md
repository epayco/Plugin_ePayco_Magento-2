# Plugin Integración ePayco para Magento 2 (2.x)

Este plugin permite integrar ePayco como medio de pago para sus tiendas en Magento 2.

**Si usted tiene alguna pregunta o problema, no dude en ponerse en contacto con nuestro soporte técnico: desarrollo@payco.co.**

## Versiones
* [ePayco plugin Magento v2.2.x](https://github.com/epayco/magento2.x/releases/tag/v2.2).
* [ePayco plugin Magento v2.3.x](https://github.com/epayco/magento2.x/releases/tag/v2.3).
* [ePayco plugin Magento v2.4.x](https://github.com/epayco/Plugin_ePayco_Magento-2/releases/tag/v2.4).
* [ePayco plugin Magento v2.5.0](https://github.com/epayco/Plugin_ePayco_Magento-2/releases/tag/v2.5.0).
## Iniciando

En estas instrucciones usted encontrará las indicaciones para instalar el módulo y activarlo en su instalación de Magento 2.

### Prerrequisitos

Necesita tener instalado Magento 2 con todas sus dependencias y una cuenta en ePayco.


### Installing


1- Clonar el repositorio en su máquina.

```
git clone https://github.com/epayco/magento2.x.git
```
2- Ingresar a la carpeta creada y copiar el contenido en su instalacion en magento en la ruta ruta/de/su/instalacion/app/code/
```
cd magento2
cp . -R /ruta/de/su/instalacion/app/code/
```
3- Dirigirse a la ruta de instalación de su magento 2 y ejecutar los siguientes comandos
```
php bin/magento module:enable PagoEpayco_Payco
php bin/magento setup:upgrade
php bin/magento setup:di:compile
```
4- Si desea puede ejecutar el siguiente comando para verificar que el modulo esté habilitado
```
php bin/magento module:status
```

## Finalización

Ya puede ingresar al área de administración de Magento2 e ingresar a Tiendas->configuracion->Metodos de pago
y encontrará el panel de ePayco para configurarlo.



