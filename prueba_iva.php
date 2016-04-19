<?php
/*
Cod.	Por%
-------------
3 ==>	0%	
4 ==>	10.5%	
5 ==>	21%	
6 ==> 27%	
8 ==>	5%	
9 ==>	2.5%	
*/
echo('prueba 7');echo('</br>');
include('wsfe-client.php');
$nro = 0;
$PtoVta = 100;
$TipoComp = 1;
$FechaComp = date("Ymd");
$certificado = "certificado.crt";
$clave = "clave.key";
$cuit = 20939802593;
$urlwsaa = URLWSAA;


$wsfe = new WsFE();
$wsfe->CUIT = $cuit;
$wsfe->setURL(URLWSW);


$importe_21 = 1000;
$iva_21     = $importe_21 *.21;

$importe_105 = 1000;
$iva_105 = $importe_105 * .105;

$importe_0 = 1000;
$iva_0 = 0;

$total_neto = $importe_21 + $importe_105 + $importe_0;
$total_gral = $total_neto + $iva_21 + $iva_105;
if ($wsfe->Login($certificado, $clave, $urlwsaa)) {

    if (!$wsfe->RecuperaLastCMP($PtoVta, $TipoComp)) {
        echo ('error linea (1) '.$wsfe->ErrorDesc);echo('</br>');
		echo ('error linea (1) COD '.$wsfe->ErrorCode);echo('</br>');
    } else {
        $wsfe->Reset();
        //$wsfe->AgregaFactura(1, 80, 21111111113, $wsfe->RespUltNro + 1, $wsfe->RespUltNro + 1, $FechaComp, 12100.0, 0.0, 10000.0, 0.0, "", "", "", "PES", 1);
		$wsfe->AgregaFactura(1, 80, 21111111113, $wsfe->RespUltNro + 1, $wsfe->RespUltNro + 1, $FechaComp, $total_gral, 0.0, $total_neto, 0.0, "", "", "", "PES", 1);
        $wsfe->AgregaIVA(5, $importe_21, $iva_21);
		$wsfe->AgregaIVA(4, $importe_105, $iva_105);
		$wsfe->AgregaIVA(5, $importe_0, $iva_0);
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
            echo ('Error (2) '.$wsfe->ErrorDesc);echo('</br>');
			echo ('Error COD '.$wsfe->ErrorCode);echo('</br>');
        }
    }
} else {
    echo ('error (3) '.$wsfe->ErrorDesc);echo('</br>');
	echo ('Error COD '.$wsfe->ErrorCode);echo('</br>');
}

?>