v<?php

/**
 *  Steemnova
 *   by Adam "dotevo" Jordanek 2018
 *
 * For the full copyright and license information, please view the LICENSE
 *
 */

class ShowMarketPlacePage extends AbstractGamePage
{
	public static $requireModule = MODULE_BATTLEHALL;

	function __construct()
    {
		parent::__construct();
	}

	private function checkSlots($USER) {
		$ActualFleets		= FleetFunctions::GetCurrentFleets($USER['id']);
		if (FleetFunctions::GetMaxFleetSlots($USER) <= $ActualFleets)
		{
			return array('result' => -1, 'message' => $LNG['fl_no_slots']);
		}
		return array('result' => 0);
	}

	private function doBuy() {
		global $USER, $PLANET, $reslist, $resource, $LNG, $pricelist;
		$FleetID			= HTTP::_GP('fleetID', 0);
		$shipType		= HTTP::_GP('shipType', "");
		$db = Database::get();

		//Slots checking
		$checkResult = $this->checkSlots($USER);
		if ($checkResult['result'] < 0)
		{
			return $checkResult['message'];
		}
		//Get trade fleet
		$sql = "SELECT * FROM %%FLEETS%% WHERE fleet_id = :fleet_id AND fleet_mess = 2;";
		$fleetResult = $db->select($sql, array(
			':fleet_id' => $FleetID,
		));

		//Error: no results
		if ($db->rowCount() == 0) {
			return $LNG['market_p_msg_not_found'];
		}

		//if not in range 1-3
		if($fleetResult[0]['fleet_wanted_resource'] >= 4 ||
			$fleetResult[0]['fleet_wanted_resource'] <= 0) {
				return $LNG['market_p_msg_wrong_resource_type'];
		}

		//-------------FLEET SIZE CALCULATION---------------
		$fleetResult = $fleetResult[0];
		$amount = $fleetResult['fleet_wanted_resource_amount'];

		$F1capacity = 0;
		$F1type = 0;
		//PRIO for LC
		if($shipType == 1) {
			$F1capacity = $pricelist[202]['capacity'];
			$F1type = 202;
		}
		// PRIO for HC
		else {
			$F1capacity = $pricelist[203]['capacity'];
			$F1type = 203;
		}

		$F1 = min($PLANET[$resource[$F1type]], ceil($amount / $F1capacity));

			//taken
		$amountTMP = $amount - $F1 * $F1capacity;
		// If still fleet needed
		$F2 = 0;
		$F2capacity = 0;
		$F2type = 0;
		if ($amountTMP > 0) {
			//We need HC
			if($shipType == 1) {
				$F2capacity = $pricelist[203]['capacity'];
				$F2type = 203;
			}
			//We need LC
			else{
				$F2capacity = $pricelist[202]['capacity'];
				$F2type = 202;
			}
			$F2 = min($PLANET[$resource[$F2type]], ceil($amountTMP / $F2capacity));
			$amountTMP -= $F2 * $F2capacity;
		}
		//------------------------------------------------------------------------

		if($amountTMP > 0) {
			return $LNG['market_p_msg_more_ships_is_needed'];
		}

		$fleetArrayTMP = array();
		$fleetArrayTMP = array($F1type => $F1, $F2type => $F2);
		$fleetArray = $fleetArrayTMP;
		$fleetArray						= array_filter($fleetArrayTMP);
		$SpeedFactor    	= FleetFunctions::GetGameSpeedFactor();
		$Distance    		= FleetFunctions::GetTargetDistance(array($PLANET['galaxy'], $PLANET['system'], $PLANET['planet']), array($fleetResult['fleet_end_galaxy'], $fleetResult['fleet_end_system'], $fleetResult['fleet_end_planet']));
		$SpeedAllMin		= FleetFunctions::GetFleetMaxSpeed($fleetArray, $USER);
		$Duration			= FleetFunctions::GetMissionDuration(10, $SpeedAllMin, $Distance, $SpeedFactor, $USER);
		$consumption		= FleetFunctions::GetFleetConsumption($fleetArray, $Duration, $Distance, $USER, $SpeedFactor);

		$fleetStartTime		= $Duration + TIMESTAMP;
		$fleetStayTime		= $fleetStartTime;
		$fleetEndTime		= $fleetStayTime + $Duration;

		$met = 0;
		$cry = 0;
		$deu = 0;
		if ($fleetResult['fleet_wanted_resource'] == 1)
			$met = $amount;
		elseif ($fleetResult['fleet_wanted_resource'] == 2)
			$cry = $amount;
		elseif ($fleetResult['fleet_wanted_resource'] == 3)
			$deu = $amount;

		$fleetResource	= array(
			901	=> $met,
			902	=> $cry,
			903	=> $deu,
		);

		if($PLANET[$resource[901]]-$fleetResource[901] < 0 ||
			$PLANET[$resource[902]]	-$fleetResource[902] <0 ||
			$PLANET[$resource[903]]	-$fleetResource[903] - $consumption < 0) {
			return $LNG['market_p_msg_resources_error'];
		}

		$PLANET[$resource[901]]	-= $fleetResource[901];
		$PLANET[$resource[902]]	-= $fleetResource[902];
		$PLANET[$resource[903]]	-= $fleetResource[903] + $consumption;

		FleetFunctions::sendFleet($fleetArray, 3/*Transport*/, $USER['id'], $PLANET['id'], $PLANET['galaxy'],
			$PLANET['system'], $PLANET['planet'], $PLANET['planet_type'], $fleetResult['fleet_owner'], $fleetResult['fleet_start_id'],
			$fleetResult['fleet_start_galaxy'], $fleetResult['fleet_start_system'], $fleetResult['fleet_start_planet'], $fleetResult['fleet_start_type'],
			$fleetResource, $fleetStartTime, $fleetStayTime, $fleetEndTime,0,0,0,0,1);

		/////////////////////////////////////////////////////////////////////////////
		/// SEND/
		$sql = "SELECT * FROM %%USERS%% WHERE id = :userId;";
		$USER_2   = Database::get()->selectSingle($sql, array(
			':userId'       => $fleetResult['fleet_owner']
		));
		$fleetArray						= FleetFunctions::unserialize($fleetResult['fleet_array']);
		$SpeedFactor    	= FleetFunctions::GetGameSpeedFactor();
		$Distance    		= FleetFunctions::GetTargetDistance(array($PLANET['galaxy'], $PLANET['system'], $PLANET['planet']), array($fleetResult['fleet_end_galaxy'], $fleetResult['fleet_end_system'], $fleetResult['fleet_end_planet']));
		$SpeedAllMin		= FleetFunctions::GetFleetMaxSpeed($fleetArray, $USER_2);
		$Duration			= FleetFunctions::GetMissionDuration(10, $SpeedAllMin, $Distance, $SpeedFactor, $USER_2);
		//$consumption		= FleetFunctions::GetFleetConsumption($fleetArray, $Duration, $Distance, $fleetResult['fleet_owner'], $SpeedFactor);

		$fleetStartTime		= $Duration + TIMESTAMP;
		$fleetStayTime		= $fleetStartTime;
		$fleetEndTime		= $fleetStayTime + $Duration;


		$params = array(
			':fleetID' => $FleetID,
			':fleet_target_owner' => $USER['id'],
			':fleet_end_id' => $PLANET['id'],
			':fleet_end_planet' => $PLANET['planet'],
			':fleet_end_system' => $PLANET['system'],
			':fleet_end_galaxy' => $PLANET['galaxy'],
			':fleet_start_time' => $fleetStartTime,
			':fleet_end_stay' => $fleetStayTime,
			':fleet_end_time' => $fleetEndTime,
			':fleet_mission' => 3,
			':fleet_no_m_return' => 1,
			':fleet_mess'=> 0,
		);
		$sql = "UPDATE %%FLEETS%% SET `fleet_no_m_return` = :fleet_no_m_return, `fleet_end_id` = :fleet_end_id,`fleet_target_owner` = :fleet_target_owner, `fleet_mess` = :fleet_mess, `fleet_mission` = :fleet_mission, `fleet_end_stay` = :fleet_end_stay ,`fleet_end_time` = :fleet_end_time ,`fleet_start_time` = :fleet_start_time , `fleet_end_planet` = :fleet_end_planet, `fleet_end_system` = :fleet_end_system, `fleet_end_galaxy` = :fleet_end_galaxy WHERE fleet_id = :fleetID;";
		$fleetResult = $db->update($sql, $params);
		$sql = "UPDATE %%LOG_FLEETS%% SET `fleet_no_m_return` = :fleet_no_m_return, `fleet_end_id` = :fleet_end_id,`fleet_target_owner` = :fleet_target_owner, `fleet_mess` = :fleet_mess, `fleet_mission` = :fleet_mission, `fleet_end_stay` = :fleet_end_stay ,`fleet_end_time` = :fleet_end_time ,`fleet_start_time` = :fleet_start_time , `fleet_end_planet` = :fleet_end_planet, `fleet_end_system` = :fleet_end_system, `fleet_end_galaxy` = :fleet_end_galaxy WHERE fleet_id = :fleetID;";
		$fleetResult = $db->update($sql, $params);
		$sql	= 'UPDATE %%FLEETS_EVENT%% SET  `time` = :endTime WHERE fleetID	= :fleetId;';
		$db->update($sql, array(
			':fleetId'	=> $FleetID,
			':endTime'	=> $fleetStartTime
		));
		$LC = 0;
		$HC = 0;
		if(array_key_exists(202,$fleetArrayTMP))
			$LC = $fleetArrayTMP[202];
		if(array_key_exists(203,$fleetArrayTMP))
			$HC = $fleetArrayTMP[203];
		return sprintf($LNG['market_p_msg_sent'], $LC, $HC);
	}


	public function show()
	{
		global $USER, $PLANET, $reslist, $resource, $LNG;

		$FleetID			= HTTP::_GP('fleetID', 0);
		$GetAction		= HTTP::_GP('action', "");
		$shipType		= HTTP::_GP('shipType', "");

		$message = "";
		$db = Database::get();

		if($GetAction == "buy") {
			$message = $this->doBuy();
		}

		$sql = "SELECT * FROM %%FLEETS%%, %%USERS%% WHERE fleet_mission = 16 AND fleet_mess = 2 AND fleet_owner = %%USERS%%.id ORDER BY fleet_end_time ASC;";
		$fleetResult = $db->select($sql, array(
		));

		$activeFleetSlots	= $db->rowCount();

		$FlyingFleetList	= array();

		foreach ($fleetResult as $fleetsRow)
		{
			$resourceN = " ";
			//TODO TRANSLATION
			switch($fleetsRow['fleet_wanted_resource']) {
				case 1:
					$resourceN = $LNG['tech'][901];
					break;
				case 2:
					$resourceN = $LNG['tech'][902];
					break;
				case 3:
					$resourceN = $LNG['tech'][903];
					break;
				default:
					break;
			}

			$SpeedFactor    	= FleetFunctions::GetGameSpeedFactor();
			//FROM
			$FROM_fleet =  FleetFunctions::unserialize($fleetsRow['fleet_array']);
			$FROM_Distance    		= FleetFunctions::GetTargetDistance(array($PLANET['galaxy'], $PLANET['system'], $PLANET['planet']), array($fleetsRow['fleet_end_galaxy'], $fleetsRow['fleet_end_system'], $fleetsRow['fleet_end_planet']));
			$FROM_SpeedAllMin		= FleetFunctions::GetFleetMaxSpeed($FROM_fleet, $fleetsRow);
			$FROM_Duration			= FleetFunctions::GetMissionDuration(10, $FROM_SpeedAllMin, $FROM_Distance, $SpeedFactor, $fleetsRow);

			//TO
			$TO_Distance    		= FleetFunctions::GetTargetDistance(array($PLANET['galaxy'], $PLANET['system'], $PLANET['planet']), array($fleetsRow['fleet_start_galaxy'], $fleetsRow['fleet_start_system'], $fleetsRow['fleet_start_planet']));
			$TO_LC_SPEED		= FleetFunctions::GetFleetMaxSpeed(array(202 =>1), $USER);
			$TO_LC_DUR			= FleetFunctions::GetMissionDuration(10, $TO_LC_SPEED, $TO_Distance, $SpeedFactor, $USER);
			$TO_HC_SPEED		= FleetFunctions::GetFleetMaxSpeed(array(203 =>1), $USER);
			$TO_HC_DUR			= FleetFunctions::GetMissionDuration(10, $TO_HC_SPEED, $TO_Distance, $SpeedFactor, $USER);



			$FlyingFleetList[]	= array(
				'id'			=> $fleetsRow['fleet_id'],
				'username'			=> $fleetsRow['username'],

				'fleet_resource_metal'		=> $fleetsRow['fleet_resource_metal'],
				'fleet_resource_crystal'			=> $fleetsRow['fleet_resource_crystal'],
				'fleet_resource_deuterium'			=> $fleetsRow['fleet_resource_deuterium'],

				'total' => $fleetsRow['fleet_resource_metal'] + $fleetsRow['fleet_resource_crystal'] + $fleetsRow['fleet_resource_deuterium'],

				'fleet_wanted_resource'	=> $resourceN,
				'fleet_wanted_resource_amount'	=> $fleetsRow['fleet_wanted_resource_amount'],

				'end'	=> $fleetsRow['fleet_end_stay'] - TIMESTAMP,

				'from_duration' => $FROM_Duration,
				'to_lc_duration' => $TO_LC_DUR,
				'to_hc_duration' => $TO_HC_DUR,
				//'distance' => $FROM_Duration//$Distance    		= FleetFunctions::GetTargetDistance(array($PLANET['galaxy'], $PLANET['system'], $PLANET['planet']), array($fleetsRow['fleet_end_galaxy'], $fleetsRow['fleet_end_system'], $fleetsRow['fleet_end_planet'])),
			);
		}


		$this->assign(array(
			'message' => $message,
			'FlyingFleetList'		=> $FlyingFleetList,
		));
		$this->tplObj->loadscript('marketplace.js');
		$this->display('page.marketPlace.default.tpl');
	}
}