<?php
echo('prueba 7');echo('</br>');
include('wsfe-client.php');
$nro = 0;
$PtoVta = 100;
//$TipoComp = 1;
$FechaComp = date("Ymd");
$certificado = "certificado.crt";
$clave = "clave.key";
$cuit = 20939802593;
$urlwsaa = URLWSAA;

/*
tipos de documento
cuit 80
cuil 86
dni 96
-----------------------------------------

codigos iva
Cod.	Por%
-------------
3 ==>	0%	
4 ==>	10.5%	
5 ==>	21%	
6 ==> 27%	
8 ==>	5%	
9 ==>	2.5%	
-----------------------------------------

$TipoComp = 1 ==> Factura A
$TipoComp = 2 ==> Nota de Debito A
$TipoComp = 3 ==> Nota de Credito A
$TipoComp = 6 ==> Factura B
$TipoComp = 7 ==> Nota de Debito B
$TipoComp = 8 ==> Nota de Credito B
$TipoComp = 11 ==> Factura C
$TipoComp = 12 ==> Nota de Debito C
$TipoComp = 13 ==> Nota de Credito C

*/


/*
//___para consumidor final
$tipoDocumento =96;
$cabecera_cuit=16765443;
$TipoComp = 6;
$cabecera_total= 100.0; // total de la factura
$linea_importe=100.0; //total de iva
$tipo_iva=3;
$neto=100;
$monto_iva=0;
*/

//___ para iva 21
$tipoDocumento =80;
$cabecera_cuit=27167654431;
$TipoComp = 1;
$cabecera_total= 1210.0; // total de la factura
$linea_importe=1000.0; //total de iva
$tipo_iva=5;
$neto=1000;
$monto_iva=210;


/*
//___ para iva 105
$tipoDocumento =80;
$cabecera_cuit=27167654431;
$TipoComp = 1;
$cabecera_total= 1105.0; // total de la factura
$linea_importe=105.0; //total de iva
$tipo_iva=4;
$neto=1000;
$monto_iva=105;
*/

/*
//___ para iva exento
$tipoDocumento =80;
$cabecera_cuit=27167654431;
$TipoComp = 1;
$cabecera_total= 1000; // total de la factura
$linea_importe=1000; //total de iva
$tipo_iva=3;
$neto=1000;
$monto_iva=0;
*/


/*
//___ para iva 21 y exento
//producto 1 = 100+21
//producto 2 =50
$tipoDocumento =80;
$cabecera_cuit=27167654431;
$TipoComp = 1;
$cabecera_total= 171.0; // total de la factura
$linea_importe=21.0; //total de iva
$tipo_iva=5;
$neto=100;
$monto_iva=21;

$tipo_iva_exento=3;
$neto_exento=50;
$monto_iva_exento=0;


*/



$wsfe = new WsFE();
$wsfe->CUIT = $cuit;
$wsfe->setURL(URLWSW);


if ($wsfe->Login($certificado, $clave, $urlwsaa)) {

    if (!$wsfe->RecuperaLastCMP($PtoVta, $TipoComp)) {
        echo ('error linea 23 '.$wsfe->ErrorDesc);echo('</br>');
		echo ('error linea (1) COD '.$wsfe->ErrorCode);echo('</br>');
    } else {
        $wsfe->Reset();
		
/*        $wsfe->AgregaFactura(1, 80, 27167654431, $wsfe->RespUltNro + 1, $wsfe->RespUltNro + 1, $FechaComp, 13205.0, 0.0, 11000.0, 0.0, $FechaDesde, $FechaHasta, $fechaVencimiento, "PES", 1);
        $wsfe->AgregaIVA(5, 10000, 2100);
        $wsfe->AgregaIVA(4, 1000, 105);
*/		
        $wsfe->AgregaFactura(1, $tipoDocumento, $cabecera_cuit, $wsfe->RespUltNro + 1, $wsfe->RespUltNro + 1, $FechaComp, $cabecera_total, 0.00, $linea_importe, 0.00, $FechaDesde, $FechaHasta, $FechaVencimiento, "PES", 1);
        $wsfe->AgregaIVA($tipo_iva,$neto,$monto_iva);
		
        if ($wsfe->Autorizar($PtoVta, $TipoComp)) {
            //echo ('linea 29 '.$wsfe->getXMLRequest()); die;
           // echo "Felicitaciones! CAE y Vencimiento y Numero:" . $wsfe->RespCAE . " " . $wsfe->RespVencimiento.'  Fac '.$wsfe->RespUltNro;
                   $nuevo_numero = $wsfe->RespUltNro;
                   echo('Numero anterior = '.$nuevo_numero);echo('</br>');
                   $nuevo_numero = $nuevo_numero + 1;
                   echo('CAE = '.$wsfe->RespCAE);echo('</br>');
                   echo('Vencimiento = '.$wsfe->RespVencimiento);echo('</br>');
                   //echo('Nro Factura = '.$wsfe->RespUltNro);echo('</br>');
                   echo('Nro Factura = '.$nuevo_numero);echo('</br>');
        } else {
            echo 'error 1'.$wsfe->ErrorDesc.' / '.$wsfe->ErrorCode;
        }
    }
} else {
    echo ('error linea 36 '.$wsfe->ErrorDesc);
}

?>