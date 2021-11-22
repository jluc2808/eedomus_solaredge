

Piloter une installation Photovoltaïque Solaredge

Message par jluc2808 » 22 Nov 2021 12:49
bonjour,

Objectif utiliser l'API Solaredge documentée ici: https://www.solaredge.com/sites/default/files/se_monitoring_api.pdf
sous la forme de plugin pour eedomus.

L'intégration dans eedomus est sous la forme de 21 équipements, permettant d'avoir les valeurs de production, consommation, autoconsommation, achat, vente : instantané, du jour, du mois de l'année, dont 2 équipements d'informations agrégées (textes) qui reprennent les données de production et de consommation.

Le programme central est un script PHP qui fait le lien entre les équipements et l'API solaredge. Il permet de lancer des commandes eedomus en requête formatée pour l'API, puis transforme et stocke le retour JSON en un fichier XML.
Note: Pour éviter de saturer en commande l'API solaredge (qui est limitée en nombre de demande par jour), les retours de requêtes (fichier XML) sont mis en cache, rafraichit toutes les 15 minutes (pas de collecte de l'API solaredge).

Pour chaque équipement les données récupérées sont traitées sous la forme de xpath qui mettent en forme (wh, Kwh, Mwh, %) et calculent les valeurs au bon format.

Les données nécessaires à l'identification et l'authentification du site vis à vis de l'API - siteId et Api_key - doivent être récupérée directement sur le site Solaredge dans la partie administration et copiée dans les VAR1 (siteId) et VAR2 (API_key) des équipements. Cette étape est suffisamment simple, pour être faite manuellement.

les commandes sont toutes sous la forme :
   http://localhost/script/?exec=solaredge.php&function=consommation_jour&site=[VAR1]&api_key=[VAR2]

la liste des commandes depuis eedomus et les données récupérées:
- overview (production de l'année, production du mois, production du jour, production instantanée)
- consommation_jour (production du jour, consommation du jour, autoconsommation du jour, achat du jour, vente du jour)
- autoconsommation_jour (idem consommation jour)
- consommation_instant (production du dernier quart d'heure, consommation du dernier quart d'heure, autoconsommation du dernier quart d'heure, achat du dernier quart d'heure, vente du dernier quart d'heure)
- autoconsommation_instant (idem consommation instant)
- info_mois (production du mois, consommation du mois, autoconsommation du mois, achat du mois, vente du mois)
- info_an (production de l'année, consommation de l'année, autoconsommation de l'année, achat de l'année, vente de l'année)

la liste des équipements: 
- production instantanée Wh
- production jour Kwh
- production mois Mwh
- production an Mwh
- consommation instantanée KWh
- consommation jour Kwh
- consommation mois Mwh
- consommation an Mwh
- autoconsommation jour Wh
- autoconsommation mois Kwh
- autoconsommation jour Mwh
- achat / vente jour Kwh
- % autoconsommation jour
- % autoconsommation mois
- % autoconsommation an 
- % autousage jour
- % autousage mois
- % autousage an 
- heure de dernière interrogation
- dernière interrogation production (agrégat des données sous forme texte)
- dernière interrogation consommation (agrégat des données sous forme texte)

lien vers le forum eedomus: https://forum.eedomus.com/viewtopic.php?f=12&t=11078
