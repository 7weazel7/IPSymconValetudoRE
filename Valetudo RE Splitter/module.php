<?php
	class ValetudoRESplitter extends IPSModule {

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RequireParent("{E2578347-0E4E-0E2C-D636-6616717C1926}");
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

		public function ForwardData($JSONString)
		{
			$data = json_decode($JSONString);
			IPS_LogMessage("Splitter FRWD", utf8_decode($data->Buffer));

			$this->SendDataToParent(json_encode(Array("DataID" => "{AA62A632-C1C9-5ACB-D681-D04C02B80C00}", "Buffer" => $data->Buffer)));

			return "String data for device instance!";
		}

		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString);
			IPS_LogMessage("Splitter RECV", utf8_decode($data->Buffer));

			$this->SendDataToChildren(json_encode(Array("DataID" => "{015AC80F-5C9F-0FFD-BD4F-6AD277B0C01B}", "Buffer" => $data->Buffer)));
		}

	}