<?php /** @noinspection AutoloadingIssuesInspection */

declare(strict_types=1);

if (function_exists('IPSUtils_Include')) {
    IPSUtils_Include('IPSLogger.inc.php', 'IPSLibrary::app::core::IPSLogger');
}

require_once __DIR__ . '/../libs/ValetudoRE_MQTT_Helper.php';

	class ValetudoREDevice extends IPSModule {

		use ValetudoREMQTTHelper;

		private const STATUS_INST_IP_IS_EMPTY      = 201; //IP Adresse nicht eingetragen
		private const STATUS_INST_IP_IS_INVALID    = 202; //IP Adresse ist ungültig

		//property names
		private const PROP_HOST                             = 'Host';
		private const PROP_WRITEDEBUGINFORMATIONTOIPSLOGGER = 'WriteDebugInformationToIPSLogger';
		private const PROP_API_MQTT_CONFIG                  = 'ApiMqttConfig';

		private $trace               = false;

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			// Verbinde zur GUID vom MQTT Server (Splitter)
			$this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
			// RX (vom Server zum Modul) {7F7632D9-FA40-4F38-8DEA-C83CD4325A32}
			// TX (vom Modul zum Server) {043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}

			$this->RegisterPropertyString(self::PROP_HOST, '');
			$this->RegisterPropertyString(self::PROP_API_MQTT_CONFIG, 'mqtt_config');
			$this->RegisterPropertyBoolean(self::PROP_WRITEDEBUGINFORMATIONTOIPSLOGGER, false);

			//we will wait until the kernel is ready
			$this->RegisterMessage(0, IPS_KERNELMESSAGE);
		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

			if (IPS_GetKernelRunlevel() !== KR_READY) {
				return;
			}

			if (!$this->HasActiveParent()) {
				return;
			}
			
			//Setze Filter für ReceiveData
			$filter = 'valetudo' . '/' . 'rockrobo';
			if ($this->trace) {
				$this->Logger_Dbg('Filter', $filter);
			}
			$this->SetReceiveDataFilter($filter);
		
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

		public function Send()
		{
			$this->SendDataToParent(json_encode(Array("DataID" => "{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}")));
		}

		public function ReceiveData($JSONString)
		{	
			$this->Logger_Dbg(__FUNCTION__, $JSONString);
			$data = json_decode($JSONString);
			//IPS_LogMessage("Device RECV", utf8_decode($data->Buffer));
		}

		private function SetInstanceStatus(): void
    	{
			$host        = $this->ReadPropertyString(self::PROP_HOST);
			$mqttConfig = strtolower($this->ReadPropertyString(self::PROP_API_MQTT_CONFIG));

			//IP Prüfen
			if ($host === '') {
				$this->SetStatus(self::STATUS_INST_IP_IS_EMPTY);
				$this->Logger_Dbg(__FUNCTION__, sprintf('Status: %s (%s)', $this->GetStatus(), 'empty Host'));
				return;
			}

			if (!filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
				$this->SetStatus(self::STATUS_INST_IP_IS_INVALID); //IP Adresse ist ungültig
				$this->Logger_Dbg(__FUNCTION__, sprintf('Status: %s (%s)', $this->GetStatus(), 'invalid IP'));
				return;
			}
			
			if (!$this->HasActiveParent()) {
				$this->SetStatus(IS_INACTIVE);
				$this->Logger_Dbg(__FUNCTION__, sprintf('Status: %s (%s)', $this->GetStatus(), 'Parent not active'));
				return;
			}

			//Verbindung prüfen und circuits holen
			$url    = sprintf(
				'http://%s/api/%s',
				$this->ReadPropertyString(self::PROP_HOST),
				$mqttConfig
			);
			$result = $this->readURL($url);

			if ($this->GetStatus() !== IS_ACTIVE) {
				$this->SetStatus(IS_ACTIVE);
				$this->Logger_Dbg(__FUNCTION__, sprintf('Status: %s (%s)', $this->GetStatus(), 'active'));
			}
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