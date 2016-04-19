<?php
echo('prueba 8');echo('</br>');
include('wsfe-client.php');
$nro = 0;
$PtoVta = 002;
$TipoComp = 1;
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
        echo('Ultimo numero = '.$wsfe->RespUltNro);

    }
} else {
    echo ('error linea 36 '.$wsfe->ErrorDesc);
}

?>