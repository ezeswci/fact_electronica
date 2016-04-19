<?php
set_time_limit(0) ;
echo('prueba 7');echo('</br>');
include('wsfe-client.php');
$nro = 0;
$PtoVta = 100;
$TipoComp = 6;
$FechaComp = date("Ymd");
$certificado = "certificado.crt";
$clave = "clave.key";
$cuit = 20939802593;
$urlwsaa = URLWSAA;


$wsfe = new WsFE();
$wsfe->CUIT = $cuit;
$wsfe->setURL(URLWSW);


if ($wsfe->Login($certificado, $clave, $urlwsaa)) {

    if (!$wsfe->RecuperaLastCMP($PtoVta, $TipoComp)) {
        echo ('error linea 23 '.$wsfe->ErrorDesc);
    } else {
        $wsfe->Reset();
        $wsfe->AgregaFactura(1, 99, 0, $wsfe->RespUltNro + 1, $wsfe->RespUltNro + 1, $FechaComp, 605.0, 0.0, 500.0, 0.0, "", "", "", "PES", 1);
        $wsfe->AgregaIVA(5, 500, 105);
        if ($wsfe->Autorizar($PtoVta, $TipoComp)) {
            //echo ('linea 29 '.$wsfe->getXMLRequest()); die;
           // echo "Felicitaciones! CAE y Vencimiento y Numero:" . $wsfe->RespCAE . " " . $wsfe->RespVencimiento.'  Fac '.$wsfe->RespUltNro;
		   $nuevo_numero = $wsfe->RespUltNro;
		   echo('Numero anterior = '.$nuevo_numero);
		   $nuevo_numero = $nuevo_numero + 1;
		   echo('CAE = '.$wsfe->RespCAE);echo('</br>');
		   echo('Vencimiento = '.$wsfe->RespVencimiento);echo('</br>');
		   //echo('Nro Factura = '.$wsfe->RespUltNro);echo('</br>');
		   echo('Nro Factura = '.$nuevo_numero);echo('</br>');
        } else {
            echo ('Error linea 32 '.$wsfe->ErrorDesc);
        }
    }
} else {
    echo ('error linea 36 '.$wsfe->ErrorDesc);
}

?>