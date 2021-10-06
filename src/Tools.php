<?php
namespace NFService\HubDoDev;

use Exception;

/**
 * Classe Tools
 *
 * Classe responsável pela comunicação com a API Tecnospeed
 *
 * @category  NFService
 * @package   NFService\HubDoDev\Tools
 * @author    Diego Almeida <diego.feres82 at gmail dot com>
 * @copyright 2020 NFSERVICE
 * @license   https://opensource.org/licenses/MIT MIT
 */
class Tools
{
    private $decode = true;
    /**
     * Variável responsável por armazenar os dados a serem utilizados para comunicação com a API
     *
     * @var array
     */
    private $config = [
        'token' => '',
        'debug' => true
    ];

    /**
     * Função responsável por setar o token a ser utilizado na comunicação com a API
     *
     * @param string $token Token para comunicação
     *
     * @access public
     * @return void
     */
    public function setToken(string $token)
    {
        $this->config['token'] = $token;
    }

    /**
     * Função responsável por definir se está em modo de debug ou não a comunicação com a API
     * Utilizado para pegar informações da requisição
     *
     * @param bool $isDebug Boleano para definir se é produção ou não
     *
     * @access public
     * @return void
     */
    public function setDebug(bool $isDebug) : void
    {
        $this->config['debug'] = $isDebug;
    }

    /**
     * Retorna os cabeçalhos padrão para comunicação com a API
     *
     * @access private
     * @return array
     */
    private function getDefaultHeaders() :array
    {
        return [
            'Content-Type: application/json'
        ];
    }

    /**
     * Retorna os parametros query padrão para comunicação com a API
     *
     * @access private
     * @return array
     */
    private function getDefaultQueryParams() :array
    {
        return [
            [
                'name' => 'token',
                'value' => $this->config['token']
            ]
        ];
    }

    /**
     * Realiza consulta de CNPJ
     *
     * @param string $cnpj CNPJ a ser consultado
     * @param array $params Parametros adicionais para a consulta
     * @return array
     */
    public function consultaCNPJ(string $cnpj, array $params = []) :array
    {
        try {
            $params = array_filter($params, function($item) {
                return $item['name'] !== 'cnpj';
            }, ARRAY_FILTER_USE_BOTH);

            $params[] = [
                'name' => 'cnpj',
                'value' => $cnpj
            ];

            $data = $this->get('cnpj', $params);

            if ($data['body']['status']) {
                if (!empty($data['body']['result']['quadro_de_socios'])) {
                    if (isset($data['body']['result']['quadro_de_socios'][0]['informacoes'])) {
                        unset($data['body']['result']['quadro_de_socios'][0]);
                    }
                }
                return $data['body']['result'];
            }

            if (isset($data['body']['erro'])) {
                throw new Exception($data['body']['erro'], 1);
            }

            if (isset($data['body']['message'])) {
                throw new Exception($data['body']['message'], 1);
            }

            throw new Exception("Ocorreu um erro interno ao tentar consulta o CNPJ", 1);
        } catch (Exception $error) {
            throw new Exception($error, 1);
        }
    }

    /**
     * Realiza consulta de CEP
     *
     * @param string $cep CEP a ser consultado
     * @param array $params Parametros adicionais para a consulta
     * @return array
     */
    public function consultaCEP(string $cep, array $params = []) :array
    {
        try {
            $params = array_filter($params, function($item) {
                return $item['name'] !== 'cep';
            }, ARRAY_FILTER_USE_BOTH);

            $params[] = [
                'name' => 'cep',
                'value' => $cep
            ];

            $data = $this->get('cep3', $params);

            if (isset($data['body']['status']) && $data['body']['status']) {
                return $data['body']['result'];
            }

            if (isset($data['body']['erro'])) {
                throw new Exception($data['body']['erro'], 1);
            }

            if (isset($data['body']['message'])) {
                throw new Exception($data['body']['message'], 1);
            }

            throw new Exception("Ocorreu um erro interno ao tentar consulta o CEP", 1);
        } catch (Exception $error) {
            throw new Exception($error, 1);
        }
    }

    /**
     * Execute a GET Request
     *
     * @param string $path
     * @param array $params
     * @param array $headers Cabeçalhos adicionais para requisição
     * @return array
     */
    private function get(string $path, array $params = [], array $headers = []) :array
    {
        $opts = [
            CURLOPT_HTTPHEADER => $this->getDefaultHeaders()
        ];

        if (!empty($headers)) {
            $opts[CURLOPT_HTTPHEADER] = array_merge($opts[CURLOPT_HTTPHEADER], $headers);
        }

        $params = array_merge($params, $this->getDefaultQueryParams());

        $exec = $this->execute($path, $opts, $params);

        return $exec;
    }

    /**
     * Execute a OPTION Request
     *
     * @param string $path
     * @param array $params
     * @param array $headers Cabeçalhos adicionais para requisição
     * @return array
     */
    private function options(string $path, array $params = [], array $headers = []) :array
    {
        $opts = [
            CURLOPT_CUSTOMREQUEST => "OPTIONS"
        ];

        if (!empty($headers)) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }

        $params = array_merge($params, $this->getDefaultQueryParams());

        $exec = $this->execute($path, $opts, $params);

        return $exec;
    }

    /**
     * Função responsável por realizar a requisição e devolver os dados
     *
     * @param string $path Rota a ser acessada
     * @param array $opts Opções do CURL
     * @param array $params Parametros query a serem passados para requisição
     *
     * @access private
     * @return array
     */
    private function execute(string $path, array $opts = [], array $params = []) :array
    {
        if (!preg_match("/^\//", $path)) {
            $path = '/' . $path;
        }

        $url = "https://ws.hubdodesenvolvedor.com.br/v2$path";

        $curlC = curl_init();

        if (!empty($opts)) {
            curl_setopt_array($curlC, $opts);
        }

        if (!empty($params)) {
            $paramsJoined = [];

            foreach ($params as $param) {
                if (isset($param['name']) && !empty($param['name']) && isset($param['value']) && !empty($param['value'])) {
                    $paramsJoined[] = urlencode($param['name'])."=".urlencode($param['value']);
                }
            }

            if (!empty($paramsJoined)) {
                $params = '?'.implode('&', $paramsJoined);
                $url = $url.$params;
            }
        }

        curl_setopt($curlC, CURLOPT_URL, $url);
        curl_setopt($curlC, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlC, CURLOPT_HEADER, false);
        curl_setopt($curlC, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curlC, CURLOPT_CONNECTTIMEOUT, 180);
        curl_setopt($curlC, CURLOPT_TIMEOUT, 180);
        if (!empty($dados)) {
            curl_setopt($curlC, CURLOPT_POSTFIELDS, json_encode($dados));
        }
        $retorno = curl_exec($curlC);
        $info = curl_getinfo($curlC);
        $return["body"] = json_decode($retorno, true);
        $return["httpCode"] = curl_getinfo($curlC, CURLINFO_HTTP_CODE);
        if ($this->config['debug']) {
            $return['info'] = curl_getinfo($curlC);
        }
        curl_close($curlC);

        return $return;
    }
}
