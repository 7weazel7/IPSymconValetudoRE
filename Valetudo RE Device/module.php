<?php
	class ValetudoREDevice extends IPSModule {

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