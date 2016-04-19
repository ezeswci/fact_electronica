
<?php
echo('prueba controlar');echo('</br>');
include('wsfe-client1.php');

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


if ($wsfe->Login($certificado, $clave, $urlwsaa)) {

    if (!$wsfe->RecuperaLastCMP($PtoVta, $TipoComp)) {
        echo $wsfe->ErrorDesc;
    } else {
        if ($wsfe->CmpConsultar($TipoComp, $PtoVta, $wsfe->RespUltNro, $cbte)) {
            echo var_dump($cbte);echo('</br>');
			echo('Cae Otorgado = '.$cbte[1]);
        } else {
            echo $wsfe->ErrorDesc;
        };
    }
} else {
    echo $wsfe->ErrorDesc;
}

?>