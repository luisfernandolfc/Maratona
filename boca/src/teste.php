<?php

require("vendor/autoload.php");

const CLIENT_ID     = 'Dfqxae0dy3iU97f34YOFLm5mi87LHO';
const CLIENT_SECRET = '9l4zTeOUrmXJh7RwaVvYAglGDDPKpx';

const REDIRECT_URI           = 'http://localhost/teste/teste.php';
const AUTHORIZATION_ENDPOINT = 'http://localhost/site/?oauth=authorize';
const TOKEN_ENDPOINT         = 'http://localhost/site/?oauth=token';

$client = new OAuth2\Client(CLIENT_ID, CLIENT_SECRET);

//Pegar O Código de Autenticação no if e o Token de Acesso em um dos else's.
if (!isset($_GET['code']))
{
	$auth_url = $client->getAuthenticationUrl(AUTHORIZATION_ENDPOINT, REDIRECT_URI);
    header('Location: ' . $auth_url);
    die('Redirect');

}
else
{
	$params = array('code' => $_GET['code'], 'redirect_uri' => REDIRECT_URI);
    $response = $client->getAccessToken(TOKEN_ENDPOINT, 'authorization_code', $params);
    echo "<pre>";
    // print_r($response);
    Request($client, $response["result"]["access_token"]);
   
}

//Gerar o token de acesso pelo comando cURL do PHP

/*else
{

 $curl_post_data = array(

 'grant_type' => 'authorization_code',

 'code' => $_GET['code'],

 'redirect uri' => REDIRECT_URI

 );

 $curl = curl_init(TOKEN_ENDPOINT);
 
 curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

 curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

 curl_setopt($curl, CURLOPT_USERPWD, CLIENT_ID.':'.CLIENT_SECRET); //Credenciais do cliente aqui

 curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

 curl_setopt($curl, CURLOPT_POST, true);

 curl_setopt($curl, CURLOPT_POSTFIELDS, $curl_post_data);

 curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // Caso a url seja https e não queira verificar certificado.
 

 $curl_response = curl_exec($curl);

 $response = $curl_response;

 curl_close($curl);


 echo '<br/><pre>';
 print_r($response);

}*/

//Fazer requisições para a api do wordpress
function Request($clientC, $tokenAccess)
{
	$clientC->setAccessToken($tokenAccess);
	$data = $clientC->fetch("http://localhost/site/?json_route=/users/me");
	echo "<pre>";
	var_dump($data);


	/*$name = $data["result"]["roles"];
	print_r($name);*/
	
}



//Comandos sem a utilização do código PHP

// //Gerar Token de Autorização 
//http://localhost/site?oauth=authorize&response_type=code&client_id=Dfqxae0dy3iU97f34YOFLm5mi87LHO&redirect_uri=http://localhost/teste/teste.php

// //Gerar Token de Acesso
// curl -u Dfqxae0dy3iU97f34YOFLm5mi87LHO:9l4zTeOUrmXJh7RwaVvYAglGDDPKpx -L http://localhost/site/?oauth=token -d 'grant_type=authorization_code&code=MUDARAQUI&redirect_uri=http://localhost/teste/teste.php'

// //GET dos Usuários
// curl -u admin:password http://localhost/site/?json_route=/users //Necessita de um plugin do wordpress. Comando: git clone https://github.com/WP-API/Basic-Auth basicAuth

