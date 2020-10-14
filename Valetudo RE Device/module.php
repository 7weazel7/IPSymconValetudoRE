<?php /** @noinspection AutoloadingIssuesInspection */

declare(strict_types=1);

if (function_exists('IPSUtils_Include')) {
    IPSUtils_Include('IPSLogger.inc.php', 'IPSLibrary::app::core::IPSLogger');
}

require_once __DIR__ . '/../libs/ValetudoRE_MQTT_Helper.php';

	class ValetudoREDevice extends IPSModule {

		use ValetudoREMQTTHelper;

		// status values
		private const STATUS_INST_IP_IS_EMPTY                = 201; // IP Adresse nicht eingetragen
		private const STATUS_INST_IP_IS_INVALID              = 202; // IP Adresse ist ungültig
		private const STATUS_INST_MQTT_NOT_ENABLED           = 203; // MQTT ist nicht aktiviert
		private const STATUS_INST_MQTT_TOPICPREFIX_NOT_SET   = 204; // MQTT topicPrefix nicht gesetzt
		private const STATUS_INST_MQTT_IDENTIFIER_NOT_SET    = 205; // MQTT identifier nicht gesetzt

		// property names
		private const PROP_HOST                             = 'Host';
		private const PROP_WRITEDEBUGINFORMATIONTOIPSLOGGER = 'WriteDebugInformationToIPSLogger';

		// attribute names
		private const ATTR_ROOMLIST          			    = 'RoomList';
		private const ATTR_API_MQTT_CONFIG                  = 'ApiMqttConfig';
		private const ATTR_MQTT_TOPICPREFIX                 = 'MqttTopicPrefix';
		private const ATTR_MQTT_IDENTIFIER	                = 'MqttIdentifier';
		private const ATTR_MQTT_TOPIC						= 'MqttTopic';
		
		// form element names
		private const FORM_LIST_ROOMLIST 				    = 'RoomList';

		private $trace         						        = true;

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			// Verbinde zur GUID vom MQTT Server (Splitter)
			$this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
			// RX (vom Server zum Modul) {7F7632D9-FA40-4F38-8DEA-C83CD4325A32}
			// TX (vom Modul zum Server) {043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}

			$this->RegisterPropertyString(self::PROP_HOST, '');
			$this->RegisterPropertyBoolean(self::PROP_WRITEDEBUGINFORMATIONTOIPSLOGGER, true);

			$this->RegisterAttributeString(self::ATTR_ROOMLIST, json_encode([], JSON_THROW_ON_ERROR));
			$this->RegisterAttributeString(self::ATTR_API_MQTT_CONFIG, 'mqtt_config');
			$this->RegisterAttributeString(self::ATTR_MQTT_TOPICPREFIX, 'valetudo');
			$this->RegisterAttributeString(self::ATTR_MQTT_IDENTIFIER, 'rockrobo');
			$this->RegisterAttributeString(self::ATTR_MQTT_TOPIC, 'valetudo/rockrobo');

			//we will wait until the kernel is ready
			$this->RegisterMessage(0, IPS_KERNELMESSAGE);
		}

		public function Destroy()
		{
			if (IPS_GetKernelRunlevel() !== KR_READY) {
				return parent::Destroy();
			}
	
			$removeVarProfiles = false;
			$ModuleInstancesAR = IPS_GetInstanceListByModuleID('{F6307FB2-D3F4-655E-FCC8-37836FA866A1}');
			if (@array_key_exists('0', $ModuleInstancesAR) === false) {
				$removeVarProfiles = true;
			} else {
				if ((@array_key_exists('1', $ModuleInstancesAR) === false) && ($ModuleInstancesAR[0] === $this->InstanceID)) {
					$removeVarProfiles = true;
				}
			}
			if ($removeVarProfiles === true) {
				$VarProfilesAR = array('HELIOS.Bypass', 'HELIOS.CO2VOC.ppm', 'HELIOS.Days', 'HELIOS.DefrostState', 'HELIOS.ErrorNoYes', 'HELIOS.FanLevel', 'HELIOS.FanSpeedRPM', 'HELIOS.FanLevelPercent', 'HELIOS.Filter.Months', 'HELIOS.HeatRecoveryEfficiency', 'HELIOS.Mode', 'HELIOS.ModeDuration', 'HELIOS.ModeIntervalTime', 'HELIOS.ModeActivationPeriod', 'HELIOS.OperatingModeRemainingTime', 'HELIOS.ModeVacationProgram', 'HELIOS.OperatingHours', 'HELIOS.OperatingMode', 'HELIOS.PreAfterheater.Perc.Float', 'HELIOS.PreAfterheater.Perc.Int', 'HELIOS.PreAfterheaterState', 'HELIOS.RelativeHumidity', 'HELIOS.ResetAction', 'HELIOS.StateSwitch', 'HELIOS.Temperature.Indoor', 'HELIOS.Temperature.Outdoor', 'HELIOS.VOCCO2HUMControl', 'HELIOS.WeekProgram');
				foreach ($VarProfilesAR as $VarProfileNameDEL) {
					@IPS_DeleteVariableProfile($VarProfileNameDEL);
				}
			}
	
			parent::Destroy();
	
			return true;

		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

			// Register Kernel Messages
			$this->RegisterMessage(0, IPS_KERNELSTARTED);
			if (IPS_GetKernelRunlevel() !== KR_READY) {
				return;
			}

			if (!$this->HasActiveParent()) {
				return;
			}
			
			$this->checkConnection();

			//Setze Filter für ReceiveData
			$this->WriteAttributeString(self::ATTR_MQTT_TOPIC, $this->ReadAttributeString(self::ATTR_MQTT_TOPICPREFIX) . '/' . $this->ReadAttributeString(self::ATTR_MQTT_IDENTIFIER));
			$filter = strtolower($this->ReadAttributeString(self::ATTR_MQTT_TOPIC));
			if ($this->trace) {
				$this->Logger_Dbg('Filter', $filter);
			}
			$this->SetReceiveDataFilter('.*' . $filter . '.*');
		
			$this->SetSummary(
				sprintf(
					'%s',
					$this->ReadPropertyString(self::PROP_HOST)
				)
			);
			
			//we will set the instance status when the parent status changes
			$instIDMQTTServer = $this->GetParent($this->InstanceID);
			if ($this->trace) {
				$this->Logger_Dbg('MQTTServer', '#' . $instIDMQTTServer);
			}

			if ($instIDMQTTServer > 0) {
				$this->RegisterMessage($instIDMQTTServer, IM_CHANGESTATUS);
				$instIDMQTTServerSocket = $this->GetParent($instIDMQTTServer);
				if ($instIDMQTTServerSocket > 0) {
					$this->RegisterMessage($instIDMQTTServerSocket, IM_CHANGESTATUS);
				}
			}	
		}

		public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
		{
			$this->Logger_Dbg(
				__FUNCTION__,
				sprintf('SenderID: %s, Message: %s, Data: %s', $SenderID, $Message, json_encode($Data, JSON_THROW_ON_ERROR))
			);
	
			parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
	
			switch ($Message) {
				case IPS_KERNELMESSAGE: //the kernel status has changed
					if ($Data[0] === KR_READY) {
						$this->ApplyChanges();
					}
					break;
	
				case IM_CHANGESTATUS: // the parent status has changed
					$this->ApplyChanges();
					break;
			}
		}

		public function RequestAction($Ident, $Value)
		{
			$this->Logger_Dbg(__FUNCTION__, sprintf('Ident: %s, Value: %s', $Ident, json_encode($Value, JSON_THROW_ON_ERROR)));

			if (!$this->HasActiveParent()) {
				$this->checkConnection();
				$this->Logger_Dbg(__FUNCTION__, 'No active parent - ignored');
				return;
			}

			switch ($Ident) {
				case 'btnReadRoomConfiguration':
					$ret = $this->ReadRoomConfiguration();
					if ($ret === null) {
						$this->MsgBox('Fehler');
					} else {
						$this->MsgBox(count($ret) . ' Einträge gefunden');
					}
					return;
			}
		}

		public function Send()
		{
			$this->SendDataToParent(json_encode(Array("DataID" => "{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}")));
		}

		public function ReceiveData($JSONString)
		{	
			$this->Logger_Dbg(__FUNCTION__, $JSONString);

			//prüfen, ob MQTT_TOPIC vorhanden
			$mqttTopic = strtolower($this->ReadAttributeString(self::ATTR_MQTT_TOPIC));
			if (empty($mqttTopic)) {
				return;
			}
			//prüfen, ob Buffer gefüllt ist und Topic und Payload vorhanden sind
			$Buffer = json_decode($JSONString, false, 512, JSON_THROW_ON_ERROR);
			if (($Buffer === false) || ($Buffer === null) || !property_exists($Buffer, 'Topic') || !property_exists($Buffer, 'Payload')) {
				return;
			}

			//prüfen, ob Payload gefüllt ist
			$this->Logger_Dbg('MQTT Topic/Payload', sprintf('Topic: %s -- Payload: %s', $Buffer->Topic, $Buffer->Payload));

			$Payload = json_decode($Buffer->Payload, true);

			if ($Payload === null) {
				$this->Logger_Dbg(__FUNCTION__, 'Payload is not set: ' . $Buffer->Payload);
				return;
			}

			if($Buffer->Topic === $mqttTopic . '/destinations') {
				foreach($Payload["rooms"] as $room) {
					$this->Logger_Dbg('MQTT Topic - destinations', sprintf('Payload room: %s', $room));
				}
			}
		}

		private function SetInstanceStatus(): void
    	{
			// Eingetragenen Host auslesen
			$host = gethostbyname($this->ReadPropertyString(self::PROP_HOST));

			// Überprüfen ob es einen übergeordneten MQTT Splitter gibt
			if (!$this->HasActiveParent()) {
				$this->SetStatus(IS_INACTIVE);
				$this->Logger_Dbg(__FUNCTION__, sprintf('Status: %s (%s)', $this->GetStatus(), $this->Translate("Parent not active")));
				return;
			}

			// Überprüfen ob Host/IP eingetragen wurde
			if ($host === '') {
				$this->SetStatus(self::STATUS_INST_IP_IS_EMPTY);
				$this->Logger_Dbg(__FUNCTION__, sprintf('Status: %s (%s)', $this->GetStatus(), $this->Translate("Empty host")));
				return;
			}

			// Überprüfen ob Host/IP einem Saugroboter entpsricht
			if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				$this->SetStatus(self::STATUS_INST_IP_IS_INVALID); // IP Adresse ist ungültig
				$this->Logger_Dbg(__FUNCTION__, sprintf('Status: %s (%s)', $this->GetStatus(), $this->Translate("Invalid IP. Please check DNS entry")));
				return;
			}

			// MQTT-Config abholen und setzen
			list($state, $topicPrefix, $identifier) = $this->getMQTTConfig();
			if ($this->trace) {
				$this->Logger_Dbg(__FUNCTION__, sprintf('list: %s, %s, %s', $state, $topicPrefix, $identifier));
			}

			// Prüfen ob MQTT aktiviert ist
			if(!$state) {
				$this->SetStatus(self::STATUS_INST_MQTT_NOT_ENABLED); // MQTT nicht aktiviert
				$this->Logger_Dbg(__FUNCTION__, sprintf('Status: %s (%s)', $this->GetStatus(), $this->Translate("MQTT not enabled")));
				return;
			}

			// Prüfen ob topicPrefix gesetzt ist
			if(!ctype_alpha($topicPrefix)) {
				$this->SetStatus(self::STATUS_INST_MQTT_TOPICPREFIX_NOT_SET); // topicPrefix nicht gesetzt
				$this->Logger_Dbg(__FUNCTION__, sprintf('Status: %s (%s)', $this->GetStatus(), $this->Translate("topicPrefix not set")));
				return;
			} else {
				$this->WriteAttributeString(self::ATTR_MQTT_TOPICPREFIX, $topicPrefix);
			}

			// Prüfen ob identifier gesetzt ist
			if(!ctype_alpha($identifier)) {
				$this->SetStatus(self::STATUS_INST_MQTT_IDENTIFIER_NOT_SET); // identifier nicht gesetzt
				$this->Logger_Dbg(__FUNCTION__, sprintf('Status: %s (%s)', $this->GetStatus(), $this->Translate("identifier not set")));
				return;
			} else {
				$this->WriteAttributeString(self::ATTR_MQTT_IDENTIFIER, $identifier);
			}

			// Instanz aktiv setzen
			if ($this->GetStatus() !== IS_ACTIVE) {
				$this->SetStatus(IS_ACTIVE);
				$this->Logger_Dbg(__FUNCTION__, sprintf('Status: %s (%s)', $this->GetStatus(), $this->Translate("Active")));
			}
		}

		private function ReadRoomConfiguration(): ?array
		{
			$rooms = strtolower($this->ReadPropertyString(self::PROP_CIRCUITNAME));
			$url         = sprintf(
				'http://%s:%s/data/%s/?def&verbose&exact&write',
				$this->ReadPropertyString(self::PROP_HOST),
				$this->ReadPropertyString(self::PROP_PORT),
				$circuitName
			);
			$result      = $this->readURL($url);
	
			//alle Felder des Ergebnisses auswerten
			if (!array_key_exists($circuitName, $result)) {
				trigger_error(sprintf('configuration of topic \'%s\' not found (URL: %s)', $circuitName, $url));
				return null;
			}
			$configurationMessages = $result[$circuitName]['messages'];
			if (count($configurationMessages) === 1) {
				trigger_error('Unexpected count of messages: ' . count($configurationMessages));
				return null;
			}
	
			//ebusd Konfiguration aufbereiten und als Attribut speichern
			$configurationMessages = $this->selectAndPrepareConfigurationMessages($configurationMessages);
			$this->WriteAttributeString(self::ATTR_EBUSD_CONFIGURATION_MESSAGES, json_encode($configurationMessages, JSON_THROW_ON_ERROR));
	
			//Ausgabeliste aufbereiten und als Attribut speichern
			$variableList = $this->getVariableList(json_encode($configurationMessages, JSON_THROW_ON_ERROR));
			$this->UpdateFormField(self::FORM_LIST_VARIABLELIST, 'values', json_encode($variableList, JSON_THROW_ON_ERROR, 3));
			$this->SaveVariableList($variableList);
	
			return $configurationMessages;
		}
		private function checkConnection(): void
		{
			$this->SetInstanceStatus();
	
			if ($this->trace) {
				$this->Logger_Dbg(__FUNCTION__, 'InstanceStatus: ' . $this->GetStatus());
			}
		}

		private function getMQTTConfig(): ?array
		{	
			$mqtt_config = $this->readURL($this->getApiURL($this->ReadAttributeString(self::ATTR_API_MQTT_CONFIG)));
			return array ($mqtt_config['enabled'], $mqtt_config['topicPrefix'], $mqtt_config['identifier']);
		}

		private function getApiURL(string $section): string
		{
			$apiURL 	= sprintf(
								'http://%s/api/%s',
								$this->ReadPropertyString(self::PROP_HOST),
								$section
						);
			return $apiURL;
		}

		private function readURL(string $url): ?array
		{
			$this->Logger_Dbg(__FUNCTION__, 'URL: ' . $url);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 5); //max 5 Sekunden für Verbindungsaufbau
	
			$result_json = curl_exec($ch);
			curl_close($ch);
	
			if ($result_json === false) {
				$this->Logger_Dbg('CURL return', sprintf('empty result for %s', $url));
				return null;
			}
			return json_decode($result_json, true, 512, JSON_THROW_ON_ERROR);
		}

		private function Logger_Dbg(string $message, string $data): void
		{
			$this->SendDebug($message, $data, 0);
			if (function_exists('IPSLogger_Dbg') && $this->ReadPropertyBoolean(self::PROP_WRITEDEBUGINFORMATIONTOIPSLOGGER)) {
				IPSLogger_Dbg(__CLASS__ . '.' . IPS_GetObject($this->InstanceID)['ObjectName'] . '.' . $message, $data);
			}
		}
	}