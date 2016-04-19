<?php
echo('prueba 7');echo('</br>');
include('wsfe-client1.php');
$nro = 0;
$FechaComp = date("Ymd");
$certificado = "certificado.crt";
$clave = "clave.key";
$cuit = 20939802593; // PRUEBA
//$cuit = 30715159550; // fyg
//$cuit = 20926764633; // Norseg
$urlwsaa = URLWSAA;
$PtoVta = 001;
//---------------------------------------------------
$wsfe = new WsFE();
$wsfe->CUIT = $cuit;
$wsfe->setURL(URLWSW);
$TipoComp = 1;
if ($wsfe->Login($certificado, $clave, $urlwsaa)) {

	if (!$wsfe->RecuperaLastCMP($PtoVta, $TipoComp))
		{echo ('error linea 23 '.$wsfe->ErrorDesc);}
		else
		{echo('Ultimo numero Factura A = '.$PtoVta.'-'.$wsfe->RespUltNro);echo('</br>');}

	$TipoComp = 4;
	if (!$wsfe->RecuperaLastCMP($PtoVta, $TipoComp))
		{echo ('error linea 23 '.$wsfe->ErrorDesc);}
		else
		{echo('Ultimo numero Recibo A = '.$PtoVta.'-'.$wsfe->RespUltNro);echo('</br>');}


		
} else {
    echo ('error linea 36 '.$wsfe->ErrorDesc);
}

?>