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

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			// Verbinde zur GUID vom MQTT Server (Splitter)
			$this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
			// RX (vom Server zum Modul) {7F7632D9-FA40-4F38-8DEA-C83CD4325A32}
			// TX (vom Modul zum Server) {043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}

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
		}

		public function Send()
		{
			$this->SendDataToParent(json_encode(Array("DataID" => "{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}")));
		}

		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString);
			IPS_LogMessage("Device RECV", utf8_decode($data->Buffer));
		}

	}