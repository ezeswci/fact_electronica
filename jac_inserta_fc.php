<?php // Validacion
include("includes/conexion.php");
// Fin validacion
#==============================================================================
define ("WSDLWSAA", "wsaa.wsdl");
define ("WSDLWSW", "wsfe.wsdl");
/*define ("URLWSAA", "https://wsaahomo.afip.gov.ar/ws/services/LoginCms");
define ("URLWSW", "https://wswhomo.afip.gov.ar/wsfev1/service.asmx");
*/
# Cambiar para produccion
define ("URLWSAA", "https://wsaa.afip.gov.ar/ws/services/LoginCms");
define ("URLWSW", "https://servicios1.afip.gov.ar/wsfev1/service.asmx");
#==============================================================================


//_______________________________________________________________________ funciones

date_default_timezone_set('America/Buenos_Aires');

class WsFE
{

    private $Token;
    private $Sign;
    public $CUIT;
    public $ErrorCode;
    public $ErrorDesc;

    public $RespCAE;
    public $RespVencimiento;
    public $RespResultado;
    public $RespUltNro;

    private $client;
    private $Request;
    private $Response;


    private function CreateTRA($SERVICE)
    {
        $TRA = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>' .
            '<loginTicketRequest version="1.0">' .
            '</loginTicketRequest>');
        $TRA->addChild('header');
        $TRA->header->addChild('uniqueId', date('U'));
        $TRA->header->addChild('generationTime', date('c', date('U') - 60 * 5));
        $TRA->header->addChild('expirationTime', date('c', date('U') + 3600 * 12));
        $TRA->addChild('service', $SERVICE);
        $TRA->asXML('TRA.xml');
    }

    private function SignTRA($certificado, $clave)
    {
        $currentPath = getcwd() . "/";
        $STATUS = openssl_pkcs7_sign($currentPath . "TRA.xml", $currentPath . "TRA.tmp", "file://" . $currentPath . $certificado,
            array("file://" . $currentPath . $clave, ""),
            array(),
            !PKCS7_DETACHED
        );
        if (!$STATUS) {
            exit("ERROR generating PKCS#7 signature\n");
        }
        $inf = fopen($currentPath . "TRA.tmp", "r");
        $i = 0;
        $CMS = "";
        while (!feof($inf)) {
            $buffer = fgets($inf);
            if ($i++ >= 4) {
                $CMS .= $buffer;
            }
        }
        fclose($inf);
        unlink($currentPath . "TRA.tmp");
        return $CMS;
    }

    private function CallWSAA($CMS, $urlWsaa)
    {
        $wsaaClient = new SoapClient(WSDLWSAA, array(
            'soap_version' => SOAP_1_2,
            'location' => $urlWsaa,
            'trace' => 1,
            'exceptions' => 0
        ));
        $results = $wsaaClient->loginCms(array('in0' => $CMS));
        file_put_contents("request-loginCms.xml", $wsaaClient->__getLastRequest());
        file_put_contents("response-loginCms.xml", $wsaaClient->__getLastResponse());
        if (is_soap_fault($results)) {
            exit("SOAP Fault: " . $results->faultcode . "\n" . $results->faultstring . "\n");
        }
        return $results->loginCmsReturn;
    }

    private function ProcesaErrores($Errors)
    {
        $this->ErrorCode = $Errors->Err->Code;
        $this->ErrorDesc = $Errors->Err->Msg;
    }

    function Login($certificado, $clave, $urlWsaa)
    {
        ini_set("soap.wsdl_cache_enabled", "1");
        if (!file_exists($certificado)) {
            exit("Failed to open " . $certificado . "\n");
        }
        if (!file_exists($clave)) {
            exit("Failed to open " . $clave . "\n");
        }
        if (!file_exists(WSDLWSAA)) {
            exit("Failed to open " . WSDLWSAA . "\n");
        }
        $SERVICE = "wsfe";
        $this->CreateTRA($SERVICE);
        $CMS = $this->SignTRA($certificado, $clave);
        $TA = simplexml_load_string($this->CallWSAA($CMS, $urlWsaa));
        $this->Token = $TA->credentials->token;
        $this->Sign = $TA->credentials->sign;

        return true;
    }

    function RecuperaLastCMP($PtoVta, $TipoComp)
    {
        $results = $this->client->FECompUltimoAutorizado(
            array('Auth' => array('Token' => $this->Token,
                'Sign' => $this->Sign,
                'Cuit' => $this->CUIT),
                'PtoVta' => $PtoVta,
                'CbteTipo' => $TipoComp));
        if (isset($results->FECompUltimoAutorizadoResult->Errors)) {
            $this->procesaErrores($results->FECompUltimoAutorizadoResult->Errors);
            return false;
        }
        $this->RespUltNro = $results->FECompUltimoAutorizadoResult->CbteNro;

        return true;
    }

    function Reset()
    {
        $this->Request = array();
        return;
    }

    function AgregaFactura($Concepto, $DocTipo, $DocNro, $CbteDesde, $CbteHasta, $CbteFch, $ImpTotal, $ImpTotalConc, $ImpNeto,
                           $ImpOpEx, $FchServDesde, $FchServHasta, $FchVtoPago, $MonId, $MonCotiz)
    {
        $this->Request['Concepto'] = $Concepto;
        $this->Request['DocTipo'] = $DocTipo;
        $this->Request['DocNro'] = $DocNro;
        $this->Request['CbteDesde'] = $CbteDesde;
        $this->Request['CbteHasta'] = $CbteHasta;
        $this->Request['CbteFch'] = $CbteFch;
        $this->Request['ImpTotal'] = $ImpTotal;
        $this->Request['ImpTotConc'] = $ImpTotalConc;
        $this->Request['ImpNeto'] = $ImpNeto;
        $this->Request['ImpOpEx'] = $ImpOpEx;
        $this->Request['ImpTrib'] = 0;
        $this->Request['ImpIVA'] = 0;
        $this->Request['FchServDesde'] = $FchServDesde;
        $this->Request['FchServHasta'] = $FchServHasta;
        $this->Request['FchVtoPago'] = $FchVtoPago;
        $this->Request['MonId'] = $MonId;
        $this->Request['MonCotiz'] = $MonCotiz;
    }

    function AgregaIVA($Id, $BaseImp, $Importe)
    {
        $AlicIva = array('Id' => $Id,
            'BaseImp' => $BaseImp,
            'Importe' => $Importe);

        if (!isset($this->Request['Iva'])) {
            $this->Request['Iva'] = array('AlicIva' => array());
        }

        $this->Request['Iva']['AlicIva'][] = $AlicIva;

         foreach ($this->Request['Iva']['AlicIva'] as $key => $value) {
            $this->Request['ImpIVA'] = $this->Request['ImpIVA'] + $value['Importe'];
        }
   }

    function AgregaTributo($Id, $Desc, $BaseImp, $Alic, $Importe)
    {
        $Tributo = array('Id' => $Id,
            'Desc' => $Desc,
            'BaseImp' => $BaseImp,
            'Alic' => $Alic,
            'Importe' => $Importe);

        if (!isset($this->Request['Tributos'])) {
            $this->Request['Tributos'] = array('Tributo' => array());
        }

        $this->Request['Tributos']['Tributo'][] = $Tributo;

       foreach ($this->Request['Tributos']['Tributo'] as $key => $value) {
            $this->Request['ImpTrib'] = $this->Request['ImpTrib'] + $value['Importe'];
       }
    }

    function Autorizar($PtoVta, $TipoComp)
    {
 
        $Request = array('Auth' => array(
            'Token' => $this->Token,
            'Sign' => $this->Sign,
            'Cuit' => $this->CUIT),
            'FeCAEReq' => array(
                'FeCabReq' => array(
                    'CantReg' => 1,
                    'PtoVta' => $PtoVta,
                    'CbteTipo' => $TipoComp),
                'FeDetReq' => array(
                    'FECAEDetRequest' => $this->Request)
            )
        );

        $results = $this->client->FECAESolicitar($Request);
        if (isset($results->FECAESolicitarResult->Errors)) {
            $this->ProcesaErrores($results->FECAESolicitarResult->Errors);
            return;
        }

        $this->RespResultado = $results->FECAESolicitarResult->FeCabResp->Resultado;

        if ($this->RespResultado == "A") {
            $this->RespCAE = $results->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAE;
            $this->RespVencimiento = $results->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAEFchVto;
        }


        if (isset($results->FECAESolicitarResult->FeDetResp->FECAEDetResponse->Observaciones)){
            if (is_array($results->FECAESolicitarResult->FeDetResp->FECAEDetResponse->Observaciones->Obs)){
                $this->ErrorCode = $results->FECAESolicitarResult->FeDetResp->FECAEDetResponse->Observaciones->Obs[0]->Code;
                $this->ErrorDesc = $results->FECAESolicitarResult->FeDetResp->FECAEDetResponse->Observaciones->Obs[0]->Msg;
            } else {
                $this->ErrorCode = $results->FECAESolicitarResult->FeDetResp->FECAEDetResponse->Observaciones->Obs->Code;
                $this->ErrorDesc = $results->FECAESolicitarResult->FeDetResp->FECAEDetResponse->Observaciones->Obs->Msg;
            }
        }


        return $this->RespResultado == "A";
    }

    function CmpConsultar($TipoComp, $PtoVta, $nro, &$cbte)
    {
        $results = $this->client->FECompConsultar(
            array('Auth' => array('Token' => $this->Token,
                'Sign' => $this->Sign,
                'Cuit' => $this->CUIT),
                'FeCompConsReq' => array('PtoVta' => $PtoVta,
                    'CbteTipo' => $TipoComp,
                    'CbteNro' => $nro)
            )
        );
        if (isset($results->FECompConsultarResult->Errors)) {
            $this->procesaErrores($results->FECompConsultarResult->Errors);
            return false;
        }
        $cbte = $results->FECompConsultarResult->ResultGet;

        return true;
    }

    function getXMLRequest()
    {
        return $this->client->__getLastRequest();
    }

    function setURL($URL)
    {
        $this->client = new SoapClient(WSDLWSW, array(
                'soap_version' => SOAP_1_2,
                'location' => $URL,
                'trace' => 1,
                'exceptions' => 0
            )
        );
    }

}
//_______________________________________________________________________ fin funciones

$nro = 33;
$PtoVta = 2;
$TipoComp = 1;
$FechaComp = date("Ymd");
$certificado = "poli_54aebe5723d8bf36.crt";
$clave = "clave_poli.key";
$cuit = 30710156529;
$urlwsaa = URLWSAA;


$wsfe = new WsFE();
$wsfe->CUIT = $cuit;
$wsfe->setURL(URLWSW);


if ($wsfe->Login($certificado, $clave, $urlwsaa)) {

    if (!$wsfe->RecuperaLastCMP($PtoVta, $TipoComp)) {
        echo $wsfe->ErrorDesc;
    } else {
       // if ($wsfe->CmpConsultar($TipoComp, $PtoVta, $wsfe->RespUltNro, $cbte)) {
        if ($wsfe->CmpConsultar($TipoComp, $PtoVta, $nro, $cbte)) {
            //echo var_dump($cbte);
			$var= var_export($cbte, true);
			//echo $var."<br><br>";
			
    //if (is_array($var)) {
        $toImplode = array();
        foreach($var as $key => $value) {
            $toImplode[] = var_export($key, true).'=>'.var_export_min($value, true);
        echo $value." ---<br><br><br>";
        }
        $code = 'array('.implode(',', $toImplode).')';
        //if ($return) return $code;
        //else echo $code."-*-*-*-*<br>";
        echo $code."<br><br><br><br><br><br>";
    //} //else {
       // return var_export($var, $return);
    //}
	
	
			echo var_export($cbte, true)."<br><br>";
			echo $var[Concepto]."<br>";
			echo $var[DocTipo]."<br>";
			echo $var[DocNro]."<br>";
			echo $var[CbteDesde]."<br>";



        } else {
            echo $wsfe->ErrorDesc;
        };
    }
} else {
    echo $wsfe->ErrorDesc;
}

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="iso-8859-1">
<title>Herramientas JAC</title>
</head>
<body>

<form name="nueva_fac" id="nueva_fac" method="post"  autocomplete="off" action="jac_inserta_fc.php">
<table border="0" cellspacing="10" cellpadding="0">
  <tr>
    <td>fc original:</td>
    <td><input type="text" name="fc_original"></td>
  </tr>
  <tr>
    <td>fc faltante:</td>
    <td><input type="text" name="fc_nueva"></td>
  </tr>
  <tr>
    <td>CAE:</td>
    <td><input type="text" name="cae"></td>
  </tr>
  <tr>
    <td>tipo:</td>
    <td>
        <select name="TipoComp" id="TipoComp">
        <option value="0">Tipo de comprobante</option>
        <option value="1">Factura A</option>
        <option value="2">Nota de Debito A</option>
        <option value="3">Nota de Credito A</option>
        <option value="6">Factura B</option>
        <option value="7">Nota de Debito B</option>
        <option value="8">Nota de Credito B</option>
        <option value="11">Factura C</option>
        <option value="12">Nota de Debito C</option>
        <option value="13">Nota de Credito C</option>
        </select>
    </td>
  </tr>
  <tr>
    <td>pto. vta:</td>
    <td><input type="text" name="pto_vta" id="pto_vta"></td>
  </tr>
</table>

 

<?php
$TipoComp=$_POST[TipoComp]; 
$fc_original=$_POST[fc_original]; 
$fc_nueva=$_POST[fc_nueva]; 
$cae=$_POST[cae]; 
$TipoComp=$_POST[pto_vta]; 


if($TipoComp == '1') { $letra = 'A' ;}  // Factura A
if($TipoComp == '2') { $letra = 'A' ;}  // Nota de Debito A
if($TipoComp == '3') { $letra = 'A' ;}  // Nota de Credito A
if($TipoComp == '6') { $letra = 'B' ;}  // Factura B
if($TipoComp == '7') { $letra = 'B' ;}  // Nota de Debito B
if($TipoComp == '8') { $letra = 'B' ;}  // Nota de Credito B
if($TipoComp == '11') { $letra = 'C' ;} // Factura C
if($TipoComp == '12') { $letra = 'C' ;} // Nota de Debito C
if($TipoComp == '13') { $letra = 'C' ;} // Nota de Credito C

if($TipoComp > 0)
{
	//_____________________________________________________________________________________________ cabecera
	$sq1="select * from vtafaccab where  vfc_letra='A' and vfc_sucu='2' and vfc_numero='80'";
	$rs1 = mysql_query($sq1) or die (mysql_error()." -Error 1");
	while($row1=mysql_fetch_array($rs1))
	{
	$sq2="insert into vtafaccab values('',	
		'".$row1[vfc_cli_id]."',
		'".$row1[vfc_fecha]."',
		'".$row1[vfc_tipo]."',
		'".$row1[vfc_letra]."',
		'".$row1[vfc_sucu]."',
		'79',
		'".$row1[vfc_detalle]."',
		'".$row1[vfc_fec_vto]."',
		'".$row1[vfc_moneda]."',
		'".$row1[vfc_total]."',
		'".$row1[vfc_estado]."',
		'".$row1[vfc_desc]."',
		'".$row1[vfc_con_vta]."',
		'".$row1[vfc_oc]."',
		'".$row1[vfc_ppto]."',
		'".$row1[usuario]."',
		'".$row1[fecha]."',
		'65299969685931',
		'".$row1[vfc_veto_cae]."' )";
		
		
	echo $sq2."<br><br>";
	$rs2 = mysql_query($sq2) or die (mysql_error()." -Error 2");
	}
	
	//_____________________________________________________________________________________________ lineas
	$sq3="select * from vtafaclin where  vfl_letra='A' and vfl_sucu='2' and vfl_numero='80'";
	$rs3 = mysql_query($sq3) or die (mysql_error()." -Error 3");
	while($row3=mysql_fetch_array($rs3))
	{
	$sq4="insert into vtafaclin values('',
		'".$row3[vfl_cli_id]."',
		'".$row3[vfl_fecha]."',
		'".$row3[vfl_fec_con]."',
		'".$row3[vfl_tipo]."',
		'".$row3[vfl_letra]."',
		'".$row3[vfl_sucu]."',
		'79',
		'".$row3[vfl_concepto]."',
		'".$row3[vfl_cuenta]."',
		'".$row3[vfl_centro]."',
		'".$row3[vfl_importe]."',
		'".$row3[vfl_descuento]."',
		'".$row3[vfl_producto]."',
		'".$row3[vfl_cantidad]."',
		'".$row3[vfl_pre_unit]."',
		'".$row3[vfl_pre_desc]."',
		'".$row3[vfl_detalle]."',
		'".$row3[vfl_desc_1]."',
		'".$row3[vfl_desc_2]."',
		'".$row3[vfl_desc_3]."',
		'".$row3[usuario]."',
		'".$row3[fecha]."' )";
	echo $sq4."<br>";
	$rs4 = mysql_query($sq4) or die (mysql_error()." -Error 4");
	}
	echo "<br>";
	
	//_____________________________________________________________________________________________ cta cte
	$sq5="select * from vtactacte where  vcc_letra='A' and vcc_sucu='2' and vcc_numero='80'";
	$rs5 = mysql_query($sq5) or die (mysql_error()." -Error 5");
	while($row5=mysql_fetch_array($rs5))
	{
	$sq6="insert into vtactacte values('',
	'".$row5[vcc_cli_id]."',
		'".$row5[vcc_fecha]."',
		'".$row5[vcc_fec_vto]."',
		'".$row5[vcc_tipo]."',
		'".$row5[vcc_letra]."',
		'".$row5[vcc_sucu]."',
		'79',
		'".$row5[vcc_moneda]."',
		'".$row5[vcc_importe]."',
		'".$row5[vcc_impuesto]."',
		'".$row5[vcc_total]."',
		'".$row5[vcc_saldo]."',
		'".$row5[vcc_sal_imp]."',
		'".$row5[vcc_tipo_p]."',
		'".$row5[vcc_letra_p]."',
		'".$row5[vcc_sucu_p]."',
		'79',
		'".$row5[usuario]."',
		'".$row5[fecha]."')";
		
	echo $sq6."<br>";
	$rs6 = mysql_query($sq6) or die (mysql_error()." -Error 6");
	}
	echo "<br>";
	
	//_____________________________________________________________________________________________ autorizaciones afip
	$sq7="select * from autorizaciones_afip where aut_tipo='1' and aut_letra='A' and aut_numero='80'";
	$rs7 = mysql_query($sq7) or die (mysql_error()." -Error 7");
	while($row7=mysql_fetch_array($rs7))
	{
	$sq8="insert into autorizaciones_afip values('',
	
		'".$row7[aut_fecha]."',
		'".$row7[aut_tipo]."',
		'".$row7[aut_letra]."',
		'79',
		'65299969685931',
		'".$row7[aut_cuit]."',
		'".$row7[aut_total_21]."',
		'".$row7[aut_iva_21]."',
		'".$row7[aut_total_105]."',
		'".$row7[aut_iva_105]."',
		'".$row7[aut_total_0]."',
		'".$row7[aut_iva_0]."',
		'".$row7[aut_vencimiento]."')";
	echo $sq8."<br>";
	$rs8= mysql_query($sq8) or die (mysql_error()." -Error 8");
	}
}

 ?>

 




</form>



