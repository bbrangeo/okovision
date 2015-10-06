<?PHP
/*****************************************************
* Projet : Okovision - Supervision chaudiere OeKofen
* Auteur : Stawen Dronek
* Utilisation commerciale interdite sans mon accord
******************************************************/

class rendu extends connectDb{

	public function __construct() {
		parent::__construct();
	}
	
	public function __destruct() {
		parent::__destruct();
	}
	
	private function sendResponse($t){
        header("Content-type: text/json; charset=utf-8");
		echo $t;
    }
	
	public function getGrapheData($id,$jour){
		
		$q = "select capteur.name as name, capteur.id as id, asso.correction_effect as coeff from oko_asso_capteur_graphe as asso ".
	            "LEFT JOIN oko_capteur as capteur ON capteur.id = asso.oko_capteur_id  ".
	            "WHERE asso.oko_graphe_id=".$id." ORDER BY asso.position";
	            
	    $this->log->debug("Class ".__CLASS__." | ".__FUNCTION__." | ".$q);
	   
	    $result = $this->query($q);
		
		$resultat = "";
		$cap = new capteur();
		
    	while($c = $result->fetch_object()){
			
			$capteur = $cap->get($c->id);
			
		    $q = "SELECT jour, DATE_FORMAT(heure,'%H:%i:%s'), round((col_".$capteur['column_oko']." * ".$c->coeff."),2) as value FROM oko_historique_full "
			     ."WHERE jour ='".$jour."'";
			        
			$this->log->debug("Class ".__CLASS__." | ".__FUNCTION__." | ".$c->name." | ".$q);
			
			$resultat .= '{ "name": "'.$c->name.'",';
			$resultat .= '"data": '.$this->getDataWithTime($q);
			$resultat .= '},';
		}
		
		//on retire la derniere virgule qui ne sert à rien
		$resultat = substr($resultat,0,strlen($resultat)-1);
		
		$r =  '{ "grapheData": ['.$resultat.']'
	    	  .'}';
	 	
	 	$this->sendResponse($r);
	 	
	}
	

	/****
	Fonction pour recuperer et structurer toutes les data associées au timestamp
	***/
	private function getDataWithTime($q){	
		
		$result = $this->query($q);
		$data = null;
	
		while($r = $result->fetch_row() ) {
			
			$date = new DateTime($r[0]." ".$r[1], new DateTimeZone('Europe/Paris'));
			$utc = ($date->getTimestamp() + $date->getOffset()) * 1000;	
			$data .= "[".$utc.",".$r[2]."],";
			
		}
		
		$data = substr($data,0,strlen($data)-1);
		
		return '['.$data.']';
	}
	

	public function getIndicByDay($jour){
		
		$c 		= $this->getConsoByday($jour);
		$min 	= $this->getTcMinByDay($jour);
		$max 	= $this->getTcMaxByDay($jour);
		
		$this->sendResponse(
							json_encode(	array("consoPellet" => $c->consoPellet, 
											 "tcExtMax" => $max->tcExtMax, 
											 "tcExtMin" => $min->tcExtMin 
											)
										, JSON_NUMERIC_CHECK
									)
							);
		
	}
	
	public function getConsoByday($jour){
		$coeff = POIDS_PELLET_PAR_MINUTE/1000;
		$c = new capteur();
		$capteur_vis = $c->getByType('tps_vis');
		$capteur_vis_pause = $c->getByType('tps_vis_pause');
		
		$q = "select round (sum((1/(a.col_".$capteur_vis['column_oko']." + a.col_".$capteur_vis_pause['column_oko'].")) * a.col_".$capteur_vis['column_oko'].")*(".$coeff."),2) as consoPellet from oko_historique_full as a "
				."WHERE a.jour = '".$jour."';";
		
		$this->log->debug("Class ".__CLASS__." | ".__FUNCTION__." | ".$q); 
		
		$result = $this->query($q);
		
		return $result->fetch_object();
	}
	
	public function getTcMaxByDay($jour){
		$c = new capteur();
		$capteur = $c->getByType('tc_ext');
		
		$q = "SELECT round(max(a.col_".$capteur['column_oko']."),2) as tcExtMax FROM oko_historique_full as a "
				."WHERE a.jour = '".$jour."';";
		
		$this->log->debug("Class ".__CLASS__." | ".__FUNCTION__." | ".$q); 
		
		$result = $this->query($q);
		
		return $result->fetch_object();		
				
	}
	
	public function getTcMinByDay($jour){
		$c = new capteur();
		$capteur = $c->getByType('tc_ext');
		
		$q = "SELECT round(min(a.col_".$capteur['column_oko']."),2) as tcExtMin FROM oko_historique_full as a "
				."WHERE a.jour = '".$jour."';";
		
		$this->log->debug("Class ".__CLASS__." | ".__FUNCTION__." | ".$q); 
		
		$result = $this->query($q);
		
		return $result->fetch_object();		
				
	}
	
	public function getDju($tcMax,$tcMin){
		$tcMoy = ($tcMax + $tcMin) / 2;
		
		if(TC_REF <=  $tcMoy ){
			return 0;
		}else{
			return round( TC_REF - $tcMoy ,2);
		}
		
	}
	
	public function getNbCycleByDay($jour){
		$c = new capteur();
		$capteur = $c->getByType('startCycle');
		
		$q = "SELECT sum(a.col_".$capteur['column_oko'].") as nbCycle FROM oko_historique_full as a "
				."WHERE a.jour = '".$jour."';";
		
		$this->log->debug("Class ".__CLASS__." | ".__FUNCTION__." | ".$q); 
		
		$result = $this->query($q);
		
		return $result->fetch_object();	
	}
	
	public function getIndicByMonth($month, $year){
		
		$q = "SELECT max(Tc_ext_max) as tcExtMax, min(Tc_ext_min) as tcExtMin, ".
				"sum(conso_kg) as consoPellet, sum(dju) as dju, sum(nb_cycle) as nbCycle ".
				"FROM oko_resume_day ".
				"WHERE MONTH(oko_resume_day.jour) = ".$month." AND ".
				"YEAR(oko_resume_day.jour) = ".$year;
		
		
		$this->log->debug("Class ".__CLASS__." | ".__FUNCTION__." | ".$q); 
		
		$result = $this->query($q);
		$r = $result->fetch_object();
		
		$this->sendResponse( json_encode( 	array( 	"tcExtMax" => $r->tcExtMax,
												"tcExtMin" => $r->tcExtMin,
												"consoPellet" => $r->consoPellet,
												"dju" => $r->dju,
												"nbCycle" => $r->nbCycle
											)
											, JSON_NUMERIC_CHECK ) );
		
		
	}
	
	public function getHistoByMonth($month,$year){
		$categorie = array( session::getLabel('lang.text.graphe.label.tcmax') => 'tc_ext_max',
							session::getLabel('lang.text.graphe.label.tcmin') => 'tc_ext_min',
							session::getLabel('lang.text.graphe.label.conso') => 'conso_kg',
							session::getLabel('lang.text.graphe.label.dju') => 'dju',
							session::getLabel('lang.text.graphe.label.nbcycle') => 'nb_cycle'
						);
						
		$where ='FROM oko_resume_day '
				.'RIGHT JOIN oko_dateref ON oko_resume_day.jour = oko_dateref.jour '
				.'WHERE MONTH(oko_dateref.jour) = '.$month.' AND ' 
				.'YEAR(oko_dateref.jour) = '.$year.' '
				.'ORDER BY oko_dateref.jour ASC	';
		
		$resultat = array();
		
		foreach ($categorie as $label => $colonneSql){
			$q = "SELECT ".$colonneSql." ".$where;
			
			$this->log->debug("Class ".__CLASS__." | ".__FUNCTION__." | ".$q); 
			
			$result = $this->query($q);
			
			$data = array();
			while($r = $result->fetch_row() ) {
				$data[] = $r[0];
			}
			
			array_push($resultat,array( 'name' => $label,
										'data' => $data
									)
						);
		}
		
		$this->sendResponse( json_encode( $resultat ,JSON_NUMERIC_CHECK) );
		
		
	}
	
	public function getTotalSaison($idSaison){
		
		$q = "SELECT max(Tc_ext_max) as tcExtMax, min(Tc_ext_min) as tcExtMin, ".
				"sum(conso_kg) as consoPellet, sum(dju) as dju, sum(nb_cycle) as nbCycle ".
				"FROM oko_resume_day, oko_saisons ".
				"WHERE oko_saisons.id = ".$idSaison." ".
				"AND oko_resume_day.jour BETWEEN oko_saisons.date_debut AND oko_saisons.date_fin ;";
				
		
		
		$this->log->debug("Class ".__CLASS__." | ".__FUNCTION__." | ".$q); 
		
		$result = $this->query($q);
		$r = $result->fetch_object();
		
		$this->sendResponse( json_encode( 	array("tcExtMax" => $r->tcExtMax,
												"tcExtMin" => $r->tcExtMin,
												"consoPellet" => $r->consoPellet,
												"dju" => $r->dju,
												"nbCycle" => $r->nbCycle
											)
											, JSON_NUMERIC_CHECK ) );
											
	}
	
	public function getSyntheseSaison($idSaison){
		
		$categorie = array( session::getLabel('lang.text.graphe.label.tcmax') => 'max(Tc_ext_max)',
							session::getLabel('lang.text.graphe.label.tcmin') => 'min(Tc_ext_min)',
							session::getLabel('lang.text.graphe.label.conso') => 'sum(conso_kg)',
							session::getLabel('lang.text.graphe.label.dju') => 'sum(dju)',
							session::getLabel('lang.text.graphe.label.nbcycle') => 'sum(nb_cycle)'
						);
		
		$where = ", DATE_FORMAT(oko_dateref.jour,'%Y-%m-01 00:00:00') FROM oko_saisons, oko_resume_day ".
					"RIGHT JOIN oko_dateref ON oko_dateref.jour = oko_resume_day.jour ".
					"WHERE oko_saisons.id=".$idSaison." AND oko_dateref.jour BETWEEN oko_saisons.date_debut AND oko_saisons.date_fin ".
					"GROUP BY MONTH(oko_dateref.jour) ".
					"ORDER BY YEAR(oko_dateref.jour), MONTH(oko_dateref.jour) ASC;";
				
		$resultat = null;
		
		foreach ($categorie as $label => $colonneSql){
			
			$q = "SELECT ".$colonneSql." ".$where;
			$this->log->debug("Class ".get_called_class()." | getSyntheseSaison | ".$q); 
			
			$result = $this->query($q);
			$data = null;
			
			while($r = $result->fetch_row() ) {
				$date = new DateTime($r[1], new DateTimeZone('Europe/Paris'));
				$utc = ($date->getTimestamp() + $date->getOffset()) * 1000;	
				$data .= "[".$utc.",".(($r[0]<>'')?$r[0]:'null')."],";
			}
			$data = substr($data,0,strlen($data)-1);
			
			$resultat .= '{ "name": "'.$label.'",';
			$resultat .= '"data": ['.$data.']';
			$resultat .= '},';
		
		}
		$resultat = substr($resultat,0,strlen($resultat)-1);
		
		$this->sendResponse( '{ "grapheData": ['.$resultat.']}' );		
	}
	
	public function getSyntheseSaisonTable($idSaison){
		
		$q = "select DATE_FORMAT(oko_dateref.jour,'%m-%Y') as mois, ".
					"IFNULL(sum(oko_resume_day.nb_cycle),'-') as nbCycle, ".
					"IFNULL(sum(oko_resume_day.conso_kg),'-') as conso, ".
					"IFNULL(sum(oko_resume_day.dju),'-') as dju, ".
					"IFNULL(round( ((sum(oko_resume_day.conso_kg) * 1000) / sum(oko_resume_day.dju) / ".SURFACE_HOUSE."),2),'-') as g_dju_m ".
					"FROM oko_saisons, oko_resume_day ".
					"RIGHT JOIN oko_dateref ON oko_dateref.jour = oko_resume_day.jour ".
					"WHERE oko_saisons.id=".$idSaison." AND oko_dateref.jour BETWEEN oko_saisons.date_debut AND oko_saisons.date_fin ".
					"GROUP BY MONTH(oko_dateref.jour) ".
					"ORDER BY YEAR(oko_dateref.jour), MONTH(oko_dateref.jour) ASC;";
					
		$this->log->debug("Class ".__CLASS__." | ".__FUNCTION__." | ".$q); 
		
		$result = $this->query($q);
		
		$data = array();
		while($r = $result->fetch_object() ) {
			$data[] = $r;
		}
		$this->sendResponse( json_encode($data, JSON_NUMERIC_CHECK) );
		
	}
	
	
	
}

?>
