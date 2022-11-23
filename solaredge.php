<?php
/** ****************************************************************************
* JL Berno eedomus Script solaredge 
********************************************************************************
* Plugin version : 1.2
* Author : JL BERNO
* solaredge API : janvier 2019 - https://www.solaredge.com/sites/default/files/se_monitoring_api.pdf
*******************************************************************************/

/** Utile en cours de dev uniquement */
// $eedomusScriptsEmulatorDatasetPath = "eedomusScriptsEmulator_dataset.json";
// require_once ("eedomusScriptsEmulator.php");

/** version 1.0:
	développement de base
	version 1.1:
	ajout d'un argument nocache pour forcer la requête 
	ajout des requêtes pour les conso, autoconso du mois et année
	la liste des requêtes possibles depuis eedomus et les données récupérées:
	- details (informations générales sur le site)
	- overview  (production de l'année, production du mois, production du jour, production instantanée)
	- consommation_jour (production du jour, consommation du jour, autoconsommation du jour, achat du jour, vente du jour)
	- autoconsommation_jour (non utilisé, idem consommation jour)
	- consommation_instant (production du dernier quart d'heure, consommation du dernier quart d'heure, autoconsommation du dernier quart d'heure, achat du dernier quart d'heure, vente du dernier quart d'heure)
	- autoconsommation_instant (non utilisé, idem consommation instant) 
	- info_mois (production du mois, consommation du mois, autoconsommation du mois, achat du mois, vente du mois)
	- info_an (production de l'année, consommation de l'année, autoconsommation de l'année, achat de l'année, vente de l'année)
	version 1.2:
	ajout de la requête details pour récupérer toutes les infos du site 
	ajout de la valeur 0 par défaut à la consommation si pas de valeur
	paramétrage de la durée du cache en fonction de la requête
*/	
/** Initialisation de la réponse */
$response = null;

/** Lecture de la fonction */
$function = getArg('function');

/** Lecture des infos site et api_key */
$site = getArg('site');
$api_key = getArg('api_key');
$unit = "";

/** force la requête par défaut pas de forçage */
$nocache = true;
$nocache = getArg('nocache', $mandatory = false, $default = true);

/** initialisation de la durée du cache */
$cache_duration = 15; 	// 15 minutes par défaut

/** ****************************************************************************
* Routeur de fonction
*/
switch($function) {
	case 'details':				// info du site
		$cache_duration = 1440; // minutes
		sdk_callAPI('details', $site, $api_key, "", $unit, $cache_duration, $nocache);
		break;
	case 'overview':			// données de production
		$cache_duration = 15; // minutes
		sdk_callAPI('overview', $site, $api_key, "", $unit, $cache_duration, $nocache);
		break;
	case 'consommation_jour':
	case 'autoconsommation_jour':
		$cache_duration = 15; // minutes
		// calcul des bornes de récupération des données 
		$tms = time() - 25 * 60;				// on neutralise les 25 premières minutes de la journée pour être certains d'avoir des infos 
		$today = date('Y-m-d', $tms);
		$unit = "DAY";
		// on fixe arbitrairement les heures Ã Â  0h et 23h59 pour avoir toute la journée 
		$params = "&startTime=".$today." 00:00:00&endTime=".$today." 23:59:59";	
		sdk_callAPI ('energyDetails', $site, $api_key, $params, $unit, $cache_duration, $nocache);
		break;
	case 'consommation_instant':
	case 'autoconsommation_instant':
		$cache_duration = 15; // minutes
		$unit = "QUARTER_OF_AN_HOUR";
		// calcul des bornes de récupération des données 
		$tms = time();
		$tms_10min_before = $tms - (10 * 60);				// 10 min avant maintenant 
		$now = date('Y-m-d H:i:s', $tms);					// transformation en date formatÃƒÂ©e 
		$now_10min_before = date('Y-m-d H:i:s', $tms_10min_before);
		// si 10min avant on change de jour alors on mettra la date du jour à 00:00:00
		if (date('Y-m-d', $tms_10min_before) != date('Y-m-d', $tms))	{$now_10min_before = date('Y-m-d', $tms).' 00:00:00';}
			
		$params = "&timeUnit=".$unit."&startTime=".$now_10min_before."&endTime=".$now;
		sdk_callAPI ('powerDetails', $site, $api_key, $params, $unit, $cache_duration, $nocache);
		break;	
	case 'info_mois':
		$cache_duration = 1440; // minutes
		$unit = "MONTH";
		// calcul des bornes de récupération des données 
		$tms = time();
		$now = date('Y-m-d', $tms);					// transformation en date formatée 
		//  on mettra la date du jour à 00:00:00 pour début et 23:59:00 pour la fin
		$params = "&timeUnit=".$unit."&startTime=".$now." 00:00:00"."&endTime=".$now." 23:59:00";
		sdk_callAPI ('energyDetails', $site, $api_key, $params, $unit, $cache_duration, $nocache);
		break;
	case 'info_an':
		$cache_duration = 1440; // minutes
		$unit = "YEAR";
		// calcul des bornes de récupération des données 
		$tms = time();
		$now = date('Y-m-d', $tms);					// transformation en date formatée 
		//  on mettra la date du jour à 00:00:00 pour début et 23:59:00 pour la fin
		$params = "&timeUnit=".$unit."&startTime=".$now." 00:00:00"."&endTime=".$now." 23:59:00";
		sdk_callAPI ('energyDetails', $site, $api_key, $params, $unit, $cache_duration, $nocache);
		break;
	default:
		$response = '{ "success" : "false", "message" : "Unknown function '.$function.' " }';
}


/** ****************************************************************************
* Appeler l'API de solaredge
*
* @param $api_call la commande cible
* @param $site le site appellé
* @param $api_key la clé api
* @param $params complément Ã  envoyer sur la cible
* @return le résulat de l'appel au format Json
*/
function sdk_callAPI($api_call, $site, $api_key, $params, $unit, $cache_duration, $nocache) {
	global $xml_response;
	global $response;
	$time_last_response = 0;
	$time_last_response = loadVariable('time_last_response'.$api_call.$unit);	
	$url = "https://monitoringapi.solaredge.com/site/".$site."/".$api_call."?api_key=".$api_key.$params;
	$lfcr   = array("\r\n", "\n", "\r", "\n\r");
		// on contrÃ´le si la même requête n'a pas déjà à été executée depuis 15 minutes 
		if (((time() - $time_last_response) / 60 < $cache_duration) & $nocache)
			{$xml_response = loadVariable('cached_response_'.$api_call.$unit);
			// changement de la valeur de balise du cache
			$xml_response = str_replace('<root><cached>0</cached>', '<root><cached>1</cached>', $xml_response);
			$xml_response = str_replace($lfcr, '', $xml_response);			// suppression de tous les LF et CR
			$xml_response = str_replace('</date></meters>', '</date><value>0</value></meters>', $xml_response);  // ajout de value=0 si pas de value
			} 	// on est inférieur à  15 minutes alors on utilise le cache 	
		else {	
				// on est au delà des 15 minutes alors on envoi la requête au serveur distant et on stocke dans le cache	
				sdk_header('text/xml');
				$response = httpQuery($url);
				$xml_response = jsonToXML($response);
				// ajout d'une balise pour le cache 
				$xml_response = str_replace('<root>', '<root><cached>0</cached>', $xml_response);
				// on ajoute une valeur si pas de retour de type value  
				$xml_response = str_replace($lfcr, '', $xml_response);		// suppression de tous les LF et CR 
				$xml_response = str_replace('</date></meters>', '</date><value>0</value></meters>', $xml_response); // ajout de value=0 si pas de value
				if ($api_call == 'powerDetails' & strpos($xml_response, '<type>Consumption</type>') === false)  // ajout d'une valeur à la consommation courante si pas de valeur
					{$xml_response = str_replace('</unit><meters>', '</unit><meters><meters><type>Consumption</type><values><meters><date>'.date('Y-d-m H:i:s',$time_last_response).'</date><value>0</value></meters></values></meters>', $xml_response);}
				saveVariable('cached_response_'.$api_call.$unit, $xml_response);
				saveVariable('time_last_response'.$api_call.$unit, time());

		}

	if ($xml_response != '')
		{return $xml_response;}
	else {saveVariable('time_last_response'.$api_call.$unit, 0);}
}

/** ****************************************************************************
* Fin du script, affichage du résultat au format XML
*/
sdk_header('text/xml');
echo $xml_response;
?>