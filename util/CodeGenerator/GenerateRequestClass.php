<?php
namespace FedEx\Utility\CodeGenerator;

/**
 * Generates the Request.php class for each
 *
 * @author      Jeremy Dunn <jeremy@jsdunn.info>
 * @package     PHP FedEx API wrapper
 * @subpackage  Utilities
 */
class GenerateRequestClass extends AbstractGenerate
{
    /**
     * @var string
     */
    protected $testingUrl;

    /**
     * @var string
     */
    protected $productionUrl;

    /**
     * Constructor
     *
     * @param string $exportPath Path to Request.php file
     * @param string $wsdlPath Path to WSDL file
     * @param string $namespace base Namespace name (eg: FedEx\RateService).
     * @param string $subPackageName Sub package the generated class belongs to (used in DocBlock)
     * @throws \Exception
     */
    public function __construct($exportPath, $wsdlPath, $namespace, $subPackageName)
    {
        if (file_exists($wsdlPath)) {
            $this->wsdlPath = $wsdlPath;
        } else {
            throw new \Exception('path to wsdl file is invalid');
        }

        $this->exportPath = $exportPath;
        $this->namespace = $namespace;
        $this->subPackageName = $subPackageName;

        $this->loadXML();
    }

    /**
     * Run generator
     */
    public function run()
    {
        $this->setupWebServiceUrls();

        $soapClient = new \Soapclient($this->wsdlPath, array('trace' => true));

        $soapFunctions = $soapClient->__getFunctions();

        $requestFunctionDefinitions = array();

        foreach ($soapFunctions as $soapFunctionDescription) {
            $thisDefinition = array();

            $parts = explode(' ', $soapFunctionDescription);

            $functionDefinition = 'public function get' . ucfirst(substr($parts[1], 0, stripos($parts[1], '(')) . 'Reply');

            $thisDefinition['soapFunction'] = substr($parts[1], 0, stripos($parts[1], '('));

            $requestObjectName = substr($parts[1], stripos($parts[1], '(') + 1);
            $thisDefinition['requestObjectName'] = $requestObjectName;

            $arg1Type = 'ComplexType\\' . $requestObjectName;
            $arg1VariableName = '$' . lcfirst($requestObjectName);

            $thisDefinition['arg1VariableName'] = $arg1VariableName;

            $functionDefinition .= "($arg1Type $arg1VariableName)";

            $thisDefinition['functionDefinition'] = $functionDefinition;

            $requestFunctionDefinitions[] = $thisDefinition;
        }

        echo "Writing file: {$this->exportPath}\n";

        $fh = fopen($this->exportPath, 'w');

        $fileBody = $this->getGeneratedFileBody($requestFunctionDefinitions);

        fwrite($fh, $fileBody);
        fclose($fh);
    }

    /**
     * Parses the web service URL from .wdl file
     */
    protected function setupWebServiceUrls()
    {
        $betaUrl = (string) $this->xml->service->port->children($this->xml->getDocNamespaces()['s1'])->address->attributes()->location;
        if (empty($betaUrl)) {
            return;
        }

        $this->testingUrl = $betaUrl;
        $this->productionUrl = str_ireplace('beta', '', $this->testingUrl);
    }

    /**
     * Generate body of class file
     *
     * @param array $requestFunctionDefinitions Array contining the request function details
     * @return string
     */
    protected function getGeneratedFileBody(array $requestFunctionDefinitions)
    {
        $wsdlFileName = basename($this->wsdlPath);

        $requestFunctions = '';

        foreach ($requestFunctionDefinitions as $functionDefinition) {
            $requestFunctions .= <<<TEXT
            
    /**
     * Sends the {$functionDefinition['requestObjectName']} and returns the response
     *
     * @param ComplexType\\{$functionDefinition['requestObjectName']} {$functionDefinition['arg1VariableName']}
     * @return stdClass
     */
    {$functionDefinition['functionDefinition']}
    {
        return \$this->getSoapClient()->{$functionDefinition['soapFunction']}({$functionDefinition['arg1VariableName']}->toArray());
    }

TEXT;
        }


        $fileBody = <<<TEXT
<?php
namespace {$this->namespace};
    
use FedEx\AbstractRequest;

/**
 * Request sends the SOAP call to the FedEx servers and returns the response
 *
 * @author      Jeremy Dunn <jeremy@jsdunn.info>
 * @package     PHP FedEx API wrapper
 * @subpackage  {$this->subPackageName}
 */
class Request extends AbstractRequest
{
    const PRODUCTION_URL = '{$this->productionUrl}';
    const TESTING_URL = '{$this->testingUrl}';

    protected static \$wsdlFileName = '$wsdlFileName';
$requestFunctions
}

TEXT;

        return $fileBody;
    }
}
