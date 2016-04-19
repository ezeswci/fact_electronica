<?php
#==============================================================================
define ("WSDLWSAA", "wsaa.wsdl");
define ("WSDLWSW", "wsfe.wsdl");
#define ("URLWSAA", "https://wsaahomo.afip.gov.ar/ws/services/LoginCms");
#define ("URLWSW", "https://wswhomo.afip.gov.ar/wsfev1/service.asmx");
# Cambiar para produccion
define ("URLWSAA", "https://wsaa.afip.gov.ar/ws/services/LoginCms");
define ("URLWSW", "https://servicios1.afip.gov.ar/wsfev1/service.asmx");
#==============================================================================

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

    public $mw_comprobante;
	
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
		$this->mw_comprobante = $results->FECompConsultarResult->CbteDesde; // matias

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
?>