<?php
////////////////////////////////////////////////////////////////////////////////
//BOCA Online Contest Administrator
//    Copyright (C) 2003-2012 by BOCA Development Team (bocasystem@gmail.com)
//
//    This program is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 3 of the License, or
//    (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//    You should have received a copy of the GNU General Public License
//    along with this program.  If not, see <http://www.gnu.org/licenses/>.
////////////////////////////////////////////////////////////////////////////////
// Last modified 05/aug/2012 by cassio@ime.usp.br


ob_start();
header ("Expires: " . gmdate("D, d M Y H:i:s") . " GMT");
header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header ("Cache-Control: no-cache, must-revalidate");
header ("Pragma: no-cache");
header ("Content-Type: text/html; charset=utf-8");
session_start();
$_SESSION["loc"] = dirname($_SERVER['PHP_SELF']);
if($_SESSION["loc"]=="/") $_SESSION["loc"] = "";
$_SESSION["locr"] = dirname(__FILE__);
if($_SESSION["locr"]=="/") $_SESSION["locr"] = "";

if(isset($_GET["getsessionid"])) 
{
	echo session_id();
	exit;
}

ob_end_flush();

require_once("globals.php");
require_once("db.php");

require_once('version.php');

//Editar team/header.php e admin/header.php para que ao clicar em logout retornem ao index com o endpoint ?logout=true.
if (isset($_GET["logout"])) {
	if (ValidSession())
		DBLogOut($_SESSION["usertable"]["contestnumber"], 
				 $_SESSION["usertable"]["usersitenumber"], $_SESSION["usertable"]["usernumber"],
				 $_SESSION["usertable"]["username"]=='admin');
	session_unset();
	session_destroy();
	header('Location: ' . "index.php");
	die('Redirect');
}

?>
<title>BOCA Online Contest Administrator <?php echo $BOCAVERSION; ?> - Login</title>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel=stylesheet href="Css.php" type="text/css">
<script language="JavaScript" src="sha256.js"></script>
<script language="JavaScript">

function computeHASH()
{
	var userHASH, passHASH;
	userHASH = document.form1.name.value;
	passHASH = js_myhash(js_myhash(document.form1.password.value)+'<?php echo session_id(); ?>');
	document.form1.name.value = '';
	document.form1.password.value = '                                                                                 ';
	document.location = 'index.php?name='+userHASH+'&password='+passHASH;
}
</script>
<?php

require("vendor/autoload.php");

const CLIENT_ID     = 'Dfqxae0dy3iU97f34YOFLm5mi87LHO';
const CLIENT_SECRET = '9l4zTeOUrmXJh7RwaVvYAglGDDPKpx';

const REDIRECT_URI           = 'http://localhost/boca/src/index.php';
const AUTHORIZATION_ENDPOINT = 'http://localhost/site/?oauth=authorize';
const TOKEN_ENDPOINT         = 'http://localhost/site/?oauth=token';

$client = new OAuth2\Client(CLIENT_ID, CLIENT_SECRET);

//Checar se foi pressionado o botão de entrar. E qual usuário foi selecionado.
if (isset($_POST['group']))
{	
	$userName = $_POST['group'];		
	
	//Logar usuário caso existe, caso contrário, criar um novo.
	userLogin($userName);			
}

//caso pressione o botão de logar com outra conta, redirecionar para autenticação Wordpress.
elseif (isset($_GET['other'])) 
{
	$redirect_uri = "http://localhost/site/wp-login.php?redirect_to=http://localhost/boca/src/index.php";
	header('Location: ' . $redirect_uri);
	die('Redirect');	
}

//Autenticação OAUTH 2 com o wordpress. Se foi definido o códio de acesso então pegar o Token de acesso e pegar os dados do usuário.
//senão, pegar o código de acesso por meio da função authentication().
elseif (isset($_GET['code']))
{
    
	$params = array('code' => $_GET['code'], 'redirect_uri' => REDIRECT_URI);
    $response = $client->getAccessToken(TOKEN_ENDPOINT, 'authorization_code', $params);
    $data = Request($client, $response["result"]["access_token"]);

    //Insere os dados do usuário do wordpress no array da seção.
    $_SESSION["data"] = $data;
    $name = $data["result"]["username"];
    
    ob_end_clean();   

	//Quando recarrega a página após ter gerado o código é perdido algumas informações.
    if ($name == NULL)
    	ForceLoad("index.php");
     
}
elseif (!isset($_GET['code']))
{
	Authentication();
}

function Authentication()
{
	$client = new OAuth2\Client(CLIENT_ID, CLIENT_SECRET);
	$auth_url = $client->getAuthenticationUrl(AUTHORIZATION_ENDPOINT, REDIRECT_URI);
	header('Location: ' . $auth_url);
	die('Redirect');	
}

function userLogin($userName)
{

	//Conectar no banco e procurar pelo nome do usuário
	// Se o usuário não existir no banco do Boca, cria-lo.
	// Logo após, logar o usuário.
	// A senha é a Hash da concatenação entre a Hash da senha e o Id da seção.

	if ($userName == "admin")
		//Pode causar um problema, caso exista um usuário sem admin no banco boca e que deletou sua conta no
		//banco do wordpress. Posteriormente foi criada uma conta com o mesmo username para admin no wordpress.
		$sql = "select * from usertable where username='" . $_SESSION["data"]["result"]["username"] . "'";	
	else
		$sql = "select * from usertable where username='" . $userName . "'";

	$conn = DBConnect();
	$result = DB_pg_exec ($conn, $sql);
	$user = pg_fetch_array ($result, "0", PGSQL_ASSOC);
	$contestNumber = DBGetActiveContest();
	// $user = DBGetRow($sql, 0, null, "DBLogIn(get user)");
	if(!$user) 
	{
		//Carrega as configurações salvas no private/conf.php. OBS: Checar se ao trocar no conf, não ocorrerá erro.
		$cf=globalconf();
		$admpass = myhash($cf["basepass"]);

		//ForceLoad("index.php");
		$param = array();
		$param['site']="1";
		$param['username']= $userName;
		$param['userfull']= $userName;	
		$param['pass']=myhash($admpass);
		$param['usericpcid']="";				
		$param['userdesc']="";
		$param['enabled']="t";
		$param['multilogin']="t";
		$param['changepass']=true;
		$param['permitip']=NULL;
		$param['contest']=$contestNumber["contestnumber"];

		//Caso seja usuário com privilégios de admin e ainda não exista no banco do boca.
		if ($userName == "admin" || $userName == "system" || $userName == "judge" || $userName == "score")
		{
			
			
			$param['userfull']= "";			

			if ($userName == "admin")
			{					
				$param['username']= $_SESSION["data"]["result"]["username"];
				$param['userfull']= $_SESSION["data"]["result"]["username"] . " - Admin";	
				$param['usernumber']=$_SESSION["data"]["result"]["ID"]+1000;
				$param['type']="admin";
			}
			elseif ($userName == "judge")
			{					
				$param['username']= "judge";
				$param['userfull']= $userName;	
				$param['usernumber']="2";
				$param['type']="judge";
			}
			elseif ($userName == "score")
			{					
				$param['username']= "score";
				$param['userfull']= $userName;	
				$param['usernumber']="3";
				$param['type']="score";
			}							
			else
			{
				$param['username']= "system";
				$param['usernumber']="1";
				$param['userfull']= "System";
				//alterar fcontest.php - função DBNewUser() para aceitar gravar system.
				$param['type']="system";
				$param['contest']="0";
			}			
		}

		//Caso sejam nomes dos usuários que não são grupos e ainda não exista no banco do boca.
		elseif ($userName == $_SESSION["data"]["result"]["username"])
		{					
			$param['type']="team";
			$param['usernumber']=(int)$_SESSION["data"]["result"]["ID"]+2000;
			$param['userfull']= $_SESSION["data"]["result"]["name"];
		}

		//Caso sejam nomes dos grupos e ainda não exista no banco do boca.
		else
		{	
					
			$value = groupCount($userName);
			$param['type']="team";
			$param['usernumber']=$value;
			$param['userdesc']="Grupo";				
		}
		//Criar novo usuário.Funcao em fcontest.php
		DBNewUser($param);
	}
	login($userName);	
}

function login($userName)
{
	//Carrega as configurações salvas no private/conf.php. OBS: Checar se ao trocar no conf, não ocorrerá erro.
	$cf=globalconf();
	$admPass = myhash($cf["basepass"]);
	$hashPass = myhash(myhash($admPass) . session_id());

	if ($userName == "admin")
	{		
		//loga o usuário no boca e guarda os dados do usuário na $_SESSION["usertable"].
		$usertable = DBLogIn($_SESSION["data"]["result"]["username"], $hashPass);
	}	
	//Se forem usuários comuns, grupos ou o sistema, então logar no boca.
	else
	{		
		$usertable = DBLogIn($userName, $hashPass);
	}
	redirect();
		
}

function redirect()
{	
	// Redireciona para a página de acordo com o tipo do usuário (team, admin, system, etc).
	header('Location: ' . $_SESSION["usertable"]["usertype"] . "/index.php");
	die('Redirect');
}

function groupCount($groupName)
{
	$value = 0;

	while (count($_SESSION["data"]["result"]["grupos"]) > $userGroups )
		{
			if ($groupName == $_SESSION["data"]["result"]["grupos"][$userGroups]["slug"])
			{
				$value = (int)$_SESSION["data"]["result"]["grupos"][$userGroups]["term_id"];
				$value = $value + 3000;
			}

			$userGroups = $userGroups+1;
		}

	return (string)$value;
}
function Request($clientC, $tokenAccess)
{
	$clientC->setAccessToken($tokenAccess);
	$data = $clientC->fetch("http://localhost/site/?json_route=/users/me");
	
	return $data;
}

function closePreviousSection()
{
	DBLogOut($_SESSION["usertable"]["contestnumber"], 
			 $_SESSION["usertable"]["usersitenumber"], $_SESSION["usertable"]["usernumber"],
			 $_SESSION["usertable"]["username"]=='admin');

	session_unset();
	session_destroy();
	session_start();
	$_SESSION["loc"] = dirname($_SERVER['PHP_SELF']);
	if($_SESSION["loc"]=="/") $_SESSION["loc"] = "";
	$_SESSION["locr"] = dirname(__FILE__);
	if($_SESSION["locr"]=="/") $_SESSION["locr"] = "";
}
?>
</head>
<body onload="document.form1.name.focus()">
	<table width="100%" height="100%" border="0">
  		<tr align="center" valign="middle"> 
    		<td>
    			<form action="index.php" method="post">
    				<div align="center">
					<select name = "group" style="font-size:1.5em; height:50px; width:380px">
						<?php
						$userGroups = 0;
						if ($_SESSION["data"]["result"]["roles"][0] == "administrator")
						{
							echo("<option value='admin'>" . $_SESSION["data"]["result"]["username"]." - Admin</option>");
							echo("<option value='system'>System</option>");
							echo("<option value='judge'>Judge</option>");
							echo("<option value='score'>Score</option>");
							while (count($_SESSION["data"]["result"]["grupos"]) > $userGroups )
							{
								echo("<option value='".$_SESSION["data"]["result"]["grupos"][$userGroups]["slug"]."'>".$_SESSION["data"]["result"]["grupos"][$userGroups]["slug"]."</option>");
								$userGroups = $userGroups+1;
							}
						}
						else
						{
							echo("<option value='".$_SESSION["data"]["result"]["username"]."'>".$_SESSION["data"]["result"]["username"]."</option>");
							while (count($_SESSION["data"]["result"]["grupos"]) > $userGroups )
							{
								echo("<option value='".$_SESSION["data"]["result"]["grupos"][$userGroups]["slug"]."'>".$_SESSION["data"]["result"]["grupos"][$userGroups]["slug"]."</option>");
								$userGroups = $userGroups+1;
							}
						}						
						?>						
					</select>
					<div align="center"> 
					<input type="submit" name="submit" value="Entrar" style="font-size:1.5em; height:50px; width:380px"/>   
				</form>	      			

				<form action="index.php" method="get">
  					<div align="center">   
     				<input type="submit" name="other" value="Logar Com Outra Conta"style="font-size:1.5em; height:50px; width:380px">
      			</form>

    		</td>
  		</tr>
	</table>
</body>


<?php include('footnote.php'); ?>

